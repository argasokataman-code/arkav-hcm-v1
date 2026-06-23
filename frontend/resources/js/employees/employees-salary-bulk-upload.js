export function bindSalaryBulkUploadModule(deps) {
    var requestFormData = deps.requestFormData;
    var formatApiError = deps.formatApiError;
    var escapeHtml = deps.escapeHtml;
    var loadEmployeesData = deps.loadEmployeesData;
    var getOrganizationReferenceSnapshot = deps.getOrganizationReferenceSnapshot;
    var __orgSnapshotProviderAvailable = typeof getOrganizationReferenceSnapshot === 'function';

    var form = document.querySelector("[data-employee-bulk-upload-form]");
    if (!form || form.getAttribute("data-bulk-upload-bound") === "1") {
        return;
    }
    form.setAttribute("data-bulk-upload-bound", "1");

    var resultBox = form.querySelector("[data-employee-bulk-upload-results]");
    var fileInput = form.querySelector("[data-employee-bulk-upload-file]");
    var modalEl = document.getElementById("employee_bulk_upload");
    var prerequisiteModalEl = document.getElementById("employee_bulk_org_required");
    var prerequisiteMessageEl = document.querySelector("[data-employee-bulk-org-required-message]");
    var templateLinks = Array.prototype.slice.call(document.querySelectorAll("[data-employee-bulk-template-link]"));
    var uploadOpeners = Array.prototype.slice.call(document.querySelectorAll("[data-employee-bulk-upload-open]"));

    function getOrgSnapshot() {
        if (!__orgSnapshotProviderAvailable) {
            return { departments: [], designations: [] };
        }
        var snapshot = getOrganizationReferenceSnapshot() || {};

        return {
            departments: Array.isArray(snapshot.departments) ? snapshot.departments : [],
            designations: Array.isArray(snapshot.designations) ? snapshot.designations : [],
        };
    }

    function buildPrerequisiteMessage() {
        var snapshot = getOrgSnapshot();
        var missing = [];

        if (!snapshot.departments.length) {
            missing.push("department");
        }
        if (!snapshot.designations.length) {
            missing.push("designation");
        }

        return {
            ready: missing.length === 0,
            message: missing.length === 0
                ? "Master department dan designation sudah siap untuk bulk employee."
                : "Isi minimal 1 " + missing.join(" dan 1 ") + " sebelum download template atau upload bulk employee.",
        };
    }

    function showPrerequisiteModal() {
        var prerequisite = buildPrerequisiteMessage();

        // Re-query DOM for modal and message elements at invocation time.
        // This handles cases where the module was loaded before the DOM
        // (so the initially-captured references may be null).
        var modalElLocal = document.getElementById("employee_bulk_org_required") || prerequisiteModalEl;
        var messageElLocal = document.querySelector("[data-employee-bulk-org-required-message]") || prerequisiteMessageEl;

        if (messageElLocal) {
            messageElLocal.textContent = prerequisite.message;
        }
        if (window.ArcavUi && window.ArcavUi.showToast) {
            window.ArcavUi.showToast(prerequisite.message, "warning");
        }
        if (window.bootstrap && window.bootstrap.Modal && modalElLocal) {
            window.bootstrap.Modal.getOrCreateInstance(modalElLocal).show();
        }
    }

    function hasOrgPrerequisites() {
        return buildPrerequisiteMessage().ready;
    }

    function syncPrerequisiteUiState() {
        // If there's no organization snapshot provider available on the page
        // we assume unknown state and avoid proactively blocking the template
        // link so users can still download; server-side will enforce guards.
        var blocked = __orgSnapshotProviderAvailable ? !hasOrgPrerequisites() : false;
        var tooltip = blocked
            ? "Lengkapi minimal 1 department dan 1 designation terlebih dahulu."
            : "";

        templateLinks.concat(uploadOpeners).forEach(function (element) {
            if (!element) {
                return;
            }
            element.classList.toggle("disabled", blocked);
            element.setAttribute("aria-disabled", blocked ? "true" : "false");
            if (tooltip) {
                element.setAttribute("title", tooltip);
            } else {
                element.removeAttribute("title");
            }
        });
    }

    function renderBulkResult(kind, title, lines) {
        if (!resultBox) {
            return;
        }
        var list = Array.isArray(lines) ? lines.filter(Boolean) : [];
        resultBox.className = "alert alert-" + kind + " mb-0";
        resultBox.classList.remove("d-none");
        resultBox.innerHTML = '<strong class="d-block mb-1">' + escapeHtml(title) + '</strong>' +
            (list.length ? ('<ul class="mb-0 ps-3">' + list.map(function (line) {
                return '<li>' + escapeHtml(line) + '</li>';
            }).join("") + '</ul>') : "");
    }

    function clearBulkResult() {
        if (!resultBox) {
            return;
        }
        resultBox.className = "alert d-none mb-0";
        resultBox.textContent = "";
        resultBox.innerHTML = "";
    }

    if (modalEl) {
        modalEl.addEventListener("show.bs.modal", function (event) {
            clearBulkResult();
            syncPrerequisiteUiState();
            if (hasOrgPrerequisites()) {
                return;
            }
            if (event && typeof event.preventDefault === "function") {
                event.preventDefault();
            }
            showPrerequisiteModal();
        });
        modalEl.addEventListener("shown.bs.modal", function () {
            var firstInput = document.querySelector("#employee_bulk_upload input:not([type=hidden]):not([type=password]), #employee_bulk_upload select");
            if (firstInput) setTimeout(function () { firstInput.focus(); }, 100);
        });
    }
    if (fileInput) {
        fileInput.addEventListener("change", clearBulkResult);
    }

    templateLinks.forEach(function (link) {
        link.addEventListener("click", function (event) {
            syncPrerequisiteUiState();
            if (hasOrgPrerequisites()) {
                return;
            }
            event.preventDefault();
            showPrerequisiteModal();
        });
    });

    syncPrerequisiteUiState();

    // Delegated click handler: ensures clicks on template or upload triggers
    // are intercepted even if those elements were not present at module bind time.
    document.addEventListener("click", function (event) {
        try {
            var trigger = event && event.target && event.target.closest ? event.target.closest("[data-employee-bulk-upload-open]") : null;
            if (!trigger) {
                return;
            }
            syncPrerequisiteUiState();
            if (!hasOrgPrerequisites()) {
                event.preventDefault();
                showPrerequisiteModal();
            }
        }
        catch (err) {
            console.error && console.error('bulk upload delegated click handler error', err);
        }
    });

    form.addEventListener("submit", function (event) {
        event.preventDefault();
        if (!ArcavValidation.validateForm(form)) { return; }
        clearBulkResult();
        syncPrerequisiteUiState();

        if (!hasOrgPrerequisites()) {
            showPrerequisiteModal();
            return;
        }

        var file = fileInput && fileInput.files && fileInput.files[0] ? fileInput.files[0] : null;
        if (!file) {
            if (window.ArcavUi && window.ArcavUi.showToast) {
                window.ArcavUi.showToast("Pilih file template bulk employee terlebih dahulu.", "warning");
            }
            renderBulkResult("warning", "Belum ada file dipilih.", ["Silakan pilih workbook bulk employee terlebih dahulu."]);
            return;
        }

        var fd = new FormData();
        fd.append("file", file);
        requestFormData("post", "/v1/hcm/employees/bulk-upload", fd).then(function (resp) {
            if (!resp || resp.success !== true) {
                if (resp && resp.error && resp.error.code === "EMPLOYEE_BULK_ORG_SETUP_REQUIRED") {
                    showPrerequisiteModal();
                }
                window.ArcavUi && window.ArcavUi.showToast && window.ArcavUi.showToast(formatApiError(resp, 0), "danger");
                renderBulkResult("danger", "Bulk upload gagal.", [formatApiError(resp, 0)]);
                return;
            }
            var createdRows = Number(resp && resp.data ? resp.data.createdRows : 0);
            var updatedRows = Number(resp && resp.data ? resp.data.updatedRows : 0);
            var failedRows = Number(resp && resp.data ? resp.data.failedRows : 0);
            var message = "Bulk upload selesai. Created: " + createdRows + ", Updated: " + updatedRows + ", Failed: " + failedRows + ".";
            if (window.ArcavUi && window.ArcavUi.showToast) {
                window.ArcavUi.showToast(message, failedRows > 0 ? "warning" : "success");
            }
            renderBulkResult(failedRows > 0 ? "warning" : "success", "Bulk upload selesai.", [
                "Created rows: " + createdRows,
                "Updated rows: " + updatedRows,
                "Failed rows: " + failedRows
            ].concat(resp.data && Array.isArray(resp.data.errors) ? resp.data.errors.slice(0, 8) : []));
            form.reset();
            if (window.bootstrap && window.bootstrap.Modal && modalEl) {
                window.setTimeout(function () {
                    window.bootstrap.Modal.getOrCreateInstance(modalEl).hide();
                }, 900);
            }
            loadEmployeesData();
        }).catch(function (error) {
            if (error && window.AuthApi && window.AuthApi.handleUnauthorizedFromApi(error.status, error.data)) {
                return;
            }
            if (error && error.data && error.data.error && error.data.error.code === "EMPLOYEE_BULK_ORG_SETUP_REQUIRED") {
                showPrerequisiteModal();
            }
            var rowErrors = error && error.data && error.data.data && Array.isArray(error.data.data.errors)
                ? error.data.data.errors
                : [];
            if (rowErrors.length) {
                console.warn("Employee bulk upload validation errors:", rowErrors);
            }
            if (window.ArcavUi && window.ArcavUi.showToast) {
                window.ArcavUi.showToast(formatApiError(error && error.data, error && error.status), "danger");
            }
            renderBulkResult("danger", "Bulk upload dibatalkan.", rowErrors.length ? rowErrors : [formatApiError(error && error.data, error && error.status)]);
        });
    });
}