export function bindEmployeeCompensationFormsModule(deps) {
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

    function showValidationToast(message) {
        if (window.ArcavUi && window.ArcavUi.showToast) {
            window.ArcavUi.showToast(message, "warning");
        }
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

    function validateCurrentStep(form) {
        if (!form) {
            return true;
        }
        toggleContractEndDateVisibility(form);
        var stepIndex = Number(form.getAttribute("data-employee-step-index") || 0);

        var passwordInput = form.querySelector('[data-employee-add-field="password"], [data-employee-edit-field="password"]');
        var confirmPasswordInput = form.querySelector('[data-employee-add-field="confirmPassword"], [data-employee-edit-field="confirmPassword"]');
        if (confirmPasswordInput && typeof confirmPasswordInput.setCustomValidity === "function") {
            confirmPasswordInput.setCustomValidity("");
        }

        var pane = form.querySelector('[data-employee-step-pane="' + stepIndex + '"]');
        if (pane) {
            var fields = pane.querySelectorAll("input, select, textarea");
            for (var i = 0; i < fields.length; i += 1) {
                if (fields[i].disabled) {
                    continue;
                }
                if (typeof fields[i].reportValidity === "function" && !fields[i].reportValidity()) {
                    return false;
                }
            }
        }

        if (stepIndex === 0 && passwordInput && confirmPasswordInput) {
            var passwordValue = String(passwordInput.value || "");
            var confirmPasswordValue = String(confirmPasswordInput.value || "");
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

        var nik = readField(form, "nik");
        if (nik && !/^[0-9]{16}$/.test(nik)) {
            showValidationToast("NIK wajib tepat 16 digit angka.");
            return false;
        }

        var phone = readField(form, "phone");
        if (phone && !/^[0-9]{10,13}$/.test(phone)) {
            showValidationToast("Nomor telepon wajib 10-13 digit angka.");
            return false;
        }

        var nationalityInput = form.querySelector('[data-employee-add-field="nationality"], [data-employee-edit-field="nationality"]');
        if (nationalityInput) {
            nationalityInput.value = "Indonesia";
        }

        var startDate = readField(form, "startDate");
        var probationEndDate = readField(form, "probationEndDate");
        if (startDate && probationEndDate && probationEndDate < startDate) {
            showValidationToast("Probation end date tidak boleh lebih awal dari effective start date.");
            return false;
        }

        var contractType = normalizeContractTypeValue(readText(form, "contractType"));
        var contractStartDate = readField(form, "contractStartDate");
        var contractEndDate = readField(form, "contractEndDate");
        if (contractStartDate && contractEndDate && contractEndDate < contractStartDate) {
            showValidationToast("Contract end date tidak boleh lebih awal dari contract start date.");
            return false;
        }
        if (contractType === "contract" && !contractEndDate) {
            showValidationToast("Contract end date wajib diisi untuk contract.");
            return false;
        }
        if (contractType === "permanent" && contractEndDate) {
            showValidationToast("Contract end date tidak boleh diisi untuk permanent.");
            return false;
        }

        var baseSalary = readField(form, "baseSalary");
        if (stepIndex === 2 && (!/^[0-9]+$/.test(baseSalary) || Number(baseSalary) < 0)) {
            showValidationToast("Base salary wajib angka 0 atau lebih besar.");
            return false;
        }

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
        }

        return payload;
    }

    function hydrateEditForm(item) {
        if (!editForm || !item) {
            return;
        }

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
        setStep(form, 0);

        form.addEventListener("change", function (event) {
            var contractTypeInput = event.target && event.target.closest ? event.target.closest('[data-employee-add-field="contractType"], [data-employee-edit-field="contractType"]') : null;
            if (contractTypeInput) {
                toggleContractEndDateVisibility(form);
            }
        });

        form.addEventListener("click", function (event) {
            var nextBtn = event.target.closest("[data-employee-step-next]");
            if (nextBtn) {
                event.preventDefault();
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
                if (targetIndex > currentIndex && !validateCurrentStep(form)) {
                    return;
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
            if (!validateCurrentStep(addForm)) {
                return;
            }
            var payload = buildPayload(addForm, false);
            requestJson("post", "/v1/hcm/employees", payload)
                .then(function (resp) {
                    if (!resp || resp.success !== true) {
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
                    if (error && window.AuthApi && window.AuthApi.handleUnauthorizedFromApi(error.status, error.data)) {
                        return;
                    }
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
            var payload = buildPayload(editForm, true);
            requestJson("put", "/v1/hcm/employees/" + encodeURIComponent(employeeId), payload)
                .then(function (resp) {
                    if (!resp || resp.success !== true) {
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
                    if (error && window.AuthApi && window.AuthApi.handleUnauthorizedFromApi(error.status, error.data)) {
                        return;
                    }
                    if (window.ArcavUi && window.ArcavUi.showToast) {
                        window.ArcavUi.showToast(formatApiError(error && error.data, error && error.status), "danger");
                    }
                });
        });
    }
}
