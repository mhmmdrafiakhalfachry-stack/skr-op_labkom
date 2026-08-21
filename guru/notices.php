<?php

$page_title = 'Notice';

require_once __DIR__ . '/../includes/header.php';

check_role(['guru']);



$guru_id = $_SESSION['user_id'];



if (isset($_GET['read']) && $_GET['read'] === 'all') {

    mark_all_notices_read($guru_id);

    set_alert('success', 'Semua notice ditandai sudah dibaca.');

    header('Location: notices.php');

    exit;

}



if (isset($_GET['read_id'])) {

    mark_notice_read((int)$_GET['read_id'], $guru_id);

    header('Location: notices.php');

    exit;

}



$notices = get_guru_notices($guru_id, 50);

$unread = count_unread_notices($guru_id);

$maintenance_aktif = get_maintenance_labs();

?>



<?php guru_page_header('Notice & Pengumuman', 'Informasi maintenance lab dan pengumuman dari admin', $unread > 0 ? '<a href="notices.php?read=all" class="btn btn-sm btn-outline-primary"><i class="fas fa-check-double"></i> Tandai Semua Dibaca ('.$unread.')</a>' : ''); ?>



<?php if (count($maintenance_aktif) > 0): ?>

<div class="card guru-panel border-0 mb-4" style="border-left:4px solid #ffc107 !important">

    <div class="card-body">

        <h6 class="mb-3"><i class="fas fa-wrench text-warning"></i> Lab Sedang / Akan Maintenance</h6>

        <div class="table-responsive">

            <table class="table table-sm mb-0">

                <thead>

                    <tr><th>Lab</th><th>Jenis</th><th>Tanggal</th><th>Waktu</th><th>Status</th></tr>

                </thead>

                <tbody>

                <?php foreach ($maintenance_aktif as $m): ?>

                    <tr>

                        <td><strong><?= htmlspecialchars($m['nama_lab']) ?></strong></td>

                        <td><span class="badge badge-secondary"><?= ucfirst($m['jenis']) ?></span></td>

                        <td><?= format_tanggal($m['tanggal_mulai']) ?></td>

                        <td><?= substr($m['jam_mulai'],0,5) ?> - <?= substr($m['jam_selesai'],0,5) ?></td>

                        <td>

                            <?php if ($m['status'] == 'berlangsung'): ?>

                                <span class="badge badge-warning">Berlangsung — Lab tidak dapat digunakan</span>

                            <?php else: ?>

                                <span class="badge badge-info">Dijadwalkan — Pengajuan bentrok ditolak</span>

                            <?php endif; ?>

                        </td>

                    </tr>

                <?php endforeach; ?>

                </tbody>

            </table>

        </div>

    </div>

</div>

<?php endif; ?>



<div class="card guru-panel border-0">

    <div class="card-header guru-card-header">

        <h6 class="mb-0 font-weight-bold"><i class="fas fa-bell mr-2"></i>Daftar Notice</h6>

    </div>

    <div class="card-body p-0">

        <?php if (count($notices) > 0): ?>

            <?php foreach ($notices as $n):

                $is_unread = empty($n['is_read']);

                $bg = $is_unread ? '#fff8e1' : '#fff';

                $icon = $n['jenis'] == 'maintenance' ? 'fa-wrench text-warning' : 'fa-info-circle text-info';

            ?>

            <div class="d-flex align-items-start p-3 border-bottom" style="background:<?= $bg ?>">

                <div class="mr-3 mt-1"><i class="fas <?= $icon ?>"></i></div>

                <div class="flex-grow-1">

                    <div class="d-flex justify-content-between align-items-start">

                        <strong style="font-size:14px">

                            <?= htmlspecialchars($n['judul']) ?>

                            <?php if ($is_unread): ?><span class="badge badge-danger badge-pill ml-1">Baru</span><?php endif; ?>

                        </strong>

                        <small class="text-muted ml-2"><?= format_datetime($n['created_at']) ?></small>

                    </div>

                    <p class="mb-0 mt-1 small text-muted"><?= nl2br(htmlspecialchars($n['pesan'])) ?></p>

                    <?php if ($is_unread): ?>

                        <a href="notices.php?read_id=<?= $n['id'] ?>" class="btn btn-link btn-sm p-0 mt-1">Tandai dibaca</a>

                    <?php endif; ?>

                </div>

            </div>

            <?php endforeach; ?>

        <?php else: ?>

            <div class="text-center text-muted py-5">

                <i class="fas fa-bell-slash fa-2x mb-2"></i><br>

                <small>Belum ada notice</small>

            </div>

        <?php endif; ?>

    </div>

</div>



<?php require_once __DIR__ . '/../includes/footer.php'; ?>

