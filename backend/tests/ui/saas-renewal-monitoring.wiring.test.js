import { beforeEach, describe, expect, it, vi } from 'vitest';

async function loadRenewalMonitoring() {
  vi.resetModules();
  return import('../../../frontend/resources/js/saas-renewal-monitoring.js');
}

describe('SaaS renewal monitoring wiring', () => {
  beforeEach(() => {
    document.body.innerHTML = '';

    localStorage.setItem('arcav_access_token', 'token-abc');
    window.__ARCAV_DISABLE_AUTOINIT__ = true;
    window.AuthApi = {
      tokenKey: 'arcav_access_token',
      request: vi.fn(),
    };
  });

  it('loads summary, records, anomalies, and detail from renewal monitoring endpoints', async () => {
    document.body.innerHTML = `
      <div class="page-wrapper">
        <div class="content" data-saas-renewal-monitoring-page>
          <select data-renewal-days><option value="30" selected>30</option></select>
          <select data-renewal-status><option value="">all</option></select>
          <input data-renewal-reason value="" />
          <input data-renewal-company-id value="" />
          <button data-renewal-refresh type="button"></button>
          <button data-renewal-reset type="button"></button>
          <button data-renewal-prev type="button"></button>
          <button data-renewal-next type="button"></button>
          <div data-renewal-error class="d-none"></div>
          <div data-renewal-summary-total></div>
          <div data-renewal-summary-paid></div>
          <div data-renewal-summary-retrying></div>
          <div data-renewal-summary-grace></div>
          <div data-renewal-summary-suspended></div>
          <div data-renewal-summary-anomalies></div>
          <div data-renewal-records-page-info></div>
          <div data-renewal-records-pagination></div>
          <table><tbody data-renewal-records-body></tbody></table>
          <div data-renewal-anomalies-list></div>
          <div data-renewal-detail-key></div>
          <div data-renewal-detail-panel></div>
        </div>
      </div>
    `;

    const requestMock = window.AuthApi.request;
    requestMock
      .mockResolvedValueOnce({
        data: {
          success: true,
          data: {
            windowDays: 30,
            summary: {
              totalRecords: 12,
              paid: 7,
              retrying: 2,
              gracePeriod: 1,
              suspended: 1,
              anomalies: 3,
            },
          },
        },
      })
      .mockResolvedValueOnce({
        data: {
          success: true,
          data: [
            {
              renewalPeriodKey: 'sub_99_2026_05',
              invoice: { number: 'INV-000501', amountDue: 250000, isPaid: false },
              company: { name: 'ACME Corp', code: 'ACME' },
              subscription: { status: 'grace_period', billingCycle: 'monthly' },
              reason: { code: 'RENEWAL_RETRY_SCHEDULED', message: 'Retry scheduled in 24 hours.' },
            },
          ],
          pagination: { total: 1, per_page: 20, current_page: 1, last_page: 1 },
        },
      })
      .mockResolvedValueOnce({
        data: {
          success: true,
          data: [
            {
              renewalPeriodKey: 'sub_99_2026_05',
              company: { name: 'ACME Corp' },
              reasonCode: 'XENDIT_DOWN',
              reasonMessage: 'Xendit reconciliation unavailable.',
              issueDate: '2026-05-14',
              dueDate: '2026-05-21',
              isPaid: false,
            },
          ],
          pagination: { total: 1, per_page: 10, current_page: 1, last_page: 1 },
        },
      })
      .mockResolvedValueOnce({
        data: {
          success: true,
          data: {
            renewalPeriodKey: 'sub_99_2026_05',
            company: { name: 'ACME Corp', code: 'ACME' },
            subscription: { status: 'active' },
            invoice: { number: 'INV-000501', amountDue: 250000, isPaid: true },
            reason: { code: 'WEBHOOK_INVOICE_PAID', message: 'Renewal paid from webhook.' },
            timeline: [
              {
                event_type: 'renewal_paid',
                reason_code: 'WEBHOOK_INVOICE_PAID',
                reason_message: 'Renewal paid from webhook.',
                occurred_at: '2026-05-14T09:00:00Z',
              },
            ],
          },
        },
      });

    await loadRenewalMonitoring();
    window.SaaSRenewalMonitoring.init();

    await vi.waitFor(() => {
      expect(requestMock).toHaveBeenCalledWith('get', '/saas/renewal-monitoring/summary', { days: 30 });
      expect(requestMock).toHaveBeenCalledWith('get', '/saas/renewal-monitoring/records', {
        days: 30,
        page: 1,
        per_page: 20,
      });
      expect(requestMock).toHaveBeenCalledWith('get', '/saas/renewal-monitoring/anomalies', {
        days: 30,
        page: 1,
        per_page: 10,
      });
    });

    await vi.waitFor(() => {
      expect(document.querySelector('[data-renewal-summary-total]').textContent).toContain('12');
      expect(document.querySelector('[data-renewal-records-body]').innerHTML).toContain('sub_99_2026_05');
      expect(document.querySelector('[data-renewal-anomalies-list]').innerHTML).toContain('XENDIT_DOWN');
    });

    document.querySelector('[data-renewal-detail-trigger="sub_99_2026_05"]').click();

    await vi.waitFor(() => {
      expect(requestMock).toHaveBeenCalledWith('get', '/saas/renewal-monitoring/records/sub_99_2026_05', {});
      expect(document.querySelector('[data-renewal-detail-panel]').innerHTML).toContain('WEBHOOK_INVOICE_PAID');
      expect(document.querySelector('[data-renewal-detail-panel]').innerHTML).toContain('Renewal Paid');
    });
  });
});