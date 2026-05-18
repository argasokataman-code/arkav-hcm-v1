export function createLeaveDateHelpers(esc) {
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
        holidayMetaRows = meta && Array.isArray(meta.holidays) ? meta.holidays.slice() : [];
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
                return '<span class="badge ' + tone + ' me-1">' + esc(h.date) + ' &nbsp;' + esc(h.name) + "</span>";
            }).join("");
        }

        leaveFlatpickrInstances.forEach(function (picker) {
            if (picker && typeof picker.redraw === "function") {
                picker.redraw();
            }
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
            daysEl.placeholder = stats.days > 0 ? "Auto: " + stats.days + " hari kerja" : "Auto from range if empty";
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
                onChange: function (_selectedDates, dateStr) {
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

    return {
        bindDateValidation: bindDateValidation,
        refreshFormDateHint: refreshFormDateHint,
        renderHolidayPanel: renderHolidayPanel,
    };
}