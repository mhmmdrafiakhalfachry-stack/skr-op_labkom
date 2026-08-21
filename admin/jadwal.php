<?php
$page_title = 'Jadwal Praktikum';
require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../config/auth.php';
check_login();
check_role(['admin']);

if (isset($_GET['delete'])) {
    mysqli_query($koneksi, "DELETE FROM jadwal WHERE id=" . (int)$_GET['delete']);
    set_alert('success', 'Jadwal dihapus!');
    header("Location: jadwal.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    $lab_id = (int)$_POST['lab_id'];
    $hari = sanitize($_POST['hari']);
    $jm = sanitize($_POST['jam_mulai']);
    $js = sanitize($_POST['jam_selesai']);
    $mapel = sanitize($_POST['mata_pelajaran']);
    $kelas = sanitize($_POST['kelas']);
    $guru_id = (int)($_POST['guru_id'] ?? 0);
    $ket = sanitize($_POST['keterangan'] ?? '');
    if ($id > 0) {
        mysqli_query($koneksi, "UPDATE jadwal SET lab_id=$lab_id,hari='$hari',jam_mulai='$jm',jam_selesai='$js',mata_pelajaran='$mapel',kelas='$kelas',guru_id=" . ($guru_id ?: 'NULL') . ",keterangan='$ket' WHERE id=$id");
        set_alert('success', 'Jadwal diperbarui!');
    } else {
        mysqli_query($koneksi, "INSERT INTO jadwal (lab_id,hari,jam_mulai,jam_selesai,mata_pelajaran,kelas,guru_id,keterangan) VALUES ($lab_id,'$hari','$jm','$js','$mapel','$kelas'," . ($guru_id ?: 'NULL') . ",'$ket')");
        set_alert('success', 'Jadwal ditambahkan!');
    }
    header("Location: jadwal.php");
    exit;
}

$edit_data = null;
if (isset($_GET['edit'])) $edit_data = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT * FROM jadwal WHERE id=" . (int)$_GET['edit']));

$fq = sanitize($_GET['search'] ?? '');
$where = "WHERE 1=1";
$where .= page_search_where($fq, ["j.hari", "l.nama_lab", "u.nama", "j.kelas", "j.mata_pelajaran", "j.keterangan"]);

$hari_order = "FIELD(j.hari,'Senin','Selasa','Rabu','Kamis','Jumat','Sabtu')";
$jadwals = mysqli_query($koneksi, "SELECT j.*, l.nama_lab, u.nama as nama_guru FROM jadwal j LEFT JOIN laboratorium l ON j.lab_id=l.id LEFT JOIN users u ON j.guru_id=u.id $where ORDER BY $hari_order, j.jam_mulai");
$gurus = mysqli_query($koneksi, "SELECT id, nama FROM users WHERE role='guru' AND status='aktif' ORDER BY nama");

require_once __DIR__ . '/../includes/header.php';
?>

<?php admin_page_header('Jadwal Praktikum', 'Kelola jadwal rutin praktikum laboratorium', '<button class="btn btn-primary" data-toggle="modal" data-target="#modal" onclick="resetForm()"><i class="fas fa-plus"></i> Tambah Jadwal</button>'); ?>

<?php page_search_only_bar([
    'placeholder' => 'Cari hari, lab, guru, mapel...',
    'search' => $fq,
    'reset_url' => 'jadwal.php',
]); ?>

<?php page_print_area_open(); ?>

<div class="card admin-panel border-0">
    <div class="card-header bg-white border-bottom py-3">
        <h6 class="mb-0 font-weight-bold"><i class="fas fa-calendar-alt text-primary mr-2"></i>Daftar Jadwal Praktikum</h6>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover admin-table mb-0">
                <thead>
                    <tr><th>#</th><th>Hari</th><th>Jam</th><th>Lab</th><th>Mapel</th><th>Kelas</th><th>Guru</th><th class="text-center col-aksi no-print d-print-none">Aksi</th></tr>
                </thead>
                <tbody>
                <?php $no = 1; while ($j = mysqli_fetch_assoc($jadwals)): ?>
                    <tr>
                        <td><?= $no++ ?></td>
                        <td><strong><?= $j['hari'] ?></strong></td>
                        <td><?= substr($j['jam_mulai'], 0, 5) ?>–<?= substr($j['jam_selesai'], 0, 5) ?></td>
                        <td><?= htmlspecialchars($j['nama_lab'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($j['mata_pelajaran']) ?></td>
                        <td><?= badge($j['kelas'], 'primary') ?></td>
                        <td><?= htmlspecialchars($j['nama_guru'] ?? '-') ?></td>
                        <td class="text-center col-aksi no-print d-print-none">
                            <a href="jadwal.php?edit=<?= $j['id'] ?>" class="btn btn-sm btn-outline-warning"><i class="fas fa-edit"></i></a>
                            <a href="jadwal.php?delete=<?= $j['id'] ?>" class="btn btn-sm btn-outline-danger"
                               data-confirm-delete
                               data-confirm-title="Hapus Data Jadwal"
                               data-confirm-message="Yakin ingin menghapus jadwal ini?"
                               data-confirm-desc="Data jadwal yang dihapus tidak dapat dikembalikan dan akan hilang secara permanen."><i class="fas fa-trash"></i></a>
                        </td>
                    </tr>
                <?php endwhile; if (!mysqli_num_rows($jadwals)): ?>
                    <tr><td colspan="8" class="text-center py-5 text-muted">Belum ada jadwal praktikum</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php page_print_area_close(); ?>

<div class="modal fade" id="modal"><div class="modal-dialog"><form method="POST" class="modal-content">
<div class="modal-header bg-primary text-white"><h5 class="modal-title" id="mt">Tambah Jadwal</h5><button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button></div>
<div class="modal-body"><input type="hidden" name="id" id="f_id" value="0">
<div class="form-row"><div class="form-group col-md-6"><label class="font-weight-bold">Laboratorium <span class="text-danger">*</span></label><?= lab_dropdown() ?></div>
<div class="form-group col-md-6"><label class="font-weight-bold">Hari <span class="text-danger">*</span></label><select name="hari" id="f_hari" class="form-control"><?php foreach (['Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'] as $h): ?><option value="<?= $h ?>"><?= $h ?></option><?php endforeach; ?></select></div></div>
<div class="form-row"><div class="form-group col-md-6"><label class="font-weight-bold">Jam Mulai <span class="text-danger">*</span></label><input type="time" name="jam_mulai" id="f_jm" class="form-control" required></div>
<div class="form-group col-md-6"><label class="font-weight-bold">Jam Selesai <span class="text-danger">*</span></label><input type="time" name="jam_selesai" id="f_js" class="form-control" required></div></div>
<div class="form-group"><label class="font-weight-bold">Mata Pelajaran <span class="text-danger">*</span></label><input type="text" name="mata_pelajaran" id="f_mapel" class="form-control" required></div>
<div class="form-row"><div class="form-group col-md-6"><label class="font-weight-bold">Kelas <span class="text-danger">*</span></label><input type="text" name="kelas" id="f_kelas" class="form-control" required></div>
<div class="form-group col-md-6"><label class="font-weight-bold">Guru</label><select name="guru_id" id="f_guru" class="form-control"><option value="">-- Pilih --</option><?php mysqli_data_seek($gurus, 0); while ($g = mysqli_fetch_assoc($gurus)): ?><option value="<?= $g['id'] ?>"><?= htmlspecialchars($g['nama']) ?></option><?php endwhile; ?></select></div></div>
<div class="form-group"><label class="font-weight-bold">Keterangan</label><textarea name="keterangan" id="f_ket" class="form-control" rows="2"></textarea></div>
</div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button><button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Simpan</button></div>
</form></div></div>
<script>
function resetForm(){document.getElementById('f_id').value='0';document.getElementById('f_hari').value='Senin';document.getElementById('f_jm').value='';document.getElementById('f_js').value='';document.getElementById('f_mapel').value='';document.getElementById('f_kelas').value='';document.getElementById('f_guru').value='';document.getElementById('f_ket').value='';document.getElementById('mt').innerText='Tambah Jadwal';}
<?php if($edit_data):?>
document.addEventListener('DOMContentLoaded',function(){document.getElementById('f_id').value='<?=$edit_data['id']?>';document.querySelector('[name=lab_id]').value='<?=$edit_data['lab_id']?>';
document.getElementById('f_hari').value='<?=$edit_data['hari']?>';document.getElementById('f_jm').value='<?=$edit_data['jam_mulai']?>';document.getElementById('f_js').value='<?=$edit_data['jam_selesai']?>';
document.getElementById('f_mapel').value='<?=htmlspecialchars($edit_data['mata_pelajaran'], ENT_QUOTES)?>';document.getElementById('f_kelas').value='<?=htmlspecialchars($edit_data['kelas'], ENT_QUOTES)?>';
document.getElementById('f_guru').value='<?=$edit_data['guru_id']?:''?>';document.getElementById('f_ket').value='<?=htmlspecialchars($edit_data['keterangan']??'', ENT_QUOTES)?>';
document.getElementById('mt').innerText='Edit Jadwal';$('#modal').modal('show');});<?php endif;?></script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
