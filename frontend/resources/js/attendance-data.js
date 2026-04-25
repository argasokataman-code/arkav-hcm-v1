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
    var scheduleShiftsCache = [];
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

    function renderScheduleTimingRows(rows) {
        var tbody = document.querySelector("[data-schedule-timing-body]");
        if (!tbody) return;
        if (!rows.length) {
            tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-4">No schedule timings found.</td></tr>';
            tbody.setAttribute("data-hydrated", "1");
            return;
        }
        tbody.innerHTML = rows.map(function (r) {
            var shiftBadge = r.shiftName
                ? ' <span class="badge bg-light text-dark border ms-1">' + esc(r.shiftName) + "</span>"
                : "";
            var sm = r.startMinutes != null ? String(r.startMinutes) : "0";
            var em = r.endMinutes != null ? String(r.endMinutes) : "0";
            var sid = r.shiftId != null && r.shiftId !== "" ? String(r.shiftId) : "";
            return (
                "<tr>" +
                '<td><div class="form-check form-check-md"><input class="form-check-input" type="checkbox"></div></td>' +
                "<td>" + esc(r.name) + "</td>" +
                "<td>" + esc(r.jobTitle) + "</td>" +
                "<td>" +
                esc(r.availableTimings) +
                shiftBadge +
                (r.source === "manual" ? ' <span class="badge badge-info-transparent ms-1">Manual</span>' : "") +
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
    }

    function renderScheduleTimingMessage(msg) {
        var tbody = document.querySelector("[data-schedule-timing-body]");
        if (!tbody) return;
        tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-4">' + esc(msg) + "</td></tr>";
        tbody.setAttribute("data-hydrated", "1");
    }

    function renderScheduleTimingPagination(pagination) {
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
        foot.style.display = "";
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
        setupScheduleTimingFilters();
        setupScheduleTimingPaginationControls();
        setupScheduleTimingEditModal();
        setupAttendanceAdminEdit();
        loadAdminAttendance();
        loadReportAttendance();
        loadEmployeeAttendance();
        loadTimesheets();
        loadScheduleTiming();
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
