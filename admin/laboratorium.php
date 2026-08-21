<?php
$page_title = 'Data Laboratorium';
require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../config/auth.php';
check_login();
check_role(['admin']);

if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    mysqli_query($koneksi, "DELETE FROM laboratorium WHERE id=$id");
    set_alert('success', 'Laboratorium berhasil dihapus!');
    header("Location: laboratorium.php"); exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    $nama = sanitize($_POST['nama_lab']);
    $lokasi = sanitize($_POST['lokasi'] ?? '');
    $kapasitas = (int)($_POST['kapasitas'] ?? 30);
    $fasilitas = sanitize($_POST['fasilitas'] ?? '');
    $status = sanitize($_POST['status']);

    if ($id > 0) {
        mysqli_query($koneksi, "UPDATE laboratorium SET nama_lab='$nama', lokasi='$lokasi', kapasitas=$kapasitas, fasilitas='$fasilitas', status='$status' WHERE id=$id");
        set_alert('success', 'Data laboratorium berhasil diperbarui!');
    } else {
        mysqli_query($koneksi, "INSERT INTO laboratorium (nama_lab, lokasi, kapasitas, fasilitas, status) VALUES ('$nama','$lokasi',$kapasitas,'$fasilitas','$status')");
        set_alert('success', 'Laboratorium baru berhasil ditambahkan!');
    }
    header("Location: laboratorium.php"); exit;
}

$edit_data = null;
if (isset($_GET['edit'])) $edit_data = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT * FROM laboratorium WHERE id=".(int)$_GET['edit']));

$fq = sanitize($_GET['search'] ?? '');
$where = 'WHERE 1=1';
$where .= page_search_where($fq, ["nama_lab", "lokasi", "fasilitas", "status"]);

$labs = mysqli_query($koneksi, "SELECT * FROM laboratorium $where ORDER BY nama_lab");

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 no-print">
    <div>
        <h4 class="mb-0 font-weight-bold">Data Laboratorium</h4>
        <small class="text-muted">Kelola data laboratorium komputer</small>
    </div>
    <div>
        <button class="btn btn-primary" data-toggle="modal" data-target="#modal" onclick="resetForm()"><i class="fas fa-plus"></i> Tambah Lab</button>
    </div>
</div>

<div class="card admin-panel border-0 mb-3 no-print">
    <div class="card-body py-3 admin-filter-bar">
        <form method="GET" class="form-inline flex-wrap align-items-center">
            <input type="text" name="search" class="form-control form-control-sm mr-2 mb-1" style="min-width:220px" placeholder="Cari nama lab, lokasi..." value="<?= htmlspecialchars($fq) ?>">
            <button type="submit" class="btn btn-sm btn-primary mr-2 mb-1"><i class="fas fa-search"></i> Cari</button>
            <?php if ($fq): ?><a href="laboratorium.php" class="btn btn-sm btn-outline-secondary mb-1 mr-2">Reset</a><?php endif; ?>
            <?= page_print_button() ?>
        </form>
    </div>
</div>

<?php page_print_area_open(); ?>

<div class="row">
<?php while ($l = mysqli_fetch_assoc($labs)): ?>
    <div class="col-md-4 mb-3">
        <div class="card admin-panel border-0 h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <h5 class="mb-0 font-weight-bold"><?= htmlspecialchars($l['nama_lab']) ?></h5>
                    <?= badge($l['status'], $l['status']=='aktif'?'success':'secondary') ?>
                </div>
                <p class="text-muted mb-3" style="font-size:14px"><i class="fas fa-map-marker-alt mr-1"></i> <?= htmlspecialchars($l['lokasi'] ?: '-') ?></p>
                <div class="text-center mb-3 py-2" style="background:#f8f9fa;border-radius:8px">
                    <strong style="font-size:24px;color:#1e3c72"><?= $l['kapasitas'] ?></strong>
                    <br><small class="text-muted">Kapasitas Siswa</small>
                </div>
                <?php if ($l['fasilitas']): ?>
                    <p class="text-muted mb-3" style="font-size:13px"><i class="fas fa-info-circle mr-1"></i> <?= htmlspecialchars($l['fasilitas']) ?></p>
                <?php endif; ?>
                <div class="d-flex justify-content-end print-actions">
                    <a href="laboratorium.php?edit=<?= $l['id'] ?>" class="btn btn-sm btn-outline-warning mr-1"><i class="fas fa-edit"></i> Edit</a>
                    <a href="laboratorium.php?delete=<?= $l['id'] ?>" class="btn btn-sm btn-outline-danger"
                       data-confirm-delete
                       data-confirm-title="Hapus Data Laboratorium"
                       data-confirm-message="Yakin ingin menghapus laboratorium ini?"
                       data-confirm-desc="Data laboratorium yang dihapus tidak dapat dikembalikan dan akan hilang secara permanen."><i class="fas fa-trash"></i></a>
                </div>
            </div>
        </div>
    </div>
<?php endwhile; ?>
</div>

<?php page_print_area_close(); ?>

<div class="modal fade" id="modal"><div class="modal-dialog"><form method="POST" class="modal-content">
    <div class="modal-header bg-primary text-white"><h5 class="modal-title" id="modalTitle">Tambah Laboratorium</h5><button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button></div>
    <div class="modal-body">
        <input type="hidden" name="id" id="f_id" value="0">
        <div class="form-group"><label>Nama Lab <span class="text-danger">*</span></label><input type="text" name="nama_lab" id="f_nama" class="form-control" required></div>
        <div class="form-row">
            <div class="form-group col-md-8"><label>Lokasi</label><input type="text" name="lokasi" id="f_lokasi" class="form-control"></div>
            <div class="form-group col-md-4"><label>Kapasitas</label><input type="number" name="kapasitas" id="f_kap" class="form-control" value="30" min="1"></div>
        </div>
        <div class="form-group"><label>Fasilitas</label><textarea name="fasilitas" id="f_fas" class="form-control" rows="2"></textarea></div>
        <div class="form-group"><label>Status</label><select name="status" id="f_status" class="form-control"><option value="aktif">Aktif</option><option value="nonaktif">Nonaktif</option></select></div>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button><button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Simpan</button></div>
</form></div></div>

<script>
function resetForm(){
    document.getElementById('f_id').value='0';
    document.getElementById('f_nama').value='';
    document.getElementById('f_lokasi').value='';
    document.getElementById('f_kap').value=30;
    document.getElementById('f_fas').value='';
    document.getElementById('f_status').value='aktif';
    document.getElementById('modalTitle').innerText='Tambah Laboratorium';
}
<?php if($edit_data): ?>
document.addEventListener('DOMContentLoaded',function(){
    document.getElementById('f_id').value='<?=$edit_data['id']?>';
    document.getElementById('f_nama').value='<?=htmlspecialchars($edit_data['nama_lab'], ENT_QUOTES)?>';
    document.getElementById('f_lokasi').value='<?=htmlspecialchars($edit_data['lokasi']??'', ENT_QUOTES)?>';
    document.getElementById('f_kap').value='<?=$edit_data['kapasitas']?>';
    document.getElementById('f_fas').value='<?=htmlspecialchars($edit_data['fasilitas']??'', ENT_QUOTES)?>';
    document.getElementById('f_status').value='<?=$edit_data['status']?>';
    document.getElementById('modalTitle').innerText='Edit Laboratorium';
    $('#modal').modal('show');
});
<?php endif; ?>
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
