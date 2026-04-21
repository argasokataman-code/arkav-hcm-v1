import { beforeEach, describe, expect, it, vi } from 'vitest';

const flush = async () => {
  await Promise.resolve();
  await new Promise((resolve) => setTimeout(resolve, 0));
};

describe('company invoices wiring', () => {
  beforeEach(() => {
    vi.resetModules();

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
      <div data-invoice-modal-number></div>
      <span data-invoice-modal-status></span>
      <div data-invoice-modal-company></div>
      <span data-invoice-modal-payment-status></span>
      <div data-invoice-modal-issue-date></div>
      <div data-invoice-modal-due-date></div>
      <div data-invoice-modal-amount></div>
      <div data-invoice-modal-notes></div>
      <button type="button" data-company-invoice-download></button>
    `;

    window.bootstrap = {
      Modal: {
        getOrCreateInstance: () => ({ show: vi.fn() }),
      },
    };

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
});