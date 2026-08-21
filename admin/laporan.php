<?php
$page_title = 'Laporan';
require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../config/auth.php';
check_login();
check_role(['admin']);

$periode_dari = sanitize($_GET['dari'] ?? date('Y-m-01'));
$periode_sampai = sanitize($_GET['sampai'] ?? date('Y-m-d'));
$lab_id = (int)($_GET['lab_id'] ?? 0);

$where_date = " AND p.tanggal >= '$periode_dari' AND p.tanggal <= '$periode_sampai'";
$where_lab = $lab_id ? " AND p.lab_id = $lab_id" : '';
$lab_name = $lab_id ? get_nama_lab($lab_id) : 'Semua Laboratorium';

// === Main page chart data ===
$total_pengajuan = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(*) as t FROM peminjaman p WHERE 1=1 $where_date $where_lab"))['t'];
$total_selesai = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(*) as t FROM peminjaman p WHERE p.status='selesai' $where_date $where_lab"))['t'];
$total_diterima = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(*) as t FROM peminjaman p WHERE p.status='diterima' $where_date $where_lab"))['t'];
$total_ditolak = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(*) as t FROM peminjaman p WHERE p.status='ditolak' $where_date $where_lab"))['t'];
$total_tidak = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(*) as t FROM peminjaman p WHERE p.status='tidak_terlaksana' $where_date $where_lab"))['t'];
$total_checkin = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(*) as t FROM check_in c JOIN peminjaman p ON c.peminjaman_id=p.id WHERE 1=1 $where_date $where_lab"))['t'];
$total_bentrok = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(*) as t FROM peminjaman p WHERE p.status='ditolak' AND p.alasan_penolakan LIKE '%bentrok%' $where_date $where_lab"))['t'];
$total_guru = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(*) as t FROM users WHERE role='guru'"))['t'];
$total_lab = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(*) as t FROM laboratorium WHERE status='aktif'"))['t'];

$chart_bulan = [];
for ($i = 5; $i >= 0; $i--) {
    $month = date('Y-m', strtotime("-$i months"));
    $label = date('M Y', strtotime("-$i months"));
    $cnt = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(*) as t FROM peminjaman p WHERE DATE_FORMAT(p.tanggal,'%Y-%m')='$month' $where_lab"))['t'];
    $chart_bulan[] = ['label' => $label, 'value' => (int)$cnt];
}

$status_chart = [
    ['label' => 'Selesai', 'value' => (int)$total_selesai, 'color' => '#28a745'],
    ['label' => 'Diterima', 'value' => (int)$total_diterima, 'color' => '#17a2b8'],
    ['label' => 'Ditolak', 'value' => (int)$total_ditolak, 'color' => '#dc3545'],
    ['label' => 'Tidak Terlaksana', 'value' => (int)$total_tidak, 'color' => '#6c757d'],
];

$lab_chart = [];
$lab_q = mysqli_query($koneksi, "SELECT l.nama_lab, COUNT(p.id) as total
    FROM laboratorium l LEFT JOIN peminjaman p ON l.id=p.lab_id AND p.tanggal >= '$periode_dari' AND p.tanggal <= '$periode_sampai'
    " . ($lab_id ? "WHERE l.id=$lab_id" : "") . " GROUP BY l.id ORDER BY total DESC");
while ($lr = mysqli_fetch_assoc($lab_q)) {
    $lab_chart[] = ['label' => $lr['nama_lab'], 'value' => (int)$lr['total']];
}

$lab_stats = mysqli_query($koneksi, "SELECT l.nama_lab, COUNT(p.id) as total,
    SUM(CASE WHEN p.status='selesai' THEN 1 ELSE 0 END) as selesai,
    SUM(CASE WHEN p.status='diterima' THEN 1 ELSE 0 END) as diterima,
    SUM(CASE WHEN p.status='ditolak' THEN 1 ELSE 0 END) as ditolak,
    SUM(CASE WHEN p.status='tidak_terlaksana' THEN 1 ELSE 0 END) as tidak_terlaksana
    FROM laboratorium l LEFT JOIN peminjaman p ON l.id=p.lab_id AND p.tanggal >= '$periode_dari' AND p.tanggal <= '$periode_sampai'
    " . ($lab_id ? "WHERE l.id=$lab_id" : "") . " GROUP BY l.id ORDER BY l.nama_lab");

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-start flex-wrap mb-4">
    <div>
        <h4 class="font-weight-bold mb-1">Laporan & Statistik</h4>
        <small class="text-muted">Grafik keseluruhan laporan penggunaan laboratorium komputer</small>
    </div>
    <div class="no-print mt-2">
        <button onclick="window.print()" class="btn btn-primary btn-sm"><i class="fas fa-print"></i> Cetak</button>
    </div>
</div>

<!-- Filter -->
<div class="card admin-panel border-0 mb-4 no-print">
    <div class="card-body py-3">
        <form method="GET" id="filterForm" class="form-inline flex-wrap">
            <label class="mr-2 text-muted" style="font-size:14px">Periode:</label>
            <input type="date" name="dari" class="form-control form-control-sm mr-2 mb-1" value="<?= $periode_dari ?>">
            <span class="mr-2 mb-1">s/d</span>
            <input type="date" name="sampai" class="form-control form-control-sm mr-3 mb-1" value="<?= $periode_sampai ?>">
            <select name="lab_id" class="form-control form-control-sm mr-2 mb-1">
<option value="">Semua Lab</option>
                <?php foreach (get_lab_options() as $lb): ?>
                <option value="<?= $lb['id'] ?>" <?= $lab_id == $lb['id'] ? 'selected' : '' ?>><?= htmlspecialchars($lb['nama_lab']) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-sm btn-primary mb-1"><i class="fas fa-filter"></i> Terapkan</button>
        </form>
    </div>
</div>

<div id="laporanPrintArea">
    <div class="text-center mb-4 d-none d-print-block">
        <h5 class="font-weight-bold mb-0">LAPORAN SISTEM LABORATORIUM KOMPUTER</h5>
        <small>Periode: <?= format_tanggal($periode_dari) ?> — <?= format_tanggal($periode_sampai) ?> | <?= htmlspecialchars($lab_name) ?></small>
    </div>

    <!-- Summary Stats -->
    <div class="row mb-4">
        <div class="col-lg-2 col-md-4 col-6 mb-3">
            <div class="card admin-stat-card border-0 h-100"><div class="card-body text-center py-3">
                <div class="admin-stat-value text-primary"><?= $total_pengajuan ?></div>
                <div class="admin-stat-label">Total Pengajuan</div>
            </div></div>
        </div>
        <div class="col-lg-2 col-md-4 col-6 mb-3">
            <div class="card admin-stat-card border-0 h-100"><div class="card-body text-center py-3">
                <div class="admin-stat-value text-success"><?= $total_selesai ?></div>
                <div class="admin-stat-label">Selesai</div>
            </div></div>
        </div>
        <div class="col-lg-2 col-md-4 col-6 mb-3">
            <div class="card admin-stat-card border-0 h-100"><div class="card-body text-center py-3">
                <div class="admin-stat-value text-info"><?= $total_checkin ?></div>
                <div class="admin-stat-label">Check-in</div>
            </div></div>
        </div>
        <div class="col-lg-2 col-md-4 col-6 mb-3">
            <div class="card admin-stat-card border-0 h-100"><div class="card-body text-center py-3">
                <div class="admin-stat-value text-danger"><?= $total_ditolak ?></div>
                <div class="admin-stat-label">Ditolak</div>
            </div></div>
        </div>
        <div class="col-lg-2 col-md-4 col-6 mb-3">
            <div class="card admin-stat-card border-0 h-100"><div class="card-body text-center py-3">
                <div class="admin-stat-value text-secondary"><?= $total_tidak ?></div>
                <div class="admin-stat-label">Tidak Terlaksana</div>
            </div></div>
        </div>
        <div class="col-lg-2 col-md-4 col-6 mb-3">
            <div class="card admin-stat-card border-0 h-100"><div class="card-body text-center py-3">
                <div class="admin-stat-value text-warning"><?= $total_bentrok ?></div>
                <div class="admin-stat-label">Bentrok Jadwal</div>
            </div></div>
        </div>
    </div>

    <!-- Charts -->
    <div class="row mb-4">
        <div class="col-lg-6 mb-3">
            <div class="card admin-panel border-0 h-100">
                <div class="card-header bg-white border-bottom py-2">
                    <h6 class="mb-0 font-weight-bold small"><i class="fas fa-chart-bar text-primary mr-2"></i>Penggunaan Lab — 6 Bulan Terakhir</h6>
                </div>
                <div class="card-body py-2">
                    <div class="chart-wrap chart-wrap-bar"><canvas id="chartBulanan"></canvas></div>
                </div>
            </div>
        </div>
        <div class="col-lg-6 mb-3">
            <div class="card admin-panel border-0 h-100">
                <div class="card-header bg-white border-bottom py-2">
                    <h6 class="mb-0 font-weight-bold small"><i class="fas fa-chart-pie text-success mr-2"></i>Distribusi Status Pengajuan</h6>
                </div>
                <div class="card-body py-2">
                    <div class="chart-wrap chart-wrap-donut"><canvas id="chartStatus"></canvas></div>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-lg-6 mb-3">
            <div class="card admin-panel border-0 h-100">
                <div class="card-header bg-white border-bottom py-2">
                    <h6 class="mb-0 font-weight-bold small"><i class="fas fa-building text-info mr-2"></i>Penggunaan per Laboratorium</h6>
                </div>
                <div class="card-body py-2">
                    <div class="chart-wrap chart-wrap-hbar"><canvas id="chartLab"></canvas></div>
                </div>
            </div>
        </div>
        <div class="col-lg-6 mb-3">
            <div class="card admin-panel border-0 h-100">
                <div class="card-header bg-white border-bottom py-3">
                    <h6 class="mb-0 font-weight-bold"><i class="fas fa-info-circle text-secondary mr-2"></i>Ringkasan Sistem</h6>
                </div>
                <div class="card-body">
                    <table class="table table-sm table-borderless mb-0" style="font-size:14px">
                        <tr><td class="text-muted">Total Guru</td><td class="text-right font-weight-bold"><?= $total_guru ?></td></tr>
                        <tr><td class="text-muted">Laboratorium Aktif</td><td class="text-right font-weight-bold"><?= $total_lab ?></td></tr>
                        <tr><td class="text-muted">Periode Laporan</td><td class="text-right"><?= format_tanggal($periode_dari) ?> — <?= format_tanggal($periode_sampai) ?></td></tr>
                        <tr><td class="text-muted">Cakupan Lab</td><td class="text-right"><?= htmlspecialchars($lab_name) ?></td></tr>
                        <tr><td class="text-muted">Tingkat Penyelesaian</td><td class="text-right font-weight-bold text-success"><?= $total_pengajuan > 0 ? round($total_selesai / $total_pengajuan * 100, 1) : 0 ?>%</td></tr>
                        <tr><td class="text-muted">Tingkat Check-in</td><td class="text-right font-weight-bold text-info"><?= $total_pengajuan > 0 ? round($total_checkin / $total_pengajuan * 100, 1) : 0 ?>%</td></tr>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Detail Table -->
    <div class="card admin-panel border-0 mb-4">
        <div class="card-header bg-white border-bottom py-3">
            <h6 class="mb-0 font-weight-bold"><i class="fas fa-table text-primary mr-2"></i>Rekap Statistik per Laboratorium</h6>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover admin-table mb-0">
                    <thead>
                        <tr>
                            <th>Laboratorium</th>
                            <th class="text-center">Total</th>
                            <th class="text-center">Selesai</th>
                            <th class="text-center">Diterima</th>
                            <th class="text-center">Ditolak</th>
                            <th class="text-center">Tidak Terlaksana</th>
                            <th class="text-center">Tingkat Penyelesaian</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php while ($ls = mysqli_fetch_assoc($lab_stats)):
                        $rate = $ls['total'] > 0 ? round(($ls['selesai'] / $ls['total']) * 100, 1) : 0;
                    ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($ls['nama_lab']) ?></strong></td>
                            <td class="text-center"><?= $ls['total'] ?></td>
                            <td class="text-center"><?= $ls['selesai'] ?></td>
                            <td class="text-center"><?= $ls['diterima'] ?></td>
                            <td class="text-center"><?= $ls['ditolak'] ?></td>
                            <td class="text-center"><?= $ls['tidak_terlaksana'] ?></td>
                            <td class="text-center"><span class="badge badge-<?= $rate >= 70 ? 'success' : ($rate >= 40 ? 'warning' : 'secondary') ?>"><?= $rate ?>%</span></td>
                        </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
<script>
var baseChartOpts = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: { position: 'bottom', labels: { font: { size: 10 }, boxWidth: 12, padding: 8 } }
    }
};

new Chart(document.getElementById('chartBulanan'), {
    type: 'bar',
    data: {
        labels: [<?php foreach ($chart_bulan as $c) echo "'".$c['label']."',"; ?>],
        datasets: [{ label: 'Pengajuan', data: [<?php foreach ($chart_bulan as $c) echo $c['value'].','; ?>],
            backgroundColor: 'rgba(30,60,114,0.75)', borderRadius: 3, maxBarThickness: 36 }]
    },
    options: {
        ...baseChartOpts,
        plugins: { legend: { display: false } },
        scales: {
            y: { beginAtZero: true, ticks: { stepSize: 1, font: { size: 10 } } },
            x: { ticks: { font: { size: 10 } } }
        }
    }
});

new Chart(document.getElementById('chartStatus'), {
    type: 'doughnut',
    data: {
        labels: [<?php foreach ($status_chart as $s) if($s['value']>0) echo "'".$s['label']."',"; ?>],
        datasets: [{ data: [<?php foreach ($status_chart as $s) if($s['value']>0) echo $s['value'].','; ?>],
            backgroundColor: [<?php foreach ($status_chart as $s) if($s['value']>0) echo "'".$s['color']."',"; ?>],
            borderWidth: 1 }]
    },
    options: { ...baseChartOpts, cutout: '55%' }
});

new Chart(document.getElementById('chartLab'), {
    type: 'bar',
    data: {
        labels: [<?php foreach ($lab_chart as $l) echo "'".addslashes($l['label'])."',"; ?>],
        datasets: [{ label: 'Total', data: [<?php foreach ($lab_chart as $l) echo $l['value'].','; ?>],
            backgroundColor: 'rgba(23,162,184,0.75)', borderRadius: 3, maxBarThickness: 22 }]
    },
    options: {
        ...baseChartOpts,
        indexAxis: 'y',
        plugins: { legend: { display: false } },
        scales: {
            x: { beginAtZero: true, ticks: { stepSize: 1, font: { size: 10 } } },
            y: { ticks: { font: { size: 10 } } }
        }
    }
});

</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
