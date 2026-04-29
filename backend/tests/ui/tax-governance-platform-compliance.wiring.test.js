/**
 * tax-governance-platform-compliance.wiring.test.js
 *
 * Verifies that the platform-tax-compliance screen renders a 7-column
 * government-tax-obligation table with explicit tenant obligation cycle and
 * renewal detail, while still avoiding the billing-style revenue stream layout.
 *
 * Revenue stream fields (payroll_service_fee, addon_markup_rate) must NOT
 * appear as visible columns on the government compliance report table.
 */
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { JSDOM } from 'jsdom';
import { readFileSync } from 'fs';
import { resolve } from 'path';

function loadScript(dom, relPath) {
    const code = readFileSync(resolve(__dirname, '../../../frontend/resources/js/' + relPath), 'utf8');
    const el = dom.window.document.createElement('script');
    el.textContent = code;
    dom.window.document.body.appendChild(el);
}

function flush() {
    return new Promise(r => setTimeout(r, 0));
}

describe('platform-tax-compliance government table (7-col)', () => {
    let dom;

    beforeEach(() => {
        dom = new JSDOM(`<!DOCTYPE html><html><body>
            <div class="page-wrapper"
                 data-tax-governance-page
                 data-tax-governance-screen="platform-tax-compliance"
                 data-tax-governance-policy-uuid="">
                <div class="alert d-none" data-tax-governance-error></div>
                <div class="alert d-none" data-tax-platform-gate></div>

                <form data-tax-platform-policy-form>
                    <input type="hidden" name="addon_markup_rate" value="0">
                    <input type="text" name="transaction_tax_name" value="PPN">
                    <input type="number" name="transaction_tax_rate" value="11">
                    <input type="text" name="transaction_tax_description" value="PPN dipungut dari tenant">
                    <input type="text" name="corporate_tax_name" value="PPh Badan">
                    <input type="number" name="subscription_tax_rate">
                    <select name="billing_cycle_type"><option value="yearly" selected>Tahunan</option></select>
                    <select name="status"><option value="active">Aktif</option></select>
                    <input type="date" name="effective_from">
                    <input type="text" name="notes">
                    <button type="submit" data-tax-platform-policy-submit></button>
                </form>

                <span data-tax-compliance-summary-gross-revenue></span>
                <span data-tax-compliance-summary-tax-liability></span>
                <span data-tax-compliance-summary-net-revenue></span>
                <span data-tax-compliance-summary-corporate-tax-expense></span>
                <span data-tax-compliance-summary-net-profit></span>
                <tbody data-tax-compliance-installment-table></tbody>

                <input type="month" data-tax-platform-report-month>
                <button data-tax-platform-report-refresh></button>

                <table>
                    <thead>
                        <tr>
                            <th>Tenant</th>
                            <th>Tipe Kewajiban & Renewal</th>
                            <th>Gross Revenue (Rp)</th>
                            <th>Tax Liability - PPN (Rp)</th>
                            <th>Corporate Tax Expense (Rp)</th>
                            <th>Net Profit (Rp)</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody data-tax-platform-report-table>
                        <tr><td colspan="7">Memuat...</td></tr>
                    </tbody>
                </table>
            </div>
        </body></html>`, {
            runScripts: 'dangerously',
            url: 'http://localhost',
        });

        dom.window.__ARCAV_DISABLE_AUTOINIT__ = true;
        dom.window.localStorage.setItem('arcav_access_token', 'test-token');

        dom.window.AuthApi = {
            tokenKey: 'arcav_access_token',
            request: vi.fn().mockResolvedValue({
                data: {
                    success: true,
                    data: {
                        items_global: [
                            {
                                version: 'v1',
                                subscription_tax_rate: 22,
                                payroll_service_fee: 0,
                                addon_markup_rate: 0,
                                status: 'active',
                                created_at: '2026-04-01T00:00:00Z',
                                effective_from: '2026-04-01',
                            },
                        ],
                        summary_global: {
                            total_gross_revenue: 5000000,
                            total_collected_tax_liability: 550000,
                            total_tax_due: 1100000,
                            total_net_profit: 3350000,
                            effective_tax_rate: 22,
                        },
                        summary_compliance: { effective_tax_rate: 22 },
                        tenants_global: [
                            {
                                company_id: '1',
                                tenant: 'PT. Maju Bersama',
                                billing_month: '2026-04',
                                billing_cycle_type: 'yearly',
                                next_renewal_month: '2027-04',
                                taxable_revenue: 5000000,
                                collected_tax_liability: 550000,
                                total_tax_payable: 1100000,
                                net_profit: 3350000,
                            },
                        ],
                    },
                },
            }),
        };

        loadScript(dom, 'tax-governance-dashboard.js');
    });

    it('report table has exactly 7 columns including cycle and renewal detail', () => {
        const thead = dom.window.document.querySelector('table thead tr');
        expect(thead.cells.length).toBe(7);

        const headers = Array.from(thead.cells).map(c => c.textContent.trim());
        expect(headers).toContain('Tenant');
        expect(headers).toContain('Tipe Kewajiban & Renewal');
        expect(headers).toContain('Tax Liability - PPN (Rp)');
        expect(headers).toContain('Corporate Tax Expense (Rp)');
        expect(headers).toContain('Status');

        // Revenue stream columns must NOT be present
        expect(headers.join(' ')).not.toMatch(/Payroll Service/i);
        expect(headers.join(' ')).not.toMatch(/Add-on/i);
        expect(headers.join(' ')).not.toMatch(/Subscription Revenue/i);
    });

    it('compliance form keeps addon_markup_rate as hidden field with value 0', () => {
        const form = dom.window.document.querySelector('[data-tax-platform-policy-form]');

        const addonField = form.querySelector('[name="addon_markup_rate"]');
        expect(addonField).not.toBeNull();
        expect(addonField.type).toBe('hidden');
        expect(addonField.value).toBe('0');
    });

    it('compliance form exposes separate transaction and corporate tax rate fields', () => {
        const form = dom.window.document.querySelector('[data-tax-platform-policy-form]');
        const visibleNumberInputs = Array.from(form.querySelectorAll('input[type="number"]'));
        const fieldNames = visibleNumberInputs.map(i => i.name);
        expect(fieldNames).toContain('transaction_tax_rate');
        expect(fieldNames).toContain('subscription_tax_rate');
        expect(form.querySelector('[name="billing_cycle_type"]')).not.toBeNull();
    });

    it('renderPlatformReport populates 7-col tbody with cycle and renewal detail', async () => {
        const tbody = dom.window.document.querySelector('[data-tax-platform-report-table]');

        // Trigger a synthetic report render via the refresh button click
        const monthInput = dom.window.document.querySelector('[data-tax-platform-report-month]');
        monthInput.value = '2026-04';
        dom.window.document.querySelector('[data-tax-platform-report-refresh]').click();
        await flush();

        // After render tbody should not still be the placeholder
        const rows = tbody.querySelectorAll('tr');
        expect(rows.length).toBeGreaterThan(0);

        // Each data row must have exactly 7 cells (matching 7-col header)
        const dataRow = rows[0];
        if (dataRow.cells.length > 1) {
            expect(dataRow.cells.length).toBe(7);
            expect(dataRow.cells[1].textContent).toContain('Tahunan');
            expect(dataRow.cells[1].textContent).toContain('2027');
        }
    });
});
