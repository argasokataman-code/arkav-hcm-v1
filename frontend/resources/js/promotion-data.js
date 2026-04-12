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

    /** Laravel 422 + Arcav envelope */
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

    var promotionsTbody = document.querySelector("[data-arcav-promotions-tbody]");
    var addBtn = document.querySelector("[data-arcav-promotion-add]");

    var modalEl = document.getElementById("arcav_promotion_modal");
    var modal = modalEl && window.bootstrap && window.bootstrap.Modal ? window.bootstrap.Modal.getOrCreateInstance(modalEl) : null;
    var form = modalEl ? modalEl.querySelector("[data-arcav-promotion-form]") : null;
    var modalTitle = modalEl ? modalEl.querySelector("[data-arcav-promotion-modal-title]") : null;
    var flashEl = modalEl ? modalEl.querySelector("[data-arcav-promotion-flash]") : null;
    var idInput = modalEl ? modalEl.querySelector("[data-arcav-promotion-id]") : null;
    var userSelect = modalEl ? modalEl.querySelector("[data-arcav-promotion-user]") : null;
    var dateInput = modalEl ? modalEl.querySelector("[data-arcav-promotion-date]") : null;
    var deptInput = modalEl ? modalEl.querySelector("[data-arcav-promotion-department]") : null;
    var fromInput = modalEl ? modalEl.querySelector("[data-arcav-promotion-from]") : null;
    var toInput = modalEl ? modalEl.querySelector("[data-arcav-promotion-to]") : null;
    var notesInput = modalEl ? modalEl.querySelector("[data-arcav-promotion-notes]") : null;

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

    function autoFillFromEmployee(userId) {
        if (!deptInput && !fromInput) return;
        return getEmployeeDetail(userId).then(function (emp) {
            if (!emp) return;
            var team = (emp.team && emp.team !== "-" && emp.team !== "—") ? emp.team : "";
            var des = (emp.designation && emp.designation !== "-" && emp.designation !== "—") ? emp.designation : "";
            if (deptInput) deptInput.value = team;
            if (fromInput) fromInput.value = des;
        });
    }

    var designationOptionsLoaded = false;
    function loadDesignationOptions() {
        if (!toInput) return Promise.resolve();
        if (designationOptionsLoaded) return Promise.resolve();
        return apiRequest("get", "/v1/hcm/designations", null).then(function (res) {
            if (!res || !res.success) return;
            var rows = Array.isArray(res.data) ? res.data : [];
            if (!rows.length) return;
            var opts = '<option value="">Select designation…</option>' + rows
                .filter(function (d) { return d && d.isActive !== false; })
                .map(function (d) {
                    var label = d.department && d.department !== "Unassigned"
                        ? (d.name + " — " + d.department)
                        : d.name;
                    return '<option value="' + esc(d.name) + '">' + esc(label) + "</option>";
                }).join("");
            toInput.innerHTML = opts;
            designationOptionsLoaded = true;
        }).catch(function () {});
    }

    function setDesignationToValue(val) {
        if (!toInput) return;
        var v = String(val || "").trim();
        if (!v) {
            toInput.value = "";
            return;
        }
        var found = false;
        var i;
        for (i = 0; i < toInput.options.length; i++) {
            if (toInput.options[i].value === v) {
                found = true;
                break;
            }
        }
        if (!found) {
            var o = document.createElement("option");
            o.value = v;
            o.textContent = v + " (di luar master)";
            toInput.appendChild(o);
        }
        toInput.value = v;
    }

    function renderPromotions(rows) {
        if (!promotionsTbody) return;
        if (!rows.length) {
            promotionsTbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">No promotions found.</td></tr>';
            return;
        }
        promotionsTbody.innerHTML = rows.map(function (p) {
            var emp = p.employee || {};
            var name = emp.name || "—";
            var dept = p.department || "—";
            var from = p.designationFrom || "—";
            var to = p.designationTo || "—";
            var date = p.promotionDate || "—";
            return (
                '<tr data-promotion-row="' + esc(p.id) + '">' +
                '<td><div class="d-flex align-items-center"><div class="avatar avatar-md me-2 bg-light text-dark d-flex align-items-center justify-content-center rounded-circle">' + esc(String(name).trim().slice(0, 1).toUpperCase() || "U") + '</div>' +
                '<div><h6 class="fw-medium mb-0">' + esc(name) + '</h6><small class="text-muted">' + esc(emp.email || "") + '</small></div></div></td>' +
                "<td>" + esc(dept) + "</td>" +
                "<td>" + esc(from) + "</td>" +
                "<td>" + esc(to) + "</td>" +
                "<td>" + esc(date) + "</td>" +
                '<td><div class="action-icon d-inline-flex">' +
                '<a href="#" class="me-2" title="Detail" data-arcav-promotion-view="' + esc(p.id) + '"><i class="ti ti-eye"></i></a>' +
                '<a href="#" class="me-2" data-arcav-promotion-edit="' + esc(p.id) + '"><i class="ti ti-edit"></i></a>' +
                '<a href="#" data-arcav-promotion-delete="' + esc(p.id) + '"><i class="ti ti-trash"></i></a>' +
                "</div></td>" +
                "</tr>"
            );
        }).join("");
    }

    function loadPromotions() {
        if (!promotionsTbody) return;
        promotionsTbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">Loading…</td></tr>';
        apiRequest("get", "/v1/hcm/promotions", null).then(function (res) {
            if (!res || !res.success) {
                renderPromotions([]);
                return;
            }
            renderPromotions(Array.isArray(res.data) ? res.data : []);
        }).catch(function () {
            promotionsTbody.innerHTML = '<tr><td colspan="6" class="text-center text-danger py-4">Failed to load promotions.</td></tr>';
        });
    }

    function openModal(promotion) {
        if (!modal || !form) return;
        clearFlash(flashEl);
        var pendingDesignationTo = promotion ? (promotion.designationTo || "") : "";
        if (modalTitle) modalTitle.textContent = promotion ? "Edit Promotion" : "Add Promotion";
        if (idInput) idInput.value = promotion ? String(promotion.id) : "";
        if (deptInput) {
            deptInput.disabled = true;
            deptInput.value = promotion ? (promotion.department || "") : "";
        }
        if (fromInput) {
            fromInput.disabled = true;
            fromInput.value = promotion ? (promotion.designationFrom || "") : "";
        }
        if (toInput) toInput.value = "";
        if (dateInput) dateInput.value = promotion ? (promotion.promotionDate || "") : "";
        if (notesInput) notesInput.value = promotion ? (promotion.notes || "") : "";

        loadEmployeeOptions().then(function () {
            if (userSelect) {
                userSelect.disabled = !!promotion;
                userSelect.value = promotion && promotion.employee ? String(promotion.employee.id) : "";
            }
            if (!promotion && userSelect && userSelect.value) {
                return autoFillFromEmployee(userSelect.value);
            }
            return Promise.resolve();
        }).then(function () {
            return loadDesignationOptions();
        }).then(function () {
            setDesignationToValue(pendingDesignationTo);
        }).finally(function () {
            modal.show();
        });
    }

    function findPromotionById(id) {
        var row = promotionsTbody && promotionsTbody.querySelector('tr[data-promotion-row="' + CSS.escape(String(id)) + '"]');
        if (!row) return null;
        // Data is not stored on DOM; simplest is re-fetch from list after opening.
        return null;
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
                if (fromInput) fromInput.value = "";
                return;
            }
            autoFillFromEmployee(v);
        });
    }

    if (promotionsTbody) {
        promotionsTbody.addEventListener("click", function (e) {
            var t = e.target;
            if (!t) return;
            var edit = t.closest && t.closest("[data-arcav-promotion-edit]");
            var del = t.closest && t.closest("[data-arcav-promotion-delete]");
            if (edit) {
                e.preventDefault();
                var id = edit.getAttribute("data-arcav-promotion-edit");
                // For simplicity, open empty then load list and find by id is not available.
                // We'll re-fetch list and find it.
                apiRequest("get", "/v1/hcm/promotions?perPage=100", null).then(function (res) {
                    var rows = res && res.success && Array.isArray(res.data) ? res.data : [];
                    var p = rows.find(function (x) { return String(x.id) === String(id); }) || null;
                    openModal(p);
                }).catch(function () {
                    notify("Failed to load promotion.", true);
                });
                return;
            }
            if (del) {
                e.preventDefault();
                var did = del.getAttribute("data-arcav-promotion-delete");
                var go = function () {
                    apiRequest("delete", "/v1/hcm/promotions/" + encodeURIComponent(String(did)), null).then(function (res) {
                        if (!res) return;
                        if (res.success) {
                            notify("Promotion deleted.");
                            loadPromotions();
                            return;
                        }
                        notify("Failed to delete promotion.", true);
                    }).catch(function () {
                        notify("Failed to delete promotion.", true);
                    });
                };
                if (window.ArcavUi && typeof window.ArcavUi.confirmDelete === "function") {
                    window.ArcavUi.confirmDelete("Hapus promotion ini?", "Delete Promotion").then(function (ok) {
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
            var dept = deptInput ? String(deptInput.value || "").trim() : "";
            var dfrom = fromInput ? String(fromInput.value || "").trim() : "";
            var dto = toInput ? String(toInput.value || "").trim() : "";
            var notes = notesInput ? String(notesInput.value || "") : "";
            if (dept.length > 150 || dfrom.length > 150 || dto.length > 150) {
                flash(flashEl, "Department / designation maksimal 150 karakter (sesuai API).", true);
                return;
            }
            if (notes.length > 2000) {
                flash(flashEl, "Notes maksimal 2000 karakter.", true);
                return;
            }
            var id = idInput && idInput.value ? String(idInput.value) : "";
            var payload = {
                userId: userSelect ? Number(userSelect.value) : null,
                department: dept,
                designationFrom: dfrom,
                designationTo: dto,
                promotionDate: dateInput ? dateInput.value : "",
                notes: notes,
            };
            if (!payload.userId) {
                flash(flashEl, "Employee wajib dipilih.", true);
                return;
            }
            var method = id ? "put" : "post";
            var url = id ? ("/v1/hcm/promotions/" + encodeURIComponent(id)) : "/v1/hcm/promotions";
            apiRequest(method, url, payload).then(function (res) {
                if (!res) return;
                if (res.success) {
                    flash(flashEl, "Saved.", false);
                    window.setTimeout(function () {
                        if (modal) modal.hide();
                        loadPromotions();
                    }, 400);
                    return;
                }
                flash(flashEl, (res.error && res.error.message) ? res.error.message : "Save failed.", true);
            }).catch(function (err) {
                var st = err && err.status ? err.status : 0;
                var d = err && err.data ? err.data : null;
                flash(flashEl, formatSaveError(st, d), true);
            });
        });
    }

    var detailModalEl = document.getElementById("arcav_promotion_detail_modal");
    var detailModal = detailModalEl && window.bootstrap && window.bootstrap.Modal
        ? window.bootstrap.Modal.getOrCreateInstance(detailModalEl)
        : null;

    function openPromotionDetail(promotionId) {
        if (!detailModalEl || !detailModal) return;
        var errEl = detailModalEl.querySelector("[data-arcav-promotion-detail-error]");
        var bodyWrap = detailModalEl.querySelector("[data-arcav-promotion-detail-body]");
        var loadingEl = detailModalEl.querySelector("[data-arcav-promotion-detail-loading]");
        if (errEl) {
            errEl.classList.add("d-none");
            errEl.textContent = "";
        }
        if (bodyWrap) bodyWrap.classList.add("d-none");
        if (loadingEl) loadingEl.classList.remove("d-none");
        detailModal.show();
        apiRequest("get", "/v1/hcm/promotions/" + encodeURIComponent(String(promotionId)), null).then(function (res) {
            if (loadingEl) loadingEl.classList.add("d-none");
            if (!res || !res.success || !res.data) {
                if (errEl) {
                    errEl.textContent = (res && res.error && res.error.message) ? res.error.message : "Tidak dapat memuat promotion.";
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
            setDetail("[data-arcav-promotion-detail-employee]", emp.name || "—");
            setDetail("[data-arcav-promotion-detail-email]", emp.email || "—");
            setDetail("[data-arcav-promotion-detail-department]", d.department || "—");
            setDetail("[data-arcav-promotion-detail-from]", d.designationFrom || "—");
            setDetail("[data-arcav-promotion-detail-to]", d.designationTo || "—");
            setDetail("[data-arcav-promotion-detail-date]", d.promotionDate || "—");
            setDetail("[data-arcav-promotion-detail-notes]", d.notes || "—");
            setDetail("[data-arcav-promotion-detail-created]", d.createdAt || "—");
            var prof = detailModalEl.querySelector("[data-arcav-promotion-detail-profile]");
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
        var view = e.target.closest && e.target.closest("[data-arcav-promotion-view]");
        if (!view) return;
        e.preventDefault();
        var pid = view.getAttribute("data-arcav-promotion-view");
        if (pid) openPromotionDetail(pid);
    });

    window.ArcavPromotionDetail = { open: openPromotionDetail };

    if (promotionsTbody) {
        apiRequest("get", "/v1/identity/auth/me", null).then(function (me) {
            if (!me || !me.success || !me.data || !me.data.hcmAdmin) {
                window.location.replace("/employee-dashboard");
                return;
            }
            loadPromotions();
        }).catch(function () {
            window.location.replace("/employee-dashboard");
        });
    }
})(window, document);

