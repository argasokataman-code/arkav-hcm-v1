(function (window, document) {
    "use strict";
    var hcmPagesUtils = window.ArcavHcmPagesUtils || {};
    var getAuthHeaders = typeof hcmPagesUtils.getAuthHeaders === "function" ? hcmPagesUtils.getAuthHeaders : function () { return {}; };
    var apiGet = typeof hcmPagesUtils.apiGet === "function" ? hcmPagesUtils.apiGet : function (url) { return fetch(url).then(function (res) { return res.json(); }); };
    var apiGetSafe = typeof hcmPagesUtils.apiGetSafe === "function" ? hcmPagesUtils.apiGetSafe : function (url, fallbackValue) { return apiGet(url).catch(function () { return fallbackValue; }); };
    var esc = typeof hcmPagesUtils.esc === "function" ? hcmPagesUtils.esc : function (v) { return String(v || ""); };
    var pathMatches = typeof hcmPagesUtils.pathMatches === "function" ? hcmPagesUtils.pathMatches : function (path, target) { return path === target; };
    var formatRupiah = typeof hcmPagesUtils.formatRupiah === "function" ? hcmPagesUtils.formatRupiah : function (value) { return "Rp" + String(value || 0); };
    var formatEmployeeCode = typeof hcmPagesUtils.formatEmployeeCode === "function" ? hcmPagesUtils.formatEmployeeCode : function (value) { return String(value || "-"); };
    var fillDesignationDepartmentSelects = typeof hcmPagesUtils.fillDesignationDepartmentSelects === "function" ? hcmPagesUtils.fillDesignationDepartmentSelects : function () {};
    var fillPolicyDepartmentSelects = typeof hcmPagesUtils.fillPolicyDepartmentSelects === "function" ? hcmPagesUtils.fillPolicyDepartmentSelects : function () {};
    var departmentIdPayloadFromSelect = typeof hcmPagesUtils.departmentIdPayloadFromSelect === "function" ? hcmPagesUtils.departmentIdPayloadFromSelect : function () { return { departmentId: null }; };
    var policyEffectiveDatePayload = typeof hcmPagesUtils.policyEffectiveDatePayload === "function" ? hcmPagesUtils.policyEffectiveDatePayload : function () { return {}; };

    function renderDepartments(rows) {
        var body = document.querySelector("[data-departments-body]");
        if (!body) return;
        body.innerHTML = (rows || []).map(function (r) {
            var badge = r.isActive ? "success" : "danger";
            var status = r.isActive ? "Active" : "Inactive";
            var desigCount = r.designationCount != null ? r.designationCount : (r.employeeCount != null ? r.employeeCount : 0);
            return '<tr><td><div class="form-check form-check-md"><input class="form-check-input" type="checkbox"></div></td><td><h6 class="fw-medium"><a href="#">' +
                esc(r.name) + '</a></h6></td><td>' + esc(desigCount) + '</td><td><span class="badge badge-' + badge +
                ' d-inline-flex align-items-center badge-xs"><i class="ti ti-point-filled me-1"></i>' + status +
                '</span></td><td><div class="action-icon d-inline-flex"><a href="#" class="me-2" data-hcm-edit="department" data-id="' + esc(r.id) + '" data-name="' + esc(r.name) + '" data-active="' + (r.isActive ? "1" : "0") + '" data-bs-toggle="modal" data-bs-target="#edit_department"><i class="ti ti-edit"></i></a><a href="#" data-hcm-delete="department" data-id="' + esc(r.id) + '"><i class="ti ti-trash"></i></a></div></td></tr>';
        }).join("") || '<tr><td colspan="5" class="text-center py-4 text-muted">No departments found.</td></tr>';
        body.setAttribute("data-hydrated", "1");
    }

    function renderDesignations(rows) {
        var body = document.querySelector("[data-designations-body]");
        if (!body) return;
        body.innerHTML = (rows || []).map(function (r) {
            var badge = r.isActive ? "success" : "danger";
            var status = r.isActive ? "Active" : "Inactive";
            var deptId = r.departmentId != null && r.departmentId !== "" ? String(r.departmentId) : "";
            return '<tr><td><div class="form-check form-check-md"><input class="form-check-input" type="checkbox"></div></td><td><h6 class="fw-medium fs-14 text-dark">' +
                esc(r.name) + '</h6></td><td>' + esc(r.department) + '</td><td>' + esc(r.employeeCount) +
                '</td><td><span class="badge badge-' + badge + ' d-inline-flex align-items-center badge-xs"><i class="ti ti-point-filled me-1"></i>' +
                status + '</span></td><td><div class="action-icon d-inline-flex"><a href="#" class="me-2" data-hcm-edit="designation" data-id="' + esc(r.id) + '" data-name="' + esc(r.name) + '" data-department-id="' + esc(deptId) + '" data-active="' + (r.isActive ? "1" : "0") + '" data-bs-toggle="modal" data-bs-target="#edit_designation"><i class="ti ti-edit"></i></a><a href="#" data-hcm-delete="designation" data-id="' + esc(r.id) + '"><i class="ti ti-trash"></i></a></div></td></tr>';
        }).join("") || '<tr><td colspan="6" class="text-center py-4 text-muted">No designations found.</td></tr>';
        body.setAttribute("data-hydrated", "1");
    }

    function renderPolicies(rows) {
        var body = document.querySelector("[data-policies-body]");
        if (!body) return;
        body.innerHTML = (rows || []).map(function (r) {
            var deptId = r.departmentId != null && r.departmentId !== "" ? String(r.departmentId) : "";
            var descForAttr = String(r.description || "").replace(/\r?\n/g, " ").replace(/\s+/g, " ").trim();
            var eff = r.effectiveDate || r.createdDate || "";
            var effForInput = eff && String(eff).length >= 10 ? String(eff).slice(0, 10) : "";
            var attUrl = r.attachmentUrl || "";
            var attCell = attUrl
                ? '<a href="' + esc(attUrl) + '" target="_blank" rel="noopener noreferrer" class="text-primary text-decoration-underline">View file</a>'
                : '<span class="text-muted">—</span>';
            return "<tr><td><div class=\"form-check form-check-md\"><input class=\"form-check-input\" type=\"checkbox\"></div></td><td><h6 class=\"fs-14 fw-medium text-gray-9\">" +
                esc(r.name) + "</h6></td><td>" + esc(r.department) + "</td><td>" + esc(r.description) + "</td><td>" + esc(eff || "-") +
                "</td><td>" + attCell + "</td><td><div class=\"action-icon d-inline-flex\"><a href=\"#\" class=\"me-2\" data-hcm-edit=\"policy\" data-id=\"" + esc(r.id) + "\" data-name=\"" + esc(r.name) + "\" data-description=\"" + esc(descForAttr) + "\" data-effective-date=\"" + esc(effForInput) + "\" data-department-id=\"" + esc(deptId) + "\" data-bs-toggle=\"modal\" data-bs-target=\"#edit_policy\"><i class=\"ti ti-edit\"></i></a><a href=\"#\" data-hcm-delete=\"policy\" data-id=\"" + esc(r.id) + "\"><i class=\"ti ti-trash\"></i></a></div></td></tr>";
        }).join("") || '<tr><td colspan="7" class="text-center py-4 text-muted">No policies found.</td></tr>';
        body.setAttribute("data-hydrated", "1");
    }

    var normalizeYesNo = typeof hcmPagesUtils.normalizeYesNo === "function" ? hcmPagesUtils.normalizeYesNo : function (value) { return !!value; };
    var formatApiError = typeof hcmPagesUtils.formatApiError === "function" ? hcmPagesUtils.formatApiError : function (data, status) { return (data && data.message) || (status ? "Request failed (" + status + ")" : "Request failed"); };
    var renderHcmShowing = typeof hcmPagesUtils.renderHcmShowing === "function" ? hcmPagesUtils.renderHcmShowing : function () {};
    var renderHcmPagination = typeof hcmPagesUtils.renderHcmPagination === "function" ? hcmPagesUtils.renderHcmPagination : function () {};
    var toCsv = typeof hcmPagesUtils.toCsv === "function" ? hcmPagesUtils.toCsv : function () { return ""; };
    var downloadBlob = typeof hcmPagesUtils.downloadBlob === "function" ? hcmPagesUtils.downloadBlob : function () {};

    function bindCrudForms(path) {
        function notify(message, isError) {
            var existing = document.querySelector("[data-hcm-toast-container]");
            var container = existing;
            if (!container) {
                container = document.createElement("div");
                container.setAttribute("data-hcm-toast-container", "1");
                container.style.position = "fixed";
                container.style.top = "16px";
                container.style.right = "16px";
                container.style.zIndex = "1080";
                container.style.maxWidth = "340px";
                document.body.appendChild(container);
            }

            var toast = document.createElement("div");
            toast.className = "alert " + (isError ? "alert-danger" : "alert-success") + " shadow-sm mb-2";
            toast.textContent = message;
            container.appendChild(toast);

            window.setTimeout(function () {
                toast.remove();
            }, 2400);
        }
        function post(url, payload, method) {
            if (window.axios) {
                var authHeaders = getAuthHeaders();
                return window.axios({
                    method: method || "post",
                    url: url,
                    headers: Object.assign({ Accept: "application/json" }, authHeaders),
                    data: payload,
                    withCredentials: true,
                }).then(function () {
                    notify("Saved successfully", false);
                    window.location.reload();
                }).catch(function (err) {
                    var st = err && err.response ? err.response.status : 0;
                    var body = err && err.response ? err.response.data : null;
                    if (window.AuthApi && window.AuthApi.handleUnauthorizedFromApi(st, body)) {
                        return;
                    }
                    var msg = body ? formatApiError(body, st) : "Failed to save data";
                    notify(msg, true);
                });
            }
            return fetch(url, {
                method: (method || "post").toUpperCase(),
                headers: Object.assign({ "Content-Type": "application/json", Accept: "application/json" }, getAuthHeaders()),
                credentials: "same-origin",
                body: JSON.stringify(payload),
            }).then(function (res) {
                return res.json().catch(function () {
                    return {};
                }).then(function (data) {
                    if (!res.ok) {
                        if (window.AuthApi && window.AuthApi.handleUnauthorizedFromApi(res.status, data)) {
                            return;
                        }
                        notify(formatApiError(data, res.status), true);
                        return;
                    }
                    notify("Saved successfully", false);
                    window.location.reload();
                });
            }).catch(function () {
                notify("Failed to save data", true);
            });
        }

        function postMultipart(url, formData) {
            return fetch(url, {
                method: "POST",
                headers: Object.assign({ Accept: "application/json" }, getAuthHeaders()),
                credentials: "same-origin",
                body: formData,
            }).then(function (res) {
                return res.json().catch(function () {
                    return {};
                }).then(function (data) {
                    if (!res.ok) {
                        if (window.AuthApi && window.AuthApi.handleUnauthorizedFromApi(res.status, data)) {
                            return;
                        }
                        notify(formatApiError(data, res.status), true);
                        return;
                    }
                    notify("Saved successfully", false);
                    window.location.reload();
                });
            }).catch(function () {
                notify("Failed to save data", true);
            });
        }

        function confirmDelete(message) {
            if (window.ArcavUi && typeof window.ArcavUi.confirmDelete === "function") {
                return window.ArcavUi.confirmDelete(message || "Hapus data ini?", "Konfirmasi hapus");
            }
            return Promise.resolve(false);
        }

        function remove(url) {
            return confirmDelete("Are you sure you want to delete this data?")
                .then(function (ok) {
                    if (!ok) return null;
                    if (window.axios) {
                        var authHeaders = getAuthHeaders();
                        return window.axios({
                            method: "delete",
                            url: url,
                            headers: Object.assign({ Accept: "application/json" }, authHeaders),
                            withCredentials: true,
                        }).then(function () {
                            notify("Deleted successfully", false);
                            window.location.reload();
                        }).catch(function (err) {
                            var st = err && err.response ? err.response.status : 0;
                            var body = err && err.response ? err.response.data : null;
                            if (window.AuthApi && window.AuthApi.handleUnauthorizedFromApi(st, body)) {
                                return;
                            }
                            var msg = body ? formatApiError(body, st) : "Failed to delete data";
                            notify(msg, true);
                        });
                    }
                    return fetch(url, {
                        method: "DELETE",
                        headers: Object.assign({ Accept: "application/json" }, getAuthHeaders()),
                        credentials: "same-origin",
                    }).then(function (res) {
                        return res.json().catch(function () {
                            return {};
                        }).then(function (data) {
                            if (!res.ok) {
                                if (window.AuthApi && window.AuthApi.handleUnauthorizedFromApi(res.status, data)) {
                                    return;
                                }
                                notify(formatApiError(data, res.status), true);
                                return;
                            }
                            notify("Deleted successfully", false);
                            window.location.reload();
                        });
                    }).catch(function () {
                        notify("Failed to delete data", true);
                    });
                })
                .catch(function () {
                    notify("Failed to delete data", true);
                });
        }

        var depAdd = document.querySelector('[data-hcm-form="department-add"]');
        if (depAdd && path === "/departments") {
            depAdd.addEventListener("submit", function (e) {
                e.preventDefault();
                var name = depAdd.querySelector('[data-hcm-field="department-name"]').value.trim();
                var active = normalizeYesNo(depAdd.querySelector('[data-hcm-field="department-active"]').value);
                post("/v1/hcm/departments", { name: name, isActive: active });
            });
        }
        var depEdit = document.querySelector('[data-hcm-form="department-edit"]');
        if (depEdit && path === "/departments") {
            document.addEventListener("click", function (e) {
                var btn = e.target.closest('[data-hcm-edit="department"]');
                if (!btn) return;
                depEdit.dataset.id = btn.dataset.id || "";
                depEdit.querySelector('[data-hcm-field="department-name"]').value = btn.dataset.name || "";
                depEdit.querySelector('[data-hcm-field="department-active"]').value = btn.dataset.active === "1" ? "Active" : "Inactive";
            });
            depEdit.addEventListener("submit", function (e) {
                e.preventDefault();
                var id = depEdit.dataset.id;
                if (!id) return;
                post("/v1/hcm/departments/" + encodeURIComponent(id), {
                    name: depEdit.querySelector('[data-hcm-field="department-name"]').value.trim(),
                    isActive: normalizeYesNo(depEdit.querySelector('[data-hcm-field="department-active"]').value),
                }, "put");
            });
            document.addEventListener("click", function (e) {
                var del = e.target.closest('[data-hcm-delete="department"]');
                if (!del) return;
                e.preventDefault();
                remove("/v1/hcm/departments/" + encodeURIComponent(del.dataset.id));
            });
        }

        var desAdd = document.querySelector('[data-hcm-form="designation-add"]');
        if (desAdd && path === "/designations") {
            desAdd.addEventListener("submit", function (e) {
                e.preventDefault();
                var deptSel = desAdd.querySelector('[data-hcm-field="designation-department"]');
                var base = {
                    name: desAdd.querySelector('[data-hcm-field="designation-name"]').value.trim(),
                    isActive: normalizeYesNo(desAdd.querySelector('[data-hcm-field="designation-active"]').value),
                };
                Object.assign(base, departmentIdPayloadFromSelect(deptSel));
                post("/v1/hcm/designations", base);
            });
        }
        var desEdit = document.querySelector('[data-hcm-form="designation-edit"]');
        if (desEdit && path === "/designations") {
            document.addEventListener("click", function (e) {
                var btn = e.target.closest('[data-hcm-edit="designation"]');
                if (!btn) return;
                desEdit.dataset.id = btn.dataset.id || "";
                desEdit.querySelector('[data-hcm-field="designation-name"]').value = btn.dataset.name || "";
                desEdit.querySelector('[data-hcm-field="designation-department"]').value = btn.dataset.departmentId || "";
                desEdit.querySelector('[data-hcm-field="designation-active"]').value = btn.dataset.active === "1" ? "Active" : "Inactive";
            });
            desEdit.addEventListener("submit", function (e) {
                e.preventDefault();
                var id = desEdit.dataset.id;
                if (!id) return;
                var deptSelEdit = desEdit.querySelector('[data-hcm-field="designation-department"]');
                var editBase = {
                    name: desEdit.querySelector('[data-hcm-field="designation-name"]').value.trim(),
                    isActive: normalizeYesNo(desEdit.querySelector('[data-hcm-field="designation-active"]').value),
                };
                Object.assign(editBase, departmentIdPayloadFromSelect(deptSelEdit));
                post("/v1/hcm/designations/" + encodeURIComponent(id), editBase, "put");
            });
            document.addEventListener("click", function (e) {
                var del = e.target.closest('[data-hcm-delete="designation"]');
                if (!del) return;
                e.preventDefault();
                remove("/v1/hcm/designations/" + encodeURIComponent(del.dataset.id));
            });
        }

        var polAdd = document.querySelector('[data-hcm-form="policy-add"]');
        if (polAdd && path === "/policy") {
            polAdd.addEventListener("submit", function (e) {
                e.preventDefault();
                var nameVal = polAdd.querySelector('[data-hcm-field="policy-name"]').value.trim();
                var descVal = polAdd.querySelector('[data-hcm-field="policy-description"]').value.trim();
                var polDeptSel = polAdd.querySelector('[data-hcm-field="policy-department"]');
                var polDate = polAdd.querySelector('[data-hcm-field="policy-effective-date"]');
                var polFile = polAdd.querySelector("[data-policy-file-input]");
                var fd = new FormData();
                fd.append("name", nameVal);
                fd.append("description", descVal);
                fd.append("departmentId", (polDeptSel && polDeptSel.value.trim()) || "");
                fd.append("effectiveDate", (polDate && polDate.value.trim()) || "");
                if (polFile && polFile.files && polFile.files[0]) {
                    fd.append("attachment", polFile.files[0]);
                }
                postMultipart("/v1/hcm/policies", fd);
            });
        }
        var polEdit = document.querySelector('[data-hcm-form="policy-edit"]');
        if (polEdit && path === "/policy") {
            document.addEventListener("click", function (e) {
                var btn = e.target.closest('[data-hcm-edit="policy"]');
                if (!btn) return;
                polEdit.dataset.id = btn.dataset.id || "";
                polEdit.querySelector('[data-hcm-field="policy-name"]').value = btn.dataset.name || "";
                polEdit.querySelector('[data-hcm-field="policy-description"]').value = btn.dataset.description || "";
                polEdit.querySelector('[data-hcm-field="policy-effective-date"]').value = btn.dataset.effectiveDate || "";
                polEdit.querySelector('[data-hcm-field="policy-department"]').value = btn.dataset.departmentId || "";
            });
            polEdit.addEventListener("submit", function (e) {
                e.preventDefault();
                var id = polEdit.dataset.id;
                if (!id) return;
                var nameVal = polEdit.querySelector('[data-hcm-field="policy-name"]').value.trim();
                var descVal = polEdit.querySelector('[data-hcm-field="policy-description"]').value.trim();
                var polDeptEdit = polEdit.querySelector('[data-hcm-field="policy-department"]');
                var polDateEdit = polEdit.querySelector('[data-hcm-field="policy-effective-date"]');
                var polFileEdit = polEdit.querySelector("[data-policy-file-input]");
                var newFile = polFileEdit && polFileEdit.files && polFileEdit.files[0];
                if (newFile) {
                    var fdEdit = new FormData();
                    fdEdit.append("name", nameVal);
                    fdEdit.append("description", descVal);
                    fdEdit.append("departmentId", (polDeptEdit && polDeptEdit.value.trim()) || "");
                    fdEdit.append("effectiveDate", (polDateEdit && polDateEdit.value.trim()) || "");
                    fdEdit.append("attachment", newFile);
                    postMultipart("/v1/hcm/policies/" + encodeURIComponent(id), fdEdit);
                    return;
                }
                var polEditBase = { name: nameVal, description: descVal };
                Object.assign(polEditBase, departmentIdPayloadFromSelect(polDeptEdit));
                Object.assign(polEditBase, policyEffectiveDatePayload(polDateEdit));
                post("/v1/hcm/policies/" + encodeURIComponent(id), polEditBase, "put");
            });
            document.addEventListener("click", function (e) {
                var del = e.target.closest('[data-hcm-delete="policy"]');
                if (!del) return;
                e.preventDefault();
                remove("/v1/hcm/policies/" + encodeURIComponent(del.dataset.id));
            });
        }
    }

    function renderEmployeeDetail(item) {
        if (!item) return;
        function set(selector, value) {
            var el = document.querySelector(selector);
            if (el) el.textContent = value || "-";
        }
        set("[data-employee-name]", item.fullName);
        set("[data-employee-no]", formatEmployeeCode(item.id));
        set("[data-employee-team]", item.team);
        set("[data-employee-department]", item.departmentName);
        set("[data-employee-team-leader]", (item.assignment && item.assignment.managerName) || item.managerName || "Belum ditentukan");
        set("[data-employee-join-date]", item.joinDate);
        set("[data-employee-designation]", item.designation);
        set("[data-employee-phone]", item.phone);
        set("[data-employee-email]", item.email);
        set("[data-employee-address]", item.address);
        set("[data-employee-base-salary]", formatRupiah(item.baseSalary));
        set("[data-employee-report-office]", item.reportOffice || "-");
        set("[data-employee-initial]", (item.fullName || "?").charAt(0).toUpperCase());

        var photoWrap = document.querySelector("[data-employee-avatar-wrap]");
        var photoImg = document.querySelector("[data-employee-photo-preview]");
        var modalImg = document.querySelector("[data-employee-photo-modal-image]");
        var initialEl = document.querySelector("[data-employee-initial]");
        var editBtn = document.querySelector("[data-employee-photo-edit-btn]");
        var viewBtn = document.querySelector("[data-employee-photo-view-btn]");
        var hasPhoto = !!(item.profilePhotoUrl && String(item.profilePhotoUrl).trim());
        if (photoWrap) {
            photoWrap.classList.toggle("bg-white", !hasPhoto);
        }
        if (photoImg) {
            if (hasPhoto) {
                photoImg.src = item.profilePhotoUrl;
                photoImg.classList.remove("d-none");
            } else {
                photoImg.src = "";
                photoImg.classList.add("d-none");
            }
        }
        if (modalImg) {
            modalImg.src = hasPhoto ? item.profilePhotoUrl : "";
        }
        if (initialEl) {
            initialEl.classList.toggle("d-none", hasPhoto);
        }
        if (editBtn) {
            editBtn.classList.toggle("d-none", !hasPhoto);
        }
        if (viewBtn) {
            viewBtn.classList.toggle("d-none", !hasPhoto);
        }
        var schedule = item.schedule || {};
        set("[data-employee-schedule-display]", schedule.display || "-");
        set("[data-employee-schedule-source]", schedule.sourceLabel || "-");
        set("[data-employee-schedule-shift]", schedule.shiftName || "Not assigned");

        var rightSections = document.querySelector("[data-employee-details-sections]");
        if (rightSections) {
            var bank = item.bank || {};
            var personal = item.personal || {};
            var taxProfile = item.taxProfile || {};
            var education = Array.isArray(item.educationItems) ? item.educationItems : [];
            var experience = Array.isArray(item.experienceItems) ? item.experienceItems : [];
            var employmentHistory = Array.isArray(item.employmentHistory) ? item.employmentHistory : [];
            var assignmentHistory = Array.isArray(item.assignmentHistory) ? item.assignmentHistory : [];
            var compensationHistory = Array.isArray(item.compensationHistory) ? item.compensationHistory : [];
            var contractHistory = Array.isArray(item.contractHistory) ? item.contractHistory : [];
            var compensationHeading = compensationHistory.length > 1
                ? "Compensation History"
                : (compensationHistory.length === 1 ? "Initial Compensation" : "Compensation");
            var bankAccounts = Array.isArray(item.bankAccounts) ? item.bankAccounts : [];
            var documents = Array.isArray(item.documents) ? item.documents : [];
            var trainings = Array.isArray(item.trainingItems) ? item.trainingItems : [];
            var promotions = Array.isArray(item.promotionItems) ? item.promotionItems : [];
            var resignations = Array.isArray(item.resignationItems) ? item.resignationItems : [];
            var terminations = Array.isArray(item.terminationItems) ? item.terminationItems : [];

            var educationHtml = education.map(function (ed) {
                var period = [ed.startYear || "—", ed.endYear || "—"].join(" - ");
                return '<li class="mb-2"><strong>' + esc(ed.institution || "-") + '</strong> - ' + esc(ed.degree || "-") + ' <span class="text-muted">(' + esc(period) + ')</span></li>';
            }).join("");
            var experienceHtml = experience.map(function (ex) {
                var period = [ex.startDate || "—", ex.endDate || "—"].join(" - ");
                return '<li class="mb-2"><strong>' + esc(ex.company || "-") + '</strong> - ' + esc(ex.position || "-") + ' <span class="text-muted">(' + esc(period) + ')</span></li>';
            }).join("");
            var employmentHistoryHtml = employmentHistory.map(function (row) {
                var probation = row.probationEndDate ? ('<div class="small text-muted">Probation ends: ' + esc(row.probationEndDate) + '</div>') : '';
                return '<li class="mb-2"><strong>' + esc(row.employmentStatus || "-") + '</strong> <span class="text-muted">(' + esc((row.startDate || "—") + ' - ' + (row.endDate || "Present")) + ')</span>' + probation + '</li>';
            }).join("");
            var assignmentHistoryHtml = assignmentHistory.map(function (row) {
                return '<li class="mb-2"><strong>' + esc(row.teamName || row.departmentName || "-") + '</strong> — ' + esc(row.designationName || "-") + ' <span class="text-muted">(' + esc((row.startDate || "—") + ' - ' + (row.endDate || "Present")) + ')</span></li>';
            }).join("");
            var compensationHistoryHtml = compensationHistory.map(function (row) {
                return '<li class="mb-2"><strong>' + formatRupiah(row.baseSalary || 0) + '</strong> <span class="text-muted">(' + esc((row.salaryType || "monthly") + ', ' + (row.effectiveDate || "—")) + ')</span></li>';
            }).join("");
            var contractHistoryHtml = contractHistory.map(function (row) {
                return '<li class="mb-2"><strong>' + esc((row.contractType || "-").toUpperCase()) + '</strong> — ' + esc(row.status || "-") + ' <span class="text-muted">(' + esc((row.startDate || "—") + ' - ' + (row.endDate || "Present")) + ')</span></li>';
            }).join("");
            var bankAccountsHtml = bankAccounts.map(function (row) {
                return '<li class="mb-2"><strong>' + esc(row.bankName || "-") + '</strong> — ' + esc(row.accountNumber || "-") + ' <span class="text-muted">' + esc(row.accountHolderName || "-") + '</span></li>';
            }).join("");
            var documentsHtml = documents.map(function (row) {
                return '<li class="mb-2"><strong>' + esc(row.name || row.label || "Document") + '</strong></li>';
            }).join("");

            var trainingsHtml = trainings.map(function (t) {
                var typeName = t.type && t.type.name ? t.type.name : "—";
                var duration = (t.startDate || t.endDate) ? (esc(t.startDate || "—") + " - " + esc(t.endDate || "—")) : "—";
                var status = String(t.status || "");
                var badge = status === "completed" ? "soft-success" : (status === "inactive" ? "danger" : "success");
                return '<tr><td>' + esc(typeName) + '</td><td class="text-break">' + esc(t.trainerName || "—") +
                    '</td><td>' + duration + '</td><td><span class="badge badge-' + esc(badge) +
                    ' d-inline-flex align-items-center badge-xs"><i class="ti ti-point-filled me-1"></i>' + esc(status || "—") +
                    '</span></td><td class="text-break">' + esc(t.description || "—") + '</td></tr>';
            }).join("") || '<tr><td colspan="5" class="text-center py-4 text-muted">Belum ada training untuk karyawan ini.</td></tr>';

            var promotionsHtml = promotions.map(function (pr) {
                return '<tr><td>' + esc(pr.promotionDate || "—") + '</td><td class="text-break">' + esc(pr.department || "—") +
                    '</td><td class="text-break">' + esc(pr.designationFrom || "—") + '</td><td class="text-break">' + esc(pr.designationTo || "—") +
                    '</td><td><a href="#" class="btn btn-sm btn-light border d-inline-flex align-items-center" data-arcav-promotion-view="' + esc(pr.id) + '">' +
                    '<i class="ti ti-eye me-1"></i>Detail</a></td></tr>';
            }).join("") || '<tr><td colspan="5" class="text-center py-4 text-muted">Belum ada riwayat promotion.</td></tr>';

            var resignationsHtml = resignations.map(function (rg) {
                var raw = String(rg.reason || "").trim();
                var reasonShort = raw.length > 56 ? raw.slice(0, 53) + "…" : raw;
                return '<tr><td>' + esc(rg.noticeDate || "—") + '</td><td>' + esc(rg.resignationDate || "—") +
                    '</td><td class="text-break">' + esc(reasonShort || "—") + '</td><td>' + esc(rg.status || "—") +
                    '</td><td><a href="#" class="btn btn-sm btn-light border d-inline-flex align-items-center" data-arcav-resignation-view="' + esc(rg.id) + '">' +
                    '<i class="ti ti-eye me-1"></i>Detail</a></td></tr>';
            }).join("") || '<tr><td colspan="5" class="text-center py-4 text-muted">Belum ada riwayat resignation.</td></tr>';

            var terminationsHtml = terminations.map(function (tm) {
                var raw = String(tm.reason || "").trim();
                var reasonShort = raw.length > 48 ? raw.slice(0, 45) + "…" : raw;
                return '<tr><td>' + esc(tm.noticeDate || "—") + '</td><td>' + esc(tm.terminationDate || "—") +
                    '</td><td class="text-break">' + esc(tm.terminationType || "—") + '</td><td class="text-break">' + esc(reasonShort || "—") +
                    '</td><td>' + esc(tm.status || "—") +
                    '</td><td><a href="#" class="btn btn-sm btn-light border d-inline-flex align-items-center" data-arcav-termination-view="' + esc(tm.id) + '">' +
                    '<i class="ti ti-eye me-1"></i>Detail</a></td></tr>';
            }).join("") || '<tr><td colspan="6" class="text-center py-4 text-muted">Belum ada riwayat termination.</td></tr>';

            rightSections.innerHTML =
                '<div class="card"><div class="card-body">' +
                '<h5 class="mb-3">Personal Info</h5>' +
                '<div class="row mb-4">' +
                '<div class="col-md-3"><small class="text-muted d-block">NIK</small><strong>' + esc(personal.nik || item.nik || "-") + '</strong></div>' +
                '<div class="col-md-3"><small class="text-muted d-block">Birth</small><strong>' + esc((personal.placeOfBirth || item.placeOfBirth || "-") + ' / ' + (personal.dateOfBirth || item.dateOfBirth || "-")) + '</strong></div>' +
                '<div class="col-md-3"><small class="text-muted d-block">Gender</small><strong>' + esc(personal.gender || item.gender || "-") + '</strong></div>' +
                '<div class="col-md-3"><small class="text-muted d-block">Marital</small><strong>' + esc(personal.maritalStatus || item.maritalStatus || "-") + '</strong></div>' +
                '<div class="col-md-3 mt-3"><small class="text-muted d-block">Religion</small><strong>' + esc(personal.religion || item.religion || "-") + '</strong></div>' +
                '<div class="col-md-3 mt-3"><small class="text-muted d-block">Nationality</small><strong>' + esc(personal.nationality || item.nationality || "-") + '</strong></div>' +
                '</div>' +
                '<h5 class="mb-3">Employment History</h5><ul class="mb-4">' + (employmentHistoryHtml || '<li>-</li>') + '</ul>' +
                '<h5 class="mb-3">Assignment</h5><ul class="mb-4">' + (assignmentHistoryHtml || '<li>-</li>') + '</ul>' +
                '<h5 class="mb-3">' + esc(compensationHeading) + '</h5><ul class="mb-4">' + (compensationHistoryHtml || '<li>-</li>') + '</ul>' +
                '<h5 class="mb-3">Contracts</h5><ul class="mb-4">' + (contractHistoryHtml || '<li>-</li>') + '</ul>' +
                '<h5 class="mb-3">Bank Accounts</h5><ul class="mb-4">' + (bankAccountsHtml || '<li><strong>' + esc(bank.name || '-') + '</strong> — ' + esc(bank.accountNo || '-') + '</li>') + '</ul>' +
                '<h5 class="mb-3">Tax Info</h5>' +
                '<div class="row mb-4"><div class="col-md-4"><small class="text-muted d-block">NPWP</small><strong>' + esc(taxProfile.npwp || '-') + '</strong></div><div class="col-md-4"><small class="text-muted d-block">Tax Status</small><strong>' + esc(taxProfile.taxStatus || '-') + '</strong></div><div class="col-md-4"><small class="text-muted d-block">PTKP Alias</small><strong>' + esc(taxProfile.ptkpStatus || '-') + '</strong></div></div>' +
                '<h5 class="mb-3">Documents</h5><ul class="mb-4">' + (documentsHtml || '<li>No document metadata available.</li>') + '</ul>' +
                '<h5 class="mb-3">About Employee</h5><p class="mb-4">' + esc(item.bio || '-') + '</p>' +
                '<h5 class="mb-3">Training</h5>' +
                '<div class="table-responsive mb-4"><table class="table mb-0"><thead class="thead-light"><tr><th>Type</th><th>Trainer</th><th>Duration</th><th>Status</th><th>Description</th></tr></thead><tbody>' + trainingsHtml + '</tbody></table></div>' +
                '<h5 class="mb-3">Promotion</h5>' +
                '<div class="table-responsive mb-4"><table class="table mb-0"><thead class="thead-light"><tr><th>Date</th><th>Department</th><th>From</th><th>To</th><th></th></tr></thead><tbody>' + promotionsHtml + '</tbody></table></div>' +
                '<h5 class="mb-3">Resignation</h5>' +
                '<div class="table-responsive mb-4"><table class="table mb-0"><thead class="thead-light"><tr><th>Notice</th><th>Resignation</th><th>Reason</th><th>Status</th><th></th></tr></thead><tbody>' + resignationsHtml + '</tbody></table></div>' +
                '<h5 class="mb-3">Termination</h5>' +
                '<div class="table-responsive mb-4"><table class="table mb-0"><thead class="thead-light"><tr><th>Notice</th><th>Termination</th><th>Type</th><th>Reason</th><th>Status</th><th></th></tr></thead><tbody>' + terminationsHtml + '</tbody></table></div>' +
                '<h5 class="mb-3">Education</h5><ul class="mb-4">' + (educationHtml || '<li>-</li>') + '</ul>' +
                '<h5 class="mb-3">Experience</h5><ul class="mb-0">' + (experienceHtml || '<li>-</li>') + '</ul>' +
                '</div></div>';
        }

        var emergencyCard = document.querySelector("[data-employee-emergency-card]");
        if (emergencyCard) {
            var contacts = Array.isArray(item.emergencyContacts) ? item.emergencyContacts : [];
            emergencyCard.innerHTML =
                '<div class="card-body p-0">' +
                (contacts.map(function (c) {
                    return '<div class="p-3 border-bottom"><div class="d-flex align-items-center justify-content-between"><div><span class="d-inline-flex align-items-center">' + esc(c.label || "Contact") + '</span><h6 class="d-flex align-items-center fw-medium mt-1">' + esc(c.name || "-") + '<span class="d-inline-flex mx-1"><i class="ti ti-point-filled text-danger"></i></span>' + esc(c.relation || "-") + '</h6></div><p class="text-dark">' + esc(c.phone || "-") + "</p></div></div>";
                }).join("") || '<div class="p-3 border-bottom text-muted">No emergency contact data.</div>') +
                "</div>";
        }
    }

    function init() {
        var path = String(window.location.pathname || "").replace(/\/+$/, "") || "/";

        function exportRows(moduleKey, format, rows) {
            if (!rows.length) {
                return;
            }
            var mapped = [];
            if (moduleKey === "departments") {
                mapped = rows.map(function (r) {
                    return { name: r.name || "", designationCount: r.designationCount || 0, status: r.isActive ? "Active" : "Inactive" };
                });
            } else if (moduleKey === "designations") {
                mapped = rows.map(function (r) {
                    return { name: r.name || "", department: r.department || "", employeeCount: r.employeeCount || 0, status: r.isActive ? "Active" : "Inactive" };
                });
            } else {
                mapped = rows.map(function (r) {
                    return { name: r.name || "", department: r.department || "", description: r.description || "", effectiveDate: r.effectiveDate || "" };
                });
            }
            var headers = Object.keys(mapped[0] || {});
            var csv = toCsv(mapped, headers);
            if (format === "pdf") {
                var win = window.open("", "_blank");
                if (!win) return;
                win.document.write('<html><head><title>' + esc(moduleKey) + ' export</title></head><body><pre>' + esc(csv) + '</pre></body></html>');
                win.document.close();
                win.focus();
                win.print();
                return;
            }
            var stamp = new Date().toISOString().slice(0, 10);
            downloadBlob(moduleKey + "-" + stamp + ".csv", "text/csv;charset=utf-8", csv);
        }

        function bootListModule(moduleKey, endpoint, renderFn, extraQueryBuilder, afterLoad) {
            var params = new URL(window.location.href).searchParams;
            var state = {
                page: Math.max(1, parseInt(params.get("page") || "1", 10) || 1),
                perPage: Math.max(1, parseInt(params.get("perPage") || "20", 10) || 20),
                search: String(params.get("search") || "").trim(),
                status: String(params.get("status") || "").trim(),
                departmentId: String(params.get("departmentId") || "").trim(),
            };

            function buildUrl(pageOverride, perPageOverride) {
                var query = new URLSearchParams();
                query.set("page", String(pageOverride || state.page));
                query.set("perPage", String(perPageOverride || state.perPage));
                if (state.search) query.set("search", state.search);
                if (state.status) query.set("status", state.status);
                if (state.departmentId && (moduleKey === "designations" || moduleKey === "policies")) {
                    query.set("departmentId", state.departmentId);
                }
                if (typeof extraQueryBuilder === "function") {
                    extraQueryBuilder(query, state);
                }
                return endpoint + "?" + query.toString();
            }

            function buildExportUrl(format) {
                var query = new URLSearchParams();
                if (state.search) query.set("search", state.search);
                if (state.status) query.set("status", state.status);
                if (state.departmentId && (moduleKey === "designations" || moduleKey === "policies")) {
                    query.set("departmentId", state.departmentId);
                }
                query.set("format", format === "pdf" ? "pdf" : "xlsx");
                return endpoint + "/export?" + query.toString();
            }

            function load() {
                return apiGet(buildUrl()).then(function (payload) {
                    var rows = payload && payload.success === true && Array.isArray(payload.data) ? payload.data : [];
                    var meta = payload && payload.meta ? payload.meta : { page: 1, perPage: state.perPage, total: rows.length };
                    state.page = Number(meta.page || 1);
                    state.perPage = Number(meta.perPage || state.perPage);
                    renderFn(rows);
                    renderHcmShowing(moduleKey, meta, rows.length);
                    renderHcmPagination(moduleKey, meta);
                    if (typeof afterLoad === "function") {
                        afterLoad(rows, meta);
                    }
                    return rows;
                });
            }

            var searchInput = document.querySelector('[data-hcm-search-input="' + moduleKey + '"]');
            var statusSel = document.querySelector('[data-hcm-status-filter="' + moduleKey + '"]');
            var perPageSel = document.querySelector('[data-hcm-per-page="' + moduleKey + '"]');
            var deptSel = moduleKey === "designations"
                ? document.querySelector("[data-hcm-designation-department-filter]")
                : moduleKey === "policies"
                    ? document.querySelector("[data-hcm-policy-department-filter]")
                    : null;

            if (searchInput) {
                searchInput.value = state.search;
                var searchTimer = null;
                searchInput.addEventListener("input", function () {
                    window.clearTimeout(searchTimer);
                    searchTimer = window.setTimeout(function () {
                        state.search = String(searchInput.value || "").trim();
                        state.page = 1;
                        load();
                    }, 300);
                });
            }
            if (statusSel) {
                statusSel.value = state.status;
                statusSel.addEventListener("change", function () {
                    state.status = String(statusSel.value || "");
                    state.page = 1;
                    load();
                });
            }
            if (perPageSel) {
                perPageSel.value = String(state.perPage);
                perPageSel.addEventListener("change", function () {
                    state.perPage = Math.max(1, parseInt(perPageSel.value || "20", 10) || 20);
                    state.page = 1;
                    load();
                });
            }
            if (deptSel) {
                deptSel.value = state.departmentId;
                deptSel.addEventListener("change", function () {
                    state.departmentId = String(deptSel.value || "");
                    state.page = 1;
                    load();
                });
            }

            document.addEventListener("click", function (event) {
                var pg = event.target.closest('[data-hcm-page][data-hcm-module="' + moduleKey + '"]');
                if (pg) {
                    event.preventDefault();
                    var target = Math.max(1, parseInt(pg.getAttribute("data-hcm-page") || "1", 10) || 1);
                    if (target === state.page) return;
                    state.page = target;
                    load();
                    return;
                }
                var exp = event.target.closest('[data-hcm-export][data-hcm-export-module="' + moduleKey + '"]');
                if (exp) {
                    event.preventDefault();
                    window.location.assign(buildExportUrl(exp.getAttribute("data-hcm-export") || "xlsx"));
                }
            });

            return load;
        }

        if (pathMatches(path, "/departments")) {
            return apiGet("/v1/identity/auth/me").then(function (me) {
                if (!(me && me.success && me.data && me.data.permissions && me.data.permissions['department.manage'])) {
                    window.location.replace("/employee-dashboard");
                    return;
                }
                bindCrudForms(path);
                return bootListModule("departments", "/v1/hcm/departments", renderDepartments, null, null)();
            });
        }
        if (pathMatches(path, "/designations")) {
            return apiGet("/v1/identity/auth/me").then(function (me) {
                if (!(me && me.success && me.data && me.data.permissions && me.data.permissions['designation.manage'])) {
                    window.location.replace("/employee-dashboard");
                    return;
                }
                bindCrudForms(path);
                return apiGet("/v1/hcm/departments?perPage=200").then(function (depPayload) {
                    var deps = depPayload && depPayload.success === true ? (depPayload.data || []) : [];
                    fillDesignationDepartmentSelects(deps);
                    var depFilter = document.querySelector("[data-hcm-designation-department-filter]");
                    if (depFilter) {
                        depFilter.innerHTML = '<option value="">All Departments</option>' + deps.map(function (d) {
                            return '<option value="' + esc(d.id) + '">' + esc(d.name) + '</option>';
                        }).join("");
                    }
                    return bootListModule("designations", "/v1/hcm/designations", renderDesignations, null, null)();
                });
            });
        }
        if (pathMatches(path, "/policy")) {
            return apiGet("/v1/identity/auth/me").then(function (me) {
                if (!(me && me.success && me.data && me.data.permissions && me.data.permissions['policy.manage'])) {
                    window.location.replace("/employee-dashboard");
                    return;
                }
                bindCrudForms(path);
                return apiGet("/v1/hcm/departments?perPage=200").then(function (depPayload) {
                    var deps = depPayload && depPayload.success === true ? (depPayload.data || []) : [];
                    fillPolicyDepartmentSelects(deps);
                    var depFilter = document.querySelector("[data-hcm-policy-department-filter]");
                    if (depFilter) {
                        depFilter.innerHTML = '<option value="">All Departments</option>' + deps.map(function (d) {
                            return '<option value="' + esc(d.id) + '">' + esc(d.name) + '</option>';
                        }).join("");
                    }
                    return bootListModule("policies", "/v1/hcm/policies", renderPolicies, null, null)();
                });
            });
        }
        if (pathMatches(path, "/employee-details") || pathMatches(path, "/employeee-details")) {
            var backLink = document.querySelector("[data-employee-back-link]");
            var returnTo = new URL(window.location.href).searchParams.get("returnTo");
            if (backLink && returnTo) {
                try {
                    var parsed = new URL(returnTo, window.location.origin);
                    if (parsed.origin === window.location.origin) {
                        backLink.setAttribute("href", parsed.pathname + parsed.search + parsed.hash);
                    }
                } catch (_e) {}
            }

            var id = new URL(window.location.href).searchParams.get("id");
            var photoInput = document.querySelector("[data-employee-photo-input]");
            var isSelf = false;

            function applyPhotoButtonSelfVisibility() {
                var _uploadBtn = document.querySelector("[data-employee-photo-upload-btn]");
                var _editBtn = document.querySelector("[data-employee-photo-edit-btn]");
                if (_uploadBtn) _uploadBtn.classList.toggle("d-none", !isSelf);
                if (_editBtn && !isSelf) _editBtn.classList.add("d-none");
            }

            apiGet("/v1/identity/auth/me").then(function (me) {
                if (me && me.success && me.data) {
                    isSelf = (parseInt(id, 10) === me.data.id);
                }
                applyPhotoButtonSelfVisibility();
            }).catch(function () {
                applyPhotoButtonSelfVisibility();
            });

            function showUploadNotice(message, tone) {
                if (window.ArcavUi && typeof window.ArcavUi.showToast === "function") {
                    window.ArcavUi.showToast(message, tone || "info");
                    return;
                }

                var root = document.querySelector("[data-employee-photo-upload-notice]");
                if (!root) {
                    root = document.createElement("div");
                    root.setAttribute("data-employee-photo-upload-notice", "1");
                    root.style.position = "fixed";
                    root.style.top = "16px";
                    root.style.right = "16px";
                    root.style.zIndex = "1090";
                    root.style.maxWidth = "320px";
                    document.body.appendChild(root);
                }

                var box = document.createElement("div");
                var type = tone === "danger" ? "danger" : tone === "success" ? "success" : "warning";
                box.className = "alert alert-" + type + " shadow-sm mb-2";
                box.textContent = String(message || "Informasi upload");
                root.appendChild(box);
                window.setTimeout(function () {
                    box.remove();
                }, 2800);
            }

            function applyUploadedPhoto(photoUrl) {
                if (!photoUrl) {
                    return;
                }
                var photoImg = document.querySelector("[data-employee-photo-preview]");
                if (photoImg) {
                    photoImg.src = photoUrl;
                    photoImg.classList.remove("d-none");
                }
                var modalImg = document.querySelector("[data-employee-photo-modal-image]");
                if (modalImg) {
                    modalImg.src = photoUrl;
                }
                var initialEl = document.querySelector("[data-employee-initial]");
                if (initialEl) {
                    initialEl.classList.add("d-none");
                }
                var editBtn = document.querySelector("[data-employee-photo-edit-btn]");
                if (editBtn && isSelf) {
                    editBtn.classList.remove("d-none");
                }
                var uploadBtn = document.querySelector("[data-employee-photo-upload-btn]");
                if (uploadBtn) {
                    uploadBtn.classList.add("d-none");
                }
                var viewBtn = document.querySelector("[data-employee-photo-view-btn]");
                if (viewBtn) {
                    viewBtn.classList.remove("d-none");
                }
            }

            function refreshEmployeeDetail() {
                if (!id) {
                    return Promise.resolve();
                }
                return apiGet("/v1/hcm/employees/" + encodeURIComponent(id)).then(function (payload) {
                    if (payload && payload.success === true) {
                        renderEmployeeDetail(payload.data || {});
                        applyPhotoButtonSelfVisibility();
                    }
                }).catch(function () {
                    return;
                });
            }

            function uploadProfilePhoto(file) {
                if (!id) {
                    showUploadNotice("Employee ID tidak ditemukan. Buka detail dari list Employees.", "danger");
                    return Promise.resolve();
                }
                if (!file) {
                    return Promise.resolve();
                }
                var mime = String(file.type || "").toLowerCase();
                if (mime && mime.indexOf("image/") !== 0) {
                    showUploadNotice("File harus berupa gambar.", "warning");
                    return Promise.resolve();
                }

                var fd = new FormData();
                fd.append("photo", file);
                showUploadNotice("Uploading photo...", "warning");

                if (window.axios) {
                    return window.axios({
                        method: "post",
                        url: "/v1/hcm/employees/" + encodeURIComponent(id) + "/profile-photo",
                        headers: { Accept: "application/json" },
                        data: fd,
                        withCredentials: true,
                    }).then(function (resp) {
                        var uploadData = resp && resp.data;
                        var photoUrl = uploadData && uploadData.data && uploadData.data.profilePhotoUrl;
                        showUploadNotice("Foto profil berhasil diperbarui.", "success");
                        applyUploadedPhoto(photoUrl);
                        return refreshEmployeeDetail();
                    }).catch(function (err) {
                        var status = err && err.response ? err.response.status : 0;
                        var data = err && err.response ? err.response.data : null;
                        if (window.AuthApi && window.AuthApi.handleUnauthorizedFromApi(status, data)) {
                            return;
                        }
                        showUploadNotice(formatApiError(data, status), "danger");
                    });
                }

                return fetch("/v1/hcm/employees/" + encodeURIComponent(id) + "/profile-photo", {
                    method: "POST",
                    headers: { Accept: "application/json" },
                    credentials: "same-origin",
                    body: fd,
                }).then(function (res) {
                    return res.json().catch(function () { return {}; }).then(function (data) {
                        if (!res.ok) {
                            throw { status: res.status, data: data };
                        }
                        var photoUrl = data && data.data && data.data.profilePhotoUrl;
                        showUploadNotice("Foto profil berhasil diperbarui.", "success");
                        applyUploadedPhoto(photoUrl);
                        return refreshEmployeeDetail();
                    });
                }).catch(function (error) {
                    showUploadNotice(formatApiError(error && error.data, error && error.status), "danger");
                });
            }

            if (document.body.getAttribute("data-employee-photo-bound") !== "1") {
                document.body.setAttribute("data-employee-photo-bound", "1");
                document.addEventListener("click", function (event) {
                    var trigger = event.target.closest("[data-employee-photo-upload-btn], [data-employee-photo-edit-btn]");
                    if (!trigger) {
                        return;
                    }
                    var input = document.querySelector("[data-employee-photo-input]");
                    if (input) {
                        // Ensure selecting the same file still triggers a change event.
                        input.value = "";
                    }
                });

                var handlePickedPhoto = function (input) {
                    if (!input) {
                        return;
                    }
                    var file = input.files && input.files[0] ? input.files[0] : null;
                    if (!file) {
                        return;
                    }
                    uploadProfilePhoto(file).finally(function () {
                        input.value = "";
                    });
                };

                var bindInput = function (input) {
                    if (!input || input.getAttribute("data-upload-bound") === "1") {
                        return;
                    }
                    input.setAttribute("data-upload-bound", "1");
                    input.addEventListener("change", function () {
                        handlePickedPhoto(input);
                    });
                    input.addEventListener("input", function () {
                        handlePickedPhoto(input);
                    });
                };

                bindInput(photoInput);

                document.addEventListener("change", function (event) {
                    var input = event.target && event.target.closest ? event.target.closest("[data-employee-photo-input]") : null;
                    if (!input) {
                        return;
                    }
                    bindInput(input);
                    handlePickedPhoto(input);
                });
            }

            if (!id) {
                renderEmployeeDetail({
                    fullName: "Pilih karyawan",
                    uuid: "—",
                    email: "Buka Employees lalu klik nomor / nama untuk melihat detail.",
                    team: "—",
                    departmentName: "—",
                    designation: "—",
                    joinDate: "—",
                    phone: "—",
                    address: "—",
                    baseSalary: 0,
                    fixedAllowance: 0,
                    bio: "—",
                    reportOffice: "—",
                    bank: {},
                    educationItems: [],
                    experienceItems: [],
                    emergencyContacts: [],
                    schedule: {},
                });
                var uploadBtn = document.querySelector("[data-employee-photo-upload-btn]");
                var editBtn = document.querySelector("[data-employee-photo-edit-btn]");
                var viewBtn = document.querySelector("[data-employee-photo-view-btn]");
                if (uploadBtn) {
                    uploadBtn.classList.add("disabled");
                    uploadBtn.setAttribute("aria-disabled", "true");
                }
                if (editBtn) {
                    editBtn.classList.add("disabled");
                    editBtn.setAttribute("aria-disabled", "true");
                }
                if (viewBtn) {
                    viewBtn.classList.add("disabled");
                    viewBtn.setAttribute("aria-disabled", "true");
                }
                if (photoInput) {
                    photoInput.setAttribute("disabled", "disabled");
                }
                return Promise.resolve();
            }
            return apiGet("/v1/hcm/employees/" + encodeURIComponent(id)).then(function (payload) {
                if (!payload || payload.success !== true) {
                    var msg = (payload && payload.error && payload.error.message) ? payload.error.message : "Tidak dapat memuat data karyawan.";
                    renderEmployeeDetail({
                        fullName: "Error",
                        uuid: "—",
                        email: msg,
                        team: "—",
                        departmentName: "—",
                        designation: "—",
                        joinDate: "—",
                        phone: "—",
                        address: "—",
                        baseSalary: 0,
                        fixedAllowance: 0,
                        bio: "—",
                        reportOffice: "—",
                        bank: {},
                        educationItems: [],
                        experienceItems: [],
                        emergencyContacts: [],
                        schedule: {},
                    });
                    return;
                }
                return Promise.all([
                    Promise.resolve(payload.data),
                    apiGetSafe("/v1/hcm/training/users/" + encodeURIComponent(id) + "/trainings?perPage=20", { success: false, data: [] }),
                    apiGetSafe("/v1/hcm/promotions/users/" + encodeURIComponent(id) + "/promotions?perPage=20", { success: false, data: [] }),
                    apiGetSafe("/v1/hcm/resignations/users/" + encodeURIComponent(id) + "/resignations?perPage=20", { success: false, data: [] }),
                    apiGetSafe("/v1/hcm/terminations/users/" + encodeURIComponent(id) + "/terminations?perPage=20", { success: false, data: [] })
                ]).then(function (results) {
                    var item = results[0] || {};
                    var trainingPayload = results[1];
                    var promotionPayload = results[2];
                    var resignationPayload = results[3];
                    var terminationPayload = results[4];
                    item.trainingItems = trainingPayload && trainingPayload.success === true ? (trainingPayload.data || []) : [];
                    item.promotionItems = promotionPayload && promotionPayload.success === true ? (promotionPayload.data || []) : [];
                    item.resignationItems = resignationPayload && resignationPayload.success === true ? (resignationPayload.data || []) : [];
                    item.terminationItems = terminationPayload && terminationPayload.success === true ? (terminationPayload.data || []) : [];
                    renderEmployeeDetail(item);
                    applyPhotoButtonSelfVisibility();
                }).catch(function () {
                    renderEmployeeDetail(payload.data || {});
                    applyPhotoButtonSelfVisibility();
                });
            }).catch(function (error) {
                var message = formatApiError(error && error.data, error && error.status) || "Tidak dapat memuat data karyawan.";
                renderEmployeeDetail({
                    fullName: "Error",
                    uuid: "—",
                    email: message,
                    team: "—",
                    departmentName: "—",
                    designation: "—",
                    joinDate: "—",
                    phone: "—",
                    address: "—",
                    baseSalary: 0,
                    fixedAllowance: 0,
                    bio: "—",
                    reportOffice: "—",
                    bank: {},
                    educationItems: [],
                    experienceItems: [],
                    emergencyContacts: [],
                    schedule: {},
                });
            });
        }
        return Promise.resolve();
    }

    function bootEmployeePages() {
        init().catch(function (error) {
            console.error(error);
        });
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", bootEmployeePages);
    } else {
        bootEmployeePages();
    }

    window.addEventListener("pageshow", function () {
        bootEmployeePages();
    });
})(window, document);
