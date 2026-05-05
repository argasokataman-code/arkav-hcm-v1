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

  var violationCodeLabels = {
    'COVERAGE_UNMET': 'Kebutuhan jumlah karyawan di slot ini tidak terpenuhi',
    'MIN_REST_VIOLATED': 'Jeda istirahat antar shift terlalu pendek',
    'MAX_NIGHT_STREAK': 'Terlalu banyak shift malam berturut-turut untuk satu karyawan',
    'MAX_WORK_DAYS': 'Jumlah hari kerja melebihi batas maksimum per minggu',
    'MIN_DAYS_OFF': 'Hari libur karyawan kurang dari minimum yang disyaratkan',
    'ILLEGAL_TRANSITION': 'Urutan shift tidak diizinkan (misal: malam langsung pagi)',
    'RULE': 'Pelanggaran aturan jadwal terdeteksi',
  };

  var suggestionTitleLabels = {
    'Resolve schedule rule violations first': 'Selesaikan pelanggaran aturan jadwal terlebih dahulu',
    'Trigger attendance recovery workflow': 'Tinjau kehadiran karyawan yang absen di hari kerja terjadwal',
    'Increase headcount': 'Tambah jumlah karyawan dalam scope generate',
    'Relax constraints': 'Kurangi ketketatatan aturan (Min Rest / Max Work Days)',
    'Review coverage requirements': 'Tinjau kembali kebutuhan coverage minimum per shift',
  };

  renderSimpleList(violationsEl, violations, function (row) {
    var code = row && row.code ? String(row.code) : "RULE";
    var rawMessage = row && row.message ? String(row.message) : "Violation detected";
    var friendlyCode = violationCodeLabels[code] || rawMessage;
    return friendlyCode;
  });

  renderSimpleList(suggestionsEl, suggestions, function (row) {
    var title = row && row.title ? String(row.title).trim() : "";
    var d = (row && row.data) ? row.data : {};
    var empCount = typeof d.employee_count === 'number' ? d.employee_count : 0;

    if (title === 'Resolve schedule rule violations first') {
      var parts = [];
      if (d.coverage_violation_count > 0) {
        parts.push(d.coverage_violation_count + ' slot shift tidak dapat terpenuhi karena karyawan tidak cukup');
      }
      if (d.other_violation_count > 0) {
        parts.push(d.other_violation_count + ' pelanggaran aturan lainnya (min rest / max hari kerja / urutan shift)');
      }
      var msg = 'Jadwal belum valid — ' + (parts.length ? parts.join(' dan ') + '. ' : 'terdapat ' + (d.violation_count || 0) + ' violations. ');
      if (d.min_employees_needed && empCount > 0) {
        var gap = d.min_employees_needed - empCount;
        msg += 'Dengan ' + empCount + ' karyawan saat ini, dibutuhkan minimal ' + d.min_employees_needed + ' karyawan untuk menutup semua slot — tambah ' + gap + ' karyawan lagi ke scope generate.';
      } else {
        msg += 'Selesaikan violations di atas sebelum publish.';
      }
      return msg;
    }

    if (title === 'Trigger attendance recovery workflow') {
      var absentCount = typeof d.absent_count === 'number' ? d.absent_count : 0;
      return 'Terdeteksi ' + absentCount + ' karyawan yang tidak hadir di hari kerja terjadwal' +
        (empCount > 0 ? ' (dari ' + empCount + ' karyawan dalam scope)' : '') +
        '. Cek kehadiran mereka dan lakukan penyesuaian jadwal atau proses izin/cuti sebelum publish.';
    }

    if (title === 'Rebalance night shift distribution') {
      var fScore = d.fairness_score != null ? d.fairness_score : null;
      return 'Distribusi shift malam tidak merata' +
        (fScore != null ? ' (fairness score: ' + fScore + '/100 — target minimal 80)' : '') +
        '. Pertimbangkan menambah karyawan ke scope atau mengatur ulang Max Night Streak agar beban shift malam lebih seimbang antar karyawan.';
    }

    if (title === 'Reduce consecutive heavy patterns') {
      var rScore = d.fatigue_risk_score != null ? d.fatigue_risk_score : null;
      return 'Risiko kelelahan tinggi' +
        (rScore != null ? ' (fatigue risk score: ' + rScore + '/100 — ambang kritis 70)' : '') +
        '. Kurangi Max Night Streak atau tambah Min Days Off agar pola kerja berat tidak berturut-turut.';
    }

    if (title === 'Maintain current schedule pattern with weekly monitoring') {
      var okFairness = d.fairness_score != null ? d.fairness_score : null;
      var okFatigue = d.fatigue_risk_score != null ? d.fatigue_risk_score : null;
      var scoreInfo = [];
      if (okFairness != null) { scoreInfo.push('fairness score: ' + okFairness + '/100'); }
      if (okFatigue != null) { scoreInfo.push('fatigue risk: ' + okFatigue + '/100'); }
      return 'Tidak ada masalah kritis yang terdeteksi' +
        (scoreInfo.length ? ' (' + scoreInfo.join(', ') + ')' : '') +
        '. Jadwal sudah aman untuk dipublish. Lanjutkan dengan pemantauan kehadiran mingguan.';
    }

    // Fallback: translate title only if known, else show as-is
    return suggestionTitleLabels[title] || title;
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
