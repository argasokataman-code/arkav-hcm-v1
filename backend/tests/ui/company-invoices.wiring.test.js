import { beforeEach, describe, expect, it, vi } from 'vitest';

const flush = async () => {
  await Promise.resolve();
  await new Promise((resolve) => setTimeout(resolve, 0));
};

describe('company invoices wiring', () => {
  beforeEach(() => {
    vi.resetModules();
    vi.restoreAllMocks();

    document.body.innerHTML = `
      <div data-company-invoices-page></div>
      <input id="search_invoices" />
      <select id="filter_invoice_status"><option value=""></option><option value="paid">paid</option></select>
      <select id="filter_invoice_paid"><option value=""></option><option value="1">paid</option></select>
      <button type="button" id="btn_reset_invoice_filters"></button>
      <div class="alert alert-danger d-none" data-company-invoices-feedback></div>
      <div id="total_due"></div>
      <div id="count_unpaid"></div>
      <div id="count_overdue"></div>
      <div id="paid_this_month"></div>
      <div data-company-invoices-list-container></div>
      <div data-company-invoice-modal></div>
      <div data-company-invoice-print-root></div>
      <div data-invoice-modal-number></div>
      <div data-invoice-modal-status-wrap><span data-invoice-modal-status></span></div>
      <div data-invoice-modal-company></div>
      <div data-invoice-modal-package-name></div>
      <div data-invoice-modal-package-summary></div>
      <div data-invoice-modal-summary></div>
      <div data-invoice-modal-payment-status-wrap><span data-invoice-modal-payment-status></span></div>
      <div data-invoice-modal-issue-date></div>
      <div data-invoice-modal-due-date></div>
      <div data-invoice-modal-paid-date></div>
      <div data-invoice-modal-billing-cycle></div>
      <div data-invoice-modal-next-billing-date></div>
      <div data-invoice-modal-current-period></div>
      <div data-invoice-modal-amount></div>
      <div data-invoice-modal-line-label></div>
      <div data-invoice-modal-line-caption></div>
      <div data-invoice-modal-charge-status-wrap><span data-invoice-modal-charge-status></span></div>
      <div data-invoice-modal-table-amount></div>
      <div data-invoice-modal-table-total></div>
      <div data-invoice-modal-guidance></div>
      <div data-invoice-modal-terms-summary></div>
      <div data-invoice-modal-header-terms></div>
      <div data-invoice-modal-footer-terms></div>
      <div data-invoice-modal-notes></div>
      <button type="button" data-company-invoice-download></button>
      <button type="button" data-company-invoice-print></button>
    `;

    window.bootstrap = {
      Modal: {
        getOrCreateInstance: () => ({ show: vi.fn() }),
      },
    };

    vi.spyOn(HTMLAnchorElement.prototype, 'click').mockImplementation(() => {});

    window.AuthApi = {
      request: vi.fn((method, path, payload) => {
        if (method === 'get' && path === '/hcm/billing/invoices') {
          return Promise.resolve({
            data: {
              success: true,
              data: [
                {
                  id: 88,
                  invoiceNumber: 'INV-88',
                  company: 'Tenant Pro',
                  subscriptionId: 70,
                  packageName: 'Starter',
                  packageCode: 'starter',
                  billingCycle: 'monthly',
                  billingCycleLabel: 'Bulanan',
                  nextBillingDate: '2026-05-10',
                  currentPeriodStart: '2026-04-10',
                  currentPeriodEnd: '2026-05-10',
                  issueDate: '2026-04-10',
                  dueDate: '2026-04-30',
                  amountDue: 1500000,
                  status: 'draft',
                  isPaid: false,
                },
                {
                  id: 89,
                  invoiceNumber: 'INV-89',
                  company: 'Tenant Pro',
                  subscriptionId: 71,
                  packageName: 'Growth',
                  packageCode: 'growth',
                  billingCycle: 'yearly',
                  billingCycleLabel: 'Tahunan',
                  nextBillingDate: '2027-04-06',
                  currentPeriodStart: '2026-04-06',
                  currentPeriodEnd: '2027-04-06',
                  issueDate: '2026-04-01',
                  dueDate: '2026-04-05',
                  amountDue: 2400000,
                  status: 'paid',
                  isPaid: true,
                  paidDate: '2026-04-06',
                  notes: 'Paid invoice',
                },
              ],
              meta: { total: 2 },
            },
          });
        }

        if (method === 'get' && path === '/hcm/billing/invoices/89') {
          return Promise.resolve({
            data: {
              success: true,
              data: {
                id: 89,
                invoiceNumber: 'INV-89',
                company: 'Tenant Pro',
                subscriptionId: 71,
                packageName: 'Growth',
                packageCode: 'growth',
                billingCycle: 'yearly',
                billingCycleLabel: 'Tahunan',
                nextBillingDate: '2027-04-06',
                currentPeriodStart: '2026-04-06',
                currentPeriodEnd: '2027-04-06',
                issueDate: '2026-04-01',
                dueDate: '2026-04-05',
                amountDue: 2400000,
                status: 'paid',
                isPaid: true,
                notes: 'Paid invoice',
              },
            },
          });
        }

        if (method === 'get' && path === '/hcm/invoice-settings') {
          return Promise.resolve({
            data: {
              success: true,
              data: {
                invoice_prefix: 'TENANT-',
                invoice_due_days: '45',
                invoice_show_tax: '0',
                invoice_round_off_enabled: '1',
                invoice_round_off: 'round_up',
                invoice_header_terms: 'Header term tenant invoice.',
                invoice_footer_terms: 'Footer term tenant invoice.',
              },
            },
          });
        }

        if (method === 'post' && path === '/hcm/billing/invoices/88/mock-pay') {
          return Promise.resolve({ data: { success: true } });
        }

        throw new Error(`Unexpected AuthApi call: ${method} ${path} ${JSON.stringify(payload)}`);
      }),
      downloadV1Binary: vi.fn(() => Promise.resolve()),
    };
  });

  it('renders invoice rows, updates billing summary, and downloads with tenant-aware helper', async () => {
    await import('../../../frontend/resources/js/company-invoices.js');
    await flush();

    expect(document.querySelector('[data-company-invoices-list-container]')?.textContent).toContain('INV-88');
    expect(document.querySelector('[data-company-invoices-list-container]')?.textContent).toContain('Starter');
    expect(document.querySelector('[data-company-invoices-list-container]')?.textContent).toContain('Next payment');
    expect(document.getElementById('total_due')?.textContent).toContain('Rp');
    expect(document.getElementById('count_unpaid')?.textContent).toBe('1');
    expect(document.getElementById('paid_this_month')?.textContent).toContain('Rp');

    document.querySelector('[data-invoice-download="88"]')?.dispatchEvent(new MouseEvent('click', { bubbles: true }));
    await flush();

    expect(window.AuthApi.downloadV1Binary).toHaveBeenCalledWith('/hcm/billing/invoices/88/download', 'INV-88.pdf');
  });

  it('sends filters back to the invoices endpoint', async () => {
    await import('../../../frontend/resources/js/company-invoices.js');
    await flush();

    document.getElementById('filter_invoice_paid').value = '1';
    document.getElementById('filter_invoice_paid').dispatchEvent(new Event('change', { bubbles: true }));
    await flush();

    expect(window.AuthApi.request).toHaveBeenCalledWith('get', '/hcm/billing/invoices', expect.objectContaining({ perPage: 50, is_paid: '1' }));
  });

  it('falls back to cookie-based fetch when AuthApi is not initialized', async () => {
    delete window.AuthApi;
    global.fetch = vi.fn((input, options) => {
      if (String(input) === '/v1/hcm/billing/invoices?perPage=50') {
        return Promise.resolve({
          ok: true,
          json: () => Promise.resolve({
            success: true,
            data: [
              {
                id: 99,
                invoiceNumber: 'INV-99',
                company: 'Tenant Pro',
                subscriptionId: 70,
                packageName: 'Starter',
                packageCode: 'starter',
                billingCycle: 'monthly',
                billingCycleLabel: 'Bulanan',
                nextBillingDate: '2026-05-21',
                issueDate: '2026-04-21',
                dueDate: '2026-04-28',
                amountDue: 199000,
                status: 'paid',
                isPaid: true,
                paidDate: '2026-04-21',
              },
            ],
          }),
        });
      }

      return Promise.reject(new Error(`Unexpected fetch: ${String(input)} ${JSON.stringify(options || {})}`));
    });

    await import('../../../frontend/resources/js/company-invoices.js');
    await flush();

    expect(global.fetch).toHaveBeenCalledWith(
      '/v1/hcm/billing/invoices?perPage=50',
      expect.objectContaining({ method: 'GET', credentials: 'same-origin' })
    );
    expect(document.querySelector('[data-company-invoices-list-container]')?.textContent).toContain('INV-99');
    expect(document.querySelector('[data-company-invoices-feedback]')?.classList.contains('d-none')).toBe(true);
  });

  it('downloads pdf via fetch fallback when AuthApi.downloadV1Binary is unavailable', async () => {
    window.AuthApi = {
      request: vi.fn((method, path) => {
        if (method === 'get' && path === '/hcm/billing/invoices') {
          return Promise.resolve({
            data: {
              success: true,
              data: [
                {
                  id: 90,
                  invoiceNumber: 'INV-90',
                  company: 'Tenant Pro',
                  subscriptionId: 71,
                  packageName: 'Growth',
                  packageCode: 'growth',
                  billingCycle: 'yearly',
                  billingCycleLabel: 'Tahunan',
                  nextBillingDate: '2027-04-30',
                  issueDate: '2026-04-10',
                  dueDate: '2026-04-30',
                  amountDue: 1500000,
                  status: 'paid',
                  isPaid: true,
                },
              ],
            },
          });
        }
        if (method === 'get' && path === '/hcm/invoice-settings') {
          return Promise.resolve({
            data: {
              success: true,
              data: {
                invoice_prefix: 'INV-',
                invoice_due_days: '30',
                invoice_show_tax: '1',
                invoice_round_off_enabled: '0',
                invoice_round_off: 'none',
                invoice_header_terms: '',
                invoice_footer_terms: '',
              },
            },
          });
        }
        if (method === 'get' && path === '/hcm/invoice-settings') {
          return Promise.resolve({
            data: {
              success: true,
              data: {
                invoice_prefix: 'INV-',
                invoice_due_days: '30',
                invoice_show_tax: '1',
                invoice_round_off_enabled: '0',
                invoice_round_off: 'none',
                invoice_header_terms: '',
                invoice_footer_terms: '',
              },
            },
          });
        }
        if (method === 'get' && path === '/hcm/invoice-settings') {
          return Promise.resolve({
            data: {
              success: true,
              data: {
                invoice_prefix: 'INV-',
                invoice_due_days: '30',
                invoice_show_tax: '1',
                invoice_round_off_enabled: '0',
                invoice_round_off: 'none',
                invoice_header_terms: '',
                invoice_footer_terms: '',
              },
            },
          });
        }
        throw new Error(`Unexpected AuthApi call: ${method} ${path}`);
      }),
      getTenantContext: vi.fn(() => ({ companyCode: 'tenant_pro', companyId: 77 })),
    };

    const blob = new Blob(['%PDF-1.4 test'], { type: 'application/pdf' });
    global.fetch = vi.fn((input, options) => {
      if (String(input) === '/v1/hcm/billing/invoices/90/download') {
        return Promise.resolve({
          ok: true,
          headers: { get: (name) => (String(name).toLowerCase() === 'content-type' ? 'application/pdf' : null) },
          blob: () => Promise.resolve(blob),
        });
      }
      return Promise.reject(new Error(`Unexpected fetch: ${String(input)} ${JSON.stringify(options || {})}`));
    });

    const createObjectURL = vi.fn(() => 'blob:test-pdf');
    const revokeObjectURL = vi.fn();
    global.URL.createObjectURL = createObjectURL;
    global.URL.revokeObjectURL = revokeObjectURL;

    await import('../../../frontend/resources/js/company-invoices.js');
    await flush();

    document.querySelector('[data-invoice-download="90"]')?.dispatchEvent(new MouseEvent('click', { bubbles: true }));
    await flush();

    expect(global.fetch).toHaveBeenCalledWith(
      '/v1/hcm/billing/invoices/90/download',
      expect.objectContaining({
        method: 'GET',
        credentials: 'same-origin',
        headers: expect.objectContaining({
          Accept: 'application/octet-stream, */*',
          'X-Company-Code': 'tenant_pro',
          'X-Company-Id': '77',
        }),
      })
    );
    expect(createObjectURL).toHaveBeenCalled();
    expect(document.querySelector('[data-company-invoices-feedback]')?.classList.contains('d-none')).toBe(true);
  });

  it('loads invoice detail into the preview modal when view is clicked', async () => {
    const showSpy = vi.fn();
    window.bootstrap = {
      Modal: {
        getOrCreateInstance: () => ({ show: showSpy }),
      },
    };

    await import('../../../frontend/resources/js/company-invoices.js');
    await flush();

    document.querySelector('[data-invoice-view="89"]')?.dispatchEvent(new MouseEvent('click', { bubbles: true }));
    await flush();

    expect(window.AuthApi.request).toHaveBeenCalledWith('get', '/hcm/billing/invoices/89', undefined);
    expect(window.AuthApi.request).toHaveBeenCalledWith('get', '/hcm/invoice-settings', undefined);
    expect(document.querySelector('[data-invoice-modal-number]')?.textContent).toContain('INV-89');
    expect(document.querySelector('[data-invoice-modal-payment-status]')?.textContent).toContain('Paid');
    expect(document.querySelector('[data-invoice-modal-package-name]')?.textContent).toContain('Growth');
    expect(document.querySelector('[data-invoice-modal-next-billing-date]')?.textContent).toContain('2027');
    expect(document.querySelector('[data-invoice-modal-guidance]')?.textContent).toContain('Pembayaran sudah diterima');
    expect(document.querySelector('[data-invoice-modal-terms-summary]')?.textContent).toContain('Prefix TENANT-');
    expect(document.querySelector('[data-invoice-modal-header-terms]')?.textContent).toContain('Header term tenant invoice');
    expect(document.querySelector('[data-invoice-modal-footer-terms]')?.textContent).toContain('Footer term tenant invoice');
    expect(document.querySelector('[data-invoice-modal-table-total]')?.textContent).toContain('Rp');
    expect(showSpy).toHaveBeenCalled();
  });

  it('closes fallback modal via dismiss button when bootstrap runtime is unavailable', async () => {
    delete window.bootstrap;

    document.body.innerHTML = `
      <div data-company-invoices-page></div>
      <input id="search_invoices" />
      <select id="filter_invoice_status"><option value=""></option></select>
      <select id="filter_invoice_paid"><option value=""></option></select>
      <button type="button" id="btn_reset_invoice_filters"></button>
      <div class="alert alert-danger d-none" data-company-invoices-feedback></div>
      <div id="total_due"></div>
      <div id="count_unpaid"></div>
      <div id="count_overdue"></div>
      <div id="paid_this_month"></div>
      <div data-company-invoices-list-container></div>
      <div data-company-invoice-modal class="modal fade" aria-hidden="true">
        <button type="button" data-bs-dismiss="modal">Close</button>
      </div>
      <div data-company-invoice-print-root></div>
      <div data-invoice-modal-number></div>
      <div data-invoice-modal-status-wrap><span data-invoice-modal-status></span></div>
      <div data-invoice-modal-company></div>
      <div data-invoice-modal-package-name></div>
      <div data-invoice-modal-package-summary></div>
      <div data-invoice-modal-summary></div>
      <div data-invoice-modal-payment-status-wrap><span data-invoice-modal-payment-status></span></div>
      <div data-invoice-modal-issue-date></div>
      <div data-invoice-modal-due-date></div>
      <div data-invoice-modal-paid-date></div>
      <div data-invoice-modal-billing-cycle></div>
      <div data-invoice-modal-next-billing-date></div>
      <div data-invoice-modal-current-period></div>
      <div data-invoice-modal-amount></div>
      <div data-invoice-modal-line-label></div>
      <div data-invoice-modal-line-caption></div>
      <div data-invoice-modal-charge-status-wrap><span data-invoice-modal-charge-status></span></div>
      <div data-invoice-modal-table-amount></div>
      <div data-invoice-modal-table-total></div>
      <div data-invoice-modal-guidance></div>
      <div data-invoice-modal-notes></div>
      <button type="button" data-company-invoice-download></button>
      <button type="button" data-company-invoice-print></button>
    `;

    window.AuthApi = {
      request: vi.fn((method, path) => {
        if (method === 'get' && path === '/hcm/billing/invoices') {
          return Promise.resolve({
            data: {
              success: true,
              data: [
                {
                  id: 89,
                  invoiceNumber: 'INV-89',
                  company: 'Tenant Pro',
                  subscriptionId: 71,
                  packageName: 'Growth',
                  packageCode: 'growth',
                  billingCycle: 'yearly',
                  billingCycleLabel: 'Tahunan',
                  nextBillingDate: '2027-04-06',
                  issueDate: '2026-04-01',
                  dueDate: '2026-04-05',
                  amountDue: 2400000,
                  status: 'paid',
                  isPaid: true,
                },
              ],
            },
          });
        }
        if (method === 'get' && path === '/hcm/billing/invoices/89') {
          return Promise.resolve({
            data: {
              success: true,
              data: {
                id: 89,
                invoiceNumber: 'INV-89',
                company: 'Tenant Pro',
                subscriptionId: 71,
                packageName: 'Growth',
                packageCode: 'growth',
                billingCycle: 'yearly',
                billingCycleLabel: 'Tahunan',
                nextBillingDate: '2027-04-06',
                issueDate: '2026-04-01',
                dueDate: '2026-04-05',
                amountDue: 2400000,
                status: 'paid',
                isPaid: true,
              },
            },
          });
        }
        if (method === 'get' && path === '/hcm/invoice-settings') {
          return Promise.resolve({
            data: {
              success: true,
              data: {
                invoice_prefix: 'INV-',
                invoice_due_days: '30',
                invoice_show_tax: '1',
                invoice_round_off_enabled: '0',
                invoice_round_off: 'none',
                invoice_header_terms: '',
                invoice_footer_terms: '',
              },
            },
          });
        }
        throw new Error(`Unexpected AuthApi call: ${method} ${path}`);
      }),
      downloadV1Binary: vi.fn(() => Promise.resolve()),
    };

    await import('../../../frontend/resources/js/company-invoices.js');
    await flush();

    document.querySelector('[data-invoice-view="89"]')?.dispatchEvent(new MouseEvent('click', { bubbles: true }));
    await flush();

    const modalEl = document.querySelector('[data-company-invoice-modal]');
    expect(modalEl?.classList.contains('show')).toBe(true);

    document.querySelector('[data-bs-dismiss="modal"]')?.dispatchEvent(new MouseEvent('click', { bubbles: true }));
    await flush();

    expect(modalEl?.classList.contains('show')).toBe(false);
    expect(modalEl?.getAttribute('aria-hidden')).toBe('true');
  });
});