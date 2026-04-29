import { apiDelete, apiGet, apiPost, apiPut } from "./attendance-api";
import {
    fillAdminDepartmentFilter,
    fillReportDepartmentFilter,
    filterAndSortAdminRows,
    filterAndSortReportRows,
    getAdminFilters,
    getReportFilters,
    getTimesheetDateRange,
    parseProductionHours,
    parseTimeToMinutes,
} from "./attendance-filter";
import { bindGpsDebugButton } from "./attendance-form";
import { createAttendanceTimesheetModule } from "./attendance-timesheet";
import {
    getReportSourceMode,
    getSelectedReportDate,
    getSelectedSnapshotId,
    normalizeArchiveAttendanceRows,
    normalizeArchiveAttendanceSummary,
    setReportSourceBadge,
} from "./attendance-report";
import { createReportAttendanceModule } from "./attendance-report-attendance";
import {
    bindBreakToggle as bindBreakToggleAction,
    bindPunch as bindPunchAction,
    setupAttendanceAdminEdit as setupAttendanceAdminEditAction,
} from "./attendance-actions";
import {
    loadScheduleTiming as loadScheduleTimingModule,
    renderScheduleTimingPagination as renderScheduleTimingPaginationModule,
    setupScheduleTimingFilters as setupScheduleTimingFiltersModule,
    setupScheduleTimingPaginationControls as setupScheduleTimingPaginationControlsModule,
    setupScheduleViewMode as setupScheduleViewModeModule,
} from "./attendance-schedule";
import { renderScheduleTimingRowsModule } from "./attendance-schedule-timing-rows";
import { createScheduleCalendarModule } from "./attendance-schedule-calendar";
import { createPunchMapModule } from "./attendance-punch-map";
import { createScheduleTimingEditModule } from "./attendance-schedule-edit";
import { createPlannerHelpers } from "./attendance-planner-helpers";
import {
    renderSmartPlannerAssignmentPreview as renderSmartPlannerAssignmentPreviewModule,
    renderSmartPlannerResult as renderSmartPlannerResultModule,
} from "./attendance-smartplanner";
import { initSelfieCapture as initSelfieCaptureModule } from "./attendance-selfie";
import { bindAttendanceExtras as bindAttendanceExtrasModule } from "./attendance-extras";
import { bindPlannerSettingsPanel } from "./attendance-planner-settings";
import { bindPlannerApplyButtons } from "./attendance-planner-apply";
import { bindPlannerSubmitHandler } from "./attendance-planner-submit";
import { bindSmartPlannerModule } from "./attendance-planner-bind";
import { createPlannerAnalysisModule } from "./attendance-planner-analysis";
import { createAdminAttendanceModule } from "./attendance-admin-attendance";
import { createEmployeeAttendanceModule } from "./attendance-employee-attendance";
import {
    renderAdminMessage,
    renderMeHistoryMessage,
    renderReportMessage,
    renderScheduleTimingMessage as renderScheduleTimingMessageBase,
    renderTimesheetsMessage,
} from "./attendance-table";
import {
    downloadCsv,
    esc,
    formatApiError,
    formatIsoDate,
    formatMmSs,
    minutesToTimeStr,
    notify,
    parseHiToMinutes,
    timeInputToHi,
    todayIsoLocal,
} from "./attendance-utils";

(function (window, document) {
    "use strict";

    var adminRowsCache = [];
    var adminAttendancePage = 1;
    var scheduleTimingPage = 1;
    var meHistoryCache = [];
    var reportRowsCache = [];
    var scheduleTimingRowsCache = [];
    var scheduleTimingPaginationCache = null;
    var scheduleShiftsCache = [];
    var smartPlannerLastResult = null;
    var smartPlannerLastPayload = null;
    var smartPlannerSettingsCache = null;
    var smartPlannerTransitionCatalog = ["morning:afternoon", "morning:night", "afternoon:morning", "afternoon:night", "night:morning", "night:afternoon"];
    var smartPlannerForbiddenTransitionKeys = ["night:morning"];
    var smartPlannerAssignmentByUserId = {};
    var smartPlannerConflictSummary = { total: 0, critical: 0 };
    var smartPlannerEditMode = false;
    var smartPlannerEditModeOriginalValues = {};
    var scheduleTimingAiOnly = false;
    var scheduleTimingView = "list";
    var scheduleHolidayRowsCache = [];
    var scheduleCalendar = null;
    var smartPlannerScopeMeta = "";
    var correctionModalState = { open: false };
    var reportChart = null;
    var reportActiveDate = "";
    var reportSourceMode = "live";
    var reportPage = 1;
    var reportPerPage = 100;
    var reportTotalPages = 1;
    var reportLoading = false;
    var breakTicker = null;
    var meRefreshTimer = null;
    var punchMapElId = "arcav-attendance-punch-map";
    var punchMap = null;
    var punchMarker = null;
    var manualPunchCoords = null;
    var timesheetModule = createAttendanceTimesheetModule({
        apiGet: apiGet,
        formatApiError: formatApiError,
        getTimesheetDateRange: getTimesheetDateRange,
    });

    var punchMapModule = createPunchMapModule({
        punchMapElId: punchMapElId,
        getPunchMap: function () {
            return punchMap;
        },
        setPunchMap: function (value) {
            punchMap = value;
        },
        getPunchMarker: function () {
            return punchMarker;
        },
        setPunchMarker: function (value) {
            punchMarker = value;
        },
        getManualPunchCoords: function () {
            return manualPunchCoords;
        },
        setManualPunchCoords: function (value) {
            manualPunchCoords = value;
        },
    });

    var scheduleTimingEditModule = createScheduleTimingEditModule({
        esc: esc,
        minutesToTimeStr: minutesToTimeStr,
        parseHiToMinutes: parseHiToMinutes,
        apiGet: apiGet,
        apiDelete: apiDelete,
        apiPut: apiPut,
        notify: notify,
        formatApiError: formatApiError,
        timeInputToHi: timeInputToHi,
        loadScheduleTiming: function () {
            loadScheduleTiming();
        },
        getScheduleShiftsCache: function () {
            return scheduleShiftsCache;
        },
        setScheduleShiftsCache: function (value) {
            scheduleShiftsCache = value;
        },
    });

    function destroyPunchMap() {
        punchMapModule.destroyPunchMap();
    }

    function showPunchMapAt(lat, lng) {
        punchMapModule.showPunchMapAt(lat, lng);
    }

    function ensureInteractivePunchMap() {
        punchMapModule.ensureInteractivePunchMap();
    }

    function syncPunchMapFromMe(d) {
        punchMapModule.syncPunchMapFromMe(d);
    }

    function getCurrentPositionForPunch() {
        return punchMapModule.getCurrentPositionForPunch();
    }

    function geolocationErrorMessage(err) {
        return punchMapModule.geolocationErrorMessage(err);
    }

    function runGpsDebugCheck() {
        punchMapModule.runGpsDebugCheck();
    }


    function fillScheduleShiftSelect(selectEl) {
        scheduleTimingEditModule.fillScheduleShiftSelect(selectEl);
    }

    function syncTimesFromShiftSelect(selectEl, startInp, endInp) {
        scheduleTimingEditModule.syncTimesFromShiftSelect(selectEl, startInp, endInp);
    }

    function ensureScheduleShiftsLoaded(callback) {
        scheduleTimingEditModule.ensureScheduleShiftsLoaded(callback);
    }

    function setupScheduleTimingEditModal() {
        scheduleTimingEditModule.setupScheduleTimingEditModal();
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



    var adminAttendanceModule = createAdminAttendanceModule({
        todayIsoLocal: todayIsoLocal,
        formatIsoDate: formatIsoDate,
        esc: esc,
        filterAndSortAdminRows: filterAndSortAdminRows,
        downloadCsv: downloadCsv,
        getAdminFilters: getAdminFilters,
        fillAdminDepartmentFilter: fillAdminDepartmentFilter,
        apiGet: apiGet,
        renderAdminMessage: renderAdminMessage,
        formatApiError: formatApiError,
        getAdminAttendancePage: function () {
            return adminAttendancePage;
        },
        setAdminAttendancePage: function (value) {
            adminAttendancePage = value;
        },
        getAdminRowsCache: function () {
            return adminRowsCache;
        },
        setAdminRowsCache: function (rows) {
            adminRowsCache = rows;
        },
    });

    function getSelectedAdminDate() {
        return adminAttendanceModule.getSelectedAdminDate();
    }

    function setupAdminDateFilter() {
        adminAttendanceModule.setupAdminDateFilter(loadAdminAttendance);
    }

    function renderAdminPagination(pagination) {
        adminAttendanceModule.renderAdminPagination(pagination);
    }

    function setupAdminPaginationControls() {
        adminAttendanceModule.setupAdminPaginationControls(loadAdminAttendance);
    }

    function renderAdminSummary(meta) {
        adminAttendanceModule.renderAdminSummary(meta);
    }

    function renderAdminRows(rows) {
        adminAttendanceModule.renderAdminRows(rows);
    }

    function rerenderAdminRowsFromCache() {
        adminAttendanceModule.rerenderAdminRowsFromCache();
    }

    function setupAdminFilters() {
        adminAttendanceModule.setupAdminFilters(loadAdminAttendance);
    }

    function exportAdminCsv() {
        adminAttendanceModule.exportAdminCsv();
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
        adminAttendanceModule.loadAdminAttendance();
    }

    var employeeAttendanceModule = createEmployeeAttendanceModule({
        esc: esc,
        apiGet: apiGet,
        renderMeHistoryMessage: renderMeHistoryMessage,
        syncAttendanceCircle: syncAttendanceCircle,
        syncPunchMapFromMe: syncPunchMapFromMe,
        startBreakTicker: startBreakTicker,
        stopBreakTicker: stopBreakTicker,
        ensureInteractivePunchMap: ensureInteractivePunchMap,
        getMeHistoryCache: function () {
            return meHistoryCache;
        },
        setMeHistoryCache: function (value) {
            meHistoryCache = value;
        },
        getMeRefreshTimer: function () {
            return meRefreshTimer;
        },
        setMeRefreshTimer: function (value) {
            meRefreshTimer = value;
        },
    });

    function applyMeToday(d) {
        employeeAttendanceModule.applyMeToday(d);
    }

    function applyMeStats(s) {
        employeeAttendanceModule.applyMeStats(s);
    }

    function renderMeHistory(rows) {
        employeeAttendanceModule.renderMeHistory(rows);
    }

    function clearMeRefreshTimer() {
        employeeAttendanceModule.clearMeRefreshTimer();
    }

    function scheduleMeRefresh(isPunchInProgress) {
        employeeAttendanceModule.scheduleMeRefresh(isPunchInProgress, loadEmployeeAttendance);
    }

    function loadEmployeeAttendance() {
        employeeAttendanceModule.loadEmployeeAttendance();
    }
    var reportAttendanceModule = createReportAttendanceModule({
        todayIsoLocal: todayIsoLocal,
        getReportSourceMode: getReportSourceMode,
        getSelectedSnapshotId: getSelectedSnapshotId,
        setReportSourceBadge: setReportSourceBadge,
        normalizeArchiveAttendanceRows: normalizeArchiveAttendanceRows,
        normalizeArchiveAttendanceSummary: normalizeArchiveAttendanceSummary,
        formatIsoDate: formatIsoDate,
        esc: esc,
        parseProductionHours: parseProductionHours,
        apiGet: apiGet,
        renderReportMessage: renderReportMessage,
        formatApiError: formatApiError,
        getReportFilters: getReportFilters,
        fillReportDepartmentFilter: fillReportDepartmentFilter,
        filterAndSortReportRows: filterAndSortReportRows,
        getReportRowsCache: function () {
            return reportRowsCache;
        },
        setReportRowsCache: function (value) {
            reportRowsCache = value;
        },
        getReportPage: function () {
            return reportPage;
        },
        setReportPage: function (value) {
            reportPage = value;
        },
        getReportPerPage: function () {
            return reportPerPage;
        },
        setReportPerPage: function (value) {
            reportPerPage = value;
        },
        getReportTotalPages: function () {
            return reportTotalPages;
        },
        setReportTotalPages: function (value) {
            reportTotalPages = value;
        },
        getReportLoading: function () {
            return reportLoading;
        },
        setReportLoading: function (value) {
            reportLoading = value;
        },
        getReportActiveDate: function () {
            return reportActiveDate;
        },
        setReportActiveDate: function (value) {
            reportActiveDate = value;
        },
        getReportSourceModeState: function () {
            return reportSourceMode;
        },
        setReportSourceModeState: function (value) {
            reportSourceMode = value;
        },
        getReportChart: function () {
            return reportChart;
        },
        setReportChart: function (value) {
            reportChart = value;
        },
    });

    function ensureReportPaginationControls() {
        return reportAttendanceModule.ensureReportPaginationControls(loadReportAttendance);
    }

    function setReportPaginationLoading(isLoading) {
        reportAttendanceModule.setReportPaginationLoading(isLoading);
    }

    function renderReportPagination(pagination) {
        reportAttendanceModule.renderReportPagination(pagination, loadReportAttendance);
    }

    function setupReportSourceMode() {
        reportAttendanceModule.setupReportSourceMode(loadReportAttendance);
    }

    function setupReportDateFilter() {
        reportAttendanceModule.setupReportDateFilter(loadReportAttendance);
    }

    function renderReportRows(rows, dateYmd) {
        reportAttendanceModule.renderReportRows(rows, dateYmd);
    }

    function rerenderReportRowsFromCache() {
        reportAttendanceModule.rerenderReportRowsFromCache();
    }

    function setupReportFilters() {
        reportAttendanceModule.setupReportFilters(loadReportAttendance);
    }

    function applyReportSummary(summary, dateYmd) {
        reportAttendanceModule.applyReportSummary(summary, dateYmd);
    }

    function renderReportChart(rows, dateYmd) {
        reportAttendanceModule.renderReportChart(rows, dateYmd);
    }

    function loadReportAttendance() {
        reportAttendanceModule.loadReportAttendance();
    }

    function setupAttendanceAdminEdit() {
        setupAttendanceAdminEditAction({
            getSelectedAdminDate: getSelectedAdminDate,
            notify: notify,
            apiPut: apiPut,
            formatApiError: formatApiError,
            loadAdminAttendance: loadAdminAttendance,
        });
    }

    function bindPunch() {
        bindPunchAction({
            punchMapElId: punchMapElId,
            notify: notify,
            getCurrentPositionForPunch: getCurrentPositionForPunch,
            showPunchMapAt: showPunchMapAt,
            apiPost: apiPost,
            formatApiError: formatApiError,
            loadEmployeeAttendance: loadEmployeeAttendance,
            geolocationErrorMessage: geolocationErrorMessage,
            getManualPunchCoords: function () {
                return manualPunchCoords;
            },
        });
    }

    function bindBreakToggle() {
        bindBreakToggleAction({
            apiPost: apiPost,
            notify: notify,
            formatApiError: formatApiError,
            loadEmployeeAttendance: loadEmployeeAttendance,
        });
    }

    function bindGpsDebug() {
        bindGpsDebugButton(runGpsDebugCheck);
    }

    function initSelfieCapture() {
        initSelfieCaptureModule({
            notify: notify,
            apiPost: apiPost,
            formatApiError: formatApiError,
            loadEmployeeAttendance: loadEmployeeAttendance,
        });
    }

    function bindAttendanceExtras() {
        bindAttendanceExtrasModule({
            exportAdminCsv: exportAdminCsv,
            exportMeCsv: exportMeCsv,
            filterAndSortReportRows: filterAndSortReportRows,
            getReportRowsCache: function () {
                return reportRowsCache;
            },
            formatIsoDate: formatIsoDate,
            getSelectedReportDate: getSelectedReportDate,
            downloadCsv: downloadCsv,
            correctionModalState: correctionModalState,
            getTimesheetRowsCache: function () {
                return timesheetModule.getRowsCache();
            },
            getScheduleTimingRowsCache: function () {
                return scheduleTimingRowsCache;
            },
            ensureScheduleShiftsLoaded: ensureScheduleShiftsLoaded,
            fillScheduleShiftSelect: fillScheduleShiftSelect,
            syncTimesFromShiftSelect: syncTimesFromShiftSelect,
            minutesToTimeStr: minutesToTimeStr,
            notify: notify,
            apiPost: apiPost,
            formatApiError: formatApiError,
            loadEmployeeAttendance: loadEmployeeAttendance,
        });
    }

    var scheduleCalendarModule = createScheduleCalendarModule({
        getScheduleTimingView: function () {
            return scheduleTimingView;
        },
        getScheduleCalendar: function () {
            return scheduleCalendar;
        },
        setScheduleCalendar: function (value) {
            scheduleCalendar = value;
        },
        getScheduleHolidayRowsCache: function () {
            return scheduleHolidayRowsCache;
        },
        setScheduleHolidayRowsCache: function (value) {
            scheduleHolidayRowsCache = Array.isArray(value) ? value : [];
        },
        getSmartPlannerAssignmentByUserId: function () {
            return smartPlannerAssignmentByUserId;
        },
        getSmartPlannerScopeMeta: function () {
            return smartPlannerScopeMeta;
        },
        plannerShiftMeta: function (assignment) {
            return plannerHelpers.plannerShiftMeta(assignment);
        },
        apiGet: apiGet,
    });

    function renderScheduleCalendar() {
        scheduleCalendarModule.renderScheduleCalendar();
    }

    function loadScheduleCalendarHolidays() {
        scheduleCalendarModule.loadScheduleCalendarHolidays();
    }

    function setupScheduleViewMode() {
        setupScheduleViewModeModule({
            getScheduleTimingView: function () {
                return scheduleTimingView;
            },
            setScheduleTimingView: function (value) {
                scheduleTimingView = value;
            },
            getScheduleTimingPaginationCache: function () {
                return scheduleTimingPaginationCache;
            },
            renderScheduleTimingPagination: renderScheduleTimingPagination,
            renderScheduleCalendar: renderScheduleCalendar,
        });
    }

    function renderScheduleTimingRows(rows) {
        renderScheduleTimingRowsModule(
            {
                esc: esc,
                getScheduleTimingAiOnly: function () {
                    return scheduleTimingAiOnly;
                },
                getSmartPlannerAssignmentByUserId: function () {
                    return smartPlannerAssignmentByUserId;
                },
                renderScheduleCalendar: renderScheduleCalendar,
            },
            rows
        );
    }

    function renderScheduleTimingMessage(msg) {
        renderScheduleTimingMessageBase(msg);
        renderScheduleCalendar();
    }

    function renderScheduleTimingPagination(pagination) {
        renderScheduleTimingPaginationModule({
            setScheduleTimingPaginationCache: function (value) {
                scheduleTimingPaginationCache = value;
            },
            getScheduleTimingView: function () {
                return scheduleTimingView;
            },
        }, pagination);
    }

    function setupScheduleTimingPaginationControls() {
        setupScheduleTimingPaginationControlsModule({
            getScheduleTimingPage: function () {
                return scheduleTimingPage;
            },
            setScheduleTimingPage: function (value) {
                scheduleTimingPage = value;
            },
            loadScheduleTiming: loadScheduleTiming,
        });
    }

    function loadScheduleTiming() {
        loadScheduleTimingModule({
            getScheduleTimingPage: function () {
                return scheduleTimingPage;
            },
            setScheduleTimingPage: function (value) {
                scheduleTimingPage = value;
            },
            setScheduleTimingRowsCache: function (rows) {
                scheduleTimingRowsCache = rows;
            },
            apiGet: apiGet,
            formatApiError: formatApiError,
            renderScheduleTimingMessage: renderScheduleTimingMessage,
            renderScheduleTimingRows: renderScheduleTimingRows,
            renderScheduleTimingPagination: renderScheduleTimingPagination,
        });
    }

    function setupScheduleTimingFilters() {
        setupScheduleTimingFiltersModule({
            setScheduleTimingPage: function (value) {
                scheduleTimingPage = value;
            },
            loadScheduleTiming: loadScheduleTiming,
            setScheduleTimingAiOnly: function (value) {
                scheduleTimingAiOnly = value;
            },
            getScheduleTimingRowsCache: function () {
                return scheduleTimingRowsCache;
            },
            renderScheduleTimingRows: renderScheduleTimingRows,
        });
    }

    var plannerHelpers = createPlannerHelpers({
        getScheduleShiftsCache: function () {
            return scheduleShiftsCache;
        },
    });

    var plannerAnalysisModule = createPlannerAnalysisModule({
        esc: esc,
        apiPut: apiPut,
        apiPost: apiPost,
        formatApiError: formatApiError,
        setSmartPlannerFeedback: plannerHelpers.setSmartPlannerFeedback,
        findScheduleShiftById: plannerHelpers.findScheduleShiftById,
        plannerShiftMeta: plannerHelpers.plannerShiftMeta,
        getScheduleTimingRowsCache: function () {
            return scheduleTimingRowsCache;
        },
        getScheduleHolidayRowsCache: function () {
            return scheduleHolidayRowsCache;
        },
        getSmartPlannerTransitionCatalog: function () {
            return smartPlannerTransitionCatalog;
        },
        getSmartPlannerForbiddenTransitionKeys: function () {
            return smartPlannerForbiddenTransitionKeys;
        },
        getSmartPlannerConflictSummary: function () {
            return smartPlannerConflictSummary;
        },
        setSmartPlannerConflictSummary: function (value) {
            smartPlannerConflictSummary = value;
        },
    });

    function plannerLegacyRulesFromTransitionKeys(keys) {
        return plannerAnalysisModule.plannerLegacyRulesFromTransitionKeys(keys);
    }

    function renderPlannerTransitionMatrix(catalog, selectedKeys) {
        plannerAnalysisModule.renderPlannerTransitionMatrix(catalog, selectedKeys);
    }

    function readPlannerTransitionSelection() {
        return plannerAnalysisModule.readPlannerTransitionSelection();
    }

    function setPlannerSettingsFeedback(message, isError) {
        plannerAnalysisModule.setPlannerSettingsFeedback(message, isError);
    }

    function applyPlannerSettingsToForm(form, settings) {
        plannerAnalysisModule.applyPlannerSettingsToForm(form, settings);
    }

    function renderPlannerDiffPreview(result) {
        plannerAnalysisModule.renderPlannerDiffPreview(result);
    }

    function renderPlannerConflictPreview(result, payload) {
        return plannerAnalysisModule.renderPlannerConflictPreview(result, payload);
    }

    function updatePlannerApplyState(result) {
        plannerAnalysisModule.updatePlannerApplyState(result);
    }

    function applyPlannerDominantShifts(result) {
        return plannerAnalysisModule.applyPlannerDominantShifts(result);
    }

    function applyPlannerDailyRoster(result) {
        return plannerAnalysisModule.applyPlannerDailyRoster(result);
    }

    function combinePlannerResults(results) {
        return plannerAnalysisModule.combinePlannerResults(results);
    }

    function executePlannerBatchRequests(basePayload, weekStarts, onProgress) {
        return plannerAnalysisModule.executePlannerBatchRequests(basePayload, weekStarts, onProgress);
    }
    function renderSmartPlannerAssignmentPreview(result) {
        renderSmartPlannerAssignmentPreviewModule({
            buildPlannerAssignmentIndex: plannerHelpers.buildPlannerAssignmentIndex,
            setSmartPlannerAssignmentByUserId: function (value) {
                smartPlannerAssignmentByUserId = value;
            },
            getSmartPlannerAssignmentByUserId: function () {
                return smartPlannerAssignmentByUserId;
            },
            formatPlannerPattern: plannerHelpers.formatPlannerPattern,
            esc: esc,
        }, result);
    }

    function renderSmartPlannerResult(result) {
        renderSmartPlannerResultModule({
            renderSmartPlannerAssignmentPreview: renderSmartPlannerAssignmentPreview,
            renderPlannerDiffPreview: renderPlannerDiffPreview,
            renderPlannerConflictPreview: renderPlannerConflictPreview,
            getSmartPlannerLastPayload: function () {
                return smartPlannerLastPayload;
            },
            getScheduleTimingRowsCache: function () {
                return scheduleTimingRowsCache;
            },
            renderScheduleTimingRows: renderScheduleTimingRows,
            renderScheduleCalendar: renderScheduleCalendar,
            updatePlannerApplyState: updatePlannerApplyState,
        }, result);
    }

    function bindSmartPlanner() {
        bindSmartPlannerModule({
            ensureScheduleShiftsLoaded: ensureScheduleShiftsLoaded,
            getSmartPlannerLastResult: function () {
                return smartPlannerLastResult;
            },
            renderSmartPlannerResult: renderSmartPlannerResult,
            getCurrentWeekStartIso: plannerHelpers.getCurrentWeekStartIso,
            plannerEndOfYearIso: plannerHelpers.plannerEndOfYearIso,
            apiGet: apiGet,
            getSmartPlannerSettingsCache: function () {
                return smartPlannerSettingsCache;
            },
            setSmartPlannerSettingsCache: function (value) {
                smartPlannerSettingsCache = value;
            },
            getSmartPlannerTransitionCatalog: function () {
                return smartPlannerTransitionCatalog;
            },
            setSmartPlannerTransitionCatalog: function (value) {
                smartPlannerTransitionCatalog = value;
            },
            getSmartPlannerForbiddenTransitionKeys: function () {
                return smartPlannerForbiddenTransitionKeys;
            },
            setSmartPlannerForbiddenTransitionKeys: function (value) {
                smartPlannerForbiddenTransitionKeys = value;
            },
            applyPlannerSettingsToForm: applyPlannerSettingsToForm,
            renderPlannerTransitionMatrix: renderPlannerTransitionMatrix,
            setPlannerSettingsFeedback: setPlannerSettingsFeedback,
            esc: esc,
            bindPlannerSubmitHandler: bindPlannerSubmitHandler,
            readPlannerTransitionSelection: readPlannerTransitionSelection,
            plannerLegacyRulesFromTransitionKeys: plannerLegacyRulesFromTransitionKeys,
            plannerBuildWeekStarts: plannerHelpers.plannerBuildWeekStarts,
            executePlannerBatchRequests: executePlannerBatchRequests,
            combinePlannerResults: combinePlannerResults,
            apiPost: apiPost,
            setSmartPlannerFeedback: plannerHelpers.setSmartPlannerFeedback,
            formatApiError: formatApiError,
            updatePlannerApplyState: updatePlannerApplyState,
            notify: notify,
            getSmartPlannerLastPayload: function () {
                return smartPlannerLastPayload;
            },
            setSmartPlannerLastPayload: function (value) {
                smartPlannerLastPayload = value;
            },
            setSmartPlannerScopeMeta: function (value) {
                smartPlannerScopeMeta = value;
            },
            setSmartPlannerLastResult: function (value) {
                smartPlannerLastResult = value;
            },
            bindPlannerApplyButtons: bindPlannerApplyButtons,
            applyPlannerDominantShifts: applyPlannerDominantShifts,
            applyPlannerDailyRoster: applyPlannerDailyRoster,
            loadScheduleTiming: loadScheduleTiming,
            bindPlannerSettingsPanel: bindPlannerSettingsPanel,
            apiPut: apiPut,
            getSmartPlannerEditModeOriginalValues: function () {
                return smartPlannerEditModeOriginalValues;
            },
            setSmartPlannerEditModeOriginalValues: function (value) {
                smartPlannerEditModeOriginalValues = value;
            },
            setSmartPlannerEditMode: function (value) {
                smartPlannerEditMode = value;
            },
        });
    }

    function init() {
        setupAdminDateFilter();
        setupAdminFilters();
        setupAdminPaginationControls();
        setupReportDateFilter();
        setupReportSourceMode();
        setupReportFilters();
        timesheetModule.setupTimesheetFilters();
        timesheetModule.setupTimesheetPaginationControls();
        setupScheduleViewMode();
        setupScheduleTimingFilters();
        setupScheduleTimingPaginationControls();
        setupScheduleTimingEditModal();
        bindSmartPlanner();
        setupAttendanceAdminEdit();
        loadAdminAttendance();
        loadReportAttendance();
        loadEmployeeAttendance();
        timesheetModule.loadTimesheets();
        loadScheduleTiming();
        loadScheduleCalendarHolidays();
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
