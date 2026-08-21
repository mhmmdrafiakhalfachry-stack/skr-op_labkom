<?php
/**
 * Auth & Helper Functions - Lab Komputer v2
 */

/**
 * Cek apakah user sudah login
 * Redirects to the correct login page based on current section
 */
function check_login() {
    if (!isset($_SESSION['user_id'])) {
        $uri = $_SERVER['REQUEST_URI'];
        if (strpos($uri, '/admin/') !== false) {
            header("Location: " . BASE_URL . "login_admin.php");
        } else {
            header("Location: " . BASE_URL . "login.php");
        }
        exit;
    }
}

/**
 * Cek role user
 * If wrong role, redirect to the correct login page
 */
function check_role($allowed_roles = []) {
    if (!in_array($_SESSION['role'], $allowed_roles)) {
        if (in_array('admin', $allowed_roles)) {
            header("Location: " . BASE_URL . "login_admin.php");
        } else {
            header("Location: " . BASE_URL . "login.php");
        }
        exit;
    }
}

/**
 * Cek apakah sudah login, redirect ke dashboard
 */
function check_already_login() {
    if (isset($_SESSION['user_id'])) {
        if ($_SESSION['role'] == 'admin') {
            header("Location: " . BASE_URL . "admin/dashboard.php");
        } else {
            header("Location: " . BASE_URL . "guru/dashboard.php");
        }
        exit;
    }
}

/**
 * Get user info dari session
 */
function get_user($field = null) {
    if ($field) {
        return $_SESSION[$field] ?? null;
    }
    return [
        'id' => $_SESSION['user_id'] ?? null,
        'nama' => $_SESSION['nama'] ?? null,
        'nip' => $_SESSION['nip'] ?? null,
        'role' => $_SESSION['role'] ?? null,
        'email' => $_SESSION['email'] ?? null,
    ];
}

/**
 * Alert message
 */
function set_alert($type, $message) {
    $_SESSION['alert_type'] = $type;
    $_SESSION['alert_message'] = $message;
}

function get_alert() {
    if (isset($_SESSION['alert_type'])) {
        $type = $_SESSION['alert_type'];
        $message = $_SESSION['alert_message'];
        unset($_SESSION['alert_type'], $_SESSION['alert_message']);
        return ['type' => $type, 'message' => $message];
    }
    return null;
}

function set_bentrok_popup($data) {
    $_SESSION['bentrok_popup'] = $data;
}

function pull_bentrok_popup() {
    if (!isset($_SESSION['bentrok_popup'])) {
        return null;
    }
    $data = $_SESSION['bentrok_popup'];
    unset($_SESSION['bentrok_popup']);
    return $data;
}

/**
 * Sanitize input
 */
function sanitize($data) {
    global $koneksi;
    return mysqli_real_escape_string($koneksi, trim(htmlspecialchars($data)));
}

/**
 * Format tanggal Indonesia
 */
function format_tanggal($tanggal) {
    if (!$tanggal || $tanggal == '0000-00-00') return '-';
    $bulan = [
        '01'=>'Januari','02'=>'Februari','03'=>'Maret','04'=>'April',
        '05'=>'Mei','06'=>'Juni','07'=>'Juli','08'=>'Agustus',
        '09'=>'September','10'=>'Oktober','11'=>'November','12'=>'Desember'
    ];
    $t = explode('-', $tanggal);
    return isset($t[2]) ? (int)$t[2] . ' ' . ($bulan[$t[1]] ?? $t[1]) . ' ' . $t[0] : $tanggal;
}

/**
 * Format datetime
 */
function format_datetime($dt) {
    if (!$dt) return '-';
    $parts = explode(' ', $dt);
    return format_tanggal($parts[0]) . ' ' . ($parts[1] ?? '');
}

/**
 * Generate badge HTML
 */
function badge($text, $type = 'secondary') {
    return '<span class="badge badge-' . $type . '">' . ucfirst(str_replace('_',' ',$text)) . '</span>';
}

/**
 * Kondisi badge komputer
 */
function kondisi_badge($kondisi) {
    $map = ['baik'=>'success','rusak_ringan'=>'warning','rusak_berat'=>'danger','perbaikan'=>'info'];
    return badge(str_replace('_',' ',$kondisi), $map[$kondisi] ?? 'secondary');
}

/**
 * Status pengajuan badge
 */
function status_badge($status) {
    $map = [
        'pending'           => 'warning',
        'diterima'          => 'success',
        'ditolak'           => 'danger',
        'berlangsung'       => 'primary',
        'tidak_terlaksana'  => 'dark',
        'selesai'           => 'info',
    ];
    $label = str_replace('_', ' ', $status);
    return badge($label, $map[$status] ?? 'secondary');
}

/**
 * Check-in / check-out badge helpers
 */
function checkin_badge($record) {
    if (!$record || empty($record['waktu_check_in'])) {
        return badge('Belum Check-in', 'warning');
    }
    if (!empty($record['waktu_check_out'])) {
        return badge('Selesai (Check-out)', 'info');
    }
    return badge('Sudah Check-in', 'success');
}

function checkout_badge($record) {
    if (!$record || empty($record['waktu_check_in'])) {
        return badge('Belum Check-in', 'secondary');
    }
    if (!empty($record['waktu_check_out'])) {
        return badge('Sudah Check-out', 'success');
    }
    return badge('Belum Check-out', 'warning');
}

/**
 * Lab options helper - returns array of labs
 */
function get_lab_options() {
    global $koneksi;
    $result = mysqli_query($koneksi, "SELECT id, nama_lab FROM laboratorium WHERE status='aktif' ORDER BY nama_lab");
    $labs = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $labs[] = $row;
    }
    return $labs;
}

/**
 * Generate lab dropdown HTML
 */
function lab_dropdown($name = 'lab_id', $selected = '', $class = 'form-control') {
    $labs = get_lab_options();

    // Check which labs are under maintenance today
    $maint_lab_ids = [];
    global $koneksi;
    $check = mysqli_query($koneksi, "SHOW TABLES LIKE 'maintenance'");
    if (mysqli_num_rows($check) > 0) {
        $today = date('Y-m-d');
        $maint = mysqli_query($koneksi, "SELECT lab_id FROM maintenance WHERE tanggal_mulai='$today' AND status IN ('dijadwalkan','berlangsung')");
        while ($m = mysqli_fetch_assoc($maint)) {
            $maint_lab_ids[] = (int)$m['lab_id'];
        }
    }

    $html = '<select name="'.$name.'" class="'.$class.'" required id="select_lab">';
    $html .= '<option value="">-- Pilih Laboratorium --</option>';
    foreach ($labs as $lab) {
        $sel = ($selected == $lab['id']) ? ' selected' : '';
        $is_maint = in_array((int)$lab['id'], $maint_lab_ids);
        $label = htmlspecialchars($lab['nama_lab']);
        if ($is_maint) {
            $label .= '  [MAINTENANCE]';
        }
        $data_maint = $is_maint ? ' data-maintenance="1"' : '';
        $html .= '<option value="'.$lab['id'].'"'.$sel.$data_maint.'>'.$label.'</option>';
    }
    $html .= '</select>';
    return $html;
}

/**
 * Get nama lab by id
 */
function get_nama_lab($lab_id) {
    global $koneksi;
    if (!$lab_id) return '-';
    $r = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT nama_lab FROM laboratorium WHERE id=".intval($lab_id)));
    return $r ? $r['nama_lab'] : '-';
}

/**
 * Role display label
 */
function role_label($role) {
    $map = [
        'admin' => 'Kepala Laboratorium',
        'guru'  => 'Guru',
    ];
    return $map[$role] ?? ucfirst($role);
}

require_once __DIR__ . '/../includes/page_tools.php';
