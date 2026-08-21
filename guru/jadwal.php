<?php

$page_title = 'Jadwal Lab';

require_once __DIR__ . '/../includes/header.php';

check_role(['guru']);



$fq = sanitize($_GET['search'] ?? '');

$where = "WHERE 1=1";

$where .= page_search_where($fq, ["j.hari", "l.nama_lab", "u.nama", "j.kelas", "j.mata_pelajaran"]);



$jadwal = mysqli_query($koneksi, "SELECT j.*, u.nama as nama_guru, l.nama_lab FROM jadwal j

    LEFT JOIN users u ON j.guru_id = u.id LEFT JOIN laboratorium l ON j.lab_id = l.id

    $where ORDER BY FIELD(j.hari,'Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'), j.jam_mulai");

?>



<?php guru_page_header('Jadwal Lab', 'Jadwal praktikum laboratorium komputer'); ?>



<?php page_search_only_bar([

    'panel' => 'guru-panel',

    'placeholder' => 'Cari hari, lab, guru, mapel...',

    'search' => $fq,

    'reset_url' => 'jadwal.php',

]); ?>



<?php page_print_area_open(); ?>



<div class="card guru-panel border-0">

    <div class="card-header guru-card-header">

        <h6 class="mb-0 font-weight-bold"><i class="fas fa-calendar-alt mr-2"></i>Data Jadwal Praktikum</h6>

    </div>

    <div class="card-body p-0">

        <div class="table-responsive">

            <table class="table guru-table mb-0">

                <thead>

                    <tr><th>No</th><th>Hari</th><th>Lab</th><th>Guru</th><th>Kelas</th><th>Mapel</th><th>Waktu</th></tr>

                </thead>

                <tbody>

                <?php $no = 1; if (mysqli_num_rows($jadwal) > 0): while ($j = mysqli_fetch_assoc($jadwal)): ?>

                    <tr>

                        <td><?= $no++ ?></td>

                        <td><strong><?= htmlspecialchars($j['hari']) ?></strong></td>

                        <td><?= htmlspecialchars($j['nama_lab'] ?? '-') ?></td>

                        <td><?= htmlspecialchars($j['nama_guru'] ?? '-') ?></td>

                        <td><?= htmlspecialchars($j['kelas'] ?: '-') ?></td>

                        <td><?= htmlspecialchars($j['mata_pelajaran'] ?: '-') ?></td>

                        <td><?= substr($j['jam_mulai'], 0, 5) ?>–<?= substr($j['jam_selesai'], 0, 5) ?></td>

                    </tr>

                <?php endwhile; else: ?>

                    <tr><td colspan="7" class="text-center py-5 text-muted">Belum ada jadwal tersedia</td></tr>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

    </div>

</div>



<?php page_print_area_close(); ?>



<?php require_once __DIR__ . '/../includes/footer.php'; ?>

