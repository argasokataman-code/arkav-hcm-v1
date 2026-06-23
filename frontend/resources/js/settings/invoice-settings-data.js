/**
 * invoice-settings-data.js
 *
 * Loads and saves per-company invoice configuration from/to
 * GET|PUT /v1/hcm/invoice-settings (requires admin/owner RBAC).
 */
(function () {
    'use strict';

    var panel       = document.querySelector('[data-invoice-settings-panel]');
    var loadingCard = document.querySelector('[data-invoice-settings-loading]');
    var form        = document.querySelector('[data-invoice-settings-form]');
    var feedback    = document.querySelector('[data-invoice-settings-feedback]');
    var submitBtn   = document.querySelector('[data-invoice-settings-submit]');
    var resetBtn    = document.querySelector('[data-invoice-settings-reset]');
    var spinner     = document.querySelector('[data-invoice-settings-spinner]');
    var submitLabel = document.querySelector('[data-invoice-settings-submit-label]');
    var documentsList = document.querySelector('[data-invoice-documents-list]');
    var documentsEmpty = document.querySelector('[data-invoice-documents-empty]');
    var previewModalEl = document.querySelector('[data-invoice-preview-modal]');
    var previewTitle = document.querySelector('[data-invoice-preview-title]');
    var previewName = document.querySelector('[data-invoice-preview-name]');
    var previewCode = document.querySelector('[data-invoice-preview-code]');
    var previewTemplate = document.querySelector('[data-invoice-preview-template]');
    var previewGenerated = document.querySelector('[data-invoice-preview-generated]');
    var previewLatest = document.querySelector('[data-invoice-preview-latest]');
    var previewNote = document.querySelector('[data-invoice-preview-note]');
    var previewStatus = document.querySelector('[data-invoice-preview-status]');
    var previewFrame = document.querySelector('[data-invoice-preview-frame]');
    var previewDownload = document.querySelector('[data-invoice-preview-download]');
    var previewMock = document.querySelector('[data-invoice-preview-mock]');
    var previewModeDesignBtn = document.querySelector('[data-invoice-preview-mode="design"]');
    var previewModePdfBtn = document.querySelector('[data-invoice-preview-mode="pdf"]');

    if (!panel || !form) {
        return;
    }

    /** Snapshot of last successfully loaded server values, used by Reset. */
    var snapshot = {};
    var documentStatusSnapshot = {};
    var documentsByCode = {};
    var previewBlobUrl = null;
    var currentPreviewDocCode = null;
    var currentPreviewHasPdf = false;
    var currentPreviewPdfLoaded = false;

    // -------------------------------------------------------------------------
    // Auth / tenant helpers
    // -------------------------------------------------------------------------

    function getApiToken() {
        try {
            if (window.AuthApi && typeof window.AuthApi.getToken === 'function') {
                return window.AuthApi.getToken() || null;
            }
        } catch (_e) {}
        try {
            var key = (window.AuthApi && window.AuthApi.tokenKey) || 'arcav_access_token';
            return window.localStorage.getItem(key);
        } catch (_e) {
            return null;
        }
    }

    function getTenantContext() {
        try {
            if (window.AuthApi && typeof window.AuthApi.getTenantContext === 'function') {
                return window.AuthApi.getTenantContext() || {};
            }
        } catch (_e) {}
        return {};
    }

    function buildHeaders(extra) {
        var headers = {
            'Accept': 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        };
        var token = getApiToken();
        if (token) {
            headers['Authorization'] = 'Bearer ' + String(token);
        }
        var tenant = getTenantContext();
        if (tenant && tenant.companyCode) {
            headers['X-Company-Code'] = String(tenant.companyCode);
        }
        if (tenant && tenant.companyId) {
            headers['X-Company-Id'] = String(tenant.companyId);
        }
        if (tenant && tenant.companyUuid) {
            headers['X-Company-UUID'] = String(tenant.companyUuid);
        }
        if (extra) {
            Object.keys(extra).forEach(function (k) { headers[k] = extra[k]; });
        }
        return headers;
    }

    // -------------------------------------------------------------------------
    // Feedback helpers
    // -------------------------------------------------------------------------

    function showFeedback(type, message) {
        if (!feedback) { return; }
        feedback.classList.remove('d-none', 'alert-success', 'alert-danger', 'alert-warning', 'alert-info');
        feedback.classList.add('alert-' + type);
        feedback.textContent = message;
    }

    function clearFeedback() {
        if (!feedback) { return; }
        feedback.classList.add('d-none');
        feedback.textContent = '';
    }

    // -------------------------------------------------------------------------
    // DOM field helpers
    // -------------------------------------------------------------------------

    /**
     * Returns all input/select/textarea elements tagged with [data-invoice-settings-input]
     * plus all checkboxes tagged with [data-invoice-settings-toggle].
     */
    function getAllFields() {
        return Array.prototype.slice.call(
            document.querySelectorAll('[data-invoice-settings-input], [data-invoice-settings-toggle]')
        );
    }

    /**
     * Reads the current form values into a plain object keyed by data-invoice-field.
     */
    function readFormValues() {
        var values = {};
        getAllFields().forEach(function (el) {
            var key = el.getAttribute('data-invoice-field');
            if (!key) { return; }
            if (el.hasAttribute('data-invoice-settings-toggle')) {
                values[key] = el.checked ? '1' : '0';
            } else {
                values[key] = el.value;
            }
        });
        return values;
    }

    function readDocumentStatuses() {
        var map = {};
        if (!documentsList) { return map; }
        var nodes = documentsList.querySelectorAll('[data-invoice-doc-active]');
        Array.prototype.slice.call(nodes).forEach(function (el) {
            var code = el.getAttribute('data-doc-code');
            if (!code) { return; }
            map[code] = !!el.checked;
        });
        return map;
    }

    function applyDocumentStatuses(statusMap) {
        if (!documentsList || !statusMap) { return; }
        var nodes = documentsList.querySelectorAll('[data-invoice-doc-active]');
        Array.prototype.slice.call(nodes).forEach(function (el) {
            var code = el.getAttribute('data-doc-code');
            if (!code || !(code in statusMap)) { return; }
            el.checked = !!statusMap[code];
            var stateEl = documentsList.querySelector('[data-invoice-doc-state="' + code + '"]');
            if (stateEl) {
                stateEl.textContent = el.checked ? 'Active' : 'Inactive';
                stateEl.className = 'badge ' + (el.checked ? 'bg-success' : 'bg-secondary');
            }
        });
    }

    function formatDateTime(value) {
        if (!value) { return '-'; }
        var dt = new Date(value);
        if (Number.isNaN(dt.getTime())) {
            return String(value);
        }
        return dt.toLocaleString();
    }

    function cleanupPreviewBlob() {
        if (previewBlobUrl) {
            try {
                window.URL.revokeObjectURL(previewBlobUrl);
            } catch (_e) {}
            previewBlobUrl = null;
        }
    }

    function setPreviewStatus(message) {
        if (!previewStatus) { return; }
        previewStatus.textContent = message;
    }

    function setPreviewFrameSource(url) {
        if (!previewFrame) { return; }
        if (url) {
            previewFrame.src = url;
            return;
        }
        previewFrame.removeAttribute('src');
    }

    function setPreviewMode(mode, hasPdf) {
        var usePdf = mode === 'pdf' && !!hasPdf;

        if (previewFrame) {
            previewFrame.classList.toggle('d-none', !usePdf);
        }
        if (previewMock) {
            previewMock.classList.toggle('d-none', usePdf);
        }

        if (previewModeDesignBtn) {
            previewModeDesignBtn.classList.toggle('btn-primary', !usePdf);
            previewModeDesignBtn.classList.toggle('btn-outline-secondary', usePdf);
        }

        if (previewModePdfBtn) {
            previewModePdfBtn.classList.toggle('d-none', !hasPdf);
            previewModePdfBtn.classList.toggle('btn-primary', usePdf);
            previewModePdfBtn.classList.toggle('btn-outline-secondary', !usePdf);
        }
    }

    function escapeHtml(value) {
        return String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function parseBooleanFlag(value) {
        return value === true || value === 1 || value === '1';
    }

    function renderInvoiceDesignMock(doc) {
        if (!previewMock) { return; }

        var values = readFormValues();
        var prefix = values.invoice_prefix || 'INV-';
        var dueDays = Number(values.invoice_due_days || 30);
        var showTax = parseBooleanFlag(values.invoice_show_tax);
        var roundOffEnabled = parseBooleanFlag(values.invoice_round_off_enabled);
        var roundOff = values.invoice_round_off || 'none';
        var headerTerms = values.invoice_header_terms || '-';
        var footerTerms = values.invoice_footer_terms || '-';
        var issueDate = new Date();
        var dueDate = new Date(issueDate.getTime() + (Number.isFinite(dueDays) ? dueDays : 30) * 24 * 60 * 60 * 1000);
        var subtotal = 2500000;
        var tax = showTax ? subtotal * 0.11 : 0;
        var total = subtotal + tax;
        if (roundOffEnabled) {
            if (roundOff === 'round_up') {
                total = Math.ceil(total);
            } else if (roundOff === 'round_down') {
                total = Math.floor(total);
            }
        }

        previewMock.innerHTML = [
            '<div class="bg-white border rounded p-4" style="max-width: 900px; margin: 0 auto;">',
            '  <div class="d-flex justify-content-between align-items-start mb-4">',
            '    <div>',
            '      <h4 class="mb-1">INVOICE PREVIEW</h4>',
            '      <div class="text-muted fs-12">Template: ' + escapeHtml(doc.template || '-') + '</div>',
            '      <div class="text-muted fs-12">Type: ' + escapeHtml(doc.name || doc.code || '-') + '</div>',
            '    </div>',
            '    <div class="text-end">',
            '      <div class="fw-semibold">' + escapeHtml(prefix) + 'DRAFT-0001</div>',
            '      <div class="text-muted fs-12">Issue: ' + escapeHtml(issueDate.toLocaleDateString()) + '</div>',
            '      <div class="text-muted fs-12">Due: ' + escapeHtml(dueDate.toLocaleDateString()) + ' (' + String(dueDays) + ' days)</div>',
            '    </div>',
            '  </div>',
            '  <div class="mb-3 p-2 rounded" style="background:#f8f9fb; border:1px dashed #d7deea;">',
            '    <div class="fw-semibold mb-1">Header Terms</div>',
            '    <div class="text-muted fs-12">' + escapeHtml(headerTerms) + '</div>',
            '  </div>',
            '  <table class="table table-sm mb-3">',
            '    <thead><tr><th>Item</th><th class="text-end">Amount</th></tr></thead>',
            '    <tbody>',
            '      <tr><td>Payroll & billing services</td><td class="text-end">2,500,000</td></tr>',
            '    </tbody>',
            '    <tfoot>',
            '      <tr><th>Subtotal</th><th class="text-end">' + String(Math.round(subtotal)).replace(/\B(?=(\d{3})+(?!\d))/g, ',') + '</th></tr>',
            (showTax ? '      <tr><th>Tax (11%)</th><th class="text-end">' + String(Math.round(tax)).replace(/\B(?=(\d{3})+(?!\d))/g, ',') + '</th></tr>' : ''),
            '      <tr><th>Total</th><th class="text-end">' + String(Math.round(total)).replace(/\B(?=(\d{3})+(?!\d))/g, ',') + '</th></tr>',
            '    </tfoot>',
            '  </table>',
            '  <div class="mb-3 p-2 rounded" style="background:#f8f9fb; border:1px dashed #d7deea;">',
            '    <div class="fw-semibold mb-1">Footer Terms</div>',
            '    <div class="text-muted fs-12">' + escapeHtml(footerTerms) + '</div>',
            '  </div>',
            '  <div class="text-muted fs-12">',
            '    Preview ini mengikuti setting form saat ini (belum harus disimpan).',
            '    ' + (roundOffEnabled ? 'Round off aktif: ' + escapeHtml(roundOff) + '.' : 'Round off tidak aktif.') +
            '  </div>',
            '</div>'
        ].join('');
    }

    function showPreviewModal() {
        if (!previewModalEl || !window.bootstrap || !window.bootstrap.Modal) { return false; }
        var modal = window.bootstrap.Modal.getOrCreateInstance(previewModalEl);
        modal.show();
        var firstInput = previewModalEl.querySelector("input:not([type=hidden]):not([type=password]), select");
        if (firstInput) setTimeout(function() { firstInput.focus(); }, 100);
        return true;
    }

    function renderPreviewMetadata(doc) {
        if (previewTitle) {
            previewTitle.textContent = (doc.name || 'Invoice Document') + ' Preview';
        }
        if (previewName) {
            previewName.textContent = doc.name || '-';
        }
        if (previewCode) {
            previewCode.textContent = doc.code || '-';
        }
        if (previewTemplate) {
            previewTemplate.textContent = doc.template || '-';
        }
        if (previewGenerated) {
            previewGenerated.textContent = String(Number(doc.total_generated || 0));
        }
        if (previewLatest) {
            previewLatest.textContent = formatDateTime(doc.latest_generated_at);
        }
        if (previewNote) {
            previewNote.textContent = doc.preview_note || '-';
        }
    }

    function loadGeneratedPdfPreview() {
        var doc = currentPreviewDocCode ? documentsByCode[currentPreviewDocCode] : null;
        var previewUrl = doc && doc.preview_url ? String(doc.preview_url) : '';

        if (!doc || !previewUrl) {
            setPreviewStatus('Belum ada dokumen yang bisa dipreview untuk tipe ini.');
            setPreviewMode('design', false);
            return;
        }

        if (currentPreviewPdfLoaded && previewBlobUrl) {
            setPreviewMode('pdf', true);
            setPreviewStatus('Preview siap. Gunakan Open/Download jika ingin membuka file asli.');
            return;
        }

        setPreviewStatus('Memuat PDF preview...');

        fetch(previewUrl, {
            method: 'GET',
            headers: buildHeaders({ 'Accept': 'application/pdf' }),
            credentials: 'same-origin'
        })
        .then(function (res) {
            if (!res.ok) {
                throw new Error('Tidak bisa mengambil file preview (HTTP ' + res.status + ').');
            }
            return res.blob();
        })
        .then(function (blob) {
            if (!blob || (blob.type && blob.type.indexOf('pdf') === -1)) {
                throw new Error('Respons preview bukan PDF.');
            }
            cleanupPreviewBlob();
            previewBlobUrl = window.URL.createObjectURL(blob);
            currentPreviewPdfLoaded = true;
            setPreviewFrameSource(previewBlobUrl);
            setPreviewMode('pdf', true);
            setPreviewStatus('Preview siap. Gunakan Open/Download jika ingin membuka file asli.');
        })
        .catch(function (err) {
            currentPreviewPdfLoaded = false;
            setPreviewFrameSource(null);
            setPreviewMode('design', true);
            setPreviewStatus((err && err.message) ? err.message : 'Preview gagal dimuat.');
        });
    }

    function openDocumentPreview(code) {
        if (!code || !documentsByCode[code]) { return; }
        var doc = documentsByCode[code];
        var previewUrl = doc.preview_url ? String(doc.preview_url) : '';
        currentPreviewDocCode = code;
        currentPreviewHasPdf = !!previewUrl;
        currentPreviewPdfLoaded = false;

        renderPreviewMetadata(doc);
        renderInvoiceDesignMock(doc);
        cleanupPreviewBlob();
        setPreviewFrameSource(null);
        setPreviewMode('design', !!previewUrl);

        if (previewDownload) {
            if (previewUrl) {
                previewDownload.href = previewUrl;
                previewDownload.classList.remove('d-none');
            } else {
                previewDownload.classList.add('d-none');
                previewDownload.removeAttribute('href');
            }
        }

        if (!previewUrl) {
            setPreviewStatus('Belum ada dokumen yang bisa dipreview untuk tipe ini.');
            showPreviewModal();
            return;
        }

        showPreviewModal();
        setPreviewStatus('Design preview siap. Klik Generated PDF kalau memang mau lihat file aslinya.');
    }

    function renderInvoiceDocuments(documents, statusMap) {
        if (!documentsList) { return; }
        var rows = Array.isArray(documents) ? documents : [];
        documentsByCode = {};

        if (documentsEmpty) {
            documentsEmpty.style.display = rows.length > 0 ? 'none' : '';
        }

        var oldRows = documentsList.querySelectorAll('[data-invoice-doc-row]');
        Array.prototype.slice.call(oldRows).forEach(function (row) {
            row.parentNode.removeChild(row);
        });

        rows.forEach(function (doc) {
            var code = String(doc.code || 'unknown');
            documentsByCode[code] = doc;
            var active = (statusMap && (code in statusMap)) ? !!statusMap[code] : !!doc.active;
            var row = document.createElement('tr');
            row.setAttribute('data-invoice-doc-row', '1');

            var generated = Number(doc.total_generated || 0);
            var previewUrl = doc.preview_url ? String(doc.preview_url) : '';
            var previewNote = doc.preview_note ? String(doc.preview_note) : '';
            var templateName = doc.template ? String(doc.template) : '-';
            var docName = doc.name ? String(doc.name) : code;

            row.innerHTML = [
                '<td>',
                '  <div class="fw-semibold">' + docName + '</div>',
                '  <div class="text-muted fs-12">' + code + '</div>',
                '</td>',
                '<td><code>' + templateName + '</code></td>',
                '<td class="text-center">' + generated + '</td>',
                '<td class="text-muted fs-12">' + formatDateTime(doc.latest_generated_at) + '</td>',
                '<td class="text-center">',
                '  <div class="form-check form-switch d-inline-flex align-items-center gap-2 mb-0">',
                '    <input class="form-check-input" type="checkbox" data-invoice-doc-active data-doc-code="' + code + '" ' + (active ? 'checked' : '') + ' />',
                '    <span class="badge ' + (active ? 'bg-success' : 'bg-secondary') + '" data-invoice-doc-state="' + code + '">' + (active ? 'Active' : 'Inactive') + '</span>',
                '  </div>',
                '</td>',
                '<td class="text-end">',
                '  <button type="button" class="btn btn-sm btn-outline-primary" data-invoice-doc-preview data-doc-code="' + code + '">Preview Detail</button>',
                (previewUrl ? '' : '  <div class="text-muted fs-12 mt-1">No file preview yet</div>'),
                '</td>'
            ].join('');

            if (previewNote) {
                row.title = previewNote;
            }

            documentsList.appendChild(row);
        });

        applyDocumentStatuses(statusMap || {});
    }

    /**
     * Populates form fields from a settings object.
     */
    function applyToForm(settings) {
        getAllFields().forEach(function (el) {
            var key = el.getAttribute('data-invoice-field');
            if (!key || !(key in settings)) { return; }
            var val = settings[key];
            if (el.hasAttribute('data-invoice-settings-toggle')) {
                el.checked = (val === '1' || val === true || val === 1);
            } else {
                el.value = val || '';
            }
        });
    }

    // -------------------------------------------------------------------------
    // Submit state helpers
    // -------------------------------------------------------------------------

    function setSubmitting(loading) {
        if (!submitBtn) { return; }
        submitBtn.disabled = loading;
        if (spinner) {
            if (loading) {
                spinner.classList.remove('d-none');
            } else {
                spinner.classList.add('d-none');
            }
        }
        if (submitLabel) {
            submitLabel.textContent = loading ? 'Saving…' : 'Save Changes';
        }
    }

    // -------------------------------------------------------------------------
    // API: load settings
    // -------------------------------------------------------------------------

    function loadSettings() {
        if (loadingCard) { loadingCard.classList.remove('d-none'); }
        if (panel)       { panel.classList.add('d-none'); }
        clearFeedback();

        fetch('/v1/hcm/invoice-settings', {
            method: 'GET',
            headers: buildHeaders()
        })
        .then(function (res) { return res.json().then(function (body) { return { status: res.status, body: body }; }); })
        .then(function (result) {
            if (loadingCard) { loadingCard.classList.add('d-none'); }

            if (!result.body.success) {
                // 403 = no permission; show informational message
                var msg = (result.body.error && result.body.error.message) || 'Failed to load invoice settings.';
                if (result.status === 403) {
                    showFeedback('warning', 'You do not have permission to manage invoice settings.');
                } else {
                    showFeedback('danger', msg);
                }
                return;
            }

            var settings = result.body.data || {};
            snapshot = JSON.parse(JSON.stringify(settings));
            documentStatusSnapshot = JSON.parse(JSON.stringify(settings.invoice_document_status_map || {}));
            applyToForm(settings);
            renderInvoiceDocuments(settings.invoice_documents || [], settings.invoice_document_status_map || {});
            if (panel) { panel.classList.remove('d-none'); }
        })
        .catch(function (err) {
            if (loadingCard) { loadingCard.classList.add('d-none'); }
            showFeedback('danger', 'Network error loading invoice settings.');
            // eslint-disable-next-line no-console
            console.error('[invoice-settings]', err);
        });
    }

    // -------------------------------------------------------------------------
    // API: save settings
    // -------------------------------------------------------------------------

    function saveSettings(values) {
        setSubmitting(true);
        clearFeedback();

        fetch('/v1/hcm/invoice-settings', {
            method: 'PUT',
            headers: buildHeaders(),
            body: JSON.stringify(values)
        })
        .then(function (res) { return res.json().then(function (body) { return { status: res.status, body: body }; }); })
        .then(function (result) {
            setSubmitting(false);
            if (!result.body.success) {
                var msg = (result.body.error && result.body.error.message) || 'Failed to save invoice settings.';
                if (result.body.error && result.body.error.details) {
                    var details = result.body.error.details;
                    var msgs = [];
                    Object.keys(details).forEach(function (k) {
                        var arr = details[k];
                        if (Array.isArray(arr)) { msgs = msgs.concat(arr); }
                    });
                    if (msgs.length > 0) { msg = msgs.join(' '); }
                }
                showFeedback('danger', msg);
                return;
            }

            var saved = result.body.data || {};
            snapshot = JSON.parse(JSON.stringify(saved));
            documentStatusSnapshot = JSON.parse(JSON.stringify(saved.invoice_document_status_map || {}));
            applyToForm(saved);
            renderInvoiceDocuments(saved.invoice_documents || [], saved.invoice_document_status_map || {});
            showFeedback('success', result.body.message || 'Invoice settings saved successfully.');
        })
        .catch(function (err) {
            setSubmitting(false);
            showFeedback('danger', 'Network error saving invoice settings.');
            // eslint-disable-next-line no-console
            console.error('[invoice-settings]', err);
        });
    }

    // -------------------------------------------------------------------------
    // Event bindings
    // -------------------------------------------------------------------------

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        if (!ArcavValidation.validateForm(form)) { return; }
        var values = readFormValues();
        values.invoice_document_status_map = readDocumentStatuses();
        saveSettings(values);
    });

    if (resetBtn) {
        resetBtn.addEventListener('click', function () {
            if (Object.keys(snapshot).length > 0) {
                applyToForm(snapshot);
            }
            if (Object.keys(documentStatusSnapshot).length > 0) {
                applyDocumentStatuses(documentStatusSnapshot);
            }
            clearFeedback();
        });
    }

    if (documentsList) {
        documentsList.addEventListener('click', function (e) {
            var target = e.target;
            if (!target || !target.matches('[data-invoice-doc-preview]')) {
                return;
            }
            var code = target.getAttribute('data-doc-code');
            if (!code) { return; }
            openDocumentPreview(code);
        });

        documentsList.addEventListener('change', function (e) {
            var target = e.target;
            if (!target || !target.matches('[data-invoice-doc-active]')) {
                return;
            }
            var code = target.getAttribute('data-doc-code');
            if (!code) { return; }
            var stateEl = documentsList.querySelector('[data-invoice-doc-state="' + code + '"]');
            if (!stateEl) { return; }
            stateEl.textContent = target.checked ? 'Active' : 'Inactive';
            stateEl.className = 'badge ' + (target.checked ? 'bg-success' : 'bg-secondary');
        });
    }

    if (previewModeDesignBtn) {
        previewModeDesignBtn.addEventListener('click', function () {
            var doc = currentPreviewDocCode ? documentsByCode[currentPreviewDocCode] : null;
            if (doc) {
                renderInvoiceDesignMock(doc);
            }
            setPreviewMode('design', !!(doc && doc.preview_url));
        });
    }

    if (previewModePdfBtn) {
        previewModePdfBtn.addEventListener('click', function () {
            if (!currentPreviewHasPdf) {
                setPreviewStatus('Belum ada dokumen yang bisa dipreview untuk tipe ini.');
                return;
            }
            loadGeneratedPdfPreview();
        });
    }

    if (previewModalEl) {
        previewModalEl.addEventListener('hidden.bs.modal', function () {
            cleanupPreviewBlob();
            setPreviewFrameSource(null);
            setPreviewStatus('Preparing preview…');
            currentPreviewDocCode = null;
            currentPreviewHasPdf = false;
            currentPreviewPdfLoaded = false;
        });
    }

    // -------------------------------------------------------------------------
    // Boot
    // -------------------------------------------------------------------------

    loadSettings();

}());
