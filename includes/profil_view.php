<?php
$foto_url = get_foto_url($user['foto'] ?? '');
$is_admin = ($user['role'] ?? '') === 'admin';
?>

<div class="guru-page-header mb-4">
    <h4 class="mb-1 font-weight-bold text-dark">Profil Saya</h4>
    <p class="text-muted mb-0 guru-page-subtitle">Kelola informasi akun, foto profil, dan kartu identitas lab</p>
</div>

<div class="row">
    <div class="col-lg-4 mb-4">
        <div class="card guru-panel border-0">
            <div class="card-body text-center pt-4 pb-4">
                <div class="mx-auto mb-3 position-relative" style="width:120px;height:120px;">
                    <?= user_avatar_html($user, 120) ?>
                </div>
                <h5 class="mb-1 font-weight-bold"><?= htmlspecialchars($user['nama']) ?></h5>
                <p class="mb-3"><?= badge($user['role'], $is_admin ? 'primary' : 'info') ?></p>

                <!-- Upload Foto -->
                <form method="POST" enctype="multipart/form-data" class="mb-3">
                    <input type="hidden" name="upload_foto" value="1">
                    <div class="custom-file mb-2 text-left">
                        <input type="file" name="foto" class="custom-file-input" id="inputFoto" accept="image/jpeg,image/png,image/webp" required>
                        <label class="custom-file-label" for="inputFoto" data-browse="Pilih">Pilih foto...</label>
                    </div>
                    <button type="submit" class="btn btn-outline-primary btn-sm btn-block"><i class="fas fa-camera"></i> Upload Foto Profil</button>
                    <small class="text-muted d-block mt-1">Ukuran Profil maks. 2 MB</small>
                </form>

                <hr>

                <table class="table table-sm table-borderless text-left mb-3 profil-info-table">
                    <tr><th>NIP</th><td><?= htmlspecialchars($user['nip']) ?></td></tr>
                    <tr><th>Email</th><td><?= htmlspecialchars($user['email'] ?: '-') ?></td></tr>
                    <tr><th>No HP</th><td><?= htmlspecialchars($user['no_hp'] ?: '-') ?></td></tr>
                    <tr><th>Status</th><td><?= badge($user['status'], $user['status'] == 'aktif' ? 'success' : 'secondary') ?></td></tr>
                    <tr><th>Terdaftar</th><td><?= format_tanggal(substr($user['created_at'], 0, 10)) ?></td></tr>
                </table>

                <a href="profil.php?kartu=1" target="_blank" class="btn btn-primary btn-block">
                    <i class="fas fa-id-card"></i> Download Kartu Identitas Lab
                </a>
                <small class="text-muted d-block mt-2"></small>
            </div>
        </div>
    </div>

    <!-- Form -->
    <div class="col-lg-8 mb-4">
        <div class="card guru-panel border-0 mb-3">
            <div class="card-header guru-card-header">
                <h6 class="mb-0 font-weight-bold"><i class="fas fa-user-edit mr-2"></i>Edit Profil</h6>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="update_profil" value="1">
                    <div class="form-group">
                        <label class="font-weight-bold">Nama Lengkap</label>
                        <input type="text" name="nama" class="form-control" value="<?= htmlspecialchars($user['nama']) ?>" required>
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label class="font-weight-bold">Email</label>
                            <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($user['email'] ?? '') ?>">
                        </div>
                        <div class="form-group col-md-6">
                            <label class="font-weight-bold">No HP</label>
                            <input type="text" name="no_hp" class="form-control" value="<?= htmlspecialchars($user['no_hp'] ?? '') ?>">
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Simpan Perubahan</button>
                </form>
            </div>
        </div>

        <div class="card guru-panel border-0">
            <div class="card-header guru-card-header">
                <h6 class="mb-0 font-weight-bold"><i class="fas fa-key mr-2"></i>Ganti Password</h6>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="ganti_password" value="1">
                    <div class="form-group">
                        <label class="font-weight-bold">Password Lama</label>
                        <input type="password" name="old_password" class="form-control" required>
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label class="font-weight-bold">Password Baru</label>
                            <input type="password" name="new_password" class="form-control" required minlength="6">
                        </div>
                        <div class="form-group col-md-6">
                            <label class="font-weight-bold">Konfirmasi Password</label>
                            <input type="password" name="confirm_password" class="form-control" required minlength="6">
                        </div>
                    </div>
                    <button type="submit" class="btn btn-warning"><i class="fas fa-key"></i> Ganti Password</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('inputFoto')?.addEventListener('change', function() {
    var label = this.nextElementSibling;
    label.textContent = this.files[0] ? this.files[0].name : 'Pilih foto...';
});
</script>
