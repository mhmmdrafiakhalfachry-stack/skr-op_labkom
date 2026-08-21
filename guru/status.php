<?php

$page_title = 'Status Pengajuan';

require_once __DIR__ . '/../includes/header.php';

check_role(['guru']);



$guru_id = $_SESSION['user_id'];

$fq = sanitize($_GET['search'] ?? '');



$where = "WHERE p.guru_id = $guru_id";

$where .= page_search_where($fq, [

    "l.nama_lab", "p.kelas", "p.mata_pelajaran", "p.tujuan", "p.status",

    "CONCAT('PJN-', LPAD(p.id, 5, '0'))"

]);



$list = mysqli_query($koneksi, "

    SELECT p.*, l.nama_lab, c.waktu_check_in, c.waktu_check_out

    FROM peminjaman p

    LEFT JOIN laboratorium l ON p.lab_id = l.id

    LEFT JOIN check_in c ON c.peminjaman_id = p.id

    $where

    ORDER BY p.tanggal DESC, p.jam_mulai DESC

");

?>



<?php guru_page_header('Status Pengajuan', 'Pantau status seluruh pengajuan penggunaan lab Anda'); ?>



<?php page_search_only_bar([

    'panel' => 'guru-panel',

    'placeholder' => 'Cari lab, kelas, mapel, kode...',

    'search' => $fq,

    'reset_url' => 'status.php',

]); ?>



<?php page_print_area_open(); ?>



<div class="card guru-panel border-0">

    <div class="card-header guru-card-header">

        <h6 class="mb-0 font-weight-bold"><i class="fas fa-list-alt mr-2"></i>Daftar Pengajuan</h6>

    </div>

    <div class="card-body p-0">

        <div class="table-responsive">

            <table class="table table-hover guru-table mb-0">

                <thead>

                    <tr>

                        <th>No</th>

                        <th>Tanggal</th>

                        <th>Laboratorium</th>

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

                if (mysqli_num_rows($list) > 0):

                    mysqli_data_seek($list, 0);

                    while ($row = mysqli_fetch_assoc($list)):

                ?>

                    <tr>

                        <td><?= $no++ ?></td>

                        <td><?= format_tanggal($row['tanggal']) ?></td>

                        <td><?= htmlspecialchars($row['nama_lab'] ?? '-') ?></td>

                        <td><?= htmlspecialchars($row['kelas'] ?: '-') ?></td>

                        <td><?= htmlspecialchars($row['mata_pelajaran'] ?: '-') ?></td>

                        <td><?= substr($row['jam_mulai'], 0, 5) ?>–<?= substr($row['jam_selesai'], 0, 5) ?></td>

                        <td><?= status_badge($row['status']) ?><?php if ($row['status'] == 'ditolak' && is_bentrok_alasan($row['alasan_penolakan'])): ?><br><small class="text-danger font-weight-bold"><i class="fas fa-exclamation-triangle"></i> Bentrok</small><?php endif; ?></td>

                        <td><?= $row['waktu_check_in'] ? '<small class="text-success"><i class="fas fa-check"></i> ' . substr($row['waktu_check_in'], 11, 5) . '</small>' : '<small class="text-warning"><i class="fas fa-clock"></i> Belum</small>' ?></td>

                        <td><?= page_checkout_cell($row['waktu_check_out'] ?? null) ?></td>

                        <td class="text-center text-nowrap col-aksi no-print d-print-none">

                            <button class="btn btn-sm btn-outline-primary" data-toggle="modal" data-target="#m<?= $row['id'] ?>" title="Detail"><i class="fas fa-eye"></i></button>

                            <?php if ($row['status'] == 'diterima' && empty($row['waktu_check_in']) && $row['tanggal'] == date('Y-m-d')): ?>

                            <a href="checkin.php" class="btn btn-sm btn-outline-success" title="Check-In"><i class="fas fa-sign-in-alt"></i></a>

                            <?php endif; ?>

                        </td>

                    </tr>

                <?php endwhile; else: ?>

                    <tr><td colspan="10" class="text-center py-5 text-muted">Tidak ada data pengajuan</td></tr>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

    </div>

</div>



<?php page_print_area_close(); ?>



<?php

if (mysqli_num_rows($list) > 0):

    mysqli_data_seek($list, 0);

    while ($d = mysqli_fetch_assoc($list)):

        $id = $d['id'];

        $ci = has_checkin($id);

?>

<div class="modal fade" id="m<?= $id ?>" tabindex="-1">

    <div class="modal-dialog modal-lg">

        <div class="modal-content">

            <div class="modal-header bg-primary text-white py-2">

                <h6 class="modal-title mb-0">Detail — PJN-<?= str_pad($id, 5, '0', STR_PAD_LEFT) ?></h6>

                <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>

            </div>

            <div class="modal-body">

                <div class="row">

                    <div class="col-md-6">

                        <table class="table table-sm table-borderless">

                            <tr><th class="text-muted">Lab</th><td><strong><?= htmlspecialchars($d['nama_lab'] ?? '-') ?></strong></td></tr>

                            <tr><th class="text-muted">Tanggal</th><td><?= format_tanggal($d['tanggal']) ?></td></tr>

                            <tr><th class="text-muted">Waktu</th><td><?= substr($d['jam_mulai'], 0, 5) ?> – <?= substr($d['jam_selesai'], 0, 5) ?></td></tr>

                            <tr><th class="text-muted">Kelas</th><td><?= htmlspecialchars($d['kelas'] ?: '-') ?></td></tr>

                            <tr><th class="text-muted">Mapel</th><td><?= htmlspecialchars($d['mata_pelajaran'] ?: '-') ?></td></tr>

                        </table>

                    </div>

                    <div class="col-md-6">

                        <table class="table table-sm table-borderless">

                            <tr><th class="text-muted">Status</th><td><?= status_badge($d['status']) ?></td></tr>

                            <tr><th class="text-muted">Check-in</th><td><?= checkin_badge($ci) ?></td></tr>

                            <tr><th class="text-muted">Check-out</th><td><?= checkout_badge($ci) ?></td></tr>

                            <tr><th class="text-muted">Diajukan</th><td><?= format_datetime($d['created_at']) ?></td></tr>

                        </table>

                    </div>

                </div>

                <?php if ($d['tujuan']): ?>

                <div class="p-3 mt-2" style="background:#f8f9fa;border-radius:8px">

                    <strong class="text-muted small d-block mb-1">Tujuan</strong><?= nl2br(htmlspecialchars($d['tujuan'])) ?>

                </div>

                <?php endif; ?>

                <?php if ($d['alasan_penolakan']): ?>

                <?= render_penolakan_notice($d['alasan_penolakan'], $d['rekomendasi_jadwal'] ?? '') ?>

                <?php elseif ($d['rekomendasi_jadwal']): ?>

                <div class="alert alert-info py-2 mb-0 mt-2 alert-persistent"><strong>Rekomendasi:</strong> <small><?= htmlspecialchars($d['rekomendasi_jadwal']) ?></small></div>

                <?php endif; ?>

            </div>

        </div>

    </div>

</div>

<?php endwhile; endif; ?>



<?php require_once __DIR__ . '/../includes/footer.php'; ?>

