import { beforeEach, describe, expect, it, vi } from 'vitest';

const flush = async () => {
  await Promise.resolve();
  await new Promise((resolve) => setTimeout(resolve, 0));
};

describe('subscription checkout wiring', () => {
  beforeEach(() => {
    vi.resetModules();
    document.body.innerHTML = `
      <div data-subscription-checkout-page data-checkout-mock-pay-enabled="1"></div>
      <div class="alert d-none" data-checkout-feedback></div>
      <form data-checkout-form class="checkout-upgrade-form">
        <input data-checkout-company-name />
        <input data-checkout-company-id />
        <input data-checkout-company-code />
        <button type="button" data-checkout-copy-code></button>
        <select data-checkout-package-select></select>
        <input data-checkout-billing-email />
        <button type="submit" data-checkout-submit>submit</button>
      </form>
      <form data-checkout-form class="checkout-addon-form">
        <select data-checkout-addon-select></select>
        <button type="submit" data-checkout-addon-submit>submit addon</button>
      </form>
      <span data-checkout-company-badge></span>
      <span class="d-none" data-checkout-trial-badge></span>
      <input type="radio" name="billing_cycle" value="monthly" checked />
      <div class="d-none" data-checkout-invoice-box></div>
      <div data-checkout-invoice-hint></div>
      <div data-checkout-invoice-title></div>
      <div data-checkout-invoice-subtitle></div>
      <div data-checkout-invoice-amount></div>
      <div data-checkout-invoice-due></div>
      <div class="d-none" data-checkout-invoice-breakdown></div>
      <a data-checkout-open-invoices href="/company/invoices"></a>
      <button type="button" class="d-none" data-checkout-pay-now>Bayar sekarang</button>
      <a class="d-none" data-checkout-go-dashboard href="/index">Masuk dashboard</a>
    `;

    window.AuthApi = {
      request: vi.fn((method, path) => {
        if (method === 'get' && path === '/hcm/billing/invoices?perPage=20&is_paid=0') {
          return Promise.resolve({
            data: {
              success: true,
              data: [{
                id: 55,
                invoiceNumber: 'INV-55',
                amountDue: 1200000,
                dueDate: '2026-05-01',
                status: 'draft',
                isPaid: false,
                notes: JSON.stringify({
                  pricing_breakdown: {
                    base_amount: 1000000,
                    components: [
                      {
                        key: 'subscription_tax_rate',
                        label: 'Pajak langganan',
                        rate: 7,
                        amount: 70000,
                      },
                      {
                        key: 'addon_markup_rate',
                        label: 'Corporate tax',
                        rate: 22,
                        amount: 220000,
                      },
                    ],
                    service_fee_rate: 0,
                    service_fee_amount: 0,
                    subscription_tax_rate: 7,
                    subscription_tax_amount: 70000,
                    total_amount: 1290000,
                  },
                }),
                createdAt: '2026-04-21T10:00:00Z',
                updatedAt: '2026-04-21T10:00:00Z',
              }],
            },
          });
        }

        if (method === 'post' && path === '/hcm/billing/invoices/55/mock-hosted-checkout') {
          return Promise.resolve({
            data: {
              success: true,
              data: {
                id: 55,
                invoiceNumber: 'INV-55',
                amountDue: 1200000,
                dueDate: '2026-05-01',
                status: 'draft',
                isPaid: false,
              },
              flow: {
                hostedCheckoutUrl: '/mock-hosted-payment.html?payment_uuid=pay-55',
              },
            },
          });
        }

        if (method === 'get' && path === '/hcm/billing/invoices/55') {
          return Promise.resolve({
            data: {
              success: true,
              data: {
                id: 55,
                invoiceNumber: 'INV-55',
                amountDue: 1200000,
                dueDate: '2026-05-01',
                paidDate: '2026-04-21',
                status: 'paid',
                isPaid: true,
              },
            },
          });
        }

        return Promise.reject(new Error(`Unexpected AuthApi call: ${method} ${path}`));
      }),
      getTenantContext: vi.fn(() => ({ companyCode: 'demo_co_01', companyId: 42 })),
    };

    global.fetch = vi.fn((input) => {
      const url = String(input);

      if (url === '/v1/identity/auth/me') {
        return Promise.resolve({
          ok: true,
          json: () => Promise.resolve({
            success: true,
            data: {
              email: 'owner@example.com',
              activeCompany: {
                id: 42,
                code: 'demo_co_01',
                name: 'Demo Company',
              },
            },
          }),
        });
      }

      if (url === '/v1/saas/packages?status=active&per_page=100') {
        return Promise.resolve({
          ok: true,
          json: () => Promise.resolve({
            success: true,
            data: [
              { id: 'pkg-starter', code: 'starter', name: 'Starter' },
              { id: 'pkg-trial', code: 'trial', name: 'Trial' },
            ],
          }),
        });
      }

      if (url === '/v1/saas/package-addons?status=active&per_page=100') {
        return Promise.resolve({
          ok: true,
          json: () => Promise.resolve({
            success: true,
            data: [
              { id: 88, code: 'asset_management', name: 'Asset Management', pricePerUnit: 49000, unitName: 'tenant / month' },
            ],
          }),
        });
      }

      if (url === '/v1/hcm/billing/addons/checkout') {
        return Promise.resolve({
          ok: true,
          json: () => Promise.resolve({
            success: true,
            data: {
              addon: { id: 88, code: 'asset_management', name: 'Asset Management' },
              invoice: {
                id: 99,
                invoiceNumber: 'INV-99',
                amountDue: 59780,
                dueDate: '2026-05-02',
                status: 'draft',
                isPaid: false,
              },
              reused: false,
            },
          }),
        });
      }

      return Promise.reject(new Error(`Unexpected fetch: ${url}`));
    });

    window.__ARCAV_DISABLE_REDIRECTS__ = true;
    window.__ARCAV_LAST_REDIRECT__ = '';
    window.history.replaceState({}, '', '/subscription');
  });

  it('loads pending invoice on boot and opens hosted mock checkout', async () => {
    await import('../../../frontend/resources/js/subscription-checkout.js');
    await flush();

    expect(document.querySelector('[data-checkout-invoice-box]')?.classList.contains('d-none')).toBe(false);
    expect(document.querySelector('[data-checkout-invoice-title]')?.textContent).toContain('Invoice pending ditemukan');
    expect(document.querySelector('[data-checkout-pay-now]')?.classList.contains('d-none')).toBe(false);
    expect(document.querySelector('[data-checkout-invoice-breakdown]')?.textContent).not.toContain('Biaya layanan');
    expect(document.querySelector('[data-checkout-invoice-breakdown]')?.textContent).toContain('Pajak langganan 7%');
    expect(document.querySelector('[data-checkout-invoice-breakdown]')?.textContent).toContain('Corporate tax 22%');
    expect(document.querySelector('[data-checkout-invoice-breakdown]')?.classList.contains('d-none')).toBe(false);

    // Form must be locked (hidden) when a pending invoice exists — prevents double invoice creation.
    expect(document.querySelector('[data-checkout-form]')?.classList.contains('d-none')).toBe(true);
    // Feedback must warn, not just info.
    expect(document.querySelector('[data-checkout-feedback]')?.textContent).toContain('Ada invoice pending');

    document.querySelector('[data-checkout-pay-now]')?.dispatchEvent(new MouseEvent('click', { bubbles: true }));
    await flush();

    expect(window.AuthApi.request).toHaveBeenCalledWith('post', '/hcm/billing/invoices/55/mock-hosted-checkout', {});
    expect(window.__ARCAV_LAST_REDIRECT__).toContain('/mock-hosted-payment.html?payment_uuid=pay-55');
  });

  it('restores paid invoice state after returning from hosted gateway', async () => {
    window.history.replaceState({}, '', '/subscription?mock_payment_status=completed&invoice_id=55');

    await import('../../../frontend/resources/js/subscription-checkout.js');
    await flush();

    expect(window.AuthApi.request).toHaveBeenCalledWith('get', '/hcm/billing/invoices/55', undefined);
    expect(document.querySelector('[data-checkout-invoice-title]')?.textContent).toContain('Invoice sudah dibayar');
    expect(document.querySelector('[data-checkout-go-dashboard]')?.classList.contains('d-none')).toBe(false);
    expect(document.querySelector('[data-checkout-feedback]')?.textContent).toContain('Pembayaran berhasil.');
    expect(document.querySelector('[data-checkout-form]')?.classList.contains('d-none')).toBe(true);
    expect(document.querySelector('[data-checkout-pay-now]')?.classList.contains('d-none')).toBe(true);
  });

  it('submits add-on checkout separately from package checkout', async () => {
    window.AuthApi.request.mockImplementation((method, path) => {
      if (method === 'get' && path === '/hcm/billing/invoices?perPage=20&is_paid=0') {
        return Promise.resolve({ data: { success: true, data: [] } });
      }

      throw new Error(`Unexpected AuthApi call: ${method} ${path}`);
    });

    await import('../../../frontend/resources/js/subscription-checkout.js');
    await flush();

    const addonSelect = document.querySelector('[data-checkout-addon-select]');
    if (addonSelect) {
      addonSelect.value = '88';
    }

    document.querySelector('[data-checkout-form].checkout-addon-form')
      ?.dispatchEvent(new Event('submit', { bubbles: true }));
    await flush();

    expect(global.fetch).toHaveBeenCalledWith(
      '/v1/hcm/billing/addons/checkout',
      expect.objectContaining({ method: 'POST' })
    );
    expect(document.querySelector('[data-checkout-invoice-title]')?.textContent).toContain('Invoice dibuat');
    expect(document.querySelector('[data-checkout-feedback]')?.textContent).toContain('Invoice add-on berhasil dibuat');
  });
});