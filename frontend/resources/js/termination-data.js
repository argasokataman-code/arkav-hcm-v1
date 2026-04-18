(function (window, document) {
    "use strict";

    function esc(v) {
        return String(v || "")
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#39;");
    }

    function notify(msg, isError) {
        if (window.ArcavUi && typeof window.ArcavUi.toast === "function") {
            window.ArcavUi.toast(msg, isError ? "danger" : "success");
            return;
        }
        var c = document.querySelector("[data-hcm-toast-container]") || document.body.appendChild(Object.assign(document.createElement("div"), { style: "position:fixed;top:16px;right:16px;z-index:1080" }));
        c.setAttribute("data-hcm-toast-container", "1");
        var t = document.createElement("div");
        t.className = "alert " + (isError ? "alert-danger" : "alert-success") + " shadow-sm mb-2";
        t.textContent = msg;
        c.appendChild(t);
        window.setTimeout(function () { t.remove(); }, 2500);
    }

    function flash(el, msg, isError) {
        if (!el) return;
        el.classList.remove("d-none", "alert-success", "alert-danger");
        el.classList.add(isError ? "alert-danger" : "alert-success");
        el.textContent = msg;
    }

    function clearFlash(el) {
        if (!el) return;
        el.classList.add("d-none");
        el.textContent = "";
    }

    function formatSaveError(status, data) {
        if (data && typeof data === "object") {
            if (data.error && data.error.message) {
                return String(data.error.message);
            }
            if (data.message && data.errors && typeof data.errors === "object") {
                var parts = [];
                Object.keys(data.errors).forEach(function (k) {
                    var arr = data.errors[k];
                    if (Array.isArray(arr) && arr.length) {
                        parts.push(arr[0]);
                    }
                });
                if (parts.length) {
                    return parts.join(" ");
                }
                return String(data.message);
            }
            if (data.message) {
                return String(data.message);
            }
        }
        if (status === 422) {
            return "Validasi gagal. Periksa isian form.";
        }
        return "Save failed.";
    }

    function apiRequest(method, url, body) {
        var headers = { Accept: "application/json" };
        headers["Content-Type"] = "application/json";
        if (window.axios) {
            return window.axios({
                method: method,
                url: url,
                data: body || null,
                headers: headers,
                withCredentials: true,
            }).then(function (res) {
                return res.data;
            }).catch(function (err) {
                var st = err && err.response ? err.response.status : 0;
                var d = err && err.response ? err.response.data : null;
                if (window.AuthApi && window.AuthApi.handleUnauthorizedFromApi && window.AuthApi.handleUnauthorizedFromApi(st, d)) {
                    return null;
                }
                return Promise.reject({ status: st, data: d });
            });
        }
        return fetch(url, {
            method: method.toUpperCase(),
            headers: headers,
            credentials: "same-origin",
            body: body ? JSON.stringify(body) : undefined,
        }).then(function (res) {
            return res.json().catch(function () { return {}; }).then(function (data) {
                if (!res.ok) {
                    if (window.AuthApi && window.AuthApi.handleUnauthorizedFromApi && window.AuthApi.handleUnauthorizedFromApi(res.status, data)) {
                        return null;
                    }
                    return Promise.reject({ status: res.status, data: data });
                }
                return data;
            });
        });
    }

    function shortReason(text) {
        var s = String(text || "").trim();
        if (s.length <= 56) return s;
        return s.slice(0, 53) + "…";
    }

    function statusBadgeClass(st) {
        var s = String(st || "").toLowerCase();
        if (s === "approved") return "success";
        if (s === "cancelled") return "secondary";
        return "warning";
    }

    var tbody = document.querySelector("[data-arcav-terminations-tbody]");
    var addBtn = document.querySelector("[data-arcav-termination-add]");

    var modalEl = document.getElementById("arcav_termination_modal");
    var modal = modalEl && window.bootstrap && window.bootstrap.Modal ? window.bootstrap.Modal.getOrCreateInstance(modalEl) : null;
    var form = modalEl ? modalEl.querySelector("[data-arcav-termination-form]") : null;
    var modalTitle = modalEl ? modalEl.querySelector("[data-arcav-termination-modal-title]") : null;
    var flashEl = modalEl ? modalEl.querySelector("[data-arcav-termination-flash]") : null;
    var idInput = modalEl ? modalEl.querySelector("[data-arcav-termination-id]") : null;
    var userSelect = modalEl ? modalEl.querySelector("[data-arcav-termination-user]") : null;
    var typeInput = modalEl ? modalEl.querySelector("[data-arcav-termination-type]") : null;
    var noticeInput = modalEl ? modalEl.querySelector("[data-arcav-termination-notice-date]") : null;
    var termDateInput = modalEl ? modalEl.querySelector("[data-arcav-termination-termination-date]") : null;
    var deptInput = modalEl ? modalEl.querySelector("[data-arcav-termination-department]") : null;
    var reasonInput = modalEl ? modalEl.querySelector("[data-arcav-termination-reason]") : null;
    var notesInput = modalEl ? modalEl.querySelector("[data-arcav-termination-notes]") : null;
    var statusSelect = modalEl ? modalEl.querySelector("[data-arcav-termination-status]") : null;

    var employeeOptionsLoaded = false;
    var employeeDetailCache = {};

    function loadEmployeeOptions() {
        if (!userSelect || employeeOptionsLoaded) return Promise.resolve();
        return apiRequest("get", "/v1/hcm/employees?perPage=100", null).then(function (res) {
            if (!res || !res.success) {
                userSelect.innerHTML = '<option value="">Failed to load</option>';
                return;
            }
            var rows = Array.isArray(res.data) ? res.data : [];
            if (!rows.length) {
                userSelect.innerHTML = '<option value="">No employees</option>';
                employeeOptionsLoaded = true;
                return;
            }
            userSelect.innerHTML = '<option value="">Select employee…</option>' + rows.map(function (u) {
                return '<option value="' + esc(u.id) + '">' + esc(u.fullName || u.name || ("User " + u.id)) + (u.email ? (" — " + esc(u.email)) : "") + "</option>";
            }).join("");
            employeeOptionsLoaded = true;
        }).catch(function () {
            userSelect.innerHTML = '<option value="">Failed to load</option>';
        });
    }

    function getEmployeeDetail(userId) {
        var id = String(userId || "");
        if (!id) return Promise.resolve(null);
        if (employeeDetailCache[id]) return Promise.resolve(employeeDetailCache[id]);
        return apiRequest("get", "/v1/hcm/employees/" + encodeURIComponent(id), null).then(function (res) {
            var data = res && res.success ? (res.data || null) : null;
            employeeDetailCache[id] = data;
            return data;
        }).catch(function () {
            return null;
        });
    }

    function autoFillDepartment(userId) {
        if (!deptInput) return Promise.resolve();
        return getEmployeeDetail(userId).then(function (emp) {
            if (!emp) return;
            var team = (emp.team && emp.team !== "-" && emp.team !== "—") ? emp.team : "";
            deptInput.value = team;
        });
    }

    function renderRows(rows) {
        if (!tbody) return;
        if (!rows.length) {
            tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-4">No terminations found.</td></tr>';
            return;
        }
        tbody.innerHTML = rows.map(function (r) {
            var emp = r.employee || {};
            var name = emp.name || "—";
            var dept = r.department || "—";
            var ttype = r.terminationType || "—";
            var reason = shortReason(r.reason);
            var notice = r.noticeDate || "—";
            var tdate = r.terminationDate || "—";
            var st = String(r.status || "pending");
            var badge = statusBadgeClass(st);
            return (
                '<tr data-termination-row="' + esc(r.id) + '">' +
                '<td><div class="d-flex align-items-center"><div class="avatar avatar-md me-2 bg-light text-dark d-flex align-items-center justify-content-center rounded-circle">' + esc(String(name).trim().slice(0, 1).toUpperCase() || "U") + '</div>' +
                '<div><h6 class="fw-medium mb-0">' + esc(name) + '</h6><small class="text-muted">' + esc(emp.email || "") + '</small></div></div></td>' +
                "<td>" + esc(dept) + "</td>" +
                "<td>" + esc(ttype) + "</td>" +
                '<td class="text-break">' + esc(reason) + "</td>" +
                "<td>" + esc(notice) + "</td>" +
                "<td>" + esc(tdate) + "</td>" +
                '<td><span class="badge badge-' + esc(badge) + ' d-inline-flex align-items-center badge-xs"><i class="ti ti-point-filled me-1"></i>' + esc(st) + "</span></td>" +
                '<td><div class="action-icon d-inline-flex">' +
                '<a href="#" class="me-2" title="Detail" data-arcav-termination-view="' + esc(r.id) + '"><i class="ti ti-eye"></i></a>' +
                '<a href="#" class="me-2" data-arcav-termination-edit="' + esc(r.id) + '"><i class="ti ti-edit"></i></a>' +
                '<a href="#" data-arcav-termination-delete="' + esc(r.id) + '"><i class="ti ti-trash"></i></a>' +
                "</div></td>" +
                "</tr>"
            );
        }).join("");
    }

    function loadList() {
        if (!tbody) return;
        tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-4">Loading…</td></tr>';
        apiRequest("get", "/v1/hcm/terminations", null).then(function (res) {
            if (!res || !res.success) {
                renderRows([]);
                return;
            }
            renderRows(Array.isArray(res.data) ? res.data : []);
        }).catch(function () {
            tbody.innerHTML = '<tr><td colspan="8" class="text-center text-danger py-4">Failed to load terminations.</td></tr>';
        });
    }

    function openModal(row) {
        if (!modal || !form) return;
        clearFlash(flashEl);
        if (modalTitle) modalTitle.textContent = row ? "Edit Termination" : "Add Termination";
        if (idInput) idInput.value = row ? String(row.id) : "";
        if (typeInput) typeInput.value = row ? (row.terminationType || "") : "";
        if (noticeInput) noticeInput.value = row ? (row.noticeDate || "") : "";
        if (termDateInput) termDateInput.value = row ? (row.terminationDate || "") : "";
        if (reasonInput) reasonInput.value = row ? (row.reason || "") : "";
        if (notesInput) notesInput.value = row ? (row.notes || "") : "";
        if (statusSelect) statusSelect.value = row ? String(row.status || "pending") : "pending";
        if (deptInput) {
            deptInput.disabled = true;
            deptInput.value = row ? (row.department || "") : "";
        }

        loadEmployeeOptions().then(function () {
            if (userSelect) {
                userSelect.disabled = !!row;
                userSelect.value = row && row.employee ? String(row.employee.id) : "";
            }
            if (!row && userSelect && userSelect.value) {
                return autoFillDepartment(userSelect.value);
            }
            return Promise.resolve();
        }).finally(function () {
            modal.show();
        });
    }

    if (addBtn) {
        addBtn.addEventListener("click", function (e) {
            e.preventDefault();
            openModal(null);
        });
    }

    if (userSelect) {
        userSelect.addEventListener("change", function () {
            if (userSelect.disabled) return;
            var v = userSelect.value;
            if (!v) {
                if (deptInput) deptInput.value = "";
                return;
            }
            autoFillDepartment(v);
        });
    }

    if (tbody) {
        tbody.addEventListener("click", function (e) {
            var t = e.target;
            if (!t) return;
            var edit = t.closest && t.closest("[data-arcav-termination-edit]");
            var del = t.closest && t.closest("[data-arcav-termination-delete]");
            if (edit) {
                e.preventDefault();
                var id = edit.getAttribute("data-arcav-termination-edit");
                apiRequest("get", "/v1/hcm/terminations?perPage=100", null).then(function (res) {
                    var rows = res && res.success && Array.isArray(res.data) ? res.data : [];
                    var r = rows.find(function (x) { return String(x.id) === String(id); }) || null;
                    openModal(r);
                }).catch(function () {
                    notify("Failed to load termination.", true);
                });
                return;
            }
            if (del) {
                e.preventDefault();
                var did = del.getAttribute("data-arcav-termination-delete");
                var go = function () {
                    apiRequest("delete", "/v1/hcm/terminations/" + encodeURIComponent(String(did)), null).then(function (res) {
                        if (!res) return;
                        if (res.success) {
                            notify("Termination deleted.");
                            loadList();
                            return;
                        }
                        notify("Failed to delete termination.", true);
                    }).catch(function () {
                        notify("Failed to delete termination.", true);
                    });
                };
                if (window.ArcavUi && typeof window.ArcavUi.confirmDelete === "function") {
                    window.ArcavUi.confirmDelete("Hapus termination ini?", "Delete Termination").then(function (ok) {
                        if (ok) go();
                    });
                } else {
                    go();
                }
            }
        });
    }

    if (form) {
        form.addEventListener("submit", function (e) {
            e.preventDefault();
            clearFlash(flashEl);
            if (!form.checkValidity()) {
                form.reportValidity();
                flash(flashEl, "Mohon lengkapi field yang wajib.", true);
                return;
            }
            var nd = noticeInput ? String(noticeInput.value || "") : "";
            var td = termDateInput ? String(termDateInput.value || "") : "";
            if (nd && td && nd > td) {
                flash(flashEl, "Termination date harus pada atau setelah notice date.", true);
                return;
            }
            var ttype = typeInput ? String(typeInput.value || "").trim() : "";
            var dept = deptInput ? String(deptInput.value || "").trim() : "";
            var reason = reasonInput ? String(reasonInput.value || "").trim() : "";
            var notes = notesInput ? String(notesInput.value || "") : "";
            var st = statusSelect ? String(statusSelect.value || "pending") : "pending";
            if (!ttype) {
                flash(flashEl, "Termination type wajib diisi.", true);
                return;
            }
            if (ttype.length > 150) {
                flash(flashEl, "Termination type maksimal 150 karakter.", true);
                return;
            }
            if (dept.length > 150) {
                flash(flashEl, "Department maksimal 150 karakter.", true);
                return;
            }
            if (reason.length > 2000) {
                flash(flashEl, "Reason maksimal 2000 karakter.", true);
                return;
            }
            if (notes.length > 2000) {
                flash(flashEl, "Notes maksimal 2000 karakter.", true);
                return;
            }
            var id = idInput && idInput.value ? String(idInput.value) : "";
            var payload = {
                terminationType: ttype,
                noticeDate: nd,
                terminationDate: td,
                reason: reason,
                notes: notes || null,
                status: st,
            };
            if (dept) {
                payload.department = dept;
            } else {
                payload.department = null;
            }
            if (!id) {
                payload.userId = userSelect ? Number(userSelect.value) : null;
                if (!payload.userId) {
                    flash(flashEl, "Employee wajib dipilih.", true);
                    return;
                }
            }
            var method = id ? "put" : "post";
            var url = id ? ("/v1/hcm/terminations/" + encodeURIComponent(id)) : "/v1/hcm/terminations";
            apiRequest(method, url, payload).then(function (res) {
                if (!res) return;
                if (res.success) {
                    flash(flashEl, "Saved.", false);
                    window.setTimeout(function () {
                        if (modal) modal.hide();
                        loadList();
                    }, 400);
                    return;
                }
                flash(flashEl, (res.error && res.error.message) ? res.error.message : "Save failed.", true);
            }).catch(function (err) {
                var stx = err && err.status ? err.status : 0;
                var d = err && err.data ? err.data : null;
                flash(flashEl, formatSaveError(stx, d), true);
            });
        });
    }

    var detailModalEl = document.getElementById("arcav_termination_detail_modal");
    var detailModal = detailModalEl && window.bootstrap && window.bootstrap.Modal
        ? window.bootstrap.Modal.getOrCreateInstance(detailModalEl)
        : null;

    function openTerminationDetail(terminationId) {
        if (!detailModalEl || !detailModal) return;
        var errEl = detailModalEl.querySelector("[data-arcav-termination-detail-error]");
        var bodyWrap = detailModalEl.querySelector("[data-arcav-termination-detail-body]");
        var loadingEl = detailModalEl.querySelector("[data-arcav-termination-detail-loading]");
        if (errEl) {
            errEl.classList.add("d-none");
            errEl.textContent = "";
        }
        if (bodyWrap) bodyWrap.classList.add("d-none");
        if (loadingEl) loadingEl.classList.remove("d-none");
        detailModal.show();
        apiRequest("get", "/v1/hcm/terminations/" + encodeURIComponent(String(terminationId)), null).then(function (res) {
            if (loadingEl) loadingEl.classList.add("d-none");
            if (!res || !res.success || !res.data) {
                if (errEl) {
                    errEl.textContent = (res && res.error && res.error.message) ? res.error.message : "Tidak dapat memuat termination.";
                    errEl.classList.remove("d-none");
                }
                return;
            }
            var d = res.data;
            var emp = d.employee || {};
            function setDetail(sel, text) {
                var el = detailModalEl.querySelector(sel);
                if (el) el.textContent = text || "—";
            }
            setDetail("[data-arcav-termination-detail-employee]", emp.name || "—");
            setDetail("[data-arcav-termination-detail-email]", emp.email || "—");
            setDetail("[data-arcav-termination-detail-department]", d.department || "—");
            setDetail("[data-arcav-termination-detail-type]", d.terminationType || "—");
            setDetail("[data-arcav-termination-detail-status]", d.status || "—");
            setDetail("[data-arcav-termination-detail-notice-date]", d.noticeDate || "—");
            setDetail("[data-arcav-termination-detail-termination-date]", d.terminationDate || "—");
            setDetail("[data-arcav-termination-detail-reason]", d.reason || "—");
            setDetail("[data-arcav-termination-detail-notes]", d.notes || "—");
            setDetail("[data-arcav-termination-detail-created]", d.createdAt || "—");
            var prof = detailModalEl.querySelector("[data-arcav-termination-detail-profile]");
            if (prof && emp.id) {
                try {
                    var u = new URL(prof.getAttribute("href"), window.location.origin);
                    u.searchParams.set("id", String(emp.id));
                    prof.setAttribute("href", u.pathname + u.search);
                } catch (_e) {
                    prof.setAttribute("href", "/employee-details?id=" + encodeURIComponent(String(emp.id)));
                }
            }
            if (bodyWrap) bodyWrap.classList.remove("d-none");
        }).catch(function (err) {
            if (loadingEl) loadingEl.classList.add("d-none");
            var st = err && err.status ? err.status : 0;
            var data = err && err.data ? err.data : null;
            if (errEl) {
                errEl.textContent = formatSaveError(st, data);
                errEl.classList.remove("d-none");
            }
        });
    }

    document.addEventListener("click", function (e) {
        var view = e.target.closest && e.target.closest("[data-arcav-termination-view]");
        if (!view) return;
        e.preventDefault();
        var tid = view.getAttribute("data-arcav-termination-view");
        if (tid) openTerminationDetail(tid);
    });

    window.ArcavTerminationDetail = { open: openTerminationDetail };

    if (tbody) {
        apiRequest("get", "/v1/identity/auth/me", null).then(function (me) {
            if (!me || !me.success || !me.data || !me.data.permissions || !me.data.permissions['termination.view']) {
                window.location.replace("/employee-dashboard");
                return;
            }
            loadList();
        }).catch(function () {
            window.location.replace("/employee-dashboard");
        });
    }
})(window, document);
