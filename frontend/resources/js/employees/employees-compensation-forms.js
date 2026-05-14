import { bindEmployeeCompensationFormsLifecycle } from "./compensation-forms/lifecycle.js";
import { createEmployeeCompensationValidationTools } from "./compensation-forms/validation.js";
import { createEmployeeCompensationWilayah } from "./compensation-forms/wilayah.js";

export function bindEmployeeCompensationFormsModule(deps) {
    var PASSWORD_RULE_REGEX = /^(?=.*[A-Z])(?=.*[a-z])(?=.*\d)[A-Za-z\d@$!%*?&._-]{8,64}$/;
    var LETTER_SPACE_PUNCT_150_REGEX = /^[\p{L}\p{M} .,'-]{2,150}$/u;
    var LETTER_SPACE_PUNCT_100_REGEX = /^[\p{L}\p{M} .,'-]{2,100}$/u;
    var PHONE_WITH_COUNTRY_REGEX = /^\+?[0-9]{10,15}$/;
    var requestJson = deps.requestJson;
    var requestEmployeeDetail = deps.requestEmployeeDetail;
    var fillDesignationSelectForDepartment = deps.fillDesignationSelectForDepartment;
    var loadTeamsDropdown = deps.loadTeamsDropdown;
    var formatApiError = deps.formatApiError;
    var loadEmployeesData = deps.loadEmployeesData;

    var addForm = document.querySelector("[data-employee-add-form]");
    var editForm = document.querySelector("[data-employee-edit-form]");
    var wilayahTools = createEmployeeCompensationWilayah({
        requestJson: requestJson,
    });
    var bindWilayahChangeHandlers = wilayahTools.bindWilayahChangeHandlers;
    var resetWilayahCascade = wilayahTools.resetWilayahCascade;
    var setWilayahCascade = wilayahTools.setWilayahCascade;

    function readField(form, key) {
        var el = form ? form.querySelector('[data-employee-add-field="' + key + '"], [data-employee-edit-field="' + key + '"]') : null;
        return el ? String(el.value || "").trim() : "";
    }

    function writeField(form, key, value) {
        var el = form ? form.querySelector('[data-employee-add-field="' + key + '"], [data-employee-edit-field="' + key + '"]') : null;
        if (el) {
            el.value = value == null ? "" : String(value);
        }
    }

    function readText(form, key) {
        var value = readField(form, key);
        return value === "" ? null : value;
    }

    function readChecked(form, key) {
        var el = form ? form.querySelector('[data-employee-add-field="' + key + '"], [data-employee-edit-field="' + key + '"]') : null;
        return !!(el && el.checked === true);
    }

    function readNumberOrNull(value) {
        var raw = String(value == null ? "" : value).trim();
        if (raw === "") {
            return null;
        }
        var n = Number(raw);
        return isFinite(n) && n >= 0 ? n : null;
    }

    function readInteger(form, key) {
        var raw = readField(form, key);
        if (!raw) {
            return null;
        }
        var n = parseInt(raw, 10);
        return isNaN(n) ? null : n;
    }

    function formatEmployeeCode(value) {
        var n = Number(value);
        if (!Number.isFinite(n) || n <= 0) {
            return null;
        }
        return "EMP-" + String(Math.trunc(n));
    }

    function updateModalEmployeeUuid(form, item) {
        var modal = form ? form.closest(".modal") : null;
        var label = modal ? modal.querySelector("[data-employee-modal-employee-no]") : null;
        if (!label) {
            return;
        }
        var employeeCode = item ? formatEmployeeCode(item.id) : null;
        label.textContent = employeeCode ? ("ID " + employeeCode) : "ID akan tersedia setelah save";
    }

    function setStep(form, index) {
        if (!form) {
            return;
        }
        var panes = Array.prototype.slice.call(form.querySelectorAll("[data-employee-step-pane]"));
        var triggers = Array.prototype.slice.call(form.querySelectorAll("[data-employee-step-trigger]"));
        if (!panes.length) {
            return;
        }
        var safeIndex = Math.max(0, Math.min(index, panes.length - 1));
        form.setAttribute("data-employee-step-index", String(safeIndex));

        panes.forEach(function (pane, paneIndex) {
            pane.classList.toggle("d-none", paneIndex !== safeIndex);
        });
        triggers.forEach(function (trigger, triggerIndex) {
            var active = triggerIndex === safeIndex;
            trigger.classList.toggle("active", active);
            trigger.classList.toggle("btn-primary", active);
            trigger.classList.toggle("text-white", active);
            trigger.classList.toggle("btn-light", !active);
        });

        var prevBtn = form.querySelector("[data-employee-step-prev]");
        var nextBtn = form.querySelector("[data-employee-step-next]");
        var submitBtn = form.querySelector("[data-employee-step-submit]");
        if (prevBtn) {
            prevBtn.classList.toggle("d-none", safeIndex === 0);
        }
        if (nextBtn) {
            nextBtn.classList.toggle("d-none", safeIndex >= panes.length - 1);
        }
        if (submitBtn) {
            submitBtn.classList.toggle("d-none", safeIndex < panes.length - 1);
        }

        var caption = form.querySelector("[data-employee-step-caption]");
        if (caption) {
            caption.textContent = "Step " + (safeIndex + 1) + " of " + panes.length;
        }
    }

    function normalizeContractTypeValue(value) {
        var raw = String(value || "").trim().toLowerCase();
        if (!raw || raw === "pkwtt") {
            return "permanent";
        }
        if (raw === "pkwt") {
            return "contract";
        }
        return raw === "contract" ? "contract" : "permanent";
    }

    function normalizeEmployeeTypeValue(value) {
        var raw = String(value || "").trim().toLowerCase();
        if (!raw) {
            return "";
        }
        if (raw === "pkwtt") {
            return "permanent";
        }
        if (raw === "pkwt") {
            return "contract";
        }
        return raw;
    }

    function expectedContractTypeByEmployeeType(employeeType) {
        if (employeeType === "permanent") {
            return "permanent";
        }
        if (employeeType === "contract" || employeeType === "intern") {
            return "contract";
        }
        return null;
    }

    function ensureEmployeeContractTypeConsistency(form, options) {
        if (!form) {
            return true;
        }
        var opts = options || {};
        var employeeType = normalizeEmployeeTypeValue(readText(form, "employeeType"));
        var contractTypeInput = form.querySelector('[data-employee-add-field="contractType"], [data-employee-edit-field="contractType"]');
        if (!employeeType || !contractTypeInput) {
            return true;
        }

        var expected = expectedContractTypeByEmployeeType(employeeType);
        if (!expected) {
            return true;
        }

        var current = normalizeContractTypeValue(contractTypeInput.value);
        if (current === expected) {
            return true;
        }

        if (opts.autoCorrect) {
            contractTypeInput.value = expected;
            toggleContractEndDateVisibility(form);
            if (opts.notify !== false) {
                showValidationToast("Contract type otomatis disesuaikan dengan employee type untuk mencegah data bentrok.");
            }
            return true;
        }

        showValidationToast("Employee type dan contract type tidak boleh bertentangan. Permanent harus permanent, contract/intern harus contract.");
        return false;
    }

    var validationTools = createEmployeeCompensationValidationTools({
        setStep: setStep,
    });
    var applyBackendValidationErrors = validationTools.applyBackendValidationErrors;
    var clearBackendValidationState = validationTools.clearBackendValidationState;
    var enforceInputRules = validationTools.enforceInputRules;
    var failField = validationTools.failField;
    var maybeShowEmployeeLimitPopup = validationTools.maybeShowEmployeeLimitPopup;
    var showValidationToast = validationTools.showValidationToast;
    var validateEducationRows = validationTools.validateEducationRows;
    var validateEmergencyContactRows = validationTools.validateEmergencyContactRows;
    var validateExperienceRows = validationTools.validateExperienceRows;
    var validateRegexField = validationTools.validateRegexField;

    function toggleContractEndDateVisibility(form) {
        if (!form) {
            return;
        }
        var contractType = normalizeContractTypeValue(readText(form, "contractType"));
        var wrap = form.querySelector("[data-employee-contract-end-wrap]");
        var input = form.querySelector('[data-employee-add-field="contractEndDate"], [data-employee-edit-field="contractEndDate"]');
        if (!wrap || !input) {
            return;
        }
        var isContract = contractType === "contract";
        wrap.classList.toggle("d-none", !isContract);
        input.required = isContract;
        input.disabled = !isContract;
        if (!isContract) {
            input.value = "";
        }
    }

    function maybeAutoSyncContractStartDate(form) {
        if (!form) {
            return;
        }
        var startDateInput = form.querySelector('[data-employee-add-field="startDate"], [data-employee-edit-field="startDate"]');
        var contractStartInput = form.querySelector('[data-employee-add-field="contractStartDate"], [data-employee-edit-field="contractStartDate"]');
        if (!startDateInput || !contractStartInput) {
            return;
        }

        var startDateValue = String(startDateInput.value || "").trim();
        var contractStartValue = String(contractStartInput.value || "").trim();
        var previousAutoValue = String(form.getAttribute("data-employee-contract-start-auto") || "").trim();

        var canAutofill = contractStartValue === "" || contractStartValue === previousAutoValue;
        if (canAutofill) {
            contractStartInput.value = startDateValue;
            form.setAttribute("data-employee-contract-start-auto", startDateValue);
            return;
        }

        form.setAttribute("data-employee-contract-start-auto", previousAutoValue || startDateValue);
    }

    function validateCurrentStep(form) {
        if (!form) {
            return true;
        }
        toggleContractEndDateVisibility(form);
        ensureEmployeeContractTypeConsistency(form, { autoCorrect: true, notify: false });
        var stepIndex = Number(form.getAttribute("data-employee-step-index") || 0);

        var passwordInput = form.querySelector('[data-employee-add-field="password"], [data-employee-edit-field="password"]');
        var confirmPasswordInput = form.querySelector('[data-employee-add-field="confirmPassword"], [data-employee-edit-field="confirmPassword"]');
        // Clear password custom validity upfront so stale errors don't pollute the reportValidity loop below.
        if (passwordInput && typeof passwordInput.setCustomValidity === "function") {
            passwordInput.setCustomValidity("");
        }
        if (confirmPasswordInput && typeof confirmPasswordInput.setCustomValidity === "function") {
            confirmPasswordInput.setCustomValidity("");
        }

        var pane = form.querySelector('[data-employee-step-pane="' + stepIndex + '"]');
        if (pane) {
            var fields = pane.querySelectorAll("input, select, textarea");
            for (var i = 0; i < fields.length; i += 1) {
                if (fields[i].disabled) {
                    // Required-but-disabled fields mean async options are not ready yet.
                    if (fields[i].required) {
                        showValidationToast("Masih ada field wajib yang belum siap. Tunggu loading selesai lalu lengkapi field tersebut.");
                        return false;
                    }
                    continue;
                }
                if (typeof fields[i].reportValidity === "function" && !fields[i].reportValidity()) {
                    return false;
                }
            }
        }

        // Password strength + match check — only on step 0 (Personal) add form.
        // Trim values to match what buildPayload sends so validation is consistent.
        if (stepIndex === 0 && passwordInput && confirmPasswordInput) {
            var passwordValue = String(passwordInput.value || "").trim();
            var confirmPasswordValue = String(confirmPasswordInput.value || "").trim();
            if (passwordValue !== "" && !PASSWORD_RULE_REGEX.test(passwordValue)) {
                if (typeof passwordInput.setCustomValidity === "function") {
                    passwordInput.setCustomValidity("Password harus 8-64 karakter, mengandung huruf besar, huruf kecil, dan angka.");
                }
                if (typeof passwordInput.reportValidity === "function") {
                    passwordInput.reportValidity();
                }
                showValidationToast("Format password belum valid. Gunakan minimal 8 karakter dengan huruf besar, huruf kecil, dan angka.");
                return false;
            }
            if (typeof passwordInput.setCustomValidity === "function") {
                passwordInput.setCustomValidity("");
            }
            if (passwordValue !== confirmPasswordValue) {
                if (typeof confirmPasswordInput.setCustomValidity === "function") {
                    confirmPasswordInput.setCustomValidity("Konfirmasi password harus sama dengan password.");
                }
                if (typeof confirmPasswordInput.reportValidity === "function") {
                    confirmPasswordInput.reportValidity();
                }
                showValidationToast("Password dan konfirmasi password harus sama sebelum lanjut ke step berikutnya.");
                return false;
            }
        }

        // Force nationality to Indonesia (always OK to run on any step).
        var nationalityInput = form.querySelector('[data-employee-add-field="nationality"], [data-employee-edit-field="nationality"]');
        if (nationalityInput) {
            nationalityInput.value = "Indonesia";
        }

        // Date cross-check — only relevant once step 1 (Employment) fields exist.
        if (stepIndex >= 1) {
            var startDate = readField(form, "startDate");
            var probationEndDate = readField(form, "probationEndDate");
            if (startDate && probationEndDate && probationEndDate < startDate) {
                showValidationToast("Probation end date tidak boleh lebih awal dari effective start date.");
                return false;
            }
        }

        if (stepIndex === 0) {
            // When district has no villages, addressDetail becomes the required fallback.
            var villageSelect = form.querySelector("[data-employee-wilayah-village]");
            if (villageSelect && villageSelect.getAttribute("data-village-unavailable") === "1") {
                var addressDetailEl = form.querySelector('[data-employee-add-field="addressDetail"], [data-employee-edit-field="addressDetail"]');
                var addressDetailVal = addressDetailEl ? String(addressDetailEl.value || "").trim() : "";
                if (!addressDetailVal) {
                    if (addressDetailEl) { addressDetailEl.focus(); }
                    showValidationToast("Kecamatan ini tidak memiliki data kelurahan. Wajib isi Address Detail sebagai pengganti.");
                    return false;
                }
            }
            if (!validateRegexField(form, "name", LETTER_SPACE_PUNCT_150_REGEX, "Full name hanya boleh huruf, spasi, dan tanda baca umum (2-150 karakter).")) {
                return false;
            }
            if (!validateRegexField(form, "placeOfBirth", LETTER_SPACE_PUNCT_150_REGEX, "Place of birth hanya boleh huruf, spasi, dan tanda baca umum (2-150 karakter).")) {
                return false;
            }
            if (!validateRegexField(form, "phone", PHONE_WITH_COUNTRY_REGEX, "Phone wajib 10-15 digit, boleh diawali +.")) {
                return false;
            }
            if (!validateRegexField(form, "nik", /^[0-9]{16}$/, "NIK wajib 16 digit angka.")) {
                return false;
            }
        }

        // Contract cross-checks — only relevant from step 2 (Compensation) onward.
        // Running on step 0/1 would produce confusing toasts about fields the user hasn't seen yet.
        if (stepIndex >= 2) {
            var contractType = normalizeContractTypeValue(readText(form, "contractType"));
            var employeeType = normalizeEmployeeTypeValue(readText(form, "employeeType"));
            var expectedContractType = expectedContractTypeByEmployeeType(employeeType);
            if (expectedContractType && contractType !== expectedContractType) {
                showValidationToast("Employee type dan contract type tidak sinkron. Mohon cek ulang tipe employee.");
                return false;
            }
            var contractStartDate = readField(form, "contractStartDate");
            var contractEndDate = readField(form, "contractEndDate");
            if (contractStartDate && contractEndDate && contractEndDate < contractStartDate) {
                showValidationToast("Contract end date tidak boleh lebih awal dari contract start date.");
                return false;
            }
            if (contractType === "contract" && !contractEndDate) {
                showValidationToast("Contract end date wajib diisi untuk tipe contract.");
                return false;
            }
            if (contractType === "permanent" && contractEndDate) {
                showValidationToast("Contract end date tidak boleh diisi untuk tipe permanent.");
                return false;
            }
        }

        // Base salary — step 2 only.
        if (stepIndex === 2) {
            var baseSalary = readField(form, "baseSalary");
            // baseSalary is required in HTML, so reportValidity catches empty; only check non-empty invalid values here.
            if (baseSalary !== "" && (!/^[0-9]+$/.test(baseSalary) || Number(baseSalary) < 0)) {
                showValidationToast("Base salary wajib angka 0 atau lebih besar.");
                return false;
            }
        }

        // Bank & Tax fields — step 3 (Bank & Tax pane) or submit from step 4.
        // Runs on both so "Next" on step 3 catches bad format AND submit always re-checks.
        if (stepIndex >= 3) {
            if (!validateRegexField(form, "bankAccountNo", /^[0-9]{8,30}$/, "Nomor rekening wajib angka 8-30 digit.")) {
                return false;
            }
            if (!validateRegexField(form, "bankAccountHolderName", LETTER_SPACE_PUNCT_100_REGEX, "Nama pemilik rekening hanya boleh huruf, spasi, dan tanda baca umum (2-100 karakter).")) {
                return false;
            }
            var npwpField = form.querySelector('[data-employee-add-field="npwp"], [data-employee-edit-field="npwp"]');
            var npwpValue = npwpField ? String(npwpField.value || "").trim() : "";
            if (npwpValue) {
                var npwpDigits = npwpValue.replace(/\D+/g, "");
                if (!/^[0-9]{15,16}$/.test(npwpDigits)) {
                    return failField(npwpField, "NPWP wajib berisi 15-16 digit angka (titik/strip diperbolehkan).");
                }
            }
            if (!validateRegexField(form, "bpjsKesehatanNo", /^[0-9]{13}$/, "BPJS Kesehatan wajib 13 digit angka.", { preserveSpaces: false })) {
                return false;
            }
            if (!validateRegexField(form, "bpjsKetenagakerjaanNo", /^[0-9]{11}$/, "BPJS Ketenagakerjaan wajib 11 digit angka.", { preserveSpaces: false })) {
                return false;
            }
        }

        // Emergency contacts — step 4 (Background/final step) only.
        if (stepIndex === 4) {
            if (!validateEducationRows(form)) {
                return false;
            }
            if (!validateExperienceRows(form)) {
                return false;
            }
            if (!validateEmergencyContactRows(form)) {
                return false;
            }
        }

        return true;
    }

    bindEmployeeCompensationFormsLifecycle({
        addForm: addForm,
        editForm: editForm,
        requestJson: requestJson,
        requestEmployeeDetail: requestEmployeeDetail,
        fillDesignationSelectForDepartment: fillDesignationSelectForDepartment,
        loadTeamsDropdown: loadTeamsDropdown,
        formatApiError: formatApiError,
        loadEmployeesData: loadEmployeesData,
        readField: readField,
        readText: readText,
        readChecked: readChecked,
        readInteger: readInteger,
        readNumberOrNull: readNumberOrNull,
        writeField: writeField,
        normalizeContractTypeValue: normalizeContractTypeValue,
        toggleContractEndDateVisibility: toggleContractEndDateVisibility,
        resetWilayahCascade: resetWilayahCascade,
        setWilayahCascade: setWilayahCascade,
        bindWilayahChangeHandlers: bindWilayahChangeHandlers,
        updateModalEmployeeUuid: updateModalEmployeeUuid,
        ensureEmployeeContractTypeConsistency: ensureEmployeeContractTypeConsistency,
        maybeAutoSyncContractStartDate: maybeAutoSyncContractStartDate,
        setStep: setStep,
        clearBackendValidationState: clearBackendValidationState,
        validateCurrentStep: validateCurrentStep,
        applyBackendValidationErrors: applyBackendValidationErrors,
        maybeShowEmployeeLimitPopup: maybeShowEmployeeLimitPopup,
        enforceInputRules: enforceInputRules,
    });

    function validateComplianceFields(form) {
        if (!form) {
            return true;
        }

        var npwpInput = form.querySelector('[data-employee-add-field="npwp"], [data-employee-edit-field="npwp"]');
        var bpjsKesInput = form.querySelector('[data-employee-add-field="bpjsKesehatanNo"], [data-employee-edit-field="bpjsKesehatanNo"]');
        var bpjsKetInput = form.querySelector('[data-employee-add-field="bpjsKetenagakerjaanNo"], [data-employee-edit-field="bpjsKetenagakerjaanNo"]');

        var npwp = String(npwpInput && npwpInput.value ? npwpInput.value : "").trim();
        var bpjsKes = String(bpjsKesInput && bpjsKesInput.value ? bpjsKesInput.value : "").trim();
        var bpjsKet = String(bpjsKetInput && bpjsKetInput.value ? bpjsKetInput.value : "").trim();

        var NPWP_REGEX = /^[0-9]{15}$/;
        var BPJS_REGEX = /^[0-9]{11,13}$/;

        if (npwp && !NPWP_REGEX.test(npwp)) {
            return failField(npwpInput, "Nomor NPWP harus terdiri dari 15 digit angka.");
        }

        if (bpjsKes && !BPJS_REGEX.test(bpjsKes)) {
            return failField(bpjsKesInput, "Nomor BPJS Kesehatan harus terdiri dari 11-13 digit angka.");
        }

        if (bpjsKet && !BPJS_REGEX.test(bpjsKet)) {
            return failField(bpjsKetInput, "Nomor BPJS Ketenagakerjaan harus terdiri dari 11-13 digit angka.");
        }

        return true;
    }

    function validateEmployeeForm(form) {
        if (!form) {
            return false;
        }

        if (!validateComplianceFields(form)) {
            return false;
        }

        if (!validateEducationRows(form)) {
            return false;
        }

        if (!validateExperienceRows(form)) {
            return false;
        }

        if (!validateEmergencyContactRows(form)) {
            return false;
        }

        return true;
    }
}
