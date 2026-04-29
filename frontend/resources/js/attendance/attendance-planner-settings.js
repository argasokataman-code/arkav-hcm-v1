export function bindPlannerSettingsPanel(deps) {
  var form = deps.form;
  var settingsPanel = deps.settingsPanel;
  var saveSettingsBtn = deps.saveSettingsBtn;
  var submitBtn = deps.submitBtn;
  var applyDominantBtn = deps.applyDominantBtn;
  var applyDailyBtn = deps.applyDailyBtn;
  var forceApplyEl = deps.forceApplyEl;
  var readPlannerTransitionSelection = deps.readPlannerTransitionSelection;
  var renderPlannerTransitionMatrix = deps.renderPlannerTransitionMatrix;
  var applyPlannerSettingsToForm = deps.applyPlannerSettingsToForm;
  var setPlannerSettingsFeedback = deps.setPlannerSettingsFeedback;
  var setSmartPlannerFeedback = deps.setSmartPlannerFeedback;
  var loadPlannerSettings = deps.loadPlannerSettings;
  var updatePlannerApplyState = deps.updatePlannerApplyState;
  var notify = deps.notify;
  var apiPut = deps.apiPut;
  var formatApiError = deps.formatApiError;
  var readInt = deps.readInt;

  var getSmartPlannerSettingsCache = deps.getSmartPlannerSettingsCache;
  var setSmartPlannerSettingsCache = deps.setSmartPlannerSettingsCache;
  var getSmartPlannerTransitionCatalog = deps.getSmartPlannerTransitionCatalog;
  var getSmartPlannerForbiddenTransitionKeys = deps.getSmartPlannerForbiddenTransitionKeys;
  var setSmartPlannerForbiddenTransitionKeys = deps.setSmartPlannerForbiddenTransitionKeys;
  var getSmartPlannerEditModeOriginalValues = deps.getSmartPlannerEditModeOriginalValues;
  var setSmartPlannerEditModeOriginalValues = deps.setSmartPlannerEditModeOriginalValues;
  var setSmartPlannerEditMode = deps.setSmartPlannerEditMode;
  var getSmartPlannerLastResult = deps.getSmartPlannerLastResult;

  var editModeBtn = settingsPanel ? settingsPanel.querySelector("[data-smart-planner-edit-mode-btn]") : null;
  var cancelEditBtn = settingsPanel ? settingsPanel.querySelector("[data-smart-planner-cancel-edit-btn]") : null;
  var resetDefaultsBtn = settingsPanel ? settingsPanel.querySelector("[data-smart-planner-reset-defaults-btn]") : null;
  var modeIndicator = settingsPanel ? settingsPanel.querySelector("[data-smart-planner-mode-indicator]") : null;
  var settingsPanelMaxWorkDays = settingsPanel ? settingsPanel.querySelector("[data-smart-planner-default-max-work-days]") : null;
  var settingsPanelMinDaysOff = settingsPanel ? settingsPanel.querySelector("[data-smart-planner-default-min-days-off]") : null;
  var settingsPanelMinRest = settingsPanel ? settingsPanel.querySelector("[data-smart-planner-default-min-rest]") : null;
  var settingsPanelMaxNight = settingsPanel ? settingsPanel.querySelector("[data-smart-planner-default-max-night]") : null;

  function storeDefaultInputValues() {
    setSmartPlannerEditModeOriginalValues({
      maxWorkDays: settingsPanelMaxWorkDays && settingsPanelMaxWorkDays.value ? String(settingsPanelMaxWorkDays.value) : "5",
      minDaysOff: settingsPanelMinDaysOff && settingsPanelMinDaysOff.value ? String(settingsPanelMinDaysOff.value) : "2",
      minRest: settingsPanelMinRest && settingsPanelMinRest.value ? String(settingsPanelMinRest.value) : "12",
      maxNight: settingsPanelMaxNight && settingsPanelMaxNight.value ? String(settingsPanelMaxNight.value) : "3",
      transitions: readPlannerTransitionSelection().slice(),
    });
  }

  function setEditMode(enabled) {
    setSmartPlannerEditMode(!!enabled);
    var defaults = [settingsPanelMaxWorkDays, settingsPanelMinDaysOff, settingsPanelMinRest, settingsPanelMaxNight];
    var matrixCheckboxes = settingsPanel ? Array.prototype.slice.call(settingsPanel.querySelectorAll("[data-smart-planner-transition-key]")) : [];

    if (enabled) {
      storeDefaultInputValues();
      defaults.forEach(function (el) {
        if (el) {
          el.disabled = false;
        }
      });
      matrixCheckboxes.forEach(function (el) {
        el.disabled = false;
      });
      if (editModeBtn) editModeBtn.classList.add("d-none");
      if (cancelEditBtn) cancelEditBtn.classList.remove("d-none");
      if (saveSettingsBtn) saveSettingsBtn.classList.remove("d-none");
      if (resetDefaultsBtn) resetDefaultsBtn.classList.remove("d-none");
      if (modeIndicator) {
        modeIndicator.textContent = "Edit mode";
        modeIndicator.className = "badge bg-warning text-dark border";
      }
      if (submitBtn) submitBtn.disabled = true;
      if (applyDominantBtn) applyDominantBtn.disabled = true;
      if (applyDailyBtn) applyDailyBtn.disabled = true;
    } else {
      defaults.forEach(function (el) {
        if (el) {
          el.disabled = true;
        }
      });
      matrixCheckboxes.forEach(function (el) {
        el.disabled = true;
      });
      if (editModeBtn) editModeBtn.classList.remove("d-none");
      if (cancelEditBtn) cancelEditBtn.classList.add("d-none");
      if (saveSettingsBtn) saveSettingsBtn.classList.add("d-none");
      if (resetDefaultsBtn) resetDefaultsBtn.classList.add("d-none");
      if (modeIndicator) {
        modeIndicator.textContent = "View mode";
        modeIndicator.className = "badge bg-white text-dark border";
      }
      if (submitBtn) submitBtn.disabled = false;
      if (applyDominantBtn) applyDominantBtn.disabled = false;
      if (applyDailyBtn) applyDailyBtn.disabled = false;
    }
  }

  function restoreOriginalValues() {
    var original = getSmartPlannerEditModeOriginalValues() || {};
    if (settingsPanelMaxWorkDays && original.maxWorkDays) {
      settingsPanelMaxWorkDays.value = original.maxWorkDays;
    }
    if (settingsPanelMinDaysOff && original.minDaysOff) {
      settingsPanelMinDaysOff.value = original.minDaysOff;
    }
    if (settingsPanelMinRest && original.minRest) {
      settingsPanelMinRest.value = original.minRest;
    }
    if (settingsPanelMaxNight && original.maxNight) {
      settingsPanelMaxNight.value = original.maxNight;
    }
    renderPlannerTransitionMatrix(getSmartPlannerTransitionCatalog(), original.transitions || getSmartPlannerForbiddenTransitionKeys());
  }

  if (settingsPanel) {
    if (editModeBtn && editModeBtn.getAttribute("data-bound") !== "1") {
      editModeBtn.setAttribute("data-bound", "1");
      editModeBtn.addEventListener("click", function () {
        setEditMode(true);
      });
    }

    if (cancelEditBtn && cancelEditBtn.getAttribute("data-bound") !== "1") {
      cancelEditBtn.setAttribute("data-bound", "1");
      cancelEditBtn.addEventListener("click", function () {
        restoreOriginalValues();
        setEditMode(false);
      });
    }

    if (resetDefaultsBtn && resetDefaultsBtn.getAttribute("data-bound") !== "1") {
      resetDefaultsBtn.setAttribute("data-bound", "1");
      resetDefaultsBtn.addEventListener("click", function () {
        if (window.confirm("Reset semua ke default tenant yang tersimpan? Perubahan belum disimpan akan hilang.")) {
          loadPlannerSettings().then(function () {
            setPlannerSettingsFeedback("Default tenant berhasil di-reset.", false);
            setEditMode(true);
          });
        }
      });
    }

    if (saveSettingsBtn && saveSettingsBtn.getAttribute("data-bound") !== "1") {
      saveSettingsBtn.setAttribute("data-bound", "1");
      saveSettingsBtn.addEventListener("click", function () {
      var cache = getSmartPlannerSettingsCache();
      var payload = {
        defaultRules: {
          max_work_days_per_week: readInt(settingsPanelMaxWorkDays, 5),
          min_days_off_per_week: readInt(settingsPanelMinDaysOff, 2),
          min_rest_hours_between_shifts: readInt(settingsPanelMinRest, 12),
          max_consecutive_night_shifts: readInt(settingsPanelMaxNight, 3),
          late_tolerance_minutes:
            cache && cache.defaultRules && cache.defaultRules.late_tolerance_minutes != null
              ? Number(cache.defaultRules.late_tolerance_minutes)
              : 5,
          early_leave_tolerance_minutes:
            cache && cache.defaultRules && cache.defaultRules.early_leave_tolerance_minutes != null
              ? Number(cache.defaultRules.early_leave_tolerance_minutes)
              : 5,
          overtime_threshold_minutes:
            cache && cache.defaultRules && cache.defaultRules.overtime_threshold_minutes != null
              ? Number(cache.defaultRules.overtime_threshold_minutes)
              : 30,
        },
        forbiddenTransitions: readPlannerTransitionSelection(),
      };

      saveSettingsBtn.disabled = true;
      setPlannerSettingsFeedback("Menyimpan default tenant...", false);

      apiPut("/v1/hcm/smart-attendance-shifting/settings", payload)
        .then(function (response) {
          if (!response || response.success !== true || !response.data) {
            setPlannerSettingsFeedback(formatApiError(response, 0) || "Gagal simpan planner defaults.", true);
            return;
          }
          setSmartPlannerSettingsCache({
            defaultRules: response.data.defaultRules || payload.defaultRules,
            forbiddenTransitions: response.data.forbiddenTransitions || payload.forbiddenTransitions,
            transitionCatalog: getSmartPlannerTransitionCatalog(),
          });
          applyPlannerSettingsToForm(form, getSmartPlannerSettingsCache());
          setSmartPlannerForbiddenTransitionKeys(
            Array.isArray(response.data.forbiddenTransitions) ? response.data.forbiddenTransitions : payload.forbiddenTransitions
          );
          renderPlannerTransitionMatrix(getSmartPlannerTransitionCatalog(), getSmartPlannerForbiddenTransitionKeys());
          setPlannerSettingsFeedback("Default tenant planner berhasil disimpan.", false);
          notify("Planner defaults tersimpan.", false);
          setEditMode(false);
        })
        .catch(function (err) {
          var data = err && err.response ? err.response.data : err && err.data ? err.data : null;
          var status = err && err.response ? err.response.status : err && err.status ? err.status : 0;
          setPlannerSettingsFeedback(formatApiError(data, status) || "Gagal simpan planner defaults.", true);
        })
        .finally(function () {
          saveSettingsBtn.disabled = false;
        });
      });
    }

    setEditMode(false);
  }

  if (forceApplyEl && forceApplyEl.getAttribute("data-bound") !== "1") {
    forceApplyEl.setAttribute("data-bound", "1");
    forceApplyEl.addEventListener("change", function () {
      updatePlannerApplyState(getSmartPlannerLastResult());
      setSmartPlannerFeedback("Mode force apply diperbarui.", false);
    });
  }
}
