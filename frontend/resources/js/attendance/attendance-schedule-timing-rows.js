export function renderScheduleTimingRowsModule(deps, rows) {
    var esc = deps.esc;
    var getScheduleTimingAiOnly = deps.getScheduleTimingAiOnly;
    var getSmartPlannerAssignmentByUserId = deps.getSmartPlannerAssignmentByUserId;
    var renderScheduleCalendar = deps.renderScheduleCalendar;

    var tbody = document.querySelector("[data-schedule-timing-body]");
    if (!tbody) return;
    var sourceRows = Array.isArray(rows) ? rows : [];
    if (getScheduleTimingAiOnly()) {
        sourceRows = sourceRows.filter(function (r) {
            return !!getSmartPlannerAssignmentByUserId()[String((r && r.userId) || "")];
        });
    }
    if (!sourceRows.length) {
        tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-4">No schedule timings found.</td></tr>';
        tbody.setAttribute("data-hydrated", "1");
        return;
    }
    tbody.innerHTML = sourceRows
        .map(function (r) {
            var shiftBadge = r.shiftName
                ? ' <span class="badge bg-light text-dark border ms-1">' + esc(r.shiftName) + "</span>"
                : "";
            var sm = r.startMinutes != null ? String(r.startMinutes) : "0";
            var em = r.endMinutes != null ? String(r.endMinutes) : "0";
            var sid = r.shiftId != null && r.shiftId !== "" ? String(r.shiftId) : "";
            var aiPlan = getSmartPlannerAssignmentByUserId()[String((r && r.userId) || "")];
            var aiPlanBadge = "";
            if (aiPlan) {
                aiPlanBadge =
                    ' <span class="badge badge-warning-transparent ms-1">AI Draft 24h</span>' +
                    ' <span class="text-muted small d-block mt-1">M:' +
                    esc(String(aiPlan.morningCount || 0)) +
                    " A:" +
                    esc(String(aiPlan.afternoonCount || 0)) +
                    " N:" +
                    esc(String(aiPlan.nightCount || 0)) +
                    " OFF:" +
                    esc(String(aiPlan.offDays || 0)) +
                    "</span>";
            }
            return (
                "<tr>" +
                '<td><div class="form-check form-check-md"><input class="form-check-input" type="checkbox"></div></td>' +
                "<td>" +
                esc(r.name) +
                "</td>" +
                "<td>" +
                esc(r.jobTitle) +
                "</td>" +
                "<td>" +
                esc(r.availableTimings) +
                shiftBadge +
                (r.source === "manual" ? ' <span class="badge badge-info-transparent ms-1">Manual</span>' : "") +
                aiPlanBadge +
                "</td>" +
                '<td><a href="#" data-schedule-timing-edit data-user-id="' +
                esc(String((r && r.userId) || "")) +
                '" data-name="' +
                esc(r.name || "") +
                '" data-start-minutes="' +
                esc(sm) +
                '" data-end-minutes="' +
                esc(em) +
                '" data-shift-id="' +
                esc(sid) +
                '" data-source="' +
                esc(String(r.source || "auto")) +
                '"><i class="ti ti-edit"></i></a></td>' +
                "</tr>"
            );
        })
        .join("");
    tbody.setAttribute("data-hydrated", "1");
    renderScheduleCalendar();
}
