import { beforeEach, describe, expect, it, vi } from 'vitest';

const flush = async () => {
  await Promise.resolve();
  await new Promise((resolve) => setTimeout(resolve, 0));
};

describe('company invoices tenant wiring — addon lifecycle', () => {
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
      <div data-company-invoice-modal>
        <div data-invoice-modal-loading class="d-none">
          <div data-invoice-modal-loading-message></div>
        </div>
        <div data-invoice-modal-content>
          <div data-invoice-modal-number></div>
          <div data-invoice-modal-status-wrap><span data-invoice-modal-status></span></div>
          <div data-invoice-modal-amount></div>
          <div data-invoice-modal-line-label></div>
          <div data-invoice-modal-line-caption></div>
          <div data-invoice-modal-table-amount></div>
          <div data-invoice-modal-table-total></div>
          <div data-invoice-modal-tax-row style="display:none"><div data-invoice-modal-tax-label></div><div data-invoice-modal-tax-amount></div></div>
          <div data-invoice-modal-tax-breakdown class="d-none"><div data-invoice-modal-tax-breakdown-list></div></div>
          <div data-invoice-modal-notes></div>
          <div data-invoice-modal-guidance></div>
          <div data-invoice-modal-charge-status-wrap><span data-invoice-modal-charge-status></span></div>
          <div data-invoice-modal-payment-status-wrap><span data-invoice-modal-payment-status></span></div>
          <div data-invoice-modal-company></div>
          <div data-invoice-modal-package-name></div>
          <div data-invoice-modal-package-summary></div>
          <div data-invoice-modal-summary></div>
          <div data-invoice-modal-issue-date></div>
          <div data-invoice-modal-due-date></div>
          <div data-invoice-modal-paid-date></div>
          <div data-invoice-modal-billing-cycle></div>
          <div data-invoice-modal-next-billing-date></div>
          <div data-invoice-modal-current-period></div>
          <div data-invoice-modal-header-terms></div>
          <div data-invoice-modal-footer-terms></div>
        </div>
      </div>
      <button data-company-invoice-download></button>
      <button data-company-invoice-print></button>
    `;

    window.AuthUser = { id: 1, name: 'Admin', isHcmAdmin: true, permissions: [] };
    window.AuthApi = {
      request: vi.fn(),
      getToken: vi.fn(() => 'mock-token'),
      getTenantContext: vi.fn(() => '7'),
    };
    global.fetch = vi.fn();
    window.bootstrap = { Modal: { getOrCreateInstance: vi.fn(() => ({ show: vi.fn(), hide: vi.fn() })) } };
    window.ArcavUi = { confirmDelete: vi.fn() };
  });

  it('renders cancel addon button for paid addon invoices', async () => {
    const mockInvoices = [
      {
        id: 1,
        invoiceNumber: 'INV-202606-0001',
        issueDate: '2026-06-01',
        dueDate: '2026-06-08',
        amountDue: 49000,
        status: 'paid',
        isPaid: true,
        subscriptionId: 7,
        notes: JSON.stringify({
          source: 'tenant_addon_checkout',
          pricing_breakdown: {
            base_amount: 49000,
            total_amount: 49000,
            addon_code: 'asset_management',
            addon_name: 'Asset Management',
            addon_id: 9,
          },
        }),
      },
      {
        id: 2,
        invoiceNumber: 'INV-202606-0002',
        issueDate: '2026-06-01',
        dueDate: '2026-06-15',
        amountDue: 1299000,
        status: 'paid',
        isPaid: true,
        subscriptionId: 7,
        notes: '{"source":"tenant_subscription_checkout","pricing_breakdown":{"total_amount":1299000}}',
      },
    ];

    window.AuthApi.request.mockImplementation((method, path) => {
      if (method === 'get' && path.startsWith('/hcm/billing/invoices')) {
        return Promise.resolve({ data: { success: true, data: mockInvoices, meta: { page: 1, perPage: 15, total: 2 } } });
      }
      return Promise.reject(new Error(`Unexpected: ${method} ${path}`));
    });

    await import('../../../frontend/resources/js/company/company-invoices.js');
    await flush();

    // Paid addon invoice should show "Cancel Add-on" button
    const cancelBtn = document.querySelector('[data-addon-cancel]');
    expect(cancelBtn).toBeTruthy();
    expect(cancelBtn.textContent).toContain('Cancel Add-on');

    // Subscription invoice should NOT have cancel button
    const allCancelBtns = document.querySelectorAll('[data-addon-cancel]');
    expect(allCancelBtns.length).toBe(1);
  });

  it('calls cancel addon API on button click', async () => {
    const mockInvoices = [
      {
        id: 3,
        invoiceNumber: 'INV-202606-0003',
        issueDate: '2026-06-01',
        dueDate: '2026-06-08',
        amountDue: 49000,
        status: 'paid',
        isPaid: true,
        subscriptionId: 7,
        notes: JSON.stringify({
          source: 'tenant_addon_checkout',
          pricing_breakdown: {
            base_amount: 49000,
            total_amount: 49000,
            addon_code: 'asset_management',
            addon_name: 'Asset Management',
            addon_id: 9,
          },
        }),
      },
    ];

    let cancelCount = 0;
    window.AuthApi.request.mockImplementation((method, path, body) => {
      if (method === 'get' && path.startsWith('/hcm/billing/invoices')) {
        return Promise.resolve({ data: { success: true, data: mockInvoices, meta: { page: 1, perPage: 15, total: 1 } } });
      }
      // Mock cancel endpoint
      if (method === 'post' && path === '/hcm/billing/addons/cancel') {
        cancelCount++;
        return Promise.resolve({ data: { success: true, data: { addon: { id: 9, code: 'asset_management', name: 'Asset Management' }, previousAmount: 1348000, newAmount: 1299000, effective: 'next_billing_cycle' } } });
      }
      return Promise.reject(new Error(`Unexpected: ${method} ${path}`));
    });

    await import('../../../frontend/resources/js/company/company-invoices.js');
    await flush();

    const cancelBtn = document.querySelector('[data-addon-cancel]');
    expect(cancelBtn).toBeTruthy();

    // Click cancel
    window.confirm = vi.fn(() => true);
    cancelBtn.dispatchEvent(new MouseEvent('click', { bubbles: true }));
    await flush();

    expect(cancelCount).toBe(1);
    expect(window.AuthApi.request).toHaveBeenCalledWith('post', '/hcm/billing/addons/cancel', { addon_id: 9 });
  });
});
