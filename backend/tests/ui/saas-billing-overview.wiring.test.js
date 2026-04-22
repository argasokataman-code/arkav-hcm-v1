import { beforeEach, describe, expect, it, vi } from 'vitest';

async function loadBillingOverview() {
  vi.resetModules();
  return import('../../../frontend/resources/js/saas-billing-overview.js');
}

describe('SaaS billing overview wiring', () => {
  beforeEach(() => {
    document.body.innerHTML = '';

    localStorage.setItem('arcav_access_token', 'token-abc');
    window.__ARCAV_DISABLE_AUTOINIT__ = true;
    window.AuthApi = {
      tokenKey: 'arcav_access_token',
      request: vi.fn(),
    };
  });

  it('loads overview rows, renders mismatch badges, and wires resend/detail actions to the billing endpoints', async () => {
    document.body.innerHTML = `
      <div class="page-wrapper">
        <div class="content" data-saas-billing-overview-page>
          <input data-billing-search value="" />
          <select data-billing-tab>
            <option value="trial" selected>Trial</option>
            <option value="subscribed">Subscribed</option>
          </select>
          <select data-billing-per-page>
            <option value="15" selected>15</option>
          </select>
          <button data-billing-refresh type="button"></button>
          <button data-billing-prev type="button"></button>
          <button data-billing-next type="button"></button>
          <div data-billing-error class="d-none"></div>
          <div data-billing-pagination-info></div>
          <table><tbody data-billing-tbody></tbody></table>
        </div>
      </div>
    `;

    const requestMock = window.AuthApi.request;
    requestMock
      .mockResolvedValueOnce({
        data: {
          success: true,
          data: [
            {
              company: { id: 1, code: 'ACME01', name: 'ACME Corp' },
              subscription: {
                id: 11,
                status: 'active',
                billingCycle: 'monthly',
                startsAt: '2026-04-01T00:00:00.000000Z',
                endsAt: '2026-05-01T00:00:00.000000Z',
                trialEndsAt: null,
                planCode: 'pro',
                packageId: '11111111-2222-3333-4444-555555555555',
                packageName: 'Pro Plan',
                amount: 200000,
              },
              latestInvoice: {
                id: 99,
                uuid: '99999999-2222-3333-4444-555555555555',
                invoiceNumber: 'INV-000099',
                issueDate: '2026-04-16',
                dueDate: '2026-04-23',
                amountDue: 200000,
                isPaid: false,
                status: 'draft',
                detailUrl: '/saas/billing-overview/invoices/99999999-2222-3333-4444-555555555555',
              },
              email: {
                status: 'not_sent',
                sentAt: null,
                lastError: null,
              },
              stateBadges: [
                { code: 'STATE_MISMATCH', label: 'State Mismatch', kind: 'warning' },
              ],
            },
          ],
          pagination: { total: 1, per_page: 15, current_page: 1, last_page: 1 },
        },
      })
      .mockResolvedValueOnce({ data: { success: true, message: 'sent' } })
      .mockResolvedValueOnce({
        data: {
          success: true,
          data: [],
          pagination: { total: 0, per_page: 15, current_page: 1, last_page: 1 },
        },
      });

    await loadBillingOverview();
    window.SaaSBillingOverview.init();

    await vi.waitFor(() => {
      expect(requestMock).toHaveBeenCalledWith('get', '/saas/companies/billing-overview', {
        tab: 'trial',
        search: '',
        page: 1,
        per_page: 15,
      });
    });

    await vi.waitFor(() => {
      expect(document.querySelector('[data-billing-tbody]').innerHTML).toContain('ACME Corp');
      expect(document.querySelector('[data-billing-tbody]').innerHTML).toContain('INV-000099');
      expect(document.querySelector('[data-billing-tbody]').innerHTML).toContain('State Mismatch');
    });

    expect(document.querySelector('a[href="/saas/billing-overview/invoices/99999999-2222-3333-4444-555555555555"]')).not.toBeNull();

    document.querySelector('[data-action="resend"]').click();

    await vi.waitFor(() => {
      expect(requestMock).toHaveBeenCalledWith('post', '/saas/invoices/99999999-2222-3333-4444-555555555555/send-email', {});
    });
  });

  it('hides send-email action when latest email is already sent', async () => {
    document.body.innerHTML = `
      <div class="page-wrapper">
        <div class="content" data-saas-billing-overview-page>
          <input data-billing-search value="" />
          <select data-billing-tab>
            <option value="subscribed" selected>Subscribed</option>
          </select>
          <select data-billing-per-page>
            <option value="15" selected>15</option>
          </select>
          <button data-billing-refresh type="button"></button>
          <button data-billing-prev type="button"></button>
          <button data-billing-next type="button"></button>
          <div data-billing-error class="d-none"></div>
          <div data-billing-pagination-info></div>
          <table><tbody data-billing-tbody></tbody></table>
        </div>
      </div>
    `;

    const requestMock = window.AuthApi.request;
    requestMock.mockResolvedValueOnce({
      data: {
        success: true,
        data: [
          {
            company: { id: 3, code: 'SENT01', name: 'Sent Co' },
            subscription: {
              id: 13,
              status: 'active',
              billingCycle: 'monthly',
              startsAt: '2026-04-01T00:00:00.000000Z',
              endsAt: '2026-05-01T00:00:00.000000Z',
              trialEndsAt: null,
              planCode: 'pro',
              packageId: '11111111-2222-3333-4444-555555555555',
              packageName: 'Pro Plan',
              amount: 200000,
            },
            latestInvoice: {
              id: 98,
              uuid: '88888888-2222-3333-4444-555555555555',
              invoiceNumber: 'INV-000098',
              issueDate: '2026-04-15',
              dueDate: '2026-04-22',
              amountDue: 200000,
              isPaid: false,
              status: 'sent',
              detailUrl: '/saas/billing-overview/invoices/88888888-2222-3333-4444-555555555555',
            },
            email: {
              status: 'sent',
              sentAt: '2026-04-15T12:00:00.000000Z',
              lastError: null,
            },
            stateBadges: [],
          },
        ],
        pagination: { total: 1, per_page: 15, current_page: 1, last_page: 1 },
      },
    });

    await loadBillingOverview();
    window.SaaSBillingOverview.init();

    await vi.waitFor(() => {
      expect(document.querySelector('[data-billing-tbody]').innerHTML).toContain('Sent Co');
      expect(document.querySelector('[data-action="resend"]')).toBeNull();
    });
  });

  it('loads invoice detail page and renders full email history with resend action', async () => {
    document.body.innerHTML = `
      <div class="page-wrapper">
        <div class="content" data-saas-billing-invoice-detail-page data-invoice-uuid="99999999-2222-3333-4444-555555555555">
          <div data-billing-detail-error class="d-none"></div>
          <h2 data-billing-detail-title></h2>
          <div data-billing-detail-state-badges></div>
          <div data-billing-detail-company-name></div>
          <div data-billing-detail-company-code></div>
          <div data-billing-detail-subscription-status></div>
          <div data-billing-detail-subscription-plan></div>
          <div data-billing-detail-subscription-period></div>
          <div data-billing-detail-invoice-status></div>
          <div data-billing-detail-invoice-number></div>
          <div data-billing-detail-invoice-due-date></div>
          <div data-billing-detail-invoice-amount></div>
          <div data-billing-detail-latest-email-status></div>
          <div data-billing-detail-latest-email-target></div>
          <div data-billing-detail-latest-email-sent-at></div>
          <div data-billing-detail-latest-email-error></div>
          <button data-billing-detail-resend type="button"></button>
          <table><tbody data-billing-email-history-body></tbody></table>
        </div>
      </div>
    `;

    const requestMock = window.AuthApi.request;
    requestMock
      .mockResolvedValueOnce({
        data: {
          success: true,
          data: {
            uuid: '99999999-2222-3333-4444-555555555555',
            invoiceNumber: 'INV-000099',
            status: 'paid',
            isPaid: true,
            dueDate: '2026-04-23',
            amountDue: 200000,
            companyName: 'ACME Corp',
            company: { code: 'ACME01', name: 'ACME Corp' },
            subscription: {
              uuid: 'sub-uuid',
              status: 'pending_payment',
              planCode: 'pro',
              packageName: 'Pro Plan',
              startsAt: '2026-04-01T00:00:00.000000Z',
              endsAt: '2026-05-01T00:00:00.000000Z',
            },
            latestEmail: {
              uuid: 'log-2',
              toEmail: 'billing@acme.test',
              status: 'sent',
              createdAt: '2026-04-16T12:00:00.000000Z',
              errorMessage: null,
            },
            emailLogs: [
              {
                uuid: 'log-2',
                toEmail: 'billing@acme.test',
                status: 'sent',
                createdAt: '2026-04-16T12:00:00.000000Z',
                errorMessage: null,
              },
              {
                uuid: 'log-1',
                toEmail: 'billing@acme.test',
                status: 'failed',
                createdAt: '2026-04-16T11:00:00.000000Z',
                errorMessage: 'SMTP timeout',
              },
            ],
          },
        },
      })
      .mockResolvedValueOnce({ data: { success: true, message: 'sent' } })
      .mockResolvedValueOnce({
        data: {
          success: true,
          data: {
            uuid: '99999999-2222-3333-4444-555555555555',
            invoiceNumber: 'INV-000099',
            status: 'paid',
            isPaid: true,
            dueDate: '2026-04-23',
            amountDue: 200000,
            companyName: 'ACME Corp',
            company: { code: 'ACME01', name: 'ACME Corp' },
            subscription: {
              uuid: 'sub-uuid',
              status: 'pending_payment',
              planCode: 'pro',
              packageName: 'Pro Plan',
              startsAt: '2026-04-01T00:00:00.000000Z',
              endsAt: '2026-05-01T00:00:00.000000Z',
            },
            latestEmail: {
              uuid: 'log-3',
              toEmail: 'billing@acme.test',
              status: 'sent',
              createdAt: '2026-04-16T13:00:00.000000Z',
              errorMessage: null,
            },
            emailLogs: [
              {
                uuid: 'log-3',
                toEmail: 'billing@acme.test',
                status: 'sent',
                createdAt: '2026-04-16T13:00:00.000000Z',
                errorMessage: null,
              },
            ],
          },
        },
      });

    await loadBillingOverview();
    window.SaaSBillingOverview.init();

    await vi.waitFor(() => {
      expect(requestMock).toHaveBeenCalledWith('get', '/saas/invoices/99999999-2222-3333-4444-555555555555', undefined);
      expect(document.querySelector('[data-billing-detail-title]').textContent).toContain('INV-000099');
      expect(document.querySelector('[data-billing-email-history-body]').innerHTML).toContain('SMTP timeout');
      expect(document.querySelector('[data-billing-detail-state-badges]').innerHTML).toContain('State Mismatch');
    });

    document.querySelector('[data-billing-detail-resend]').click();

    await vi.waitFor(() => {
      expect(requestMock).toHaveBeenCalledWith('post', '/saas/invoices/99999999-2222-3333-4444-555555555555/send-email', {});
    });
  });
});