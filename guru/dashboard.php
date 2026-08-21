<?php
$page_title = 'Dashboard';
require_once __DIR__ . '/../includes/header.php';
check_role(['guru']);

$guru_id = $_SESSION['user_id'];
$today = date('Y-m-d');
$hari_ini = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'][date('w')];

$total_pengajuan = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(*) as c FROM peminjaman WHERE guru_id=$guru_id"))['c'];
$total_diterima = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(*) as c FROM peminjaman WHERE guru_id=$guru_id AND status='diterima'"))['c'];
$total_berlangsung = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(*) as c FROM peminjaman WHERE guru_id=$guru_id AND status='berlangsung'"))['c'];
$total_selesai = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(*) as c FROM peminjaman WHERE guru_id=$guru_id AND status='selesai'"))['c'];
$total_pending = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(*) as c FROM peminjaman WHERE guru_id=$guru_id AND status='pending'"))['c'];
$total_ditolak = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(*) as c FROM peminjaman WHERE guru_id=$guru_id AND status='ditolak'"))['c'];

$jadwal_today = mysqli_query($koneksi, "SELECT p.*, l.nama_lab, c.waktu_check_in, c.waktu_check_out FROM peminjaman p LEFT JOIN laboratorium l ON p.lab_id=l.id LEFT JOIN check_in c ON c.peminjaman_id=p.id WHERE p.guru_id=$guru_id AND p.tanggal='$today' ORDER BY p.jam_mulai");
$recent = mysqli_query($koneksi, "SELECT p.id, p.status, p.tanggal, p.jam_mulai, p.jam_selesai, l.nama_lab FROM peminjaman p LEFT JOIN laboratorium l ON p.lab_id=l.id WHERE p.guru_id=$guru_id ORDER BY p.created_at DESC LIMIT 5");
$notices = get_guru_notices($guru_id, 5);
$unread_notices = count_unread_notices($guru_id);
$maintenance_aktif = get_maintenance_labs();
?>

<div class="guru-page-header mb-4">
    <div class="d-flex justify-content-between align-items-start flex-wrap">
        <div>
            <h4 class="mb-1 font-weight-bold text-dark">Selamat Datang, <?= htmlspecialchars($_SESSION['nama'] ?? 'Guru') ?></h4>
            <p class="text-muted mb-0 guru-page-subtitle">Dashboard Guru — Sistem Operasional Laboratorium Komputer</p>
        </div>
        <?php live_clock_widget(); ?>
    </div>
</div>

<div class="row mb-4">
    <?php
    $stats = [
        ['Total', $total_pengajuan, 'primary'],
        ['Pending', $total_pending, 'warning'],
        ['Diterima', $total_diterima, 'success'],
        ['Berlangsung', $total_berlangsung, 'info'],
        ['Selesai', $total_selesai, 'secondary'],
        ['Ditolak', $total_ditolak, 'danger'],
    ];
    foreach ($stats as $st):
    ?>
    <div class="col-md-4 col-lg-2 mb-3">
        <div class="card guru-panel border-0 h-100 text-center">
            <div class="card-body py-3">
                <div class="guru-stat-value text-<?= $st[2] ?>"><?= $st[1] ?></div>
                <small class="text-muted"><?= $st[0] ?></small>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<div class="row mb-4">
    <div class="col-lg-7 mb-3">
        <div class="card guru-panel border-0 h-100">
            <div class="card-header guru-card-header">
                <h6 class="mb-0 font-weight-bold"><i class="fas fa-calendar-day mr-2"></i>Jadwal Hari Ini</h6>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table guru-table mb-0">
                        <thead><tr><th>Lab</th><th>Kelas/Mapel</th><th>Waktu</th><th>Status</th><th>Aksi</th></tr></thead>
                        <tbody>
                        <?php if (mysqli_num_rows($jadwal_today) > 0): while ($j = mysqli_fetch_assoc($jadwal_today)): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($j['nama_lab'] ?? '-') ?></strong></td>
                                <td><small><?= htmlspecialchars($j['kelas'] ?: '-') ?> / <?= htmlspecialchars($j['mata_pelajaran'] ?: '-') ?></small></td>
                                <td><?= substr($j['jam_mulai'], 0, 5) ?>–<?= substr($j['jam_selesai'], 0, 5) ?></td>
                                <td><?= status_badge($j['status']) ?></td>
                                <td>
                                    <?php if (!empty($j['waktu_check_out'])): ?>
                                        <small class="text-info"><i class="fas fa-check"></i> Selesai</small>
                                    <?php elseif (!empty($j['waktu_check_in'])): ?>
                                        <a href="checkin.php" class="btn btn-xs btn-warning btn-sm py-0">Check-Out</a>
                                    <?php elseif ($j['status'] == 'diterima' || $j['status'] == 'berlangsung'): ?>
                                        <a href="checkin.php" class="btn btn-xs btn-success btn-sm py-0">Check-In</a>
                                    <?php else: ?>—<?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; else: ?>
                            <tr><td colspan="5" class="text-center py-4 text-muted">Tidak ada jadwal hari ini</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-5 mb-3">
        <div class="card guru-panel border-0 h-100">
            <div class="card-header guru-card-header">
                <h6 class="mb-0 font-weight-bold"><i class="fas fa-history mr-2"></i>Pengajuan Terbaru</h6>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table guru-table mb-0">
                        <thead><tr><th>Kode</th><th>Lab</th><th>Status</th></tr></thead>
                        <tbody>
                        <?php if (mysqli_num_rows($recent) > 0): while ($r = mysqli_fetch_assoc($recent)): ?>
                            <tr>
                                <td><code class="small">PJN-<?= str_pad($r['id'], 5, '0', STR_PAD_LEFT) ?></code></td>
                                <td><small><?= htmlspecialchars($r['nama_lab'] ?? '-') ?></small></td>
                                <td><?= status_badge($r['status']) ?></td>
                            </tr>
                        <?php endwhile; else: ?>
                            <tr><td colspan="3" class="text-center py-4 text-muted">Belum ada pengajuan</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer bg-white text-center border-top">
                <a href="pengajuan.php" class="btn btn-sm btn-primary"><i class="fas fa-plus"></i> Ajukan Lab</a>
            </div>
        </div>
    </div>
</div>

<div class="row mb-4">
    <div class="col-lg-6 mb-3">
        <div class="card guru-panel border-0 h-100">
            <div class="card-header guru-card-header d-flex justify-content-between">
                <h6 class="mb-0 font-weight-bold"><i class="fas fa-bell mr-2"></i>Notice</h6>
                <?php if ($unread_notices > 0): ?><span class="badge badge-danger"><?= $unread_notices ?> baru</span><?php endif; ?>
            </div>
            <div class="card-body p-0">
                <?php if (count($notices) > 0): foreach ($notices as $n): ?>
                <div class="p-3 border-bottom" style="background:<?= empty($n['is_read']) ? '#fff8e1' : '#fff' ?>">
                    <strong class="small"><?= htmlspecialchars($n['judul']) ?></strong>
                    <?php if (empty($n['is_read'])): ?><span class="badge badge-danger badge-pill ml-1">Baru</span><?php endif; ?>
                    <br><small class="text-muted"><?= htmlspecialchars(strlen($n['pesan']) > 80 ? substr($n['pesan'], 0, 80) . '...' : $n['pesan']) ?></small>
                </div>
                <?php endforeach; else: ?>
                <div class="text-center text-muted py-4"><small>Tidak ada notice</small></div>
                <?php endif; ?>
            </div>
            <div class="card-footer bg-white text-center border-top">
                <a href="notices.php" class="btn btn-sm btn-outline-primary">Lihat Semua</a>
            </div>
        </div>
    </div>
    <div class="col-lg-6 mb-3">
        <div class="card guru-panel border-0 h-100">
            <div class="card-header guru-card-header">
                <h6 class="mb-0 font-weight-bold"><i class="fas fa-wrench mr-2"></i>Maintenance Lab</h6>
            </div>
            <div class="card-body p-0">
                <?php if (count($maintenance_aktif) > 0): foreach (array_slice($maintenance_aktif, 0, 4) as $m): ?>
                <div class="p-3 border-bottom">
                    <strong class="small"><?= htmlspecialchars($m['nama_lab']) ?></strong>
                    <span class="badge badge-<?= $m['status'] == 'berlangsung' ? 'warning' : 'info' ?> ml-1"><?= ucfirst($m['status']) ?></span>
                    <br><small class="text-muted"><?= format_tanggal($m['tanggal_mulai']) ?>, <?= substr($m['jam_mulai'],0,5) ?>–<?= substr($m['jam_selesai'],0,5) ?></small>
                </div>
                <?php endforeach; else: ?>
                <div class="text-center text-muted py-4"><i class="fas fa-check-circle text-success"></i><br><small>Semua lab tersedia</small></div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <?php
    $quick = [
        ['pengajuan.php', 'fa-paper-plane', 'Ajukan Penggunaan', 'Buat pengajuan baru'],
        ['checkin.php', 'fa-exchange-alt', 'Check-In / Out', 'Konfirmasi penggunaan lab'],
        ['status.php', 'fa-list-alt', 'Status Pengajuan', 'Lihat semua status'],
    ];
    foreach ($quick as $q):
    ?>
    <div class="col-md-4 mb-3">
        <a href="<?= $q[0] ?>" class="card guru-quick-card text-decoration-none h-100 text-dark">
            <div class="card-body text-center py-4">
                <i class="fas <?= $q[1] ?> fa-2x mb-2" style="color:#8d8006"></i>
                <h6 class="font-weight-bold mb-1"><?= $q[2] ?></h6>
                <small class="text-muted"><?= $q[3] ?></small>
            </div>
        </a>
    </div>
    <?php endforeach; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
