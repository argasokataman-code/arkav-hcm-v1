export function createScheduleTimingEditModule(deps) {
    var esc = deps.esc;
    var minutesToTimeStr = deps.minutesToTimeStr;
    var parseHiToMinutes = deps.parseHiToMinutes;
    var apiGet = deps.apiGet;
    var apiDelete = deps.apiDelete;
    var apiPut = deps.apiPut;
    var notify = deps.notify;
    var formatApiError = deps.formatApiError;
    var timeInputToHi = deps.timeInputToHi;
    var loadScheduleTiming = deps.loadScheduleTiming;
    var getScheduleShiftsCache = deps.getScheduleShiftsCache;
    var setScheduleShiftsCache = deps.setScheduleShiftsCache;

    function fillScheduleShiftSelect(selectEl) {
        if (!selectEl) {
            return;
        }
        var html = '<option value="">Custom (manual)</option>';
        var shifts = getScheduleShiftsCache();
        for (var i = 0; i < shifts.length; i++) {
            var s = shifts[i];
            if (!s.isActive) {
                continue;
            }
            html +=
                '<option value="' +
                esc(String(s.id)) +
                '" data-start="' +
                esc(s.startTime || "") +
                '" data-end="' +
                esc(s.endTime || "") +
                '">' +
                esc((s.name || "") + " (" + (s.slotLabel || "") + ")") +
                "</option>";
        }
        selectEl.innerHTML = html;
    }

    function syncTimesFromShiftSelect(selectEl, startInp, endInp) {
        if (!selectEl || !startInp || !endInp) {
            return;
        }
        var opt = selectEl.options[selectEl.selectedIndex];
        if (!opt || !selectEl.value) {
            return;
        }
        var ds = opt.getAttribute("data-start");
        var de = opt.getAttribute("data-end");
        if (ds) {
            startInp.value = minutesToTimeStr(parseHiToMinutes(ds));
        }
        if (de) {
            endInp.value = minutesToTimeStr(parseHiToMinutes(de));
        }
    }

    function ensureScheduleShiftsLoaded(callback) {
        apiGet("/v1/hcm/shifts")
            .then(function (p) {
                if (p && p.success === true && Array.isArray(p.data)) {
                    setScheduleShiftsCache(p.data);
                } else {
                    setScheduleShiftsCache([]);
                }
                if (typeof callback === "function") {
                    callback();
                }
            })
            .catch(function () {
                setScheduleShiftsCache([]);
                if (typeof callback === "function") {
                    callback();
                }
            });
    }

    function setupScheduleTimingEditModal() {
        var form = document.querySelector("[data-schedule-timing-edit-form]");
        var modalEl = document.getElementById("arcav_schedule_timing_edit");
        if (!form || !modalEl || !(window.bootstrap && window.bootstrap.Modal)) {
            return;
        }
        var shiftSel = form.querySelector("[data-st-edit-shift]");
        var startInp = form.querySelector("[data-st-edit-start]");
        var endInp = form.querySelector("[data-st-edit-end]");
        var uidInp = form.querySelector("[data-st-edit-user-id]");
        var resetBtn = form.querySelector("[data-st-edit-reset]");
        if (shiftSel && startInp && endInp) {
            shiftSel.addEventListener("change", function () {
                syncTimesFromShiftSelect(shiftSel, startInp, endInp);
            });
        }
        if (resetBtn && !resetBtn.getAttribute("data-arcav-st-reset-bound")) {
            resetBtn.setAttribute("data-arcav-st-reset-bound", "1");
            resetBtn.addEventListener("click", function () {
                var uid = uidInp ? uidInp.value.trim() : "";
                if (!uid) {
                    return;
                }
                var run =
                    window.ArcavUi && typeof window.ArcavUi.confirmDelete === "function"
                        ? window.ArcavUi.confirmDelete(
                              "Jadwal manual akan dihapus. Tampilan kembali mengikuti rata-rata absensi 30 hari terakhir.",
                              "Reset ke otomatis"
                          )
                        : Promise.resolve(false);
                run.then(function (ok) {
                    if (!ok) {
                        return;
                    }
                    resetBtn.disabled = true;
                    apiDelete("/v1/hcm/schedule-timing/" + encodeURIComponent(uid))
                        .then(function (payload) {
                            if (!payload || payload.success !== true) {
                                notify(formatApiError(payload, 0) || "Gagal reset jadwal.", true);
                                return;
                            }
                            notify("Jadwal dikembalikan ke otomatis.", false);
                            window.bootstrap.Modal.getOrCreateInstance(modalEl).hide();
                            loadScheduleTiming();
                        })
                        .catch(function (err) {
                            var data = err && err.response ? err.response.data : err && err.data ? err.data : null;
                            var status = err && err.response ? err.response.status : err && err.status ? err.status : 0;
                            notify(formatApiError(data, status) || "Gagal reset jadwal.", true);
                        })
                        .finally(function () {
                            resetBtn.disabled = false;
                        });
                });
            });
        }
        form.addEventListener("submit", function (e) {
            e.preventDefault();
            var uid = uidInp ? uidInp.value.trim() : "";
            if (!uid) {
                return;
            }
            var body;
            if (shiftSel && shiftSel.value) {
                body = { shiftId: parseInt(shiftSel.value, 10) };
            } else {
                body = {
                    startTime: timeInputToHi(startInp ? startInp.value : ""),
                    endTime: timeInputToHi(endInp ? endInp.value : ""),
                };
            }
            var sub = form.querySelector("[data-st-edit-submit]");
            if (sub) {
                sub.disabled = true;
            }
            apiPut("/v1/hcm/schedule-timing/" + encodeURIComponent(uid), body)
                .then(function (payload) {
                    if (!payload || payload.success !== true) {
                        notify(formatApiError(payload, 0) || "Gagal menyimpan jadwal.", true);
                        return;
                    }
                    notify("Jadwal disimpan.", false);
                    window.bootstrap.Modal.getOrCreateInstance(modalEl).hide();
                    loadScheduleTiming();
                })
                .catch(function (err) {
                    var data = err && err.response ? err.response.data : err && err.data ? err.data : null;
                    var status = err && err.response ? err.response.status : err && err.status ? err.status : 0;
                    notify(formatApiError(data, status) || "Gagal menyimpan jadwal.", true);
                })
                .finally(function () {
                    if (sub) {
                        sub.disabled = false;
                    }
                });
        });
    }

    return {
        fillScheduleShiftSelect: fillScheduleShiftSelect,
        syncTimesFromShiftSelect: syncTimesFromShiftSelect,
        ensureScheduleShiftsLoaded: ensureScheduleShiftsLoaded,
        setupScheduleTimingEditModal: setupScheduleTimingEditModal,
    };
}
