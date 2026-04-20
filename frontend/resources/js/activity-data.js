(function () {
    'use strict';

    var tbody = document.querySelector('[data-activity-body]');
    if (!tbody) {
        return;
    }

    var searchInput = document.querySelector('[data-activity-search]');
    var typeSelect = document.querySelector('[data-activity-type]');
    var sourceSelect = document.querySelector('[data-activity-source]');
    var statusSelect = document.querySelector('[data-activity-status]');
    var companySelect = document.querySelector('[data-activity-company]');
    var companySelectWrap = document.querySelector('[data-activity-company-select-wrap]');
    var prevButton = document.querySelector('[data-activity-prev]');
    var nextButton = document.querySelector('[data-activity-next]');
    var pageInfo = document.querySelector('[data-activity-page-info]');
    var addButton = document.querySelector('[data-activity-add]');
    var modalEl = document.getElementById('manual_activity_modal');
    var modalTitle = document.querySelector('[data-manual-activity-modal-title]');
    var modalForm = document.querySelector('[data-manual-activity-form]');
    var modalId = document.querySelector('[data-manual-activity-id]');
    var modalTitleInput = document.querySelector('[data-manual-activity-title]');
    var modalKindInput = document.querySelector('[data-manual-activity-kind]');
    var modalStatusInput = document.querySelector('[data-manual-activity-status]');
    var modalDueDateInput = document.querySelector('[data-manual-activity-due-date]');
    var modalError = document.querySelector('[data-manual-activity-error]');
    var modalSubmit = document.querySelector('[data-manual-activity-submit]');
    var modalInstance = modalEl && window.bootstrap ? new window.bootstrap.Modal(modalEl) : null;

    var state = {
        page: 1,
        perPage: 20,
        totalPages: 1,
        loading: false,
        q: '',
        type: 'all',
        sourceType: 'all',
        statusType: 'all',
        companyId: '',
        isSuperAdmin: false,
        manualRowsById: {},
    };

    function escapeHtml(value) {
        return String(value == null ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function formatDate(value) {
        if (!value) {
            return '-';
        }

        var date = new Date(value);
        if (Number.isNaN(date.getTime())) {
            return '-';
        }

        return date.toLocaleDateString('en-GB', {
            day: '2-digit',
            month: 'short',
            year: 'numeric',
        });
    }

    function typeBadge(type, label) {
        var normalized = String(type || '').toLowerCase();
        if (normalized === 'asset') {
            return '<span class="badge badge-info-transparent"><i class="ti ti-package me-1"></i>' + escapeHtml(label || 'Asset') + '</span>';
        }
        if (normalized === 'user_access') {
            return '<span class="badge badge-purple-transparent"><i class="ti ti-user-shield me-1"></i>' + escapeHtml(label || 'User Access') + '</span>';
        }
        if (normalized === 'payroll') {
            return '<span class="badge badge-success-transparent"><i class="ti ti-report-money me-1"></i>' + escapeHtml(label || 'Payroll') + '</span>';
        }

        return '<span class="badge badge-secondary-transparent">' + escapeHtml(label || 'Activity') + '</span>';
    }

    function sourceBadge(type, label) {
        var normalized = String(type || '').toLowerCase();
        if (normalized === 'manual') {
            return '<span class="badge badge-success-transparent"><i class="ti ti-user me-1"></i>' + escapeHtml(label || 'Manual') + '</span>';
        }

        return '<span class="badge badge-warning-transparent"><i class="ti ti-settings me-1"></i>' + escapeHtml(label || 'System') + '</span>';
    }

    function statusBadge(type, label) {
        var normalized = String(type || '').toLowerCase();
        if (normalized === 'finalized' || normalized === 'assigned' || normalized === 'created') {
            return '<span class="badge badge-success-transparent">' + escapeHtml(label || normalized) + '</span>';
        }
        if (normalized === 'revoked' || normalized === 'void' || normalized === 'deleted') {
            return '<span class="badge badge-danger-transparent">' + escapeHtml(label || normalized) + '</span>';
        }
        if (normalized === 'calculated' || normalized === 'updated') {
            return '<span class="badge badge-info-transparent">' + escapeHtml(label || normalized) + '</span>';
        }

        return '<span class="badge badge-secondary-transparent">' + escapeHtml(label || normalized || 'Draft') + '</span>';
    }

    function renderRows(rows) {
        if (!rows || rows.length === 0) {
            tbody.innerHTML = '<tr><td colspan="9" class="text-center text-muted py-4">No activity found.</td></tr>';
            return;
        }

        state.manualRowsById = {};

        var html = rows.map(function (row) {
            if (row.manualActivityId) {
                state.manualRowsById[String(row.manualActivityId)] = row;
            }

            var actions = '<span class="badge badge-secondary-transparent">View only</span>';
            if (row.canEdit || row.canDelete) {
                var editBtn = row.canEdit
                    ? '<button type="button" class="btn btn-sm btn-outline-primary me-2" data-activity-edit="' + escapeHtml(row.manualActivityId || '') + '">Edit</button>'
                    : '';
                var deleteBtn = row.canDelete
                    ? '<button type="button" class="btn btn-sm btn-outline-danger" data-activity-delete="' + escapeHtml(row.manualActivityId || '') + '">Delete</button>'
                    : '';
                actions = editBtn + deleteBtn;
            }

            return ''
                + '<tr>'
                + '<td><p class="fs-14 text-dark fw-medium mb-0">' + escapeHtml(row.title || '-') + '</p></td>'
                + '<td>' + typeBadge(row.activityType, row.activityTypeLabel) + '</td>'
                + '<td>' + sourceBadge(row.sourceType, row.sourceTypeLabel) + '</td>'
                + '<td><span class="badge badge-light-transparent">' + escapeHtml(row.companyName || row.companyCode || '-') + '</span></td>'
                + '<td>' + statusBadge(row.statusType, row.statusTypeLabel) + '</td>'
                + '<td>' + escapeHtml(formatDate(row.dueDate)) + '</td>'
                + '<td>' + escapeHtml(row.ownerName || '-') + '</td>'
                + '<td>' + escapeHtml(formatDate(row.createdAt)) + '</td>'
                + '<td class="text-end">' + actions + '</td>'
                + '</tr>';
        }).join('');

        tbody.innerHTML = html;
    }

    function setLoading(loading) {
        state.loading = loading;
        if (loading) {
            tbody.innerHTML = '<tr><td colspan="9" class="text-center text-muted py-4">Loading activity...</td></tr>';
        }

        if (prevButton) {
            prevButton.disabled = loading || state.page <= 1;
        }
        if (nextButton) {
            nextButton.disabled = loading || state.page >= state.totalPages;
        }
    }

    function updatePagination(meta) {
        state.totalPages = Math.max(1, Number(meta && meta.totalPages) || 1);

        if (pageInfo) {
            pageInfo.textContent = 'Page ' + state.page + ' of ' + state.totalPages;
        }

        if (prevButton) {
            prevButton.disabled = state.loading || state.page <= 1;
        }
        if (nextButton) {
            nextButton.disabled = state.loading || state.page >= state.totalPages;
        }
    }

    function buildUrl() {
        var params = new URLSearchParams();
        params.set('page', String(state.page));
        params.set('perPage', String(state.perPage));

        if (state.q) {
            params.set('q', state.q);
        }
        if (state.type && state.type !== 'all') {
            params.set('type', state.type);
        }
        if (state.sourceType && state.sourceType !== 'all') {
            params.set('sourceType', state.sourceType);
        }
        if (state.statusType && state.statusType !== 'all') {
            params.set('statusType', state.statusType);
        }
        if (state.companyId) {
            params.set('companyId', state.companyId);
        }

        return '/v1/hcm/activity-feed?' + params.toString();
    }

    function loadActivities() {
        setLoading(true);

        return fetch(buildUrl(), {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        })
            .then(function (res) {
                return res.json().catch(function () {
                    return null;
                }).then(function (payload) {
                    if (!res.ok || !payload || payload.success !== true) {
                        var message = payload && payload.error && payload.error.message
                            ? payload.error.message
                            : 'Failed to load activity feed.';
                        throw new Error(message);
                    }
                    return payload;
                });
            })
            .then(function (payload) {
                renderRows(payload.data || []);
                updatePagination(payload.meta || {});
            })
            .catch(function (error) {
                tbody.innerHTML = '<tr><td colspan="9" class="text-center text-danger py-4">' + escapeHtml(error.message || 'Failed to load activity feed.') + '</td></tr>';
                updatePagination({ totalPages: 1 });
            })
            .finally(function () {
                setLoading(false);
            });
    }

    function setModalError(message) {
        if (!modalError) {
            return;
        }

        if (!message) {
            modalError.classList.add('d-none');
            modalError.textContent = '';
            return;
        }

        modalError.classList.remove('d-none');
        modalError.textContent = message;
    }

    function openCreateModal() {
        if (!modalInstance) {
            return;
        }

        modalId.value = '';
        modalTitleInput.value = '';
        modalKindInput.value = 'task';
        modalStatusInput.value = 'planned';
        modalDueDateInput.value = '';
        modalTitle.textContent = 'Add Manual Activity';
        setModalError('');
        modalSubmit.disabled = false;
        modalSubmit.textContent = 'Save';
        modalInstance.show();
    }

    function openEditModal(id) {
        if (!modalInstance) {
            return;
        }

        var row = state.manualRowsById[String(id)];
        if (!row) {
            return;
        }

        modalId.value = String(id);
        modalTitleInput.value = row.title || '';
        modalKindInput.value = row.activityKind || 'task';
        modalStatusInput.value = row.statusType || 'planned';
        modalDueDateInput.value = row.dueDate || '';
        modalTitle.textContent = 'Edit Manual Activity';
        setModalError('');
        modalSubmit.disabled = false;
        modalSubmit.textContent = 'Save';
        modalInstance.show();
    }

    function parseApiError(payload, fallback) {
        if (payload && payload.error && payload.error.message) {
            return payload.error.message;
        }

        return fallback;
    }

    function submitManualActivity(event) {
        event.preventDefault();
        setModalError('');

        var id = String(modalId.value || '').trim();
        var payload = {
            title: String(modalTitleInput.value || '').trim(),
            activityKind: String(modalKindInput.value || 'task'),
            statusType: String(modalStatusInput.value || 'planned'),
            dueDate: String(modalDueDateInput.value || '').trim() || null,
        };

        if (!payload.title) {
            setModalError('Title is required.');
            return;
        }

        var method = id ? 'PUT' : 'POST';
        var url = id ? '/v1/hcm/activity-manual/' + encodeURIComponent(id) : '/v1/hcm/activity-manual';
        modalSubmit.disabled = true;
        modalSubmit.textContent = 'Saving...';

        fetch(url, {
            method: method,
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            body: JSON.stringify(payload),
        })
            .then(function (res) {
                return res.json().catch(function () { return null; }).then(function (body) {
                    if (!res.ok || !body || body.success !== true) {
                        throw new Error(parseApiError(body, 'Failed to save manual activity.'));
                    }

                    return body;
                });
            })
            .then(function () {
                if (modalInstance) {
                    modalInstance.hide();
                }
                loadActivities();
            })
            .catch(function (error) {
                setModalError(error.message || 'Failed to save manual activity.');
            })
            .finally(function () {
                modalSubmit.disabled = false;
                modalSubmit.textContent = 'Save';
            });
    }

    function deleteManualActivity(id) {
        var normalizedId = String(id || '').trim();
        if (!normalizedId) {
            return;
        }

        var confirmPromise = window.ArcavUi && typeof window.ArcavUi.confirmDelete === 'function'
            ? window.ArcavUi.confirmDelete('Hapus manual activity ini? Tindakan tidak dapat dibatalkan.', 'Delete Manual Activity')
            : window.ArcavUi && typeof window.ArcavUi.confirm === 'function'
                ? window.ArcavUi.confirm('Delete this manual activity?', 'Konfirmasi')
                : Promise.resolve(false);

        confirmPromise.then(function (confirmed) {
            if (!confirmed) {
                return;
            }

            fetch('/v1/hcm/activity-manual/' + encodeURIComponent(normalizedId), {
                method: 'DELETE',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            })
                .then(function (res) {
                    return res.json().catch(function () { return null; }).then(function (body) {
                        if (!res.ok || !body || body.success !== true) {
                            throw new Error(parseApiError(body, 'Failed to delete manual activity.'));
                        }
                    });
                })
                .then(function () {
                    loadActivities();
                    if (window.ArcavUi && typeof window.ArcavUi.showToast === 'function') {
                        window.ArcavUi.showToast('Manual activity deleted.', 'success');
                    }
                })
                .catch(function (error) {
                    if (window.ArcavUi && typeof window.ArcavUi.showToast === 'function') {
                        window.ArcavUi.showToast(error.message || 'Failed to delete manual activity.', 'danger');
                        return;
                    }
                    console.error(error);
                });
        });
    }

    var searchTimer = null;
    if (searchInput) {
        searchInput.addEventListener('input', function () {
            if (searchTimer) {
                clearTimeout(searchTimer);
            }

            searchTimer = setTimeout(function () {
                state.q = String(searchInput.value || '').trim();
                state.page = 1;
                loadActivities();
            }, 300);
        });
    }

    if (typeSelect) {
        typeSelect.addEventListener('change', function () {
            state.type = String(typeSelect.value || 'all');
            state.page = 1;
            loadActivities();
        });
    }

    if (sourceSelect) {
        sourceSelect.addEventListener('change', function () {
            state.sourceType = String(sourceSelect.value || 'all');
            state.page = 1;
            loadActivities();
        });
    }

    if (statusSelect) {
        statusSelect.addEventListener('change', function () {
            state.statusType = String(statusSelect.value || 'all');
            state.page = 1;
            loadActivities();
        });
    }

    if (prevButton) {
        prevButton.addEventListener('click', function () {
            if (state.page <= 1 || state.loading) {
                return;
            }
            state.page -= 1;
            loadActivities();
        });
    }

    if (nextButton) {
        nextButton.addEventListener('click', function () {
            if (state.page >= state.totalPages || state.loading) {
                return;
            }
            state.page += 1;
            loadActivities();
        });
    }

    if (addButton) {
        addButton.addEventListener('click', function () {
            openCreateModal();
        });
    }

    if (modalForm) {
        modalForm.addEventListener('submit', submitManualActivity);
    }

    if (companySelect) {
        companySelect.addEventListener('change', function () {
            state.companyId = String(companySelect.value || '').trim();
            state.page = 1;
            loadActivities();
        });
    }

    function loadCompanies() {
        if (!companySelect) {
            return;
        }

        fetch('/v1/hcm/activity-feed-companies', {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        })
            .then(function (res) {
                return res.json().catch(function () {
                    return null;
                }).then(function (payload) {
                    if (!res.ok || !payload || payload.success !== true) {
                        return null;
                    }
                    return payload;
                });
            })
            .then(function (payload) {
                if (!payload || !payload.data || payload.data.length === 0) {
                    if (companySelectWrap) {
                        companySelectWrap.style.display = 'none';
                    }
                    state.isSuperAdmin = false;
                    return;
                }

                state.isSuperAdmin = true;
                if (companySelectWrap) {
                    companySelectWrap.style.display = 'block';
                }

                var companies = payload.data;
                var options = '<option value="">All Companies</option>';
                options += companies.map(function (company) {
                    return '<option value="' + escapeHtml(company.id || '') + '">'
                        + escapeHtml(company.code || '') + ' - ' + escapeHtml(company.name || '')
                        + '</option>';
                }).join('');

                companySelect.innerHTML = options;
            })
            .catch(function () {
                if (companySelectWrap) {
                    companySelectWrap.style.display = 'none';
                }
                state.isSuperAdmin = false;
            });
    }

    tbody.addEventListener('click', function (event) {
        var editId = event.target && event.target.getAttribute('data-activity-edit');
        if (editId) {
            openEditModal(editId);
            return;
        }

        var deleteId = event.target && event.target.getAttribute('data-activity-delete');
        if (deleteId) {
            deleteManualActivity(deleteId);
        }
    });

    loadCompanies();
    loadActivities();
})();
