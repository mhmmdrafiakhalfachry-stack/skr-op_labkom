    </div><!-- /.content-wrapper -->
</div><!-- /.main-content -->

<!-- Modal Konfirmasi -->
<div class="modal fade" id="modalConfirmAction" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content confirm-delete-modal">
            <div class="modal-header confirm-delete-header">
                <h5 class="modal-title" id="confirmModalTitle">Konfirmasi</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Tutup">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body confirm-delete-body text-center">
                <div class="confirm-delete-icon confirm-icon-info" id="confirmModalIcon">
                    <i class="fas fa-question"></i>
                </div>
                <h5 class="confirm-delete-question" id="confirmModalMessage">Yakin ingin melanjutkan?</h5>
                <p class="confirm-delete-desc text-muted mb-0" id="confirmModalDesc"></p>
            </div>
            <div class="modal-footer confirm-delete-footer">
                <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-primary" id="confirmModalBtn">Oke</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/jquery@3.5.1/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Auto-hide flash alerts after 4 seconds (not modal/persistent notices)
setTimeout(function() {
    document.querySelectorAll('.flash-alert:not(.alert-no-autohide)').forEach(function(alert) {
        $(alert).fadeOut('slow');
    });
}, 4000);

// Live clock (WIB) for dashboard widgets
(function() {
    var widgets = document.querySelectorAll('.live-clock-widget');
    if (!widgets.length) return;
    var days = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
    var months = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
    function pad(n) { return n < 10 ? '0' + n : '' + n; }
    function tick() {
        var now = new Date();
        var utc = now.getTime() + (now.getTimezoneOffset() * 60000);
        var wib = new Date(utc + (7 * 3600000));
        var dayStr = days[wib.getDay()] + ', ' + wib.getDate() + ' ' + months[wib.getMonth()] + ' ' + wib.getFullYear();
        var timeStr = pad(wib.getHours()) + ':' + pad(wib.getMinutes()) + ':' + pad(wib.getSeconds());
        widgets.forEach(function(w) {
            var dayEl = w.querySelector('.live-clock-day');
            var dateEl = w.querySelector('.live-clock-date');
            var timeEl = w.querySelector('.live-clock-time-val');
            if (dayEl) dayEl.textContent = days[wib.getDay()];
            if (dateEl) dateEl.textContent = wib.getDate() + ' ' + months[wib.getMonth()] + ' ' + wib.getFullYear();
            if (timeEl) timeEl.textContent = timeStr;
        });
    }
    tick();
    setInterval(tick, 1000);
})();

// Pastikan kolom aksi tidak ikut saat preview/cetak browser
(function() {
    function preparePrint() {
        document.body.classList.add('is-printing');
        document.querySelectorAll('.col-aksi, .print-actions, [data-print-hide="true"]').forEach(function(el) {
            el.setAttribute('data-print-hidden', '1');
            el.style.display = 'none';
        });
    }
    function restorePrint() {
        document.body.classList.remove('is-printing');
        document.querySelectorAll('[data-print-hidden="1"]').forEach(function(el) {
            el.removeAttribute('data-print-hidden');
            el.style.display = '';
        });
    }
    if (window.matchMedia) {
        var mq = window.matchMedia('print');
        if (mq.addEventListener) {
            mq.addEventListener('change', function(e) { e.matches ? preparePrint() : restorePrint(); });
        } else if (mq.addListener) {
            mq.addListener(function(e) { e.matches ? preparePrint() : restorePrint(); });
        }
    }
    window.addEventListener('beforeprint', preparePrint);
    window.addEventListener('afterprint', restorePrint);
})();

// Modal konfirmasi (ganti dialog confirm() bawaan browser)
(function() {
    var pendingUrl = null;
    var pendingForm = null;
    var pendingSubmitter = null;
    var defaultDeleteDesc = 'Data yang dihapus tidak dapat dikembalikan dan akan hilang secara permanen.';

    function readConfirmOptions(el, defaults) {
        defaults = defaults || {};
        return {
            title: el.getAttribute('data-confirm-title') || defaults.title || 'Konfirmasi',
            message: el.getAttribute('data-confirm-message') || defaults.message || 'Yakin ingin melanjutkan?',
            description: el.getAttribute('data-confirm-desc') || defaults.description || '',
            icon: el.getAttribute('data-confirm-icon') || defaults.icon || 'question',
            iconStyle: el.getAttribute('data-confirm-icon-style') || defaults.iconStyle || 'info',
            btnClass: el.getAttribute('data-confirm-btn-class') || defaults.btnClass || 'btn-primary',
            btnText: el.getAttribute('data-confirm-btn-text') || defaults.btnText || 'Oke',
            url: el.tagName === 'A' ? el.getAttribute('href') : null,
            form: defaults.form || null
        };
    }

    window.showConfirmModal = function(options) {
        options = options || {};
        $('#confirmModalTitle').text(options.title || 'Konfirmasi');
        $('#confirmModalMessage').text(options.message || 'Yakin ingin melanjutkan?');

        var descEl = $('#confirmModalDesc');
        if (options.description) {
            descEl.text(options.description).show();
        } else {
            descEl.text('').hide();
        }

        var iconWrap = $('#confirmModalIcon');
        iconWrap.removeClass('confirm-icon-danger confirm-icon-warning confirm-icon-success confirm-icon-info');
        iconWrap.addClass('confirm-icon-' + (options.iconStyle || 'info'));
        $('#confirmModalIcon i').attr('class', 'fas fa-' + (options.icon || 'question'));

        var btn = $('#confirmModalBtn');
        btn.removeClass('btn-danger btn-primary btn-warning btn-success btn-info');
        btn.addClass(options.btnClass || 'btn-primary');
        btn.text(options.btnText || 'Oke');

        pendingUrl = options.url || null;
        pendingForm = options.form || null;
        $('#modalConfirmAction').modal('show');
    };

    window.showDeleteConfirm = function(options) {
        options = options || {};
        if (!options.description) options.description = defaultDeleteDesc;
        options.icon = options.icon || 'question';
        options.iconStyle = options.iconStyle || 'danger';
        options.btnClass = options.btnClass || 'btn-danger';
        showConfirmModal(options);
    };

    $('#confirmModalBtn').on('click', function() {
        $('#modalConfirmAction').modal('hide');
        if (pendingForm) {
            pendingForm.setAttribute('data-confirmed', '1');
            pendingForm.submit();
        } else if (pendingUrl) {
            window.location.href = pendingUrl;
        }
        pendingUrl = null;
        pendingForm = null;
    });

    document.addEventListener('click', function(e) {
        var deleteEl = e.target.closest('[data-confirm-delete]');
        if (deleteEl) {
            e.preventDefault();
            showDeleteConfirm(readConfirmOptions(deleteEl, {
                icon: 'question',
                iconStyle: 'danger',
                btnClass: 'btn-danger'
            }));
            return;
        }

        var actionEl = e.target.closest('[data-confirm-action]');
        if (actionEl) {
            e.preventDefault();
            showConfirmModal(readConfirmOptions(actionEl));
        }
    });

    document.addEventListener('submit', function(e) {
        var form = e.target.closest('[data-confirm-form], [data-confirm-delete-form]');
        if (!form) return;
        if (form.getAttribute('data-confirmed') === '1') {
            form.removeAttribute('data-confirmed');
            return;
        }
        e.preventDefault();

        if (form.hasAttribute('data-confirm-delete') || form.hasAttribute('data-confirm-delete-form')) {
            var deleteOpts = readConfirmOptions(form, {
                form: form,
                description: defaultDeleteDesc,
                icon: 'question',
                iconStyle: 'danger',
                btnClass: 'btn-danger'
            });
            deleteOpts.form = form;
            showDeleteConfirm(deleteOpts);
            return;
        }

        var opts = readConfirmOptions(form, { form: form });
        opts.form = form;
        showConfirmModal(opts);
    });
})();
</script>
</body>
</html>
