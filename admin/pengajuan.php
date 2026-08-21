<?php
$page_title = 'Pengajuan Penggunaan';
require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../config/auth.php';
check_login();
check_role(['admin']);

if (isset($_GET['delete'])) {
    mysqli_query($koneksi, "DELETE FROM peminjaman WHERE id=" . (int)$_GET['delete']);
    set_alert('success', 'Data pengajuan dihapus!');
    header("Location: pengajuan.php");
    exit;
}

$fq = sanitize($_GET['search'] ?? '');

$where = "WHERE 1=1";
$where .= page_search_where($fq, [
    "l.nama_lab", "u.nama", "p.kelas", "p.mata_pelajaran", "p.tujuan", "p.status",
    "CONCAT('PJN-', LPAD(p.id, 5, '0'))"
]);

$data = mysqli_query($koneksi, "
    SELECT p.*, l.nama_lab, u.nama, c.waktu_check_in, c.waktu_check_out
    FROM peminjaman p
    LEFT JOIN laboratorium l ON p.lab_id=l.id
    LEFT JOIN users u ON p.guru_id=u.id
    LEFT JOIN check_in c ON c.peminjaman_id=p.id
    $where
    ORDER BY p.tanggal DESC, p.jam_mulai DESC
");

require_once __DIR__ . '/../includes/header.php';
?>

<div class="admin-page-header mb-4">
    <div class="d-flex justify-content-between align-items-start flex-wrap">
        <div>
            <h4 class="mb-1 font-weight-bold text-dark">Pengajuan Penggunaan Lab</h4>
            <p class="text-muted mb-0 admin-page-subtitle">Seluruh pengajuan diproses otomatis oleh Rule-Based System</p>
        </div>
    </div>
</div>

<?php page_search_only_bar([
    'placeholder' => 'Cari guru, lab, kelas, kode...',
    'search' => $fq,
    'reset_url' => 'pengajuan.php',
]); ?>

<?php page_print_area_open(); ?>

<div class="card admin-panel border-0">
    <div class="card-header bg-white border-bottom py-3">
        <h6 class="mb-0 font-weight-bold"><i class="fas fa-clipboard-list text-primary mr-2"></i>Data Pengajuan Penggunaan</h6>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover admin-table mb-0">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Tanggal</th>
                        <th>Laboratorium</th>
                        <th>Guru</th>
                        <th>Kelas</th>
                        <th>Mapel</th>
                        <th>Waktu</th>
                        <th>Status</th>
                        <th>Check-in</th>
                        <th>Check-out</th>
                        <th class="text-center col-aksi no-print d-print-none">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                $no = 1;
                if (mysqli_num_rows($data) > 0):
                    mysqli_data_seek($data, 0);
                    while ($p = mysqli_fetch_assoc($data)):
                        $ci = has_checkin($p['id']);
                ?>
                    <tr>
                        <td><?= $no++ ?></td>
                        <td><?= format_tanggal($p['tanggal']) ?></td>
                        <td><?= htmlspecialchars($p['nama_lab'] ?? '-') ?></td>
                        <td><strong><?= htmlspecialchars($p['nama'] ?? '-') ?></strong></td>
                        <td><?= htmlspecialchars($p['kelas'] ?: '-') ?></td>
                        <td><?= htmlspecialchars($p['mata_pelajaran'] ?: '-') ?></td>
                        <td><?= substr($p['jam_mulai'], 0, 5) ?>–<?= substr($p['jam_selesai'], 0, 5) ?></td>
                        <td><?= status_badge($p['status']) ?></td>
                        <td><?= $p['waktu_check_in'] ? '<small class="text-success"><i class="fas fa-check"></i> ' . substr($p['waktu_check_in'], 11, 5) . '</small>' : '<small class="text-warning"><i class="fas fa-clock"></i> Belum</small>' ?></td>
                        <td><?= page_checkout_cell($p['waktu_check_out'] ?? null) ?></td>
                        <td class="text-center text-nowrap col-aksi no-print d-print-none">
                            <button class="btn btn-sm btn-outline-primary" data-toggle="modal" data-target="#detail<?= $p['id'] ?>" title="Detail">
                                <i class="fas fa-eye"></i>
                            </button>
                            <a href="pengajuan.php?delete=<?= $p['id'] ?>" class="btn btn-sm btn-outline-danger" title="Hapus"
                               data-confirm-delete
                               data-confirm-title="Hapus Data Pengajuan"
                               data-confirm-message="Yakin ingin menghapus pengajuan ini?"
                               data-confirm-desc="Data pengajuan yang dihapus tidak dapat dikembalikan dan akan hilang secara permanen.">
                                <i class="fas fa-trash"></i>
                            </a>
                        </td>
                    </tr>
                <?php endwhile; else: ?>
                    <tr><td colspan="11" class="text-center py-5 text-muted">Tidak ada data pengajuan</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php page_print_area_close(); ?>

<?php
if (mysqli_num_rows($data) > 0):
    mysqli_data_seek($data, 0);
    while ($p = mysqli_fetch_assoc($data)):
        $ci = has_checkin($p['id']);
?>
<div class="modal fade" id="detail<?= $p['id'] ?>" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fas fa-file-alt"></i> Detail Pengajuan — PJN-<?= str_pad($p['id'], 5, '0', STR_PAD_LEFT) ?></h5>
                <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6">
                        <table class="table table-sm table-borderless">
                            <tr><th class="text-muted" style="width:140px">Tanggal</th><td><?= format_tanggal($p['tanggal']) ?></td></tr>
                            <tr><th class="text-muted">Laboratorium</th><td><strong><?= htmlspecialchars($p['nama_lab'] ?? '-') ?></strong></td></tr>
                            <tr><th class="text-muted">Guru</th><td><?= htmlspecialchars($p['nama'] ?? '-') ?></td></tr>
                            <tr><th class="text-muted">Kelas</th><td><?= htmlspecialchars($p['kelas'] ?: '-') ?></td></tr>
                            <tr><th class="text-muted">Mata Pelajaran</th><td><?= htmlspecialchars($p['mata_pelajaran'] ?: '-') ?></td></tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <table class="table table-sm table-borderless">
                            <tr><th class="text-muted" style="width:140px">Waktu</th><td><?= substr($p['jam_mulai'], 0, 5) ?> – <?= substr($p['jam_selesai'], 0, 5) ?></td></tr>
                            <tr><th class="text-muted">Status</th><td><?= status_badge($p['status']) ?></td></tr>
                            <tr><th class="text-muted">Check-in</th><td><?= checkin_badge($ci) ?></td></tr>
                            <?php if ($ci && !empty($ci['waktu_check_out'])): ?>
                            <tr><th class="text-muted">Check-out</th><td><?= format_datetime($ci['waktu_check_out']) ?></td></tr>
                            <?php endif; ?>
                            <tr><th class="text-muted">Diajukan</th><td><?= format_datetime($p['created_at']) ?></td></tr>
                        </table>
                    </div>
                </div>
                <?php if (!empty($p['tujuan'])): ?>
                <div class="mt-2 p-3" style="background:#f8f9fa;border-radius:8px">
                    <strong class="text-muted d-block mb-1" style="font-size:13px">Tujuan Penggunaan</strong>
                    <?= nl2br(htmlspecialchars($p['tujuan'])) ?>
                </div>
                <?php endif; ?>
                <?php if (!empty($p['alasan_penolakan'])): ?>
                <div class="mt-3 alert alert-danger mb-0 py-2">
                    <strong>Alasan Penolakan:</strong> <?= htmlspecialchars($p['alasan_penolakan']) ?>
                </div>
                <?php endif; ?>
                <?php if (!empty($p['rekomendasi_jadwal'])): ?>
                <div class="mt-2 alert alert-info mb-0 py-2">
                    <strong>Rekomendasi:</strong> <?= htmlspecialchars($p['rekomendasi_jadwal']) ?>
                </div>
                <?php endif; ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>
<?php
    endwhile;
endif;
?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
