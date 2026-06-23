import { createLeaveDateHelpers } from "./date-helpers.js";
import { buildLeaveTypeOptionsHtml, createLeaveUiHelpers, splitDeclinedLeaveNotes } from "./ui-helpers.js";

export function bindLeaves(deps, scope, isAdmin) {
    var apiRequest = deps.apiRequest;
    var esc = deps.esc;
    var notify = deps.notify;
    var downloadFileFromUrl = deps.downloadFileFromUrl;
    var formatApiError = deps.formatApiError;
    var loadEmployeeOptions = deps.loadEmployeeOptions;

    var leavePage = 1;
    var leavePerPage = 20;
    var leaveFilters = {
        leaveType: "",
        status: "",
        dateFrom: "",
        dateTo: "",
    };
    var openLeaveRequestId = "";
    var openLeaveRequestUuid = "";

    try {
        var openParams = new URLSearchParams(window.location.search || "");
        openLeaveRequestId = String(openParams.get("openLeaveRequestId") || "").trim();
        openLeaveRequestUuid = String(openParams.get("openLeaveRequestUuid") || "").trim();
    } catch (_e) {
        openLeaveRequestId = "";
        openLeaveRequestUuid = "";
    }

    var bodySel = isAdmin ? "[data-hcm-leaves-admin-body]" : "[data-hcm-leaves-me-body]";
    var body = document.querySelector(bodySel);
    var leaveTypesCache = [];
    var leaveRowsCache = [];
    var leaveTypeMetaByName = {};
    var leaveTypeLabelByCode = {};

    var dateHelpers = createLeaveDateHelpers(esc);
    var uiHelpers = createLeaveUiHelpers({ apiRequest: apiRequest, esc: esc });

    function refreshLeaveTypeHints() {
        uiHelpers.refreshLeaveTypeHints(leaveTypeMetaByName);
    }

    function displayLeaveType(row) {
        return uiHelpers.displayLeaveType(row, leaveTypeLabelByCode);
    }

    function clearOpenLeaveQuery() {
        if (!openLeaveRequestId && !openLeaveRequestUuid) {
            return;
        }

        openLeaveRequestId = "";
        openLeaveRequestUuid = "";

        try {
            var next = new URL(window.location.href);
            next.searchParams.delete("openLeaveRequestId");
            next.searchParams.delete("openLeaveRequestUuid");
            window.history.replaceState({}, "", next.pathname + (next.search || "") + (next.hash || ""));
        } catch (_e) {}
    }

    function tryOpenLeaveFromQuery() {
        if (!isAdmin || (!openLeaveRequestId && !openLeaveRequestUuid)) {
            return;
        }

        var target = null;
        document.querySelectorAll("[data-hcm-leave-edit]").forEach(function (el) {
            if (target) {
                return;
            }

            var rowId = String(el.getAttribute("data-id") || "");
            var rowUuid = String(el.getAttribute("data-uuid") || "");
            if ((openLeaveRequestId && rowId === openLeaveRequestId) || (openLeaveRequestUuid && rowUuid === openLeaveRequestUuid)) {
                target = el;
            }
        });

        if (!target) {
            return;
        }

        target.click();
        clearOpenLeaveQuery();
    }

    function buildFilterQuery() {
        var params = [];
        Object.keys(leaveFilters).forEach(function (key) {
            var value = String(leaveFilters[key] || "").trim();
            if (!value) {
                return;
            }
            params.push(encodeURIComponent(key) + "=" + encodeURIComponent(value));
        });
        return params.join("&");
    }

    function buildLeaveUrl() {
        var q = "page=" + encodeURIComponent(String(leavePage)) + "&perPage=" + encodeURIComponent(String(leavePerPage));
        var fq = buildFilterQuery();
        if (scope === "me") {
            return "/v1/hcm/leave-requests?scope=me&" + q + (fq ? "&" + fq : "");
        }
        return "/v1/hcm/leave-requests?" + q + (fq ? "&" + fq : "");
    }

    function setupLeaveFilterControls() {
        var container = document.querySelector("[data-hcm-leaves-filters]");
        if (!container || container.getAttribute("data-bound") === "1") {
            return;
        }
        container.setAttribute("data-bound", "1");

        var typeSelect = container.querySelector('[data-hcm-leaves-filter="leaveType"]');
        var statusSelect = container.querySelector('[data-hcm-leaves-filter="status"]');
        var dateFromInput = container.querySelector('[data-hcm-leaves-filter="dateFrom"]');
        var dateToInput = container.querySelector('[data-hcm-leaves-filter="dateTo"]');
        var resetBtn = container.querySelector("[data-hcm-leaves-filter-reset]");

        function syncFiltersFromUi() {
            leaveFilters.leaveType = typeSelect ? String(typeSelect.value || "") : "";
            leaveFilters.status = statusSelect ? String(statusSelect.value || "") : "";
            leaveFilters.dateFrom = dateFromInput ? String(dateFromInput.value || "") : "";
            leaveFilters.dateTo = dateToInput ? String(dateToInput.value || "") : "";
        }

        function applyFiltersAndReload() {
            syncFiltersFromUi();
            leavePage = 1;
            reload();
        }

        [typeSelect, statusSelect, dateFromInput, dateToInput].forEach(function (el) {
            if (!el) {
                return;
            }

            function bind(eventName) {
                el.addEventListener(eventName, applyFiltersAndReload);
            }

            if (el.tagName === "SELECT") {
                bind("change");
                return;
            }

            bind("input");
            bind("change");
            bind("blur");
        });

        if (resetBtn) {
            resetBtn.addEventListener("click", function () {
                leaveFilters = { leaveType: "", status: "", dateFrom: "", dateTo: "" };
                if (typeSelect) {
                    typeSelect.value = "";
                }
                if (statusSelect) {
                    statusSelect.value = "";
                }
                if (dateFromInput) {
                    dateFromInput.value = "";
                }
                if (dateToInput) {
                    dateToInput.value = "";
                }
                leavePage = 1;
                reload();
            });
        }
    }

    function populateLeaveTypeFilter() {
        var container = document.querySelector("[data-hcm-leaves-filters]");
        var select = container ? container.querySelector('[data-hcm-leaves-filter="leaveType"]') : null;
        if (!select) {
            return;
        }
        var selectedValue = String(leaveFilters.leaveType || select.value || "");
        var opts = '<option value="">Semua tipe cuti</option>';
        var seen = {};

        function addOption(value, label) {
            var v = String(value || "").trim();
            var l = String(label || "").trim();
            if (!v || !l) {
                return;
            }
            var key = v.toLowerCase();
            if (seen[key]) {
                return;
            }
            seen[key] = true;
            opts += '<option value="' + esc(v) + '">' + esc(l) + "</option>";
        }

        leaveTypesCache.forEach(function (t) {
            if (!t || !t.name) {
                return;
            }
            addOption(String(t.name), String(t.name));
        });

        leaveRowsCache.forEach(function (r) {
            if (!r || !r.leaveType) {
                return;
            }
            addOption(String(r.leaveType), displayLeaveType(r));
        });

        select.innerHTML = opts;
        if (selectedValue) {
            select.value = selectedValue;
        }
    }

    function bindExportButton() {
        var btn = document.querySelector("[data-hcm-leaves-export]");
        if (!btn || btn.getAttribute("data-bound") === "1") {
            return;
        }
        btn.setAttribute("data-bound", "1");
        btn.addEventListener("click", function () {
            btn.disabled = true;
            var fq = buildFilterQuery();
            var url = "/v1/hcm/leave-requests/export";
            var parts = [];
            if (scope === "me") {
                parts.push("scope=me");
            }
            if (fq) {
                parts.push(fq);
            }
            if (parts.length) {
                url += "?" + parts.join("&");
            }
            url += (url.indexOf("?") >= 0 ? "&" : "?") + "format=xlsx";

            downloadFileFromUrl(url, "leave-requests.xlsx")
                .then(function (ok) {
                    if (!ok) {
                        return;
                    }
                    notify("Export leave Excel berhasil.", false);
                })
                .catch(function (err) {
                    notify(formatApiError(err && err.data, err && err.status), true);
                })
                .finally(function () {
                    btn.disabled = false;
                });
        });
    }

    function render(rows) {
        if (!body) {
            return;
        }
        leaveRowsCache = Array.isArray(rows) ? rows.slice() : [];
        body.innerHTML =
            (rows || [])
                .map(function (r) {
                    var badge = r.status === "approved" ? "success" : r.status === "declined" ? "danger" : "warning";
                    var leaveTypeText = displayLeaveType(r);
                    var empCell = isAdmin
                        ? "<td><div class=\"fw-medium\">" + esc(r.employeeName) + '</div><small class="text-muted">' + esc(r.email) + "</small></td>"
                        : "";
                    var cb = '<td><div class="form-check form-check-md"><input class="form-check-input" type="checkbox"></div></td>';
                    var isOwnRequest = String(r.userId || "") === String(window.__arcav_me_id || "");
                    var canEdit = isAdmin ? (!isOwnRequest || r.status === "pending") : r.status === "pending";
                    var canDelete = isOwnRequest && r.status === "pending";
                    var actions = [];

                    if (canEdit) {
                        actions.push(
                            '<a href="#" class="me-2" data-hcm-leave-edit data-id="' +
                                esc(r.id) +
                                '" data-uuid="' +
                                esc(r.uuid || "") +
                                '" data-user="' +
                                esc(r.userId) +
                                '" data-type="' +
                                esc(r.leaveType) +
                                '" data-from="' +
                                esc(r.dateFrom) +
                                '" data-to="' +
                                esc(r.dateTo) +
                                '" data-days="' +
                                esc(String(r.days)) +
                                '" data-status="' +
                                esc(r.status) +
                                '" data-notes="' +
                                esc(r.notes) +
                                '" data-bs-toggle="modal" data-bs-target="#arcav_edit_leave"><i class="ti ti-edit"></i></a>'
                        );
                    }

                    if (canDelete) {
                        actions.push('<a href="#" data-hcm-leave-delete="' + esc(r.id) + '"><i class="ti ti-trash"></i></a>');
                    }

                    return (
                        "<tr>" +
                        cb +
                        (isAdmin ? empCell : "") +
                        "<td>" +
                        esc(leaveTypeText) +
                        "</td><td>" +
                        esc(r.dateFrom) +
                        "</td><td>" +
                        esc(r.dateTo) +
                        "</td><td>" +
                        esc(String(r.days)) +
                        '</td><td><span class="badge badge-' +
                        badge +
                        ' d-inline-flex align-items-center badge-xs">' +
                        esc(r.status) +
                        "</span></td><td>" +
                        (actions.length ? actions.join("") : '<span class="text-muted">-</span>') +
                        "</td></tr>"
                    );
                })
                .join("") ||
            '<tr><td colspan="' + (isAdmin ? "8" : "7") + '" class="text-center py-4 text-muted">No leave requests.</td></tr>';
    }

    function renderLeavePagination(meta) {
        var foot = document.querySelector("[data-hcm-leaves-pagination]");
        var info = document.querySelector("[data-hcm-leaves-page-info]");
        if (!foot) {
            return;
        }
        var pag = (meta && meta.pagination) || {};
        if (pag.total == null) {
            foot.style.display = "none";
            return;
        }
        var total = parseInt(pag.total, 10) || 0;
        var page = parseInt(pag.page, 10) || 1;
        var perPage = parseInt(pag.perPage, 10) || leavePerPage;
        var totalPages = parseInt(pag.totalPages, 10) || 1;
        if (totalPages <= 1) {
            foot.style.display = "none";
            return;
        }
        foot.style.display = "";
        if (info) {
            var from = total === 0 ? 0 : (page - 1) * perPage + 1;
            var to = Math.min(page * perPage, total);
            info.textContent = "Menampilkan " + from + "–" + to + " dari " + total;
        }
        var prev = foot.querySelector("[data-hcm-leaves-prev]");
        var next = foot.querySelector("[data-hcm-leaves-next]");
        if (prev) {
            prev.disabled = page <= 1;
        }
        if (next) {
            next.disabled = page >= totalPages;
        }
    }

    function setupLeavePagination() {
        var foot = document.querySelector("[data-hcm-leaves-pagination]");
        if (!foot) {
            return;
        }
        var prev = foot.querySelector("[data-hcm-leaves-prev]");
        var next = foot.querySelector("[data-hcm-leaves-next]");
        if (prev && !prev.getAttribute("data-bound")) {
            prev.setAttribute("data-bound", "1");
            prev.addEventListener("click", function () {
                if (leavePage > 1) {
                    leavePage -= 1;
                    reload();
                }
            });
        }
        if (next && !next.getAttribute("data-bound")) {
            next.setAttribute("data-bound", "1");
            next.addEventListener("click", function () {
                leavePage += 1;
                reload();
            });
        }
    }

    function reload() {
        apiRequest("get", buildLeaveUrl(), null)
            .then(function (p) {
                if (!p || p.success !== true) {
                    notify("Failed to load leaves.", true);
                    dateHelpers.renderHolidayPanel({});
                    return;
                }
                var pag = (p.meta && p.meta.pagination) || {};
                if (pag.totalPages != null && leavePage > pag.totalPages && pag.totalPages > 0) {
                    leavePage = pag.totalPages;
                    reload();
                    return;
                }
                render(p.data || []);
                renderLeavePagination(p.meta || {});
                uiHelpers.updateLeaveCards(p.meta || {}, isAdmin);
                dateHelpers.renderHolidayPanel(p.meta || {});
                dateHelpers.refreshFormDateHint(document.querySelector('[data-hcm-leave-form="add"]'));
                dateHelpers.refreshFormDateHint(document.querySelector('[data-hcm-leave-form="edit"]'));
                tryOpenLeaveFromQuery();
            })
            .catch(function (e) {
                dateHelpers.renderHolidayPanel({});
                notify(formatApiError(e && e.data, e && e.status), true);
            });
    }

    setupLeavePagination();
    setupLeaveFilterControls();
    bindExportButton();

    var addForm = document.querySelector('[data-hcm-leave-form="add"]');
    if (addForm) {
        dateHelpers.bindDateValidation(addForm);

        var addModal = document.getElementById("arcav_add_leave");
        if (addModal) {
            addModal.addEventListener("show.bs.modal", function () {
                var errorAlert = addForm.querySelector('[data-hcm-leave-error-add]');
                if (errorAlert) {
                    errorAlert.classList.add("d-none");
                }
                var balanceCard = addForm.querySelector('[data-hcm-leave-balance-card]');
                if (balanceCard) {
                    balanceCard.classList.add("d-none");
                }
                addForm.reset();
            });
        }

        addForm.addEventListener("input", function () {
            var errorAlert = addForm.querySelector('[data-hcm-leave-error-add]');
            if (errorAlert) {
                errorAlert.classList.add("d-none");
            }
        });

        var userSel = addForm.querySelector('[data-hcm-field="userId"]');
        if (userSel && isAdmin) {
            loadEmployeeOptions(userSel);
        }

        addForm.addEventListener("submit", function (e) {
            e.preventDefault();
            dateHelpers.refreshFormDateHint(addForm);
            if (!ArcavValidation.validateForm(addForm)) { return; }

            var ltEl = addForm.querySelector('[data-hcm-field="leaveType"]');
            var payload = {
                leaveType: ltEl && ltEl.value ? ltEl.value.trim() : "",
                dateFrom: addForm.querySelector('[data-hcm-field="dateFrom"]').value,
                dateTo: addForm.querySelector('[data-hcm-field="dateTo"]').value,
                notes: addForm.querySelector('[data-hcm-field="notes"]').value.trim() || null,
            };
            var daysVal = addForm.querySelector('[data-hcm-field="days"]').value.trim();
            if (daysVal) {
                payload.days = parseFloat(daysVal, 10);
            }
            if (isAdmin && userSel && userSel.value) {
                payload.userId = parseInt(userSel.value, 10);
            }

            apiRequest("post", "/v1/hcm/leave-requests", payload)
                .then(function (p) {
                    if (!p || p.success !== true) {
                        notify("Submit failed.", true);
                        return;
                    }
                    notify("Submitted.", false);
                    var el = document.getElementById("arcav_add_leave");
                    var mi = el && window.bootstrap && window.bootstrap.Modal.getInstance(el);
                    if (mi) {
                        mi.hide();
                    }
                    addForm.reset();
                    reload();
                })
                .catch(function (err) {
                    var errorMsg = formatApiError(err.data, err.status);
                    notify(errorMsg, true);

                    var errorAlert = addForm.querySelector('[data-hcm-leave-error-add]');
                    if (errorAlert) {
                        var titleEl = errorAlert.querySelector('[data-hcm-error-title]');
                        var msgEl = errorAlert.querySelector('[data-hcm-error-message]');
                        if (titleEl && msgEl) {
                            var errorCode = err.data && err.data.error && err.data.error.code ? err.data.error.code : "ERROR";
                            var errorText = err.data && err.data.error && err.data.error.message ? err.data.error.message : errorMsg;
                            var codeDisplay = errorCode
                                .replace(/_/g, " ")
                                .toLowerCase()
                                .split(" ")
                                .map(function (w) { return w.charAt(0).toUpperCase() + w.slice(1); })
                                .join(" ");

                            titleEl.textContent = codeDisplay;
                            msgEl.textContent = errorText;
                            errorAlert.classList.remove("d-none");
                            if (typeof errorAlert.scrollIntoView === "function") { errorAlert.scrollIntoView({ behavior: "smooth", block: "start" }); }
                        }
                    }

                    // Per-field validation errors
                    var fieldErrors = err.data && err.data.errors;
                    if (fieldErrors && typeof fieldErrors === "object") {
                        Object.keys(fieldErrors).forEach(function (fieldName) {
                            var fieldEl = addForm.querySelector('[data-hcm-field="' + fieldName + '"]');
                            if (fieldEl) {
                                fieldEl.classList.add("is-invalid");
                                var feedbackEl = fieldEl.parentNode.querySelector(".invalid-feedback");
                                if (!feedbackEl) {
                                    feedbackEl = document.createElement("div");
                                    feedbackEl.className = "invalid-feedback";
                                    fieldEl.parentNode.appendChild(feedbackEl);
                                }
                                feedbackEl.textContent = Array.isArray(fieldErrors[fieldName])
                                    ? fieldErrors[fieldName].join(". ")
                                    : String(fieldErrors[fieldName]);
                            }
                        });
                    }
                });
        });
    }

    var editForm = document.querySelector('[data-hcm-leave-form="edit"]');
    if (editForm) {
        dateHelpers.bindDateValidation(editForm);

        function syncAdminLeaveReviewNotes() {
            var notesEl = editForm.querySelector('[data-hcm-field="notes"]');
            var statusEl = editForm.querySelector('[data-hcm-field="status"]');
            if (!notesEl) {
                return;
            }

            var owner = String(editForm.querySelector('[data-hcm-field="ownerUserId"]').value || "");
            var me = String(window.__arcav_me_id || "");
            var adminReviewMode = isAdmin && owner && owner !== me;
            var notesLabel = notesEl.closest(".mb-3") && notesEl.closest(".mb-3").querySelector(".form-label");

            if (!adminReviewMode) {
                notesEl.readOnly = false;
                notesEl.required = false;
                if (notesLabel) {
                    notesLabel.textContent = "Notes";
                }
                return;
            }

            var status = statusEl ? String(statusEl.value || "pending").toLowerCase() : "pending";
            var employeeNotes = String(editForm.dataset.employeeNotes || "");
            var rejectionReason = String(editForm.dataset.rejectionReason || "");

            if (status === "declined") {
                notesEl.readOnly = false;
                notesEl.required = true;
                notesEl.value = rejectionReason;
                if (notesLabel) {
                    notesLabel.textContent = "Rejection reason";
                }
                return;
            }

            notesEl.readOnly = true;
            notesEl.required = false;
            notesEl.value = employeeNotes;
            if (notesLabel) {
                notesLabel.textContent = "Employee notes";
            }
        }

        var statusInput = editForm.querySelector('[data-hcm-field="status"]');
        if (statusInput) {
            statusInput.addEventListener("change", function () {
                syncAdminLeaveReviewNotes();
            });
        }

        editForm.addEventListener("input", function (event) {
            var errorAlert = editForm.querySelector('[data-hcm-leave-error-edit]');
            if (errorAlert) {
                errorAlert.classList.add("d-none");
            }

            var statusValue = String((editForm.querySelector('[data-hcm-field="status"]') || {}).value || "").toLowerCase();
            var owner = String((editForm.querySelector('[data-hcm-field="ownerUserId"]') || {}).value || "");
            var me = String(window.__arcav_me_id || "");
            if (isAdmin && owner && owner !== me && statusValue === "declined") {
                var notesEl = editForm.querySelector('[data-hcm-field="notes"]');
                if (notesEl && event && event.target === notesEl) {
                    editForm.dataset.rejectionReason = notesEl.value.trim();
                }
            }
        });

        document.addEventListener("click", function (e) {
            var btn = e.target.closest("[data-hcm-leave-edit]");
            if (!btn) {
                return;
            }

            var errorAlert = editForm.querySelector('[data-hcm-leave-error-edit]');
            if (errorAlert) {
                errorAlert.classList.add("d-none");
            }

            editForm.querySelector('[data-hcm-field="id"]').value = btn.dataset.id || "";
            editForm.querySelector('[data-hcm-field="ownerUserId"]').value = btn.dataset.user || "";
            var editLt = editForm.querySelector('[data-hcm-field="leaveType"]');
            if (editLt) {
                editLt.innerHTML = buildLeaveTypeOptionsHtml(leaveTypesCache, btn.dataset.type || "", esc);
                uiHelpers.updateLeaveBalanceDisplay(editLt);
            }
            editForm.querySelector('[data-hcm-field="dateFrom"]').value = btn.dataset.from || "";
            editForm.querySelector('[data-hcm-field="dateTo"]').value = btn.dataset.to || "";
            editForm.querySelector('[data-hcm-field="days"]').value = btn.dataset.days || "";
            editForm.querySelector('[data-hcm-field="status"]').value = btn.dataset.status || "pending";
            var noteParts = splitDeclinedLeaveNotes(btn.dataset.notes || "");
            editForm.dataset.employeeNotes = noteParts.employeeNotes;
            editForm.dataset.rejectionReason = noteParts.rejectionReason;
            editForm.querySelector('[data-hcm-field="notes"]').value = noteParts.employeeNotes;
            refreshLeaveTypeHints();
            dateHelpers.refreshFormDateHint(editForm);
            syncAdminLeaveReviewNotes();
        });

        editForm.addEventListener("submit", function (e) {
            e.preventDefault();
            dateHelpers.refreshFormDateHint(editForm);
            if (!ArcavValidation.validateForm(editForm)) { return; }
            var id = editForm.querySelector('[data-hcm-field="id"]').value;
            var owner = editForm.querySelector('[data-hcm-field="ownerUserId"]').value;
            if (!id) {
                return;
            }

            var me = window.__arcav_me_id;
            var payload;
            if (isAdmin && String(owner) !== String(me)) {
                var statusValue = String(editForm.querySelector('[data-hcm-field="status"]').value || "pending").toLowerCase();
                var rejectionReason = editForm.querySelector('[data-hcm-field="notes"]').value.trim();
                if (statusValue === "declined" && !rejectionReason) {
                    notify("Alasan penolakan wajib diisi saat status Declined.", true);
                    return;
                }

                payload = { status: statusValue };
                if (statusValue === "declined") {
                    payload.notes = rejectionReason;
                }
            } else {
                payload = {
                    leaveType: (function () {
                        var el = editForm.querySelector('[data-hcm-field="leaveType"]');
                        return el && el.value ? el.value.trim() : "";
                    })(),
                    dateFrom: editForm.querySelector('[data-hcm-field="dateFrom"]').value,
                    dateTo: editForm.querySelector('[data-hcm-field="dateTo"]').value,
                    days: parseFloat(editForm.querySelector('[data-hcm-field="days"]').value, 10) || undefined,
                    notes: editForm.querySelector('[data-hcm-field="notes"]').value.trim() || null,
                };
            }

            apiRequest("put", "/v1/hcm/leave-requests/" + encodeURIComponent(id), payload)
                .then(function (p) {
                    if (!p || p.success !== true) {
                        notify("Update failed.", true);
                        return;
                    }
                    notify("Updated.", false);
                    var el = document.getElementById("arcav_edit_leave");
                    var mi = el && window.bootstrap && window.bootstrap.Modal.getInstance(el);
                    if (mi) {
                        mi.hide();
                    }
                    reload();
                })
                .catch(function (err) {
                    var errorMsg = formatApiError(err.data, err.status);
                    notify(errorMsg, true);

                    var errorAlert = editForm.querySelector('[data-hcm-leave-error-edit]');
                    if (errorAlert) {
                        var titleEl = errorAlert.querySelector('[data-hcm-error-title]');
                        var msgEl = errorAlert.querySelector('[data-hcm-error-message]');
                        if (titleEl && msgEl) {
                            var errorCode = err.data && err.data.error && err.data.error.code ? err.data.error.code : "ERROR";
                            var errorText = err.data && err.data.error && err.data.error.message ? err.data.error.message : errorMsg;
                            var codeDisplay = errorCode
                                .replace(/_/g, " ")
                                .toLowerCase()
                                .split(" ")
                                .map(function (w) { return w.charAt(0).toUpperCase() + w.slice(1); })
                                .join(" ");

                            titleEl.textContent = codeDisplay;
                            msgEl.textContent = errorText;
                            errorAlert.classList.remove("d-none");
                            if (typeof errorAlert.scrollIntoView === "function") { errorAlert.scrollIntoView({ behavior: "smooth", block: "start" }); }
                        }
                    }

                    // Per-field validation errors
                    var fieldErrors = err.data && err.data.errors;
                    if (fieldErrors && typeof fieldErrors === "object") {
                        Object.keys(fieldErrors).forEach(function (fieldName) {
                            var fieldEl = editForm.querySelector('[data-hcm-field="' + fieldName + '"]');
                            if (fieldEl) {
                                fieldEl.classList.add("is-invalid");
                                var feedbackEl = fieldEl.parentNode.querySelector(".invalid-feedback");
                                if (!feedbackEl) {
                                    feedbackEl = document.createElement("div");
                                    feedbackEl.className = "invalid-feedback";
                                    fieldEl.parentNode.appendChild(feedbackEl);
                                }
                                feedbackEl.textContent = Array.isArray(fieldErrors[fieldName])
                                    ? fieldErrors[fieldName].join(". ")
                                    : String(fieldErrors[fieldName]);
                            }
                        });
                    }
                });
        });

        document.addEventListener("click", function (e) {
            var del = e.target.closest("[data-hcm-leave-delete]");
            if (!del) {
                return;
            }
            e.preventDefault();
            var lid = del.getAttribute("data-hcm-leave-delete");
            var run = window.ArcavUi && typeof window.ArcavUi.confirmDelete === "function"
                ? window.ArcavUi.confirmDelete("Pengajuan cuti pending ini akan dihapus. Lanjutkan?", "Hapus pengajuan")
                : Promise.resolve(false);
            run.then(function (ok) {
                if (!ok) {
                    return;
                }
                apiRequest("delete", "/v1/hcm/leave-requests/" + encodeURIComponent(lid), null)
                    .then(function (p) {
                        if (!p || p.success !== true) {
                            notify("Delete failed.", true);
                            return;
                        }
                        notify("Deleted.", false);
                        reload();
                    })
                    .catch(function (err) {
                        notify(formatApiError(err.data, err.status), true);
                    });
            });
        });
    }

    function applyLeaveTypeOptions() {
        var addLt = document.querySelector('#arcav_add_leave [data-hcm-field="leaveType"]');
        var editLt = document.querySelector('#arcav_edit_leave [data-hcm-field="leaveType"]');
        if (addLt) {
            addLt.innerHTML = buildLeaveTypeOptionsHtml(leaveTypesCache, "", esc);
        }
        if (editLt) {
            editLt.innerHTML = buildLeaveTypeOptionsHtml(leaveTypesCache, "", esc);
        }
        refreshLeaveTypeHints();
    }

    document.addEventListener("change", function (e) {
        var select = e.target && e.target.closest('[data-hcm-field="leaveType"]');
        if (!select) {
            return;
        }
        refreshLeaveTypeHints();
        uiHelpers.updateLeaveBalanceDisplay(select);
    });

    apiRequest("get", "/v1/hcm/leave-type-options", null)
        .then(function (p) {
            if (p && p.success && Array.isArray(p.data)) {
                leaveTypesCache = p.data;
                leaveTypeMetaByName = {};
                p.data.forEach(function (t) {
                    if (!t || !t.name) {
                        return;
                    }
                    leaveTypeMetaByName[String(t.name).trim()] = {
                        deductFromBalance: !!t.deductFromBalance,
                        isPaid: t.isPaid !== false,
                    };
                    if (t.code) {
                        leaveTypeLabelByCode[String(t.code).toLowerCase()] = String(t.name).trim();
                    }
                    leaveTypeLabelByCode[String(t.name).trim().toLowerCase()] = String(t.name).trim();
                });
            } else {
                leaveTypesCache = [];
                notify("Gagal memuat jenis cuti.", true);
            }
            applyLeaveTypeOptions();
            populateLeaveTypeFilter();
        })
        .catch(function (err) {
            leaveTypesCache = [];
            applyLeaveTypeOptions();
            populateLeaveTypeFilter();
            notify(formatApiError(err && err.data, err && err.status), true);
        })
        .then(function () {
            return apiRequest("get", "/v1/identity/auth/me", null).catch(function () { return null; });
        })
        .then(function (m) {
            if (m && m.success && m.data && m.data.id) {
                window.__arcav_me_id = m.data.id;
            }
            reload();
        });
}