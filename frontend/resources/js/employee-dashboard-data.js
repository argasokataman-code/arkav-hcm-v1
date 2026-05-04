(function (window, document) {
    "use strict";

    var legacyLeavesChart = null;
    var legacyPerformanceChart = null;
    var latestDashboardData = null;
    var attendanceMap = null;
    var attendanceMarker = null;
    var attendancePreviewMarker = null;
    var latestDashboardNotifications = [];

    function escapeHtml(value) {
        return String(value || "")
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/\"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    function notify(message, tone) {
        var text = String(message || "");
        if (!text) return;

        var useTone = String(tone || "info");
        if (window.ArcavUi && typeof window.ArcavUi.showToast === "function") {
            window.ArcavUi.showToast(text, useTone);
            return;
        }
        if (window.ArcavUi && typeof window.ArcavUi.toast === "function") {
            window.ArcavUi.toast(text, useTone);
            return;
        }

        var container = document.querySelector("[data-hcm-toast-container]");
        if (!container) {
            container = document.createElement("div");
            container.setAttribute("data-hcm-toast-container", "1");
            container.style.position = "fixed";
            container.style.top = "16px";
            container.style.right = "16px";
            container.style.zIndex = "1080";
            document.body.appendChild(container);
        }

        var alert = document.createElement("div");
        var danger = useTone === "danger" || useTone === "error";
        var warning = useTone === "warning";
        var className = danger ? "alert-danger" : (warning ? "alert-warning" : "alert-info");
        alert.className = "alert " + className + " shadow-sm mb-2";
        alert.textContent = text;
        container.appendChild(alert);
        window.setTimeout(function () {
            alert.remove();
        }, 3500);
    }

    function setText(selector, value, fallback) {
        var el = document.querySelector(selector);
        if (!el) return;
        var useFallback = fallback == null ? "-" : fallback;
        el.textContent = String(value == null || value === "" ? useFallback : value);
    }

    function setWidth(selector, percent) {
        var el = document.querySelector(selector);
        if (!el) return;
        var val = Number(percent || 0);
        if (!Number.isFinite(val)) val = 0;
        el.style.width = Math.max(0, Math.min(100, val)) + "%";
    }

    function formatHours(value) {
        var val = Number(value || 0);
        if (!Number.isFinite(val)) return "0.0";
        return val.toFixed(1);
    }

    function parseDurationToMinutes(raw) {
        var str = String(raw || "").trim();
        if (!str || str === "-") return 0;

        var hourMatch = str.match(/(\d+)\s*h/i);
        var minuteMatch = str.match(/(\d+)\s*m/i);
        var secondMatch = str.match(/(\d+)\s*s/i);

        var hours = hourMatch ? Number(hourMatch[1] || 0) : 0;
        var minutes = minuteMatch ? Number(minuteMatch[1] || 0) : 0;
        var seconds = secondMatch ? Number(secondMatch[1] || 0) : 0;

        if (!Number.isFinite(hours)) hours = 0;
        if (!Number.isFinite(minutes)) minutes = 0;
        if (!Number.isFinite(seconds)) seconds = 0;

        return (hours * 60) + minutes + Math.round(seconds / 60);
    }

    function shiftClockLabel(baseClock, shiftMinutes) {
        var match = String(baseClock || "").match(/^(\d{1,2}):(\d{2})$/);
        if (!match) return null;

        var hh = Number(match[1]);
        var mm = Number(match[2]);
        if (!Number.isFinite(hh) || !Number.isFinite(mm)) return null;

        var total = (hh * 60) + mm + Number(shiftMinutes || 0);
        total = ((total % 1440) + 1440) % 1440;

        var outH = String(Math.floor(total / 60)).padStart(2, "0");
        var outM = String(total % 60).padStart(2, "0");
        return outH + ":" + outM;
    }

    function renderLegacyTimeline(attendanceToday, attendanceStats) {
        var barsRoot = document.querySelector("[data-employee-legacy-timeline-bars]");
        var labelsRoot = document.querySelector("[data-employee-legacy-timeline-labels]");
        if (!barsRoot || !labelsRoot) return;

        var productiveMinutes = parseDurationToMinutes(attendanceToday && attendanceToday.summaryProductive);
        var breakMinutes = parseDurationToMinutes(attendanceToday && attendanceToday.summaryBreak);
        var overtimeMinutes = parseDurationToMinutes(attendanceToday && attendanceToday.summaryOvertime);

        var targetMinutes = Math.max(1, Math.round(Number(attendanceStats && attendanceStats.todayTarget || 8) * 60));
        var productiveOnTargetMinutes = Math.max(0, productiveMinutes - overtimeMinutes);
        var remainingMinutes = Math.max(0, targetMinutes - productiveOnTargetMinutes);

        var segments = [
            { minutes: productiveOnTargetMinutes, cls: "bg-success" },
            { minutes: breakMinutes, cls: "bg-warning" },
            { minutes: overtimeMinutes, cls: "bg-info" },
            { minutes: remainingMinutes, cls: "bg-white" }
        ];

        var totalMinutes = segments.reduce(function (sum, seg) {
            return sum + Math.max(0, Number(seg.minutes || 0));
        }, 0);

        if (totalMinutes <= 0) {
            barsRoot.innerHTML = '<div class="progress-bar bg-transparent rounded" role="progressbar" style="width: 100%;"></div>';
        } else {
            barsRoot.innerHTML = segments
                .filter(function (seg) { return Number(seg.minutes || 0) > 0; })
                .map(function (seg, index) {
                    var pct = Math.max(0, Math.min(100, (Number(seg.minutes || 0) / totalMinutes) * 100));
                    var spacing = index === segments.length - 1 ? "" : " me-2";
                    return '<div class="progress-bar ' + seg.cls + ' rounded' + spacing + '" role="progressbar" style="width: ' + pct.toFixed(2) + '%;"></div>';
                })
                .join("");
        }

        var clockIn = attendanceToday && attendanceToday.punchInAt && attendanceToday.punchInAt !== "-"
            ? String(attendanceToday.punchInAt)
            : "00:00";
        var clockOut = attendanceToday && attendanceToday.punchOutAt && attendanceToday.punchOutAt !== "-"
            ? String(attendanceToday.punchOutAt)
            : null;
        var targetClock = shiftClockLabel(clockIn, targetMinutes) || "08:00";
        var endClock = clockOut || shiftClockLabel(clockIn, productiveOnTargetMinutes + breakMinutes + overtimeMinutes) || "24:00";

        labelsRoot.innerHTML = [clockIn, targetClock, endClock, "24:00"]
            .map(function (label) {
                return '<span class="fs-10">' + escapeHtml(label) + '</span>';
            })
            .join("");
    }

    function formatRupiah(amount) {
        var val = Number(amount || 0);
        if (!Number.isFinite(val)) val = 0;
        return new Intl.NumberFormat("id-ID", { style: "currency", currency: "IDR", maximumFractionDigits: 0 }).format(val);
    }

    function formatDate(isoDate) {
        if (!isoDate) return "-";
        var date = new Date(String(isoDate));
        if (isNaN(date.getTime())) {
            date = new Date(String(isoDate) + "T00:00:00");
        }
        if (isNaN(date.getTime())) return String(isoDate);
        return date.toLocaleDateString("id-ID", { day: "2-digit", month: "short", year: "numeric" });
    }

    function parseDateInput(raw) {
        if (!raw) return "";
        var str = String(raw).trim();
        if (/^\d{4}-\d{2}-\d{2}$/.test(str)) return str;
        var dmy = str.match(/^(\d{2})[-/](\d{2})[-/](\d{4})$/);
        if (dmy) return dmy[3] + "-" + dmy[2] + "-" + dmy[1];
        var date = new Date(str);
        if (isNaN(date.getTime())) return "";
        var yyyy = date.getFullYear();
        var mm = String(date.getMonth() + 1).padStart(2, "0");
        var dd = String(date.getDate()).padStart(2, "0");
        return yyyy + "-" + mm + "-" + dd;
    }

    function updateRingProgress(root, percent) {
        if (!root) return;
        var val = Number(percent || 0);
        if (!Number.isFinite(val)) val = 0;
        val = Math.max(0, Math.min(100, val));
        root.setAttribute("data-value", String(val));

        var leftBar = root.querySelector(".progress-left .progress-bar");
        var rightBar = root.querySelector(".progress-right .progress-bar");
        if (!leftBar || !rightBar) return;

        var leftDeg = 0;
        var rightDeg = 0;
        if (val <= 50) {
            rightDeg = (val / 50) * 180;
        } else {
            rightDeg = 180;
            leftDeg = ((val - 50) / 50) * 180;
        }

        rightBar.style.transform = "rotate(" + rightDeg + "deg)";
        leftBar.style.transform = "rotate(" + leftDeg + "deg)";
    }

    function renderAttendanceMap(attendanceToday) {
        var mapRoot = document.querySelector("[data-employee-legacy-attendance-map]");
        var hint = document.querySelector("[data-employee-legacy-map-hint]");
        if (!mapRoot) return;

        function parseCoordinate(raw) {
            if (raw === null || raw === undefined || raw === "") return null;
            var num = Number(raw);
            return Number.isFinite(num) ? num : null;
        }

        if (!window.L || typeof window.L.map !== "function") {
            if (hint) {
                hint.textContent = "Library map belum termuat.";
            }
            return;
        }

        if (!attendanceMap) {
            attendanceMap = window.L.map(mapRoot).setView([-6.200000, 106.816666], 11);
            window.L.tileLayer("https://tile.openstreetmap.org/{z}/{x}/{y}.png", {
                maxZoom: 19,
                attribution: "&copy; OpenStreetMap contributors"
            }).addTo(attendanceMap);
            window.setTimeout(function () {
                attendanceMap.invalidateSize();
            }, 120);
        }

        var checkOutLat = parseCoordinate(attendanceToday && attendanceToday.checkOutLatitude);
        var checkOutLng = parseCoordinate(attendanceToday && attendanceToday.checkOutLongitude);
        var checkInLat = parseCoordinate(attendanceToday && attendanceToday.checkInLatitude);
        var checkInLng = parseCoordinate(attendanceToday && attendanceToday.checkInLongitude);
        var previewLat = parseCoordinate(attendanceToday && attendanceToday.previewLatitude);
        var previewLng = parseCoordinate(attendanceToday && attendanceToday.previewLongitude);

        var hasCheckOut = checkOutLat !== null && checkOutLng !== null;
        var hasCheckIn = checkInLat !== null && checkInLng !== null;
        var hasPreview = previewLat !== null && previewLng !== null;

        var lat = null;
        var lng = null;
        var label = "Belum ada titik attendance hari ini.";

        if (hasCheckOut) {
            lat = checkOutLat;
            lng = checkOutLng;
            label = "Lokasi punch out tercatat.";
        } else if (hasCheckIn) {
            lat = checkInLat;
            lng = checkInLng;
            label = "Lokasi punch in tercatat.";
        } else if (hasPreview) {
            lat = previewLat;
            lng = previewLng;
            label = String(attendanceToday.previewLabel || "Lokasi GPS perangkat siap digunakan untuk punch.");
        }

        if (lat !== null && lng !== null) {
            attendanceMap.setView([lat, lng], 16);
            var usePreviewMarker = hasPreview && !hasCheckOut && !hasCheckIn;
            if (usePreviewMarker) {
                if (!attendancePreviewMarker) {
                    attendancePreviewMarker = window.L.marker([lat, lng]).addTo(attendanceMap);
                } else {
                    attendancePreviewMarker.setLatLng([lat, lng]);
                }
                if (attendanceMarker) {
                    attendanceMap.removeLayer(attendanceMarker);
                    attendanceMarker = null;
                }
            } else {
                if (!attendanceMarker) {
                    attendanceMarker = window.L.marker([lat, lng]).addTo(attendanceMap);
                } else {
                    attendanceMarker.setLatLng([lat, lng]);
                }
                if (attendancePreviewMarker) {
                    attendanceMap.removeLayer(attendancePreviewMarker);
                    attendancePreviewMarker = null;
                }
            }
        } else {
            if (attendanceMarker) {
                attendanceMap.removeLayer(attendanceMarker);
                attendanceMarker = null;
            }
            if (attendancePreviewMarker) {
                attendanceMap.removeLayer(attendancePreviewMarker);
                attendancePreviewMarker = null;
            }
            attendanceMap.setView([-6.200000, 106.816666], 11);
        }

        if (hint) {
            hint.textContent = label;
        }
    }

    function apiGet(url) {
        if (window.axios) {
            return window.axios({ method: "get", url: url, headers: { Accept: "application/json" }, withCredentials: true }).then(function (r) {
                return r.data;
            });
        }

        return fetch(url, { method: "GET", headers: { Accept: "application/json" }, credentials: "same-origin" }).then(function (res) {
            return res.json().catch(function () {
                return {};
            }).then(function (data) {
                if (!res.ok) throw { status: res.status, data: data };
                return data;
            });
        });
    }

    function apiPost(url, body) {
        if (window.axios) {
            return window.axios({ method: "post", url: url, data: body, headers: { Accept: "application/json" }, withCredentials: true }).then(function (r) {
                return r.data;
            });
        }

        return fetch(url, {
            method: "POST",
            headers: { "Content-Type": "application/json", Accept: "application/json" },
            credentials: "same-origin",
            body: JSON.stringify(body || {})
        }).then(function (res) {
            return res.json().catch(function () {
                return {};
            }).then(function (data) {
                if (!res.ok) throw { status: res.status, data: data };
                return data;
            });
        });
    }

    function getTenantContext() {
        try {
            if (window.AuthApi && typeof window.AuthApi.getTenantContext === "function") {
                return window.AuthApi.getTenantContext() || {};
            }
        } catch (_e) {}

        return {};
    }

    async function getApiToken() {
        try {
            if (window.AuthApi && typeof window.AuthApi.getToken === "function") {
                var authToken = window.AuthApi.getToken();
                if (authToken) {
                    return authToken;
                }
            }
        } catch (_e) {}

        try {
            var response = await fetch("/api-token", { credentials: "include" });
            var payload = await response.json();
            var tokenFromPayload = payload && payload.data && payload.data.token
                ? payload.data.token
                : (payload && payload.token ? payload.token : null);
            if (tokenFromPayload) {
                return tokenFromPayload;
            }
        } catch (_e) {}

        return null;
    }

    function buildNotificationHeaders(token) {
        var headers = {
            Accept: "application/json",
            "X-Requested-With": "XMLHttpRequest"
        };

        if (token) {
            headers.Authorization = "Bearer " + String(token);
        }

        var tenant = getTenantContext();
        if (tenant && tenant.companyCode) {
            headers["X-Company-Code"] = String(tenant.companyCode);
        }
        if (tenant && tenant.companyId) {
            headers["X-Company-Id"] = String(tenant.companyId);
        }
        if (tenant && tenant.companyUuid) {
            headers["X-Company-UUID"] = String(tenant.companyUuid);
        }

        return headers;
    }

    function formatRelativeTime(value) {
        if (!value) {
            return "Just now";
        }

        var parsed = new Date(value);
        if (isNaN(parsed.getTime())) {
            return "Just now";
        }

        var diffSeconds = Math.max(0, Math.floor((Date.now() - parsed.getTime()) / 1000));
        if (diffSeconds < 60) {
            return "Just now";
        }
        if (diffSeconds < 3600) {
            return Math.floor(diffSeconds / 60) + " mins ago";
        }
        if (diffSeconds < 86400) {
            return Math.floor(diffSeconds / 3600) + " hrs ago";
        }
        return Math.floor(diffSeconds / 86400) + " days ago";
    }

    function canViewLeavesAdmin() {
        try {
            var authUser = window.AuthUser || null;
            var permissions = authUser && Array.isArray(authUser.permissions)
                ? authUser.permissions
                : [];
            return permissions.indexOf("leave.view") >= 0;
        } catch (_e) {
            return false;
        }
    }

    function buildNotificationTarget(item) {
        if (!item) {
            return null;
        }

        var data = (item && item.data && typeof item.data === "object") ? item.data : {};
        var eventKey = String(item.eventKey || data.eventKey || data.event || "").trim().toLowerCase();
        if (!eventKey || eventKey.indexOf("leave.") !== 0) {
            return null;
        }

        var requestId = data.leaveRequestId != null ? String(data.leaveRequestId).trim() : "";
        var requestUuid = data.leaveRequestUuid != null ? String(data.leaveRequestUuid).trim() : "";
        if (!requestId && !requestUuid) {
            return null;
        }

        var basePath = canViewLeavesAdmin() ? "/leaves" : "/leaves-employee";
        var params = [];
        if (requestId) {
            params.push("openLeaveRequestId=" + encodeURIComponent(requestId));
        }
        if (requestUuid) {
            params.push("openLeaveRequestUuid=" + encodeURIComponent(requestUuid));
        }

        return basePath + (params.length ? ("?" + params.join("&")) : "");
    }

    function renderDashboardNotifications(items, unreadCount) {
        var titleNode = document.querySelector("[data-employee-dashboard-notifications-title]");
        var bodyNode = document.querySelector("[data-employee-dashboard-notifications-body]");
        if (!titleNode || !bodyNode) {
            return;
        }

        var list = Array.isArray(items) ? items : [];
        titleNode.textContent = "Notifications (" + String(Number(unreadCount || 0)) + ")";

        if (!list.length) {
            bodyNode.innerHTML = '<p class="text-muted mb-0">Belum ada notifikasi terbaru.</p>';
            return;
        }

        var html = '<div class="d-flex flex-column gap-3">';
        list.slice(0, 5).forEach(function (item, idx) {
            var target = buildNotificationTarget(item);
            var title = escapeHtml(item.title || item.eventKey || "Notification");
            var body = escapeHtml(item.body || "");
            var severity = escapeHtml(item.severity || "informational");
            var when = escapeHtml(formatRelativeTime(item.createdAt));
            var toneClass = item.isRead ? "text-muted" : "text-dark";
            var rowClass = idx === list.slice(0, 5).length - 1 ? "" : "border-bottom pb-3";

            html += '<div class="' + rowClass + '">';
            if (target) {
                html += '<a href="' + escapeHtml(target) + '" class="text-decoration-none d-block">';
            }
            html += '<p class="mb-1 fw-semibold ' + toneClass + '">' + title + '</p>';
            if (body) {
                html += '<p class="mb-1 small text-muted">' + body + '</p>';
            }
            html += '<div class="d-flex align-items-center gap-2 small text-muted">';
            html += '<span>' + when + '</span>';
            html += '<span class="badge bg-light text-dark">' + severity + '</span>';
            if (!item.isRead) {
                html += '<span class="badge bg-primary-subtle text-primary">new</span>';
            }
            html += '</div>';
            if (target) {
                html += '</a>';
            }
            html += '</div>';
        });
        html += '</div>';

        bodyNode.innerHTML = html;
    }

    async function refreshDashboardNotifications() {
        var titleNode = document.querySelector("[data-employee-dashboard-notifications-title]");
        var bodyNode = document.querySelector("[data-employee-dashboard-notifications-body]");
        if (!titleNode || !bodyNode) {
            return;
        }

        var token = await getApiToken();
        if (!token) {
            renderDashboardNotifications([], 0);
            return;
        }

        try {
            var response = await fetch("/v1/hcm/notifications?page=1&perPage=5", {
                method: "GET",
                headers: buildNotificationHeaders(token),
                credentials: "same-origin"
            });
            var payload = await response.json().catch(function () { return null; });
            if (!response.ok || !payload || payload.success !== true) {
                renderDashboardNotifications(latestDashboardNotifications, latestDashboardNotifications.length);
                return;
            }

            var data = payload.data || {};
            var items = Array.isArray(data.items) ? data.items : [];
            var unreadCount = data.meta && Number.isFinite(Number(data.meta.unreadCount))
                ? Number(data.meta.unreadCount)
                : items.filter(function (item) { return !item.isRead; }).length;

            latestDashboardNotifications = items.slice();
            renderDashboardNotifications(items, unreadCount);
        } catch (_e) {
            renderDashboardNotifications(latestDashboardNotifications, latestDashboardNotifications.length);
        }
    }

    function renderLegacyLeavesChart(leave, overtime) {
        var chartEl = document.querySelector("#leaves_chart");
        if (!chartEl || typeof window.ApexCharts !== "function") return;

        var baseSeries = [
            Number(leave.approved || 0),
            Number(leave.pending || 0),
            Number(leave.declined || 0),
            Number(overtime.approvedThisMonth || 0)
        ];
        var hasData = baseSeries.some(function (val) { return Number(val || 0) > 0; });
        var series = hasData ? baseSeries : [];
        var labels = hasData ? ["Approved", "Pending", "Declined", "Overtime"] : [];

        if (legacyLeavesChart && typeof legacyLeavesChart.destroy === "function") legacyLeavesChart.destroy();
        chartEl.innerHTML = "";

        legacyLeavesChart = new window.ApexCharts(chartEl, {
            chart: { type: "donut", height: 220 },
            series: series,
            labels: labels,
            colors: ["#16a34a", "#eab308", "#dc2626", "#2563eb"],
            legend: { show: false },
            dataLabels: { enabled: false },
            stroke: { width: 0 },
            plotOptions: { pie: { donut: { size: "70%" } } },
            noData: {
                text: "Belum ada data",
                align: "center",
                verticalAlign: "middle"
            }
        });
        legacyLeavesChart.render();
    }

    function renderPerformanceChart(performance) {
        var chartEl = document.querySelector("#performance_chart2");
        if (!chartEl || typeof window.ApexCharts !== "function") return;

        var seriesRows = Array.isArray(performance.series) ? performance.series : [];
        var categories = seriesRows.map(function (row) { return row.label || "-"; });
        var values = seriesRows.map(function (row) { return Number(row.score || 0); });
        var hasData = values.some(function (val) { return Number(val || 0) > 0; });
        var chartSeries = hasData ? [{ name: "Score", data: values }] : [];

        if (legacyPerformanceChart && typeof legacyPerformanceChart.destroy === "function") legacyPerformanceChart.destroy();
        chartEl.innerHTML = "";

        legacyPerformanceChart = new window.ApexCharts(chartEl, {
            chart: { type: "area", height: 220, toolbar: { show: false } },
            series: chartSeries,
            xaxis: { categories: categories },
            yaxis: { min: 0, max: 100 },
            colors: ["#2563eb"],
            stroke: { curve: "smooth", width: 3 },
            fill: {
                type: "gradient",
                gradient: { shadeIntensity: 1, opacityFrom: 0.35, opacityTo: 0.08, stops: [0, 100] }
            },
            dataLabels: { enabled: false },
            noData: {
                text: "Belum ada data",
                align: "center",
                verticalAlign: "middle"
            }
        });
        legacyPerformanceChart.render();
    }

    function emptyDashboardData() {
        return {
            profile: {},
            attendanceToday: {},
            attendanceStats: {},
            leave: {},
            overtime: {},
            payroll: {},
            ui: {},
            nextHoliday: {},
            leavePolicy: {},
            teamBirthday: {},
            teamMembers: [],
            performance: { currentPercent: 0, vsLastPercent: 0, series: [] },
            mySkills: []
        };
    }

    function renderMySkills(skills) {
        var body = document.querySelector("[data-my-skills-body]");
        if (!body) return;

        var rows = Array.isArray(skills) ? skills : [];
        if (!rows.length) {
            body.innerHTML = '<div class="border border-dashed bg-transparent-light rounded p-3"><p class="mb-0 text-muted">Belum ada data skill/training.</p></div>';
            return;
        }

        var tones = ["primary", "success", "purple", "info", "dark"];
        var html = rows.map(function (row, idx) {
            var tone = tones[idx % tones.length];
            var level = Math.max(0, Math.min(100, Number(row.level || 0)));
            var name = row.name || "Skill";
            var updated = row.updatedAt || "-";
            return '' +
                '<div class="border border-dashed bg-transparent-light rounded p-2 mb-2">' +
                '<div class="d-flex align-items-center justify-content-between">' +
                '<div class="d-flex align-items-center">' +
                '<span class="d-block border border-2 h-12 border-' + tone + ' rounded-5 me-2"></span>' +
                '<div><h6 class="fw-medium mb-1">' + name + '</h6><p>Updated : ' + updated + '</p></div>' +
                '</div>' +
                '<div class="circle-progress circle-progress-md" data-value="' + level + '">' +
                '<span class="progress-left"><span class="progress-bar border-' + tone + '"></span></span>' +
                '<span class="progress-right"><span class="progress-bar border-' + tone + '"></span></span>' +
                '<div class="progress-value">' + level + '%</div>' +
                '</div>' +
                '</div>' +
                '</div>';
        }).join("");

        body.innerHTML = html;
        Array.prototype.forEach.call(body.querySelectorAll(".circle-progress"), function (node) {
            updateRingProgress(node, node.getAttribute("data-value"));
        });
    }

    function renderTeamMembers(members) {
        var body = document.querySelector("[data-team-members-body]");
        if (!body) return;

        var list = Array.isArray(members) ? members : [];
        if (!list.length) {
            body.innerHTML = '<p class="text-muted mb-0">Belum ada anggota tim yang bisa ditampilkan.</p>';
            return;
        }

        var html = list.slice(0, 6).map(function (m, index) {
            var mb = index === list.slice(0, 6).length - 1 ? "" : " mb-4";
            var photo = m.photoUrl || "/build/img/users/user-27.jpg";
            var name = m.name || "-";
            var role = m.designation || "Employee";
            var phoneHref = m.phone ? "tel:" + m.phone : "javascript:void(0);";
            var emailHref = m.email ? "mailto:" + m.email : "javascript:void(0);";

            return '' +
                '<div class="d-flex align-items-center justify-content-between' + mb + '">' +
                '<div class="d-flex align-items-center">' +
                '<a href="javascript:void(0);" class="avatar flex-shrink-0"><img src="' + photo + '" class="rounded-circle border border-2" alt="img"></a>' +
                '<div class="ms-2"><h6 class="fs-14 fw-medium text-truncate mb-1"><a href="javascript:void(0);">' + name + '</a></h6><p class="fs-13">' + role + '</p></div>' +
                '</div>' +
                '<div class="d-flex align-items-center">' +
                '<a href="' + phoneHref + '" class="btn btn-light btn-icon btn-sm me-2"><i class="ti ti-phone fs-16"></i></a>' +
                '<a href="' + emailHref + '" class="btn btn-light btn-icon btn-sm me-2"><i class="ti ti-mail-bolt fs-16"></i></a>' +
                '<a href="javascript:void(0);" class="btn btn-light btn-icon btn-sm"><i class="ti ti-brand-hipchat fs-16"></i></a>' +
                '</div>' +
                '</div>';
        }).join("");

        body.innerHTML = html;
    }

    function bindLegacyTemplate(data) {
        var profile = data.profile || {};
        var attendanceToday = data.attendanceToday || {};
        var attendanceStats = data.attendanceStats || {};
        var leave = data.leave || {};
        var overtime = data.overtime || {};
        var nextHoliday = data.nextHoliday || {};
        var leavePolicy = data.leavePolicy || {};
        var teamBirthday = data.teamBirthday || {};
        var performance = data.performance || {};

        setText("[data-employee-legacy-name]", profile.name, "User");
        setText("[data-employee-legacy-designation]", profile.designation, "Employee");
        setText("[data-employee-legacy-team]", profile.team, "General");
        setText("[data-employee-legacy-phone]", profile.phone);
        setText("[data-employee-legacy-email]", profile.email);
        setText("[data-employee-legacy-report-office]", profile.reportOffice || "-");
        setText("[data-employee-legacy-join-date]", formatDate(profile.joinDate));
        var avatar = document.querySelector("[data-employee-legacy-avatar]");
        if (avatar && profile.profilePhotoUrl) {
            avatar.setAttribute("src", profile.profilePhotoUrl);
        }

        setText("[data-employee-legacy-now-label]", attendanceToday.nowLabel);
        setText("[data-employee-legacy-total-hours]", attendanceToday.summaryTotalWorking);
        setText("[data-employee-legacy-production-badge]", "Production : " + formatHours(attendanceToday.productionHours) + " hrs");
        setText("[data-employee-legacy-punch-line]", "Punch In at " + (attendanceToday.punchInAt || "-"));
        renderAttendanceMap(attendanceToday);

        var punchButton = document.querySelector("[data-employee-legacy-punch-button]");
        if (punchButton) {
            if (!attendanceToday.canPunch) {
                punchButton.textContent = "View Attendance";
                punchButton.setAttribute("href", "/attendance-employee");
                punchButton.dataset.punchAction = "view";
                punchButton.classList.remove("btn-success");
                punchButton.classList.add("btn-primary");
            } else if (attendanceToday.punchState === "done") {
                punchButton.textContent = "Attendance Completed";
                punchButton.dataset.punchAction = "none";
                punchButton.classList.remove("btn-primary");
                punchButton.classList.add("btn-success");
            } else if (attendanceToday.punchState === "in") {
                punchButton.textContent = "Punch Out";
                punchButton.dataset.punchAction = "punch";
                punchButton.classList.remove("btn-success");
                punchButton.classList.add("btn-primary");
            } else {
                punchButton.textContent = "Punch In";
                punchButton.dataset.punchAction = "punch";
                punchButton.classList.remove("btn-success");
                punchButton.classList.add("btn-primary");
            }
        }

        // Handle selfie button state
        var selfieBtn = document.querySelector("[data-attendance-me-selfie-btn]");
        if (selfieBtn) {
            var canSelfie = attendanceToday.punchState === "in" || attendanceToday.punchState === "done";
            selfieBtn.setAttribute("data-arcav-selfie-allowed", canSelfie ? "1" : "0");
            selfieBtn.setAttribute(
                "title",
                canSelfie ? "" : "Lakukan punch masuk terlebih dahulu untuk mengambil selfie."
            );
        }

        updateRingProgress(document.querySelector("[data-employee-legacy-attendance-progress]"), attendanceToday.progressPercent || 0);

        setText("[data-employee-legacy-stat-today-hours]", formatHours(attendanceStats.todayHours), "0.0");
        setText("[data-employee-legacy-stat-today-target]", attendanceStats.todayTarget || 9, "9");
        setText("[data-employee-legacy-stat-week-hours]", formatHours(attendanceStats.weekHours), "0.0");
        setText("[data-employee-legacy-stat-week-target]", attendanceStats.weekTarget || 40, "40");
        setText("[data-employee-legacy-stat-month-hours]", formatHours(attendanceStats.monthHours), "0.0");
        setText("[data-employee-legacy-stat-month-target]", attendanceStats.monthTarget || 98, "98");
        setText("[data-employee-legacy-stat-ot-hours]", formatHours(attendanceStats.monthOvertimeHours), "0.0");
        setText("[data-employee-legacy-stat-ot-target]", attendanceStats.monthOvertimeTarget || 28, "28");

        var todayHours = Number(attendanceStats.todayHours || 0);
        var todayTarget = Number(attendanceStats.todayTarget || 8);
        var weekHours = Number(attendanceStats.weekHours || 0);
        var weekTarget = Number(attendanceStats.weekTarget || 40);
        var monthHours = Number(attendanceStats.monthHours || 0);
        var monthTarget = Number(attendanceStats.monthTarget || 98);
        var otHours = Number(attendanceStats.monthOvertimeHours || 0);
        var otTarget = Number(attendanceStats.monthOvertimeTarget || 28);

        var todayPct = todayTarget > 0 ? Math.min(100, Math.round((todayHours / todayTarget) * 100)) : 0;
        var weekPct = weekTarget > 0 ? Math.min(100, Math.round((weekHours / weekTarget) * 100)) : 0;
        var monthPct = monthTarget > 0 ? Math.min(100, Math.round((monthHours / monthTarget) * 100)) : 0;
        var otPct = otTarget > 0 ? Math.min(100, Math.round((otHours / otTarget) * 100)) : 0;

        setText("[data-employee-legacy-stat-today-hint]", "Tercapai " + todayPct + "% dari target harian.");
        setText("[data-employee-legacy-stat-week-hint]", "Progress minggu ini " + weekPct + "% dari target.");
        setText("[data-employee-legacy-stat-month-hint]", "Progress bulan ini " + monthPct + "% dari target.");
        setText("[data-employee-legacy-stat-ot-hint]", "Lembur bulan ini " + otPct + "% dari batas acuan.");

        setText("[data-employee-legacy-summary-total-working]", attendanceToday.summaryTotalWorking);
        setText("[data-employee-legacy-summary-productive]", attendanceToday.summaryProductive);
        setText("[data-employee-legacy-summary-break]", attendanceToday.summaryBreak);
        setText("[data-employee-legacy-summary-overtime]", attendanceToday.summaryOvertime);
        renderLegacyTimeline(attendanceToday, attendanceStats);

        setText("[data-employee-legacy-leave-total]", leave.total || 0, "0");
        setText("[data-employee-legacy-leave-taken]", leave.approved || 0, "0");
        setText("[data-employee-legacy-leave-absent]", leave.declined || 0, "0");
        setText("[data-employee-legacy-leave-request]", leave.pending || 0, "0");
        setText("[data-employee-legacy-worked-days]", Math.max(0, Math.round(Number(attendanceStats.monthHours || 0) / 8)), "0");
        setText("[data-employee-legacy-loss-of-pay]", leave.declined || 0, "0");

        setText("[data-employee-legacy-leave-approved]", leave.approved || 0, "0");
        setText("[data-employee-legacy-leave-pending]", leave.pending || 0, "0");
        setText("[data-employee-legacy-ot-approved]", overtime.approvedThisMonth || 0, "0");
        setText("[data-employee-legacy-leave-declined]", leave.declined || 0, "0");
        setText("[data-employee-legacy-ot-hours]", formatHours(overtime.approvedHoursThisMonth), "0.0");
        setText("[data-employee-legacy-leave-insight]", "Total " + (leave.total || 0) + " request(s), " + (leave.pending || 0) + " still pending approval");

        var leaveAlert = document.querySelector("[data-employee-legacy-leave-alert]");
        var leaveAlertText = document.querySelector("[data-employee-legacy-leave-alert-text]");
        if (leaveAlert && leaveAlertText) {
            if ((leave.pending || 0) > 0) {
                leaveAlert.classList.remove("d-none");
                leaveAlertText.textContent = "Kamu masih punya " + (leave.pending || 0) + " pengajuan cuti menunggu approval.";
            } else if ((leave.approved || 0) > 0) {
                leaveAlert.classList.remove("d-none");
                leaveAlertText.textContent = "Pengajuan cuti terbaru kamu sudah diproses. Approved: " + (leave.approved || 0) + ", Declined: " + (leave.declined || 0) + ".";
            } else {
                leaveAlert.classList.add("d-none");
            }
        }

        setText("[data-next-holiday-label]", (nextHoliday.title || "-") + ", " + (nextHoliday.dateLabel || "-"), "-");
        setText("[data-leave-policy-last-updated]", "Last Updated : " + (leavePolicy.lastUpdated || "-"));
        setText("[data-team-birthday-name]", teamBirthday.name || "-");
        setText("[data-team-birthday-role]", (teamBirthday.designation || "-") + " • " + (teamBirthday.birthdayLabel || "-"));

        var birthdayImg = document.querySelector("[data-team-birthday-photo]");
        if (birthdayImg && teamBirthday.photoUrl) birthdayImg.setAttribute("src", teamBirthday.photoUrl);

        var wishBtn = document.querySelector("[data-team-birthday-wish]");
        if (wishBtn) {
            wishBtn.onclick = function (event) {
                event.preventDefault();
                var nm = teamBirthday.name || "Teammate";
                notify("Jangan lupa kirim ucapan ulang tahun ke " + nm + ".", "info");
            };
        }

        setText("[data-performance-current-percent]", (performance.currentPercent || 0) + "%", "0%");
        var trend = Number(performance.vsLastPercent || 0);
        var trendEl = document.querySelector("[data-performance-vs-last]");
        if (trendEl) {
            trendEl.textContent = (trend > 0 ? "+" : "") + trend.toFixed(1) + "%";
            trendEl.classList.remove("bg-success-transparent", "bg-danger-transparent", "text-success", "text-danger");
            if (trend >= 0) {
                trendEl.classList.add("bg-success-transparent", "text-success");
            } else {
                trendEl.classList.add("bg-danger-transparent", "text-danger");
            }
        }

        renderLegacyLeavesChart(leave, overtime);
        renderPerformanceChart(performance);
        renderMySkills(data.mySkills || []);
        renderTeamMembers(data.teamMembers || []);
    }

    function bindModernTemplate(data) {
        var profile = data.profile || {};
        var attendanceToday = data.attendanceToday || {};
        var attendanceStats = data.attendanceStats || {};
        var leave = data.leave || {};
        var overtime = data.overtime || {};
        var payroll = data.payroll || {};

        setText("[data-employee-greeting]", profile.greeting || "Good Day");
        setText("[data-employee-name]", profile.name, "User");
        setText("[data-employee-designation]", profile.designation, "Employee");
        setText("[data-employee-team]", profile.team, "General");
        setText("[data-employee-email]", profile.email);
        setText("[data-employee-phone]", profile.phone);
        setText("[data-employee-join-date]", profile.joinDate);

        setText("[data-attendance-checkin]", attendanceToday.punchInAt);
        setText("[data-attendance-checkout]", attendanceToday.punchOutAt);
        setText("[data-attendance-now-label]", attendanceToday.nowLabel);
        setText("[data-attendance-production-hours]", formatHours(attendanceToday.productionHours), "0.0");
        setWidth("[data-attendance-progress-bar]", attendanceToday.progressPercent);
        setText("[data-attendance-total-working]", attendanceToday.summaryTotalWorking);
        setText("[data-attendance-break]", attendanceToday.summaryBreak);
        setText("[data-attendance-productive]", attendanceToday.summaryProductive);
        setText("[data-attendance-overtime]", attendanceToday.summaryOvertime);

        var warning = document.querySelector("[data-attendance-warning]");
        if (warning) {
            if (attendanceToday.needsReview) warning.classList.remove("d-none");
            else warning.classList.add("d-none");
        }

        setText("[data-stat-today-hours]", formatHours(attendanceStats.todayHours), "0.0");
        setText("[data-stat-today-target]", attendanceStats.todayTarget || 9, "9");
        setText("[data-stat-week-hours]", formatHours(attendanceStats.weekHours), "0.0");
        setText("[data-stat-week-target]", attendanceStats.weekTarget || 40, "40");
        setText("[data-stat-month-hours]", formatHours(attendanceStats.monthHours), "0.0");
        setText("[data-stat-month-target]", attendanceStats.monthTarget || 98, "98");
        setText("[data-stat-month-ot-hours]", formatHours(attendanceStats.monthOvertimeHours), "0.0");
        setText("[data-stat-month-ot-target]", attendanceStats.monthOvertimeTarget || 28, "28");

        setText("[data-leave-total]", leave.total || 0, "0");
        setText("[data-leave-pending]", leave.pending || 0, "0");
        setText("[data-leave-approved]", leave.approved || 0, "0");
        setText("[data-leave-declined]", leave.declined || 0, "0");

        setText("[data-ot-pending]", overtime.pending || 0, "0");
        setText("[data-ot-approved-month]", overtime.approvedThisMonth || 0, "0");
        setText("[data-ot-approved-hours]", formatHours(overtime.approvedHoursThisMonth), "0.0");

        setText("[data-payroll-latest-period]", payroll.latestPeriod);
        setText("[data-payroll-latest-status]", payroll.latestRunStatus);
        setText("[data-payroll-payment-status]", payroll.paymentStatus);
        setText("[data-payroll-net-pay]", formatRupiah(payroll.latestNetPay || 0), "Rp 0");
    }

    function bindYearAndDateUi(data) {
        var ui = data.ui || {};
        var year = ui.referenceYear || new Date().getFullYear();
        Array.prototype.forEach.call(document.querySelectorAll("[data-employee-dashboard-year]"), function (el) {
            var icon = el.querySelector("i");
            if (icon) {
                el.innerHTML = icon.outerHTML + String(year);
            } else {
                el.textContent = String(year);
            }
        });

        var dateInput = document.querySelector("[data-employee-dashboard-date]");
        if (dateInput && ui.referenceDate) {
            var d = new Date(ui.referenceDate + "T00:00:00");
            if (!isNaN(d.getTime())) {
                var dd = String(d.getDate()).padStart(2, "0");
                var mm = String(d.getMonth() + 1).padStart(2, "0");
                var yyyy = d.getFullYear();
                dateInput.value = dd + "-" + mm + "-" + yyyy;
            }
        }
    }

    function exportSummary(format) {
        if (!latestDashboardData) return;
        var data = latestDashboardData;
        if (format === "pdf") {
            window.print();
            return;
        }

        var rows = [
            ["Metric", "Value"],
            ["Employee", data.profile && data.profile.name || "-"],
            ["Designation", data.profile && data.profile.designation || "-"],
            ["Team", data.profile && data.profile.team || "-"],
            ["Reference Date", data.ui && data.ui.referenceDate || "-"],
            ["Today Hours", data.attendanceStats && data.attendanceStats.todayHours || 0],
            ["Week Hours", data.attendanceStats && data.attendanceStats.weekHours || 0],
            ["Month Hours", data.attendanceStats && data.attendanceStats.monthHours || 0],
            ["Month Overtime", data.attendanceStats && data.attendanceStats.monthOvertimeHours || 0],
            ["Leave Total", data.leave && data.leave.total || 0],
            ["Leave Pending", data.leave && data.leave.pending || 0],
            ["Overtime Pending", data.overtime && data.overtime.pending || 0],
            ["Latest Payroll Period", data.payroll && data.payroll.latestPeriod || "-"],
            ["Latest Net Pay", data.payroll && data.payroll.latestNetPay || 0]
        ];

        var csv = rows.map(function (r) {
            return r.map(function (cell) {
                var text = String(cell == null ? "" : cell).replace(/"/g, '""');
                return '"' + text + '"';
            }).join(",");
        }).join("\n");

        var ext = format === "excel" ? "xls" : "csv";
        var blob = new Blob([csv], { type: "text/csv;charset=utf-8;" });
        var url = window.URL.createObjectURL(blob);
        var a = document.createElement("a");
        a.href = url;
        a.download = "employee-dashboard-summary." + ext;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        window.URL.revokeObjectURL(url);
    }

    function bindExportButtons() {
        Array.prototype.forEach.call(document.querySelectorAll("[data-employee-export]"), function (btn) {
            btn.addEventListener("click", function (event) {
                event.preventDefault();
                exportSummary(String(btn.getAttribute("data-employee-export") || "csv"));
            });
        });
    }

    function bindDateFilter() {
        var dateInput = document.querySelector("[data-employee-dashboard-date]");
        if (!dateInput) return;

        var handler = function () {
            var dateIso = parseDateInput(dateInput.value);
            loadDashboardSummary(dateIso || "");
        };

        dateInput.addEventListener("change", handler);
        dateInput.addEventListener("blur", handler);
    }

    function bindPunchAction(currentDateIso) {
        var punchButton = document.querySelector("[data-employee-legacy-punch-button]");
        if (!punchButton || punchButton.dataset.punchBound === "1") return;
        punchButton.dataset.punchBound = "1";

        punchButton.addEventListener("click", function (event) {
            var action = String(punchButton.dataset.punchAction || "");
            if (action !== "punch") return;

            event.preventDefault();

            if (!navigator.geolocation) {
                notify("Browser tidak mendukung geolocation untuk punch attendance.", "warning");
                return;
            }

            punchButton.classList.add("disabled");
            navigator.geolocation.getCurrentPosition(function (pos) {
                var payload = {
                    latitude: Number(pos.coords && pos.coords.latitude || 0),
                    longitude: Number(pos.coords && pos.coords.longitude || 0)
                };

                apiPost("/v1/hcm/attendance/me/punch", payload).then(function () {
                    loadDashboardSummary(currentDateIso || "");
                }).catch(function (err) {
                    var msg = "Punch attendance gagal.";
                    if (err && err.data && err.data.error && err.data.error.message) {
                        msg = err.data.error.message;
                    }
                    notify(msg, "danger");
                }).finally(function () {
                    punchButton.classList.remove("disabled");
                });
            }, function () {
                punchButton.classList.remove("disabled");
                notify("Akses lokasi ditolak. Punch attendance butuh izin lokasi.", "warning");
            }, { enableHighAccuracy: true, timeout: 10000 });
        });
    }

    function bindGpsPreviewAction() {
        var gpsButton = document.querySelector("[data-employee-legacy-gps-button]");
        if (!gpsButton || gpsButton.dataset.gpsBound === "1") return;
        gpsButton.dataset.gpsBound = "1";

        gpsButton.addEventListener("click", function () {
            if (!navigator.geolocation) {
                notify("Browser tidak mendukung geolocation untuk preview GPS attendance.", "warning");
                return;
            }

            var originalHtml = gpsButton.innerHTML;
            gpsButton.disabled = true;
            gpsButton.innerHTML = '<i class="ti ti-loader-2 me-1"></i> Memuat GPS...';

            navigator.geolocation.getCurrentPosition(function (pos) {
                var attendanceToday = Object.assign({}, (latestDashboardData && latestDashboardData.attendanceToday) || {}, {
                    previewLatitude: Number(pos.coords && pos.coords.latitude || 0),
                    previewLongitude: Number(pos.coords && pos.coords.longitude || 0),
                    previewLabel: "GPS perangkat terdeteksi. Lokasi siap dipakai untuk attendance."
                });
                renderAttendanceMap(attendanceToday);
                notify("Lokasi GPS berhasil dimuat.", "success");
                gpsButton.disabled = false;
                gpsButton.innerHTML = originalHtml;
            }, function () {
                gpsButton.disabled = false;
                gpsButton.innerHTML = originalHtml;
                notify("Akses lokasi ditolak. Izinkan GPS untuk preview lokasi attendance.", "warning");
            }, { enableHighAccuracy: true, timeout: 10000 });
        });
    }

    function bindDashboard(data) {
        latestDashboardData = data || {};
        bindModernTemplate(data || {});
        bindLegacyTemplate(data || {});
        bindYearAndDateUi(data || {});

        // --- Patch: Update Employee Summary Widgets ---
        // Defensive: handle missing keys gracefully
        var exec = (data && data.executive) || {};
        var workforce = (data && data.workforceAndAlerts) || {};
        // Total employee = active + inactive (assume inactive = total - active)
        var total = (exec.activeEmployees || 0) + (exec.inactiveEmployees || 0);
        var active = exec.activeEmployees || 0;
        var inactive = exec.inactiveEmployees || 0;
        // If inactive not provided, try to infer from total
        if (!inactive && typeof exec.totalEmployees === 'number') {
            inactive = exec.totalEmployees - active;
        }
        // New joiners this month
        var joiners = workforce.joinerThisMonth || 0;

        setText('[data-employees-total]', total, '0');
        setText('[data-employees-active]', active, '0');
        setText('[data-employees-inactive]', inactive, '0');
        setText('[data-employees-new-joiners]', joiners, '0');

        var ui = (data && data.ui) || {};
        bindPunchAction(ui.referenceDate || "");
        bindGpsPreviewAction();
    }

    function loadDashboardSummary(dateIso) {
        var url = "/v1/hcm/employee-dashboard-summary";
        if (dateIso) {
            url += "?date=" + encodeURIComponent(dateIso);
        }

        apiGet(url).then(function (payload) {
            if (!payload || payload.success !== true) {
                notify("Data dashboard tidak valid. Menampilkan data kosong.", "warning");
                bindDashboard(emptyDashboardData());
                return;
            }
            bindDashboard(payload.data || emptyDashboardData());
        }).catch(function (err) {
            var status = err && err.status ? err.status : (err && err.response ? err.response.status : 0);
            var data = err && err.data ? err.data : (err && err.response ? err.response.data : null);
            if (window.AuthApi && window.AuthApi.handleUnauthorizedFromApi && window.AuthApi.handleUnauthorizedFromApi(status, data)) {
                return;
            }

            var apiMessage = data && data.error && data.error.message ? String(data.error.message) : "Gagal memuat dashboard employee.";
            notify(apiMessage, "danger");
            bindDashboard(latestDashboardData || emptyDashboardData());
        });
    }

    function init() {
        var path = String(window.location.pathname || "").replace(/\/+$/, "") || "/";
        if (path !== "/employee-dashboard") return;

        bindExportButtons();
        bindDateFilter();

        var dateInput = document.querySelector("[data-employee-dashboard-date]");
        var dateIso = dateInput ? parseDateInput(dateInput.value) : "";
        loadDashboardSummary(dateIso || "");
        refreshDashboardNotifications();

        var refreshBtn = document.querySelector("[data-employee-dashboard-notifications-refresh]");
        if (refreshBtn) {
            refreshBtn.addEventListener("click", function (event) {
                event.preventDefault();
                refreshDashboardNotifications();
            });
        }

        window.setInterval(refreshDashboardNotifications, 60000);
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", init);
    } else {
        init();
    }
})(window, document);
