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
        <span id="ppn_period_label"></span>
        <span id="ppn_batas_setor_badge"></span>
        <span id="ppn_batas_lapor_badge"></span>
        <span id="ppn_total_dpp"></span>
        <span id="ppn_total_keluaran"></span>
        <span id="ppn_total_masukan"></span>
        <span id="ppn_kurang_bayar"></span>
        <tbody id="ppn_detail_tbody"></tbody>
        <span id="pph23_period_label"></span>
        <span id="pph23_batas_setor_badge"></span>
        <span id="pph23_batas_lapor_badge"></span>
        <span id="pph23_total_bruto"></span>
        <span id="pph23_total_terutang"></span>
        <span id="pph23_payment_count"></span>
        <tbody id="pph23_detail_tbody"></tbody>
        <span id="pph_badan_period_label"></span>
        <span id="pph_badan_status_badge"></span>
        <span id="pph_badan_batas_pelunasan_badge"></span>
        <span id="pph_badan_batas_lapor_badge"></span>
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
              batas_pelunasan: '2027-04-30',
              batas_lapor: '2027-04-30',
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

      if (urlString.includes('/spt-ppn?')) {
        return Promise.resolve({
          json: () => Promise.resolve({
            success: true,
            data: {
              period: '2026-05',
              masa_pajak: 'May 2026',
              batas_setor: '2026-06-15',
              batas_lapor: '2026-06-30',
              summary: {
                total_penyerahan_dpp: 100000,
                total_ppn_keluaran: 11000,
                total_ppn_masukan: 0,
                ppn_kurang_bayar: 11000,
              },
              detail_penyerahan: [],
            },
          }),
        });
      }

      if (urlString.includes('/spt-pph23?')) {
        return Promise.resolve({
          json: () => Promise.resolve({
            success: true,
            data: {
              period: '2026-05',
              masa_pajak: 'May 2026',
              batas_setor: '2026-06-10',
              batas_lapor: '2026-06-20',
              summary: {
                total_bruto: 50000,
                total_pph23_terutang: 1000,
                payment_count: 1,
              },
              detail_pemotongan: [],
            },
          }),
        });
      }

      if (urlString.includes('/dashboard?')) {
        return Promise.resolve({
          json: () => Promise.resolve({
            success: true,
            data: {
              ppn_rate: 12,
              revenue_summary: {
                gross_revenue: 111000,
                paid_revenue: 111000,
                pending_revenue: 0,
                dpp_ppn: 100000,
                tenant_count: 1,
                invoice_count: 1,
              },
              tax_obligations: {
                ppn: {
                  label: 'PPN (Pajak Pertambahan Nilai)',
                  dasar_hukum: 'UU HPP No. 7/2021 — Pasal 7 (1)',
                  rate: 11,
                  dpp: 100000,
                  amount: 11000,
                  batas_setor: '2026-06-15',
                  batas_lapor: '2026-06-30',
                  kode_akun_pajak: '411211',
                },
                pph23: {
                  label: 'PPh Pasal 23 atas Jasa Platform',
                  dasar_hukum: 'PMK-141/PMK.03/2015',
                  rate: 2,
                  dpp: 50000,
                  amount: 1000,
                  batas_setor: '2026-06-10',
                  batas_lapor: '2026-06-20',
                  kode_akun_pajak: '411124',
                },
              },
              total_kewajiban_pajak: 12000,
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

  it('renders monthly and annual tax deadline badges', async () => {
    await flush();

    expect(String(dom.window.document.getElementById('ppn_batas_setor_badge')?.textContent || '')).toContain('15 Jun 2026');
    expect(String(dom.window.document.getElementById('ppn_batas_lapor_badge')?.textContent || '')).toContain('30 Jun 2026');
    expect(String(dom.window.document.getElementById('pph23_batas_setor_badge')?.textContent || '')).toContain('10 Jun 2026');
    expect(String(dom.window.document.getElementById('pph23_batas_lapor_badge')?.textContent || '')).toContain('20 Jun 2026');
    expect(String(dom.window.document.getElementById('pph_badan_batas_pelunasan_badge')?.textContent || '')).toContain('30 Apr 2027');
    expect(String(dom.window.document.getElementById('pph_badan_batas_lapor_badge')?.textContent || '')).toContain('30 Apr 2027');
  });
});
