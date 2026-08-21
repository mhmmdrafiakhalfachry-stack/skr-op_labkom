<?php
/**
 * Profil user — foto & kartu identitas
 */

function ensure_user_foto_column() {
    global $koneksi;
    $check = mysqli_query($koneksi, "SHOW COLUMNS FROM users LIKE 'foto'");
    if (mysqli_num_rows($check) == 0) {
        mysqli_query($koneksi, "ALTER TABLE users ADD COLUMN foto VARCHAR(255) NULL AFTER no_hp");
    }
}

function profil_upload_dir() {
    $dir = __DIR__ . '/../uploads/profil/';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    return $dir;
}

function get_foto_url($foto) {
    if (!$foto) return null;
    $path = profil_upload_dir() . $foto;
    if (file_exists($path)) {
        return BASE_URL . 'uploads/profil/' . rawurlencode($foto);
    }
    return null;
}

function upload_user_foto($user_id, $file) {
    global $koneksi;
    ensure_user_foto_column();
    $user_id = (int)$user_id;

    if (empty($file['tmp_name']) || $file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'Gagal mengupload file.'];
    }

    $allowed = ['image/jpeg', 'image/png', 'image/jpg', 'image/webp'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mime, $allowed)) {
        return ['success' => false, 'message' => 'Format foto harus JPG, PNG, atau WEBP.'];
    }

    if ($file['size'] > 2 * 1024 * 1024) {
        return ['success' => false, 'message' => 'Ukuran foto maksimal 2 MB.'];
    }

    $ext = $mime === 'image/png' ? 'png' : ($mime === 'image/webp' ? 'webp' : 'jpg');
    $filename = 'user_' . $user_id . '_' . time() . '.' . $ext;
    $dest = profil_upload_dir() . $filename;

    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        return ['success' => false, 'message' => 'Gagal menyimpan foto.'];
    }

    $old = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT foto FROM users WHERE id=$user_id"));
    if (!empty($old['foto'])) {
        $old_path = profil_upload_dir() . $old['foto'];
        if (file_exists($old_path)) @unlink($old_path);
    }

    $filename_esc = sanitize($filename);
    mysqli_query($koneksi, "UPDATE users SET foto='$filename_esc' WHERE id=$user_id");
    $_SESSION['foto'] = $filename;

    return ['success' => true, 'message' => 'Foto profil berhasil diupload!', 'filename' => $filename];
}

function user_avatar_html($user, $size = 80, $class = '') {
    $nama = $user['nama'] ?? 'U';
    $initial = strtoupper(substr($nama, 0, 1));
    $url = get_foto_url($user['foto'] ?? '');

    if ($url) {
        return '<img src="' . htmlspecialchars($url) . '" alt="Foto Profil" class="user-avatar-img ' . $class . '" style="width:' . (int)$size . 'px;height:' . (int)$size . 'px;object-fit:cover;border-radius:50%;">';
    }

    return '<div class="user-avatar-initial ' . $class . '" style="width:' . (int)$size . 'px;height:' . (int)$size . 'px;border-radius:50%;background:linear-gradient(135deg,#1e3c72,#2a5298);display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:' . round($size * 0.4) . 'px;">' . $initial . '</div>';
}

function render_kartu_identitas($user) {
    $foto_url = get_foto_url($user['foto'] ?? '');
    $role_label = role_label($user['role'] ?? '');
    $status_label = ucfirst($user['status'] ?? 'aktif');
    $terdaftar = format_tanggal(substr($user['created_at'] ?? date('Y-m-d'), 0, 10));
    $nip = htmlspecialchars($user['nip'] ?? '-');
    $nama = htmlspecialchars($user['nama'] ?? '-');
    $email = htmlspecialchars($user['email'] ?: '-');
    $no_hp = htmlspecialchars($user['no_hp'] ?: '-');
    $id_lab = 'LAB-' . str_pad($user['id'], 5, '0', STR_PAD_LEFT);
    $logo_url = BASE_URL . 'assets/img/logo.png';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Kartu Identitas Lab — <?= $nama ?></title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            background: linear-gradient(135deg, #8d8006, #fffbd7);
            display: flex; flex-direction: column; align-items: center;
            padding: 32px 16px; min-height: 100vh;
        }
        .toolbar { margin-bottom: 20px; display: flex; gap: 10px; }
        .toolbar button { padding: 10px 20px; border: none; border-radius: 6px; cursor: pointer; font-size: 14px; font-weight: 600; }
        .btn-print { background: linear-gradient(135deg, #8d8006, #ffe819); color: #fff; }
        .btn-close { background: #6c757d; color: #fff; }
        .kartu {
            width: 540px; height: 340px; background: #fff; border-radius: 14px;
            overflow: hidden; box-shadow: 0 8px 32px rgba(0,0,0,0.18);
            border: 2px solid #d4c200; position: relative;
        }
        .kartu-header {
            background: linear-gradient(135deg, #8d8006 0%, #ffe819 100%);
            color: #fff; padding: 12px 20px 10px; text-align: center;
            position: relative; z-index: 2;
        }
        .kartu-kop {
            display: flex; align-items: center; justify-content: center; gap: 12px; margin-bottom: 6px;
        }
        .kartu-kop img {
            width: 48px; height: 48px; object-fit: contain;
            background: rgba(255,255,255,0.92); border-radius: 50%; padding: 4px;
            border: 2px solid rgba(255,255,255,0.6);
        }
        .kartu-kop-text { text-align: left; }
        .kartu-kop-text .sekolah { font-size: 11px; font-weight: 700; letter-spacing: 0.5px; line-height: 1.3; }
        .kartu-kop-text .sub { font-size: 9px; opacity: 0.92; }
        .kartu-header h1 { font-size: 12px; letter-spacing: 1.2px; font-weight: 700; margin-top: 4px; text-shadow: 0 1px 2px rgba(0,0,0,0.15); }
        .kartu-body {
            display: flex; padding: 16px 20px; gap: 18px;
            position: relative; z-index: 1; min-height: 220px;
        }
        .kartu-body::before {
            content: '';
            position: absolute; inset: 0;
            background: url('<?= htmlspecialchars($logo_url) ?>') center center no-repeat;
            background-size: 180px 180px;
            opacity: 0.07;
            z-index: 0; pointer-events: none;
        }
        .kartu-foto {
            width: 110px; height: 135px; border: 2px solid #8d8006; border-radius: 6px;
            overflow: hidden; background: rgba(255,251,215,0.85); flex-shrink: 0;
            display: flex; align-items: center; justify-content: center;
            position: relative; z-index: 1;
            box-shadow: 0 2px 8px rgba(141,128,6,0.15);
        }
        .kartu-foto img { width: 100%; height: 100%; object-fit: cover; }
        .kartu-foto .initial { font-size: 48px; font-weight: 700; color: #8d8006; }
        .kartu-data { flex: 1; font-size: 12px; position: relative; z-index: 1; }
        .kartu-data .field { margin-bottom: 7px; display: flex; }
        .kartu-data .label { width: 110px; color: #666; font-weight: 600; flex-shrink: 0; }
        .kartu-data .value { color: #222; font-weight: 500; }
        .kartu-data .nama { font-size: 16px; font-weight: 700; color: #8d8006; margin-bottom: 10px; }
        .kartu-footer {
            position: absolute; bottom: 0; left: 0; right: 0;
            background: linear-gradient(to right, #fffbd7, #fff8e1);
            border-top: 1px solid #e6d800;
            padding: 8px 20px; display: flex; justify-content: space-between; align-items: center;
            font-size: 10px; color: #666; z-index: 2;
        }
        .kartu-id { font-family: 'Courier New', monospace; font-weight: 700; color: #8d8006; font-size: 11px; }
        .chip {
            display: inline-block;
            background: linear-gradient(135deg, #8d8006, #c4b800);
            color: #fff; padding: 2px 8px; border-radius: 4px;
            font-size: 10px; font-weight: 600;
        }
        @media print {
            body { background: #fff; padding: 0; }
            .toolbar { display: none !important; }
            .kartu { box-shadow: none; border: 2px solid #8d8006; page-break-inside: avoid; }
        }
    </style>
</head>
<body>
    <div class="toolbar no-print">
        <button class="btn-print" onclick="window.print()">🖨 Cetak</button>
        <button class="btn-close" onclick="window.close()">Tutup</button>
    </div>
    <div class="kartu">
        <div class="kartu-header">
            <div class="kartu-kop">
                <img src="<?= htmlspecialchars($logo_url) ?>" alt="Logo SMK PGRI 35" onerror="this.style.display='none'">
                <div class="kartu-kop-text">
                    <div class="sekolah">SMK PGRI 35 SOLOKAN JERUK</div>
                    <div class="sub"> Jl. R.H.O Kosasih No.90, Cibodas, Solokanjeruk-Bandung</div>
                </div>
            </div>
            <h1>KARTU IDENTITAS LABORATORIUM KOMPUTER</h1>
        </div>
        <div class="kartu-body">
            <div class="kartu-foto">
                <?php if ($foto_url): ?>
                    <img src="<?= htmlspecialchars($foto_url) ?>" alt="Foto">
                <?php else: ?>
                    <span class="initial"><?= strtoupper(substr($user['nama'] ?? 'U', 0, 1)) ?></span>
                <?php endif; ?>
            </div>
            <div class="kartu-data">
                <div class="nama"><?= $nama ?></div>
                <div class="field"><span class="label">NIP</span><span class="value"><?= $nip ?></span></div>
                <div class="field"><span class="label">Jabatan</span><span class="value"><span class="chip"><?= htmlspecialchars($role_label) ?></span></span></div>
                <div class="field"><span class="label">Email</span><span class="value"><?= $email ?></span></div>
                <div class="field"><span class="label">No. Telepon</span><span class="value"><?= $no_hp ?></span></div>
                <div class="field"><span class="label">Status</span><span class="value"><?= $status_label ?></span></div>
                <div class="field"><span class="label">Terdaftar</span><span class="value"><?= $terdaftar ?></span></div>
            </div>
        </div>
        <div class="kartu-footer">
            <span class="kartu-id"><?= $id_lab ?></span>
            <span>Dicetak: <?= date('d/m/Y H:i') ?></span>
        </div>
    </div>
</body>
</html>
<?php
}
