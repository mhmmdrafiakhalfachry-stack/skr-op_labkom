<?php
/**
 * Helper layout halaman admin — tampilan formal & konsisten
 */

function admin_page_header($title, $subtitle = '', $action_html = '') {
    ?>
    <div class="admin-page-header mb-4">
        <div class="d-flex justify-content-between align-items-start flex-wrap">
            <div>
                <h4 class="mb-1 font-weight-bold text-dark"><?= htmlspecialchars($title) ?></h4>
                <?php if ($subtitle): ?>
                    <p class="text-muted mb-0 admin-page-subtitle"><?= htmlspecialchars($subtitle) ?></p>
                <?php endif; ?>
            </div>
            <?php if ($action_html): ?>
                <div class="mt-2 mt-md-0 no-print"><?= $action_html ?></div>
            <?php endif; ?>
        </div>
    </div>
    <?php
}

function admin_filter_card_open() {
    echo '<div class="card admin-panel border-0 mb-3"><div class="card-body py-3">';
}

function admin_filter_card_close() {
    echo '</div></div>';
}

function admin_table_card_open($title = '', $icon = 'fas fa-table') {
    ?>
    <div class="card admin-panel border-0">
        <?php if ($title): ?>
        <div class="card-header bg-white border-bottom py-3">
            <h6 class="mb-0 font-weight-bold"><i class="<?= htmlspecialchars($icon) ?> text-primary mr-2"></i><?= htmlspecialchars($title) ?></h6>
        </div>
        <?php endif; ?>
        <div class="card-body p-0">
            <div class="table-responsive">
    <?php
}

function admin_table_card_close() {
    echo '</div></div></div>';
}
