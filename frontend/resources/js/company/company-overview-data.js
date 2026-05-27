(function () {
    'use strict';

    if (!document.querySelector('[data-company-name]') && !document.querySelector('[data-co-stat-total]')) {
        return;
    }

    var SPT_MONTH_LABELS = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

    function setText(selector, value) {
        var el = document.querySelector(selector);
            if (el) el.textContent = (value !== null && value !== undefined && value !== '') ? value : '—';
    }

    function normalizeValue(value) {
        if (value === null || value === undefined) return '';
        return String(value).trim();
    }

    function renderProfileCompletion(data, companyProfile, activeCompany, profile) {
        var percentEl = document.querySelector('[data-co-completion-percent]');
        var fillEl = document.querySelector('[data-co-completion-fill]');
        var progressEl = document.querySelector('[data-co-completion-progress]');
        var detailEl = document.querySelector('[data-co-completion-detail]');
        var subtitleEl = document.querySelector('[data-co-completion-subtitle]');
        var missingEl = document.querySelector('[data-co-completion-missing]');

        if (!percentEl || !fillEl || !progressEl || !detailEl || !missingEl) {
            return;
        }

        var checklist = [
            { key: 'company_name', label: 'Nama perusahaan', value: normalizeValue(companyProfile.name || activeCompany.name) },
            { key: 'legal_name', label: 'Nama legal', value: normalizeValue(companyProfile.legalName || activeCompany.legalName) },
            { key: 'address', label: 'Alamat', value: normalizeValue(companyProfile.address) },
            { key: 'city', label: 'Kota', value: normalizeValue(companyProfile.city) },
            { key: 'state', label: 'Provinsi', value: normalizeValue(companyProfile.state) },
            { key: 'country', label: 'Negara', value: normalizeValue(companyProfile.country) },
            { key: 'postal_code', label: 'Kode pos', value: normalizeValue(companyProfile.postalCode) },
            { key: 'npwp', label: 'NPWP', value: normalizeValue(companyProfile.npwp) },
            { key: 'owner_name', label: 'Nama owner', value: normalizeValue(data.name) },
            { key: 'owner_email', label: 'Email owner', value: normalizeValue(data.email) },
            { key: 'owner_phone', label: 'Telepon owner', value: (function () {
                var v = normalizeValue(profile.phone);
                // Only count as present if format matches valid phone rule (8-15 digits)
                return (v && /^\+?(?=(?:\D*\d){8,15}\D*$)[0-9\s\-()]+$/.test(v)) ? v : '';
            }()) },
        ];

        var completed = checklist.filter(function (item) { return item.value !== ''; }).length;
        var total = checklist.length;
        var percent = Math.round((completed / total) * 100);
        var missing = checklist.filter(function (item) { return item.value === ''; });

        percentEl.textContent = percent + '%';
        fillEl.style.width = percent + '%';
        progressEl.setAttribute('aria-valuenow', String(percent));
        detailEl.textContent = 'Terisi ' + completed + ' dari ' + total + ' field penting. Sisa ' + (total - completed) + ' field lagi.';

        if (subtitleEl) {
            if (percent >= 100) {
                subtitleEl.textContent = 'Mantap, profil perusahaanmu sudah lengkap dan siap dipakai operasional.';
            } else if (percent >= 70) {
                subtitleEl.textContent = 'Profil hampir selesai. Tinggal sedikit lagi supaya data perusahaan makin solid.';
            } else {
                subtitleEl.textContent = 'Masih ada data penting yang belum lengkap. Yuk rapikan sekarang biar tidak kelewat.';
            }
        }

        if (missing.length === 0) {
            missingEl.innerHTML = '<span class="badge badge-soft-success"><i class="ti ti-check me-1"></i>Semua field penting sudah lengkap</span>';
            return;
        }

        missingEl.innerHTML = missing.map(function (item) {
            return '<span class="badge badge-soft-danger">' + item.label + '</span>';
        }).join('');
    }

    function formatRupiah(value) {
        var num = parseFloat(value) || 0;
        return 'Rp' + num.toLocaleString('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
    }

    function apiGet(url) {
        var token = (window.AuthApi && window.AuthApi.getToken()) || localStorage.getItem('arcav_access_token');
        var headers = { Accept: 'application/json' };
        if (token) { headers['Authorization'] = 'Bearer ' + token; }
        if (window.axios) {
            return window.axios({ method: 'get', url: url, headers: headers, withCredentials: true })
                .then(function (resp) { return resp.data; });
        }
        return fetch(url, { method: 'GET', credentials: 'same-origin', headers: headers })
            .then(function (res) { return res.json(); });
    }

    // ── /me → company + owner info ────────────────────────────────────────────
    function loadCompanyInfo() {
        apiGet('/v1/identity/auth/me').then(function (payload) {
            if (!payload || payload.success !== true) return;
            var data = payload.data || {};
            var cp = data.companyProfile || {};
            // Also pull from activeCompany as fallback
            var ac = data.activeCompany || {};

            var companyName = cp.name || ac.name || '—';
            var legalName = cp.legalName || ac.legalName || '—';

            setText('[data-company-name]', companyName);
            setText('[data-company-legal-name]', legalName);
            setText('[data-company-name-display]', companyName);
            setText('[data-company-legal-name-display]', legalName);
            setText('[data-company-address]', cp.address);
            setText('[data-company-city]', cp.city);
            setText('[data-company-state]', cp.state);
            setText('[data-company-country]', cp.country);
            setText('[data-company-postal-code]', cp.postalCode);
            setText('[data-company-npwp]', cp.npwp);

            // Owner: data.name is the full name from users table
            setText('[data-owner-name]', data.name);
            setText('[data-owner-email]', data.email);
            // phone is in profile or employeeProfile
            var profile = data.profile || {};
            setText('[data-owner-phone]', profile.phone || '—');

            renderProfileCompletion(data, cp, ac, profile);
        }).catch(function () {});
    }

    // ── /employees → stats (single call, use meta.summary) ──────────────────
    function loadEmployeeStats() {
        apiGet('/v1/hcm/employees?perPage=1').then(function (payload) {
            if (!payload || payload.success !== true) {
                setText('[data-co-stat-total]', '—');
                setText('[data-co-stat-active]', '—');
                setText('[data-co-stat-inactive]', '—');
                return;
            }
            var meta = payload.meta || {};
            var summary = meta.summary || {};
            setText('[data-co-stat-total]', summary.totalEmployees !== undefined ? summary.totalEmployees : meta.total);
            setText('[data-co-stat-active]', summary.activeEmployees !== undefined ? summary.activeEmployees : '—');
            setText('[data-co-stat-inactive]', summary.inactiveEmployees !== undefined ? summary.inactiveEmployees : '—');
        }).catch(function () {
            setText('[data-co-stat-total]', '—');
            setText('[data-co-stat-active]', '—');
            setText('[data-co-stat-inactive]', '—');
        });
    }

    // ── SPT Masa ──────────────────────────────────────────────────────────────
    function sptStatusBadge(status) {
        var map = {
            draft: '<span class="badge badge-soft-secondary">Draft</span>',
            ready: '<span class="badge badge-soft-primary">Ready</span>',
            submitted: '<span class="badge badge-soft-success">Submitted</span>',
            void: '<span class="badge badge-soft-danger">Void</span>',
        };
        return map[status] || ('<span class="badge badge-soft-secondary">' + (status || '—') + '</span>');
    }

    function periodeLabel(periode) {
        if (!periode) return '—';
        var parts = String(periode).split('-');
        var month = parseInt(parts[1], 10);
        var year = parts[0];
        return (SPT_MONTH_LABELS[month - 1] || parts[1]) + ' ' + year;
    }

    function loadSptMasa() {
        var loading = document.querySelector('[data-co-spt-loading]');
        var tableWrap = document.querySelector('[data-co-spt-table]');
        var tbody = document.querySelector('[data-co-spt-tbody]');
        var emptyEl = document.querySelector('[data-co-spt-empty]');
        var errorEl = document.querySelector('[data-co-spt-error]');
        var errorMsg = document.querySelector('[data-co-spt-error-msg]');

        apiGet('/v1/hcm/spt-masa/headers?per_page=5').then(function (payload) {
            if (loading) loading.classList.add('d-none');
            if (!payload || payload.success !== true) {
                if (errorEl) errorEl.classList.remove('d-none');
                if (errorMsg) errorMsg.textContent = (payload && payload.error && payload.error.message) ? payload.error.message : 'Gagal memuat data SPT.';
                return;
            }
            var items = (payload.data && payload.data.items) ? payload.data.items : (Array.isArray(payload.data) ? payload.data : []);
            if (items.length === 0) {
                if (emptyEl) emptyEl.classList.remove('d-none');
                return;
            }
            if (tableWrap) tableWrap.classList.remove('d-none');
            if (!tbody) return;
            tbody.innerHTML = '';
            items.forEach(function (spt) {
                var tr = document.createElement('tr');
                tr.innerHTML = '<td>' + periodeLabel(spt.periode) + '</td>'
                    + '<td>' + (String(spt.periode || '').split('-')[0] || '—') + '</td>'
                    + '<td>' + sptStatusBadge(spt.status) + '</td>'
                    + '<td>' + formatRupiah(spt.totalPph21) + '</td>'
                    + '<td class="text-end"><a href="' + window.location.origin + '/tax-employees" class="btn btn-xs btn-outline-primary">Detail</a></td>';
                tbody.appendChild(tr);
            });
        }).catch(function (err) {
            if (loading) loading.classList.add('d-none');
            if (errorEl) errorEl.classList.remove('d-none');
            if (errorMsg) errorMsg.textContent = 'Gagal memuat data SPT.';
        });
    }

    // ── Tax Governance Policies ───────────────────────────────────────────────
    function taxStatusBadge(status) {
        var map = {
            draft: '<span class="badge badge-soft-secondary">Draft</span>',
            submitted: '<span class="badge badge-soft-info">Submitted</span>',
            approved: '<span class="badge badge-soft-primary">Approved</span>',
            published: '<span class="badge badge-soft-success">Published</span>',
            superseded: '<span class="badge badge-soft-warning">Superseded</span>',
            void: '<span class="badge badge-soft-danger">Void</span>',
        };
        return map[status] || ('<span class="badge badge-soft-secondary">' + (status || '—') + '</span>');
    }

    function loadTaxPolicies() {
        var loading = document.querySelector('[data-co-tax-loading]');
        var tableWrap = document.querySelector('[data-co-tax-table]');
        var tbody = document.querySelector('[data-co-tax-tbody]');
        var emptyEl = document.querySelector('[data-co-tax-empty]');
        var errorEl = document.querySelector('[data-co-tax-error]');
        var errorMsg = document.querySelector('[data-co-tax-error-msg]');

        apiGet('/v1/hcm/tax-governance/policies?per_page=5').then(function (payload) {
            if (loading) loading.classList.add('d-none');
            if (!payload || payload.success !== true) {
                if (errorEl) errorEl.classList.remove('d-none');
                if (errorMsg) errorMsg.textContent = (payload && payload.error && payload.error.message) ? payload.error.message : 'Gagal memuat kebijakan pajak.';
                return;
            }
            var items = (payload.data && payload.data.items) ? payload.data.items : (Array.isArray(payload.data) ? payload.data : []);
            if (items.length === 0) {
                if (emptyEl) emptyEl.classList.remove('d-none');
                return;
            }
            if (tableWrap) tableWrap.classList.remove('d-none');
            if (!tbody) return;
            tbody.innerHTML = '';
            items.forEach(function (policy) {
                var method = (policy.rules && policy.rules.calculationMethod) ? policy.rules.calculationMethod : '—';
                var effective = policy.effectiveStartDate || policy.publishedAt || policy.createdAt;
                if (effective && effective.length > 10) effective = effective.substring(0, 10);
                var tr = document.createElement('tr');
                tr.innerHTML = '<td>' + (policy.name || '—') + '</td>'
                    + '<td>' + method + '</td>'
                    + '<td>' + taxStatusBadge(policy.status) + '</td>'
                    + '<td class="text-end">' + (effective || '—') + '</td>';
                tbody.appendChild(tr);
            });
        }).catch(function (err) {
            if (loading) loading.classList.add('d-none');
            if (errorEl) errorEl.classList.remove('d-none');
            if (errorMsg) errorMsg.textContent = 'Gagal memuat kebijakan pajak.';
        });
    }

    // ── Boot ──────────────────────────────────────────────────────────────────
    loadCompanyInfo();
    loadEmployeeStats();
    loadSptMasa();
    loadTaxPolicies();
})();
