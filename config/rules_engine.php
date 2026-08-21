<?php
/**
 * Rule-Based System Engine
 * Laboratorium Komputer v2
 *
 * Implements IF-THEN rules for automatic schedule management.
 * Rules are stored in the `rules` table and evaluated here.
 */

/**
 * Check if a rule is active
 */
function is_rule_active($kode_rule) {
    global $koneksi;
    $r = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT status FROM rules WHERE kode_rule='".sanitize($kode_rule)."'"));
    return $r && $r['status'] == 'aktif';
}

/**
 * Get rule by code
 */
function get_rule($kode_rule) {
    global $koneksi;
    return mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT * FROM rules WHERE kode_rule='".sanitize($kode_rule)."'"));
}

/**
 * Auto-update maintenance statuses based on date/time
 */
function auto_update_maintenance_status() {
    global $koneksi;
    $check = mysqli_query($koneksi, "SHOW TABLES LIKE 'maintenance'");
    if (mysqli_num_rows($check) == 0) return;

    $today = date('Y-m-d');
    $now = date('H:i:s');

    mysqli_query($koneksi, "UPDATE maintenance SET status='berlangsung'
        WHERE tanggal_mulai='$today' AND jam_mulai<='$now' AND jam_selesai>'$now'
        AND status='dijadwalkan'");
    mysqli_query($koneksi, "UPDATE maintenance SET status='selesai'
        WHERE tanggal_mulai<'$today' AND status IN ('dijadwalkan','berlangsung')");
    mysqli_query($koneksi, "UPDATE maintenance SET status='selesai'
        WHERE tanggal_mulai='$today' AND jam_selesai<='$now' AND status='berlangsung'");
}

/**
 * Check if a lab is under maintenance on a given date (optional time overlap)
 * Returns maintenance record if active, null otherwise
 */
function check_lab_maintenance($lab_id, $tanggal, $jam_mulai = null, $jam_selesai = null) {
    global $koneksi;
    $lab_id = intval($lab_id);
    $tanggal = sanitize($tanggal);

    auto_update_maintenance_status();

    $check = mysqli_query($koneksi, "SHOW TABLES LIKE 'maintenance'");
    if (mysqli_num_rows($check) == 0) return null;

    $time_filter = '';
    if ($jam_mulai && $jam_selesai) {
        $jam_mulai = sanitize($jam_mulai);
        $jam_selesai = sanitize($jam_selesai);
        $time_filter = " AND (m.jam_mulai < '$jam_selesai' AND m.jam_selesai > '$jam_mulai')";
    }

    $sql = "SELECT m.*, l.nama_lab FROM maintenance m
            LEFT JOIN laboratorium l ON m.lab_id = l.id
            WHERE m.lab_id = $lab_id
            AND m.tanggal_mulai = '$tanggal'
            AND m.status IN ('dijadwalkan', 'berlangsung')
            $time_filter
            ORDER BY m.jam_mulai ASC
            LIMIT 1";
    $r = mysqli_fetch_assoc(mysqli_query($koneksi, $sql));
    return $r ?: null;
}

/**
 * Get all labs currently under maintenance (today or future)
 */
function get_maintenance_labs($tanggal = null) {
    global $koneksi;
    $check = mysqli_query($koneksi, "SHOW TABLES LIKE 'maintenance'");
    if (mysqli_num_rows($check) == 0) return [];

    auto_update_maintenance_status();

    $where = "m.status IN ('dijadwalkan','berlangsung')";
    if ($tanggal) {
        $tanggal = sanitize($tanggal);
        $where .= " AND m.tanggal_mulai = '$tanggal'";
    }
    $sql = "SELECT m.*, l.nama_lab FROM maintenance m
            LEFT JOIN laboratorium l ON m.lab_id = l.id
            WHERE $where ORDER BY m.tanggal_mulai ASC";
    $result = mysqli_query($koneksi, $sql);
    $labs = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $labs[] = $row;
    }
    return $labs;
}

/**
 * RULE R001 & R002: Check schedule conflict for a lab on a specific date/time
 * Returns array of conflicting peminjaman records
 */
function check_schedule_conflict($lab_id, $tanggal, $jam_mulai, $jam_selesai, $exclude_id = 0) {
    global $koneksi;
    $lab_id = intval($lab_id);
    $exclude_id = intval($exclude_id);
    $tanggal = sanitize($tanggal);
    $jam_mulai = sanitize($jam_mulai);
    $jam_selesai = sanitize($jam_selesai);

    $sql = "SELECT p.*, u.nama as nama_guru FROM peminjaman p
            LEFT JOIN users u ON p.guru_id = u.id
            WHERE p.lab_id = $lab_id
            AND p.tanggal = '$tanggal'
            AND p.status IN ('diterima','berlangsung')
            AND p.id != $exclude_id
            AND (
                (p.jam_mulai < '$jam_selesai' AND p.jam_selesai > '$jam_mulai')
            )";
    $result = mysqli_query($koneksi, $sql);
    $conflicts = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $conflicts[] = $row;
    }
    return $conflicts;
}

/**
 * RULE R004: Get available time slots for a lab on a specific date
 * Returns array of free time ranges
 */
function get_available_slots($lab_id, $tanggal, $duration_hours = 2) {
    global $koneksi;
    $lab_id = intval($lab_id);
    $tanggal = sanitize($tanggal);

    // Get all booked slots for this lab/date
    $sql = "SELECT jam_mulai, jam_selesai FROM peminjaman
            WHERE lab_id = $lab_id AND tanggal = '$tanggal'
            AND status IN ('diterima','berlangsung')
            ORDER BY jam_mulai";
    $result = mysqli_query($koneksi, $sql);
    $booked = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $booked[] = $row;
    }

    // Include maintenance windows as blocked slots
    $maint_check = mysqli_query($koneksi, "SHOW TABLES LIKE 'maintenance'");
    if (mysqli_num_rows($maint_check) > 0) {
        auto_update_maintenance_status();
        $maint = mysqli_query($koneksi, "SELECT jam_mulai, jam_selesai FROM maintenance
            WHERE lab_id = $lab_id AND tanggal_mulai = '$tanggal'
            AND status IN ('dijadwalkan','berlangsung')");
        while ($row = mysqli_fetch_assoc($maint)) {
            $booked[] = $row;
        }
    }

    // Lab operating hours: 07:00 - 17:00
    $slots = [];
    $start_hour = 7;
    $end_hour = 17;

    for ($h = $start_hour; $h < $end_hour - ($duration_hours - 1); $h++) {
        $slot_start = sprintf('%02d:00:00', $h);
        $slot_end = sprintf('%02d:00:00', $h + $duration_hours);

        $is_free = true;
        foreach ($booked as $b) {
            if ($b['jam_mulai'] < $slot_end && $b['jam_selesai'] > $slot_start) {
                $is_free = false;
                break;
            }
        }
        if ($is_free) {
            $slots[] = [
                'jam_mulai' => substr($slot_start, 0, 5),
                'jam_selesai' => substr($slot_end, 0, 5),
            ];
        }
    }
    return $slots;
}

/**
 * Get available slots across ALL labs for a given date
 * Returns array with lab info and free slots
 */
function get_all_available_slots($tanggal, $duration_hours = 2) {
    global $koneksi;

    // Get labs under maintenance on this date
    $maint_ids = [];
    $check = mysqli_query($koneksi, "SHOW TABLES LIKE 'maintenance'");
    if (mysqli_num_rows($check) > 0) {
        $tanggal_esc = sanitize($tanggal);
        $maint = mysqli_query($koneksi, "SELECT lab_id FROM maintenance WHERE tanggal_mulai='$tanggal_esc' AND status IN ('dijadwalkan','berlangsung')");
        while ($m = mysqli_fetch_assoc($maint)) {
            $maint_ids[] = $m['lab_id'];
        }
    }

    $exclude = count($maint_ids) > 0 ? " AND l.id NOT IN (" . implode(',', $maint_ids) . ")" : '';
    $labs = mysqli_query($koneksi, "SELECT id, nama_lab FROM laboratorium WHERE status='aktif'" . $exclude);
    $all_slots = [];
    while ($lab = mysqli_fetch_assoc($labs)) {
        $slots = get_available_slots($lab['id'], $tanggal, $duration_hours);
        if (!empty($slots)) {
            $all_slots[] = [
                'lab_id' => $lab['id'],
                'nama_lab' => $lab['nama_lab'],
                'slots' => $slots
            ];
        }
    }
    return $all_slots;
}

/**
 * RULE R001 & R002: Process a new pengajuan (auto approve/reject)
 * This is the main rule evaluation function called when guru submits a request.
 *
 * @param int $peminjaman_id
 * @return array ['success' => bool, 'message' => string, 'status' => string, 'rekomendasi' => array]
 */
function process_pengajuan($peminjaman_id) {
    global $koneksi;
    $peminjaman_id = intval($peminjaman_id);

    // Fetch the pengajuan
    $p = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT * FROM peminjaman WHERE id = $peminjaman_id"));
    if (!$p) {
        return ['success' => false, 'message' => 'Data pengajuan tidak ditemukan.', 'status' => 'error'];
    }

    // Calculate duration in hours for slot recommendations
    $start = strtotime($p['jam_mulai']);
    $end = strtotime($p['jam_selesai']);
    $duration_hours = max(1, round(($end - $start) / 3600));

    // ===== RULE R007: IF lab sedang maintenance THEN pengajuan ditolak =====
    $maintenance = check_lab_maintenance($p['lab_id'], $p['tanggal'], $p['jam_mulai'], $p['jam_selesai']);
    if ($maintenance) {
        $alasan = 'Laboratorium ' . $maintenance['nama_lab'] . ' sedang dalam jadwal maintenance pada tanggal ' . format_tanggal($p['tanggal']) . ' (' . substr($maintenance['jam_mulai'],0,5) . ' - ' . substr($maintenance['jam_selesai'],0,5) . ')';
        $alasan_esc = sanitize($alasan);
        $rek_esc = sanitize($maintenance['deskripsi'] ?? '');
        mysqli_query($koneksi, "UPDATE peminjaman SET status='ditolak', alasan_penolakan='$alasan_esc', rekomendasi_jadwal='$rek_esc' WHERE id=$peminjaman_id");

        // Get available slots from OTHER labs
        $rekomendasi = get_all_available_slots($p['tanggal'], $duration_hours);

        return [
            'success' => false,
            'message' => 'Pengajuan DITOLAK. ' . $alasan,
            'status' => 'ditolak',
            'rekomendasi' => $rekomendasi
        ];
    }

    // ===== RULE R001: IF jadwal bentrok THEN pengajuan ditolak =====
    if (is_rule_active('R001')) {
        $conflicts = check_schedule_conflict($p['lab_id'], $p['tanggal'], $p['jam_mulai'], $p['jam_selesai'], $p['id']);

        if (!empty($conflicts)) {
            // Bentrok ditemukan - tolak pengajuan
            $conflict_info = [];
            foreach ($conflicts as $c) {
                $conflict_info[] = $c['nama_guru'] . ' (' . substr($c['jam_mulai'],0,5) . '-' . substr($c['jam_selesai'],0,5) . ')';
            }
            $alasan = 'Jadwal bentrok dengan: ' . implode(', ', $conflict_info);

            // ===== RULE R004: IF jadwal bentrok THEN tampilkan rekomendasi =====
            $rekomendasi = '';
            if (is_rule_active('R004')) {
                $all_slots = get_all_available_slots($p['tanggal'], $duration_hours);
                if (!empty($all_slots)) {
                    $rek_parts = [];
                    foreach ($all_slots as $lab_slots) {
                        foreach ($lab_slots['slots'] as $slot) {
                            $rek_parts[] = $lab_slots['nama_lab'] . ': ' . $slot['jam_mulai'] . '-' . $slot['jam_selesai'];
                        }
                    }
                    $rekomendasi = implode(' | ', array_slice($rek_parts, 0, 10));
                }
            }

            // Update status to ditolak
            $alasan_esc = sanitize($alasan);
            $rek_esc = sanitize($rekomendasi);
            mysqli_query($koneksi, "UPDATE peminjaman SET status='ditolak', alasan_penolakan='$alasan_esc', rekomendasi_jadwal='$rek_esc' WHERE id=$peminjaman_id");

            return [
                'success' => false,
                'message' => 'Pengajuan DITOLAK. ' . $alasan,
                'status' => 'ditolak',
                'is_bentrok' => true,
                'alasan' => $alasan,
                'conflicts' => $conflicts,
                'rekomendasi' => $rekomendasi ? get_all_available_slots($p['tanggal'], $duration_hours) : []
            ];
        }
    }

    // ===== RULE R002: IF jadwal tersedia THEN pengajuan diterima =====
    if (is_rule_active('R002')) {
        mysqli_query($koneksi, "UPDATE peminjaman SET status='diterima' WHERE id=$peminjaman_id");
        return [
            'success' => true,
            'message' => 'Pengajuan DITERIMA secara otomatis. Jadwal tersedia.',
            'status' => 'diterima',
            'rekomendasi' => []
        ];
    }

    // Default: keep as pending if R002 is inactive
    return [
        'success' => true,
        'message' => 'Pengajuan tersimpan, menunggu proses.',
        'status' => 'pending',
        'rekomendasi' => []
    ];
}

/**
 * RULE R003, R005: Auto-update statuses based on time
 * Check-out harus dilakukan manual oleh guru (tidak auto).
 */
function auto_update_statuses() {
    global $koneksi;
    ensure_checkout_column();
    $now = date('H:i:s');
    $today = date('Y-m-d');

    // ===== RULE R005: Lab sedang digunakan — status berlangsung hanya setelah check-in =====
    // (Occupancy lab dicek saat check-in via get_lab_active_session)

    // ===== RULE R003: IF tidak check-in THEN tidak terlaksana =====
    if (is_rule_active('R003')) {
        // If jam_selesai has passed and status is still 'diterima' (no check-in happened, no status change)
        // Also check: if jam_mulai has passed by more than 15 minutes and no check-in
        $threshold = date('H:i:s', strtotime($now . ' - 15 minutes'));

        mysqli_query($koneksi, "UPDATE peminjaman p
            LEFT JOIN check_in c ON c.peminjaman_id = p.id
            SET p.status='tidak_terlaksana'
            WHERE p.status='diterima'
            AND p.tanggal='$today'
            AND p.jam_mulai <= '$threshold'
            AND c.id IS NULL");

        // Also mark past events that were never checked in
        mysqli_query($koneksi, "UPDATE peminjaman p
            LEFT JOIN check_in c ON c.peminjaman_id = p.id
            SET p.status='tidak_terlaksana'
            WHERE p.status='diterima'
            AND p.tanggal < '$today'
            AND c.id IS NULL");
    }
}

/**
 * Ensure waktu_check_out column exists
 */
function ensure_checkout_column() {
    global $koneksi;
    $check = mysqli_query($koneksi, "SHOW COLUMNS FROM check_in LIKE 'waktu_check_out'");
    if (mysqli_num_rows($check) == 0) {
        mysqli_query($koneksi, "ALTER TABLE check_in ADD COLUMN waktu_check_out DATETIME NULL AFTER waktu_check_in");
    }
}

/**
 * Lab sedang dipakai: check-in aktif tanpa check-out
 */
function get_lab_active_session($lab_id, $tanggal, $exclude_peminjaman_id = 0) {
    global $koneksi;
    ensure_checkout_column();
    $lab_id = intval($lab_id);
    $exclude_peminjaman_id = intval($exclude_peminjaman_id);
    $tanggal = sanitize($tanggal);

    $sql = "SELECT p.*, u.nama as nama_guru, c.waktu_check_in
            FROM peminjaman p
            INNER JOIN check_in c ON c.peminjaman_id = p.id
            LEFT JOIN users u ON p.guru_id = u.id
            WHERE p.lab_id = $lab_id
            AND p.tanggal = '$tanggal'
            AND p.status = 'berlangsung'
            AND c.waktu_check_out IS NULL
            AND p.id != $exclude_peminjaman_id
            LIMIT 1";
    return mysqli_fetch_assoc(mysqli_query($koneksi, $sql)) ?: null;
}

/**
 * Evaluasi kelayakan check-in (untuk UI & validasi)
 */
function get_checkin_eligibility($peminjaman, $lab_blocked = null) {
    if (!empty($peminjaman['waktu_check_in'])) {
        return ['allowed' => false, 'code' => 'already', 'message' => 'Anda sudah melakukan check-in.'];
    }

    if ($lab_blocked) {
        $who = htmlspecialchars($lab_blocked['nama_guru'] ?? 'Guru lain');
        return [
            'allowed' => false,
            'code' => 'lab_blocked',
            'message' => "Laboratorium masih digunakan oleh $who yang belum check-out. Tunggu hingga sesi sebelumnya selesai.",
        ];
    }

    $now = date('H:i:s');
    $jam_mulai = $peminjaman['jam_mulai'];
    $jam_selesai = $peminjaman['jam_selesai'];

    if ($now < $jam_mulai) {
        return [
            'allowed' => false,
            'code' => 'too_early',
            'message' => 'Anda belum waktunya untuk melakukan check-in. Check-in dibuka mulai pukul ' . substr($jam_mulai, 0, 5) . '.',
        ];
    }

    if ($now > $jam_selesai) {
        return [
            'allowed' => false,
            'code' => 'too_late',
            'message' => 'Waktu check-in sudah lewat. Jadwal berakhir pukul ' . substr($jam_selesai, 0, 5) . '.',
        ];
    }

    return ['allowed' => true, 'code' => 'ok', 'message' => ''];
}

/**
 * Evaluasi kelayakan check-out (untuk UI & validasi)
 */
function get_checkout_eligibility($peminjaman, $force = false) {
    if (empty($peminjaman['waktu_check_in'])) {
        return ['allowed' => false, 'code' => 'no_checkin', 'message' => 'Anda belum melakukan check-in.'];
    }

    if (!empty($peminjaman['waktu_check_out'])) {
        return ['allowed' => false, 'code' => 'already', 'message' => 'Anda sudah melakukan check-out.'];
    }

    if (($peminjaman['status'] ?? '') !== 'berlangsung') {
        return ['allowed' => false, 'code' => 'invalid_status', 'message' => 'Pengajuan tidak dalam status berlangsung.'];
    }

    if (!$force) {
        $end_ts = strtotime($peminjaman['tanggal'] . ' ' . $peminjaman['jam_selesai']);
        if (time() < $end_ts) {
            return [
                'allowed' => false,
                'code' => 'too_early',
                'message' => 'Anda belum waktunya untuk melakukan check-out. Check-out dibuka mulai pukul ' . substr($peminjaman['jam_selesai'], 0, 5) . ' atau setelahnya.',
            ];
        }
    }

    return ['allowed' => true, 'code' => 'ok', 'message' => ''];
}

/**
 * Perform check-in for a peminjaman
 */
function do_checkin($peminjaman_id, $guru_id) {
    global $koneksi;
    ensure_checkout_column();
    $peminjaman_id = intval($peminjaman_id);
    $guru_id = intval($guru_id);

    $p = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT p.*, c.waktu_check_in, c.waktu_check_out
        FROM peminjaman p
        LEFT JOIN check_in c ON c.peminjaman_id = p.id
        WHERE p.id=$peminjaman_id AND p.guru_id=$guru_id AND p.status IN ('diterima','berlangsung')"));
    if (!$p) return ['success' => false, 'message' => 'Pengajuan tidak valid atau tidak bisa di-check-in.'];

    $active = get_lab_active_session($p['lab_id'], $p['tanggal'], $peminjaman_id);
    $eligibility = get_checkin_eligibility($p, $active);
    if (!$eligibility['allowed']) {
        return ['success' => false, 'message' => $eligibility['message']];
    }

    $waktu = date('Y-m-d H:i:s');
    mysqli_query($koneksi, "INSERT INTO check_in (peminjaman_id, guru_id, waktu_check_in) VALUES ($peminjaman_id, $guru_id, '$waktu')");
    mysqli_query($koneksi, "UPDATE peminjaman SET status='berlangsung' WHERE id=$peminjaman_id");

    return ['success' => true, 'message' => 'Check-in berhasil! Jangan lupa check-out setelah waktu jadwal selesai.'];
}

/**
 * Perform check-out — menandai selesai menggunakan lab
 */
function do_checkout($peminjaman_id, $guru_id, $force = false) {
    global $koneksi;
    ensure_checkout_column();
    $peminjaman_id = intval($peminjaman_id);
    $guru_id = intval($guru_id);

    $p = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT p.*, c.waktu_check_in, c.waktu_check_out
        FROM peminjaman p
        LEFT JOIN check_in c ON c.peminjaman_id = p.id
        WHERE p.id=$peminjaman_id AND p.guru_id=$guru_id AND p.status='berlangsung'"));
    if (!$p) return ['success' => false, 'message' => 'Pengajuan tidak valid atau tidak dalam status berlangsung.'];

    $eligibility = get_checkout_eligibility($p, $force);
    if (!$eligibility['allowed']) {
        return ['success' => false, 'message' => $eligibility['message']];
    }

    $waktu = date('Y-m-d H:i:s');
    $keterangan_sql = '';
    if ($force) {
        $keterangan = sanitize('Check-out paksa oleh admin');
        $keterangan_sql = ", keterangan='$keterangan'";
    }

    mysqli_query($koneksi, "UPDATE check_in SET waktu_check_out='$waktu'$keterangan_sql WHERE peminjaman_id=$peminjaman_id");
    mysqli_query($koneksi, "UPDATE peminjaman SET status='selesai' WHERE id=$peminjaman_id");

    $msg = $force
        ? 'Check-out paksa berhasil! Laboratorium siap digunakan oleh guru berikutnya.'
        : 'Check-out berhasil! Laboratorium siap digunakan oleh guru berikutnya.';

    return ['success' => true, 'message' => $msg];
}

/**
 * Check-out paksa oleh admin (sebelum waktu jadwal selesai)
 */
function do_force_checkout($peminjaman_id, $admin_id) {
    global $koneksi;
    ensure_checkout_column();
    $peminjaman_id = intval($peminjaman_id);
    $admin_id = intval($admin_id);

    $p = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT p.*, c.waktu_check_in, c.waktu_check_out, u.nama as nama_guru
        FROM peminjaman p
        LEFT JOIN check_in c ON c.peminjaman_id = p.id
        LEFT JOIN users u ON p.guru_id = u.id
        WHERE p.id=$peminjaman_id AND p.status='berlangsung'"));
    if (!$p) {
        return ['success' => false, 'message' => 'Sesi tidak valid atau tidak dalam status berlangsung.'];
    }

    if (empty($p['waktu_check_in'])) {
        return ['success' => false, 'message' => 'Guru belum melakukan check-in.'];
    }

    if (!empty($p['waktu_check_out'])) {
        return ['success' => false, 'message' => 'Check-out sudah dilakukan.'];
    }

    return do_checkout($peminjaman_id, (int)$p['guru_id'], true);
}

/**
 * Check if a peminjaman has been checked in (returns full record)
 */
function has_checkin($peminjaman_id) {
    global $koneksi;
    ensure_checkout_column();
    return mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT * FROM check_in WHERE peminjaman_id=" . intval($peminjaman_id)));
}

/**
 * Get all active rules
 */
function get_all_rules($status = null) {
    global $koneksi;
    $where = $status ? "WHERE status='".sanitize($status)."'" : '';
    $result = mysqli_query($koneksi, "SELECT * FROM rules $where ORDER BY prioritas");
    $rules = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $rules[] = $row;
    }
    return $rules;
}
