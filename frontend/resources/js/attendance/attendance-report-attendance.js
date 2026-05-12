export function createReportAttendanceModule(deps) {
    var todayIsoLocal = deps.todayIsoLocal;
    var getReportSourceMode = deps.getReportSourceMode;
    var getSelectedSnapshotId = deps.getSelectedSnapshotId;
    var setReportSourceBadge = deps.setReportSourceBadge;
    var normalizeArchiveAttendanceRows = deps.normalizeArchiveAttendanceRows;
    var normalizeArchiveAttendanceSummary = deps.normalizeArchiveAttendanceSummary;
    var formatIsoDate = deps.formatIsoDate;
    var esc = deps.esc;
    var parseProductionHours = deps.parseProductionHours;
    var apiGet = deps.apiGet;
    var renderReportMessage = deps.renderReportMessage;
    var formatApiError = deps.formatApiError;
    var getReportFilters = deps.getReportFilters;
    var fillReportDepartmentFilter = deps.fillReportDepartmentFilter;
    var filterAndSortReportRows = deps.filterAndSortReportRows;

    var getReportRowsCache = deps.getReportRowsCache;
    var setReportRowsCache = deps.setReportRowsCache;
    var getReportPage = deps.getReportPage;
    var setReportPage = deps.setReportPage;
    var getReportPerPage = deps.getReportPerPage;
    var setReportPerPage = deps.setReportPerPage;
    var getReportTotalPages = deps.getReportTotalPages;
    var setReportTotalPages = deps.setReportTotalPages;
    var getReportLoading = deps.getReportLoading;
    var setReportLoading = deps.setReportLoading;
    var getReportActiveDate = deps.getReportActiveDate;
    var setReportActiveDate = deps.setReportActiveDate;
    var getReportSourceModeState = deps.getReportSourceModeState;
    var setReportSourceModeState = deps.setReportSourceModeState;
    var getReportChart = deps.getReportChart;
    var setReportChart = deps.setReportChart;

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

    function ensureReportPaginationControls(loadReportAttendance) {
        var table = document.querySelector("#attendance-report-table");
        if (!table) {
            return null;
        }
        var wrapper = table.closest(".custom-datatable-filter") || table.parentElement;
        if (!wrapper || !wrapper.parentElement) {
            return null;
        }

        var root = wrapper.parentElement.querySelector("[data-attendance-report-pagination]");
        if (!root) {
            root = document.createElement("div");
            root.setAttribute("data-attendance-report-pagination", "1");
            root.className = "d-flex justify-content-between align-items-center flex-wrap gap-2 px-3 py-3 border-top";
            root.innerHTML =
                '<div class="text-muted small" data-attendance-report-page-info>-</div>' +
                '<div class="d-flex align-items-center gap-2">' +
                '  <button type="button" class="btn btn-sm btn-outline-secondary" data-attendance-report-prev>Prev</button>' +
                '  <button type="button" class="btn btn-sm btn-outline-secondary" data-attendance-report-next>Next</button>' +
                "</div>";
            wrapper.parentElement.appendChild(root);
        }

        var prev = root.querySelector("[data-attendance-report-prev]");
        var next = root.querySelector("[data-attendance-report-next]");

        if (prev && !prev.getAttribute("data-bound")) {
            prev.setAttribute("data-bound", "1");
            prev.addEventListener("click", function () {
                if (getReportLoading() || getReportPage() <= 1) {
                    return;
                }
                setReportPage(getReportPage() - 1);
                loadReportAttendance();
            });
        }

        if (next && !next.getAttribute("data-bound")) {
            next.setAttribute("data-bound", "1");
            next.addEventListener("click", function () {
                if (getReportLoading()) {
                    return;
                }
                setReportPage(getReportPage() + 1);
                loadReportAttendance();
            });
        }

        return root;
    }

    function setReportPaginationLoading(isLoading) {
        var root = document.querySelector("[data-attendance-report-pagination]");
        if (!root) {
            return;
        }
        var prev = root.querySelector("[data-attendance-report-prev]");
        var next = root.querySelector("[data-attendance-report-next]");
        if (prev) {
            prev.disabled = isLoading || getReportPage() <= 1;
        }
        if (next) {
            next.disabled = isLoading || getReportPage() >= getReportTotalPages();
        }
    }

    function renderReportPagination(pagination, loadReportAttendance) {
        var root = ensureReportPaginationControls(loadReportAttendance);
        if (!root) {
            return;
        }
        var info = root.querySelector("[data-attendance-report-page-info]");
        var prev = root.querySelector("[data-attendance-report-prev]");
        var next = root.querySelector("[data-attendance-report-next]");

        if (!pagination || pagination.total == null) {
            root.style.display = "none";
            return;
        }

        var total = parseInt(pagination.total, 10) || 0;
        var page = parseInt(pagination.page, 10) || 1;
        var perPage = parseInt(pagination.perPage, 10) || getReportPerPage();
        var totalPages = parseInt(pagination.totalPages, 10) || 1;

        setReportPage(page);
        setReportPerPage(perPage);
        setReportTotalPages(totalPages);

        if (totalPages <= 1) {
            root.style.display = "none";
            return;
        }

        root.style.display = "";
        if (info) {
            var from = total === 0 ? 0 : (page - 1) * perPage + 1;
            var to = Math.min(page * perPage, total);
            info.textContent = "Page " + page + " of " + totalPages + " | Menampilkan " + from + "-" + to + " dari " + total;
        }
        if (prev) {
            prev.disabled = getReportLoading() || page <= 1;
        }
        if (next) {
            next.disabled = getReportLoading() || page >= totalPages;
        }
    }

    function setupReportSourceMode(loadReportAttendance) {
        var sourceSel = document.querySelector("[data-attendance-report-source]");
        var wrap = document.querySelector("[data-attendance-report-snapshot-wrap]");
        var loadBtn = document.querySelector("[data-attendance-report-load]");
        if (!sourceSel) {
            return;
        }

        function syncUi() {
            var mode = getReportSourceMode();
            setReportSourceModeState(mode);
            if (wrap) {
                wrap.classList.toggle("d-none", mode !== "archive");
            }
            setReportSourceBadge(mode, getSelectedSnapshotId());
            renderReportPagination(null, loadReportAttendance);
        }

        sourceSel.addEventListener("change", function () {
            setReportPage(1);
            syncUi();
            loadReportAttendance();
        });

        if (loadBtn) {
            loadBtn.addEventListener("click", function () {
                setReportPage(1);
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

    function setupReportDateFilter(loadReportAttendance) {
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
                setReportPage(1);
                loadReportAttendance();
            }
        });
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

        tbody.innerHTML = rows
            .map(function (row) {
                var prodClass = row.productionBadgeClass === "success" ? "success" : "danger";
                var ot = row.overtime != null && row.overtime !== undefined ? row.overtime : "-";
                var checkInLoc = row.checkInLocation || row.checkInLocationName || "-";
                var checkOutLoc = row.checkOutLocation || row.checkOutLocationName || "-";
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
                    '<td><span class="fs-12">' +
                    esc(checkInLoc) +
                    "</span></td>" +
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

    function rerenderReportRowsFromCache() {
        var date = getSelectedReportDate();
        if (!getReportRowsCache() || !getReportRowsCache().length) {
            renderReportRows([], date);
            renderReportChart([], date);
            return;
        }
        var filtered = filterAndSortReportRows(getReportRowsCache());
        renderReportRows(filtered, date);
        renderReportChart(filtered, date);
    }

    function setupReportFilters(loadReportAttendance) {
        var depSel = document.querySelector("[data-attendance-report-filter-department]");
        var statusSel = document.querySelector("[data-attendance-report-filter-status]");
        var sortSel = document.querySelector("[data-attendance-report-sort]");

        function onChange() {
            setReportPage(1);
            if (getReportSourceMode() === "live") {
                loadReportAttendance();
                return;
            }
            rerenderReportRowsFromCache();
        }

        if (depSel) depSel.addEventListener("change", onChange);
        if (statusSel) statusSel.addEventListener("change", onChange);
        if (sortSel) sortSel.addEventListener("change", onChange);
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

        setFoot("[data-attendance-report-stat-foot-working]", "Data absensi per " + label);
        setFoot("[data-attendance-report-stat-foot-leave]", "Karyawan yang belum check in pada tanggal ini");
        setFoot("[data-attendance-report-stat-foot-holiday]", "Perhitungan keterlambatan dibanding jam masuk");
        setFoot(
            "[data-attendance-report-stat-foot-halfday]",
            "Izin: " + perm + " | Tanpa keterangan: " + uninformed + " (belum ditampilkan per baris)"
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
        var labelDate = formatIsoDate(dateYmd || getReportActiveDate() || getSelectedReportDate());

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

        if (getReportChart()) {
            getReportChart().destroy();
        }
        var chart = new window.ApexCharts(el, options);
        setReportChart(chart);
        chart.render();
    }

    function loadReportAttendance() {
        var path = window.location.pathname || "";
        if (path.indexOf("/attendance-report") !== 0) {
            return;
        }

        ensureReportPaginationControls(loadReportAttendance);
        setReportLoading(true);
        setReportPaginationLoading(true);

        var tbody = document.querySelector("[data-attendance-report-body]");
        if (tbody) {
            tbody.innerHTML =
                '<tr><td class="text-center text-muted py-4">Loading...</td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td></tr>';
            tbody.removeAttribute("data-hydrated");
        }

        var dateParam = getSelectedReportDate();
        var mode = getReportSourceMode();
        var snapshotId = getSelectedSnapshotId();
        setReportSourceBadge(mode, snapshotId);

        if (mode === "archive") {
            if (!snapshotId) {
                renderReportMessage("Nomor arsip wajib diisi untuk mode Data Arsip.");
                setReportLoading(false);
                setReportPaginationLoading(false);
                return;
            }

            apiGet("/v1/hcm/reports/snapshots/" + encodeURIComponent(String(snapshotId)))
                .then(function (payload) {
                    if (!payload || payload.success !== true || !payload.data) {
                        setReportRowsCache([]);
                        fillReportDepartmentFilter(getReportRowsCache());
                        applyReportSummary({}, dateParam);
                        renderReportChart([], dateParam);
                        renderReportPagination(null, loadReportAttendance);
                        renderReportMessage("Data arsip tidak ditemukan atau tidak bisa diakses.");
                        return;
                    }
                    var snapshot = payload.data;
                    if (String(snapshot.reportType || "").toLowerCase() !== "attendance") {
                        setReportRowsCache([]);
                        fillReportDepartmentFilter(getReportRowsCache());
                        applyReportSummary({}, dateParam);
                        renderReportChart([], dateParam);
                        renderReportPagination(null, loadReportAttendance);
                        renderReportMessage("Nomor arsip ini bukan untuk laporan absensi.");
                        return;
                    }
                    if (String(snapshot.status || "").toLowerCase() !== "completed") {
                        setReportRowsCache([]);
                        fillReportDepartmentFilter(getReportRowsCache());
                        applyReportSummary({}, dateParam);
                        renderReportChart([], dateParam);
                        renderReportPagination(null, loadReportAttendance);
                        renderReportMessage("Data arsip absensi belum siap digunakan.");
                        return;
                    }
                    var effectiveDate = snapshot.periodEnd || dateParam;
                    setReportActiveDate(effectiveDate);
                    var rows = normalizeArchiveAttendanceRows(snapshot, effectiveDate);
                    setReportRowsCache(rows);
                    fillReportDepartmentFilter(getReportRowsCache());
                    applyReportSummary(normalizeArchiveAttendanceSummary(snapshot), effectiveDate);
                    var filteredArchive = filterAndSortReportRows(getReportRowsCache());
                    renderReportRows(filteredArchive, effectiveDate);
                    renderReportChart(filteredArchive, effectiveDate);
                    renderReportPagination(null, loadReportAttendance);
                })
                .catch(function (err) {
                    var statusA = err && err.response ? err.response.status : err && err.status ? err.status : 0;
                    var dataA = err && err.response ? err.response.data : err && err.data ? err.data : null;
                    if (window.AuthApi && window.AuthApi.handleUnauthorizedFromApi(statusA, dataA)) {
                        return;
                    }
                    renderReportPagination(null, loadReportAttendance);
                    renderReportMessage(formatApiError(dataA, statusA) || "Gagal memuat data arsip absensi.");
                })
                .finally(function () {
                    setReportLoading(false);
                    setReportPaginationLoading(false);
                });
            return;
        }

        var filters = getReportFilters();
        var query = [
            "date=" + encodeURIComponent(dateParam),
            "page=" + encodeURIComponent(String(getReportPage())),
            "perPage=" + encodeURIComponent(String(getReportPerPage())),
            "sort=" + encodeURIComponent(filters.sort || "name_asc"),
        ];
        if (filters.department) {
            query.push("department=" + encodeURIComponent(filters.department));
        }
        if (filters.status) {
            query.push("status=" + encodeURIComponent(filters.status));
        }

        var url = "/v1/hcm/attendance/admin?" + query.join("&");
        apiGet(url)
            .then(function (payload) {
                if (!payload) {
                    renderReportMessage("Session not found. Redirecting to login...");
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
                var pag = meta.pagination || null;
                if (pag && pag.totalPages != null && getReportPage() > pag.totalPages && pag.totalPages > 0) {
                    setReportPage(pag.totalPages);
                    loadReportAttendance();
                    return;
                }
                setReportActiveDate(meta.date || dateParam);
                applyReportSummary(meta.summary || {}, meta.date || dateParam);
                setReportRowsCache(Array.isArray(payload.data) ? payload.data : []);

                fillReportDepartmentFilter(getReportRowsCache(), meta.departments || []);
                renderReportRows(getReportRowsCache(), meta.date || dateParam);
                renderReportChart(getReportRowsCache(), meta.date || dateParam);
                renderReportPagination(pag, loadReportAttendance);
            })
            .catch(function (err) {
                var status = err && err.response ? err.response.status : err && err.status ? err.status : 0;
                var data = err && err.response ? err.response.data : err && err.data ? err.data : null;
                if (window.AuthApi && window.AuthApi.handleUnauthorizedFromApi(status, data)) {
                    return;
                }
                renderReportMessage(formatApiError(data, status) || "Failed loading report. Please try again.");
            })
            .finally(function () {
                setReportLoading(false);
                setReportPaginationLoading(false);
            });
    }

    return {
        ensureReportPaginationControls: ensureReportPaginationControls,
        setReportPaginationLoading: setReportPaginationLoading,
        renderReportPagination: renderReportPagination,
        setupReportSourceMode: setupReportSourceMode,
        setupReportDateFilter: setupReportDateFilter,
        renderReportRows: renderReportRows,
        rerenderReportRowsFromCache: rerenderReportRowsFromCache,
        setupReportFilters: setupReportFilters,
        applyReportSummary: applyReportSummary,
        renderReportChart: renderReportChart,
        loadReportAttendance: loadReportAttendance,
    };
}
