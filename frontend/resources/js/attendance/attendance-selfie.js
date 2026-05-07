export function initSelfieCapture(deps) {
  var notify = deps.notify;
  var apiPost = deps.apiPost;
  var formatApiError = deps.formatApiError;
  var loadEmployeeAttendance = deps.loadEmployeeAttendance;

  var selfieBtn = document.querySelector("[data-attendance-me-selfie-btn]");
  var selfieModalEl = document.getElementById("arcav_attendance_selfie_modal");
  var prereqModalEl = document.getElementById("arcav_attendance_selfie_prereq_modal");
  var consentModalEl = document.getElementById("arcav_biometric_consent_modal");
  var consentAgreeBtn = document.getElementById("arcav_biometric_consent_agree_btn");
  var consentDeclineBtn = document.getElementById("arcav_biometric_consent_decline_btn");
  var videoEl = document.querySelector("[data-selfie-camera-video]");
  var canvasEl = document.querySelector("[data-selfie-preview]");
  var captureBtn = document.querySelector("[data-selfie-capture-btn]");
  var retakeBtn = document.querySelector("[data-selfie-retake-btn]");
  var submitBtn = document.querySelector("[data-selfie-submit-btn]");

  if (!selfieBtn || !selfieModalEl || !videoEl || !canvasEl || !captureBtn || !retakeBtn || !submitBtn) {
    return;
  }

  function showConsentModal(onAgreed) {
    if (!consentModalEl || !(window.bootstrap && window.bootstrap.Modal)) {
      notify("Persetujuan biometrik diperlukan. Hubungi HR untuk mengaktifkan fitur selfie.", true);
      return;
    }
    var modal = window.bootstrap.Modal.getOrCreateInstance(consentModalEl);

    function handleAgree() {
      var btn = consentAgreeBtn;
      if (btn) { btn.disabled = true; btn.textContent = "Menyimpan..."; }
      apiPost("/v1/hcm/data-privacy/me/biometric-consent", {
        selfie_consent: true,
        gps_consent: true,
      })
        .then(function (res) {
          modal.hide();
          if (res && res.success) {
            if (typeof onAgreed === "function") onAgreed();
          } else {
            var msg = formatApiError(res, 0) || "Gagal menyimpan persetujuan.";
            notify(msg, true);
          }
        })
        .catch(function (err) {
          var data = err && err.response ? err.response.data : null;
          var status = err && err.response ? err.response.status : 0;
          notify(formatApiError(data, status) || "Gagal menyimpan persetujuan.", true);
        })
        .finally(function () {
          if (btn) { btn.disabled = false; btn.innerHTML = '<i class="ti ti-check me-1"></i>Saya Setuju'; }
          cleanup();
        });
    }

    function handleDecline() {
      modal.hide();
      notify("Selfie tidak dapat digunakan tanpa persetujuan biometrik.", true);
      cleanup();
    }

    function cleanup() {
      if (consentAgreeBtn) consentAgreeBtn.removeEventListener("click", handleAgree);
      if (consentDeclineBtn) consentDeclineBtn.removeEventListener("click", handleDecline);
    }

    if (consentAgreeBtn) consentAgreeBtn.addEventListener("click", handleAgree, { once: true });
    if (consentDeclineBtn) consentDeclineBtn.addEventListener("click", handleDecline, { once: true });

    modal.show();
  }

  if (selfieBtn.getAttribute("data-selfie-bound") === "1") {
    return;
  }
  selfieBtn.setAttribute("data-selfie-bound", "1");

  var mediaStream = null;
  var capturedImageData = null;
  var selfiePrereqDefaultMsg =
    "Harap lakukan punch in terlebih dahulu sebelum mengambil selfie. Setelah absensi hari ini tercatat, Anda dapat membuka kamera selfie dari tombol yang sama.";

  function stopCamera() {
    if (mediaStream) {
      try {
        mediaStream.getTracks().forEach(function (track) {
          track.stop();
        });
      } catch (ignore) {
        // browser may throw when track already stopped
      }
    }
    mediaStream = null;
    videoEl.srcObject = null;
  }

  function resetCaptureState() {
    capturedImageData = null;
    videoEl.classList.remove("d-none");
    canvasEl.classList.remove("show");
    canvasEl.removeAttribute("data-show");
    videoEl.removeAttribute("data-recording");
    captureBtn.classList.remove("d-none");
    retakeBtn.classList.add("d-none");
    submitBtn.classList.add("d-none");
  }

  function showSelfiePrereqModal(message) {
    var msg = (message && String(message).trim()) || selfiePrereqDefaultMsg;
    if (!(window.bootstrap && window.bootstrap.Modal) || !prereqModalEl) {
      notify(msg, true);
      return;
    }
    var msgEl = prereqModalEl.querySelector("[data-arcav-selfie-prereq-message]");
    if (msgEl) {
      msgEl.textContent = msg;
    }
    window.bootstrap.Modal.getOrCreateInstance(prereqModalEl).show();
  }

  function startCamera() {
    if (!navigator.mediaDevices || typeof navigator.mediaDevices.getUserMedia !== "function") {
      notify("Browser tidak mendukung akses kamera. Gunakan browser terbaru.", true);
      return;
    }

    navigator.mediaDevices
      .getUserMedia({
        video: {
          facingMode: "user",
          width: { ideal: 400 },
          height: { ideal: 300 },
        },
        audio: false,
      })
      .then(function (stream) {
        mediaStream = stream;
        videoEl.srcObject = stream;
        videoEl.setAttribute("data-recording", "1");
        return videoEl.play();
      })
      .catch(function (error) {
        var msg = error && error.message ? error.message : "Akses kamera ditolak. Cek izin browser Anda.";
        notify("Akses kamera ditolak: " + msg, true);
      });
  }

  selfieBtn.addEventListener("click", function () {
    var allowed = selfieBtn.getAttribute("data-arcav-selfie-allowed") !== "0";
    if (!allowed) {
      showSelfiePrereqModal("Harap lakukan punch in terlebih dahulu sebelum mengambil selfie.");
      return;
    }
    resetCaptureState();
    if (window.bootstrap && window.bootstrap.Modal) {
      window.bootstrap.Modal.getOrCreateInstance(selfieModalEl).show();
    }
  });

  selfieModalEl.addEventListener("shown.bs.modal", function () {
    if (!mediaStream) {
      startCamera();
    }
  });

  selfieModalEl.addEventListener("hidden.bs.modal", function () {
    stopCamera();
    resetCaptureState();
  });

  captureBtn.addEventListener("click", function () {
    var ctx = canvasEl.getContext("2d");
    if (!ctx) {
      notify("Canvas tidak tersedia untuk capture selfie.", true);
      return;
    }
    ctx.drawImage(videoEl, 0, 0, canvasEl.width, canvasEl.height);
    capturedImageData = canvasEl.toDataURL("image/jpeg", 0.9);

    videoEl.classList.add("d-none");
    videoEl.removeAttribute("data-recording");
    canvasEl.classList.add("show");
    canvasEl.setAttribute("data-show", "1");
    captureBtn.classList.add("d-none");
    retakeBtn.classList.remove("d-none");
    submitBtn.classList.remove("d-none");
  });

  retakeBtn.addEventListener("click", function () {
    capturedImageData = null;
    videoEl.classList.remove("d-none");
    canvasEl.classList.remove("show");
    canvasEl.removeAttribute("data-show");
    captureBtn.classList.remove("d-none");
    retakeBtn.classList.add("d-none");
    submitBtn.classList.add("d-none");
    if (!mediaStream) {
      startCamera();
    }
  });

  submitBtn.addEventListener("click", function () {
    if (!capturedImageData) {
      notify("Tidak ada foto untuk disimpan.", true);
      return;
    }

    submitBtn.disabled = true;
    submitBtn.textContent = "Mengirim...";

    apiPost("/v1/hcm/attendance/me/selfie", {
      selfie_base64: capturedImageData,
    })
      .then(function (payload) {
        if (!payload || payload.success !== true) {
          var msg = formatApiError(payload, 0) || "Gagal menyimpan selfie.";
          notify(msg, true);
          return;
        }
        notify("Selfie berhasil disimpan.", false);
        if (window.bootstrap && window.bootstrap.Modal) {
          window.bootstrap.Modal.getOrCreateInstance(selfieModalEl).hide();
        }
        loadEmployeeAttendance();
      })
      .catch(function (err) {
        var data = err && err.response ? err.response.data : null;
        var status = err && err.response ? err.response.status : 0;
        var code = data && data.error ? data.error.code : "";
        var msg = formatApiError(data, status) || "Gagal menyimpan selfie.";
        if (code === "ATTENDANCE_NOT_STARTED") {
          if (window.bootstrap && window.bootstrap.Modal) {
            window.bootstrap.Modal.getOrCreateInstance(selfieModalEl).hide();
          }
          showSelfiePrereqModal(msg);
          return;
        }
        if (code === "BIOMETRIC_CONSENT_REQUIRED") {
          if (window.bootstrap && window.bootstrap.Modal) {
            window.bootstrap.Modal.getOrCreateInstance(selfieModalEl).hide();
          }
          showConsentModal(function () {
            // After consent, re-open the selfie modal so user can retry immediately
            resetCaptureState();
            if (window.bootstrap && window.bootstrap.Modal) {
              window.bootstrap.Modal.getOrCreateInstance(selfieModalEl).show();
            }
          });
          return;
        }
        notify(msg, true);
      })
      .finally(function () {
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="ti ti-check me-1"></i>Simpan Selfie';
      });
  });
}
