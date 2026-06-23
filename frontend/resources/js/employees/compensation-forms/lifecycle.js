export function bindEmployeeCompensationFormsLifecycle(deps) {
    var addForm = deps.addForm;
    var editForm = deps.editForm;
    var requestJson = deps.requestJson;
    var requestEmployeeDetail = deps.requestEmployeeDetail;
    var fillDesignationSelectForDepartment = deps.fillDesignationSelectForDepartment;
    var loadTeamsDropdown = deps.loadTeamsDropdown;
    var formatApiError = deps.formatApiError;
    var loadEmployeesData = deps.loadEmployeesData;
    var readField = deps.readField;
    var readText = deps.readText;
    var readChecked = deps.readChecked;
    var readInteger = deps.readInteger;
    var readNumberOrNull = deps.readNumberOrNull;
    var writeField = deps.writeField;
    var normalizeContractTypeValue = deps.normalizeContractTypeValue;
    var toggleContractEndDateVisibility = deps.toggleContractEndDateVisibility;
    var resetWilayahCascade = deps.resetWilayahCascade;
    var setWilayahCascade = deps.setWilayahCascade;
    var updateModalEmployeeUuid = deps.updateModalEmployeeUuid;
    var ensureEmployeeContractTypeConsistency = deps.ensureEmployeeContractTypeConsistency;
    var maybeAutoSyncContractStartDate = deps.maybeAutoSyncContractStartDate;
    var setStep = deps.setStep;
    var clearBackendValidationState = deps.clearBackendValidationState;
    var validateCurrentStep = deps.validateCurrentStep;
    var applyBackendValidationErrors = deps.applyBackendValidationErrors;
    var maybeShowEmployeeLimitPopup = deps.maybeShowEmployeeLimitPopup;
    var enforceInputRules = deps.enforceInputRules;

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
        editForm.setAttribute("data-employee-orig-base-salary", item.baseSalary != null ? String(Math.round(Number(item.baseSalary || 0))) : "");
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
        editForm.setAttribute("data-employee-orig-tax-status", item.taxProfile && item.taxProfile.taxStatus ? item.taxProfile.taxStatus : "");
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
        deps.bindWilayahChangeHandlers(form);
        form.setAttribute("data-employee-contract-start-auto", "");
        ensureEmployeeContractTypeConsistency(form, { autoCorrect: true, notify: false });
        maybeAutoSyncContractStartDate(form);
        setStep(form, 0);

        function handleFieldInteraction(event) {
            var changedField = event.target && event.target.closest ? event.target.closest("input, select, textarea") : null;
            if (changedField) {
                enforceInputRules(changedField);
                if (typeof changedField.setCustomValidity === "function") {
                    changedField.setCustomValidity("");
                }
                if (changedField.getAttribute("data-employee-backend-error") === "1") {
                    changedField.removeAttribute("data-employee-backend-error");
                }
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

            var origSalary = editForm.getAttribute("data-employee-orig-base-salary");
            var origTaxStatus = editForm.getAttribute("data-employee-orig-tax-status");
            var newSalary = readField(editForm, "baseSalary");
            var newTaxStatus = readText(editForm, "taxStatus");
            if (
                (origSalary !== null && newSalary !== origSalary)
                || (origTaxStatus !== null && newTaxStatus !== origTaxStatus)
            ) {
                var changes = [];
                if (origSalary !== null && newSalary !== origSalary) changes.push("Base Salary (" + origSalary + " → " + newSalary + ")");
                if (origTaxStatus !== null && newTaxStatus !== origTaxStatus) changes.push("Tax Status (" + origTaxStatus + " → " + newTaxStatus + ")");
                var msg = "PERHATIAN: Perubahan data berikut akan memengaruhi kalkulasi payroll:\n\n" + changes.join("\n") + "\n\nLanjutkan?";
                if (!window.confirm(msg)) {
                    return;
                }
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
