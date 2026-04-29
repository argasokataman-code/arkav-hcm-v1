export function renderPlatformReportModule(deps, root, reportResponse) {
    var qs = deps.qs;
    var setText = deps.setText;
    var formatMoney = deps.formatMoney;
    var getActiveScreen = deps.getActiveScreen;
    var showPlatformGate = deps.showPlatformGate;
    var escapeHtml = deps.escapeHtml;
    var renderBillingCycleDetail = deps.renderBillingCycleDetail;

    var data = reportResponse && reportResponse.data ? reportResponse.data : {};
    var complianceConfigured = data.policy_configured !== false;
    var summary = data.summary_global || data.summary || {};
    var rows = Array.isArray(data.tenants_global) ? data.tenants_global : Array.isArray(data.tenants) ? data.tenants : [];

    var grossRevenueTotal = Number(summary.total_gross_revenue || 0);
    var taxableRevenueTotal = Number(summary.total_taxable_revenue_amount || 0);
    var effectiveGrossRevenueTotal = grossRevenueTotal > 0 ? grossRevenueTotal : taxableRevenueTotal;
    var effectiveNetRevenueTotal = Number(summary.total_net_revenue || 0);
    if (effectiveNetRevenueTotal <= 0 && effectiveGrossRevenueTotal > 0) {
        effectiveNetRevenueTotal = Math.max(0, effectiveGrossRevenueTotal - Number(summary.total_tax_due || 0));
    }
    var complianceSummary = data.summary_compliance || {};
    var totalTaxLiability = Number(summary.total_collected_tax_liability || complianceSummary.total_collected_tax_liability || 0);
    var totalCorporateTaxExpense = Number(summary.total_tax_due || complianceSummary.total_tax_payable || 0);
    var netRevenueExcludingTax = Math.max(0, effectiveGrossRevenueTotal - totalTaxLiability);
    var netProfitTotal = Number(summary.total_net_profit || complianceSummary.total_net_profit || 0);
    if (netProfitTotal <= 0) {
        netProfitTotal = Math.max(0, netRevenueExcludingTax - totalCorporateTaxExpense);
    }
    var installmentRows = Array.isArray(data.tax_installments) ? data.tax_installments : [];

    setText(qs("[data-tax-platform-summary-subscription-revenue]", root), formatMoney(summary.total_subscription_revenue || summary.total_invoice_amount || 0));
    setText(qs("[data-tax-platform-summary-addon-revenue]", root), formatMoney(summary.total_addon_revenue || summary.total_uncleared_revenue_amount || 0));
    setText(qs("[data-tax-platform-summary-net-revenue]", root), formatMoney(effectiveGrossRevenueTotal));

    setText(qs("[data-tax-compliance-summary-gross]", root), formatMoney(effectiveGrossRevenueTotal));
    setText(qs("[data-tax-compliance-summary-tax-due]", root), formatMoney(totalCorporateTaxExpense));
    setText(qs("[data-tax-compliance-summary-gross-revenue]", root), formatMoney(effectiveGrossRevenueTotal));
    setText(qs("[data-tax-compliance-summary-tax-liability]", root), formatMoney(totalTaxLiability));
    setText(qs("[data-tax-compliance-summary-net-revenue]", root), formatMoney(netRevenueExcludingTax));
    setText(qs("[data-tax-compliance-summary-corporate-tax-expense]", root), formatMoney(totalCorporateTaxExpense));
    setText(qs("[data-tax-compliance-summary-net-profit]", root), formatMoney(netProfitTotal));
    setText(qs("[data-tax-compliance-summary-effective-rate]", root), String(Number(complianceSummary.effective_tax_rate || summary.effective_tax_rate || 0)) + "%");

    if (!complianceConfigured && getActiveScreen(root) === "platform-tax-compliance") {
        showPlatformGate(root, "Belum ada kebijakan Government Tax untuk bulan ini. Kewajiban pajak ditampilkan 0 sampai policy compliance disimpan.");
    }

    var installmentTbody = qs("[data-tax-compliance-installment-table]", root);
    if (installmentTbody) {
        if (!installmentRows.length) {
            installmentTbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-3">Belum ada histori pembayaran installment pada periode ini.</td></tr>';
        } else {
            installmentTbody.innerHTML = installmentRows
                .map(function (row) {
                    var paid = String(row.status || "").toLowerCase() === "paid";
                    var statusClass = paid ? "badge bg-success-subtle text-success" : "badge bg-warning-subtle text-warning";
                    var statusLabel = paid ? "Paid" : "Pending";
                    return "<tr><td>" + escapeHtml(row.period || "-") + "</td><td>" + escapeHtml(formatMoney(row.amount_paid || 0)) + "</td><td><span class=\"" + statusClass + "\">" + escapeHtml(statusLabel) + "</span></td><td>" + escapeHtml(row.payment_date || "-") + "</td></tr>";
                })
                .join("");
        }
    }

    var tbody = qs("[data-tax-platform-report-table]", root);
    if (!tbody) {
        return;
    }
    if (!rows.length) {
        var emptyColspan = tbody.closest("table") && tbody.closest("table").tHead && tbody.closest("table").tHead.rows[0] ? tbody.closest("table").tHead.rows[0].cells.length : 8;
        tbody.innerHTML = '<tr><td colspan="' + emptyColspan + '" class="text-center text-muted py-4">Tidak ada data laporan pada bulan terpilih.</td></tr>';
        return;
    }

    var tableColCount = tbody.closest("table") && tbody.closest("table").tHead && tbody.closest("table").tHead.rows[0] ? tbody.closest("table").tHead.rows[0].cells.length : 8;
    var isGovernmentTable = getActiveScreen(root) === "platform-tax-compliance" || tableColCount === 6 || tableColCount === 7;

    tbody.innerHTML = rows
        .map(function (item) {
            var taxableRevenue = Number(item.taxable_revenue_amount || 0);
            var grossRevenue = Number(item.gross_revenue || 0);
            var effectiveGrossRevenue = grossRevenue > 0 ? grossRevenue : taxableRevenue;
            var netRevenue = Number(item.net_revenue || 0);
            var effectiveNetRevenue = netRevenue > 0 ? netRevenue : Math.max(0, effectiveGrossRevenue - Number(item.tax_amount_due || 0));
            var taxLiability = Number(item.collected_tax_liability || 0);
            var corporateTaxExpense = Number(item.total_tax_payable || item.tax_amount_due || 0);
            var netProfit = Number(item.net_profit || 0);
            if (netProfit <= 0) {
                netProfit = Math.max(0, effectiveGrossRevenue - taxLiability - corporateTaxExpense);
            }
            var firstCol = item.tenant || item.company_name || "-";
            var secondCol = item.plan || item.plan_name || "-";

            if (isGovernmentTable) {
                var taxDue = corporateTaxExpense;
                var complianceStatus = !complianceConfigured ? "Belum Dikonfigurasi" : taxDue > 0 ? "Terhitung" : "Tidak Ada Pajak";
                var statusClass = !complianceConfigured
                    ? "badge bg-info-subtle text-info"
                    : taxDue > 0
                    ? "badge bg-warning-subtle text-warning"
                    : "badge bg-secondary-subtle text-secondary";
                return (
                    "<tr>" +
                    "<td><div class=\"fw-semibold\">" +
                    escapeHtml(firstCol) +
                    "</div><small class=\"text-muted\">ID " +
                    escapeHtml(item.company_id || "-") +
                    "</small></td>" +
                    "<td>" +
                    renderBillingCycleDetail(item) +
                    "</td>" +
                    "<td>" +
                    escapeHtml(formatMoney(item.taxable_revenue || effectiveGrossRevenue)) +
                    "</td>" +
                    "<td>" +
                    escapeHtml(formatMoney(taxLiability)) +
                    "</td>" +
                    '<td class="fw-semibold">' +
                    escapeHtml(formatMoney(taxDue)) +
                    "</td>" +
                    "<td>" +
                    escapeHtml(formatMoney(netProfit)) +
                    "</td>" +
                    '<td><span class="' +
                    statusClass +
                    '\">' +
                    escapeHtml(complianceStatus) +
                    "</span></td>" +
                    "</tr>"
                );
            }

            return (
                "<tr><td><div class=\"fw-semibold\">" +
                escapeHtml(firstCol) +
                "</div><small class=\"text-muted\">ID " +
                escapeHtml(item.company_id || "-") +
                "</small></td><td>" +
                escapeHtml(secondCol) +
                "</td><td>" +
                escapeHtml(formatMoney(item.subscription_revenue || 0)) +
                "</td><td>" +
                escapeHtml(formatMoney(item.addon_revenue || 0)) +
                "</td><td>" +
                escapeHtml(formatMoney(effectiveGrossRevenue)) +
                "</td><td>" +
                escapeHtml(formatMoney(item.tax_amount_due || 0)) +
                "</td><td>" +
                escapeHtml(formatMoney(effectiveNetRevenue)) +
                "</td></tr>"
            );
        })
        .join("");
}
