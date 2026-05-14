(function (window, document) {
    "use strict";
    var terminationUtils = window.ArcavTerminationUtils || {};
    var esc = terminationUtils.esc || function (v) { return String(v || ""); };
    var notify = terminationUtils.notify || function () {};
    var flash = terminationUtils.flash || function () {};
    var clearFlash = terminationUtils.clearFlash || function () {};
    var redirectToEmployeeDashboard = terminationUtils.redirectToEmployeeDashboard || function () {};
    var hasMePermission = terminationUtils.hasMePermission || function () { return false; };
    var getTenantContext = terminationUtils.getTenantContext || function () { return {}; };
    var buildHeaders = terminationUtils.buildHeaders || function () { return { Accept: "application/json" }; };
    var formatSaveError = terminationUtils.formatSaveError || function () { return "Save failed."; };
    var apiRequest = terminationUtils.apiRequest || function () { return Promise.reject({ status: 500, data: { message: "Termination utils not loaded" } }); };
    var shortReason = terminationUtils.shortReason || function (text) { return String(text || ""); };
    var statusBadgeClass = terminationUtils.statusBadgeClass || function () { return "secondary"; };

    function deriveWorkflowStageFromStatus(status) {
        var s = String(status || "pending").toLowerCase();
        if (s === "approved") return "approved_internal";
        if (s === "finalized") return "finalized_execution";
        if (s === "cancelled") return "cancelled";
        return "draft_review";
    }

    function deriveStatusFromWorkflowStage(stage) {
        var s = String(stage || "draft_review").toLowerCase();
        if (s === "approved_internal") return "approved";
        if (s === "finalized_execution") return "finalized";
        if (s === "cancelled") return "cancelled";
        return "pending";
    }

    function formatWorkflowAuditEntry(entry) {
        if (!entry) return "—";
        var user = entry.user || {};
        var parts = [];
        if (user.name) {
            parts.push(String(user.name));
        } else if (user.email) {
            parts.push(String(user.email));
        }
        if (entry.at) {
            parts.push(String(entry.at));
        }
        return parts.length ? parts.join(" · ") : "—";
    }

    function syncWorkflowFromStatus() {
        if (!statusSelect) return;
        var derivedWorkflow = deriveWorkflowStageFromStatus(statusSelect.value);
        if (workflowStageSelect) {
            workflowStageSelect.value = derivedWorkflow;
        }
        toggleFinalizationFields(statusSelect.value);
    }

    function syncStatusFromWorkflow() {
        var derivedStatus = deriveStatusFromWorkflowStage(workflowStageSelect ? workflowStageSelect.value : "draft_review");
        if (statusSelect) {
            statusSelect.value = derivedStatus;
        }
        toggleFinalizationFields(derivedStatus);
    }

    function parseAmount(value) {
        if (value === null || value === undefined || value === "") {
            return null;
        }
        var parsed = Number(value);
        return Number.isFinite(parsed) ? parsed : null;
    }

    function formatRupiah(value) {
        var amount = parseAmount(value);
        if (amount === null) return "—";
        try {
            return new Intl.NumberFormat("id-ID", {
                style: "currency",
                currency: "IDR",
                maximumFractionDigits: 2,
            }).format(amount);
        } catch (_error) {
            return "Rp " + amount.toFixed(2);
        }
    }

    function settlementSummary(settlement) {
        if (!settlement) return "";
        var parts = [];
        if (settlement.payrollPeriod) {
            parts.push("Payroll " + esc(settlement.payrollPeriod));
        }
        if (settlement.finalNetAmount !== null && settlement.finalNetAmount !== undefined && settlement.finalNetAmount !== "") {
            parts.push("Net " + esc(formatRupiah(settlement.finalNetAmount)));
        }
        if (settlement.assetReturnNotes) {
            parts.push("Asset: " + esc(shortReason(settlement.assetReturnNotes)));
        }
        return parts.join(" • ");
    }

    function previewSourceLabel(source) {
        var s = String(source || "");
        if (s === "estimated_compensation_snapshot") return "Compensation snapshot";
        if (s === "termination_policy_prorated") return "Termination proration policy";
        if (s === "termination_policy_prorated_plus_payroll_reference") return "Termination proration + payroll reference";
        if (s === "termination_policy_prorated_plus_pkwt") return "Termination proration + PKWT compensation";
        if (s === "payroll_run_finalized") return "Finalized payroll run";
        if (s === "payroll_run_draft") return "Draft payroll run";
        return s || "—";
    }

    function breakdownLineHtml(item) {
        if (!item) {
            return '<div class="list-group-item text-muted small">No settlement breakdown.</div>';
        }
        return '' +
            '<div class="list-group-item">' +
                '<div class="d-flex justify-content-between gap-3">' +
                    '<div>' +
                        '<div class="fw-semibold small">' + esc(item.componentName || item.componentCode || 'Component') + '</div>' +
                        '<div class="text-muted small">' + esc(item.bucket || item.kind || 'item') + '</div>' +
                    '</div>' +
                    '<div class="fw-semibold small text-end">' + esc(formatRupiah(item.amount)) + '</div>' +
                '</div>' +
            '</div>';
    }

    function clearanceLineHtml(item, options) {
        var opts = options || {};
        if (!item) {
            return '<div class="list-group-item text-muted small">No outstanding clearance items.</div>';
        }
        var showReturnAction = !!opts.actionable && !!opts.terminationId && String(item.status || '') === 'pending_return' && item.assignmentId;
        var actionHtml = showReturnAction
            ? '<button type="button" class="btn btn-sm btn-outline-primary mt-2" data-arcav-termination-clearance-return="' + esc(item.assignmentId) + '" data-arcav-termination-clearance-termination="' + esc(opts.terminationId) + '">Mark returned</button>'
            : '';
        return '' +
            '<div class="list-group-item">' +
                '<div class="d-flex justify-content-between gap-3">' +
                    '<div>' +
                        '<div class="fw-semibold small">' + esc(item.assetCode || 'Asset') + ' · ' + esc(item.assetName || 'Unnamed asset') + '</div>' +
                        '<div class="text-muted small">Assigned ' + esc(item.assignedDate || '—') + '</div>' +
                        actionHtml +
                    '</div>' +
                    '<div class="text-muted small text-end">' + esc(item.status || 'pending_return') + '</div>' +
                '</div>' +
            '</div>';
    }

    var tbody = document.querySelector("[data-arcav-terminations-tbody]");
    var addBtn = document.querySelector("[data-arcav-termination-add]");

    var modalEl = document.getElementById("arcav_termination_modal");
    var modal = modalEl && window.bootstrap && window.bootstrap.Modal ? window.bootstrap.Modal.getOrCreateInstance(modalEl) : null;
    var form = modalEl ? modalEl.querySelector("[data-arcav-termination-form]") : null;
    var modalTitle = modalEl ? modalEl.querySelector("[data-arcav-termination-modal-title]") : null;
    var flashEl = modalEl ? modalEl.querySelector("[data-arcav-termination-flash]") : null;
    var idInput = modalEl ? modalEl.querySelector("[data-arcav-termination-id]") : null;
    var userSelect = modalEl ? modalEl.querySelector("[data-arcav-termination-user]") : null;
    var workflowStageSelect = modalEl ? modalEl.querySelector("[data-arcav-termination-workflow-stage]") : null;
    var typeInput = modalEl ? modalEl.querySelector("[data-arcav-termination-type]") : null;
    var reasonCodeSelect = modalEl ? modalEl.querySelector("[data-arcav-termination-reason-code]") : null;
    var legalBasisCodeSelect = modalEl ? modalEl.querySelector("[data-arcav-termination-legal-basis-code]") : null;
    var noticeInput = modalEl ? modalEl.querySelector("[data-arcav-termination-notice-date]") : null;
    var termDateInput = modalEl ? modalEl.querySelector("[data-arcav-termination-termination-date]") : null;
    var deptInput = modalEl ? modalEl.querySelector("[data-arcav-termination-department]") : null;
    var reasonInput = modalEl ? modalEl.querySelector("[data-arcav-termination-reason]") : null;
    var notesInput = modalEl ? modalEl.querySelector("[data-arcav-termination-notes]") : null;
    var statusSelect = modalEl ? modalEl.querySelector("[data-arcav-termination-status]") : null;
    var finalizationFieldsWrap = modalEl ? modalEl.querySelector("[data-arcav-termination-finalization-fields]") : null;
    var settlementPayrollPeriodInput = modalEl ? modalEl.querySelector("[data-arcav-termination-settlement-payroll-period]") : null;
    var finalSalaryAmountInput = modalEl ? modalEl.querySelector("[data-arcav-termination-final-salary-amount]") : null;
    var finalAllowanceAmountInput = modalEl ? modalEl.querySelector("[data-arcav-termination-final-allowance-amount]") : null;
    var finalDeductionAmountInput = modalEl ? modalEl.querySelector("[data-arcav-termination-final-deduction-amount]") : null;
    var assetReturnNotesInput = modalEl ? modalEl.querySelector("[data-arcav-termination-asset-return-notes]") : null;
    var clearanceNotesInput = modalEl ? modalEl.querySelector("[data-arcav-termination-clearance-notes]") : null;
    var previewSettlementBtn = modalEl ? modalEl.querySelector("[data-arcav-termination-preview-settlement]") : null;
    var previewFlashEl = modalEl ? modalEl.querySelector("[data-arcav-termination-preview-flash]") : null;
    var previewWrap = modalEl ? modalEl.querySelector("[data-arcav-termination-preview-wrap]") : null;
    var previewPeriodEl = modalEl ? modalEl.querySelector("[data-arcav-termination-preview-period]") : null;
    var previewSourceEl = modalEl ? modalEl.querySelector("[data-arcav-termination-preview-source]") : null;
    var previewNetEl = modalEl ? modalEl.querySelector("[data-arcav-termination-preview-net]") : null;
    var previewBreakdownEl = modalEl ? modalEl.querySelector("[data-arcav-termination-preview-breakdown]") : null;
    var previewClearanceEl = modalEl ? modalEl.querySelector("[data-arcav-termination-preview-clearance]") : null;

    var employeeOptionsLoaded = false;
    var employeeDetailCache = {};
    var canManageTermination = false;
    var latestSettlementPreview = null;
    var currentDetailTerminationId = null;

    function toggleFinalizationFields(status) {
        var isFinalized = String(status || "pending") === "finalized";
        if (finalizationFieldsWrap) {
            finalizationFieldsWrap.classList.toggle("d-none", !isFinalized);
        }
        if (!isFinalized) {
            latestSettlementPreview = null;
            clearFlash(previewFlashEl);
            if (previewWrap) previewWrap.classList.add("d-none");
        }
        if (previewSettlementBtn) {
            previewSettlementBtn.disabled = !isFinalized;
        }
    }

    function renderSettlementPreview(preview) {
        latestSettlementPreview = preview || null;
        if (!preview || !previewWrap) {
            if (previewWrap) previewWrap.classList.add("d-none");
            return;
        }

        previewWrap.classList.remove("d-none");
        if (previewPeriodEl) {
            var period = preview.resolvedPeriod || {};
            var periodText = period.label || "—";
            if (period.status) {
                periodText += " (" + period.status + ")";
            }
            previewPeriodEl.textContent = "Payroll period: " + periodText;
        }
        if (previewSourceEl) {
            previewSourceEl.textContent = "Source: " + previewSourceLabel(preview.source);
        }
        if (previewNetEl) {
            var summary = preview.summary || {};
            previewNetEl.textContent = "Net: " + formatRupiah(summary.finalNetAmount);
        }
        if (previewBreakdownEl) {
            var breakdown = Array.isArray(preview.breakdown) ? preview.breakdown : [];
            previewBreakdownEl.innerHTML = breakdown.length
                ? breakdown.map(function (item) { return breakdownLineHtml(item); }).join("")
                : breakdownLineHtml(null);
        }
        if (previewClearanceEl) {
            var previewTerminationId = idInput && idInput.value ? String(idInput.value) : "";
            var clearance = preview.clearance && Array.isArray(preview.clearance.items) ? preview.clearance.items : [];
            previewClearanceEl.innerHTML = clearance.length
                ? clearance.map(function (item) {
                    return clearanceLineHtml(item, {
                        actionable: canManageTermination,
                        terminationId: previewTerminationId,
                    });
                }).join("")
                : clearanceLineHtml(null, null);
        }
    }

    function previewFromSettlement(settlement) {
        if (!settlement) return null;
        return {
            resolvedPeriod: {
                label: settlement.payrollPeriod,
                status: settlement.payrollPeriodStatus,
            },
            source: 'termination_snapshot',
            summary: {
                finalSalaryAmount: settlement.finalSalaryAmount,
                finalAllowanceAmount: settlement.finalAllowanceAmount,
                finalDeductionAmount: settlement.finalDeductionAmount,
                finalNetAmount: settlement.finalNetAmount,
            },
            breakdown: Array.isArray(settlement.breakdown) ? settlement.breakdown : [],
            clearance: {
                items: Array.isArray(settlement.clearanceItems) ? settlement.clearanceItems : [],
                summaryNotes: settlement.assetReturnNotes || '',
            },
        };
    }

    function applySettlementPreview(preview) {
        if (!preview) return;
        var summary = preview.summary || {};
        var period = preview.resolvedPeriod || {};
        if (settlementPayrollPeriodInput) settlementPayrollPeriodInput.value = period.label || "";
        if (finalSalaryAmountInput) finalSalaryAmountInput.value = summary.finalSalaryAmount || "";
        if (finalAllowanceAmountInput) finalAllowanceAmountInput.value = summary.finalAllowanceAmount || "";
        if (finalDeductionAmountInput) finalDeductionAmountInput.value = summary.finalDeductionAmount || "";
        if (assetReturnNotesInput && !String(assetReturnNotesInput.value || "").trim()) {
            assetReturnNotesInput.value = preview.clearance && preview.clearance.summaryNotes ? preview.clearance.summaryNotes : "";
        }
    }

    function settlementPreviewRequestUrl() {
        var id = idInput && idInput.value ? String(idInput.value) : "";
        if (id) {
            return "/v1/hcm/terminations/" + encodeURIComponent(id) + "/settlement-preview";
        }
        var selectedOption = userSelect && userSelect.options ? userSelect.options[userSelect.selectedIndex] : null;
        var userUuid = selectedOption ? String(selectedOption.getAttribute("data-user-uuid") || "").trim() : "";
        if (!userUuid) return "";
        var url = new URL('/v1/hcm/terminations/settlement-preview', window.location.origin);
        url.searchParams.set('userId', userUuid);
        if (termDateInput && termDateInput.value) {
            url.searchParams.set('terminationDate', termDateInput.value);
        }
        return url.pathname + url.search;
    }

    function refreshSettlementPreview() {
        var url = settlementPreviewRequestUrl();
        if (!url) {
            flash(previewFlashEl, 'Pilih employee dan termination date dulu untuk mengambil preview settlement.', true);
            return Promise.resolve();
        }
        clearFlash(previewFlashEl);
        if (previewSettlementBtn) {
            previewSettlementBtn.disabled = true;
            previewSettlementBtn.textContent = 'Refreshing…';
        }
        return apiRequest("get", url, null).then(function (res) {
            if (!res || !res.success || !res.data) {
                flash(previewFlashEl, 'Tidak dapat memuat preview settlement.', true);
                return;
            }
            renderSettlementPreview(res.data);
            applySettlementPreview(res.data);
            flash(previewFlashEl, 'Settlement preview diperbarui dari payroll dan asset clearance.', false);
        }).catch(function (err) {
            flash(previewFlashEl, formatSaveError(err && err.status ? err.status : 0, err && err.data ? err.data : null), true);
        }).finally(function () {
            if (previewSettlementBtn) {
                previewSettlementBtn.disabled = false;
                previewSettlementBtn.textContent = 'Refresh from payroll & assets';
            }
        });
    }

    function loadEmployeeOptions() {
        if (!userSelect || employeeOptionsLoaded) return Promise.resolve();
        return apiRequest("get", "/v1/hcm/employees?perPage=100", null).then(function (res) {
            if (!res || !res.success) {
                userSelect.innerHTML = '<option value="">Failed to load</option>';
                return;
            }
            var rows = Array.isArray(res.data) ? res.data : [];
            if (!rows.length) {
                userSelect.innerHTML = '<option value="">No employees</option>';
                employeeOptionsLoaded = true;
                return;
            }
            userSelect.innerHTML = '<option value="">Select employee…</option>' + rows.map(function (u) {
                return '<option value="' + esc(u.id) + '" data-user-uuid="' + esc(u.uuid || '') + '">' + esc(u.fullName || u.name || ("User " + u.id)) + (u.email ? (" — " + esc(u.email)) : "") + "</option>";
            }).join("");
            employeeOptionsLoaded = true;
        }).catch(function () {
            userSelect.innerHTML = '<option value="">Failed to load</option>';
        });
    }

    function getEmployeeDetail(userId) {
        var id = String(userId || "");
        if (!id) return Promise.resolve(null);
        if (employeeDetailCache[id]) return Promise.resolve(employeeDetailCache[id]);
        return apiRequest("get", "/v1/hcm/employees/" + encodeURIComponent(id), null).then(function (res) {
            var data = res && res.success ? (res.data || null) : null;
            employeeDetailCache[id] = data;
            return data;
        }).catch(function () {
            return null;
        });
    }

    function autoFillDepartment(userId) {
        if (!deptInput) return Promise.resolve();
        return getEmployeeDetail(userId).then(function (emp) {
            if (!emp) return;
            var team = (emp.team && emp.team !== "-" && emp.team !== "—") ? emp.team : "";
            deptInput.value = team;
        });
    }

    function renderRows(rows) {
        if (!tbody) return;
        if (!rows.length) {
            tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-4">No terminations found.</td></tr>';
            return;
        }
        tbody.innerHTML = rows.map(function (r) {
            var emp = r.employee || {};
            var name = emp.name || "—";
            var dept = r.department || "—";
            var ttype = r.terminationType || "—";
            var reason = shortReason(r.reason);
            var notice = r.noticeDate || "—";
            var tdate = r.terminationDate || "—";
            var st = String(r.status || "pending");
            var badge = statusBadgeClass(st);
            var finalizedMeta = st === "finalized" && r.settlement
                ? '<div class="small text-muted mt-1" data-arcav-termination-finalized-summary="' + esc(r.id) + '">' + settlementSummary(r.settlement) + '</div>'
                : '';
            var actions = '<a href="#" class="me-2" title="Detail" data-arcav-termination-view="' + esc(r.id) + '"><i class="ti ti-eye"></i></a>';
            if (canManageTermination) {
                actions += '<a href="#" class="me-2" data-arcav-termination-edit="' + esc(r.id) + '"><i class="ti ti-edit"></i></a>' +
                    '<a href="#" data-arcav-termination-delete="' + esc(r.id) + '"><i class="ti ti-trash"></i></a>';
            }
            return (
                '<tr data-termination-row="' + esc(r.id) + '">' +
                '<td><div class="d-flex align-items-center"><div class="avatar avatar-md me-2 bg-light text-dark d-flex align-items-center justify-content-center rounded-circle">' + esc(String(name).trim().slice(0, 1).toUpperCase() || "U") + '</div>' +
                '<div><h6 class="fw-medium mb-0">' + esc(name) + '</h6><small class="text-muted">' + esc(emp.email || "") + '</small></div></div></td>' +
                "<td>" + esc(dept) + "</td>" +
                "<td>" + esc(ttype) + "</td>" +
                '<td class="text-break">' + esc(reason) + finalizedMeta + "</td>" +
                "<td>" + esc(notice) + "</td>" +
                "<td>" + esc(tdate) + "</td>" +
                '<td><span class="badge badge-' + esc(badge) + ' d-inline-flex align-items-center badge-xs"><i class="ti ti-point-filled me-1"></i>' + esc(st) + "</span></td>" +
                '<td><div class="action-icon d-inline-flex">' + actions + "</div></td>" +
                "</tr>"
            );
        }).join("");
    }

    function loadList() {
        if (!tbody) return;
        tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-4">Loading…</td></tr>';
        apiRequest("get", "/v1/hcm/terminations", null).then(function (res) {
            if (!res || !res.success) {
                renderRows([]);
                return;
            }
            renderRows(Array.isArray(res.data) ? res.data : []);
        }).catch(function () {
            tbody.innerHTML = '<tr><td colspan="8" class="text-center text-danger py-4">Failed to load terminations.</td></tr>';
        });
    }

    function openModal(row) {
        if (!modal || !form) return;
        clearFlash(flashEl);
        clearFlash(previewFlashEl);
        latestSettlementPreview = null;
        if (modalTitle) modalTitle.textContent = row ? "Edit Termination" : "Add Termination";
        if (idInput) idInput.value = row ? String(row.id) : "";
        if (workflowStageSelect) workflowStageSelect.value = row ? (row.workflowStage || deriveWorkflowStageFromStatus(row.status || "pending")) : "draft_review";
        if (typeInput) typeInput.value = row ? (row.terminationType || "") : "";
        if (reasonCodeSelect) reasonCodeSelect.value = row ? (row.terminationReasonCode || "") : "";
        if (legalBasisCodeSelect) legalBasisCodeSelect.value = row ? (row.legalBasisCode || "") : "";
        if (noticeInput) noticeInput.value = row ? (row.noticeDate || "") : "";
        if (termDateInput) termDateInput.value = row ? (row.terminationDate || "") : "";
        if (reasonInput) reasonInput.value = row ? (row.reason || "") : "";
        if (notesInput) notesInput.value = row ? (row.notes || "") : "";
        if (statusSelect) {
            statusSelect.value = row ? String(row.status || deriveStatusFromWorkflowStage(workflowStageSelect ? workflowStageSelect.value : "draft_review")) : deriveStatusFromWorkflowStage(workflowStageSelect ? workflowStageSelect.value : "draft_review");
        }
        if (settlementPayrollPeriodInput) settlementPayrollPeriodInput.value = row && row.settlement ? (row.settlement.payrollPeriod || "") : "";
        if (finalSalaryAmountInput) finalSalaryAmountInput.value = row && row.settlement ? (row.settlement.finalSalaryAmount || "") : "";
        if (finalAllowanceAmountInput) finalAllowanceAmountInput.value = row && row.settlement ? (row.settlement.finalAllowanceAmount || "") : "";
        if (finalDeductionAmountInput) finalDeductionAmountInput.value = row && row.settlement ? (row.settlement.finalDeductionAmount || "") : "";
        if (assetReturnNotesInput) assetReturnNotesInput.value = row && row.settlement ? (row.settlement.assetReturnNotes || "") : "";
        if (clearanceNotesInput) clearanceNotesInput.value = row && row.settlement ? (row.settlement.clearanceNotes || "") : "";
        toggleFinalizationFields(statusSelect ? statusSelect.value : deriveStatusFromWorkflowStage(workflowStageSelect ? workflowStageSelect.value : "draft_review"));
        if (row && row.settlement) {
            renderSettlementPreview(previewFromSettlement(row.settlement));
        } else {
            renderSettlementPreview(null);
        }
        if (deptInput) {
            deptInput.disabled = true;
            deptInput.value = row ? (row.department || "") : "";
        }

        loadEmployeeOptions().then(function () {
            if (userSelect) {
                userSelect.disabled = !!row;
                userSelect.value = row && row.employee ? String(row.employee.id) : "";
            }
            if (!row && userSelect && userSelect.value) {
                return autoFillDepartment(userSelect.value);
            }
            return Promise.resolve();
        }).finally(function () {
            modal.show();
        });
    }

    if (addBtn) {
        addBtn.addEventListener("click", function (e) {
            e.preventDefault();
            openModal(null);
        });
    }

    if (userSelect) {
        userSelect.addEventListener("change", function () {
            if (userSelect.disabled) return;
            var v = userSelect.value;
            if (!v) {
                if (deptInput) deptInput.value = "";
                return;
            }
            autoFillDepartment(v);
        });
    }

    if (statusSelect) {
        statusSelect.addEventListener("change", function () {
            syncWorkflowFromStatus();
        });
    }

    if (workflowStageSelect) {
        workflowStageSelect.addEventListener("change", function () {
            syncStatusFromWorkflow();
        });
    }

    if (previewSettlementBtn) {
        previewSettlementBtn.addEventListener('click', function (e) {
            e.preventDefault();
            if (String(statusSelect && statusSelect.value ? statusSelect.value : 'pending') !== 'finalized') {
                return;
            }
            refreshSettlementPreview();
        });
    }

    if (tbody) {
        tbody.addEventListener("click", function (e) {
            var t = e.target;
            if (!t) return;
            var view = t.closest && t.closest("[data-arcav-termination-view]");
            var edit = t.closest && t.closest("[data-arcav-termination-edit]");
            var del = t.closest && t.closest("[data-arcav-termination-delete]");
            if (view) {
                return;
            }
            if (edit) {
                e.preventDefault();
                if (!canManageTermination) {
                    return;
                }
                var id = edit.getAttribute("data-arcav-termination-edit");
                apiRequest("get", "/v1/hcm/terminations?perPage=100", null).then(function (res) {
                    var rows = res && res.success && Array.isArray(res.data) ? res.data : [];
                    var r = rows.find(function (x) { return String(x.id) === String(id); }) || null;
                    openModal(r);
                }).catch(function () {
                    notify("Failed to load termination.", true);
                });
                return;
            }
            if (del) {
                e.preventDefault();
                if (!canManageTermination) {
                    return;
                }
                var did = del.getAttribute("data-arcav-termination-delete");
                var go = function () {
                    apiRequest("delete", "/v1/hcm/terminations/" + encodeURIComponent(String(did)), null).then(function (res) {
                        if (!res) return;
                        if (res.success) {
                            notify("Termination deleted.");
                            loadList();
                            return;
                        }
                        notify("Failed to delete termination.", true);
                    }).catch(function () {
                        notify("Failed to delete termination.", true);
                    });
                };
                if (window.ArcavUi && typeof window.ArcavUi.confirmDelete === "function") {
                    window.ArcavUi.confirmDelete("Hapus termination ini?", "Delete Termination").then(function (ok) {
                        if (ok) go();
                    });
                } else {
                    go();
                }
            }
        });
    }

    if (form) {
        form.addEventListener("submit", function (e) {
            e.preventDefault();
            if (!canManageTermination) {
                flash(flashEl, "Anda tidak memiliki izin untuk mengubah termination.", true);
                return;
            }
            clearFlash(flashEl);
            if (!form.checkValidity()) {
                form.reportValidity();
                flash(flashEl, "Mohon lengkapi field yang wajib.", true);
                return;
            }
            var nd = noticeInput ? String(noticeInput.value || "") : "";
            var td = termDateInput ? String(termDateInput.value || "") : "";
            if (nd && td && nd > td) {
                flash(flashEl, "Termination date harus pada atau setelah notice date.", true);
                return;
            }
            var ttype = typeInput ? String(typeInput.value || "").trim() : "";
            var workflowStage = workflowStageSelect ? String(workflowStageSelect.value || "draft_review").trim() : "draft_review";
            var terminationReasonCode = reasonCodeSelect ? String(reasonCodeSelect.value || "").trim() : "";
            var legalBasisCode = legalBasisCodeSelect ? String(legalBasisCodeSelect.value || "").trim() : "";
            var dept = deptInput ? String(deptInput.value || "").trim() : "";
            var reason = reasonInput ? String(reasonInput.value || "").trim() : "";
            var notes = notesInput ? String(notesInput.value || "") : "";
            var st = statusSelect ? String(statusSelect.value || deriveStatusFromWorkflowStage(workflowStage)) : deriveStatusFromWorkflowStage(workflowStage);
            workflowStage = deriveWorkflowStageFromStatus(st || workflowStage);
            var settlementPayrollPeriod = settlementPayrollPeriodInput ? String(settlementPayrollPeriodInput.value || "").trim() : "";
            var finalSalaryAmount = finalSalaryAmountInput ? String(finalSalaryAmountInput.value || "").trim() : "";
            var finalAllowanceAmount = finalAllowanceAmountInput ? String(finalAllowanceAmountInput.value || "").trim() : "";
            var finalDeductionAmount = finalDeductionAmountInput ? String(finalDeductionAmountInput.value || "").trim() : "";
            var assetReturnNotes = assetReturnNotesInput ? String(assetReturnNotesInput.value || "") : "";
            var clearanceNotes = clearanceNotesInput ? String(clearanceNotesInput.value || "") : "";
            if (!ttype) {
                flash(flashEl, "Termination type wajib diisi.", true);
                return;
            }
            if (ttype.length > 150) {
                flash(flashEl, "Termination type maksimal 150 karakter.", true);
                return;
            }
            if (dept.length > 150) {
                flash(flashEl, "Department maksimal 150 karakter.", true);
                return;
            }
            if (reason.length > 2000) {
                flash(flashEl, "Reason maksimal 2000 karakter.", true);
                return;
            }
            if (notes.length > 2000) {
                flash(flashEl, "Notes maksimal 2000 karakter.", true);
                return;
            }
            if (assetReturnNotes.length > 2000) {
                flash(flashEl, "Asset return notes maksimal 2000 karakter.", true);
                return;
            }
            if (clearanceNotes.length > 2000) {
                flash(flashEl, "Clearance notes maksimal 2000 karakter.", true);
                return;
            }
            if (st === "finalized") {
                if (!clearanceNotes.trim()) {
                    flash(flashEl, "Status finalized wajib dilengkapi clearance notes.", true);
                    return;
                }
            }
            var id = idInput && idInput.value ? String(idInput.value) : "";
            var payload = {
                terminationType: ttype,
                workflowStage: workflowStage,
                terminationReasonCode: terminationReasonCode || null,
                legalBasisCode: legalBasisCode || null,
                noticeDate: nd,
                terminationDate: td,
                reason: reason,
                notes: notes || null,
                status: st,
                settlementPayrollPeriod: settlementPayrollPeriod || null,
                finalSalaryAmount: finalSalaryAmount || null,
                finalAllowanceAmount: finalAllowanceAmount || null,
                finalDeductionAmount: finalDeductionAmount || null,
                assetReturnNotes: assetReturnNotes || null,
                clearanceNotes: clearanceNotes || null,
            };
            if (st === 'finalized' && latestSettlementPreview) {
                payload.settlementBreakdown = Array.isArray(latestSettlementPreview.breakdown) ? latestSettlementPreview.breakdown : [];
                payload.clearanceItems = latestSettlementPreview.clearance && Array.isArray(latestSettlementPreview.clearance.items)
                    ? latestSettlementPreview.clearance.items
                    : [];
            }
            if (dept) {
                payload.department = dept;
            } else {
                payload.department = null;
            }
            if (!id) {
                var selectedOption = userSelect && userSelect.options ? userSelect.options[userSelect.selectedIndex] : null;
                payload.userId = selectedOption ? String(selectedOption.getAttribute("data-user-uuid") || "").trim() : "";
                if (!payload.userId) {
                    flash(flashEl, "Employee wajib dipilih.", true);
                    return;
                }
            }
            var method = id ? "put" : "post";
            var url = id ? ("/v1/hcm/terminations/" + encodeURIComponent(id)) : "/v1/hcm/terminations";
            apiRequest(method, url, payload).then(function (res) {
                if (!res) return;
                if (res.success) {
                    flash(flashEl, "Saved.", false);
                    window.setTimeout(function () {
                        if (modal) modal.hide();
                        loadList();
                    }, 400);
                    return;
                }
                flash(flashEl, (res.error && res.error.message) ? res.error.message : "Save failed.", true);
            }).catch(function (err) {
                var stx = err && err.status ? err.status : 0;
                var d = err && err.data ? err.data : null;
                flash(flashEl, formatSaveError(stx, d), true);
            });
        });
    }

    var detailModalEl = document.getElementById("arcav_termination_detail_modal");
    var detailModal = detailModalEl && window.bootstrap && window.bootstrap.Modal
        ? window.bootstrap.Modal.getOrCreateInstance(detailModalEl)
        : null;

    function openTerminationDetail(terminationId) {
        if (!detailModalEl || !detailModal) return;
        currentDetailTerminationId = String(terminationId || '') || null;
        var errEl = detailModalEl.querySelector("[data-arcav-termination-detail-error]");
        var bodyWrap = detailModalEl.querySelector("[data-arcav-termination-detail-body]");
        var loadingEl = detailModalEl.querySelector("[data-arcav-termination-detail-loading]");
        if (errEl) {
            errEl.classList.add("d-none");
            errEl.textContent = "";
        }
        if (bodyWrap) bodyWrap.classList.add("d-none");
        if (loadingEl) loadingEl.classList.remove("d-none");
        detailModal.show();
        apiRequest("get", "/v1/hcm/terminations/" + encodeURIComponent(String(terminationId)), null).then(function (res) {
            if (loadingEl) loadingEl.classList.add("d-none");
            if (!res || !res.success || !res.data) {
                if (errEl) {
                    errEl.textContent = (res && res.error && res.error.message) ? res.error.message : "Tidak dapat memuat termination.";
                    errEl.classList.remove("d-none");
                }
                return;
            }
            var d = res.data;
            var emp = d.employee || {};
            function setDetail(sel, text) {
                var el = detailModalEl.querySelector(sel);
                if (el) el.textContent = text || "—";
            }
            setDetail("[data-arcav-termination-detail-employee]", emp.name || "—");
            setDetail("[data-arcav-termination-detail-email]", emp.email || "—");
            setDetail("[data-arcav-termination-detail-department]", d.department || "—");
            setDetail("[data-arcav-termination-detail-type]", d.terminationType || "—");
            setDetail("[data-arcav-termination-detail-reason-code]", d.terminationReasonCode || "—");
            setDetail("[data-arcav-termination-detail-legal-basis-code]", d.legalBasisCode || "—");
            setDetail("[data-arcav-termination-detail-workflow-stage]", d.workflowStage || deriveWorkflowStageFromStatus(d.status || "pending"));
            setDetail("[data-arcav-termination-detail-status]", d.status || "—");
            setDetail("[data-arcav-termination-detail-notice-date]", d.noticeDate || "—");
            setDetail("[data-arcav-termination-detail-termination-date]", d.terminationDate || "—");
            setDetail("[data-arcav-termination-detail-reason]", d.reason || "—");
            setDetail("[data-arcav-termination-detail-notes]", d.notes || "—");
            setDetail("[data-arcav-termination-detail-reviewed]", formatWorkflowAuditEntry(d.workflow && d.workflow.reviewed ? d.workflow.reviewed : null));
            setDetail("[data-arcav-termination-detail-approved]", formatWorkflowAuditEntry(d.workflow && d.workflow.approved ? d.workflow.approved : null));
            setDetail("[data-arcav-termination-detail-finalized]", formatWorkflowAuditEntry(d.workflow && d.workflow.finalized ? d.workflow.finalized : null));
            setDetail("[data-arcav-termination-detail-created]", d.createdAt || "—");
            var settlementWrap = detailModalEl.querySelector("[data-arcav-termination-detail-settlement-wrap]");
            if (settlementWrap) {
                var settlement = d.settlement || null;
                var showSettlement = String(d.status || "") === "finalized" || !!settlement;
                var detailBreakdownEl = detailModalEl.querySelector('[data-arcav-termination-detail-breakdown]');
                var detailClearanceEl = detailModalEl.querySelector('[data-arcav-termination-detail-clearance-items]');
                settlementWrap.classList.toggle("d-none", !showSettlement);
                setDetail("[data-arcav-termination-detail-settlement-period]", settlement && settlement.payrollPeriod ? settlement.payrollPeriod + (settlement.payrollPeriodStatus ? (" (" + settlement.payrollPeriodStatus + ")") : "") : "—");
                setDetail("[data-arcav-termination-detail-final-salary]", settlement ? formatRupiah(settlement.finalSalaryAmount) : "—");
                setDetail("[data-arcav-termination-detail-final-allowance]", settlement ? formatRupiah(settlement.finalAllowanceAmount) : "—");
                setDetail("[data-arcav-termination-detail-final-deduction]", settlement ? formatRupiah(settlement.finalDeductionAmount) : "—");
                setDetail("[data-arcav-termination-detail-final-net]", settlement ? formatRupiah(settlement.finalNetAmount) : "—");
                setDetail("[data-arcav-termination-detail-asset-return-notes]", settlement && settlement.assetReturnNotes ? settlement.assetReturnNotes : "—");
                setDetail("[data-arcav-termination-detail-clearance-notes]", settlement && settlement.clearanceNotes ? settlement.clearanceNotes : "—");
                if (detailBreakdownEl) {
                    var detailBreakdown = settlement && Array.isArray(settlement.breakdown) ? settlement.breakdown : [];
                    detailBreakdownEl.innerHTML = detailBreakdown.length
                        ? detailBreakdown.map(function (item) { return breakdownLineHtml(item); }).join('')
                        : breakdownLineHtml(null);
                }
                if (detailClearanceEl) {
                    var detailClearance = settlement && Array.isArray(settlement.clearanceItems) ? settlement.clearanceItems : [];
                    detailClearanceEl.innerHTML = detailClearance.length
                        ? detailClearance.map(function (item) {
                            return clearanceLineHtml(item, {
                                actionable: canManageTermination,
                                terminationId: d.id,
                            });
                        }).join('')
                        : clearanceLineHtml(null, null);
                }
            }
            var prof = detailModalEl.querySelector("[data-arcav-termination-detail-profile]");
            if (prof && emp.id) {
                try {
                    var u = new URL(prof.getAttribute("href"), window.location.origin);
                    u.searchParams.set("id", String(emp.id));
                    prof.setAttribute("href", u.pathname + u.search);
                } catch (_e) {
                    prof.setAttribute("href", "/employee-details?id=" + encodeURIComponent(String(emp.id)));
                }
            }
            if (bodyWrap) bodyWrap.classList.remove("d-none");
        }).catch(function (err) {
            if (loadingEl) loadingEl.classList.add("d-none");
            var st = err && err.status ? err.status : 0;
            var data = err && err.data ? err.data : null;
            if (errEl) {
                errEl.textContent = formatSaveError(st, data);
                errEl.classList.remove("d-none");
            }
        });
    }

    document.addEventListener("click", function (e) {
        var view = e.target.closest && e.target.closest("[data-arcav-termination-view]");
        if (!view) return;
        e.preventDefault();
        var tid = view.getAttribute("data-arcav-termination-view");
        if (tid) openTerminationDetail(tid);
    });

    document.addEventListener('click', function (e) {
        var btn = e.target.closest && e.target.closest('[data-arcav-termination-clearance-return]');
        if (!btn || !canManageTermination) return;
        e.preventDefault();

        var assignmentId = btn.getAttribute('data-arcav-termination-clearance-return');
        var terminationId = btn.getAttribute('data-arcav-termination-clearance-termination');
        if (!assignmentId || !terminationId) return;

        var submitReturn = function () {
            btn.disabled = true;
            apiRequest('post', '/v1/hcm/terminations/' + encodeURIComponent(String(terminationId)) + '/clearance-items/' + encodeURIComponent(String(assignmentId)) + '/return', {
                notes: 'Returned from termination workflow.',
            }).then(function (res) {
                if (!res || !res.success || !res.data) {
                    notify('Failed to update clearance item.', true);
                    return;
                }

                var updatedTermination = res.data.termination || null;
                if (updatedTermination && idInput && String(idInput.value || '') === String(terminationId) && updatedTermination.settlement) {
                    renderSettlementPreview(previewFromSettlement(updatedTermination.settlement));
                    if (assetReturnNotesInput) {
                        assetReturnNotesInput.value = updatedTermination.settlement.assetReturnNotes || '';
                    }
                }

                loadList();
                if (currentDetailTerminationId && String(currentDetailTerminationId) === String(terminationId)) {
                    openTerminationDetail(terminationId);
                }
                notify('Clearance item marked as returned.');
            }).catch(function (err) {
                notify(formatSaveError(err && err.status ? err.status : 0, err && err.data ? err.data : null), true);
            }).finally(function () {
                btn.disabled = false;
            });
        };

        if (window.ArcavUi && typeof window.ArcavUi.confirmDelete === 'function') {
            window.ArcavUi.confirmDelete('Tandai aset ini sudah dikembalikan?', 'Return Asset').then(function (ok) {
                if (ok) submitReturn();
            });
            return;
        }

        submitReturn();
    });

    window.ArcavTerminationDetail = { open: openTerminationDetail };

    if (tbody) {
        apiRequest("get", "/v1/identity/auth/me", null).then(function (me) {
            if (!me || !me.success || !hasMePermission(me, 'termination.view')) {
                redirectToEmployeeDashboard();
                return;
            }
            canManageTermination = hasMePermission(me, 'termination.manage');
            if (addBtn) {
                addBtn.classList.toggle("d-none", !canManageTermination);
            }
            loadList();
        }).catch(function () {
            redirectToEmployeeDashboard();
        });
    }
})(window, document);
