export function bindPlannerSubmitHandler(deps) {
  var form = deps.form;
  var submitBtn = deps.submitBtn;
  var weekStartEl = deps.weekStartEl;
  var endDateEl = deps.endDateEl;
  var maxWorkDaysEl = deps.maxWorkDaysEl;
  var minDaysOffEl = deps.minDaysOffEl;
  var minRestEl = deps.minRestEl;
  var maxNightEl = deps.maxNightEl;
  var scopeMetaEl = deps.scopeMetaEl;

  var currentCategory = deps.currentCategory;
  var currentHorizon = deps.currentHorizon;
  var getCurrentWeekStartIso = deps.getCurrentWeekStartIso;
  var readInt = deps.readInt;
  var readPlannerTransitionSelection = deps.readPlannerTransitionSelection;
  var plannerLegacyRulesFromTransitionKeys = deps.plannerLegacyRulesFromTransitionKeys;
  var resolvePlannerScope = deps.resolvePlannerScope;
  var plannerEndOfYearIso = deps.plannerEndOfYearIso;
  var plannerBuildWeekStarts = deps.plannerBuildWeekStarts;
  var executePlannerBatchRequests = deps.executePlannerBatchRequests;
  var combinePlannerResults = deps.combinePlannerResults;
  var apiPost = deps.apiPost;
  var setSmartPlannerFeedback = deps.setSmartPlannerFeedback;
  var formatApiError = deps.formatApiError;
  var renderSmartPlannerResult = deps.renderSmartPlannerResult;
  var updatePlannerApplyState = deps.updatePlannerApplyState;
  var notify = deps.notify;

  var getSmartPlannerForbiddenTransitionKeys = deps.getSmartPlannerForbiddenTransitionKeys;
  var setSmartPlannerForbiddenTransitionKeys = deps.setSmartPlannerForbiddenTransitionKeys;
  var getSmartPlannerLastPayload = deps.getSmartPlannerLastPayload;
  var setSmartPlannerLastPayload = deps.setSmartPlannerLastPayload;
  var setSmartPlannerScopeMeta = deps.setSmartPlannerScopeMeta;
  var setSmartPlannerLastResult = deps.setSmartPlannerLastResult;

  function fallbackUpdateApplyStateFromResult(result) {
    var applyBtn = document.querySelector("[data-smart-planner-apply-dominant]");
    var applyDailyBtn = document.querySelector("[data-smart-planner-apply-daily]");
    var applyMeta = document.querySelector("[data-smart-planner-apply-meta]");
    if (!applyBtn && !applyDailyBtn) {
      return;
    }

    var schedule = result && result.schedule_generation ? result.schedule_generation : {};
    var weekly = Array.isArray(schedule.weekly_schedule) ? schedule.weekly_schedule : [];
    var hasDraftAssignments = weekly.length > 0;
    var hasCritical =
      String(schedule.validation_status || "").toLowerCase() === "invalid" ||
      (Array.isArray(schedule.violations) && schedule.violations.length > 0) ||
      (Array.isArray(schedule.unmet_coverage) && schedule.unmet_coverage.length > 0);

    if (applyBtn) {
      applyBtn.disabled = !hasDraftAssignments || hasCritical;
    }
    if (applyDailyBtn) {
      applyDailyBtn.disabled = !hasDraftAssignments || hasCritical;
    }
    if (applyMeta) {
      applyMeta.textContent = hasCritical
        ? "Conflict kritikal terdeteksi. Aktifkan Force apply untuk melanjutkan publish."
        : "Draft planner siap dipublish.";
    }
  }

  if (!form) {
    return;
  }

  form.addEventListener("submit", function (e) {
    e.preventDefault();
    if (submitBtn) {
      submitBtn.disabled = true;
      var lbl = submitBtn.querySelector('[data-smart-planner-submit-label]');
      var spin = submitBtn.querySelector('[data-smart-planner-spinner]');
      if (lbl) lbl.textContent = 'Generating...';
      if (spin) spin.classList.remove('d-none');
    }
    var inlineFeedback = document.querySelector('[data-smart-planner-feedback-inline]');
    if (inlineFeedback) { inlineFeedback.classList.add('d-none'); inlineFeedback.textContent = ''; }
    setSmartPlannerFeedback("Mempersiapkan scope karyawan...", false);

    var mode = currentCategory();
    var isOffice = mode === "office_hour";
    var horizon = currentHorizon();

    var payload = {
      shiftCategory: mode,
      weekStart: weekStartEl && weekStartEl.value ? String(weekStartEl.value) : getCurrentWeekStartIso(),
      rules: {
        max_work_days_per_week: readInt(maxWorkDaysEl, 5),
        min_days_off_per_week: readInt(minDaysOffEl, 2),
        min_rest_hours_between_shifts: readInt(minRestEl, 12),
        max_consecutive_night_shifts: isOffice ? 1 : readInt(maxNightEl, 3),
        illegal_transition_rules: isOffice ? [] : plannerLegacyRulesFromTransitionKeys(readPlannerTransitionSelection()),
      },
    };

    setSmartPlannerForbiddenTransitionKeys(readPlannerTransitionSelection());
    if (!isOffice && payload.rules.illegal_transition_rules.length === 0) {
      var selectedTransitions = getSmartPlannerForbiddenTransitionKeys();
      payload.rules.illegal_transition_rules = plannerLegacyRulesFromTransitionKeys(
        selectedTransitions.length ? selectedTransitions : ["night:morning"]
      );
    }

    setSmartPlannerLastPayload(JSON.parse(JSON.stringify(payload)));

    resolvePlannerScope()
      .then(function (scopeInfo) {
        if (Array.isArray(scopeInfo.employeeIds) && scopeInfo.employeeIds.length > 0) {
          payload.employeeIds = scopeInfo.employeeIds;
          var snapshot = getSmartPlannerLastPayload() || {};
          snapshot.employeeIds = scopeInfo.employeeIds.slice();
          setSmartPlannerLastPayload(snapshot);
        }
        if (scopeMetaEl) {
          scopeMetaEl.textContent = scopeInfo.message;
        }
        setSmartPlannerScopeMeta(String(scopeInfo.message || ""));

        var employeeCountText = Array.isArray(scopeInfo.employeeIds) ? String(scopeInfo.employeeIds.length) : "default";

        if (horizon === "end_of_year") {
          var endIso = plannerEndOfYearIso(payload.weekStart);
          if (endDateEl) {
            endDateEl.value = endIso;
          }
          var weekStarts = plannerBuildWeekStarts(payload.weekStart, endIso);
          if (!weekStarts.length) {
            return Promise.reject({ plannerMessage: "Rentang batch planner tidak valid. Cek Week Start." });
          }
          setSmartPlannerFeedback(
            "Generating batch planner " + String(weekStarts.length) + " minggu untuk " + employeeCountText + " karyawan...",
            false
          );
          return executePlannerBatchRequests(payload, weekStarts, function (index, total, weekIso) {
            setSmartPlannerFeedback(
              "Memproses minggu " + String(index + 1) + "/" + String(total) + " (" + String(weekIso) + ")...",
              false
            );
          }).then(function (batchResults) {
            return {
              success: true,
              data: combinePlannerResults(batchResults),
              meta: {
                batchWeeks: weekStarts.length,
                horizon: "end_of_year",
                endDate: endIso,
              },
            };
          });
        }

        setSmartPlannerFeedback("Generating smart planner untuk " + employeeCountText + " karyawan...", false);
        return apiPost("/v1/hcm/smart-attendance-shifting/generate", payload).then(function (singleResponse) {
          if (!singleResponse || singleResponse.success !== true || !singleResponse.data) {
            return singleResponse;
          }
          return {
            success: true,
            data: singleResponse.data,
            meta: {
              batchWeeks: 1,
              horizon: "single_week",
              endDate: payload.weekStart,
            },
          };
        });
      })
      .then(function (response) {
        if (!response || response.success !== true || !response.data) {
          setSmartPlannerFeedback(formatApiError(response, 0) || "Gagal generate smart planner.", true);
          return;
        }
        setSmartPlannerLastResult(response.data);
        var renderFailed = false;
        try {
          renderSmartPlannerResult(response.data);
        } catch (renderError) {
          renderFailed = true;
          setSmartPlannerFeedback("Planner berhasil digenerate, tetapi render UI gagal. Coba refresh halaman.", true);
        }
        if (!renderFailed && typeof updatePlannerApplyState === "function") {
          updatePlannerApplyState(response.data);
        } else if (renderFailed) {
          fallbackUpdateApplyStateFromResult(response.data);
        }
        if (response.meta && response.meta.horizon === "end_of_year") {
          setSmartPlannerFeedback(
            "Batch planner berhasil digenerate sampai " +
              String(response.meta.endDate || "akhir tahun") +
              " (" +
              String(response.meta.batchWeeks || 0) +
              " minggu).",
            false
          );
        } else {
          var empCount = Array.isArray(payload.employeeIds) ? payload.employeeIds.length : 0;
          var deptEl = document.querySelector('[data-smart-planner-field="department"] select, [data-smart-planner-scope="department"]');
          if (!deptEl) deptEl = document.querySelector('[data-bs-smart-planner-department]');
          var deptLabel = deptEl && deptEl.selectedOptions && deptEl.selectedOptions[0]
            ? String(deptEl.selectedOptions[0].textContent || '').trim()
            : '';
          var successMsg = "Smart planner berhasil digenerate";
          if (empCount > 0) successMsg += " untuk " + String(empCount) + " karyawan";
          if (deptLabel && deptLabel !== 'Pilih departemen') successMsg += ", departemen " + deptLabel;
          successMsg += ", periode minggu " + String((weekStartEl && weekStartEl.value) || payload.weekStart || '-') + ".";
          setSmartPlannerFeedback(successMsg, false);
        }
        notify("Smart planner siap direview.", false);
      })
      .catch(function (err) {
        if (err && err.plannerMessage) {
          setSmartPlannerFeedback(String(err.plannerMessage), true);
          var inlineFb = document.querySelector('[data-smart-planner-feedback-inline]');
          if (inlineFb) {
            inlineFb.textContent = String(err.plannerMessage);
            inlineFb.classList.remove('d-none');
          }
          return;
        }
        var data = err && err.response ? err.response.data : err && err.data ? err.data : null;
        var status = err && err.response ? err.response.status : err && err.status ? err.status : 0;
        setSmartPlannerFeedback(formatApiError(data, status) || "Gagal generate smart planner.", true);
      })
      .finally(function () {
        if (submitBtn) {
          submitBtn.disabled = false;
          var lbl = submitBtn.querySelector('[data-smart-planner-submit-label]');
          var spin = submitBtn.querySelector('[data-smart-planner-spinner]');
          if (lbl) lbl.textContent = 'Generate';
          if (spin) spin.classList.add('d-none');
        }
      });
  });
}
