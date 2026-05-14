export function createEmployeeCompensationValidationTools(deps) {
    var setStep = deps.setStep;
    var EMERGENCY_NAME_REGEX = /^[\p{L}\p{M}' .,-]{2,100}$/u;
    var EMERGENCY_RELATIONSHIP_REGEX = /^[\p{L}\p{M}' ./-]{2,50}$/u;
    var PHONE_WITH_COUNTRY_REGEX = /^\+?[0-9]{10,15}$/;
    var ALPHA_REQUIRED_REGEX = /[A-Za-z\p{L}]/u;

    function showValidationToast(message) {
        if (window.ArcavUi && window.ArcavUi.showToast) {
            window.ArcavUi.showToast(message, "warning");
        }
    }

    function digitsOnly(value) {
        return String(value || "").replace(/\D+/g, "");
    }

    function phoneCharsOnly(value) {
        var raw = String(value || "").replace(/[^0-9+]/g, "");
        if (!raw) {
            return "";
        }
        if (raw.charAt(0) === "+") {
            return "+" + raw.slice(1).replace(/\+/g, "");
        }
        return raw.replace(/\+/g, "");
    }

    function lettersSpacePunctOnly(value) {
        return String(value || "").replace(/[^\p{L}\p{M} .,'-]/gu, "");
    }

    function relationshipCharsOnly(value) {
        return String(value || "").replace(/[^\p{L}\p{M} .'\/-]/gu, "");
    }

    function npwpCharsOnly(value) {
        return String(value || "").replace(/[^0-9.-]/g, "");
    }

    function trimToMax(value, maxLength) {
        if (typeof maxLength !== "number" || maxLength <= 0) {
            return value;
        }
        return String(value || "").slice(0, maxLength);
    }

    function enforceInputRules(field) {
        if (!field) {
            return;
        }

        var fieldKey = field.getAttribute("data-employee-add-field") || field.getAttribute("data-employee-edit-field") || "";
        var repeatKey = field.getAttribute("data-repeat-key") || "";
        var raw = String(field.value || "");
        var next = raw;

        if (
            fieldKey === "nik"
            || fieldKey === "bankAccountNo"
            || fieldKey === "bpjsKesehatanNo"
            || fieldKey === "bpjsKetenagakerjaanNo"
            || fieldKey === "baseSalary"
        ) {
            next = digitsOnly(raw);
        } else if (
            fieldKey === "phone"
            || repeatKey === "phone"
        ) {
            next = phoneCharsOnly(raw);
        } else if (
            fieldKey === "name"
            || fieldKey === "placeOfBirth"
            || fieldKey === "bankAccountHolderName"
            || repeatKey === "name"
        ) {
            next = lettersSpacePunctOnly(raw);
        } else if (repeatKey === "relationship") {
            next = relationshipCharsOnly(raw);
        } else if (fieldKey === "npwp") {
            next = npwpCharsOnly(raw);
        }

        var attrMaxLength = parseInt(String(field.getAttribute("maxlength") || ""), 10);
        if (!isNaN(attrMaxLength) && attrMaxLength > 0) {
            next = trimToMax(next, attrMaxLength);
        }

        if (next !== raw) {
            field.value = next;
        }
    }

    function failField(field, message) {
        if (!field) {
            showValidationToast(message);
            return false;
        }
        if (typeof field.setCustomValidity === "function") {
            field.setCustomValidity(message);
        }
        if (typeof field.reportValidity === "function") {
            field.reportValidity();
        }
        if (typeof field.focus === "function") {
            field.focus();
        }
        showValidationToast(message);
        return false;
    }

    function validateRegexField(form, key, regex, message, options) {
        var opts = options || {};
        var field = form ? form.querySelector('[data-employee-add-field="' + key + '"], [data-employee-edit-field="' + key + '"]') : null;
        if (!field) {
            return true;
        }
        var raw = String(field.value || "");
        var value = opts.preserveSpaces === true ? raw : raw.trim();
        if (!value) {
            return true;
        }
        if (!regex.test(value)) {
            return failField(field, message);
        }
        return true;
    }

    function validateEducationRows(form) {
        if (!form) { return true; }
        var instRegex = /^[\p{L}\p{M}0-9 .,'\-]{2,100}$/u;
        var degRegex = /^[\p{L}\p{M}0-9 .,'\-]{2,50}$/u;
        var rows = form.querySelectorAll('[data-employee-repeatable="educationItems"] [data-repeat-row]');
        for (var i = 0; i < rows.length; i += 1) {
            var row = rows[i];
            var instInput = row.querySelector('[data-repeat-key="institution"]');
            var degInput = row.querySelector('[data-repeat-key="degree"]');
            var syInput = row.querySelector('[data-repeat-key="startYear"]');
            var eyInput = row.querySelector('[data-repeat-key="endYear"]');
            var inst = String(instInput && instInput.value ? instInput.value : "").trim();
            var deg = String(degInput && degInput.value ? degInput.value : "").trim();
            var sy = syInput ? parseInt(syInput.value, 10) : 0;
            var ey = eyInput ? parseInt(eyInput.value, 10) : 0;
            var empty = inst === "" && deg === "";
            if (empty) { continue; }
            if (!inst || !instRegex.test(inst)) {
                if (instInput) { instInput.focus(); }
                showValidationToast("Nama institusi pendidikan wajib huruf/angka/spasi/tanda baca (2-100 karakter) dan mengandung setidaknya satu huruf.");
                return false;
            }
            if (!ALPHA_REQUIRED_REGEX.test(inst)) {
                if (instInput) { instInput.focus(); }
                showValidationToast("Nama institusi pendidikan harus mengandung setidaknya satu huruf (bukan angka semua).");
                return false;
            }
            if (!deg || !degRegex.test(deg)) {
                if (degInput) { degInput.focus(); }
                showValidationToast("Degree/jenjang pendidikan wajib huruf/angka/spasi/tanda baca (2-50 karakter).");
                return false;
            }
            if (!ALPHA_REQUIRED_REGEX.test(deg)) {
                if (degInput) { degInput.focus(); }
                showValidationToast("Degree/jenjang pendidikan harus mengandung setidaknya satu huruf (bukan angka semua).");
                return false;
            }
            if (sy && ey && ey < sy) {
                showValidationToast("End year pendidikan tidak boleh lebih kecil dari start year.");
                return false;
            }
        }
        return true;
    }

    function validateExperienceRows(form) {
        if (!form) { return true; }
        var compRegex = /^[\p{L}\p{M}0-9 .,'\-]{2,100}$/u;
        var posRegex = /^[\p{L}\p{M}0-9 .,'\-]{2,100}$/u;
        var rows = form.querySelectorAll('[data-employee-repeatable="experienceItems"] [data-repeat-row]');
        for (var i = 0; i < rows.length; i += 1) {
            var row = rows[i];
            var compInput = row.querySelector('[data-repeat-key="company"]');
            var posInput = row.querySelector('[data-repeat-key="position"]');
            var sdInput = row.querySelector('[data-repeat-key="startDate"]');
            var edInput = row.querySelector('[data-repeat-key="endDate"]');
            var comp = String(compInput && compInput.value ? compInput.value : "").trim();
            var pos = String(posInput && posInput.value ? posInput.value : "").trim();
            var sd = sdInput && sdInput.value ? String(sdInput.value).trim() : "";
            var ed = edInput && edInput.value ? String(edInput.value).trim() : "";
            var empty = comp === "" && pos === "";
            if (empty) { continue; }
            if (!comp || !compRegex.test(comp)) {
                if (compInput) { compInput.focus(); }
                showValidationToast("Nama perusahaan wajib huruf/angka/spasi/tanda baca (2-100 karakter).");
                return false;
            }
            if (!ALPHA_REQUIRED_REGEX.test(comp)) {
                if (compInput) { compInput.focus(); }
                showValidationToast("Nama perusahaan harus mengandung setidaknya satu huruf.");
                return false;
            }
            if (!pos || !posRegex.test(pos)) {
                if (posInput) { posInput.focus(); }
                showValidationToast("Posisi/jabatan pengalaman kerja wajib huruf/angka/spasi/tanda baca (2-100 karakter).");
                return false;
            }
            if (!ALPHA_REQUIRED_REGEX.test(pos)) {
                if (posInput) { posInput.focus(); }
                showValidationToast("Posisi/jabatan harus mengandung setidaknya satu huruf.");
                return false;
            }
            if (sd && ed && ed < sd) {
                showValidationToast("End date pengalaman kerja tidak boleh lebih awal dari start date.");
                return false;
            }
        }
        return true;
    }

    function validateEmergencyContactRows(form) {
        if (!form) {
            return true;
        }
        var rows = form.querySelectorAll('[data-employee-repeatable="emergencyContacts"] [data-repeat-row]');
        var hasValid = false;

        for (var i = 0; i < rows.length; i += 1) {
            var row = rows[i];
            var nameInput = row.querySelector('[data-repeat-key="name"]');
            var relationshipInput = row.querySelector('[data-repeat-key="relationship"]');
            var phoneInput = row.querySelector('[data-repeat-key="phone"]');

            var name = String(nameInput && nameInput.value ? nameInput.value : "").trim();
            var relationship = String(relationshipInput && relationshipInput.value ? relationshipInput.value : "").trim();
            var phone = String(phoneInput && phoneInput.value ? phoneInput.value : "").trim();

            var isCompletelyEmpty = name === "" && relationship === "" && phone === "";
            if (isCompletelyEmpty) {
                continue;
            }

            if (!name || !EMERGENCY_NAME_REGEX.test(name)) {
                return failField(nameInput, "Nama emergency contact wajib huruf/spasi/tanda baca umum (2-100 karakter).");
            }
            if (!relationship || !EMERGENCY_RELATIONSHIP_REGEX.test(relationship)) {
                return failField(relationshipInput, "Relationship emergency contact wajib huruf/spasi/tanda baca umum (2-50 karakter).");
            }
            if (!PHONE_WITH_COUNTRY_REGEX.test(phone)) {
                return failField(phoneInput, "Nomor telepon emergency contact wajib 10-15 digit, boleh diawali +.");
            }

            hasValid = true;
        }

        if (!hasValid) {
            showValidationToast("Minimal satu emergency contact dengan nama, hubungan, dan nomor telepon valid wajib diisi.");
            return false;
        }

        return true;
    }

    function maybeShowEmployeeLimitPopup(payload, fallbackMessage) {
        var error = payload && payload.error ? payload.error : null;
        var code = String((error && error.code) || "").trim();
        if (code !== "EMPLOYEE_COUNT_EXCEEDED") {
            return false;
        }
        var message = String((error && error.message) || fallbackMessage || "Plan employee limit reached.").trim();
        if (window.ArcavUi && typeof window.ArcavUi.showInfo === "function") {
            window.ArcavUi.showInfo("Kapasitas Karyawan Penuh", message);
        }
        return true;
    }

    function snakeToCamel(value) {
        return String(value || "")
            .replace(/\[(\d+)\]/g, ".$1")
            .replace(/[_-]([a-zA-Z0-9])/g, function (_, ch) {
                return String(ch || "").toUpperCase();
            });
    }

    function clearBackendValidationState(form) {
        if (!form) {
            return;
        }
        var fields = form.querySelectorAll("input, select, textarea");
        Array.prototype.forEach.call(fields, function (field) {
            if (field && typeof field.setCustomValidity === "function" && field.getAttribute("data-employee-backend-error") === "1") {
                field.setCustomValidity("");
                field.removeAttribute("data-employee-backend-error");
            }
        });
    }

    function findFieldByValidationKey(form, rawFieldKey) {
        if (!form) {
            return null;
        }

        var key = String(rawFieldKey || "").trim();
        if (!key) {
            return null;
        }

        var base = key.split(".")[0] || key;
        var aliases = {
            ktpNo: "nik",
            ktp_no: "nik",
            confirm_password: "confirmPassword",
        };

        var candidates = [
            key,
            base,
            snakeToCamel(key),
            snakeToCamel(base),
            aliases[key],
            aliases[base],
        ].filter(function (item, index, array) {
            return !!item && array.indexOf(item) === index;
        });

        for (var i = 0; i < candidates.length; i += 1) {
            var candidate = String(candidates[i]);
            var field = form.querySelector('[data-employee-add-field="' + candidate + '"]')
                || form.querySelector('[data-employee-edit-field="' + candidate + '"]');
            if (field) {
                return field;
            }
        }

        return null;
    }

    function extractValidationErrors(payload) {
        var output = [];
        if (!payload || typeof payload !== "object") {
            return output;
        }

        var errorsObj = payload.errors;
        if (errorsObj && typeof errorsObj === "object") {
            Object.keys(errorsObj).forEach(function (field) {
                var value = errorsObj[field];
                var message = Array.isArray(value) ? String(value[0] || "").trim() : String(value || "").trim();
                if (field && message) {
                    output.push({ field: field, message: message });
                }
            });
        }

        var detailList = payload.error && Array.isArray(payload.error.details) ? payload.error.details : [];
        detailList.forEach(function (detail) {
            var field = String((detail && detail.field) || "").trim();
            var message = String((detail && detail.message) || "").trim();
            if (!field || !message) {
                return;
            }
            var exists = output.some(function (item) {
                return item.field === field;
            });
            if (!exists) {
                output.push({ field: field, message: message });
            }
        });

        return output;
    }

    function applyBackendValidationErrors(form, payload) {
        var code = String(payload && payload.error && payload.error.code ? payload.error.code : "").trim();
        if (code !== "VALIDATION_ERROR") {
            return false;
        }

        var details = extractValidationErrors(payload);
        if (!details.length) {
            return false;
        }

        clearBackendValidationState(form);

        var firstField = null;
        var fallbackMessages = [];

        details.forEach(function (item) {
            var fieldEl = findFieldByValidationKey(form, item.field);
            if (!fieldEl) {
                fallbackMessages.push(item.message);
                return;
            }

            if (typeof fieldEl.setCustomValidity === "function") {
                fieldEl.setCustomValidity(item.message);
                fieldEl.setAttribute("data-employee-backend-error", "1");
            }

            if (!firstField) {
                firstField = fieldEl;
            }
        });

        if (firstField) {
            var pane = firstField.closest("[data-employee-step-pane]");
            if (pane) {
                var paneIndex = parseInt(String(pane.getAttribute("data-employee-step-pane") || "0"), 10);
                if (!isNaN(paneIndex)) {
                    setStep(form, paneIndex);
                }
            }
            if (typeof firstField.reportValidity === "function") {
                firstField.reportValidity();
            }
            if (typeof firstField.focus === "function") {
                firstField.focus();
            }
        }

        if (fallbackMessages.length && window.ArcavUi && window.ArcavUi.showToast) {
            var fallbackSummary = fallbackMessages.length === 1
                ? fallbackMessages[0]
                : fallbackMessages.length + " error dari server: " + fallbackMessages.join("; ");
            window.ArcavUi.showToast(fallbackSummary, "warning");
        }

        return true;
    }

    return {
        applyBackendValidationErrors: applyBackendValidationErrors,
        clearBackendValidationState: clearBackendValidationState,
        enforceInputRules: enforceInputRules,
        failField: failField,
        maybeShowEmployeeLimitPopup: maybeShowEmployeeLimitPopup,
        showValidationToast: showValidationToast,
        validateEducationRows: validateEducationRows,
        validateEmergencyContactRows: validateEmergencyContactRows,
        validateExperienceRows: validateExperienceRows,
        validateRegexField: validateRegexField,
    };
}
