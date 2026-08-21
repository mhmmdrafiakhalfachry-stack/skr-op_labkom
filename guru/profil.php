<?php
$page_title = 'Profil Saya';
require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/profil.php';
check_login();

$profil_redirect = 'profil.php';
require_once __DIR__ . '/../includes/profil_handler.php';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/profil_view.php';
require_once __DIR__ . '/../includes/footer.php';
