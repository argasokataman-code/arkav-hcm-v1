/**
 * Team Master Data Manager
 * Handles CRUD operations for teams
 */

(function (window) {
    "use strict";

    // Utility: escape HTML
    function esc(val) {
        if (val == null) return '';
        var div = document.createElement('div');
        div.textContent = val;
        return div.innerHTML;
    }

    // Get auth headers
    function getAuthHeaders() {
        var headers = {};
        var token = (window.AuthApi && typeof window.AuthApi.getToken === "function" && window.AuthApi.getToken()) || localStorage.getItem('arcav_access_token');
        if (!token) {
            var tokenEl = document.querySelector('[data-auth-token]');
            if (tokenEl && tokenEl.getAttribute('data-auth-token')) {
                token = tokenEl.getAttribute('data-auth-token');
            }
        }
        if (token) { headers['Authorization'] = 'Bearer ' + token; }
        var company = document.querySelector('[data-company-id]');
        if (company && company.getAttribute('data-company-id')) {
            headers['X-Company-Id'] = company.getAttribute('data-company-id');
        }
        return headers;
    }

    // Toast notification
    function notify(message, isError) {
        var existing = document.querySelector("[data-hcm-toast-container]");
        var container = existing;
        if (!container) {
            container = document.createElement("div");
            container.setAttribute("data-hcm-toast-container", "1");
            container.style.position = "fixed";
            container.style.top = "16px";
            container.style.right = "16px";
            container.style.zIndex = "1080";
            container.style.maxWidth = "340px";
            document.body.appendChild(container);
        }

        var toast = document.createElement("div");
        toast.className = "alert " + (isError ? "alert-danger" : "alert-success") + " shadow-sm mb-2";
        toast.textContent = message;
        container.appendChild(toast);

        window.setTimeout(function () {
            toast.remove();
        }, 2400);
    }

    // API helper
    function api(method, url, data) {
        var options = {
            method: method.toUpperCase(),
            headers: Object.assign({ 'Content-Type': 'application/json', 'Accept': 'application/json' }, getAuthHeaders()),
            credentials: 'same-origin',
        };
        if (data) {
            options.body = JSON.stringify(data);
        }
        return fetch(url, options).then(function (res) {
            return res.json().then(function (body) {
                if (!res.ok) {
                    throw { status: res.status, body: body };
                }
                return body;
            });
        });
    }

    // Fetch teams list
    function fetchTeams(page, perPage, search, status) {
        var params = new URLSearchParams({
            page: page || 1,
            perPage: perPage || 20,
            search: search || '',
            status: status || 'all'
        });
        return api('GET', '/v1/hcm/teams?' + params.toString()).catch(function (err) {
            notify('Failed to load teams: ' + (err.body && err.body.message ? err.body.message : 'Unknown error'), true);
            throw err;
        });
    }

    // Fetch departments for dropdown
    function fetchDepartments() {
        return api('GET', '/v1/hcm/departments').then(function (res) {
            return res.data || [];
        }).catch(function () {
            return [];
        });
    }

    function setTeamLeadSelectValue(selectEl, userId, userName) {
        if (!selectEl) return;

        var normalizedId = userId != null && String(userId).trim() !== '' ? String(userId) : '';

        if (!normalizedId) {
            selectEl.value = '';
            if (window.jQuery && window.jQuery.fn && window.jQuery.fn.select2) {
                window.jQuery(selectEl).trigger('change');
            }
            return;
        }

        var exists = Array.prototype.slice.call(selectEl.options).some(function (opt) {
            return String(opt.value) === normalizedId;
        });

        if (!exists) {
            var label = userName && String(userName).trim() ? String(userName).trim() : ('User #' + normalizedId);
            var opt = new Option(label, normalizedId, true, true);
            selectEl.appendChild(opt);
        }

        selectEl.value = normalizedId;
        if (window.jQuery && window.jQuery.fn && window.jQuery.fn.select2) {
            window.jQuery(selectEl).trigger('change');
        }
    }

    function initTeamLeadRemoteSelect(selector, modalSelector) {
        if (!(window.jQuery && window.jQuery.fn && window.jQuery.fn.select2)) return;

        var el = document.querySelector(selector);
        if (!el || el.getAttribute('data-team-lead-remote-init') === '1') return;

        var $el = window.jQuery(el);
        var $modal = modalSelector ? window.jQuery(modalSelector) : null;
        var pageSize = 20;

        $el.select2({
            width: '100%',
            allowClear: true,
            placeholder: 'Cari employee untuk Team Lead (opsional)',
            dropdownParent: $modal && $modal.length ? $modal : null,
            minimumInputLength: 0,
            ajax: {
                delay: 250,
                transport: function (params, success, failure) {
                    var term = params.data && params.data.search ? String(params.data.search) : '';
                    var page = params.data && params.data.page ? Number(params.data.page) : 1;
                    var query = new URLSearchParams({
                        page: String(page),
                        perPage: String(pageSize),
                        status: 'active',
                        search: term
                    });

                    api('GET', '/v1/hcm/user-management/users?' + query.toString())
                        .then(success)
                        .catch(failure);
                },
                data: function (params) {
                    return {
                        search: params.term || '',
                        page: params.page || 1
                    };
                },
                processResults: function (response, params) {
                    var rows = Array.isArray(response && response.data) ? response.data : [];
                    var pagination = response && response.meta && response.meta.pagination ? response.meta.pagination : {};
                    var page = Number((params && params.page) || 1);
                    var lastPage = Math.max(page, Number(pagination.lastPage || page) || page);

                    return {
                        results: rows.map(function (user) {
                            var label = user && user.name ? user.name : ('User #' + String(user && user.id ? user.id : ''));
                            if (user && user.email) {
                                label += ' (' + user.email + ')';
                            }
                            return {
                                id: user.id,
                                text: label,
                                rawName: user && user.name ? user.name : label
                            };
                        }),
                        pagination: {
                            more: page < lastPage
                        }
                    };
                }
            }
        });

        el.setAttribute('data-team-lead-remote-init', '1');
    }

    // Render teams grid
    function renderTeams(data, meta) {
        var body = document.querySelector('[data-teams-body]');
        if (!body) return;

        body.innerHTML = (data || []).map(function (team) {
            var badge = team.is_active ? 'success' : 'danger';
            var status = team.is_active ? 'Active' : 'Inactive';
            var deptName = team.department_name || '—';
            var leadName = team.team_lead_id ? team.team_lead_name || '—' : '—';
            var memberCount = team.member_count || 0;
            var membersUrl = '/teams/' + esc(team.id) + '/members';
            
            return '<tr>' +
                '<td><h6 class="fw-medium">' + esc(team.name) + '</h6></td>' +
                '<td>' + esc(deptName) + '</td>' +
                '<td><a href="' + membersUrl + '" class="badge bg-light-info text-decoration-none">' + memberCount + '</a></td>' +
                '<td>' + esc(leadName) + '</td>' +
                '<td><span class="badge badge-' + badge + ' d-inline-flex align-items-center badge-xs"><i class="ti ti-point-filled me-1"></i>' + status + '</span></td>' +
                '<td><div class="action-icon d-inline-flex align-items-center">' +
                '<a href="' + membersUrl + '" class="me-2" title="Assign Members" aria-label="Assign Members"><i class="ti ti-user-plus"></i></a>' +
                '<a href="#" class="me-2" data-hcm-edit="team" data-id="' + esc(team.id) + '" data-name="' + esc(team.name) + '" data-department-id="' + esc(team.department_id) + '" data-team-lead-id="' + esc(team.team_lead_id || '') + '" data-team-lead-name="' + esc(team.team_lead_name || '') + '" data-active="' + (team.is_active ? "1" : "0") + '"><i class="ti ti-edit"></i></a>' +
                '<a href="#" data-hcm-delete="team" data-id="' + esc(team.id) + '" data-name="' + esc(team.name) + '"><i class="ti ti-trash"></i></a>' +
                '</div></td>' +
                '</tr>';
        }).join('') || '<tr><td colspan="6" class="text-center py-4 text-muted">No teams found.</td></tr>';

        body.setAttribute('data-hydrated', '1');
        renderPagination(meta);
        renderShowing(data.length, meta);
    }

    // Render pagination
    function renderPagination(meta) {
        var list = document.querySelector('[data-hcm-pagination="teams"]');
        if (!list) return;

        var total = meta.total || 0;
        var page = meta.page || 1;
        var perPage = meta.perPage || 20;
        var totalPages = Math.max(1, Math.ceil(total / Math.max(1, perPage)));

        if (totalPages <= 1) {
            list.innerHTML = '';
            return;
        }

        var startPage = Math.max(1, page - 2);
        var endPage = Math.min(totalPages, page + 2);
        var html = '';

        html += '<li class="page-item ' + (page <= 1 ? 'disabled' : '') + '"><a href="#" class="page-link" data-hcm-page="' + (page - 1) + '">Prev</a></li>';
        for (var p = startPage; p <= endPage; p++) {
            html += '<li class="page-item ' + (p === page ? 'active' : '') + '"><a href="#" class="page-link" data-hcm-page="' + p + '">' + p + '</a></li>';
        }
        html += '<li class="page-item ' + (page >= totalPages ? 'disabled' : '') + '"><a href="#" class="page-link" data-hcm-page="' + (page + 1) + '">Next</a></li>';

        list.innerHTML = html;
    }

    // Render showing text
    function renderShowing(rowCount, meta) {
        var el = document.querySelector('[data-hcm-showing="teams"]');
        if (!el) return;

        var total = meta.total || 0;
        var page = meta.page || 1;
        var perPage = meta.perPage || 20;

        if (!total || !rowCount) {
            el.textContent = 'Showing 0 - 0 of 0 entries';
            return;
        }

        var start = ((page - 1) * perPage) + 1;
        var end = Math.min(start + rowCount - 1, total);
        el.textContent = 'Showing ' + start + ' - ' + end + ' of ' + total + ' entries';
    }

    // Populate dropdown
    function populateDropdown(selector, items, valueKey, labelKey) {
        var select = document.querySelector(selector);
        if (!select) return;

        var currentValue = select.value;
        select.innerHTML = '<option value="">Select</option>';
        (items || []).forEach(function (item) {
            var opt = document.createElement('option');
            opt.value = item[valueKey] || '';
            opt.textContent = item[labelKey] || '';
            select.appendChild(opt);
        });
        select.value = currentValue;
    }

    // Load page
    function loadPage(page, perPage, search, status) {
        page = page || 1;
        perPage = perPage || 20;
        search = search || '';
        status = status || 'all';

        fetchTeams(page, perPage, search, status).then(function (res) {
            renderTeams(res.data, res.meta);
        });
    }

    // Init page
    function initTeamsPage() {
        var path = window.location.pathname;
        if (path !== '/teams') return;

        var currentPage = 1;
        var currentPerPage = 20;
        var currentSearch = '';
        var currentStatus = 'all';

        // Load initial data
        loadPage(currentPage, currentPerPage, currentSearch, currentStatus);

        // Load dropdown options
        fetchDepartments().then(function (depts) {
            populateDropdown('[data-hcm-field="team-department"]', depts, 'id', 'name');
            populateDropdown('#edit_team [data-hcm-field="team-department"]', depts, 'id', 'name');
        });
        initTeamLeadRemoteSelect('[data-hcm-form="team-add"] [data-hcm-field="team-lead"]', '#add_team');
        initTeamLeadRemoteSelect('[data-hcm-form="team-edit"] [data-hcm-field="team-lead"]', '#edit_team');

        // Pagination
        document.addEventListener('click', function (e) {
            var pageLink = e.target.closest('[data-hcm-page]');
            if (pageLink) {
                e.preventDefault();
                currentPage = parseInt(pageLink.getAttribute('data-hcm-page'), 10);
                loadPage(currentPage, currentPerPage, currentSearch, currentStatus);
            }
        });

        // Search
        var searchInput = document.querySelector('[data-hcm-search-input="teams"]');
        if (searchInput) {
            searchInput.addEventListener('change', function () {
                currentSearch = this.value;
                currentPage = 1;
                loadPage(currentPage, currentPerPage, currentSearch, currentStatus);
            });
        }

        // Status filter
        var statusFilter = document.querySelector('[data-hcm-status-filter="teams"]');
        if (statusFilter) {
            statusFilter.addEventListener('change', function () {
                currentStatus = this.value;
                currentPage = 1;
                loadPage(currentPage, currentPerPage, currentSearch, currentStatus);
            });
        }

        // Per page
        var perPageSelect = document.querySelector('[data-hcm-per-page="teams"]');
        if (perPageSelect) {
            perPageSelect.addEventListener('change', function () {
                currentPerPage = parseInt(this.value, 10);
                currentPage = 1;
                loadPage(currentPage, currentPerPage, currentSearch, currentStatus);
            });
        }

        // Create form
        var addForm = document.querySelector('[data-hcm-form="team-add"]');
        if (addForm) {
            addForm.addEventListener('submit', function (e) {
                e.preventDefault();
                if (!ArcavValidation.validateForm(addForm)) { return; }
                var nameInput = this.querySelector('[data-hcm-field="team-name"]');
                var deptInput = this.querySelector('[data-hcm-field="team-department"]');
                var leadInput = this.querySelector('[data-hcm-field="team-lead"]');
                var activeInput = this.querySelector('[data-hcm-field="team-active"]');

                api('POST', '/v1/hcm/teams', {
                    name: nameInput.value,
                    department_id: parseInt(deptInput.value, 10),
                    team_lead_id: leadInput.value ? parseInt(leadInput.value, 10) : null,
                    is_active: activeInput.value === '1',
                }).then(function () {
                    notify('Team created successfully', false);
                    addForm.reset();
                    setTeamLeadSelectValue(addForm.querySelector('[data-hcm-field="team-lead"]'), '', '');
                    document.querySelector('#add_team').closest('.modal').modal = null;
                    var modal = window.bootstrap.Modal.getInstance(document.querySelector('#add_team'));
                    if (modal) modal.hide();
                    loadPage(currentPage, currentPerPage, currentSearch, currentStatus);
                }).catch(function (err) {
                    var msg = err.body && err.body.error ? err.body.error.message : 'Failed to create team';
                    notify(msg, true);
                });
            });
        }

        // Edit modal
        document.addEventListener('click', function (e) {
            var editBtn = e.target.closest('[data-hcm-edit="team"]');
            if (editBtn) {
                e.preventDefault();
                var form = document.querySelector('[data-hcm-form="team-edit"]');
                if (form) {
                    form.dataset.id = editBtn.getAttribute('data-id');
                    form.querySelector('[data-hcm-field="team-name"]').value = editBtn.getAttribute('data-name');
                    form.querySelector('[data-hcm-field="team-department"]').value = editBtn.getAttribute('data-department-id');
                    setTeamLeadSelectValue(
                        form.querySelector('[data-hcm-field="team-lead"]'),
                        editBtn.getAttribute('data-team-lead-id') || '',
                        editBtn.getAttribute('data-team-lead-name') || ''
                    );
                    form.querySelector('[data-hcm-field="team-active"]').value = editBtn.getAttribute('data-active');
                    var teamModalEl = document.getElementById('edit_team');
                    if (teamModalEl && window.bootstrap && window.bootstrap.Modal) {
                        window.bootstrap.Modal.getOrCreateInstance(teamModalEl).show();
                    }
                }
            }
        });

        // Edit form
        var editForm = document.querySelector('[data-hcm-form="team-edit"]');
        if (editForm) {
            editForm.addEventListener('submit', function (e) {
                e.preventDefault();
                if (!ArcavValidation.validateForm(editForm)) { return; }
                var id = this.dataset.id;
                var nameInput = this.querySelector('[data-hcm-field="team-name"]');
                var deptInput = this.querySelector('[data-hcm-field="team-department"]');
                var leadInput = this.querySelector('[data-hcm-field="team-lead"]');
                var activeInput = this.querySelector('[data-hcm-field="team-active"]');

                api('PUT', '/v1/hcm/teams/' + id, {
                    name: nameInput.value,
                    department_id: parseInt(deptInput.value, 10),
                    team_lead_id: leadInput.value ? parseInt(leadInput.value, 10) : null,
                    is_active: activeInput.value === '1',
                }).then(function () {
                    notify('Team updated successfully', false);
                    var modal = window.bootstrap.Modal.getInstance(document.querySelector('#edit_team'));
                    if (modal) modal.hide();
                    loadPage(currentPage, currentPerPage, currentSearch, currentStatus);
                }).catch(function (err) {
                    var msg = err.body && err.body.error ? err.body.error.message : 'Failed to update team';
                    notify(msg, true);
                });
            });
        }

        // Delete
        document.addEventListener('click', function (e) {
            var deleteBtn = e.target.closest('[data-hcm-delete="team"]');
            if (deleteBtn) {
                e.preventDefault();
                var id = deleteBtn.getAttribute('data-id');
                var name = deleteBtn.getAttribute('data-name');

                function doDeleteTeam() {
                    api('DELETE', '/v1/hcm/teams/' + id).then(function () {
                        notify('Team deleted successfully', false);
                        loadPage(currentPage, currentPerPage, currentSearch, currentStatus);
                    }).catch(function (err) {
                        var msg = err.body && err.body.error ? err.body.error.message : 'Failed to delete team';
                        notify(msg, true);
                    });
                }
                if (window.ArcavUi && window.ArcavUi.confirmDelete) {
                    window.ArcavUi.confirmDelete('Delete team "' + name + '"? This cannot be undone.', "Hapus Team").then(function(ok){ if (ok) doDeleteTeam(); });
                } else if (window.confirm('Delete team "' + name + '"? This cannot be undone.')) {
                    doDeleteTeam();
                }
            }
        });
    }

    function initTeamMembersPage() {
        var path = window.location.pathname || '';
        if (path.indexOf('/teams/') !== 0 || path.indexOf('/members') < 0) return;

        var idEl = document.querySelector('[data-team-members-id]');
        if (!idEl || !idEl.value) return;

        var state = {
            teamId: String(idEl.value),
            page: 1,
            perPage: 20,
            search: '',
            status: 'all'
        };

        function showAssignResult(level, message) {
            var box = document.querySelector('[data-team-members-assign-result]');
            if (!box) return;

            if (!message) {
                box.className = 'alert d-none mb-0';
                box.textContent = '';
                return;
            }

            var kind = level || 'info';
            var map = {
                info: 'alert-info',
                success: 'alert-success',
                warning: 'alert-warning',
                danger: 'alert-danger'
            };
            box.className = 'alert ' + (map[kind] || 'alert-info') + ' mb-0';
            box.textContent = message;
        }

        function getAssignModalInstance() {
            if (!(window.bootstrap && window.bootstrap.Modal)) return null;
            var modalEl = document.getElementById('team_members_assign_modal');
            if (!modalEl) return null;
            return window.bootstrap.Modal.getOrCreateInstance(modalEl);
        }

        function initAssignMembersPicker() {
            if (!(window.jQuery && window.jQuery.fn && window.jQuery.fn.select2)) return;

            var select = document.querySelector('[data-team-members-assign-users]');
            var modalEl = document.getElementById('team_members_assign_modal');
            if (!select || !modalEl || select.getAttribute('data-team-members-assign-init') === '1') return;

            var $select = window.jQuery(select);
            var $modal = window.jQuery(modalEl);
            var pageSize = 20;
            var currentTeamId = Number(state.teamId || 0);

            $select.select2({
                width: '100%',
                dropdownParent: $modal,
                placeholder: 'Search employee to assign',
                minimumInputLength: 1,
                ajax: {
                    delay: 250,
                    transport: function (params, success, failure) {
                        var term = params.data && params.data.search ? String(params.data.search) : '';
                        var page = params.data && params.data.page ? Number(params.data.page) : 1;
                        var query = new URLSearchParams({
                            page: String(page),
                            perPage: String(pageSize),
                            status: 'active',
                            search: term
                        });

                        api('GET', '/v1/hcm/employees?' + query.toString())
                            .then(success)
                            .catch(failure);
                    },
                    data: function (params) {
                        return {
                            search: params.term || '',
                            page: params.page || 1
                        };
                    },
                    processResults: function (response, params) {
                        var rows = Array.isArray(response && response.data) ? response.data : [];
                        var page = Number((params && params.page) || 1);
                        var meta = response && response.meta ? response.meta : {};
                        var total = Number(meta.total || 0);
                        var perPage = Math.max(1, Number(meta.perPage || pageSize));

                        var filtered = rows.filter(function (item) {
                            var employeeProfileId = Number(item && item.employeeProfileId ? item.employeeProfileId : 0);
                            return employeeProfileId > 0 && Number(item && item.teamId ? item.teamId : 0) !== currentTeamId;
                        });

                        return {
                            results: filtered.map(function (item) {
                                var name = item && item.fullName ? item.fullName : ('Employee #' + String(item && item.id ? item.id : ''));
                                var email = item && item.email ? item.email : '';
                                var caption = email ? (name + ' (' + email + ')') : name;
                                return {
                                    id: item.employeeProfileId,
                                    text: caption
                                };
                            }),
                            pagination: {
                                more: (page * perPage) < total
                            }
                        };
                    }
                }
            });

            select.setAttribute('data-team-members-assign-init', '1');
        }

        function renderMembersSummary(team, meta) {
            var title = document.querySelector('[data-team-members-title]');
            if (title) {
                title.textContent = 'Team Members - ' + (team && team.name ? team.name : '-');
            }
            var teamName = document.querySelector('[data-team-members-team-name]');
            if (teamName) teamName.textContent = team && team.name ? team.name : '-';
            var teamDept = document.querySelector('[data-team-members-team-department]');
            if (teamDept) teamDept.textContent = team && team.department_name ? team.department_name : '-';
            var teamLead = document.querySelector('[data-team-members-team-lead]');
            if (teamLead) teamLead.textContent = team && team.team_lead_name ? team.team_lead_name : '-';
            var total = document.querySelector('[data-team-members-total]');
            if (total) total.textContent = String(meta && meta.total ? meta.total : 0);
        }

        function renderMembersRows(rows) {
            var body = document.querySelector('[data-team-members-body]');
            if (!body) return;

            body.innerHTML = (rows || []).map(function (row) {
                var status = String(row.employment_status || '-');
                var statusBadge = status === 'active' ? 'success' : (status === 'probation' ? 'warning' : 'danger');
                var employeeId = Number(row && row.employee_id ? row.employee_id : 0);
                return '<tr>' +
                    '<td>' + esc(row.name || '-') + '</td>' +
                    '<td>' + esc(row.email || '-') + '</td>' +
                    '<td>' + esc(row.nik || '-') + '</td>' +
                    '<td>' + esc(row.department_name || '-') + '</td>' +
                    '<td>' + esc(row.designation_name || '-') + '</td>' +
                    '<td><span class="badge badge-' + statusBadge + '">' + esc(status) + '</span></td>' +
                    '<td class="text-end">' +
                        '<button type="button" class="btn btn-sm btn-outline-danger" data-team-members-remove="' + employeeId + '" data-team-members-remove-name="' + esc(row.name || '') + '">' +
                            '<i class="ti ti-user-off me-1"></i>Take Out' +
                        '</button>' +
                    '</td>' +
                    '</tr>';
            }).join('') || '<tr><td colspan="7" class="text-center py-4 text-muted">No members found.</td></tr>';

            body.setAttribute('data-hydrated', '1');
        }

        function renderMembersShowing(meta, rowCount) {
            var showing = document.querySelector('[data-team-members-showing]');
            if (!showing) return;

            var total = Number(meta && meta.total ? meta.total : 0);
            var page = Number(meta && meta.page ? meta.page : 1);
            var perPage = Number(meta && meta.perPage ? meta.perPage : 20);

            if (!rowCount || !total) {
                showing.textContent = 'Showing 0 - 0 of 0 entries';
                return;
            }

            var start = ((page - 1) * perPage) + 1;
            var end = Math.min(start + rowCount - 1, total);
            showing.textContent = 'Showing ' + start + ' - ' + end + ' of ' + total + ' entries';
        }

        function renderMembersPagination(meta) {
            var list = document.querySelector('[data-team-members-pagination]');
            if (!list) return;

            var total = Number(meta && meta.total ? meta.total : 0);
            var page = Number(meta && meta.page ? meta.page : 1);
            var perPage = Number(meta && meta.perPage ? meta.perPage : 20);
            var totalPages = Math.max(1, Math.ceil(total / Math.max(1, perPage)));

            if (totalPages <= 1) {
                list.innerHTML = '';
                return;
            }

            var html = '';
            html += '<li class="page-item ' + (page <= 1 ? 'disabled' : '') + '"><a href="#" class="page-link" data-team-members-page="' + (page - 1) + '">Prev</a></li>';
            for (var p = Math.max(1, page - 2); p <= Math.min(totalPages, page + 2); p++) {
                html += '<li class="page-item ' + (p === page ? 'active' : '') + '"><a href="#" class="page-link" data-team-members-page="' + p + '">' + p + '</a></li>';
            }
            html += '<li class="page-item ' + (page >= totalPages ? 'disabled' : '') + '"><a href="#" class="page-link" data-team-members-page="' + (page + 1) + '">Next</a></li>';
            list.innerHTML = html;
        }

        function loadMembers() {
            var params = new URLSearchParams({
                page: state.page,
                perPage: state.perPage,
                search: state.search,
                status: state.status,
            });

            api('GET', '/v1/hcm/teams/' + encodeURIComponent(state.teamId) + '/members?' + params.toString())
                .then(function (res) {
                    var payload = res.data || {};
                    var rows = payload.members || [];
                    renderMembersSummary(payload.team || {}, res.meta || {});
                    renderMembersRows(rows);
                    renderMembersShowing(res.meta || {}, rows.length);
                    renderMembersPagination(res.meta || {});
                })
                .catch(function (err) {
                    var body = document.querySelector('[data-team-members-body]');
                    if (body) {
                        var msg = (err.body && err.body.error && err.body.error.message) ? err.body.error.message : 'Failed to load team members.';
                        body.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-danger">' + esc(msg) + '</td></tr>';
                        body.setAttribute('data-hydrated', '1');
                    }
                });
        }

        function confirmTakeOut(memberName) {
            var label = memberName && String(memberName).trim() ? String(memberName).trim() : 'this member';
            if (window.ArcavUi && typeof window.ArcavUi.confirmDelete === 'function') {
                return window.ArcavUi.confirmDelete('Take out ' + label + ' from this team?', "Konfirmasi");
            }
            return Promise.resolve(window.confirm('Take out ' + label + ' from this team?'));
        }

        document.addEventListener('click', function (e) {
            var pageLink = e.target.closest('[data-team-members-page]');
            if (!pageLink) return;
            e.preventDefault();
            var nextPage = parseInt(pageLink.getAttribute('data-team-members-page') || '1', 10);
            if (nextPage < 1 || Number.isNaN(nextPage)) return;
            state.page = nextPage;
            loadMembers();
        });

        document.addEventListener('click', function (e) {
            var removeBtn = e.target.closest('[data-team-members-remove]');
            if (!removeBtn) return;

            e.preventDefault();
            var employeeId = Number(removeBtn.getAttribute('data-team-members-remove') || 0);
            if (!Number.isFinite(employeeId) || employeeId <= 0) return;

            var memberName = removeBtn.getAttribute('data-team-members-remove-name') || 'member';

            confirmTakeOut(memberName).then(function (ok) {
                if (!ok) return;

                removeBtn.disabled = true;
                api('POST', '/v1/hcm/teams/reassign-members', {
                    employee_ids: [employeeId],
                    source_team_id: Number(state.teamId),
                    target_team_id: null
                }).then(function (res) {
                    var affected = Number(res && res.data && res.data.affected_count ? res.data.affected_count : 0);
                    notify('Member removed from team (' + affected + ').', false);
                    loadMembers();
                }).catch(function (err) {
                    var msg = err && err.body && err.body.error && err.body.error.message
                        ? err.body.error.message
                        : 'Failed to take out member from team.';
                    notify(msg, true);
                }).finally(function () {
                    removeBtn.disabled = false;
                });
            });
        });

        var searchInput = document.querySelector('[data-team-members-search]');
        if (searchInput) {
            searchInput.addEventListener('change', function () {
                state.search = String(this.value || '').trim();
                state.page = 1;
                loadMembers();
            });
        }

        var statusInput = document.querySelector('[data-team-members-status]');
        if (statusInput) {
            statusInput.addEventListener('change', function () {
                state.status = String(this.value || 'all');
                state.page = 1;
                loadMembers();
            });
        }

        var perPageInput = document.querySelector('[data-team-members-per-page]');
        if (perPageInput) {
            perPageInput.addEventListener('change', function () {
                state.perPage = parseInt(this.value || '20', 10) || 20;
                state.page = 1;
                loadMembers();
            });
        }

        initAssignMembersPicker();

        document.addEventListener('click', function (e) {
            var openAssign = e.target.closest('[data-team-members-assign-open]');
            if (!openAssign) return;
            e.preventDefault();

            var select = document.querySelector('[data-team-members-assign-users]');
            if (select && window.jQuery && window.jQuery.fn && window.jQuery.fn.select2) {
                window.jQuery(select).val(null).trigger('change');
            }
            showAssignResult('', '');

            var modal = getAssignModalInstance();
            if (modal) modal.show();
        });

        var assignForm = document.querySelector('[data-team-members-assign-form]');
        if (assignForm) {
            assignForm.addEventListener('submit', function (e) {
                e.preventDefault();
                if (!ArcavValidation.validateForm(assignForm)) { return; }

                var select = this.querySelector('[data-team-members-assign-users]');
                var submitBtn = this.querySelector('[data-team-members-assign-submit]');
                var selectedRaw = select && window.jQuery ? (window.jQuery(select).val() || []) : [];
                var employeeIds = (selectedRaw || []).map(function (id) {
                    return Number(id);
                }).filter(function (id) {
                    return Number.isFinite(id) && id > 0;
                });

                if (!employeeIds.length) {
                    showAssignResult('warning', 'Pilih minimal 1 employee untuk diassign.');
                    return;
                }

                if (submitBtn) submitBtn.disabled = true;
                showAssignResult('info', 'Assigning selected members...');

                api('POST', '/v1/hcm/teams/reassign-members', {
                    employee_ids: employeeIds,
                    target_team_id: Number(state.teamId)
                }).then(function (res) {
                    var affected = Number(res && res.data && res.data.affected_count ? res.data.affected_count : 0);
                    showAssignResult('success', 'Berhasil assign ' + affected + ' employee ke team ini.');
                    notify('Members assigned successfully (' + affected + ').', false);
                    loadMembers();

                    window.setTimeout(function () {
                        if (select && window.jQuery && window.jQuery.fn && window.jQuery.fn.select2) {
                            window.jQuery(select).val(null).trigger('change');
                        }
                        var modal = getAssignModalInstance();
                        if (modal) modal.hide();
                    }, 500);
                }).catch(function (err) {
                    var msg = err && err.body && err.body.error && err.body.error.message
                        ? err.body.error.message
                        : 'Gagal assign members ke team.';
                    showAssignResult('danger', msg);
                }).finally(function () {
                    if (submitBtn) submitBtn.disabled = false;
                });
            });
        }

        loadMembers();
    }

    // Auto-init when DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            initTeamsPage();
            initTeamMembersPage();
        });
    } else {
        initTeamsPage();
        initTeamMembersPage();
    }

})(window);
