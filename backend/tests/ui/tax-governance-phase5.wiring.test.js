/**
 * tax-governance-phase5.wiring.test.js
 * Vitest wiring tests for Phase 5 Tax Governance UI screens:
 *   - employee-tax-profiles screen (tax-employee-profiles.js)
 *   - tenant-compliance screen (tax-tenant-compliance.js)
 */
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { JSDOM } from 'jsdom';
import { readFileSync } from 'fs';
import { resolve } from 'path';

/* ------------------------------------------------------------------ */
/* Helpers                                                             */
/* ------------------------------------------------------------------ */
function loadScript(dom, scriptPath) {
    const code = readFileSync(resolve(__dirname, '../../../frontend/resources/js/' + scriptPath), 'utf8');
    const scriptEl = dom.window.document.createElement('script');
    scriptEl.textContent = code;
    dom.window.document.body.appendChild(scriptEl);
}

function flush() {
    return new Promise(function (resolve) { setTimeout(resolve, 0); });
}

/* ------------------------------------------------------------------ */
/* SCREEN: employee-tax-profiles                                       */
/* ------------------------------------------------------------------ */
describe('tax-employee-profiles.js', function () {
    let dom;

    beforeEach(function () {
        dom = new JSDOM(`<!DOCTYPE html><html><body>
            <span data-emp-tax-count></span>
            <input data-emp-tax-search type="text">
            <select data-emp-tax-filter></select>
            <button data-emp-tax-refresh></button>
            <h4 data-emp-tax-kpi-total></h4>
            <h4 data-emp-tax-kpi-npwp></h4>
            <h4 data-emp-tax-kpi-ptkp></h4>
            <table>
                <tbody data-emp-tax-tbody></tbody>
            </table>
            <span data-emp-tax-pagination-info></span>
            <button data-emp-tax-prev></button>
            <button data-emp-tax-next></button>
            <div id="empTaxEditModal">
                <form data-emp-tax-edit-form>
                    <input data-emp-tax-edit-user-id name="userId" type="hidden">
                    <input data-emp-tax-edit-name type="text">
                    <input data-emp-tax-edit-npwp name="npwp" type="text">
                    <select data-emp-tax-edit-tax-status name="taxStatus">
                        <option value="TK0">TK0</option>
                        <option value="K1">K1</option>
                    </select>
                    <div data-emp-tax-edit-error class="d-none"></div>
                    <button data-emp-tax-edit-submit type="submit"></button>
                    <span data-emp-tax-edit-spinner class="d-none"></span>
                </form>
            </div>
        </body></html>`, { runScripts: 'dangerously', resources: 'usable' });

        // Mock bootstrap Modal
        dom.window.bootstrap = {
            Modal: {
                getOrCreateInstance: vi.fn().mockReturnValue({ show: vi.fn() }),
                getInstance: vi.fn().mockReturnValue({ hide: vi.fn() }),
            },
        };

        // Mock AuthApi
        dom.window.AuthApi = {
            request: vi.fn().mockResolvedValue({
                data: {
                    data: [
                        { id: '1', name: 'Budi Santoso',  email: 'budi@example.com',  npwp: '123456789012345', tax_status: 'K1', ptkp_status: 'K1' },
                        { id: '2', name: 'Sari Dewi',     email: 'sari@example.com',  npwp: '',               tax_status: 'TK0', ptkp_status: 'TK0' },
                        { id: '3', name: 'Ahmad Yusuf',   email: 'ahmad@example.com', npwp: '',               tax_status: '',    ptkp_status: '' },
                    ],
                    meta: { total: 3, last_page: 1 },
                },
            }),
        };
    });

    afterEach(function () {
        dom.window.close();
    });

    it('loads employees on init and renders rows in tbody', async function () {
        loadScript(dom, 'tax-employee-profiles.js');
        await flush();
        const tbody = dom.window.document.querySelector('[data-emp-tax-tbody]');
        expect(tbody.querySelectorAll('tr').length).toBe(3);
    });

    it('renders employee fullName when API uses camelCase field', async function () {
        dom.window.AuthApi.request = vi.fn().mockResolvedValue({
            data: {
                data: [
                    {
                        id: '1',
                        fullName: 'Camel Case Name',
                        email: 'camel@example.com',
                        npwp: '',
                        tax_status: '',
                        ptkp_status: '',
                    },
                ],
                meta: { total: 1, last_page: 1 },
            },
        });

        loadScript(dom, 'tax-employee-profiles.js');
        await flush();

        const tbody = dom.window.document.querySelector('[data-emp-tax-tbody]');
        expect(tbody.innerHTML).toContain('Camel Case Name');
    });

    it('renders NPWP value for employee with NPWP', async function () {
        loadScript(dom, 'tax-employee-profiles.js');
        await flush();
        const tbody = dom.window.document.querySelector('[data-emp-tax-tbody]');
        expect(tbody.innerHTML).toContain('123456789012345');
    });

    it('shows Kosong text for employee without NPWP', async function () {
        loadScript(dom, 'tax-employee-profiles.js');
        await flush();
        const tbody = dom.window.document.querySelector('[data-emp-tax-tbody]');
        expect(tbody.innerHTML).toContain('Kosong');
    });

    it('updates KPI total count', async function () {
        loadScript(dom, 'tax-employee-profiles.js');
        await flush();
        const kpiTotal = dom.window.document.querySelector('[data-emp-tax-kpi-total]');
        expect(kpiTotal.textContent).toBe('3');
    });

    it('updates NPWP KPI count correctly (1 of 3 has NPWP)', async function () {
        loadScript(dom, 'tax-employee-profiles.js');
        await flush();
        const kpiNpwp = dom.window.document.querySelector('[data-emp-tax-kpi-npwp]');
        expect(kpiNpwp.textContent).toBe('1');
    });

    it('updates count badge with total', async function () {
        loadScript(dom, 'tax-employee-profiles.js');
        await flush();
        const badge = dom.window.document.querySelector('[data-emp-tax-count]');
        expect(badge.textContent).toContain('3');
    });

    it('shows error message on API failure', async function () {
        dom.window.AuthApi.request = vi.fn().mockRejectedValue(new Error('Unauthorized'));
        loadScript(dom, 'tax-employee-profiles.js');
        await flush();
        const tbody = dom.window.document.querySelector('[data-emp-tax-tbody]');
        expect(tbody.innerHTML).toContain('Gagal memuat data');
    });
});

/* ------------------------------------------------------------------ */
/* SCREEN: tenant-compliance                                           */
/* ------------------------------------------------------------------ */
describe('tax-tenant-compliance.js', function () {
    let dom;

    const mockAuditResponse = {
        compliance_checklist: {
            has_published_policy: true,
            has_recent_publication: false,
            all_payroll_runs_covered: true,
            no_unresolved_anomalies: false,
        },
        policy_snapshot: {
            current_published_version: '2',
            effective_date: '2024-01-01',
            policy_summary: {
                policy_code: 'PPH21-2024',
                name: 'Kebijakan PPh 21 Tahun 2024',
                rules_count: 3,
            },
        },
        change_history: [
            { created_at: '2024-03-15T10:00:00Z', event_type: 'PUBLISHED', created_by: 'Admin HR', note: 'Publikasi kebijakan Q1' },
        ],
    };

    beforeEach(function () {
        dom = new JSDOM(`<!DOCTYPE html><html><body>
            <div data-compliance-checklist-area>
                <span data-compliance-check-icon-policy></span>
                <div data-compliance-check-label-policy></div>
                <span data-compliance-check-icon-recent></span>
                <div data-compliance-check-label-recent></div>
                <span data-compliance-check-icon-payroll></span>
                <div data-compliance-check-label-payroll></div>
                <span data-compliance-check-icon-anomaly></span>
                <div data-compliance-check-label-anomaly></div>
            </div>
            <input type="month" data-compliance-period-start>
            <input type="month" data-compliance-period-end>
            <button data-compliance-refresh></button>
            <a data-compliance-export-pdf href="#"></a>
            <div data-compliance-policy-snapshot>
                <dd data-compliance-policy-code></dd>
                <dd data-compliance-policy-name></dd>
                <dd data-compliance-policy-version></dd>
                <dd data-compliance-policy-effective></dd>
                <dd data-compliance-payroll-runs></dd>
            </div>
            <table>
                <tbody data-compliance-history-tbody></tbody>
            </table>
            <small data-compliance-change-period></small>
            <div data-compliance-error class="d-none"></div>
        </body></html>`, { runScripts: 'dangerously', resources: 'usable' });

        dom.window.AuthApi = {
            request: vi.fn().mockResolvedValue({ data: mockAuditResponse }),
        };
    });

    afterEach(function () {
        dom.window.close();
    });

    it('auto-loads compliance data on init', async function () {
        loadScript(dom, 'tax-tenant-compliance.js');
        await flush();
        expect(dom.window.AuthApi.request).toHaveBeenCalledWith(
            'GET',
            expect.stringContaining('tenant-self-audit'),
            expect.any(Object)
        );
    });

    it('renders passing checklist item with checkmark', async function () {
        loadScript(dom, 'tax-tenant-compliance.js');
        await flush();
        const icon = dom.window.document.querySelector('[data-compliance-check-icon-policy]');
        expect(icon.textContent).toBe('\u2705');
    });

    it('renders failing checklist item with cross', async function () {
        loadScript(dom, 'tax-tenant-compliance.js');
        await flush();
        const icon = dom.window.document.querySelector('[data-compliance-check-icon-recent]');
        expect(icon.textContent).toBe('\u274C');
    });

    it('populates policy code from snapshot', async function () {
        loadScript(dom, 'tax-tenant-compliance.js');
        await flush();
        const codeEl = dom.window.document.querySelector('[data-compliance-policy-code]');
        expect(codeEl.textContent).toBe('PPH21-2024');
    });

    it('populates policy name from snapshot', async function () {
        loadScript(dom, 'tax-tenant-compliance.js');
        await flush();
        const nameEl = dom.window.document.querySelector('[data-compliance-policy-name]');
        expect(nameEl.textContent).toBe('Kebijakan PPh 21 Tahun 2024');
    });

    it('renders change history row', async function () {
        loadScript(dom, 'tax-tenant-compliance.js');
        await flush();
        const tbody = dom.window.document.querySelector('[data-compliance-history-tbody]');
        expect(tbody.innerHTML).toContain('Admin HR');
        expect(tbody.innerHTML).toContain('PUBLISHED');
    });

    it('shows error state on API failure', async function () {
        dom.window.AuthApi.request = vi.fn().mockRejectedValue(new Error('Server error'));
        loadScript(dom, 'tax-tenant-compliance.js');
        await flush();
        const errBox = dom.window.document.querySelector('[data-compliance-error]');
        expect(errBox.classList.contains('d-none')).toBe(false);
        expect(errBox.textContent).toContain('Server error');
    });
});

/* ------------------------------------------------------------------ */
/* SCREEN: tax-governance-dashboard landing                            */
/* ------------------------------------------------------------------ */
describe('tax-governance-dashboard.js (landing)', function () {
    let dom;

    function buildCompliancePayload() {
        return {
            success: true,
            data: {
                reporting_period: '2026-Q2',
                compliance_status: {
                    overall_status: 'attention_required',
                    next_review_date: '2026-06-03',
                    statutory_tax_compliance: {
                        policy_version: 6,
                        last_publication_date: '2026-05-03',
                        anomalies_unresolved: 0,
                    },
                    billing_tax_compliance: {
                        amount_outstanding: 0,
                        payment_status: 'current',
                    },
                },
                recommended_actions: [
                    {
                        action: 'Reconcile unpaid billing tax invoices.',
                        priority: 'medium',
                    },
                ],
            },
        };
    }

    function buildAuditPayload() {
        return {
            success: true,
            data: {
                period: { start: '2026-02-01', end: '2026-05-03' },
                anomalies_detected: [],
                change_history: [
                    {
                        version: '6',
                        action: 'updated',
                        actor_name: 'Deltas',
                        timestamp: '2026-05-03T12:57:00Z',
                        change_summary: '-',
                    },
                ],
            },
        };
    }

    function createDom() {
        dom = new JSDOM(`<!DOCTYPE html><html><body>
            <div class="page-wrapper"
                 data-tax-governance-page
                 data-tax-governance-screen="landing"
                 data-tax-governance-policy-uuid="">
                <div data-tax-overall-status>-</div>
                <div data-tax-overall-badge>Unknown</div>
                <div data-tax-next-review>Next review: -</div>
                <div data-tax-policy-version>-</div>
                <div data-tax-policy-publication>-</div>
                <div data-tax-anomaly-count>0</div>
                <div data-tax-anomaly-hint>No active anomaly</div>
                <div data-tax-employee-count>0</div>
                <div data-tax-employee-hint>Dengan profil pajak</div>
                <ol data-tax-action-list>
                    <li>Rekomendasi tindakan akan ditampilkan di sini.</li>
                </ol>
                <table>
                    <thead><tr><th>Tipe</th><th>Prioritas</th><th>Status</th></tr></thead>
                    <tbody data-tax-anomaly-table><tr><td>Belum ada data</td></tr></tbody>
                </table>
                <table>
                    <thead><tr><th>Versi</th><th>Aksi</th><th>Pelaku</th><th>Waktu</th><th>Ringkasan</th></tr></thead>
                    <tbody data-tax-event-table><tr><td>Belum ada riwayat</td></tr></tbody>
                </table>
                <table>
                    <tbody data-tax-report-audit-table><tr><td>Belum ada audit</td></tr></tbody>
                </table>
                <div data-tax-audit-period>-</div>
                <div data-tax-billing-outstanding>-</div>
                <div data-tax-billing-status>-</div>
                <div data-tax-reporting-period>-</div>
                <div data-tax-governance-error class="d-none"></div>
                <div data-tax-platform-gate class="d-none"></div>
            </div>
        </body></html>`, { runScripts: 'dangerously', resources: 'usable' });
    }

    afterEach(function () {
        if (dom) {
            dom.window.close();
        }
    });

    it('hydrates registered employee count from employee API meta total', async function () {
        createDom();
        dom.window.AuthApi = {
            request: vi.fn().mockImplementation(function (method, path) {
                if (path === '/hcm/tax-governance/reports/tenant-compliance-status') {
                    return Promise.resolve(buildCompliancePayload());
                }
                if (path === '/hcm/tax-governance/reports/tenant-self-audit-export') {
                    return Promise.resolve(buildAuditPayload());
                }
                if (path === '/hcm/employees') {
                    return Promise.resolve({
                        data: {
                            data: [{ id: '1', name: 'A' }],
                            meta: { total: 42, last_page: 3 },
                        },
                    });
                }
                return Promise.resolve({ success: true, data: {} });
            }),
        };

        loadScript(dom, 'tax-governance-dashboard.js');
        await flush();

        const countEl = dom.window.document.querySelector('[data-tax-employee-count]');
        const hintEl = dom.window.document.querySelector('[data-tax-employee-hint]');

        expect(countEl.textContent).toBe('42');
        expect(hintEl.textContent).toBe('Profil pajak aktif di tenant');
        expect(dom.window.AuthApi.request).toHaveBeenCalledWith('get', '/hcm/employees', expect.any(Object));
    });

    it('falls back to data array length when employee meta total is missing', async function () {
        createDom();
        dom.window.AuthApi = {
            request: vi.fn().mockImplementation(function (method, path) {
                if (path === '/hcm/tax-governance/reports/tenant-compliance-status') {
                    return Promise.resolve(buildCompliancePayload());
                }
                if (path === '/hcm/tax-governance/reports/tenant-self-audit-export') {
                    return Promise.resolve(buildAuditPayload());
                }
                if (path === '/hcm/employees') {
                    return Promise.resolve({
                        data: {
                            data: [{ id: '1' }, { id: '2' }, { id: '3' }],
                        },
                    });
                }
                return Promise.resolve({ success: true, data: {} });
            }),
        };

        loadScript(dom, 'tax-governance-dashboard.js');
        await flush();

        const countEl = dom.window.document.querySelector('[data-tax-employee-count]');
        expect(countEl.textContent).toBe('3');
    });
});
