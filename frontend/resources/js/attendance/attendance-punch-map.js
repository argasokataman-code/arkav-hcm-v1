export function createPunchMapModule(deps) {
    var punchMapElId = deps.punchMapElId;
    var getPunchMap = deps.getPunchMap;
    var setPunchMap = deps.setPunchMap;
    var getPunchMarker = deps.getPunchMarker;
    var setPunchMarker = deps.setPunchMarker;
    var getManualPunchCoords = deps.getManualPunchCoords;
    var setManualPunchCoords = deps.setManualPunchCoords;
    var geofenceLayer = null;
    var geofenceLine = null;

    function getGeofenceData() {
        var el = document.getElementById(punchMapElId);
        if (!el) return null;
        var lat = parseFloat(el.getAttribute("data-gf-center-lat"));
        var lng = parseFloat(el.getAttribute("data-gf-center-lng"));
        var radius = parseInt(el.getAttribute("data-gf-radius"), 10);
        if (isNaN(lat) || isNaN(lng) || isNaN(radius)) return null;
        return { lat: lat, lng: lng, radius: radius };
    }

    function updateGeofenceStatusUI(employeeLat, employeeLng) {
        var box = document.querySelector("[data-gf-status-box]");
        var badge = document.querySelector("[data-gf-badge]");
        var badgeText = document.querySelector("[data-gf-badge-text]");
        var distEl = document.querySelector("[data-gf-distance-value]");
        var gf = getGeofenceData();
        if (!box || !badge || !badgeText || !distEl) return;

        if (!gf) {
            box.classList.add("d-none");
            return;
        }
        box.classList.remove("d-none");

        if (employeeLat == null || employeeLng == null) {
            badge.className = "gf-badge unverified";
            badgeText.textContent = "Memeriksa area\u2026";
            distEl.textContent = "\u2014";
            return;
        }

        var R = 6371000;
        var dLat = ((employeeLat - gf.lat) * Math.PI) / 180;
        var dLng = ((employeeLng - gf.lng) * Math.PI) / 180;
        var a = Math.sin(dLat / 2) * Math.sin(dLat / 2) +
            Math.cos((gf.lat * Math.PI) / 180) * Math.cos((employeeLat * Math.PI) / 180) *
            Math.sin(dLng / 2) * Math.sin(dLng / 2);
        var c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
        var distance = R * c;

        var inside = distance <= gf.radius;
        badge.className = "gf-badge " + (inside ? "inside" : "outside");
        badgeText.textContent = inside ? "\u2705 Dalam Area" : "\u274c Di Luar Area";
        distEl.textContent = Math.round(distance) + " m";
    }

    function renderGeofenceOnMap(map, employeeLat, employeeLng) {
        if (geofenceLayer) {
            try { map.removeLayer(geofenceLayer); } catch (e) {}
            geofenceLayer = null;
        }
        if (geofenceLine) {
            try { map.removeLayer(geofenceLine); } catch (e) {}
            geofenceLine = null;
        }
        var gf = getGeofenceData();
        if (!gf || !window.L) return;

        geofenceLayer = window.L.circle([gf.lat, gf.lng], {
            radius: gf.radius,
            color: "#0d6efd",
            fillColor: "#0d6efd",
            fillOpacity: 0.08,
            weight: 2,
            dashArray: "6 4",
        }).addTo(map);

        if (employeeLat != null && employeeLng != null) {
            geofenceLine = window.L.polyline(
                [[gf.lat, gf.lng], [employeeLat, employeeLng]],
                { color: "#dc3545", weight: 1.5, dashArray: "4 4" }
            ).addTo(map);
        }

        var gfMarker = window.L.marker([gf.lat, gf.lng], {
            icon: window.L.divIcon({
                className: "gf-center-marker",
                html: '<span style="background:#0d6efd;color:#fff;border-radius:50%;width:22px;height:22px;display:flex;align-items:center;justify-content:center;font-size:11px;border:2px solid #fff;box-shadow:0 1px 4px rgba(0,0,0,.25);">\u2302</span>',
                iconSize: [22, 22],
                iconAnchor: [11, 11],
            })
        }).addTo(map);

        map.fitBounds(geofenceLayer.getBounds().pad(0.3));
    }

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
        geofenceLayer = null;
        geofenceLine = null;
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
        renderGeofenceOnMap(map, lat, lng);
        updateGeofenceStatusUI(lat, lng);
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
            renderGeofenceOnMap(map, null, null);
            updateGeofenceStatusUI(null, null);
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
                updateGeofenceStatusUI(lat, lng);
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
