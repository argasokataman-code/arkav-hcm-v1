export function setupAttendanceAdminEdit(deps) {
  var getSelectedAdminDate = deps.getSelectedAdminDate;
  var notify = deps.notify;
  var apiPut = deps.apiPut;
  var apiPost = deps.apiPost;
  var formatApiError = deps.formatApiError;
  var loadAdminAttendance = deps.loadAdminAttendance;

  var form = document.querySelector("[data-attendance-admin-edit-form]");
  if (!form) {
    return;
  }

  document.addEventListener("click", function (e) {
    var btn = e.target.closest("[data-attendance-admin-open-edit]");
    if (!btn) {
      return;
    }
    var uid = btn.getAttribute("data-user-id") || "";
    var nm = btn.getAttribute("data-name") || "";
    var cin = btn.getAttribute("data-check-in") || "";
    var cout = btn.getAttribute("data-check-out") || "";
    var br = btn.getAttribute("data-break") || "0";
    var late = btn.getAttribute("data-late") || "0";
    var wd = getSelectedAdminDate();

    form.querySelector('[data-attendance-admin-field="userId"]').value = uid;
    form.querySelector('[data-attendance-admin-field="workDate"]').value = wd;
    var wdIn = form.querySelector('[data-attendance-admin-field="workDateInput"]');
    if (wdIn) {
      wdIn.value = wd;
    }
    form.querySelector('[data-attendance-admin-field="checkInTime"]').value = cin;
    form.querySelector('[data-attendance-admin-field="checkOutTime"]').value = cout;
    form.querySelector('[data-attendance-admin-field="breakMinutes"]').value = br;
    form.querySelector('[data-attendance-admin-field="lateMinutes"]').value = late;
    var cap = document.querySelector("[data-attendance-admin-edit-employee]");
    if (cap) {
      cap.textContent = nm ? nm + " · " + wd : wd;
    }
  });

  var wdInput = form.querySelector('[data-attendance-admin-field="workDateInput"]');
  if (wdInput) {
    wdInput.addEventListener("change", function () {
      var v = wdInput.value;
      if (v && /^\d{4}-\d{2}-\d{2}$/.test(v)) {
        form.querySelector('[data-attendance-admin-field="workDate"]').value = v;
      }
    });
  }

  form.addEventListener("submit", function (e) {
    e.preventDefault();
    if (!ArcavValidation.validateForm(form)) { return; }
    var userId = parseInt(form.querySelector('[data-attendance-admin-field="userId"]').value, 10);
    var workDate = form.querySelector('[data-attendance-admin-field="workDateInput"]').value;
    if (!userId || !workDate || !/^\d{4}-\d{2}-\d{2}$/.test(workDate)) {
      notify("Data wajib belum lengkap. Periksa user dan tanggal kerja.", true);
      return;
    }
    var ci = form.querySelector('[data-attendance-admin-field="checkInTime"]').value.trim();
    var co = form.querySelector('[data-attendance-admin-field="checkOutTime"]').value.trim();
    var bm = parseInt(form.querySelector('[data-attendance-admin-field="breakMinutes"]').value, 10);
    var lm = parseInt(form.querySelector('[data-attendance-admin-field="lateMinutes"]').value, 10);
    if (isNaN(bm)) {
      bm = 0;
    }
    if (isNaN(lm)) {
      lm = 0;
    }
    var body = {
      userId: userId,
      workDate: workDate,
      checkInTime: ci || null,
      checkOutTime: co || null,
      breakMinutes: bm,
      lateMinutes: lm,
    };
    apiPut("/v1/hcm/attendance/admin/record", body)
      .then(function (payload) {
        if (!payload) {
          return;
        }
        if (payload.success !== true) {
          notify(formatApiError(payload, 0) || "Unable to save attendance.", true);
          return;
        }
        var modalEl = document.getElementById("arcav_edit_attendance");
        if (modalEl && window.bootstrap && window.bootstrap.Modal) {
          var inst = window.bootstrap.Modal.getInstance(modalEl);
          if (inst) {
            inst.hide();
          }
        }
        loadAdminAttendance();
      })
      .catch(function (err) {
        var data = err && err.response ? err.response.data : err && err.data ? err.data : null;
        var status = err && err.response ? err.response.status : err && err.status ? err.status : 0;
        if (window.AuthApi && window.AuthApi.handleUnauthorizedFromApi(status, data)) {
          return;
        }
        notify(formatApiError(data, status) || "Save failed.", true);
      });
  });

  document.addEventListener("click", function (e) {
    var viewBtn = e.target.closest("[data-attendance-correction-view]");
    if (!viewBtn) {
      return;
    }
    var nameEl = document.querySelector("[data-attendance-correction-detail-name]");
    var timeEl = document.querySelector("[data-attendance-correction-detail-time]");
    var reasonEl = document.querySelector("[data-attendance-correction-detail-reason]");
    if (nameEl) {
      nameEl.textContent = viewBtn.getAttribute("data-name") || "-";
    }
    if (timeEl) {
      timeEl.textContent = viewBtn.getAttribute("data-time") || "-";
    }
    if (reasonEl) {
      reasonEl.textContent = viewBtn.getAttribute("data-reason") || "-";
    }
    // GAP-B/D: store all row data for the "Edit Attendance" button inside the correction detail modal
    var editBtn = document.querySelector("[data-attendance-correction-open-edit]");
    if (editBtn) {
      editBtn.setAttribute("data-user-id", viewBtn.getAttribute("data-user-id") || "");
      editBtn.setAttribute("data-name", viewBtn.getAttribute("data-name") || "");
      editBtn.setAttribute("data-work-date", viewBtn.getAttribute("data-work-date") || "");
      editBtn.setAttribute("data-check-in", viewBtn.getAttribute("data-check-in") || "");
      editBtn.setAttribute("data-check-out", viewBtn.getAttribute("data-check-out") || "");
      editBtn.setAttribute("data-break", viewBtn.getAttribute("data-break") || "0");
      editBtn.setAttribute("data-late", viewBtn.getAttribute("data-late") || "0");
      editBtn.setAttribute("data-correction-reason", viewBtn.getAttribute("data-reason") || "");
    }
    // Gap 3: store record-id for dismiss/approve buttons
    var dismissBtn = document.querySelector("[data-attendance-correction-dismiss]");
    if (dismissBtn) {
      dismissBtn.setAttribute("data-record-id", viewBtn.getAttribute("data-record-id") || "");
      dismissBtn.setAttribute("data-user-id", viewBtn.getAttribute("data-user-id") || "");
    }
    var approveBtn = document.querySelector("[data-attendance-correction-approve]");
    if (approveBtn) {
      approveBtn.setAttribute("data-record-id", viewBtn.getAttribute("data-record-id") || "");
    }
  });

  // GAP-B/D: click "Edit Attendance" inside correction detail modal → prefill edit form + show correction banner
  document.addEventListener("click", function (e) {
    var openEditBtn = e.target.closest("[data-attendance-correction-open-edit]");
    if (!openEditBtn) {
      return;
    }
    var uid = openEditBtn.getAttribute("data-user-id") || "";
    var nm = openEditBtn.getAttribute("data-name") || "";
    var wd = openEditBtn.getAttribute("data-work-date") || getSelectedAdminDate();
    var cin = openEditBtn.getAttribute("data-check-in") || "";
    var cout = openEditBtn.getAttribute("data-check-out") || "";
    var br = openEditBtn.getAttribute("data-break") || "0";
    var late = openEditBtn.getAttribute("data-late") || "0";
    var corrReason = openEditBtn.getAttribute("data-correction-reason") || "";

    if (form) {
      form.querySelector('[data-attendance-admin-field="userId"]').value = uid;
      form.querySelector('[data-attendance-admin-field="workDate"]').value = wd;
      var wdIn = form.querySelector('[data-attendance-admin-field="workDateInput"]');
      if (wdIn) { wdIn.value = wd; }
      form.querySelector('[data-attendance-admin-field="checkInTime"]').value = cin;
      form.querySelector('[data-attendance-admin-field="checkOutTime"]').value = cout;
      form.querySelector('[data-attendance-admin-field="breakMinutes"]').value = br;
      form.querySelector('[data-attendance-admin-field="lateMinutes"]').value = late;
      var cap = document.querySelector("[data-attendance-admin-edit-employee]");
      if (cap) { cap.textContent = nm ? nm + " · " + wd : wd; }
    }

    // GAP-C/E: show correction context banner if there's a pending correction reason
    var banner = document.querySelector("[data-attendance-correction-context]");
    var bannerReason = document.querySelector("[data-attendance-correction-context-reason]");
    if (banner) {
      if (corrReason) {
        if (bannerReason) { bannerReason.textContent = corrReason; }
        banner.classList.remove("d-none");
      } else {
        banner.classList.add("d-none");
      }
    }

    // Close correction detail modal
    var corrDetailEl = document.getElementById("arcav_attendance_correction_detail");
    if (corrDetailEl && window.bootstrap && window.bootstrap.Modal) {
      var corrInst = window.bootstrap.Modal.getInstance(corrDetailEl);
      if (corrInst) { corrInst.hide(); }
    }

    // Open edit modal
    var editModalEl = document.getElementById("arcav_edit_attendance");
    if (editModalEl && window.bootstrap && window.bootstrap.Modal) {
      var editInst = window.bootstrap.Modal.getOrCreateInstance(editModalEl);
      editInst.show();
      var firstInput = document.querySelector("#arcav_edit_attendance input:not([type=hidden]):not([type=password]), #arcav_edit_attendance select");
      if (firstInput) setTimeout(function() { firstInput.focus(); }, 100);
    }
  });

  // GAP-C/E: hide correction banner when edit modal is opened via normal row edit button
  document.addEventListener("click", function (e) {
    var normalEditBtn = e.target.closest("[data-attendance-admin-open-edit]");
    if (!normalEditBtn) { return; }
    var banner = document.querySelector("[data-attendance-correction-context]");
    if (banner) { banner.classList.add("d-none"); }
  });

  // Gap 3: Dismiss (reject) a pending correction request
  document.addEventListener("click", function (e) {
    var btn = e.target.closest("[data-attendance-correction-dismiss]");
    if (!btn) { return; }
    var recordId = parseInt(btn.getAttribute("data-record-id") || "", 10);
    if (!recordId) {
      notify("Record ID tidak ditemukan. Coba buka ulang detail koreksi.", true);
      return;
    }
    if (!window.confirm("Tolak pengajuan koreksi absensi ini? Karyawan akan dinotifikasi.")) {
      return;
    }
    if (!apiPost) { return; }
    apiPost("/v1/hcm/attendance/admin/correction-dismiss", { recordId: recordId })
      .then(function (payload) {
        if (!payload) { return; }
        if (payload.success !== true) {
          notify(formatApiError(payload, 0) || "Gagal menolak koreksi.", true);
          return;
        }
        var corrDetailEl = document.getElementById("arcav_attendance_correction_detail");
        if (corrDetailEl && window.bootstrap && window.bootstrap.Modal) {
          var inst = window.bootstrap.Modal.getInstance(corrDetailEl);
          if (inst) { inst.hide(); }
        }
        notify("Koreksi berhasil ditolak.", false);
        if (loadAdminAttendance) { loadAdminAttendance(); }
      })
      .catch(function (err) {
        var data = err && err.response ? err.response.data : err && err.data ? err.data : null;
        var status = err && err.response ? err.response.status : err && err.status ? err.status : 0;
        if (window.AuthApi && window.AuthApi.handleUnauthorizedFromApi(status, data)) { return; }
        notify(formatApiError(data, status) || "Gagal menolak koreksi.", true);
      });
  });

  // Approve a pending correction request (as-is, without editing)
  document.addEventListener("click", function (e) {
    var btn = e.target.closest("[data-attendance-correction-approve]");
    if (!btn) { return; }
    var recordId = parseInt(btn.getAttribute("data-record-id") || "", 10);
    if (!recordId) {
      notify("Record ID tidak ditemukan. Coba buka ulang detail koreksi.", true);
      return;
    }
    if (!window.confirm("Setujui pengajuan koreksi absensi ini? Karyawan akan dinotifikasi.")) {
      return;
    }
    if (!apiPost) { return; }
    apiPost("/v1/hcm/attendance/admin/correction-approve", { recordId: recordId })
      .then(function (payload) {
        if (!payload) { return; }
        if (payload.success !== true) {
          notify(formatApiError(payload, 0) || "Gagal menyetujui koreksi.", true);
          return;
        }
        var corrDetailEl = document.getElementById("arcav_attendance_correction_detail");
        if (corrDetailEl && window.bootstrap && window.bootstrap.Modal) {
          var inst = window.bootstrap.Modal.getInstance(corrDetailEl);
          if (inst) { inst.hide(); }
        }
        notify("Koreksi berhasil disetujui.", false);
        if (loadAdminAttendance) { loadAdminAttendance(); }
      })
      .catch(function (err) {
        var data = err && err.response ? err.response.data : err && err.data ? err.data : null;
        var status = err && err.response ? err.response.status : err && err.status ? err.status : 0;
        if (window.AuthApi && window.AuthApi.handleUnauthorizedFromApi(status, data)) { return; }
        notify(formatApiError(data, status) || "Gagal menyetujui koreksi.", true);
      });
  });

  // Gap 1: Explicit Delete Record button
  document.addEventListener("click", function (e) {
    var btn = e.target.closest("[data-attendance-admin-delete-record]");
    if (!btn) { return; }
    var userId = parseInt(form.querySelector('[data-attendance-admin-field="userId"]').value || "", 10);
    var workDate = (form.querySelector('[data-attendance-admin-field="workDateInput"]') || form.querySelector('[data-attendance-admin-field="workDate"]')).value || "";
    if (!userId || !workDate) {
      notify("Tidak dapat menentukan record yang akan dihapus.", true);
      return;
    }
    if (!window.confirm("Hapus record absensi untuk tanggal " + workDate + "? Tindakan ini tidak dapat diurungkan.")) {
      return;
    }
    var body = {
      userId: userId,
      workDate: workDate,
      checkInTime: null,
      checkOutTime: null,
      breakMinutes: 0,
      lateMinutes: 0,
    };
    apiPut("/v1/hcm/attendance/admin/record", body)
      .then(function (payload) {
        if (!payload) { return; }
        if (payload.success !== true) {
          notify(formatApiError(payload, 0) || "Gagal menghapus record.", true);
          return;
        }
        var modalEl = document.getElementById("arcav_edit_attendance");
        if (modalEl && window.bootstrap && window.bootstrap.Modal) {
          var inst = window.bootstrap.Modal.getInstance(modalEl);
          if (inst) { inst.hide(); }
        }
        notify("Record absensi berhasil dihapus.", false);
        loadAdminAttendance();
      })
      .catch(function (err) {
        var data = err && err.response ? err.response.data : err && err.data ? err.data : null;
        var status = err && err.response ? err.response.status : err && err.status ? err.status : 0;
        if (window.AuthApi && window.AuthApi.handleUnauthorizedFromApi(status, data)) { return; }
        notify(formatApiError(data, status) || "Gagal menghapus record.", true);
      });
  });
}

export function bindPunch(deps) {
  var punchMapElId = deps.punchMapElId;
  var notify = deps.notify;
  var getCurrentPositionForPunch = deps.getCurrentPositionForPunch;
  var showPunchMapAt = deps.showPunchMapAt;
  var apiPost = deps.apiPost;
  var formatApiError = deps.formatApiError;
  var loadEmployeeAttendance = deps.loadEmployeeAttendance;
  var geolocationErrorMessage = deps.geolocationErrorMessage;
  var getManualPunchCoords = deps.getManualPunchCoords;

  document.addEventListener("click", function (e) {
    var t = e.target;
    if (!t || !t.closest) {
      return;
    }
    var btn = t.closest("[data-attendance-me-punch-btn]");
    if (!btn || btn.disabled) {
      return;
    }
    e.preventDefault();
    if (!document.getElementById(punchMapElId)) {
      notify("Halaman absensi tidak memuat peta lokasi.", true);
      return;
    }
    btn.disabled = true;
    getCurrentPositionForPunch()
      .then(function (coords) {
        showPunchMapAt(coords.latitude, coords.longitude);
        return apiPost("/v1/hcm/attendance/me/punch", {
          latitude: coords.latitude,
          longitude: coords.longitude,
        });
      })
      .then(function (payload) {
        if (!payload) {
          btn.disabled = false;
          return;
        }
        if (payload.success !== true) {
          notify(formatApiError(payload, 0) || "Unable to punch.", true);
          btn.disabled = false;
          return;
        }
        loadEmployeeAttendance();
      })
      .catch(function (err) {
        var data = err && err.response ? err.response.data : err && err.data ? err.data : null;
        var status = err && err.response ? err.response.status : err && err.status ? err.status : 0;
        if (window.AuthApi && window.AuthApi.handleUnauthorizedFromApi(status, data)) {
          btn.disabled = false;
          return;
        }
        var geoMsg = geolocationErrorMessage(err);
        var manualPunchCoords = getManualPunchCoords();
        if (manualPunchCoords && manualPunchCoords.latitude != null && manualPunchCoords.longitude != null) {
          notify((geoMsg || "GPS perangkat gagal.") + " Menggunakan titik manual dari peta...", false);
          apiPost("/v1/hcm/attendance/me/punch", {
            latitude: manualPunchCoords.latitude,
            longitude: manualPunchCoords.longitude,
          })
            .then(function (payload) {
              if (!payload) {
                btn.disabled = false;
                return;
              }
              if (payload.success !== true) {
                notify(formatApiError(payload, 0) || "Unable to punch.", true);
                btn.disabled = false;
                return;
              }
              loadEmployeeAttendance();
            })
            .catch(function (err2) {
              var data2 = err2 && err2.response ? err2.response.data : err2 && err2.data ? err2.data : null;
              var status2 = err2 && err2.response ? err2.response.status : err2 && err2.status ? err2.status : 0;
              notify(formatApiError(data2, status2) || "Punch failed.", true);
              btn.disabled = false;
            });
          return;
        }
        notify(geoMsg || formatApiError(data, status) || "Punch failed.", true);
        btn.disabled = false;
      });
  });
}

export function bindBreakToggle(deps) {
  var apiPost = deps.apiPost;
  var notify = deps.notify;
  var formatApiError = deps.formatApiError;
  var loadEmployeeAttendance = deps.loadEmployeeAttendance;

  document.addEventListener("click", function (e) {
    var t = e.target;
    if (!t || !t.closest) {
      return;
    }
    var btn = t.closest("[data-attendance-me-break-btn]");
    if (!btn || btn.disabled) {
      return;
    }
    e.preventDefault();
    btn.disabled = true;
    apiPost("/v1/hcm/attendance/me/break")
      .then(function (payload) {
        if (!payload) {
          return;
        }
        if (payload.success !== true) {
          notify(formatApiError(payload, 0) || "Unable to toggle break.", true);
          return;
        }
        notify(payload.data && payload.data.action === "break_start" ? "Break started." : "Break ended.", false);
        loadEmployeeAttendance();
      })
      .catch(function (err) {
        var data = err && err.response ? err.response.data : null;
        var status = err && err.response ? err.response.status : 0;
        notify(formatApiError(data, status) || "Unable to toggle break.", true);
      })
      .finally(function () {
        btn.disabled = false;
      });
  });
}


/**
 * GAP-O: Settings modal — load & save correction window days.
 */
export function setupAttendanceSettings(deps) {
  var apiGet = deps.apiGet;
  var apiPut = deps.apiPut;
  var notify = deps.notify;
  var formatApiError = deps.formatApiError;

  var settingsModal = document.getElementById("arcav_attendance_settings");
  if (!settingsModal) { return; }

  // Load current settings when modal opens
  settingsModal.addEventListener("show.bs.modal", function () {
    apiGet("/v1/hcm/attendance/settings").then(function (payload) {
      if (payload && payload.success && payload.data) {
        var el = document.querySelector('[data-attendance-settings-field="correctionWindowDays"]');
        if (el) { el.value = String(payload.data.correctionWindowDays != null ? payload.data.correctionWindowDays : 30); }
      }
    }).catch(function () {});
  });

  // Save settings
  document.addEventListener("click", function (e) {
    if (!e.target.closest("[data-attendance-settings-save]")) { return; }
    var el = document.querySelector('[data-attendance-settings-field="correctionWindowDays"]');
    var val = el ? parseInt(el.value, 10) : NaN;
    if (isNaN(val) || val < 0 || val > 365) {
      notify("Correction window must be 0–365 days.", true);
      return;
    }
    apiPut("/v1/hcm/attendance/settings", { correctionWindowDays: val })
      .then(function (payload) {
        if (!payload || payload.success !== true) {
          notify(formatApiError(payload, 0) || "Save failed.", true);
          return;
        }
        notify("Settings saved.");
        var modalEl = document.getElementById("arcav_attendance_settings");
        if (modalEl && window.bootstrap && window.bootstrap.Modal) {
          var inst = window.bootstrap.Modal.getInstance(modalEl);
          if (inst) { inst.hide(); }
        }
      })
      .catch(function (err) {
        var data = err && err.response ? err.response.data : null;
        var status = err && err.response ? err.response.status : 0;
        notify(formatApiError(data, status) || "Save failed.", true);
      });
  });
}
