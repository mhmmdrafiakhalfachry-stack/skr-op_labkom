<?php
$page_title = 'Maintenance';

require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/notices.php';
require_once __DIR__ . '/../config/rules_engine.php';
check_login();
check_role(['admin']);

// Create table if not exists
mysqli_query($koneksi, "CREATE TABLE IF NOT EXISTS maintenance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lab_id INT NOT NULL,
    jenis VARCHAR(50) NOT NULL DEFAULT 'rutin',
    deskripsi TEXT,
    tanggal_mulai DATE NOT NULL,
    jam_mulai TIME DEFAULT '08:00:00',
    jam_selesai TIME DEFAULT '12:00:00',
    status VARCHAR(20) NOT NULL DEFAULT 'dijadwalkan',
    catatan TEXT,
    created_by INT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (lab_id) REFERENCES laboratorium(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Handle actions
if (isset($_GET['action'])) {
    $id = (int)($_GET['id'] ?? 0);

    if ($_GET['action'] == 'complete' && $id > 0) {
        mysqli_query($koneksi, "UPDATE maintenance SET status='selesai' WHERE id=$id");
        notify_guru_maintenance($id, 'selesai');
        set_alert('success', 'Maintenance ditandai selesai! Guru telah diberitahu bahwa lab kembali dapat digunakan.');
        header("Location: maintenance.php"); exit;
    }
    if ($_GET['action'] == 'cancel' && $id > 0) {
        mysqli_query($koneksi, "UPDATE maintenance SET status='dibatalkan' WHERE id=$id");
        notify_guru_maintenance($id, 'dibatalkan');
        set_alert('success', 'Maintenance dibatalkan! Guru telah diberitahu bahwa lab kembali dapat digunakan.');
        header("Location: maintenance.php"); exit;
    }
    if ($_GET['action'] == 'delete' && $id > 0) {
        mysqli_query($koneksi, "DELETE FROM maintenance WHERE id=$id");
        set_alert('success', 'Data maintenance dihapus!');
        header("Location: maintenance.php"); exit;
    }
}

// Handle form submit
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $lab_id = (int)$_POST['lab_id'];
    $jenis = sanitize($_POST['jenis']);
    $deskripsi = sanitize($_POST['deskripsi'] ?? '');
    $tanggal = sanitize($_POST['tanggal_mulai']);
    $jm = sanitize($_POST['jam_mulai'] ?? '08:00');
    $js = sanitize($_POST['jam_selesai'] ?? '12:00');

    $sql = "INSERT INTO maintenance (lab_id, jenis, deskripsi, tanggal_mulai, jam_mulai, jam_selesai, status, created_by)
            VALUES ($lab_id, '$jenis', '$deskripsi', '$tanggal', '$jm', '$js', 'dijadwalkan', " . $_SESSION['user_id'] . ")";
    if (mysqli_query($koneksi, $sql)) {
        $maint_id = mysqli_insert_id($koneksi);
        notify_guru_maintenance($maint_id, 'dijadwalkan');
        set_alert('success', 'Jadwal maintenance berhasil ditambahkan! Notice telah dikirim ke semua guru.');
    } else {
        set_alert('danger', 'Gagal menambahkan jadwal maintenance.');
    }
    header("Location: maintenance.php"); exit;
}

auto_update_maintenance_status();

// Get data
$fq = sanitize($_GET['search'] ?? '');
$where = 'WHERE 1=1';
if ($fq) {
    $where .= page_search_where($fq, ["l.nama_lab", "m.jenis", "m.deskripsi", "m.status"]);
}

$data = mysqli_query($koneksi, "SELECT m.*, l.nama_lab FROM maintenance m LEFT JOIN laboratorium l ON m.lab_id=l.id $where ORDER BY m.tanggal_mulai DESC, m.jam_mulai");
$labs = mysqli_query($koneksi, "SELECT id, nama_lab FROM laboratorium WHERE status='aktif' ORDER BY nama_lab");

require_once __DIR__ . '/../includes/header.php';

// Jenis labels
$jenis_labels = ['rutin' => 'Rutin', 'perbaikan' => 'Perbaikan', 'upgrade' => 'Upgrade', 'pembersihan' => 'Pembersihan', 'instalasi' => 'Instalasi Software'];
$jenis_colors = ['rutin' => 'primary', 'perbaikan' => 'warning', 'upgrade' => 'info', 'pembersihan' => 'success', 'instalasi' => 'secondary'];
$status_colors = ['dijadwalkan' => 'info', 'berlangsung' => 'primary', 'selesai' => 'success', 'dibatalkan' => 'secondary'];
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-0">Maintenance Laboratorium</h4>
        <small class="text-muted">Kelola jadwal perawatan dan perbaikan lab</small>
    </div>
    <button class="btn btn-primary" data-toggle="modal" data-target="#modalMaintenance">
        <i class="fas fa-plus"></i> Tambah Jadwal
    </button>
</div>

<!-- Search -->
<?php page_search_only_bar([
    'placeholder' => 'Cari lab, jenis, deskripsi, status...',
    'search' => $fq,
    'reset_url' => 'maintenance.php',
]); ?>

<?php page_print_area_open(); ?>

<!-- Table -->
<div class="card border-0 shadow-sm" style="border-radius:12px">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Lab</th>
                        <th>Jenis</th>
                        <th>Deskripsi</th>
                        <th>Tanggal</th>
                        <th>Waktu</th>
                        <th>Status</th>
                        <th class="col-aksi no-print d-print-none">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (mysqli_num_rows($data) > 0):
                    while ($d = mysqli_fetch_assoc($data)): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($d['nama_lab'] ?? '-') ?></strong></td>
                            <td><span class="badge badge-<?= $jenis_colors[$d['jenis']] ?? 'secondary' ?>"><?= $jenis_labels[$d['jenis']] ?? ucfirst($d['jenis']) ?></span></td>
                            <td style="max-width:200px"><small><?= htmlspecialchars($d['deskripsi'] ?: '-') ?></small></td>
                            <td><?= format_tanggal($d['tanggal_mulai']) ?></td>
                            <td><?= substr($d['jam_mulai'],0,5) ?> - <?= substr($d['jam_selesai'],0,5) ?></td>
                            <td><span class="badge badge-<?= $status_colors[$d['status']] ?? 'secondary' ?>"><?= ucfirst($d['status']) ?></span></td>
                            <td class="col-aksi no-print d-print-none">
                                <?php if ($d['status'] == 'dijadwalkan' || $d['status'] == 'berlangsung'): ?>
                                    <a href="maintenance.php?action=complete&id=<?= $d['id'] ?>" class="btn btn-sm btn-success" title="Selesai"><i class="fas fa-check"></i></a>
                                    <a href="maintenance.php?action=cancel&id=<?= $d['id'] ?>" class="btn btn-sm btn-secondary" title="Batalkan"><i class="fas fa-ban"></i></a>
                                <?php endif; ?>
                                <a href="maintenance.php?action=delete&id=<?= $d['id'] ?>" class="btn btn-sm btn-danger" title="Hapus"
                                   data-confirm-delete
                                   data-confirm-title="Hapus Data Maintenance"
                                   data-confirm-message="Yakin ingin menghapus data maintenance ini?"
                                   data-confirm-desc="Data maintenance yang dihapus tidak dapat dikembalikan dan akan hilang secara permanen."><i class="fas fa-trash"></i></a>
                            </td>
                        </tr>
                    <?php endwhile;
                else: ?>
                    <tr><td colspan="7" class="text-center py-4 text-muted">Belum ada data maintenance</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php page_print_area_close(); ?>

<!-- Info Card -->
<div class="card border-0 shadow-sm mt-4 no-print" style="border-radius:12px">
    <div class="card-body">
        <h6><i class="fas fa-info-circle text-info"></i> Informasi</h6>
        <ul class="mb-0 small text-muted">
            <li><strong>Rutin</strong> - Perawatan berkala (pembersihan, pengecekan hardware)</li>
            <li><strong>Perbaikan</strong> - Memperbaiki kerusakan pada perangkat</li>
            <li><strong>Upgrade</strong> - Peningkatan hardware atau kapasitas lab</li>
            <li><strong>Instalasi Software</strong> - Install/update software di komputer lab</li>
            <li><strong>Pembersihan</strong> - Pembersihan ruangan dan perangkat</li>
            <li>Jadwal maintenance otomatis dikirim sebagai <strong>notice</strong> ke semua akun guru</li>
            <li>Lab tidak dapat diajukan selama status maintenance <em>dijadwalkan</em> atau <em>berlangsung</em></li>
            <li>Lab kembali dapat digunakan setelah admin menandai maintenance sebagai <strong>selesai</strong></li>
        </ul>
    </div>
</div>

<!-- Modal -->
<div class="modal fade" id="modalMaintenance" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fas fa-wrench"></i> Tambah Jadwal Maintenance</h5>
                <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label>Laboratorium <span class="text-danger">*</span></label>
                    <select name="lab_id" class="form-control" required>
                        <option value="">-- Pilih Lab --</option>
                        <?php while ($lb = mysqli_fetch_assoc($labs)): ?>
                            <option value="<?= $lb['id'] ?>"><?= htmlspecialchars($lb['nama_lab']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Jenis Maintenance <span class="text-danger">*</span></label>
                    <select name="jenis" class="form-control" required>
                        <?php foreach ($jenis_labels as $jk => $jl): ?>
                            <option value="<?= $jk ?>"><?= $jl ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Tanggal Mulai <span class="text-danger">*</span></label>
                    <input type="date" name="tanggal_mulai" class="form-control" required value="<?= date('Y-m-d') ?>">
                </div>
                <div class="form-row">
                    <div class="form-group col-6">
                        <label>Jam Mulai</label>
                        <input type="time" name="jam_mulai" class="form-control" value="08:00">
                    </div>
                    <div class="form-group col-6">
                        <label>Jam Selesai</label>
                        <input type="time" name="jam_selesai" class="form-control" value="12:00">
                    </div>
                </div>
                <div class="form-group">
                    <label>Deskripsi / Catatan</label>
                    <textarea name="deskripsi" class="form-control" rows="3" placeholder="Jelaskan kegiatan maintenance..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Simpan</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
