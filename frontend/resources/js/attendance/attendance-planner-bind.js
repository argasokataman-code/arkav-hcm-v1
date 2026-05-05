export function bindSmartPlannerModule(deps) {
  var ensureScheduleShiftsLoaded = deps.ensureScheduleShiftsLoaded;
  var getSmartPlannerLastResult = deps.getSmartPlannerLastResult;
  var renderSmartPlannerResult = deps.renderSmartPlannerResult;
  var getCurrentWeekStartIso = deps.getCurrentWeekStartIso;
  var plannerEndOfYearIso = deps.plannerEndOfYearIso;
  var apiGet = deps.apiGet;
  var getSmartPlannerSettingsCache = deps.getSmartPlannerSettingsCache;
  var setSmartPlannerSettingsCache = deps.setSmartPlannerSettingsCache;
  var getSmartPlannerTransitionCatalog = deps.getSmartPlannerTransitionCatalog;
  var setSmartPlannerTransitionCatalog = deps.setSmartPlannerTransitionCatalog;
  var getSmartPlannerForbiddenTransitionKeys = deps.getSmartPlannerForbiddenTransitionKeys;
  var setSmartPlannerForbiddenTransitionKeys = deps.setSmartPlannerForbiddenTransitionKeys;
  var applyPlannerSettingsToForm = deps.applyPlannerSettingsToForm;
  var renderPlannerTransitionMatrix = deps.renderPlannerTransitionMatrix;
  var setPlannerSettingsFeedback = deps.setPlannerSettingsFeedback;
  var esc = deps.esc;
  var bindPlannerSubmitHandler = deps.bindPlannerSubmitHandler;
  var plannerLegacyRulesFromTransitionKeys = deps.plannerLegacyRulesFromTransitionKeys;
  var plannerBuildWeekStarts = deps.plannerBuildWeekStarts;
  var executePlannerBatchRequests = deps.executePlannerBatchRequests;
  var combinePlannerResults = deps.combinePlannerResults;
  var setSmartPlannerFeedback = deps.setSmartPlannerFeedback;
  var formatApiError = deps.formatApiError;
  var updatePlannerApplyState = deps.updatePlannerApplyState;
  var notify = deps.notify;
  var getSmartPlannerLastPayload = deps.getSmartPlannerLastPayload;
  var setSmartPlannerLastPayload = deps.setSmartPlannerLastPayload;
  var setSmartPlannerScopeMeta = deps.setSmartPlannerScopeMeta;
  var setSmartPlannerLastResult = deps.setSmartPlannerLastResult;
  var bindPlannerApplyButtons = deps.bindPlannerApplyButtons;
  var applyPlannerDominantShifts = deps.applyPlannerDominantShifts;
  var applyPlannerDailyRoster = deps.applyPlannerDailyRoster;
  var loadScheduleTiming = deps.loadScheduleTiming;
  var bindPlannerSettingsPanel = deps.bindPlannerSettingsPanel;
  var apiPut = deps.apiPut;
  var getSmartPlannerEditModeOriginalValues = deps.getSmartPlannerEditModeOriginalValues;
  var setSmartPlannerEditModeOriginalValues = deps.setSmartPlannerEditModeOriginalValues;
  var setSmartPlannerEditMode = deps.setSmartPlannerEditMode;

  var path = window.location.pathname || "";
  if (path.indexOf("/schedule-timing") !== 0) {
    return;
  }
  var form = document.querySelector("[data-smart-planner-form]");
  if (!form || form.getAttribute("data-bound") === "1") {
    return;
  }
  form.setAttribute("data-bound", "1");

  var weekStartEl = form.querySelector("[data-smart-planner-week-start]");
  var horizonEl = form.querySelector("[data-smart-planner-horizon]");
  var horizonHintEl = form.querySelector("[data-smart-planner-horizon-hint]");
  var endDateWrap = form.querySelector('[data-smart-planner-field="horizon-end-date"]');
  var endDateEl = form.querySelector("[data-smart-planner-end-date]");
  var shiftCategoryEl = form.querySelector("[data-smart-planner-shift-category]");
  var scopeEl = form.querySelector("[data-smart-planner-scope]");
  var scopeHintEl = form.querySelector("[data-smart-planner-scope-hint]");
  var scopeMetaEl = form.querySelector("[data-smart-planner-scope-meta]");
  var departmentWrap = form.querySelector('[data-smart-planner-field="department"]');
  var customIdsWrap = form.querySelector('[data-smart-planner-field="custom-ids"]');
  var departmentEl = form.querySelector("[data-smart-planner-department]");
  var customIdsEl = form.querySelector("[data-smart-planner-custom-ids]");
  var modeHintEl = form.querySelector("[data-smart-planner-mode-hint]");
  var maxWorkDaysEl = form.querySelector("[data-smart-planner-max-work-days]");
  var minDaysOffEl = form.querySelector("[data-smart-planner-min-days-off]");
  var minRestEl = form.querySelector("[data-smart-planner-min-rest]");
  var maxNightEl = form.querySelector("[data-smart-planner-max-night]");
  var saveSettingsBtn = document.querySelector("[data-smart-planner-save-settings]");
  var submitBtn = form.querySelector("[data-smart-planner-submit]");
  var applyDominantBtn = document.querySelector("[data-smart-planner-apply-dominant]");
  var applyDailyBtn = document.querySelector("[data-smart-planner-apply-daily]");
  var forceApplyEl = document.querySelector("[data-smart-planner-force-apply]");
  var restRuleWrap = form.querySelector('[data-smart-planner-field="rest-rule"]');
  var nightRuleWrap = form.querySelector('[data-smart-planner-field="night-rule"]');
  var plannerEmployeeDirectoryRows = [];
  var plannerEmployeeDirectoryPromise = null;

  ensureScheduleShiftsLoaded(function () {
    if (getSmartPlannerLastResult()) {
      renderSmartPlannerResult(getSmartPlannerLastResult());
    }
  });

  if (weekStartEl && !weekStartEl.value) {
    weekStartEl.value = getCurrentWeekStartIso();
  }
  if (endDateEl) {
    endDateEl.value = plannerEndOfYearIso(weekStartEl && weekStartEl.value ? weekStartEl.value : getCurrentWeekStartIso());
  }

  function loadPlannerSettings() {
    return apiGet('/v1/hcm/smart-attendance-shifting/settings')
      .then(function (payload) {
        if (!payload || payload.success !== true || !payload.data) {
          return;
        }
        setSmartPlannerSettingsCache(payload.data);
        setSmartPlannerTransitionCatalog(
          Array.isArray(payload.data.transitionCatalog) && payload.data.transitionCatalog.length
            ? payload.data.transitionCatalog.map(function (k) {
                return String(k || '').toLowerCase();
              })
            : getSmartPlannerTransitionCatalog()
        );
        setSmartPlannerForbiddenTransitionKeys(
          Array.isArray(payload.data.forbiddenTransitions) && payload.data.forbiddenTransitions.length
            ? payload.data.forbiddenTransitions.map(function (k) {
                return String(k || '').toLowerCase();
              })
            : ['night:morning']
        );
        applyPlannerSettingsToForm(form, payload.data);
        renderPlannerTransitionMatrix(getSmartPlannerTransitionCatalog(), getSmartPlannerForbiddenTransitionKeys());
        setPlannerSettingsFeedback('Default tenant berhasil dimuat.', false);
        window.setTimeout(function () { setPlannerSettingsFeedback('', false); }, 3000);
      })
      .catch(function () {
        renderPlannerTransitionMatrix(getSmartPlannerTransitionCatalog(), getSmartPlannerForbiddenTransitionKeys());
        setPlannerSettingsFeedback('Gagal load planner defaults, gunakan fallback lokal.', true);
      });
  }

  function currentCategory() {
    return shiftCategoryEl && shiftCategoryEl.value ? String(shiftCategoryEl.value) : 'office_hour';
  }

  function currentScope() {
    return scopeEl && scopeEl.value ? String(scopeEl.value) : 'legacy';
  }

  function currentHorizon() {
    return horizonEl && horizonEl.value ? String(horizonEl.value) : 'single_week';
  }

  function plannerSelectionSummary() {
    var mode = currentCategory();
    var scope = currentScope();
    var horizon = currentHorizon();
    var modeLabel = mode === 'shifting_24h' ? 'Shifting 24 Jam' : mode === 'hybrid' ? 'Hybrid' : 'Office Hour';
    var selectedDepartmentLabel = departmentEl && departmentEl.selectedOptions && departmentEl.selectedOptions[0]
      ? String(departmentEl.selectedOptions[0].textContent || '').trim()
      : '';
    var scopeLabel =
      scope === 'department'
        ? selectedDepartmentLabel && selectedDepartmentLabel !== 'Pilih departemen'
          ? 'departemen ' + selectedDepartmentLabel
          : 'departemen tertentu'
        : scope === 'custom'
          ? 'user ID pilihan'
          : 'semua employee aktif';
    var horizonLabel = horizon === 'end_of_year' ? 'batch sampai 31 Desember' : '1 minggu';
    return 'Mode: ' + modeLabel + '. Scope: ' + scopeLabel + '. Horizon: ' + horizonLabel + '.';
  }

  function loadPlannerEmployeeDirectory(forceReload) {
    if (!forceReload && plannerEmployeeDirectoryRows.length > 0) {
      return Promise.resolve(plannerEmployeeDirectoryRows.slice());
    }
    if (!forceReload && plannerEmployeeDirectoryPromise) {
      return plannerEmployeeDirectoryPromise;
    }

    function loadPage(page, collected) {
      return apiGet('/v1/hcm/employees?perPage=100&page=' + String(page)).then(function (payload) {
        var rows = Array.isArray(payload && payload.data) ? payload.data : [];
        var meta = payload && payload.meta ? payload.meta : {};
        var perPage = Number(meta.perPage || 100);
        var total = Number(meta.total || rows.length);
        var nextCollected = collected.concat(rows);

        if (!rows.length) {
          return nextCollected;
        }
        if (total > 0 && nextCollected.length >= total) {
          return nextCollected;
        }
        if (total <= 0 && rows.length < perPage) {
          return nextCollected;
        }

        return loadPage(page + 1, nextCollected);
      });
    }

    plannerEmployeeDirectoryPromise = loadPage(1, [])
      .then(function (rows) {
        plannerEmployeeDirectoryRows = rows.slice();
        plannerEmployeeDirectoryPromise = null;
        return rows.slice();
      })
      .catch(function (error) {
        plannerEmployeeDirectoryPromise = null;
        throw error;
      });

    return plannerEmployeeDirectoryPromise;
  }

  function renderPlannerDepartmentOptions(rows) {
    if (!departmentEl) {
      return;
    }
    var currentValue = String(departmentEl.value || '');
    var seen = {};
    var options = [{ value: '', label: 'Pilih departemen' }];

    (Array.isArray(rows) ? rows : []).forEach(function (row) {
      var departmentId = row && row.departmentId != null ? String(row.departmentId) : '';
      var departmentName = row && row.departmentName ? String(row.departmentName).trim() : '';
      if (!departmentId || !departmentName || departmentName === '—' || seen[departmentId]) {
        return;
      }
      seen[departmentId] = true;
      options.push({ value: departmentId, label: departmentName });
    });

    options.sort(function (left, right) {
      if (!left.value) {
        return -1;
      }
      if (!right.value) {
        return 1;
      }
      return left.label.localeCompare(right.label);
    });

    departmentEl.innerHTML = options
      .map(function (option) {
        var selected = option.value === currentValue ? ' selected' : '';
        return '<option value="' + esc(option.value) + '"' + selected + '>' + esc(option.label) + '</option>';
      })
      .join('');
  }

  function ensurePlannerDepartmentOptionsLoaded() {
    if (!departmentEl) {
      return Promise.resolve([]);
    }
    return loadPlannerEmployeeDirectory()
      .then(function (rows) {
        renderPlannerDepartmentOptions(rows);
        return rows;
      })
      .catch(function (error) {
        departmentEl.innerHTML = '<option value="">Departemen tidak tersedia</option>';
        throw error;
      });
  }

  function parseCustomIds(raw) {
    return String(raw || '')
      .split(/[\s,]+/)
      .map(function (item) {
        return parseInt(item, 10);
      })
      .filter(function (n) {
        return !isNaN(n) && n > 0;
      });
  }

  function resolvePlannerScope() {
    var scope = currentScope();
    if (scope === 'legacy') {
      return Promise.resolve({
        employeeIds: null,
        message: 'Scope mengikuti perilaku default planner.',
      });
    }
    if (scope === 'custom') {
      var customIds = parseCustomIds((customIdsEl && customIdsEl.value) || '');
      if (!customIds.length) {
        return Promise.reject({ plannerMessage: 'Isi minimal satu user ID untuk custom scope.' });
      }
      return Promise.resolve({
        employeeIds: customIds,
        message: 'Scope custom aktif: ' + String(customIds.length) + ' karyawan.',
      });
    }

    return loadPlannerEmployeeDirectory().then(function (rows) {
      if (!rows.length) {
        return Promise.reject({ plannerMessage: 'Employee list kosong di tenant aktif.' });
      }

      if (scope === 'all') {
        var allIds = rows
          .map(function (row) {
            return parseInt(row && row.userId != null ? row.userId : row && row.id, 10);
          })
          .filter(function (id) {
            return !isNaN(id) && id > 0;
          });
        return {
          employeeIds: allIds,
          message: 'Scope semua karyawan aktif: ' + String(allIds.length) + ' orang.',
        };
      }

      var selectedDepartmentId = parseInt((departmentEl && departmentEl.value) || '', 10);
      if (isNaN(selectedDepartmentId) || selectedDepartmentId <= 0) {
        return Promise.reject({ plannerMessage: 'Pilih departemen untuk sasaran draft planner.' });
      }

      var filteredIds = rows
        .filter(function (row) {
          return parseInt((row && row.departmentId != null ? row.departmentId : 0), 10) === selectedDepartmentId;
        })
        .map(function (row) {
          return parseInt(row && row.userId != null ? row.userId : row && row.id, 10);
        })
        .filter(function (id) {
          return !isNaN(id) && id > 0;
        });

      var departmentLabel =
        departmentEl && departmentEl.selectedOptions && departmentEl.selectedOptions[0]
          ? String(departmentEl.selectedOptions[0].textContent || '').trim()
          : 'departemen terpilih';

      if (!filteredIds.length) {
        return Promise.reject({ plannerMessage: 'Tidak ada employee aktif pada departemen "' + departmentLabel + '".' });
      }

      return {
        employeeIds: filteredIds,
        message: 'Scope departemen "' + departmentLabel + '": ' + String(filteredIds.length) + ' karyawan.',
      };
    });
  }

  function syncModeUi() {
    var mode = currentCategory();
    var isOffice = mode === 'office_hour';
    var scope = currentScope();
    var horizon = currentHorizon();
    if (nightRuleWrap) {
      nightRuleWrap.classList.toggle('d-none', isOffice);
    }
    if (restRuleWrap) {
      restRuleWrap.classList.toggle('d-none', isOffice);
    }
    if (departmentWrap) {
      departmentWrap.classList.toggle('d-none', scope !== 'department');
    }
    if (scope === 'department') {
      ensurePlannerDepartmentOptionsLoaded()
        .then(function () {
          if (scopeMetaEl) {
            scopeMetaEl.textContent = plannerSelectionSummary();
          }
        })
        .catch(function () {
          if (scopeMetaEl) {
            scopeMetaEl.textContent = 'Departemen tidak bisa dimuat. Cek employee directory tenant aktif.';
          }
        });
    }
    if (customIdsWrap) {
      customIdsWrap.classList.toggle('d-none', scope !== 'custom');
    }
    if (endDateWrap) {
      endDateWrap.classList.toggle('d-none', horizon !== 'end_of_year');
    }
    if (modeHintEl) {
      if (mode === 'shifting_24h') {
        modeHintEl.textContent = 'Pilihan manual untuk rotasi shift. Bukan auto dari master shift.';
      } else if (mode === 'hybrid') {
        modeHintEl.textContent = 'Pilihan manual untuk gabungan office hour dan shift.';
      } else {
        modeHintEl.textContent = 'Pilihan manual untuk pola kerja office hour.';
      }
    }
    if (scopeHintEl) {
      if (scope === 'department') {
        scopeHintEl.textContent = 'Sumber data: employee tenant aktif, dikelompokkan menurut departemen.';
      } else if (scope === 'custom') {
        scopeHintEl.textContent = 'Sumber data: employee tenant aktif, dibatasi ke user ID yang Anda isi.';
      } else {
        scopeHintEl.textContent = 'Sumber data: semua employee tenant aktif.';
      }
    }
    if (horizonHintEl) {
      if (horizon === 'end_of_year') {
        horizonHintEl.textContent = 'Batch mingguan dari Week Start sampai 31 Desember.';
      } else {
        horizonHintEl.textContent = 'Generate hanya untuk minggu yang dipilih.';
      }
    }
    if (scopeMetaEl) {
      scopeMetaEl.textContent = plannerSelectionSummary();
    }
    if (endDateEl) {
      endDateEl.value = plannerEndOfYearIso(weekStartEl && weekStartEl.value ? weekStartEl.value : getCurrentWeekStartIso());
    }
  }

  syncModeUi();
  loadPlannerSettings();
  if (shiftCategoryEl && !shiftCategoryEl.getAttribute('data-bound')) {
    shiftCategoryEl.setAttribute('data-bound', '1');
    shiftCategoryEl.addEventListener('change', syncModeUi);
  }
  if (scopeEl && !scopeEl.getAttribute('data-bound')) {
    scopeEl.setAttribute('data-bound', '1');
    scopeEl.addEventListener('change', syncModeUi);
  }
  if (departmentEl && !departmentEl.getAttribute('data-bound')) {
    departmentEl.setAttribute('data-bound', '1');
    departmentEl.addEventListener('change', syncModeUi);
  }
  if (horizonEl && !horizonEl.getAttribute('data-bound')) {
    horizonEl.setAttribute('data-bound', '1');
    horizonEl.addEventListener('change', syncModeUi);
  }
  if (weekStartEl && !weekStartEl.getAttribute('data-bound')) {
    weekStartEl.setAttribute('data-bound', '1');
    weekStartEl.addEventListener('change', syncModeUi);
  }

  function readInt(inputEl, fallback) {
    var n = parseInt((inputEl && inputEl.value) || '', 10);
    return isNaN(n) ? fallback : n;
  }

  bindPlannerSubmitHandler({
    form: form,
    submitBtn: submitBtn,
    weekStartEl: weekStartEl,
    endDateEl: endDateEl,
    maxWorkDaysEl: maxWorkDaysEl,
    minDaysOffEl: minDaysOffEl,
    minRestEl: minRestEl,
    maxNightEl: maxNightEl,
    scopeMetaEl: scopeMetaEl,
    currentCategory: currentCategory,
    currentHorizon: currentHorizon,
    getCurrentWeekStartIso: getCurrentWeekStartIso,
    readInt: readInt,
    readPlannerTransitionSelection: deps.readPlannerTransitionSelection,
    plannerLegacyRulesFromTransitionKeys: plannerLegacyRulesFromTransitionKeys,
    resolvePlannerScope: resolvePlannerScope,
    plannerEndOfYearIso: plannerEndOfYearIso,
    plannerBuildWeekStarts: plannerBuildWeekStarts,
    executePlannerBatchRequests: executePlannerBatchRequests,
    combinePlannerResults: combinePlannerResults,
    apiPost: deps.apiPost,
    setSmartPlannerFeedback: setSmartPlannerFeedback,
    formatApiError: formatApiError,
    renderSmartPlannerResult: renderSmartPlannerResult,
    updatePlannerApplyState: updatePlannerApplyState,
    notify: notify,
    getSmartPlannerForbiddenTransitionKeys: function () {
      return getSmartPlannerForbiddenTransitionKeys();
    },
    setSmartPlannerForbiddenTransitionKeys: function (value) {
      setSmartPlannerForbiddenTransitionKeys(value);
    },
    getSmartPlannerLastPayload: function () {
      return getSmartPlannerLastPayload();
    },
    setSmartPlannerLastPayload: function (value) {
      setSmartPlannerLastPayload(value);
    },
    setSmartPlannerScopeMeta: function (value) {
      setSmartPlannerScopeMeta(value);
    },
    setSmartPlannerLastResult: function (value) {
      setSmartPlannerLastResult(value);
    },
  });

  bindPlannerApplyButtons({
    applyDominantBtn: applyDominantBtn,
    applyDailyBtn: applyDailyBtn,
    getSmartPlannerLastResult: function () {
      return getSmartPlannerLastResult();
    },
    setSmartPlannerFeedback: setSmartPlannerFeedback,
    applyPlannerDominantShifts: applyPlannerDominantShifts,
    applyPlannerDailyRoster: applyPlannerDailyRoster,
    notify: notify,
    loadScheduleTiming: loadScheduleTiming,
    updatePlannerApplyState: updatePlannerApplyState,
  });

  bindPlannerSettingsPanel({
    form: form,
    settingsPanel: document.querySelector('[data-smart-planner-settings-panel]'),
    saveSettingsBtn: saveSettingsBtn,
    submitBtn: submitBtn,
    applyDominantBtn: applyDominantBtn,
    applyDailyBtn: applyDailyBtn,
    forceApplyEl: forceApplyEl,
    readPlannerTransitionSelection: deps.readPlannerTransitionSelection,
    renderPlannerTransitionMatrix: renderPlannerTransitionMatrix,
    applyPlannerSettingsToForm: applyPlannerSettingsToForm,
    setPlannerSettingsFeedback: setPlannerSettingsFeedback,
    setSmartPlannerFeedback: setSmartPlannerFeedback,
    loadPlannerSettings: loadPlannerSettings,
    updatePlannerApplyState: updatePlannerApplyState,
    notify: notify,
    apiPut: apiPut,
    formatApiError: formatApiError,
    readInt: readInt,
    getSmartPlannerSettingsCache: function () {
      return getSmartPlannerSettingsCache();
    },
    setSmartPlannerSettingsCache: function (value) {
      setSmartPlannerSettingsCache(value);
    },
    getSmartPlannerTransitionCatalog: function () {
      return getSmartPlannerTransitionCatalog();
    },
    getSmartPlannerForbiddenTransitionKeys: function () {
      return getSmartPlannerForbiddenTransitionKeys();
    },
    setSmartPlannerForbiddenTransitionKeys: function (value) {
      setSmartPlannerForbiddenTransitionKeys(value);
    },
    getSmartPlannerEditModeOriginalValues: function () {
      return getSmartPlannerEditModeOriginalValues();
    },
    setSmartPlannerEditModeOriginalValues: function (value) {
      setSmartPlannerEditModeOriginalValues(value);
    },
    setSmartPlannerEditMode: function (value) {
      setSmartPlannerEditMode(value);
    },
    getSmartPlannerLastResult: function () {
      return getSmartPlannerLastResult();
    },
  });
}