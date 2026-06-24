import { beforeEach, describe, expect, it, vi } from 'vitest';
import { loadPricingPlansScreenModule } from '../../../frontend/resources/js/tax-governance/tax-governance-platform-pricing.js';
import { renderPlatformReportModule } from '../../../frontend/resources/js/tax-governance/tax-governance-platform-report.js';

function flush(times = 6) {
  return Array.from({ length: times }).reduce(
    (promise) => promise.then(() => new Promise((resolve) => setTimeout(resolve, 0))),
    Promise.resolve(),
  );
}

function createDom() {
  document.body.innerHTML = `
    <div class="page-wrapper" data-tax-governance-page data-tax-governance-screen="platform-billing">
      <div class="alert d-none" data-tax-governance-error></div>
      <div class="alert d-none" data-tax-platform-gate></div>
      <input type="month" data-tax-platform-report-month value="2026-05">
      <table>
        <thead>
          <tr>
            <th>Nama Plan</th>
            <th>Harga Bulanan</th>
            <th>Harga Tahunan</th>
            <th>Billing Unit</th>
            <th>Fitur</th>
            <th>Status</th>
            <th>Aksi</th>
          </tr>
        </thead>
        <tbody data-pricing-plans-table>
          <tr><td colspan="7">Memuat subscription plans...</td></tr>
        </tbody>
      </table>
      <table>
        <thead>
          <tr>
            <th>Nama Add-on</th>
            <th>Harga / Unit (Rp)</th>
            <th>Unit</th>
            <th>Status</th>
            <th>Dibuat</th>
            <th>Aksi</th>
          </tr>
        </thead>
        <tbody data-pricing-addons-table>
          <tr><td colspan="6">Memuat add-on catalog...</td></tr>
        </tbody>
      </table>
      <form data-pricing-addon-form>
        <input type="hidden" name="addon_id" value="">
        <input type="hidden" name="code" value="">
        <input type="number" name="price_per_unit" value="0">
        <div class="alert d-none" data-pricing-addon-form-error></div>
        <button type="submit" data-pricing-addon-submit>Simpan</button>
      </form>
      <span data-addon-name-display></span>
      <span data-addon-code-display></span>
      <div id="addonCrudModal"></div>
      <span data-tax-platform-summary-subscription-revenue></span>
      <span data-tax-platform-summary-addon-revenue></span>
      <span data-tax-platform-summary-net-revenue></span>
      <table>
        <thead>
          <tr>
            <th>Tenant</th>
            <th>Plan</th>
            <th>Subscription (Rp)</th>
            <th>Add-on (Rp)</th>
            <th>Gross Revenue (Rp)</th>
            <th>Billing Charge (Rp)</th>
            <th>Total Revenue (Rp)</th>
          </tr>
        </thead>
        <tbody data-tax-platform-report-table>
          <tr><td colspan="7">Memuat report...</td></tr>
        </tbody>
      </table>
    </div>
  `;
}

function formatMoney(value) {
  return `Rp ${Number(value || 0).toLocaleString('id-ID')}`;
}

function formatDate(value) {
  if (!value) return '-';
  const date = new Date(value);
  return Number.isNaN(date.getTime()) ? String(value) : date.toISOString().slice(0, 10);
}

function toTitleCase(value) {
  return String(value || '')
    .replace(/[_-]+/g, ' ')
    .replace(/\b\w/g, (char) => char.toUpperCase());
}

function escapeHtml(value) {
  return String(value || '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');
}

describe('saas pricing screen wiring', () => {
  let apiGet;
  let apiPut;
  let root;

  beforeEach(() => {
    createDom();
    window.ArcavValidation = { validateForm: vi.fn().mockReturnValue(true) };
    window.bootstrap = {
      Modal: {
        getInstance() {
          return { hide() {} };
        },
      },
    };

    apiGet = vi.fn((path) => {
      if (path === '/saas/packages') {
        return Promise.resolve({
          success: true,
          data: [
            {
              id: 'pkg-growth',
              code: 'growth',
              name: 'Growth',
              monthlyPrice: 350000,
              yearlyPrice: 3500000,
              billingUnit: 'company',
              status: 'active',
              features: [{ code: 'payroll' }, { code: 'attendance' }],
            },
          ],
        });
      }

      if (path === '/saas/package-addons') {
        return Promise.resolve({
          success: true,
          data: [
            {
              id: 7,
              code: 'asset_management',
              name: 'Asset Management',
              description: 'Kelola aset tenant',
              pricePerUnit: 175000,
              unitName: 'tenant / bulan',
              status: 'active',
              createdAt: '2026-05-03T10:00:00Z',
            },
          ],
        });
      }

      if (path === '/hcm/tax-governance/platform-billing/reports') {
        return Promise.resolve({
          success: true,
          data: {
            summary_global: {
              total_subscription_revenue: 900000,
              total_addon_revenue: 175000,
              total_gross_revenue: 1075000,
              total_tax_due: 107500,
              total_net_revenue: 967500,
            },
            tenants_global: [
              {
                company_id: 12,
                company_name: 'PT Maju Bersama',
                tenant: 'PT Maju Bersama',
                plan: 'Growth',
                subscription_revenue: 900000,
                addon_revenue: 175000,
                gross_revenue: 1075000,
                tax_amount_due: 107500,
                net_revenue: 967500,
              },
            ],
          },
        });
      }

      return Promise.resolve({ success: true, data: [] });
    });

    apiPut = vi.fn(() => Promise.resolve({ success: true, data: {} }));
    root = document.querySelector('[data-tax-governance-page]');
  });

  function runModule() {
    return loadPricingPlansScreenModule({
      qs: (selector, scope) => (scope || document).querySelector(selector),
      apiGet,
      apiPut,
      parseApiError: (error, fallbackMessage) => ({ message: error?.message || fallbackMessage }),
      showError: (scope, message) => {
        const node = (scope || document).querySelector('[data-tax-governance-error]');
        if (node) node.textContent = message;
      },
      getCurrentMonthValue: () => '2026-05',
      formatMoney,
      formatDate,
      toTitleCase,
      escapeHtml,
      renderPlatformReport: (scope, response) => renderPlatformReportModule({
        qs: (selector, localScope) => (localScope || document).querySelector(selector),
        setText: (node, value) => {
          if (node) node.textContent = value == null ? '' : String(value);
        },
        formatMoney,
        getActiveScreen: (localRoot) => localRoot?.getAttribute('data-tax-governance-screen') || null,
        showPlatformGate: () => {},
        escapeHtml,
        renderBillingCycleDetail: () => '-',
      }, scope, response),
    }, root);
  }

  it('renders plans, add-ons, and revenue summary for the pricing screen', async () => {
    runModule();
    await flush();

    const plansText = String(document.querySelector('[data-pricing-plans-table]')?.textContent || '');
    const addonsText = String(document.querySelector('[data-pricing-addons-table]')?.textContent || '');
    const reportText = String(document.querySelector('[data-tax-platform-report-table]')?.textContent || '');

    expect(plansText).toContain('Growth');
    expect(plansText).toContain('Rp 350.000');
    expect(addonsText).toContain('Asset Management');
    expect(addonsText).toContain('tenant / bulan');
    expect(String(document.querySelector('[data-tax-platform-summary-subscription-revenue]')?.textContent || '')).toContain('900.000');
    expect(String(document.querySelector('[data-tax-platform-summary-addon-revenue]')?.textContent || '')).toContain('175.000');
    expect(String(document.querySelector('[data-tax-platform-summary-net-revenue]')?.textContent || '')).toContain('1.075.000');
    expect(reportText).toContain('PT Maju Bersama');
    expect(reportText).toContain('Growth');
  });

  it('toggles add-on status through the package add-ons endpoint', async () => {
    runModule();
    await flush();

    document.querySelector('[data-pricing-addon-toggle="7"]').click();
    await flush();

    expect(apiPut).toHaveBeenCalledWith('/saas/package-addons/7', { status: 'inactive' });
  });

  it('submits edited add-on price through the package add-ons endpoint', async () => {
    runModule();
    await flush();

    document.querySelector('[data-pricing-addon-edit="7"]').click();
    document.querySelector('[data-pricing-addon-form] [name="price_per_unit"]').value = '200000';
    document.querySelector('[data-pricing-addon-form]').dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
    await flush();

    expect(apiPut).toHaveBeenCalledWith('/saas/package-addons/7', { price_per_unit: 200000 });
  });
});