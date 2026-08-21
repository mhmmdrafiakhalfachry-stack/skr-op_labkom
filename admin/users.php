<?php
$page_title = 'Data Pengguna';

// Load config FIRST (no HTML output yet)
require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../config/auth.php';
check_login();
check_role(['admin']);

// Handle actions BEFORE any output
if (isset($_GET['action'])) {
    $id = (int)($_GET['id'] ?? 0);

    if ($_GET['action'] == 'delete' && $id > 0) {
        if ($id == $_SESSION['user_id']) {
            set_alert('danger', 'Tidak bisa menghapus akun sendiri!');
        } else {
            mysqli_query($koneksi, "DELETE FROM users WHERE id = $id");
            set_alert('success', 'Pengguna berhasil dihapus!');
        }
        header("Location: users.php");
        exit;
    }

    if ($_GET['action'] == 'toggle' && $id > 0) {
        if ($id == $_SESSION['user_id']) {
            set_alert('danger', 'Tidak bisa menonaktifkan akun sendiri!');
        } else {
            mysqli_query($koneksi, "UPDATE users SET status = IF(status='aktif','nonaktif','aktif') WHERE id = $id");
            set_alert('success', 'Status pengguna berhasil diubah!');
        }
        header("Location: users.php");
        exit;
    }
}

// Handle form submit (add/edit) BEFORE any output
$edit_data = null;
if (isset($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    $edit_data = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT * FROM users WHERE id = $edit_id"));
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    $nama = sanitize($_POST['nama']);
    $nip = sanitize($_POST['nip']);
    $email = sanitize($_POST['email'] ?? '');
    $no_hp = sanitize($_POST['no_hp'] ?? '');
    $role = sanitize($_POST['role']);
    $password = $_POST['password'] ?? '';

    if ($id > 0) {
        $sql = "UPDATE users SET nama='$nama', nip='$nip', email='$email', no_hp='$no_hp', role='$role'";
        if (!empty($password)) {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $sql .= ", password='$hash'";
        }
        $sql .= " WHERE id=$id";
        mysqli_query($koneksi, $sql);
        set_alert('success', 'Data pengguna berhasil diperbarui!');
    } else {
        if (empty($password)) {
            set_alert('danger', 'Password wajib diisi untuk pengguna baru!');
        } else {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $sql = "INSERT INTO users (nama, nip, password, role, email, no_hp, status) VALUES ('$nama', '$nip', '$hash', '$role', '$email', '$no_hp', 'aktif')";
            if (mysqli_query($koneksi, $sql)) {
                set_alert('success', 'Pengguna baru berhasil ditambahkan!');
            } else {
                set_alert('danger', 'Gagal menambahkan pengguna: ' . mysqli_error($koneksi));
            }
        }
    }
    header("Location: users.php");
    exit;
}

// Search
$search = sanitize($_GET['search'] ?? '');
$fr = sanitize($_GET['role'] ?? '');
$where = 'WHERE 1=1';
if ($search) {
    $where .= " AND (nama LIKE '%$search%' OR nip LIKE '%$search%' OR email LIKE '%$search%')";
}
if ($fr) {
    $where .= " AND role='$fr'";
}
$users = mysqli_query($koneksi, "SELECT * FROM users $where ORDER BY created_at DESC");

// NOW include header (starts HTML output)
require_once __DIR__ . '/../includes/header.php';
?>

<?php admin_page_header('Data Pengguna', 'Kelola akun guru dan admin', '<button class="btn btn-primary" data-toggle="modal" data-target="#modalUser" onclick="resetForm()"><i class="fas fa-plus"></i> Tambah Pengguna</button>'); ?>

<!-- Search -->
<div class="card admin-panel border-0 mb-3 no-print">
    <div class="card-body py-3 admin-filter-bar">
        <form method="GET" class="form-inline flex-wrap align-items-center">
            <input type="text" name="search" class="form-control form-control-sm mr-2 mb-1" style="min-width:220px" placeholder="Cari nama, NIP, email..." value="<?= htmlspecialchars($search) ?>">
            <select name="role" class="form-control form-control-sm mr-2 mb-1 page-filter-role" style="min-width:150px;width:auto;height:31px">
                <option value="">Semua Role</option>
                <option value="admin" <?= $fr == 'admin' ? 'selected' : '' ?>>Admin</option>
                <option value="guru" <?= $fr == 'guru' ? 'selected' : '' ?>>Guru</option>
            </select>
            <button type="submit" class="btn btn-sm btn-primary mr-2 mb-1"><i class="fas fa-search"></i> Cari</button>
            <?php if ($search || $fr): ?>
                <a href="users.php" class="btn btn-sm btn-outline-secondary mb-1 mr-2">Reset</a>
            <?php endif; ?>
            <?= page_print_button() ?>
        </form>
    </div>
</div>

<?php page_print_area_open(); ?>

<!-- Table -->
<div class="card admin-panel border-0">
    <div class="card-header bg-white border-bottom py-3">
        <h6 class="mb-0 font-weight-bold"><i class="fas fa-users text-primary mr-2"></i>Daftar Pengguna</h6>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover admin-table mb-0">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Nama</th>
                        <th>NIP</th>
                        <th>Email</th>
                        <th>No HP</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th class="col-aksi no-print d-print-none">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $no = 1;
                    if (mysqli_num_rows($users) > 0):
                        while ($u = mysqli_fetch_assoc($users)): ?>
                            <tr>
                                <td><?= $no++ ?></td>
                                <td><strong><?= htmlspecialchars($u['nama']) ?></strong></td>
                                <td><?= htmlspecialchars($u['nip']) ?></td>
                                <td><?= htmlspecialchars($u['email'] ?: '-') ?></td>
                                <td><?= htmlspecialchars($u['no_hp'] ?: '-') ?></td>
                                <td><?= badge($u['role'], $u['role'] == 'admin' ? 'primary' : 'info') ?></td>
                                <td><?= badge($u['status'], $u['status'] == 'aktif' ? 'success' : 'secondary') ?></td>
                                <td class="col-aksi no-print d-print-none">
                                    <a href="users.php?edit=<?= $u['id'] ?>" class="btn btn-sm btn-warning" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <?php if ($u['id'] != $_SESSION['user_id']): ?>
                                        <a href="users.php?action=toggle&id=<?= $u['id'] ?>" class="btn btn-sm btn-<?= $u['status'] == 'aktif' ? 'secondary' : 'success' ?>" title="<?= $u['status'] == 'aktif' ? 'Nonaktifkan' : 'Aktifkan' ?>">
                                            <i class="fas fa-<?= $u['status'] == 'aktif' ? 'ban' : 'check' ?>"></i>
                                        </a>
                                        <a href="users.php?action=delete&id=<?= $u['id'] ?>" class="btn btn-sm btn-danger" title="Hapus"
                                           data-confirm-delete
                                           data-confirm-title="Hapus Data User"
                                           data-confirm-message="Yakin ingin menghapus user ini?"
                                           data-confirm-desc="Data user yang dihapus tidak dapat dikembalikan dan akan hilang secara permanen.">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile;
                    else: ?>
                        <tr><td colspan="8" class="text-center py-4 text-muted">Belum ada data pengguna</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php page_print_area_close(); ?>

<!-- Modal Form -->
<div class="modal fade" id="modalUser" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="modalTitle">Tambah Pengguna</h5>
                <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="id" id="form_id" value="0">
                <div class="form-group">
                    <label>Nama Lengkap <span class="text-danger">*</span></label>
                    <input type="text" name="nama" id="form_nama" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>NIP <span class="text-danger">*</span></label>
                    <input type="text" name="nip" id="form_nip" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Password <small class="text-muted">(kosongkan jika tidak diubah)</small></label>
                    <input type="password" name="password" id="form_password" class="form-control">
                </div>
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label>Role</label>
                        <select name="role" id="form_role" class="form-control" required>
                            <option value="guru">Guru</option>
                            <option value="admin">Admin (Kepala Laboran)</option>
                        </select>
                    </div>
                    <div class="form-group col-md-6">
                        <label>No HP</label>
                        <input type="text" name="no_hp" id="form_nohp" class="form-control">
                    </div>
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" id="form_email" class="form-control">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Simpan</button>
            </div>
        </form>
    </div>
</div>

<script>
function resetForm() {
    document.getElementById('form_id').value = '0';
    document.getElementById('form_nama').value = '';
    document.getElementById('form_nip').value = '';
    document.getElementById('form_password').value = '';
    document.getElementById('form_password').placeholder = '';
    document.getElementById('form_role').value = 'guru';
    document.getElementById('form_nohp').value = '';
    document.getElementById('form_email').value = '';
    document.getElementById('modalTitle').innerText = 'Tambah Pengguna';
}

<?php if ($edit_data): ?>
document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('form_id').value = '<?= $edit_data['id'] ?>';
    document.getElementById('form_nama').value = '<?= htmlspecialchars($edit_data['nama']) ?>';
    document.getElementById('form_nip').value = '<?= htmlspecialchars($edit_data['nip']) ?>';
    document.getElementById('form_password').placeholder = 'Kosongkan jika tidak diubah';
    document.getElementById('form_role').value = '<?= $edit_data['role'] ?>';
    document.getElementById('form_nohp').value = '<?= htmlspecialchars($edit_data['no_hp'] ?? '') ?>';
    document.getElementById('form_email').value = '<?= htmlspecialchars($edit_data['email'] ?? '') ?>';
    document.getElementById('modalTitle').innerText = 'Edit Pengguna';
    $('#modalUser').modal('show');
});
<?php endif; ?>
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
