export function setupAttendanceAdminEdit(deps) {
  var getSelectedAdminDate = deps.getSelectedAdminDate;
  var notify = deps.notify;
  var apiPut = deps.apiPut;
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
