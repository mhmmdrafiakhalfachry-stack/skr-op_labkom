<?php
$page_title = 'Dashboard';
require_once __DIR__ . '/../includes/header.php';
check_role(['admin']);

$today = date('Y-m-d');
$bulan_ini = date('Y-m');
$hari_ini = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'][date('w')];

// Statistics
$total_lab = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(*) as t FROM laboratorium WHERE status='aktif'"))['t'];
$total_guru = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(*) as t FROM users WHERE role='guru'"))['t'];
$usage_today_count = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(*) as t FROM peminjaman WHERE tanggal='$today' AND status IN ('diterima','berlangsung','selesai')"))['t'];
$usage_month_count = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(*) as t FROM peminjaman WHERE DATE_FORMAT(tanggal,'%Y-%m')='$bulan_ini' AND status IN ('diterima','berlangsung','selesai')"))['t'];
$pending_count = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(*) as t FROM peminjaman WHERE status='pending'"))['t'];
$tidak_terlaksana = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(*) as t FROM peminjaman WHERE DATE_FORMAT(tanggal,'%Y-%m')='$bulan_ini' AND status='tidak_terlaksana'"))['t'];

// Jadwal hari ini
$schedule_today = mysqli_query($koneksi, "
    SELECT p.jam_mulai, p.jam_selesai, p.kelas, p.mata_pelajaran, p.status, u.nama as nama_guru, l.nama_lab
    FROM peminjaman p
    LEFT JOIN users u ON p.guru_id=u.id
    LEFT JOIN laboratorium l ON p.lab_id=l.id
    WHERE p.tanggal='$today'
    ORDER BY p.jam_mulai
");

// Notifications
$maintenance_notif = [];
if (mysqli_num_rows(mysqli_query($koneksi, "SHOW TABLES LIKE 'maintenance'")) > 0) {
    $maintenance_notif = mysqli_fetch_all(mysqli_query($koneksi, "
        SELECT m.*, l.nama_lab FROM maintenance m
        LEFT JOIN laboratorium l ON m.lab_id=l.id
        WHERE m.status IN ('dijadwalkan','berlangsung')
        ORDER BY m.tanggal_mulai ASC LIMIT 5
    "), MYSQLI_ASSOC);
}

$notif_bentrok = mysqli_query($koneksi, "
    SELECT p.*, l.nama_lab, u.nama FROM peminjaman p
    LEFT JOIN laboratorium l ON p.lab_id=l.id
    LEFT JOIN users u ON p.guru_id=u.id
    WHERE p.status='ditolak' AND p.alasan_penolakan LIKE '%bentrok%'
    ORDER BY p.tanggal DESC LIMIT 3
");
$count_tidak_bulan = $tidak_terlaksana;

function schedule_status($status, $jam_mulai, $jam_selesai) {
    $now = date('H:i:s');
    if ($status == 'selesai') return '<span class="badge badge-success">Selesai</span>';
    if ($status == 'berlangsung' || ($now >= $jam_mulai && $now <= $jam_selesai)) return '<span class="badge badge-primary">Berlangsung</span>';
    if ($status == 'diterima' && $now < $jam_mulai) return '<span class="badge badge-warning">Akan Datang</span>';
    if ($status == 'ditolak') return '<span class="badge badge-danger">Ditolak</span>';
    if ($status == 'tidak_terlaksana') return '<span class="badge badge-secondary">Tidak Terlaksana</span>';
    if ($status == 'pending') return '<span class="badge badge-light text-dark">Pending</span>';
    return '<span class="text-muted">-</span>';
}
?>

<!-- Page Header -->
<div class="admin-page-header mb-4">
    <div class="d-flex justify-content-between align-items-start flex-wrap">
        <div>
            <h4 class="mb-1 font-weight-bold text-dark">Dashboard Administrator</h4>
            <p class="text-muted mb-0" style="font-size:14px">Sistem Operasional Laboratorium Komputer — SMK PGRI 35 Solokan Jeruk</p>
        </div>
        <?php live_clock_widget(); ?>
    </div>
</div>

<!-- Stat Cards -->
<div class="row mb-4">
    <div class="col-lg-2 col-md-4 col-6 mb-3">
        <div class="card admin-stat-card h-100 border-0">
            <div class="card-body text-center py-3">
                <div class="admin-stat-value text-primary"><?= $total_lab ?></div>
                <div class="admin-stat-label">Laboratorium Aktif</div>
            </div>
        </div>
    </div>
    <div class="col-lg-2 col-md-4 col-6 mb-3">
        <div class="card admin-stat-card h-100 border-0">
            <div class="card-body text-center py-3">
                <div class="admin-stat-value text-secondary"><?= $total_guru ?></div>
                <div class="admin-stat-label">Guru Terdaftar</div>
            </div>
        </div>
    </div>
    <div class="col-lg-2 col-md-4 col-6 mb-3">
        <div class="card admin-stat-card h-100 border-0">
            <div class="card-body text-center py-3">
                <div class="admin-stat-value text-success"><?= $usage_today_count ?></div>
                <div class="admin-stat-label">Penggunaan Hari Ini</div>
            </div>
        </div>
    </div>
    <div class="col-lg-2 col-md-4 col-6 mb-3">
        <div class="card admin-stat-card h-100 border-0">
            <div class="card-body text-center py-3">
                <div class="admin-stat-value text-info"><?= $usage_month_count ?></div>
                <div class="admin-stat-label">Penggunaan Bulan Ini</div>
            </div>
        </div>
    </div>
    <div class="col-lg-2 col-md-4 col-6 mb-3">
        <div class="card admin-stat-card h-100 border-0">
            <div class="card-body text-center py-3">
                <div class="admin-stat-value text-warning"><?= $pending_count ?></div>
                <div class="admin-stat-label">Pengajuan Pending</div>
            </div>
        </div>
    </div>
    <div class="col-lg-2 col-md-4 col-6 mb-3">
        <div class="card admin-stat-card h-100 border-0">
            <div class="card-body text-center py-3">
                <div class="admin-stat-value text-danger"><?= $tidak_terlaksana ?></div>
                <div class="admin-stat-label">Tidak Terlaksana</div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Jadwal Hari Ini -->
    <div class="col-lg-8 mb-4">
        <div class="card admin-panel border-0 h-100">
            <div class="card-header bg-white border-bottom py-3">
                <h6 class="mb-0 font-weight-bold"><i class="fas fa-calendar-day text-primary mr-2"></i>Jadwal Penggunaan Lab — Hari Ini</h6>
            </div>
            <div class="card-body p-0">
                <?php if (mysqli_num_rows($schedule_today) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover admin-table mb-0">
                        <thead>
                            <tr>
                                <th>Waktu</th>
                                <th>Laboratorium</th>
                                <th>Kelas / Mapel</th>
                                <th>Guru</th>
                                <th class="text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php while ($s = mysqli_fetch_assoc($schedule_today)): ?>
                            <tr>
                                <td class="font-weight-bold" style="white-space:nowrap"><?= substr($s['jam_mulai'],0,5) ?> – <?= substr($s['jam_selesai'],0,5) ?></td>
                                <td><?= htmlspecialchars($s['nama_lab'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($s['kelas'] ?: '-') ?> / <?= htmlspecialchars($s['mata_pelajaran'] ?: '-') ?></td>
                                <td><?= htmlspecialchars($s['nama_guru'] ?? '-') ?></td>
                                <td class="text-center"><?= schedule_status($s['status'], $s['jam_mulai'], $s['jam_selesai']) ?></td>
                            </tr>
                        <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="text-center text-muted py-5">
                    <i class="fas fa-calendar-check fa-2x mb-2 d-block" style="opacity:0.4"></i>
                    <span style="font-size:14px">Tidak ada jadwal penggunaan lab hari ini</span>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Notifikasi -->
    <div class="col-lg-4 mb-4">
        <div class="card admin-panel border-0 h-100">
            <div class="card-header bg-white border-bottom py-3">
                <h6 class="mb-0 font-weight-bold"><i class="fas fa-bell text-warning mr-2"></i>Notifikasi Sistem</h6>
            </div>
            <div class="card-body">
                <?php $has_notif = false; ?>

                <?php if (count($maintenance_notif) > 0):
                    $has_notif = true;
                    foreach ($maintenance_notif as $m): ?>
                    <div class="admin-notif-item admin-notif-info mb-3">
                        <div class="font-weight-bold" style="font-size:13px">Maintenance — <?= htmlspecialchars($m['nama_lab'] ?? 'Lab') ?></div>
                        <small class="text-muted"><?= format_tanggal($m['tanggal_mulai']) ?>, <?= substr($m['jam_mulai'],0,5) ?> – <?= substr($m['jam_selesai'],0,5) ?></small>
                    </div>
                <?php endforeach; endif; ?>

                <?php if (mysqli_num_rows($notif_bentrok) > 0):
                    $has_notif = true;
                    while ($n = mysqli_fetch_assoc($notif_bentrok)): ?>
                    <div class="admin-notif-item admin-notif-danger mb-3">
                        <div class="font-weight-bold" style="font-size:13px">Jadwal Bentrok Dihindari</div>
                        <small class="text-muted"><?= format_tanggal($n['tanggal']) ?>, <?= substr($n['jam_mulai'],0,5) ?>–<?= substr($n['jam_selesai'],0,5) ?> — <?= htmlspecialchars($n['nama_lab']) ?></small>
                    </div>
                <?php endwhile; endif; ?>

                <?php if ($count_tidak_bulan > 0):
                    $has_notif = true; ?>
                    <div class="admin-notif-item admin-notif-warning mb-3">
                        <div class="font-weight-bold" style="font-size:13px"><?= $count_tidak_bulan ?> Penggunaan Tidak Terlaksana</div>
                        <small class="text-muted">Bulan ini — lihat detail di menu Monitoring</small>
                    </div>
                <?php endif; ?>

                <?php if (!$has_notif): ?>
                <div class="text-center text-muted py-4">
                    <i class="fas fa-check-circle fa-2x mb-2 text-success" style="opacity:0.6"></i>
                    <div style="font-size:13px">Semua sistem berjalan normal</div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Quick Access -->
<div class="row">
    <div class="col-12">
        <div class="card admin-panel border-0">
            <div class="card-header bg-white border-bottom py-3">
                <h6 class="mb-0 font-weight-bold"><i class="fas fa-link text-secondary mr-2"></i>Akses Cepat</h6>
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-md-3 col-6 mb-2">
                        <a href="pengajuan.php" class="btn btn-outline-primary btn-block btn-sm py-2"><i class="fas fa-clipboard-list mr-1"></i> Pengajuan</a>
                    </div>
                    <div class="col-md-3 col-6 mb-2">
                        <a href="monitoring.php" class="btn btn-outline-info btn-block btn-sm py-2"><i class="fas fa-eye mr-1"></i> Monitoring</a>
                    </div>
                    <div class="col-md-3 col-6 mb-2">
                        <a href="maintenance.php" class="btn btn-outline-warning btn-block btn-sm py-2"><i class="fas fa-wrench mr-1"></i> Maintenance</a>
                    </div>
                    <div class="col-md-3 col-6 mb-2">
                        <a href="laporan.php" class="btn btn-outline-secondary btn-block btn-sm py-2"><i class="fas fa-file-alt mr-1"></i> Laporan</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
