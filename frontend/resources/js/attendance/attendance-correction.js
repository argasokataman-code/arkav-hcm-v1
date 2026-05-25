/**
 * Attendance Correction Queue — dedicated page JS
 * Page: /attendance-correction
 *
 * Loads all pending corrections, supports filter by name + date range,
 * inline approve/dismiss, and detail modal.
 */
import { apiGet, apiPost } from "./attendance-api.js";
import { formatApiError } from "./attendance-utils.js";

(function (window, document) {
    "use strict";

    // Only run on /attendance-correction
    if (String(window.location.pathname || "").indexOf("/attendance-correction") !== 0) {
        return;
    }

    // ─── State ───────────────────────────────────────────────────────────────
    var allRows = [];      // raw API rows (all pages loaded once)
    var filtered = [];     // after filter applied
    var page = 1;
    var perPage = 25;

    // Active detail row (for modal)
    var activeRecord = null;

    // ─── DOM refs ─────────────────────────────────────────────────────────────
    function el(attr) { return document.querySelector("[" + attr + "]"); }

    var tbody         = el("data-correction-tbody");
    var totalBadge    = el("data-correction-total-badge");
    var statTotal     = el("data-correction-stat-total");
    var statEmployees = el("data-correction-stat-employees");
    var subtitleEl    = el("data-correction-subtitle");
    var pageInfo      = el("data-correction-page-info");
    var pagination    = el("data-correction-pagination");
    var filterName    = el("data-correction-filter-name");
    var filterFrom    = el("data-correction-filter-date-from");
    var filterTo      = el("data-correction-filter-date-to");
    var resetBtn      = el("data-correction-filter-reset");
    var refreshBtn    = el("data-correction-refresh");
    var prevBtn       = el("data-correction-prev");
    var nextBtn       = el("data-correction-next");

    // Modal elements
    var modal            = document.getElementById("arcav_correction_detail_modal");
    var modalName        = el("data-correction-modal-name");
    var modalDate        = el("data-correction-modal-date");
    var modalCheckin     = el("data-correction-modal-checkin");
    var modalCheckout    = el("data-correction-modal-checkout");
    var modalBreak       = el("data-correction-modal-break");
    var modalLate        = el("data-correction-modal-late");
    var modalReason      = el("data-correction-modal-reason");
    var modalReqAt       = el("data-correction-modal-requested-at");
    var modalGotoAdmin   = el("data-correction-modal-goto-admin");
    var modalApproveBtn  = el("data-correction-modal-approve");
    var modalDismissBtn  = el("data-correction-modal-dismiss");

    // ─── Helpers ──────────────────────────────────────────────────────────────
    function esc(s) {
        return String(s || "")
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;");
    }

    function fmtBreak(mins) {
        var m = parseInt(mins, 10) || 0;
        if (m === 0) { return '<span class="text-muted">—</span>'; }
        return m + " mnt";
    }

    function fmtLate(mins) {
        var m = parseInt(mins, 10) || 0;
        if (m === 0) { return '<span class="text-success">Tepat</span>'; }
        return '<span class="text-danger">' + m + " mnt</span>";
    }

    function fmtTime(t) {
        return t ? esc(t) : '<span class="text-muted">—</span>';
    }

    function updateSidebarBadge(count) {
        ["sidebar-correction-badge", "sidebar-correction-badge-2col"].forEach(function (id) {
            var el2 = document.getElementById(id);
            if (!el2) { return; }
            if (count > 0) {
                el2.textContent = String(count > 99 ? "99+" : count);
                el2.classList.remove("d-none");
            } else {
                el2.classList.add("d-none");
            }
        });
    }

    // ─── Load ─────────────────────────────────────────────────────────────────
    function loadCorrections() {
        if (tbody) {
            tbody.innerHTML = '<tr data-correction-loading><td colspan="9" class="text-center text-muted py-4">' +
                '<div class="spinner-border spinner-border-sm me-2" role="status"></div>Memuat...</td></tr>';
        }

        apiGet("/v1/hcm/attendance/admin/corrections?perPage=500")
            .then(function (payload) {
                if (!payload || payload.success !== true) {
                    renderError("Gagal memuat data koreksi.");
                    return;
                }
                allRows = Array.isArray(payload.data) ? payload.data : [];
                applyFiltersAndRender();
                updateSidebarBadge(allRows.length);
            })
            .catch(function (err) {
                renderError("Gagal memuat: " + formatApiError(err));
            });
    }

    // ─── Filter ───────────────────────────────────────────────────────────────
    function applyFiltersAndRender() {
        var nameQ = (filterName ? filterName.value.trim().toLowerCase() : "");
        var dateFrom = filterFrom ? filterFrom.value : "";
        var dateTo   = filterTo ? filterTo.value : "";

        filtered = allRows.filter(function (r) {
            if (nameQ && String(r.employeeName || "").toLowerCase().indexOf(nameQ) === -1) { return false; }
            if (dateFrom && r.workDate < dateFrom) { return false; }
            if (dateTo   && r.workDate > dateTo)   { return false; }
            return true;
        });

        page = 1;
        renderStats();
        renderTable();
    }

    // ─── Stats ────────────────────────────────────────────────────────────────
    function renderStats() {
        var total = allRows.length;
        var uniqueEmps = {};
        allRows.forEach(function (r) { uniqueEmps[String(r.userId || "")] = true; });
        var empCount = Object.keys(uniqueEmps).length;

        if (statTotal)     { statTotal.textContent = String(total); }
        if (statEmployees) { statEmployees.textContent = String(empCount); }
        if (totalBadge)    { totalBadge.textContent = String(filtered.length); }
        if (subtitleEl) {
            subtitleEl.textContent = total === 0
                ? "Tidak ada correction request pending."
                : total + " request dari " + empCount + " karyawan";
        }
    }

    // ─── Table Render ─────────────────────────────────────────────────────────
    function renderTable() {
        if (!tbody) { return; }

        if (filtered.length === 0) {
            tbody.innerHTML = '<tr><td colspan="9" class="text-center text-muted py-4">' +
                '<i class="ti ti-mood-happy me-2 fs-18"></i>Tidak ada correction request pending.</td></tr>';
            hidePagination();
            return;
        }

        var totalPages = Math.ceil(filtered.length / perPage);
        var start = (page - 1) * perPage;
        var slice = filtered.slice(start, start + perPage);

        tbody.innerHTML = slice.map(function (r, idx) {
            var rowIdx = start + idx;
            return '<tr data-correction-row data-row-idx="' + rowIdx + '">' +
                '<td class="fw-semibold">' +
                    '<div>' + esc(r.employeeName || "—") + '</div>' +
                '</td>' +
                '<td>' + esc(r.workDate || "—") + '</td>' +
                '<td>' + fmtTime(r.checkIn || r.checkInTime24) + '</td>' +
                '<td>' + fmtTime(r.checkOut || r.checkOutTime24) + '</td>' +
                '<td>' + fmtBreak(r.breakMinutesRaw) + '</td>' +
                '<td>' + fmtLate(r.lateMinutesRaw) + '</td>' +
                '<td class="text-truncate" style="max-width:280px" title="' + esc(r.correctionReason) + '">' +
                    esc(r.correctionReason || "—") +
                '</td>' +
                '<td class="text-muted small">' + esc(r.correctionRequestedAt || "—") + '</td>' +
                '<td class="text-end">' +
                    '<button type="button" class="btn btn-sm btn-outline-secondary me-1" ' +
                        'data-correction-open-detail data-row-idx="' + rowIdx + '" title="Lihat detail">' +
                        '<i class="ti ti-eye"></i>' +
                    '</button>' +
                    '<button type="button" class="btn btn-sm btn-success me-1" ' +
                        'data-correction-approve-inline data-record-id="' + esc(String(r.recordId || "")) + '" ' +
                        'data-row-idx="' + rowIdx + '" title="Setujui">' +
                        '<i class="ti ti-check"></i>' +
                    '</button>' +
                    '<button type="button" class="btn btn-sm btn-outline-danger" ' +
                        'data-correction-dismiss-inline data-record-id="' + esc(String(r.recordId || "")) + '" ' +
                        'data-row-idx="' + rowIdx + '" title="Tolak">' +
                        '<i class="ti ti-x"></i>' +
                    '</button>' +
                '</td>' +
                '</tr>';
        }).join("");

        // Pagination
        if (filtered.length > perPage) {
            if (pagination) { pagination.style.removeProperty("display"); pagination.style.display = "flex"; }
            if (pageInfo)   { pageInfo.textContent = "Halaman " + page + " dari " + totalPages + " (" + filtered.length + " total)"; }
            if (prevBtn)    { prevBtn.disabled = page <= 1; }
            if (nextBtn)    { nextBtn.disabled = page >= totalPages; }
        } else {
            hidePagination();
        }
    }

    function hidePagination() {
        if (pagination) { pagination.style.display = "none"; }
    }

    function renderError(msg) {
        if (tbody) {
            tbody.innerHTML = '<tr><td colspan="9" class="text-center text-danger py-4">' + esc(msg) + '</td></tr>';
        }
    }

    // ─── Remove row from local data ───────────────────────────────────────────
    function removeRowByRecordId(recordId) {
        var id = String(recordId);
        allRows   = allRows.filter(function (r) { return String(r.recordId) !== id; });
        filtered  = filtered.filter(function (r) { return String(r.recordId) !== id; });
        // If page is now out of range, go back
        var totalPages = Math.max(1, Math.ceil(filtered.length / perPage));
        if (page > totalPages) { page = totalPages; }
        renderStats();
        renderTable();
        updateSidebarBadge(allRows.length);
    }

    // ─── Open Detail Modal ────────────────────────────────────────────────────
    function openDetailModal(row) {
        if (!row || !modal) { return; }
        activeRecord = row;

        if (modalName)    { modalName.textContent    = row.employeeName || "—"; }
        if (modalDate)    { modalDate.textContent    = row.workDate || "—"; }
        if (modalCheckin) { modalCheckin.textContent = row.checkIn || row.checkInTime24 || "—"; }
        if (modalCheckout){ modalCheckout.textContent= row.checkOut || row.checkOutTime24 || "—"; }
        if (modalBreak)   { modalBreak.textContent   = (parseInt(row.breakMinutesRaw, 10) || 0) + " menit"; }
        if (modalLate)    { modalLate.textContent    = (parseInt(row.lateMinutesRaw, 10) || 0) + " menit"; }
        if (modalReason)  { modalReason.textContent  = row.correctionReason || "—"; }
        if (modalReqAt)   { modalReqAt.textContent   = row.correctionRequestedAt || "—"; }
        if (modalGotoAdmin && row.workDate) {
            modalGotoAdmin.href = "/attendance-admin?date=" + encodeURIComponent(row.workDate);
        }

        var bsModal = window.bootstrap && window.bootstrap.Modal ? new window.bootstrap.Modal(modal) : null;
        if (bsModal) { bsModal.show(); }
    }

    function hideModal() {
        if (!modal) { return; }
        var bsInstance = window.bootstrap && window.bootstrap.Modal ? window.bootstrap.Modal.getInstance(modal) : null;
        if (bsInstance) { bsInstance.hide(); }
        activeRecord = null;
    }

    // ─── Approve / Dismiss ────────────────────────────────────────────────────
    function doApprove(recordId, btn) {
        if (btn) { btn.disabled = true; }

        apiPost("/v1/hcm/attendance/admin/correction-approve", { recordId: parseInt(recordId, 10) })
            .then(function (payload) {
                if (!payload || payload.success !== true) {
                    if (window.ArcavNotify) { window.ArcavNotify.error("Gagal menyetujui koreksi."); }
                    if (btn) { btn.disabled = false; }
                    return;
                }
                if (window.ArcavNotify) { window.ArcavNotify.success("Koreksi disetujui."); }
                hideModal();
                removeRowByRecordId(recordId);
            })
            .catch(function (err) {
                if (window.ArcavNotify) { window.ArcavNotify.error("Error: " + formatApiError(err)); }
                if (btn) { btn.disabled = false; }
            });
    }

    function doDismiss(recordId, btn) {
        if (btn) { btn.disabled = true; }

        apiPost("/v1/hcm/attendance/admin/correction-dismiss", { recordId: parseInt(recordId, 10) })
            .then(function (payload) {
                if (!payload || payload.success !== true) {
                    if (window.ArcavNotify) { window.ArcavNotify.error("Gagal menolak koreksi."); }
                    if (btn) { btn.disabled = false; }
                    return;
                }
                if (window.ArcavNotify) { window.ArcavNotify.success("Koreksi ditolak."); }
                hideModal();
                removeRowByRecordId(recordId);
            })
            .catch(function (err) {
                if (window.ArcavNotify) { window.ArcavNotify.error("Error: " + formatApiError(err)); }
                if (btn) { btn.disabled = false; }
            });
    }

    // ─── Event Bindings ───────────────────────────────────────────────────────
    function bindEvents() {
        // Filter inputs — debounced
        var filterTimer;
        function onFilterChange() {
            clearTimeout(filterTimer);
            filterTimer = setTimeout(applyFiltersAndRender, 280);
        }
        if (filterName) { filterName.addEventListener("input", onFilterChange); }
        if (filterFrom) { filterFrom.addEventListener("change", applyFiltersAndRender); }
        if (filterTo)   { filterTo.addEventListener("change", applyFiltersAndRender); }

        // Reset filter
        if (resetBtn) {
            resetBtn.addEventListener("click", function () {
                if (filterName) { filterName.value = ""; }
                if (filterFrom) { filterFrom.value = ""; }
                if (filterTo)   { filterTo.value = ""; }
                applyFiltersAndRender();
            });
        }

        // Refresh
        if (refreshBtn) {
            refreshBtn.addEventListener("click", function () { loadCorrections(); });
        }

        // Pagination
        if (prevBtn) {
            prevBtn.addEventListener("click", function () {
                if (page > 1) { page--; renderTable(); }
            });
        }
        if (nextBtn) {
            nextBtn.addEventListener("click", function () {
                var totalPages = Math.ceil(filtered.length / perPage);
                if (page < totalPages) { page++; renderTable(); }
            });
        }

        // Table delegation (open detail, inline approve, inline dismiss)
        if (tbody) {
            tbody.addEventListener("click", function (e) {
                // Open detail modal
                var detailBtn = e.target.closest("[data-correction-open-detail]");
                if (detailBtn) {
                    var rowIdx = parseInt(detailBtn.getAttribute("data-row-idx"), 10);
                    var row = filtered[rowIdx];
                    if (row) { openDetailModal(row); }
                    return;
                }

                // Inline approve
                var approveBtn = e.target.closest("[data-correction-approve-inline]");
                if (approveBtn) {
                    var recordId = approveBtn.getAttribute("data-record-id");
                    if (recordId) { doApprove(recordId, approveBtn); }
                    return;
                }

                // Inline dismiss
                var dismissBtn = e.target.closest("[data-correction-dismiss-inline]");
                if (dismissBtn) {
                    var recordId2 = dismissBtn.getAttribute("data-record-id");
                    if (recordId2) { doDismiss(recordId2, dismissBtn); }
                    return;
                }
            });
        }

        // Modal approve / dismiss
        if (modalApproveBtn) {
            modalApproveBtn.addEventListener("click", function () {
                if (activeRecord) { doApprove(activeRecord.recordId, modalApproveBtn); }
            });
        }
        if (modalDismissBtn) {
            modalDismissBtn.addEventListener("click", function () {
                if (activeRecord) { doDismiss(activeRecord.recordId, modalDismissBtn); }
            });
        }
    }

    // ─── Init ─────────────────────────────────────────────────────────────────
    function init() {
        bindEvents();
        loadCorrections();
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", init);
    } else {
        init();
    }

})(window, document);
