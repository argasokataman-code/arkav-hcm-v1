/**
 * Shift Swap Simulator + Replacement Finder
 * Populates selectors from planner assignment index after generate,
 * then calls backend simulate-swap / find-replacement endpoints.
 */
export function createSwapReplacementModule(deps) {
  var apiPost = deps.apiPost;
  var esc = deps.esc;
  var getScheduleShiftsCache = deps.getScheduleShiftsCache;
  var getSmartPlannerAssignmentByUserId = deps.getSmartPlannerAssignmentByUserId;

  // ─── Helpers ────────────────────────────────────────────────────────────────

  function riskBadge(level) {
    if (level === 0) return '<span class="badge bg-success">✅ Aman</span>';
    if (level === 1) return '<span class="badge bg-warning text-dark">⚠️ Perlu Perhatian</span>';
    return '<span class="badge bg-danger">🔴 Berisiko Tinggi</span>';
  }

  function alertClass(level) {
    if (level === 0) return 'alert-success';
    if (level === 1) return 'alert-warning';
    return 'alert-danger';
  }

  // ─── Populate employee selectors from planner assignment index ───────────────

  function populateEmployeeSelectors() {
    var assignmentIndex = getSmartPlannerAssignmentByUserId ? getSmartPlannerAssignmentByUserId() : {};
    var employees = Object.keys(assignmentIndex).map(function (uid) {
      return { id: uid, name: String(assignmentIndex[uid].employeeName || uid) };
    }).sort(function (a, b) { return a.name.localeCompare(b.name); });

    var selectors = [
      document.querySelector('[data-swap-user-a]'),
      document.querySelector('[data-swap-user-b]'),
      document.querySelector('[data-replacement-absent-user]'),
    ];

    selectors.forEach(function (sel) {
      if (!sel) return;
      var currentVal = sel.value;
      sel.innerHTML = '<option value="">Pilih karyawan...</option>';
      employees.forEach(function (emp) {
        var opt = document.createElement('option');
        opt.value = emp.id;
        opt.textContent = emp.name;
        sel.appendChild(opt);
      });
      if (currentVal) sel.value = currentVal;
    });
  }

  function populateShiftSelector() {
    var shifts = getScheduleShiftsCache ? getScheduleShiftsCache() : [];
    var sel = document.querySelector('[data-replacement-shift]');
    if (!sel || !shifts.length) return;
    sel.innerHTML = '<option value="">Pilih shift...</option>';
    shifts.forEach(function (s) {
      if (!s || !s.isActive) return;
      var opt = document.createElement('option');
      opt.value = String(s.id);
      opt.textContent = String(s.name) + (s.startTime ? ' (' + s.startTime + '–' + s.endTime + ')' : '');
      sel.appendChild(opt);
    });
  }

  // ─── Button enable/disable ────────────────────────────────────────────────

  function syncSwapBtn() {
    var btn = document.querySelector('[data-swap-simulate-btn]');
    if (!btn) return;
    var a = document.querySelector('[data-swap-user-a]');
    var b = document.querySelector('[data-swap-user-b]');
    var da = document.querySelector('[data-swap-date-a]');
    var db = document.querySelector('[data-swap-date-b]');
    btn.disabled = !(a && a.value && b && b.value && da && da.value && db && db.value && a.value !== b.value);
  }

  function syncReplacementBtn() {
    var btn = document.querySelector('[data-replacement-find-btn]');
    if (!btn) return;
    var user = document.querySelector('[data-replacement-absent-user]');
    var date = document.querySelector('[data-replacement-date]');
    var shift = document.querySelector('[data-replacement-shift]');
    btn.disabled = !(user && user.value && date && date.value && shift && shift.value);
  }

  // ─── Swap Simulator ──────────────────────────────────────────────────────

  function handleSimulateSwap() {
    var btn = document.querySelector('[data-swap-simulate-btn]');
    var resultEl = document.querySelector('[data-swap-result]');
    if (!btn || !resultEl) return;

    var userAId = parseInt(String(document.querySelector('[data-swap-user-a]')?.value || ''), 10);
    var userBId = parseInt(String(document.querySelector('[data-swap-user-b]')?.value || ''), 10);
    var dateA = String(document.querySelector('[data-swap-date-a]')?.value || '');
    var dateB = String(document.querySelector('[data-swap-date-b]')?.value || '');

    if (!userAId || !userBId || !dateA || !dateB) return;

    btn.disabled = true;
    btn.textContent = 'Menganalisis...';
    resultEl.className = 'mt-3';
    resultEl.innerHTML = '<div class="text-muted small">Memproses simulasi swap...</div>';

    apiPost('/v1/hcm/smart-attendance-shifting/simulate-swap', {
      userAId: userAId,
      userBId: userBId,
      swapDateA: dateA,
      swapDateB: dateB,
    })
      .then(function (res) {
        if (!res || res.success !== true || !res.data) {
          throw new Error((res && res.error && res.error.message) || 'Gagal simulasi swap.');
        }
        renderSwapResult(resultEl, res.data);
      })
      .catch(function (err) {
        var msg = (err && err.data && err.data.error && err.data.error.message) || (err && err.message) || 'Terjadi kesalahan.';
        resultEl.innerHTML = '<div class="alert alert-danger small">' + esc(msg) + '</div>';
      })
      .finally(function () {
        btn.disabled = false;
        btn.textContent = 'Simulasi Swap';
        syncSwapBtn();
      });
  }

  function renderSwapResult(el, data) {
    if (!data.swappable && data.reason) {
      el.innerHTML = '<div class="alert alert-warning small">' + esc(data.reason) + '</div>';
      return;
    }

    var riskLevel = typeof data.overall_risk_level === 'number' ? data.overall_risk_level : 0;
    var empA = data.employee_a || {};
    var empB = data.employee_b || {};

    var warningsHtml = '';
    if (Array.isArray(data.warnings) && data.warnings.length) {
      warningsHtml = '<ul class="mb-0 ps-3 mt-2">' +
        data.warnings.map(function (w) { return '<li class="small">' + esc(w) + '</li>'; }).join('') +
        '</ul>';
    }

    el.innerHTML =
      '<div class="alert ' + alertClass(riskLevel) + ' mb-0">' +
      '<div class="d-flex align-items-center justify-content-between mb-2">' +
      '<strong>' + esc(data.swap_summary || 'Simulasi Swap') + '</strong>' +
      riskBadge(riskLevel) +
      '</div>' +
      '<div class="row g-2 mb-2">' +
      '<div class="col-md-6 border-end">' +
      '<div class="small fw-semibold mb-1">' + esc(empA.name || '') + '</div>' +
      '<div class="small">Shift semula: <strong>' + esc(String(empA.original_shift || '—')) + '</strong></div>' +
      '<div class="small">Shift baru (setelah swap): <strong>' + esc(String(empA.new_shift || '—')) + '</strong></div>' +
      '<div class="mt-1">' + riskBadge(empA.risk_level || 0) + '</div>' +
      (empA.warnings && empA.warnings.length ? '<ul class="mb-0 ps-3 mt-1">' + empA.warnings.map(function (w) { return '<li class="small">' + esc(w) + '</li>'; }).join('') + '</ul>' : '') +
      '</div>' +
      '<div class="col-md-6">' +
      '<div class="small fw-semibold mb-1">' + esc(empB.name || '') + '</div>' +
      '<div class="small">Shift semula: <strong>' + esc(String(empB.original_shift || '—')) + '</strong></div>' +
      '<div class="small">Shift baru (setelah swap): <strong>' + esc(String(empB.new_shift || '—')) + '</strong></div>' +
      '<div class="mt-1">' + riskBadge(empB.risk_level || 0) + '</div>' +
      (empB.warnings && empB.warnings.length ? '<ul class="mb-0 ps-3 mt-1">' + empB.warnings.map(function (w) { return '<li class="small">' + esc(w) + '</li>'; }).join('') + '</ul>' : '') +
      '</div>' +
      '</div>' +
      '<div class="small fw-semibold">' + esc(data.advice || '') + '</div>' +
      '</div>';
  }

  // ─── Replacement Finder ──────────────────────────────────────────────────

  function handleFindReplacement() {
    var btn = document.querySelector('[data-replacement-find-btn]');
    var resultEl = document.querySelector('[data-replacement-result]');
    if (!btn || !resultEl) return;

    var absentUserId = parseInt(String(document.querySelector('[data-replacement-absent-user]')?.value || ''), 10);
    var date = String(document.querySelector('[data-replacement-date]')?.value || '');
    var shiftId = String(document.querySelector('[data-replacement-shift]')?.value || '');

    if (!absentUserId || !date || !shiftId) return;

    btn.disabled = true;
    btn.textContent = 'Mencari...';
    resultEl.className = 'mt-3';
    resultEl.innerHTML = '<div class="text-muted small">Mencari kandidat pengganti...</div>';

    apiPost('/v1/hcm/smart-attendance-shifting/find-replacement', {
      absentUserId: absentUserId,
      absentDates: [date],
      shiftId: shiftId,
    })
      .then(function (res) {
        if (!res || res.success !== true || !res.data) {
          throw new Error((res && res.error && res.error.message) || 'Gagal mencari pengganti.');
        }
        renderReplacementResult(resultEl, res.data);
      })
      .catch(function (err) {
        var msg = (err && err.data && err.data.error && err.data.error.message) || (err && err.message) || 'Terjadi kesalahan.';
        resultEl.innerHTML = '<div class="alert alert-danger small">' + esc(msg) + '</div>';
      })
      .finally(function () {
        btn.disabled = false;
        btn.textContent = 'Cari Pengganti';
        syncReplacementBtn();
      });
  }

  function renderReplacementResult(el, data) {
    var candidates = Array.isArray(data.candidates) ? data.candidates : [];
    var msgClass = candidates.length > 0 ? 'alert-info' : 'alert-warning';

    var candidatesHtml = '';
    if (candidates.length) {
      candidatesHtml = '<ol class="mb-0 ps-3 mt-2">' +
        candidates.map(function (c, i) {
          return '<li class="mb-2">' +
            '<strong>' + esc(c.employee_name || '') + '</strong>' +
            (c.job_title ? ' <span class="text-muted small">(' + esc(c.job_title) + ')</span>' : '') +
            '<br><span class="small text-muted">' + esc(c.reason || '') + '</span>' +
            '</li>';
        }).join('') +
        '</ol>';
    }

    el.innerHTML =
      '<div class="alert ' + msgClass + ' mb-0">' +
      '<div class="fw-semibold mb-1">' + esc(data.message || '') + '</div>' +
      candidatesHtml +
      '</div>';
  }

  // ─── Init ────────────────────────────────────────────────────────────────

  function init() {
    // Sync buttons on any change to selectors/dates
    var swapFields = ['[data-swap-user-a]', '[data-swap-user-b]', '[data-swap-date-a]', '[data-swap-date-b]'];
    swapFields.forEach(function (sel) {
      var el = document.querySelector(sel);
      if (el) el.addEventListener('change', syncSwapBtn);
    });

    var replacementFields = ['[data-replacement-absent-user]', '[data-replacement-date]', '[data-replacement-shift]'];
    replacementFields.forEach(function (sel) {
      var el = document.querySelector(sel);
      if (el) el.addEventListener('change', syncReplacementBtn);
    });

    var swapBtn = document.querySelector('[data-swap-simulate-btn]');
    if (swapBtn) swapBtn.addEventListener('click', handleSimulateSwap);

    var replacementBtn = document.querySelector('[data-replacement-find-btn]');
    if (replacementBtn) replacementBtn.addEventListener('click', handleFindReplacement);
  }

  return {
    init: init,
    populateEmployeeSelectors: populateEmployeeSelectors,
    populateShiftSelector: populateShiftSelector,
    syncSwapBtn: syncSwapBtn,
    syncReplacementBtn: syncReplacementBtn,
  };
}
