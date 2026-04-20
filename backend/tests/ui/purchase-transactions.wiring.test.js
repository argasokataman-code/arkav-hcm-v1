import { beforeEach, describe, expect, it, vi } from 'vitest';

async function loadTransactionsManager() {
  vi.resetModules();
  await import('../../../frontend/resources/js/purchase-transactions-data.js');
  return window.TransactionsManager;
}

function flush(times = 6) {
  return Array.from({ length: times }).reduce((promise) => promise.then(() => Promise.resolve()), Promise.resolve());
}

function buildPurchaseTransactionsDom() {
  document.body.innerHTML = `
    <div class="content" data-saas-transactions-page>
      <button class="btn btn-primary d-flex align-items-center" id="btn_download_all">Download All</button>
      <input type="text" class="form-control" id="search_invoice_number" />
      <input type="text" class="form-control" id="search_company" />
      <select class="form-select" id="filter_status">
        <option value="">All</option>
        <option value="paid">Paid</option>
      </select>
      <select class="form-select" id="filter_payment_method">
        <option value="">All</option>
        <option value="bank_transfer">Bank Transfer</option>
      </select>
      <input type="date" class="form-control" id="filter_date_from" />
      <button class="btn btn-outline-secondary w-100" id="btn_reset_filters"></button>
      <div data-transactions-list-container></div>
    </div>
    <div class="modal fade" id="detailsModal" tabindex="-1" role="dialog">
      <div id="details_content"></div>
    </div>
  `;
}

describe('Purchase transactions wiring', () => {
  beforeEach(() => {
    localStorage.clear();
    buildPurchaseTransactionsDom();
    window.open = vi.fn();
    window.bootstrap = {
      Modal: class {
        constructor() {}
        static getOrCreateInstance() {
          return new window.bootstrap.Modal();
        }
        show() {}
      },
    };
  });

  it('loads transactions with the active filter inputs and renders the active template ids', async () => {
    const fetchMock = vi.fn((url) => {
      const urlString = String(url);

      if (urlString.includes('/v1/saas/transactions/42')) {
        return Promise.resolve({
          ok: true,
          status: 200,
          json: async () => ({
            success: true,
            data: {
              id: 42,
              invoiceNumber: 'INV-202604-0042',
              companyName: 'PT Nusantara Labs',
              packageName: 'Pro',
              amount: 150000,
              paymentMethod: 'bank_transfer',
              status: 'paid',
              createdAt: '2026-04-19T08:00:00Z',
              paidAt: '2026-04-19T09:00:00Z',
              description: 'Monthly billing',
            },
          }),
        });
      }

      return Promise.resolve({
        ok: true,
        status: 200,
        json: async () => ({
          success: true,
          data: [
            {
              id: 42,
              invoiceNumber: 'INV-202604-0042',
              companyName: 'PT Nusantara Labs',
              packageName: 'Pro',
              amount: 150000,
              paymentMethod: 'bank_transfer',
              status: 'paid',
              createdAt: '2026-04-19T08:00:00Z',
              paidAt: '2026-04-19T09:00:00Z',
            },
          ],
          pagination: {
            total: 1,
            per_page: 15,
            current_page: 1,
            last_page: 1,
          },
        }),
      });
    });

    vi.stubGlobal('fetch', fetchMock);

    const manager = await loadTransactionsManager();
    document.dispatchEvent(new Event('DOMContentLoaded'));
    await flush();

    document.getElementById('search_invoice_number').value = 'INV-202604-0042';
    document.getElementById('search_company').value = 'Nusantara';
    document.getElementById('filter_status').value = 'paid';
    document.getElementById('filter_payment_method').value = 'bank_transfer';
    document.getElementById('filter_date_from').value = '2026-04-01';

    await manager.loadTransactions();
    await flush();

    const lastListCall = fetchMock.mock.calls.filter(([url]) => String(url).includes('/v1/saas/transactions?')).at(-1);
    expect(lastListCall).toBeTruthy();
    expect(String(lastListCall[0])).toContain('/v1/saas/transactions?page=1&per_page=15');
    expect(String(lastListCall[0])).toContain('invoice_number=INV-202604-0042');
    expect(String(lastListCall[0])).toContain('company_search=Nusantara');
    expect(String(lastListCall[0])).toContain('status=paid');
    expect(String(lastListCall[0])).toContain('payment_method=bank_transfer');
    expect(String(lastListCall[0])).toContain('date_from=2026-04-01');

    const container = document.querySelector('[data-transactions-list-container]');
    expect(container.innerHTML).toContain('INV-202604-0042');
    expect(container.innerHTML).toContain('PT Nusantara Labs');
    expect(container.innerHTML).toContain('Pro');

    await manager.viewTransactionDetails(42);
    await flush();

    expect(document.getElementById('details_content').innerHTML).toContain('INV-202604-0042');
    expect(document.getElementById('details_content').innerHTML).toContain('PT Nusantara Labs');
    expect(document.getElementById('details_content').innerHTML).toContain('Monthly billing');
  });

  it('exports transactions from the active Download All button', async () => {
    const fetchMock = vi.fn().mockResolvedValue({
      ok: true,
      status: 200,
      json: async () => ({ success: true, data: [] }),
    });

    vi.stubGlobal('fetch', fetchMock);

    const manager = await loadTransactionsManager();
    document.dispatchEvent(new Event('DOMContentLoaded'));
    await flush();

    document.getElementById('btn_download_all').click();
    expect(window.open).toHaveBeenCalledWith('/v1/saas/transactions/export', '_self');
    expect(manager).toBeTruthy();
  });

  it('renders backend error messages for failed transaction loads', async () => {
    const fetchMock = vi.fn().mockResolvedValue({
      ok: false,
      status: 403,
      json: async () => ({
        success: false,
        error: {
          code: 'ADMIN_REQUIRED',
          message: 'Admin access required.',
        },
      }),
    });

    vi.stubGlobal('fetch', fetchMock);

    await loadTransactionsManager();
    document.dispatchEvent(new Event('DOMContentLoaded'));
    await flush();

    expect(document.body.textContent).toContain('Admin access required.');
  });
});