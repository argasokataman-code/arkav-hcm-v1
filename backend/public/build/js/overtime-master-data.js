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
                    if (window.AuthApi && typeof window.AuthApi.handleForbiddenFromApi === "function" && window.AuthApi.handleForbiddenFromApi(st, d)) {
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
                    if (window.AuthApi && typeof window.AuthApi.handleForbiddenFromApi === "function" && window.AuthApi.handleForbiddenFromApi(res.status, data)) {
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

    function formatRupiah(value) {
        var n = isFinite(value) ? Number(value) : 0;
        return "Rp" + n.toLocaleString("id-ID", { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function computeOvertimeSimulation(baseSalary, fixedAllowance, minutes, dayType, weeklyWorkDays) {
        var monthlyWage = Math.max(0, baseSalary + fixedAllowance);
        var hourly = monthlyWage / 173;
        var hours = Math.max(0, minutes / 60);
        var totalMultiplierHours = 0;

        if (dayType === "workday") {
            var firstHour = Math.min(1, hours);
            var nextHours = Math.max(0, hours - 1);
            totalMultiplierHours += (firstHour * 1.5) + (nextHours * 2);
        } else if (weeklyWorkDays <= 5) {
            var h1 = Math.min(8, hours);
            var h2 = Math.min(1, Math.max(0, hours - 8));
            var h3 = Math.max(0, hours - 9);
            totalMultiplierHours += (h1 * 2) + (h2 * 3) + (h3 * 4);
        } else {
            var w1 = Math.min(7, hours);
            var w2 = Math.min(1, Math.max(0, hours - 7));
            var w3 = Math.max(0, hours - 8);
            totalMultiplierHours += (w1 * 2) + (w2 * 3) + (w3 * 4);
        }

        return {
            hourly: hourly,
            hours: hours,
            totalPay: hourly * totalMultiplierHours,
        };
    }

    function bindGuideCalculator() {
        var modal = document.getElementById("arcav_ot_calc_guide");
        if (!modal) {
            return;
        }

        var baseSalaryInput = modal.querySelector('[data-ot-guide-field="baseSalary"]');
        var fixedAllowanceInput = modal.querySelector('[data-ot-guide-field="fixedAllowance"]');
        var minutesInput = modal.querySelector('[data-ot-guide-field="minutes"]');
        var dayTypeInput = modal.querySelector('[data-ot-guide-field="dayType"]');
        var weeklyWorkDaysInput = modal.querySelector('[data-ot-guide-field="weeklyWorkDays"]');

        var hourlyWageEl = modal.querySelector('[data-ot-guide-result="hourlyWage"]');
        var hoursEl = modal.querySelector('[data-ot-guide-result="hours"]');
        var totalPayEl = modal.querySelector('[data-ot-guide-result="totalPay"]');

        if (!baseSalaryInput || !fixedAllowanceInput || !minutesInput || !dayTypeInput || !weeklyWorkDaysInput || !hourlyWageEl || !hoursEl || !totalPayEl) {
            return;
        }

        function readNumber(input) {
            var v = parseFloat(input.value);
            return isNaN(v) ? 0 : Math.max(0, v);
        }

        function updateResult() {
            var baseSalary = readNumber(baseSalaryInput);
            var fixedAllowance = readNumber(fixedAllowanceInput);
            var minutes = Math.max(1, parseInt(minutesInput.value, 10) || 0);
            var dayType = dayTypeInput.value === "holiday" ? "holiday" : "workday";
            var weeklyWorkDays = parseInt(weeklyWorkDaysInput.value, 10) === 6 ? 6 : 5;

            var calc = computeOvertimeSimulation(baseSalary, fixedAllowance, minutes, dayType, weeklyWorkDays);

            hourlyWageEl.textContent = formatRupiah(calc.hourly);
            hoursEl.textContent = calc.hours.toFixed(2).replace(".", ",") + " jam";
            totalPayEl.textContent = formatRupiah(calc.totalPay);
        }

        ["input", "change"].forEach(function (evt) {
            baseSalaryInput.addEventListener(evt, updateResult);
            fixedAllowanceInput.addEventListener(evt, updateResult);
            minutesInput.addEventListener(evt, updateResult);
            dayTypeInput.addEventListener(evt, updateResult);
            weeklyWorkDaysInput.addEventListener(evt, updateResult);
        });

        updateResult();
        modal.addEventListener("shown.bs.modal", updateResult);
    }

    function bindOvertimeTypes() {
        var body = document.querySelector("[data-hcm-ot-types-body]");

        function render(rows) {
            if (!body) {
                return;
            }
            body.innerHTML =
                (rows || [])
                    .map(function (t) {
                        var badge = t.isActive ? "success" : "danger";
                        var st = t.isActive ? "Active" : "Inactive";
                        var mult = t.paymentMultiplier != null ? String(t.paymentMultiplier) : "1.00";
                        return (
                            "<tr><td><div class=\"form-check form-check-md\"><input class=\"form-check-input\" type=\"checkbox\"></div></td><td><code>" +
                            esc(t.code) +
                            "</code></td><td><h6 class=\"fw-medium mb-0\">" +
                            esc(t.name) +
                            "</h6></td><td>" +
                            esc(mult + "×") +
                            "</td><td><span class=\"badge badge-" +
                            badge +
                            ' d-inline-flex align-items-center badge-xs"><i class="ti ti-point-filled me-1"></i>' +
                            esc(st) +
                            "</span></td><td><div class=\"action-icon d-inline-flex\"><a href=\"#\" class=\"me-2\" data-hcm-ot-type-edit data-id=\"" +
                            esc(t.id) +
                            "\" data-code=\"" +
                            esc(t.code) +
                            "\" data-name=\"" +
                            esc(t.name) +
                            "\" data-mult=\"" +
                            esc(mult) +
                            "\" data-desc=\"" +
                            esc(t.description || "") +
                            "\" data-active=\"" +
                            (t.isActive ? "1" : "0") +
                            "\" data-sort=\"" +
                            esc(String(t.sortOrder != null ? t.sortOrder : 0)) +
                            "\" data-bs-toggle=\"modal\" data-bs-target=\"#arcav_edit_ot_type\"><i class=\"ti ti-edit\"></i></a><a href=\"#\" data-hcm-ot-type-delete=\"" +
                            esc(t.id) +
                            "\"><i class=\"ti ti-trash\"></i></a></div></td></tr>"
                        );
                    })
                    .join("") || '<tr><td colspan="6" class="text-center py-4 text-muted">No overtime types yet.</td></tr>';
        }

        function reload() {
            apiRequest("get", "/v1/hcm/overtime-types", null)
                .then(function (p) {
                    if (!p) {
                        notify("Please sign in.", true);
                        return;
                    }
                    if (p.success !== true) {
                        notify(formatApiError(p, 0) || "Failed to load overtime types.", true);
                        return;
                    }
                    render(p.data || []);
                })
                .catch(function (e) {
                    notify(formatApiError(e.data, e.status), true);
                });
        }

        var addForm = document.querySelector('[data-hcm-ot-type-form="add"]');
        if (addForm) {
            addForm.addEventListener("submit", function (e) {
                e.preventDefault();
                var name = addForm.querySelector('[data-hcm-field="name"]').value.trim();
                var code = addForm.querySelector('[data-hcm-field="code"]').value.trim();
                var multRaw = addForm.querySelector('[data-hcm-field="paymentMultiplier"]').value;
                var mult = parseFloat(multRaw);
                var desc = addForm.querySelector('[data-hcm-field="description"]').value.trim();
                var sortOrder = parseInt(addForm.querySelector('[data-hcm-field="sortOrder"]').value, 10);
                var isActive = addForm.querySelector('[data-hcm-field="isActive"]').checked;
                if (!name || isNaN(mult) || mult < 0.01) {
                    notify("Lengkapi nama dan multiplier.", true);
                    return;
                }
                var payload = {
                    name: name,
                    paymentMultiplier: mult,
                    description: desc || null,
                    isActive: isActive,
                    sortOrder: isNaN(sortOrder) ? 0 : sortOrder,
                };
                if (code) {
                    payload.code = code;
                }
                apiRequest("post", "/v1/hcm/overtime-types", payload)
                    .then(function (p) {
                        if (!p || p.success !== true) {
                            notify(formatApiError(p, 0) || "Save failed.", true);
                            return;
                        }
                        notify("Tipe lembur tersimpan.", false);
                        var el = document.getElementById("arcav_add_ot_type");
                        var mi = el && window.bootstrap && window.bootstrap.Modal.getInstance(el);
                        if (mi) {
                            mi.hide();
                        }
                        addForm.reset();
                        var pm = addForm.querySelector('[data-hcm-field="paymentMultiplier"]');
                        if (pm) {
                            pm.value = "1.00";
                        }
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

        var editForm = document.querySelector('[data-hcm-ot-type-form="edit"]');
        if (editForm) {
            document.addEventListener("click", function (e) {
                var btn = e.target.closest("[data-hcm-ot-type-edit]");
                if (!btn) {
                    return;
                }
                editForm.querySelector('[data-hcm-field="id"]').value = btn.getAttribute("data-id") || "";
                editForm.querySelector('[data-hcm-field="code"]').value = btn.getAttribute("data-code") || "";
                editForm.querySelector('[data-hcm-field="name"]').value = btn.getAttribute("data-name") || "";
                editForm.querySelector('[data-hcm-field="paymentMultiplier"]').value = btn.getAttribute("data-mult") || "1.00";
                editForm.querySelector('[data-hcm-field="description"]').value = btn.getAttribute("data-desc") || "";
                editForm.querySelector('[data-hcm-field="sortOrder"]').value = btn.getAttribute("data-sort") || "0";
                var chk = editForm.querySelector('[data-hcm-field="isActive"]');
                if (chk) {
                    chk.checked = btn.getAttribute("data-active") === "1";
                }
            });
            editForm.addEventListener("submit", function (e) {
                e.preventDefault();
                var id = editForm.querySelector('[data-hcm-field="id"]').value;
                if (!id) {
                    return;
                }
                var mult = parseFloat(editForm.querySelector('[data-hcm-field="paymentMultiplier"]').value);
                if (isNaN(mult) || mult < 0.01) {
                    notify("Multiplier tidak valid.", true);
                    return;
                }
                var payload = {
                    code: editForm.querySelector('[data-hcm-field="code"]').value.trim(),
                    name: editForm.querySelector('[data-hcm-field="name"]').value.trim(),
                    paymentMultiplier: mult,
                    description: editForm.querySelector('[data-hcm-field="description"]').value.trim() || null,
                    isActive: editForm.querySelector('[data-hcm-field="isActive"]').checked,
                    sortOrder: parseInt(editForm.querySelector('[data-hcm-field="sortOrder"]').value, 10) || 0,
                };
                apiRequest("put", "/v1/hcm/overtime-types/" + encodeURIComponent(id), payload)
                    .then(function (p) {
                        if (!p || p.success !== true) {
                            notify(formatApiError(p, 0) || "Update failed.", true);
                            return;
                        }
                        notify("Tipe lembur diperbarui.", false);
                        var el = document.getElementById("arcav_edit_ot_type");
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
                var del = e.target.closest("[data-hcm-ot-type-delete]");
                if (!del) {
                    return;
                }
                e.preventDefault();
                var tid = del.getAttribute("data-hcm-ot-type-delete");
                var run =
                    window.ArcavUi && typeof window.ArcavUi.confirmDelete === "function"
                        ? window.ArcavUi.confirmDelete("Tipe lembur ini akan dihapus. Pengajuan lama tetap ada tanpa tipe. Lanjutkan?", "Hapus tipe")
                        : Promise.resolve(false);
                run.then(function (ok) {
                    if (!ok) {
                        return;
                    }
                    apiRequest("delete", "/v1/hcm/overtime-types/" + encodeURIComponent(tid), null)
                        .then(function (p) {
                            if (!p || p.success !== true) {
                                notify(formatApiError(p, 0) || "Delete failed.", true);
                                return;
                            }
                            notify("Tipe dihapus.", false);
                            reload();
                        })
                        .catch(function (err) {
                            notify(formatApiError(err.data, err.status), true);
                        });
                });
            });
        }

        reload();
    }

    function init() {
        if (document.querySelector("[data-hcm-ot-types-body]")) {
            bindOvertimeTypes();
        }
        bindGuideCalculator();
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", init);
    } else {
        init();
    }
})(window, document);
