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

    function formatApiError(data, status) {
        if (data && typeof data === "object" && data.error && data.error.message && String(data.error.message).trim() !== "") {
            return String(data.error.message);
        }
        if (window.ApiErrorHelper && typeof window.ApiErrorHelper.format === "function") {
            var out = window.ApiErrorHelper.format(data, status);
            if (out && out !== "Permintaan gagal.") {
                return out;
            }
        }
        if (status === 422) {
            return "Data belum lengkap atau tidak valid. Periksa isian lalu coba lagi.";
        }
        if (status === 403) {
            return "Anda tidak punya akses untuk aksi ini.";
        }
        if (status === 401) {
            return "Sesi habis. Silakan login ulang.";
        }
        return "Terjadi kesalahan. Coba lagi nanti.";
    }

    function toast(msg, danger) {
        if (window.ArcavUi && window.ArcavUi.showToast) {
            window.ArcavUi.showToast(msg, danger ? "danger" : "success");
        }
    }

    function formatIdr(n) {
        if (n == null || n === "") {
            return "—";
        }
        var x = Number(n);
        if (Number.isNaN(x)) {
            return String(n);
        }
        return new Intl.NumberFormat("id-ID", { style: "currency", currency: "IDR", maximumFractionDigits: 0 }).format(x);
    }

    function escapeHtml(s) {
        if (s == null) {
            return "";
        }
        return String(s)
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;");
    }

    /** Sinkron panel mass THR: muat batch + opsi auto-generate jika cut-off sudah di setup. */
    function notifyThrBatchPanel(calendarYear, settingsRow) {
        if (typeof window.CustomEvent === "undefined" || typeof window.dispatchEvent !== "function") {
            return;
        }
        var cy = typeof calendarYear === "number" && !Number.isNaN(calendarYear) ? calendarYear : null;
        window.dispatchEvent(
            new CustomEvent("arcavThrSettingsApplied", {
                detail: {
                    calendarYear: cy,
                    settings: settingsRow && typeof settingsRow === "object" ? settingsRow : null,
                },
            }),
        );
    }

    function ymdMinusOneDay(ymd) {
        if (!ymd || typeof ymd !== "string") {
            return "";
        }
        var p = ymd.split("-");
        if (p.length !== 3) {
            return "";
        }
        var d = new Date(Date.UTC(parseInt(p[0], 10), parseInt(p[1], 10) - 1, parseInt(p[2], 10)));
        d.setUTCDate(d.getUTCDate() - 1);
        var y = d.getUTCFullYear();
        var m = String(d.getUTCMonth() + 1).padStart(2, "0");
        var day = String(d.getUTCDate()).padStart(2, "0");
        return y + "-" + m + "-" + day;
    }

    function initCalculator() {
        var form = document.querySelector("[data-payroll-thr-form]");
        var out = document.querySelector("[data-payroll-thr-result]");
        var errEl = document.querySelector("[data-payroll-thr-error]");
        if (!form || !out) {
            return;
        }

        form.addEventListener("submit", function (e) {
            e.preventDefault();
            if (errEl) {
                errEl.textContent = "";
                errEl.classList.add("d-none");
            }
            out.innerHTML = '<p class="text-muted mb-0">Menghitung…</p>';

            var fd = new FormData(form);
            var base = parseFloat(String(fd.get("baseMonthlySalary") || "0").replace(/,/g, ".")) || 0;
            var fixed = parseFloat(String(fd.get("fixedMonthlyAllowance") || "0").replace(/,/g, ".")) || 0;
            var payload = {
                joinDate: String(fd.get("joinDate") || "").trim(),
                cutoffDate: String(fd.get("cutoffDate") || "").trim(),
                baseMonthlySalary: base,
                fixedMonthlyAllowance: fixed,
            };

            apiRequest("post", "/v1/hcm/payroll/thr-calculate", payload)
                .then(function (resp) {
                    if (!resp || resp.success !== true || !resp.data) {
                        out.innerHTML = '<span class="text-danger">Respons tidak valid.</span>';
                        return;
                    }
                    var d = resp.data;
                    var notes = (d.notes || []).map(function (n) {
                        return "<li>" + escapeHtml(n) + "</li>";
                    }).join("");
                    out.innerHTML =
                        "<dl class=\"row mb-0 small\">" +
                        "<dt class=\"col-sm-4\">Status</dt><dd class=\"col-sm-8\">" +
                        escapeHtml(d.status) +
                        " · eligible: " +
                        (d.eligible ? "ya" : "tidak") +
                        "</dd>" +
                        "<dt class=\"col-sm-4\">Bulan masa kerja (M)</dt><dd class=\"col-sm-8\">" +
                        escapeHtml(String(d.monthsOfService)) +
                        "</dd>" +
                        "<dt class=\"col-sm-4\">Pengali</dt><dd class=\"col-sm-8\">" +
                        escapeHtml(String(d.multiplier)) +
                        "</dd>" +
                        "<dt class=\"col-sm-4\">Upah acuan / bulan</dt><dd class=\"col-sm-8\">" +
                        formatIdr(d.referenceMonthlyWage) +
                        "</dd>" +
                        "<dt class=\"col-sm-4\">THR bruto (estimasi)</dt><dd class=\"col-sm-8 fw-semibold\">" +
                        formatIdr(d.thrGross) +
                        "</dd>" +
                        "<dt class=\"col-sm-4\">Referensi</dt><dd class=\"col-sm-8\">" +
                        escapeHtml(d.regulationReference || "") +
                        "</dd>" +
                        "</dl>" +
                        (notes ? "<ul class=\"mt-2 mb-0 small text-muted\">" + notes + "</ul>" : "");
                })
                .catch(function (err) {
                    out.innerHTML = "";
                    var msg = formatApiError(err.data, err.status);
                    if (errEl) {
                        errEl.textContent = msg;
                        errEl.classList.remove("d-none");
                    } else {
                        out.innerHTML = '<span class="text-danger">' + escapeHtml(msg) + "</span>";
                    }
                });
        });
    }

    function initSettings(settingsByYear, cutoffInput) {
        var form = document.querySelector("[data-thr-settings-form]");
        var errEl = document.querySelector("[data-thr-settings-error]");
        var listEl = document.querySelector("[data-thr-settings-saved-list]");
        var yearInput = document.querySelector("[data-thr-settings-year]");
        var eidInput = document.querySelector("[data-thr-settings-eid]");
        var payInput = document.querySelector("[data-thr-settings-payment]");
        var cutoffSettings = document.querySelector("[data-thr-settings-cutoff]");
        var notesTa = document.querySelector("[data-thr-settings-notes]");
        var btnSuggest = document.querySelector("[data-thr-suggest-cutoff]");

        if (!form || !yearInput) {
            return;
        }

        var y = new Date().getFullYear();
        yearInput.value = String(y);

        function fillFormForYear(calendarYear) {
            var row = settingsByYear[calendarYear];
            if (!row) {
                eidInput.value = "";
                payInput.value = "";
                cutoffSettings.value = "";
                notesTa.value = "";
                return;
            }
            eidInput.value = row.eidDate || "";
            payInput.value = row.paymentDate || "";
            cutoffSettings.value = row.calculationCutoffDate || "";
            notesTa.value = row.notes || "";
            if (cutoffInput && row.calculationCutoffDate) {
                cutoffInput.value = row.calculationCutoffDate;
            }
        }

        function renderList() {
            if (!listEl) {
                return;
            }
            var keys = Object.keys(settingsByYear).map(Number).sort(function (a, b) {
                return b - a;
            });
            if (!keys.length) {
                listEl.innerHTML = "<span class=\"text-muted\">Belum ada tahun tersimpan.</span>";
                return;
            }
            listEl.innerHTML =
                "<strong class=\"d-block mb-1\">Tersimpan:</strong><ul class=\"mb-0 ps-3\">" +
                keys
                    .map(function (cy) {
                        var r = settingsByYear[cy];
                        return (
                            "<li><button type=\"button\" class=\"btn btn-link btn-sm p-0 align-baseline\" data-thr-load-year=\"" +
                            cy +
                            "\">" +
                            cy +
                            "</button> — H: " +
                            escapeHtml(r.eidDate || "—") +
                            (r.paymentDate ? ", bayar: " + escapeHtml(r.paymentDate) : "") +
                            "</li>"
                        );
                    })
                    .join("") +
                "</ul>";
            listEl.querySelectorAll("[data-thr-load-year]").forEach(function (btn) {
                btn.addEventListener("click", function () {
                    var cy = parseInt(btn.getAttribute("data-thr-load-year"), 10);
                    yearInput.value = String(cy);
                    fillFormForYear(cy);
                    notifyThrBatchPanel(cy, settingsByYear[cy] || null);
                });
            });
        }

        yearInput.addEventListener("change", function () {
            var cy = parseInt(yearInput.value, 10);
            fillFormForYear(cy);
            notifyThrBatchPanel(cy, settingsByYear[cy] || null);
        });

        if (btnSuggest && eidInput && cutoffSettings) {
            btnSuggest.addEventListener("click", function () {
                var h = eidInput.value;
                var prev = ymdMinusOneDay(h);
                if (prev) {
                    cutoffSettings.value = prev;
                    toast("Cut-off diisi H-1 dari tanggal H.", false);
                } else {
                    toast("Isi tanggal Lebaran (H) dulu.", true);
                }
            });
        }

        form.addEventListener("submit", function (e) {
            e.preventDefault();
            if (errEl) {
                errEl.textContent = "";
                errEl.classList.add("d-none");
            }
            var cy = parseInt(yearInput.value, 10);
            if (Number.isNaN(cy)) {
                toast("Tahun tidak valid.", true);
                return;
            }
            var payload = {
                eidDate: eidInput.value,
                paymentDate: payInput.value || null,
                calculationCutoffDate: cutoffSettings.value || null,
                notes: notesTa.value || null,
            };
            apiRequest("put", "/v1/hcm/payroll/thr-settings/" + cy, payload)
                .then(function (resp) {
                    if (!resp || resp.success !== true || !resp.data) {
                        toast("Gagal menyimpan.", true);
                        return;
                    }
                    var d = resp.data;
                    settingsByYear[d.calendarYear] = d;
                    renderList();
                    if (cutoffInput && d.calculationCutoffDate) {
                        cutoffInput.value = d.calculationCutoffDate;
                    }
                    toast("Pengaturan THR disimpan.", false);
                    notifyThrBatchPanel(d.calendarYear, settingsByYear[d.calendarYear] || null);
                })
                .catch(function (err) {
                    var msg = formatApiError(err.data, err.status);
                    if (errEl) {
                        errEl.textContent = msg;
                        errEl.classList.remove("d-none");
                    }
                    toast(msg, true);
                });
        });

        fillFormForYear(y);
        renderList();
        notifyThrBatchPanel(y, settingsByYear[y] || null);
    }

    function boot() {
        var path = (window.location.pathname || "").replace(/\/+$/, "") || "/";
        if (path !== "/payroll-thr") {
            return;
        }

        var cutoffInput = document.querySelector("[data-thr-calc-cutoff]");
        var settingsByYear = {};

        apiRequest("get", "/v1/hcm/payroll/thr-settings", null)
            .then(function (resp) {
                if (resp && resp.success && resp.data && resp.data.settings) {
                    resp.data.settings.forEach(function (row) {
                        settingsByYear[row.calendarYear] = row;
                    });
                }
                initSettings(settingsByYear, cutoffInput);
            })
            .catch(function () {
                initSettings(settingsByYear, cutoffInput);
            });

        initCalculator();
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", boot);
    } else {
        boot();
    }
})(window, document);
