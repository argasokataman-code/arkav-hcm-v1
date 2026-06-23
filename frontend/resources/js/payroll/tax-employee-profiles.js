/**
 * tax-employee-profiles.js
 * Phase 5 – Employee Tax Profile Screen
 * Manages per-employee NPWP/PTKP/taxStatus data for HR and Tenant Admin.
 */
(function (window, document) {
    'use strict';

    /* ------------------------------------------------------------------ */
    /* API helpers (mirrors pattern from tax-governance-dashboard.js)      */
    /* ------------------------------------------------------------------ */
    function apiRequest(method, path, data) {
        if (!window.AuthApi || typeof window.AuthApi.request !== 'function') {
            return Promise.reject(new Error('AuthApi not available'));
        }
        return window.AuthApi.request(method, path, data);
    }
    function apiGet(path, params) { return apiRequest('GET', path, params); }
    function apiPatch(path, payload) { return apiRequest('PATCH', path, payload); }
    function apiPut(path, payload) { return apiRequest('PUT', path, payload); }

    /* ------------------------------------------------------------------ */
    /* State                                                               */
    /* ------------------------------------------------------------------ */
    var currentPage = 1;
    var totalPages  = 1;
    var totalCount  = 0;
    var debounceTimer = null;
    var PAGE_SIZE = 20;

    /* ------------------------------------------------------------------ */
    /* DOM selectors                                                       */
    /* ------------------------------------------------------------------ */
    function q(sel) { return document.querySelector(sel); }

    /* ------------------------------------------------------------------ */
    /* Render helpers                                                      */
    /* ------------------------------------------------------------------ */
    function completenessBadge(npwp, taxStatus) {
        var hasNpwp = npwp && npwp.trim() !== '';
        var hasStatus = taxStatus && taxStatus.trim() !== '';
        if (hasNpwp && hasStatus) {
            return '<span class="badge bg-success-subtle text-success">Lengkap</span>';
        }
        if (hasNpwp || hasStatus) {
            return '<span class="badge bg-warning-subtle text-warning">Parsial</span>';
        }
        return '<span class="badge bg-danger-subtle text-danger">Kosong</span>';
    }

    function renderRows(employees) {
        var tbody = q('[data-emp-tax-tbody]');
        if (!tbody) { return; }
        if (!employees || employees.length === 0) {
            tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-4">Tidak ada data karyawan.</td></tr>';
            return;
        }
        var html = '';
        employees.forEach(function (emp) {
            var employeeName = emp.name || emp.full_name || emp.fullName || emp.employee_name || '-';
            var npwp = emp.npwp || '';
            var taxStatus = emp.tax_status || emp.taxStatus || '';
            var ptkpStatus = emp.ptkp_status || emp.ptkpStatus || taxStatus;
            var ptkpAnnualNominal = emp.ptkp_annual_nominal;
            if (ptkpAnnualNominal === null || ptkpAnnualNominal === undefined) {
                ptkpAnnualNominal = emp.ptkpAnnualNominal;
            }
            var npwpDisplay = npwp
                ? '<code>' + escHtml(npwp) + '</code>'
                : '<span class="text-muted small">Kosong</span>';
            var ptkpDisplay = ptkpStatus
                ? '<span class="badge bg-info-subtle text-info">' + escHtml(ptkpStatus) + '</span>'
                : '<span class="text-muted small">-</span>';
            var ptkpNominalDisplay = (ptkpAnnualNominal === null || ptkpAnnualNominal === undefined || Number.isNaN(Number(ptkpAnnualNominal)))
                ? '<span class="text-muted small">-</span>'
                : '<span class="fw-semibold">' + escHtml(formatRupiah(Number(ptkpAnnualNominal))) + '</span>';
            var taxStatusDisplay = taxStatus
                ? '<span class="badge bg-secondary-subtle text-secondary">' + escHtml(taxStatus) + '</span>'
                : '<span class="text-muted small">-</span>';
            html += '<tr>'
                + '<td>' + escHtml(employeeName) + '</td>'
                + '<td class="text-muted small">' + escHtml(emp.email || '-') + '</td>'
                + '<td>' + npwpDisplay + '</td>'
                + '<td>' + ptkpDisplay + '</td>'
                + '<td>' + ptkpNominalDisplay + '</td>'
                + '<td>' + taxStatusDisplay + '</td>'
                + '<td>' + completenessBadge(npwp, taxStatus) + '</td>'
                + '<td><button type="button" class="btn btn-sm btn-outline-primary" '
                +   'data-emp-edit-btn '
                +   'data-emp-id="' + escAttr(String(emp.id || emp.uuid || '')) + '" '
                +   'data-emp-name="' + escAttr(employeeName === '-' ? '' : employeeName) + '" '
                +   'data-emp-npwp="' + escAttr(npwp) + '" '
                +   'data-emp-taxstatus="' + escAttr(taxStatus) + '" '
                +   'aria-label="Edit profil pajak ' + escAttr(employeeName === '-' ? '' : employeeName) + '">'
                +   '<i class="ti ti-edit" aria-hidden="true"></i>'
                + '</button></td>'
                + '</tr>';
        });
        tbody.innerHTML = html;
    }

    function formatRupiah(value) {
        return new Intl.NumberFormat('id-ID', {
            style: 'currency',
            currency: 'IDR',
            maximumFractionDigits: 0,
        }).format(value);
    }

    function updateKpis(employees, total) {
        var kpiTotal = q('[data-emp-tax-kpi-total]');
        var kpiNpwp  = q('[data-emp-tax-kpi-npwp]');
        var kpiPtkp  = q('[data-emp-tax-kpi-ptkp]');
        var countBadge = q('[data-emp-tax-count]');

        var npwpCount = 0;
        var ptkpCount = 0;
        (employees || []).forEach(function (emp) {
            if (emp.npwp && emp.npwp.trim() !== '') { npwpCount++; }
            var ts = emp.tax_status || emp.taxStatus || '';
            if (ts && ts.trim() !== '') { ptkpCount++; }
        });

        if (kpiTotal) { kpiTotal.textContent = total; }
        if (kpiNpwp)  { kpiNpwp.textContent  = npwpCount; }
        if (kpiPtkp)  { kpiPtkp.textContent  = ptkpCount; }
        if (countBadge) {
            countBadge.textContent = total + ' karyawan';
        }
    }

    function updatePagination() {
        var info = q('[data-emp-tax-pagination-info]');
        var prev = q('[data-emp-tax-prev]');
        var next = q('[data-emp-tax-next]');
        if (info) {
            info.textContent = 'Halaman ' + currentPage + ' dari ' + totalPages + ' (' + totalCount + ' total)';
        }
        if (prev) { prev.disabled = currentPage <= 1; }
        if (next) { next.disabled = currentPage >= totalPages; }
    }

    /* ------------------------------------------------------------------ */
    /* Load employees                                                      */
    /* ------------------------------------------------------------------ */
    function loadEmployees() {
        var tbody = q('[data-emp-tax-tbody]');
        if (!tbody) { return; }
        tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-4">'
            + '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Memuat...</td></tr>';

        var searchEl  = q('[data-emp-tax-search]');
        var filterEl  = q('[data-emp-tax-filter]');
        var params = {
            perPage: PAGE_SIZE,
            page: currentPage,
            scope: 'active_company',
        };
        if (searchEl && searchEl.value.trim() !== '') {
            params.search = searchEl.value.trim();
        }
        if (filterEl && filterEl.value !== '') {
            params.taxFilter = filterEl.value;
        }

        apiGet('/hcm/employees', params)
            .then(function (res) {
                var data = res && res.data ? res.data : res;
                var employees = Array.isArray(data) ? data : (data.data || []);
                var meta = data.meta || data.pagination || {};
                totalCount = meta.total || employees.length;
                totalPages = meta.last_page || meta.totalPages || 1;
                renderRows(employees);
                updateKpis(employees, totalCount);
                updatePagination();
            })
            .catch(function (err) {
                tbody.innerHTML = '<tr><td colspan="8" class="text-center text-danger py-4">'
                    + 'Gagal memuat data: ' + escHtml(String(err && err.message ? err.message : err)) + '</td></tr>';
            });
    }

    /* ------------------------------------------------------------------ */
    /* Edit modal                                                          */
    /* ------------------------------------------------------------------ */
    function openEditModal(btn) {
        var empId = btn.getAttribute('data-emp-id') || '';
        var name  = btn.getAttribute('data-emp-name') || '';
        var npwp  = btn.getAttribute('data-emp-npwp') || '';
        var taxStatus = btn.getAttribute('data-emp-taxstatus') || '';

        var idInput = q('[data-emp-tax-edit-user-id]');
        var nameInput = q('[data-emp-tax-edit-name]');
        var npwpInput = q('[data-emp-tax-edit-npwp]');
        var taxStatusSel = q('[data-emp-tax-edit-tax-status]');
        var statusHint = q('[data-emp-tax-status-hint]');
        var errBox = q('[data-emp-tax-edit-error]');

        if (idInput) { idInput.value = empId; }
        if (nameInput) { nameInput.value = name; }
        if (npwpInput) { npwpInput.value = npwp; }
        if (taxStatusSel) { taxStatusSel.value = taxStatus; }
        updateTaxStatusHint(taxStatusSel, statusHint);
        if (errBox) { errBox.classList.add('d-none'); errBox.textContent = ''; }

        var modal = document.getElementById('empTaxEditModal');
        if (modal && window.bootstrap && window.bootstrap.Modal) {
            var bsModal = window.bootstrap.Modal.getOrCreateInstance(modal);
            bsModal.show();
            var firstInput = document.querySelector("#empTaxEditModal input:not([type=hidden]):not([type=password]), #empTaxEditModal select");
            if (firstInput) setTimeout(function() { firstInput.focus(); }, 100);
        }
    }

    function updateTaxStatusHint(selectEl, hintEl) {
        if (!hintEl) { return; }
        if (!selectEl || !selectEl.value) {
            hintEl.textContent = 'Pilih status untuk melihat arti singkatnya.';
            return;
        }

        var selectedOption = selectEl.options[selectEl.selectedIndex] || null;
        var description = selectedOption ? (selectedOption.getAttribute('data-description') || '') : '';
        hintEl.textContent = description || 'Kategori PTKP/PPh21 untuk menentukan pengurangan pajak tahunan.';
    }

    function handleEditSubmit(e) {
        e.preventDefault();
        var form = e.target;
        if (!ArcavValidation.validateForm(form)) { return; }
        var idInput = form.querySelector('[data-emp-tax-edit-user-id]');
        var npwpInput = form.querySelector('[data-emp-tax-edit-npwp]');
        var taxStatusSel = form.querySelector('[data-emp-tax-edit-tax-status]');
        var errBox = form.querySelector('[data-emp-tax-edit-error]');
        var spinner = form.querySelector('[data-emp-tax-edit-spinner]');
        var submitBtn = form.querySelector('[data-emp-tax-edit-submit]');

        var empId = idInput ? idInput.value.trim() : '';
        if (!empId) { return; }

        var payload = {
            npwp: npwpInput ? npwpInput.value.trim() : '',
            taxStatus: taxStatusSel ? taxStatusSel.value : '',
            ptkpStatus: taxStatusSel ? taxStatusSel.value : '',
        };

        if (errBox) { errBox.classList.add('d-none'); errBox.textContent = ''; }
        if (spinner) { spinner.classList.remove('d-none'); }
        if (submitBtn) { submitBtn.disabled = true; }

        apiPut('/hcm/employees/' + empId, payload)
            .then(function () {
                var modal = document.getElementById('empTaxEditModal');
                if (modal && window.bootstrap && window.bootstrap.Modal) {
                    var bsModal = window.bootstrap.Modal.getInstance(modal);
                    if (bsModal) { bsModal.hide(); }
                }
                loadEmployees();
            })
            .catch(function (err) {
                var msg = (err && err.message) ? err.message : 'Gagal menyimpan perubahan.';
                if (errBox) {
                    errBox.textContent = msg;
                    errBox.classList.remove('d-none');
                }
            })
            .finally(function () {
                if (spinner) { spinner.classList.add('d-none'); }
                if (submitBtn) { submitBtn.disabled = false; }
            });
    }

    /* ------------------------------------------------------------------ */
    /* Escape helpers                                                      */
    /* ------------------------------------------------------------------ */
    function escHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }
    function escAttr(str) {
        return String(str).replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }

    /* ------------------------------------------------------------------ */
    /* Init                                                                */
    /* ------------------------------------------------------------------ */
    function init() {
        // Only run on employee-tax-profiles screen
        if (!q('[data-emp-tax-tbody]')) { return; }

        // Initial load
        loadEmployees();

        // Search – debounce 300ms
        var searchEl = q('[data-emp-tax-search]');
        if (searchEl) {
            searchEl.addEventListener('input', function () {
                clearTimeout(debounceTimer);
                debounceTimer = setTimeout(function () {
                    currentPage = 1;
                    loadEmployees();
                }, 300);
            });
        }

        // Filter – immediate reload
        var filterEl = q('[data-emp-tax-filter]');
        if (filterEl) {
            filterEl.addEventListener('change', function () {
                currentPage = 1;
                loadEmployees();
            });
        }

        // Refresh button
        var refreshBtn = q('[data-emp-tax-refresh]');
        if (refreshBtn) {
            refreshBtn.addEventListener('click', function () {
                currentPage = 1;
                loadEmployees();
            });
        }

        // Pagination
        var prevBtn = q('[data-emp-tax-prev]');
        if (prevBtn) {
            prevBtn.addEventListener('click', function () {
                if (currentPage > 1) {
                    currentPage--;
                    loadEmployees();
                }
            });
        }
        var nextBtn = q('[data-emp-tax-next]');
        if (nextBtn) {
            nextBtn.addEventListener('click', function () {
                if (currentPage < totalPages) {
                    currentPage++;
                    loadEmployees();
                }
            });
        }

        // Edit buttons (delegated)
        var tbody = q('[data-emp-tax-tbody]');
        if (tbody) {
            tbody.addEventListener('click', function (e) {
                var btn = e.target.closest('[data-emp-edit-btn]');
                if (btn) { openEditModal(btn); }
            });
        }

        // Edit form submit
        var editForm = q('[data-emp-tax-edit-form]');
        if (editForm) {
            editForm.addEventListener('submit', handleEditSubmit);
        }

        var taxStatusSel = q('[data-emp-tax-edit-tax-status]');
        var statusHint = q('[data-emp-tax-status-hint]');
        if (taxStatusSel) {
            taxStatusSel.addEventListener('change', function () {
                updateTaxStatusHint(taxStatusSel, statusHint);
            });
            updateTaxStatusHint(taxStatusSel, statusHint);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})(window, document);
