export function createPunchMapModule(deps) {
    var punchMapElId = deps.punchMapElId;
    var getPunchMap = deps.getPunchMap;
    var setPunchMap = deps.setPunchMap;
    var getPunchMarker = deps.getPunchMarker;
    var setPunchMarker = deps.setPunchMarker;
    var getManualPunchCoords = deps.getManualPunchCoords;
    var setManualPunchCoords = deps.setManualPunchCoords;

    function destroyPunchMap() {
        var punchMap = getPunchMap();
        if (punchMap) {
            try {
                punchMap.remove();
            } catch (ignore) {
                /* leaflet may throw if container gone */
            }
            setPunchMap(null);
            setPunchMarker(null);
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
        var map = window.L.map(el, { zoomControl: true }).setView([lat, lng], 17);
        window.L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
            maxZoom: 19,
        }).addTo(map);
        var marker = window.L.marker([lat, lng]).addTo(map);
        setPunchMap(map);
        setPunchMarker(marker);
        setManualPunchCoords({ latitude: lat, longitude: lng });
        var hint = document.querySelector("[data-attendance-me-map-hint]");
        if (hint) {
            hint.textContent = "Lokasi aktif: " + String(lat.toFixed(6)) + ", " + String(lng.toFixed(6));
        }
        window.setTimeout(function () {
            var activeMap = getPunchMap();
            if (activeMap) {
                activeMap.invalidateSize();
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
        if (!getPunchMap()) {
            var map = window.L.map(el, { zoomControl: true }).setView([-6.2088, 106.8456], 12);
            window.L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
                maxZoom: 19,
            }).addTo(map);
            map.on("click", function (e) {
                if (!e || !e.latlng) {
                    return;
                }
                var lat = Number(e.latlng.lat);
                var lng = Number(e.latlng.lng);
                var marker = getPunchMarker();
                if (!marker) {
                    marker = window.L.marker([lat, lng]).addTo(map);
                    setPunchMarker(marker);
                } else {
                    marker.setLatLng([lat, lng]);
                }
                setManualPunchCoords({ latitude: lat, longitude: lng });
                var hint = document.querySelector("[data-attendance-me-map-hint]");
                if (hint) {
                    hint.textContent = "Titik manual dipilih: " + String(lat.toFixed(6)) + ", " + String(lng.toFixed(6));
                }
            });
            setPunchMap(map);
        }
        window.setTimeout(function () {
            var activeMap = getPunchMap();
            if (activeMap) {
                activeMap.invalidateSize();
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
            setManualPunchCoords(null);
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
        setText("[data-gps-debug-coords]", "-");
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
                var msg = err && err.message ? String(err.message) : "-";
                setText("[data-gps-debug-status]", "ERR code=" + code + " msg=" + msg + " | " + geolocationErrorMessage(err));
            });
    }

    return {
        destroyPunchMap: destroyPunchMap,
        showPunchMapAt: showPunchMapAt,
        ensureInteractivePunchMap: ensureInteractivePunchMap,
        syncPunchMapFromMe: syncPunchMapFromMe,
        getCurrentPositionForPunch: getCurrentPositionForPunch,
        geolocationErrorMessage: geolocationErrorMessage,
        runGpsDebugCheck: runGpsDebugCheck,
        getManualPunchCoords: function () {
            return getManualPunchCoords();
        },
    };
}
