/**
 * platform-tax.wiring.test.js
 *
 * Guards the reporting page export behavior so all tabs export XLSX
 * via their respective backend endpoints.
 */
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { JSDOM } from 'jsdom';
import { readFileSync } from 'fs';
import { resolve } from 'path';

function loadScript(dom, relPath) {
  const resolvedPath = relPath === 'platform-tax.js'
    ? 'payroll/platform-tax.js'
    : relPath;
  const code = readFileSync(resolve(__dirname, '../../../frontend/resources/js/' + resolvedPath), 'utf8');
  const el = dom.window.document.createElement('script');
  el.textContent = code;
  dom.window.document.body.appendChild(el);
}

function flush() {
  return new Promise((resolvePromise) => setTimeout(resolvePromise, 0));
}

describe('platform tax export wiring', () => {
  let dom;
  let openSpy;

  beforeEach(() => {
    dom = new JSDOM(`<!DOCTYPE html><html><body>
      <div data-platform-tax-page>
        <input id="input_tax_month" value="2026-05">
        <div id="display_ppn_rate"></div>
        <button id="btn_load_tax_data"></button>
        <button id="btn_print_tax"></button>
        <div id="tax-tabs">
          <button class="nav-link active" data-bs-target="#tab-pph-badan"></button>
        </div>
        <div id="kpi_cards_container"></div>
        <tbody id="tax_obligations_tbody"></tbody>
        <tr id="tax_total_row"></tr>
        <span id="tax_total_amount"></span>
        <div id="revenue_breakdown_body"></div>
        <span id="pph_badan_period_label"></span>
        <span id="pph_badan_status_badge"></span>
        <span id="pph_badan_taxable_revenue"></span>
        <span id="pph_badan_tax_payable"></span>
        <span id="pph_badan_net_revenue"></span>
        <span id="pph_badan_net_profit"></span>
        <tbody id="pph_badan_detail_tbody"></tbody>
      </div>
    </body></html>`, {
      runScripts: 'dangerously',
      url: 'http://localhost/saas/platform-tax',
    });

    dom.window.fetch = vi.fn((url) => {
      const urlString = String(url);
      if (urlString.includes('/active-ppn-rate')) {
        return Promise.resolve({
          json: () => Promise.resolve({
            success: true,
            data: {
              ppn_rate: 12,
              source: 'compliance_settings',
            },
          }),
        });
      }

      if (urlString.includes('/spt-pph-badan?')) {
        return Promise.resolve({
          json: () => Promise.resolve({
            success: true,
            data: {
              year: 2026,
              summary: {
                total_taxable_revenue: 1200000,
                total_transaction_tax_liability: 120000,
                total_pph_badan_payable: 220000,
                total_net_profit_estimate: 860000,
              },
              monthly_breakdown: [
                {
                  month: '2026-05',
                  taxable_revenue: 1200000,
                  transaction_tax_liability: 120000,
                  pph_badan_payable: 220000,
                  net_profit_estimate: 860000,
                  effective_pph_badan_rate: 22,
                  policy_configured: true,
                },
              ],
            },
          }),
        });
      }

      return Promise.resolve({
        json: () => Promise.resolve({ success: true, data: { revenue_summary: {}, tax_obligations: {}, total_kewajiban_pajak: 0, summary: {}, detail_penyerahan: [], detail_pemotongan: [] } }),
      });
    });

    openSpy = vi.spyOn(dom.window, 'open').mockImplementation(() => null);
    loadScript(dom, 'platform-tax.js');
  });

  it('uses xlsx export when PPh Badan tab is active', async () => {
    await flush();
    dom.window.document.getElementById('btn_print_tax').click();

    expect(openSpy).toHaveBeenCalledTimes(1);
    expect(String(openSpy.mock.calls[0][0])).toContain('/v1/saas/tax/spt-pph-badan/export');
    expect(String(openSpy.mock.calls[0][0])).toContain('format=xlsx');
    expect(String(openSpy.mock.calls[0][0])).not.toContain('format=csv');
    expect(String(openSpy.mock.calls[0][1])).toBe('_self');
  });

  it('uses dashboard xlsx export when Dashboard tab is active', async () => {
    await flush();
    const activeTab = dom.window.document.querySelector('#tax-tabs .nav-link.active');
    activeTab.setAttribute('data-bs-target', '#tab-dashboard');

    dom.window.document.getElementById('btn_print_tax').click();

    expect(openSpy).toHaveBeenCalled();
    expect(String(openSpy.mock.calls[openSpy.mock.calls.length - 1][0])).toContain('/v1/saas/tax/dashboard/export');
    expect(String(openSpy.mock.calls[openSpy.mock.calls.length - 1][0])).toContain('format=xlsx');
  });

  it('uses SPT PPN xlsx export when PPN tab is active', async () => {
    await flush();
    const activeTab = dom.window.document.querySelector('#tax-tabs .nav-link.active');
    activeTab.setAttribute('data-bs-target', '#tab-ppn');

    dom.window.document.getElementById('btn_print_tax').click();

    expect(openSpy).toHaveBeenCalled();
    expect(String(openSpy.mock.calls[openSpy.mock.calls.length - 1][0])).toContain('/v1/saas/tax/spt-ppn/export');
    expect(String(openSpy.mock.calls[openSpy.mock.calls.length - 1][0])).toContain('format=xlsx');
  });

  it('uses SPT PPh23 xlsx export when PPh23 tab is active', async () => {
    await flush();
    const activeTab = dom.window.document.querySelector('#tax-tabs .nav-link.active');
    activeTab.setAttribute('data-bs-target', '#tab-pph23');

    dom.window.document.getElementById('btn_print_tax').click();

    expect(openSpy).toHaveBeenCalled();
    expect(String(openSpy.mock.calls[openSpy.mock.calls.length - 1][0])).toContain('/v1/saas/tax/spt-pph23/export');
    expect(String(openSpy.mock.calls[openSpy.mock.calls.length - 1][0])).toContain('format=xlsx');
  });

  it('renders readonly ppn rate from compliance endpoint', async () => {
    await flush();

    const text = String(dom.window.document.getElementById('display_ppn_rate')?.textContent || '');
    expect(text).toContain('12');
    expect(text).toContain('Compliance Settings');
  });
});
