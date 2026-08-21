<?php
$page_title = 'Data Komputer';
require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../config/auth.php';
check_login();
check_role(['admin']);

if (isset($_GET['delete'])) {
    mysqli_query($koneksi, "DELETE FROM komputer WHERE id=".(int)$_GET['delete']);
    set_alert('success','Data komputer dihapus!'); header("Location: komputer.php"); exit;
}

if ($_SERVER['REQUEST_METHOD']=='POST') {
    $id=(int)($_POST['id']??0); $lab_id=(int)$_POST['lab_id']; $no_pc=sanitize($_POST['no_pc']);
    $nama=sanitize($_POST['nama_pc']); $merk=sanitize($_POST['merk']??''); $spec=sanitize($_POST['spesifikasi']??'');
    $kondisi=sanitize($_POST['kondisi']); $lokasi=sanitize($_POST['lokasi']??''); $tahun=(int)($_POST['tahun_pengadaan']??0);
    $ket=sanitize($_POST['keterangan']??'');
    if($id>0){
        mysqli_query($koneksi,"UPDATE komputer SET lab_id=$lab_id,no_pc='$no_pc',nama_pc='$nama',merk='$merk',spesifikasi='$spec',kondisi='$kondisi',lokasi='$lokasi',tahun_pengadaan=$tahun,keterangan='$ket' WHERE id=$id");
        set_alert('success','Data diperbarui!');
    } else {
        mysqli_query($koneksi,"INSERT INTO komputer (lab_id,no_pc,nama_pc,merk,spesifikasi,kondisi,lokasi,tahun_pengadaan,keterangan) VALUES ($lab_id,'$no_pc','$nama','$merk','$spec','$kondisi','$lokasi',$tahun,'$ket')");
        set_alert('success','Komputer ditambahkan!');
    }
    header("Location: komputer.php"); exit;
}

$edit_data=null;
if(isset($_GET['edit'])) $edit_data=mysqli_fetch_assoc(mysqli_query($koneksi,"SELECT * FROM komputer WHERE id=".(int)$_GET['edit']));

$filter_lab=(int)($_GET['lab_id']??0);
$where=$filter_lab?"WHERE k.lab_id=$filter_lab":'';
$pcs=mysqli_query($koneksi,"SELECT k.*,l.nama_lab FROM komputer k LEFT JOIN laboratorium l ON k.lab_id=l.id $where ORDER BY l.nama_lab,k.no_pc");

require_once __DIR__ . '/../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div><h4 class="mb-0">Data Komputer</h4><small class="text-muted">Kelola PC per laboratorium</small></div>
    <button class="btn btn-primary" data-toggle="modal" data-target="#modal" onclick="resetForm()"><i class="fas fa-plus"></i> Tambah PC</button>
</div>
<div class="card mb-3"><div class="card-body"><form method="GET" class="form-inline"><label class="mr-2">Filter Lab:</label>
    <select name="lab_id" class="form-control mr-2" onchange="this.form.submit()"><option value="">Semua</option>
    <?php foreach(get_lab_options() as $lb): ?><option value="<?=$lb['id']?>" <?=$filter_lab==$lb['id']?'selected':''?>><?=htmlspecialchars($lb['nama_lab'])?></option><?php endforeach; ?>
    </select><?php if($filter_lab):?><a href="komputer.php" class="btn btn-outline-secondary btn-sm">Reset</a><?php endif;?></form></div></div>
<div class="card table-card"><div class="card-body p-0"><div class="table-responsive"><table class="table table-hover mb-0">
<thead><tr><th>#</th><th>Lab</th><th>No PC</th><th>Nama</th><th>Merk</th><th>Kondisi</th><th>Lokasi</th><th>Aksi</th></tr></thead>
<tbody><?php $no=1; while($k=mysqli_fetch_assoc($pcs)):?>
<tr><td><?=$no++?></td><td><small><?=htmlspecialchars($k['nama_lab']??'-')?></small></td><td><strong><?=htmlspecialchars($k['no_pc'])?></strong></td><td><?=htmlspecialchars($k['nama_pc'])?></td><td><?=htmlspecialchars($k['merk']?:'-')?></td><td><?=kondisi_badge($k['kondisi'])?></td><td><?=htmlspecialchars($k['lokasi']?:'-')?></td>
<td><a href="komputer.php?edit=<?=$k['id']?>" class="btn btn-sm btn-warning"><i class="fas fa-edit"></i></a> <a href="komputer.php?delete=<?=$k['id']?>" class="btn btn-sm btn-danger"
   data-confirm-delete
   data-confirm-title="Hapus Data Komputer"
   data-confirm-message="Yakin ingin menghapus komputer ini?"
   data-confirm-desc="Data komputer yang dihapus tidak dapat dikembalikan dan akan hilang secara permanen."><i class="fas fa-trash"></i></a></td></tr>
<?php endwhile; if(!mysqli_num_rows($pcs)):?><tr><td colspan="8" class="text-center py-4 text-muted">Belum ada data</td></tr><?php endif;?></tbody></table></div></div></div>

<div class="modal fade" id="modal"><div class="modal-dialog modal-lg"><form method="POST" class="modal-content">
<div class="modal-header bg-primary text-white"><h5 class="modal-title" id="mt">Tambah Komputer</h5><button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button></div>
<div class="modal-body"><input type="hidden" name="id" id="f_id" value="0">
<div class="form-row">
<div class="form-group col-md-4"><label>Laboratorium *</label><?=lab_dropdown('lab_id','','form-control')?></div>
<div class="form-group col-md-4"><label>No PC *</label><input type="text" name="no_pc" id="f_nopc" class="form-control" required></div>
<div class="form-group col-md-4"><label>Nama PC *</label><input type="text" name="nama_pc" id="f_nama" class="form-control" required></div>
</div>
<div class="form-row">
<div class="form-group col-md-4"><label>Merk</label><input type="text" name="merk" id="f_merk" class="form-control"></div>
<div class="form-group col-md-4"><label>Kondisi</label><select name="kondisi" id="f_kondisi" class="form-control"><option value="baik">Baik</option><option value="rusak_ringan">Rusak Ringan</option><option value="rusak_berat">Rusak Berat</option><option value="perbaikan">Perbaikan</option></select></div>
<div class="form-group col-md-4"><label>Lokasi</label><input type="text" name="lokasi" id="f_lokasi" class="form-control"></div>
</div>
<div class="form-group"><label>Spesifikasi</label><textarea name="spesifikasi" id="f_spec" class="form-control" rows="2"></textarea></div>
<div class="form-row">
<div class="form-group col-md-6"><label>Tahun Pengadaan</label><input type="number" name="tahun_pengadaan" id="f_tahun" class="form-control" value="<?=date('Y')?>"></div>
<div class="form-group col-md-6"><label>Keterangan</label><input type="text" name="keterangan" id="f_ket" class="form-control"></div>
</div></div>
<div class="modal-footer"><button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button><button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Simpan</button></div>
</form></div></div>
<script>
function resetForm(){document.getElementById('f_id').value='0';document.getElementById('f_nopc').value='';document.getElementById('f_nama').value='';document.getElementById('f_merk').value='';document.getElementById('f_spec').value='';document.getElementById('f_kondisi').value='baik';document.getElementById('f_lokasi').value='';document.getElementById('f_tahun').value='<?=date('Y')?>';document.getElementById('f_ket').value='';document.getElementById('mt').innerText='Tambah Komputer';}
<?php if($edit_data):?>
document.addEventListener('DOMContentLoaded',function(){
document.getElementById('f_id').value='<?=$edit_data['id']?>';document.querySelector('[name=lab_id]').value='<?=$edit_data['lab_id']?>';
document.getElementById('f_nopc').value='<?=htmlspecialchars($edit_data['no_pc'])?>';document.getElementById('f_nama').value='<?=htmlspecialchars($edit_data['nama_pc'])?>';
document.getElementById('f_merk').value='<?=htmlspecialchars($edit_data['merk']??'')?>';document.getElementById('f_spec').value='<?=htmlspecialchars($edit_data['spesifikasi']??'')?>';
document.getElementById('f_kondisi').value='<?=$edit_data['kondisi']?>';document.getElementById('f_lokasi').value='<?=htmlspecialchars($edit_data['lokasi']??'')?>';
document.getElementById('f_tahun').value='<?=$edit_data['tahun_pengadaan']?:date('Y')?>';document.getElementById('f_ket').value='<?=htmlspecialchars($edit_data['keterangan']??'')?>';
document.getElementById('mt').innerText='Edit Komputer';$('#modal').modal('show');});
<?php endif;?></script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
