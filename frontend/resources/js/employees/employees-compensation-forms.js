export function bindEmployeeCompensationFormsModule(deps) {
    var PASSWORD_RULE_REGEX = /^(?=.*[A-Z])(?=.*[a-z])(?=.*\d)[A-Za-z\d@$!%*?&._-]{8,64}$/;
    var requestJson = deps.requestJson;
    var requestEmployeeDetail = deps.requestEmployeeDetail;
    var fillDesignationSelectForDepartment = deps.fillDesignationSelectForDepartment;
    var loadTeamsDropdown = deps.loadTeamsDropdown;
    var formatApiError = deps.formatApiError;
    var loadEmployeesData = deps.loadEmployeesData;

    var addForm = document.querySelector("[data-employee-add-form]");
    var editForm = document.querySelector("[data-employee-edit-form]");

    function getWilayahElements(form) {
        if (!form) {
            return null;
        }
        var province = form.querySelector("[data-employee-wilayah-province]");
        var regency = form.querySelector("[data-employee-wilayah-regency]");
        var district = form.querySelector("[data-employee-wilayah-district]");
        var village = form.querySelector("[data-employee-wilayah-village]");
        var address = form.querySelector("[data-employee-address-autofill]");
        if (!province || !regency || !district || !village || !address) {
            return null;
        }
        return {
            province: province,
            regency: regency,
            district: district,
            village: village,
            address: address,
        };
    }

    function setSelectOptions(selectEl, rows, placeholder, selectedValue) {
        if (!selectEl) {
            return;
        }
        var selected = selectedValue == null ? "" : String(selectedValue);
        selectEl.innerHTML = "";
        var first = document.createElement("option");
        first.value = "";
        first.textContent = placeholder;
        selectEl.appendChild(first);

        (Array.isArray(rows) ? rows : []).forEach(function (row) {
            var opt = document.createElement("option");
            opt.value = String(row && row.id != null ? row.id : "");
            opt.textContent = String(row && row.name ? row.name : "");
            selectEl.appendChild(opt);
        });

        if (selected) {
            var exists = Array.prototype.slice.call(selectEl.options).some(function (optEl) {
                return optEl.value === selected;
            });
            if (exists) {
                selectEl.value = selected;
            }
        }
    }

    function setSelectLoading(selectEl, placeholder) {
        if (!selectEl) {
            return;
        }
        selectEl.disabled = true;
        selectEl.innerHTML = '<option value="">' + placeholder + "</option>";
    }

    function composeAddressLabel(form) {
        var wilayah = getWilayahElements(form);
        if (!wilayah) {
            return "";
        }
        var villageLabel = wilayah.village.options[wilayah.village.selectedIndex] && wilayah.village.value
            ? wilayah.village.options[wilayah.village.selectedIndex].textContent
            : "";
        var districtLabel = wilayah.district.options[wilayah.district.selectedIndex] && wilayah.district.value
            ? wilayah.district.options[wilayah.district.selectedIndex].textContent
            : "";
        var regencyLabel = wilayah.regency.options[wilayah.regency.selectedIndex] && wilayah.regency.value
            ? wilayah.regency.options[wilayah.regency.selectedIndex].textContent
            : "";
        var provinceLabel = wilayah.province.options[wilayah.province.selectedIndex] && wilayah.province.value
            ? wilayah.province.options[wilayah.province.selectedIndex].textContent
            : "";

        return [villageLabel, districtLabel, regencyLabel, provinceLabel]
            .filter(function (item) {
                return !!item;
            })
            .join(", ");
    }

    function syncAddressAutofill(form) {
        var wilayah = getWilayahElements(form);
        if (!wilayah) {
            return;
        }
        var composed = composeAddressLabel(form);
        var current = String(wilayah.address.value || "").trim();
        var previousAuto = String(form.getAttribute("data-employee-address-auto") || "").trim();
        if (current === "" || current === previousAuto) {
            wilayah.address.value = composed || "";
        }
        form.setAttribute("data-employee-address-auto", composed || "");
    }

    function resetWilayahSelect(selectEl, placeholder) {
        if (!selectEl) {
            return;
        }
        selectEl.disabled = true;
        selectEl.innerHTML = '<option value="">' + placeholder + "</option>";
    }

    function fetchWilayah(url) {
        return requestJson("get", url, null)
            .then(function (resp) {
                if (!resp || resp.success !== true || !Array.isArray(resp.data)) {
                    return [];
                }
                return resp.data;
            })
            .catch(function () {
                return [];
            });
    }

    function loadProvinces(form, selectedProvinceId) {
        var wilayah = getWilayahElements(form);
        if (!wilayah) {
            return Promise.resolve();
        }
        setSelectLoading(wilayah.province, "Loading provinces...");
        return fetchWilayah("/v1/hcm/wilayah/provinces").then(function (rows) {
            setSelectOptions(wilayah.province, rows, "Select province", selectedProvinceId || "");
            wilayah.province.disabled = false;
            syncAddressAutofill(form);
        });
    }

    function loadRegencies(form, provinceId, selectedRegencyId) {
        var wilayah = getWilayahElements(form);
        if (!wilayah) {
            return Promise.resolve();
        }
        resetWilayahSelect(wilayah.regency, "Select regency");
        resetWilayahSelect(wilayah.district, "Select district");
        resetWilayahSelect(wilayah.village, "Select village");
        syncAddressAutofill(form);
        if (!provinceId) {
            return Promise.resolve();
        }

        setSelectLoading(wilayah.regency, "Loading regencies...");
        return fetchWilayah("/v1/hcm/wilayah/regencies?provinceId=" + encodeURIComponent(String(provinceId))).then(function (rows) {
            setSelectOptions(wilayah.regency, rows, "Select regency", selectedRegencyId || "");
            wilayah.regency.disabled = false;
            syncAddressAutofill(form);
        });
    }

    function loadDistricts(form, regencyId, selectedDistrictId) {
        var wilayah = getWilayahElements(form);
        if (!wilayah) {
            return Promise.resolve();
        }
        resetWilayahSelect(wilayah.district, "Select district");
        resetWilayahSelect(wilayah.village, "Select village");
        syncAddressAutofill(form);
        if (!regencyId) {
            return Promise.resolve();
        }

        setSelectLoading(wilayah.district, "Loading districts...");
        return fetchWilayah("/v1/hcm/wilayah/districts?regencyId=" + encodeURIComponent(String(regencyId))).then(function (rows) {
            setSelectOptions(wilayah.district, rows, "Select district", selectedDistrictId || "");
            wilayah.district.disabled = false;
            syncAddressAutofill(form);
        });
    }

    function loadVillages(form, districtId, selectedVillageId) {
        var wilayah = getWilayahElements(form);
        if (!wilayah) {
            return Promise.resolve();
        }
        resetWilayahSelect(wilayah.village, "Select village");
        syncAddressAutofill(form);
        if (!districtId) {
            return Promise.resolve();
        }

        setSelectLoading(wilayah.village, "Loading villages...");
        return fetchWilayah("/v1/hcm/wilayah/villages?districtId=" + encodeURIComponent(String(districtId))).then(function (rows) {
            setSelectOptions(wilayah.village, rows, "Select village", selectedVillageId || "");
            wilayah.village.disabled = false;
            syncAddressAutofill(form);
        });
    }

    function resetWilayahCascade(form) {
        var wilayah = getWilayahElements(form);
        if (!wilayah) {
            return;
        }
        resetWilayahSelect(wilayah.province, "Select province");
        resetWilayahSelect(wilayah.regency, "Select regency");
        resetWilayahSelect(wilayah.district, "Select district");
        resetWilayahSelect(wilayah.village, "Select village");
        wilayah.address.value = "";
        form.setAttribute("data-employee-address-auto", "");
        loadProvinces(form, "").then(function () {
            resetWilayahSelect(wilayah.regency, "Select regency");
            resetWilayahSelect(wilayah.district, "Select district");
            resetWilayahSelect(wilayah.village, "Select village");
            syncAddressAutofill(form);
        });
    }

    function setWilayahCascade(form, region, fallbackAddress) {
        var wilayah = getWilayahElements(form);
        if (!wilayah) {
            return Promise.resolve();
        }
        var provinceId = region && region.provinceId != null ? String(region.provinceId) : "";
        var regencyId = region && region.regencyId != null ? String(region.regencyId) : "";
        var districtId = region && region.districtId != null ? String(region.districtId) : "";
        var villageId = region && region.villageId != null ? String(region.villageId) : "";

        return loadProvinces(form, provinceId)
            .then(function () {
                return loadRegencies(form, provinceId, regencyId);
            })
            .then(function () {
                return loadDistricts(form, regencyId, districtId);
            })
            .then(function () {
                return loadVillages(form, districtId, villageId);
            })
            .then(function () {
                var composed = composeAddressLabel(form);
                var fallback = String(fallbackAddress || "").trim();
                wilayah.address.value = fallback || composed || "";
                form.setAttribute("data-employee-address-auto", composed || "");
            });
    }

    function bindWilayahChangeHandlers(form) {
        var wilayah = getWilayahElements(form);
        if (!wilayah || form.getAttribute("data-employee-wilayah-bound") === "1") {
            return;
        }
        form.setAttribute("data-employee-wilayah-bound", "1");

        wilayah.province.addEventListener("change", function () {
            var provinceId = wilayah.province.value || "";
            loadRegencies(form, provinceId, "");
        });

        wilayah.regency.addEventListener("change", function () {
            var regencyId = wilayah.regency.value || "";
            loadDistricts(form, regencyId, "");
        });

        wilayah.district.addEventListener("change", function () {
            var districtId = wilayah.district.value || "";
            loadVillages(form, districtId, "");
        });

        wilayah.village.addEventListener("change", function () {
            syncAddressAutofill(form);
        });
    }

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

    function showValidationToast(message) {
        if (window.ArcavUi && window.ArcavUi.showToast) {
            window.ArcavUi.showToast(message, "warning");
        }
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

        // Emergency contacts — step 4 (Background/final step) only.
        if (stepIndex === 4) {
            var contacts = collectRepeatable(form, "emergencyContacts");
            var hasValidEmergencyContact = contacts.some(function (item) {
                return item && item.name && item.relationship && /^[0-9]{10,13}$/.test(String(item.phone || ""));
            });
            if (!hasValidEmergencyContact) {
                showValidationToast("Minimal satu emergency contact dengan nama, hubungan, dan nomor telepon valid wajib diisi.");
                return false;
            }
        }

        return true;
    }

    function addRepeatableRow(form, type, values) {
        var container = form ? form.querySelector('[data-employee-repeatable="' + type + '"]') : null;
        var template = form ? form.querySelector('[data-employee-repeatable-template="' + type + '"]') : null;
        if (!container || !template || !template.content) {
            return;
        }
        var fragment = template.content.cloneNode(true);
        var row = fragment.querySelector("[data-repeat-row]");
        if (!row) {
            return;
        }
        Array.prototype.forEach.call(row.querySelectorAll("[data-repeat-key]"), function (input) {
            var key = input.getAttribute("data-repeat-key");
            var nextValue = values && values[key] != null ? values[key] : "";
            input.value = nextValue;
        });
        container.appendChild(fragment);
    }

    function resetRepeatable(form, type, items) {
        var container = form ? form.querySelector('[data-employee-repeatable="' + type + '"]') : null;
        if (!container) {
            return;
        }
        container.innerHTML = "";
        var list = Array.isArray(items) && items.length ? items : [{}];
        list.forEach(function (item) {
            addRepeatableRow(form, type, item || {});
        });
    }

    function collectRepeatable(form, type) {
        var rows = form ? form.querySelectorAll('[data-employee-repeatable="' + type + '"] [data-repeat-row]') : [];
        var output = [];
        Array.prototype.forEach.call(rows, function (row) {
            var item = {};
            var hasValue = false;
            Array.prototype.forEach.call(row.querySelectorAll("[data-repeat-key]"), function (input) {
                var key = input.getAttribute("data-repeat-key");
                var value = String(input.value || "").trim();
                if (value !== "") {
                    hasValue = true;
                }
                if (key === "startYear" || key === "endYear") {
                    item[key] = value === "" ? null : parseInt(value, 10);
                } else {
                    item[key] = value === "" ? null : value;
                }
            });
            if (hasValue) {
                output.push(item);
            }
        });
        return output;
    }

    function resetFormState(form) {
        if (!form) {
            return;
        }
        clearBackendValidationState(form);
        form.reset();
        form.removeAttribute("data-employee-id");
        form.removeAttribute("data-employee-edit-org-snapshot-dept");
        form.removeAttribute("data-employee-edit-org-snapshot-des");
        writeField(form, "nationality", "Indonesia");
        updateModalEmployeeUuid(form, null);
        resetRepeatable(form, "emergencyContacts", []);
        resetRepeatable(form, "educationItems", []);
        resetRepeatable(form, "experienceItems", []);
        toggleContractEndDateVisibility(form);
        resetWilayahCascade(form);
        var teamEl = form.querySelector("[data-employee-org-team]");
        if (teamEl) {
            loadTeamsDropdown(teamEl, "");
        }
        form.setAttribute("data-employee-contract-start-auto", "");
        ensureEmployeeContractTypeConsistency(form, { autoCorrect: true, notify: false });
        maybeAutoSyncContractStartDate(form);
        setStep(form, 0);
    }

    function buildPayload(form, isEdit) {
        var taxStatusValue = readText(form, "taxStatus");
        var ptkpStatusValue = readText(form, "ptkpStatus") || taxStatusValue;
        var contractType = normalizeContractTypeValue(readText(form, "contractType"));
        var payload = {
            name: readField(form, "name"),
            email: readField(form, "email"),
            phone: readText(form, "phone"),
            nik: readText(form, "nik"),
            address: readText(form, "address"),
            addressDetail: readText(form, "addressDetail"),
            provinceId: readInteger(form, "provinceId"),
            regencyId: readInteger(form, "regencyId"),
            districtId: readInteger(form, "districtId"),
            villageId: readInteger(form, "villageId"),
            placeOfBirth: readText(form, "placeOfBirth"),
            dateOfBirth: readText(form, "dateOfBirth"),
            gender: readText(form, "gender"),
            maritalStatus: readText(form, "maritalStatus"),
            religion: readText(form, "religion"),
            nationality: "Indonesia",
            bio: readText(form, "bio"),
            employmentStatus: readText(form, "employmentStatus") || "active",
            employeeType: readText(form, "employeeType"),
            startDate: readText(form, "startDate"),
            probationEndDate: readText(form, "probationEndDate"),
            baseSalary: readNumberOrNull(readField(form, "baseSalary")),
            contractType: contractType,
            contractStatus: readText(form, "contractStatus") || "active",
            contractStartDate: readText(form, "contractStartDate"),
            contractEndDate: contractType === "contract" ? readText(form, "contractEndDate") : null,
            bankName: readText(form, "bankName"),
            bankAccountNo: readText(form, "bankAccountNo"),
            bankAccountHolderName: readText(form, "bankAccountHolderName"),
            bankIfscCode: readText(form, "bankIfscCode"),
            bankBranch: readText(form, "bankBranch"),
            npwp: readText(form, "npwp"),
            taxStatus: taxStatusValue,
            ptkpStatus: ptkpStatusValue,
            bpjsKesehatanNo: readText(form, "bpjsKesehatanNo"),
            bpjsKetenagakerjaanNo: readText(form, "bpjsKetenagakerjaanNo"),
            emergencyContacts: collectRepeatable(form, "emergencyContacts"),
            educationItems: collectRepeatable(form, "educationItems"),
            experienceItems: collectRepeatable(form, "experienceItems"),
        };

        var departmentId = readInteger(form, "departmentId");
        var designationId = readInteger(form, "designationId");
        var teamId = readInteger(form, "teamId");
        payload.departmentId = departmentId;
        payload.designationId = designationId;
        payload.teamId = teamId;

        var teamSelectEl = form.querySelector("[data-employee-org-team]");
        var inactiveTeamPref = teamSelectEl ? String(teamSelectEl.getAttribute("data-inactive-team-pref") || "") : "";
        var selectedTeamRaw = teamSelectEl ? String(teamSelectEl.value || "") : "";
        if (isEdit && inactiveTeamPref && selectedTeamRaw === "") {
            delete payload.teamId;
        }

        if (!isEdit) {
            payload.password = readField(form, "password");
            payload.confirmPassword = readField(form, "confirmPassword");
            payload.data_disclosure_acknowledged = readChecked(form, "dataDisclosureAcknowledged");
        }

        return payload;
    }

    function hydrateEditForm(item) {
        if (!editForm || !item) {
            return;
        }
        clearBackendValidationState(editForm);

        writeField(editForm, "name", item.fullName || "");
        writeField(editForm, "email", item.email || "");
        writeField(editForm, "phone", item.phone && item.phone !== "-" ? item.phone : "");
        writeField(editForm, "nik", item.nik || (item.personal && item.personal.nik ? item.personal.nik : ""));
        writeField(editForm, "addressDetail", item.addressDetail && item.addressDetail !== "-" ? item.addressDetail : "");
        writeField(editForm, "placeOfBirth", item.placeOfBirth || (item.personal && item.personal.placeOfBirth ? item.personal.placeOfBirth : ""));
        writeField(editForm, "dateOfBirth", item.dateOfBirth || (item.personal && item.personal.dateOfBirth ? item.personal.dateOfBirth : ""));
        writeField(editForm, "gender", item.gender || (item.personal && item.personal.gender ? item.personal.gender : ""));
        writeField(editForm, "maritalStatus", item.maritalStatus || (item.personal && item.personal.maritalStatus ? item.personal.maritalStatus : ""));
        writeField(editForm, "religion", item.religion || (item.personal && item.personal.religion ? item.personal.religion : ""));
        writeField(editForm, "nationality", item.nationality || (item.personal && item.personal.nationality ? item.personal.nationality : "Indonesia"));
        writeField(editForm, "bio", item.bio && item.bio !== "-" ? item.bio : "");
        writeField(editForm, "employmentStatus", item.employmentStatus || "active");
        writeField(editForm, "employeeType", item.employeeType || "");
        writeField(editForm, "startDate", item.startDate || item.joinDate || "");
        writeField(
            editForm,
            "probationEndDate",
            item.employmentHistory && item.employmentHistory[0] && item.employmentHistory[0].probationEndDate
                ? item.employmentHistory[0].probationEndDate
                : ""
        );
        writeField(editForm, "baseSalary", item.baseSalary != null ? String(Math.round(Number(item.baseSalary || 0))) : "");
        writeField(editForm, "contractType", normalizeContractTypeValue(item.contract && item.contract.contractType ? item.contract.contractType : item.contractType || "permanent"));
        writeField(editForm, "contractStatus", item.contract && item.contract.status ? item.contract.status : "active");
        writeField(editForm, "contractStartDate", item.contract && item.contract.startDate ? item.contract.startDate : item.contractStartDate || "");
        writeField(editForm, "contractEndDate", item.contract && item.contract.endDate ? item.contract.endDate : item.contractEndDate || "");
        toggleContractEndDateVisibility(editForm);
        writeField(editForm, "bankName", item.bank && item.bank.name && item.bank.name !== "-" ? item.bank.name : "");
        writeField(editForm, "bankAccountNo", item.bank && item.bank.accountNo && item.bank.accountNo !== "-" ? item.bank.accountNo : "");
        writeField(editForm, "bankAccountHolderName", item.bank && item.bank.accountHolderName && item.bank.accountHolderName !== "-" ? item.bank.accountHolderName : "");
        writeField(editForm, "bankIfscCode", item.bank && item.bank.ifscCode && item.bank.ifscCode !== "-" ? item.bank.ifscCode : "");
        writeField(editForm, "bankBranch", item.bank && item.bank.branch && item.bank.branch !== "-" ? item.bank.branch : "");
        writeField(editForm, "npwp", item.taxProfile && item.taxProfile.npwp ? item.taxProfile.npwp : "");
        writeField(editForm, "taxStatus", item.taxProfile && item.taxProfile.taxStatus ? item.taxProfile.taxStatus : "");
        writeField(editForm, "ptkpStatus", item.taxProfile && item.taxProfile.ptkpStatus ? item.taxProfile.ptkpStatus : "");
        writeField(editForm, "bpjsKesehatanNo", item.benefits && item.benefits.bpjsKesehatanNo ? item.benefits.bpjsKesehatanNo : "");
        writeField(editForm, "bpjsKetenagakerjaanNo", item.benefits && item.benefits.bpjsKetenagakerjaanNo ? item.benefits.bpjsKetenagakerjaanNo : "");

        writeField(editForm, "departmentId", item.departmentId != null && item.departmentId !== "" ? String(item.departmentId) : "");
        var depEl = editForm.querySelector("[data-employee-org-department]");
        var desEl = editForm.querySelector("[data-employee-org-designation]");
        var teamEl = editForm.querySelector("[data-employee-org-team]");
        if (depEl && desEl) {
            fillDesignationSelectForDepartment(desEl, depEl.value, item.designationId != null && item.designationId !== "" ? String(item.designationId) : "");
        } else {
            writeField(editForm, "designationId", item.designationId != null && item.designationId !== "" ? String(item.designationId) : "");
        }
        if (teamEl) {
            loadTeamsDropdown(teamEl, item.teamId != null && item.teamId !== "" ? String(item.teamId) : "");
        }

        editForm.setAttribute("data-employee-edit-org-snapshot-dept", item.departmentId != null && item.departmentId !== "" ? String(item.departmentId) : "");
        editForm.setAttribute("data-employee-edit-org-snapshot-des", item.designationId != null && item.designationId !== "" ? String(item.designationId) : "");

        resetRepeatable(editForm, "emergencyContacts", Array.isArray(item.emergencyContacts) ? item.emergencyContacts : []);
        resetRepeatable(editForm, "educationItems", Array.isArray(item.educationItems) ? item.educationItems : []);
        resetRepeatable(editForm, "experienceItems", Array.isArray(item.experienceItems) ? item.experienceItems : []);
        setWilayahCascade(editForm, item.addressRegion || null, item.address && item.address !== "-" ? item.address : "");
        updateModalEmployeeUuid(editForm, item);
        editForm.setAttribute("data-employee-contract-start-auto", String(readField(editForm, "contractStartDate") || ""));
        ensureEmployeeContractTypeConsistency(editForm, { autoCorrect: true, notify: false });
        maybeAutoSyncContractStartDate(editForm);
        setStep(editForm, 0);
    }

    [addForm, editForm].forEach(function (form) {
        if (!form || form.getAttribute("data-employee-step-bound") === "1") {
            return;
        }
        form.setAttribute("data-employee-step-bound", "1");
        resetRepeatable(form, "emergencyContacts", []);
        resetRepeatable(form, "educationItems", []);
        resetRepeatable(form, "experienceItems", []);
        bindWilayahChangeHandlers(form);
        form.setAttribute("data-employee-contract-start-auto", "");
        ensureEmployeeContractTypeConsistency(form, { autoCorrect: true, notify: false });
        maybeAutoSyncContractStartDate(form);
        setStep(form, 0);

        // Clear backend error state on both input (per-keystroke) and change (on blur/select).
        // Using input for text fields clears the red indicator immediately as user types;
        // change is kept for select/date fields that don't fire input.
        function handleFieldInteraction(event) {
            var changedField = event.target && event.target.closest ? event.target.closest("input, select, textarea") : null;
            if (changedField && changedField.getAttribute("data-employee-backend-error") === "1" && typeof changedField.setCustomValidity === "function") {
                changedField.setCustomValidity("");
                changedField.removeAttribute("data-employee-backend-error");
            }
        }
        form.addEventListener("input", handleFieldInteraction);

        form.addEventListener("change", function (event) {
            handleFieldInteraction(event);

            var employeeTypeInput = event.target && event.target.closest ? event.target.closest('[data-employee-add-field="employeeType"], [data-employee-edit-field="employeeType"]') : null;
            if (employeeTypeInput) {
                ensureEmployeeContractTypeConsistency(form, { autoCorrect: true, notify: false });
            }

            var startDateInput = event.target && event.target.closest ? event.target.closest('[data-employee-add-field="startDate"], [data-employee-edit-field="startDate"]') : null;
            if (startDateInput) {
                maybeAutoSyncContractStartDate(form);
            }

            var contractTypeInput = event.target && event.target.closest ? event.target.closest('[data-employee-add-field="contractType"], [data-employee-edit-field="contractType"]') : null;
            if (contractTypeInput) {
                toggleContractEndDateVisibility(form);
                ensureEmployeeContractTypeConsistency(form, { autoCorrect: true, notify: true });
            }
        });

        form.addEventListener("click", function (event) {
            var nextBtn = event.target.closest("[data-employee-step-next]");
            if (nextBtn) {
                event.preventDefault();
                clearBackendValidationState(form);
                if (!validateCurrentStep(form)) {
                    return;
                }
                setStep(form, Number(form.getAttribute("data-employee-step-index") || 0) + 1);
                return;
            }

            var prevBtn = event.target.closest("[data-employee-step-prev]");
            if (prevBtn) {
                event.preventDefault();
                setStep(form, Number(form.getAttribute("data-employee-step-index") || 0) - 1);
                return;
            }

            var trigger = event.target.closest("[data-employee-step-trigger]");
            if (trigger) {
                event.preventDefault();
                var targetIndex = parseInt(trigger.getAttribute("data-employee-step-trigger"), 10) || 0;
                var currentIndex = Number(form.getAttribute("data-employee-step-index") || 0);
                if (targetIndex > currentIndex) {
                    clearBackendValidationState(form);
                    if (!validateCurrentStep(form)) {
                        return;
                    }
                }
                setStep(form, targetIndex);
                return;
            }

            var addRepeat = event.target.closest("[data-employee-repeat-add]");
            if (addRepeat) {
                event.preventDefault();
                addRepeatableRow(form, addRepeat.getAttribute("data-employee-repeat-add"), {});
                return;
            }

            var removeRepeat = event.target.closest("[data-employee-repeat-remove]");
            if (removeRepeat) {
                event.preventDefault();
                var row = removeRepeat.closest("[data-repeat-row]");
                var parent = row ? row.parentNode : null;
                if (row && row.parentNode) {
                    row.parentNode.removeChild(row);
                }
                if (parent && !parent.querySelector("[data-repeat-row]")) {
                    addRepeatableRow(form, parent.getAttribute("data-employee-repeatable"), {});
                }
            }
        });
    });

    var addModalEl = document.getElementById("add_employee");
    if (addModalEl && addForm && addModalEl.getAttribute("data-employee-modal-bound") !== "1") {
        addModalEl.setAttribute("data-employee-modal-bound", "1");
        addModalEl.addEventListener("show.bs.modal", function () {
            resetFormState(addForm);
        });
    }

    if (addForm) {
        resetWilayahCascade(addForm);
    }

    if (editForm) {
        resetWilayahCascade(editForm);
    }

    if (addForm) {
        addForm.addEventListener("submit", function (event) {
            event.preventDefault();
            clearBackendValidationState(addForm);
            if (!validateCurrentStep(addForm)) {
                return;
            }
            var submitBtn = addForm.querySelector("[data-employee-step-submit]");
            var originalLabel = submitBtn ? (submitBtn.getAttribute("data-original-label") || submitBtn.textContent) : "";
            if (submitBtn) {
                submitBtn.setAttribute("data-original-label", originalLabel);
                submitBtn.disabled = true;
                submitBtn.textContent = "Menyimpan...";
            }
            var payload = buildPayload(addForm, false);
            requestJson("post", "/v1/hcm/employees", payload)
                .then(function (resp) {
                    if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = originalLabel; }
                    if (!resp || resp.success !== true) {
                        if (applyBackendValidationErrors(addForm, resp)) {
                            return;
                        }
                        maybeShowEmployeeLimitPopup(resp, formatApiError(resp, 0));
                        window.ArcavUi && window.ArcavUi.showToast && window.ArcavUi.showToast(formatApiError(resp, 0), "danger");
                        return;
                    }
                    if (window.ArcavUi && window.ArcavUi.showToast) {
                        window.ArcavUi.showToast("Employee berhasil ditambahkan.", "success");
                    }
                    resetFormState(addForm);
                    if (window.bootstrap && addModalEl) {
                        window.bootstrap.Modal.getOrCreateInstance(addModalEl).hide();
                    }
                    loadEmployeesData();
                })
                .catch(function (error) {
                    if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = originalLabel; }
                    if (error && window.AuthApi && window.AuthApi.handleUnauthorizedFromApi(error.status, error.data)) {
                        return;
                    }
                    if (applyBackendValidationErrors(addForm, error && error.data)) {
                        return;
                    }
                    maybeShowEmployeeLimitPopup(error && error.data, formatApiError(error && error.data, error && error.status));
                    if (window.ArcavUi && window.ArcavUi.showToast) {
                        window.ArcavUi.showToast(formatApiError(error && error.data, error && error.status), "danger");
                    }
                });
        });
    }

    document.addEventListener("click", function (event) {
        var openEdit = event.target.closest("[data-employee-edit-open]");
        if (!openEdit || !editForm) {
            return;
        }
        event.preventDefault();
        var employeeId = String(openEdit.getAttribute("data-employee-id") || "");
        if (!employeeId) {
            return;
        }
        editForm.setAttribute("data-employee-id", employeeId);
        requestEmployeeDetail(employeeId)
            .then(function (payload) {
                var item = payload && payload.success === true ? payload.data : null;
                if (!item) {
                    throw { status: 0, data: payload };
                }
                hydrateEditForm(item);
                if (window.bootstrap && window.bootstrap.Modal) {
                    var modalEl = document.getElementById("edit_employee");
                    if (modalEl) {
                        window.bootstrap.Modal.getOrCreateInstance(modalEl).show();
                    }
                }
            })
            .catch(function (error) {
                if (window.ArcavUi && window.ArcavUi.showToast) {
                    window.ArcavUi.showToast(formatApiError(error && error.data, error && error.status), "danger");
                }
            });
    });

    if (editForm) {
        editForm.addEventListener("submit", function (event) {
            event.preventDefault();
            clearBackendValidationState(editForm);
            if (!validateCurrentStep(editForm)) {
                return;
            }
            var employeeId = String(editForm.getAttribute("data-employee-id") || "");
            if (!employeeId) {
                if (window.ArcavUi && window.ArcavUi.showToast) {
                    window.ArcavUi.showToast("Pilih employee yang akan diupdate terlebih dahulu.", "warning");
                }
                return;
            }
            var editSubmitBtn = editForm.querySelector("[data-employee-step-submit]");
            var editOriginalLabel = editSubmitBtn ? (editSubmitBtn.getAttribute("data-original-label") || editSubmitBtn.textContent) : "";
            if (editSubmitBtn) {
                editSubmitBtn.setAttribute("data-original-label", editOriginalLabel);
                editSubmitBtn.disabled = true;
                editSubmitBtn.textContent = "Menyimpan...";
            }
            var payload = buildPayload(editForm, true);
            requestJson("put", "/v1/hcm/employees/" + encodeURIComponent(employeeId), payload)
                .then(function (resp) {
                    if (editSubmitBtn) { editSubmitBtn.disabled = false; editSubmitBtn.textContent = editOriginalLabel; }
                    if (!resp || resp.success !== true) {
                        if (applyBackendValidationErrors(editForm, resp)) {
                            return;
                        }
                        window.ArcavUi && window.ArcavUi.showToast && window.ArcavUi.showToast(formatApiError(resp, 0), "danger");
                        return;
                    }
                    if (window.ArcavUi && window.ArcavUi.showToast) {
                        window.ArcavUi.showToast("Data employee berhasil diperbarui.", "success");
                    }
                    if (window.bootstrap) {
                        var modalEl = document.getElementById("edit_employee");
                        if (modalEl) {
                            window.bootstrap.Modal.getOrCreateInstance(modalEl).hide();
                        }
                    }
                    loadEmployeesData();
                })
                .catch(function (error) {
                    if (editSubmitBtn) { editSubmitBtn.disabled = false; editSubmitBtn.textContent = editOriginalLabel; }
                    if (error && window.AuthApi && window.AuthApi.handleUnauthorizedFromApi(error.status, error.data)) {
                        return;
                    }
                    if (applyBackendValidationErrors(editForm, error && error.data)) {
                        return;
                    }
                    if (window.ArcavUi && window.ArcavUi.showToast) {
                        window.ArcavUi.showToast(formatApiError(error && error.data, error && error.status), "danger");
                    }
                });
        });
    }
}
