export function createEmployeeAttendanceModule(deps) {
    var esc = deps.esc;
    var apiGet = deps.apiGet;
    var renderMeHistoryMessage = deps.renderMeHistoryMessage;
    var syncAttendanceCircle = deps.syncAttendanceCircle;
    var syncPunchMapFromMe = deps.syncPunchMapFromMe;
    var startBreakTicker = deps.startBreakTicker;
    var stopBreakTicker = deps.stopBreakTicker;
    var ensureInteractivePunchMap = deps.ensureInteractivePunchMap;
    var getMeHistoryCache = deps.getMeHistoryCache;
    var setMeHistoryCache = deps.setMeHistoryCache;
    var getMeRefreshTimer = deps.getMeRefreshTimer;
    var setMeRefreshTimer = deps.setMeRefreshTimer;

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
            if (d.correctionStatus === "approved") {
                // GAP-G: approved — hide correction button, show approved label
                correctionBtn.classList.add("d-none");
                correctionBtn.disabled = true;
            } else if (d.correctionStatus === "dismissed") {
                correctionBtn.classList.add("d-none");
                correctionBtn.disabled = true;
            } else if (d.needsReview || d.correctionStatus === "requested") {
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
                // GAP-G: show approved badge when correction was approved
                var corrStatus = String(row.correctionStatus || "");
                var corrApprovedBadge = corrStatus === "approved"
                    ? ' <span class="badge badge-soft-success ms-1" title="Correction approved"><i class="ti ti-check me-1"></i>Koreksi disetujui</span>'
                    : corrStatus === "dismissed"
                    ? ' <span class="badge badge-soft-danger ms-1" title="Correction dismissed"><i class="ti ti-x me-1"></i>Koreksi ditolak</span>'
                    : "";
                // GAP-H + GAP-O: show correction request button when eligible
                var corrActionCell = "";
                if (corrStatus === "requested") {
                    corrActionCell = '<button type="button" class="btn btn-xs btn-light border" disabled title="Koreksi sudah diajukan">Koreksi diajukan</button>';
                } else if (corrStatus === "dismissed") {
                    corrActionCell = '<span class="text-muted fs-12">—</span>';
                } else if (row.correctionEligible) {
                    corrActionCell = '<button type="button" class="btn btn-xs btn-outline-warning" data-attendance-me-request-correction data-work-date="' + esc(row.workDate || "") + '">Ajukan koreksi</button>';
                }
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
                    "</span>" +
                    corrApprovedBadge +
                    "</td>" +
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
                    "<td>" + corrActionCell + "</td>" +
                    "</tr>"
                );
            })
            .join("");
        tbody.setAttribute("data-hydrated", "1");
    }

    function clearMeRefreshTimer() {
        var timer = getMeRefreshTimer();
        if (timer) {
            window.clearTimeout(timer);
            setMeRefreshTimer(null);
        }
    }

    function scheduleMeRefresh(isPunchInProgress, loadEmployeeAttendance) {
        clearMeRefreshTimer();
        if (!isPunchInProgress) {
            return;
        }
        setMeRefreshTimer(
            window.setTimeout(function () {
                loadEmployeeAttendance();
            }, 30000)
        );
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
                    scheduleMeRefresh(todayPayload.data.punchState === "in", loadEmployeeAttendance);
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
                    setMeHistoryCache(Array.isArray(histPayload.data) ? histPayload.data : []);
                    renderMeHistory(getMeHistoryCache());
                } else {
                    renderMeHistoryMessage("Unable to load attendance history.");
                }
            })
            .catch(function (err) {
                var status = err && err.response ? err.response.status : err && err.status ? err.status : 0;
                var data = err && err.response ? err.response.data : err && err.data ? err.data : null;
                if (window.AuthApi && window.AuthApi.handleUnauthorizedFromApi(status, data)) {
                    return;
                }
                renderMeHistoryMessage("Failed loading attendance history.");
            });
    }

    return {
        applyMeToday: applyMeToday,
        applyMeStats: applyMeStats,
        renderMeHistory: renderMeHistory,
        clearMeRefreshTimer: clearMeRefreshTimer,
        scheduleMeRefresh: scheduleMeRefresh,
        loadEmployeeAttendance: loadEmployeeAttendance,
    };
}