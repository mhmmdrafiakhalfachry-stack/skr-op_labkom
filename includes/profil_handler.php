<?php
/**
 * Handler profil — upload foto, update data, kartu identitas
 * Set $profil_redirect sebelum include (mis. 'profil.php')
 */

require_once __DIR__ . '/../config/profil.php';

ensure_user_foto_column();
$profil_redirect = $profil_redirect ?? 'profil.php';

$user = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT * FROM users WHERE id = " . (int)$_SESSION['user_id']));
if (!$user) {
    set_alert('danger', 'Data user tidak ditemukan.');
    header('Location: ' . BASE_URL);
    exit;
}

// Kartu identitas (print / PDF)
if (isset($_GET['kartu'])) {
    render_kartu_identitas($user);
    exit;
}

// Upload foto
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['upload_foto'])) {
    $result = upload_user_foto($_SESSION['user_id'], $_FILES['foto'] ?? []);
    set_alert($result['success'] ? 'success' : 'danger', $result['message']);
    header('Location: ' . $profil_redirect);
    exit;
}

// Update profil
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profil'])) {
    $nama = sanitize($_POST['nama']);
    $email = sanitize($_POST['email'] ?? '');
    $no_hp = sanitize($_POST['no_hp'] ?? '');

    mysqli_query($koneksi, "UPDATE users SET nama='$nama', email='$email', no_hp='$no_hp' WHERE id=" . $_SESSION['user_id']);
    $_SESSION['nama'] = $nama;
    $_SESSION['email'] = $email;
    set_alert('success', 'Profil berhasil diperbarui!');
    header('Location: ' . $profil_redirect);
    exit;
}

// Ganti password
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['ganti_password'])) {
    $old_pass = $_POST['old_password'] ?? '';
    $new_pass = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if (!password_verify($old_pass, $user['password'])) {
        set_alert('danger', 'Password lama salah!');
    } elseif (strlen($new_pass) < 6) {
        set_alert('danger', 'Password baru minimal 6 karakter!');
    } elseif ($new_pass !== $confirm) {
        set_alert('danger', 'Konfirmasi password tidak cocok!');
    } else {
        $hash = password_hash($new_pass, PASSWORD_BCRYPT);
        mysqli_query($koneksi, "UPDATE users SET password='$hash' WHERE id=" . $_SESSION['user_id']);
        set_alert('success', 'Password berhasil diubah!');
    }
    header('Location: ' . $profil_redirect);
    exit;
}

// Refresh user after possible updates
$user = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT * FROM users WHERE id = " . (int)$_SESSION['user_id']));
