(function (window, document) {
    "use strict";

    var adminRowsCache = [];
    var adminAttendancePage = 1;
    var scheduleTimingPage = 1;
    var meHistoryCache = [];
    var reportRowsCache = [];
    var timesheetRowsCache = [];
    var timesheetPage = 1;
    var scheduleTimingRowsCache = [];
    var scheduleTimingPaginationCache = null;
    var scheduleShiftsCache = [];
    var smartPlannerLastResult = null;
    var smartPlannerLastPayload = null;
    var smartPlannerSettingsCache = null;
    var smartPlannerTransitionCatalog = ["morning:afternoon", "morning:night", "afternoon:morning", "afternoon:night", "night:morning", "night:afternoon"];
    var smartPlannerForbiddenTransitionKeys = ["night:morning"];
    var smartPlannerAssignmentByUserId = {};
    var smartPlannerConflictSummary = { total: 0, critical: 0 };
    var smartPlannerEditMode = false;
    var smartPlannerEditModeOriginalValues = {};
    var scheduleTimingAiOnly = false;
    var scheduleTimingView = "list";
    var scheduleHolidayRowsCache = [];
    var scheduleCalendar = null;
    var smartPlannerScopeMeta = "";
    var correctionModalState = { open: false };
    var reportChart = null;
    var reportActiveDate = "";
    var reportSourceMode = "live";
    var breakTicker = null;
    var meRefreshTimer = null;
    var punchMapElId = "arcav-attendance-punch-map";
    var punchMap = null;
    var punchMarker = null;
    var manualPunchCoords = null;

    function destroyPunchMap() {
        if (punchMap) {
            try {
                punchMap.remove();
            } catch (ignore) {
                /* leaflet may throw if container gone */
            }
            punchMap = null;
            punchMarker = null;
        }
        var el = document.getElementById(punchMapElId);
        if (el) {
            el.innerHTML = "";
        }
    }

    function showPunchMapAt(lat, lng) {
        if (!window.L) {
            return;
        }
        var el = document.getElementById(punchMapElId);
        if (!el) {
            return;
        }
        destroyPunchMap();
        punchMap = window.L.map(el, { zoomControl: true }).setView([lat, lng], 17);
        window.L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
            maxZoom: 19,
        }).addTo(punchMap);
        punchMarker = window.L.marker([lat, lng]).addTo(punchMap);
        manualPunchCoords = { latitude: lat, longitude: lng };
        var hint = document.querySelector("[data-attendance-me-map-hint]");
        if (hint) {
            hint.textContent = "Lokasi aktif: " + String(lat.toFixed(6)) + ", " + String(lng.toFixed(6));
        }
        window.setTimeout(function () {
            if (punchMap) {
                punchMap.invalidateSize();
            }
        }, 250);
    }

    function ensureInteractivePunchMap() {
        if (!window.L) {
            return;
        }
        var el = document.getElementById(punchMapElId);
        if (!el) {
            return;
        }
        if (!punchMap) {
            punchMap = window.L.map(el, { zoomControl: true }).setView([-6.2088, 106.8456], 12);
            window.L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
                maxZoom: 19,
            }).addTo(punchMap);
            punchMap.on("click", function (e) {
                if (!e || !e.latlng) {
                    return;
                }
                var lat = Number(e.latlng.lat);
                var lng = Number(e.latlng.lng);
                if (!punchMarker) {
                    punchMarker = window.L.marker([lat, lng]).addTo(punchMap);
                } else {
                    punchMarker.setLatLng([lat, lng]);
                }
                manualPunchCoords = { latitude: lat, longitude: lng };
                var hint = document.querySelector("[data-attendance-me-map-hint]");
                if (hint) {
                    hint.textContent = "Titik manual dipilih: " + String(lat.toFixed(6)) + ", " + String(lng.toFixed(6));
                }
            });
        }
        window.setTimeout(function () {
            if (punchMap) {
                punchMap.invalidateSize();
            }
        }, 250);
    }

    function syncPunchMapFromMe(d) {
        if (!d || !document.getElementById(punchMapElId)) {
            return;
        }
        if (!window.L) {
            return;
        }
        var lat;
        var lng;
        if (d.punchState === "in" && d.checkInLatitude != null && d.checkInLongitude != null) {
            lat = d.checkInLatitude;
            lng = d.checkInLongitude;
        } else if (d.punchState === "done") {
            if (d.checkOutLatitude != null && d.checkOutLongitude != null) {
                lat = d.checkOutLatitude;
                lng = d.checkOutLongitude;
            } else if (d.checkInLatitude != null && d.checkInLongitude != null) {
                lat = d.checkInLatitude;
                lng = d.checkInLongitude;
            }
        }
        if (lat != null && lng != null) {
            showPunchMapAt(lat, lng);
        } else {
            destroyPunchMap();
            manualPunchCoords = null;
            ensureInteractivePunchMap();
            var el = document.getElementById(punchMapElId);
            if (el) {
                var hint = document.querySelector("[data-attendance-me-map-hint]");
                if (hint) {
                    hint.textContent = "Tip: klik titik di peta untuk set lokasi manual jika GPS browser gagal.";
                }
            }
        }
    }

    function getCurrentPositionForPunch() {
        return new Promise(function (resolve, reject) {
            if (!navigator.geolocation) {
                reject({ code: 0, message: "NO_GEO" });
                return;
            }
            if (!window.isSecureContext) {
                reject({ code: 0, message: "INSECURE_CONTEXT" });
                return;
            }
            navigator.geolocation.getCurrentPosition(
                function (pos) {
                    resolve({
                        latitude: pos.coords.latitude,
                        longitude: pos.coords.longitude,
                    });
                },
                function (geoErr) {
                    reject(geoErr || { code: 3 });
                },
                { enableHighAccuracy: true, timeout: 25000, maximumAge: 0 }
            );
        });
    }

    function geolocationErrorMessage(err) {
        var code = err && typeof err.code !== "undefined" ? err.code : null;
        var rawMessage = String((err && err.message) || "");
        var msgLower = rawMessage.toLowerCase();
        var host = String(window.location.hostname || "");
        var isLocalhost = host === "localhost" || host === "127.0.0.1" || host === "::1";

        if (!window.isSecureContext && !isLocalhost) {
            return "GPS butuh koneksi HTTPS. Akses aplikasi lewat URL HTTPS.";
        }
        if (rawMessage === "INSECURE_CONTEXT") {
            return "GPS diblokir karena halaman bukan HTTPS. Buka aplikasi lewat URL HTTPS.";
        }
        if (code === 1) {
            if (msgLower.indexOf("denied") >= 0 || msgLower.indexOf("permission") >= 0) {
                return "Akses lokasi ditolak. Cek 2 level izin: (1) izin situs di browser = Allow, (2) izin aplikasi browser di sistem operasi (Location Services) = aktif.";
            }
            return "Lokasi tidak diizinkan. Periksa izin lokasi browser dan izin Location Services di sistem operasi.";
        }
        if (code === 2) {
            return "Lokasi tidak tersedia. Pastikan GPS/Layanan Lokasi perangkat aktif.";
        }
        if (code === 3) {
            return "Pengambilan lokasi timeout. Coba lagi di area sinyal GPS lebih baik.";
        }
        if (err && (err.code === 0 || err.message === "NO_GEO")) {
            return "Perangkat atau browser tidak mendukung geolokasi.";
        }
        return "";
    }

    function runGpsDebugCheck() {
        var box = document.querySelector("[data-attendance-gps-debug-box]");
        if (!box) {
            return;
        }
        box.classList.remove("d-none");

        function setText(sel, value) {
            var el = document.querySelector(sel);
            if (el) {
                el.textContent = value;
            }
        }

        var host = String(window.location.hostname || "");
        var isLocalhost = host === "localhost" || host === "127.0.0.1" || host === "::1";
        var secure = !!window.isSecureContext || isLocalhost;
        setText("[data-gps-debug-secure]", secure ? "YES" : "NO");
        setText("[data-gps-debug-permission]", "checking...");
        setText("[data-gps-debug-coords]", "—");
        setText("[data-gps-debug-status]", "Requesting current position...");

        var permissionPromise = Promise.resolve("unknown");
        if (navigator.permissions && typeof navigator.permissions.query === "function") {
            permissionPromise = navigator.permissions
                .query({ name: "geolocation" })
                .then(function (r) {
                    return String(r.state || "unknown");
                })
                .catch(function () {
                    return "unavailable";
                });
        }

        permissionPromise.then(function (state) {
            setText("[data-gps-debug-permission]", state);
        });

        getCurrentPositionForPunch()
            .then(function (coords) {
                setText(
                    "[data-gps-debug-coords]",
                    String(coords.latitude.toFixed(6)) + ", " + String(coords.longitude.toFixed(6))
                );
                setText("[data-gps-debug-status]", "OK (GPS terbaca).");
                showPunchMapAt(coords.latitude, coords.longitude);
            })
            .catch(function (err) {
                var code = err && typeof err.code !== "undefined" ? String(err.code) : "-";
                var msg = (err && err.message) ? String(err.message) : "-";
                setText("[data-gps-debug-status]", "ERR code=" + code + " msg=" + msg + " | " + geolocationErrorMessage(err));
            });
    }

    function onAuthFailure(status, data) {
        if (window.AuthApi && typeof window.AuthApi.handleUnauthorizedFromApi === "function") {
            return window.AuthApi.handleUnauthorizedFromApi(status, data);
        }
        return false;
    }

    function apiGet(url) {
        if (window.axios) {
            return window.axios({
                method: "get",
                url: url,
                headers: { Accept: "application/json" },
                withCredentials: true,
            }).then(function (res) {
                return res.data;
            }).catch(function (err) {
                var status = err && err.response ? err.response.status : 0;
                var data = err && err.response ? err.response.data : null;
                if (onAuthFailure(status, data)) {
                    return null;
                }
                throw err;
            });
        }

        return fetch(url, {
            headers: { Accept: "application/json" },
            credentials: "same-origin",
        }).then(function (res) {
            return res.json().catch(function () {
                return {};
            }).then(function (data) {
                if (!res.ok) {
                    if (onAuthFailure(res.status, data)) {
                        return null;
                    }
                    throw new Error("Request failed: " + url);
                }
                return data;
            });
        });
    }

    function apiPost(url, body) {
        var payload = body && typeof body === "object" ? body : {};

        if (window.axios) {
            return window.axios({
                method: "post",
                url: url,
                headers: { Accept: "application/json" },
                data: payload,
                withCredentials: true,
            }).then(function (res) {
                return res.data;
            }).catch(function (err) {
                var status = err && err.response ? err.response.status : 0;
                var data = err && err.response ? err.response.data : null;
                if (onAuthFailure(status, data)) {
                    return null;
                }
                throw err;
            });
        }

        return fetch(url, {
            method: "POST",
            headers: {
                Accept: "application/json",
                "Content-Type": "application/json",
            },
            credentials: "same-origin",
            body: JSON.stringify(payload),
        }).then(function (res) {
            return res.json().catch(function () {
                return {};
            }).then(function (data) {
                if (!res.ok) {
                    if (onAuthFailure(res.status, data)) {
                        return null;
                    }
                    var err = new Error("Request failed");
                    err.response = { data: data, status: res.status };
                    throw err;
                }
                return data;
            });
        });
    }

    function apiPut(url, body) {
        var payload = body && typeof body === "object" ? body : {};

        if (window.axios) {
            return window.axios({
                method: "put",
                url: url,
                headers: {
                    Accept: "application/json",
                    "Content-Type": "application/json",
                },
                data: payload,
                withCredentials: true,
            })
                .then(function (res) {
                    return res.data;
                })
                .catch(function (err) {
                    var status = err && err.response ? err.response.status : 0;
                    var data = err && err.response ? err.response.data : null;
                    if (onAuthFailure(status, data)) {
                        return null;
                    }
                    throw err;
                });
        }

        return fetch(url, {
            method: "PUT",
            headers: {
                Accept: "application/json",
                "Content-Type": "application/json",
            },
            credentials: "same-origin",
            body: JSON.stringify(payload),
        }).then(function (res) {
            return res.json().catch(function () {
                return {};
            }).then(function (data) {
                if (!res.ok) {
                    if (onAuthFailure(res.status, data)) {
                        return null;
                    }
                    var err = new Error("Request failed");
                    err.response = { data: data, status: res.status };
                    throw err;
                }
                return data;
            });
        });
    }

    function apiDelete(url) {
        if (window.axios) {
            return window.axios({
                method: "delete",
                url: url,
                headers: { Accept: "application/json" },
                withCredentials: true,
            })
                .then(function (res) {
                    return res.data;
                })
                .catch(function (err) {
                    var status = err && err.response ? err.response.status : 0;
                    var data = err && err.response ? err.response.data : null;
                    if (onAuthFailure(status, data)) {
                        return null;
                    }
                    throw err;
                });
        }
        return fetch(url, {
            method: "DELETE",
            headers: { Accept: "application/json" },
            credentials: "same-origin",
        }).then(function (res) {
            return res.json().catch(function () {
                return {};
            }).then(function (data) {
                if (!res.ok) {
                    if (onAuthFailure(res.status, data)) {
                        return null;
                    }
                    var err = new Error("Request failed");
                    err.response = { data: data, status: res.status };
                    throw err;
                }
                return data;
            });
        });
    }

    function esc(v) {
        return String(v || "")
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#39;");
    }

    function formatApiError(data, status) {
        if (window.ApiErrorHelper && typeof window.ApiErrorHelper.format === "function") {
            return window.ApiErrorHelper.format(data, status);
        }
        if (data && data.error && data.error.message) {
            return data.error.message;
        }
        if (data && data.message) {
            return data.message;
        }
        return status ? "Request failed (" + status + ")" : "Request failed";
    }

    function notify(message, isError) {
        var existing = document.querySelector("[data-hcm-toast-container]");
        var container = existing;
        if (!container) {
            container = document.createElement("div");
            container.setAttribute("data-hcm-toast-container", "1");
            container.style.position = "fixed";
            container.style.top = "16px";
            container.style.right = "16px";
            container.style.zIndex = "3000";
            document.body.appendChild(container);
        }
        var toast = document.createElement("div");
        toast.className = "alert " + (isError ? "alert-danger" : "alert-success") + " shadow-sm mb-2";
        toast.textContent = message;
        container.appendChild(toast);
        window.setTimeout(function () {
            toast.remove();
        }, 2600);
    }

    function minutesToTimeStr(totalMins) {
        var n = Math.max(0, parseInt(totalMins, 10) || 0);
        var h = Math.floor(n / 60) % 24;
        var m = n % 60;
        return String(h).padStart(2, "0") + ":" + String(m).padStart(2, "0");
    }

    function timeInputToHi(val) {
        if (!val || typeof val !== "string") {
            return "09:00";
        }
        var p = val.split(":");
        return String(parseInt(p[0], 10) || 0).padStart(2, "0") + ":" + String(parseInt(p[1], 10) || 0).padStart(2, "0");
    }

    function parseHiToMinutes(hi) {
        if (!hi || typeof hi !== "string") {
            return 0;
        }
        var p = hi.split(":");
        return (parseInt(p[0], 10) || 0) * 60 + (parseInt(p[1], 10) || 0);
    }

    function fillScheduleShiftSelect(selectEl) {
        if (!selectEl) {
            return;
        }
        var html = '<option value="">Custom (manual)</option>';
        for (var i = 0; i < scheduleShiftsCache.length; i++) {
            var s = scheduleShiftsCache[i];
            if (!s.isActive) {
                continue;
            }
            html +=
                '<option value="' +
                esc(String(s.id)) +
                '" data-start="' +
                esc(s.startTime || "") +
                '" data-end="' +
                esc(s.endTime || "") +
                '">' +
                esc((s.name || "") + " (" + (s.slotLabel || "") + ")") +
                "</option>";
        }
        selectEl.innerHTML = html;
    }

    function syncTimesFromShiftSelect(selectEl, startInp, endInp) {
        if (!selectEl || !startInp || !endInp) {
            return;
        }
        var opt = selectEl.options[selectEl.selectedIndex];
        if (!opt || !selectEl.value) {
            return;
        }
        var ds = opt.getAttribute("data-start");
        var de = opt.getAttribute("data-end");
        if (ds) {
            startInp.value = minutesToTimeStr(parseHiToMinutes(ds));
        }
        if (de) {
            endInp.value = minutesToTimeStr(parseHiToMinutes(de));
        }
    }

    function ensureScheduleShiftsLoaded(callback) {
        apiGet("/v1/hcm/shifts")
            .then(function (p) {
                if (p && p.success === true && Array.isArray(p.data)) {
                    scheduleShiftsCache = p.data;
                } else {
                    scheduleShiftsCache = [];
                }
                if (typeof callback === "function") {
                    callback();
                }
            })
            .catch(function () {
                scheduleShiftsCache = [];
                if (typeof callback === "function") {
                    callback();
                }
            });
    }

    function setupScheduleTimingEditModal() {
        var form = document.querySelector("[data-schedule-timing-edit-form]");
        var modalEl = document.getElementById("arcav_schedule_timing_edit");
        if (!form || !modalEl || !(window.bootstrap && window.bootstrap.Modal)) {
            return;
        }
        var shiftSel = form.querySelector("[data-st-edit-shift]");
        var startInp = form.querySelector("[data-st-edit-start]");
        var endInp = form.querySelector("[data-st-edit-end]");
        var uidInp = form.querySelector("[data-st-edit-user-id]");
        var cap = form.querySelector("[data-st-edit-employee]");
        var resetBtn = form.querySelector("[data-st-edit-reset]");
        if (shiftSel && startInp && endInp) {
            shiftSel.addEventListener("change", function () {
                syncTimesFromShiftSelect(shiftSel, startInp, endInp);
            });
        }
        if (resetBtn && !resetBtn.getAttribute("data-arcav-st-reset-bound")) {
            resetBtn.setAttribute("data-arcav-st-reset-bound", "1");
            resetBtn.addEventListener("click", function () {
                var uid = uidInp ? uidInp.value.trim() : "";
                if (!uid) {
                    return;
                }
                var run = window.ArcavUi && typeof window.ArcavUi.confirmDelete === "function"
                    ? window.ArcavUi.confirmDelete(
                          "Jadwal manual akan dihapus. Tampilan kembali mengikuti rata-rata absensi 30 hari terakhir.",
                          "Reset ke otomatis"
                      )
                    : Promise.resolve(false);
                run.then(function (ok) {
                    if (!ok) {
                        return;
                    }
                    resetBtn.disabled = true;
                    apiDelete("/v1/hcm/schedule-timing/" + encodeURIComponent(uid))
                        .then(function (payload) {
                            if (!payload || payload.success !== true) {
                                notify(formatApiError(payload, 0) || "Gagal reset jadwal.", true);
                                return;
                            }
                            notify("Jadwal dikembalikan ke otomatis.", false);
                            window.bootstrap.Modal.getOrCreateInstance(modalEl).hide();
                            loadScheduleTiming();
                        })
                        .catch(function (err) {
                            var data = err && err.response ? err.response.data : err && err.data ? err.data : null;
                            var status = err && err.response ? err.response.status : err && err.status ? err.status : 0;
                            notify(formatApiError(data, status) || "Gagal reset jadwal.", true);
                        })
                        .finally(function () {
                            resetBtn.disabled = false;
                        });
                });
            });
        }
        form.addEventListener("submit", function (e) {
            e.preventDefault();
            var uid = uidInp ? uidInp.value.trim() : "";
            if (!uid) {
                return;
            }
            var body;
            if (shiftSel && shiftSel.value) {
                body = { shiftId: parseInt(shiftSel.value, 10) };
            } else {
                body = {
                    startTime: timeInputToHi(startInp ? startInp.value : ""),
                    endTime: timeInputToHi(endInp ? endInp.value : ""),
                };
            }
            var sub = form.querySelector("[data-st-edit-submit]");
            if (sub) {
                sub.disabled = true;
            }
            apiPut("/v1/hcm/schedule-timing/" + encodeURIComponent(uid), body)
                .then(function (payload) {
                    if (!payload || payload.success !== true) {
                        notify(formatApiError(payload, 0) || "Gagal menyimpan jadwal.", true);
                        return;
                    }
                    notify("Jadwal disimpan.", false);
                    window.bootstrap.Modal.getOrCreateInstance(modalEl).hide();
                    loadScheduleTiming();
                })
                .catch(function (err) {
                    var data = err && err.response ? err.response.data : err && err.data ? err.data : null;
                    var status = err && err.response ? err.response.status : err && err.status ? err.status : 0;
                    notify(formatApiError(data, status) || "Gagal menyimpan jadwal.", true);
                })
                .finally(function () {
                    if (sub) {
                        sub.disabled = false;
                    }
                });
        });
    }

    function syncAttendanceCircle(percent) {
        if (!window.jQuery) {
            return;
        }
        var $wrap = window.jQuery(".attendance-circle-progress");
        if (!$wrap.length) {
            return;
        }
        var value = Math.max(0, Math.min(100, parseInt(percent, 10) || 0));
        $wrap.attr("data-value", String(value));
        var left = $wrap.find(".progress-left .progress-bar");
        var right = $wrap.find(".progress-right .progress-bar");

        function percentageToDegrees(p) {
            return (p / 100) * 360;
        }

        left.css("transform", "rotate(0deg)");
        right.css("transform", "rotate(0deg)");
        if (value > 0) {
            if (value <= 50) {
                right.css("transform", "rotate(" + percentageToDegrees(value) + "deg)");
            } else {
                right.css("transform", "rotate(180deg)");
                left.css("transform", "rotate(" + percentageToDegrees(value - 50) + "deg)");
            }
        }
    }

    function formatIsoDate(iso) {
        if (!iso || typeof iso !== "string") {
            return "—";
        }
        var p = iso.split("-");
        if (p.length !== 3) {
            return iso;
        }
        var y = parseInt(p[0], 10);
        var mo = parseInt(p[1], 10) - 1;
        var d = parseInt(p[2], 10);
        if (!y || mo < 0 || mo > 11 || !d) {
            return iso;
        }
        var dt = new Date(y, mo, d);
        return dt.toLocaleDateString(undefined, { day: "numeric", month: "short", year: "numeric" });
    }

    function formatMmSs(totalSeconds) {
        var safe = Math.max(0, parseInt(totalSeconds, 10) || 0);
        var mins = Math.floor(safe / 60);
        var secs = safe % 60;
        return String(mins).padStart(2, "0") + ":" + String(secs).padStart(2, "0");
    }

    function stopBreakTicker() {
        if (breakTicker) {
            window.clearInterval(breakTicker);
            breakTicker = null;
        }
    }

    function startBreakTicker(startIso) {
        stopBreakTicker();
        if (!startIso) {
            return;
        }
        var startMs = new Date(startIso).getTime();
        if (!startMs || isNaN(startMs)) {
            return;
        }
        var durEl = document.querySelector("[data-attendance-me-break-duration]");
        if (!durEl) {
            return;
        }
        var tick = function () {
            var nowMs = Date.now();
            var secs = Math.floor((nowMs - startMs) / 1000);
            durEl.textContent = formatMmSs(secs);
        };
        tick();
        breakTicker = window.setInterval(tick, 1000);
    }

    function todayIsoLocal() {
        var today = new Date();
        var y = today.getFullYear();
        var m = String(today.getMonth() + 1).padStart(2, "0");
        var da = String(today.getDate()).padStart(2, "0");
        return y + "-" + m + "-" + da;
    }

    function getSelectedAdminDate() {
        var input = document.querySelector("[data-attendance-admin-date]");
        if (input && input.value && /^\d{4}-\d{2}-\d{2}$/.test(input.value)) {
            return input.value;
        }
        var params = new URLSearchParams(window.location.search || "");
        var qd = params.get("date");
        if (qd && /^\d{4}-\d{2}-\d{2}$/.test(qd)) {
            return qd;
        }
        return todayIsoLocal();
    }

    function setupAdminDateFilter() {
        var input = document.querySelector("[data-attendance-admin-date]");
        if (!input) {
            return;
        }
        var params = new URLSearchParams(window.location.search || "");
        var qd = params.get("date");
        if (qd && /^\d{4}-\d{2}-\d{2}$/.test(qd)) {
            input.value = qd;
        } else if (!input.value) {
            input.value = todayIsoLocal();
        }
        input.addEventListener("change", function () {
            var v = input.value;
            if (!v || !/^\d{4}-\d{2}-\d{2}$/.test(v)) {
                return;
            }
            try {
                var u = new URL(window.location.href);
                u.searchParams.set("date", v);
                window.history.replaceState({}, "", u.pathname + u.search + u.hash);
            } catch (e) {
                /* ignore */
            }
            adminAttendancePage = 1;
            loadAdminAttendance();
        });
    }

    function renderAdminPagination(pagination) {
        var foot = document.querySelector("[data-attendance-admin-pagination]");
        var info = document.querySelector("[data-attendance-admin-page-info]");
        if (!foot) {
            return;
        }
        if (!pagination || pagination.total == null) {
            foot.style.display = "none";
            return;
        }
        var total = parseInt(pagination.total, 10) || 0;
        var page = parseInt(pagination.page, 10) || 1;
        var perPage = parseInt(pagination.perPage, 10) || 50;
        var totalPages = parseInt(pagination.totalPages, 10) || 1;
        if (totalPages <= 1) {
            foot.style.display = "none";
            return;
        }
        foot.style.display = "";
        if (info) {
            var from = total === 0 ? 0 : (page - 1) * perPage + 1;
            var to = Math.min(page * perPage, total);
            info.textContent = "Menampilkan " + from + "–" + to + " dari " + total;
        }
        var prev = foot.querySelector("[data-attendance-admin-prev]");
        var next = foot.querySelector("[data-attendance-admin-next]");
        if (prev) {
            prev.disabled = page <= 1;
        }
        if (next) {
            next.disabled = page >= totalPages;
        }
    }

    function setupAdminPaginationControls() {
        var foot = document.querySelector("[data-attendance-admin-pagination]");
        if (!foot) {
            return;
        }
        var prev = foot.querySelector("[data-attendance-admin-prev]");
        var next = foot.querySelector("[data-attendance-admin-next]");
        if (prev && !prev.getAttribute("data-bound")) {
            prev.setAttribute("data-bound", "1");
            prev.addEventListener("click", function () {
                if (adminAttendancePage > 1) {
                    adminAttendancePage -= 1;
                    loadAdminAttendance();
                }
            });
        }
        if (next && !next.getAttribute("data-bound")) {
            next.setAttribute("data-bound", "1");
            next.addEventListener("click", function () {
                adminAttendancePage += 1;
                loadAdminAttendance();
            });
        }
    }

    function renderAdminSummary(meta) {
        var m = (meta && meta.summary) || {};
        var dateRaw = meta && meta.date ? meta.date : null;
        var heading = document.querySelector("[data-attendance-admin-heading]");
        if (heading) {
            heading.textContent = dateRaw ? "Attendance · " + formatIsoDate(dateRaw) : "Attendance";
        }
        var subtitle = document.querySelector("[data-attendance-admin-subtitle]");
        if (subtitle) {
            var total = m.totalEmployees != null ? m.totalEmployees : "—";
            var when = formatIsoDate(dateRaw || getSelectedAdminDate());
            subtitle.textContent = "Data from " + String(total) + " employees · " + when;
        }
        var presentQuick = document.querySelector("[data-attendance-admin-present-quick]");
        if (presentQuick) {
            presentQuick.textContent = String(m.present != null ? m.present : 0);
        }
        var absentees = document.querySelector("[data-attendance-admin-absentees]");
        if (absentees) {
            absentees.textContent = String(m.absent != null ? m.absent : 0);
        }
        var lateVal = m.late != null ? m.late : m.lateLogin;
        var statMap = {
            present: m.present,
            late: lateVal,
            uninformed: m.uninformed,
            permission: m.permission,
            absent: m.absent,
        };
        var keys = ["present", "late", "uninformed", "permission", "absent"];
        for (var i = 0; i < keys.length; i++) {
            var k = keys[i];
            var el = document.querySelector('[data-attendance-admin-stat="' + k + '"]');
            if (el) {
                var v = statMap[k];
                el.textContent = String(v != null ? v : "—");
            }
        }
    }

    function renderAdminRows(rows) {
        var tbody = document.querySelector("[data-attendance-admin-body]");
        if (!tbody) {
            return;
        }

        if (!rows.length) {
            tbody.innerHTML =
                '<tr><td class="text-center text-muted py-4">No employees found.</td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td></tr>';
            tbody.setAttribute("data-hydrated", "1");
            return;
        }

        tbody.innerHTML = rows
            .map(function (row) {
                var prodClass = row.productionBadgeClass === "success" ? "success" : "danger";
                var correctionRequested = String(row.correctionStatus || "") === "requested";
                var statusSuffix = correctionRequested ? " (Requested)" : "";
                var correctionAction = correctionRequested
                    ? '<a href="#" class="me-2" data-attendance-correction-view data-name="' +
                      esc(row.employeeName || "") +
                      '" data-time="' +
                      esc(row.correctionRequestedAt || "") +
                      '" data-reason="' +
                      esc(row.correctionReason || "") +
                      '" data-bs-toggle="modal" data-bs-target="#arcav_attendance_correction_detail"><i class="ti ti-message-circle"></i></a>'
                    : "";
                var checkInLoc = row.checkInLocation || "-";
                var checkOutLoc = row.checkOutLocation || "-";
                var selfieCell = row.hasSelfie
                    ? '<a class="btn btn-sm btn-outline-primary" target="_blank" rel="noopener" href="/v1/hcm/attendance/admin/records/' +
                      encodeURIComponent(String(row.recordId || "")) +
                      '/selfie/download">View</a>'
                    : '<span class="text-muted fs-12">—</span>';
                return (
                    "<tr data-attendance-user-id=\"" +
                    esc(row.userId) +
                    "\">" +
                    '<td><div class="form-check form-check-md"><input class="form-check-input" type="checkbox"></div></td>' +
                    '<td><div class="d-flex align-items-center file-name-icon">' +
                    '<span class="avatar avatar-md border avatar-rounded bg-primary-subtle text-primary fw-semibold d-inline-flex align-items-center justify-content-center">' +
                    esc(row.initial || "?") +
                    "</span>" +
                    '<div class="ms-2"><h6 class="fw-medium">' +
                    esc(row.employeeName) +
                    "</h6>" +
                    '<span class="fs-12 fw-normal">' +
                    esc(row.team) +
                    "</span></div></div></td>" +
                    '<td><span class="badge badge-' +
                    esc(row.statusBadgeClass) +
                    ' d-inline-flex align-items-center"><i class="ti ti-point-filled me-1"></i>' +
                    esc(row.statusLabel + statusSuffix) +
                    "</span></td>" +
                    "<td>" +
                    esc(row.checkIn) +
                    "</td>" +
                    '<td><span class="fs-12">' +
                    esc(checkInLoc) +
                    "</span></td>" +
                    "<td>" +
                    esc(row.checkOut) +
                    "</td>" +
                    '<td><span class="fs-12">' +
                    esc(checkOutLoc) +
                    "</span></td>" +
                    "<td>" +
                    esc(row.break) +
                    "</td>" +
                    "<td>" +
                    esc(row.late) +
                    "</td>" +
                    '<td><span class="badge badge-' +
                    prodClass +
                    ' d-inline-flex align-items-center"><i class="ti ti-clock-hour-11 me-1"></i>' +
                    esc(row.productionLabel) +
                    "</span></td>" +
                    "<td>" +
                    selfieCell +
                    "</td>" +
                    '<td><div class="action-icon d-inline-flex">' +
                    correctionAction +
                    '<a href="#" class="me-2" data-attendance-admin-open-edit data-user-id="' +
                    esc(String(row.userId)) +
                    '" data-name="' +
                    esc(row.employeeName) +
                    '" data-check-in="' +
                    esc(row.checkInTime24 || "") +
                    '" data-check-out="' +
                    esc(row.checkOutTime24 || "") +
                    '" data-break="' +
                    esc(String(row.breakMinutesRaw != null ? row.breakMinutesRaw : 0)) +
                    '" data-late="' +
                    esc(String(row.lateMinutesRaw != null ? row.lateMinutesRaw : 0)) +
                    '" data-bs-toggle="modal" data-bs-target="#arcav_edit_attendance"><i class="ti ti-edit"></i></a>' +
                    "</div></td>" +
                    "</tr>"
                );
            })
            .join("");
        tbody.setAttribute("data-hydrated", "1");
    }

    function parseTimeToMinutes(v) {
        if (!v || typeof v !== "string") {
            return -1;
        }
        var p = v.split(":");
        if (p.length !== 2) {
            return -1;
        }
        var h = parseInt(p[0], 10);
        var m = parseInt(p[1], 10);
        if (isNaN(h) || isNaN(m)) {
            return -1;
        }
        return h * 60 + m;
    }

    function parseProductionHours(row) {
        if (!row || typeof row.productionLabel !== "string") {
            return 0;
        }
        var n = parseFloat(String(row.productionLabel).replace(/[^0-9.]/g, ""));
        return isNaN(n) ? 0 : n;
    }

    function getAdminFilters() {
        var depSel = document.querySelector("[data-attendance-admin-filter-department]");
        var statusSel = document.querySelector("[data-attendance-admin-filter-status]");
        var sortSel = document.querySelector("[data-attendance-admin-sort]");
        return {
            department: depSel ? String(depSel.value || "").trim() : "",
            status: statusSel ? String(statusSel.value || "").trim().toLowerCase() : "",
            sort: sortSel ? String(sortSel.value || "name_asc") : "name_asc",
        };
    }

    function fillAdminDepartmentFilter(rowsOrDeptList) {
        var depSel = document.querySelector("[data-attendance-admin-filter-department]");
        if (!depSel) {
            return;
        }
        var prev = depSel.value || "";
        var deps;
        if (
            Array.isArray(rowsOrDeptList) &&
            rowsOrDeptList.length &&
            typeof rowsOrDeptList[0] === "string"
        ) {
            deps = rowsOrDeptList.slice().sort(function (a, b) {
                return String(a).localeCompare(String(b));
            });
        } else {
            var rows = rowsOrDeptList || [];
            var map = {};
            for (var i = 0; i < rows.length; i++) {
                var team = String(rows[i].team || "").trim();
                if (!team) {
                    continue;
                }
                map[team] = true;
            }
            deps = Object.keys(map).sort(function (a, b) {
                return a.localeCompare(b);
            });
        }
        var html = ['<option value="">All departments</option>'];
        for (var j = 0; j < deps.length; j++) {
            html.push('<option value="' + esc(deps[j]) + '">' + esc(deps[j]) + "</option>");
        }
        depSel.innerHTML = html.join("");
        if (prev && deps.indexOf(prev) !== -1) {
            depSel.value = prev;
        }
    }

    function filterAndSortAdminRows(rows) {
        var filters = getAdminFilters();
        var out = rows.filter(function (row) {
            if (filters.department) {
                var team = String(row.team || "").trim();
                if (team !== filters.department) {
                    return false;
                }
            }
            if (filters.status) {
                var key = String(row.statusKey || "").trim().toLowerCase();
                if (key !== filters.status) {
                    return false;
                }
            }
            return true;
        });

        out.sort(function (a, b) {
            if (filters.sort === "name_desc") {
                return String(b.employeeName || "").localeCompare(String(a.employeeName || ""));
            }
            if (filters.sort === "checkin_asc") {
                return parseTimeToMinutes(a.checkInTime24) - parseTimeToMinutes(b.checkInTime24);
            }
            if (filters.sort === "checkin_desc") {
                return parseTimeToMinutes(b.checkInTime24) - parseTimeToMinutes(a.checkInTime24);
            }
            if (filters.sort === "production_desc") {
                return parseProductionHours(b) - parseProductionHours(a);
            }
            if (filters.sort === "production_asc") {
                return parseProductionHours(a) - parseProductionHours(b);
            }
            return String(a.employeeName || "").localeCompare(String(b.employeeName || ""));
        });

        return out;
    }

    function rerenderAdminRowsFromCache() {
        renderAdminRows(Array.isArray(adminRowsCache) ? adminRowsCache : []);
    }

    function setupAdminFilters() {
        var depSel = document.querySelector("[data-attendance-admin-filter-department]");
        var statusSel = document.querySelector("[data-attendance-admin-filter-status]");
        var sortSel = document.querySelector("[data-attendance-admin-sort]");

        function onChange() {
            adminAttendancePage = 1;
            loadAdminAttendance();
        }

        if (depSel) {
            depSel.addEventListener("change", onChange);
        }
        if (statusSel) {
            statusSel.addEventListener("change", onChange);
        }
        if (sortSel) {
            sortSel.addEventListener("change", onChange);
        }
    }

    function renderAdminMessage(msg) {
        var tbody = document.querySelector("[data-attendance-admin-body]");
        if (!tbody) {
            return;
        }
        tbody.innerHTML =
            '<tr><td class="text-center text-muted py-4">' +
            esc(msg) +
            '</td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td></tr>';
        tbody.setAttribute("data-hydrated", "1");
    }

    function toCsvCell(v) {
        var str = String(v == null ? "" : v);
        if (/[",\n]/.test(str)) {
            return '"' + str.replace(/"/g, '""') + '"';
        }
        return str;
    }

    function downloadCsv(filename, headers, rows) {
        var csv = [headers.map(toCsvCell).join(",")];
        for (var i = 0; i < rows.length; i++) {
            csv.push(rows[i].map(toCsvCell).join(","));
        }
        var blob = new Blob([csv.join("\n")], { type: "text/csv;charset=utf-8;" });
        var url = URL.createObjectURL(blob);
        var a = document.createElement("a");
        a.href = url;
        a.download = filename;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
    }

    function exportAdminCsv() {
        var rows = filterAndSortAdminRows(adminRowsCache || []);
        var headers = ["Employee", "Department", "Status", "Check In", "Check Out", "Break", "Late", "Production Hours"];
        var data = rows.map(function (r) {
            return [
                r.employeeName || "",
                r.team || "",
                r.statusLabel || "",
                r.checkIn || "",
                r.checkOut || "",
                r.break || "",
                r.late || "",
                r.productionLabel || "",
            ];
        });
        downloadCsv("attendance-admin.csv", headers, data);
    }

    function exportMeCsv() {
        var headers = ["Date", "Check In", "Status", "Check Out", "Break", "Late", "Overtime", "Production Hours"];
        var data = (meHistoryCache || []).map(function (r) {
            return [
                r.dateLabel || "",
                r.checkIn || "",
                r.statusLabel || "",
                r.checkOut || "",
                r.break || "",
                r.late || "",
                r.overtime || "",
                r.productionLabel || "",
            ];
        });
        downloadCsv("attendance-my-history.csv", headers, data);
    }

    function loadAdminAttendance() {
        var path = window.location.pathname || "";
        if (path.indexOf("/attendance-admin") !== 0) {
            return;
        }

        var tbody = document.querySelector("[data-attendance-admin-body]");
        if (tbody) {
            tbody.innerHTML =
                '<tr><td class="text-center text-muted py-4">Loading attendance...</td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td></tr>';
            tbody.removeAttribute("data-hydrated");
        }

        var dateParam = getSelectedAdminDate();
        var filters = getAdminFilters();
        var qs = [
            "date=" + encodeURIComponent(dateParam),
            "page=" + encodeURIComponent(String(adminAttendancePage)),
            "perPage=50",
        ];
        if (filters.department) {
            qs.push("department=" + encodeURIComponent(filters.department));
        }
        if (filters.status) {
            qs.push("status=" + encodeURIComponent(filters.status));
        }
        if (filters.sort) {
            qs.push("sort=" + encodeURIComponent(filters.sort));
        }
        var adminUrl = "/v1/hcm/attendance/admin?" + qs.join("&");
        apiGet(adminUrl)
            .then(function (payload) {
                if (!payload) {
                    renderAdminMessage("Session not found. Redirecting to login…");
                    window.setTimeout(function () {
                        window.location.assign("/login");
                    }, 500);
                    return;
                }
                if (payload.success !== true) {
                    renderAdminMessage(formatApiError(payload, 0) || "Unable to load attendance.");
                    return;
                }
                var meta = payload.meta || {};
                var pag = meta.pagination || {};
                if (pag.totalPages != null && adminAttendancePage > pag.totalPages && pag.totalPages > 0) {
                    adminAttendancePage = pag.totalPages;
                    loadAdminAttendance();
                    return;
                }
                renderAdminSummary(meta);
                adminRowsCache = Array.isArray(payload.data) ? payload.data : [];
                if (Array.isArray(meta.departments) && meta.departments.length) {
                    fillAdminDepartmentFilter(meta.departments);
                } else {
                    fillAdminDepartmentFilter(adminRowsCache);
                }
                rerenderAdminRowsFromCache();
                renderAdminPagination(pag);
            })
            .catch(function (err) {
                var status = err && err.response ? err.response.status : err && err.status ? err.status : 0;
                var data = err && err.response ? err.response.data : err && err.data ? err.data : null;
                if (window.AuthApi && window.AuthApi.handleUnauthorizedFromApi(status, data)) {
                    return;
                }
                renderAdminMessage(formatApiError(data, status) || "Failed loading attendance. Please try again.");
            });
    }

    function applyMeToday(d) {
        if (!d) {
            return;
        }

        function applyProfileAvatar(photoUrl, userName) {
            var wrap = document.querySelector("[data-attendance-me-avatar]");
            var img = document.querySelector("[data-attendance-me-avatar-image]");
            var initialNode = document.querySelector("[data-attendance-me-avatar-initial]");
            var initialText = userName ? String(userName).charAt(0).toUpperCase() : "?";

            if (initialNode) {
                initialNode.textContent = initialText;
            }

            if (!wrap || !img) {
                return;
            }

            var hasPhoto = !!(photoUrl && String(photoUrl).trim());
            if (!hasPhoto) {
                wrap.classList.remove("has-image");
                img.removeAttribute("src");
                img.setAttribute("aria-hidden", "true");
                return;
            }

            img.onload = function () {
                wrap.classList.add("has-image");
                img.removeAttribute("aria-hidden");
            };
            img.onerror = function () {
                wrap.classList.remove("has-image");
                img.removeAttribute("src");
                img.setAttribute("aria-hidden", "true");
            };
            img.src = String(photoUrl);
        }

        var g = document.querySelector("[data-attendance-me-greeting]");
        if (g) {
            var greet = String(d.greeting || "")
                .replace(/^Good Morning,/i, "Selamat pagi,")
                .replace(/^Good Afternoon,/i, "Selamat siang,")
                .replace(/^Good Evening,/i, "Selamat malam,");
            g.textContent = greet;
        }
        var nowEl = document.querySelector("[data-attendance-me-now]");
        if (nowEl) {
            nowEl.textContent = d.nowLabel || "";
        }
        var userNameEl = document.querySelector("[data-attendance-me-user-name]");
        if (userNameEl) {
            userNameEl.textContent = d.userName || "Employee";
        }
        var teamEl = document.querySelector("[data-attendance-me-team]");
        if (teamEl) {
            teamEl.textContent = d.team || "—";
        }
        var badge = document.querySelector("[data-attendance-me-production-badge]");
        if (badge) {
            var hoursSoFar = d.productionHoursSoFar != null ? String(d.productionHoursSoFar).trim() : "";
            if (hoursSoFar) {
                badge.textContent = "Produktivitas: " + hoursSoFar + " jam";
            } else {
                var fallbackBadge = String(d.productionBadge || "").replace(/^Production\s*:\s*/i, "").trim();
                badge.textContent = fallbackBadge ? "Produktivitas: " + fallbackBadge : "Produktivitas: —";
            }
        }
        var punchLine = document.querySelector("[data-attendance-me-punch-line]");
        if (punchLine) {
            var pl = String(d.punchLine || "")
                .replace(/^Punch In at\s+/i, "Punch masuk pukul ")
                .replace(/^Belum punch in hari ini/i, "Belum punch masuk hari ini");
            punchLine.innerHTML =
                '<i class="ti ti-fingerprint text-primary fs-18"></i><span>' + esc(pl) + "</span>";
        }
        var btn = document.querySelector("[data-attendance-me-punch-btn]");
        if (btn) {
            var plab = d.punchButtonLabel || "Punch In";
            if (plab === "Punch In") {
                plab = "Punch masuk";
            } else if (plab === "Punch Out") {
                plab = "Punch keluar";
            } else if (plab === "Completed") {
                plab = "Selesai";
            }
            btn.textContent = plab;
            btn.disabled = !!d.punchButtonDisabled;
        }
        var breakBtn = document.querySelector("[data-attendance-me-break-btn]");
        if (breakBtn) {
            var blab = d.breakButtonLabel || "Start Break";
            if (blab === "Start Break") {
                blab = "Mulai istirahat";
            } else if (blab === "End Break") {
                blab = "Akhiri istirahat";
            }
            breakBtn.textContent = blab;
            breakBtn.disabled = !!d.breakButtonDisabled;
            breakBtn.setAttribute("data-break-in-progress", d.breakInProgress ? "1" : "0");
        }
        var breakIndicator = document.querySelector("[data-attendance-me-break-indicator]");
        if (breakIndicator) {
            if (d.breakInProgress) {
                breakIndicator.classList.remove("d-none");
                startBreakTicker(d.breakStartedAtIso || "");
            } else {
                breakIndicator.classList.add("d-none");
                stopBreakTicker();
                var durEl = breakIndicator.querySelector("[data-attendance-me-break-duration]");
                if (durEl) {
                    durEl.textContent = "00:00";
                }
            }
        }
        var alertBox = document.querySelector("[data-attendance-me-alert]");
        if (alertBox) {
            if (d.needsReview && d.alertMessage) {
                alertBox.textContent = d.alertMessage;
                alertBox.classList.remove("d-none");
            } else {
                alertBox.textContent = "";
                alertBox.classList.add("d-none");
            }
        }
        var correctionBtn = document.querySelector("[data-attendance-me-request-correction]");
        if (correctionBtn) {
            if (d.needsReview || d.correctionStatus === "requested") {
                correctionBtn.classList.remove("d-none");
                correctionBtn.disabled = d.correctionStatus === "requested";
                correctionBtn.textContent =
                    d.correctionStatus === "requested" ? "Koreksi diajukan" : "Ajukan koreksi";
            } else {
                correctionBtn.classList.add("d-none");
                correctionBtn.disabled = true;
            }
        }
        var selfieBtn = document.querySelector("[data-attendance-me-selfie-btn]");
        if (selfieBtn) {
            var canSelfie = d.punchState === "in" || d.punchState === "done";
            selfieBtn.setAttribute("data-arcav-selfie-allowed", canSelfie ? "1" : "0");
            selfieBtn.setAttribute(
                "title",
                canSelfie ? "" : "Lakukan punch masuk terlebih dahulu untuk mengambil selfie."
            );
        }
        applyProfileAvatar(d.profilePhotoUrl, d.userName);
        syncAttendanceCircle(d.productionProgressPercent || 0);

        function setText(sel, val) {
            var n = document.querySelector(sel);
            if (n) {
                n.textContent = val != null && val !== "" ? String(val) : "—";
            }
        }
        setText("[data-attendance-me-summary-total]", d.summaryTotalWorking);
        setText("[data-attendance-me-summary-productive]", d.summaryProductive);
        setText("[data-attendance-me-summary-break]", d.summaryBreak);
        setText("[data-attendance-me-summary-ot]", d.summaryOvertime);
        syncPunchMapFromMe(d);
    }

    function applyMeStats(s) {
        if (!s) {
            return;
        }
        function pair(hSel, tSel, h, t) {
            var he = document.querySelector(hSel);
            var te = document.querySelector(tSel);
            if (he) {
                he.textContent = h != null ? String(h) : "—";
            }
            if (te) {
                te.textContent = t != null ? String(t) : "—";
            }
        }
        pair("[data-attendance-stat-today-hours]", "[data-attendance-stat-today-target]", s.todayHours, s.todayTarget);
        pair("[data-attendance-stat-week-hours]", "[data-attendance-stat-week-target]", s.weekHours, s.weekTarget);
        pair("[data-attendance-stat-month-hours]", "[data-attendance-stat-month-target]", s.monthHours, s.monthTarget);
        pair(
            "[data-attendance-stat-ot-hours]",
            "[data-attendance-stat-ot-target]",
            s.monthOvertimeHours,
            s.monthOvertimeTarget
        );

        function foot(sel, a, b, suffix) {
            var el = document.querySelector(sel);
            if (!el) {
                return;
            }
            if (a != null && b != null) {
                el.textContent = String(a) + " / " + String(b) + (suffix || "");
            } else {
                el.textContent = "—";
            }
        }
        foot("[data-attendance-me-stat-foot-today]", s.todayHours, s.todayTarget, "h");
        foot("[data-attendance-me-stat-foot-week]", s.weekHours, s.weekTarget, "h");
        foot("[data-attendance-me-stat-foot-month]", s.monthHours, s.monthTarget, "h");
        foot("[data-attendance-me-stat-foot-ot]", s.monthOvertimeHours, s.monthOvertimeTarget, "h");
    }

    function renderMeHistory(rows) {
        var tbody = document.querySelector("[data-attendance-me-history-body]");
        if (!tbody) {
            return;
        }
        if (!rows.length) {
            tbody.innerHTML =
                '<tr><td class="text-center text-muted py-4">No attendance history yet.</td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td></tr>';
            tbody.setAttribute("data-hydrated", "1");
            return;
        }
        tbody.innerHTML = rows
            .map(function (row) {
                var prodClass = row.productionBadgeClass === "success" ? "success" : "danger";
                var checkInLoc = row.checkInLocation || "-";
                var checkOutLoc = row.checkOutLocation || "-";
                return (
                    "<tr>" +
                    "<td>" +
                    esc(row.dateLabel) +
                    "</td>" +
                    "<td>" +
                    esc(row.checkIn) +
                    "</td>" +
                    '<td><span class="fs-12">' +
                    esc(checkInLoc) +
                    "</span></td>" +
                    '<td><span class="badge badge-' +
                    esc(row.statusBadgeClass) +
                    ' d-inline-flex align-items-center"><i class="ti ti-point-filled me-1"></i>' +
                    esc(row.statusLabel) +
                    "</span></td>" +
                    "<td>" +
                    esc(row.checkOut) +
                    "</td>" +
                    '<td><span class="fs-12">' +
                    esc(checkOutLoc) +
                    "</span></td>" +
                    "<td>" +
                    esc(row.break) +
                    "</td>" +
                    "<td>" +
                    esc(row.late) +
                    "</td>" +
                    "<td>" +
                    esc(row.overtime) +
                    "</td>" +
                    '<td><span class="badge badge-' +
                    prodClass +
                    ' d-inline-flex align-items-center"><i class="ti ti-clock-hour-11 me-1"></i>' +
                    esc(row.productionLabel) +
                    "</span></td>" +
                    "</tr>"
                );
            })
            .join("");
        tbody.setAttribute("data-hydrated", "1");
    }

    function renderMeHistoryMessage(msg) {
        var tbody = document.querySelector("[data-attendance-me-history-body]");
        if (!tbody) {
            return;
        }
        tbody.innerHTML =
            '<tr><td class="text-center text-muted py-4">' +
            esc(msg) +
            '</td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td></tr>';
        tbody.setAttribute("data-hydrated", "1");
    }

    function clearMeRefreshTimer() {
        if (meRefreshTimer) {
            window.clearTimeout(meRefreshTimer);
            meRefreshTimer = null;
        }
    }

    function scheduleMeRefresh(isPunchInProgress) {
        clearMeRefreshTimer();
        if (!isPunchInProgress) {
            return;
        }
        meRefreshTimer = window.setTimeout(function () {
            loadEmployeeAttendance();
        }, 30000);
    }

    function loadEmployeeAttendance() {
        var path = window.location.pathname || "";
        if (path.indexOf("/attendance-employee") !== 0) {
            stopBreakTicker();
            clearMeRefreshTimer();
            return;
        }

        var tbody = document.querySelector("[data-attendance-me-history-body]");
        if (tbody) {
            tbody.innerHTML =
                '<tr><td class="text-center text-muted py-4">Loading history...</td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td></tr>';
            tbody.removeAttribute("data-hydrated");
        }
        ensureInteractivePunchMap();

        apiGet("/v1/hcm/attendance/me/today")
            .then(function (todayPayload) {
                if (!todayPayload) {
                    renderMeHistoryMessage("Session not found. Redirecting to login…");
                    window.setTimeout(function () {
                        window.location.assign("/login");
                    }, 500);
                    return;
                }
                if (todayPayload.success === true && todayPayload.data) {
                    applyMeToday(todayPayload.data);
                    scheduleMeRefresh(todayPayload.data.punchState === "in");
                }
            })
            .catch(function (err) {
                var status = err && err.response ? err.response.status : 0;
                var data = err && err.response ? err.response.data : null;
                if (window.AuthApi && window.AuthApi.handleUnauthorizedFromApi(status, data)) {
                    return;
                }
            });

        apiGet("/v1/hcm/attendance/me/stats")
            .then(function (statsPayload) {
                if (statsPayload && statsPayload.success === true && statsPayload.data) {
                    applyMeStats(statsPayload.data);
                }
            })
            .catch(function () {});

        apiGet("/v1/hcm/attendance/me/history?days=30")
            .then(function (histPayload) {
                if (!histPayload) {
                    renderMeHistoryMessage("Session not found. Redirecting to login…");
                    return;
                }
                if (histPayload.success === true) {
                    meHistoryCache = Array.isArray(histPayload.data) ? histPayload.data : [];
                    renderMeHistory(meHistoryCache);
                } else {
                    renderMeHistoryMessage("Unable to load history.");
                }
            })
            .catch(function (err) {
                var status = err && err.response ? err.response.status : 0;
                var data = err && err.response ? err.response.data : null;
                if (window.AuthApi && window.AuthApi.handleUnauthorizedFromApi(status, data)) {
                    return;
                }
                renderMeHistoryMessage("Failed loading attendance. Please try again.");
            });
    }

    function getSelectedReportDate() {
        var input = document.querySelector("[data-attendance-report-date]");
        if (input && input.value && /^\d{4}-\d{2}-\d{2}$/.test(input.value)) {
            return input.value;
        }
        var params = new URLSearchParams(window.location.search || "");
        var qd = params.get("date");
        if (qd && /^\d{4}-\d{2}-\d{2}$/.test(qd)) {
            return qd;
        }
        return todayIsoLocal();
    }

    function getReportSourceMode() {
        var sel = document.querySelector("[data-attendance-report-source]");
        var mode = sel ? String(sel.value || "live").toLowerCase() : "live";
        return mode === "archive" ? "archive" : "live";
    }

    function getSelectedSnapshotId() {
        var input = document.querySelector("[data-attendance-report-snapshot-id]");
        if (!input) {
            return 0;
        }
        var id = parseInt(String(input.value || "0"), 10);
        return Number.isFinite(id) && id > 0 ? id : 0;
    }

    function setReportSourceBadge(mode, snapshotId) {
        var badge = document.querySelector("[data-attendance-report-source-badge]");
        if (!badge) {
            return;
        }
        if (mode === "archive") {
            var suffix = snapshotId > 0 ? (" #" + String(snapshotId)) : "";
            badge.textContent = "Source: Archive" + suffix;
            return;
        }
        badge.textContent = "Source: Live";
    }

    function setupReportSourceMode() {
        var sourceSel = document.querySelector("[data-attendance-report-source]");
        var wrap = document.querySelector("[data-attendance-report-snapshot-wrap]");
        var loadBtn = document.querySelector("[data-attendance-report-load]");
        if (!sourceSel) {
            return;
        }

        function syncUi() {
            var mode = getReportSourceMode();
            reportSourceMode = mode;
            if (wrap) {
                wrap.classList.toggle("d-none", mode !== "archive");
            }
            setReportSourceBadge(mode, getSelectedSnapshotId());
        }

        sourceSel.addEventListener("change", function () {
            syncUi();
            loadReportAttendance();
        });

        if (loadBtn) {
            loadBtn.addEventListener("click", function () {
                loadReportAttendance();
            });
        }

        var snapshotInput = document.querySelector("[data-attendance-report-snapshot-id]");
        if (snapshotInput) {
            snapshotInput.addEventListener("change", function () {
                setReportSourceBadge(getReportSourceMode(), getSelectedSnapshotId());
            });
        }

        syncUi();
    }

    function setupReportDateFilter() {
        var input = document.querySelector("[data-attendance-report-date]");
        if (!input) {
            return;
        }
        var params = new URLSearchParams(window.location.search || "");
        var qd = params.get("date");
        if (qd && /^\d{4}-\d{2}-\d{2}$/.test(qd)) {
            input.value = qd;
        } else if (!input.value) {
            input.value = todayIsoLocal();
        }
        input.addEventListener("change", function () {
            var v = input.value;
            if (!v || !/^\d{4}-\d{2}-\d{2}$/.test(v)) {
                return;
            }
            try {
                var u = new URL(window.location.href);
                u.searchParams.set("date", v);
                window.history.replaceState({}, "", u.pathname + u.search + u.hash);
            } catch (e) {
                /* ignore */
            }
            if (getReportSourceMode() === "live") {
                loadReportAttendance();
            }
        });
    }

    function normalizeArchiveAttendanceRows(snapshotPayload, dateYmd) {
        var moduleData = snapshotPayload && snapshotPayload.dataByModule ? snapshotPayload.dataByModule.attendance : null;
        if (!moduleData || typeof moduleData !== "object") {
            return [];
        }
        var keys = Object.keys(moduleData);
        var out = [];
        for (var i = 0; i < keys.length; i++) {
            var key = keys[i];
            if (key.indexOf("user_") !== 0) {
                continue;
            }
            var item = moduleData[key] || {};
            var presentCount = Number(item.present || 0);
            var absentCount = Number(item.absent || 0);
            var statusKey = presentCount >= absentCount ? "present" : "absent";
            out.push({
                initial: String(item.user_name || "?").trim().charAt(0).toUpperCase() || "?",
                employeeName: item.user_name || "Unknown",
                team: "Archive",
                checkIn: "-",
                checkOut: "-",
                checkInTime24: "00:00",
                break: "-",
                late: String(item.total_late_minutes || 0) + " min",
                overtime: "-",
                productionLabel: "-",
                productionBadgeClass: "danger",
                statusKey: statusKey,
                statusLabel: statusKey === "present" ? "Present" : "Absent",
                workDate: dateYmd,
            });
        }
        return out;
    }

    function normalizeArchiveAttendanceSummary(snapshotPayload) {
        var moduleData = snapshotPayload && snapshotPayload.dataByModule ? snapshotPayload.dataByModule.attendance : null;
        var summary = moduleData && moduleData.summary ? moduleData.summary : {};
        var byStatus = moduleData && moduleData.by_status ? moduleData.by_status : {};
        var present = Number(byStatus.present || 0);
        var absent = Number(byStatus.absent || 0);
        var total = Number(summary.total_records || (present + absent));
        return {
            totalEmployees: total,
            present: present,
            absent: absent,
            lateLogin: 0,
            permission: 0,
            uninformed: 0,
        };
    }

    function renderReportRows(rows, dateYmd) {
        var tbody = document.querySelector("[data-attendance-report-body]");
        if (!tbody) {
            return;
        }
        var dateLabel = formatIsoDate(dateYmd);
        if (!rows.length) {
            tbody.innerHTML =
                '<tr><td class="text-center text-muted py-4">No rows for this date.</td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td></tr>';
            tbody.setAttribute("data-hydrated", "1");
            return;
        }
        
        // DEBUG: Log first row to console
        if (rows.length > 0) {
            console.log('=== REPORT ROWS DEBUG ===');
            console.log('Total rows:', rows.length);
            console.log('First row:', rows[0]);
            console.log('checkInLocation value:', rows[0].checkInLocation);
            console.log('checkInLocationName value:', rows[0].checkInLocationName);
        }
        
        tbody.innerHTML = rows
            .map(function (row) {
                var prodClass = row.productionBadgeClass === "success" ? "success" : "danger";
                var ot = row.overtime != null && row.overtime !== undefined ? row.overtime : "-";
                var checkInLoc = row.checkInLocation || row.checkInLocationName || "-";
                var checkOutLoc = row.checkOutLocation || row.checkOutLocationName || "-";
                console.log('Row:', row.employeeName, '| checkInLoc:', checkInLoc, '| checkOutLoc:', checkOutLoc);
                return (
                    "<tr>" +
                    '<td><div class="d-flex align-items-center">' +
                    '<span class="avatar avatar-md border avatar-rounded bg-primary-subtle text-primary fw-semibold d-inline-flex align-items-center justify-content-center">' +
                    esc(row.initial || "?") +
                    "</span>" +
                    '<div class="ms-2"><p class="text-dark mb-0">' +
                    esc(row.employeeName) +
                    "</p>" +
                    '<span class="fs-12">' +
                    esc(row.team) +
                    "</span></div></div></td>" +
                    "<td>" +
                    esc(dateLabel) +
                    "</td>" +
                    "<td>" +
                    esc(row.checkIn) +
                    "</td>" +
                    '<td style="background:#ffff99;"><span class="fs-12">' +
                    esc(checkInLoc) +
                    " [LOCATION] </span></td>" +
                    '<td><span class="badge badge-soft-' +
                    (row.statusKey === "present" ? "success" : "danger") +
                    ' d-inline-flex align-items-center badge-xs"><i class="ti ti-point-filled me-1"></i>' +
                    esc(row.statusLabel) +
                    "</span></td>" +
                    "<td>" +
                    esc(row.checkOut) +
                    "</td>" +
                    '<td><span class="fs-12">' +
                    esc(checkOutLoc) +
                    "</span></td>" +
                    "<td>" +
                    esc(row.break) +
                    "</td>" +
                    "<td>" +
                    esc(row.late) +
                    "</td>" +
                    "<td>" +
                    esc(ot) +
                    "</td>" +
                    '<td><span class="badge badge-' +
                    prodClass +
                    ' d-inline-flex align-items-center badge-sm"><i class="ti ti-clock-hour-11 me-1"></i>' +
                    esc(row.productionLabel) +
                    "</span></td>" +
                    "</tr>"
                );
            })
            .join("");
        tbody.setAttribute("data-hydrated", "1");
    }

    function getReportFilters() {
        var depSel = document.querySelector("[data-attendance-report-filter-department]");
        var statusSel = document.querySelector("[data-attendance-report-filter-status]");
        var sortSel = document.querySelector("[data-attendance-report-sort]");
        return {
            department: depSel ? String(depSel.value || "").trim().toLowerCase() : "",
            status: statusSel ? String(statusSel.value || "").trim().toLowerCase() : "",
            sort: sortSel ? String(sortSel.value || "name_asc") : "name_asc",
        };
    }

    function fillReportDepartmentFilter(rows) {
        var depSel = document.querySelector("[data-attendance-report-filter-department]");
        if (!depSel) {
            return;
        }
        var prev = depSel.value || "";
        var map = {};
        for (var i = 0; i < rows.length; i++) {
            var team = String(rows[i].team || "").trim();
            if (!team) {
                continue;
            }
            map[team] = true;
        }
        var deps = Object.keys(map).sort(function (a, b) {
            return a.localeCompare(b);
        });
        var html = ['<option value="">All departments</option>'];
        for (var j = 0; j < deps.length; j++) {
            html.push('<option value="' + esc(deps[j]) + '">' + esc(deps[j]) + "</option>");
        }
        depSel.innerHTML = html.join("");
        if (prev && map[prev]) {
            depSel.value = prev;
        }
    }

    function filterAndSortReportRows(rows) {
        var filters = getReportFilters();
        var out = rows.filter(function (row) {
            if (filters.department) {
                var team = String(row.team || "").trim().toLowerCase();
                if (team !== filters.department) {
                    return false;
                }
            }
            if (filters.status) {
                var key = String(row.statusKey || "").trim().toLowerCase();
                if (key !== filters.status) {
                    return false;
                }
            }
            return true;
        });

        out.sort(function (a, b) {
            if (filters.sort === "name_desc") {
                return String(b.employeeName || "").localeCompare(String(a.employeeName || ""));
            }
            if (filters.sort === "checkin_asc") {
                return parseTimeToMinutes(a.checkInTime24) - parseTimeToMinutes(b.checkInTime24);
            }
            if (filters.sort === "checkin_desc") {
                return parseTimeToMinutes(b.checkInTime24) - parseTimeToMinutes(a.checkInTime24);
            }
            if (filters.sort === "production_desc") {
                return parseProductionHours(b) - parseProductionHours(a);
            }
            if (filters.sort === "production_asc") {
                return parseProductionHours(a) - parseProductionHours(b);
            }
            return String(a.employeeName || "").localeCompare(String(b.employeeName || ""));
        });

        return out;
    }

    function rerenderReportRowsFromCache() {
        var date = getSelectedReportDate();
        if (!reportRowsCache || !reportRowsCache.length) {
            renderReportRows([], date);
            renderReportChart([], date);
            return;
        }
        var filtered = filterAndSortReportRows(reportRowsCache);
        renderReportRows(filtered, date);
        renderReportChart(filtered, date);
    }

    function setupReportFilters() {
        var depSel = document.querySelector("[data-attendance-report-filter-department]");
        var statusSel = document.querySelector("[data-attendance-report-filter-status]");
        var sortSel = document.querySelector("[data-attendance-report-sort]");

        function onChange() {
            rerenderReportRowsFromCache();
        }

        if (depSel) depSel.addEventListener("change", onChange);
        if (statusSel) statusSel.addEventListener("change", onChange);
        if (sortSel) sortSel.addEventListener("change", onChange);
    }

    function renderReportMessage(msg) {
        var tbody = document.querySelector("[data-attendance-report-body]");
        if (!tbody) {
            return;
        }
        tbody.innerHTML =
            '<tr><td class="text-center text-muted py-4">' +
            esc(msg) +
            '</td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td></tr>';
        tbody.setAttribute("data-hydrated", "1");
    }

    function applyReportSummary(summary, dateYmd) {
        summary = summary || {};
        var total = typeof summary.totalEmployees === "number" ? summary.totalEmployees : 0;
        var present = typeof summary.present === "number" ? summary.present : 0;
        var absent = typeof summary.absent === "number" ? summary.absent : 0;
        var late = typeof summary.lateLogin === "number" ? summary.lateLogin : 0;
        var perm = typeof summary.permission === "number" ? summary.permission : 0;
        var uninformed = typeof summary.uninformed === "number" ? summary.uninformed : 0;

        function setH4(sel, val) {
            var el = document.querySelector(sel);
            if (el) {
                el.textContent = String(val);
            }
        }
        function setFoot(sel, text) {
            var el = document.querySelector(sel);
            if (el) {
                el.textContent = text;
            }
        }
        function barPct(index, pct) {
            var bars = document.querySelectorAll(".attendance-report-bar .progress-bar");
            if (bars[index]) {
                bars[index].style.width = Math.min(100, Math.max(0, pct)) + "%";
            }
        }

        var label = formatIsoDate(dateYmd);
        setH4("[data-attendance-report-stat-working]", present);
        setH4("[data-attendance-report-stat-leave]", absent);
        setH4("[data-attendance-report-stat-holiday]", late);
        setH4("[data-attendance-report-stat-halfday]", total);
        barPct(0, total ? (present / total) * 100 : 0);
        barPct(1, total ? (absent / total) * 100 : 0);
        barPct(2, present ? (late / present) * 100 : 0);
        barPct(3, total ? 100 : 0);

        setFoot("[data-attendance-report-stat-foot-working]", "API /attendance/admin · " + label);
        setFoot("[data-attendance-report-stat-foot-leave]", "Tidak ada punch in pada tanggal ini");
        setFoot("[data-attendance-report-stat-foot-holiday]", "Dari perhitungan terlambat vs jam masuk");
        setFoot(
            "[data-attendance-report-stat-foot-halfday]",
            "Permission: " + perm + " · Uninformed: " + uninformed + " (belum dimodelkan di baris)"
        );
    }

    function renderReportChart(rows, dateYmd) {
        var el = document.querySelector("#attendance-report-chart");
        if (!el) {
            return;
        }
        if (!window.ApexCharts) {
            el.innerHTML =
                '<div class="rounded border border-dashed text-muted small d-flex align-items-center justify-content-center" style="min-height: 200px;">Chart library not available.</div>';
            return;
        }
        var present = 0;
        var absent = 0;
        var needsReview = 0;
        var totalProd = 0;
        var countProd = 0;
        for (var i = 0; i < rows.length; i++) {
            var key = String(rows[i].statusKey || "").toLowerCase();
            if (key === "present") present += 1;
            else if (key === "needs_review") needsReview += 1;
            else absent += 1;
            var p = parseProductionHours(rows[i]);
            if (p > 0) {
                totalProd += p;
                countProd += 1;
            }
        }
        var avgProd = countProd ? Number((totalProd / countProd).toFixed(2)) : 0;
        var labelDate = formatIsoDate(dateYmd || reportActiveDate || getSelectedReportDate());

        var options = {
            chart: { type: "bar", height: 220, toolbar: { show: false } },
            series: [
                {
                    name: "Count",
                    data: [present, absent, needsReview, avgProd],
                },
            ],
            xaxis: {
                categories: ["Present", "Absent", "Needs Review", "Avg Prod (Hrs)"],
            },
            colors: ["#3b82f6"],
            plotOptions: {
                bar: { borderRadius: 4, columnWidth: "45%" },
            },
            dataLabels: { enabled: true },
            yaxis: {
                labels: {
                    formatter: function (val) { return Number(val).toFixed(val % 1 === 0 ? 0 : 2); },
                },
            },
            tooltip: {
                y: {
                    formatter: function (val, ctx) {
                        return ctx && ctx.dataPointIndex === 3 ? Number(val).toFixed(2) + " Hrs" : String(val) + " employee";
                    },
                },
            },
            title: {
                text: "Attendance Snapshot - " + labelDate,
                align: "left",
                style: { fontSize: "13px", fontWeight: 500 },
            },
        };

        if (reportChart) {
            reportChart.destroy();
        }
        reportChart = new window.ApexCharts(el, options);
        reportChart.render();
    }

    function loadReportAttendance() {
        var path = window.location.pathname || "";
        if (path.indexOf("/attendance-report") !== 0) {
            return;
        }

        var tbody = document.querySelector("[data-attendance-report-body]");
        if (tbody) {
            tbody.innerHTML =
                '<tr><td class="text-center text-muted py-4">Loading…</td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td></tr>';
            tbody.removeAttribute("data-hydrated");
        }

        var dateParam = getSelectedReportDate();
        var mode = getReportSourceMode();
        var snapshotId = getSelectedSnapshotId();
        setReportSourceBadge(mode, snapshotId);

        if (mode === "archive") {
            if (!snapshotId) {
                renderReportMessage("Snapshot ID wajib diisi untuk mode Archive.");
                return;
            }

            apiGet("/v1/hcm/reports/snapshots/" + encodeURIComponent(String(snapshotId)))
                .then(function (payload) {
                    if (!payload || payload.success !== true || !payload.data) {
                        reportRowsCache = [];
                        fillReportDepartmentFilter(reportRowsCache);
                        applyReportSummary({}, dateParam);
                        renderReportChart([], dateParam);
                        renderReportMessage("Snapshot tidak ditemukan atau tidak bisa diakses.");
                        return;
                    }
                    var snapshot = payload.data;
                    if (String(snapshot.reportType || "").toLowerCase() !== "attendance") {
                        reportRowsCache = [];
                        fillReportDepartmentFilter(reportRowsCache);
                        applyReportSummary({}, dateParam);
                        renderReportChart([], dateParam);
                        renderReportMessage("Snapshot ini bukan report attendance.");
                        return;
                    }
                    if (String(snapshot.status || "").toLowerCase() !== "completed") {
                        reportRowsCache = [];
                        fillReportDepartmentFilter(reportRowsCache);
                        applyReportSummary({}, dateParam);
                        renderReportChart([], dateParam);
                        renderReportMessage("Snapshot attendance belum siap digunakan.");
                        return;
                    }
                    var effectiveDate = snapshot.periodEnd || dateParam;
                    reportActiveDate = effectiveDate;
                    var rows = normalizeArchiveAttendanceRows(snapshot, effectiveDate);
                    reportRowsCache = rows;
                    fillReportDepartmentFilter(reportRowsCache);
                    applyReportSummary(normalizeArchiveAttendanceSummary(snapshot), effectiveDate);
                    var filteredArchive = filterAndSortReportRows(reportRowsCache);
                    renderReportRows(filteredArchive, effectiveDate);
                    renderReportChart(filteredArchive, effectiveDate);
                })
                .catch(function (err) {
                    var statusA = err && err.response ? err.response.status : err && err.status ? err.status : 0;
                    var dataA = err && err.response ? err.response.data : err && err.data ? err.data : null;
                    if (window.AuthApi && window.AuthApi.handleUnauthorizedFromApi(statusA, dataA)) {
                        return;
                    }
                    renderReportMessage(formatApiError(dataA, statusA) || "Gagal memuat snapshot archive.");
                });
            return;
        }

        var url = "/v1/hcm/attendance/admin?date=" + encodeURIComponent(dateParam);
        apiGet(url)
            .then(function (payload) {
                if (!payload) {
                    renderReportMessage("Session not found. Redirecting to login…");
                    window.setTimeout(function () {
                        window.location.assign("/login");
                    }, 500);
                    return;
                }
                if (payload.success !== true) {
                    renderReportMessage(formatApiError(payload, 0) || "Unable to load report.");
                    return;
                }
                var meta = payload.meta || {};
                reportActiveDate = meta.date || dateParam;
                applyReportSummary(meta.summary || {}, meta.date || dateParam);
                reportRowsCache = Array.isArray(payload.data) ? payload.data : [];
                
                // DEBUG: Log the raw API response
                console.log('=== API RESPONSE ===');
                console.log('URL:', url);
                console.log('Payload success:', payload.success);
                console.log('Data length:', reportRowsCache.length);
                if (reportRowsCache.length > 0) {
                    console.log('First row from API:', reportRowsCache[0]);
                    console.log('First row checkInLocation:', reportRowsCache[0].checkInLocation);
                    console.log('First row checkOutLocation:', reportRowsCache[0].checkOutLocation);
                }
                
                fillReportDepartmentFilter(reportRowsCache);
                var filtered = filterAndSortReportRows(reportRowsCache);
                renderReportRows(filtered, meta.date || dateParam);
                renderReportChart(filtered, meta.date || dateParam);
            })
            .catch(function (err) {
                var status = err && err.response ? err.response.status : err && err.status ? err.status : 0;
                var data = err && err.response ? err.response.data : err && err.data ? err.data : null;
                if (window.AuthApi && window.AuthApi.handleUnauthorizedFromApi(status, data)) {
                    return;
                }
                renderReportMessage(formatApiError(data, status) || "Failed loading report. Please try again.");
            });
    }

    function setupAttendanceAdminEdit() {
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

    function bindPunch() {
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

    function bindBreakToggle() {
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

    function bindGpsDebug() {
        document.addEventListener("click", function (e) {
            var t = e.target;
            if (!t || !t.closest) {
                return;
            }
            var btn = t.closest("[data-attendance-gps-debug-btn]");
            if (!btn) {
                return;
            }
            e.preventDefault();
            runGpsDebugCheck();
        });
    }

    function initSelfieCapture() {
        var selfieBtn = document.querySelector("[data-attendance-me-selfie-btn]");
        var selfieModalEl = document.getElementById("arcav_attendance_selfie_modal");
        var prereqModalEl = document.getElementById("arcav_attendance_selfie_prereq_modal");
        var videoEl = document.querySelector("[data-selfie-camera-video]");
        var canvasEl = document.querySelector("[data-selfie-preview]");
        var captureBtn = document.querySelector("[data-selfie-capture-btn]");
        var retakeBtn = document.querySelector("[data-selfie-retake-btn]");
        var submitBtn = document.querySelector("[data-selfie-submit-btn]");

        if (!selfieBtn || !selfieModalEl || !videoEl || !canvasEl || !captureBtn || !retakeBtn || !submitBtn) {
            return;
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
                    notify(msg, true);
                })
                .finally(function () {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<i class="ti ti-check me-1"></i>Simpan Selfie';
                });
        });
    }

    function bindAttendanceExtras() {
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
                var rows = filterAndSortReportRows(reportRowsCache || []);
                var headers = ["Employee", "Department", "Date", "Check In", "Check In Location", "Status", "Check Out", "Check Out Location", "Break", "Late", "Overtime", "Production Hours"];
                var dateLabel = formatIsoDate(getSelectedReportDate());
                var data = rows.map(function (r) {
                    return [
                        r.employeeName || "",
                        r.team || "",
                        dateLabel,
                        r.checkIn || "",
                        (r.checkInLocation || r.checkInLocationName || "-"),
                        r.statusLabel || "",
                        r.checkOut || "",
                        (r.checkOutLocation || r.checkOutLocationName || "-"),
                        r.break || "",
                        r.late || "",
                        r.overtime || "",
                        r.productionLabel || "",
                    ];
                });
                downloadCsv("attendance-report.csv", headers, data);
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
                correctionModalState.open = true;
                window.bootstrap.Modal.getOrCreateInstance(modalEl).show();
            }
            var tsExport = e.target.closest("[data-timesheets-export]");
            if (tsExport) {
                e.preventDefault();
                var headers = ["Employee", "Date", "Project", "Assigned Hours", "Worked Hours"];
                var data = (timesheetRowsCache || []).map(function (r) {
                    return [r.employeeName || "", r.dateLabel || "", r.project || "", r.assignedHours || 0, r.workedHours || 0];
                });
                downloadCsv("timesheets.csv", headers, data);
                return;
            }
            var stExport = e.target.closest("[data-schedule-timing-export]");
            if (stExport) {
                e.preventDefault();
                var sh = ["Name", "Job Title", "User Available Timings", "Shift", "Source"];
                var sd = (scheduleTimingRowsCache || []).map(function (r) {
                    return [
                        r.name || "",
                        r.jobTitle || "",
                        r.availableTimings || "",
                        r.shiftName || "",
                        r.source || "",
                    ];
                });
                downloadCsv("schedule-timing.csv", sh, sd);
                return;
            }
            var stEdit = e.target.closest("[data-schedule-timing-edit]");
            if (stEdit) {
                e.preventDefault();
                var uid = stEdit.getAttribute("data-user-id");
                if (!uid) {
                    return;
                }
                var modalEl = document.getElementById("arcav_schedule_timing_edit");
                var form = document.querySelector("[data-schedule-timing-edit-form]");
                if (!modalEl || !form || !(window.bootstrap && window.bootstrap.Modal)) {
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
                    window.bootstrap.Modal.getOrCreateInstance(modalEl).show();
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
                var dateStr = today.getFullYear() + "-" + String(today.getMonth() + 1).padStart(2, "0") + "-" + String(today.getDate()).padStart(2, "0");
                correctionSubmit.disabled = true;
                correctionSubmit.textContent = "Sending...";
                apiPost("/v1/hcm/attendance/me/correction-request", {
                    workDate: dateStr,
                    reason: value,
                }).then(function (payload) {
                    if (!payload || payload.success !== true) {
                        notify(formatApiError(payload, 0) || "Failed request correction.", true);
                        return;
                    }
                    if (modal && window.bootstrap && window.bootstrap.Modal) {
                        window.bootstrap.Modal.getOrCreateInstance(modal).hide();
                    }
                    notify("Correction request sent to admin.", false);
                    loadEmployeeAttendance();
                }).catch(function (err) {
                    var data = err && err.response ? err.response.data : null;
                    var status = err && err.response ? err.response.status : 0;
                    notify(formatApiError(data, status) || "Failed request correction.", true);
                }).finally(function () {
                    correctionSubmit.disabled = false;
                    correctionSubmit.textContent = "Send Request";
                });
            }
        });
    }

    function renderTimesheetsRows(rows) {
        var tbody = document.querySelector("[data-timesheets-body]");
        if (!tbody) return;
        if (!rows.length) {
            tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">No timesheet rows found.</td></tr>';
            tbody.setAttribute("data-hydrated", "1");
            return;
        }
        tbody.innerHTML = rows.map(function (r) {
            return (
                "<tr>" +
                "<td>" + esc(r.employeeName) + "</td>" +
                "<td>" + esc(r.dateLabel || r.date || "-") + "</td>" +
                "<td>" + esc(r.project || "-") + "</td>" +
                "<td>" + esc(String(r.assignedHours != null ? Number(r.assignedHours).toFixed(2) : "0.00")) + "</td>" +
                "<td>" + esc(String(r.workedHours != null ? Number(r.workedHours).toFixed(2) : "0.00")) + "</td>" +
                '<td><span class="text-muted">-</span></td>' +
                "</tr>"
            );
        }).join("");
        tbody.setAttribute("data-hydrated", "1");
    }

    function renderTimesheetsMessage(msg) {
        var tbody = document.querySelector("[data-timesheets-body]");
        if (!tbody) return;
        tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">' + esc(msg) + "</td></tr>";
        tbody.setAttribute("data-hydrated", "1");
    }

    function renderTimesheetPagination(pagination) {
        var foot = document.querySelector("[data-timesheets-pagination]");
        var info = document.querySelector("[data-timesheets-page-info]");
        if (!foot) {
            return;
        }
        if (!pagination || pagination.total == null) {
            foot.style.display = "none";
            return;
        }
        var total = parseInt(pagination.total, 10) || 0;
        var page = parseInt(pagination.page, 10) || 1;
        var perPage = parseInt(pagination.perPage, 10) || 50;
        var totalPages = parseInt(pagination.totalPages, 10) || 1;
        if (totalPages <= 1) {
            foot.style.display = "none";
            return;
        }
        foot.style.display = "";
        if (info) {
            var from = total === 0 ? 0 : (page - 1) * perPage + 1;
            var to = Math.min(page * perPage, total);
            info.textContent = "Menampilkan " + from + "–" + to + " dari " + total;
        }
        var prev = foot.querySelector("[data-timesheets-prev]");
        var next = foot.querySelector("[data-timesheets-next]");
        if (prev) {
            prev.disabled = page <= 1;
        }
        if (next) {
            next.disabled = page >= totalPages;
        }
    }

    function setupTimesheetPaginationControls() {
        var foot = document.querySelector("[data-timesheets-pagination]");
        if (!foot) {
            return;
        }
        var prev = foot.querySelector("[data-timesheets-prev]");
        var next = foot.querySelector("[data-timesheets-next]");
        if (prev && !prev.getAttribute("data-bound")) {
            prev.setAttribute("data-bound", "1");
            prev.addEventListener("click", function () {
                if (timesheetPage > 1) {
                    timesheetPage -= 1;
                    loadTimesheets();
                }
            });
        }
        if (next && !next.getAttribute("data-bound")) {
            next.setAttribute("data-bound", "1");
            next.addEventListener("click", function () {
                timesheetPage += 1;
                loadTimesheets();
            });
        }
    }

    function fillTimesheetsProjectFilter(metaProjects) {
        var sel = document.querySelector("[data-timesheets-filter-project]");
        if (!sel) return;
        var prev = sel.value || "";
        var projects = Array.isArray(metaProjects) ? metaProjects : [];
        var html = ['<option value="">All projects</option>'];
        for (var i = 0; i < projects.length; i++) {
            html.push('<option value="' + esc(projects[i]) + '">' + esc(projects[i]) + "</option>");
        }
        sel.innerHTML = html.join("");
        if (prev) sel.value = prev;
    }

    function getTimesheetDateRange() {
        var from = document.querySelector("[data-timesheets-date-from]");
        var to = document.querySelector("[data-timesheets-date-to]");
        var now = new Date();
        var toDefault = now.getFullYear() + "-" + String(now.getMonth() + 1).padStart(2, "0") + "-" + String(now.getDate()).padStart(2, "0");
        var fromDt = new Date(now.getTime() - (29 * 24 * 60 * 60 * 1000));
        var fromDefault = fromDt.getFullYear() + "-" + String(fromDt.getMonth() + 1).padStart(2, "0") + "-" + String(fromDt.getDate()).padStart(2, "0");
        return {
            from: from && from.value ? from.value : fromDefault,
            to: to && to.value ? to.value : toDefault,
        };
    }

    function loadTimesheets() {
        var path = window.location.pathname || "";
        if (path.indexOf("/timesheets") !== 0) return;

        var tbody = document.querySelector("[data-timesheets-body]");
        if (tbody) {
            tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">Loading timesheets...</td></tr>';
            tbody.removeAttribute("data-hydrated");
        }

        var range = getTimesheetDateRange();
        if (range.from && range.to && range.from > range.to) {
            renderTimesheetsMessage("Date to harus sama atau setelah Date from.");
            return;
        }
        var sortSel = document.querySelector("[data-timesheets-sort]");
        var projectSel = document.querySelector("[data-timesheets-filter-project]");
        var sort = sortSel ? sortSel.value || "date_desc" : "date_desc";
        var project = projectSel ? projectSel.value || "" : "";
        var url =
            "/v1/hcm/timesheets?dateFrom=" +
            encodeURIComponent(range.from) +
            "&dateTo=" +
            encodeURIComponent(range.to) +
            "&sort=" +
            encodeURIComponent(sort) +
            "&page=" +
            encodeURIComponent(String(timesheetPage)) +
            "&perPage=50";
        if (project) url += "&project=" + encodeURIComponent(project);

        apiGet(url).then(function (payload) {
            if (!payload || payload.success !== true) {
                renderTimesheetsMessage(formatApiError(payload, 0) || "Failed loading timesheets.");
                return;
            }
            var pag = (payload.meta && payload.meta.pagination) || {};
            if (pag.totalPages != null && timesheetPage > pag.totalPages && pag.totalPages > 0) {
                timesheetPage = pag.totalPages;
                loadTimesheets();
                return;
            }
            timesheetRowsCache = Array.isArray(payload.data) ? payload.data : [];
            fillTimesheetsProjectFilter(payload.meta && payload.meta.projects ? payload.meta.projects : []);
            renderTimesheetsRows(timesheetRowsCache);
            renderTimesheetPagination(pag);
        }).catch(function (err) {
            var data = err && err.response ? err.response.data : err && err.data ? err.data : null;
            var status = err && err.response ? err.response.status : err && err.status ? err.status : 0;
            renderTimesheetsMessage(formatApiError(data, status) || "Failed loading timesheets.");
        });
    }

    function setupTimesheetFilters() {
        var from = document.querySelector("[data-timesheets-date-from]");
        var to = document.querySelector("[data-timesheets-date-to]");
        var sort = document.querySelector("[data-timesheets-sort]");
        var proj = document.querySelector("[data-timesheets-filter-project]");
        var range = getTimesheetDateRange();
        if (from && !from.value) from.value = range.from;
        if (to && !to.value) to.value = range.to;
        function onChange() {
            timesheetPage = 1;
            loadTimesheets();
        }
        if (from) from.addEventListener("change", onChange);
        if (to) to.addEventListener("change", onChange);
        if (sort) sort.addEventListener("change", onChange);
        if (proj) proj.addEventListener("change", onChange);
    }

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

    function scheduleCalendarLoading(message, isError) {
        var box = document.querySelector("[data-schedule-calendar-loading]");
        var wrap = document.querySelector("[data-schedule-calendar-wrap]");
        if (box) {
            box.textContent = String(message || "");
            box.classList.remove("d-none", "alert-light", "alert-danger");
            box.classList.add(isError ? "alert-danger" : "alert-light");
        }
        if (wrap) {
            if (isError) {
                wrap.classList.add("d-none");
                wrap.removeAttribute("data-hydrated");
            }
        }
    }

    function scheduleCalendarMeta(message) {
        var meta = document.querySelector("[data-schedule-calendar-meta]");
        if (meta) {
            meta.textContent = String(message || "");
        }
    }

    function shiftVisualConfig(label) {
        if (label === "M") {
            return { color: "#2563eb", textColor: "#ffffff", title: "Morning" };
        }
        if (label === "A") {
            return { color: "#f59e0b", textColor: "#111827", title: "Afternoon" };
        }
        if (label === "N") {
            return { color: "#111827", textColor: "#ffffff", title: "Night" };
        }
        return { color: "#6b7280", textColor: "#ffffff", title: "Shift" };
    }

    function buildScheduleCalendarEvents() {
        var events = [];
        var holidayCount = 0;
        var draftCount = 0;

        (Array.isArray(scheduleHolidayRowsCache) ? scheduleHolidayRowsCache : []).forEach(function (holiday) {
            if (!holiday || holiday.isActive === false) {
                return;
            }
            var holidayDate = scheduleDateIso(holiday.holidayDate);
            if (!holidayDate) {
                return;
            }
            holidayCount += 1;
            events.push({
                id: "holiday-bg-" + String(holiday.id || holidayDate),
                start: holidayDate,
                allDay: true,
                display: "background",
                backgroundColor: "rgba(220, 38, 38, 0.12)",
                classNames: ["event-holiday-background"],
            });
            events.push({
                id: "holiday-" + String(holiday.id || holidayDate),
                title: "Holiday: " + String(holiday.title || "Holiday"),
                start: holidayDate,
                allDay: true,
                color: "#dc2626",
                textColor: "#ffffff",
                extendedProps: {
                    eventType: "holiday",
                },
            });
        });

        Object.keys(smartPlannerAssignmentByUserId).forEach(function (userId) {
            var planner = smartPlannerAssignmentByUserId[userId];
            if (!planner || !Array.isArray(planner.assignments)) {
                return;
            }
            planner.assignments.forEach(function (assignment, idx) {
                var dateIso = scheduleDateIso(assignment && assignment.date);
                if (!dateIso) {
                    return;
                }

                var shiftId = String(assignment && assignment.shift_id || "");
                if (shiftId === "OFF") {
                    draftCount += 1;
                    events.push({
                        id: "draft-off-" + String(userId) + "-" + String(idx),
                        title: String(planner.employeeName || "Employee") + " OFF",
                        start: dateIso,
                        allDay: true,
                        color: "#6b7280",
                        textColor: "#ffffff",
                        extendedProps: {
                            eventType: "draft",
                            shiftLabel: "OFF",
                            employeeName: String(planner.employeeName || "Employee"),
                        },
                    });
                    return;
                }

                var meta = plannerShiftMeta(assignment);
                var startDateTime = scheduleEventDateTime(dateIso, assignment && assignment.start_time);
                var endDateTime = scheduleEventDateTime(dateIso, assignment && assignment.end_time);
                if (!startDateTime) {
                    return;
                }
                if (endDateTime && endDateTime <= startDateTime) {
                    var endDate = new Date(endDateTime);
                    if (!isNaN(endDate.getTime())) {
                        endDate.setDate(endDate.getDate() + 1);
                        endDateTime = endDate.toISOString().slice(0, 19);
                    }
                }

                draftCount += 1;
                events.push({
                    id: "draft-" + String(userId) + "-" + String(idx),
                    title: String(planner.employeeName || "Employee") + " " + meta.title,
                    start: startDateTime,
                    end: endDateTime || undefined,
                    allDay: false,
                    color: meta.color,
                    textColor: meta.textColor,
                    extendedProps: {
                        eventType: "draft",
                        shiftLabel: meta.label,
                        employeeName: String(planner.employeeName || "Employee"),
                    },
                });
            });
        });

        return {
            events: events,
            holidayCount: holidayCount,
            draftCount: draftCount,
        };
    }

    function ensureScheduleCalendar() {
        if (scheduleCalendar) {
            return true;
        }

        var el = document.querySelector("[data-schedule-calendar]");
        if (!el) {
            return false;
        }

        if (!window.FullCalendar || !window.FullCalendar.Calendar) {
            scheduleCalendarLoading("Calendar library belum tersedia di halaman ini.", true);
            return false;
        }

        scheduleCalendar = new window.FullCalendar.Calendar(el, {
            initialView: "dayGridMonth",
            height: "auto",
            locale: "id",
            headerToolbar: {
                left: "prev,next today",
                center: "title",
                right: "dayGridMonth,timeGridWeek,listWeek",
            },
            dayMaxEventRows: 3,
            eventDisplay: "block",
            eventDidMount: function (info) {
                var employee = info.event.extendedProps && info.event.extendedProps.employeeName
                    ? String(info.event.extendedProps.employeeName)
                    : "";
                var label = info.event.extendedProps && info.event.extendedProps.shiftLabel
                    ? " (" + String(info.event.extendedProps.shiftLabel) + ")"
                    : "";
                if (employee) {
                    info.el.setAttribute("title", employee + label);
                }
            },
        });
        scheduleCalendar.render();

        var loading = document.querySelector("[data-schedule-calendar-loading]");
        var wrap = document.querySelector("[data-schedule-calendar-wrap]");
        if (loading) {
            loading.classList.add("d-none");
        }
        if (wrap) {
            wrap.classList.remove("d-none");
            wrap.setAttribute("data-hydrated", "1");
        }

        return true;
    }

    function renderScheduleCalendar() {
        if (scheduleTimingView !== "calendar") {
            return;
        }
        if (!ensureScheduleCalendar()) {
            return;
        }

        var payload = buildScheduleCalendarEvents();
        scheduleCalendar.removeAllEvents();
        scheduleCalendar.addEventSource(payload.events);

        var scopeMeta = smartPlannerScopeMeta
            ? " Scope draft: " + smartPlannerScopeMeta
            : " Scope draft mengikuti filter planner terakhir.";
        scheduleCalendarMeta(
            "Holiday aktif: " +
            String(payload.holidayCount) +
            " hari, Draft shift planner: " +
            String(payload.draftCount) +
            " event." +
            scopeMeta
        );
        if (!payload.events.length) {
            scheduleCalendarLoading("Belum ada data kalender. Generate planner dulu untuk melihat draft shift.", false);
        }
    }

    function loadScheduleCalendarHolidays() {
        var path = window.location.pathname || "";
        if (path.indexOf("/schedule-timing") !== 0) {
            return;
        }
        if (!document.querySelector("[data-schedule-calendar]") && !document.querySelector("[data-schedule-view-toggle]")) {
            return;
        }
        apiGet("/v1/hcm/holidays")
            .then(function (payload) {
                if (!payload || payload.success !== true || !Array.isArray(payload.data)) {
                    scheduleHolidayRowsCache = [];
                    return;
                }
                scheduleHolidayRowsCache = payload.data;
                renderScheduleCalendar();
            })
            .catch(function () {
                scheduleHolidayRowsCache = [];
            });
    }

    function setupScheduleViewMode() {
        var path = window.location.pathname || "";
        if (path.indexOf("/schedule-timing") !== 0) {
            return;
        }

        var toggles = Array.prototype.slice.call(document.querySelectorAll("[data-schedule-view-toggle]"));
        var listPanel = document.querySelector('[data-schedule-view-panel="list"]');
        var calendarPanel = document.querySelector('[data-schedule-view-panel="calendar"]');
        var pagination = document.querySelector("[data-schedule-timing-pagination]");
        if (!toggles.length || !listPanel || !calendarPanel) {
            return;
        }

        function applyView(view) {
            scheduleTimingView = view === "calendar" ? "calendar" : "list";
            toggles.forEach(function (btn) {
                var isActive = String(btn.getAttribute("data-schedule-view-toggle") || "") === scheduleTimingView;
                btn.classList.toggle("active", isActive);
            });
            listPanel.classList.toggle("d-none", scheduleTimingView !== "list");
            calendarPanel.classList.toggle("d-none", scheduleTimingView !== "calendar");
            if (pagination) {
                if (scheduleTimingView === "calendar") {
                    pagination.style.display = "none";
                } else {
                    renderScheduleTimingPagination(scheduleTimingPaginationCache);
                }
            }
            if (scheduleTimingView === "calendar") {
                renderScheduleCalendar();
            }
        }

        toggles.forEach(function (btn) {
            if (btn.getAttribute("data-bound") === "1") {
                return;
            }
            btn.setAttribute("data-bound", "1");
            btn.addEventListener("click", function () {
                var view = String(btn.getAttribute("data-schedule-view-toggle") || "list");
                applyView(view);
            });
        });

        applyView("list");
    }

    function renderScheduleTimingRows(rows) {
        var tbody = document.querySelector("[data-schedule-timing-body]");
        if (!tbody) return;
        var sourceRows = Array.isArray(rows) ? rows : [];
        if (scheduleTimingAiOnly) {
            sourceRows = sourceRows.filter(function (r) {
                return !!smartPlannerAssignmentByUserId[String(r && r.userId || "")];
            });
        }
        if (!sourceRows.length) {
            tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-4">No schedule timings found.</td></tr>';
            tbody.setAttribute("data-hydrated", "1");
            return;
        }
        tbody.innerHTML = sourceRows.map(function (r) {
            var shiftBadge = r.shiftName
                ? ' <span class="badge bg-light text-dark border ms-1">' + esc(r.shiftName) + "</span>"
                : "";
            var sm = r.startMinutes != null ? String(r.startMinutes) : "0";
            var em = r.endMinutes != null ? String(r.endMinutes) : "0";
            var sid = r.shiftId != null && r.shiftId !== "" ? String(r.shiftId) : "";
            var aiPlan = smartPlannerAssignmentByUserId[String(r.userId || "")];
            var aiPlanBadge = "";
            if (aiPlan) {
                aiPlanBadge =
                    ' <span class="badge badge-warning-transparent ms-1">AI Draft 24h</span>' +
                    ' <span class="text-muted small d-block mt-1">M:' +
                    esc(String(aiPlan.morningCount || 0)) +
                    " A:" +
                    esc(String(aiPlan.afternoonCount || 0)) +
                    " N:" +
                    esc(String(aiPlan.nightCount || 0)) +
                    " OFF:" +
                    esc(String(aiPlan.offDays || 0)) +
                    "</span>";
            }
            return (
                "<tr>" +
                '<td><div class="form-check form-check-md"><input class="form-check-input" type="checkbox"></div></td>' +
                "<td>" + esc(r.name) + "</td>" +
                "<td>" + esc(r.jobTitle) + "</td>" +
                "<td>" +
                esc(r.availableTimings) +
                shiftBadge +
                (r.source === "manual" ? ' <span class="badge badge-info-transparent ms-1">Manual</span>' : "") +
                aiPlanBadge +
                "</td>" +
                '<td><a href="#" data-schedule-timing-edit data-user-id="' +
                esc(String(r.userId || "")) +
                '" data-name="' +
                esc(r.name || "") +
                '" data-start-minutes="' +
                esc(sm) +
                '" data-end-minutes="' +
                esc(em) +
                '" data-shift-id="' +
                esc(sid) +
                '" data-source="' +
                esc(String(r.source || "auto")) +
                '"><i class="ti ti-edit"></i></a></td>' +
                "</tr>"
            );
        }).join("");
        tbody.setAttribute("data-hydrated", "1");
        renderScheduleCalendar();
    }

    function renderScheduleTimingMessage(msg) {
        var tbody = document.querySelector("[data-schedule-timing-body]");
        if (!tbody) return;
        tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-4">' + esc(msg) + "</td></tr>";
        tbody.setAttribute("data-hydrated", "1");
        renderScheduleCalendar();
    }

    function renderScheduleTimingPagination(pagination) {
        scheduleTimingPaginationCache = pagination || null;
        var foot = document.querySelector("[data-schedule-timing-pagination]");
        var info = document.querySelector("[data-schedule-timing-page-info]");
        if (!foot) {
            return;
        }
        if (!pagination || pagination.total == null) {
            foot.style.display = "none";
            return;
        }
        var total = parseInt(pagination.total, 10) || 0;
        var page = parseInt(pagination.page, 10) || 1;
        var perPage = parseInt(pagination.perPage, 10) || 50;
        var totalPages = parseInt(pagination.totalPages, 10) || 1;
        if (totalPages <= 1) {
            foot.style.display = "none";
            return;
        }
        foot.style.display = scheduleTimingView === "calendar" ? "none" : "";
        if (info) {
            var from = total === 0 ? 0 : (page - 1) * perPage + 1;
            var to = Math.min(page * perPage, total);
            info.textContent = "Menampilkan " + from + "–" + to + " dari " + total;
        }
        var prev = foot.querySelector("[data-schedule-timing-prev]");
        var next = foot.querySelector("[data-schedule-timing-next]");
        if (prev) {
            prev.disabled = page <= 1;
        }
        if (next) {
            next.disabled = page >= totalPages;
        }
    }

    function setupScheduleTimingPaginationControls() {
        var foot = document.querySelector("[data-schedule-timing-pagination]");
        if (!foot) {
            return;
        }
        var prev = foot.querySelector("[data-schedule-timing-prev]");
        var next = foot.querySelector("[data-schedule-timing-next]");
        if (prev && !prev.getAttribute("data-bound")) {
            prev.setAttribute("data-bound", "1");
            prev.addEventListener("click", function () {
                if (scheduleTimingPage > 1) {
                    scheduleTimingPage -= 1;
                    loadScheduleTiming();
                }
            });
        }
        if (next && !next.getAttribute("data-bound")) {
            next.setAttribute("data-bound", "1");
            next.addEventListener("click", function () {
                scheduleTimingPage += 1;
                loadScheduleTiming();
            });
        }
    }

    function loadScheduleTiming() {
        var path = window.location.pathname || "";
        if (path.indexOf("/schedule-timing") !== 0) return;
        var tbody = document.querySelector("[data-schedule-timing-body]");
        if (tbody) {
            tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-4">Loading schedule timings...</td></tr>';
            tbody.removeAttribute("data-hydrated");
        }
        var searchEl = document.querySelector("[data-schedule-timing-search]");
        var sortEl = document.querySelector("[data-schedule-timing-sort]");
        var search = searchEl ? String(searchEl.value || "").trim() : "";
        var sort = sortEl ? String(sortEl.value || "name_asc") : "name_asc";
        var url =
            "/v1/hcm/schedule-timing?sort=" +
            encodeURIComponent(sort) +
            "&page=" +
            encodeURIComponent(String(scheduleTimingPage)) +
            "&perPage=50";
        if (search) url += "&search=" + encodeURIComponent(search);
        apiGet(url).then(function (payload) {
            if (!payload || payload.success !== true) {
                renderScheduleTimingMessage(formatApiError(payload, 0) || "Failed loading schedule timing.");
                return;
            }
            var pag = (payload.meta && payload.meta.pagination) || {};
            if (pag.totalPages != null && scheduleTimingPage > pag.totalPages && pag.totalPages > 0) {
                scheduleTimingPage = pag.totalPages;
                loadScheduleTiming();
                return;
            }
            scheduleTimingRowsCache = Array.isArray(payload.data) ? payload.data : [];
            renderScheduleTimingRows(scheduleTimingRowsCache);
            renderScheduleTimingPagination(pag);
        }).catch(function (err) {
            var data = err && err.response ? err.response.data : err && err.data ? err.data : null;
            var status = err && err.response ? err.response.status : err && err.status ? err.status : 0;
            renderScheduleTimingMessage(formatApiError(data, status) || "Failed loading schedule timing.");
        });
    }

    function setupScheduleTimingFilters() {
        var searchEl = document.querySelector("[data-schedule-timing-search]");
        var sortEl = document.querySelector("[data-schedule-timing-sort]");
        var aiOnlyEl = document.querySelector("[data-schedule-timing-ai-only]");
        if (searchEl) {
            var timer = null;
            searchEl.addEventListener("input", function () {
                if (timer) window.clearTimeout(timer);
                timer = window.setTimeout(function () {
                    scheduleTimingPage = 1;
                    loadScheduleTiming();
                }, 250);
            });
        }
        if (sortEl) {
            sortEl.addEventListener("change", function () {
                scheduleTimingPage = 1;
                loadScheduleTiming();
            });
        }
        if (aiOnlyEl && !aiOnlyEl.getAttribute("data-bound")) {
            aiOnlyEl.setAttribute("data-bound", "1");
            aiOnlyEl.addEventListener("change", function () {
                scheduleTimingAiOnly = !!aiOnlyEl.checked;
                renderScheduleTimingRows(scheduleTimingRowsCache);
            });
        }
    }

    function getCurrentWeekStartIso() {
        var now = new Date();
        var day = now.getDay();
        var diff = day === 0 ? -6 : 1 - day;
        now.setDate(now.getDate() + diff);
        var y = now.getFullYear();
        var m = String(now.getMonth() + 1).padStart(2, "0");
        var d = String(now.getDate()).padStart(2, "0");
        return y + "-" + m + "-" + d;
    }

    function setSmartPlannerFeedback(message, isError) {
        var feedback = document.querySelector("[data-smart-planner-feedback]");
        if (!feedback) {
            return;
        }
        feedback.textContent = String(message || "");
        feedback.classList.remove("d-none", "alert-light", "alert-danger", "alert-success");
        feedback.classList.add(isError ? "alert-danger" : "alert-success");
    }

    function findScheduleShiftById(shiftId) {
        var target = parseInt(String(shiftId || ""), 10);
        if (!Number.isFinite(target)) {
            return null;
        }
        for (var i = 0; i < scheduleShiftsCache.length; i++) {
            var row = scheduleShiftsCache[i];
            if (parseInt(String(row && row.id || ""), 10) === target) {
                return row;
            }
        }
        return null;
    }

    function plannerShiftMeta(assignment) {
        var shiftIdRaw = String(assignment && assignment.shift_id || "");
        if (shiftIdRaw.toUpperCase() === "OFF") {
            return { label: "OFF", title: "Off", color: "#6b7280", textColor: "#ffffff", shiftName: "OFF" };
        }

        var shift = findScheduleShiftById(shiftIdRaw);
        var shiftName = String((shift && shift.name) || "");
        var shiftType = String((shift && shift.shiftType) || "").toLowerCase();
        var start = String((assignment && assignment.start_time) || (shift && shift.startTime) || "");
        var h = parseInt(start.slice(0, 2), 10);
        var normalizedName = shiftName.toLowerCase();

        var label = "S";
        var title = shiftName || "Shift";

        if (shiftType === "night" || normalizedName.indexOf("night") !== -1 || normalizedName.indexOf("malam") !== -1 || (Number.isFinite(h) && (h >= 20 || h < 5))) {
            label = "N";
            title = shiftName || "Night";
        } else if (shiftType === "afternoon" || normalizedName.indexOf("afternoon") !== -1 || normalizedName.indexOf("siang") !== -1 || (Number.isFinite(h) && h >= 12 && h < 20)) {
            label = "A";
            title = shiftName || "Afternoon";
        } else if (shiftType === "morning" || normalizedName.indexOf("morning") !== -1 || normalizedName.indexOf("pagi") !== -1 || Number.isFinite(h)) {
            label = "M";
            title = shiftName || "Morning";
        }

        var visual = shiftVisualConfig(label);
        return {
            label: label,
            title: title,
            color: visual.color,
            textColor: visual.textColor,
            shiftName: shiftName || (shiftIdRaw ? ("#" + shiftIdRaw) : "Shift"),
        };
    }

    function formatPlannerPattern(assignments) {
        if (!Array.isArray(assignments) || assignments.length === 0) {
            return "-";
        }
        return assignments
            .map(function (a) {
                if (String(a && a.shift_id || "") === "OFF") {
                    return "OFF";
                }
                return plannerShiftMeta(a).label;
            })
            .join(" | ");
    }

    function plannerDateFromIso(dateIso) {
        var raw = String(dateIso || "").trim();
        if (!/^\d{4}-\d{2}-\d{2}$/.test(raw)) {
            return null;
        }
        var parsed = new Date(raw + "T00:00:00");
        if (isNaN(parsed.getTime())) {
            return null;
        }
        return parsed;
    }

    function plannerIsoFromDate(dateObj) {
        if (!(dateObj instanceof Date) || isNaN(dateObj.getTime())) {
            return "";
        }
        var y = dateObj.getFullYear();
        var m = String(dateObj.getMonth() + 1).padStart(2, "0");
        var d = String(dateObj.getDate()).padStart(2, "0");
        return y + "-" + m + "-" + d;
    }

    function plannerEndOfYearIso(weekStartIso) {
        var source = plannerDateFromIso(weekStartIso) || new Date();
        return String(source.getFullYear()) + "-12-31";
    }

    function plannerBuildWeekStarts(weekStartIso, endIso) {
        var startDate = plannerDateFromIso(weekStartIso);
        var endDate = plannerDateFromIso(endIso);
        if (!startDate || !endDate || endDate < startDate) {
            return [];
        }

        var weeks = [];
        var cursor = new Date(startDate.getTime());
        while (cursor <= endDate) {
            weeks.push(plannerIsoFromDate(cursor));
            cursor.setDate(cursor.getDate() + 7);
        }

        return weeks;
    }

    function mergePlannerAssignmentRows(results) {
        var byUser = {};

        (Array.isArray(results) ? results : []).forEach(function (result) {
            var rows = result && result.schedule_generation && Array.isArray(result.schedule_generation.weekly_schedule)
                ? result.schedule_generation.weekly_schedule
                : [];

            rows.forEach(function (row) {
                var userId = String(row && row.employee_id || "");
                if (!userId) {
                    return;
                }

                if (!byUser[userId]) {
                    byUser[userId] = {
                        employee_id: userId,
                        employee_name: String(row && row.employee_name || "Employee"),
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
                        return String(a && a.date || "").localeCompare(String(b && b.date || ""));
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
                    var shiftId = String(assignment && assignment.shift_id || "");
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
                    employeeName: String(row && row.employee_name || "Employee"),
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
        for (var i = 0; i < scheduleTimingRowsCache.length; i++) {
            var row = scheduleTimingRowsCache[i];
            if (parseInt(String(row && row.userId || ""), 10) === target) {
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
        tbody.innerHTML = rows.map(function (row) {
            var beforeRow = scheduleTimingRowByUserId(row.userId);
            var beforeShiftId = parseInt(String(beforeRow && beforeRow.shiftId || ""), 10);
            var beforeShiftName = String(beforeRow && beforeRow.shiftName || "Custom / Auto");
            var beforeTiming = String(beforeRow && beforeRow.availableTimings || "-");
            var afterShift = findScheduleShiftById(row.shiftId);
            var afterShiftName = String(afterShift && afterShift.name || ("Shift #" + String(row.shiftId)));
            var afterSlot = String(afterShift && afterShift.slotLabel || "-");

            var changed = !Number.isFinite(beforeShiftId) || beforeShiftId !== row.shiftId;
            if (changed) {
                changedCount += 1;
            }

            return (
                "<tr>" +
                "<td>" + esc(row.employeeName) + "</td>" +
                "<td><span class=\"fw-semibold\">" + esc(beforeShiftName) + "</span><div class=\"text-muted small\">" + esc(beforeTiming) + "</div></td>" +
                "<td><span class=\"fw-semibold\">" + esc(afterShiftName) + "</span><div class=\"text-muted small\">" + esc(afterSlot) + "</div></td>" +
                "<td>" + (changed ? '<span class=\"badge bg-warning-subtle text-warning\">Changed</span>' : '<span class=\"badge bg-success-subtle text-success\">No change</span>') + "</td>" +
                "</tr>"
            );
        }).join("");

        if (meta) {
            meta.textContent = "Dominant shift preview: " + String(changedCount) + " perubahan dari " + String(rows.length) + " user scope draft.";
        }
    }

    function plannerHolidayDateMap() {
        var map = {};
        (Array.isArray(scheduleHolidayRowsCache) ? scheduleHolidayRowsCache : []).forEach(function (holiday) {
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
        var rows = Array.isArray(catalog) && catalog.length ? catalog : smartPlannerTransitionCatalog;
        var selectedSet = new Set(Array.isArray(selectedKeys) ? selectedKeys : []);
        holder.innerHTML = rows.map(function (key) {
            var parts = String(key || "").split(":");
            var from = String(parts[0] || "").trim();
            var to = String(parts[1] || "").trim();
            if (!from || !to) {
                return "";
            }
            var checked = selectedSet.has(from + ":" + to) ? ' checked' : '';
            return (
                '<label class="form-check form-check-md me-3 mb-2">' +
                '<input class="form-check-input" type="checkbox" data-smart-planner-transition-key="' + esc(from + ":" + to) + '"' + checked + '>' +
                '<span class="form-check-label text-capitalize">Block ' + esc(from) + ' -> ' + esc(to) + '</span>' +
                '</label>'
            );
        }).join("") || '<div class="text-muted small">Tidak ada transition key tersedia.</div>';
    }

    function readPlannerTransitionSelection() {
        return Array.prototype.slice.call(document.querySelectorAll("[data-smart-planner-transition-key]"))
            .filter(function (el) { return !!el.checked; })
            .map(function (el) { return String(el.getAttribute("data-smart-planner-transition-key") || "").trim().toLowerCase(); })
            .filter(function (key) { return key.indexOf(":") > 0; });
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
        var blockedTransitionSet = new Set(Array.isArray(transitionKeys)
            ? transitionKeys.map(function (key) { return String(key || "").toLowerCase(); }).filter(function (key) { return key.indexOf(":") > 0; })
            : []);

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
                    return String(a && a.date || "").localeCompare(String(b && b.date || ""));
                });

            assignments.forEach(function (assignment) {
                if (String(assignment && assignment.shift_id || "").toUpperCase() === "OFF") {
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
                var currentShiftId = String(current && current.shift_id || "").toUpperCase();
                var nextShiftId = String(next && next.shift_id || "").toUpperCase();
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
                var curType = curMeta.label === "N" ? "night" : (curMeta.label === "A" ? "afternoon" : (curMeta.label === "M" ? "morning" : ""));
                var nextType = nextMeta.label === "N" ? "night" : (nextMeta.label === "A" ? "afternoon" : (nextMeta.label === "M" ? "morning" : ""));
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
            return smartPlannerConflictSummary;
        }

        var summary = analyzePlannerConflicts(result, payload || {});
        smartPlannerConflictSummary = {
            total: summary.violationCount + summary.unmetCoverageCount + summary.holidayConflictCount + summary.restConflictCount + summary.transitionConflictCount,
            critical: summary.criticalCount,
        };

        list.innerHTML = [
            "Hard violations dari planner: " + String(summary.violationCount),
            "Unmet coverage: " + String(summary.unmetCoverageCount),
            "Holiday overlap (butuh review kebijakan): " + String(summary.holidayConflictCount),
            "Rest gap < rule minimum: " + String(summary.restConflictCount),
            "Illegal transition (matrix rules): " + String(summary.transitionConflictCount),
        ].map(function (line) {
            return "<li>" + esc(line) + "</li>";
        }).join("");

        if (meta) {
            meta.textContent = summary.criticalCount > 0
                ? "Terdeteksi " + String(summary.criticalCount) + " conflict kritikal. Force apply wajib dicentang jika tetap publish."
                : "Tidak ada conflict kritikal. Publish dominant shift aman dilanjutkan.";
        }

        return smartPlannerConflictSummary;
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
        var hasDraftAssignments = !!(result && result.schedule_generation && Array.isArray(result.schedule_generation.weekly_schedule) && result.schedule_generation.weekly_schedule.length);
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

        var hasCriticalConflict = (smartPlannerConflictSummary.critical || 0) > 0;
        var forceApply = !!(forceApplyEl && forceApplyEl.checked);

        if (applyBtn) {
            applyBtn.disabled = hasCriticalConflict && !forceApply;
        }
        if (applyDailyBtn) {
            applyDailyBtn.disabled = hasCriticalConflict && !forceApply;
        }
        applyMeta.textContent =
            "Siap publish " + String(rows.length) +
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
            }).then(function (response) {
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
            }).catch(function (err) {
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
                var userId = String(row && row.employee_id || "");
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

                byUser[userId].total_work_days += Number(row && row.total_work_days || 0);
                byUser[userId].late_count += Number(row && row.late_count || 0);
                byUser[userId].early_leave_count += Number(row && row.early_leave_count || 0);
                byUser[userId].absent_count += Number(row && row.absent_count || 0);
                byUser[userId].overtime_minutes += Number(row && row.overtime_minutes || 0);
                byUser[userId].compliance_score += Number(row && row.compliance_score || 0);
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
                var title = String(row && row.title || "Suggestion");
                var reason = String(row && row.reason || "");
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
                validation_status: (hasInvalid || violations.length > 0 || unmet.length > 0) ? "invalid" : "valid",
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
            explanation: totalWeeks > 1
                ? "Batch planner selesai untuk " + String(totalWeeks) + " minggu sampai akhir tahun. Hasil ringkas ditampilkan sebagai agregasi lintas minggu."
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

            return apiPost("/v1/hcm/smart-attendance-shifting/generate", payload)
                .then(function (response) {
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

    function buildPlannerAssignmentIndex(weeklySchedule) {
        var index = {};
        (Array.isArray(weeklySchedule) ? weeklySchedule : []).forEach(function (row) {
            var userId = String(row && row.employee_id || "");
            if (!userId) {
                return;
            }

            var assignments = Array.isArray(row && row.assignments) ? row.assignments : [];
            var workDays = 0;
            var offDays = 0;
            var morningCount = 0;
            var afternoonCount = 0;
            var nightCount = 0;

            assignments.forEach(function (a) {
                var shiftId = String(a && a.shift_id || "");
                if (shiftId === "OFF") {
                    offDays += 1;
                    return;
                }
                workDays += 1;
                var label = plannerShiftMeta(a).label;
                if (label === "M") {
                    morningCount += 1;
                } else if (label === "A") {
                    afternoonCount += 1;
                } else if (label === "N") {
                    nightCount += 1;
                }
            });

            index[userId] = {
                employeeName: String(row && row.employee_name || "Employee"),
                assignments: assignments,
                workDays: workDays,
                offDays: offDays,
                morningCount: morningCount,
                afternoonCount: afternoonCount,
                nightCount: nightCount,
            };
        });

        return index;
    }

    function renderSmartPlannerAssignmentPreview(result) {
        var body = document.querySelector("[data-smart-planner-assignment-body]");
        var meta = document.querySelector("[data-smart-planner-assignment-meta]");
        if (!body) {
            return;
        }

        var weeklySchedule = result && result.schedule_generation && Array.isArray(result.schedule_generation.weekly_schedule)
            ? result.schedule_generation.weekly_schedule
            : [];
        smartPlannerAssignmentByUserId = buildPlannerAssignmentIndex(weeklySchedule);

        var rows = Object.keys(smartPlannerAssignmentByUserId)
            .map(function (userId) {
                var item = smartPlannerAssignmentByUserId[userId];
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

        body.innerHTML = rows.map(function (row) {
            return (
                "<tr>" +
                "<td>" + esc(row.employeeName) + "</td>" +
                "<td><span class=\"badge bg-success-subtle text-success\">" + esc(String(row.workDays)) + "</span></td>" +
                "<td><span class=\"badge bg-secondary-subtle text-secondary\">" + esc(String(row.offDays)) + "</span></td>" +
                "<td><span class=\"badge bg-dark-subtle text-dark\">" + esc(String(row.nightCount)) + "</span></td>" +
                "<td class=\"small text-muted\">" + esc(row.pattern) + "</td>" +
                "</tr>"
            );
        }).join("");

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

    function renderSmartPlannerResult(result) {
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

        renderSimpleList(violationsEl, violations, function (row) {
            var code = row && row.code ? String(row.code) : "RULE";
            var message = row && row.message ? String(row.message) : "Violation detected";
            return code + ": " + message;
        });

        renderSimpleList(suggestionsEl, suggestions, function (row) {
            var title = row && row.title ? String(row.title) : "Suggestion";
            var reason = row && row.reason ? String(row.reason) : "";
            return reason ? title + " - " + reason : title;
        });

        renderSmartPlannerAssignmentPreview(result);
        renderPlannerDiffPreview(result);
        renderPlannerConflictPreview(result, smartPlannerLastPayload || {});
        if (Array.isArray(scheduleTimingRowsCache) && scheduleTimingRowsCache.length > 0) {
            renderScheduleTimingRows(scheduleTimingRowsCache);
        }
        renderScheduleCalendar();
        updatePlannerApplyState(result);

        wrap.classList.remove("d-none");
    }

    function bindSmartPlanner() {
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
            if (smartPlannerLastResult) {
                renderSmartPlannerResult(smartPlannerLastResult);
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
                    smartPlannerSettingsCache = payload.data;
                    smartPlannerTransitionCatalog = Array.isArray(payload.data.transitionCatalog) && payload.data.transitionCatalog.length
                        ? payload.data.transitionCatalog.map(function (k) { return String(k || '').toLowerCase(); })
                        : smartPlannerTransitionCatalog;
                    smartPlannerForbiddenTransitionKeys = Array.isArray(payload.data.forbiddenTransitions) && payload.data.forbiddenTransitions.length
                        ? payload.data.forbiddenTransitions.map(function (k) { return String(k || '').toLowerCase(); })
                        : ['night:morning'];
                    applyPlannerSettingsToForm(form, payload.data);
                    renderPlannerTransitionMatrix(smartPlannerTransitionCatalog, smartPlannerForbiddenTransitionKeys);
                    setPlannerSettingsFeedback('Default tenant berhasil dimuat.', false);
                })
                .catch(function () {
                    renderPlannerTransitionMatrix(smartPlannerTransitionCatalog, smartPlannerForbiddenTransitionKeys);
                    setPlannerSettingsFeedback('Gagal load planner defaults, gunakan fallback lokal.', true);
                });
        }

        function currentCategory() {
            return shiftCategoryEl && shiftCategoryEl.value ? String(shiftCategoryEl.value) : "office_hour";
        }

        function currentScope() {
            return scopeEl && scopeEl.value ? String(scopeEl.value) : "legacy";
        }

        function currentHorizon() {
            return horizonEl && horizonEl.value ? String(horizonEl.value) : "single_week";
        }

        function plannerSelectionSummary() {
            var mode = currentCategory();
            var scope = currentScope();
            var horizon = currentHorizon();
            var modeLabel = mode === "shifting_24h" ? "Shifting 24 Jam" : (mode === "hybrid" ? "Hybrid" : "Office Hour");
            var selectedDepartmentLabel = departmentEl && departmentEl.selectedOptions && departmentEl.selectedOptions[0]
                ? String(departmentEl.selectedOptions[0].textContent || "").trim()
                : "";
            var scopeLabel = scope === "department"
                ? (selectedDepartmentLabel && selectedDepartmentLabel !== "Pilih departemen" ? "departemen " + selectedDepartmentLabel : "departemen tertentu")
                : (scope === "custom" ? "user ID pilihan" : "semua employee aktif");
            var horizonLabel = horizon === "end_of_year" ? "batch sampai 31 Desember" : "1 minggu";
            return "Mode: " + modeLabel + ". Scope: " + scopeLabel + ". Horizon: " + horizonLabel + ".";
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
            var currentValue = String(departmentEl.value || "");
            var seen = {};
            var options = [{ value: "", label: "Pilih departemen" }];

            (Array.isArray(rows) ? rows : []).forEach(function (row) {
                var departmentId = row && row.departmentId != null ? String(row.departmentId) : "";
                var departmentName = row && row.departmentName ? String(row.departmentName).trim() : "";
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

            departmentEl.innerHTML = options.map(function (option) {
                var selected = option.value === currentValue ? ' selected' : '';
                return '<option value="' + esc(option.value) + '"' + selected + '>' + esc(option.label) + '</option>';
            }).join('');
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
            return String(raw || "")
                .split(/[\s,]+/)
                .map(function (item) { return parseInt(item, 10); })
                .filter(function (n) { return !isNaN(n) && n > 0; });
        }

        function resolvePlannerScope() {
            var scope = currentScope();
            if (scope === "legacy") {
                return Promise.resolve({
                    employeeIds: null,
                    message: "Scope mengikuti perilaku default planner.",
                });
            }
            if (scope === "custom") {
                var customIds = parseCustomIds(customIdsEl && customIdsEl.value || "");
                if (!customIds.length) {
                    return Promise.reject({ plannerMessage: "Isi minimal satu user ID untuk custom scope." });
                }
                return Promise.resolve({
                    employeeIds: customIds,
                    message: "Scope custom aktif: " + String(customIds.length) + " karyawan.",
                });
            }

            return loadPlannerEmployeeDirectory().then(function (rows) {
                if (!rows.length) {
                    return Promise.reject({ plannerMessage: "Employee list kosong di tenant aktif." });
                }

                if (scope === "all") {
                    var allIds = rows
                        .map(function (row) { return parseInt(row && row.userId != null ? row.userId : row && row.id, 10); })
                        .filter(function (id) { return !isNaN(id) && id > 0; });
                    return {
                        employeeIds: allIds,
                        message: "Scope semua karyawan aktif: " + String(allIds.length) + " orang.",
                    };
                }

                var selectedDepartmentId = parseInt(departmentEl && departmentEl.value ? departmentEl.value : "", 10);
                if (isNaN(selectedDepartmentId) || selectedDepartmentId <= 0) {
                    return Promise.reject({ plannerMessage: "Pilih departemen untuk sasaran draft planner." });
                }

                var filteredIds = rows
                    .filter(function (row) {
                        return parseInt(row && row.departmentId != null ? row.departmentId : 0, 10) === selectedDepartmentId;
                    })
                    .map(function (row) { return parseInt(row && row.userId != null ? row.userId : row && row.id, 10); })
                    .filter(function (id) { return !isNaN(id) && id > 0; });

                var departmentLabel = departmentEl && departmentEl.selectedOptions && departmentEl.selectedOptions[0]
                    ? String(departmentEl.selectedOptions[0].textContent || "").trim()
                    : "departemen terpilih";

                if (!filteredIds.length) {
                    return Promise.reject({ plannerMessage: 'Tidak ada employee aktif pada departemen "' + departmentLabel + '".' });
                }

                return {
                    employeeIds: filteredIds,
                    message: "Scope departemen \"" + departmentLabel + "\": " + String(filteredIds.length) + " karyawan.",
                };
            });
        }

        function syncModeUi() {
            var mode = currentCategory();
            var isOffice = mode === "office_hour";
            var scope = currentScope();
            var horizon = currentHorizon();
            if (nightRuleWrap) {
                nightRuleWrap.classList.toggle("d-none", isOffice);
            }
            if (restRuleWrap) {
                restRuleWrap.classList.toggle("d-none", false);
            }
            if (departmentWrap) {
                departmentWrap.classList.toggle("d-none", scope !== "department");
            }
            if (scope === "department") {
                ensurePlannerDepartmentOptionsLoaded().then(function () {
                    if (scopeMetaEl) {
                        scopeMetaEl.textContent = plannerSelectionSummary();
                    }
                }).catch(function () {
                    if (scopeMetaEl) {
                        scopeMetaEl.textContent = "Departemen tidak bisa dimuat. Cek employee directory tenant aktif.";
                    }
                });
            }
            if (customIdsWrap) {
                customIdsWrap.classList.toggle("d-none", scope !== "custom");
            }
            if (endDateWrap) {
                endDateWrap.classList.toggle("d-none", horizon !== "end_of_year");
            }
            if (modeHintEl) {
                if (mode === "shifting_24h") {
                    modeHintEl.textContent = "Pilihan manual untuk rotasi shift. Bukan auto dari master shift.";
                } else if (mode === "hybrid") {
                    modeHintEl.textContent = "Pilihan manual untuk gabungan office hour dan shift.";
                } else {
                    modeHintEl.textContent = "Pilihan manual untuk pola kerja office hour.";
                }
            }
            if (scopeHintEl) {
                if (scope === "department") {
                    scopeHintEl.textContent = "Sumber data: employee tenant aktif, dikelompokkan menurut departemen.";
                } else if (scope === "custom") {
                    scopeHintEl.textContent = "Sumber data: employee tenant aktif, dibatasi ke user ID yang Anda isi.";
                } else {
                    scopeHintEl.textContent = "Sumber data: semua employee tenant aktif.";
                }
            }
            if (horizonHintEl) {
                if (horizon === "end_of_year") {
                    horizonHintEl.textContent = "Batch mingguan dari Week Start sampai 31 Desember.";
                } else {
                    horizonHintEl.textContent = "Generate hanya untuk minggu yang dipilih.";
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
        if (shiftCategoryEl && !shiftCategoryEl.getAttribute("data-bound")) {
            shiftCategoryEl.setAttribute("data-bound", "1");
            shiftCategoryEl.addEventListener("change", syncModeUi);
        }
        if (scopeEl && !scopeEl.getAttribute("data-bound")) {
            scopeEl.setAttribute("data-bound", "1");
            scopeEl.addEventListener("change", syncModeUi);
        }
        if (departmentEl && !departmentEl.getAttribute("data-bound")) {
            departmentEl.setAttribute("data-bound", "1");
            departmentEl.addEventListener("change", syncModeUi);
        }
        if (horizonEl && !horizonEl.getAttribute("data-bound")) {
            horizonEl.setAttribute("data-bound", "1");
            horizonEl.addEventListener("change", syncModeUi);
        }
        if (weekStartEl && !weekStartEl.getAttribute("data-bound")) {
            weekStartEl.setAttribute("data-bound", "1");
            weekStartEl.addEventListener("change", syncModeUi);
        }

        function readInt(inputEl, fallback) {
            var n = parseInt(inputEl && inputEl.value ? inputEl.value : "", 10);
            return isNaN(n) ? fallback : n;
        }

        form.addEventListener("submit", function (e) {
            e.preventDefault();
            if (submitBtn) {
                submitBtn.disabled = true;
            }
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
            smartPlannerForbiddenTransitionKeys = readPlannerTransitionSelection();
            if (!isOffice && payload.rules.illegal_transition_rules.length === 0) {
                payload.rules.illegal_transition_rules = plannerLegacyRulesFromTransitionKeys(smartPlannerForbiddenTransitionKeys.length ? smartPlannerForbiddenTransitionKeys : ['night:morning']);
            }
            smartPlannerLastPayload = JSON.parse(JSON.stringify(payload));

            resolvePlannerScope()
                .then(function (scopeInfo) {
                    if (Array.isArray(scopeInfo.employeeIds) && scopeInfo.employeeIds.length > 0) {
                        payload.employeeIds = scopeInfo.employeeIds;
                        smartPlannerLastPayload.employeeIds = scopeInfo.employeeIds.slice();
                    }
                    if (scopeMetaEl) {
                        scopeMetaEl.textContent = scopeInfo.message;
                    }
                    smartPlannerScopeMeta = String(scopeInfo.message || "");
                    var employeeCountText = Array.isArray(scopeInfo.employeeIds)
                        ? String(scopeInfo.employeeIds.length)
                        : "default";
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
                            "Generating batch planner " +
                            String(weekStarts.length) +
                            " minggu untuk " +
                            employeeCountText +
                            " karyawan...",
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
                    smartPlannerLastResult = response.data;
                    renderSmartPlannerResult(response.data);
                    if (response.meta && response.meta.horizon === "end_of_year") {
                        setSmartPlannerFeedback(
                            "Batch planner berhasil digenerate sampai " + String(response.meta.endDate || "akhir tahun") +
                            " (" + String(response.meta.batchWeeks || 0) + " minggu).",
                            false
                        );
                    } else {
                        setSmartPlannerFeedback("Smart planner berhasil digenerate.", false);
                    }
                    notify("Smart planner siap direview.", false);
                })
                .catch(function (err) {
                    if (err && err.plannerMessage) {
                        setSmartPlannerFeedback(String(err.plannerMessage), true);
                        return;
                    }
                    var data = err && err.response ? err.response.data : err && err.data ? err.data : null;
                    var status = err && err.response ? err.response.status : err && err.status ? err.status : 0;
                    setSmartPlannerFeedback(formatApiError(data, status) || "Gagal generate smart planner.", true);
                })
                .finally(function () {
                    if (submitBtn) {
                        submitBtn.disabled = false;
                    }
                });
        });

        if (applyDominantBtn && applyDominantBtn.getAttribute("data-bound") !== "1") {
            applyDominantBtn.setAttribute("data-bound", "1");
            applyDominantBtn.addEventListener("click", function () {
                if (!smartPlannerLastResult) {
                    setSmartPlannerFeedback("Generate planner dulu sebelum publish dominant shift.", true);
                    return;
                }

                applyDominantBtn.disabled = true;
                applyPlannerDominantShifts(smartPlannerLastResult)
                    .then(function (summary) {
                        var failedCount = Array.isArray(summary.failed) ? summary.failed.length : 0;
                        if (failedCount > 0) {
                            setSmartPlannerFeedback(
                                "Publish selesai: " + String(summary.success) + " berhasil, " + String(failedCount) + " gagal.",
                                true
                            );
                        } else {
                            setSmartPlannerFeedback(
                                "Publish dominant shift berhasil untuk " + String(summary.success) + " user.",
                                false
                            );
                            notify("Schedule timing berhasil diupdate dari draft planner.", false);
                        }
                        loadScheduleTiming();
                        updatePlannerApplyState(smartPlannerLastResult);
                    })
                    .catch(function (err) {
                        setSmartPlannerFeedback(String((err && err.plannerMessage) || "Gagal publish dominant shift."), true);
                        updatePlannerApplyState(smartPlannerLastResult);
                    });
            });
        }

        if (applyDailyBtn && applyDailyBtn.getAttribute("data-bound") !== "1") {
            applyDailyBtn.setAttribute("data-bound", "1");
            applyDailyBtn.addEventListener("click", function () {
                if (!smartPlannerLastResult) {
                    setSmartPlannerFeedback("Generate planner dulu sebelum publish roster harian.", true);
                    return;
                }

                applyDailyBtn.disabled = true;
                applyPlannerDailyRoster(smartPlannerLastResult)
                    .then(function (summary) {
                        setSmartPlannerFeedback(
                            "Publish roster harian berhasil. Created: " + String(summary.created || 0) +
                            ", updated: " + String(summary.updated || 0) +
                            ", off-days: " + String(summary.offDays || 0) + ".",
                            false
                        );
                        notify("Roster harian per tanggal berhasil dipublish.", false);
                        updatePlannerApplyState(smartPlannerLastResult);
                    })
                    .catch(function (err) {
                        setSmartPlannerFeedback(String((err && err.plannerMessage) || "Gagal publish roster harian."), true);
                        updatePlannerApplyState(smartPlannerLastResult);
                    });
            });
        }

        var settingsPanel = document.querySelector("[data-smart-planner-settings-panel]");
        var editModeBtn = settingsPanel ? settingsPanel.querySelector("[data-smart-planner-edit-mode-btn]") : null;
        var cancelEditBtn = settingsPanel ? settingsPanel.querySelector("[data-smart-planner-cancel-edit-btn]") : null;
        var resetDefaultsBtn = settingsPanel ? settingsPanel.querySelector("[data-smart-planner-reset-defaults-btn]") : null;
        var modeIndicator = settingsPanel ? settingsPanel.querySelector("[data-smart-planner-mode-indicator]") : null;
        var settingsPanelMaxWorkDays = settingsPanel ? settingsPanel.querySelector("[data-smart-planner-default-max-work-days]") : null;
        var settingsPanelMinDaysOff = settingsPanel ? settingsPanel.querySelector("[data-smart-planner-default-min-days-off]") : null;
        var settingsPanelMinRest = settingsPanel ? settingsPanel.querySelector("[data-smart-planner-default-min-rest]") : null;
        var settingsPanelMaxNight = settingsPanel ? settingsPanel.querySelector("[data-smart-planner-default-max-night]") : null;

        function storeDefaultInputValues() {
            smartPlannerEditModeOriginalValues = {
                maxWorkDays: (settingsPanelMaxWorkDays && settingsPanelMaxWorkDays.value) ? String(settingsPanelMaxWorkDays.value) : "5",
                minDaysOff: (settingsPanelMinDaysOff && settingsPanelMinDaysOff.value) ? String(settingsPanelMinDaysOff.value) : "2",
                minRest: (settingsPanelMinRest && settingsPanelMinRest.value) ? String(settingsPanelMinRest.value) : "12",
                maxNight: (settingsPanelMaxNight && settingsPanelMaxNight.value) ? String(settingsPanelMaxNight.value) : "3",
                transitions: readPlannerTransitionSelection().slice(),
            };
        }

        function setEditMode(enabled) {
            smartPlannerEditMode = !!enabled;
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
            if (settingsPanelMaxWorkDays && smartPlannerEditModeOriginalValues.maxWorkDays) {
                settingsPanelMaxWorkDays.value = smartPlannerEditModeOriginalValues.maxWorkDays;
            }
            if (settingsPanelMinDaysOff && smartPlannerEditModeOriginalValues.minDaysOff) {
                settingsPanelMinDaysOff.value = smartPlannerEditModeOriginalValues.minDaysOff;
            }
            if (settingsPanelMinRest && smartPlannerEditModeOriginalValues.minRest) {
                settingsPanelMinRest.value = smartPlannerEditModeOriginalValues.minRest;
            }
            if (settingsPanelMaxNight && smartPlannerEditModeOriginalValues.maxNight) {
                settingsPanelMaxNight.value = smartPlannerEditModeOriginalValues.maxNight;
            }
            renderPlannerTransitionMatrix(smartPlannerTransitionCatalog, smartPlannerEditModeOriginalValues.transitions || smartPlannerForbiddenTransitionKeys);
        }

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
                var payload = {
                    defaultRules: {
                        max_work_days_per_week: readInt(settingsPanelMaxWorkDays, 5),
                        min_days_off_per_week: readInt(settingsPanelMinDaysOff, 2),
                        min_rest_hours_between_shifts: readInt(settingsPanelMinRest, 12),
                        max_consecutive_night_shifts: readInt(settingsPanelMaxNight, 3),
                        late_tolerance_minutes: (smartPlannerSettingsCache && smartPlannerSettingsCache.defaultRules && smartPlannerSettingsCache.defaultRules.late_tolerance_minutes != null)
                            ? Number(smartPlannerSettingsCache.defaultRules.late_tolerance_minutes)
                            : 5,
                        early_leave_tolerance_minutes: (smartPlannerSettingsCache && smartPlannerSettingsCache.defaultRules && smartPlannerSettingsCache.defaultRules.early_leave_tolerance_minutes != null)
                            ? Number(smartPlannerSettingsCache.defaultRules.early_leave_tolerance_minutes)
                            : 5,
                        overtime_threshold_minutes: (smartPlannerSettingsCache && smartPlannerSettingsCache.defaultRules && smartPlannerSettingsCache.defaultRules.overtime_threshold_minutes != null)
                            ? Number(smartPlannerSettingsCache.defaultRules.overtime_threshold_minutes)
                            : 30,
                    },
                    forbiddenTransitions: readPlannerTransitionSelection(),
                };

                saveSettingsBtn.disabled = true;
                setPlannerSettingsFeedback("Menyimpan default tenant...", false);

                apiPut('/v1/hcm/smart-attendance-shifting/settings', payload)
                    .then(function (response) {
                        if (!response || response.success !== true || !response.data) {
                            setPlannerSettingsFeedback(formatApiError(response, 0) || 'Gagal simpan planner defaults.', true);
                            return;
                        }
                        smartPlannerSettingsCache = {
                            defaultRules: response.data.defaultRules || payload.defaultRules,
                            forbiddenTransitions: response.data.forbiddenTransitions || payload.forbiddenTransitions,
                            transitionCatalog: smartPlannerTransitionCatalog,
                        };
                        applyPlannerSettingsToForm(form, smartPlannerSettingsCache);
                        smartPlannerForbiddenTransitionKeys = Array.isArray(response.data.forbiddenTransitions)
                            ? response.data.forbiddenTransitions
                            : payload.forbiddenTransitions;
                        renderPlannerTransitionMatrix(smartPlannerTransitionCatalog, smartPlannerForbiddenTransitionKeys);
                        setPlannerSettingsFeedback('Default tenant planner berhasil disimpan.', false);
                        notify('Planner defaults tersimpan.', false);
                        setEditMode(false);
                    })
                    .catch(function (err) {
                        var data = err && err.response ? err.response.data : err && err.data ? err.data : null;
                        var status = err && err.response ? err.response.status : err && err.status ? err.status : 0;
                        setPlannerSettingsFeedback(formatApiError(data, status) || 'Gagal simpan planner defaults.', true);
                    })
                    .finally(function () {
                        saveSettingsBtn.disabled = false;
                    });
            });
        }

        if (settingsPanel) {
            setEditMode(false);
        }

        if (forceApplyEl && forceApplyEl.getAttribute("data-bound") !== "1") {
            forceApplyEl.setAttribute("data-bound", "1");
            forceApplyEl.addEventListener("change", function () {
                updatePlannerApplyState(smartPlannerLastResult);
            });
        }
    }

    function init() {
        setupAdminDateFilter();
        setupAdminFilters();
        setupAdminPaginationControls();
        setupReportDateFilter();
        setupReportSourceMode();
        setupReportFilters();
        setupTimesheetFilters();
        setupTimesheetPaginationControls();
        setupScheduleViewMode();
        setupScheduleTimingFilters();
        setupScheduleTimingPaginationControls();
        setupScheduleTimingEditModal();
        bindSmartPlanner();
        setupAttendanceAdminEdit();
        loadAdminAttendance();
        loadReportAttendance();
        loadEmployeeAttendance();
        loadTimesheets();
        loadScheduleTiming();
        loadScheduleCalendarHolidays();
        bindPunch();
        bindBreakToggle();
        bindGpsDebug();
        initSelfieCapture();
        bindAttendanceExtras();
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", init);
    } else {
        init();
    }
})(window, document);
