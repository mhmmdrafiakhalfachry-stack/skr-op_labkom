<?php
/**
 * Notice / Pengumuman untuk Guru
 * Terintegrasi dengan jadwal maintenance lab
 */

function ensure_notices_table() {
    global $koneksi;
    mysqli_query($koneksi, "CREATE TABLE IF NOT EXISTS notices (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NULL COMMENT 'NULL = broadcast ke semua guru',
        jenis VARCHAR(50) NOT NULL DEFAULT 'info',
        ref_id INT NULL COMMENT 'ID maintenance terkait',
        judul VARCHAR(255) NOT NULL,
        pesan TEXT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_user (user_id),
        INDEX idx_jenis_ref (jenis, ref_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    mysqli_query($koneksi, "CREATE TABLE IF NOT EXISTS notice_reads (
        notice_id INT NOT NULL,
        user_id INT NOT NULL,
        read_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (notice_id, user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

/**
 * Kirim notice maintenance ke semua akun guru
 */
function notify_guru_maintenance($maintenance_id, $event) {
    global $koneksi;
    ensure_notices_table();

    $maintenance_id = intval($maintenance_id);
    $m = mysqli_fetch_assoc(mysqli_query($koneksi, "
        SELECT m.*, l.nama_lab
        FROM maintenance m
        LEFT JOIN laboratorium l ON m.lab_id = l.id
        WHERE m.id = $maintenance_id
    "));
    if (!$m) return false;

    $jenis_labels = [
        'rutin' => 'Rutin', 'perbaikan' => 'Perbaikan', 'upgrade' => 'Upgrade',
        'pembersihan' => 'Pembersihan', 'instalasi' => 'Instalasi Software'
    ];
    $jenis_label = $jenis_labels[$m['jenis']] ?? ucfirst($m['jenis']);
    $lab = $m['nama_lab'] ?? 'Laboratorium';
    $tanggal = format_tanggal($m['tanggal_mulai']);
    $waktu = substr($m['jam_mulai'], 0, 5) . ' - ' . substr($m['jam_selesai'], 0, 5);

    switch ($event) {
        case 'dijadwalkan':
            $judul = "Maintenance Lab: $lab";
            $pesan = "Jadwal maintenance ($jenis_label) pada $lab tanggal $tanggal pukul $waktu. "
                   . "Laboratorium tidak dapat digunakan selama maintenance berlangsung. "
                   . "Pengajuan penggunaan lab untuk waktu tersebut tidak dapat dilakukan.";
            break;
        case 'berlangsung':
            $judul = "Maintenance Sedang Berlangsung: $lab";
            $pesan = "Maintenance ($jenis_label) sedang berlangsung di $lab ($waktu). "
                   . "Lab tidak dapat digunakan dan pengajuan penggunaan ditangguhkan.";
            break;
        case 'selesai':
            $judul = "Maintenance Selesai: $lab";
            $pesan = "Maintenance ($jenis_label) di $lab telah selesai. "
                   . "Laboratorium kembali dapat digunakan untuk pengajuan penggunaan.";
            break;
        case 'dibatalkan':
            $judul = "Maintenance Dibatalkan: $lab";
            $pesan = "Jadwal maintenance ($jenis_label) di $lab tanggal $tanggal telah dibatalkan. "
                   . "Laboratorium kembali dapat digunakan untuk pengajuan penggunaan.";
            break;
        default:
            return false;
    }

    $judul_esc = sanitize($judul);
    $pesan_esc = sanitize($pesan);
    mysqli_query($koneksi, "
        INSERT INTO notices (user_id, jenis, ref_id, judul, pesan)
        VALUES (NULL, 'maintenance', $maintenance_id, '$judul_esc', '$pesan_esc')
    ");
    return true;
}

/**
 * Ambil notice untuk guru (broadcast + personal)
 */
function get_guru_notices($user_id, $limit = 10, $unread_only = false) {
    global $koneksi;
    ensure_notices_table();
    $user_id = intval($user_id);
    $limit = intval($limit);

    $read_filter = $unread_only ? " AND nr.user_id IS NULL" : "";

    $sql = "SELECT n.*, (nr.user_id IS NOT NULL) AS is_read
            FROM notices n
            LEFT JOIN notice_reads nr ON nr.notice_id = n.id AND nr.user_id = $user_id
            WHERE (n.user_id IS NULL OR n.user_id = $user_id)
            $read_filter
            ORDER BY n.created_at DESC
            LIMIT $limit";
    $result = mysqli_query($koneksi, $sql);
    $notices = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $notices[] = $row;
    }
    return $notices;
}

function count_unread_notices($user_id) {
    global $koneksi;
    ensure_notices_table();
    $user_id = intval($user_id);
    $r = mysqli_fetch_assoc(mysqli_query($koneksi, "
        SELECT COUNT(*) AS c FROM notices n
        LEFT JOIN notice_reads nr ON nr.notice_id = n.id AND nr.user_id = $user_id
        WHERE (n.user_id IS NULL OR n.user_id = $user_id)
        AND nr.user_id IS NULL
    "));
    return (int)($r['c'] ?? 0);
}

function mark_notice_read($notice_id, $user_id) {
    global $koneksi;
    ensure_notices_table();
    $notice_id = intval($notice_id);
    $user_id = intval($user_id);
    mysqli_query($koneksi, "
        INSERT IGNORE INTO notice_reads (notice_id, user_id) VALUES ($notice_id, $user_id)
    ");
}

function mark_all_notices_read($user_id) {
    global $koneksi;
    ensure_notices_table();
    $user_id = intval($user_id);
    mysqli_query($koneksi, "
        INSERT IGNORE INTO notice_reads (notice_id, user_id)
        SELECT n.id, $user_id FROM notices n
        LEFT JOIN notice_reads nr ON nr.notice_id = n.id AND nr.user_id = $user_id
        WHERE (n.user_id IS NULL OR n.user_id = $user_id) AND nr.user_id IS NULL
    ");
}
