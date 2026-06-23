/**
 * hcm-extras-overtime.js
 * Extracted: overtime list management (admin + employee view).
 * Loaded at runtime via hcm-extras-data.js loader.
 */

export function bindOvertimeModule(deps, isAdmin) {
    var notify = deps.notify;
    var formatApiError = deps.formatApiError;
    var apiRequest = deps.apiRequest;
    var loadEmployeeOptions = deps.loadEmployeeOptions;
    var overtimeStatusMeta = deps.overtimeStatusMeta;
    var isPendingOlderThan24h = deps.isPendingOlderThan24h;
    var overtimePolicyTypeLabel = deps.overtimePolicyTypeLabel;
    var esc = deps.esc;

    var otPage = 1;
    var otPerPage = 20;
    var otFilters = {
        status: "",
        requestType: "",
    };

    function buildOtUrl() {
        var q = "page=" + encodeURIComponent(String(otPage)) + "&perPage=" + encodeURIComponent(String(otPerPage));
        if (isAdmin) {
            return "/v1/hcm/overtime-requests?" + q;
        }
        return "/v1/hcm/overtime-requests?scope=me&" + q;
    }

    var body = document.querySelector("[data-hcm-overtime-body]");
    var typeListCache = [];

    function fillOvertimeTypeSelects() {
        var head = '<option value="">— (opsional) —</option>';
        var opts = head;
        (typeListCache || []).forEach(function (t) {
            if (!t || t.id == null) {
                return;
            }
            opts +=
                '<option value="' +
                esc(String(t.id)) +
                '">' +
                esc(t.name) +
                " (" +
                esc(String(t.paymentMultiplier != null ? t.paymentMultiplier : "1")) +
                "×)</option>";
        });
        var addForm = document.querySelector('[data-hcm-ot-form="add"]');
        var editForm = document.querySelector('[data-hcm-ot-form="edit"]');
        if (addForm) {
            var sa = addForm.querySelector('[data-hcm-field="overtimeTypeId"]');
            if (sa) {
                sa.innerHTML = opts;
            }
        }
        if (editForm) {
            var se = editForm.querySelector('[data-hcm-field="overtimeTypeId"]');
            if (se) {
                se.innerHTML = opts;
            }
        }
    }

    function updateOvertimeStats(rows, summary) {
        function setStat(key, text) {
            var el = document.querySelector('[data-hcm-ot-stat="' + key + '"]');
            if (el) {
                el.textContent = text;
            }
        }
        if (summary && isAdmin) {
            var am = parseInt(summary.approvedMinutes, 10) || 0;
            setStat("distinctUsers", String(summary.distinctUsers != null ? summary.distinctUsers : 0));
            setStat("approvedHours", (am / 60).toFixed(1) + " h");
            setStat("pending", String(summary.pending != null ? summary.pending : 0));
            setStat("declined", String(summary.declined != null ? summary.declined : 0));
            return;
        }
        var distinct = new Set();
        var pending = 0;
        var declined = 0;
        var approvedMin = 0;
        (rows || []).forEach(function (r) {
            if (r && r.userId != null) {
                distinct.add(String(r.userId));
            }
            if (r.status === "pending") {
                pending += 1;
            } else if (r.status === "declined") {
                declined += 1;
            }
            if (r.status === "approved") {
                approvedMin += parseInt(r.minutes, 10) || 0;
            }
        });
        setStat("distinctUsers", isAdmin ? String(distinct.size) : "1");
        setStat("approvedHours", (approvedMin / 60).toFixed(1) + " h");
        setStat("pending", String(pending));
        setStat("declined", String(declined));
    }

    function render(rows) {
        if (!body) {
            return;
        }
        var filteredRows = (rows || []).filter(function (r) {
            var statusOk = !otFilters.status || String(r.status || "").toLowerCase() === otFilters.status;
            var requestTypeOk = !otFilters.requestType || String(r.requestType || "employee_request").toLowerCase() === otFilters.requestType;
            return statusOk && requestTypeOk;
        });
        body.innerHTML =
            filteredRows
                .map(function (r) {
                    var statusMeta = overtimeStatusMeta(r.status);
                    var isUrgent = isPendingOlderThan24h(r);
                    var hrs = (r.minutes / 60).toFixed(2);
                    var emp = isAdmin ? "<td>" + esc(r.employeeName) + "</td>" : "";
                    var tid = r.overtimeTypeId != null && r.overtimeTypeId !== "" ? String(r.overtimeTypeId) : "";
                    var policyLine = isAdmin
                        ? '<div class="text-muted small mt-1">' +
                          esc(overtimePolicyTypeLabel(r.requestType)) +
                          (r.policyNote ? ' - ' + esc(String(r.policyNote)) : "") +
                          "</div>"
                        : "";
                    return (
                        "<tr" + (isUrgent ? ' class="table-warning"' : "") + ">" +
                        emp +
                        "<td>" +
                        esc(r.workDate) +
                        "</td><td>" +
                        esc(hrs + " h") +
                        "</td><td>" +
                        esc(r.projectName || "—") +
                        "</td><td>" +
                        esc(r.overtimeTypeName || "—") +
                        "</td><td class=\"small\">" +
                        (r.salaryComponentCode
                            ? "<code>" +
                              esc(r.salaryComponentCode) +
                              "</code><div class=\"text-muted\">" +
                              esc(r.salaryComponentName || "") +
                              "</div>"
                            : '<span class="text-muted">—</span>') +
                        "</td><td>" +
                        esc(r.notes || "—") +
                        "</td><td><span class=\"badge badge-" +
                        statusMeta.badge +
                        ' badge-xs">' +
                        esc(statusMeta.label) +
                        "</span><div class=\"text-muted small mt-1\">" +
                        esc(statusMeta.note) +
                        "</div>" +
                        (isUrgent
                            ? '<div class="text-danger small fw-semibold mt-1">Prioritas: pending >24 jam</div>'
                            : "") +
                        policyLine +
                        "</td><td><a href=\"#\" data-hcm-ot-edit data-id=\"" +
                        esc(r.id) +
                        "\" data-user=\"" +
                        esc(r.userId) +
                        "\" data-date=\"" +
                        esc(r.workDate) +
                        "\" data-min=\"" +
                        esc(String(r.minutes)) +
                        "\" data-proj=\"" +
                        esc(r.projectName) +
                        "\" data-ot-type=\"" +
                        esc(tid) +
                        "\" data-request-type=\"" +
                        esc(r.requestType || "employee_request") +
                        "\" data-policy-note=\"" +
                        esc(r.policyNote || "") +
                        "\" data-status=\"" +
                        esc(r.status) +
                        "\" data-notes=\"" +
                        esc(r.notes) +
                        "\" data-bs-toggle=\"modal\" data-bs-target=\"#arcav_edit_overtime\"><i class=\"ti ti-edit\"></i></a> " +
                        (r.status === "pending"
                            ? '<a href="#" data-hcm-ot-delete="' + esc(r.id) + '"><i class="ti ti-trash"></i></a>'
                            : "") +
                        "</td></tr>"
                    );
                })
                .join("") ||
                '<tr><td colspan="' +
                (isAdmin ? "9" : "8") +
                '" class="text-center py-4 text-muted">No overtime requests for current filter.</td></tr>';
    }

    function setupOtFilters() {
        var wrap = document.querySelector("[data-hcm-ot-filters]");
        if (!wrap || wrap.getAttribute("data-bound") === "1") {
            return;
        }
        wrap.setAttribute("data-bound", "1");
        var statusSel = wrap.querySelector('[data-hcm-ot-filter="status"]');
        var requestTypeSel = wrap.querySelector('[data-hcm-ot-filter="requestType"]');
        var resetBtn = wrap.querySelector("[data-hcm-ot-filter-reset]");

        function syncFilters() {
            otFilters.status = String((statusSel && statusSel.value) || "").trim().toLowerCase();
            otFilters.requestType = String((requestTypeSel && requestTypeSel.value) || "").trim().toLowerCase();
            otPage = 1;
            reload();
        }

        if (statusSel) {
            statusSel.addEventListener("change", syncFilters);
        }
        if (requestTypeSel) {
            requestTypeSel.addEventListener("change", syncFilters);
        }
        if (resetBtn) {
            resetBtn.addEventListener("click", function () {
                otFilters = { status: "", requestType: "" };
                if (statusSel) {
                    statusSel.value = "";
                }
                if (requestTypeSel) {
                    requestTypeSel.value = "";
                }
                otPage = 1;
                reload();
            });
        }
    }

    function renderOtPagination(meta) {
        var foot = document.querySelector("[data-hcm-overtime-pagination]");
        var info = document.querySelector("[data-hcm-overtime-page-info]");
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
        var perPage = parseInt(pag.perPage, 10) || otPerPage;
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
        var prev = foot.querySelector("[data-hcm-overtime-prev]");
        var next = foot.querySelector("[data-hcm-overtime-next]");
        if (prev) {
            prev.disabled = page <= 1;
        }
        if (next) {
            next.disabled = page >= totalPages;
        }
    }

    function setupOtPagination() {
        var foot = document.querySelector("[data-hcm-overtime-pagination]");
        if (!foot) {
            return;
        }
        var prev = foot.querySelector("[data-hcm-overtime-prev]");
        var next = foot.querySelector("[data-hcm-overtime-next]");
        if (prev && !prev.getAttribute("data-bound")) {
            prev.setAttribute("data-bound", "1");
            prev.addEventListener("click", function () {
                if (otPage > 1) {
                    otPage -= 1;
                    reload();
                }
            });
        }
        if (next && !next.getAttribute("data-bound")) {
            next.setAttribute("data-bound", "1");
            next.addEventListener("click", function () {
                otPage += 1;
                reload();
            });
        }
    }

    function reload() {
        apiRequest("get", buildOtUrl(), null)
            .then(function (p) {
                if (!p || p.success !== true) {
                    notify("Failed to load overtime.", true);
                    return;
                }
                var pag = (p.meta && p.meta.pagination) || {};
                if (pag.totalPages != null && otPage > pag.totalPages && pag.totalPages > 0) {
                    otPage = pag.totalPages;
                    reload();
                    return;
                }
                var list = p.data || [];
                render(list);
                updateOvertimeStats(
                    list,
                    isAdmin && p.meta && p.meta.summary ? p.meta.summary : null
                );
                renderOtPagination(p.meta || {});
            })
            .catch(function (e) {
                notify(formatApiError(e.data, e.status), true);
            });
    }

    setupOtPagination();
    setupOtFilters();

    var addForm = document.querySelector('[data-hcm-ot-form="add"]');
    if (addForm) {
        var userSel = addForm.querySelector('[data-hcm-field="userId"]');
        if (userSel && isAdmin) {
            loadEmployeeOptions(userSel);
        }
        addForm.addEventListener("submit", function (e) {
            e.preventDefault();
            if (!ArcavValidation.validateForm(addForm)) { return; }
            if (isAdmin && userSel && !userSel.value) {
                notify("Pilih karyawan.", true);
                return;
            }
            var payload = {
                workDate: addForm.querySelector('[data-hcm-field="workDate"]').value,
                minutes: parseInt(addForm.querySelector('[data-hcm-field="minutes"]').value, 10),
                projectName: addForm.querySelector('[data-hcm-field="projectName"]').value.trim() || null,
                notes: addForm.querySelector('[data-hcm-field="notes"]').value.trim() || null,
            };
            var reqTypeA = addForm.querySelector('[data-hcm-field="requestType"]');
            var policyNoteA = addForm.querySelector('[data-hcm-field="policyNote"]');
            var statusA = addForm.querySelector('[data-hcm-field="status"]');
            if (isAdmin && reqTypeA) {
                payload.requestType = reqTypeA.value || "employee_request";
            }
            if (isAdmin && policyNoteA) {
                payload.policyNote = policyNoteA.value.trim() || null;
            }
            if (isAdmin && statusA) {
                payload.status = statusA.value || "pending";
            }
            var otSel = addForm.querySelector('[data-hcm-field="overtimeTypeId"]');
            if (otSel && otSel.value) {
                payload.overtimeTypeId = parseInt(otSel.value, 10);
            }
            if (isAdmin && userSel && userSel.value) {
                payload.userId = parseInt(userSel.value, 10);
            }
            apiRequest("post", "/v1/hcm/overtime-requests", payload)
                .then(function (p) {
                    if (!p || p.success !== true) {
                        notify("Submit failed.", true);
                        return;
                    }
                    notify("Submitted.", false);
                    (function () {
                        var el = document.getElementById("arcav_add_overtime");
                        var mi = el && window.bootstrap && window.bootstrap.Modal.getInstance(el);
                        if (mi) {
                            mi.hide();
                        }
                    })();
                    addForm.reset();
                    fillOvertimeTypeSelects();
                    reload();
                })
                .catch(function (err) {
                    notify(formatApiError(err.data, err.status), true);
                });
        });
    }

    var editForm = document.querySelector('[data-hcm-ot-form="edit"]');
    if (editForm) {
        document.addEventListener("click", function (e) {
            var btn = e.target.closest("[data-hcm-ot-edit]");
            if (!btn) {
                return;
            }
            editForm.querySelector('[data-hcm-field="id"]').value = btn.dataset.id || "";
            editForm.querySelector('[data-hcm-field="ownerUserId"]').value = btn.dataset.user || "";
            editForm.querySelector('[data-hcm-field="workDate"]').value = btn.dataset.date || "";
            editForm.querySelector('[data-hcm-field="minutes"]').value = btn.dataset.min || "";
            editForm.querySelector('[data-hcm-field="projectName"]').value = btn.dataset.proj || "";
            editForm.querySelector('[data-hcm-field="status"]').value = btn.dataset.status || "pending";
            editForm.querySelector('[data-hcm-field="notes"]').value = btn.dataset.notes || "";
            var reqTypeE = editForm.querySelector('[data-hcm-field="requestType"]');
            if (reqTypeE) {
                reqTypeE.value = btn.getAttribute("data-request-type") || "employee_request";
            }
            var policyNoteE = editForm.querySelector('[data-hcm-field="policyNote"]');
            if (policyNoteE) {
                policyNoteE.value = btn.getAttribute("data-policy-note") || "";
            }
            var otSelE = editForm.querySelector('[data-hcm-field="overtimeTypeId"]');
            if (otSelE) {
                otSelE.value = btn.getAttribute("data-ot-type") || "";
            }
        });
        editForm.addEventListener("submit", function (e) {
            e.preventDefault();
            if (!ArcavValidation.validateForm(editForm)) { return; }
            var id = editForm.querySelector('[data-hcm-field="id"]').value;
            var owner = editForm.querySelector('[data-hcm-field="ownerUserId"]').value;
            if (!id) {
                return;
            }
            var me = window.__arcav_me_id;
            var payload;
            if (isAdmin && String(owner) !== String(me)) {
                payload = {
                    status: editForm.querySelector('[data-hcm-field="status"]').value,
                    notes: editForm.querySelector('[data-hcm-field="notes"]').value.trim() || null,
                };
            } else {
                payload = {
                    workDate: editForm.querySelector('[data-hcm-field="workDate"]').value,
                    minutes: parseInt(editForm.querySelector('[data-hcm-field="minutes"]').value, 10),
                    projectName: editForm.querySelector('[data-hcm-field="projectName"]').value.trim() || null,
                    notes: editForm.querySelector('[data-hcm-field="notes"]').value.trim() || null,
                };
                var reqType = editForm.querySelector('[data-hcm-field="requestType"]');
                if (reqType) {
                    payload.requestType = reqType.value || "employee_request";
                }
                var policyNote = editForm.querySelector('[data-hcm-field="policyNote"]');
                if (policyNote) {
                    payload.policyNote = policyNote.value.trim() || null;
                }
                var otE = editForm.querySelector('[data-hcm-field="overtimeTypeId"]');
                if (otE) {
                    payload.overtimeTypeId = otE.value ? parseInt(otE.value, 10) : null;
                }
            }
            apiRequest("put", "/v1/hcm/overtime-requests/" + encodeURIComponent(id), payload)
                .then(function (p) {
                    if (!p || p.success !== true) {
                        notify("Update failed.", true);
                        return;
                    }
                    notify("Updated.", false);
                    (function () {
                        var el = document.getElementById("arcav_edit_overtime");
                        var mi = el && window.bootstrap && window.bootstrap.Modal.getInstance(el);
                        if (mi) {
                            mi.hide();
                        }
                    })();
                    reload();
                })
                .catch(function (err) {
                    notify(formatApiError(err.data, err.status), true);
                });
        });
        document.addEventListener("click", function (e) {
            var del = e.target.closest("[data-hcm-ot-delete]");
            if (!del) {
                return;
            }
            e.preventDefault();
            var oid = del.getAttribute("data-hcm-ot-delete");
            var run =
                window.ArcavUi && typeof window.ArcavUi.confirmDelete === "function"
                    ? window.ArcavUi.confirmDelete("Pengajuan lembur pending ini akan dihapus. Lanjutkan?", "Hapus lembur")
                    : Promise.resolve(false);
            run.then(function (ok) {
                if (!ok) {
                    return;
                }
                apiRequest("delete", "/v1/hcm/overtime-requests/" + encodeURIComponent(oid), null)
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

    ["arcav_add_overtime", "arcav_edit_overtime"].forEach(function (id) {
        var el = document.getElementById(id);
        if (el) {
            el.addEventListener("shown.bs.modal", function () {
                var firstInput = document.querySelector("#" + id + " input:not([type=hidden]):not([type=password]), #" + id + " select");
                if (firstInput) setTimeout(function () { firstInput.focus(); }, 100);
            });
        }
    });

    apiRequest("get", "/v1/hcm/overtime-types", null)
        .then(function (tp) {
            if (tp && tp.success === true && Array.isArray(tp.data)) {
                typeListCache = tp.data;
            } else {
                typeListCache = [];
            }
            fillOvertimeTypeSelects();
        })
        .catch(function () {
            typeListCache = [];
            fillOvertimeTypeSelects();
        })
        .then(function () {
            reload();
        });
}
