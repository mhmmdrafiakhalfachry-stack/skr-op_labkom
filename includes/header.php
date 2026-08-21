<?php
require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/rules_engine.php';
require_once __DIR__ . '/../config/notices.php';
require_once __DIR__ . '/../config/profil.php';
check_login();

// Auto-update statuses based on Rule-Based System
auto_update_statuses();
auto_update_maintenance_status();

$current_page = basename($_SERVER['PHP_SELF']);
$role = $_SESSION['role'] ?? 'guru';
$page_title = $page_title ?? 'Dashboard';

if ($role == 'admin') {
    require_once __DIR__ . '/admin_layout.php';
} else {
    require_once __DIR__ . '/guru_layout.php';
}
require_once __DIR__ . '/page_tools.php';

$header_user_foto = null;
if (isset($_SESSION['user_id'])) {
    ensure_user_foto_column();
    $hf = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT foto FROM users WHERE id=" . (int)$_SESSION['user_id']));
    $header_user_foto = get_foto_url($hf['foto'] ?? '');
    if ($hf && !empty($hf['foto'])) $_SESSION['foto'] = $hf['foto'];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?> - Lab Komputer</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <link href="<?= BASE_URL ?>assets/css/style.css" rel="stylesheet">
</head>
<body class="<?= $role == 'admin' ? 'admin-body' : 'guru-body' ?>">

<!-- Sidebar -->
<div class="sidebar sidebar-<?= $role ?>" id="sidebar">
    <div class="sidebar-header">
        <img src="<?= BASE_URL ?>assets/img/logo.png" alt="Logo SMK PGRI 35" class="sidebar-logo">
        <h5>Laboratorium Komputer</h5>
        <small><?= strtoupper($role) ?> — SMK PGRI 35</small>
    </div>

    <div class="sidebar-section">Menu Utama</div>
    <ul class="nav flex-column">

    <?php if ($role == 'admin'): ?>
        <li class="nav-item">
            <a class="nav-link <?= $current_page == 'dashboard.php' ? 'active' : '' ?>" href="<?= BASE_URL ?>admin/dashboard.php">
                <i class="fas fa-tachometer-alt"></i> Dashboard
            </a>
        </li>

        <div class="sidebar-section">Data Master</div>
        <li class="nav-item">
            <a class="nav-link <?= $current_page == 'users.php' ? 'active' : '' ?>" href="<?= BASE_URL ?>admin/users.php">
                <i class="fas fa-users"></i> Data Guru
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $current_page == 'laboratorium.php' ? 'active' : '' ?>" href="<?= BASE_URL ?>admin/laboratorium.php">
                <i class="fas fa-building"></i> Data Laboratorium
            </a>
        </li>
        <div class="sidebar-section">Praktikum</div>
        <li class="nav-item">
            <a class="nav-link <?= $current_page == 'jadwal.php' ? 'active' : '' ?>" href="<?= BASE_URL ?>admin/jadwal.php">
                <i class="fas fa-calendar-alt"></i> Jadwal Praktikum
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $current_page == 'pengajuan.php' ? 'active' : '' ?>" href="<?= BASE_URL ?>admin/pengajuan.php">
                <i class="fas fa-clipboard-list"></i> Pengajuan Penggunaan
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $current_page == 'monitoring.php' ? 'active' : '' ?>" href="<?= BASE_URL ?>admin/monitoring.php">
                <i class="fas fa-eye"></i> Monitoring
                <span class="live-dot"></span>
            </a>
        </li>

        <div class="sidebar-section">Operasional</div>
        <li class="nav-item">
            <a class="nav-link <?= $current_page == 'maintenance.php' ? 'active' : '' ?>" href="<?= BASE_URL ?>admin/maintenance.php">
                <i class="fas fa-wrench"></i> Maintenance
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $current_page == 'laporan.php' ? 'active' : '' ?>" href="<?= BASE_URL ?>admin/laporan.php">
                <i class="fas fa-file-alt"></i> Laporan
            </a>
        </li>

    <?php else: ?>
        <li class="nav-item">
            <a class="nav-link <?= $current_page == 'dashboard.php' ? 'active' : '' ?>" href="<?= BASE_URL ?>guru/dashboard.php">
                <i class="fas fa-tachometer-alt"></i> Dashboard
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $current_page == 'pengajuan.php' ? 'active' : '' ?>" href="<?= BASE_URL ?>guru/pengajuan.php">
                <i class="fas fa-paper-plane"></i> Ajukan Penggunaan
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $current_page == 'status.php' ? 'active' : '' ?>" href="<?= BASE_URL ?>guru/status.php">
                <i class="fas fa-list-alt"></i> Status Pengajuan
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $current_page == 'jadwal.php' ? 'active' : '' ?>" href="<?= BASE_URL ?>guru/jadwal.php">
                <i class="fas fa-calendar-alt"></i> Jadwal Lab
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $current_page == 'checkin.php' ? 'active' : '' ?>" href="<?= BASE_URL ?>guru/checkin.php">
                <i class="fas fa-exchange-alt"></i> Check-In / Out
            </a>
        </li>
        <?php
        $unread_notices = count_unread_notices($_SESSION['user_id'] ?? 0);
        ?>
        <li class="nav-item">
            <a class="nav-link <?= $current_page == 'notices.php' ? 'active' : '' ?>" href="<?= BASE_URL ?>guru/notices.php">
                <i class="fas fa-bell"></i> Notice
                <?php if ($unread_notices > 0): ?>
                    <span class="badge badge-danger badge-pill"><?= $unread_notices ?></span>
                <?php endif; ?>
            </a>
        </li>
    <?php endif; ?>
    </ul>

    <div class="sidebar-section">Akun</div>
    <ul class="nav flex-column">
        <li class="nav-item">
            <a class="nav-link <?= $current_page == 'profil.php' ? 'active' : '' ?>" href="<?= BASE_URL . $role ?>/profil.php">
                <i class="fas fa-user-cog"></i> Profil Saya
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="<?= BASE_URL ?>logout.php"
               data-confirm-action
               data-confirm-title="Logout"
               data-confirm-message="Yakin ingin logout?"
               data-confirm-desc="Anda akan keluar dari sesi dan perlu login kembali untuk mengakses sistem."
               data-confirm-icon="sign-out-alt"
               data-confirm-icon-style="warning"
               data-confirm-btn-class="btn-primary">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </li>
    </ul>
</div>

<!-- Main Content -->
<div class="main-content">
    <!-- Top Navbar -->
    <div class="top-navbar">
        <div class="d-flex align-items-center">
            <button class="btn btn-sm btn-outline-secondary mr-3 d-md-none" onclick="document.getElementById('sidebar').classList.toggle('show')">
                <i class="fas fa-bars"></i>
            </button>
            <h5 class="page-title mb-0"><?= $page_title ?></h5>
        </div>
        <div class="user-info">
            <span class="d-none d-md-inline text-right">
                <small class="text-muted"><?= role_label($_SESSION['role'] ?? '') ?></small><br>
                <strong style="font-size:13px"><?= htmlspecialchars($_SESSION['nama'] ?? '') ?></strong>
            </span>
            <div class="avatar">
                <?php if ($header_user_foto): ?>
                    <img src="<?= htmlspecialchars($header_user_foto) ?>" alt="" style="width:36px;height:36px;border-radius:50%;object-fit:cover;">
                <?php else: ?>
                    <?= strtoupper(substr($_SESSION['nama'] ?? 'U', 0, 1)) ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Content -->
    <div class="content-wrapper">
        <?php
        $alert = get_alert();
        if ($alert): ?>
            <div class="alert alert-<?= $alert['type'] ?> alert-dismissible fade show flash-alert<?= (stripos($alert['message'], 'bentrok') !== false ? ' alert-no-autohide' : '') ?>">
                <?= $alert['message'] ?>
                <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
            </div>
        <?php endif; ?>
