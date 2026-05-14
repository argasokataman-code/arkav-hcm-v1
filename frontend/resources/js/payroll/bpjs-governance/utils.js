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

    window.ArcavBpjsGovernanceUtils = {
        q: q,
        apiRequest: apiRequest,
        apiGet: apiGet,
        apiPost: apiPost,
        apiPut: apiPut,
        apiDelete: apiDelete,
        escapeHtml: escapeHtml,
        unwrapData: unwrapData,
        boolBadge: boolBadge,
        membershipBadge: membershipBadge,
        basisLabel: basisLabel,
        programLabel: programLabel,
        partyLabel: partyLabel,
        formatPercent: formatPercent,
        formatDateTime: formatDateTime,
        actionLabel: actionLabel,
        showError: showError,
        hideError: hideError,
        getScreen: getScreen,
        formatRupiah: formatRupiah,
        salaryCapLabel: salaryCapLabel,
    };
})(window, document);
