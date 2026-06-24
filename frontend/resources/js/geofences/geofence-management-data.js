(function (window) {
    "use strict";

    var DEFAULT_LAT = -6.2088;
    var DEFAULT_LNG = 106.8456;

    function esc(val) {
        if (val == null) return "";
        var div = document.createElement("div");
        div.textContent = val;
        return div.innerHTML;
    }

    function getHeaders() {
        var h = { "Content-Type": "application/json", Accept: "application/json" };
        var tok = (window.AuthApi && typeof window.AuthApi.getToken === "function" && window.AuthApi.getToken())
            || localStorage.getItem("arcav_access_token");
        if (tok) h["Authorization"] = "Bearer " + tok;
        var co = document.querySelector("[data-company-id]");
        if (co && co.getAttribute("data-company-id")) {
            h["X-Company-Id"] = co.getAttribute("data-company-id");
        }
        return h;
    }

    function api(method, url, data) {
        var opts = {
            method: method.toUpperCase(),
            headers: Object.assign({}, getHeaders()),
            credentials: "same-origin",
        };
        if (data) opts.body = JSON.stringify(data);
        return fetch(url, opts).then(function (r) {
            return r.json().then(function (b) {
                if (!r.ok) throw { status: r.status, body: b };
                return b;
            });
        });
    }

    function notify(msg, isErr) {
        var c = document.querySelector("[data-hcm-toast-container]");
        if (!c) {
            c = document.createElement("div");
            c.setAttribute("data-hcm-toast-container", "1");
            c.style.cssText = "position:fixed;top:16px;right:16px;z-index:1080;max-width:340px;";
            document.body.appendChild(c);
        }
        var t = document.createElement("div");
        t.className = "alert " + (isErr ? "alert-danger" : "alert-success") + " shadow-sm mb-2";
        t.textContent = msg;
        c.appendChild(t);
        setTimeout(function () { t.remove(); }, 2600);
    }

    function getCurrentPosition() {
        return new Promise(function (resolve, reject) {
            if (!navigator.geolocation) {
                reject({ code: 0, message: "NO_GEO" });
                return;
            }
            navigator.geolocation.getCurrentPosition(
                function (pos) {
                    resolve({ latitude: pos.coords.latitude, longitude: pos.coords.longitude });
                },
                function (err) {
                    reject(err || { code: 3, message: "POSITION_UNAVAILABLE" });
                },
                { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 }
            );
        });
    }

    function searchAddress(query) {
        if (!query || query.trim().length < 3) {
            return Promise.reject({ message: "Query too short" });
        }
        var url = "https://nominatim.openstreetmap.org/search?q="
            + encodeURIComponent(query.trim())
            + "&format=json&limit=5&countrycodes=id";
        return fetch(url, {
            headers: { "User-Agent": "ArcavHCM/1.0", "Accept-Language": "id" },
        }).then(function (r) {
            if (!r.ok) throw { message: "Search request failed" };
            return r.json();
        }).then(function (data) {
            if (!data || data.length === 0) throw { message: "No results found" };
            return data;
        });
    }

    function showSearchResults(container, results, mapInstance, latInput, lngInput, radiusInput) {
        container.innerHTML = results.map(function (r) {
            return '<button type="button" class="list-group-item list-group-item-action" '
                + 'data-lat="' + esc(r.lat) + '" data-lon="' + esc(r.lon) + '">'
                + esc(r.display_name) + '</button>';
        }).join("");
        container.style.display = "block";
        container.addEventListener("click", function (e) {
            var btn = e.target.closest("[data-lat]");
            if (!btn) return;
            var lat = parseFloat(btn.getAttribute("data-lat"));
            var lon = parseFloat(btn.getAttribute("data-lon"));
            container.style.display = "none";
            updateMapPosition(mapInstance, lat, lon, latInput, lngInput, radiusInput);
        });
    }

    function hideSearchResults(container) {
        if (container) container.style.display = "none";
    }

    function updateMapPosition(mapInstance, lat, lng, latInput, lngInput, radiusInput) {
        if (!mapInstance) return;
        lat = parseFloat(lat.toFixed(7));
        lng = parseFloat(lng.toFixed(7));
        mapInstance.updatePosition(lat, lng);
        mapInstance.map.setView([lat, lng], 15);
        if (latInput) latInput.value = lat;
        if (lngInput) lngInput.value = lng;
    }

    function initMap(containerId, latInput, lngInput, radiusInput, initialLat, initialLng, initialRadius) {
        var lat = initialLat || DEFAULT_LAT;
        var lng = initialLng || DEFAULT_LNG;
        var radius = initialRadius || 200;

        var el = document.getElementById(containerId);
        if (!el) return null;
        if (el._leaflet_id) {
            if (typeof L !== 'undefined' && L.Map && L.Map._maps) {
                var oldMap = L.Map._maps[el._leaflet_id];
                if (oldMap) { oldMap.remove(); }
            }
            el._leaflet_id = undefined;
        }
        var map = L.map(containerId).setView([lat, lng], 15);
        L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
            maxZoom: 19,
        }).addTo(map);

        var marker = L.marker([lat, lng], { draggable: true }).addTo(map);
        var circle = L.circle([lat, lng], { radius: radius, color: "#0d6efd", fillOpacity: 0.15 }).addTo(map);

        function updatePosition(latVal, lngVal) {
            latVal = parseFloat(latVal.toFixed(7));
            lngVal = parseFloat(lngVal.toFixed(7));
            marker.setLatLng([latVal, lngVal]);
            circle.setLatLng([latVal, lngVal]);
            latInput.value = latVal;
            lngInput.value = lngVal;
        }

        function updateRadius(r) {
            r = parseInt(r, 10) || 200;
            circle.setRadius(r);
            if (radiusInput) radiusInput.value = r;
        }

        updatePosition(lat, lng);
        updateRadius(radius);

        map.on("click", function (e) {
            updatePosition(e.latlng.lat, e.latlng.lng);
        });

        marker.on("dragend", function () {
            updatePosition(marker.getLatLng().lat, marker.getLatLng().lng);
        });

        if (radiusInput) {
            radiusInput.addEventListener("input", function () {
                var v = parseInt(this.value, 10);
                if (v >= 10) updateRadius(v);
            });
        }

        map.invalidateSize();

        return { map: map, marker: marker, circle: circle, updatePosition: updatePosition, updateRadius: updateRadius };
    }

    function renderRows(data) {
        var body = document.querySelector("[data-gf-body]");
        if (!body) return;
        body.innerHTML = (data || []).map(function (g) {
            var badge = g.is_active ? "success" : "danger";
            var label = g.is_active ? "Active" : "Inactive";
            return "<tr>" +
                "<td>" + esc(g.name) + "</td>" +
                "<td>" + esc(g.latitude) + "</td>" +
                "<td>" + esc(g.longitude) + "</td>" +
                "<td>" + esc(g.radius_meters) + "</td>" +
                "<td><span class=\"badge badge-" + badge + "\">" + label + "</span></td>" +
                "<td><div class=\"action-icon d-inline-flex align-items-center\">" +
                "<a href=\"#\" class=\"me-2\" data-gf-edit=\"" + esc(g.id) + "\" " +
                "data-name=\"" + esc(g.name) + "\" " +
                "data-lat=\"" + esc(g.latitude) + "\" " +
                "data-lng=\"" + esc(g.longitude) + "\" " +
                "data-radius=\"" + esc(g.radius_meters) + "\" " +
                "data-active=\"" + (g.is_active ? "1" : "0") + "\">" +
                "<i class=\"ti ti-edit\"></i></a>" +
                "<a href=\"#\" data-gf-delete=\"" + esc(g.id) + "\" data-name=\"" + esc(g.name) + "\">" +
                "<i class=\"ti ti-trash\"></i></a>" +
                "</div></td>" +
                "</tr>";
        }).join("") || '<tr><td colspan="6" class="text-center py-4 text-muted">No geofences found.</td></tr>';
        body.setAttribute("data-hydrated", "1");
    }

    function renderPagination(meta) {
        var list = document.querySelector("[data-gf-pagination]");
        if (!list) return;
        var total = meta.total || 0;
        var page = meta.page || 1;
        var pp = meta.perPage || 20;
        var tp = Math.max(1, Math.ceil(total / Math.max(1, pp)));
        if (tp <= 1) { list.innerHTML = ""; return; }
        var h = "";
        h += '<li class="page-item ' + (page <= 1 ? "disabled" : "") + '"><a href="#" class="page-link" data-gf-page="' + (page - 1) + '">Prev</a></li>';
        for (var p = Math.max(1, page - 2); p <= Math.min(tp, page + 2); p++) {
            h += '<li class="page-item ' + (p === page ? "active" : "") + '"><a href="#" class="page-link" data-gf-page="' + p + '">' + p + "</a></li>";
        }
        h += '<li class="page-item ' + (page >= tp ? "disabled" : "") + '"><a href="#" class="page-link" data-gf-page="' + (page + 1) + '">Next</a></li>';
        list.innerHTML = h;
    }

    function renderShowing(meta, rowCount) {
        var el = document.querySelector("[data-gf-showing]");
        if (!el) return;
        var total = meta.total || 0;
        var page = meta.page || 1;
        var pp = meta.perPage || 20;
        if (!rowCount || !total) { el.textContent = "Showing 0 - 0 of 0 entries"; return; }
        var start = (page - 1) * pp + 1;
        var end = Math.min(start + rowCount - 1, total);
        el.textContent = "Showing " + start + " - " + end + " of " + total + " entries";
    }

    function loadPage(state) {
        var params = new URLSearchParams({
            page: state.page || 1,
            perPage: state.perPage || 20,
            search: state.search || "",
        });
        api("GET", "/v1/hcm/geofences?" + params.toString()).then(function (res) {
            renderRows(res.data);
            renderPagination(res.meta);
            renderShowing(res.meta, (res.data || []).length);
        }).catch(function () {
            notify("Failed to load geofences.", true);
        });
    }

    var addMapInstance = null;

    function initPage() {
        var path = window.location.pathname;
        if (path !== "/geofences") return;

        var state = { page: 1, perPage: 20, search: "" };

        loadPage(state);

        document.addEventListener("click", function (e) {
            var pageLink = e.target.closest("[data-gf-page]");
            if (pageLink) {
                e.preventDefault();
                state.page = parseInt(pageLink.getAttribute("data-gf-page"), 10) || 1;
                loadPage(state);
            }
        });

        var searchInput = document.querySelector("[data-gf-search]");
        if (searchInput) {
            searchInput.addEventListener("change", function () {
                state.search = this.value;
                state.page = 1;
                loadPage(state);
            });
        }

        var perPageSelect = document.querySelector("[data-gf-per-page]");
        if (perPageSelect) {
            perPageSelect.addEventListener("change", function () {
                state.perPage = parseInt(this.value, 10) || 20;
                state.page = 1;
                loadPage(state);
            });
        }

        document.getElementById("add_geofence").addEventListener("shown.bs.modal", function () {
            if (!addMapInstance) {
                addMapInstance = initMap(
                    "gf-add-map",
                    document.querySelector('[data-gf-form="add"] [data-gf-field="latitude"]'),
                    document.querySelector('[data-gf-form="add"] [data-gf-field="longitude"]'),
                    document.querySelector('[data-gf-form="add"] [data-gf-field="radius"]')
                );
            } else {
                setTimeout(function () { addMapInstance.map.invalidateSize(); }, 200);
            }
        });

        document.querySelector('[data-gf-form="add"]').addEventListener("submit", function (e) {
            e.preventDefault();
            if (!window.ArcavValidation.validateForm(this)) return;
            var name = this.querySelector('[data-gf-field="name"]').value;
            var lat = parseFloat(this.querySelector('[data-gf-field="latitude"]').value);
            var lng = parseFloat(this.querySelector('[data-gf-field="longitude"]').value);
            var radius = parseInt(this.querySelector('[data-gf-field="radius"]').value, 10);
            var isActive = this.querySelector('[data-gf-field="is_active"]').value === "1";
            api("POST", "/v1/hcm/geofences", {
                name: name, latitude: lat, longitude: lng, radius_meters: radius, is_active: isActive,
            }).then(function () {
                notify("Geofence created.", false);
                var modal = bootstrap.Modal.getInstance(document.getElementById("add_geofence"));
                if (modal) modal.hide();
                loadPage(state);
            }).catch(function (err) {
                var msg = err.body && err.body.error ? err.body.error.message : "Failed to create geofence.";
                notify(msg, true);
            });
        });

        document.addEventListener("click", function (e) {
            var btn = e.target.closest("[data-gf-edit]");
            if (!btn) return;
            e.preventDefault();
            var form = document.querySelector('[data-gf-form="edit"]');
            form.dataset.id = btn.getAttribute("data-gf-edit");
            form.querySelector('[data-gf-field="name"]').value = btn.getAttribute("data-name");
            form.querySelector('[data-gf-field="latitude"]').value = btn.getAttribute("data-lat");
            form.querySelector('[data-gf-field="longitude"]').value = btn.getAttribute("data-lng");
            form.querySelector('[data-gf-field="radius"]').value = btn.getAttribute("data-radius");
            form.querySelector('[data-gf-field="is_active"]').value = btn.getAttribute("data-active");
            var modalEl = document.getElementById("edit_geofence");
            var bsModal = new bootstrap.Modal(modalEl);
            bsModal.show();
            modalEl.addEventListener("shown.bs.modal", function initEditMap() {
                modalEl.removeEventListener("shown.bs.modal", initEditMap);
                var editMap = initMap(
                    "gf-edit-map",
                    form.querySelector('[data-gf-field="latitude"]'),
                    form.querySelector('[data-gf-field="longitude"]'),
                    form.querySelector('[data-gf-field="radius"]'),
                    parseFloat(btn.getAttribute("data-lat")),
                    parseFloat(btn.getAttribute("data-lng")),
                    parseInt(btn.getAttribute("data-radius"), 10) || 200
                );
                form._editMap = editMap;
            });
        });

        document.querySelector('[data-gf-form="edit"]').addEventListener("submit", function (e) {
            e.preventDefault();
            if (!window.ArcavValidation.validateForm(this)) return;
            var id = this.dataset.id;
            var name = this.querySelector('[data-gf-field="name"]').value;
            var lat = parseFloat(this.querySelector('[data-gf-field="latitude"]').value);
            var lng = parseFloat(this.querySelector('[data-gf-field="longitude"]').value);
            var radius = parseInt(this.querySelector('[data-gf-field="radius"]').value, 10);
            var isActive = this.querySelector('[data-gf-field="is_active"]').value === "1";
            api("PUT", "/v1/hcm/geofences/" + id, {
                name: name, latitude: lat, longitude: lng, radius_meters: radius, is_active: isActive,
            }).then(function () {
                notify("Geofence updated.", false);
                var modal = bootstrap.Modal.getInstance(document.getElementById("edit_geofence"));
                if (modal) modal.hide();
                loadPage(state);
            }).catch(function (err) {
                var msg = err.body && err.body.error ? err.body.error.message : "Failed to update geofence.";
                notify(msg, true);
            });
        });

        document.addEventListener("click", function (e) {
            var btn = e.target.closest("[data-gf-delete]");
            if (!btn) return;
            e.preventDefault();
            var id = btn.getAttribute("data-gf-delete");
            var name = btn.getAttribute("data-name");
            function doDelete() {
                api("DELETE", "/v1/hcm/geofences/" + id).then(function () {
                    notify("Geofence deleted.", false);
                    loadPage(state);
                }).catch(function (err) {
                    var msg = err.body && err.body.error ? err.body.error.message : "Failed to delete geofence.";
                    notify(msg, true);
                });
            }
            if (window.ArcavUi && window.ArcavUi.confirmDelete) {
                window.ArcavUi.confirmDelete('Delete geofence "' + name + '"?', "Hapus Geofence").then(function (ok) { if (ok) doDelete(); });
            } else {
                doDelete();
            }
        });

        document.addEventListener("click", function (e) {
            var btn = e.target.closest("[data-gf-current-loc]");
            if (!btn) return;
            var key = btn.getAttribute("data-gf-current-loc");
            var latInput = document.querySelector('[data-gf-form="' + key + '"] [data-gf-field="latitude"]');
            var lngInput = document.querySelector('[data-gf-form="' + key + '"] [data-gf-field="longitude"]');
            var radiusInput = document.querySelector('[data-gf-form="' + key + '"] [data-gf-field="radius"]');
            var mapInstance = key === "add" ? addMapInstance : document.querySelector('[data-gf-form="edit"]')._editMap;
            getCurrentPosition().then(function (pos) {
                updateMapPosition(mapInstance, pos.latitude, pos.longitude, latInput, lngInput, radiusInput);
            }).catch(function () {
                notify("Gagal mendapatkan lokasi. Periksa izin GPS.", true);
            });
        });

        document.addEventListener("click", function (e) {
            var btn = e.target.closest("[data-gf-search-btn]");
            if (!btn) return;
            var key = btn.getAttribute("data-gf-search-btn");
            var input = document.querySelector('[data-gf-address-search="' + key + '"]');
            var container = document.querySelector('[data-gf-search-results="' + key + '"]');
            var latInput = document.querySelector('[data-gf-form="' + key + '"] [data-gf-field="latitude"]');
            var lngInput = document.querySelector('[data-gf-form="' + key + '"] [data-gf-field="longitude"]');
            var radiusInput = document.querySelector('[data-gf-form="' + key + '"] [data-gf-field="radius"]');
            var mapInstance = key === "add" ? addMapInstance : document.querySelector('[data-gf-form="edit"]')._editMap;
            var query = input ? input.value : "";
            if (query.trim().length < 3) {
                notify("Masukkan minimal 3 karakter.", true);
                return;
            }
            searchAddress(query).then(function (results) {
                if (container) showSearchResults(container, results, mapInstance, latInput, lngInput, radiusInput);
            }).catch(function (err) {
                notify(err.message || "Pencarian gagal.", true);
            });
        });

        document.addEventListener("keydown", function (e) {
            if (e.key !== "Enter") return;
            var input = e.target.closest("[data-gf-address-search]");
            if (!input) return;
            var key = input.getAttribute("data-gf-address-search");
            var btn = document.querySelector('[data-gf-search-btn="' + key + '"]');
            if (btn) btn.click();
        });

        document.addEventListener("click", function (e) {
            if (e.target.closest("[data-gf-search-results]") || e.target.closest("[data-gf-address-search]") || e.target.closest("[data-gf-search-btn]")) return;
            document.querySelectorAll("[data-gf-search-results]").forEach(function (el) { el.style.display = "none"; });
        });
    }

    window.__geofenceTest = {
        searchAddress: searchAddress,
        getCurrentPosition: getCurrentPosition,
        updateMapPosition: updateMapPosition,
        initMap: initMap,
    };

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", initPage);
    } else {
        initPage();
    }
})(window);
