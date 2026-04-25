(function (window, document) {
    "use strict";

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
        if (window.ApiClient && typeof window.ApiClient.toast === "function") {
            window.ApiClient.toast(message, isError);
            return;
        }
        var c = document.querySelector("[data-hcm-toast-container]") || document.body.appendChild(Object.assign(document.createElement("div"), { style: "position:fixed;top:16px;right:16px;z-index:3000" }));
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
        if (data && data.error && data.error.message) {
            return data.error.message;
        }
        return status ? "Error " + status : "Request failed";
    }

    function timeToInput(v) {
        if (!v || typeof v !== "string") {
            return "09:00";
        }
        var p = v.split(":");
        if (p.length < 2) {
            return "09:00";
        }
        return String(parseInt(p[0], 10)).padStart(2, "0") + ":" + String(parseInt(p[1], 10)).padStart(2, "0");
    }

    function bindShifts() {
        var body = document.querySelector("[data-hcm-shifts-body]");
        var canManageShift = false;
        var addShiftButton = document.querySelector('[data-bs-target="#arcav_add_shift"]');

        function resolveScheduleManagePermission(mePayload) {
            var user = mePayload && mePayload.data ? mePayload.data : mePayload;
            if (!user || typeof user !== "object") {
                return false;
            }

            var permissions = user.permissions;
            if (permissions && typeof permissions === "object" && !Array.isArray(permissions)) {
                return permissions["schedule.manage"] === true || permissions["schedule.admin"] === true;
            }

            var permissionCodes = [];
            if (Array.isArray(user.permissionCodes)) {
                permissionCodes = user.permissionCodes.slice();
            } else if (Array.isArray(user.permissions)) {
                permissionCodes = user.permissions.slice();
            }

            return permissionCodes.indexOf("schedule.manage") !== -1 || permissionCodes.indexOf("schedule.admin") !== -1;
        }

        function syncWriteUiVisibility() {
            if (addShiftButton) {
                addShiftButton.classList.toggle("d-none", !canManageShift);
            }
        }

        function bootstrapPermissions() {
            return apiRequest("get", "/v1/identity/auth/me", null)
                .then(function (payload) {
                    if (!payload || payload.success !== true) {
                        canManageShift = false;
                        return;
                    }
                    canManageShift = resolveScheduleManagePermission(payload);
                })
                .catch(function () {
                    canManageShift = false;
                })
                .finally(function () {
                    syncWriteUiVisibility();
                });
        }

        function render(rows) {
            if (!body) {
                return;
            }
            body.innerHTML =
                (rows || [])
                    .map(function (s) {
                        var badge = s.isActive ? "success" : "danger";
                        var st = s.isActive ? "Active" : "Inactive";
                        return (
                            "<tr><td><div class=\"form-check form-check-md\"><input class=\"form-check-input\" type=\"checkbox\"></div></td><td><code>" +
                            esc(s.code) +
                            "</code></td><td><h6 class=\"fw-medium mb-0\">" +
                            esc(s.name) +
                            "</h6></td><td>" +
                            esc(s.startTime) +
                            "</td><td>" +
                            esc(s.endTime) +
                            "</td><td><span class=\"badge badge-" +
                            badge +
                            ' d-inline-flex align-items-center badge-xs"><i class="ti ti-point-filled me-1"></i>' +
                            esc(st) +
                                                        "</span></td><td>" +
                                                        (canManageShift
                                                                ? "<div class=\"action-icon d-inline-flex\"><a href=\"#\" class=\"me-2\" data-hcm-shift-edit data-id=\"" +
                                                                    esc(s.id) +
                                                                    "\" data-code=\"" +
                                                                    esc(s.code) +
                                                                    "\" data-name=\"" +
                                                                    esc(s.name) +
                                                                    "\" data-start=\"" +
                                                                    esc(s.startTime) +
                                                                    "\" data-end=\"" +
                                                                    esc(s.endTime) +
                                                                    "\" data-desc=\"" +
                                                                    esc(s.description || "") +
                                                                    "\" data-active=\"" +
                                                                    (s.isActive ? "1" : "0") +
                                                                    "\" data-sort=\"" +
                                                                    esc(String(s.sortOrder != null ? s.sortOrder : 0)) +
                                                                    "\" data-bs-toggle=\"modal\" data-bs-target=\"#arcav_edit_shift\"><i class=\"ti ti-edit\"></i></a><a href=\"#\" data-hcm-shift-delete=\"" +
                                                                    esc(s.id) +
                                                                    "\"><i class=\"ti ti-trash\"></i></a></div>"
                                                                : '<span class="text-muted">-</span>') +
                                                        "</td></tr>"
                        );
                    })
                    .join("") || '<tr><td colspan="7" class="text-center py-4 text-muted">No shifts yet.</td></tr>';
        }

        function reload() {
            apiRequest("get", "/v1/hcm/shifts", null)
                .then(function (p) {
                    if (!p) {
                        notify("Please sign in.", true);
                        return;
                    }
                    if (p.success !== true) {
                        notify(formatApiError(p, 0) || "Failed to load shifts.", true);
                        return;
                    }
                    render(p.data || []);
                })
                .catch(function (e) {
                    notify(formatApiError(e.data, e.status), true);
                });
        }

        var addForm = document.querySelector('[data-hcm-shift-form="add"]');
        if (addForm) {
            addForm.addEventListener("submit", function (e) {
                e.preventDefault();
                if (!canManageShift) {
                    notify("Kamu tidak punya izin untuk mengelola shift.", true);
                    return;
                }
                var name = addForm.querySelector('[data-hcm-field="name"]').value.trim();
                var code = addForm.querySelector('[data-hcm-field="code"]').value.trim();
                var startTime = addForm.querySelector('[data-hcm-field="startTime"]').value;
                var endTime = addForm.querySelector('[data-hcm-field="endTime"]').value;
                var desc = addForm.querySelector('[data-hcm-field="description"]').value.trim();
                var sortOrder = parseInt(addForm.querySelector('[data-hcm-field="sortOrder"]').value, 10);
                var isActive = addForm.querySelector('[data-hcm-field="isActive"]').checked;
                if (!name || !startTime || !endTime) {
                    notify("Lengkapi nama dan jam.", true);
                    return;
                }
                var payload = {
                    name: name,
                    startTime: timeToInput(startTime.length > 5 ? startTime.slice(0, 5) : startTime),
                    endTime: timeToInput(endTime.length > 5 ? endTime.slice(0, 5) : endTime),
                    description: desc || null,
                    isActive: isActive,
                    sortOrder: isNaN(sortOrder) ? 0 : sortOrder,
                };
                if (code) {
                    payload.code = code;
                }
                apiRequest("post", "/v1/hcm/shifts", payload)
                    .then(function (p) {
                        if (!p || p.success !== true) {
                            notify(formatApiError(p, 0) || "Save failed.", true);
                            return;
                        }
                        notify("Shift tersimpan.", false);
                        var el = document.getElementById("arcav_add_shift");
                        var mi = el && window.bootstrap && window.bootstrap.Modal.getInstance(el);
                        if (mi) {
                            mi.hide();
                        }
                        addForm.reset();
                        var ac = addForm.querySelector('[data-hcm-field="isActive"]');
                        if (ac) {
                            ac.checked = true;
                        }
                        reload();
                    })
                    .catch(function (err) {
                        notify(formatApiError(err.data, err.status), true);
                    });
            });
        }

        var editForm = document.querySelector('[data-hcm-shift-form="edit"]');
        if (editForm) {
            document.addEventListener("click", function (e) {
                var btn = e.target.closest("[data-hcm-shift-edit]");
                if (!btn) {
                    return;
                }
                if (!canManageShift) {
                    e.preventDefault();
                    notify("Kamu tidak punya izin untuk mengelola shift.", true);
                    return;
                }
                editForm.querySelector('[data-hcm-field="id"]').value = btn.getAttribute("data-id") || "";
                editForm.querySelector('[data-hcm-field="code"]').value = btn.getAttribute("data-code") || "";
                editForm.querySelector('[data-hcm-field="name"]').value = btn.getAttribute("data-name") || "";
                editForm.querySelector('[data-hcm-field="startTime"]').value = timeToInput(btn.getAttribute("data-start") || "");
                editForm.querySelector('[data-hcm-field="endTime"]').value = timeToInput(btn.getAttribute("data-end") || "");
                editForm.querySelector('[data-hcm-field="description"]').value = btn.getAttribute("data-desc") || "";
                editForm.querySelector('[data-hcm-field="sortOrder"]').value = btn.getAttribute("data-sort") || "0";
                var chk = editForm.querySelector('[data-hcm-field="isActive"]');
                if (chk) {
                    chk.checked = btn.getAttribute("data-active") === "1";
                }
            });
            editForm.addEventListener("submit", function (e) {
                e.preventDefault();
                if (!canManageShift) {
                    notify("Kamu tidak punya izin untuk mengelola shift.", true);
                    return;
                }
                var id = editForm.querySelector('[data-hcm-field="id"]').value;
                if (!id) {
                    return;
                }
                var payload = {
                    code: editForm.querySelector('[data-hcm-field="code"]').value.trim(),
                    name: editForm.querySelector('[data-hcm-field="name"]').value.trim(),
                    startTime: timeToInput(editForm.querySelector('[data-hcm-field="startTime"]').value),
                    endTime: timeToInput(editForm.querySelector('[data-hcm-field="endTime"]').value),
                    description: editForm.querySelector('[data-hcm-field="description"]').value.trim() || null,
                    isActive: editForm.querySelector('[data-hcm-field="isActive"]').checked,
                    sortOrder: parseInt(editForm.querySelector('[data-hcm-field="sortOrder"]').value, 10) || 0,
                };
                apiRequest("put", "/v1/hcm/shifts/" + encodeURIComponent(id), payload)
                    .then(function (p) {
                        if (!p || p.success !== true) {
                            notify(formatApiError(p, 0) || "Update failed.", true);
                            return;
                        }
                        notify("Shift diperbarui.", false);
                        var el = document.getElementById("arcav_edit_shift");
                        var mi = el && window.bootstrap && window.bootstrap.Modal.getInstance(el);
                        if (mi) {
                            mi.hide();
                        }
                        reload();
                    })
                    .catch(function (err) {
                        notify(formatApiError(err.data, err.status), true);
                    });
            });
            document.addEventListener("click", function (e) {
                var del = e.target.closest("[data-hcm-shift-delete]");
                if (!del) {
                    return;
                }
                if (!canManageShift) {
                    e.preventDefault();
                    notify("Kamu tidak punya izin untuk mengelola shift.", true);
                    return;
                }
                e.preventDefault();
                var sid = del.getAttribute("data-hcm-shift-delete");
                var run =
                    window.ArcavUi && typeof window.ArcavUi.confirmDelete === "function"
                        ? window.ArcavUi.confirmDelete("Shift ini akan dihapus dari master data. Lanjutkan?", "Hapus shift")
                        : Promise.resolve(false);
                run.then(function (ok) {
                    if (!ok) {
                        return;
                    }
                    apiRequest("delete", "/v1/hcm/shifts/" + encodeURIComponent(sid), null)
                        .then(function (p) {
                            if (!p || p.success !== true) {
                                notify(formatApiError(p, 0) || "Delete failed.", true);
                                return;
                            }
                            notify("Shift dihapus.", false);
                            reload();
                        })
                        .catch(function (err) {
                            notify(formatApiError(err.data, err.status), true);
                        });
                });
            });
        }

        bootstrapPermissions().finally(function () {
            reload();
        });
    }

    function init() {
        if (document.querySelector("[data-hcm-shifts-body]")) {
            bindShifts();
        }
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", init);
    } else {
        init();
    }
})(window, document);
