export function renderSmartPlannerAssignmentPreview(deps, result) {
  var buildPlannerAssignmentIndex = deps.buildPlannerAssignmentIndex;
  var setSmartPlannerAssignmentByUserId = deps.setSmartPlannerAssignmentByUserId;
  var getSmartPlannerAssignmentByUserId = deps.getSmartPlannerAssignmentByUserId;
  var formatPlannerPattern = deps.formatPlannerPattern;
  var esc = deps.esc;

  var body = document.querySelector("[data-smart-planner-assignment-body]");
  var meta = document.querySelector("[data-smart-planner-assignment-meta]");
  if (!body) {
    return;
  }

  var weeklySchedule =
    result && result.schedule_generation && Array.isArray(result.schedule_generation.weekly_schedule)
      ? result.schedule_generation.weekly_schedule
      : [];

  setSmartPlannerAssignmentByUserId(buildPlannerAssignmentIndex(weeklySchedule));

  var rows = Object.keys(getSmartPlannerAssignmentByUserId())
    .map(function (userId) {
      var item = getSmartPlannerAssignmentByUserId()[userId];
      return {
        userId: userId,
        employeeName: item.employeeName,
        workDays: item.workDays,
        offDays: item.offDays,
        nightCount: item.nightCount,
        morningCount: item.morningCount,
        afternoonCount: item.afternoonCount,
        pattern: formatPlannerPattern(item.assignments),
      };
    })
    .sort(function (a, b) {
      return String(a.employeeName).localeCompare(String(b.employeeName));
    });

  if (!rows.length) {
    body.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-3">Draft assignment tidak tersedia.</td></tr>';
    if (meta) {
      meta.textContent = "Belum ada draft assignment.";
    }
    return;
  }

  body.innerHTML = rows
    .map(function (row) {
      return (
        "<tr>" +
        "<td>" + esc(row.employeeName) + "</td>" +
        '<td><span class="badge bg-success-subtle text-success">' + esc(String(row.workDays)) + "</span></td>" +
        '<td><span class="badge bg-secondary-subtle text-secondary">' + esc(String(row.offDays)) + "</span></td>" +
        '<td><span class="badge bg-dark-subtle text-dark">' + esc(String(row.nightCount)) + "</span></td>" +
        '<td class="small text-muted">' + esc(row.pattern) + "</td>" +
        "</tr>"
      );
    })
    .join("");

  if (meta) {
    meta.textContent =
      "Terjadwal " +
      String(rows.length) +
      " karyawan (M/A/N dihitung di tabel). Baris di Schedule Timing List yang punya badge AI Draft 24h berasal dari rekomendasi ini.";
  }
}

function renderSimpleList(target, rows, formatter) {
  if (!target) {
    return;
  }
  while (target.firstChild) {
    target.removeChild(target.firstChild);
  }

  if (!rows || !rows.length) {
    var empty = document.createElement("li");
    empty.className = "text-muted";
    empty.textContent = "Tidak ada data.";
    target.appendChild(empty);
    return;
  }

  rows.forEach(function (row) {
    var li = document.createElement("li");
    li.textContent = String(formatter(row) || "");
    target.appendChild(li);
  });
}

export function renderSmartPlannerResult(deps, result) {
  var renderSmartPlannerAssignmentPreview = deps.renderSmartPlannerAssignmentPreview;
  var renderPlannerDiffPreview = deps.renderPlannerDiffPreview;
  var renderPlannerConflictPreview = deps.renderPlannerConflictPreview;
  var getSmartPlannerLastPayload = deps.getSmartPlannerLastPayload;
  var getScheduleTimingRowsCache = deps.getScheduleTimingRowsCache;
  var renderScheduleTimingRows = deps.renderScheduleTimingRows;
  var renderScheduleCalendar = deps.renderScheduleCalendar;
  var updatePlannerApplyState = deps.updatePlannerApplyState;

  var wrap = document.querySelector("[data-smart-planner-result]");
  if (!wrap || !result) {
    return;
  }

  var schedule = result.schedule_generation || {};
  var recommendation = result.recommendation || {};
  var violations = Array.isArray(schedule.violations) ? schedule.violations : [];
  var unmetCoverage = Array.isArray(schedule.unmet_coverage) ? schedule.unmet_coverage : [];
  var suggestions = Array.isArray(recommendation.improvement_suggestions)
    ? recommendation.improvement_suggestions
    : [];

  var validationEl = wrap.querySelector("[data-smart-planner-validation]");
  var fairnessEl = wrap.querySelector("[data-smart-planner-fairness]");
  var fatigueEl = wrap.querySelector("[data-smart-planner-fatigue]");
  var unmetEl = wrap.querySelector("[data-smart-planner-unmet]");
  var explanationEl = wrap.querySelector("[data-smart-planner-explanation]");
  var violationsEl = wrap.querySelector("[data-smart-planner-violations]");
  var suggestionsEl = wrap.querySelector("[data-smart-planner-suggestions]");

  if (validationEl) {
    validationEl.textContent = String(schedule.validation_status || "unknown").toUpperCase();
  }
  if (fairnessEl) {
    fairnessEl.textContent = String(recommendation.fairness_score != null ? recommendation.fairness_score : "-");
  }
  if (fatigueEl) {
    fatigueEl.textContent = String(recommendation.fatigue_risk_score != null ? recommendation.fatigue_risk_score : "-");
  }
  if (unmetEl) {
    unmetEl.textContent = String(unmetCoverage.length);
  }
  if (explanationEl) {
    explanationEl.textContent = String(result.explanation || "-");
  }

  renderSimpleList(violationsEl, violations, function (row) {
    var code = row && row.code ? String(row.code) : "RULE";
    var message = row && row.message ? String(row.message) : "Violation detected";
    return code + ": " + message;
  });

  renderSimpleList(suggestionsEl, suggestions, function (row) {
    var title = row && row.title ? String(row.title) : "Suggestion";
    var reason = row && row.reason ? String(row.reason) : "";
    return reason ? title + " - " + reason : title;
  });

  renderSmartPlannerAssignmentPreview(result);
  renderPlannerDiffPreview(result);
  renderPlannerConflictPreview(result, getSmartPlannerLastPayload() || {});

  if (Array.isArray(getScheduleTimingRowsCache()) && getScheduleTimingRowsCache().length > 0) {
    renderScheduleTimingRows(getScheduleTimingRowsCache());
  }

  renderScheduleCalendar();
  updatePlannerApplyState(result);
  wrap.classList.remove("d-none");
}
