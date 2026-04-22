(function (window, document) {
    "use strict";

    var state = {
        types: [],
        customByType: {},
        deleteTargetId: null,
        activeTypeCode: null,
        pending: null, // used only for toggle confirmation
    };
    var LEAVE_TYPE_CATALOG_ROUTE = "/leave-type";

    function onAuthFailure(status, data) {
        if (window.AuthApi && typeof window.AuthApi.handleUnauthorizedFromApi === "function") {
            return window.AuthApi.handleUnauthorizedFromApi(status, data);
        }
        return false;
    }

    function apiRequest(method, url, body) {
        var headers = { Accept: "application/json" };
        if (body && typeof body === "object" && !(body instanceof FormData)) {
            headers["Content-Type"] = "application/json";
        }
        if (window.axios) {
            return window.axios({ method: method, url: url, headers: headers, data: body, withCredentials: true })
                .then(function (res) {
                    return res.data;
                })
                .catch(function (err) {
                    var st = err && err.response ? err.response.status : 0;
                    var d = err && err.response ? err.response.data : null;
                    if (onAuthFailure(st, d)) {
                        return null;
                    }
                    return Promise.reject({ status: st, data: d });
                });
        }
        var opts = { method: method, headers: headers, credentials: "same-origin" };
        if (body && method !== "GET") {
            opts.body = body instanceof FormData ? body : JSON.stringify(body);
        }
        return fetch(url, opts).then(function (res) {
            return res.json().catch(function () {
                return {};
            }).then(function (data) {
                if (!res.ok) {
                    if (onAuthFailure(res.status, data)) {
                        return null;
                    }
                    return Promise.reject({ status: res.status, data: data });
                }
                return data;
            });
        });
    }

    function esc(v) {
        return String(v || "")
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#39;");
    }

    function notify(message, isError) {
        var c =
            document.querySelector("[data-hcm-toast-container]") ||
            document.body.appendChild(
                Object.assign(document.createElement("div"), { style: "position:fixed;top:16px;right:16px;z-index:3000" })
            );
        c.setAttribute("data-hcm-toast-container", "1");
        var t = document.createElement("div");
        t.className = "alert " + (isError ? "alert-danger" : "alert-success") + " shadow-sm mb-2";
        t.textContent = message;
        c.appendChild(t);
        window.setTimeout(function () {
            t.remove();
        }, 2600);
    }

    function formatApiError(data, status) {
        if (window.ApiErrorHelper && typeof window.ApiErrorHelper.format === "function") {
            return window.ApiErrorHelper.format(data, status);
        }
        if (data && data.error && Array.isArray(data.error.details) && data.error.details.length) {
            return data.error.details.map(function (d) { return d.message; }).join(" ");
        }
        if (data && data.errors && typeof data.errors === "object") {
            var parts = [];
            var keys = Object.keys(data.errors);
            for (var i = 0; i < keys.length; i++) {
                var msgs = data.errors[keys[i]];
                if (Array.isArray(msgs) && msgs.length) {
                    parts.push(msgs[0]);
                }
            }
            if (parts.length) {
                return parts.join(" ");
            }
        }
        if (data && data.error && data.error.message) {
            return data.error.message;
        }
        if (data && data.message) {
            return data.message;
        }
        return status ? "Error " + status : "Request failed";
    }

    function getType(code) {
        for (var i = 0; i < state.types.length; i++) {
            if (state.types[i].code === code) {
                return state.types[i];
            }
        }
        return null;
    }

    function fillLeaveTypeSelect(selectEl, selectedCode) {
        if (!selectEl) {
            return;
        }
        var opts = '<option value="">— Pilih tipe —</option>';
        for (var i = 0; i < state.types.length; i++) {
            var t = state.types[i];
            var sel = t.code === selectedCode ? " selected" : "";
            opts += '<option value="' + esc(t.code) + '"' + sel + ">" + esc(t.name) + "</option>";
        }
        var createSel = selectedCode === "__new__" ? " selected" : "";
        opts += '<option value="__new__"' + createSel + '>+ Buat leave type baru</option>';
        selectEl.innerHTML = opts;
    }

    function syncLeaveTypeInputMode() {
        var typeSelect = document.querySelector('[data-hcm-ls-field="leaveTypeCode"]');
        var newTypeInput = document.querySelector('[data-hcm-ls-field="newLeaveTypeName"]');
        if (!typeSelect || !newTypeInput) {
            return;
        }
        var isNew = typeSelect.value === "__new__";
        if (isNew) {
            typeSelect.value = "";
            newTypeInput.classList.add("d-none");
            newTypeInput.required = false;
            newTypeInput.value = "";
            window.location.assign(LEAVE_TYPE_CATALOG_ROUTE);
            return;
        }
        newTypeInput.classList.toggle("d-none", !isNew);
        newTypeInput.required = isNew;
        if (!isNew) {
            newTypeInput.value = "";
        }
    }

    function loadAssigneeSelect(multiEl) {
        if (!multiEl) {
            return Promise.resolve();
        }
        function fetchEmpPage(page, acc) {
            var url = "/v1/hcm/employees?perPage=100&page=" + encodeURIComponent(page);
            return apiRequest("get", url, null).then(function (payload) {
                if (!payload || payload.success !== true) {
                    return acc;
                }
                var chunk = Array.isArray(payload.data) ? payload.data : [];
                var next = acc.concat(chunk);
                var meta = payload.meta || {};
                var total = typeof meta.total === "number" ? meta.total : next.length;
                if (chunk.length < 1 || next.length >= total || page >= 50) {
                    return next;
                }
                return fetchEmpPage(page + 1, next);
            });
        }
        return fetchEmpPage(1, [])
            .then(function (rows) {
                var opts = "";
                for (var i = 0; i < rows.length; i++) {
                    var r = rows[i];
                    opts += '<option value="' + esc(String(r.id)) + '">' + esc(r.fullName + " (" + r.email + ")") + "</option>";
                }
                multiEl.innerHTML = opts;
            })
            .catch(function (err) {
                notify(formatApiError(err && err.data, err && err.status), true);
            });
    }

    function renderTypeCards() {
        var grid = document.querySelector("[data-hcm-leave-types-grid]");
        if (!grid) {
            return;
        }
        if (!state.types.length) {
            grid.innerHTML = '<div class="col-12"><p class="text-muted">No leave types.</p></div>';
            return;
        }
        grid.innerHTML = state.types
            .map(function (t) {
                var on = t.isEnabled ? " checked" : "";
                return (
                    '<div class="col-xl-4 col-md-6">' +
                    '<div class="card">' +
                    '<div class="card-body d-flex align-items-center justify-content-between">' +
                    '<div class="d-flex align-items-center">' +
                    '<div class="form-check form-check-md form-switch me-1">' +
                    '<label class="form-check-label">' +
                    '<input class="form-check-input" type="checkbox" role="switch"' +
                    on +
                    ' data-hcm-ls-toggle="' +
                    esc(t.code) +
                    '">' +
                    "</label>" +
                    "</div>" +
                    '<h6 class="d-flex align-items-center mb-0">' +
                    esc(t.name) +
                    "</h6>" +
                    "</div>" +
                    '<div class="d-flex align-items-center">' +
                    '<a href="javascript:void(0);" class="text-decoration-underline me-2" data-hcm-ls-open-custom="' +
                    esc(t.code) +
                    '">View policies</a>' +
                    '<a href="javascript:void(0);" class="me-2" title="Add custom policy" data-hcm-ls-open-add="' +
                    esc(t.code) +
                    '"><i class="ti ti-circle-plus"></i></a>' +
                    '<a href="javascript:void(0);" class="me-2" title="View detail" data-hcm-ls-open-detail="' +
                    esc(t.code) +
                    '"><i class="ti ti-eye"></i></a>' +
                    '<a href="javascript:void(0);" title="Edit settings" data-hcm-ls-open-settings="' +
                    esc(t.code) +
                    '"><i class="ti ti-settings"></i></a>' +
                    "</div>" +
                    "</div></div></div>"
                );
            })
            .join("");
    }

    function setTypeModalMode(simple) {
        var full = document.querySelector("[data-hcm-ls-full-fields]");
        var simpleEl = document.querySelector("[data-hcm-ls-simple-fields]");
        if (full) {
            full.classList.toggle("d-none", !!simple);
        }
        if (simpleEl) {
            simpleEl.classList.toggle("d-none", !simple);
        }
    }

    function openTypeModal(code, initialTab) {
        var t = getType(code);
        if (!t) {
            return;
        }
        state.activeTypeCode = code;
        var modalEl = document.getElementById("arcav_leave_type_settings");
        var title = document.querySelector("[data-hcm-ls-type-modal-title]");
        if (title) {
            title.textContent = t.name + " settings";
        }
        var form = document.querySelector("[data-hcm-ls-type-form]");
        if (!form) {
            return;
        }
        form.querySelector('[data-hcm-ls-type-field="code"]').value = t.code;
        var simple = t.settingsMode === "simple";
        setTypeModalMode(simple);
        if (simple) {
            var ds = form.querySelector('[data-hcm-ls-type-field="daysSimple"]');
            if (ds) {
                ds.value = t.days != null ? String(t.days) : "0";
            }
        } else {
            var d = form.querySelector('[data-hcm-ls-type-field="days"]');
            if (d) {
                d.value = t.days != null ? String(t.days) : "";
            }
            var mc = form.querySelector('[data-hcm-ls-type-field="maxCarryDays"]');
            if (mc) {
                mc.value = t.maxCarryDays != null ? String(t.maxCarryDays) : "";
            }
            form.querySelector("#arcav_ls_carry_" + (t.carryForward ? "y" : "n")).checked = true;
            form.querySelector("#arcav_ls_earned_" + (t.earnedLeave ? "y" : "n")).checked = true;
        }
        renderCustomListForType(code);
        showBsModal(modalEl);
        if (initialTab === "custom") {
            var tabBtn = document.querySelector('[data-bs-target="#arcav-ls-pane-custom"]');
            if (tabBtn && window.bootstrap && window.bootstrap.Tab) {
                window.bootstrap.Tab.getOrCreateInstance(tabBtn).show();
            }
        } else {
            var setBtn = document.querySelector('[data-bs-target="#arcav-ls-pane-settings"]');
            if (setBtn && window.bootstrap && window.bootstrap.Tab) {
                window.bootstrap.Tab.getOrCreateInstance(setBtn).show();
            }
        }
    }

    function renderCustomListForType(code) {
        var list = document.querySelector("[data-hcm-ls-custom-list]");
        var empty = document.querySelector("[data-hcm-ls-custom-empty]");
        if (!list) {
            return;
        }
        var rows = state.customByType[code] || [];
        if (empty) {
            empty.classList.toggle("d-none", rows.length > 0);
        }
        if (!rows.length) {
            list.innerHTML = "";
            return;
        }
        list.innerHTML = rows
            .map(function (p) {
                var nAssign = Array.isArray(p.assigneeUserIds) ? p.assigneeUserIds.length : 0;
                var assignLabel = nAssign ? nAssign + " karyawan" : "Semua / belum ditetapkan";
                return (
                    '<div class="col-md-12">' +
                    '<div class="card border mb-2">' +
                    '<div class="card-body py-2 d-flex align-items-center justify-content-between flex-wrap gap-2">' +
                    "<div>" +
                    '<p class="mb-0 fw-medium">' +
                    esc(p.name) +
                    "</p>" +
                    '<span class="text-muted fs-12">' +
                    esc(String(p.days)) +
                    " hari · " +
                    esc(assignLabel) +
                    "</span>" +
                    "</div>" +
                    '<div class="action-icon d-inline-flex">' +
                    '<a href="#" class="me-2" data-hcm-ls-edit-custom="' +
                    esc(String(p.id)) +
                    '"><i class="ti ti-edit"></i></a>' +
                    '<a href="#" data-hcm-ls-trash-custom="' +
                    esc(String(p.id)) +
                    '"><i class="ti ti-trash"></i></a>' +
                    "</div>" +
                    "</div></div></div>"
                );
            })
            .join("");
    }

    function reload() {
        return apiRequest("get", "/v1/hcm/leave-settings", null)
            .then(function (p) {
                if (!p) {
                    notify("Silakan login.", true);
                    return;
                }
                if (p.success !== true || !p.data) {
                    notify("Gagal memuat pengaturan cuti.", true);
                    return;
                }
                state.types = p.data.types || [];
                state.customByType = p.data.customPoliciesByType || {};
                renderTypeCards();
                fillLeaveTypeSelect(document.querySelector('[data-hcm-ls-field="leaveTypeCode"]'), "");
            })
            .catch(function (e) {
                notify(formatApiError(e.data, e.status), true);
            });
    }

    function showBsModal(el) {
        if (!el || !window.bootstrap || !window.bootstrap.Modal) {
            return;
        }
        var mi = window.bootstrap.Modal.getInstance(el);
        if (!mi) {
            mi = new window.bootstrap.Modal(el);
        }
        mi.show();
    }

    function hideModal(id) {
        var el = document.getElementById(id);
        if (el && window.bootstrap && window.bootstrap.Modal) {
            var mi = window.bootstrap.Modal.getInstance(el);
            if (mi) {
                mi.hide();
            }
        }
    }

    function openTypeDetailModal(code) {
        var t = getType(code);
        if (!t) {
            notify("Detail leave setting tidak ditemukan.", true);
            return;
        }
        var setText = function (sel, val) {
            var el = document.querySelector(sel);
            if (el) {
                el.textContent = val;
            }
        };
        setText("[data-hcm-ls-detail-title]", (t.name || "Leave") + " detail");
        setText("[data-hcm-ls-detail-code]", t.code || "—");
        setText("[data-hcm-ls-detail-days]", t.days != null ? String(t.days) : "—");
        setText("[data-hcm-ls-detail-carry]", t.carryForward ? "Yes" : "No");
        setText("[data-hcm-ls-detail-max-carry]", t.maxCarryDays != null ? String(t.maxCarryDays) : "—");
        setText("[data-hcm-ls-detail-earned]", t.earnedLeave ? "Yes" : "No");
        var wrap = document.querySelector("[data-hcm-ls-detail-custom-list]");
        if (wrap) {
            var rows = state.customByType[code] || [];
            if (!rows.length) {
                wrap.innerHTML = '<p class="mb-0 text-muted small">No custom policy.</p>';
            } else {
                wrap.innerHTML = rows.map(function (p) {
                    var nAssign = Array.isArray(p.assigneeUserIds) ? p.assigneeUserIds.length : 0;
                    return '<div class="border rounded p-2 mb-2"><div class="fw-medium">' +
                        esc(p.name) + '</div><div class="text-muted small">' +
                        esc(String(p.days)) + ' hari · ' + esc(nAssign ? (nAssign + ' karyawan') : 'Semua / belum ditetapkan') +
                        '</div></div>';
                }).join("");
            }
        }
        showBsModal(document.getElementById("arcav_leave_type_detail"));
    }

    function showConfirmModal(title, body, proceedLabel) {
        var t = document.querySelector("[data-hcm-ls-confirm-title]");
        var b = document.querySelector("[data-hcm-ls-confirm-body]");
        var btn = document.querySelector("[data-hcm-ls-confirm-proceed]");
        if (t) {
            t.textContent = title;
        }
        if (b) {
            b.textContent = body;
        }
        if (btn) {
            btn.textContent = proceedLabel || "Ya, simpan";
        }
        var errEl = document.querySelector("[data-hcm-ls-confirm-error]");
        if (!errEl) {
            var bodyEl = document.querySelector("#arcav_ls_confirm_save .modal-body");
            if (bodyEl) {
                errEl = document.createElement("p");
                errEl.setAttribute("data-hcm-ls-confirm-error", "1");
                errEl.className = "text-danger small mt-2 mb-0 d-none";
                bodyEl.appendChild(errEl);
            }
        }
        if (errEl) {
            errEl.textContent = "";
            errEl.classList.add("d-none");
        }
        var confirmEl = document.getElementById("arcav_ls_confirm_save");
        if (!confirmEl) {
            notify("Modal konfirmasi tidak ditemukan. Coba refresh halaman.", true);
            return;
        }
        // Keep confirmation dialog above the currently opened settings modal.
        confirmEl.style.zIndex = "2000";
        showBsModal(confirmEl);
        window.setTimeout(function () {
            var backdrops = document.querySelectorAll(".modal-backdrop");
            if (backdrops && backdrops.length) {
                backdrops[backdrops.length - 1].style.zIndex = "1990";
            }
        }, 0);
    }

    function clearFieldErrors(form) {
        if (!form) {
            return;
        }
        var invalid = form.querySelectorAll(".is-invalid");
        for (var i = 0; i < invalid.length; i++) {
            invalid[i].classList.remove("is-invalid");
        }
        var msgs = form.querySelectorAll("[data-hcm-ls-field-error]");
        for (var j = 0; j < msgs.length; j++) {
            msgs[j].remove();
        }
    }

    function setFieldError(inputEl, message) {
        if (!inputEl || !message) {
            return;
        }
        inputEl.classList.add("is-invalid");
        var help = document.createElement("div");
        help.className = "invalid-feedback d-block";
        help.setAttribute("data-hcm-ls-field-error", "1");
        help.textContent = message;
        inputEl.insertAdjacentElement("afterend", help);
    }

    function applyValidationErrors(form, data, fallback) {
        if (!form) {
            notify(fallback || "Validation error.", true);
            return;
        }
        clearFieldErrors(form);
        var errs = data && data.errors && typeof data.errors === "object" ? data.errors : null;
        if (!errs) {
            notify(formatApiError(data, 0) || fallback || "Validation error.", true);
            return;
        }
        var mapped = {
            leaveTypeCode: '[data-hcm-ls-field="leaveTypeCode"]',
            leaveTypeName: '[data-hcm-ls-field="newLeaveTypeName"]',
            name: '[data-hcm-ls-field="name"]',
            days: '[data-hcm-ls-field="days"]',
            isEnabled: '[data-hcm-ls-type-field="code"]',
            carryForward: "#arcav_ls_carry_y",
            maxCarryDays: '[data-hcm-ls-type-field="maxCarryDays"]',
            earnedLeave: "#arcav_ls_earned_y",
        };
        var keys = Object.keys(errs);
        var shownAny = false;
        for (var i = 0; i < keys.length; i++) {
            var key = keys[i];
            var msgs = errs[key];
            if (!Array.isArray(msgs) || !msgs.length) {
                continue;
            }
            var sel = mapped[key] || ('[name="' + key + '"]');
            var inputEl = form.querySelector(sel);
            if (inputEl) {
                setFieldError(inputEl, String(msgs[0]));
                shownAny = true;
            }
        }
        notify(formatApiError(data, 422) || fallback || "Validation error.", true);
        if (!shownAny && fallback) {
            notify(fallback, true);
        }
    }

    function buildTypeSaveBody(typeForm, t) {
        var body = {};
        if (t.settingsMode === "simple") {
            var ds = typeForm.querySelector('[data-hcm-ls-type-field="daysSimple"]');
            var dsv = ds && ds.value !== "" ? parseFloat(ds.value, 10) : 0;
            body.days = isNaN(dsv) ? 0 : dsv;
        } else {
            var dIn = typeForm.querySelector('[data-hcm-ls-type-field="days"]');
            var dv = dIn && dIn.value !== "" ? parseFloat(dIn.value, 10) : null;
            body.days = dv !== null && !isNaN(dv) ? dv : null;
            body.carryForward = typeForm.querySelector("#arcav_ls_carry_y").checked;
            var mc = typeForm.querySelector('[data-hcm-ls-type-field="maxCarryDays"]');
            body.maxCarryDays = mc && mc.value !== "" ? parseInt(mc.value, 10) : null;
            body.earnedLeave = typeForm.querySelector("#arcav_ls_earned_y").checked;
        }
        return body;
    }

    function runPendingToggle(p) {
        return apiRequest("put", "/v1/hcm/leave-settings/types/" + encodeURIComponent(p.code), { isEnabled: p.wantEnabled })
            .then(function (res) {
                if (!res || res.success !== true) {
                    var msg = formatApiError(res, 0) || "Gagal memperbarui.";
                    notify(msg, true);
                    return false;
                }
                notify("Perubahan disimpan.", false);
                reload();
                return true;
            })
            .catch(function (err) {
                notify(formatApiError(err.data, err.status), true);
                return false;
            });
    }

    function runPendingTypeSave(p) {
        var typeForm = document.querySelector("[data-hcm-ls-type-form]");
        return apiRequest("put", "/v1/hcm/leave-settings/types/" + encodeURIComponent(p.code), p.body)
            .then(function (res) {
                if (!res || res.success !== true) {
                    var msg = formatApiError(res, 0) || "Gagal menyimpan.";
                    notify(msg, true);
                    return false;
                }
                clearFieldErrors(typeForm);
                notify("Perubahan disimpan.", false);
                reload().then(function () {
                    openTypeModal(p.code, "settings");
                });
                return true;
            })
            .catch(function (err) {
                if (err && err.status === 422) {
                    applyValidationErrors(typeForm, err.data, "Periksa kembali field pengaturan cuti.");
                } else {
                    notify(formatApiError(err.data, err.status), true);
                }
                return false;
            });
    }

    function runPendingCustomSave(p) {
        var customForm = document.querySelector("[data-hcm-ls-custom-form]");
        return apiRequest(p.method, p.url, p.payload)
            .then(function (res) {
                if (!res || res.success !== true) {
                    var msg = formatApiError(res, 0) || "Gagal menyimpan.";
                    notify(msg, true);
                    return false;
                }
                clearFieldErrors(customForm);
                notify("Perubahan disimpan.", false);
                hideModal("new_custom_policy");
                var typeSelect = customForm && customForm.querySelector('[data-hcm-ls-field="leaveTypeCode"]');
                var typeNameInput = customForm && customForm.querySelector('[data-hcm-ls-field="leaveTypeName"]');
                if (typeSelect) {
                    typeSelect.disabled = false;
                    typeSelect.classList.remove("d-none");
                }
                if (typeNameInput) {
                    typeNameInput.classList.add("d-none");
                }
                var newTypeInput = customForm && customForm.querySelector('[data-hcm-ls-field="newLeaveTypeName"]');
                if (newTypeInput) {
                    newTypeInput.classList.add("d-none");
                    newTypeInput.required = false;
                    newTypeInput.value = "";
                }
                reload().then(function () {
                    if (state.activeTypeCode) {
                        openTypeModal(state.activeTypeCode);
                    }
                });
                return true;
            })
            .catch(function (err) {
                if (err && err.status === 422) {
                    applyValidationErrors(customForm, err.data, "Periksa kembali field custom policy.");
                } else {
                    notify(formatApiError(err.data, err.status), true);
                }
                return false;
            });
    }

    function openCustomForm(editId) {
        var form = document.querySelector("[data-hcm-ls-custom-form]");
        var title = document.querySelector("[data-hcm-ls-custom-title]");
        var typeSelect = document.querySelector('[data-hcm-ls-field="leaveTypeCode"]');
        var typeNameInput = document.querySelector('[data-hcm-ls-field="leaveTypeName"]');
        if (!form) {
            return;
        }
        form.reset();
        form.querySelector('[data-hcm-ls-field="id"]').value = "";
        if (editId) {
            var found = null;
            var codes = Object.keys(state.customByType);
            for (var c = 0; c < codes.length; c++) {
                var arr = state.customByType[codes[c]] || [];
                for (var i = 0; i < arr.length; i++) {
                    if (String(arr[i].id) === String(editId)) {
                        found = arr[i];
                        break;
                    }
                }
                if (found) {
                    break;
                }
            }
            if (!found) {
                return;
            }
            state.activeTypeCode = found.leaveTypeCode;
            if (title) {
                title.textContent = "Edit Custom Policy";
            }
            form.querySelector('[data-hcm-ls-field="id"]').value = String(found.id);
            if (typeSelect) {
                fillLeaveTypeSelect(typeSelect, found.leaveTypeCode || "");
                typeSelect.disabled = true;
                typeSelect.classList.add("d-none");
            }
            var newTypeInput = form.querySelector('[data-hcm-ls-field="newLeaveTypeName"]');
            if (newTypeInput) {
                newTypeInput.classList.add("d-none");
                newTypeInput.required = false;
                newTypeInput.value = "";
            }
            if (typeNameInput) {
                typeNameInput.classList.remove("d-none");
                var tf = getType(found.leaveTypeCode);
                typeNameInput.value = tf ? tf.name : (found.leaveTypeCode || "");
            }
            form.querySelector('[data-hcm-ls-field="name"]').value = found.name || "";
            form.querySelector('[data-hcm-ls-field="days"]').value = String(found.days);
            var multi = form.querySelector('[data-hcm-ls-field="assigneeIds"]');
            if (multi) {
                var ids = found.assigneeUserIds || [];
                for (var j = 0; j < multi.options.length; j++) {
                    multi.options[j].selected = ids.indexOf(parseInt(multi.options[j].value, 10)) !== -1;
                }
            }
        } else {
            if (title) {
                title.textContent = "Add Custom Policy";
            }
            if (!state.activeTypeCode && state.types.length) {
                state.activeTypeCode = state.types[0].code;
            }
            if (typeSelect) {
                fillLeaveTypeSelect(typeSelect, state.activeTypeCode || "");
                typeSelect.disabled = false;
                typeSelect.classList.remove("d-none");
            }
            if (typeNameInput) {
                typeNameInput.value = "";
                typeNameInput.classList.add("d-none");
            }
            syncLeaveTypeInputMode();
        }
        showBsModal(document.getElementById("new_custom_policy"));
    }

    function init() {
        var path = String(window.location.pathname || "").replace(/\/+$/, "") || "/";
        if (path !== "/leave-settings") {
            return;
        }

        apiRequest("get", "/v1/identity/auth/me", null).then(function (m) {
            if (m && m.success && m.data && (!m.data.permissions || !m.data.permissions['leave.settings'])) {
                window.location.replace("/employee-dashboard");
                return;
            }
            startLeaveSettingsPage();
        });
    }

    function startLeaveSettingsPage() {
        reload().then(function () {
            loadAssigneeSelect(document.querySelector('[data-hcm-ls-field="assigneeIds"]'));
        });

        document.addEventListener("change", function (e) {
            var typeSel = e.target.closest('[data-hcm-ls-field="leaveTypeCode"]');
            if (typeSel) {
                syncLeaveTypeInputMode();
                return;
            }
            var sw = e.target.closest("[data-hcm-ls-toggle]");
            if (!sw) {
                return;
            }
            var code = sw.getAttribute("data-hcm-ls-toggle");
            var wantEnabled = sw.checked;
            sw.checked = !wantEnabled;
            var t = getType(code);
            var label = t ? t.name : code;
            state.pending = { kind: "toggle", code: code, wantEnabled: wantEnabled };
            showConfirmModal(
                "Konfirmasi status cuti",
                'Anda akan ' +
                    (wantEnabled ? "mengaktifkan" : "menonaktifkan") +
                    ' jenis cuti "' +
                    label +
                    '". Perubahan ini memengaruhi opsi cuti di seluruh sistem. Lanjutkan?',
                "Ya, terapkan"
            );
        });

        document.addEventListener("click", function (e) {
            var headAdd = e.target.closest("[data-hcm-ls-head-add]");
            if (headAdd) {
                e.preventDefault();
                if (!state.activeTypeCode && state.types.length) {
                    state.activeTypeCode = state.types[0].code;
                }
                loadAssigneeSelect(document.querySelector('[data-hcm-ls-field="assigneeIds"]')).then(function () {
                    openCustomForm(null);
                });
                return;
            }
            var openS = e.target.closest("[data-hcm-ls-open-settings]");
            if (openS) {
                e.preventDefault();
                openTypeModal(openS.getAttribute("data-hcm-ls-open-settings"), "settings");
                return;
            }
            var openC = e.target.closest("[data-hcm-ls-open-custom]");
            if (openC) {
                e.preventDefault();
                openTypeModal(openC.getAttribute("data-hcm-ls-open-custom"), "custom");
                return;
            }
            var openD = e.target.closest("[data-hcm-ls-open-detail]");
            if (openD) {
                e.preventDefault();
                openTypeDetailModal(openD.getAttribute("data-hcm-ls-open-detail"));
                return;
            }
            var openA = e.target.closest("[data-hcm-ls-open-add]");
            if (openA) {
                e.preventDefault();
                state.activeTypeCode = openA.getAttribute("data-hcm-ls-open-add");
                loadAssigneeSelect(document.querySelector('[data-hcm-ls-field="assigneeIds"]')).then(function () {
                    openCustomForm(null);
                });
                return;
            }
            var addFromType = e.target.closest("[data-hcm-ls-add-custom-from-type]");
            if (addFromType) {
                e.preventDefault();
                hideModal("arcav_leave_type_settings");
                openCustomForm(null);
                return;
            }
            var ed = e.target.closest("[data-hcm-ls-edit-custom]");
            if (ed) {
                e.preventDefault();
                openCustomForm(ed.getAttribute("data-hcm-ls-edit-custom"));
                return;
            }
            var tr = e.target.closest("[data-hcm-ls-trash-custom]");
            if (tr) {
                e.preventDefault();
                state.deleteTargetId = tr.getAttribute("data-hcm-ls-trash-custom");
                showBsModal(document.getElementById("arcav_delete_leave_custom"));
            }
        });

        var typeForm = document.querySelector("[data-hcm-ls-type-form]");
        if (typeForm) {
            typeForm.addEventListener("submit", function (e) {
                e.preventDefault();
                clearFieldErrors(typeForm);
                if (typeof typeForm.reportValidity === "function" && !typeForm.reportValidity()) {
                    notify("Lengkapi field yang wajib sebelum menyimpan.", true);
                    return;
                }
                var code = typeForm.querySelector('[data-hcm-ls-type-field="code"]').value;
                var t = getType(code);
                if (!t) {
                    return;
                }
                var body = buildTypeSaveBody(typeForm, t);
                var saveBtn = typeForm.querySelector('button[type="submit"]');
                var oldText = saveBtn ? saveBtn.textContent : "";
                if (saveBtn) {
                    saveBtn.disabled = true;
                    saveBtn.textContent = "Menyimpan...";
                }
                runPendingTypeSave({ kind: "type", code: code, body: body }).finally(function () {
                    if (saveBtn) {
                        saveBtn.disabled = false;
                        saveBtn.textContent = oldText || "Save changes";
                    }
                });
            });
        }

        var customForm = document.querySelector("[data-hcm-ls-custom-form]");
        if (customForm) {
            customForm.addEventListener("submit", function (e) {
                e.preventDefault();
                clearFieldErrors(customForm);
                if (typeof customForm.reportValidity === "function" && !customForm.reportValidity()) {
                    notify("Lengkapi field yang wajib sebelum menyimpan.", true);
                    return;
                }
                var id = customForm.querySelector('[data-hcm-ls-field="id"]').value;
                var leaveTypeCode = customForm.querySelector('[data-hcm-ls-field="leaveTypeCode"]').value;
                var name = customForm.querySelector('[data-hcm-ls-field="name"]').value.trim();
                var days = parseFloat(customForm.querySelector('[data-hcm-ls-field="days"]').value, 10);
                if (leaveTypeCode === "__new__") {
                    window.location.assign(LEAVE_TYPE_CATALOG_ROUTE);
                    return;
                }
                if (!id && !leaveTypeCode) {
                    notify("Leave type wajib dipilih.", true);
                    return;
                }
                if (!name) {
                    notify("Policy name wajib diisi.", true);
                    return;
                }
                if (isNaN(days) || days <= 0) {
                    notify("No. of days wajib diisi dengan angka > 0.", true);
                    return;
                }
                var multi = customForm.querySelector('[data-hcm-ls-field="assigneeIds"]');
                var assigneeUserIds = [];
                if (multi) {
                    for (var i = 0; i < multi.selectedOptions.length; i++) {
                        assigneeUserIds.push(parseInt(multi.selectedOptions[i].value, 10));
                    }
                }
                var method = id ? "put" : "post";
                var url = id
                    ? "/v1/hcm/leave-settings/custom-policies/" + encodeURIComponent(id)
                    : "/v1/hcm/leave-settings/custom-policies";
                var payload = id
                    ? { name: name, days: days, assigneeUserIds: assigneeUserIds }
                    : {
                          leaveTypeCode: leaveTypeCode,
                          leaveTypeName: null,
                          name: name,
                          days: days,
                          assigneeUserIds: assigneeUserIds,
                      };
                var saveBtn = customForm.querySelector('button[type="submit"]');
                var oldText = saveBtn ? saveBtn.textContent : "";
                if (saveBtn) {
                    saveBtn.disabled = true;
                    saveBtn.textContent = "Menyimpan...";
                }
                runPendingCustomSave({ kind: "custom", method: method, url: url, payload: payload }).finally(function () {
                    if (saveBtn) {
                        saveBtn.disabled = false;
                        saveBtn.textContent = oldText || "Save";
                    }
                });
            });
        }

        var confirmProceed = document.querySelector("[data-hcm-ls-confirm-proceed]");
        if (confirmProceed) {
            confirmProceed.addEventListener("click", function () {
                var p = state.pending;
                state.pending = null;
                var oldText = confirmProceed.textContent;
                confirmProceed.disabled = true;
                confirmProceed.textContent = "Menyimpan...";
                if (!p) {
                    confirmProceed.disabled = false;
                    confirmProceed.textContent = oldText;
                    return;
                }
                var job = null;
                if (p.kind === "toggle") {
                    job = runPendingToggle(p);
                } else if (p.kind === "type") {
                    job = runPendingTypeSave(p);
                } else if (p.kind === "custom") {
                    job = runPendingCustomSave(p);
                }
                Promise.resolve(job).then(function (ok) {
                    if (ok) {
                        hideModal("arcav_ls_confirm_save");
                        return;
                    }
                    var errEl = document.querySelector("[data-hcm-ls-confirm-error]");
                    if (errEl) {
                        errEl.textContent = "Gagal menyimpan. Cek pesan error lalu coba lagi.";
                        errEl.classList.remove("d-none");
                    }
                }).finally(function () {
                    confirmProceed.disabled = false;
                    confirmProceed.textContent = oldText;
                });
            });
        }

        var confirmEl = document.getElementById("arcav_ls_confirm_save");
        if (confirmEl) {
            confirmEl.addEventListener("hidden.bs.modal", function () {
                if (state.pending) {
                    state.pending = null;
                }
            });
        }

        var delBtn = document.querySelector("[data-hcm-ls-delete-confirm]");
        if (delBtn) {
            delBtn.addEventListener("click", function () {
                var id = state.deleteTargetId;
                if (!id) {
                    return;
                }
                apiRequest("delete", "/v1/hcm/leave-settings/custom-policies/" + encodeURIComponent(id), null)
                    .then(function (p) {
                        if (!p || p.success !== true) {
                            notify("Gagal menghapus.", true);
                            return;
                        }
                        notify("Dihapus.", false);
                        hideModal("arcav_delete_leave_custom");
                        state.deleteTargetId = null;
                        reload().then(function () {
                            if (state.activeTypeCode) {
                                openTypeModal(state.activeTypeCode);
                            }
                        });
                    })
                    .catch(function (err) {
                        notify(formatApiError(err.data, err.status), true);
                    });
            });
        }
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", init);
    } else {
        init();
    }
})(window, document);
