<?php
$page_title = 'Check-In / Check-Out';
require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/rules_engine.php';
check_login();
check_role(['guru']);

$guru_id = $_SESSION['user_id'];
$today = date('Y-m-d');

if (isset($_POST['do_checkin'])) {
    $result = do_checkin((int)$_POST['peminjaman_id'], $guru_id);
    set_alert($result['success'] ? 'success' : 'danger', $result['message']);
    header('Location: checkin.php');
    exit;
}

if (isset($_POST['do_checkout'])) {
    $result = do_checkout((int)$_POST['peminjaman_id'], $guru_id);
    set_alert($result['success'] ? 'success' : 'danger', $result['message']);
    header('Location: checkin.php');
    exit;
}

require_once __DIR__ . '/../includes/header.php';

$fq = sanitize($_GET['search'] ?? '');
$history_where = "WHERE c.guru_id = $guru_id";
if ($fq) {
    $history_where .= page_search_where($fq, ["l.nama_lab", "p.kelas", "p.mata_pelajaran", "p.tanggal"]);
}

$active = mysqli_query($koneksi, "SELECT p.*, l.nama_lab, c.waktu_check_in, c.waktu_check_out
    FROM peminjaman p
    LEFT JOIN laboratorium l ON p.lab_id = l.id
    LEFT JOIN check_in c ON c.peminjaman_id = p.id
    WHERE p.guru_id = $guru_id
    AND (
        (p.tanggal = '$today' AND p.status IN ('diterima','berlangsung'))
        OR (p.status = 'berlangsung' AND c.waktu_check_in IS NOT NULL AND c.waktu_check_out IS NULL)
    )
    ORDER BY p.tanggal DESC, p.jam_mulai");

$history = mysqli_query($koneksi, "SELECT c.*, p.tanggal, p.jam_mulai, p.jam_selesai, p.kelas, p.mata_pelajaran, l.nama_lab
    FROM check_in c
    LEFT JOIN peminjaman p ON c.peminjaman_id = p.id
    LEFT JOIN laboratorium l ON p.lab_id = l.id
    $history_where
    ORDER BY c.waktu_check_in DESC LIMIT 50");
?>

<?php guru_page_header('Check-In / Check-Out Lab', 'Konfirmasi mulai dan selesai penggunaan laboratorium — ' . format_tanggal($today)); ?>

<?php page_search_only_bar([
    'panel' => 'guru-panel',
    'placeholder' => 'Cari riwayat: lab, kelas, mapel...',
    'search' => $fq,
    'reset_url' => 'checkin.php',
]); ?>

<?php page_print_area_open(); ?>

<div class="card guru-panel border-0 mb-4">
    <div class="card-header guru-card-header">
        <h6 class="mb-0 font-weight-bold"><i class="fas fa-calendar-check text-success mr-2"></i>Jadwal Aktif</h6>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover guru-table mb-0">
                <thead>
                    <tr>
                        <th>Laboratorium</th>
                        <th>Tanggal</th>
                        <th>Kelas / Mapel</th>
                        <th>Jadwal</th>
                        <th>Status</th>
                        <th>Check-in</th>
                        <th>Check-out</th>
                        <th class="text-center col-aksi no-print d-print-none">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (mysqli_num_rows($active) > 0): while ($a = mysqli_fetch_assoc($active)):
                    $lab_blocked = get_lab_active_session($a['lab_id'], $a['tanggal'], $a['id']);
                    $checkin_elig = get_checkin_eligibility($a, $lab_blocked);
                    $checkout_elig = get_checkout_eligibility($a);
                    $can_checkin = $checkin_elig['allowed'];
                    $can_checkout = $checkout_elig['allowed'];
                    $has_in = !empty($a['waktu_check_in']);
                    $has_out = !empty($a['waktu_check_out']);
                ?>
                    <tr class="<?= $can_checkin ? 'table-success' : ($can_checkout ? 'table-warning' : '') ?>">
                        <td><strong><?= htmlspecialchars($a['nama_lab'] ?? '-') ?></strong></td>
                        <td><?= format_tanggal($a['tanggal']) ?><?= $a['tanggal'] !== $today ? ' <span class="badge badge-warning">Belum Out</span>' : '' ?></td>
                        <td><?= htmlspecialchars($a['kelas'] ?: '-') ?> / <?= htmlspecialchars($a['mata_pelajaran'] ?: '-') ?></td>
                        <td><strong><?= substr($a['jam_mulai'], 0, 5) ?></strong> – <?= substr($a['jam_selesai'], 0, 5) ?></td>
                        <td><?= status_badge($a['status']) ?></td>
                        <td><?= $has_in ? '<span class="text-success"><i class="fas fa-sign-in-alt"></i> ' . substr($a['waktu_check_in'], 11, 5) . '</span>' : '<span class="text-muted">Belum</span>' ?></td>
                        <td><?= $has_out ? '<span class="text-info"><i class="fas fa-sign-out-alt"></i> ' . substr($a['waktu_check_out'], 11, 5) . '</span>' : '<span class="text-muted">Belum</span>' ?></td>
                        <td class="text-center text-nowrap col-aksi no-print d-print-none">
                            <?php if ($can_checkin): ?>
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="peminjaman_id" value="<?= $a['id'] ?>">
                                    <button type="submit" name="do_checkin" class="btn btn-sm btn-success">
                                        <i class="fas fa-sign-in-alt"></i> Check-In
                                    </button>
                                </form>
                            <?php elseif ($can_checkout): ?>
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="peminjaman_id" value="<?= $a['id'] ?>">
                                    <button type="submit" name="do_checkout" class="btn btn-sm btn-warning">
                                        <i class="fas fa-sign-out-alt"></i> Check-Out
                                    </button>
                                </form>
                            <?php elseif ($has_out): ?>
                                <span class="badge badge-info">Selesai</span>
                            <?php elseif ($checkin_elig['code'] === 'too_early'): ?>
                                <small class="text-warning d-block" style="max-width:180px;margin:0 auto" title="<?= htmlspecialchars($checkin_elig['message']) ?>">
                                    <i class="fas fa-clock"></i> Belum waktunya
                                </small>
                            <?php elseif ($checkin_elig['code'] === 'lab_blocked'): ?>
                                <small class="text-danger" title="Lab masih dipakai"><i class="fas fa-lock"></i> Lab dipakai</small>
                            <?php elseif ($checkout_elig['code'] === 'too_early'): ?>
                                <small class="text-warning d-block" style="max-width:180px;margin:0 auto" title="<?= htmlspecialchars($checkout_elig['message']) ?>">
                                    <i class="fas fa-clock"></i> Belum waktunya
                                </small>
                            <?php else: ?>
                                <button class="btn btn-sm btn-outline-secondary" disabled title="<?= htmlspecialchars($checkin_elig['message'] ?: $checkout_elig['message']) ?>"><i class="fas fa-lock"></i></button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php if ($checkin_elig['code'] === 'lab_blocked'): ?>
                    <tr class="no-print"><td colspan="8" class="py-1 px-3"><small class="text-danger"><i class="fas fa-info-circle"></i> Lab masih digunakan oleh <strong><?= htmlspecialchars($lab_blocked['nama_guru']) ?></strong> (belum check-out). Guru berikutnya dapat check-in setelah check-out selesai.</small></td></tr>
                    <?php elseif ($checkin_elig['code'] === 'too_early'): ?>
                    <tr class="no-print"><td colspan="8" class="py-1 px-3"><small class="text-warning"><i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($checkin_elig['message']) ?></small></td></tr>
                    <?php elseif ($checkout_elig['code'] === 'too_early'): ?>
                    <tr class="no-print"><td colspan="8" class="py-1 px-3"><small class="text-warning"><i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($checkout_elig['message']) ?></small></td></tr>
                    <?php endif; ?>
                <?php endwhile; else: ?>
                    <tr><td colspan="8" class="text-center py-5 text-muted">
                        <i class="fas fa-calendar-times fa-2x mb-2 d-block opacity-50"></i>
                        Tidak ada jadwal hari ini.
                        <br><a href="pengajuan.php" class="btn btn-sm btn-primary mt-2"><i class="fas fa-plus"></i> Ajukan Penggunaan</a>
                    </td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card guru-panel border-0 mb-4 no-print">
    <div class="card-body py-3">
        <h6 class="font-weight-bold mb-2"><i class="fas fa-info-circle text-primary mr-1"></i> Aturan Check-In / Check-Out</h6>
        <ul class="mb-0 small text-muted pl-3">
            <li>Check-in hanya dapat dilakukan <strong>mulai jam jadwal</strong> atau setelahnya (tidak bisa lebih awal)</li>
            <li>Check-out hanya dapat dilakukan <strong>mulai jam selesai jadwal</strong> atau setelahnya — <strong>wajib dilakukan manual</strong> oleh guru</li>
            <li>Sistem <strong>tidak akan check-out otomatis</strong>; guru harus menekan tombol Check-Out sendiri</li>
            <li>Check-out sebelum waktunya hanya dapat dilakukan <strong>secara paksa oleh admin</strong></li>
            <li>Guru berikutnya dapat check-in jika guru sebelumnya <strong>sudah check-out</strong></li>
            <li>Tanpa check-in, status otomatis menjadi <strong>Tidak Terlaksana</strong></li>
        </ul>
    </div>
</div>

<div class="card guru-panel border-0">
    <div class="card-header guru-card-header">
        <h6 class="mb-0 font-weight-bold"><i class="fas fa-history text-info mr-2"></i>Riwayat Check-In / Check-Out</h6>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover guru-table mb-0">
                <thead>
                    <tr><th>No</th><th>Tanggal</th><th>Lab</th><th>Jadwal</th><th>Check-in</th><th>Check-out</th></tr>
                </thead>
                <tbody>
                <?php $no = 1; if (mysqli_num_rows($history) > 0): while ($h = mysqli_fetch_assoc($history)): ?>
                    <tr>
                        <td><?= $no++ ?></td>
                        <td><?= format_tanggal($h['tanggal']) ?></td>
                        <td><?= htmlspecialchars($h['nama_lab'] ?? '-') ?></td>
                        <td><?= substr($h['jam_mulai'], 0, 5) ?>–<?= substr($h['jam_selesai'], 0, 5) ?></td>
                        <td class="text-success"><?= substr($h['waktu_check_in'], 11, 5) ?></td>
                        <td><?= !empty($h['waktu_check_out']) ? '<span class="text-info">' . substr($h['waktu_check_out'], 11, 5) . '</span>' : '<span class="text-muted">-</span>' ?></td>
                    </tr>
                <?php endwhile; else: ?>
                    <tr><td colspan="6" class="text-center py-4 text-muted">Belum ada riwayat</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php page_print_area_close(); ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
