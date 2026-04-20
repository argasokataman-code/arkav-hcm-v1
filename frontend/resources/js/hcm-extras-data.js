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
            headers: { Accept: "text/csv,application/json" },
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
        var body = document.querySelector("[data-hcm-holidays-body]");
        var holidaysCache = [];
        var holidaysFilter = { search: "", source: "", status: "" };
        var latestLinkageMeta = null;

        function toInt(val, fallback) {
            var parsed = parseInt(val, 10);
            return Number.isFinite(parsed) ? parsed : fallback;
        }

        function renderHolidaySignals(rows) {
            var prevName = document.querySelector("[data-hcm-holiday-prev-name]");
            var prevDate = document.querySelector("[data-hcm-holiday-prev-date]");
            var nextName = document.querySelector("[data-hcm-holiday-next-name]");
            var nextDate = document.querySelector("[data-hcm-holiday-next-date]");
            if (!prevName || !prevDate || !nextName || !nextDate) {
                return;
            }

            var today = new Date();
            var t = new Date(today.getFullYear(), today.getMonth(), today.getDate());
            var validRows = (rows || []).filter(function (h) {
                return h && h.holidayDate;
            });

            var prev = null;
            var next = null;
            validRows.forEach(function (h) {
                var d = new Date(String(h.holidayDate) + "T00:00:00");
                if (!(d instanceof Date) || Number.isNaN(d.getTime())) {
                    return;
                }
                if (d < t && (!prev || d > prev.__dateObj)) {
                    prev = Object.assign({ __dateObj: d }, h);
                }
                if (d >= t && (!next || d < next.__dateObj)) {
                    next = Object.assign({ __dateObj: d }, h);
                }
            });

            prevName.textContent = prev ? String(prev.title || "-") : "Belum ada data";
            prevDate.textContent = prev ? String(prev.holidayDate || "-") : "-";
            nextName.textContent = next ? String(next.title || "-") : "Belum ada data";
            nextDate.textContent = next ? String(next.holidayDate || "-") : "-";
        }

        function renderLinkageSummary(meta) {
            var linkage = (meta && meta.linkage) || {};
            var holidayRows = holidaysCache.length;
            var manualRows = holidaysCache.filter(function (h) {
                return String(h && h.source || "manual").toLowerCase() !== "api";
            }).length;
            var apiRows = holidaysCache.filter(function (h) {
                return String(h && h.source || "").toLowerCase() === "api";
            }).length;
            var calendarRowsFallback = holidayRows;
            var linkedRowsFallback = calendarRowsFallback;

            var resolved = {
                holidayRows: toInt(linkage.holidayRows, holidayRows),
                calendarRows: toInt(linkage.calendarRows, calendarRowsFallback),
                linkedRows: toInt(linkage.linkedRows, linkedRowsFallback),
                unlinkedRows: 0,
                manualRows: toInt(linkage.manualRows, manualRows),
                apiRows: toInt(linkage.apiRows, apiRows),
            };
            resolved.unlinkedRows = toInt(linkage.unlinkedRows, Math.max(0, resolved.calendarRows - resolved.linkedRows));

            var keys = ["holidayRows", "calendarRows", "linkedRows", "unlinkedRows", "manualRows", "apiRows"];
            keys.forEach(function (key) {
                var node = document.querySelector('[data-hcm-holiday-linkage="' + key + '"]');
                if (!node) {
                    return;
                }
                var val = resolved[key];
                node.textContent = val == null ? "0" : String(val);
            });
        }

        function renderListCount(filteredCount) {
            var filteredEl = document.querySelector("[data-hcm-holidays-filtered-count]");
            var totalEl = document.querySelector("[data-hcm-holidays-total-count]");
            if (filteredEl) {
                filteredEl.textContent = String(filteredCount);
            }
            if (totalEl) {
                totalEl.textContent = String(holidaysCache.length);
            }
        }

        function render(rows) {
            if (!body) {
                return;
            }
            body.innerHTML =
                (rows || []).map(function (h) {
                    var badge = h.isActive ? "success" : "danger";
                    var st = h.isActive ? "Active" : "Inactive";
                    return (
                        "<tr><td><div class=\"form-check form-check-md\"><input class=\"form-check-input\" type=\"checkbox\"></div></td><td><h6 class=\"fw-medium\">" +
                        esc(h.title) +
                        "</h6></td><td>" +
                        esc(h.holidayDate) +
                        "</td><td>" +
                        esc(h.description) +
                        '</td><td><span class="badge badge-soft-dark">' +
                        esc(h.source || "manual") +
                        "</span></td><td><span class=\"badge badge-" +
                        badge +
                        ' d-inline-flex align-items-center badge-xs"><i class="ti ti-point-filled me-1"></i>' +
                        esc(st) +
                        "</td><td><span class=\"badge badge-" +
                        'secondary d-inline-flex align-items-center badge-xs">' +
                        esc(h.lastSyncedAt ? new Date(h.lastSyncedAt).toLocaleString("id-ID") : "—") +
                        "</span></td><td><div class=\"action-icon d-inline-flex\"><a href=\"#\" class=\"me-2\" data-hcm-holiday-edit data-id=\"" +
                        esc(h.id) +
                        "\" data-title=\"" +
                        esc(h.title) +
                        "\" data-date=\"" +
                        esc(h.holidayDate) +
                        "\" data-desc=\"" +
                        esc(h.description) +
                        "\" data-active=\"" +
                        (h.isActive ? "1" : "0") +
                        "\" data-bs-toggle=\"modal\" data-bs-target=\"#arcav_edit_holiday\"><i class=\"ti ti-edit\"></i></a><a href=\"#\" data-hcm-holiday-delete=\"" +
                        esc(h.id) +
                        "\"><i class=\"ti ti-trash\"></i></a></div></td></tr>"
                    );
                }).join("") || '<tr><td colspan="8" class="text-center py-4 text-muted">No holidays.</td></tr>';
        }

        function reload() {
            apiRequest("get", "/v1/hcm/holidays", null)
                .then(function (p) {
                    if (!p) {
                        notify("Please sign in.", true);
                        return;
                    }
                    if (p.success !== true) {
                        notify("Failed to load holidays.", true);
                        return;
                    }
                    holidaysCache = Array.isArray(p.data) ? p.data.slice() : [];
                    latestLinkageMeta = p.meta || {};
                    renderFiltered();
                    renderHolidaySignals(holidaysCache);
                    renderLinkageSummary(latestLinkageMeta);
                })
                .catch(function (e) {
                    notify(formatApiError(e.data, e.status), true);
                });
        }

        function renderFiltered() {
            var s = holidaysFilter.search.toLowerCase().trim();
            var src = holidaysFilter.source.toLowerCase();
            var st = holidaysFilter.status.toLowerCase();
            var rows = holidaysCache.filter(function (h) {
                if (s && !(
                    (h.title || "").toLowerCase().includes(s) ||
                    (h.holidayDate || "").includes(s) ||
                    (h.description || "").toLowerCase().includes(s)
                )) {
                    return false;
                }
                if (src) {
                    var hsrc = (h.source || "manual").toLowerCase();
                    if (src === "api" && hsrc !== "api") return false;
                    if (src === "manual" && hsrc === "api") return false;
                }
                if (st) {
                    if (st === "active" && !h.isActive) return false;
                    if (st === "inactive" && h.isActive) return false;
                }
                return true;
            });
            render(rows);
            renderListCount(rows.length);
            renderLinkageSummary(latestLinkageMeta);
        }

        // Attach filter inputs
        document.querySelectorAll("[data-hcm-holidays-filter]").forEach(function (el) {
            var key = el.getAttribute("data-hcm-holidays-filter");
            var evt = el.tagName === "SELECT" ? "change" : "input";
            el.addEventListener(evt, function () {
                holidaysFilter[key] = el.value;
                renderFiltered();
            });
        });
        var holidaysResetBtn = document.querySelector("[data-hcm-holidays-filter-reset]");
        if (holidaysResetBtn) {
            holidaysResetBtn.addEventListener("click", function () {
                holidaysFilter = { search: "", source: "", status: "" };
                document.querySelectorAll("[data-hcm-holidays-filter]").forEach(function (el) {
                    el.value = "";
                });
                renderFiltered();
            });
        }

        var addForm = document.querySelector('[data-hcm-holiday-form="add"]');
        var syncBtn = document.querySelector("[data-hcm-holiday-sync]");
        var syncYearInput = document.querySelector("[data-hcm-holiday-sync-year]");
        var exportBtn = document.querySelector("[data-hcm-holiday-export]");
        if (syncYearInput && !syncYearInput.value) {
            syncYearInput.value = String(new Date().getFullYear());
        }
        if (exportBtn) {
            exportBtn.addEventListener("click", function () {
                if (!holidaysCache.length) {
                    notify("Belum ada data holiday untuk diexport.", true);
                    return;
                }
                var headers = ["Title", "Date", "Description", "Source", "Status", "Synced At"];
                var rows = holidaysCache.map(function (h) {
                    return [
                        h.title || "",
                        h.holidayDate || "",
                        h.description || "",
                        h.source || "manual",
                        h.isActive ? "Active" : "Inactive",
                        h.lastSyncedAt ? new Date(h.lastSyncedAt).toLocaleString("id-ID") : "",
                    ];
                });
                var yearTag = String((syncYearInput && syncYearInput.value) || new Date().getFullYear());
                downloadCsv("holidays-" + yearTag + ".csv", headers, rows);
                notify("Export CSV berhasil.", false);
            });
        }
        if (syncBtn) {
            syncBtn.addEventListener("click", function () {
                var year = parseInt((syncYearInput && syncYearInput.value) || String(new Date().getFullYear()), 10);
                if (!year || year < 2000 || year > 2100) {
                    notify("Year must be between 2000 and 2100.", true);
                    return;
                }
                syncBtn.disabled = true;
                apiRequest("post", "/v1/hcm/holidays/sync-indonesia", { year: year })
                    .then(function (p) {
                        if (!p || p.success !== true) {
                            notify(formatApiError(p, 0) || "Sync failed.", true);
                            return;
                        }
                        var d = p.data || {};
                        notify(
                            "Sync " +
                                String(d.year || year) +
                                " selesai. Created: " +
                                String(d.created || 0) +
                                ", Updated: " +
                                String(d.updated || 0) +
                                ", Stale cleaned: " +
                                String(d.cleanedStaleApi || 0) +
                                ", Skipped manual: " +
                                String(d.skippedManual || 0),
                            false
                        );
                        reload();
                    })
                    .catch(function (err) {
                        notify(formatApiError(err.data, err.status), true);
                    })
                    .finally(function () {
                        syncBtn.disabled = false;
                    });
            });
        }

        if (addForm) {
            addForm.addEventListener("submit", function (e) {
                e.preventDefault();
                var title = addForm.querySelector('[data-hcm-field="title"]').value.trim();
                var hd = addForm.querySelector('[data-hcm-field="holidayDate"]').value;
                var desc = addForm.querySelector('[data-hcm-field="description"]').value.trim();
                var active = addForm.querySelector('[data-hcm-field="isActive"]').value === "1";
                apiRequest("post", "/v1/hcm/holidays", { title: title, holidayDate: hd, description: desc || null, isActive: active })
                    .then(function (p) {
                        if (!p || p.success !== true) {
                            notify("Save failed.", true);
                            return;
                        }
                        notify("Saved.", false);
                        (function () {
                            var el = document.getElementById("arcav_add_holiday");
                            var mi = el && window.bootstrap && window.bootstrap.Modal.getInstance(el);
                            if (mi) {
                                mi.hide();
                            }
                        })();
                        addForm.reset();
                        reload();
                    })
                    .catch(function (err) {
                        notify(formatApiError(err.data, err.status), true);
                    });
            });
        }

        var editForm = document.querySelector('[data-hcm-holiday-form="edit"]');
        if (editForm) {
            document.addEventListener("click", function (e) {
                var btn = e.target.closest("[data-hcm-holiday-edit]");
                if (!btn) {
                    return;
                }
                editForm.querySelector('[data-hcm-field="id"]').value = btn.dataset.id || "";
                editForm.querySelector('[data-hcm-field="title"]').value = btn.dataset.title || "";
                editForm.querySelector('[data-hcm-field="holidayDate"]').value = btn.dataset.date || "";
                editForm.querySelector('[data-hcm-field="description"]').value = btn.dataset.desc || "";
                editForm.querySelector('[data-hcm-field="isActive"]').value = btn.dataset.active === "1" ? "1" : "0";
            });
            editForm.addEventListener("submit", function (e) {
                e.preventDefault();
                var id = editForm.querySelector('[data-hcm-field="id"]').value;
                if (!id) {
                    return;
                }
                var payload = {
                    title: editForm.querySelector('[data-hcm-field="title"]').value.trim(),
                    holidayDate: editForm.querySelector('[data-hcm-field="holidayDate"]').value,
                    description: editForm.querySelector('[data-hcm-field="description"]').value.trim() || null,
                    isActive: editForm.querySelector('[data-hcm-field="isActive"]').value === "1",
                };
                apiRequest("put", "/v1/hcm/holidays/" + encodeURIComponent(id), payload)
                    .then(function (p) {
                        if (!p || p.success !== true) {
                            notify("Update failed.", true);
                            return;
                        }
                        notify("Updated.", false);
                        (function () {
                            var el = document.getElementById("arcav_edit_holiday");
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
                var del = e.target.closest("[data-hcm-holiday-delete]");
                if (!del) {
                    return;
                }
                e.preventDefault();
                var hid = del.getAttribute("data-hcm-holiday-delete");
                var run =
                    window.ArcavUi && typeof window.ArcavUi.confirmDelete === "function"
                        ? window.ArcavUi.confirmDelete("Libur ini akan dihapus. Lanjutkan?", "Hapus libur")
                        : Promise.resolve(false);
                run.then(function (ok) {
                    if (!ok) {
                        return;
                    }
                    apiRequest("delete", "/v1/hcm/holidays/" + encodeURIComponent(hid), null)
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

        reload();
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

                downloadFileFromUrl(url, "leave-requests.csv")
                    .then(function (ok) {
                        if (!ok) {
                            return;
                        }
                        notify("Export leave CSV berhasil.", false);
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
            
            // Hide error alert when user starts editing
            editForm.addEventListener("input", function () {
                var errorAlert = editForm.querySelector('[data-hcm-leave-error-edit]');
                if (errorAlert) {
                    errorAlert.classList.add("d-none");
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
                editForm.querySelector('[data-hcm-field="notes"]').value = btn.dataset.notes || "";
                refreshLeaveTypeHints();
                refreshFormDateHint(editForm);
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
                    payload = {
                        status: editForm.querySelector('[data-hcm-field="status"]').value,
                        notes: editForm.querySelector('[data-hcm-field="notes"]').value.trim() || null,
                    };
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
                chartEl.innerHTML = '<div class="text-muted small">Chart library tidak tersedia.</div>';
                return;
            }

            if (series.reduce(function (a, b) { return a + b; }, 0) <= 0) {
                chartEl.innerHTML = '<div class="text-center text-muted small py-5">Belum ada data leave untuk ditampilkan.</div>';
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

        function setSourceBadge() {
            var badge = document.querySelector("[data-leave-report-source-badge]");
            if (!badge) {
                return;
            }
            var mode = getSourceMode();
            if (mode === "archive") {
                var id = getSnapshotId();
                badge.textContent = "Source: Archive" + (id > 0 ? " #" + String(id) : "");
                return;
            }
            badge.textContent = "Source: Live";
        }

        function getSourceMode() {
            var sel = document.querySelector("[data-leave-report-source]");
            var mode = sel ? String(sel.value || "live").toLowerCase() : "live";
            return mode === "archive" ? "archive" : "live";
        }

        function getSnapshotId() {
            var input = document.querySelector("[data-leave-report-snapshot-id]");
            var parsed = input ? parseInt(String(input.value || "0"), 10) : 0;
            return Number.isFinite(parsed) && parsed > 0 ? parsed : 0;
        }

        function syncSourceControls() {
            var wrap = document.querySelector("[data-leave-report-snapshot-wrap]");
            if (wrap) {
                if (getSourceMode() === "archive") {
                    wrap.classList.remove("d-none");
                } else {
                    wrap.classList.add("d-none");
                }
            }
            setSourceBadge();
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
                tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">Tidak ada data leave report.</td></tr>';
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
                tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">Memuat data live…</td></tr>';
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

        function loadArchiveReport(snapshotId) {
            if (!snapshotId) {
                renderRows([]);
                renderSummary({}, {});
                notify("Snapshot ID wajib diisi untuk mode Archive.", true);
                return;
            }
            if (tbody) {
                tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">Memuat snapshot archive…</td></tr>';
            }
            apiRequest("get", "/v1/hcm/reports/snapshots/" + encodeURIComponent(String(snapshotId)), null)
                .then(function (payload) {
                    if (!payload || payload.success !== true || !payload.data) {
                        notify("Snapshot tidak ditemukan atau tidak bisa diakses.", true);
                        renderRows([]);
                        renderSummary({}, {});
                        return;
                    }
                    var snapshot = payload.data;
                    if (snapshot.reportType !== "leave") {
                        notify("Snapshot ini bukan leave report.", true);
                        renderRows([]);
                        renderSummary({}, {});
                        return;
                    }
                    if (String(snapshot.status || "").toLowerCase() !== "completed") {
                        notify("Snapshot leave belum siap digunakan.", true);
                        renderRows([]);
                        renderSummary({}, {});
                        return;
                    }
                    var moduleData = snapshot.dataByModule && snapshot.dataByModule.leave ? snapshot.dataByModule.leave : {};
                    var users = Object.keys(moduleData)
                        .filter(function (key) { return key.indexOf("user_") === 0; })
                        .map(function (key) { return moduleData[key]; });

                    renderRows(users.map(function (item) {
                        return {
                            employeeName: item.user_name || "Unknown",
                            leaveType: "Archive Aggregate",
                            dateFrom: snapshot.periodStart || "-",
                            dateTo: snapshot.periodEnd || "-",
                            days: item.total_days || 0,
                            status: "archived",
                        };
                    }));

                    renderSummary(moduleData.summary || {}, moduleData.by_status || {});
                })
                .catch(function (err) {
                    notify(formatApiError(err && err.data, err && err.status), true);
                    renderRows([]);
                    renderSummary({}, {});
                });
        }

        function reload() {
            setSourceBadge();
            if (getSourceMode() === "archive") {
                loadArchiveReport(getSnapshotId());
                return;
            }
            loadLiveReport();
        }

        syncSourceControls();
        document.addEventListener("change", function (event) {
            var source = event.target && event.target.closest ? event.target.closest("[data-leave-report-source]") : null;
            if (source) {
                syncSourceControls();
                return;
            }
            var snapshotInput = event.target && event.target.closest ? event.target.closest("[data-leave-report-snapshot-id]") : null;
            if (snapshotInput) {
                setSourceBadge();
            }
        });
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
        var otPage = 1;
        var otPerPage = 20;
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
            body.innerHTML =
                (rows || [])
                    .map(function (r) {
                        var badge =
                            r.status === "approved" ? "success" : r.status === "declined" ? "danger" : "warning";
                        var hrs = (r.minutes / 60).toFixed(2);
                        var emp = isAdmin ? "<td>" + esc(r.employeeName) + "</td>" : "";
                        var tid = r.overtimeTypeId != null && r.overtimeTypeId !== "" ? String(r.overtimeTypeId) : "";
                        return (
                            "<tr>" +
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
                            badge +
                            ' badge-xs">' +
                            esc(r.status) +
                            "</span></td><td><a href=\"#\" data-hcm-ot-edit data-id=\"" +
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
                    '" class="text-center py-4 text-muted">No overtime requests.</td></tr>';
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

        var addForm = document.querySelector('[data-hcm-ot-form="add"]');
        if (addForm) {
            var userSel = addForm.querySelector('[data-hcm-field="userId"]');
            if (userSel && isAdmin) {
                loadEmployeeOptions(userSel);
            }
            addForm.addEventListener("submit", function (e) {
                e.preventDefault();
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

    function bindOvertimeCalculator() {
        var resultEl = document.querySelector('[data-hcm-ot-calc="result"]');
        var btn = document.querySelector('[data-hcm-ot-calc="run"]');
        var employeeSelect = document.querySelector('[data-hcm-ot-calc="employeeId"]');
        var baseSalaryInput = document.querySelector('[data-hcm-ot-calc="baseSalary"]');
        var fixedAllowanceInput = document.querySelector('[data-hcm-ot-calc="fixedAllowance"]');
        if (!resultEl || !btn) {
            return;
        }

        function applyCompensationFromEmployee() {
            if (!employeeSelect || !baseSalaryInput || !fixedAllowanceInput) {
                return;
            }
            var emp = employeeCompensationById[String(employeeSelect.value || "")];
            if (!emp) {
                return;
            }
            baseSalaryInput.value = String(Math.round(emp.baseSalary));
            fixedAllowanceInput.value = String(Math.round(emp.fixedAllowance));
        }

        if (employeeSelect) {
            employeeSelect.addEventListener("change", applyCompensationFromEmployee);
        }

        btn.addEventListener("click", function () {
            var payload = {
                baseMonthlySalary: parseFloat((document.querySelector('[data-hcm-ot-calc="baseSalary"]') || {}).value || "0"),
                fixedAllowance: parseFloat((document.querySelector('[data-hcm-ot-calc="fixedAllowance"]') || {}).value || "0"),
                minutes: parseInt((document.querySelector('[data-hcm-ot-calc="minutes"]') || {}).value || "0", 10),
                dayType: (document.querySelector('[data-hcm-ot-calc="dayType"]') || {}).value || "workday",
                weeklyWorkDays: parseInt((document.querySelector('[data-hcm-ot-calc="weeklyWorkDays"]') || {}).value || "5", 10),
            };
            if (!payload.baseMonthlySalary || !payload.minutes) {
                notify("Isi dulu gaji pokok dan menit lembur.", true);
                return;
            }
            apiRequest("post", "/v1/hcm/overtime-requests/calculate", payload)
                .then(function (r) {
                    if (!r || r.success !== true) {
                        resultEl.textContent = "Gagal menghitung.";
                        return;
                    }
                    var d = r.data || {};
                    var seg = (d.segments || []).map(function (s) {
                        return (s.label || "-") + ": " + (s.hours || 0) + " jam x " + (s.multiplier || 0) + "x";
                    }).join(" | ");
                    var sc = d.salaryComponent;
                    var scPart =
                        sc && (sc.code || sc.name)
                            ? " | Komponen slip: " + (sc.code || "") + (sc.name ? " — " + sc.name : "")
                            : "";
                    resultEl.textContent =
                        "Upah sejam Rp" + Number(d.hourlyWage || 0).toLocaleString("id-ID") +
                        " | Segment: " + seg +
                        " | Total lembur Rp" + Number(d.totalOvertimePay || 0).toLocaleString("id-ID") +
                        scPart;
                })
                .catch(function (e) {
                    resultEl.textContent = formatApiError(e.data, e.status);
                });
        });

        // Load employee list for compensation auto-fill (UI helper only).
        if (employeeSelect) {
            loadEmployeeOptions(employeeSelect).then(function () {
                if (employeeSelect.value) {
                    applyCompensationFromEmployee();
                }
            });
        }
    }

    function init() {
        var path = String(window.location.pathname || "").replace(/\/+$/, "") || "/";
        if (path === "/holidays") {
            apiRequest("get", "/v1/identity/auth/me", null).then(function (m) {
                if (m && m.success && m.data && (!m.data.permissions || !m.data.permissions['holiday.view'])) {
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
                var isAdmin = !!(m && m.success && m.data && m.data.permissions && m.data.permissions['leave.view']);
                if (m && m.success && m.data && (!m.data.permissions || !m.data.permissions['leave.view'])) {
                    window.location.replace("/leaves-employee");
                    return;
                }
                bindLeaves("all", true);
            });
        } else if (path === "/leaves-employee") {
            bindLeaves("me", false);
        } else if (path === "/leave-report") {
            apiRequest("get", "/v1/identity/auth/me", null).then(function (m) {
                if (m && m.success && m.data && (!m.data.permissions || !m.data.permissions['leave.view'])) {
                    window.location.replace("/employee-dashboard");
                    return;
                }
                bindLeaveReport();
            });
        } else if (path === "/overtime") {
            apiRequest("get", "/v1/identity/auth/me", null).then(function (m) {
                if (m && m.success && m.data && (!m.data.permissions || !m.data.permissions['overtime.view'])) {
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
