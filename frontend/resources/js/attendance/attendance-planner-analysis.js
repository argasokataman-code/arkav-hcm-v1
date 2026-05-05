export function createPlannerAnalysisModule(deps) {
  var esc = deps.esc;
  var apiPut = deps.apiPut;
  var apiPost = deps.apiPost;
  var formatApiError = deps.formatApiError;
  var setSmartPlannerFeedback = deps.setSmartPlannerFeedback;
  var findScheduleShiftById = deps.findScheduleShiftById;
  var plannerShiftMeta = deps.plannerShiftMeta;
  var getScheduleTimingRowsCache = deps.getScheduleTimingRowsCache;
  var getScheduleHolidayRowsCache = deps.getScheduleHolidayRowsCache;
  var getSmartPlannerTransitionCatalog = deps.getSmartPlannerTransitionCatalog;
  var getSmartPlannerForbiddenTransitionKeys = deps.getSmartPlannerForbiddenTransitionKeys;
  var getSmartPlannerConflictSummary = deps.getSmartPlannerConflictSummary;
  var setSmartPlannerConflictSummary = deps.setSmartPlannerConflictSummary;

  function scheduleDateIso(value) {
    var raw = String(value || "").trim();
    if (!raw) {
      return "";
    }
    if (/^\d{4}-\d{2}-\d{2}$/.test(raw)) {
      return raw;
    }
    var parsed = new Date(raw);
    if (isNaN(parsed.getTime())) {
      return "";
    }
    return parsed.toISOString().slice(0, 10);
  }

  function scheduleEventDateTime(dateIso, timeValue) {
    var datePart = scheduleDateIso(dateIso);
    var timePart = String(timeValue || "").trim();
    if (!datePart || !timePart) {
      return "";
    }
    if (/^\d{2}:\d{2}$/.test(timePart)) {
      return datePart + "T" + timePart + ":00";
    }
    if (/^\d{2}:\d{2}:\d{2}$/.test(timePart)) {
      return datePart + "T" + timePart;
    }
    return "";
  }

  function mergePlannerAssignmentRows(results) {
    var byUser = {};

    (Array.isArray(results) ? results : []).forEach(function (result) {
      var rows = result && result.schedule_generation && Array.isArray(result.schedule_generation.weekly_schedule)
        ? result.schedule_generation.weekly_schedule
        : [];

      rows.forEach(function (row) {
        var userId = String((row && row.employee_id) || "");
        if (!userId) {
          return;
        }

        if (!byUser[userId]) {
          byUser[userId] = {
            employee_id: userId,
            employee_name: String((row && row.employee_name) || "Employee"),
            assignments: [],
          };
        }

        var incoming = Array.isArray(row && row.assignments) ? row.assignments : [];
        byUser[userId].assignments = byUser[userId].assignments.concat(incoming);
      });
    });

    return Object.keys(byUser)
      .map(function (userId) {
        var item = byUser[userId];
        item.assignments = item.assignments
          .slice()
          .sort(function (a, b) {
            return String((a && a.date) || "").localeCompare(String((b && b.date) || ""));
          });
        return item;
      })
      .sort(function (a, b) {
        return String(a.employee_name || "").localeCompare(String(b.employee_name || ""));
      });
  }

  function plannerDominantShiftRows(result) {
    var weeklySchedule = result && result.schedule_generation && Array.isArray(result.schedule_generation.weekly_schedule)
      ? result.schedule_generation.weekly_schedule
      : [];

    return weeklySchedule
      .map(function (row) {
        var userId = parseInt(row && row.employee_id, 10);
        var counts = {};
        (Array.isArray(row && row.assignments) ? row.assignments : []).forEach(function (assignment) {
          var shiftId = String((assignment && assignment.shift_id) || "");
          if (!shiftId || shiftId === "OFF") {
            return;
          }
          counts[shiftId] = (counts[shiftId] || 0) + 1;
        });

        var dominantShiftId = "";
        var dominantCount = 0;
        Object.keys(counts).forEach(function (shiftId) {
          if (counts[shiftId] > dominantCount) {
            dominantShiftId = shiftId;
            dominantCount = counts[shiftId];
          }
        });

        return {
          userId: userId,
          employeeName: String((row && row.employee_name) || "Employee"),
          shiftId: parseInt(dominantShiftId, 10),
          assignmentCount: dominantCount,
        };
      })
      .filter(function (row) {
        return Number.isFinite(row.userId) && row.userId > 0 && Number.isFinite(row.shiftId) && row.shiftId > 0;
      });
  }

  function scheduleTimingRowByUserId(userId) {
    var target = parseInt(String(userId || ""), 10);
    if (!Number.isFinite(target)) {
      return null;
    }
    var rows = Array.isArray(getScheduleTimingRowsCache()) ? getScheduleTimingRowsCache() : [];
    for (var i = 0; i < rows.length; i++) {
      var row = rows[i];
      if (parseInt(String((row && row.userId) || ""), 10) === target) {
        return row;
      }
    }
    return null;
  }

  function renderPlannerDiffPreview(result) {
    var tbody = document.querySelector("[data-smart-planner-diff-body]");
    var meta = document.querySelector("[data-smart-planner-diff-meta]");
    if (!tbody) {
      return;
    }

    var rows = plannerDominantShiftRows(result);
    if (!rows.length) {
      tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-3">Tidak ada perubahan dominant shift untuk dipreview.</td></tr>';
      if (meta) {
        meta.textContent = "Belum ada preview diff.";
      }
      return;
    }

    var changedCount = 0;
    tbody.innerHTML = rows
      .map(function (row) {
        var beforeRow = scheduleTimingRowByUserId(row.userId);
        var beforeShiftId = parseInt(String((beforeRow && beforeRow.shiftId) || ""), 10);
        var beforeShiftName = String((beforeRow && beforeRow.shiftName) || "Custom / Auto");
        var beforeTiming = String((beforeRow && beforeRow.availableTimings) || "-");
        var afterShift = findScheduleShiftById(row.shiftId);
        var afterShiftName = String((afterShift && afterShift.name) || ("Shift #" + String(row.shiftId)));
        var afterSlot = String((afterShift && afterShift.slotLabel) || "-");

        var changed = !Number.isFinite(beforeShiftId) || beforeShiftId !== row.shiftId;
        if (changed) {
          changedCount += 1;
        }

        return (
          "<tr>" +
          "<td>" + esc(row.employeeName) + "</td>" +
          '<td><span class="fw-semibold">' + esc(beforeShiftName) + '</span><div class="text-muted small">' + esc(beforeTiming) + "</div></td>" +
          '<td><span class="fw-semibold">' + esc(afterShiftName) + '</span><div class="text-muted small">' + esc(afterSlot) + "</div></td>" +
          "<td>" +
          (changed
            ? '<span class="badge bg-warning-subtle text-warning">Changed</span>'
            : '<span class="badge bg-success-subtle text-success">No change</span>') +
          "</td>" +
          "</tr>"
        );
      })
      .join("");

    if (meta) {
      meta.textContent = "Dominant shift preview: " + String(changedCount) + " perubahan dari " + String(rows.length) + " user scope draft.";
    }
  }

  function plannerHolidayDateMap() {
    var map = {};
    (Array.isArray(getScheduleHolidayRowsCache()) ? getScheduleHolidayRowsCache() : []).forEach(function (holiday) {
      if (!holiday || holiday.isActive === false) {
        return;
      }
      var iso = scheduleDateIso(holiday.holidayDate);
      if (!iso) {
        return;
      }
      if (!map[iso]) {
        map[iso] = [];
      }
      map[iso].push(String(holiday.title || "Holiday"));
    });
    return map;
  }

  function plannerTransitionKeysFromLegacyRules(rules) {
    var keys = [];
    (Array.isArray(rules) ? rules : []).forEach(function (rule) {
      var raw = String(rule || "").trim().toLowerCase();
      if (!raw) {
        return;
      }
      if (raw.indexOf(":") !== -1) {
        keys.push(raw);
        return;
      }
      var parts = raw.split("_to_");
      if (parts.length === 2) {
        keys.push(parts[0] + ":" + parts[1]);
      }
    });
    return Array.from(new Set(keys));
  }

  function plannerLegacyRulesFromTransitionKeys(keys) {
    var rules = [];
    (Array.isArray(keys) ? keys : []).forEach(function (key) {
      var parts = String(key || "").trim().toLowerCase().split(":");
      if (parts.length !== 2 || !parts[0] || !parts[1]) {
        return;
      }
      rules.push(parts[0] + "_to_" + parts[1]);
    });
    return Array.from(new Set(rules));
  }

  function renderPlannerTransitionMatrix(catalog, selectedKeys) {
    var holder = document.querySelector("[data-smart-planner-transition-matrix]");
    if (!holder) {
      return;
    }
    var rows = Array.isArray(catalog) && catalog.length ? catalog : getSmartPlannerTransitionCatalog();
    var selectedSet = new Set(Array.isArray(selectedKeys) ? selectedKeys : []);
    holder.innerHTML =
      rows
        .map(function (key) {
          var parts = String(key || "").split(":");
          var from = String(parts[0] || "").trim();
          var to = String(parts[1] || "").trim();
          if (!from || !to) {
            return "";
          }
          var checked = selectedSet.has(from + ":" + to) ? " checked" : "";
          return (
            '<label class="form-check form-check-md me-3 mb-2">' +
            '<input class="form-check-input" type="checkbox" data-smart-planner-transition-key="' + esc(from + ":" + to) + '"' + checked + ">" +
            '<span class="form-check-label text-capitalize">Block ' + esc(from) + " -> " + esc(to) + "</span>" +
            "</label>"
          );
        })
        .join("") || '<div class="text-muted small">Tidak ada transition key tersedia.</div>';
  }

  function readPlannerTransitionSelection() {
    return Array.prototype.slice
      .call(document.querySelectorAll("[data-smart-planner-transition-key]"))
      .filter(function (el) {
        return !!el.checked;
      })
      .map(function (el) {
        return String(el.getAttribute("data-smart-planner-transition-key") || "").trim().toLowerCase();
      })
      .filter(function (key) {
        return key.indexOf(":") > 0;
      });
  }

  function setPlannerSettingsFeedback(message, isError) {
    var el = document.querySelector("[data-smart-planner-settings-feedback]");
    if (!el) {
      return;
    }
    el.textContent = String(message || "");
    el.classList.remove("text-muted", "text-danger", "text-success");
    el.classList.add(isError ? "text-danger" : "text-success");
  }

  function applyPlannerSettingsToForm(form, settings) {
    if (!form || !settings || !settings.defaultRules) {
      return;
    }

    var rules = settings.defaultRules;
    var maxWorkDaysEl = form.querySelector("[data-smart-planner-max-work-days]");
    var minDaysOffEl = form.querySelector("[data-smart-planner-min-days-off]");
    var minRestEl = form.querySelector("[data-smart-planner-min-rest]");
    var maxNightEl = form.querySelector("[data-smart-planner-max-night]");
    var settingsPanel = document.querySelector("[data-smart-planner-settings-panel]");
    var panelMaxWorkDaysEl = settingsPanel ? settingsPanel.querySelector("[data-smart-planner-default-max-work-days]") : null;
    var panelMinDaysOffEl = settingsPanel ? settingsPanel.querySelector("[data-smart-planner-default-min-days-off]") : null;
    var panelMinRestEl = settingsPanel ? settingsPanel.querySelector("[data-smart-planner-default-min-rest]") : null;
    var panelMaxNightEl = settingsPanel ? settingsPanel.querySelector("[data-smart-planner-default-max-night]") : null;

    if (maxWorkDaysEl && rules.max_work_days_per_week != null) {
      maxWorkDaysEl.value = String(rules.max_work_days_per_week);
    }
    if (panelMaxWorkDaysEl && rules.max_work_days_per_week != null) {
      panelMaxWorkDaysEl.value = String(rules.max_work_days_per_week);
    }
    if (minDaysOffEl && rules.min_days_off_per_week != null) {
      minDaysOffEl.value = String(rules.min_days_off_per_week);
    }
    if (panelMinDaysOffEl && rules.min_days_off_per_week != null) {
      panelMinDaysOffEl.value = String(rules.min_days_off_per_week);
    }
    if (minRestEl && rules.min_rest_hours_between_shifts != null) {
      minRestEl.value = String(rules.min_rest_hours_between_shifts);
    }
    if (panelMinRestEl && rules.min_rest_hours_between_shifts != null) {
      panelMinRestEl.value = String(rules.min_rest_hours_between_shifts);
    }
    if (maxNightEl && rules.max_consecutive_night_shifts != null) {
      maxNightEl.value = String(rules.max_consecutive_night_shifts);
    }
    if (panelMaxNightEl && rules.max_consecutive_night_shifts != null) {
      panelMaxNightEl.value = String(rules.max_consecutive_night_shifts);
    }
  }

  function analyzePlannerConflicts(result, payload) {
    var rules = payload && payload.rules ? payload.rules : {};
    var minRest = parseInt(String(rules.min_rest_hours_between_shifts || 12), 10);
    if (!Number.isFinite(minRest) || minRest < 1) {
      minRest = 12;
    }
    var illegalTransitions = Array.isArray(rules.illegal_transition_rules) ? rules.illegal_transition_rules : [];
    var transitionKeys = plannerTransitionKeysFromLegacyRules(illegalTransitions);
    var blockedTransitionSet = new Set(
      Array.isArray(transitionKeys)
        ? transitionKeys
            .map(function (key) {
              return String(key || "").toLowerCase();
            })
            .filter(function (key) {
              return key.indexOf(":") > 0;
            })
        : []
    );

    var holidayMap = plannerHolidayDateMap();
    var weeklySchedule = result && result.schedule_generation && Array.isArray(result.schedule_generation.weekly_schedule)
      ? result.schedule_generation.weekly_schedule
      : [];

    var violationRows = result && result.schedule_generation && Array.isArray(result.schedule_generation.violations)
      ? result.schedule_generation.violations
      : [];
    var unmetRows = result && result.schedule_generation && Array.isArray(result.schedule_generation.unmet_coverage)
      ? result.schedule_generation.unmet_coverage
      : [];

    var holidayConflictCount = 0;
    var restConflictCount = 0;
    var transitionConflictCount = 0;

    weeklySchedule.forEach(function (row) {
      var assignments = (Array.isArray(row && row.assignments) ? row.assignments : [])
        .slice()
        .sort(function (a, b) {
          return String((a && a.date) || "").localeCompare(String((b && b.date) || ""));
        });

      assignments.forEach(function (assignment) {
        if (String((assignment && assignment.shift_id) || "").toUpperCase() === "OFF") {
          return;
        }
        var dateIso = scheduleDateIso(assignment && assignment.date);
        if (dateIso && holidayMap[dateIso] && holidayMap[dateIso].length > 0) {
          holidayConflictCount += 1;
        }
      });

      for (var i = 0; i < assignments.length - 1; i++) {
        var current = assignments[i];
        var next = assignments[i + 1];
        var currentShiftId = String((current && current.shift_id) || "").toUpperCase();
        var nextShiftId = String((next && next.shift_id) || "").toUpperCase();
        if (currentShiftId === "OFF" || nextShiftId === "OFF") {
          continue;
        }
        var currentStart = scheduleEventDateTime(current && current.date, current && current.start_time);
        var currentEnd = scheduleEventDateTime(current && current.date, current && current.end_time);
        var nextStart = scheduleEventDateTime(next && next.date, next && next.start_time);

        if (currentEnd && currentStart && currentEnd <= currentStart) {
          var currentEndDate = new Date(currentEnd);
          if (!isNaN(currentEndDate.getTime())) {
            currentEndDate.setDate(currentEndDate.getDate() + 1);
            currentEnd = currentEndDate.toISOString().slice(0, 19);
          }
        }

        var endDateObj = currentEnd ? new Date(currentEnd) : null;
        var startDateObj = nextStart ? new Date(nextStart) : null;
        if (endDateObj && startDateObj && !isNaN(endDateObj.getTime()) && !isNaN(startDateObj.getTime())) {
          var diffHours = (startDateObj.getTime() - endDateObj.getTime()) / (60 * 60 * 1000);
          if (diffHours < minRest) {
            restConflictCount += 1;
          }
        }

        var curMeta = plannerShiftMeta(current);
        var nextMeta = plannerShiftMeta(next);
        var curType = curMeta.label === "N" ? "night" : curMeta.label === "A" ? "afternoon" : curMeta.label === "M" ? "morning" : "";
        var nextType = nextMeta.label === "N" ? "night" : nextMeta.label === "A" ? "afternoon" : nextMeta.label === "M" ? "morning" : "";
        if (curType && nextType && blockedTransitionSet.has(curType + ":" + nextType)) {
          transitionConflictCount += 1;
        }
      }
    });

    return {
      violationCount: violationRows.length,
      unmetCoverageCount: unmetRows.length,
      holidayConflictCount: holidayConflictCount,
      restConflictCount: restConflictCount,
      transitionConflictCount: transitionConflictCount,
      criticalCount: violationRows.length + unmetRows.length + restConflictCount + transitionConflictCount,
    };
  }

  function renderPlannerConflictPreview(result, payload) {
    var meta = document.querySelector("[data-smart-planner-conflict-meta]");
    var list = document.querySelector("[data-smart-planner-conflict-list]");
    if (!list) {
      return getSmartPlannerConflictSummary();
    }

    var summary = analyzePlannerConflicts(result, payload || {});
    setSmartPlannerConflictSummary({
      total:
        summary.violationCount +
        summary.unmetCoverageCount +
        summary.holidayConflictCount +
        summary.restConflictCount +
        summary.transitionConflictCount,
      critical: summary.criticalCount,
    });

    var conflictRows = [
      {
        count: summary.violationCount,
        critical: true,
        label: "Pelanggaran aturan jadwal (hard violation)",
        okMsg: "Tidak ada pelanggaran aturan — jadwal sesuai rule.",
        failMsg: function (n) {
          return n + " pelanggaran aturan terdeteksi (mis. max shift malam, min hari libur, urutan shift). " +
            "Publish dalam kondisi ini berisiko — kembali ke panel Violations untuk lihat detail dan perbaiki sebelum publish.";
        },
      },
      {
        count: summary.unmetCoverageCount,
        critical: true,
        label: "Slot shift tidak terpenuhi (unmet coverage)",
        okMsg: "Semua slot shift berhasil diisi oleh karyawan.",
        failMsg: function (n) {
          return n + " slot shift tidak bisa diisi karena karyawan dalam scope tidak cukup. " +
            "Tambah karyawan ke scope generate atau naikkan Max Work Days, lalu generate ulang.";
        },
      },
      {
        count: summary.holidayConflictCount,
        critical: false,
        label: "Shift dijadwalkan di hari libur nasional",
        okMsg: "Tidak ada shift yang bertabrakan dengan hari libur nasional.",
        failMsg: function (n) {
          return n + " karyawan dijadwalkan masuk di hari libur nasional. " +
            "Review kebijakan perusahaan (apakah lembur hari libur diizinkan?) sebelum publish. " +
            "Jika tidak diizinkan, hapus atau ubah assignment tersebut secara manual.";
        },
      },
      {
        count: summary.restConflictCount,
        critical: true,
        label: "Jeda istirahat antar shift kurang dari minimum",
        okMsg: "Semua pergantian shift sudah memenuhi minimum jeda istirahat.",
        failMsg: function (n) {
          return n + " pergantian shift memiliki jeda kurang dari batas minimum yang diatur (mis. shift malam selesai jam 06.00, langsung masuk pagi jam 07.00). " +
            "Ini berisiko kelelahan — naikkan nilai Min Rest Hours atau kurangi giliran shift berat berturut-turut.";
        },
      },
      {
        count: summary.transitionConflictCount,
        critical: true,
        label: "Urutan shift dilarang (illegal transition)",
        okMsg: "Semua urutan pergantian shift valid sesuai aturan transisi.",
        failMsg: function (n) {
          return n + " pergantian shift melanggar aturan transisi yang diset (mis. shift malam langsung ke shift pagi keesokan harinya). " +
            "Periksa setting Illegal Transition Rules dan sesuaikan jadwal agar transisi tidak berisiko.";
        },
      },
    ];

    list.innerHTML = conflictRows.map(function (row) {
      var isOk = row.count === 0;
      var icon = isOk ? "✅" : (row.critical ? "🔴" : "🟡");
      var text = isOk ? row.okMsg : row.failMsg(row.count);
      return "<li>" + esc(icon + " " + row.label + ": " + text) + "</li>";
    }).join("");

    if (meta) {
      meta.textContent =
        summary.criticalCount > 0
          ? "Terdeteksi " + String(summary.criticalCount) + " conflict kritikal. Centang Force apply jika tetap ingin publish setelah review manual."
          : "Tidak ada conflict kritikal. Jadwal aman untuk dipublish.";
    }

    return getSmartPlannerConflictSummary();
  }

  function updatePlannerApplyState(result) {
    var applyBtn = document.querySelector("[data-smart-planner-apply-dominant]");
    var applyDailyBtn = document.querySelector("[data-smart-planner-apply-daily]");
    var applyMeta = document.querySelector("[data-smart-planner-apply-meta]");
    var forceApplyEl = document.querySelector("[data-smart-planner-force-apply]");
    if ((!applyBtn && !applyDailyBtn) || !applyMeta) {
      return;
    }

    var rows = plannerDominantShiftRows(result);
    var hasDraftAssignments = !!(
      result &&
      result.schedule_generation &&
      Array.isArray(result.schedule_generation.weekly_schedule) &&
      result.schedule_generation.weekly_schedule.length
    );
    if (!rows.length) {
      if (applyBtn) {
        applyBtn.disabled = true;
      }
      if (applyDailyBtn) {
        applyDailyBtn.disabled = !hasDraftAssignments;
      }
      applyMeta.textContent = "Draft tidak punya shift dominan yang valid untuk dipublish.";
      return;
    }

    var hasCriticalConflict = (getSmartPlannerConflictSummary().critical || 0) > 0;
    var forceApply = !!(forceApplyEl && forceApplyEl.checked);

    if (applyBtn) {
      applyBtn.disabled = hasCriticalConflict && !forceApply;
    }
    if (applyDailyBtn) {
      applyDailyBtn.disabled = hasCriticalConflict && !forceApply;
    }
    applyMeta.textContent =
      "Siap publish " +
      String(rows.length) +
      " user (dominant shift dari draft planner terakhir), atau publish roster harian per tanggal. Hanya user dalam scope planner yang diproses." +
      (hasCriticalConflict && !forceApply ? " Conflict kritikal terdeteksi, centang Force apply jika tetap lanjut." : "");
  }

  function applyPlannerDominantShifts(result) {
    var rows = plannerDominantShiftRows(result);
    if (!rows.length) {
      return Promise.reject({ plannerMessage: "Draft tidak punya shift dominan yang valid untuk dipublish." });
    }

    var successCount = 0;
    var failed = [];

    function runNext(index) {
      if (index >= rows.length) {
        return Promise.resolve({
          total: rows.length,
          success: successCount,
          failed: failed,
        });
      }

      var row = rows[index];
      setSmartPlannerFeedback(
        "Publishing dominant shift " + String(index + 1) + "/" + String(rows.length) + " untuk " + row.employeeName + "...",
        false
      );

      return apiPut("/v1/hcm/schedule-timing/" + encodeURIComponent(String(row.userId)), {
        shiftId: row.shiftId,
      })
        .then(function (response) {
          if (!response || response.success !== true) {
            failed.push({
              userId: row.userId,
              employeeName: row.employeeName,
              reason: formatApiError(response, 0) || "Unknown error",
            });
          } else {
            successCount += 1;
          }
          return runNext(index + 1);
        })
        .catch(function (err) {
          var data = err && err.response ? err.response.data : err && err.data ? err.data : null;
          var status = err && err.response ? err.response.status : err && err.status ? err.status : 0;
          failed.push({
            userId: row.userId,
            employeeName: row.employeeName,
            reason: formatApiError(data, status) || "Request failed",
          });
          return runNext(index + 1);
        });
    }

    return runNext(0);
  }

  function applyPlannerDailyRoster(result) {
    var weeklySchedule = result && result.schedule_generation && Array.isArray(result.schedule_generation.weekly_schedule)
      ? result.schedule_generation.weekly_schedule
      : [];
    if (!weeklySchedule.length) {
      return Promise.reject({ plannerMessage: "Draft planner kosong, tidak ada roster harian untuk dipublish." });
    }

    return apiPost("/v1/hcm/smart-attendance-shifting/publish-roster", {
      weeklySchedule: weeklySchedule,
    }).then(function (response) {
      if (!response || response.success !== true) {
        return Promise.reject({ plannerMessage: formatApiError(response, 0) || "Gagal publish roster harian." });
      }
      return response.data || {};
    });
  }

  function mergePlannerEmployeeSummaries(results) {
    var byUser = {};

    (Array.isArray(results) ? results : []).forEach(function (result) {
      var rows = result && result.attendance_analysis && Array.isArray(result.attendance_analysis.employee_summaries)
        ? result.attendance_analysis.employee_summaries
        : [];

      rows.forEach(function (row) {
        var userId = String((row && row.employee_id) || "");
        if (!userId) {
          return;
        }
        if (!byUser[userId]) {
          byUser[userId] = {
            employee_id: userId,
            total_work_days: 0,
            late_count: 0,
            early_leave_count: 0,
            absent_count: 0,
            overtime_minutes: 0,
            compliance_score: 0,
            _scoreCount: 0,
          };
        }

        byUser[userId].total_work_days += Number((row && row.total_work_days) || 0);
        byUser[userId].late_count += Number((row && row.late_count) || 0);
        byUser[userId].early_leave_count += Number((row && row.early_leave_count) || 0);
        byUser[userId].absent_count += Number((row && row.absent_count) || 0);
        byUser[userId].overtime_minutes += Number((row && row.overtime_minutes) || 0);
        byUser[userId].compliance_score += Number((row && row.compliance_score) || 0);
        byUser[userId]._scoreCount += 1;
      });
    });

    return Object.keys(byUser)
      .map(function (userId) {
        var item = byUser[userId];
        var scoreCount = item._scoreCount || 1;
        item.compliance_score = Number((item.compliance_score / scoreCount).toFixed(2));
        delete item._scoreCount;
        return item;
      })
      .sort(function (a, b) {
        return String(a.employee_id || "").localeCompare(String(b.employee_id || ""));
      });
  }

  function mergePlannerSuggestions(results) {
    var seen = {};
    var output = [];

    (Array.isArray(results) ? results : []).forEach(function (result) {
      var rows = result && result.recommendation && Array.isArray(result.recommendation.improvement_suggestions)
        ? result.recommendation.improvement_suggestions
        : [];

      rows.forEach(function (row) {
        var title = String((row && row.title) || "Suggestion");
        var reason = String((row && row.reason) || "");
        var key = (title + "|" + reason).toLowerCase();
        if (seen[key]) {
          return;
        }
        seen[key] = true;
        output.push(row);
      });
    });

    return output;
  }

  function combinePlannerResults(results) {
    var safeResults = Array.isArray(results) ? results : [];
    var weeklySchedule = mergePlannerAssignmentRows(safeResults);
    var summaries = mergePlannerEmployeeSummaries(safeResults);
    var flags = [];
    var violations = [];
    var unmet = [];
    var fairnessTotal = 0;
    var fatigueTotal = 0;
    var scoreCount = 0;
    var hasInvalid = false;

    safeResults.forEach(function (result) {
      var schedule = result && result.schedule_generation ? result.schedule_generation : {};
      var attendance = result && result.attendance_analysis ? result.attendance_analysis : {};
      var recommendation = result && result.recommendation ? result.recommendation : {};

      if (String(schedule.validation_status || "").toLowerCase() === "invalid") {
        hasInvalid = true;
      }

      if (Array.isArray(schedule.violations)) {
        violations = violations.concat(schedule.violations);
      }
      if (Array.isArray(schedule.unmet_coverage)) {
        unmet = unmet.concat(schedule.unmet_coverage);
      }
      if (Array.isArray(attendance.flags)) {
        flags = flags.concat(attendance.flags);
      }

      if (recommendation.fairness_score != null && !isNaN(Number(recommendation.fairness_score))) {
        fairnessTotal += Number(recommendation.fairness_score);
        scoreCount += 1;
      }
      if (recommendation.fatigue_risk_score != null && !isNaN(Number(recommendation.fatigue_risk_score))) {
        fatigueTotal += Number(recommendation.fatigue_risk_score);
      }
    });

    var fairnessScore = scoreCount > 0 ? Number((fairnessTotal / scoreCount).toFixed(2)) : null;
    var fatigueScore = scoreCount > 0 ? Number((fatigueTotal / scoreCount).toFixed(2)) : null;
    var totalWeeks = safeResults.length;

    return {
      schedule_generation: {
        validation_status: hasInvalid || violations.length > 0 || unmet.length > 0 ? "invalid" : "valid",
        weekly_schedule: weeklySchedule,
        violations: violations,
        unmet_coverage: unmet,
      },
      attendance_analysis: {
        employee_summaries: summaries,
        flags: flags,
      },
      recommendation: {
        fairness_score: fairnessScore,
        fatigue_risk_score: fatigueScore,
        improvement_suggestions: mergePlannerSuggestions(safeResults),
      },
      explanation:
        totalWeeks > 1
          ? "Batch planner selesai untuk " +
            String(totalWeeks) +
            " minggu sampai akhir tahun. Hasil ringkas ditampilkan sebagai agregasi lintas minggu."
          : String((safeResults[0] && safeResults[0].explanation) || "Schedule generated successfully."),
    };
  }

  function executePlannerBatchRequests(basePayload, weekStarts, onProgress) {
    var results = [];

    function runNext(index) {
      if (index >= weekStarts.length) {
        return Promise.resolve(results);
      }

      var payload = JSON.parse(JSON.stringify(basePayload || {}));
      payload.weekStart = weekStarts[index];
      if (typeof onProgress === "function") {
        onProgress(index, weekStarts.length, payload.weekStart);
      }

      return apiPost("/v1/hcm/smart-attendance-shifting/generate", payload).then(function (response) {
        if (!response || response.success !== true || !response.data) {
          var errorText = formatApiError(response, 0) || "Gagal generate smart planner.";
          return Promise.reject({ plannerMessage: errorText });
        }
        results.push(response.data);
        return runNext(index + 1);
      });
    }

    return runNext(0);
  }

  return {
    plannerLegacyRulesFromTransitionKeys: plannerLegacyRulesFromTransitionKeys,
    renderPlannerTransitionMatrix: renderPlannerTransitionMatrix,
    readPlannerTransitionSelection: readPlannerTransitionSelection,
    setPlannerSettingsFeedback: setPlannerSettingsFeedback,
    applyPlannerSettingsToForm: applyPlannerSettingsToForm,
    renderPlannerDiffPreview: renderPlannerDiffPreview,
    renderPlannerConflictPreview: renderPlannerConflictPreview,
    updatePlannerApplyState: updatePlannerApplyState,
    applyPlannerDominantShifts: applyPlannerDominantShifts,
    applyPlannerDailyRoster: applyPlannerDailyRoster,
    combinePlannerResults: combinePlannerResults,
    executePlannerBatchRequests: executePlannerBatchRequests,
  };
}