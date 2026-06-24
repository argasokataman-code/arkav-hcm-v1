(function () {
    'use strict';

    var root = document.querySelector('[data-log-viewer-page]');
    if (!root) return;

    var feedback = document.querySelector('[data-log-viewer-feedback]');
    var refreshBtn = document.querySelector('[data-log-viewer-refresh]');
    var fileList = document.querySelector('[data-log-viewer-file-list]');
    var fileEmpty = document.querySelector('[data-log-viewer-file-empty]');
    var fileTitle = document.querySelector('[data-log-viewer-file-title]');
    var fileMeta = document.querySelector('[data-log-viewer-file-meta]');
    var entriesTable = document.querySelector('[data-log-viewer-entries-table]');
    var entriesBody = document.querySelector('[data-log-viewer-entries-body]');
    var entriesEmpty = document.querySelector('[data-log-viewer-entries-empty]');
    var pagination = document.querySelector('[data-log-viewer-pagination]');
    var paginationInfo = document.querySelector('[data-log-viewer-pagination-info]');
    var prevBtn = document.querySelector('[data-log-viewer-prev]');
    var nextBtn = document.querySelector('[data-log-viewer-next]');
    var modalTimestamp = document.querySelector('[data-log-entry-timestamp]');
    var modalLevel = document.querySelector('[data-log-entry-level]');
    var modalEnv = document.querySelector('[data-log-entry-env]');
    var modalMessage = document.querySelector('[data-log-entry-message]');

    var state = { files: [], currentFile: null, currentPage: 1, totalEntries: 0 };

    function showFeedback(type, msg) {
        if (!feedback) return;
        feedback.className = 'alert alert-' + type + ' mb-3';
        feedback.textContent = msg;
        feedback.classList.remove('d-none');
    }

    function clearFeedback() {
        if (!feedback) return;
        feedback.textContent = '';
        feedback.classList.add('d-none');
    }

    function getToken() {
        try {
            if (window.AuthApi && typeof window.AuthApi.getToken === 'function') {
                var t = window.AuthApi.getToken();
                if (t) return t;
            }
        } catch (_e) {}
        return window.localStorage.getItem('arcav_access_token')
            || window.sessionStorage.getItem('arcav_access_token')
            || ((document.querySelector('meta[name="api-token"]') || {}).content)
            || null;
    }

    function getHeaders() {
        var h = { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' };
        var token = getToken();
        if (token) h['Authorization'] = 'Bearer ' + token;
        var csrf = document.querySelector('meta[name="csrf-token"]');
        if (csrf && csrf.content) h['X-CSRF-TOKEN'] = csrf.content;
        return h;
    }

    async function apiGet(url) {
        var r = await fetch(url, { method: 'GET', headers: getHeaders(), credentials: 'same-origin' });
        var d = await r.json().catch(function () { return { success: false, error: { message: 'Invalid JSON' } }; });
        if (!r.ok || !d.success) throw new Error((d.error && d.error.message) || 'Request failed (' + r.status + ')');
        return d;
    }

    function levelBadge(level) {
        var map = { ERROR: 'danger', CRITICAL: 'danger', ALERT: 'danger', WARNING: 'warning', NOTICE: 'info', INFO: 'info', DEBUG: 'secondary' };
        return '<span class="badge bg-' + (map[level.toUpperCase()] || 'secondary') + '">' + level + '</span>';
    }

    function renderFileList() {
        if (!fileList) return;
        fileList.innerHTML = '';
        if (state.files.length === 0) {
            if (fileEmpty) fileEmpty.classList.remove('d-none');
            return;
        }
        if (fileEmpty) fileEmpty.classList.add('d-none');
        state.files.forEach(function (f) {
            var a = document.createElement('a');
            a.href = '#';
            a.className = 'list-group-item list-group-item-action d-flex justify-content-between align-items-center' + (state.currentFile === f.name ? ' active' : '');
            a.setAttribute('data-log-file', f.name);
            a.innerHTML = '<span class="small">' + f.name + '</span><small class="text-muted">' + f.sizeLabel + '</small>';
            a.addEventListener('click', function (e) {
                e.preventDefault();
                loadFile(f.name);
            });
            fileList.appendChild(a);
        });
    }

    function renderEntries() {
        var showEntries = entriesTable && entriesBody && pagination && paginationInfo;
        if (!showEntries) return;

        // data will be injected by loadFile callback
    }

    async function loadFiles() {
        clearFeedback();
        try {
            var r = await apiGet('/v1/hcm/log-viewer/files');
            state.files = r.data || [];
            renderFileList();
        } catch (e) {
            showFeedback('danger', e.message || 'Gagal memuat daftar file.');
        }
    }

    async function loadFile(name) {
        clearFeedback();
        state.currentFile = name;
        state.currentPage = 1;
        renderFileList();
        await fetchPage();
    }

    async function fetchPage() {
        if (!state.currentFile) return;
        clearFeedback();
        try {
            var r = await apiGet('/v1/hcm/log-viewer/files/' + encodeURIComponent(state.currentFile) + '?page=' + state.currentPage + '&perPage=100');
            var d = r.data;
            state.totalEntries = d.total;

            if (fileTitle) fileTitle.textContent = d.name + (d.tail ? ' (tail 5MB)' : '');
            if (fileMeta) fileMeta.textContent = d.sizeLabel + ' | ' + d.total + ' entri';

            if (entriesEmpty) entriesEmpty.classList.add('d-none');
            if (entriesTable) entriesTable.classList.remove('d-none');
            if (pagination) pagination.classList.remove('d-none');
            if (entriesBody) {
                entriesBody.innerHTML = '';
                (d.entries || []).forEach(function (e) {
                    var tr = document.createElement('tr');
                    tr.style.cursor = 'pointer';
                    tr.innerHTML = '<td class="small text-nowrap">' + e.timestamp + '</td><td>' + levelBadge(e.level) + '</td><td class="small text-muted">' + escHtml(e.message.substring(0, 200)) + (e.message.length > 200 ? '...' : '') + '</td>';
                    tr.addEventListener('click', function () { openDetail(e); });
                    entriesBody.appendChild(tr);
                });
            }

            var totalPages = Math.max(1, Math.ceil(d.total / d.perPage));
            if (paginationInfo) paginationInfo.textContent = 'Halaman ' + d.page + ' dari ' + totalPages + ' (' + d.total + ' entri)';
            if (prevBtn) prevBtn.disabled = d.page <= 1;
            if (nextBtn) nextBtn.disabled = d.page >= totalPages;
        } catch (e) {
            showFeedback('danger', e.message || 'Gagal memuat entri log.');
        }
    }

    function escHtml(s) {
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(s || ''));
        return div.innerHTML;
    }

    function openDetail(entry) {
        if (modalTimestamp) modalTimestamp.textContent = entry.timestamp;
        if (modalLevel) { modalLevel.innerHTML = levelBadge(entry.level); }
        if (modalEnv) modalEnv.textContent = entry.env;
        if (modalMessage) modalMessage.textContent = entry.message;
        var modalEl = document.getElementById('logEntryModal');
        if (modalEl && window.bootstrap && window.bootstrap.Modal) {
            window.bootstrap.Modal.getOrCreateInstance(modalEl).show();
        }
    }

    function bindEvents() {
        if (refreshBtn) {
            refreshBtn.addEventListener('click', function () { loadFiles(); });
        }
        if (prevBtn) {
            prevBtn.addEventListener('click', function () {
                if (state.currentPage > 1) { state.currentPage--; fetchPage(); }
            });
        }
        if (nextBtn) {
            nextBtn.addEventListener('click', function () {
                state.currentPage++; fetchPage();
            });
        }
    }

    loadFiles();
    bindEvents();
})();
