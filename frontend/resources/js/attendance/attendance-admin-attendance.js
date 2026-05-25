export function createAdminAttendanceModule(deps) {
    var todayIsoLocal = deps.todayIsoLocal;
    var formatIsoDate = deps.formatIsoDate;
    var esc = deps.esc;
    var filterAndSortAdminRows = deps.filterAndSortAdminRows;
    var downloadCsv = deps.downloadCsv;
    var getAdminFilters = deps.getAdminFilters;
    var fillAdminDepartmentFilter = deps.fillAdminDepartmentFilter;
    var apiGet = deps.apiGet;
    var apiBlobGet = deps.apiBlobGet;
    var renderAdminMessage = deps.renderAdminMessage;
    var formatApiError = deps.formatApiError;
    var getAdminAttendancePage = deps.getAdminAttendancePage;
    var setAdminAttendancePage = deps.setAdminAttendancePage;
    var getAdminRowsCache = deps.getAdminRowsCache;
    var setAdminRowsCache = deps.setAdminRowsCache;

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

    function setupAdminDateFilter(loadAdminAttendance) {
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
            setAdminAttendancePage(1);
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

    function setupAdminPaginationControls(loadAdminAttendance) {
        var foot = document.querySelector("[data-attendance-admin-pagination]");
        if (!foot) {
            return;
        }
        var prev = foot.querySelector("[data-attendance-admin-prev]");
        var next = foot.querySelector("[data-attendance-admin-next]");
        if (prev && !prev.getAttribute("data-bound")) {
            prev.setAttribute("data-bound", "1");
            prev.addEventListener("click", function () {
                if (getAdminAttendancePage() > 1) {
                    setAdminAttendancePage(getAdminAttendancePage() - 1);
                    loadAdminAttendance();
                }
            });
        }
        if (next && !next.getAttribute("data-bound")) {
            next.setAttribute("data-bound", "1");
            next.addEventListener("click", function () {
                setAdminAttendancePage(getAdminAttendancePage() + 1);
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
                      '" data-user-id="' +
                      esc(String(row.userId || "")) +
                      '" data-record-id="' +
                      esc(String(row.recordId || "")) +
                      '" data-work-date="' +
                      esc(row.workDate || getSelectedAdminDate()) +
                      '" data-check-in="' +
                      esc(row.checkInTime24 || "") +
                      '" data-check-out="' +
                      esc(row.checkOutTime24 || "") +
                      '" data-break="' +
                      esc(String(row.breakMinutesRaw != null ? row.breakMinutesRaw : 0)) +
                      '" data-late="' +
                      esc(String(row.lateMinutesRaw != null ? row.lateMinutesRaw : 0)) +
                      '" data-bs-toggle="modal" data-bs-target="#arcav_attendance_correction_detail"><i class="ti ti-message-circle"></i></a>'
                    : "";
                var checkInLoc = row.checkInLocation || "-";
                var checkOutLoc = row.checkOutLocation || "-";
                                var teamLabel = row.team || "—";
                                var emailLabel = row.employeeEmail || "";
                var selfieCell = row.hasSelfie
                    ? '<button type="button" class="btn btn-sm btn-outline-primary" data-selfie-view="' +
                      esc(String(row.recordId || "")) +
                      '" data-employee="' +
                      esc(row.employeeName || "") +
                      '">Lihat</button>'
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
                    esc(teamLabel + (emailLabel ? " • " + emailLabel : "")) +
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

    function rerenderAdminRowsFromCache() {
        renderAdminRows(Array.isArray(getAdminRowsCache()) ? getAdminRowsCache() : []);
    }

    function bindSelfieViewDelegation() {
        var tbody = document.querySelector("[data-attendance-admin-body]");
        if (!tbody || tbody.getAttribute("data-selfie-delegation") === "1") {
            return;
        }
        tbody.setAttribute("data-selfie-delegation", "1");
        tbody.addEventListener("click", function (e) {
            var btn = e.target.closest("[data-selfie-view]");
            if (!btn) {
                return;
            }
            var recordId = btn.getAttribute("data-selfie-view");
            if (!recordId) {
                return;
            }
            btn.disabled = true;
            btn.textContent = "Memuat…";
            var url = "/v1/hcm/attendance/admin/records/" + encodeURIComponent(recordId) + "/selfie/download";
            apiBlobGet(url)
                .then(function (objectUrl) {
                    btn.disabled = false;
                    btn.textContent = "Lihat";
                    if (!objectUrl) {
                        return;
                    }
                    var opened = window.open(objectUrl, "_blank", "noopener");
                    if (!opened) {
                        // Fallback when popup is blocked by browser.
                        var a = document.createElement("a");
                        a.href = objectUrl;
                        a.target = "_blank";
                        a.rel = "noopener";
                        document.body.appendChild(a);
                        a.click();
                        document.body.removeChild(a);
                    }
                    window.setTimeout(function () {
                        URL.revokeObjectURL(objectUrl);
                    }, 60000);
                })
                .catch(function () {
                    btn.disabled = false;
                    btn.textContent = "Lihat";
                    alert("Gagal memuat foto selfie. Pastikan Anda memiliki akses admin.");
                });
        });
    }

    function setupAdminFilters(loadAdminAttendance) {
        var depSel = document.querySelector("[data-attendance-admin-filter-department]");
        var statusSel = document.querySelector("[data-attendance-admin-filter-status]");
        var sortSel = document.querySelector("[data-attendance-admin-sort]");

        function onChange() {
            setAdminAttendancePage(1);
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

    function exportAdminCsv() {
        var rows = filterAndSortAdminRows(getAdminRowsCache() || []);
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
            "page=" + encodeURIComponent(String(getAdminAttendancePage())),
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
                if (pag.totalPages != null && getAdminAttendancePage() > pag.totalPages && pag.totalPages > 0) {
                    setAdminAttendancePage(pag.totalPages);
                    loadAdminAttendance();
                    return;
                }
                renderAdminSummary(meta);
                setAdminRowsCache(Array.isArray(payload.data) ? payload.data : []);
                if (Array.isArray(meta.departments) && meta.departments.length) {
                    fillAdminDepartmentFilter(meta.departments);
                } else {
                    fillAdminDepartmentFilter(getAdminRowsCache());
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

    return {
        getSelectedAdminDate: getSelectedAdminDate,
        setupAdminDateFilter: setupAdminDateFilter,
        renderAdminPagination: renderAdminPagination,
        setupAdminPaginationControls: setupAdminPaginationControls,
        renderAdminSummary: renderAdminSummary,
        renderAdminRows: renderAdminRows,
        rerenderAdminRowsFromCache: rerenderAdminRowsFromCache,
        bindSelfieViewDelegation: bindSelfieViewDelegation,
        setupAdminFilters: setupAdminFilters,
        exportAdminCsv: exportAdminCsv,
        loadAdminAttendance: loadAdminAttendance,
    };
}