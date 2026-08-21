<?php
/**
 * Helper layout halaman guru — tampilan profesional & konsisten
 */

function guru_page_header($title, $subtitle = '', $action_html = '') {
    ?>
    <div class="guru-page-header mb-4">
        <div class="d-flex justify-content-between align-items-start flex-wrap">
            <div>
                <h4 class="mb-1 font-weight-bold text-dark"><?= htmlspecialchars($title) ?></h4>
                <?php if ($subtitle): ?>
                    <p class="text-muted mb-0 guru-page-subtitle"><?= htmlspecialchars($subtitle) ?></p>
                <?php endif; ?>
            </div>
            <?php if ($action_html): ?>
                <div class="mt-2 mt-md-0 no-print"><?= $action_html ?></div>
            <?php endif; ?>
        </div>
    </div>
    <?php
}
