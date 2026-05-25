import { requestJson } from './api.js';
import { formatApiError } from './helpers.js';
import * as State from './state.js';
import * as Org from './org.js';

export function makeBulkHandlers(deps) {
    function getBulkReassignModalInstance() {
        var modalEl = document.getElementById('employee_bulk_team_reassign');
        if (!modalEl || !window.bootstrap || !window.bootstrap.Modal) {
            return null;
        }
        return window.bootstrap.Modal.getOrCreateInstance(modalEl);
    }

    function fillBulkTargetTeamOptions() {
        var select = document.querySelector('[data-employees-bulk-target-team]');
        if (!select) return;
        var previous = String(select.value || '');
        select.innerHTML = '<option value="">Select target team</option><option value="__UNASSIGN__">Unassign Team</option>';
        (Org.orgTeamsFlat || []).forEach(function (team) {
            if (!team || !team.is_active) return;
            var opt = document.createElement('option');
            opt.value = String(team.id);
            opt.textContent = team.name || String(team.id);
            select.appendChild(opt);
        });
        if (previous) {
            var exists = Array.prototype.slice.call(select.options).some(function (o) { return o.value === previous; });
            if (exists) select.value = previous;
        }
    }

    function renderBulkReassignResult(level, message) {
        var box = document.querySelector('[data-employees-bulk-reassign-result]');
        if (!box) return;
        if (!message) {
            box.className = 'alert d-none mb-0';
            box.textContent = '';
            return;
        }
        box.className = 'alert mb-0 alert-' + (level || 'info');
        box.textContent = message;
    }

    function resolveBulkSourceTeamGuard(selectedIds) {
        var rows = Array.prototype.slice.call(document.querySelectorAll('[data-employee-profile-id]'))
            .filter(function (row) {
                var employeeProfileId = String(row.getAttribute('data-employee-profile-id') || '');
                return employeeProfileId && State.selectedEmployeeProfilesMap[employeeProfileId];
            });

        if (!rows.length || rows.length !== selectedIds.length) {
            return {
                ok: false,
                message: 'Untuk menjaga akurasi bulk reassign, pilih ulang employee dari halaman list yang sama.',
            };
        }

        var sourceTeamKeys = rows.map(function (row) { return String(row.getAttribute('data-employee-team-id') || ''); })
            .filter(function (value, index, values) { return values.indexOf(value) === index; });

        if (sourceTeamKeys.length > 1) {
            return { ok: true, sourceTeamId: null };
        }

        if (!sourceTeamKeys.length || sourceTeamKeys[0] === '') {
            return { ok: true, sourceTeamId: null };
        }

        var sourceTeamId = Number(sourceTeamKeys[0]);
        if (!Number.isFinite(sourceTeamId) || sourceTeamId <= 0) {
            return { ok: false, message: 'Source team pada selection tidak valid.' };
        }

        return { ok: true, sourceTeamId: sourceTeamId };
    }

    function submitBulkTeamReassign() {
        var selectedIds = (typeof deps.getSelectedEmployeeProfileIds === 'function') ? deps.getSelectedEmployeeProfileIds() : [];
        if (!selectedIds.length) {
            renderBulkReassignResult('warning', 'Pilih minimal 1 employee.');
            return Promise.resolve();
        }

        var select = document.querySelector('[data-employees-bulk-target-team]');
        var submitBtn = document.querySelector('[data-employees-bulk-submit]');
        var rawValue = select ? String(select.value || '') : '';
        if (!rawValue) {
            renderBulkReassignResult('warning', 'Target team wajib dipilih.');
            return Promise.resolve();
        }

        var targetTeamId = rawValue === '__UNASSIGN__' ? null : Number(rawValue);
        if (targetTeamId !== null && (!Number.isFinite(targetTeamId) || targetTeamId <= 0)) {
            renderBulkReassignResult('danger', 'Target team tidak valid.');
            return Promise.resolve();
        }

        var sourceGuard = resolveBulkSourceTeamGuard(selectedIds);
        if (!sourceGuard.ok) {
            renderBulkReassignResult('warning', sourceGuard.message);
            return Promise.resolve();
        }

        if (submitBtn) submitBtn.disabled = true;
        renderBulkReassignResult('info', 'Processing reassign...');

        return requestJson('post', '/v1/hcm/teams/reassign-members', {
            employee_ids: selectedIds,
            source_team_id: sourceGuard.sourceTeamId,
            target_team_id: targetTeamId,
        }).then(function (payload) {
            if (!payload || payload.success !== true) {
                var msg = formatApiError(payload, 0) || 'Bulk reassign gagal diproses.';
                renderBulkReassignResult('danger', msg);
                return;
            }
            var affected = Number(payload.data && payload.data.affected_count ? payload.data.affected_count : 0);
            if (window.ArcavUi && typeof window.ArcavUi.showToast === 'function') {
                window.ArcavUi.showToast('Bulk team reassign berhasil. Employee terupdate: ' + affected + '.', 'success');
            }
            renderBulkReassignResult('success', 'Berhasil memproses ' + affected + ' employee.');
            if (typeof deps.clearSelectedEmployeesSelection === 'function') deps.clearSelectedEmployeesSelection();
            var modal = getBulkReassignModalInstance();
            if (modal) modal.hide();
            if (typeof deps.loadEmployeesData === 'function') deps.loadEmployeesData();
        }).catch(function (error) {
            if (error && window.AuthApi && window.AuthApi.handleUnauthorizedFromApi(error.status, error.data)) {
                return Promise.resolve();
            }
            var msg = formatApiError(error && error.data, error && error.status) || 'Bulk reassign gagal.';
            renderBulkReassignResult('danger', msg);
        }).finally(function () {
            if (submitBtn) submitBtn.disabled = false;
        });
    }

    return {
        getBulkReassignModalInstance: getBulkReassignModalInstance,
        fillBulkTargetTeamOptions: fillBulkTargetTeamOptions,
        renderBulkReassignResult: renderBulkReassignResult,
        resolveBulkSourceTeamGuard: resolveBulkSourceTeamGuard,
        submitBulkTeamReassign: submitBulkTeamReassign,
    };
}

export default { makeBulkHandlers };
