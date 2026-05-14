var hcmExtrasModuleLoaders = window.ArcavHcmExtrasModuleLoaders || {};
var resolveBindHolidaysModule = hcmExtrasModuleLoaders.resolveBindHolidaysModule || function () { return null; };
var loadBindHolidaysModule = hcmExtrasModuleLoaders.loadBindHolidaysModule || function () { return Promise.resolve(null); };
var resolveBindOvertimeCalculatorModule = hcmExtrasModuleLoaders.resolveBindOvertimeCalculatorModule || function () { return null; };
var loadBindOvertimeCalculatorModule = hcmExtrasModuleLoaders.loadBindOvertimeCalculatorModule || function () { return Promise.resolve(null); };
var resolveBindOvertimeModule = hcmExtrasModuleLoaders.resolveBindOvertimeModule || function () { return null; };
var loadBindOvertimeModule = hcmExtrasModuleLoaders.loadBindOvertimeModule || function () { return Promise.resolve(null); };

(function (window, document) {
    "use strict";

    function onAuthFailure(status, data) {
        if (window.AuthApi && typeof window.AuthApi.handleUnauthorizedFromApi === "function") {
            return window.AuthApi.handleUnauthorizedFromApi(status, data);
        }
        return false;
    }

    function getTenantContext() {
        if (window.AuthApi && typeof window.AuthApi.getTenantContext === "function") {
            return window.AuthApi.getTenantContext() || {};
        }

        return {};
    }

    function withTenantHeaders(headers) {
        var h = headers || {};
        var tenant = getTenantContext();
        if (tenant.companyCode) {
            h["X-Company-Code"] = String(tenant.companyCode);
        }
        if (tenant.companyId !== undefined && tenant.companyId !== null && tenant.companyId !== "") {
            h["X-Company-Id"] = String(tenant.companyId);
        }
        if (tenant.companyUuid) {
            h["X-Company-UUID"] = String(tenant.companyUuid);
        }

        return h;
    }

    function apiRequest(method, url, body) {
        var headers = withTenantHeaders({ Accept: "application/json" });
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
        var c = document.querySelector("[data-hcm-toast-container]") || document.body.appendChild(Object.assign(document.createElement("div"), { style: "position:fixed;top:16px;right:16px;z-index:1080" }));
        c.setAttribute("data-hcm-toast-container", "1");
        var t = document.createElement("div");
        t.className = "alert " + (isError ? "alert-danger" : "alert-success") + " shadow-sm mb-2";
        t.textContent = message;
        c.appendChild(t);
        window.setTimeout(function () {
            t.remove();
        }, 2600);
    }

    function downloadCsv(filename, headers, rows) {
        var csv = [headers.join(",")].concat(
            (rows || []).map(function (r) {
                return r.map(function (v) {
                    var s = String(v == null ? "" : v);
                    if (/[",\n]/.test(s)) {
                        return '"' + s.replace(/"/g, '""') + '"';
                    }
                    return s;
                }).join(",");
            })
        ).join("\n");
        var blob = new Blob([csv], { type: "text/csv;charset=utf-8;" });
        var url = window.URL.createObjectURL(blob);
        var a = document.createElement("a");
        a.href = url;
        a.download = filename;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        window.URL.revokeObjectURL(url);
    }

    function downloadFileFromUrl(url, fallbackFilename) {
        return fetch(url, {
            method: "GET",
            credentials: "same-origin",
            headers: withTenantHeaders({ Accept: "text/csv,application/json" }),
        }).then(function (res) {
            if (!res.ok) {
                return res
                    .json()
                    .catch(function () {
                        return {};
                    })
                    .then(function (data) {
                        if (onAuthFailure(res.status, data)) {
                            return null;
                        }
                        return Promise.reject({ status: res.status, data: data });
                    });
            }
            return Promise.all([res.blob(), Promise.resolve(res.headers.get("content-disposition") || "")]).then(function (parts) {
                var blob = parts[0];
                var disposition = parts[1];
                var filename = fallbackFilename || "export.csv";
                var match = /filename="?([^";]+)"?/i.exec(disposition);
                if (match && match[1]) {
                    filename = match[1];
                }
                var objectUrl = window.URL.createObjectURL(blob);
                var a = document.createElement("a");
                a.href = objectUrl;
                a.download = filename;
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                window.URL.revokeObjectURL(objectUrl);
                return true;
            });
        });
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
        return status ? "Error " + status : "Request failed";
    }

    function normalizeOvertimeDayType(dayType) {
        var key = String(dayType || "workday").trim().toLowerCase();
        if (key === "holiday") {
            return "public_holiday";
        }
        if (key === "rest_day" || key === "weekly_rest") {
            return "weekly_rest_day";
        }
        if (key === "short_rest_day" || key === "weekly_rest_short") {
            return "weekly_rest_day_short";
        }
        if (key !== "workday" && key !== "public_holiday" && key !== "weekly_rest_day" && key !== "weekly_rest_day_short") {
            return "workday";
        }
        return key;
    }

    function overtimeDayTypeLabel(dayType) {
        var key = normalizeOvertimeDayType(dayType);
        if (key === "public_holiday") {
            return "Hari libur nasional/tanggal merah";
        }
        if (key === "weekly_rest_day") {
            return "Hari istirahat mingguan";
        }
        if (key === "weekly_rest_day_short") {
            return "Istirahat mingguan (hari kerja terpendek)";
        }
        return "Hari kerja";
    }

    function formatOvertimeComplianceError(data, status, fallbackMessage) {
        var code = data && data.error && data.error.code ? String(data.error.code) : "";
        if (code === "OT_DAILY_LIMIT_EXCEEDED") {
            return "Durasi lembur melewati batas legal 4 jam per hari. Kurangi menit lembur atau pisah ke tanggal lain.";
        }
        if (code === "OT_WEEKLY_LIMIT_EXCEEDED") {
            return "Total lembur melewati batas legal 18 jam per minggu. Tinjau ulang distribusi lembur minggu berjalan.";
        }
        return formatApiError(data, status) || fallbackMessage || "Request failed";
    }

    function overtimeStatusMeta(status) {
        var key = String(status || "pending").toLowerCase();
        if (key === "approved") {
            return { badge: "success", label: "Disetujui", note: "Siap diproses payroll" };
        }
        if (key === "declined") {
            return { badge: "danger", label: "Ditolak", note: "Perlu revisi/klarifikasi" };
        }
        return { badge: "warning", label: "Menunggu", note: "Menunggu review atasan/HR" };
    }

    function overtimePolicyTypeLabel(requestType) {
        var key = String(requestType || "employee_request").toLowerCase();
        if (key === "company_assignment") {
            return "Penugasan perusahaan";
        }
        if (key === "missed_log_correction") {
            return "Koreksi lupa catat";
        }
        return "Pengajuan karyawan";
    }

    function isPendingOlderThan24h(row) {
        if (!row || String(row.status || "").toLowerCase() !== "pending") {
            return false;
        }
        var dt = String(row.workDate || "").slice(0, 10);
        if (!dt) {
            return false;
        }
        var workDate = new Date(dt + "T00:00:00");
        if (Number.isNaN(workDate.getTime())) {
            return false;
        }
        return (Date.now() - workDate.getTime()) > (24 * 60 * 60 * 1000);
    }

    var employeeCompensationById = {};

    function loadEmployeeOptions(selectEl) {
        if (!selectEl) {
            return Promise.resolve();
        }
        // API validates perPage max 100 (`HcmEmployeeController::index`).
        function fetchEmployeePage(page, accumulated) {
            var url = "/v1/hcm/employees?perPage=100&page=" + encodeURIComponent(page);
            return apiRequest("get", url, null).then(function (payload) {
                if (!payload || payload.success !== true) {
                    return accumulated;
                }
                var chunk = Array.isArray(payload.data) ? payload.data : [];
                var next = accumulated.concat(chunk);
                var meta = payload.meta || {};
                var total = typeof meta.total === "number" ? meta.total : next.length;
                if (chunk.length < 1 || next.length >= total || page >= 50) {
                    return next;
                }
                return fetchEmployeePage(page + 1, next);
            });
        }

        return fetchEmployeePage(1, [])
            .then(function (rows) {
                var opts = '<option value="">— Pilih karyawan —</option>';
                employeeCompensationById = {};
                for (var i = 0; i < rows.length; i++) {
                    var r = rows[i];
                    employeeCompensationById[String(r.id)] = {
                        baseSalary: Number(r.baseSalary || 0),
                        fixedAllowance: Number(r.fixedAllowance || 0),
                    };
                    opts += '<option value="' + esc(r.id) + '">' + esc(r.fullName + " (" + r.email + ")") + "</option>";
                }
                selectEl.innerHTML = opts;
            })
            .catch(function (err) {
                notify(formatApiError(err && err.data, err && err.status), true);
            });
    }

    /** Options for leave request modals; `value` stored in API is display name (max 100). */
    function buildLeaveTypeOptionsHtml(types, selectedName) {
        var opts = '<option value="">— Pilih jenis cuti —</option>';
        var seen = {};
        var list = types || [];
        for (var i = 0; i < list.length; i++) {
            var t = list[i];
            var name = t && t.name ? String(t.name).trim() : "";
            if (!name) {
                continue;
            }
            seen[name] = true;
            var sel = selectedName && String(selectedName) === name ? " selected" : "";
            opts += '<option value="' + esc(name) + '"' + sel + ">" + esc(name) + "</option>";
        }
        if (selectedName && String(selectedName).trim() && !seen[String(selectedName).trim()]) {
            var legacy = String(selectedName).trim();
            opts += '<option value="' + esc(legacy) + '" selected>' + esc(legacy) + " (riwayat)</option>";
        }
        return opts;
    }

    function bindHolidays() {
        var moduleFn = resolveBindHolidaysModule();
        var moduleArgs = {
            apiRequest: apiRequest,
            esc: esc,
            notify: notify,
            downloadCsv: downloadCsv,
            downloadFileFromUrl: downloadFileFromUrl,
            formatApiError: formatApiError,
            formatOvertimeComplianceError: formatOvertimeComplianceError,
        };

        if (moduleFn) {
            return moduleFn(moduleArgs);
        }

        loadBindHolidaysModule().then(function (loadedFn) {
            if (typeof loadedFn === "function") {
                loadedFn(moduleArgs);
            }
        });
        return null;
    }

    function bindLeaves(scope, isAdmin) {
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

        function splitDeclinedLeaveNotes(rawNotes) {
            var notes = String(rawNotes || "");
            var marker = "\n\n[Admin rejection reason]\n";
            var idx = notes.lastIndexOf(marker);
            if (idx >= 0) {
                return {
                    employeeNotes: notes.slice(0, idx).trim(),
                    rejectionReason: notes.slice(idx + marker.length).trim(),
                };
            }

            var legacy = /^\s*\[Admin rejection reason\]\s*([\s\S]*)$/i.exec(notes);
            if (legacy && legacy[1]) {
                return {
                    employeeNotes: "",
                    rejectionReason: String(legacy[1] || "").trim(),
                };
            }

            return {
                employeeNotes: notes.trim(),
                rejectionReason: "",
            };
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
        var bodySel = isAdmin ? "[data-hcm-leaves-admin-body]" : "[data-hcm-leaves-me-body]";
        var body = document.querySelector(bodySel);
        var leaveTypesCache = [];
        var leaveRowsCache = [];
        var leaveTypeMetaByName = {};
        var leaveTypeLabelByCode = {};
        var holidayMetaRows = [];
        var holidayMapByDate = {};
        var leaveFlatpickrInstances = [];

        function dateOnly(v) {
            return String(v || "").slice(0, 10);
        }

        function isWeekendDate(dateStr) {
            if (!dateStr) {
                return false;
            }
            var d = new Date(dateStr + "T00:00:00");
            var day = d.getDay();
            return day === 0 || day === 6;
        }

        function isHolidayDate(dateStr) {
            var key = dateOnly(dateStr);
            return !!holidayMapByDate[key];
        }

        function holidayNameByDate(dateStr) {
            var key = dateOnly(dateStr);
            return holidayMapByDate[key] ? holidayMapByDate[key].name : "";
        }

        function buildHolidayMap(rows) {
            holidayMapByDate = {};
            (rows || []).forEach(function (row) {
                var key = dateOnly(row && row.date);
                if (!key || holidayMapByDate[key]) {
                    return;
                }
                holidayMapByDate[key] = {
                    name: row.name || "Holiday",
                    isJointLeave: !!row.isJointLeave,
                    deductFromLeave: !!row.deductFromLeave,
                };
            });
        }

        function renderHolidayPanel(meta) {
            var panel = document.querySelector("[data-hcm-leave-holiday-panel]");
            var listEl = document.querySelector("[data-hcm-leave-holiday-list]");
            holidayMetaRows = (meta && Array.isArray(meta.holidays)) ? meta.holidays.slice() : [];
            buildHolidayMap(holidayMetaRows);

            if (!panel) {
                return;
            }

            if (!holidayMetaRows.length) {
                panel.style.display = "none";
                return;
            }

            panel.style.display = "";
            if (listEl) {
                listEl.innerHTML = holidayMetaRows.slice(0, 10).map(function (h) {
                    var tone = h.isJointLeave ? "badge-soft-warning" : "badge-soft-secondary";
                    return '<span class="badge ' + tone + ' me-1">' +
                        esc(h.date) + ' &nbsp;' + esc(h.name) +
                        "</span>";
                }).join("");
            }

            leaveFlatpickrInstances.forEach(function (picker) {
                if (picker && typeof picker.redraw === "function") {
                    picker.redraw();
                }
            });
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

                var bind = function (eventName) {
                    el.addEventListener(eventName, applyFiltersAndReload);
                };

                if (el.tagName === "SELECT") {
                    bind("change");
                    return;
                }

                // Date input sometimes does not emit change immediately on some browsers.
                bind("input");
                bind("change");
                bind("blur");
            });

            if (resetBtn) {
                resetBtn.addEventListener("click", function () {
                    leaveFilters = { leaveType: "", status: "", dateFrom: "", dateTo: "" };
                    if (typeSelect) typeSelect.value = "";
                    if (statusSelect) statusSelect.value = "";
                    if (dateFromInput) dateFromInput.value = "";
                    if (dateToInput) dateToInput.value = "";
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
                opts += '<option value="' + esc(v) + '">' + esc(l) + '</option>';
            }

            leaveTypesCache.forEach(function (t) {
                if (!t || !t.name) {
                    return;
                }
                addOption(String(t.name), String(t.name));
            });

            // Include legacy/raw values from loaded rows so filter remains usable on mixed historical data.
            leaveRowsCache.forEach(function (r) {
                if (!r || !r.leaveType) {
                    return;
                }
                var raw = String(r.leaveType);
                addOption(raw, displayLeaveType(r));
            });

            select.innerHTML = opts;
            if (selectedValue) {
                select.value = selectedValue;
            }
        }

        function titleCaseWords(s) {
            return String(s || "")
                .split(" ")
                .filter(Boolean)
                .map(function (w) {
                    return w.charAt(0).toUpperCase() + w.slice(1).toLowerCase();
                })
                .join(" ");
        }

        function displayLeaveType(row) {
            var raw = String((row && row.leaveType) || "").trim();
            if (!raw) {
                return "-";
            }

            if (row && row.leaveTypeLabel) {
                return String(row.leaveTypeLabel);
            }

            var codeKey = raw.toLowerCase();
            if (leaveTypeLabelByCode[codeKey]) {
                return leaveTypeLabelByCode[codeKey];
            }

            var normalizedCode = raw.toLowerCase().replace(/\s+/g, "_");
            if (leaveTypeLabelByCode[normalizedCode]) {
                return leaveTypeLabelByCode[normalizedCode];
            }

            if (raw.indexOf("_") >= 0 || raw.indexOf("-") >= 0) {
                return titleCaseWords(raw.replace(/[_-]+/g, " "));
            }
            return raw;
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

        function countWorkingDays(fromDate, toDate) {
            if (!fromDate || !toDate) {
                return { days: 0, excluded: [] };
            }
            var start = new Date(fromDate + "T00:00:00");
            var end = new Date(toDate + "T00:00:00");
            if (Number.isNaN(start.getTime()) || Number.isNaN(end.getTime())) {
                return { days: 0, excluded: [] };
            }
            if (end < start) {
                var tmp = start;
                start = end;
                end = tmp;
            }

            var days = 0;
            var excluded = [];
            var cursor = new Date(start.getTime());
            while (cursor <= end) {
                var key = cursor.toISOString().slice(0, 10);
                if (isWeekendDate(key)) {
                    excluded.push(key + " (weekend)");
                } else if (isHolidayDate(key)) {
                    excluded.push(key + " (" + holidayNameByDate(key) + ")");
                } else {
                    days += 1;
                }
                cursor.setDate(cursor.getDate() + 1);
            }
            return { days: days, excluded: excluded };
        }

        function validateDateInput(inputEl) {
            if (!inputEl) {
                return true;
            }
            var v = String(inputEl.value || "");
            if (!v) {
                inputEl.setCustomValidity("");
                inputEl.classList.remove("is-invalid");
                return true;
            }
            if (isWeekendDate(v)) {
                inputEl.setCustomValidity("Tanggal weekend tidak bisa dipilih sebagai batas cuti.");
                inputEl.classList.add("is-invalid");
                return false;
            }
            if (isHolidayDate(v)) {
                inputEl.setCustomValidity("Tanggal ini hari libur: " + holidayNameByDate(v) + ". Pilih tanggal kerja.");
                inputEl.classList.add("is-invalid");
                return false;
            }
            inputEl.setCustomValidity("");
            inputEl.classList.remove("is-invalid");
            return true;
        }

        function refreshFormDateHint(form) {
            if (!form) {
                return;
            }
            var fromEl = form.querySelector('[data-hcm-field="dateFrom"]');
            var toEl = form.querySelector('[data-hcm-field="dateTo"]');
            var daysEl = form.querySelector('[data-hcm-field="days"]');
            var hintEl = form.querySelector('[data-hcm-leave-date-hint]');
            var estimateEl = form.querySelector('[data-hcm-leave-days-estimate]');

            var fromOk = validateDateInput(fromEl);
            var toOk = validateDateInput(toEl);
            var fromVal = fromEl ? fromEl.value : "";
            var toVal = toEl ? toEl.value : "";
            var stats = countWorkingDays(fromVal, toVal);

            if (hintEl) {
                if (!fromVal || !toVal) {
                    hintEl.textContent = "Pilih rentang tanggal. Hari libur/weekend akan ditampilkan otomatis.";
                } else if (!fromOk || !toOk) {
                    hintEl.textContent = "Rentang tanggal mengandung batas non-working day. Silakan sesuaikan tanggal.";
                } else if (stats.excluded.length) {
                    hintEl.textContent = "Tanggal non-working dalam rentang: " + stats.excluded.slice(0, 3).join(", ") + (stats.excluded.length > 3 ? "..." : "");
                } else {
                    hintEl.textContent = "Rentang tanggal valid (hari kerja).";
                }
            }

            if (estimateEl) {
                estimateEl.textContent = "Estimasi hari kerja terpotong: " + String(stats.days) + " hari";
            }

            if (daysEl && (!daysEl.value || String(daysEl.value).trim() === "")) {
                daysEl.placeholder = stats.days > 0 ? ("Auto: " + stats.days + " hari kerja") : "Auto from range if empty";
            }
        }

        function bindDateValidation(form) {
            if (!form || form.getAttribute("data-hcm-leave-date-bound") === "1") {
                return;
            }
            form.setAttribute("data-hcm-leave-date-bound", "1");
            var fromEl = form.querySelector('[data-hcm-field="dateFrom"]');
            var toEl = form.querySelector('[data-hcm-field="dateTo"]');

            var disableFn = function (date) {
                var key = date.toISOString().slice(0, 10);
                return isWeekendDate(key) || isHolidayDate(key);
            };

            if (window.flatpickr) {
                var fromPicker = window.flatpickr(fromEl, {
                    dateFormat: "Y-m-d",
                    disableMobile: true,
                    disable: [disableFn],
                    onChange: function (selectedDates, dateStr) {
                        if (toPicker) {
                            toPicker.set("minDate", dateStr || null);
                        }
                        refreshFormDateHint(form);
                    },
                });
                var toPicker = window.flatpickr(toEl, {
                    dateFormat: "Y-m-d",
                    disableMobile: true,
                    disable: [disableFn],
                    onChange: function () {
                        refreshFormDateHint(form);
                    },
                });
                leaveFlatpickrInstances.push(fromPicker, toPicker);
            }

            [fromEl, toEl].forEach(function (el) {
                if (!el) {
                    return;
                }
                el.addEventListener("change", function () {
                    refreshFormDateHint(form);
                });
            });
        }

        function leaveTypeHintByName(name) {
            var key = String(name || "").trim();
            if (!key) {
                return "Info potong saldo akan tampil setelah jenis dipilih.";
            }
            var meta = leaveTypeMetaByName[key];
            if (!meta) {
                return "Info potong saldo belum tersedia untuk tipe ini.";
            }
            return "Dipotong saldo: " + (meta.deductFromBalance ? "Ya" : "Tidak") + " | Berbayar: " + (meta.isPaid ? "Ya" : "Tidak");
        }

        function refreshLeaveTypeHints() {
            var addForm = document.querySelector('[data-hcm-leave-form="add"]');
            if (addForm) {
                var addSelect = addForm.querySelector('[data-hcm-field="leaveType"]');
                var addHint = addForm.querySelector('[data-hcm-leave-type-hint]');
                if (addHint) {
                    addHint.textContent = leaveTypeHintByName(addSelect ? addSelect.value : "");
                }
            }

            var editForm = document.querySelector('[data-hcm-leave-form="edit"]');
            if (editForm) {
                var editSelect = editForm.querySelector('[data-hcm-field="leaveType"]');
                var editHint = editForm.querySelector('[data-hcm-leave-type-hint]');
                if (editHint) {
                    editHint.textContent = leaveTypeHintByName(editSelect ? editSelect.value : "");
                }
            }
        }

        function updateLeaveBalanceDisplay(leaveTypeSelect) {
            if (!leaveTypeSelect) return;
            
            var modal = leaveTypeSelect.closest('.modal');
            if (!modal) return;
            
            var selectedLeaveType = leaveTypeSelect.value;
            var balanceCard = modal.querySelector('[data-hcm-leave-balance-card]');
            
            if (!balanceCard) return;
            
            // Hide if no leave type selected
            if (!selectedLeaveType) {
                balanceCard.classList.add('d-none');
                return;
            }
            
            // Get employee ID from the form (default to current user)
            var form = modal.querySelector('[data-hcm-leave-form]');
            var userSelect = form ? form.querySelector('[data-hcm-field="userId"]') : null;
            var userId = userSelect && userSelect.value ? userSelect.value : null;
            
            // Build request payload
            var params = new URLSearchParams();
            params.append('leaveType', selectedLeaveType);
            if (userId) {
                params.append('userId', userId);
            }
            
            // Fetch balance from API
            apiRequest('get', '/v1/hcm/employee-leave-balance?' + params.toString(), null)
                .then(function (response) {
                    if (!response || !response.success) {
                        balanceCard.classList.add('d-none');
                        return;
                    }
                    
                    var balance = response.data;
                    if (!balance) {
                        balanceCard.classList.add('d-none');
                        return;
                    }
                    
                    // Update balance values
                    var valueEl = balanceCard.querySelector('[data-hcm-leave-balance-value]');
                    var totalEl = balanceCard.querySelector('[data-hcm-leave-balance-total]');
                    
                    if (valueEl && totalEl) {
                        var available = Math.max(0, parseFloat(balance.balance) || 0);
                        var total = (parseFloat(balance.used) || 0) + available;
                        
                        valueEl.textContent = available.toFixed(1);
                        totalEl.textContent = total.toFixed(1);
                        
                        // Show/hide based on availability
                        if (available > 0) {
                            balanceCard.classList.remove('d-none', 'alert-warning');
                            balanceCard.classList.add('alert-info');
                        } else if (available <= 0) {
                            balanceCard.classList.remove('d-none', 'alert-info');
                            balanceCard.classList.add('alert-warning');
                        }
                    }
                })
                .catch(function () {
                    balanceCard.classList.add('d-none');
                });
        }

        function setText(sel, value) {
            var el = document.querySelector(sel);
            if (el) {
                el.textContent = value;
            }
        }

        function updateLeaveCards(meta) {
            var summary = (meta && meta.summary) || {};
            if (isAdmin) {
                setText('[data-hcm-leaves-stat="totalRequests"]', String(summary.totalRequests != null ? summary.totalRequests : 0));
                setText('[data-hcm-leaves-stat="approved"]', String(summary.approved != null ? summary.approved : 0));
                setText('[data-hcm-leaves-stat="declined"]', String(summary.declined != null ? summary.declined : 0));
                setText('[data-hcm-leaves-stat="pending"]', String(summary.pending != null ? summary.pending : 0));
                return;
            }

            var balanceSummary = (meta && meta.balanceSummary) || {};
            var byType = Array.isArray(balanceSummary.byType) ? balanceSummary.byType : [];
            var buckets = {
                annual: { total: 0, remain: 0, codes: { annual_leave: true } },
                medical: { total: 0, remain: 0, codes: { sick_leave: true, hospitalisation: true } },
                casual: { total: 0, remain: 0, codes: { maternity_leave: true, paternity_leave: true } },
                other: { total: 0, remain: 0, codes: {} },
            };

            byType.forEach(function (r) {
                var code = String(r.code || "");
                var total = (parseFloat(r.used || 0) || 0) + (parseFloat(r.balance || 0) || 0);
                var remain = parseFloat(r.balance || 0) || 0;
                if (buckets.annual.codes[code]) {
                    buckets.annual.total += total;
                    buckets.annual.remain += remain;
                    return;
                }
                if (buckets.medical.codes[code]) {
                    buckets.medical.total += total;
                    buckets.medical.remain += remain;
                    return;
                }
                if (buckets.casual.codes[code]) {
                    buckets.casual.total += total;
                    buckets.casual.remain += remain;
                    return;
                }
                buckets.other.total += total;
                buckets.other.remain += remain;
            });

            ["annual", "medical", "casual", "other"].forEach(function (key) {
                setText('[data-hcm-leaves-balance-card="' + key + '"]', String(buckets[key].total.toFixed(1)).replace(/\.0$/, ""));
                setText('[data-hcm-leaves-balance-remaining="' + key + '"]', String(buckets[key].remain.toFixed(1)).replace(/\.0$/, ""));
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
                        var badge =
                            r.status === "approved" ? "success" : r.status === "declined" ? "danger" : "warning";
                        var leaveTypeText = displayLeaveType(r);
                        var empCell = isAdmin
                            ? "<td><div class=\"fw-medium\">" +
                              esc(r.employeeName) +
                              '</div><small class="text-muted">' +
                              esc(r.email) +
                              "</small></td>"
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
                            "</td><td><span class=\"badge badge-" +
                            badge +
                            ' d-inline-flex align-items-center badge-xs">' +
                            esc(r.status) +
                            "</span></td><td>" +
                            (actions.length ? actions.join("") : '<span class="text-muted">-</span>') +
                            "</td></tr>"
                        );
                    })
                    .join("") ||
                    '<tr><td colspan="' +
                    (isAdmin ? "8" : "7") +
                    '" class="text-center py-4 text-muted">No leave requests.</td></tr>';
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
                        renderHolidayPanel({});
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
                    updateLeaveCards(p.meta || {});
                    renderHolidayPanel(p.meta || {});
                    refreshFormDateHint(document.querySelector('[data-hcm-leave-form="add"]'));
                    refreshFormDateHint(document.querySelector('[data-hcm-leave-form="edit"]'));
                    tryOpenLeaveFromQuery();
                })
                .catch(function (e) {
                    renderHolidayPanel({});
                    notify(formatApiError(e && e.data, e && e.status), true);
                });
        }

        setupLeavePagination();
        setupLeaveFilterControls();
        bindExportButton();

        var addForm = document.querySelector('[data-hcm-leave-form="add"]');
        if (addForm) {
            bindDateValidation(addForm);
            
            // Clear balance display and error alert when modal is shown
            var addModal = document.getElementById('arcav_add_leave');
            if (addModal) {
                addModal.addEventListener('show.bs.modal', function () {
                    // Clear error alert
                    var errorAlert = addForm.querySelector('[data-hcm-leave-error-add]');
                    if (errorAlert) {
                        errorAlert.classList.add('d-none');
                    }
                    // Clear balance display
                    var balanceCard = addForm.querySelector('[data-hcm-leave-balance-card]');
                    if (balanceCard) {
                        balanceCard.classList.add('d-none');
                    }
                    // Reset form
                    addForm.reset();
                });
            }
            
            // Hide error alert when user starts editing
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
                refreshFormDateHint(addForm);
                if (!addForm.checkValidity()) {
                    addForm.reportValidity();
                    return;
                }
                var ltEl = addForm.querySelector('[data-hcm-field="leaveType"]');
                var payload = {
                    leaveType: (ltEl && ltEl.value ? ltEl.value : "").trim(),
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
                        (function () {
                            var el = document.getElementById("arcav_add_leave");
                            var mi = el && window.bootstrap && window.bootstrap.Modal.getInstance(el);
                            if (mi) {
                                mi.hide();
                            }
                        })();
                        addForm.reset();
                        reload();
                    })
                    .catch(function (err) {
                        var errorMsg = formatApiError(err.data, err.status);
                        notify(errorMsg, true);
                        
                        // Display error in modal alert
                        var errorAlert = addForm.querySelector('[data-hcm-leave-error-add]');
                        if (errorAlert) {
                            var titleEl = errorAlert.querySelector('[data-hcm-error-title]');
                            var msgEl = errorAlert.querySelector('[data-hcm-error-message]');
                            if (titleEl && msgEl) {
                                // Extract error code and message from API response
                                var errorCode = (err.data && err.data.error && err.data.error.code) || 'ERROR';
                                var errorText = (err.data && err.data.error && err.data.error.message) || errorMsg;
                                
                                // Format error code to readable format
                                var codeDisplay = errorCode
                                    .replace(/_/g, ' ')
                                    .toLowerCase()
                                    .split(' ')
                                    .map(function(w) { return w.charAt(0).toUpperCase() + w.slice(1); })
                                    .join(' ');
                                
                                titleEl.textContent = codeDisplay;
                                msgEl.textContent = errorText;
                                errorAlert.classList.remove("d-none");
                                
                                // Scroll to error
                                errorAlert.scrollIntoView({ behavior: 'smooth', block: 'start' });
                            }
                        }
                    });
            });
        }

        var editForm = document.querySelector('[data-hcm-leave-form="edit"]');
        if (editForm) {
            bindDateValidation(editForm);

            function syncAdminLeaveReviewNotes() {
                var notesEl = editForm.querySelector('[data-hcm-field="notes"]');
                var statusEl = editForm.querySelector('[data-hcm-field="status"]');
                if (!notesEl) {
                    return;
                }

                var owner = String(editForm.querySelector('[data-hcm-field="ownerUserId"]').value || "");
                var me = String(window.__arcav_me_id || "");
                var adminReviewMode = isAdmin && owner && owner !== me;
                var notesLabel = notesEl.closest('.mb-3') && notesEl.closest('.mb-3').querySelector('.form-label');

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
            
            // Hide error alert when user starts editing
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
                // Clear error when opening form
                var errorAlert = editForm.querySelector('[data-hcm-leave-error-edit]');
                if (errorAlert) {
                    errorAlert.classList.add("d-none");
                }
                
                editForm.querySelector('[data-hcm-field="id"]').value = btn.dataset.id || "";
                editForm.querySelector('[data-hcm-field="ownerUserId"]').value = btn.dataset.user || "";
                var editLt = editForm.querySelector('[data-hcm-field="leaveType"]');
                if (editLt) {
                    editLt.innerHTML = buildLeaveTypeOptionsHtml(leaveTypesCache, btn.dataset.type || "");
                    // Update balance display when leave type is set
                    updateLeaveBalanceDisplay(editLt);
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
                refreshFormDateHint(editForm);
                syncAdminLeaveReviewNotes();
            });
            editForm.addEventListener("submit", function (e) {
                e.preventDefault();
                refreshFormDateHint(editForm);
                if (!editForm.checkValidity()) {
                    editForm.reportValidity();
                    return;
                }
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

                    payload = {
                        status: statusValue,
                    };
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
                        (function () {
                            var el = document.getElementById("arcav_edit_leave");
                            var mi = el && window.bootstrap && window.bootstrap.Modal.getInstance(el);
                            if (mi) {
                                mi.hide();
                            }
                        })();
                        reload();
                    })
                    .catch(function (err) {
                        var errorMsg = formatApiError(err.data, err.status);
                        notify(errorMsg, true);
                        
                        // Display error in modal alert
                        var errorAlert = editForm.querySelector('[data-hcm-leave-error-edit]');
                        if (errorAlert) {
                            var titleEl = errorAlert.querySelector('[data-hcm-error-title]');
                            var msgEl = errorAlert.querySelector('[data-hcm-error-message]');
                            if (titleEl && msgEl) {
                                // Extract error code and message from API response
                                var errorCode = (err.data && err.data.error && err.data.error.code) || 'ERROR';
                                var errorText = (err.data && err.data.error && err.data.error.message) || errorMsg;
                                
                                // Format error code to readable format
                                var codeDisplay = errorCode
                                    .replace(/_/g, ' ')
                                    .toLowerCase()
                                    .split(' ')
                                    .map(function(w) { return w.charAt(0).toUpperCase() + w.slice(1); })
                                    .join(' ');
                                
                                titleEl.textContent = codeDisplay;
                                msgEl.textContent = errorText;
                                errorAlert.classList.remove("d-none");
                                
                                // Scroll to error
                                errorAlert.scrollIntoView({ behavior: 'smooth', block: 'start' });
                            }
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
                var run =
                    window.ArcavUi && typeof window.ArcavUi.confirmDelete === "function"
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
                addLt.innerHTML = buildLeaveTypeOptionsHtml(leaveTypesCache, "");
            }
            if (editLt) {
                editLt.innerHTML = buildLeaveTypeOptionsHtml(leaveTypesCache, "");
            }
            refreshLeaveTypeHints();
        }

        document.addEventListener("change", function (e) {
            var select = e.target && e.target.closest('[data-hcm-field="leaveType"]');
            if (!select) {
                return;
            }
            refreshLeaveTypeHints();
            updateLeaveBalanceDisplay(select);
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

    function bindLeaveReport() {
        var tbody = document.querySelector("[data-leave-report-body]");
        var leaveChart = null;

        function renderLeaveChart(summary, byStatus) {
            var chartEl = document.getElementById("leave-report-chart");
            if (!chartEl) {
                return;
            }
            var statusMap = byStatus || {};
            var approved = parseInt((statusMap.approved && statusMap.approved.count) || 0, 10) || 0;
            var pending = parseInt((statusMap.pending && statusMap.pending.count) || 0, 10) || 0;
            var declined = parseInt((statusMap.declined && statusMap.declined.count) || 0, 10) || 0;
            var totalRequests = summary && summary.total_requests != null
                ? parseInt(summary.total_requests, 10) || 0
                : (summary && summary.totalRequests != null ? parseInt(summary.totalRequests, 10) || 0 : 0);
            var other = Math.max(totalRequests - approved - pending - declined, 0);
            var series = [approved, pending, declined, other];

            if (leaveChart && typeof leaveChart.destroy === "function") {
                leaveChart.destroy();
                leaveChart = null;
            }

            if (!window.ApexCharts) {
                chartEl.innerHTML = '<div class="text-muted small">Grafik belum tersedia.</div>';
                return;
            }

            if (series.reduce(function (a, b) { return a + b; }, 0) <= 0) {
                chartEl.innerHTML = '<div class="text-center text-muted small py-5">Belum ada data cuti untuk ditampilkan.</div>';
                return;
            }

            chartEl.innerHTML = "";
            leaveChart = new window.ApexCharts(chartEl, {
                chart: {
                    type: "donut",
                    height: 240,
                },
                series: series,
                labels: ["Approved", "Pending", "Declined", "Other"],
                colors: ["#0E9384", "#FFB534", "#E70D0D", "#6C757D"],
                legend: {
                    position: "bottom",
                },
                dataLabels: {
                    enabled: true,
                },
                stroke: {
                    width: 1,
                },
            });
            leaveChart.render();
        }

        function renderSummary(summary, byStatus) {
            var statusMap = byStatus || {};
            var approved = statusMap.approved && statusMap.approved.count != null ? statusMap.approved.count : 0;
            var pending = statusMap.pending && statusMap.pending.count != null ? statusMap.pending.count : 0;
            var totalRequests = summary && summary.total_requests != null
                ? summary.total_requests
                : (summary && summary.totalRequests != null ? summary.totalRequests : 0);
            var totalDays = summary && summary.total_days != null
                ? summary.total_days
                : (summary && summary.totalDays != null ? summary.totalDays : 0);

            var totalEl = document.querySelector("[data-leave-report-total-requests]");
            var daysEl = document.querySelector("[data-leave-report-total-days]");
            var approvedEl = document.querySelector("[data-leave-report-approved]");
            var pendingEl = document.querySelector("[data-leave-report-pending]");
            if (totalEl) {
                totalEl.textContent = String(totalRequests || 0);
            }
            if (daysEl) {
                daysEl.textContent = String(totalDays || 0);
            }
            if (approvedEl) {
                approvedEl.textContent = String(approved || 0);
            }
            if (pendingEl) {
                pendingEl.textContent = String(pending || 0);
            }
            renderLeaveChart(summary || {}, statusMap);
        }

        function renderRows(rows) {
            if (!tbody) {
                return;
            }
            if (!rows.length) {
                tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">Belum ada data cuti.</td></tr>';
                return;
            }
            tbody.innerHTML = rows.map(function (r) {
                var status = String(r.status || "pending");
                var badge = status === "approved" ? "success" : status === "declined" ? "danger" : "warning";
                return "<tr>"
                    + "<td>" + esc(r.employeeName || "-") + "</td>"
                    + "<td>" + esc(r.leaveType || "-") + "</td>"
                    + "<td>" + esc(r.dateFrom || "-") + "</td>"
                    + "<td>" + esc(r.dateTo || "-") + "</td>"
                    + "<td>" + esc(String(r.days != null ? r.days : 0)) + "</td>"
                    + '<td><span class="badge badge-' + badge + ' badge-xs">' + esc(status) + "</span></td>"
                    + "</tr>";
            }).join("");
        }

        function fetchLiveLeaveReportPage(page, collected, firstMeta) {
            return apiRequest("get", "/v1/hcm/leave-requests?perPage=100&page=" + encodeURIComponent(String(page)), null)
                .then(function (payload) {
                    if (!payload || payload.success !== true) {
                        return Promise.reject({ payload: payload });
                    }

                    var rows = Array.isArray(payload.data) ? payload.data : [];
                    var meta = payload.meta || {};
                    var pagination = meta.pagination || {};
                    var totalPages = parseInt(pagination.totalPages, 10) || 1;
                    var nextCollected = collected.concat(rows);
                    var seedMeta = firstMeta || meta;

                    if (page >= totalPages || rows.length < 1) {
                        return {
                            rows: nextCollected,
                            meta: seedMeta,
                        };
                    }

                    return fetchLiveLeaveReportPage(page + 1, nextCollected, seedMeta);
                });
        }

        function loadLiveReport() {
            if (tbody) {
                tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">Memuat data cuti...</td></tr>';
            }
            fetchLiveLeaveReportPage(1, [], null)
                .then(function (result) {
                    var rows = Array.isArray(result && result.rows) ? result.rows : [];
                    var meta = (result && result.meta) || {};
                    renderRows(rows.map(function (item) {
                        return {
                            employeeName: item.employeeName,
                            leaveType: item.leaveTypeLabel || item.leaveType,
                            dateFrom: item.dateFrom,
                            dateTo: item.dateTo,
                            days: item.days,
                            status: item.status,
                        };
                    }));
                    var byStatus = {};
                    rows.forEach(function (item) {
                        var key = String(item.status || "pending");
                        byStatus[key] = byStatus[key] || { count: 0 };
                        byStatus[key].count += 1;
                    });
                    var totalDays = rows.reduce(function (sum, item) {
                        return sum + (parseFloat(item.days || 0) || 0);
                    }, 0);
                    renderSummary({
                        totalRequests: meta.summary && meta.summary.totalRequests != null ? meta.summary.totalRequests : rows.length,
                        totalDays: totalDays,
                    }, byStatus);
                })
                .catch(function (err) {
                    notify(formatApiError(err && err.data, err && err.status), true);
                    renderRows([]);
                    renderSummary({}, {});
                });
        }

        function reload() {
            loadLiveReport();
        }

        document.addEventListener("click", function (event) {
            var trigger = event.target && event.target.closest ? event.target.closest("[data-leave-report-load]") : null;
            if (!trigger) {
                return;
            }
            event.preventDefault();
            reload();
        });

        reload();
    }

    function bindOvertime(isAdmin) {
        var moduleArgs = {
            notify: notify,
            formatApiError: formatApiError,
            apiRequest: apiRequest,
            loadEmployeeOptions: loadEmployeeOptions,
            overtimeStatusMeta: overtimeStatusMeta,
            isPendingOlderThan24h: isPendingOlderThan24h,
            overtimePolicyTypeLabel: overtimePolicyTypeLabel,
            esc: esc,
        };

        var moduleFn = resolveBindOvertimeModule();
        if (moduleFn) {
            return moduleFn(moduleArgs, isAdmin);
        }

        loadBindOvertimeModule().then(function (loadedFn) {
            if (typeof loadedFn === "function") {
                loadedFn(moduleArgs, isAdmin);
            }
        });
        return null;
    }

    function bindOvertimeCalculator() {
        var moduleFn = resolveBindOvertimeCalculatorModule();
        var moduleArgs = {
            notify: notify,
            apiRequest: apiRequest,
            normalizeOvertimeDayType: normalizeOvertimeDayType,
            overtimeDayTypeLabel: overtimeDayTypeLabel,
            formatOvertimeComplianceError: formatOvertimeComplianceError,
            loadEmployeeOptions: loadEmployeeOptions,
            getEmployeeCompensationById: function () {
                return employeeCompensationById;
            },
        };

        if (moduleFn) {
            return moduleFn(moduleArgs);
        }

        loadBindOvertimeCalculatorModule().then(function (loadedFn) {
            if (typeof loadedFn === "function") {
                loadedFn(moduleArgs);
            }
        });
        return null;
    }

    function extractPermissionSet(mePayload) {
        var set = {};
        var data = mePayload && mePayload.data ? mePayload.data : null;
        var permissions = data && data.permissions ? data.permissions : null;
        var codes = data && Array.isArray(data.permissionCodes) ? data.permissionCodes : [];

        if (permissions && typeof permissions === "object") {
            Object.keys(permissions).forEach(function (key) {
                if (permissions[key] === true) {
                    set[String(key)] = true;
                }
            });
        }

        codes.forEach(function (code) {
            if (typeof code === "string" && code.trim() !== "") {
                set[code.trim()] = true;
            }
        });

        return set;
    }

    function canAccessAdminPage(mePayload, requiredPermission) {
        var data = mePayload && mePayload.data ? mePayload.data : null;
        if (!data) {
            return false;
        }

        if (data.hcmGlobalAdmin === true || data.hcmAdmin === true) {
            return true;
        }

        if (!requiredPermission) {
            return true;
        }

        var permissionSet = extractPermissionSet(mePayload);
        return permissionSet[requiredPermission] === true;
    }

    function init() {
        var path = String(window.location.pathname || "").replace(/\/+$/, "") || "/";
        if (path === "/holidays") {
            apiRequest("get", "/v1/identity/auth/me", null).then(function (m) {
                if (m && m.success && !canAccessAdminPage(m, "holiday.view")) {
                    window.location.replace("/employee-dashboard");
                    return;
                }
                bindHolidays();
            });
        } else if (path === "/leaves") {
            apiRequest("get", "/v1/identity/auth/me", null).then(function (m) {
                if (m && m.success && m.data && m.data.id) {
                    window.__arcav_me_id = m.data.id;
                }
                var isAdmin = !!(m && m.success && canAccessAdminPage(m, "leave.view"));
                if (m && m.success && !isAdmin) {
                    window.location.replace("/leaves-employee");
                    return;
                }
                bindLeaves("all", true);
            });
        } else if (path === "/leaves-employee") {
            bindLeaves("me", false);
        } else if (path === "/leave-report") {
            apiRequest("get", "/v1/identity/auth/me", null).then(function (m) {
                if (m && m.success && !canAccessAdminPage(m, "leave.view")) {
                    window.location.replace("/employee-dashboard");
                    return;
                }
                bindLeaveReport();
            });
        } else if (path === "/overtime") {
            apiRequest("get", "/v1/identity/auth/me", null).then(function (m) {
                if (m && m.success && !canAccessAdminPage(m, "overtime.view")) {
                    window.location.replace("/overtime-employee");
                    return;
                }
                if (m && m.success && m.data && m.data.id) {
                    window.__arcav_me_id = m.data.id;
                }
                bindOvertimeCalculator();
                document.querySelectorAll("[data-hcm-ot-admin-only]").forEach(function (el) {
                    el.style.display = "";
                });
                var addTitle = document.querySelector("[data-hcm-ot-add-title]");
                if (addTitle) {
                    addTitle.textContent = "Add overtime";
                }
                bindOvertime(true);
            });
        } else if (path === "/overtime-employee") {
            bindOvertimeCalculator();
            apiRequest("get", "/v1/identity/auth/me", null).then(function (m) {
                if (m && m.success && m.data && m.data.id) {
                    window.__arcav_me_id = m.data.id;
                }
                document.querySelectorAll("[data-hcm-ot-admin-only]").forEach(function (el) {
                    el.style.display = "none";
                });
                var addTitle = document.querySelector("[data-hcm-ot-add-title]");
                if (addTitle) {
                    addTitle.textContent = "Request overtime";
                }
                bindOvertime(false);
            });
        }
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", init);
    } else {
        init();
    }
})(window, document);
