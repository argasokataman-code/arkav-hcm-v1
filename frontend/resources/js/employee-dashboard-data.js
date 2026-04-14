(function (window, document) {
    "use strict";

    var legacyLeavesChart = null;
    var legacyPerformanceChart = null;
    var latestDashboardData = null;
    var attendanceMap = null;
    var attendanceMarker = null;

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

        var hasCheckOut = checkOutLat !== null && checkOutLng !== null;
        var hasCheckIn = checkInLat !== null && checkInLng !== null;

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
        }

        if (lat !== null && lng !== null) {
            attendanceMap.setView([lat, lng], 16);
            if (!attendanceMarker) {
                attendanceMarker = window.L.marker([lat, lng]).addTo(attendanceMap);
            } else {
                attendanceMarker.setLatLng([lat, lng]);
            }
        } else if (attendanceMarker) {
            attendanceMap.removeLayer(attendanceMarker);
            attendanceMarker = null;
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

        updateRingProgress(document.querySelector("[data-employee-legacy-attendance-progress]"), attendanceToday.progressPercent || 0);

        setText("[data-employee-legacy-stat-today-hours]", formatHours(attendanceStats.todayHours), "0.0");
        setText("[data-employee-legacy-stat-today-target]", attendanceStats.todayTarget || 9, "9");
        setText("[data-employee-legacy-stat-week-hours]", formatHours(attendanceStats.weekHours), "0.0");
        setText("[data-employee-legacy-stat-week-target]", attendanceStats.weekTarget || 40, "40");
        setText("[data-employee-legacy-stat-month-hours]", formatHours(attendanceStats.monthHours), "0.0");
        setText("[data-employee-legacy-stat-month-target]", attendanceStats.monthTarget || 98, "98");
        setText("[data-employee-legacy-stat-ot-hours]", formatHours(attendanceStats.monthOvertimeHours), "0.0");
        setText("[data-employee-legacy-stat-ot-target]", attendanceStats.monthOvertimeTarget || 28, "28");

        setText("[data-employee-legacy-summary-total-working]", attendanceToday.summaryTotalWorking);
        setText("[data-employee-legacy-summary-productive]", attendanceToday.summaryProductive);
        setText("[data-employee-legacy-summary-break]", attendanceToday.summaryBreak);
        setText("[data-employee-legacy-summary-overtime]", attendanceToday.summaryOvertime);

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

    function bindDashboard(data) {
        latestDashboardData = data || {};
        bindModernTemplate(data || {});
        bindLegacyTemplate(data || {});
        bindYearAndDateUi(data || {});

        var ui = (data && data.ui) || {};
        bindPunchAction(ui.referenceDate || "");
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
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", init);
    } else {
        init();
    }
})(window, document);
