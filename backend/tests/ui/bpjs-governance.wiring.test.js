import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { JSDOM } from 'jsdom';
import { readFileSync } from 'fs';
import { resolve } from 'path';

function loadScript(dom, scriptPath) {
    const code = readFileSync(resolve(__dirname, '../../../frontend/resources/js/' + scriptPath), 'utf8');
    const scriptEl = dom.window.document.createElement('script');
    scriptEl.textContent = code;
    dom.window.document.body.appendChild(scriptEl);
}

function flush() {
    return new Promise((resolveFn) => setTimeout(resolveFn, 0));
}

describe('bpjs-governance-data.js', function () {
    let dom;

    beforeEach(function () {
        dom = new JSDOM(`<!DOCTYPE html><html><body>
            <div data-bpjs-governance-page data-bpjs-screen="landing">
                <h4 data-bpjs-kpi-programs>0</h4>
                <h4 data-bpjs-kpi-employee-rate>0%</h4>
                <h4 data-bpjs-kpi-membership>0/0</h4>
                <table><tbody data-bpjs-policy-summary-body></tbody></table>
                <table><tbody data-bpjs-employee-membership-body></tbody></table>
                <span data-bpjs-employee-summary></span>
            </div>
        </body></html>`, { runScripts: 'dangerously', resources: 'usable' });

        dom.window.AuthApi = {
            request: vi.fn((method, path) => {
                if (method === 'GET' && path === '/hcm/bpjs-governance/policies') {
                    return Promise.resolve({
                        data: {
                            items: [
                                { id: 10, programCode: 'bpjs_kesehatan', contributionParty: 'employee', ratePercent: 1, wageBase: 'wage_bpjs_health', isActive: true },
                                { id: 11, programCode: 'bpjs_kesehatan', contributionParty: 'employer', ratePercent: 4, wageBase: 'wage_bpjs_health', isActive: true },
                            ],
                        },
                    });
                }
                if (method === 'GET' && path === '/hcm/bpjs-governance/employee-membership') {
                    return Promise.resolve({
                        data: {
                            items: [
                                { id: 1, fullName: 'Budi', email: 'budi@example.com', bpjsKesehatanNo: 'KES-001', bpjsKetenagakerjaanNo: 'TK-001', membershipStatus: 'complete' },
                                { id: 2, fullName: 'Sari', email: 'sari@example.com', bpjsKesehatanNo: '', bpjsKetenagakerjaanNo: '', membershipStatus: 'missing' },
                            ],
                            meta: { total: 2, complete: 1 },
                        },
                    });
                }
                return Promise.resolve({ data: {} });
            }),
        };
    });

    afterEach(function () {
        dom.window.close();
    });

    it('renders policy summary and membership KPI on landing screen', async function () {
        loadScript(dom, 'bpjs-governance-data.js');
        await flush();
        await flush();

        const summaryBody = dom.window.document.querySelector('[data-bpjs-policy-summary-body]');
        const membershipKpi = dom.window.document.querySelector('[data-bpjs-kpi-membership]');

        expect(summaryBody.innerHTML).toContain('BPJS Kesehatan');
        expect(membershipKpi.textContent).toBe('1/2');
    });
});
