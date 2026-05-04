(function (window, document) {
    'use strict';

    var state = {
        policies: [],
        assignments: [],
    };

    function q(selector, root) {
        return (root || document).querySelector(selector);
    }

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
    function apiPatch(path, payload) { return apiRequest('PATCH', path, payload); }

    function showError(message) {
        var el = q('[data-allowance-error]');
        if (!el) { return; }
        el.textContent = String(message || 'Terjadi kesalahan.');
        el.classList.remove('d-none');
    }

    function hideError() {
        var el = q('[data-allowance-error]');
        if (!el) { return; }
        el.textContent = '';
        el.classList.add('d-none');
    }

    function unwrapData(res) {
        if (!res) { return {}; }
        return res.data ? res.data : res;
    }

    function escapeHtml(value) {
        return String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function amountLabel(value) {
        var numeric = Number(value || 0);
        if (!isFinite(numeric)) { return '-'; }
        return 'Rp ' + numeric.toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function boolBadge(value, trueLabel, falseLabel) {
        if (value) {
            return '<span class="badge bg-success-subtle text-success">' + escapeHtml(trueLabel || 'Ya') + '</span>';
        }
        return '<span class="badge bg-secondary-subtle text-secondary">' + escapeHtml(falseLabel || 'Tidak') + '</span>';
    }

    function statusBadge(value) {
        var normalized = String(value || '').toLowerCase();
        if (normalized === 'active') {
            return '<span class="badge bg-success-subtle text-success">Active</span>';
        }
        if (normalized === 'draft') {
            return '<span class="badge bg-warning-subtle text-warning">Draft</span>';
        }
        if (normalized === 'suspended' || normalized === 'superseded') {
            return '<span class="badge bg-danger-subtle text-danger">' + escapeHtml(value) + '</span>';
        }
        return '<span class="badge bg-secondary-subtle text-secondary">' + escapeHtml(value || 'Unknown') + '</span>';
    }

    function getScreen() {
        var root = q('[data-allowance-governance-page]');
        return root ? (root.getAttribute('data-allowance-screen') || 'landing') : '';
    }

    function openModal(modalId) {
        var el = q(modalId);
        if (!el || !window.bootstrap || !window.bootstrap.Modal) { return null; }
        var instance = window.bootstrap.Modal.getOrCreateInstance(el);
        instance.show();
        return instance;
    }

    function closeModal(modalId) {
        var el = q(modalId);
        if (!el || !window.bootstrap || !window.bootstrap.Modal) { return; }
        var instance = window.bootstrap.Modal.getOrCreateInstance(el);
        instance.hide();
    }

    function renderPolicyRows(items) {
        var body = q('[data-allowance-policy-body]');
        if (!body) { return; }

        if (!items.length) {
            body.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-4">Belum ada policy allowance.</td></tr>';
            return;
        }

        body.innerHTML = items.map(function (row) {
            return '<tr>'
                + '<td><code>' + escapeHtml(row.code) + '</code></td>'
                + '<td>' + escapeHtml(row.name) + '</td>'
                + '<td>' + boolBadge(!!row.isMandatory, 'Mandatory', 'Opsional') + '</td>'
                + '<td>' + boolBadge(!!row.isTaxable, 'Taxable', 'Non-taxable') + '</td>'
                + '<td>' + escapeHtml(amountLabel(row.defaultAmount)) + '</td>'
                + '<td>' + statusBadge(row.status) + '</td>'
                + '<td class="d-flex gap-2">'
                + '<button type="button" class="btn btn-sm btn-outline-primary" data-allowance-policy-edit data-id="' + escapeHtml(String(row.id)) + '">Edit</button>'
                + '<button type="button" class="btn btn-sm btn-outline-success" data-allowance-policy-activate data-id="' + escapeHtml(String(row.id)) + '">Activate</button>'
                + '</td>'
                + '</tr>';
        }).join('');
    }

    function renderPolicyHistory(items) {
        var body = q('[data-allowance-policy-history-body]');
        if (!body) { return; }

        if (!items.length) {
            body.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-4">Belum ada riwayat.</td></tr>';
            return;
        }

        body.innerHTML = items.map(function (row) {
            return '<tr>'
                + '<td>' + escapeHtml(row.changedAt || '-') + '</td>'
                + '<td>' + escapeHtml(row.actionType || '-') + '</td>'
                + '<td>' + escapeHtml(row.name || row.code || '-') + '</td>'
                + '<td>' + statusBadge(row.status) + '</td>'
                + '<td>' + escapeHtml(row.changedByUserName || row.changedByUserEmail || 'System') + '</td>'
                + '</tr>';
        }).join('');
    }

    function fillPolicyOptions() {
        var select = q('[data-allowance-assignment-policy]');
        if (!select) { return; }

        select.innerHTML = state.policies
            .filter(function (row) { return row.isActive; })
            .map(function (row) {
                return '<option value="' + escapeHtml(String(row.uuid || row.id)) + '">' + escapeHtml(row.name + ' (' + row.code + ')') + '</option>';
            }).join('');
    }

    function renderAssignmentRows(items, compensationAllowances) {
        var body = q('[data-allowance-assignment-body]');
        if (!body) { return; }

        var compItems = Array.isArray(compensationAllowances) ? compensationAllowances : [];
        var hasAssignments = items.length > 0;
        var hasCompItems = compItems.length > 0;

        if (!hasAssignments && !hasCompItems) {
            body.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-4">Belum ada assignment allowance.</td></tr>';
            return;
        }

        var rows = '';

        if (hasAssignments) {
            rows += items.map(function (row) {
                var displayAmount = row.amountOverride ? amountLabel(row.amountOverride) : (row.amount ? amountLabel(row.amount) : '-');
                return '<tr>'
                    + '<td><div class="fw-semibold">' + escapeHtml(row.fullName || '-') + '</div><span class="text-muted small">' + escapeHtml(row.email || '-') + '</span></td>'
                    + '<td>' + escapeHtml(row.policyName || '-') + '</td>'
                    + '<td>' + escapeHtml(displayAmount) + '</td>'
                    + '<td>' + escapeHtml((row.effectiveStartDate || '-') + ' - ' + (row.effectiveEndDate || '...')) + '</td>'
                    + '<td>' + statusBadge(row.status) + '</td>'
                    + '</tr>';
            }).join('');
        }

        if (hasCompItems) {
            // Separator row
            rows += '<tr class="table-light"><td colspan="5" class="small fw-semibold text-muted py-2 ps-3">'
                + '<i class="ti ti-lock me-1"></i>Tunjangan Tetap dari Kompensasi Karyawan (read-only, dikelola via halaman Salary karyawan)'
                + '</td></tr>';

            rows += compItems.map(function (row) {
                return '<tr class="table-light">'
                    + '<td><div class="fw-semibold">' + escapeHtml(row.fullName || '-') + '</div><span class="text-muted small">' + escapeHtml(row.email || '-') + '</span></td>'
                    + '<td>' + escapeHtml(row.policyName || '-') + ' <span class="badge bg-secondary-subtle text-secondary ms-1">Kompensasi</span></td>'
                    + '<td>' + escapeHtml(row.amount ? amountLabel(row.amount) : '-') + '</td>'
                    + '<td>' + escapeHtml((row.effectiveStartDate || '-') + ' - ' + (row.effectiveEndDate || '...')) + '</td>'
                    + '<td><span class="badge bg-success-subtle text-success">Active</span> <span class="badge bg-light text-muted border">Read-only</span></td>'
                    + '</tr>';
            }).join('');
        }

        body.innerHTML = rows;
    }

    function renderReport(data) {
        var body = q('[data-allowance-report-checks]');
        if (!body) { return; }

        var checks = Array.isArray(data.checks) ? data.checks : [];

        if (!checks.length) {
            body.innerHTML = '<tr><td colspan="3" class="text-center text-muted py-4">Belum ada data compliance.</td></tr>';
            return;
        }

        body.innerHTML = checks.map(function (check) {
            var evidence = check.evidence || {};
            var parts = [];
            if (evidence.activePolicyCount != null) {
                parts.push('<span class="d-block"><i class="ti ti-file-check text-primary me-1"></i>' + evidence.activePolicyCount + ' kebijakan aktif</span>');
            }
            if (evidence.nonCompliantCount != null) {
                var cls = evidence.nonCompliantCount > 0 ? 'text-danger' : 'text-success';
                parts.push('<span class="d-block ' + cls + '"><i class="ti ti-users me-1"></i>' + evidence.nonCompliantCount + ' karyawan belum comply</span>');
            }
            if (evidence.overlapCount != null) {
                var ovCls = evidence.overlapCount > 0 ? 'text-warning' : 'text-success';
                parts.push('<span class="d-block ' + ovCls + '"><i class="ti ti-git-merge me-1"></i>' + evidence.overlapCount + ' tumpang tindih terdeteksi</span>');
            }
            if (evidence.mandatoryPolicyCount != null) {
                parts.push('<span class="d-block text-muted"><i class="ti ti-lock me-1"></i>' + evidence.mandatoryPolicyCount + ' kebijakan wajib</span>');
            }
            var evidenceHtml = parts.length ? parts.join('') : '<span class="text-muted">-</span>';
            return '<tr>'
                + '<td>' + escapeHtml(check.label || check.code || '-') + '</td>'
                + '<td>' + boolBadge(!!check.pass, 'Pass', 'Gap') + '</td>'
                + '<td class="small">' + evidenceHtml + '</td>'
                + '</tr>';
        }).join('');

        var kpiPolicy = q('[data-allowance-kpi-policy]');
        var kpiEmployees = q('[data-allowance-kpi-employees]');
        var kpiScore = q('[data-allowance-kpi-score]');
        if (kpiPolicy) { kpiPolicy.textContent = String(data.activePolicyCount || 0); }
        if (kpiEmployees) { kpiEmployees.textContent = String(data.employeeScopeCount || 0); }
        if (kpiScore) { kpiScore.textContent = String(data.score || 0) + '%'; }
    }

    function loadPolicies() {
        return apiGet('/hcm/allowance-governance/policies', { active_only: 0 }).then(function (res) {
            var data = unwrapData(res);
            var items = Array.isArray(data.items) ? data.items : [];
            state.policies = items;
            renderPolicyRows(items);
            fillPolicyOptions();
            return items;
        });
    }

    function loadPolicyHistory() {
        return apiGet('/hcm/allowance-governance/policies/history', { limit: 50 }).then(function (res) {
            var data = unwrapData(res);
            renderPolicyHistory(Array.isArray(data.items) ? data.items : []);
        });
    }

    function loadAssignments() {
        var search = q('[data-allowance-assignment-search]');
        var params = { page: 1, perPage: 100 };
        if (search && search.value.trim()) {
            params.search = search.value.trim();
        }
        return apiGet('/hcm/allowance-governance/assignments', params).then(function (res) {
            var data = unwrapData(res);
            var items = Array.isArray(data.items) ? data.items : [];
            var compensationAllowances = Array.isArray(data.compensationAllowances) ? data.compensationAllowances : [];
            state.assignments = items;
            state.compensationAllowances = compensationAllowances;
            renderAssignmentRows(items, compensationAllowances);
            return items;
        });
    }

    function loadReport() {
        var body = q('[data-allowance-report-checks]');
        if (body) {
            body.innerHTML = '<tr><td colspan="3" class="text-center text-muted py-4">Memuat laporan compliance...</td></tr>';
        }
        return apiGet('/hcm/allowance-governance/reports/compliance').then(function (res) {
            var data = unwrapData(res);
            renderReport(data);
        });
    }

    function syncPolicyModal(row) {
        q('[data-allowance-policy-id]').value = row && row.uuid ? row.uuid : '';
        q('[data-allowance-policy-code]').value = row && row.code ? row.code : '';
        q('[data-allowance-policy-name]').value = row && row.name ? row.name : '';
        q('[data-allowance-policy-default-amount]').value = row && row.defaultAmount ? row.defaultAmount : '0.00';
        q('[data-allowance-policy-start]').value = row && row.effectiveStartDate ? row.effectiveStartDate : '';
        q('[data-allowance-policy-end]').value = row && row.effectiveEndDate ? row.effectiveEndDate : '';
        q('[data-allowance-policy-mandatory]').value = row && row.isMandatory ? '1' : '0';
        q('[data-allowance-policy-taxable]').value = row && row.isTaxable ? '1' : '0';
        q('[data-allowance-policy-status]').value = row && row.status ? row.status : 'active';
        q('[data-allowance-policy-notes]').value = row && row.notes ? row.notes : '';
        var title = q('[data-allowance-policy-modal-title]');
        if (title) {
            title.textContent = row ? 'Edit Allowance Policy' : 'Tambah Allowance Policy';
        }
    }

    function submitPolicyForm(event) {
        event.preventDefault();
        hideError();

        var policyRef = q('[data-allowance-policy-id]').value.trim();
        var payload = {
            code: q('[data-allowance-policy-code]').value.trim(),
            name: q('[data-allowance-policy-name]').value.trim(),
            defaultAmount: q('[data-allowance-policy-default-amount]').value || 0,
            effectiveStartDate: q('[data-allowance-policy-start]').value,
            effectiveEndDate: q('[data-allowance-policy-end]').value || null,
            isMandatory: q('[data-allowance-policy-mandatory]').value === '1',
            isTaxable: q('[data-allowance-policy-taxable]').value === '1',
            status: q('[data-allowance-policy-status]').value,
            notes: q('[data-allowance-policy-notes]').value || null,
        };

        var action = policyRef
            ? apiPatch('/hcm/allowance-governance/policies/' + encodeURIComponent(policyRef), payload)
            : apiPost('/hcm/allowance-governance/policies', payload);

        action
            .then(function () {
                closeModal('#allowancePolicyModal');
                return Promise.all([loadPolicies(), loadPolicyHistory(), loadReport()]);
            })
            .catch(function (err) {
                showError((err && err.message) || 'Gagal menyimpan policy allowance.');
            });
    }

    function syncAssignmentModal(row) {
        q('[data-allowance-assignment-id]').value = row && row.uuid ? row.uuid : '';
        q('[data-allowance-assignment-policy]').value = row && row.policyUuid ? row.policyUuid : '';
        q('[data-allowance-assignment-user-id]').value = row && row.userId ? String(row.userId) : '';
        q('[data-allowance-assignment-amount]').value = row && row.amountOverride ? row.amountOverride : '';
        q('[data-allowance-assignment-start]').value = row && row.effectiveStartDate ? row.effectiveStartDate : '';
        q('[data-allowance-assignment-end]').value = row && row.effectiveEndDate ? row.effectiveEndDate : '';
        q('[data-allowance-assignment-status]').value = row && row.status ? row.status : 'active';
        q('[data-allowance-assignment-notes]').value = row && row.notes ? row.notes : '';
        var title = q('[data-allowance-assignment-modal-title]');
        if (title) {
            title.textContent = row ? 'Edit Assignment' : 'Tambah Assignment';
        }
    }

    function submitAssignmentForm(event) {
        event.preventDefault();
        hideError();

        var assignmentRef = q('[data-allowance-assignment-id]').value.trim();
        var payload = {
            policyRef: q('[data-allowance-assignment-policy]').value,
            userId: Number(q('[data-allowance-assignment-user-id]').value || 0),
            amountOverride: q('[data-allowance-assignment-amount]').value || null,
            effectiveStartDate: q('[data-allowance-assignment-start]').value,
            effectiveEndDate: q('[data-allowance-assignment-end]').value || null,
            status: q('[data-allowance-assignment-status]').value,
            notes: q('[data-allowance-assignment-notes]').value || null,
        };

        var action = assignmentRef
            ? apiPatch('/hcm/allowance-governance/assignments/' + encodeURIComponent(assignmentRef), payload)
            : apiPost('/hcm/allowance-governance/assignments', payload);

        action
            .then(function () {
                closeModal('#allowanceAssignmentModal');
                return Promise.all([loadAssignments(), loadReport()]);
            })
            .catch(function (err) {
                showError((err && err.message) || 'Gagal menyimpan assignment allowance.');
            });
    }

    function bindPolicyActions() {
        document.addEventListener('click', function (event) {
            var editButton = event.target.closest('[data-allowance-policy-edit]');
            if (editButton) {
                var id = editButton.getAttribute('data-id');
                var row = state.policies.find(function (item) { return String(item.id) === String(id); });
                syncPolicyModal(row || null);
                openModal('#allowancePolicyModal');
                return;
            }

            var activateButton = event.target.closest('[data-allowance-policy-activate]');
            if (activateButton) {
                var activeId = activateButton.getAttribute('data-id');
                var activeRow = state.policies.find(function (item) { return String(item.id) === String(activeId); });
                if (!activeRow) { return; }
                apiPost('/hcm/allowance-governance/policies/' + encodeURIComponent(activeRow.uuid || activeRow.id) + '/activate', {})
                    .then(function () { return Promise.all([loadPolicies(), loadPolicyHistory(), loadReport()]); })
                    .catch(function (err) { showError((err && err.message) || 'Gagal aktivasi policy.'); });
                return;
            }

            var addPolicy = event.target.closest('[data-allowance-policy-add]');
            if (addPolicy) {
                syncPolicyModal(null);
                openModal('#allowancePolicyModal');
                return;
            }

            if (event.target.closest('[data-allowance-policy-refresh]')) {
                Promise.all([loadPolicies(), loadPolicyHistory(), loadReport()]).catch(function (err) {
                    showError((err && err.message) || 'Gagal refresh policy.');
                });
                return;
            }

            if (event.target.closest('[data-allowance-assignment-refresh]')) {
                Promise.all([loadAssignments(), loadReport()]).catch(function (err) {
                    showError((err && err.message) || 'Gagal refresh assignment.');
                });
                return;
            }

            if (event.target.closest('[data-allowance-report-refresh]')) {
                loadReport().catch(function (err) {
                    showError((err && err.message) || 'Gagal refresh report.');
                });
                return;
            }

            if (event.target.closest('[data-allowance-report-export]')) {
                window.open('/v1/hcm/allowance-governance/reports/compliance/export', '_blank');
            }
        });
    }

    function initForms() {
        var policyForm = q('[data-allowance-policy-form]');
        if (policyForm) {
            policyForm.addEventListener('submit', submitPolicyForm);
        }

        var assignmentSearch = q('[data-allowance-assignment-search]');
        if (assignmentSearch) {
            assignmentSearch.addEventListener('change', function () {
                loadAssignments().catch(function (err) {
                    showError((err && err.message) || 'Gagal memuat assignment.');
                });
            });
        }
    }

    function bootstrap() {
        var screen = getScreen();
        if (!screen) {
            return;
        }

        hideError();
        bindPolicyActions();
        initForms();

        if (screen === 'landing') {
            Promise.all([loadPolicies(), loadReport()]).catch(function (err) {
                showError((err && err.message) || 'Gagal memuat data allowance governance.');
            });
            return;
        }

        if (screen === 'policies') {
            Promise.all([loadPolicies(), loadPolicyHistory(), loadReport()]).catch(function (err) {
                showError((err && err.message) || 'Gagal memuat policy allowance.');
            });
            return;
        }

        if (screen === 'assignments') {
            Promise.all([loadPolicies(), loadAssignments(), loadReport()]).catch(function (err) {
                showError((err && err.message) || 'Gagal memuat assignment allowance.');
            });
            return;
        }

        if (screen === 'reports') {
            loadReport().catch(function (err) {
                showError((err && err.message) || 'Gagal memuat laporan allowance.');
            });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bootstrap);
    } else {
        bootstrap();
    }
})(window, document);
