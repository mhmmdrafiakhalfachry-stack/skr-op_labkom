<?php
$page_title = 'Rule-Based System';
require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../config/auth.php';
check_login();
check_role(['admin']);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = (int)$_POST['id'];
    $status = sanitize($_POST['status']);
    $deskripsi = sanitize($_POST['deskripsi'] ?? '');
    mysqli_query($koneksi, "UPDATE rules SET status='$status', deskripsi='$deskripsi' WHERE id=$id");
    set_alert('success', 'Rule berhasil diperbarui!');
    header("Location: rules.php");
    exit;
}

$rules = mysqli_query($koneksi, "SELECT * FROM rules ORDER BY prioritas");

require_once __DIR__ . '/../includes/header.php';

$fq = sanitize($_GET['search'] ?? '');
$fr = sanitize($_GET['status'] ?? '');
?>

<?php admin_page_header('Rule-Based System', 'Kelola aturan IF-THEN untuk otomatisasi sistem pengajuan lab'); ?>

<?php page_toolbar([
    'placeholder' => 'Cari kode, nama, kondisi rule...',
    'search' => $fq,
    'reset_url' => 'rules.php',
    'active_filters' => ($fq || $fr),
    'filters_html' => '
        <select name="status" class="form-control form-control-sm mr-2 mb-1">
            <option value="">Semua Status</option>
            <option value="aktif"' . ($fr == 'aktif' ? ' selected' : '') . '>Aktif</option>
            <option value="nonaktif"' . ($fr == 'nonaktif' ? ' selected' : '') . '>Nonaktif</option>
        </select>',
]); ?>

<div class="alert alert-info admin-panel border-0 mb-4 no-print" style="border-left:4px solid #17a2b8 !important">
    <i class="fas fa-info-circle"></i> Rule-Based System menggunakan aturan IF-THEN untuk memproses pengajuan penggunaan lab secara otomatis tanpa persetujuan manual.
</div>

<?php page_print_area_open(); ?>

<?php
mysqli_data_seek($rules, 0);
while ($r = mysqli_fetch_assoc($rules)):
    if ($fq) {
        $hay = strtolower($r['kode_rule'] . ' ' . $r['nama_rule'] . ' ' . $r['kondisi'] . ' ' . $r['aksi'] . ' ' . ($r['deskripsi'] ?? ''));
        if (strpos($hay, strtolower($fq)) === false) continue;
    }
    if ($fr && $r['status'] != $fr) continue;
?>
<div class="card admin-panel border-0 mb-3 rule-card-form">
    <div class="card-body">
        <form method="POST">
            <input type="hidden" name="id" value="<?= $r['id'] ?>">
            <div class="d-flex justify-content-between align-items-start mb-3 flex-wrap">
                <h5 class="mb-2 font-weight-bold"><code class="text-primary"><?= $r['kode_rule'] ?></code> <?= htmlspecialchars($r['nama_rule']) ?></h5>
                <div class="d-flex align-items-center">
                    <span class="mr-2"><?= badge($r['status'], $r['status'] == 'aktif' ? 'success' : 'secondary') ?></span>
                    <select name="status" class="form-control form-control-sm" style="width:auto">
                        <option value="aktif" <?= $r['status'] == 'aktif' ? 'selected' : '' ?>>Aktif</option>
                        <option value="nonaktif" <?= $r['status'] == 'nonaktif' ? 'selected' : '' ?>>Nonaktif</option>
                    </select>
                </div>
            </div>
            <div class="row mb-3">
                <div class="col-md-6 mb-2">
                    <div class="rule-if-box"><strong class="text-primary">IF:</strong> <em><?= htmlspecialchars($r['kondisi']) ?></em></div>
                </div>
                <div class="col-md-6 mb-2">
                    <div class="rule-then-box"><strong class="text-success">THEN:</strong> <em><?= htmlspecialchars($r['aksi']) ?></em></div>
                </div>
            </div>
            <div class="form-group mb-3">
                <label class="font-weight-bold small">Deskripsi</label>
                <div class="d-none d-print-block small text-muted"><?= nl2br(htmlspecialchars($r['deskripsi'] ?: '-')) ?></div>
                <textarea name="deskripsi" class="form-control form-control-sm" rows="2"><?= htmlspecialchars($r['deskripsi'] ?? '') ?></textarea>
            </div>
            <div class="d-flex justify-content-between align-items-center">
                <small class="text-muted">Prioritas: <?= $r['prioritas'] ?></small>
                <div class="print-actions">
                    <button type="submit" class="btn btn-sm btn-primary"><i class="fas fa-save"></i> Simpan Perubahan</button>
                </div>
            </div>
        </form>
    </div>
</div>
<?php endwhile; ?>

<?php page_print_area_close(); ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
