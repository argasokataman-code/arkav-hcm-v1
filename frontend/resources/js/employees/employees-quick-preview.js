export function bindQuickPreviewModule(deps) {
    var buildEmployeeDetailUrl = deps.buildEmployeeDetailUrl;
    var requestEmployeeDetail = deps.requestEmployeeDetail;
    var formatApiError = deps.formatApiError;
    var saveReturnState = deps.saveReturnState;
    var updateActiveRowHighlight = deps.updateActiveRowHighlight;
    var getSelectedPreviewEmployeeId = deps.getSelectedPreviewEmployeeId;
    var setSelectedPreviewEmployeeId = deps.setSelectedPreviewEmployeeId;
    var escapeHtml = deps.escapeHtml;
    var formatRupiah = deps.formatRupiah;

    var panelEl = document.getElementById("employee_quick_preview");
    var contentEl = document.querySelector("[data-employee-quick-preview-content]");
    var openLinkEl = document.querySelector("[data-employee-quick-open-link]");
    if (!panelEl || !contentEl || !openLinkEl || !window.bootstrap || !window.bootstrap.Offcanvas) {
        return;
    }
    var offcanvas = window.bootstrap.Offcanvas.getOrCreateInstance(panelEl);

    function renderPreviewLoading() {
        contentEl.innerHTML = '<p class="text-muted mb-0">Loading employee preview...</p>';
        openLinkEl.classList.add("d-none");
    }

    function renderPreviewError(message) {
        contentEl.innerHTML = '<div class="alert alert-light border mb-0">' + escapeHtml(message || "Gagal memuat preview employee.") + '</div>';
        openLinkEl.classList.add("d-none");
    }

    function renderPreview(item) {
        if (!item) {
            renderPreviewError("Employee tidak ditemukan.");
            return;
        }
        contentEl.innerHTML =
            '<div class="mb-2"><h5 class="mb-1">' + escapeHtml(item.fullName || "-") + '</h5>' +
            '<span class="badge badge-soft-dark">' + escapeHtml(item.designation || "Employee") + '</span></div>' +
            '<div class="border rounded p-2 mb-2">' +
            '<div class="d-flex justify-content-between mb-1"><span class="text-muted">Employee ID</span><strong>' + escapeHtml(item.employeeNo || "-") + "</strong></div>" +
            '<div class="d-flex justify-content-between mb-1"><span class="text-muted">Department</span><strong>' + escapeHtml(item.departmentName || "-") + "</strong></div>" +
            '<div class="d-flex justify-content-between mb-1"><span class="text-muted">Status</span><strong>' + escapeHtml(item.employmentStatus || "-") + "</strong></div>" +
            '<div class="d-flex justify-content-between mb-1"><span class="text-muted">Join Date</span><strong>' + escapeHtml(item.joinDate || "-") + "</strong></div>" +
            '<div class="d-flex justify-content-between mb-1"><span class="text-muted">Base Salary</span><strong>' + escapeHtml(formatRupiah(item.baseSalary)) + "</strong></div>" +
            '<div class="d-flex justify-content-between"><span class="text-muted">Fixed Allowance</span><strong>' + escapeHtml(formatRupiah(item.fixedAllowance)) + "</strong></div>" +
            "</div>" +
            '<div class="small text-muted">' + escapeHtml(item.email || "-") + "</div>" +
            '<div class="small text-muted">' + escapeHtml(item.phone || "-") + "</div>";

        var targetUrl = buildEmployeeDetailUrl(item.id);
        openLinkEl.href = targetUrl;
        openLinkEl.setAttribute("data-employee-id", String(item.id));
        openLinkEl.classList.remove("d-none");
    }

    function openEmployeePreview(employeeId) {
        if (!employeeId) {
            return;
        }
        setSelectedPreviewEmployeeId(String(employeeId));
        updateActiveRowHighlight();
        renderPreviewLoading();
        offcanvas.show();

        requestEmployeeDetail(employeeId)
            .then(function (payload) {
                if (!payload || payload.success !== true) {
                    renderPreviewError(formatApiError(payload, 0));
                    return;
                }
                renderPreview(payload.data || null);
            })
            .catch(function (error) {
                if (error && window.AuthApi && window.AuthApi.handleUnauthorizedFromApi(error.status, error.data)) {
                    return;
                }
                renderPreviewError(formatApiError(error && error.data, error && error.status));
            });
    }

    document.addEventListener("click", function (event) {
        var detailLink = event.target.closest("[data-employee-detail-link]");
        if (detailLink) {
            saveReturnState(detailLink.getAttribute("data-employee-id") || getSelectedPreviewEmployeeId() || "");
            return;
        }

        var row = event.target.closest("[data-employees-row-preview]");
        if (!row) {
            return;
        }

        var ignore = event.target.closest("a, button, input, label, .form-check");
        if (ignore) {
            return;
        }
        event.preventDefault();
        openEmployeePreview(row.getAttribute("data-employees-row-preview"));
    });

    if (getSelectedPreviewEmployeeId()) {
        openEmployeePreview(getSelectedPreviewEmployeeId());
    }
}