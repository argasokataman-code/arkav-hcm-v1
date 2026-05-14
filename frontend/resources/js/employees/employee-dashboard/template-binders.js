(function (window, document) {
    "use strict";

    window.ArcavEmployeeDashboardTemplateBinders = function (deps) {
        var setText = deps.setText;
        var setWidth = deps.setWidth;
        var formatHours = deps.formatHours;
        var formatDate = deps.formatDate;
        var formatRupiah = deps.formatRupiah;
        var withCacheBust = deps.withCacheBust;
        var notify = deps.notify;
        var renderAttendanceMap = deps.renderAttendanceMap;
        var updateRingProgress = deps.updateRingProgress;
        var renderLegacyTimeline = deps.renderLegacyTimeline;
        var renderLegacyLeavesChart = deps.renderLegacyLeavesChart;
        var renderPerformanceChart = deps.renderPerformanceChart;

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
                var photo = m.photoUrl ? withCacheBust(m.photoUrl) : "/build/img/users/user-27.jpg";
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
                avatar.setAttribute("src", withCacheBust(profile.profilePhotoUrl));
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
            if (birthdayImg && teamBirthday.photoUrl) birthdayImg.setAttribute("src", withCacheBust(teamBirthday.photoUrl));

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

        return {
            bindLegacyTemplate: bindLegacyTemplate,
            bindModernTemplate: bindModernTemplate,
            renderMySkills: renderMySkills,
            renderTeamMembers: renderTeamMembers
        };
    };
})(window, document);
