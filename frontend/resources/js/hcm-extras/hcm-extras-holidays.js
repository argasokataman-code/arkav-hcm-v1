export function bindHolidaysModule(deps) {
    var apiRequest = deps.apiRequest;
    var esc = deps.esc;
    var notify = deps.notify;
    var downloadCsv = deps.downloadCsv;
    var downloadFileFromUrl = deps.downloadFileFromUrl;
    var formatApiError = deps.formatApiError;
    var formatOvertimeComplianceError = deps.formatOvertimeComplianceError;

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
            return String((h && h.source) || "manual").toLowerCase() !== "api";
        }).length;
        var apiRows = holidaysCache.filter(function (h) {
            return String((h && h.source) || "").toLowerCase() === "api";
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
            if (
                s &&
                !((h.title || "").toLowerCase().includes(s) || (h.holidayDate || "").includes(s) || (h.description || "").toLowerCase().includes(s))
            ) {
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
                            String(d.skippedManual || 0) +
                            ", Skipped non-primary: " +
                            String(d.skippedNonPrimary || 0),
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
            if (!ArcavValidation.validateForm(addForm)) { return; }
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
                    notify(formatOvertimeComplianceError(err.data, err.status), true);
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
            if (!ArcavValidation.validateForm(editForm)) { return; }
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
                    notify(formatOvertimeComplianceError(err.data, err.status), true);
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

    ["arcav_add_holiday", "arcav_edit_holiday"].forEach(function (id) {
        var el = document.getElementById(id);
        if (el) {
            el.addEventListener("shown.bs.modal", function () {
                var firstInput = document.querySelector("#" + id + " input:not([type=hidden]):not([type=password]), #" + id + " select");
                if (firstInput) setTimeout(function () { firstInput.focus(); }, 100);
            });
        }
    });

    reload();

    return {
        reload: reload,
    };
}
