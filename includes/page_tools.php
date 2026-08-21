<?php
/**
 * Helper pencarian, filter bar, dan cetak — konsisten di semua halaman
 */

function page_search_where($q, array $columns) {
    if (empty(trim($q ?? ''))) return '';
    global $koneksi;
    $q = mysqli_real_escape_string($koneksi, trim($q));
    $parts = [];
    foreach ($columns as $col) {
        $parts[] = "$col LIKE '%$q%'";
    }
    return ' AND (' . implode(' OR ', $parts) . ')';
}

function page_print_button($class = 'btn btn-sm btn-outline-dark mb-1') {
    return '<button type="button" class="' . htmlspecialchars($class) . ' no-print" onclick="window.print()"><i class="fas fa-print"></i> Cetak</button>';
}

/** Class untuk kolom aksi — disembunyikan otomatis saat preview/cetak */
function col_aksi_class($extra = '') {
    return trim('col-aksi no-print d-print-none ' . $extra);
}

function th_col_aksi($extra = 'text-center') {
    return '<th class="' . htmlspecialchars(col_aksi_class($extra)) . '">Aksi</th>';
}

function td_col_aksi_open($extra = 'text-center text-nowrap') {
    return '<td class="' . htmlspecialchars(col_aksi_class($extra)) . '">';
}

function page_toolbar($opts = []) {
    $search = $opts['search'] ?? ($_GET['search'] ?? '');
    $placeholder = $opts['placeholder'] ?? 'Cari...';
    $reset_url = $opts['reset_url'] ?? '';
    $show_search = $opts['show_search'] ?? true;
    $show_print = $opts['show_print'] ?? true;
    $panel = $opts['panel'] ?? 'admin-panel';
    $filters_html = $opts['filters_html'] ?? '';
    $active = $opts['active_filters'] ?? null;
    if ($active === null) {
        $active = !empty($search) || !empty($opts['active_filters_hint']);
    }
    ?>
    <div class="card <?= htmlspecialchars($panel) ?> border-0 mb-3 no-print page-tools-bar">
        <div class="card-body py-3 admin-filter-bar">
            <form method="GET" class="form-inline flex-wrap align-items-center w-100">
                <?php if ($show_search): ?>
                <label class="mr-2 text-muted small mb-1"><i class="fas fa-search"></i></label>
                <input type="text" name="search" class="form-control form-control-sm mr-2 mb-1" style="min-width:180px" placeholder="<?= htmlspecialchars($placeholder) ?>" value="<?= htmlspecialchars($search) ?>">
                <?php endif; ?>
                <?= $filters_html ?>
                <button type="submit" class="btn btn-sm btn-primary mr-2 mb-1"><i class="fas fa-filter"></i> Terapkan</button>
                <?php if ($active && $reset_url): ?>
                <a href="<?= htmlspecialchars($reset_url) ?>" class="btn btn-sm btn-outline-secondary mr-2 mb-1">Reset</a>
                <?php endif; ?>
                <?php if ($show_print): ?>
                <button type="button" class="btn btn-sm btn-outline-dark mb-1" onclick="window.print()"><i class="fas fa-print"></i> Cetak</button>
                <?php endif; ?>
            </form>
        </div>
    </div>
    <?php
}

function page_print_area_open($id = 'printArea') {
    echo '<div id="' . htmlspecialchars($id) . '" class="print-area">';
}

function page_print_area_close() {
    echo '</div>';
}

function live_clock_widget() {
    ?>
    <div class="live-clock-widget text-right mt-2 mt-md-0">
        <div class="live-clock-day font-weight-bold text-dark" style="font-size:14px">&nbsp;</div>
        <div class="live-clock-date text-muted" style="font-size:13px">&nbsp;</div>
        <div class="live-clock-time text-muted" style="font-size:13px"><i class="far fa-clock"></i> <span class="live-clock-time-val">--:--:--</span> WIB</div>
    </div>
    <?php
}

function page_checkout_cell($waktu_check_out) {
    if ($waktu_check_out) {
        return '<small class="text-info"><i class="fas fa-sign-out-alt"></i> ' . substr($waktu_check_out, 11, 5) . '</small>';
    }
    return '<small class="text-muted"><i class="fas fa-clock"></i> Belum</small>';
}

function page_search_only_bar($opts = []) {
    $opts['filters_html'] = '';
    $opts['active_filters'] = !empty($opts['search'] ?? ($_GET['search'] ?? ''));
    page_toolbar($opts);
}

function is_bentrok_alasan($alasan) {
    return !empty($alasan) && stripos($alasan, 'bentrok') !== false;
}

function format_rekomendasi_html($rekomendasi) {
    if (empty($rekomendasi)) {
        return '';
    }
    if (is_string($rekomendasi)) {
        return nl2br(htmlspecialchars($rekomendasi));
    }
    $html = '<ul class="mb-0 pl-3">';
    foreach ($rekomendasi as $lab_slots) {
        $nama = htmlspecialchars($lab_slots['nama_lab'] ?? 'Lab');
        foreach ($lab_slots['slots'] ?? [] as $slot) {
            $html .= '<li>' . $nama . ': ' . htmlspecialchars($slot['jam_mulai'] . ' - ' . $slot['jam_selesai']) . '</li>';
        }
    }
    $html .= '</ul>';
    return $html;
}

function render_bentrok_notice($alasan, $rekomendasi = '') {
    ob_start();
    ?>
    <div class="bentrok-notice alert-persistent alert alert-danger mb-0 mt-3" role="alert">
        <div class="d-flex align-items-start">
            <i class="fas fa-exclamation-triangle fa-lg mr-3 mt-1"></i>
            <div class="flex-grow-1">
                <h6 class="alert-heading font-weight-bold mb-2">Peringatan — Jadwal Bentrok</h6>
                <p class="mb-0"><?= htmlspecialchars($alasan) ?></p>
                <?php if ($rekomendasi): ?>
                <div class="small border-top pt-2 mt-2 mb-0">
                    <strong>Rekomendasi jadwal tersedia:</strong>
                    <?= format_rekomendasi_html($rekomendasi) ?>
                </div>
                <?php endif; ?>
                <small class="text-muted d-block mt-2"><i class="fas fa-info-circle"></i> Informasi bentrok tetap ditampilkan pada detail pengajuan.</small>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

function render_penolakan_notice($alasan, $rekomendasi = '') {
    if (is_bentrok_alasan($alasan)) {
        return render_bentrok_notice($alasan, $rekomendasi);
    }
    ob_start();
    ?>
    <div class="alert-persistent alert alert-danger mt-2 mb-0 py-2" role="alert">
        <strong>Alasan Penolakan:</strong> <?= htmlspecialchars($alasan) ?>
    </div>
    <?php
    return ob_get_clean();
}
