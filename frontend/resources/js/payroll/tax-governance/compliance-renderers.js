(function (window) {
    "use strict";

    function createComplianceRenderers(deps) {
        var qs = deps.qs;
        var setText = deps.setText;
        var toTitleCase = deps.toTitleCase;
        var formatDate = deps.formatDate;
        var formatDateOnly = deps.formatDateOnly;
        var formatMoney = deps.formatMoney;
        var escapeHtml = deps.escapeHtml;

        function renderOverallStatus(root, compliance) {
            var status = compliance && compliance.compliance_status ? compliance.compliance_status : {};
            var overallRaw = String(status.overall_status || "unknown");
            var badge = qs("[data-tax-overall-badge]", root);
            var statutory = status.statutory_tax_compliance || {};
            var billing = status.billing_tax_compliance || {};
            var employee = status.employee_pph21_compliance || {};

            var policyScore = statutory.has_active_policy ? 25 : 0;
            var anomalyScore = Number(statutory.anomalies_unresolved || 0) === 0 ? 25 : 0;
            var billingScore = String(billing.payment_status || "").toLowerCase() === "current" ? 25 : 0;
            var completionRate = Math.max(0, Math.min(100, Number(employee.completion_rate || 0)));
            var employeeScore = completionRate * 0.25;
            var complianceScore = Math.round(policyScore + anomalyScore + billingScore + employeeScore);

            setText(qs("[data-tax-overall-status]", root), toTitleCase(overallRaw));
            setText(badge, overallRaw === "compliant" ? "Patuh" : "Perlu Tindak Lanjut");
            if (badge) {
                badge.className = overallRaw === "compliant"
                    ? "badge bg-success-subtle text-success"
                    : "badge bg-warning-subtle text-warning";
            }

            setText(qs("[data-tax-compliance-score]", root), String(complianceScore) + "%");
            setText(
                qs("[data-tax-compliance-summary]", root),
                "Review berikutnya: " + formatDateOnly(status.next_review_date)
                    + " | Kebijakan: " + (statutory.has_active_policy ? "Aktif" : "Belum aktif")
                    + " | Profil lengkap: " + Math.round(completionRate) + "%"
            );
            var scoreBar = qs("[data-tax-compliance-score-bar]", root);
            if (scoreBar) {
                scoreBar.style.width = String(complianceScore) + "%";
                scoreBar.setAttribute("aria-valuenow", String(complianceScore));
                scoreBar.className = complianceScore >= 80
                    ? "progress-bar bg-success"
                    : (complianceScore >= 60 ? "progress-bar bg-warning" : "progress-bar bg-danger");
            }

            setText(qs("[data-tax-next-review]", root), "Review berikutnya: " + formatDateOnly(status.next_review_date));
            setText(qs("[data-tax-reporting-period]", root), String(compliance && compliance.reporting_period ? compliance.reporting_period : "-"));

            setText(qs("[data-tax-policy-version]", root), statutory.policy_version ? "v" + statutory.policy_version : "Belum ada kebijakan aktif");
            setText(qs("[data-tax-policy-publication]", root), statutory.last_publication_date ? "Dipublikasikan: " + formatDateOnly(statutory.last_publication_date) : "Belum ada riwayat publikasi");
            setText(qs("[data-tax-anomaly-count]", root), Number(statutory.anomalies_unresolved || 0));
            setText(qs("[data-tax-anomaly-hint]", root), Number(statutory.anomalies_unresolved || 0) > 0 ? "Perlu tindak lanjut penyelesaian" : "Tidak ada anomali aktif");
            setText(qs("[data-tax-billing-outstanding]", root), formatMoney(billing.amount_outstanding || 0));
            setText(qs("[data-tax-billing-status]", root), "Status pembayaran: " + toTitleCase(billing.payment_status || "unknown"));
        }

        function renderRecommendedActions(root, compliance) {
            var list = qs("[data-tax-action-list]", root);
            if (!list) {
                return;
            }
            var actions = Array.isArray(compliance && compliance.recommended_actions) ? compliance.recommended_actions : [];
            if (!actions.length) {
                list.innerHTML = "<li class=\"list-group-item\">Tidak ada tindakan mendesak saat ini.</li>";
                return;
            }
            list.innerHTML = actions.map(function (action) {
                return "<li class=\"list-group-item d-flex justify-content-between\"><span>" + escapeHtml(action.action || "Aksi") + "</span><span class=\"badge bg-warning-subtle text-warning\">" + escapeHtml(String(action.priority || "medium").toUpperCase()) + "</span></li>";
            }).join("");
        }

        function renderNonCompliantEmployees(root, compliance) {
            var tbody = qs("[data-tax-non-compliant-employee-body]", root);
            if (!tbody) {
                return;
            }

            var status = compliance && compliance.compliance_status ? compliance.compliance_status : {};
            var employee = status.employee_pph21_compliance || {};
            var rows = Array.isArray(employee.non_compliant_employees) ? employee.non_compliant_employees : [];
            var activeEmployees = Number(employee.active_employees || 0);

            if (activeEmployees <= 0) {
                tbody.innerHTML = '<tr><td colspan="2" class="text-center text-muted py-3">Belum ada karyawan aktif di tenant ini.</td></tr>';
                return;
            }

            if (!rows.length) {
                tbody.innerHTML = '<tr><td colspan="2" class="text-center text-success py-3">Semua profil karyawan sudah patuh.</td></tr>';
                return;
            }

            tbody.innerHTML = rows.slice(0, 25).map(function (row) {
                var issues = Array.isArray(row.issues) ? row.issues.map(function (issue) {
                    var label = issue && issue.label ? issue.label : '-';
                    var currentValue = issue && issue.current_value ? ' (' + issue.current_value + ')' : '';
                    return '<div class="small text-danger">• ' + escapeHtml(label + currentValue) + '</div>';
                }).join('') : '<div class="small text-danger">• Data belum lengkap</div>';

                return '<tr>'
                    + '<td><div class="fw-semibold">' + escapeHtml(row.full_name || '-') + '</div>'
                    + '<div class="small text-muted">' + escapeHtml(row.email || '-') + '</div></td>'
                    + '<td>' + issues + '</td>'
                    + '</tr>';
            }).join('');
        }

        function extractEmployeeTotal(responsePayload) {
            var payload = responsePayload;
            if (payload && payload.data && typeof payload.data === "object" && !Array.isArray(payload.data)) {
                payload = payload.data;
            }
            if (Array.isArray(payload)) {
                return payload.length;
            }

            var meta = payload && (payload.meta || payload.pagination) ? (payload.meta || payload.pagination) : {};
            if (typeof meta.total === "number") {
                return meta.total;
            }

            if (typeof (payload && payload.total) === "number") {
                return payload.total;
            }

            var rows = payload && Array.isArray(payload.data) ? payload.data : [];
            return rows.length;
        }

        function renderRegisteredEmployeeCount(root, employeesResponse) {
            var employeeTotal = extractEmployeeTotal(employeesResponse);
            setText(qs("[data-tax-employee-count]", root), employeeTotal);

            var hint = qs("[data-tax-employee-hint]", root);
            if (hint) {
                hint.textContent = employeeTotal > 0
                    ? "Profil pajak aktif di tenant"
                    : "Belum ada profil pajak aktif";
            }
        }

        function renderAnomalyTable(root, auditData) {
            var tbody = qs("[data-tax-anomaly-table]", root);
            if (!tbody) {
                return;
            }
            var table = tbody.closest("table");
            var columnCount = table && table.tHead && table.tHead.rows && table.tHead.rows[0]
                ? table.tHead.rows[0].cells.length
                : 4;
            var anomalies = Array.isArray(auditData && auditData.anomalies_detected) ? auditData.anomalies_detected : [];
            if (!anomalies.length) {
                tbody.innerHTML = "<tr><td colspan=\"" + columnCount + "\" class=\"text-center text-muted py-4\">Tidak ada anomali terdeteksi.</td></tr>";
                return;
            }
            tbody.innerHTML = anomalies.slice(0, 10).map(function (item) {
                var severityClass = item.severity === "critical" ? "badge bg-danger-subtle text-danger" : (item.severity === "warning" ? "badge bg-warning-subtle text-warning" : "badge bg-secondary-subtle text-secondary");
                if (columnCount <= 3) {
                    return "<tr><td><div class=\"fw-semibold\">" + escapeHtml(toTitleCase(item.type)) + "</div><small class=\"text-muted\">" + escapeHtml(item.description || "") + "</small></td><td><span class=\"" + severityClass + "\">" + escapeHtml(toTitleCase(item.severity)) + "</span></td><td>" + escapeHtml(item.resolved ? "Selesai" : "Terbuka") + "</td></tr>";
                }
                return "<tr><td><div class=\"fw-semibold\">" + escapeHtml(toTitleCase(item.type)) + "</div><small class=\"text-muted\">" + escapeHtml(item.description || "") + "</small></td><td><span class=\"" + severityClass + "\">" + escapeHtml(toTitleCase(item.severity)) + "</span></td><td>" + escapeHtml(formatDate(item.detected_at)) + "</td><td>" + escapeHtml(item.resolved ? "Selesai" : "Terbuka") + "</td></tr>";
            }).join("");
        }

        function renderEventTable(root, auditData) {
            var tbody = qs("[data-tax-event-table]", root);
            if (!tbody) {
                return;
            }
            var events = Array.isArray(auditData && auditData.change_history) ? auditData.change_history : [];
            var period = auditData && auditData.period ? auditData.period : {};
            setText(qs("[data-tax-audit-period]", root), "Periode " + (period.start || "-") + " sampai " + (period.end || "-"));
            if (!events.length) {
                tbody.innerHTML = "<tr><td colspan=\"5\" class=\"text-center text-muted py-4\">Tidak ada perubahan kebijakan pada periode ini.</td></tr>";
                return;
            }
            tbody.innerHTML = events.slice(0, 12).map(function (event) {
                return "<tr><td>v" + escapeHtml(event.version || "-") + "</td><td>" + escapeHtml(toTitleCase(event.action)) + "</td><td>" + escapeHtml(event.actor_name || "Sistem") + "</td><td>" + escapeHtml(formatDate(event.timestamp)) + "</td><td>" + escapeHtml(event.change_summary || "-") + "</td></tr>";
            }).join("");
        }

        function renderTenantAuditReportTable(root, auditData) {
            var tbody = qs("[data-tax-report-audit-table]", root);
            if (!tbody) {
                return;
            }

            var anomalies = Array.isArray(auditData && auditData.anomalies_detected) ? auditData.anomalies_detected : [];
            if (!anomalies.length) {
                tbody.innerHTML = "<tr><td colspan=\"5\" class=\"text-center text-muted py-4\">Tidak ada anomali pada periode terpilih.</td></tr>";
                return;
            }

            tbody.innerHTML = anomalies.slice(0, 25).map(function (item) {
                return "<tr><td>" + escapeHtml(item.tenant_name || "Tenant Aktif") + "</td><td>" + escapeHtml(toTitleCase(item.type || "unknown")) + "</td><td>" + escapeHtml(String(item.count || 1)) + "</td><td>" + escapeHtml(item.resolved ? "Resolved" : "Open") + "</td><td>" + escapeHtml(formatDate(item.detected_at)) + "</td></tr>";
            }).join("");
        }

        function renderTenantPolicies(root, policiesResponse) {
            var tbody = qs("[data-tax-tenant-policy-table]", root);
            if (!tbody) {
                return;
            }
            var rows = Array.isArray(policiesResponse && policiesResponse.data && policiesResponse.data.items) ? policiesResponse.data.items : [];
            if (!rows.length) {
                tbody.innerHTML = "<tr><td colspan=\"7\" class=\"text-center text-muted py-4\">Belum ada kebijakan PPh 21 tenant.</td></tr>";
                return;
            }
            tbody.innerHTML = rows.map(function (item) {
                var statusClass = item.status === "published" ? "badge bg-success-subtle text-success" : (item.status === "submitted" || item.status === "approved" ? "badge bg-warning-subtle text-warning" : "badge bg-secondary-subtle text-secondary");
                var editUrl = "/tax-employees/policies/" + encodeURIComponent(item.uuid) + "/edit";
                return "<tr><td>" + escapeHtml(item.policyCode || "-") + "</td><td>" + escapeHtml(item.name || "-") + "</td><td><span class=\"" + statusClass + "\">" + escapeHtml(toTitleCase(item.status || "-")) + "</span></td><td>" + escapeHtml(String(item.version || 0)) + "</td><td>" + escapeHtml(item.effectiveStartDate || "-") + "</td><td>" + escapeHtml(formatDate(item.updatedAt)) + "</td><td><a class=\"btn btn-sm btn-outline-primary\" href=\"" + editUrl + "\">Ubah</a></td></tr>";
            }).join("");
        }

        return {
            extractEmployeeTotal: extractEmployeeTotal,
            renderAnomalyTable: renderAnomalyTable,
            renderEventTable: renderEventTable,
            renderNonCompliantEmployees: renderNonCompliantEmployees,
            renderOverallStatus: renderOverallStatus,
            renderRecommendedActions: renderRecommendedActions,
            renderRegisteredEmployeeCount: renderRegisteredEmployeeCount,
            renderTenantAuditReportTable: renderTenantAuditReportTable,
            renderTenantPolicies: renderTenantPolicies,
        };
    }

    window.ArcavTaxGovernanceModules = window.ArcavTaxGovernanceModules || {};
    window.ArcavTaxGovernanceModules.createComplianceRenderers = createComplianceRenderers;
})(window);
