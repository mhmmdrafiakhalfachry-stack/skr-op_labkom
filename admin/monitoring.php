<?php

$page_title = 'Monitoring Penggunaan';

require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/rules_engine.php';
check_login();
check_role(['admin']);

if (isset($_POST['force_checkout'])) {
    $result = do_force_checkout((int)$_POST['peminjaman_id'], (int)$_SESSION['user_id']);
    set_alert($result['success'] ? 'success' : 'danger', $result['message']);
    header('Location: monitoring.php');
    exit;
}

require_once __DIR__ . '/../includes/header.php';



$fq = sanitize($_GET['search'] ?? '');

$where = "WHERE 1=1";

$where .= page_search_where($fq, [

    "l.nama_lab", "u.nama", "p.kelas", "p.mata_pelajaran", "p.status"

]);



$data = mysqli_query($koneksi, "SELECT p.*, l.nama_lab, u.nama, c.waktu_check_in, c.waktu_check_out FROM peminjaman p LEFT JOIN laboratorium l ON p.lab_id=l.id LEFT JOIN users u ON p.guru_id=u.id LEFT JOIN check_in c ON c.peminjaman_id=p.id $where ORDER BY p.tanggal DESC, p.jam_mulai DESC");

?>



<div class="admin-page-header mb-4">

    <div class="d-flex justify-content-between align-items-start flex-wrap">

        <div>

            <h4 class="mb-1 font-weight-bold text-dark">Monitoring Penggunaan Lab</h4>

            <p class="text-muted mb-0 admin-page-subtitle">Pemantauan real-time penggunaan laboratorium — <span class="live-indicator"><span class="live-dot-anim"></span> Live</span></p>

        </div>

    </div>

</div>



<?php page_search_only_bar([

    'placeholder' => 'Cari guru, lab, kelas, status...',

    'search' => $fq,

    'reset_url' => 'monitoring.php',

]); ?>



<?php page_print_area_open(); ?>



<div class="card admin-panel border-0">

    <div class="card-header bg-white border-bottom py-3">

        <h6 class="mb-0 font-weight-bold"><i class="fas fa-eye text-info mr-2"></i>Data Monitoring</h6>

    </div>

    <div class="card-body p-0">

        <div class="table-responsive">

            <table class="table table-hover admin-table mb-0">

                <thead>

                    <tr>

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

                <?php if (mysqli_num_rows($data) > 0): while ($p = mysqli_fetch_assoc($data)):
                    $can_force_checkout = $p['status'] === 'berlangsung'
                        && !empty($p['waktu_check_in'])
                        && empty($p['waktu_check_out']);
                    $checkout_elig = get_checkout_eligibility($p);
                ?>

                    <tr>

                        <td><?= format_tanggal($p['tanggal']) ?></td>

                        <td><?= htmlspecialchars($p['nama_lab'] ?? '-') ?></td>

                        <td><strong><?= htmlspecialchars($p['nama']) ?></strong></td>

                        <td><?= htmlspecialchars($p['kelas'] ?: '-') ?></td>

                        <td><?= htmlspecialchars($p['mata_pelajaran'] ?: '-') ?></td>

                        <td><?= substr($p['jam_mulai'], 0, 5) ?>–<?= substr($p['jam_selesai'], 0, 5) ?></td>

                        <td><?= status_badge($p['status']) ?></td>

                        <td><?= $p['waktu_check_in'] ? '<small class="text-success"><i class="fas fa-check"></i> ' . substr($p['waktu_check_in'], 11, 5) . '</small>' : '<small class="text-warning"><i class="fas fa-clock"></i> Belum</small>' ?></td>

                        <td><?= page_checkout_cell($p['waktu_check_out'] ?? null) ?></td>

                        <td class="text-center text-nowrap col-aksi no-print d-print-none">
                            <?php if ($can_force_checkout && !$checkout_elig['allowed'] && $checkout_elig['code'] === 'too_early'): ?>
                                <form method="POST" class="d-inline" data-confirm-form
                                      data-confirm-title="Check-Out Paksa"
                                      data-confirm-message="Check-out paksa untuk sesi ini?"
                                      data-confirm-desc="Guru belum waktunya check-out. Tindakan ini akan menutup sesi secara paksa."
                                      data-confirm-icon="exclamation-triangle"
                                      data-confirm-icon-style="danger"
                                      data-confirm-btn-class="btn-danger">
                                    <input type="hidden" name="peminjaman_id" value="<?= $p['id'] ?>">
                                    <input type="hidden" name="force_checkout" value="1">
                                    <button type="submit" class="btn btn-sm btn-danger" title="Check-out paksa oleh admin">
                                        <i class="fas fa-user-shield"></i> Paksa Out
                                    </button>
                                </form>
                            <?php elseif ($can_force_checkout && $checkout_elig['allowed']): ?>
                                <small class="text-muted">Menunggu guru</small>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>

                    </tr>

                <?php endwhile; else: ?>

                    <tr><td colspan="10" class="text-center py-5 text-muted">Tidak ada data monitoring</td></tr>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

    </div>

</div>



<?php page_print_area_close(); ?>



<script>setTimeout(function(){ location.reload(); }, 30000);</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

