/**
 * SPT Masa PPh 21 — frontend module (static-copied, not bundled)
 * Handles list page and detail page via data-spt-masa-screen attribute.
 */
(function (window, document) {
    'use strict';

    // ─────────────────────────────────────────────────────────────────────────
    // Utilities
    // ─────────────────────────────────────────────────────────────────────────

    function escapeHtml(str) {
        if (str === null || str === undefined) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function formatMoney(value) {
        if (value === null || value === undefined || value === '') return '—';
        return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(value);
    }

    function statusBadge(status) {
        const map = {
            draft: ['bg-secondary-subtle text-secondary', 'Draft'],
            ready: ['bg-warning-subtle text-warning', 'Ready'],
            submitted: ['bg-success-subtle text-success', 'Submitted'],
        };
        const [cls, label] = map[status] || ['bg-secondary-subtle text-secondary', escapeHtml(status)];
        return `<span class="badge ${cls}">${label}</span>`;
    }

    function apiRequest(method, path, payload) {
        if (!window.AuthApi || typeof window.AuthApi.request !== 'function') {
            return Promise.reject(new Error('AuthApi not available'));
        }
        return window.AuthApi.request(method, path, payload).then(function (response) {
            return response && response.data !== undefined ? response.data : response;
        }).catch(function (err) {
            // Extract the human-readable message from the API error envelope, if present.
            var body = err.response && err.response.data;
            var apiMsg = body && body.error && body.error.message;
            if (apiMsg) {
                var wrapped = new Error(apiMsg);
                wrapped.response = err.response;
                wrapped.apiCode = body.error.code || null;
                throw wrapped;
            }
            throw err;
        });
    }

    function apiGet(url) {
        return apiRequest('GET', url);
    }

    function apiPost(url, body) {
        return apiRequest('POST', url, body);
    }

    function showBanner(page, type, message) {
        const selector = type === 'error' ? '[data-spt-error]' : '[data-spt-success]';
        const hideSel = type === 'error' ? '[data-spt-success]' : '[data-spt-error]';
        const el = page.querySelector(selector);
        const other = page.querySelector(hideSel);
        if (other) { other.classList.add('d-none'); other.textContent = ''; }
        if (el) { el.textContent = message; el.classList.remove('d-none'); }
    }

    function clearBanners(page) {
        page.querySelectorAll('[data-spt-error],[data-spt-success]').forEach(function (el) {
            el.classList.add('d-none');
            el.textContent = '';
        });
    }

    function getModal(id) {
        const el = document.getElementById(id);
        if (!el) return null;
        const { Modal } = window.bootstrap || {};
        if (!Modal) return null;
        return Modal.getOrCreateInstance(el);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // List page
    // ─────────────────────────────────────────────────────────────────────────

    function initListPage(page) {
        var state = { page: 1, perPage: 20, periode: '', status: '' };

        function loadList() {
            var params = new URLSearchParams({ page: state.page, per_page: state.perPage });
            if (state.periode) params.set('periode', state.periode);
            if (state.status) params.set('status', state.status);

            var body = page.querySelector('[data-spt-list-body]');
            if (body) {
                body.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-4">' +
                    '<span class="spinner-border spinner-border-sm me-2" role="status"></span>Memuat data…</td></tr>';
            }

            apiGet('/hcm/spt-masa/headers?' + params.toString())
                .then(function (res) {
                    if (!res.success) throw new Error((res.error && res.error.message) || 'Gagal memuat data.');
                    renderList(page, res.data);
                })
                .catch(function (err) {
                    showBanner(page, 'error', err.message || 'Gagal memuat data.');
                    if (body) body.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-3">Gagal memuat data.</td></tr>';
                });
        }

        // Render rows
        function renderList(page, data) {
            var body = page.querySelector('[data-spt-list-body]');
            var items = (data && data.items) || [];
            if (!body) return;

            if (items.length === 0) {
                body.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-4">Belum ada data SPT Masa.</td></tr>';
                renderPagination(page, data && data.meta, null);
                return;
            }

            var rows = items.map(function (h) {
                var detailUrl = '/spt-masa-pph21/' + escapeHtml(h.uuid);
                var exportUrl = '/v1/hcm/spt-masa/headers/' + escapeHtml(h.uuid) + '/export.csv';
                return '<tr>' +
                    '<td>' + escapeHtml(h.periode) + '</td>' +
                    '<td>' + statusBadge(h.status) + '</td>' +
                    '<td>' + escapeHtml(h.totalKaryawan) + '</td>' +
                    '<td class="text-end">' + formatMoney(h.totalBruto) + '</td>' +
                    '<td class="text-end">' + formatMoney(h.totalPph21) + '</td>' +
                    '<td>' + escapeHtml(h.generatedAt ? h.generatedAt.substring(0, 10) : '—') + '</td>' +
                    '<td>' +
                    '<a href="' + detailUrl + '" class="btn btn-sm btn-outline-primary me-1"><i class="ti ti-eye me-1"></i>Detail</a>' +
                    '<a href="' + exportUrl + '" class="btn btn-sm btn-outline-secondary"><i class="ti ti-file-type-csv me-1"></i>CSV</a>' +
                    '</td>' +
                    '</tr>';
            });
            body.innerHTML = rows.join('');
            renderPagination(page, data && data.meta, loadList);
        }

        function renderPagination(page, meta, reloadFn) {
            var info = page.querySelector('[data-spt-pagination-info]');
            var controls = page.querySelector('[data-spt-pagination-controls]');
            if (!meta) return;
            var from = meta.total === 0 ? 0 : (meta.page - 1) * meta.perPage + 1;
            var to = Math.min(meta.page * meta.perPage, meta.total);
            if (info) info.textContent = 'Menampilkan ' + from + '–' + to + ' dari ' + meta.total + ' data';

            if (controls) {
                var totalPages = Math.ceil(meta.total / meta.perPage);
                var btns = '';
                if (meta.page > 1) btns += '<button class="btn btn-sm btn-outline-secondary" data-page="' + (meta.page - 1) + '">Prev</button>';
                btns += '<button class="btn btn-sm btn-outline-secondary" disabled>Hal. ' + meta.page + ' / ' + totalPages + '</button>';
                if (meta.page < totalPages) btns += '<button class="btn btn-sm btn-outline-secondary" data-page="' + (meta.page + 1) + '">Next</button>';
                controls.innerHTML = btns;
                controls.querySelectorAll('button[data-page]').forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        state.page = parseInt(btn.getAttribute('data-page'), 10);
                        reloadFn && reloadFn();
                    });
                });
            }
        }

        // Generate modal
        var generateBtn = page.querySelector('[data-spt-generate-btn]');
        if (generateBtn) {
            generateBtn.addEventListener('click', function () {
                var modal = getModal('sptGenerateModal');
                if (!modal) return;
                var modalEl = document.getElementById('sptGenerateModal');
                var errorEl = modalEl && modalEl.querySelector('[data-spt-modal-error]');
                if (errorEl) { errorEl.classList.add('d-none'); errorEl.textContent = ''; }
                var periodeInput = modalEl && modalEl.querySelector('[data-spt-modal-periode]');
                if (periodeInput) periodeInput.value = '';
                modal.show();
            });
        }

        var generateConfirm = document.querySelector('[data-spt-modal-generate-confirm]');
        if (generateConfirm) {
            generateConfirm.addEventListener('click', function () {
                var modalEl = document.getElementById('sptGenerateModal');
                var errorEl = modalEl && modalEl.querySelector('[data-spt-modal-error]');
                var periodeInput = modalEl && modalEl.querySelector('[data-spt-modal-periode]');
                var periode = (periodeInput && periodeInput.value) || '';

                if (!periode.match(/^\d{4}-\d{2}$/)) {
                    if (errorEl) { errorEl.textContent = 'Pilih periode yang valid (YYYY-MM).'; errorEl.classList.remove('d-none'); }
                    return;
                }
                if (errorEl) { errorEl.classList.add('d-none'); }

                generateConfirm.disabled = true;
                generateConfirm.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Generating…';

                apiPost('/hcm/spt-masa/headers', { periode: periode })
                    .then(function (res) {
                        generateConfirm.disabled = false;
                        generateConfirm.innerHTML = '<i class="ti ti-circle-plus me-1"></i>Generate';
                        if (!res.success) throw new Error((res.error && res.error.message) || 'Gagal generate.');
                        var modal = getModal('sptGenerateModal');
                        if (modal) modal.hide();
                        showBanner(page, 'success', 'SPT Masa periode ' + periode + ' berhasil digenerate.');
                        state.page = 1;
                        loadList();
                    })
                    .catch(function (err) {
                        generateConfirm.disabled = false;
                        generateConfirm.innerHTML = '<i class="ti ti-circle-plus me-1"></i>Generate';
                        if (errorEl) { errorEl.textContent = err.message || 'Gagal generate.'; errorEl.classList.remove('d-none'); }
                    });
            });
        }

        // Filter
        var filterApply = page.querySelector('[data-spt-filter-apply]');
        if (filterApply) {
            filterApply.addEventListener('click', function () {
                var pEl = page.querySelector('[data-spt-filter-periode]');
                var sEl = page.querySelector('[data-spt-filter-status]');
                state.periode = (pEl && pEl.value) ? pEl.value : '';
                state.status = (sEl && sEl.value) ? sEl.value : '';
                state.page = 1;
                clearBanners(page);
                loadList();
            });
        }
        var filterReset = page.querySelector('[data-spt-filter-reset]');
        if (filterReset) {
            filterReset.addEventListener('click', function () {
                var pEl = page.querySelector('[data-spt-filter-periode]');
                var sEl = page.querySelector('[data-spt-filter-status]');
                if (pEl) pEl.value = '';
                if (sEl) sEl.value = '';
                state.periode = '';
                state.status = '';
                state.page = 1;
                clearBanners(page);
                loadList();
            });
        }

        loadList();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Detail page
    // ─────────────────────────────────────────────────────────────────────────

    function initDetailPage(page) {
        var sptUuid = page.getAttribute('data-spt-uuid') || '';
        var currentVersion = 0;

        function loadDetail() {
            clearBanners(page);
            apiGet('/hcm/spt-masa/headers/' + sptUuid)
                .then(function (res) {
                    if (!res.success) throw new Error((res.error && res.error.message) || 'Gagal memuat detail.');
                    renderDetail(page, res.data);
                })
                .catch(function (err) {
                    showBanner(page, 'error', err.message || 'Gagal memuat detail.');
                });
        }

        function renderDetail(page, data) {
            currentVersion = data.version || 1;

            var periodeEl = page.querySelector('[data-spt-detail-periode]');
            if (periodeEl) periodeEl.textContent = data.periode || '—';

            var badgeEl = page.querySelector('[data-spt-status-badge]');
            if (badgeEl) badgeEl.innerHTML = statusBadge(data.status);

            var totKar = page.querySelector('[data-spt-total-karyawan]');
            if (totKar) totKar.textContent = data.totalKaryawan !== undefined ? data.totalKaryawan : '—';

            var totBruto = page.querySelector('[data-spt-total-bruto]');
            if (totBruto) totBruto.textContent = formatMoney(data.totalBruto);

            var totPph = page.querySelector('[data-spt-total-pph21]');
            if (totPph) totPph.textContent = formatMoney(data.totalPph21);

            var countEl = page.querySelector('[data-spt-detail-count]');
            var details = data.details || [];
            if (countEl) countEl.textContent = details.length + ' karyawan';

            // Action buttons visibility
            var regBtn = page.querySelector('[data-spt-regenerate-btn]');
            var rdyBtn = page.querySelector('[data-spt-markready-btn]');
            var subBtn = page.querySelector('[data-spt-submit-btn]');
            var expBtn = page.querySelector('[data-spt-export-btn]');

            if (regBtn) regBtn.classList.toggle('d-none', data.status === 'submitted');
            if (rdyBtn) rdyBtn.classList.toggle('d-none', data.status !== 'draft');
            if (subBtn) subBtn.classList.toggle('d-none', data.status !== 'ready');
            if (expBtn) {
                expBtn.classList.remove('d-none');
                expBtn.href = '/v1/hcm/spt-masa/headers/' + escapeHtml(sptUuid) + '/export.csv';
            }

            // Render detail rows
            var body = page.querySelector('[data-spt-detail-body]');
            if (!body) return;
            if (details.length === 0) {
                body.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-4">Belum ada detail karyawan.</td></tr>';
                return;
            }
            var rows = details.map(function (d) {
                return '<tr>' +
                    '<td>' + escapeHtml(d.nama) + '</td>' +
                    '<td>' + escapeHtml(d.npwp || '—') + '</td>' +
                    '<td>' + escapeHtml(d.nik || '—') + '</td>' +
                    '<td>' + escapeHtml(d.kategoriSpt || '—') + '</td>' +
                    '<td>' + escapeHtml(d.buktiPotongType || '—') + '</td>' +
                    '<td class="text-end">' + formatMoney(d.bruto) + '</td>' +
                    '<td class="text-end">' + formatMoney(d.pph21) + '</td>' +
                    '</tr>';
            });
            body.innerHTML = rows.join('');
        }

        // Regenerate
        var regBtn = page.querySelector('[data-spt-regenerate-btn]');
        if (regBtn) {
            regBtn.addEventListener('click', function () {
                var modal = getModal('sptRegenerateModal');
                var modalEl = document.getElementById('sptRegenerateModal');
                var errEl = modalEl && modalEl.querySelector('[data-spt-regenerate-modal-error]');
                if (errEl) { errEl.classList.add('d-none'); errEl.textContent = ''; }
                if (modal) modal.show();
            });
        }

        var regConfirm = document.querySelector('[data-spt-regenerate-confirm]');
        if (regConfirm) {
            regConfirm.addEventListener('click', function () {
                var modalEl = document.getElementById('sptRegenerateModal');
                var errEl = modalEl && modalEl.querySelector('[data-spt-regenerate-modal-error]');
                regConfirm.disabled = true;
                regConfirm.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Proses…';

                apiPost('/hcm/spt-masa/headers/' + sptUuid + '/regenerate', { version: currentVersion })
                    .then(function (res) {
                        regConfirm.disabled = false;
                        regConfirm.innerHTML = '<i class="ti ti-refresh me-1"></i>Lanjutkan Regenerate';
                        if (!res.success) throw new Error((res.error && res.error.message) || 'Gagal regenerate.');
                        var modal = getModal('sptRegenerateModal');
                        if (modal) modal.hide();
                        showBanner(page, 'success', 'SPT Masa berhasil di-regenerate.');
                        loadDetail();
                    })
                    .catch(function (err) {
                        regConfirm.disabled = false;
                        regConfirm.innerHTML = '<i class="ti ti-refresh me-1"></i>Lanjutkan Regenerate';
                        if (errEl) { errEl.textContent = err.message || 'Gagal regenerate.'; errEl.classList.remove('d-none'); }
                    });
            });
        }

        // Mark Ready
        var rdyBtn = page.querySelector('[data-spt-markready-btn]');
        if (rdyBtn) {
            rdyBtn.addEventListener('click', function () {
                clearBanners(page);
                rdyBtn.disabled = true;
                rdyBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Proses…';

                apiPost('/hcm/spt-masa/headers/' + sptUuid + '/mark-ready', { version: currentVersion })
                    .then(function (res) {
                        rdyBtn.disabled = false;
                        rdyBtn.innerHTML = '<i class="ti ti-check me-1"></i>Tandai Ready';
                        if (!res.success) throw new Error((res.error && res.error.message) || 'Gagal tandai ready.');
                        showBanner(page, 'success', 'Status berhasil diubah menjadi Ready.');
                        loadDetail();
                    })
                    .catch(function (err) {
                        rdyBtn.disabled = false;
                        rdyBtn.innerHTML = '<i class="ti ti-check me-1"></i>Tandai Ready';
                        showBanner(page, 'error', err.message || 'Gagal tandai ready.');
                    });
            });
        }

        // Submit
        var subBtn = page.querySelector('[data-spt-submit-btn]');
        if (subBtn) {
            subBtn.addEventListener('click', function () {
                var modal = getModal('sptSubmitModal');
                var modalEl = document.getElementById('sptSubmitModal');
                var errEl = modalEl && modalEl.querySelector('[data-spt-submit-modal-error]');
                var notesEl = modalEl && modalEl.querySelector('[data-spt-submit-notes]');
                if (errEl) { errEl.classList.add('d-none'); errEl.textContent = ''; }
                if (notesEl) notesEl.value = '';
                if (modal) modal.show();
            });
        }

        var subConfirm = document.querySelector('[data-spt-submit-confirm]');
        if (subConfirm) {
            subConfirm.addEventListener('click', function () {
                var modalEl = document.getElementById('sptSubmitModal');
                var errEl = modalEl && modalEl.querySelector('[data-spt-submit-modal-error]');
                var notesEl = modalEl && modalEl.querySelector('[data-spt-submit-notes]');
                var notes = (notesEl && notesEl.value) || '';

                subConfirm.disabled = true;
                subConfirm.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Proses…';

                apiPost('/hcm/spt-masa/headers/' + sptUuid + '/submit', { version: currentVersion, notes: notes })
                    .then(function (res) {
                        subConfirm.disabled = false;
                        subConfirm.innerHTML = '<i class="ti ti-send me-1"></i>Submit';
                        if (!res.success) throw new Error((res.error && res.error.message) || 'Gagal submit.');
                        var modal = getModal('sptSubmitModal');
                        if (modal) modal.hide();
                        showBanner(page, 'success', 'SPT Masa berhasil disubmit.');
                        loadDetail();
                    })
                    .catch(function (err) {
                        subConfirm.disabled = false;
                        subConfirm.innerHTML = '<i class="ti ti-send me-1"></i>Submit';
                        if (errEl) { errEl.textContent = err.message || 'Gagal submit.'; errEl.classList.remove('d-none'); }
                    });
            });
        }

        loadDetail();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Bootstrap
    // ─────────────────────────────────────────────────────────────────────────

    function init() {
        var page = document.querySelector('[data-spt-masa-page]');
        if (!page) return;

        if (typeof window.AuthApi === 'undefined') {
            console.error('[spt-masa-data] window.AuthApi is not loaded.');
            return;
        }

        var screen = page.getAttribute('data-spt-masa-screen');

        if (screen === 'list') {
            initListPage(page);
        } else if (screen === 'detail') {
            initDetailPage(page);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})(window, document);
