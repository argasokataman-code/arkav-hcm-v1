(function (window, document) {
    "use strict";
    var RETURN_STATE_KEY = "arcav_employees_return_state_v1";
    var selectedPreviewEmployeeId = null;
    var employeesTableState = {
        page: 1,
        perPage: 20,
        search: "",
        status: "",
        departmentId: "",
        designationId: "",
    };
    var employeesTableMeta = {
        page: 1,
        perPage: 20,
        total: 0,
    };

    function employeesListUrl(perPage, page) {
        var n = perPage != null ? perPage : 20;
        var p = page != null ? page : 1;
        return "/v1/hcm/employees?perPage=" + encodeURIComponent(n) + "&page=" + encodeURIComponent(p);
    }

    function requestAuthMe() {
        var url = "/v1/identity/auth/me";
        if (window.axios) {
            return window.axios({
                method: "get",
                url: url,
                headers: { Accept: "application/json" },
                withCredentials: true,
            }).then(function (res) {
                return res.data;
            }).catch(function (error) {
                return Promise.reject({
                    status: error && error.response ? error.response.status : 0,
                    data: error && error.response ? error.response.data : null,
                });
            });
        }
        return fetch(url, {
            headers: { Accept: "application/json" },
            credentials: "same-origin",
        }).then(function (res) {
            return res.json().catch(function () { return {}; }).then(function (data) {
                if (!res.ok) {
                    return Promise.reject({ status: res.status, data: data });
                }
                return data;
            });
        });
    }

    function requestEmployees(perPage, page) {
        var API_URL = employeesListUrl(perPage, page);

        if (window.axios) {
            return window.axios({
                method: "get",
                url: API_URL,
                headers: {
                    Accept: "application/json",
                },
                withCredentials: true,
            }).then(function (res) {
                return res.data;
            }).catch(function (error) {
                return Promise.reject({
                    status: error && error.response ? error.response.status : 0,
                    data: error && error.response ? error.response.data : null,
                });
            });
        }

        return fetch(API_URL, {
            headers: {
                Accept: "application/json",
            },
            credentials: "same-origin",
        }).then(function (res) {
            return res.json().catch(function () { return {}; }).then(function (data) {
                if (!res.ok) {
                    return Promise.reject({ status: res.status, data: data });
                }
                return data;
            });
        });
    }

    function requestEmployeesByState(state) {
        var params = new URLSearchParams();
        params.set("perPage", String(state.perPage || 20));
        params.set("page", String(state.page || 1));
        if (state.search) {
            params.set("search", state.search);
        }
        if (state.status) {
            params.set("status", state.status);
        }
        if (state.departmentId) {
            params.set("departmentId", state.departmentId);
        }
        if (state.designationId) {
            params.set("designationId", state.designationId);
        }
        return requestJson("get", "/v1/hcm/employees?" + params.toString(), null);
    }

    function requestAllEmployeesAggregated(perPage) {
        var size = perPage != null ? perPage : 100;
        function fetchPage(page, accumulated, metaForSummary) {
            return requestEmployees(size, page).then(function (payload) {
                if (!payload || payload.success !== true) {
                    return Promise.reject({ status: 0, data: payload });
                }
                var chunk = Array.isArray(payload.data) ? payload.data : [];
                var next = accumulated.concat(chunk);
                var pageMeta = payload.meta || {};
                var summaryMeta = metaForSummary || (pageMeta.summary ? pageMeta : null);
                var total = typeof pageMeta.total === "number" ? pageMeta.total : next.length;
                if (chunk.length < 1 || next.length >= total || page >= 50) {
                    return { success: true, data: next, meta: summaryMeta || pageMeta };
                }
                return fetchPage(page + 1, next, summaryMeta || pageMeta);
            });
        }
        return fetchPage(1, [], null);
    }

    function requestJson(method, url, payload) {
        var m = String(method || "get").toLowerCase();
        if (window.axios) {
            var cfg = {
                method: method,
                url: url,
                headers: { Accept: "application/json" },
                withCredentials: true,
            };
            if (m !== "get" && m !== "head") {
                cfg.data = payload || {};
            }
            return window.axios(cfg).then(function (res) {
                return res.data;
            }).catch(function (error) {
                return Promise.reject({
                    status: error && error.response ? error.response.status : 0,
                    data: error && error.response ? error.response.data : null,
                });
            });
        }

        var fetchOpts = {
            method: method.toUpperCase(),
            headers: {
                Accept: "application/json",
            },
            credentials: "same-origin",
        };
        if (m !== "get" && m !== "head") {
            fetchOpts.headers["Content-Type"] = "application/json";
            fetchOpts.body = JSON.stringify(payload || {});
        }
        return fetch(url, fetchOpts).then(function (res) {
            return res.json().catch(function () { return {}; }).then(function (data) {
                if (!res.ok) {
                    return Promise.reject({ status: res.status, data: data });
                }
                return data;
            });
        });
    }

    function requestFormData(method, url, formData) {
        if (window.axios) {
            return window.axios({
                method: method,
                url: url,
                headers: { Accept: "application/json" },
                data: formData,
                withCredentials: true,
            }).then(function (res) {
                return res.data;
            }).catch(function (error) {
                return Promise.reject({
                    status: error && error.response ? error.response.status : 0,
                    data: error && error.response ? error.response.data : null,
                });
            });
        }

        return fetch(url, {
            method: method.toUpperCase(),
            headers: { Accept: "application/json" },
            credentials: "same-origin",
            body: formData,
        }).then(function (res) {
            return res.json().catch(function () { return {}; }).then(function (data) {
                if (!res.ok) {
                    return Promise.reject({ status: res.status, data: data });
                }
                return data;
            });
        });
    }

    var orgDepartmentsFlat = [];
    var orgDesignationsFlat = [];
    var orgMastersPromise = null;

    function fillDesignationSelectForDepartment(selectEl, departmentId, preferredValue) {
        var pref = preferredValue != null ? String(preferredValue) : "";
        selectEl.innerHTML = '<option value="">— Pilih —</option>';
        orgDesignationsFlat.forEach(function (d) {
            var deptId = d.departmentId != null ? String(d.departmentId) : "";
            if (!departmentId || !deptId || deptId === String(departmentId)) {
                var opt = document.createElement("option");
                opt.value = String(d.id);
                opt.textContent = d.name || d.code || String(d.id);
                selectEl.appendChild(opt);
            }
        });
        if (pref) {
            var match = Array.prototype.slice.call(selectEl.options).some(function (o) {
                return o.value === pref;
            });
            if (match) {
                selectEl.value = pref;
            }
        }
    }

    function rebuildDepartmentSelectOptions() {
        document.querySelectorAll("[data-employee-org-department]").forEach(function (sel) {
            var cur = sel.value;
            sel.innerHTML = '<option value="">— Pilih —</option>';
            orgDepartmentsFlat.forEach(function (d) {
                var opt = document.createElement("option");
                opt.value = String(d.id);
                opt.textContent = d.name || d.code || String(d.id);
                sel.appendChild(opt);
            });
            if (cur) {
                var ok = Array.prototype.slice.call(sel.options).some(function (o) {
                    return o.value === cur;
                });
                if (ok) {
                    sel.value = cur;
                }
            }
            var form = sel.closest("form");
            var des = form ? form.querySelector("[data-employee-org-designation]") : null;
            if (des) {
                fillDesignationSelectForDepartment(des, sel.value, des.value);
            }
        });
    }

    function hydrateEmployeeOrgMasters() {
        return Promise.all([
            requestJson("get", "/v1/hcm/departments", null),
            requestJson("get", "/v1/hcm/designations", null),
        ]).then(function (results) {
            orgDepartmentsFlat = results[0] && results[0].success && Array.isArray(results[0].data) ? results[0].data : [];
            orgDesignationsFlat = results[1] && results[1].success && Array.isArray(results[1].data) ? results[1].data : [];
            rebuildDepartmentSelectOptions();
        });
    }

    function ensureEmployeeOrgMastersLoaded() {
        if (orgMastersPromise) {
            return orgMastersPromise;
        }
        orgMastersPromise = hydrateEmployeeOrgMasters().catch(function () {
            orgMastersPromise = null;
            return null;
        });
        return orgMastersPromise;
    }

    function bindEmployeeOrgDepartmentChange() {
        if (document.body.getAttribute("data-employee-org-dept-bound")) {
            return;
        }
        document.body.setAttribute("data-employee-org-dept-bound", "1");
        document.addEventListener("change", function (e) {
            var el = e.target && e.target.closest ? e.target.closest("[data-employee-org-department]") : null;
            if (!el) {
                return;
            }
            var form = el.closest("form");
            if (!form) {
                return;
            }
            var des = form.querySelector("[data-employee-org-designation]");
            if (!des) {
                return;
            }
            var keep = des.value;
            fillDesignationSelectForDepartment(des, el.value, keep);
        });
    }

    function escapeHtml(value) {
        return String(value || "")
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#39;");
    }

    function formatApiError(data, status) {
        if (window.ApiErrorHelper && typeof window.ApiErrorHelper.format === "function") {
            return window.ApiErrorHelper.format(data, status);
        }
        if (data && data.error && data.error.message) {
            return data.error.message;
        }
        if (data && data.message) {
            return data.message;
        }
        return status ? "Request failed (" + status + ")" : "Request failed";
    }

    function formatRupiah(value) {
        var n = Number(value || 0);
        if (!isFinite(n)) {
            n = 0;
        }
        return "Rp" + n.toLocaleString("id-ID", { minimumFractionDigits: 0, maximumFractionDigits: 0 });
    }

    function getCurrentListUrl() {
        return String(window.location.pathname || "") + String(window.location.search || "") + String(window.location.hash || "");
    }

    function buildEmployeeDetailUrl(employeeId) {
        return "/employee-details?id=" + encodeURIComponent(employeeId) + "&returnTo=" + encodeURIComponent(getCurrentListUrl());
    }

    function saveReturnState(employeeId) {
        try {
            window.sessionStorage.setItem(RETURN_STATE_KEY, JSON.stringify({
                url: getCurrentListUrl(),
                scrollY: window.scrollY || 0,
                selectedId: employeeId ? String(employeeId) : "",
                ts: Date.now()
            }));
        } catch (_e) {}
    }

    function restoreReturnStateIfAny() {
        try {
            var raw = window.sessionStorage.getItem(RETURN_STATE_KEY);
            if (!raw) {
                return;
            }
            var state = JSON.parse(raw);
            if (!state || state.url !== getCurrentListUrl()) {
                return;
            }
            window.setTimeout(function () {
                window.scrollTo(0, Number(state.scrollY || 0));
            }, 0);
            if (state.selectedId) {
                selectedPreviewEmployeeId = String(state.selectedId);
            }
        } catch (_e) {}
    }

    function updateActiveRowHighlight() {
        var rows = document.querySelectorAll("[data-employees-row-preview]");
        for (var i = 0; i < rows.length; i++) {
            var row = rows[i];
            var active = selectedPreviewEmployeeId && row.getAttribute("data-employees-row-preview") === String(selectedPreviewEmployeeId);
            row.classList.toggle("table-primary", !!active);
        }
    }

    function requestEmployeeDetail(employeeId) {
        if (!employeeId) {
            return Promise.resolve(null);
        }
        var apiUrl = "/v1/hcm/employees/" + encodeURIComponent(employeeId);

        if (window.axios) {
            return window.axios({
                method: "get",
                url: apiUrl,
                headers: { Accept: "application/json" },
                withCredentials: true
            }).then(function (res) {
                return res.data;
            }).catch(function (error) {
                return Promise.reject({
                    status: error && error.response ? error.response.status : 0,
                    data: error && error.response ? error.response.data : null,
                });
            });
        }

        return fetch(apiUrl, {
            headers: { Accept: "application/json" },
            credentials: "same-origin",
        }).then(function (res) {
            return res.json().catch(function () { return {}; }).then(function (data) {
                if (!res.ok) {
                    return Promise.reject({ status: res.status, data: data });
                }
                return data;
            });
        });
    }

    function renderList(rows) {
        var tbody = document.querySelector("[data-employees-list-body]");
        if (!tbody) {
            return;
        }

        if (!rows.length) {
            tbody.innerHTML = '<tr><td class="text-center text-muted py-4">No employees found.</td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td></tr>';
            tbody.setAttribute("data-hydrated", "1");
            return;
        }

        tbody.innerHTML = rows.map(function (row) {
            var st = row.employmentStatus || "active";
            var statusClass = st === "active" ? "success" : st === "probation" ? "warning" : "danger";
            var nameCell = row.profilePhotoUrl
                ? '<div class="d-flex align-items-center"><span class="avatar avatar-sm me-2"><img src="' + escapeHtml(row.profilePhotoUrl) + '" alt="Photo" class="rounded-circle w-100 h-100"></span><span>' + escapeHtml(row.fullName) + '</span></div>'
                : '<div class="d-flex align-items-center"><span class="avatar avatar-sm me-2 bg-primary-subtle text-primary rounded-circle d-inline-flex align-items-center justify-content-center">' + escapeHtml((row.fullName || "?").charAt(0).toUpperCase()) + '</span><span>' + escapeHtml(row.fullName) + '</span></div>';
            return (
                '<tr data-employees-row-preview="' + escapeHtml(row.id) + '" class="cursor-pointer">' +
                '<td><div class="form-check form-check-md"><input class="form-check-input" type="checkbox"></div></td>' +
                '<td><a href="' + buildEmployeeDetailUrl(row.id) + '" data-employee-detail-link data-employee-id="' + escapeHtml(row.id) + '">' + escapeHtml(row.employeeNo) + "</a></td>" +
                "<td>" + nameCell + "</td>" +
                "<td>" + escapeHtml(row.email) + "</td>" +
                "<td>" + escapeHtml(row.team || "—") + "</td>" +
                "<td>" + escapeHtml(row.departmentName || "—") + "</td>" +
                "<td>" + escapeHtml(row.designation || "Employee") + "</td>" +
                "<td>" + escapeHtml(row.joinDate || "-") + "</td>" +
                '<td><span class="badge badge-' + statusClass + ' d-inline-flex align-items-center badge-xs"><i class="ti ti-point-filled me-1"></i>' + escapeHtml(st) + "</span></td>" +
                '<td><div class="action-icon d-inline-flex">' +
                '<a href="javascript:void(0);" class="me-2" data-employee-edit-open data-employee-id="' + escapeHtml(row.id) + '" title="Edit"><i class="ti ti-edit"></i></a>' +
                '<a href="javascript:void(0);" class="me-2 ' + (row.profilePhotoUrl ? '' : 'text-muted disabled') + '" data-employees-photo-view data-photo-url="' + escapeHtml(row.profilePhotoUrl || '') + '" data-employee-name="' + escapeHtml(row.fullName || '') + '" title="View Photo"><i class="ti ti-photo"></i></a>' +
                '<a href="' + buildEmployeeDetailUrl(row.id) + '" class="me-2" data-employee-detail-link data-employee-id="' + escapeHtml(row.id) + '"><i class="ti ti-eye"></i></a>' +
                "</div></td>" +
                "</tr>"
            );
        }).join("");
        tbody.setAttribute("data-hydrated", "1");
    }

    function renderListMessage(message) {
        var tbody = document.querySelector("[data-employees-list-body]");
        if (!tbody) return;
        tbody.innerHTML = '<tr><td class="text-center text-muted py-4">' + escapeHtml(message) + '</td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td></tr>';
        tbody.setAttribute("data-hydrated", "1");
    }

    function renderGrid(rows) {
        var gridBody = document.querySelector("[data-employees-grid-body]");
        if (!gridBody) {
            return;
        }

        if (!rows.length) {
            gridBody.innerHTML = '<div class="col-12"><div class="alert alert-light text-center mb-0">No employees found.</div></div>';
            return;
        }

        gridBody.innerHTML = rows.map(function (row) {
            var st = row.employmentStatus || "active";
            var avatarHtml = row.profilePhotoUrl
                ? '<img src="' + escapeHtml(row.profilePhotoUrl) + '" alt="Photo" class="rounded-circle w-100 h-100">'
                : '<span class="avatar-title rounded-circle bg-primary-subtle text-primary">' + escapeHtml((row.fullName || "?").charAt(0).toUpperCase()) + "</span>";
            return (
                '<div class="col-xl-3 col-lg-4 col-md-6">' +
                '<div class="card"><div class="card-body">' +
                '<div class="text-center mb-3">' +
                '<a href="' + buildEmployeeDetailUrl(row.id) + '" data-employee-detail-link data-employee-id="' + escapeHtml(row.id) + '" class="avatar avatar-xl avatar-rounded border p-1 border-primary rounded-circle">' +
                avatarHtml +
                "</a>" +
                '<h6 class="mb-1 mt-3"><a href="' + buildEmployeeDetailUrl(row.id) + '" data-employee-detail-link data-employee-id="' + escapeHtml(row.id) + '">' + escapeHtml(row.fullName) + "</a></h6>" +
                '<span class="badge badge-purple-transparent fs-10 fw-medium">' + escapeHtml(row.designation || "Employee") + "</span>" +
                "</div>" +
                '<p class="mb-1 text-center"><strong>ID:</strong> ' + escapeHtml(row.employeeNo) + "</p>" +
                '<p class="mb-1 text-center"><strong>Team:</strong> ' + escapeHtml(row.team || "—") + "</p>" +
                '<p class="mb-1 text-center"><strong>Dept:</strong> ' + escapeHtml(row.departmentName || "—") + "</p>" +
                '<p class="mb-1 text-center"><strong>Email:</strong> ' + escapeHtml(row.email) + "</p>" +
                '<p class="mb-0 text-center"><strong>Status:</strong> ' + escapeHtml(st) + "</p>" +
                "</div></div></div>"
            );
        }).join("");
        gridBody.setAttribute("data-hydrated", "1");
    }

    function bindQuickPreview() {
        var panelEl = document.getElementById("employee_quick_preview");
        var contentEl = document.querySelector("[data-employee-quick-preview-content]");
        var openLinkEl = document.querySelector("[data-employee-quick-open-link]");
        if (!panelEl || !contentEl || !openLinkEl || !window.bootstrap || !window.bootstrap.Offcanvas) {
            return;
        }
        var offcanvas = window.bootstrap.Offcanvas.getOrCreateInstance(panelEl);

        function renderPreviewLoading() {
            contentEl.innerHTML = '<p class="text-muted mb-0">Loading employee preview...</p>';
            openLinkEl.classList.add("d-none");
        }

        function renderPreviewError(message) {
            contentEl.innerHTML = '<div class="alert alert-light border mb-0">' + escapeHtml(message || "Gagal memuat preview employee.") + '</div>';
            openLinkEl.classList.add("d-none");
        }

        function renderPreview(item) {
            if (!item) {
                renderPreviewError("Employee tidak ditemukan.");
                return;
            }
            contentEl.innerHTML =
                '<div class="mb-2"><h5 class="mb-1">' + escapeHtml(item.fullName || "-") + '</h5>' +
                '<span class="badge badge-soft-dark">' + escapeHtml(item.designation || "Employee") + '</span></div>' +
                '<div class="border rounded p-2 mb-2">' +
                '<div class="d-flex justify-content-between mb-1"><span class="text-muted">Employee ID</span><strong>' + escapeHtml(item.employeeNo || "-") + "</strong></div>" +
                '<div class="d-flex justify-content-between mb-1"><span class="text-muted">Team</span><strong>' + escapeHtml(item.team || "-") + "</strong></div>" +
                '<div class="d-flex justify-content-between mb-1"><span class="text-muted">Department</span><strong>' + escapeHtml(item.departmentName || "-") + "</strong></div>" +
                '<div class="d-flex justify-content-between mb-1"><span class="text-muted">Status</span><strong>' + escapeHtml(item.employmentStatus || "-") + "</strong></div>" +
                '<div class="d-flex justify-content-between mb-1"><span class="text-muted">Join Date</span><strong>' + escapeHtml(item.joinDate || "-") + "</strong></div>" +
                '<div class="d-flex justify-content-between mb-1"><span class="text-muted">Base Salary</span><strong>' + escapeHtml(formatRupiah(item.baseSalary)) + "</strong></div>" +
                '<div class="d-flex justify-content-between"><span class="text-muted">Fixed Allowance</span><strong>' + escapeHtml(formatRupiah(item.fixedAllowance)) + "</strong></div>" +
                "</div>" +
                '<div class="small text-muted">' + escapeHtml(item.email || "-") + "</div>" +
                '<div class="small text-muted">' + escapeHtml(item.phone || "-") + "</div>";

            var targetUrl = buildEmployeeDetailUrl(item.id);
            openLinkEl.href = targetUrl;
            openLinkEl.setAttribute("data-employee-id", String(item.id));
            openLinkEl.classList.remove("d-none");
        }

        function openEmployeePreview(employeeId) {
            if (!employeeId) {
                return;
            }
            selectedPreviewEmployeeId = String(employeeId);
            updateActiveRowHighlight();
            renderPreviewLoading();
            offcanvas.show();

            requestEmployeeDetail(employeeId)
                .then(function (payload) {
                    if (!payload || payload.success !== true) {
                        renderPreviewError(formatApiError(payload, 0));
                        return;
                    }
                    renderPreview(payload.data || null);
                })
                .catch(function (error) {
                    if (error && window.AuthApi && window.AuthApi.handleUnauthorizedFromApi(error.status, error.data)) {
                        return;
                    }
                    renderPreviewError(formatApiError(error && error.data, error && error.status));
                });
        }

        document.addEventListener("click", function (event) {
            var detailLink = event.target.closest("[data-employee-detail-link]");
            if (detailLink) {
                saveReturnState(detailLink.getAttribute("data-employee-id") || selectedPreviewEmployeeId || "");
                return;
            }

            var row = event.target.closest("[data-employees-row-preview]");
            if (!row) {
                return;
            }

            var ignore = event.target.closest("a, button, input, label, .form-check");
            if (ignore) {
                return;
            }
            event.preventDefault();
            openEmployeePreview(row.getAttribute("data-employees-row-preview"));
        });

        if (selectedPreviewEmployeeId) {
            openEmployeePreview(selectedPreviewEmployeeId);
        }
    }

    function bindEmployeeCompensationForms() {
        var addForm = document.querySelector("[data-employee-add-form]");
        var editForm = document.querySelector("[data-employee-edit-form]");

        function readField(form, key) {
            var el = form ? form.querySelector('[data-employee-add-field="' + key + '"], [data-employee-edit-field="' + key + '"]') : null;
            return el ? String(el.value || "").trim() : "";
        }

        function writeField(form, key, value) {
            var el = form ? form.querySelector('[data-employee-add-field="' + key + '"], [data-employee-edit-field="' + key + '"]') : null;
            if (el) {
                el.value = value == null ? "" : String(value);
            }
        }

        function readText(form, key) {
            var value = readField(form, key);
            return value === "" ? null : value;
        }

        function readNumberOrNull(value) {
            var raw = String(value == null ? "" : value).trim();
            if (raw === "") {
                return null;
            }
            var n = Number(raw);
            return isFinite(n) && n >= 0 ? n : null;
        }

        function readInteger(form, key) {
            var raw = readField(form, key);
            if (!raw) {
                return null;
            }
            var n = parseInt(raw, 10);
            return isNaN(n) ? null : n;
        }

        function updateModalEmployeeNo(form, item) {
            var modal = form ? form.closest(".modal") : null;
            var label = modal ? modal.querySelector("[data-employee-modal-employee-no]") : null;
            if (!label) {
                return;
            }
            label.textContent = item && item.employeeNo ? String(item.employeeNo) : "Auto-generated after save";
        }

        function setStep(form, index) {
            if (!form) {
                return;
            }
            var panes = Array.prototype.slice.call(form.querySelectorAll("[data-employee-step-pane]"));
            var triggers = Array.prototype.slice.call(form.querySelectorAll("[data-employee-step-trigger]"));
            if (!panes.length) {
                return;
            }
            var safeIndex = Math.max(0, Math.min(index, panes.length - 1));
            form.setAttribute("data-employee-step-index", String(safeIndex));

            panes.forEach(function (pane, paneIndex) {
                pane.classList.toggle("d-none", paneIndex !== safeIndex);
            });
            triggers.forEach(function (trigger, triggerIndex) {
                var active = triggerIndex === safeIndex;
                trigger.classList.toggle("active", active);
                trigger.classList.toggle("btn-primary", active);
                trigger.classList.toggle("text-white", active);
                trigger.classList.toggle("btn-light", !active);
            });

            var prevBtn = form.querySelector("[data-employee-step-prev]");
            var nextBtn = form.querySelector("[data-employee-step-next]");
            var submitBtn = form.querySelector("[data-employee-step-submit]");
            if (prevBtn) {
                prevBtn.classList.toggle("d-none", safeIndex === 0);
            }
            if (nextBtn) {
                nextBtn.classList.toggle("d-none", safeIndex >= panes.length - 1);
            }
            if (submitBtn) {
                submitBtn.classList.toggle("d-none", safeIndex < panes.length - 1);
            }

            var caption = form.querySelector("[data-employee-step-caption]");
            if (caption) {
                caption.textContent = "Step " + (safeIndex + 1) + " of " + panes.length;
            }
        }

        function normalizeContractTypeValue(value) {
            var raw = String(value || "").trim().toLowerCase();
            if (!raw || raw === "pkwtt") {
                return "permanent";
            }
            if (raw === "pkwt") {
                return "contract";
            }
            return raw === "contract" ? "contract" : "permanent";
        }

        function showValidationToast(message) {
            if (window.ArcavUi && window.ArcavUi.showToast) {
                window.ArcavUi.showToast(message, "warning");
            }
        }

        function toggleContractEndDateVisibility(form) {
            if (!form) {
                return;
            }
            var contractType = normalizeContractTypeValue(readText(form, "contractType"));
            var wrap = form.querySelector("[data-employee-contract-end-wrap]");
            var input = form.querySelector('[data-employee-add-field="contractEndDate"], [data-employee-edit-field="contractEndDate"]');
            if (!wrap || !input) {
                return;
            }
            var isContract = contractType === "contract";
            wrap.classList.toggle("d-none", !isContract);
            input.required = isContract;
            input.disabled = !isContract;
            if (!isContract) {
                input.value = "";
            }
        }

        function validateCurrentStep(form) {
            if (!form) {
                return true;
            }
            toggleContractEndDateVisibility(form);
            var stepIndex = Number(form.getAttribute("data-employee-step-index") || 0);
            var pane = form.querySelector('[data-employee-step-pane="' + stepIndex + '"]');
            if (pane) {
                var fields = pane.querySelectorAll("input, select, textarea");
                for (var i = 0; i < fields.length; i += 1) {
                    if (fields[i].disabled) {
                        continue;
                    }
                    if (typeof fields[i].reportValidity === "function" && !fields[i].reportValidity()) {
                        return false;
                    }
                }
            }

            var nik = readField(form, "nik");
            if (nik && !/^[0-9]{16}$/.test(nik)) {
                showValidationToast("NIK wajib tepat 16 digit angka.");
                return false;
            }

            var phone = readField(form, "phone");
            if (phone && !/^[0-9]{10,13}$/.test(phone)) {
                showValidationToast("Nomor telepon wajib 10-13 digit angka.");
                return false;
            }

            var nationalityInput = form.querySelector('[data-employee-add-field="nationality"], [data-employee-edit-field="nationality"]');
            if (nationalityInput) {
                nationalityInput.value = "Indonesia";
            }

            var startDate = readField(form, "startDate");
            var probationEndDate = readField(form, "probationEndDate");
            if (startDate && probationEndDate && probationEndDate < startDate) {
                showValidationToast("Probation end date tidak boleh lebih awal dari effective start date.");
                return false;
            }

            var contractType = normalizeContractTypeValue(readText(form, "contractType"));
            var contractStartDate = readField(form, "contractStartDate");
            var contractEndDate = readField(form, "contractEndDate");
            if (contractStartDate && contractEndDate && contractEndDate < contractStartDate) {
                showValidationToast("Contract end date tidak boleh lebih awal dari contract start date.");
                return false;
            }
            if (contractType === "contract" && !contractEndDate) {
                showValidationToast("Contract end date wajib diisi untuk contract.");
                return false;
            }
            if (contractType === "permanent" && contractEndDate) {
                showValidationToast("Contract end date tidak boleh diisi untuk permanent.");
                return false;
            }

            var baseSalary = readField(form, "baseSalary");
            if (stepIndex === 2 && (!/^[0-9]+$/.test(baseSalary) || Number(baseSalary) < 0)) {
                showValidationToast("Base salary wajib angka 0 atau lebih besar.");
                return false;
            }

            if (stepIndex === 4) {
                var contacts = collectRepeatable(form, "emergencyContacts");
                var hasValidEmergencyContact = contacts.some(function (item) {
                    return item && item.name && item.relationship && /^[0-9]{10,13}$/.test(String(item.phone || ""));
                });
                if (!hasValidEmergencyContact) {
                    showValidationToast("Minimal satu emergency contact dengan nama, hubungan, dan nomor telepon valid wajib diisi.");
                    return false;
                }
            }

            return true;
        }

        function addRepeatableRow(form, type, values) {
            var container = form ? form.querySelector('[data-employee-repeatable="' + type + '"]') : null;
            var template = form ? form.querySelector('[data-employee-repeatable-template="' + type + '"]') : null;
            if (!container || !template || !template.content) {
                return;
            }
            var fragment = template.content.cloneNode(true);
            var row = fragment.querySelector("[data-repeat-row]");
            if (!row) {
                return;
            }
            Array.prototype.forEach.call(row.querySelectorAll("[data-repeat-key]"), function (input) {
                var key = input.getAttribute("data-repeat-key");
                var nextValue = values && values[key] != null ? values[key] : "";
                input.value = nextValue;
            });
            container.appendChild(fragment);
        }

        function resetRepeatable(form, type, items) {
            var container = form ? form.querySelector('[data-employee-repeatable="' + type + '"]') : null;
            if (!container) {
                return;
            }
            container.innerHTML = "";
            var list = Array.isArray(items) && items.length ? items : [{}];
            list.forEach(function (item) {
                addRepeatableRow(form, type, item || {});
            });
        }

        function collectRepeatable(form, type) {
            var rows = form ? form.querySelectorAll('[data-employee-repeatable="' + type + '"] [data-repeat-row]') : [];
            var output = [];
            Array.prototype.forEach.call(rows, function (row) {
                var item = {};
                var hasValue = false;
                Array.prototype.forEach.call(row.querySelectorAll("[data-repeat-key]"), function (input) {
                    var key = input.getAttribute("data-repeat-key");
                    var value = String(input.value || "").trim();
                    if (value !== "") {
                        hasValue = true;
                    }
                    if (key === "startYear" || key === "endYear") {
                        item[key] = value === "" ? null : parseInt(value, 10);
                    } else {
                        item[key] = value === "" ? null : value;
                    }
                });
                if (hasValue) {
                    output.push(item);
                }
            });
            return output;
        }

        function resetFormState(form) {
            if (!form) {
                return;
            }
            form.reset();
            form.removeAttribute("data-employee-id");
            form.removeAttribute("data-employee-edit-org-snapshot-dept");
            form.removeAttribute("data-employee-edit-org-snapshot-des");
            writeField(form, "nationality", "Indonesia");
            updateModalEmployeeNo(form, null);
            resetRepeatable(form, "emergencyContacts", []);
            resetRepeatable(form, "educationItems", []);
            resetRepeatable(form, "experienceItems", []);
            toggleContractEndDateVisibility(form);
            setStep(form, 0);
        }

        function buildPayload(form, isEdit) {
            var taxStatusValue = readText(form, "taxStatus");
            var ptkpStatusValue = readText(form, "ptkpStatus") || taxStatusValue;
            var contractType = normalizeContractTypeValue(readText(form, "contractType"));
            var payload = {
                name: readField(form, "name"),
                email: readField(form, "email"),
                team: readText(form, "team"),
                phone: readText(form, "phone"),
                nik: readText(form, "nik"),
                address: readText(form, "address"),
                placeOfBirth: readText(form, "placeOfBirth"),
                dateOfBirth: readText(form, "dateOfBirth"),
                gender: readText(form, "gender"),
                maritalStatus: readText(form, "maritalStatus"),
                religion: readText(form, "religion"),
                nationality: "Indonesia",
                bio: readText(form, "bio"),
                employmentStatus: readText(form, "employmentStatus") || "active",
                employeeType: readText(form, "employeeType"),
                startDate: readText(form, "startDate"),
                probationEndDate: readText(form, "probationEndDate"),
                baseSalary: readNumberOrNull(readField(form, "baseSalary")),
                fixedAllowance: readNumberOrNull(readField(form, "fixedAllowance")),
                salaryType: readText(form, "salaryType") || "monthly",
                contractType: contractType,
                contractStatus: readText(form, "contractStatus") || "active",
                contractStartDate: readText(form, "contractStartDate"),
                contractEndDate: contractType === "contract" ? readText(form, "contractEndDate") : null,
                bankName: readText(form, "bankName"),
                bankAccountNo: readText(form, "bankAccountNo"),
                bankAccountHolderName: readText(form, "bankAccountHolderName"),
                bankIfscCode: readText(form, "bankIfscCode"),
                bankBranch: readText(form, "bankBranch"),
                npwp: readText(form, "npwp"),
                taxStatus: taxStatusValue,
                ptkpStatus: ptkpStatusValue,
                bpjsKesehatanNo: readText(form, "bpjsKesehatanNo"),
                bpjsKetenagakerjaanNo: readText(form, "bpjsKetenagakerjaanNo"),
                emergencyContacts: collectRepeatable(form, "emergencyContacts"),
                educationItems: collectRepeatable(form, "educationItems"),
                experienceItems: collectRepeatable(form, "experienceItems")
            };

            var departmentId = readInteger(form, "departmentId");
            var designationId = readInteger(form, "designationId");
            payload.departmentId = departmentId;
            payload.designationId = designationId;

            if (!isEdit) {
                payload.password = readField(form, "password");
                payload.confirmPassword = readField(form, "confirmPassword");
            }

            return payload;
        }

        function hydrateEditForm(item) {
            if (!editForm || !item) {
                return;
            }

            writeField(editForm, "name", item.fullName || "");
            writeField(editForm, "email", item.email || "");
            writeField(editForm, "team", item.team || "");
            writeField(editForm, "phone", item.phone && item.phone !== "-" ? item.phone : "");
            writeField(editForm, "nik", item.nik || (item.personal && item.personal.nik ? item.personal.nik : ""));
            writeField(editForm, "address", item.address && item.address !== "-" ? item.address : "");
            writeField(editForm, "placeOfBirth", item.placeOfBirth || (item.personal && item.personal.placeOfBirth ? item.personal.placeOfBirth : ""));
            writeField(editForm, "dateOfBirth", item.dateOfBirth || (item.personal && item.personal.dateOfBirth ? item.personal.dateOfBirth : ""));
            writeField(editForm, "gender", item.gender || (item.personal && item.personal.gender ? item.personal.gender : ""));
            writeField(editForm, "maritalStatus", item.maritalStatus || (item.personal && item.personal.maritalStatus ? item.personal.maritalStatus : ""));
            writeField(editForm, "religion", item.religion || (item.personal && item.personal.religion ? item.personal.religion : ""));
            writeField(editForm, "nationality", item.nationality || (item.personal && item.personal.nationality ? item.personal.nationality : "Indonesia"));
            writeField(editForm, "bio", item.bio && item.bio !== "-" ? item.bio : "");
            writeField(editForm, "employmentStatus", item.employmentStatus || "active");
            writeField(editForm, "employeeType", item.employeeType || "");
            writeField(editForm, "startDate", item.startDate || item.joinDate || "");
            writeField(editForm, "probationEndDate", item.employmentHistory && item.employmentHistory[0] && item.employmentHistory[0].probationEndDate ? item.employmentHistory[0].probationEndDate : "");
            writeField(editForm, "baseSalary", item.baseSalary != null ? String(Math.round(Number(item.baseSalary || 0))) : "");
            writeField(editForm, "fixedAllowance", item.fixedAllowance != null ? String(Math.round(Number(item.fixedAllowance || 0))) : "");
            writeField(editForm, "salaryType", item.compensation && item.compensation.salaryType ? item.compensation.salaryType : "monthly");
            writeField(editForm, "contractType", normalizeContractTypeValue(item.contract && item.contract.contractType ? item.contract.contractType : (item.contractType || "permanent")));
            writeField(editForm, "contractStatus", item.contract && item.contract.status ? item.contract.status : "active");
            writeField(editForm, "contractStartDate", item.contract && item.contract.startDate ? item.contract.startDate : (item.contractStartDate || ""));
            writeField(editForm, "contractEndDate", item.contract && item.contract.endDate ? item.contract.endDate : (item.contractEndDate || ""));
            toggleContractEndDateVisibility(editForm);
            writeField(editForm, "bankName", item.bank && item.bank.name && item.bank.name !== "-" ? item.bank.name : "");
            writeField(editForm, "bankAccountNo", item.bank && item.bank.accountNo && item.bank.accountNo !== "-" ? item.bank.accountNo : "");
            writeField(editForm, "bankAccountHolderName", item.bank && item.bank.accountHolderName && item.bank.accountHolderName !== "-" ? item.bank.accountHolderName : "");
            writeField(editForm, "bankIfscCode", item.bank && item.bank.ifscCode && item.bank.ifscCode !== "-" ? item.bank.ifscCode : "");
            writeField(editForm, "bankBranch", item.bank && item.bank.branch && item.bank.branch !== "-" ? item.bank.branch : "");
            writeField(editForm, "npwp", item.taxProfile && item.taxProfile.npwp ? item.taxProfile.npwp : "");
            writeField(editForm, "taxStatus", item.taxProfile && item.taxProfile.taxStatus ? item.taxProfile.taxStatus : "");
            writeField(editForm, "ptkpStatus", item.taxProfile && item.taxProfile.ptkpStatus ? item.taxProfile.ptkpStatus : "");
            writeField(editForm, "bpjsKesehatanNo", item.benefits && item.benefits.bpjsKesehatanNo ? item.benefits.bpjsKesehatanNo : "");
            writeField(editForm, "bpjsKetenagakerjaanNo", item.benefits && item.benefits.bpjsKetenagakerjaanNo ? item.benefits.bpjsKetenagakerjaanNo : "");

            writeField(editForm, "departmentId", item.departmentId != null && item.departmentId !== "" ? String(item.departmentId) : "");
            var depEl = editForm.querySelector("[data-employee-org-department]");
            var desEl = editForm.querySelector("[data-employee-org-designation]");
            if (depEl && desEl) {
                fillDesignationSelectForDepartment(desEl, depEl.value, item.designationId != null && item.designationId !== "" ? String(item.designationId) : "");
            } else {
                writeField(editForm, "designationId", item.designationId != null && item.designationId !== "" ? String(item.designationId) : "");
            }

            editForm.setAttribute("data-employee-edit-org-snapshot-dept", item.departmentId != null && item.departmentId !== "" ? String(item.departmentId) : "");
            editForm.setAttribute("data-employee-edit-org-snapshot-des", item.designationId != null && item.designationId !== "" ? String(item.designationId) : "");

            resetRepeatable(editForm, "emergencyContacts", Array.isArray(item.emergencyContacts) ? item.emergencyContacts : []);
            resetRepeatable(editForm, "educationItems", Array.isArray(item.educationItems) ? item.educationItems : []);
            resetRepeatable(editForm, "experienceItems", Array.isArray(item.experienceItems) ? item.experienceItems : []);
            updateModalEmployeeNo(editForm, item);
            setStep(editForm, 0);
        }

        [addForm, editForm].forEach(function (form) {
            if (!form || form.getAttribute("data-employee-step-bound") === "1") {
                return;
            }
            form.setAttribute("data-employee-step-bound", "1");
            resetRepeatable(form, "emergencyContacts", []);
            resetRepeatable(form, "educationItems", []);
            resetRepeatable(form, "experienceItems", []);
            setStep(form, 0);

            form.addEventListener("change", function (event) {
                var contractTypeInput = event.target && event.target.closest ? event.target.closest('[data-employee-add-field="contractType"], [data-employee-edit-field="contractType"]') : null;
                if (contractTypeInput) {
                    toggleContractEndDateVisibility(form);
                }
            });

            form.addEventListener("click", function (event) {
                var nextBtn = event.target.closest("[data-employee-step-next]");
                if (nextBtn) {
                    event.preventDefault();
                    if (!validateCurrentStep(form)) {
                        return;
                    }
                    setStep(form, Number(form.getAttribute("data-employee-step-index") || 0) + 1);
                    return;
                }

                var prevBtn = event.target.closest("[data-employee-step-prev]");
                if (prevBtn) {
                    event.preventDefault();
                    setStep(form, Number(form.getAttribute("data-employee-step-index") || 0) - 1);
                    return;
                }

                var trigger = event.target.closest("[data-employee-step-trigger]");
                if (trigger) {
                    event.preventDefault();
                    var targetIndex = parseInt(trigger.getAttribute("data-employee-step-trigger"), 10) || 0;
                    var currentIndex = Number(form.getAttribute("data-employee-step-index") || 0);
                    if (targetIndex > currentIndex && !validateCurrentStep(form)) {
                        return;
                    }
                    setStep(form, targetIndex);
                    return;
                }

                var addRepeat = event.target.closest("[data-employee-repeat-add]");
                if (addRepeat) {
                    event.preventDefault();
                    addRepeatableRow(form, addRepeat.getAttribute("data-employee-repeat-add"), {});
                    return;
                }

                var removeRepeat = event.target.closest("[data-employee-repeat-remove]");
                if (removeRepeat) {
                    event.preventDefault();
                    var row = removeRepeat.closest("[data-repeat-row]");
                    var parent = row ? row.parentNode : null;
                    if (row && row.parentNode) {
                        row.parentNode.removeChild(row);
                    }
                    if (parent && !parent.querySelector("[data-repeat-row]")) {
                        addRepeatableRow(form, parent.getAttribute("data-employee-repeatable"), {});
                    }
                }
            });
        });

        var addModalEl = document.getElementById("add_employee");
        if (addModalEl && addForm && addModalEl.getAttribute("data-employee-modal-bound") !== "1") {
            addModalEl.setAttribute("data-employee-modal-bound", "1");
            addModalEl.addEventListener("show.bs.modal", function () {
                resetFormState(addForm);
            });
        }

        if (addForm) {
            addForm.addEventListener("submit", function (event) {
                event.preventDefault();
                if (!validateCurrentStep(addForm)) {
                    return;
                }
                var payload = buildPayload(addForm, false);
                requestJson("post", "/v1/hcm/employees", payload).then(function (resp) {
                    if (!resp || resp.success !== true) {
                        window.ArcavUi && window.ArcavUi.showToast && window.ArcavUi.showToast(formatApiError(resp, 0), "danger");
                        return;
                    }
                    if (window.ArcavUi && window.ArcavUi.showToast) {
                        window.ArcavUi.showToast("Employee berhasil ditambahkan.", "success");
                    }
                    resetFormState(addForm);
                    if (window.bootstrap && addModalEl) {
                        window.bootstrap.Modal.getOrCreateInstance(addModalEl).hide();
                    }
                    loadEmployeesData();
                }).catch(function (error) {
                    if (error && window.AuthApi && window.AuthApi.handleUnauthorizedFromApi(error.status, error.data)) {
                        return;
                    }
                    if (window.ArcavUi && window.ArcavUi.showToast) {
                        window.ArcavUi.showToast(formatApiError(error && error.data, error && error.status), "danger");
                    }
                });
            });
        }

        document.addEventListener("click", function (event) {
            var openEdit = event.target.closest("[data-employee-edit-open]");
            if (!openEdit || !editForm) {
                return;
            }
            event.preventDefault();
            var employeeId = String(openEdit.getAttribute("data-employee-id") || "");
            if (!employeeId) {
                return;
            }
            editForm.setAttribute("data-employee-id", employeeId);
            requestEmployeeDetail(employeeId).then(function (payload) {
                var item = payload && payload.success === true ? payload.data : null;
                if (!item) {
                    throw { status: 0, data: payload };
                }
                hydrateEditForm(item);
                if (window.bootstrap && window.bootstrap.Modal) {
                    var modalEl = document.getElementById("edit_employee");
                    if (modalEl) {
                        window.bootstrap.Modal.getOrCreateInstance(modalEl).show();
                    }
                }
            }).catch(function (error) {
                if (window.ArcavUi && window.ArcavUi.showToast) {
                    window.ArcavUi.showToast(formatApiError(error && error.data, error && error.status), "danger");
                }
            });
        });

        if (editForm) {
            editForm.addEventListener("submit", function (event) {
                event.preventDefault();
                if (!validateCurrentStep(editForm)) {
                    return;
                }
                var employeeId = String(editForm.getAttribute("data-employee-id") || "");
                if (!employeeId) {
                    if (window.ArcavUi && window.ArcavUi.showToast) {
                        window.ArcavUi.showToast("Pilih employee yang akan diupdate terlebih dahulu.", "warning");
                    }
                    return;
                }
                var payload = buildPayload(editForm, true);
                requestJson("put", "/v1/hcm/employees/" + encodeURIComponent(employeeId), payload).then(function (resp) {
                    if (!resp || resp.success !== true) {
                        window.ArcavUi && window.ArcavUi.showToast && window.ArcavUi.showToast(formatApiError(resp, 0), "danger");
                        return;
                    }
                    if (window.ArcavUi && window.ArcavUi.showToast) {
                        window.ArcavUi.showToast("Data employee berhasil diperbarui.", "success");
                    }
                    if (window.bootstrap) {
                        var modalEl = document.getElementById("edit_employee");
                        if (modalEl) {
                            window.bootstrap.Modal.getOrCreateInstance(modalEl).hide();
                        }
                    }
                    loadEmployeesData();
                }).catch(function (error) {
                    if (error && window.AuthApi && window.AuthApi.handleUnauthorizedFromApi(error.status, error.data)) {
                        return;
                    }
                    if (window.ArcavUi && window.ArcavUi.showToast) {
                        window.ArcavUi.showToast(formatApiError(error && error.data, error && error.status), "danger");
                    }
                });
            });
        }
    }

    function bindEmployeePhotoModalPreview() {
        if (document.body.getAttribute("data-employees-photo-modal-bound") === "1") {
            return;
        }
        document.body.setAttribute("data-employees-photo-modal-bound", "1");

        var modalEl = document.getElementById("employees_photo_preview_modal");
        var imageEl = document.querySelector("[data-employees-photo-modal-image]");
        var emptyEl = document.querySelector("[data-employees-photo-modal-empty]");
        var titleEl = document.querySelector("[data-employees-photo-modal-title]");
        if (!modalEl || !window.bootstrap || !window.bootstrap.Modal) {
            return;
        }
        var modal = window.bootstrap.Modal.getOrCreateInstance(modalEl);

        document.addEventListener("click", function (event) {
            var trigger = event.target.closest("[data-employees-photo-view]");
            if (!trigger) {
                return;
            }
            event.preventDefault();
            var url = String(trigger.getAttribute("data-photo-url") || "").trim();
            var employeeName = String(trigger.getAttribute("data-employee-name") || "Employee").trim();

            if (titleEl) {
                titleEl.textContent = employeeName ? (employeeName + " - Profile Photo") : "Employee Photo";
            }
            if (url) {
                if (imageEl) {
                    imageEl.src = url;
                    imageEl.classList.remove("d-none");
                }
                if (emptyEl) {
                    emptyEl.classList.add("d-none");
                }
            } else {
                if (imageEl) {
                    imageEl.src = "";
                    imageEl.classList.add("d-none");
                }
                if (emptyEl) {
                    emptyEl.classList.remove("d-none");
                }
            }
            modal.show();
        });
    }

    function bindSalaryBulkUpload() {
        var form = document.querySelector("[data-employee-bulk-upload-form]");
        if (!form || form.getAttribute("data-bulk-upload-bound") === "1") {
            return;
        }
        form.setAttribute("data-bulk-upload-bound", "1");

        var resultBox = form.querySelector("[data-employee-bulk-upload-results]");
        var fileInput = form.querySelector("[data-employee-bulk-upload-file]");
        var modalEl = document.getElementById("employee_bulk_upload");

        function renderBulkResult(kind, title, lines) {
            if (!resultBox) {
                return;
            }
            var list = Array.isArray(lines) ? lines.filter(Boolean) : [];
            resultBox.className = "alert alert-" + kind + " mb-0";
            resultBox.classList.remove("d-none");
            resultBox.innerHTML = '<strong class="d-block mb-1">' + escapeHtml(title) + '</strong>' +
                (list.length ? ('<ul class="mb-0 ps-3">' + list.map(function (line) {
                    return '<li>' + escapeHtml(line) + '</li>';
                }).join("") + '</ul>') : "");
        }

        function clearBulkResult() {
            if (!resultBox) {
                return;
            }
            resultBox.className = "alert d-none mb-0";
            resultBox.textContent = "";
            resultBox.innerHTML = "";
        }

        if (modalEl) {
            modalEl.addEventListener("show.bs.modal", clearBulkResult);
        }
        if (fileInput) {
            fileInput.addEventListener("change", clearBulkResult);
        }

        form.addEventListener("submit", function (event) {
            event.preventDefault();
            clearBulkResult();
            var file = fileInput && fileInput.files && fileInput.files[0] ? fileInput.files[0] : null;
            if (!file) {
                if (window.ArcavUi && window.ArcavUi.showToast) {
                    window.ArcavUi.showToast("Pilih file template bulk employee terlebih dahulu.", "warning");
                }
                renderBulkResult("warning", "Belum ada file dipilih.", ["Silakan pilih workbook bulk employee terlebih dahulu."]);
                return;
            }

            var fd = new FormData();
            fd.append("file", file);
            requestFormData("post", "/v1/hcm/employees/bulk-upload", fd).then(function (resp) {
                if (!resp || resp.success !== true) {
                    window.ArcavUi && window.ArcavUi.showToast && window.ArcavUi.showToast(formatApiError(resp, 0), "danger");
                    renderBulkResult("danger", "Bulk upload gagal.", [formatApiError(resp, 0)]);
                    return;
                }
                var createdRows = Number(resp && resp.data ? resp.data.createdRows : 0);
                var updatedRows = Number(resp && resp.data ? resp.data.updatedRows : 0);
                var failedRows = Number(resp && resp.data ? resp.data.failedRows : 0);
                var message = "Bulk upload selesai. Created: " + createdRows + ", Updated: " + updatedRows + ", Failed: " + failedRows + ".";
                if (window.ArcavUi && window.ArcavUi.showToast) {
                    window.ArcavUi.showToast(message, failedRows > 0 ? "warning" : "success");
                }
                renderBulkResult(failedRows > 0 ? "warning" : "success", "Bulk upload selesai.", [
                    "Created rows: " + createdRows,
                    "Updated rows: " + updatedRows,
                    "Failed rows: " + failedRows
                ].concat(resp.data && Array.isArray(resp.data.errors) ? resp.data.errors.slice(0, 8) : []));
                form.reset();
                if (window.bootstrap && window.bootstrap.Modal && modalEl) {
                    window.setTimeout(function () {
                        window.bootstrap.Modal.getOrCreateInstance(modalEl).hide();
                    }, 900);
                }
                loadEmployeesData();
            }).catch(function (error) {
                if (error && window.AuthApi && window.AuthApi.handleUnauthorizedFromApi(error.status, error.data)) {
                    return;
                }
                var rowErrors = error && error.data && error.data.data && Array.isArray(error.data.data.errors)
                    ? error.data.data.errors
                    : [];
                if (rowErrors.length) {
                    console.warn("Employee bulk upload validation errors:", rowErrors);
                }
                if (window.ArcavUi && window.ArcavUi.showToast) {
                    window.ArcavUi.showToast(formatApiError(error && error.data, error && error.status), "danger");
                }
                renderBulkResult("danger", "Bulk upload dibatalkan.", rowErrors.length ? rowErrors : [formatApiError(error && error.data, error && error.status)]);
            });
        });
    }

    function renderGridMessage(message) {
        var gridBody = document.querySelector("[data-employees-grid-body]");
        if (!gridBody) return;
        gridBody.innerHTML = '<div class="col-12"><div class="alert alert-light text-center mb-0">' + escapeHtml(message) + '</div></div>';
        gridBody.setAttribute("data-hydrated", "1");
    }

    function updateSummary(meta) {
        var summary = (meta && meta.summary) || {};
        var total = document.querySelector("[data-employees-total]");
        var active = document.querySelector("[data-employees-active]");
        var inactive = document.querySelector("[data-employees-inactive]");
        var newJoiners = document.querySelector("[data-employees-new-joiners]");

        if (total) total.textContent = String(summary.totalEmployees || 0);
        if (active) active.textContent = String(summary.activeEmployees || 0);
        if (inactive) inactive.textContent = String(summary.inactiveEmployees || 0);
        if (newJoiners) newJoiners.textContent = String(summary.newJoiners || 0);
    }

    function syncEmployeesFilterOptions() {
        var depSel = document.querySelector("[data-employees-filter-department]");
        var desSel = document.querySelector("[data-employees-filter-designation]");
        if (depSel) {
            var depPrev = employeesTableState.departmentId || depSel.value || "";
            depSel.innerHTML = '<option value="">All Departments</option>';
            orgDepartmentsFlat.forEach(function (d) {
                var opt = document.createElement("option");
                opt.value = String(d.id);
                opt.textContent = d.name || d.code || String(d.id);
                depSel.appendChild(opt);
            });
            depSel.value = depPrev;
        }
        if (desSel) {
            var desPrev = employeesTableState.designationId || desSel.value || "";
            desSel.innerHTML = '<option value="">All Designations</option>';
            orgDesignationsFlat.forEach(function (d) {
                var opt2 = document.createElement("option");
                opt2.value = String(d.id);
                opt2.textContent = d.name || d.code || String(d.id);
                desSel.appendChild(opt2);
            });
            desSel.value = desPrev;
        }
    }

    function renderEmployeesShowing(meta, rowCount) {
        var el = document.querySelector("[data-employees-showing]");
        if (!el) {
            return;
        }
        var total = Number(meta && meta.total ? meta.total : 0);
        var page = Number(meta && meta.page ? meta.page : 1);
        var perPage = Number(meta && meta.perPage ? meta.perPage : 20);
        if (!total || !rowCount) {
            el.textContent = "Showing 0 - 0 of 0 entries";
            return;
        }
        var start = ((page - 1) * perPage) + 1;
        var end = Math.min(start + rowCount - 1, total);
        el.textContent = "Showing " + start + " - " + end + " of " + total + " entries";
    }

    function renderEmployeesPagination(meta) {
        var list = document.querySelector("[data-employees-pagination]");
        if (!list) {
            return;
        }
        var total = Number(meta && meta.total ? meta.total : 0);
        var page = Number(meta && meta.page ? meta.page : 1);
        var perPage = Number(meta && meta.perPage ? meta.perPage : 20);
        var totalPages = Math.max(1, Math.ceil(total / Math.max(1, perPage)));
        if (totalPages <= 1) {
            list.innerHTML = "";
            return;
        }

        var startPage = Math.max(1, page - 2);
        var endPage = Math.min(totalPages, page + 2);
        var html = '';

        html += '<li class="page-item ' + (page <= 1 ? 'disabled' : '') + '"><a class="page-link" href="#" data-employees-page="' + (page - 1) + '">Prev</a></li>';
        for (var p = startPage; p <= endPage; p += 1) {
            html += '<li class="page-item ' + (p === page ? 'active' : '') + '"><a class="page-link" href="#" data-employees-page="' + p + '">' + p + '</a></li>';
        }
        html += '<li class="page-item ' + (page >= totalPages ? 'disabled' : '') + '"><a class="page-link" href="#" data-employees-page="' + (page + 1) + '">Next</a></li>';

        list.innerHTML = html;
    }

    function downloadBlob(filename, type, content) {
        var blob = new Blob([content], { type: type });
        var url = window.URL.createObjectURL(blob);
        var a = document.createElement("a");
        a.href = url;
        a.download = filename;
        document.body.appendChild(a);
        a.click();
        a.remove();
        window.URL.revokeObjectURL(url);
    }

    function toCsv(rows, headers) {
        var out = [headers.join(",")];
        rows.forEach(function (row) {
            var line = headers.map(function (key) {
                var value = row[key] == null ? "" : String(row[key]);
                return '"' + value.replace(/"/g, '""') + '"';
            }).join(",");
            out.push(line);
        });
        return out.join("\n");
    }

    function exportEmployees(format) {
        var params = new URLSearchParams();
        if (employeesTableState.search) params.set("search", employeesTableState.search);
        if (employeesTableState.status) params.set("status", employeesTableState.status);
        if (employeesTableState.departmentId) params.set("departmentId", employeesTableState.departmentId);
        if (employeesTableState.designationId) params.set("designationId", employeesTableState.designationId);
        params.set("format", format === "pdf" ? "pdf" : "xlsx");
        window.location.assign("/v1/hcm/employees/export?" + params.toString());
    }

    function bindEmployeesListControls() {
        if (document.body.getAttribute("data-employees-controls-bound") === "1") {
            return;
        }
        document.body.setAttribute("data-employees-controls-bound", "1");

        var searchInput = document.querySelector("[data-employees-search]");
        var statusSel = document.querySelector("[data-employees-filter-status]");
        var depSel = document.querySelector("[data-employees-filter-department]");
        var desSel = document.querySelector("[data-employees-filter-designation]");
        var perPageSel = document.querySelector("[data-employees-per-page]");
        var debounceTimer = null;

        var params = new URL(window.location.href).searchParams;
        employeesTableState.page = Math.max(1, parseInt(params.get("page") || String(employeesTableState.page), 10) || 1);
        employeesTableState.perPage = Math.max(1, parseInt(params.get("perPage") || String(employeesTableState.perPage), 10) || 20);
        employeesTableState.search = String(params.get("search") || employeesTableState.search || "").trim();
        employeesTableState.status = String(params.get("status") || employeesTableState.status || "").trim();
        employeesTableState.departmentId = String(params.get("departmentId") || employeesTableState.departmentId || "").trim();
        employeesTableState.designationId = String(params.get("designationId") || employeesTableState.designationId || "").trim();

        if (searchInput) {
            searchInput.value = employeesTableState.search;
        }
        if (statusSel) {
            statusSel.value = employeesTableState.status;
        }
        if (perPageSel) {
            perPageSel.value = String(employeesTableState.perPage);
        }

        function triggerReload(resetPage) {
            if (resetPage) {
                employeesTableState.page = 1;
            }
            loadEmployeesData();
        }

        if (searchInput) {
            searchInput.addEventListener("input", function () {
                window.clearTimeout(debounceTimer);
                debounceTimer = window.setTimeout(function () {
                    employeesTableState.search = String(searchInput.value || "").trim();
                    triggerReload(true);
                }, 350);
            });
        }
        if (statusSel) {
            statusSel.addEventListener("change", function () {
                employeesTableState.status = String(statusSel.value || "");
                triggerReload(true);
            });
        }
        if (depSel) {
            depSel.addEventListener("change", function () {
                employeesTableState.departmentId = String(depSel.value || "");
                triggerReload(true);
            });
        }
        if (desSel) {
            desSel.addEventListener("change", function () {
                employeesTableState.designationId = String(desSel.value || "");
                triggerReload(true);
            });
        }
        if (perPageSel) {
            perPageSel.addEventListener("change", function () {
                employeesTableState.perPage = Math.max(1, parseInt(perPageSel.value || "20", 10) || 20);
                triggerReload(true);
            });
        }

        document.addEventListener("click", function (event) {
            var pageLink = event.target.closest("[data-employees-page]");
            if (pageLink) {
                event.preventDefault();
                var target = parseInt(pageLink.getAttribute("data-employees-page") || "1", 10);
                var maxPage = Math.max(1, Math.ceil(Number(employeesTableMeta.total || 0) / Math.max(1, Number(employeesTableMeta.perPage || 20))));
                if (target < 1 || target > maxPage || target === employeesTableState.page) {
                    return;
                }
                employeesTableState.page = target;
                loadEmployeesData();
                return;
            }

            var exportBtn = event.target.closest("[data-employees-export]");
            if (exportBtn) {
                event.preventDefault();
                exportEmployees(exportBtn.getAttribute("data-employees-export") || "xlsx");
            }
        });
    }

    function renderReportMessage(message) {
        var tbody = document.querySelector("[data-employee-report-body]");
        if (!tbody) {
            return;
        }
        tbody.innerHTML =
            '<tr><td colspan="7" class="text-center text-muted py-4">' + escapeHtml(message) + "</td></tr>";
        tbody.removeAttribute("data-hydrated");
    }

    function renderReportTable(rows) {
        var tbody = document.querySelector("[data-employee-report-body]");
        if (!tbody) {
            return;
        }
        if (!rows.length) {
            tbody.innerHTML =
                '<tr><td colspan="7" class="text-center text-muted py-4">No employees.</td></tr>';
            tbody.setAttribute("data-hydrated", "1");
            return;
        }
        tbody.innerHTML = rows
            .map(function (row) {
                var st = row.employmentStatus || "active";
                var badge = st === "active" ? "success" : st === "inactive" ? "danger" : "warning";
                return (
                    "<tr>" +
                    "<td>" +
                    escapeHtml(row.employeeNo || "") +
                    "</td><td>" +
                    escapeHtml(row.fullName || "") +
                    "</td><td>" +
                    escapeHtml(row.email || "") +
                    "</td><td>" +
                    escapeHtml(row.team || "—") +
                    "</td><td>" +
                    escapeHtml(row.departmentName || "—") +
                    "</td><td>" +
                    escapeHtml(row.joinDate || "—") +
                    '</td><td><span class="badge badge-' +
                    badge +
                    ' d-inline-flex align-items-center badge-xs">' +
                    escapeHtml(st) +
                    "</span></td></tr>"
                );
            })
            .join("");
        tbody.setAttribute("data-hydrated", "1");
    }

    function updateReportSummary(meta) {
        var summary = (meta && meta.summary) || {};
        var total = document.querySelector("[data-employee-report-total]");
        var active = document.querySelector("[data-employee-report-active]");
        var inactive = document.querySelector("[data-employee-report-inactive]");
        var newEl = document.querySelector("[data-employee-report-new]");
        if (total) {
            total.textContent = String(summary.totalEmployees || 0);
        }
        if (active) {
            active.textContent = String(summary.activeEmployees || 0);
        }
        if (inactive) {
            inactive.textContent = String(summary.inactiveEmployees || 0);
        }
        if (newEl) {
            newEl.textContent = String(summary.newJoiners || 0);
        }
    }

    function loadEmployeesData() {
        var hasEmployeesPage = window.location.pathname.indexOf("/employees") === 0 || window.location.pathname.indexOf("/employees-grid") === 0;
        if (!hasEmployeesPage) {
            return;
        }

        // Prevent template dummy rows from flashing before API data arrives.
        var listBody = document.querySelector("[data-employees-list-body]");
        if (listBody) {
            listBody.innerHTML = '<tr><td class="text-center text-muted py-4">Loading employees...</td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td></tr>';
        }
        var gridBody = document.querySelector("[data-employees-grid-body]");
        if (gridBody) {
            gridBody.innerHTML = '<div class="col-12"><div class="alert alert-light text-center mb-0">Loading employees...</div></div>';
        }

        requestAuthMe()
            .then(function (me) {
                if (!me || !me.success || !me.data || !me.data.hcmAdmin) {
                    window.location.replace("/employee-dashboard");
                    return null;
                }
                bindEmployeeOrgDepartmentChange();
                return ensureEmployeeOrgMastersLoaded().then(function () {
                    syncEmployeesFilterOptions();
                    return requestEmployeesByState(employeesTableState);
                });
            })
            .then(function (payload) {
                if (payload === null) {
                    return;
                }
                if (!payload || payload.success !== true) {
                    var msg = formatApiError(payload, 0);
                    renderListMessage(msg || "Unable to load employees data.");
                    renderGridMessage(msg || "Unable to load employees data.");
                    return;
                }

                var rows = Array.isArray(payload.data) ? payload.data : [];
                employeesTableMeta = {
                    page: Number(payload.meta && payload.meta.page ? payload.meta.page : employeesTableState.page),
                    perPage: Number(payload.meta && payload.meta.perPage ? payload.meta.perPage : employeesTableState.perPage),
                    total: Number(payload.meta && payload.meta.total ? payload.meta.total : rows.length),
                };
                employeesTableState.page = employeesTableMeta.page;
                renderList(rows);
                renderGrid(rows);
                updateActiveRowHighlight();
                updateSummary(payload.meta || {});
                renderEmployeesShowing(employeesTableMeta, rows.length);
                renderEmployeesPagination(employeesTableMeta);
            })
            .catch(function (error) {
                console.error("Failed to load employees data", error);
                if (error && window.AuthApi && window.AuthApi.handleUnauthorizedFromApi(error.status, error.data)) {
                    return;
                }
                var msg = formatApiError(error && error.data, error && error.status);
                renderListMessage(msg || "Failed loading employees. Please try again.");
                renderGridMessage(msg || "Failed loading employees. Please try again.");
                renderEmployeesShowing({ total: 0, page: 1, perPage: employeesTableState.perPage }, 0);
                renderEmployeesPagination({ total: 0, page: 1, perPage: employeesTableState.perPage });
            });
    }

    function loadEmployeeReportData() {
        var path = String(window.location.pathname || "").replace(/\/+$/, "") || "/";
        if (path !== "/employee-report") {
            return;
        }
        var tbody = document.querySelector("[data-employee-report-body]");
        if (tbody) {
            tbody.innerHTML =
                '<tr><td colspan="7" class="text-center text-muted py-4">Loading employees…</td></tr>';
            tbody.removeAttribute("data-hydrated");
        }
        requestAuthMe()
            .then(function (me) {
                if (!me || !me.success || !me.data || !me.data.hcmAdmin) {
                    window.location.replace("/employee-dashboard");
                    return null;
                }
                return requestAllEmployeesAggregated(100);
            })
            .then(function (payload) {
                if (payload === null) {
                    return;
                }
                if (!payload || payload.success !== true) {
                    renderReportMessage(formatApiError(payload, 0) || "Unable to load employee report.");
                    return;
                }
                var rows = Array.isArray(payload.data) ? payload.data : [];
                renderReportTable(rows);
                updateReportSummary(payload.meta || {});
            })
            .catch(function (error) {
                if (error && window.AuthApi && window.AuthApi.handleUnauthorizedFromApi(error.status, error.data)) {
                    return;
                }
                renderReportMessage(formatApiError(error && error.data, error && error.status) || "Failed loading report. Please try again.");
            });
    }

    function init() {
        restoreReturnStateIfAny();
        bindEmployeesListControls();
        bindEmployeePhotoModalPreview();
        loadEmployeesData();
        loadEmployeeReportData();
        bindQuickPreview();
        bindEmployeeCompensationForms();
        bindSalaryBulkUpload();
        document.addEventListener("employees:view-swapped", function () {
            loadEmployeesData();
        });
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", init);
    } else {
        init();
    }
})(window, document);
