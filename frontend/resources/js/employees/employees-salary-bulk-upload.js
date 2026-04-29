export function bindSalaryBulkUploadModule(deps) {
    var requestFormData = deps.requestFormData;
    var formatApiError = deps.formatApiError;
    var escapeHtml = deps.escapeHtml;
    var loadEmployeesData = deps.loadEmployeesData;

    var form = document.querySelector("[data-employee-bulk-upload-form]");
    if (!form || form.getAttribute("data-bulk-upload-bound") === "1") {
        return;
    }
    form.setAttribute("data-bulk-upload-bound", "1");

    var resultBox = form.querySelector("[data-employee-bulk-upload-results]");
    var fileInput = form.querySelector("[data-employee-bulk-upload-file]");
    var modalEl = document.getElementById("employee_bulk_upload");

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
        modalEl.addEventListener("show.bs.modal", clearBulkResult);
    }
    if (fileInput) {
        fileInput.addEventListener("change", clearBulkResult);
    }

    form.addEventListener("submit", function (event) {
        event.preventDefault();
        clearBulkResult();
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