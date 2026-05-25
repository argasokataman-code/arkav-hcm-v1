export function bindAttendanceExtras(deps) {
  var exportAdminCsv = deps.exportAdminCsv;
  var exportMeCsv = deps.exportMeCsv;
  var filterAndSortReportRows = deps.filterAndSortReportRows;
  var getReportRowsCache = deps.getReportRowsCache;
  var formatIsoDate = deps.formatIsoDate;
  var getSelectedReportDate = deps.getSelectedReportDate;
  var downloadCsv = deps.downloadCsv;
  var correctionModalState = deps.correctionModalState;
  var getTimesheetRowsCache = deps.getTimesheetRowsCache;
  var getScheduleTimingRowsCache = deps.getScheduleTimingRowsCache;
  var ensureScheduleShiftsLoaded = deps.ensureScheduleShiftsLoaded;
  var fillScheduleShiftSelect = deps.fillScheduleShiftSelect;
  var syncTimesFromShiftSelect = deps.syncTimesFromShiftSelect;
  var minutesToTimeStr = deps.minutesToTimeStr;
  var notify = deps.notify;
  var apiPost = deps.apiPost;
  var formatApiError = deps.formatApiError;
  var loadEmployeeAttendance = deps.loadEmployeeAttendance;

  document.addEventListener("click", function (e) {
    var adminExport = e.target.closest("[data-attendance-admin-export]");
    if (adminExport) {
      e.preventDefault();
      exportAdminCsv();
      return;
    }

    var meExport = e.target.closest("[data-attendance-me-export]");
    if (meExport) {
      e.preventDefault();
      exportMeCsv();
      return;
    }

    var reportExport = e.target.closest("[data-attendance-report-export]");
    if (reportExport) {
      e.preventDefault();
      var rawFormat = String(reportExport.getAttribute("data-attendance-report-export") || "xlsx").toLowerCase();
      var format = rawFormat === "csv" ? "csv" : "xlsx";
      var params = new URLSearchParams();
      params.set("format", format);
      params.set("date", getSelectedReportDate());

      var depSel = document.querySelector("[data-attendance-report-filter-department]");
      var statusSel = document.querySelector("[data-attendance-report-filter-status]");
      var sortSel = document.querySelector("[data-attendance-report-sort]");
      var sourceSel = document.querySelector("[data-attendance-report-source]");
      var snapshotInput = document.querySelector("[data-attendance-report-snapshot-id]");

      var department = depSel ? String(depSel.value || "").trim() : "";
      var status = statusSel ? String(statusSel.value || "").trim() : "";
      var sort = sortSel ? String(sortSel.value || "").trim() : "name_asc";
      var source = sourceSel ? String(sourceSel.value || "live").toLowerCase() : "live";

      if (department) params.set("department", department);
      if (status) params.set("status", status);
      if (sort) params.set("sort", sort);
      params.set("source", source === "archive" ? "archive" : "live");

      if (source === "archive") {
        var snapshotId = parseInt(String(snapshotInput ? snapshotInput.value : "0"), 10);
        if (!Number.isFinite(snapshotId) || snapshotId <= 0) {
          notify("Nomor arsip wajib diisi untuk export data arsip.", true);
          return;
        }
        params.set("snapshotId", String(snapshotId));
      }

      window.location.assign("/v1/hcm/attendance/admin/export?" + params.toString());
      return;
    }

    var correctionBtn = e.target.closest("[data-attendance-me-request-correction]");
    if (correctionBtn && !correctionBtn.disabled) {
      e.preventDefault();
      var modalEl = document.getElementById("arcav_attendance_correction_modal");
      var reasonEl = modalEl ? modalEl.querySelector("[data-attendance-correction-reason]") : null;
      if (!modalEl || !reasonEl || !(window.bootstrap && window.bootstrap.Modal)) {
        return;
      }
      reasonEl.value = "";
      // GAP-H: store work date from button attribute (history row) or fall back to today
      var wdField = modalEl.querySelector("[data-attendance-correction-work-date]");
      if (wdField) {
        var btnWd = correctionBtn.getAttribute("data-work-date") || "";
        if (!btnWd) {
          var today = new Date();
          btnWd = today.getFullYear() + "-" + String(today.getMonth() + 1).padStart(2, "0") + "-" + String(today.getDate()).padStart(2, "0");
        }
        wdField.value = btnWd;
      }
      correctionModalState.open = true;
      window.bootstrap.Modal.getOrCreateInstance(modalEl).show();
    }

    var tsExport = e.target.closest("[data-timesheets-export]");
    if (tsExport) {
      e.preventDefault();
      var tsHeaders = ["Employee", "Date", "Project", "Assigned Hours", "Worked Hours"];
      var tsData = (getTimesheetRowsCache() || []).map(function (r) {
        return [r.employeeName || "", r.dateLabel || "", r.project || "", r.assignedHours || 0, r.workedHours || 0];
      });
      downloadCsv("timesheets.csv", tsHeaders, tsData);
      return;
    }

    var stExport = e.target.closest("[data-schedule-timing-export]");
    if (stExport) {
      e.preventDefault();
      var format = String(stExport.getAttribute("data-schedule-timing-export") || "csv").toLowerCase();
      if (format !== "xlsx") {
        format = "csv";
      }

      var searchEl = document.querySelector("[data-schedule-timing-search]");
      var sortEl = document.querySelector("[data-schedule-timing-sort]");
      var deptEl = document.querySelector("[data-schedule-timing-dept-filter]");
      var params = new URLSearchParams();
      params.set("format", format);
      params.set("sort", sortEl ? String(sortEl.value || "name_asc") : "name_asc");
      if (searchEl && String(searchEl.value || "").trim()) {
        params.set("search", String(searchEl.value || "").trim());
      }
      if (deptEl && String(deptEl.value || "").trim()) {
        params.set("department", String(deptEl.value || "").trim());
      }

      window.location.assign("/v1/hcm/schedule-timing/export?" + params.toString());
      return;
    }

    var stEdit = e.target.closest("[data-schedule-timing-edit]");
    if (stEdit) {
      e.preventDefault();
      var uid = stEdit.getAttribute("data-user-id");
      if (!uid) {
        return;
      }
      var modalEl2 = document.getElementById("arcav_schedule_timing_edit");
      var form = document.querySelector("[data-schedule-timing-edit-form]");
      if (!modalEl2 || !form || !(window.bootstrap && window.bootstrap.Modal)) {
        return;
      }
      var shiftSel = form.querySelector("[data-st-edit-shift]");
      var startInp = form.querySelector("[data-st-edit-start]");
      var endInp = form.querySelector("[data-st-edit-end]");
      var uidInp = form.querySelector("[data-st-edit-user-id]");
      var cap = form.querySelector("[data-st-edit-employee]");
      var nm = stEdit.getAttribute("data-name") || "";
      var sm = parseInt(stEdit.getAttribute("data-start-minutes"), 10);
      var em = parseInt(stEdit.getAttribute("data-end-minutes"), 10);
      var shiftId = stEdit.getAttribute("data-shift-id") || "";
      var src = stEdit.getAttribute("data-source") || "auto";
      var resetBtnOpen = form.querySelector("[data-st-edit-reset]");
      if (resetBtnOpen) {
        if (src === "manual") {
          resetBtnOpen.classList.remove("d-none");
        } else {
          resetBtnOpen.classList.add("d-none");
        }
      }

      ensureScheduleShiftsLoaded(function () {
        fillScheduleShiftSelect(shiftSel);
        if (uidInp) {
          uidInp.value = uid;
        }
        if (cap) {
          cap.textContent = nm;
        }
        if (shiftSel) {
          shiftSel.value = shiftId && shiftSel.querySelector('option[value="' + shiftId + '"]') ? shiftId : "";
        }
        if (shiftSel && shiftSel.value) {
          syncTimesFromShiftSelect(shiftSel, startInp, endInp);
        } else {
          if (startInp) {
            startInp.value = minutesToTimeStr(isNaN(sm) ? 0 : sm);
          }
          if (endInp) {
            endInp.value = minutesToTimeStr(isNaN(em) ? 0 : em);
          }
        }
        window.bootstrap.Modal.getOrCreateInstance(modalEl2).show();
      });
      return;
    }

    var correctionSubmit = e.target.closest("[data-attendance-correction-submit]");
    if (correctionSubmit) {
      e.preventDefault();
      var modal = document.getElementById("arcav_attendance_correction_modal");
      var reason = modal ? modal.querySelector("[data-attendance-correction-reason]") : null;
      var value = reason ? String(reason.value || "").trim() : "";
      if (value.length < 5) {
        notify("Reason minimal 5 karakter.", true);
        return;
      }
      var today = new Date();
      var dateStr = (function () {
        // GAP-H: prefer workDate stored in modal hidden field (set when correction button clicked)
        var modal2 = document.getElementById("arcav_attendance_correction_modal");
        var wdField = modal2 ? modal2.querySelector("[data-attendance-correction-work-date]") : null;
        var stored = wdField ? String(wdField.value || "").trim() : "";
        if (stored && /^\d{4}-\d{2}-\d{2}$/.test(stored)) { return stored; }
        return today.getFullYear() + "-" + String(today.getMonth() + 1).padStart(2, "0") + "-" + String(today.getDate()).padStart(2, "0");
      })();
      correctionSubmit.disabled = true;
      correctionSubmit.textContent = "Sending...";
      apiPost("/v1/hcm/attendance/me/correction-request", {
        workDate: dateStr,
        reason: value,
      })
        .then(function (payload) {
          if (!payload || payload.success !== true) {
            notify(formatApiError(payload, 0) || "Failed request correction.", true);
            return;
          }
          if (modal && window.bootstrap && window.bootstrap.Modal) {
            window.bootstrap.Modal.getOrCreateInstance(modal).hide();
          }
          notify("Correction request sent to admin.", false);
          loadEmployeeAttendance();
        })
        .catch(function (err) {
          var data = err && err.response ? err.response.data : null;
          var status = err && err.response ? err.response.status : 0;
          notify(formatApiError(data, status) || "Failed request correction.", true);
        })
        .finally(function () {
          correctionSubmit.disabled = false;
          correctionSubmit.textContent = "Send Request";
        });
    }
  });
}
