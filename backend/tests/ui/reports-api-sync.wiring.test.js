import { beforeEach, describe, expect, it, vi } from 'vitest';

async function loadReportsSync() {
  vi.resetModules();
  await import('../../../frontend/resources/js/reports-api-sync.js');
}

function flush(times = 6) {
  return Array.from({ length: times }).reduce((promise) => promise.then(() => Promise.resolve()), Promise.resolve());
}

describe('Reports API sync wiring', () => {
  beforeEach(() => {
    localStorage.clear();
    document.body.innerHTML = '<div class="main-wrapper" data-subscription-status="trial" data-role-scope="hcm-admin"></div>';
    window.AuthApi = {
      getToken: () => 'token-xyz',
      getTenantContext: () => ({
        companyCode: 'ACME',
        companyId: 77,
        companyUuid: '11111111-2222-3333-4444-555555555555',
      }),
      handleUnauthorizedFromApi: () => false,
    };
  });

  it('sends auth and tenant context for invoice and revenue sync', async () => {
    window.history.pushState({}, '', '/invoice-report');

    const fetchMock = vi.fn((url) => {
      if (String(url).includes('/v1/saas/invoices')) {
        return Promise.resolve({
          ok: true,
          status: 200,
          json: async () => ({ success: true, data: [] }),
        });
      }

      if (String(url).includes('/v1/saas/reports/revenue')) {
        return Promise.resolve({
          ok: true,
          status: 200,
          json: async () => ({ success: true, data: { totalRevenue: 0, breakdown: [] } }),
        });
      }

      throw new Error(`Unexpected fetch: ${url}`);
    });

    vi.stubGlobal('fetch', fetchMock);

    await loadReportsSync();
    document.dispatchEvent(new Event('DOMContentLoaded'));
    await flush();

    expect(fetchMock).toHaveBeenCalledTimes(2);

    const [invoiceUrl, invoiceOptions] = fetchMock.mock.calls[0];
    expect(String(invoiceUrl)).toBe('/v1/saas/invoices');
    expect(invoiceOptions.headers.Authorization).toBe('Bearer token-xyz');
    expect(invoiceOptions.headers['X-Company-Id']).toBe('77');
    expect(invoiceOptions.headers['X-Company-Code']).toBe('ACME');
    expect(invoiceOptions.headers['X-Company-UUID']).toBe('11111111-2222-3333-4444-555555555555');

    const [revenueUrl, revenueOptions] = fetchMock.mock.calls[1];
    expect(String(revenueUrl)).toContain('/v1/saas/reports/revenue?period=monthly&company_id=77');
    expect(revenueOptions.headers.Authorization).toBe('Bearer token-xyz');
    expect(revenueOptions.headers['X-Company-Id']).toBe('77');
  });
});