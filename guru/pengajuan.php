<?php
$page_title = 'Ajukan Penggunaan Lab';
require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/rules_engine.php';
check_login();
check_role(['guru']);

$guru_id = $_SESSION['user_id'];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $lab_id = (int)($_POST['lab_id'] ?? 0);
    $tanggal = sanitize($_POST['tanggal']);
    $jam_mulai = sanitize($_POST['jam_mulai']);
    $jam_selesai = sanitize($_POST['jam_selesai']);
    $kelas = sanitize($_POST['kelas'] ?? '');
    $mapel = sanitize($_POST['mata_pelajaran'] ?? '');
    $tujuan = sanitize($_POST['tujuan'] ?? '');

    if (!$lab_id || !$tanggal || !$jam_mulai || !$jam_selesai) {
        set_alert('danger', 'Semua field wajib diisi!');
    } elseif ($jam_mulai >= $jam_selesai) {
        set_alert('danger', 'Jam mulai harus sebelum jam selesai!');
    } elseif ($tanggal < date('Y-m-d')) {
        set_alert('danger', 'Tidak bisa mengajukan untuk tanggal yang sudah lewat!');
    } elseif ($maint = check_lab_maintenance($lab_id, $tanggal, $jam_mulai, $jam_selesai)) {
        set_alert('danger', 'Laboratorium <strong>' . htmlspecialchars($maint['nama_lab']) . '</strong> sedang dalam jadwal maintenance pada ' . format_tanggal($tanggal) . ' pukul ' . substr($maint['jam_mulai'],0,5) . ' - ' . substr($maint['jam_selesai'],0,5) . '. Silakan pilih lab lain atau waktu berbeda.');
    } else {
        $sql = "INSERT INTO peminjaman (guru_id, lab_id, tanggal, jam_mulai, jam_selesai, kelas, mata_pelajaran, tujuan, status, created_at)
                VALUES ($guru_id, $lab_id, '$tanggal', '$jam_mulai', '$jam_selesai', '$kelas', '$mapel', '$tujuan', 'pending', NOW())";
        if (mysqli_query($koneksi, $sql)) {
            $peminjaman_id = mysqli_insert_id($koneksi);
            $result = process_pengajuan($peminjaman_id);

            if ($result['success']) {
                set_alert('success', $result['message']);
            } else {
                $msg = $result['message'];
                if (!empty($result['rekomendasi'])) {
                    $msg .= '<br><strong>Rekomendasi Jadwal Tersedia:</strong><ul>';
                    foreach ($result['rekomendasi'] as $lab_slots) {
                        foreach ($lab_slots['slots'] as $slot) {
                            $msg .= '<li>' . $lab_slots['nama_lab'] . ': ' . $slot['jam_mulai'] . ' - ' . $slot['jam_selesai'] . '</li>';
                        }
                    }
                    $msg .= '</ul>';
                }
                if (!empty($result['is_bentrok'])) {
                    set_bentrok_popup([
                        'alasan' => $result['alasan'] ?? $result['message'],
                        'rekomendasi' => $result['rekomendasi'] ?? [],
                        'id' => $peminjaman_id,
                    ]);
                }
                set_alert('warning', $msg);
            }
        } else {
            set_alert('danger', 'Gagal menyimpan pengajuan: ' . mysqli_error($koneksi));
        }
    }
    header("Location: pengajuan.php");
    exit;
}

// Check available slots via AJAX (must be before HTML output)
if (isset($_GET['check_slots'])) {
    header('Content-Type: application/json');
    $lab_id = (int)$_GET['lab_id'];
    $tanggal = sanitize($_GET['tanggal']);
    $durasi = (int)($_GET['durasi'] ?? 2);
    $slots = get_available_slots($lab_id, $tanggal, $durasi);
    echo json_encode($slots);
    exit;
}

// Check maintenance status via AJAX
if (isset($_GET['check_maintenance'])) {
    header('Content-Type: application/json');
    $lab_id = (int)$_GET['lab_id'];
    $tanggal = sanitize($_GET['tanggal']);
    $jam_mulai = sanitize($_GET['jam_mulai'] ?? '');
    $jam_selesai = sanitize($_GET['jam_selesai'] ?? '');
    $maint = check_lab_maintenance($lab_id, $tanggal, $jam_mulai ?: null, $jam_selesai ?: null);
    echo json_encode([
        'maintenance' => $maint ? true : false,
        'info' => $maint ? [
            'lab' => $maint['nama_lab'],
            'jenis' => $maint['jenis'],
            'deskripsi' => $maint['deskripsi'],
            'status' => $maint['status'],
            'jam' => substr($maint['jam_mulai'],0,5) . ' - ' . substr($maint['jam_selesai'],0,5)
        ] : null
    ]);
    exit;
}

require_once __DIR__ . '/../includes/header.php';

$bentrok_popup = pull_bentrok_popup();

// My submissions list
$fq = sanitize($_GET['search'] ?? '');

$where = "WHERE p.guru_id = $guru_id";
$where .= page_search_where($fq, [
    "l.nama_lab", "p.kelas", "p.mata_pelajaran", "p.tujuan", "p.status",
    "CONCAT('PJN-', LPAD(p.id, 5, '0'))"
]);

$submissions = mysqli_query($koneksi, "
    SELECT p.*, l.nama_lab, c.waktu_check_in, c.waktu_check_out
    FROM peminjaman p
    LEFT JOIN laboratorium l ON p.lab_id = l.id
    LEFT JOIN check_in c ON c.peminjaman_id = p.id
    $where
    ORDER BY p.tanggal DESC, p.jam_mulai DESC
");
?>

<?php guru_page_header('Ajukan Penggunaan Lab', 'Formulir pengajuan diproses otomatis oleh Rule-Based System'); ?>

<!-- Form -->
<div class="card guru-panel border-0 mb-4 no-print">
    <div class="card-header guru-card-header-accent">
        <h6 class="mb-0 font-weight-bold"><i class="fas fa-paper-plane mr-2"></i>Form Pengajuan Baru</h6>
    </div>
    <div class="card-body">
        <form method="POST" id="formPengajuan">
            <!-- Maintenance Warning -->
            <div id="maintenanceWarning" class="alert alert-danger d-none mb-3">
                <i class="fas fa-wrench"></i> <strong>Perhatian!</strong> Laboratorium ini sedang dalam jadwal <strong>maintenance</strong>.
                <br><small id="maintDetail"></small>
                <br><strong>Pengajuan tidak dapat dilakukan pada waktu yang bentrok dengan maintenance. Silakan pilih lab lain atau waktu berbeda.</strong>
            </div>
            <div class="form-row">
                <div class="form-group col-md-6">
                    <label>Laboratorium <span class="text-danger">*</span></label>
                    <?= lab_dropdown('lab_id', '', 'form-control') ?>
                </div>
                <div class="form-group col-md-6">
                    <label>Tanggal <span class="text-danger">*</span></label>
                    <input type="date" name="tanggal" class="form-control" required min="<?= date('Y-m-d') ?>" id="inputTanggal">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group col-md-3">
                    <label>Jam Mulai <span class="text-danger">*</span></label>
                    <input type="time" name="jam_mulai" class="form-control" required id="inputMulai">
                </div>
                <div class="form-group col-md-3">
                    <label>Jam Selesai <span class="text-danger">*</span></label>
                    <input type="time" name="jam_selesai" class="form-control" required id="inputSelesai">
                </div>
                <div class="form-group col-md-3">
                    <label>Kelas</label>
                    <input type="text" name="kelas" class="form-control" placeholder="Contoh: X IPA 1">
                </div>
                <div class="form-group col-md-3">
                    <label>Mata Pelajaran</label>
                    <input type="text" name="mata_pelajaran" class="form-control" placeholder="Contoh: TIK">
                </div>
            </div>
            <div class="form-group">
                <label>Tujuan Penggunaan</label>
                <textarea name="tujuan" class="form-control" rows="2" placeholder="Deskripsikan tujuan penggunaan lab..."></textarea>
            </div>
            <div id="slotInfo" class="alert alert-info d-none mb-3"></div>
            <button type="submit" class="btn btn-primary" id="btnSubmit"><i class="fas fa-paper-plane"></i> Kirim Pengajuan</button>
            <button type="button" class="btn btn-outline-info ml-2" onclick="checkSlots()"><i class="fas fa-search"></i> Cek Ketersediaan</button>
        </form>
    </div>
</div>

<?php page_search_only_bar([
    'panel' => 'guru-panel',
    'placeholder' => 'Cari lab, kelas, mapel, kode...',
    'search' => $fq,
    'reset_url' => 'pengajuan.php',
]); ?>

<?php page_print_area_open(); ?>

<!-- Submissions List -->
<div class="card guru-panel border-0">
    <div class="card-header guru-card-header">
        <h6 class="mb-0 font-weight-bold"><i class="fas fa-list mr-2"></i>Daftar Pengajuan Saya</h6>
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
                    if (mysqli_num_rows($submissions) > 0):
                        mysqli_data_seek($submissions, 0);
                        while ($s = mysqli_fetch_assoc($submissions)):
                            $ci = has_checkin($s['id']);
                    ?>
                    <tr>
                        <td><?= $no++ ?></td>
                        <td><?= format_tanggal($s['tanggal']) ?></td>
                        <td><?= htmlspecialchars($s['nama_lab'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($s['kelas'] ?: '-') ?></td>
                        <td><?= htmlspecialchars($s['mata_pelajaran'] ?: '-') ?></td>
                        <td><?= substr($s['jam_mulai'], 0, 5) ?>–<?= substr($s['jam_selesai'], 0, 5) ?></td>
                        <td><?= status_badge($s['status']) ?><?php if ($s['status'] == 'ditolak' && is_bentrok_alasan($s['alasan_penolakan'])): ?><br><small class="text-danger font-weight-bold"><i class="fas fa-exclamation-triangle"></i> Bentrok</small><?php endif; ?></td>
                        <td><?= $s['waktu_check_in'] ? '<small class="text-success"><i class="fas fa-check"></i> ' . substr($s['waktu_check_in'], 11, 5) . '</small>' : '<small class="text-warning"><i class="fas fa-clock"></i> Belum</small>' ?></td>
                        <td><?= page_checkout_cell($s['waktu_check_out'] ?? null) ?></td>
                        <td class="text-center text-nowrap col-aksi no-print d-print-none">
                            <button class="btn btn-sm btn-outline-primary" data-toggle="modal" data-target="#detail<?= $s['id'] ?>" title="Detail"><i class="fas fa-eye"></i></button>
                        </td>
                    </tr>
                    <?php endwhile; else: ?>
                    <tr><td colspan="10" class="text-center py-5 text-muted">Belum ada pengajuan</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php page_print_area_close(); ?>

<?php
if (mysqli_num_rows($submissions) > 0):
    mysqli_data_seek($submissions, 0);
    while ($s = mysqli_fetch_assoc($submissions)):
        $sid = $s['id'];
        $ci = has_checkin($sid);
?>
                    <!-- Detail Modal -->
                    <div class="modal fade" id="detail<?= $sid ?>" tabindex="-1">
                        <div class="modal-dialog modal-lg">
                            <div class="modal-content">
                                <div class="modal-header bg-primary text-white">
                                    <h6 class="modal-title">Detail Pengajuan — PJN-<?= str_pad($sid, 5, '0', STR_PAD_LEFT) ?></h6>
                                    <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
                                </div>
                                <div class="modal-body">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <table class="table table-sm table-borderless">
                                                <tr><th class="text-muted">Lab</th><td><strong><?= htmlspecialchars($s['nama_lab'] ?? '-') ?></strong></td></tr>
                                                <tr><th class="text-muted">Tanggal</th><td><?= format_tanggal($s['tanggal']) ?></td></tr>
                                                <tr><th class="text-muted">Waktu</th><td><?= substr($s['jam_mulai'], 0, 5) ?> – <?= substr($s['jam_selesai'], 0, 5) ?></td></tr>
                                                <tr><th class="text-muted">Kelas</th><td><?= htmlspecialchars($s['kelas'] ?: '-') ?></td></tr>
                                                <tr><th class="text-muted">Mapel</th><td><?= htmlspecialchars($s['mata_pelajaran'] ?: '-') ?></td></tr>
                                            </table>
                                        </div>
                                        <div class="col-md-6">
                                            <table class="table table-sm table-borderless">
                                                <tr><th class="text-muted">Status</th><td><?= status_badge($s['status']) ?></td></tr>
                                                <tr><th class="text-muted">Check-in</th><td><?= checkin_badge($ci) ?></td></tr>
                                                <tr><th class="text-muted">Check-out</th><td><?= checkout_badge($ci) ?></td></tr>
                                                <?php if ($ci && !empty($ci['waktu_check_in'])): ?>
                                                <tr><th class="text-muted">Waktu In</th><td><?= format_datetime($ci['waktu_check_in']) ?></td></tr>
                                                <?php endif; ?>
                                                <?php if ($ci && !empty($ci['waktu_check_out'])): ?>
                                                <tr><th class="text-muted">Waktu Out</th><td><?= format_datetime($ci['waktu_check_out']) ?></td></tr>
                                                <?php endif; ?>
                                                <tr><th class="text-muted">Diajukan</th><td><?= format_datetime($s['created_at']) ?></td></tr>
                                            </table>
                                        </div>
                                    </div>
                                    <?php if ($s['tujuan']): ?>
                                    <div class="p-3 mt-2" style="background:#f8f9fa;border-radius:8px">
                                        <strong class="text-muted small d-block mb-1">Tujuan</strong><?= nl2br(htmlspecialchars($s['tujuan'])) ?>
                                    </div>
                                    <?php endif; ?>
                                    <?php if ($s['alasan_penolakan']): ?>
                                    <?= render_penolakan_notice($s['alasan_penolakan'], $s['rekomendasi_jadwal'] ?? '') ?>
                                    <?php elseif ($s['rekomendasi_jadwal']): ?>
                                    <div class="alert alert-info mt-2 mb-0 py-2 alert-persistent"><strong>Rekomendasi:</strong> <?= htmlspecialchars($s['rekomendasi_jadwal']) ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
<?php endwhile; endif; ?>

<?php if ($bentrok_popup): ?>
<div class="modal fade" id="modalBentrokPopup" tabindex="-1" role="dialog" data-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-danger">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title"><i class="fas fa-exclamation-triangle mr-2"></i>Pengajuan Ditolak — Jadwal Bentrok</h5>
                <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body">
                <p class="mb-3">Waktu pengajuan Anda <strong>bentrok</strong> dengan jadwal guru lain pada lab yang sama:</p>
                <?= render_bentrok_notice($bentrok_popup['alasan'], $bentrok_popup['rekomendasi'] ?? '') ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-danger" data-dismiss="modal"><i class="fas fa-check"></i> Saya Mengerti</button>
                <?php if (!empty($bentrok_popup['id'])): ?>
                <button type="button" class="btn btn-outline-primary" id="btnLihatDetailBentrok"><i class="fas fa-eye"></i> Lihat Detail</button>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    $('#modalBentrokPopup').modal('show');
    var btnDetail = document.getElementById('btnLihatDetailBentrok');
    if (btnDetail) {
        btnDetail.addEventListener('click', function() {
            $('#modalBentrokPopup').modal('hide');
            setTimeout(function() { $('#detail<?= (int)$bentrok_popup['id'] ?>').modal('show'); }, 400);
        });
    }
});
</script>
<?php endif; ?>

<script>
function checkMaintenance() {
    var lab = document.querySelector('[name=lab_id]').value;
    var tgl = document.getElementById('inputTanggal').value;
    var mulai = document.getElementById('inputMulai').value;
    var selesai = document.getElementById('inputSelesai').value;
    var warn = document.getElementById('maintenanceWarning');
    var detail = document.getElementById('maintDetail');
    var btn = document.getElementById('btnSubmit');

    if (!lab || !tgl) { warn.classList.add('d-none'); if(btn) btn.disabled=false; return; }

    var url = 'pengajuan.php?check_maintenance=1&lab_id=' + lab + '&tanggal=' + tgl;
    if (mulai && selesai) url += '&jam_mulai=' + mulai + '&jam_selesai=' + selesai;

    fetch(url)
        .then(r => r.json())
        .then(data => {
            if (data.maintenance) {
                var statusLabel = data.info.status === 'berlangsung' ? ' (sedang berlangsung)' : ' (dijadwalkan)';
                detail.textContent = data.info.lab + ' - ' + data.info.jenis + statusLabel + ' (' + data.info.jam + ')' + (data.info.deskripsi ? ' - ' + data.info.deskripsi : '');
                warn.classList.remove('d-none');
                if (mulai && selesai) {
                    warn.className = 'alert alert-danger d-block mb-3';
                    if(btn) btn.disabled = true;
                } else {
                    warn.className = 'alert alert-warning d-block mb-3';
                    if(btn) btn.disabled = false;
                }
            } else {
                warn.classList.add('d-none');
                if(btn) btn.disabled = false;
            }
        });
}

// Also check if selected option has data-maintenance attribute
var selectLab = document.getElementById('select_lab');
if (selectLab) {
    selectLab.addEventListener('change', checkMaintenance);
}
var inputTgl = document.getElementById('inputTanggal');
if (inputTgl) inputTgl.addEventListener('change', checkMaintenance);
var inputMulai = document.getElementById('inputMulai');
if (inputMulai) inputMulai.addEventListener('change', checkMaintenance);
var inputSelesai = document.getElementById('inputSelesai');
if (inputSelesai) inputSelesai.addEventListener('change', checkMaintenance);

function checkSlots() {
    var lab = document.querySelector('[name=lab_id]').value;
    var tgl = document.getElementById('inputTanggal').value;
    var mulai = document.getElementById('inputMulai').value;
    var selesai = document.getElementById('inputSelesai').value;
    var info = document.getElementById('slotInfo');

    if (!lab || !tgl) { info.innerHTML='<i class="fas fa-info-circle"></i> Pilih lab dan tanggal terlebih dahulu.'; info.classList.remove('d-none'); return; }

    var durasi = 2;
    if (mulai && selesai) {
        var h1 = parseInt(mulai.split(':')[0]), h2 = parseInt(selesai.split(':')[0]);
        durasi = Math.max(1, h2 - h1);
    }

    fetch('pengajuan.php?check_slots=1&lab_id=' + lab + '&tanggal=' + tgl + '&durasi=' + durasi)
        .then(r => r.json())
        .then(slots => {
            if (slots.length === 0) {
                info.className = 'alert alert-warning mb-3';
                info.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Tidak ada slot tersedia untuk durasi ' + durasi + ' jam.';
            } else {
                info.className = 'alert alert-success mb-3';
                var html = '<i class="fas fa-check-circle"></i> <strong>Slot tersedia:</strong><br>';
                slots.slice(0, 5).forEach(s => { html += '<span class="badge badge-success mr-1">' + s.jam_mulai + '-' + s.jam_selesai + '</span>'; });
                info.innerHTML = html;
            }
            info.classList.remove('d-none');
        });
}
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>




