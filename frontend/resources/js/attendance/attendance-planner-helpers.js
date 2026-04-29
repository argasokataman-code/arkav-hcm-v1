export function createPlannerHelpers(deps) {
    var getScheduleShiftsCache = deps.getScheduleShiftsCache;

    function getCurrentWeekStartIso() {
        var now = new Date();
        var day = now.getDay();
        var diff = day === 0 ? -6 : 1 - day;
        now.setDate(now.getDate() + diff);
        var y = now.getFullYear();
        var m = String(now.getMonth() + 1).padStart(2, "0");
        var d = String(now.getDate()).padStart(2, "0");
        return y + "-" + m + "-" + d;
    }

    function setSmartPlannerFeedback(message, isError) {
        var feedback = document.querySelector("[data-smart-planner-feedback]");
        if (!feedback) {
            return;
        }
        feedback.textContent = String(message || "");
        feedback.classList.remove("d-none", "alert-light", "alert-danger", "alert-success");
        feedback.classList.add(isError ? "alert-danger" : "alert-success");
    }

    function findScheduleShiftById(shiftId) {
        var target = parseInt(String(shiftId || ""), 10);
        if (!Number.isFinite(target)) {
            return null;
        }
        var rows = getScheduleShiftsCache();
        for (var i = 0; i < rows.length; i++) {
            var row = rows[i];
            if (parseInt(String((row && row.id) || ""), 10) === target) {
                return row;
            }
        }
        return null;
    }

    function shiftVisualConfig(label) {
        if (label === "M") {
            return { color: "#2563eb", textColor: "#ffffff", title: "Morning" };
        }
        if (label === "A") {
            return { color: "#f59e0b", textColor: "#111827", title: "Afternoon" };
        }
        if (label === "N") {
            return { color: "#111827", textColor: "#ffffff", title: "Night" };
        }
        return { color: "#6b7280", textColor: "#ffffff", title: "Shift" };
    }

    function plannerShiftMeta(assignment) {
        var shiftIdRaw = String((assignment && assignment.shift_id) || "");
        if (shiftIdRaw.toUpperCase() === "OFF") {
            return { label: "OFF", title: "Off", color: "#6b7280", textColor: "#ffffff", shiftName: "OFF" };
        }

        var shift = findScheduleShiftById(shiftIdRaw);
        var shiftName = String((shift && shift.name) || "");
        var shiftType = String((shift && shift.shiftType) || "").toLowerCase();
        var start = String((assignment && assignment.start_time) || (shift && shift.startTime) || "");
        var h = parseInt(start.slice(0, 2), 10);
        var normalizedName = shiftName.toLowerCase();

        var label = "S";
        var title = shiftName || "Shift";

        if (shiftType === "night" || normalizedName.indexOf("night") !== -1 || normalizedName.indexOf("malam") !== -1 || (Number.isFinite(h) && (h >= 20 || h < 5))) {
            label = "N";
            title = shiftName || "Night";
        } else if (shiftType === "afternoon" || normalizedName.indexOf("afternoon") !== -1 || normalizedName.indexOf("siang") !== -1 || (Number.isFinite(h) && h >= 12 && h < 20)) {
            label = "A";
            title = shiftName || "Afternoon";
        } else if (shiftType === "morning" || normalizedName.indexOf("morning") !== -1 || normalizedName.indexOf("pagi") !== -1 || Number.isFinite(h)) {
            label = "M";
            title = shiftName || "Morning";
        }

        var visual = shiftVisualConfig(label);
        return {
            label: label,
            title: title,
            color: visual.color,
            textColor: visual.textColor,
            shiftName: shiftName || (shiftIdRaw ? "#" + shiftIdRaw : "Shift"),
        };
    }

    function formatPlannerPattern(assignments) {
        if (!Array.isArray(assignments) || assignments.length === 0) {
            return "-";
        }
        return assignments
            .map(function (a) {
                if (String((a && a.shift_id) || "") === "OFF") {
                    return "OFF";
                }
                return plannerShiftMeta(a).label;
            })
            .join(" | ");
    }

    function plannerDateFromIso(dateIso) {
        var raw = String(dateIso || "").trim();
        if (!/^\d{4}-\d{2}-\d{2}$/.test(raw)) {
            return null;
        }
        var parsed = new Date(raw + "T00:00:00");
        if (isNaN(parsed.getTime())) {
            return null;
        }
        return parsed;
    }

    function plannerIsoFromDate(dateObj) {
        if (!(dateObj instanceof Date) || isNaN(dateObj.getTime())) {
            return "";
        }
        var y = dateObj.getFullYear();
        var m = String(dateObj.getMonth() + 1).padStart(2, "0");
        var d = String(dateObj.getDate()).padStart(2, "0");
        return y + "-" + m + "-" + d;
    }

    function plannerEndOfYearIso(weekStartIso) {
        var source = plannerDateFromIso(weekStartIso) || new Date();
        return String(source.getFullYear()) + "-12-31";
    }

    function plannerBuildWeekStarts(weekStartIso, endIso) {
        var startDate = plannerDateFromIso(weekStartIso);
        var endDate = plannerDateFromIso(endIso);
        if (!startDate || !endDate || endDate < startDate) {
            return [];
        }

        var weeks = [];
        var cursor = new Date(startDate.getTime());
        while (cursor <= endDate) {
            weeks.push(plannerIsoFromDate(cursor));
            cursor.setDate(cursor.getDate() + 7);
        }

        return weeks;
    }

    function buildPlannerAssignmentIndex(weeklySchedule) {
        var index = {};
        (Array.isArray(weeklySchedule) ? weeklySchedule : []).forEach(function (row) {
            var userId = String((row && row.employee_id) || "");
            if (!userId) {
                return;
            }

            var assignments = Array.isArray(row && row.assignments) ? row.assignments : [];
            var workDays = 0;
            var offDays = 0;
            var morningCount = 0;
            var afternoonCount = 0;
            var nightCount = 0;

            assignments.forEach(function (a) {
                var shiftId = String((a && a.shift_id) || "");
                if (shiftId === "OFF") {
                    offDays += 1;
                    return;
                }
                workDays += 1;
                var label = plannerShiftMeta(a).label;
                if (label === "M") {
                    morningCount += 1;
                } else if (label === "A") {
                    afternoonCount += 1;
                } else if (label === "N") {
                    nightCount += 1;
                }
            });

            index[userId] = {
                employeeName: String((row && row.employee_name) || "Employee"),
                assignments: assignments,
                workDays: workDays,
                offDays: offDays,
                morningCount: morningCount,
                afternoonCount: afternoonCount,
                nightCount: nightCount,
            };
        });

        return index;
    }

    return {
        getCurrentWeekStartIso: getCurrentWeekStartIso,
        setSmartPlannerFeedback: setSmartPlannerFeedback,
        findScheduleShiftById: findScheduleShiftById,
        plannerShiftMeta: plannerShiftMeta,
        formatPlannerPattern: formatPlannerPattern,
        plannerEndOfYearIso: plannerEndOfYearIso,
        plannerBuildWeekStarts: plannerBuildWeekStarts,
        buildPlannerAssignmentIndex: buildPlannerAssignmentIndex,
    };
}
