(function (window, document) {
    'use strict';

    function q(sel) { return document.querySelector(sel); }

    function apiRequest(method, path, payload) {
        if (!window.AuthApi || typeof window.AuthApi.request !== 'function') {
            return Promise.reject(new Error('AuthApi not available'));
        }
        return window.AuthApi.request(method, path, payload).then(function (response) {
            return response && response.data !== undefined ? response.data : response;
        });
    }

    function apiGet(path, params) { return apiRequest('GET', path, params); }
    function apiPost(path, payload) { return apiRequest('POST', path, payload); }
    function apiPut(path, payload) { return apiRequest('PUT', path, payload); }
    function apiDelete(path) { return apiRequest('DELETE', path); }

    function escapeHtml(value) {
        return String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function unwrapData(res) {
        if (!res) { return {}; }
        return res.data ? res.data : res;
    }

    function boolBadge(status) {
        if (status === true) {
            return '<span class="badge bg-success-subtle text-success">Aktif</span>';
        }
        return '<span class="badge bg-secondary-subtle text-secondary">Nonaktif</span>';
    }

    function membershipBadge(status) {
        if (status === 'complete') {
            return '<span class="badge bg-success-subtle text-success">Lengkap</span>';
        }
        if (status === 'partial') {
            return '<span class="badge bg-warning-subtle text-warning">Parsial</span>';
        }
        return '<span class="badge bg-danger-subtle text-danger">Kosong</span>';
    }

    function basisLabel(value) {
        var map = {
            wage_bpjs_health: 'Dasar BPJS Kesehatan',
            wage_bpjs_tk: 'Dasar BPJS Ketenagakerjaan',
            fixed_nominal: 'Nominal Tetap',
        };
        return map[String(value || '')] || '-';
    }

    function programLabel(value) {
        var map = {
            bpjs_kesehatan: 'BPJS Kesehatan',
            jht: 'JHT',
            jp: 'JP',
            jkk: 'JKK',
            jkm: 'JKM',
        };
        return map[String(value || '')] || String(value || '-');
    }

    function partyLabel(value) {
        return value === 'employer' ? 'Perusahaan' : 'Pekerja';
    }

    function formatPercent(value) {
        var numeric = Number(value || 0);
        if (!isFinite(numeric)) { return '-'; }
        return numeric.toFixed(2) + '%';
    }

    function formatDateTime(value) {
        if (!value) { return '-'; }
        var date = new Date(value);
        if (!isFinite(date.getTime())) { return String(value); }
        return new Intl.DateTimeFormat('id-ID', {
            day: '2-digit',
            month: 'short',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        }).format(date);
    }

    function actionLabel(actionType) {
        var map = {
            created: 'Create',
            updated: 'Update',
            deleted: 'Delete',
        };
        return map[String(actionType || '').toLowerCase()] || String(actionType || '-');
    }

    function showError(el, message) {
        if (!el) { return; }
        el.textContent = String(message || 'Terjadi kesalahan.');
        el.classList.remove('d-none');
    }

    function hideError(el) {
        if (!el) { return; }
        el.textContent = '';
        el.classList.add('d-none');
    }

    function getScreen() {
        var root = q('[data-bpjs-governance-page]');
        return root ? (root.getAttribute('data-bpjs-screen') || 'landing') : null;
    }

    function computePolicySummary(policyRows) {
        var grouped = {};
        var employeeRates = [];

        (policyRows || []).forEach(function (row) {
            var key = row.programCode;
            if (!grouped[key]) {
                grouped[key] = { program: key, employeeRate: '-', employerRate: '-' };
            }
            if (row.contributionParty === 'employee') {
                grouped[key].employeeRate = formatPercent(row.ratePercent);
                employeeRates.push(Number(row.ratePercent || 0));
            } else {
                grouped[key].employerRate = formatPercent(row.ratePercent);
            }
        });

        var rows = Object.keys(grouped).sort().map(function (key) { return grouped[key]; });
        var avg = employeeRates.length
            ? employeeRates.reduce(function (sum, n) { return sum + n; }, 0) / employeeRates.length
            : 0;

        return {
            rows: rows,
            programs: rows.length,
            avgEmployeeRate: avg,
        };
    }

    function renderPolicySummary(rows) {
        var body = q('[data-bpjs-policy-summary-body]');
        if (!body) { return; }

        if (!rows || rows.length === 0) {
            body.innerHTML = '<tr><td colspan="3" class="text-center text-muted py-3">Belum ada policy BPJS aktif.</td></tr>';
            return;
        }

        body.innerHTML = rows.map(function (row) {
            return '<tr>'
                + '<td>' + escapeHtml(programLabel(row.program)) + '</td>'
                + '<td>' + escapeHtml(row.employeeRate) + '</td>'
                + '<td>' + escapeHtml(row.employerRate) + '</td>'
                + '</tr>';
        }).join('');
    }

    var policyCache = [];
    var policyBaselineCache = {};

    function policyBaselineKey(programCode, contributionParty) {
        return String(programCode || '') + '_' + String(contributionParty || '');
    }

    function formatRupiah(value) {
        var number = Number(value);
        if (!isFinite(number) || number <= 0) { return '-'; }
        return 'Rp ' + number.toLocaleString('id-ID');
    }

    function salaryCapLabel(row) {
        if (!row) { return '-'; }
        if (row.programCode === 'jp') {
            return row.jpSalaryCap != null ? formatRupiah(row.jpSalaryCap) : 'Default sistem';
        }
        if (row.programCode === 'bpjs_kesehatan') {
            return row.bpjsKesSalaryCap != null ? formatRupiah(row.bpjsKesSalaryCap) : 'Default sistem';
        }
        return '-';
    }

    function policyDefaultTemplate(programCode, contributionParty) {
        var map = {
            bpjs_kesehatan: {
                employee: { ratePercent: 1.0, wageBase: 'wage_bpjs_health' },
                employer: { ratePercent: 4.0, wageBase: 'wage_bpjs_health' },
            },
            jht: {
                employee: { ratePercent: 2.0, wageBase: 'wage_bpjs_tk' },
                employer: { ratePercent: 3.7, wageBase: 'wage_bpjs_tk' },
            },
            jp: {
                employee: { ratePercent: 1.0, wageBase: 'wage_bpjs_tk' },
                employer: { ratePercent: 2.0, wageBase: 'wage_bpjs_tk' },
            },
            jkk: {
                employee: { ratePercent: 0.0, wageBase: 'wage_bpjs_tk' },
                employer: { ratePercent: 0.24, wageBase: 'wage_bpjs_tk' },
            },
            jkm: {
                employee: { ratePercent: 0.0, wageBase: 'wage_bpjs_tk' },
                employer: { ratePercent: 0.3, wageBase: 'wage_bpjs_tk' },
            },
        };
        var program = map[String(programCode || '')] || {};
        return program[String(contributionParty || '')] || { ratePercent: 0, wageBase: '' };
    }

    function loadPolicyBaselines() {
        return apiGet('/hcm/bpjs-governance/rate-baselines')
            .then(function (res) {
                var data = unwrapData(res);
                var items = Array.isArray(data.items) ? data.items : [];
                policyBaselineCache = {};
                items.forEach(function (row) {
                    policyBaselineCache[policyBaselineKey(row.programCode, row.contributionParty)] = row;
                });
                return items;
            })
            .catch(function () {
                policyBaselineCache = {};
                return [];
            });
    }

    function renderPoliciesTable(rows) {
        var body = q('[data-bpjs-policy-table-body]');
        if (!body) { return; }

        if (!rows || rows.length === 0) {
            body.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-4">Belum ada policy BPJS.</td></tr>';
            return;
        }

        body.innerHTML = rows.map(function (row) {
            return '<tr>'
                + '<td><code>' + escapeHtml(row.programCode + '_' + row.contributionParty) + '</code></td>'
                + '<td>' + escapeHtml(programLabel(row.programCode)) + '</td>'
                + '<td>' + escapeHtml(partyLabel(row.contributionParty)) + '</td>'
                + '<td>' + escapeHtml(formatPercent(row.ratePercent)) + '</td>'
                + '<td>' + escapeHtml(salaryCapLabel(row)) + '</td>'
                + '<td>' + escapeHtml(basisLabel(row.wageBase)) + '</td>'
                + '<td>' + boolBadge(!!row.isActive) + '</td>'
                + '<td class="d-flex gap-2">'
                + '<button type="button" class="btn btn-sm btn-outline-primary" data-bpjs-policy-edit data-policy-uuid="' + escapeHtml(String(row.uuid || '')) + '">Edit</button>'
                + '<button type="button" class="btn btn-sm btn-outline-danger" data-bpjs-policy-delete data-policy-uuid="' + escapeHtml(String(row.uuid || '')) + '">Hapus</button>'
                + '</td>'
                + '</tr>';
        }).join('');
    }

    function loadPolicies() {
        var summaryBody = q('[data-bpjs-policy-summary-body]');
        var tableBody = q('[data-bpjs-policy-table-body]');
        if (summaryBody) {
            summaryBody.innerHTML = '<tr><td colspan="3" class="text-center text-muted py-3">Memuat data BPJS...</td></tr>';
        }
        if (tableBody) {
            tableBody.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-4">Memuat policy BPJS...</td></tr>';
        }

        return Promise.all([
            apiGet('/hcm/bpjs-governance/policies', { active_only: 0 }),
            loadPolicyBaselines(),
        ]).then(function (result) {
                var res = result[0];
                var data = unwrapData(res);
                var items = Array.isArray(data.items) ? data.items : [];
                policyCache = items.map(function (policyRow) {
                    var baseline = policyBaselineCache[policyBaselineKey(policyRow.programCode, policyRow.contributionParty)] || {};
                    return Object.assign({}, policyRow, {
                        jpSalaryCap: baseline.jpSalaryCap != null ? Number(baseline.jpSalaryCap) : null,
                        bpjsKesSalaryCap: baseline.bpjsKesSalaryCap != null ? Number(baseline.bpjsKesSalaryCap) : null,
                    });
                });

                var summary = computePolicySummary(policyCache.filter(function (row) { return !!row.isActive; }));
                renderPolicySummary(summary.rows);
                renderPoliciesTable(policyCache);

                var programsEl = q('[data-bpjs-kpi-programs]');
                var avgEl = q('[data-bpjs-kpi-employee-rate]');
                if (programsEl) { programsEl.textContent = String(summary.programs); }
                if (avgEl) { avgEl.textContent = formatPercent(summary.avgEmployeeRate); }

                return policyCache;
            })
            .catch(function (err) {
                var msg = escapeHtml(err && err.message ? err.message : 'unknown error');
                if (summaryBody) {
                    summaryBody.innerHTML = '<tr><td colspan="3" class="text-center text-danger py-3">Gagal memuat policy: ' + msg + '</td></tr>';
                }
                if (tableBody) {
                    tableBody.innerHTML = '<tr><td colspan="8" class="text-center text-danger py-4">Gagal memuat policy: ' + msg + '</td></tr>';
                }
                return [];
            });
    }

    function loadPolicyHistory() {
        var body = q('[data-bpjs-policy-history-body]');
        if (!body) { return Promise.resolve([]); }

        body.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-4">Memuat riwayat perubahan...</td></tr>';

        return apiGet('/hcm/bpjs-governance/policies/history', { limit: 50 })
            .then(function (res) {
                var data = unwrapData(res);
                var items = Array.isArray(data.items) ? data.items : [];

                if (!items.length) {
                    body.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-4">Belum ada riwayat perubahan.</td></tr>';
                    return items;
                }

                body.innerHTML = items.map(function (row) {
                    return '<tr>'
                        + '<td>' + escapeHtml(formatDateTime(row.changedAt)) + '</td>'
                        + '<td>' + escapeHtml(actionLabel(row.actionType)) + '</td>'
                        + '<td>' + escapeHtml(programLabel(row.programCode)) + '</td>'
                        + '<td>' + escapeHtml(partyLabel(row.contributionParty)) + '</td>'
                        + '<td>' + escapeHtml(formatPercent(row.ratePercent)) + '</td>'
                        + '<td>' + escapeHtml(row.changedByUserName || row.changedByUserEmail || 'System') + '</td>'
                        + '<td>' + escapeHtml(row.notes || '-') + '</td>'
                        + '</tr>';
                }).join('');

                return items;
            })
            .catch(function (err) {
                body.innerHTML = '<tr><td colspan="7" class="text-center text-danger py-4">Gagal memuat riwayat: '
                    + escapeHtml(err && err.message ? err.message : 'unknown error') + '</td></tr>';
                return [];
            });
    }

    function syncPolicySalaryCap(programCode, capValue) {
        if (programCode !== 'jp' && programCode !== 'bpjs_kesehatan') {
            return Promise.resolve();
        }

        var parties = ['employee', 'employer'];
        var capField = programCode === 'jp' ? 'jpSalaryCap' : 'bpjsKesSalaryCap';

        return Promise.all(parties.map(function (party) {
            var key = policyBaselineKey(programCode, party);
            var row = policyBaselineCache[key];
            if (!row) {
                return Promise.resolve();
            }

            var payload = {
                minRate: Number(row.minRate || 0),
                maxRate: Number(row.maxRate || 0),
                notes: row.notes || null,
                riskCategory: row.riskCategory != null ? Number(row.riskCategory) : null,
                jpSalaryCap: capField === 'jpSalaryCap' ? capValue : (row.jpSalaryCap != null ? Number(row.jpSalaryCap) : null),
                bpjsKesSalaryCap: capField === 'bpjsKesSalaryCap' ? capValue : (row.bpjsKesSalaryCap != null ? Number(row.bpjsKesSalaryCap) : null),
            };

            return apiPut('/hcm/bpjs-governance/rate-baselines/' + programCode + '/' + party, payload);
        })).then(function () {
            return loadPolicyBaselines();
        });
    }

    function updatePolicyCapSections(programCode) {
        var jpCapSection = q('[data-bpjs-policy-jp-cap-section]');
        var kesCapSection = q('[data-bpjs-policy-kes-cap-section]');
        if (jpCapSection) { jpCapSection.classList.toggle('d-none', programCode !== 'jp'); }
        if (kesCapSection) { kesCapSection.classList.toggle('d-none', programCode !== 'bpjs_kesehatan'); }
    }

    function openPolicyModal(policy) {
        var modalEl = document.getElementById('bpjsPolicyModal');
        if (!modalEl || !window.bootstrap || !window.bootstrap.Modal) { return; }

        var isEdit = !!(policy && policy.uuid);
        var programCode = policy ? String(policy.programCode || 'bpjs_kesehatan') : 'bpjs_kesehatan';
        var contributionParty = policy ? String(policy.contributionParty || 'employee') : 'employee';
        var defaults = policyDefaultTemplate(programCode, contributionParty);

        var programEl = q('[data-bpjs-policy-program]');
        var partyEl = q('[data-bpjs-policy-party]');
        var wageBaseEl = q('[data-bpjs-policy-wage-base]');
        var startEl = q('[data-bpjs-policy-start]');
        var endEl = q('[data-bpjs-policy-end]');

        q('[data-bpjs-policy-id]').value = policy ? String(policy.uuid || '') : '';
        if (programEl) { programEl.value = programCode; }
        if (partyEl) { partyEl.value = contributionParty; }
        q('[data-bpjs-policy-rate]').value = policy ? String(policy.ratePercent || '') : String(defaults.ratePercent || '');
        if (wageBaseEl) { wageBaseEl.value = policy ? String(policy.wageBase || '') : String(defaults.wageBase || ''); }
        if (startEl) { startEl.value = policy ? String(policy.effectiveStartDate || '') : new Date().toISOString().slice(0, 10); }
        if (endEl) { endEl.value = policy ? String(policy.effectiveEndDate || '') : ''; }
        q('[data-bpjs-policy-legal-basis]').value = policy ? String(policy.legalBasis || '') : '';
        q('[data-bpjs-policy-notes]').value = policy ? String(policy.notes || '') : '';
        q('[data-bpjs-policy-active]').checked = policy ? !!policy.isActive : true;

        if (programEl) { programEl.disabled = isEdit; }
        if (partyEl) { partyEl.disabled = isEdit; }
        if (wageBaseEl) { wageBaseEl.disabled = isEdit; }
        if (startEl) { startEl.readOnly = isEdit; }
        if (endEl) { endEl.readOnly = isEdit; }

        updatePolicyCapSections(programCode);

        var jpCapInput = q('[data-bpjs-policy-jp-salary-cap]');
        var kesCapInput = q('[data-bpjs-policy-kes-salary-cap]');
        if (jpCapInput) { jpCapInput.value = policy && policy.jpSalaryCap != null ? String(policy.jpSalaryCap) : ''; }
        if (kesCapInput) { kesCapInput.value = policy && policy.bpjsKesSalaryCap != null ? String(policy.bpjsKesSalaryCap) : ''; }

        hideError(q('[data-bpjs-policy-error]'));

        var title = q('#bpjsPolicyModalLabel');
        if (title) {
            title.textContent = isEdit ? 'Edit Policy BPJS' : 'Tambah Policy BPJS';
        }

        window.bootstrap.Modal.getOrCreateInstance(modalEl).show();
    }

    function bindPolicyActions() {
        var addBtn = q('[data-bpjs-policy-add]');
        if (addBtn) {
            addBtn.addEventListener('click', function () {
                openPolicyModal(null);
            });
        }

        var refreshBtn = q('[data-bpjs-policy-refresh]');
        if (refreshBtn) {
            refreshBtn.addEventListener('click', function () {
                loadPolicies();
                loadPolicyHistory();
            });
        }

        var programInput = q('[data-bpjs-policy-program]');
        var partyInput = q('[data-bpjs-policy-party]');
        var rateInput = q('[data-bpjs-policy-rate]');
        var wageBaseInput = q('[data-bpjs-policy-wage-base]');

        function syncDefaultsForCreateMode() {
            var id = q('[data-bpjs-policy-id]').value;
            if (id) {
                return;
            }

            var programCode = programInput ? programInput.value : 'bpjs_kesehatan';
            var contributionParty = partyInput ? partyInput.value : 'employee';
            var defaults = policyDefaultTemplate(programCode, contributionParty);
            if (rateInput) { rateInput.value = String(defaults.ratePercent || 0); }
            if (wageBaseInput) { wageBaseInput.value = String(defaults.wageBase || ''); }
            updatePolicyCapSections(programCode);
        }

        if (programInput) {
            programInput.addEventListener('change', syncDefaultsForCreateMode);
        }
        if (partyInput) {
            partyInput.addEventListener('change', syncDefaultsForCreateMode);
        }

        var tableBody = q('[data-bpjs-policy-table-body]');
        if (tableBody) {
            tableBody.addEventListener('click', function (event) {
                var btn = event.target.closest('[data-bpjs-policy-edit]');
                var deleteBtn = event.target.closest('[data-bpjs-policy-delete]');

                if (btn) {
                    var uuid = btn.getAttribute('data-policy-uuid') || '';
                    var row = policyCache.find(function (item) { return String(item.uuid) === uuid; });
                    if (row) {
                        openPolicyModal(row);
                    }
                    return;
                }

                if (deleteBtn) {
                    var deleteUuid = deleteBtn.getAttribute('data-policy-uuid') || '';
                    var deleteRow = policyCache.find(function (item) { return String(item.uuid) === deleteUuid; });
                    if (!deleteUuid || !deleteRow) { return; }

                    var confirmText = 'Hapus policy ' + programLabel(deleteRow.programCode) + ' (' + partyLabel(deleteRow.contributionParty) + ')?';
                    if (!window.confirm(confirmText)) { return; }

                    deleteBtn.disabled = true;
                    apiDelete('/hcm/bpjs-governance/policies/' + deleteUuid)
                        .then(function () {
                            return Promise.all([loadPolicies(), loadPolicyHistory()]);
                        })
                        .catch(function (err) {
                            alert(err && err.message ? err.message : 'Gagal menghapus policy BPJS.');
                        })
                        .finally(function () {
                            deleteBtn.disabled = false;
                        });
                }
            });
        }

        var policyForm = q('[data-bpjs-policy-form]');
        if (policyForm) {
            policyForm.addEventListener('submit', function (event) {
                event.preventDefault();

                var id = q('[data-bpjs-policy-id]').value;
                var isEdit = !!id;

                var programCode = q('[data-bpjs-policy-program]').value;
                var contributionParty = q('[data-bpjs-policy-party]').value;
                var jpCapInput = q('[data-bpjs-policy-jp-salary-cap]');
                var kesCapInput = q('[data-bpjs-policy-kes-salary-cap]');
                var jpCapValue = jpCapInput && jpCapInput.value !== '' ? Number(jpCapInput.value) : null;
                var kesCapValue = kesCapInput && kesCapInput.value !== '' ? Number(kesCapInput.value) : null;

                var payload = isEdit ? {
                    ratePercent: Number(q('[data-bpjs-policy-rate]').value || 0),
                    legalBasis: q('[data-bpjs-policy-legal-basis]').value || null,
                    notes: q('[data-bpjs-policy-notes]').value || null,
                    isActive: q('[data-bpjs-policy-active]').checked,
                } : {
                    programCode: programCode,
                    contributionParty: contributionParty,
                    ratePercent: Number(q('[data-bpjs-policy-rate]').value || 0),
                    wageBase: q('[data-bpjs-policy-wage-base]').value || null,
                    effectiveStartDate: q('[data-bpjs-policy-start]').value || null,
                    effectiveEndDate: q('[data-bpjs-policy-end]').value || null,
                    legalBasis: q('[data-bpjs-policy-legal-basis]').value || null,
                    notes: q('[data-bpjs-policy-notes]').value || null,
                    isActive: q('[data-bpjs-policy-active]').checked,
                };

                var spinner = q('[data-bpjs-policy-spinner]');
                var submitBtn = q('[data-bpjs-policy-submit]');
                var errorBox = q('[data-bpjs-policy-error]');
                hideError(errorBox);
                if (spinner) { spinner.classList.remove('d-none'); }
                if (submitBtn) { submitBtn.disabled = true; }

                var request = isEdit
                    ? apiPut('/hcm/bpjs-governance/policies/' + id, payload)
                    : apiPost('/hcm/bpjs-governance/policies', payload);

                request
                .then(function () {
                    if (programCode === 'jp') {
                        return syncPolicySalaryCap('jp', jpCapValue);
                    }
                    if (programCode === 'bpjs_kesehatan') {
                        return syncPolicySalaryCap('bpjs_kesehatan', kesCapValue);
                    }
                    return Promise.resolve();
                })
                .then(function () {
                    var modalEl = document.getElementById('bpjsPolicyModal');
                    if (modalEl && window.bootstrap && window.bootstrap.Modal) {
                        var modal = window.bootstrap.Modal.getInstance(modalEl);
                        if (modal) { modal.hide(); }
                    }
                    return Promise.all([loadPolicies(), loadPolicyHistory()]);
                })
                .catch(function (err) {
                    showError(errorBox, err && err.message ? err.message : 'Gagal menyimpan policy BPJS.');
                }).finally(function () {
                    if (spinner) { spinner.classList.add('d-none'); }
                    if (submitBtn) { submitBtn.disabled = false; }
                });
            });
        }
    }

    var membershipCache = [];

    function loadMembership(searchText) {
        var body = q('[data-bpjs-employee-membership-body]');
        var summary = q('[data-bpjs-employee-summary]');
        var completeEl = q('[data-bpjs-employee-complete]');
        var partialEl = q('[data-bpjs-employee-partial]');
        var missingEl = q('[data-bpjs-employee-missing]');
        if (body) {
            body.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-4">Memuat membership karyawan...</td></tr>';
        }

        return apiGet('/hcm/bpjs-governance/employee-membership', {
            search: searchText ? String(searchText).trim() : undefined,
            page: 1,
            perPage: 20,
        }).then(function (res) {
            var data = unwrapData(res);
            var items = Array.isArray(data.items) ? data.items : [];
            var meta = data.meta || {};
            membershipCache = items;

            if (body) {
                if (items.length === 0) {
                    body.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-4">Tidak ada data karyawan.</td></tr>';
                } else {
                    body.innerHTML = items.map(function (row) {
                        var kes = row.bpjsKesehatanNo
                            ? '<code>' + escapeHtml(row.bpjsKesehatanNo) + '</code>'
                            : '<span class="text-muted small">Kosong</span>';
                        var tk = row.bpjsKetenagakerjaanNo
                            ? '<code>' + escapeHtml(row.bpjsKetenagakerjaanNo) + '</code>'
                            : '<span class="text-muted small">Kosong</span>';

                        return '<tr>'
                            + '<td><div class="fw-semibold">' + escapeHtml(row.fullName || '-') + '</div><div class="text-muted small">' + escapeHtml(row.email || '-') + '</div></td>'
                            + '<td>' + kes + '</td>'
                            + '<td>' + tk + '</td>'
                            + '<td>' + membershipBadge(row.membershipStatus) + '</td>'
                            + '<td><button type="button" class="btn btn-sm btn-outline-primary" data-bpjs-membership-edit data-user-id="' + escapeHtml(String(row.id)) + '">Edit</button></td>'
                            + '</tr>';
                    }).join('');
                }
            }

            if (summary) {
                var total = Number(meta.total || 0);
                var complete = Number(meta.complete || 0);
                var displayed = Number(meta.displayedTotal || items.length);
                var filtered = Number(meta.filteredTotal || displayed || total);
                var searchApplied = !!(searchText && String(searchText).trim());

                if (searchApplied) {
                    summary.textContent = complete + '/' + total + ' membership lengkap (menampilkan ' + displayed + ' dari ' + filtered + ' hasil pencarian)';
                } else {
                    summary.textContent = complete + '/' + total + ' membership lengkap (menampilkan ' + displayed + ' data)';
                }
            }

            if (completeEl) { completeEl.textContent = String(meta.complete || 0); }
            if (partialEl) { partialEl.textContent = String(meta.partial || 0); }
            if (missingEl) { missingEl.textContent = String(meta.missing || 0); }

            var membershipKpi = q('[data-bpjs-kpi-membership]');
            if (membershipKpi) {
                membershipKpi.textContent = String(meta.complete || 0) + '/' + String(meta.total || items.length);
            }

            return { items: items, meta: meta };
        }).catch(function (err) {
            if (body) {
                body.innerHTML = '<tr><td colspan="5" class="text-center text-danger py-4">Gagal memuat membership: ' + escapeHtml(err && err.message ? err.message : 'unknown error') + '</td></tr>';
            }
            if (summary) {
                summary.textContent = 'Gagal memuat membership.';
            }
            return { items: [], meta: { total: 0, complete: 0 } };
        });
    }

    function openMembershipModal(row) {
        var modalEl = document.getElementById('bpjsMembershipModal');
        if (!modalEl || !window.bootstrap || !window.bootstrap.Modal) { return; }

        q('[data-bpjs-membership-user-id]').value = String(row.id || '');
        q('[data-bpjs-membership-name]').value = String(row.fullName || '-');
        q('[data-bpjs-membership-kes]').value = String(row.bpjsKesehatanNo || '');
        q('[data-bpjs-membership-tk]').value = String(row.bpjsKetenagakerjaanNo || '');
        q('[data-bpjs-membership-effective-date]').value = row.effectiveDate || '';
        hideError(q('[data-bpjs-membership-error]'));

        window.bootstrap.Modal.getOrCreateInstance(modalEl).show();
    }

    function bindMembershipActions() {
        var searchInput = q('[data-bpjs-employee-search]');
        var refreshBtn = q('[data-bpjs-employee-refresh]');
        var debounceTimer = null;

        if (searchInput) {
            searchInput.addEventListener('input', function () {
                clearTimeout(debounceTimer);
                debounceTimer = setTimeout(function () {
                    loadMembership(searchInput.value || '');
                }, 300);
            });
        }

        if (refreshBtn) {
            refreshBtn.addEventListener('click', function () {
                loadMembership(searchInput ? searchInput.value : '');
            });
        }

        var body = q('[data-bpjs-employee-membership-body]');
        if (body) {
            body.addEventListener('click', function (event) {
                var btn = event.target.closest('[data-bpjs-membership-edit]');
                if (!btn) { return; }
                var userId = Number(btn.getAttribute('data-user-id') || '0');
                var row = membershipCache.find(function (item) { return Number(item.id) === userId; });
                if (row) {
                    openMembershipModal(row);
                }
            });
        }

        var form = q('[data-bpjs-membership-form]');
        if (form) {
            form.addEventListener('submit', function (event) {
                event.preventDefault();
                var userId = q('[data-bpjs-membership-user-id]').value;
                if (!userId) { return; }

                var payload = {
                    bpjsKesehatanNo: q('[data-bpjs-membership-kes]').value || null,
                    bpjsKetenagakerjaanNo: q('[data-bpjs-membership-tk]').value || null,
                    effectiveDate: q('[data-bpjs-membership-effective-date]').value || null,
                };

                var spinner = q('[data-bpjs-membership-spinner]');
                var submit = q('[data-bpjs-membership-submit]');
                var errorBox = q('[data-bpjs-membership-error]');
                hideError(errorBox);
                if (spinner) { spinner.classList.remove('d-none'); }
                if (submit) { submit.disabled = true; }

                apiPut('/hcm/bpjs-governance/employee-membership/' + userId, payload)
                    .then(function () {
                        var modalEl = document.getElementById('bpjsMembershipModal');
                        if (modalEl && window.bootstrap && window.bootstrap.Modal) {
                            var modal = window.bootstrap.Modal.getInstance(modalEl);
                            if (modal) { modal.hide(); }
                        }
                        loadMembership(q('[data-bpjs-employee-search]') ? q('[data-bpjs-employee-search]').value : '');
                    })
                    .catch(function (err) {
                        showError(errorBox, err && err.message ? err.message : 'Gagal menyimpan membership BPJS.');
                    })
                    .finally(function () {
                        if (spinner) { spinner.classList.add('d-none'); }
                        if (submit) { submit.disabled = false; }
                    });
            });
        }
    }

    function loadReports() {
        var body = q('[data-bpjs-report-checklist-body]');
        var scoreEl = q('[data-bpjs-report-score]');
        var summaryEl = q('[data-bpjs-report-summary]');
        var scoreBarEl = q('[data-bpjs-report-score-bar]');
        if (body) {
            body.innerHTML = '<tr><td colspan="3" class="text-center text-muted py-3">Menyusun laporan kepatuhan...</td></tr>';
        }

        return apiGet('/hcm/bpjs-governance/reports')
            .then(function (res) {
                var data = unwrapData(res);
                var checks = Array.isArray(data.checks) ? data.checks : [];
                var score = Number(data.score || 0);

                if (body) {
                    if (!checks.length) {
                        body.innerHTML = '<tr><td colspan="3" class="text-center text-muted py-3">Belum ada checklist.</td></tr>';
                    } else {
                        body.innerHTML = checks.map(function (item) {
                            var badge = item.pass
                                ? '<span class="badge bg-success-subtle text-success">Pass</span>'
                                : '<span class="badge bg-danger-subtle text-danger">Gap</span>';

                            var evidenceDetail = escapeHtml(item.code || '-');
                            var nonCompliant = item && item.evidence && Array.isArray(item.evidence.nonCompliantEmployees)
                                ? item.evidence.nonCompliantEmployees
                                : [];
                            if (!item.pass && nonCompliant.length) {
                                var preview = nonCompliant.slice(0, 5).map(function (emp) {
                                    var issues = Array.isArray(emp.issues) ? emp.issues.map(function (issue) {
                                        return issue && issue.label ? issue.label : '-';
                                    }).join(', ') : '-';
                                    return '<div><strong>' + escapeHtml(emp.fullName || '-') + '</strong>'
                                        + ' <span class="text-muted">(' + escapeHtml(emp.email || '-') + ')</span>'
                                        + '<br><span class="text-danger">' + escapeHtml(issues) + '</span></div>';
                                }).join('');
                                evidenceDetail = '<div class="mb-1"><code>' + escapeHtml(item.code || '-') + '</code></div>'
                                    + '<div class="small">' + preview + '</div>'
                                    + (nonCompliant.length > 5
                                        ? '<div class="small text-muted mt-1">+' + String(nonCompliant.length - 5) + ' karyawan lain terdampak.</div>'
                                        : '');
                            }

                            return '<tr>'
                                + '<td>' + escapeHtml(item.label || '-') + '</td>'
                                + '<td>' + badge + '</td>'
                                + '<td class="text-muted small">' + evidenceDetail + '</td>'
                                + '</tr>';
                        }).join('');
                    }
                }

                if (scoreEl) { scoreEl.textContent = String(score) + '%'; }
                if (summaryEl) {
                    summaryEl.textContent = 'Periode: ' + escapeHtml(String(data.reportingPeriod || '-'))
                        + ' | Policy aktif: ' + String(data.policyActiveCount || 0);
                }
                if (scoreBarEl) {
                    scoreBarEl.style.width = String(score) + '%';
                    scoreBarEl.setAttribute('aria-valuenow', String(score));
                }
            })
            .catch(function (err) {
                if (body) {
                    body.innerHTML = '<tr><td colspan="3" class="text-center text-danger py-3">Gagal memuat laporan: '
                        + escapeHtml(err && err.message ? err.message : 'unknown error') + '</td></tr>';
                }
            });
    }

    function init() {
        var screen = getScreen();
        if (!screen) { return; }

        if (screen === 'landing') {
            Promise.all([loadPolicies(), loadMembership(''), loadReports()]);

            var reportRefreshLanding = q('[data-bpjs-report-refresh]');
            if (reportRefreshLanding) {
                reportRefreshLanding.addEventListener('click', function () {
                    loadReports();
                });
            }

            var exportBtnLanding = q('[data-bpjs-report-export]');
            if (exportBtnLanding) {
                exportBtnLanding.addEventListener('click', function () {
                    if (!window.AuthApi || typeof window.AuthApi.getToken !== 'function') { return; }
                    var token = window.AuthApi.getToken ? window.AuthApi.getToken() : null;
                    var companyId = window.AuthApi.getActiveCompanyId ? window.AuthApi.getActiveCompanyId() : null;
                    var url = '/v1/hcm/bpjs-governance/reports/export';
                    var headers = { 'Accept': 'application/json' };
                    if (token) { headers['Authorization'] = 'Bearer ' + token; }
                    if (companyId) { headers['X-Company-Id'] = String(companyId); }
                    exportBtnLanding.disabled = true;
                    fetch(url, { method: 'GET', headers: headers })
                        .then(function (res) {
                            if (!res.ok) { throw new Error('Export gagal: ' + res.status); }
                            return res.blob();
                        })
                        .then(function (blob) {
                            var objectUrl = URL.createObjectURL(blob);
                            var tempA = document.createElement('a');
                            tempA.href = objectUrl;
                            tempA.download = 'bpjs-compliance-report-' + new Date().toISOString().slice(0, 10) + '.json';
                            document.body.appendChild(tempA);
                            tempA.click();
                            document.body.removeChild(tempA);
                            URL.revokeObjectURL(objectUrl);
                        })
                        .catch(function (err) {
                            alert(err && err.message ? err.message : 'Gagal mengunduh laporan.');
                        })
                        .finally(function () { exportBtnLanding.disabled = false; });
                });
            }
            return;
        }

        if (screen === 'policies') {
            loadPolicies();
            loadPolicyHistory();
            bindPolicyActions();
            return;
        }

        if (screen === 'employee-membership') {
            loadMembership('');
            bindMembershipActions();
            return;
        }

        if (screen === 'reports') {
            var reportRefresh = q('[data-bpjs-report-refresh]');
            if (reportRefresh) {
                reportRefresh.addEventListener('click', function () {
                    loadReports();
                });
            }

            var exportBtn = q('[data-bpjs-report-export]');
            if (exportBtn) {
                exportBtn.addEventListener('click', function () {
                    if (!window.AuthApi || typeof window.AuthApi.getToken !== 'function') { return; }
                    var token = window.AuthApi.getToken ? window.AuthApi.getToken() : null;
                    var companyId = window.AuthApi.getActiveCompanyId ? window.AuthApi.getActiveCompanyId() : null;
                    var url = '/v1/hcm/bpjs-governance/reports/export';
                    // Build URL with auth headers via hidden anchor
                    var a = document.createElement('a');
                    a.href = url;
                    a.setAttribute('download', '');
                    // Use fetch to trigger download preserving auth headers
                    var headers = { 'Accept': 'application/json' };
                    if (token) { headers['Authorization'] = 'Bearer ' + token; }
                    if (companyId) { headers['X-Company-Id'] = String(companyId); }
                    exportBtn.disabled = true;
                    fetch(url, { method: 'GET', headers: headers })
                        .then(function (res) {
                            if (!res.ok) { throw new Error('Export gagal: ' + res.status); }
                            return res.blob();
                        })
                        .then(function (blob) {
                            var objectUrl = URL.createObjectURL(blob);
                            var tempA = document.createElement('a');
                            tempA.href = objectUrl;
                            tempA.download = 'bpjs-compliance-report-' + new Date().toISOString().slice(0, 10) + '.json';
                            document.body.appendChild(tempA);
                            tempA.click();
                            document.body.removeChild(tempA);
                            URL.revokeObjectURL(objectUrl);
                        })
                        .catch(function (err) {
                            alert(err && err.message ? err.message : 'Gagal mengunduh laporan.');
                        })
                        .finally(function () { exportBtn.disabled = false; });
                });
            }

            loadReports();
            return;
        }

        if (screen === 'rate-baselines') {
            loadRateBaselines();
            bindRateBaselineActions();
        }
    }

    var rateBaselineCache = [];

    function rateBaselineSourceBadge(source) {
        if (source === 'tenant') {
            return '<span class="badge bg-primary-subtle text-primary">Konfigurasi Tenant</span>';
        }
        return '<span class="badge bg-secondary-subtle text-secondary">Default Sistem</span>';
    }

    function loadRateBaselines() {
        var body = q('[data-bpjs-baseline-table-body]');
        var warningEl = q('[data-bpjs-baseline-warning]');
        if (body) {
            body.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-4">Memuat konfigurasi baseline...</td></tr>';
        }
        return apiGet('/hcm/bpjs-governance/rate-baselines')
            .then(function (res) {
                var data = unwrapData(res);
                var items = Array.isArray(data.items) ? data.items : [];
                rateBaselineCache = items;
                var jkkBaseline = items.find(function (item) {
                    return item.programCode === 'jkk' && item.contributionParty === 'employer';
                });

                if (warningEl) {
                    if (jkkBaseline && jkkBaseline.riskCategory) {
                        warningEl.classList.remove('alert-warning');
                        warningEl.classList.add('alert-info');
                        warningEl.textContent = 'JKK aktif pada kategori risiko ' + String(jkkBaseline.riskCategory)
                            + '. Payroll akan menghitung premi JKK mengikuti kategori ini.';
                    } else {
                        warningEl.classList.remove('alert-info');
                        warningEl.classList.add('alert-warning');
                        warningEl.textContent = 'Baseline JKK belum menetapkan kategori risiko. Payroll akan memakai default kategori 1 (0,24%) sampai dikonfigurasi.';
                    }
                }

                if (body) {
                    if (!items.length) {
                        body.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-4">Tidak ada data baseline.</td></tr>';
                    } else {
                        body.innerHTML = items.map(function (row) {
                            var riskInfo = '';
                            if (row.programCode === 'jkk' && row.contributionParty === 'employer') {
                                riskInfo = row.riskCategory
                                    ? '<div class="text-muted small mt-1">Kategori Risiko: ' + escapeHtml(String(row.riskCategory)) + '</div>'
                                    : '<div class="text-warning small mt-1">Kategori risiko belum diset (default: 1 / 0,24%)</div>';
                            }
                            return '<tr>'
                                + '<td>' + escapeHtml(programLabel(row.programCode)) + '</td>'
                                + '<td>' + escapeHtml(partyLabel(row.contributionParty)) + '</td>'
                                + '<td>' + escapeHtml(formatPercent(row.minRate)) + '</td>'
                                + '<td>' + escapeHtml(formatPercent(row.maxRate)) + '</td>'
                                + '<td>' + escapeHtml(basisLabel(row.wageBase)) + riskInfo + '</td>'
                                + '<td>' + rateBaselineSourceBadge(row.source) + '</td>'
                                + '<td><button type="button" class="btn btn-sm btn-outline-primary" data-bpjs-baseline-edit'
                                + ' data-program="' + escapeHtml(row.programCode) + '" data-party="' + escapeHtml(row.contributionParty) + '">Edit</button></td>'
                                + '</tr>';
                        }).join('');
                    }
                }
                return items;
            })
            .catch(function (err) {
                if (warningEl) {
                    warningEl.classList.remove('alert-info');
                    warningEl.classList.add('alert-warning');
                    warningEl.textContent = 'Gagal memuat baseline. Validasi kategori risiko JKK belum bisa dilakukan.';
                }
                if (body) {
                    body.innerHTML = '<tr><td colspan="7" class="text-center text-danger py-4">Gagal memuat baseline: '
                        + escapeHtml(err && err.message ? err.message : 'unknown error') + '</td></tr>';
                }
                return [];
            });
    }

    function openRateBaselineModal(row) {
        var modalEl = document.getElementById('bpjsRateBaselineModal');
        if (!modalEl || !window.bootstrap || !window.bootstrap.Modal) { return; }

        var program = String(row.programCode || '');
        var party = String(row.contributionParty || '');

        q('[data-bpjs-baseline-program]').value = program;
        q('[data-bpjs-baseline-party]').value = party;
        q('[data-bpjs-baseline-program-label]').textContent = programLabel(row.programCode) + ' — ' + partyLabel(row.contributionParty);
        q('[data-bpjs-baseline-min-rate]').value = String(row.minRate || '');
        q('[data-bpjs-baseline-max-rate]').value = String(row.maxRate || '');
        q('[data-bpjs-baseline-wage-base]').value = String(row.wageBase || '');
        var wbLabel = q('[data-bpjs-baseline-wage-base-label]');
        if (wbLabel) { wbLabel.value = basisLabel(row.wageBase); }
        q('[data-bpjs-baseline-notes]').value = String(row.notes || '');

        // JKK section — hanya tampil untuk program=jkk, porsi=employer
        var jkkSection = q('[data-bpjs-baseline-jkk-section]');
        var isJkk = (program === 'jkk' && party === 'employer');
        if (jkkSection) {
            jkkSection.classList.toggle('d-none', !isJkk);
        }
        var riskCatEl = q('[data-bpjs-baseline-risk-category]');
        if (riskCatEl) { riskCatEl.value = row.riskCategory != null ? String(row.riskCategory) : ''; }

        // JP salary cap section — tampil untuk program=jp
        var jpCapSection = q('[data-bpjs-baseline-jp-cap-section]');
        var isJp = (program === 'jp');
        if (jpCapSection) { jpCapSection.classList.toggle('d-none', !isJp); }
        var jpCapEl = q('[data-bpjs-baseline-jp-salary-cap]');
        if (jpCapEl) { jpCapEl.value = row.jpSalaryCap != null ? String(row.jpSalaryCap) : ''; }

        // BPJS Kesehatan salary cap section — tampil untuk program=bpjs_kesehatan
        var kesCapSection = q('[data-bpjs-baseline-kes-cap-section]');
        var isKes = (program === 'bpjs_kesehatan');
        if (kesCapSection) { kesCapSection.classList.toggle('d-none', !isKes); }
        var kesCapEl = q('[data-bpjs-baseline-kes-salary-cap]');
        if (kesCapEl) { kesCapEl.value = row.bpjsKesSalaryCap != null ? String(row.bpjsKesSalaryCap) : ''; }

        hideError(q('[data-bpjs-baseline-error]'));

        window.bootstrap.Modal.getOrCreateInstance(modalEl).show();
    }

    function bindRateBaselineActions() {
        var refreshBtn = q('[data-bpjs-baseline-refresh]');
        if (refreshBtn) {
            refreshBtn.addEventListener('click', function () { loadRateBaselines(); });
        }

        var tableBody = q('[data-bpjs-baseline-table-body]');
        if (tableBody) {
            tableBody.addEventListener('click', function (event) {
                var btn = event.target.closest('[data-bpjs-baseline-edit]');
                if (!btn) { return; }
                var program = btn.getAttribute('data-program');
                var party = btn.getAttribute('data-party');
                var row = rateBaselineCache.find(function (item) {
                    return item.programCode === program && item.contributionParty === party;
                });
                if (row) { openRateBaselineModal(row); }
            });
        }

        var form = q('[data-bpjs-baseline-form]');
        if (form) {
            form.addEventListener('submit', function (event) {
                event.preventDefault();
                var program = q('[data-bpjs-baseline-program]').value;
                var party = q('[data-bpjs-baseline-party]').value;
                if (!program || !party) { return; }

                var riskCatEl = q('[data-bpjs-baseline-risk-category]');
                var jpCapEl = q('[data-bpjs-baseline-jp-salary-cap]');
                var kesCapEl = q('[data-bpjs-baseline-kes-salary-cap]');

                var payload = {
                    minRate: Number(q('[data-bpjs-baseline-min-rate]').value || 0),
                    maxRate: Number(q('[data-bpjs-baseline-max-rate]').value || 0),
                    notes: q('[data-bpjs-baseline-notes]').value || null,
                    riskCategory: (riskCatEl && riskCatEl.value) ? Number(riskCatEl.value) : null,
                    jpSalaryCap: (jpCapEl && jpCapEl.value !== '') ? Number(jpCapEl.value) : null,
                    bpjsKesSalaryCap: (kesCapEl && kesCapEl.value !== '') ? Number(kesCapEl.value) : null,
                };

                var spinner = q('[data-bpjs-baseline-spinner]');
                var submitBtn = q('[data-bpjs-baseline-submit]');
                var errorBox = q('[data-bpjs-baseline-error]');
                hideError(errorBox);
                if (spinner) { spinner.classList.remove('d-none'); }
                if (submitBtn) { submitBtn.disabled = true; }

                apiPut('/hcm/bpjs-governance/rate-baselines/' + program + '/' + party, payload)
                    .then(function () {
                        var modalEl = document.getElementById('bpjsRateBaselineModal');
                        if (modalEl && window.bootstrap && window.bootstrap.Modal) {
                            var modal = window.bootstrap.Modal.getInstance(modalEl);
                            if (modal) { modal.hide(); }
                        }
                        loadRateBaselines();
                    })
                    .catch(function (err) {
                        showError(errorBox, err && err.message ? err.message : 'Gagal menyimpan konfigurasi baseline.');
                    })
                    .finally(function () {
                        if (spinner) { spinner.classList.add('d-none'); }
                        if (submitBtn) { submitBtn.disabled = false; }
                    });
            });
        }
    }



    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})(window, document);
