import { beforeEach, describe, expect, it, vi } from 'vitest';

function okJson(data) {
  return {
    ok: true,
    status: 200,
    json: async () => data,
  };
}

async function flush(times = 8) {
  for (let i = 0; i < times; i += 1) {
    // Allow chained promises from dashboard init and fetch handlers to settle.
    await Promise.resolve();
  }
}

describe('Super Admin Dashboard API wiring', () => {
  beforeEach(() => {
    vi.resetModules();
    localStorage.clear();
    document.body.innerHTML = '<div id="kpi_container"></div>';
    window.__ARCAV_LAST_REDIRECT__ = null;
  });

  it('requests api-token then calls dashboard endpoints with bearer token', async () => {
    const fetchMock = vi.fn((url, options = {}) => {
      if (url === '/api-token') {
        return Promise.resolve(okJson({ success: true, data: { token: 'sa-token' } }));
      }

      if (String(url).includes('/v1/saas/dashboard/kpi')) {
        return Promise.resolve(okJson({
          success: true,
          data: {
            totalCompanies: 2,
            totalUsers: 5,
            mrr: 100000,
            arr: 1200000,
            activeSubscriptions: 2,
            churnRate: 1.25,
            customerLifetimeValue: 500000,
            netRevenueRetention: 95,
          },
        }));
      }

      if (String(url).includes('/v1/saas/dashboard/companies/top-performers')) {
        return Promise.resolve(okJson({ success: true, data: [] }));
      }

      if (String(url).includes('/v1/saas/dashboard/companies')) {
        return Promise.resolve(okJson({ success: true, data: [], pagination: { last_page: 1 } }));
      }

      if (String(url).includes('/v1/saas/dashboard/revenue/monthly')) {
        return Promise.resolve(okJson({ success: true, data: [] }));
      }

      if (String(url).includes('/v1/saas/dashboard/revenue/by-plan')) {
        return Promise.resolve(okJson({ success: true, data: [] }));
      }

      if (String(url).includes('/v1/saas/dashboard/subscriptions/status')) {
        return Promise.resolve(okJson({ success: true, data: {} }));
      }

      if (String(url).includes('/v1/saas/dashboard/audit-logs')) {
        return Promise.resolve(okJson({ success: true, data: [], pagination: { last_page: 1 } }));
      }

      if (String(url).includes('/v1/saas/dashboard/users')) {
        return Promise.resolve(okJson({
          success: true,
          data: {
            totalUsers: 5,
            verifiedUsers: 4,
            unverifiedUsers: 1,
            newUsersThisMonth: 1,
            verificationRate: 80,
          },
        }));
      }

      return Promise.resolve(okJson({ success: true, data: [] }));
    });

    vi.stubGlobal('fetch', fetchMock);

    await import('../../../frontend/resources/js/super-admin-dashboard-data.js');
    await flush();

    const calledUrls = fetchMock.mock.calls.map(([url]) => String(url));

    expect(calledUrls).toContain('/api-token');
    expect(calledUrls.some((u) => u.includes('/v1/saas/dashboard/kpi'))).toBe(true);
    expect(calledUrls.some((u) => u.includes('/v1/saas/dashboard/companies?page='))).toBe(true);
    expect(calledUrls.some((u) => u.includes('/v1/saas/dashboard/audit-logs?page=1'))).toBe(true);
    expect(calledUrls.some((u) => u.includes('/v1/saas/dashboard/revenue/monthly'))).toBe(true);
    expect(calledUrls.some((u) => u.includes('/v1/saas/dashboard/subscriptions/status'))).toBe(true);
    expect(calledUrls.some((u) => u.includes('/v1/saas/dashboard/revenue/by-plan'))).toBe(true);

    const kpiCall = fetchMock.mock.calls.find(([url]) => String(url).includes('/v1/saas/dashboard/kpi'));
    expect(kpiCall).toBeTruthy();
    expect(kpiCall[1]?.headers?.Authorization).toBe('Bearer sa-token');
  });

  it('redirects non-admin responses to employee dashboard', async () => {
    const fetchMock = vi.fn((url) => {
      if (url === '/api-token') {
        return Promise.resolve(okJson({ success: true, data: { token: 'sa-token' } }));
      }

      if (String(url).includes('/v1/saas/dashboard/kpi')) {
        return Promise.resolve({
          ok: false,
          status: 403,
          json: async () => ({
            success: false,
            error: { code: 'ADMIN_REQUIRED', message: 'Admin access required.' },
          }),
        });
      }

      return Promise.resolve(okJson({ success: true, data: [], pagination: { last_page: 1 } }));
    });

    vi.stubGlobal('fetch', fetchMock);

    const replaceMock = vi.fn();
    Object.defineProperty(window, 'location', {
      configurable: true,
      value: { replace: replaceMock },
    });

    await import('../../../frontend/resources/js/super-admin-dashboard-data.js');
    await flush();

    expect(window.__ARCAV_LAST_REDIRECT__).toBe('/employee-dashboard');
    expect(replaceMock).toHaveBeenCalledWith('/employee-dashboard');
  });

  it('renders company revenue from backend company list data', async () => {
    document.body.innerHTML = `
      <div id="kpi_container"></div>
      <div id="subscription_status"></div>
      <div id="revenue_by_plan"></div>
      <div id="revenue_chart"></div>
      <div id="pagination_container"></div>
      <table id="companies_table"><tbody></tbody></table>
      <table id="audit_logs_table"><tbody></tbody></table>
    `;

    const fetchMock = vi.fn((url) => {
      if (url === '/api-token') {
        return Promise.resolve(okJson({ success: true, data: { token: 'sa-token' } }));
      }

      if (String(url).includes('/v1/saas/dashboard/kpi')) {
        return Promise.resolve(okJson({
          success: true,
          data: {
            totalCompanies: 1,
            totalUsers: 2,
            mrr: 49000,
            arr: 588000,
            activeSubscriptions: 1,
            churnRate: 0,
            customerLifetimeValue: 49000,
            netRevenueRetention: 100,
          },
        }));
      }

      if (String(url).includes('/v1/saas/dashboard/companies/top-performers')) {
        return Promise.resolve(okJson({ success: true, data: [] }));
      }

      if (String(url).includes('/v1/saas/dashboard/companies')) {
        return Promise.resolve(okJson({
          success: true,
          data: [{
            id: 1,
            uuid: 'company-uuid',
            name: 'Revenue Co',
            code: 'REV001',
            email: 'rev@example.com',
            userCount: 4,
            subscriptionCount: 1,
            totalRevenue: 49000,
            createdAt: '2026-04-19T00:00:00Z',
          }],
          pagination: { last_page: 1 },
        }));
      }

      if (String(url).includes('/v1/saas/dashboard/revenue/monthly')) {
        return Promise.resolve(okJson({ success: true, data: [] }));
      }

      if (String(url).includes('/v1/saas/dashboard/revenue/by-plan')) {
        return Promise.resolve(okJson({ success: true, data: [] }));
      }

      if (String(url).includes('/v1/saas/dashboard/subscriptions/status')) {
        return Promise.resolve(okJson({ success: true, data: {} }));
      }

      if (String(url).includes('/v1/saas/dashboard/audit-logs')) {
        return Promise.resolve(okJson({ success: true, data: [], pagination: { last_page: 1 } }));
      }

      if (String(url).includes('/v1/saas/dashboard/users')) {
        return Promise.resolve(okJson({
          success: true,
          data: {
            totalUsers: 2,
            verifiedUsers: 2,
            unverifiedUsers: 0,
            newUsersThisMonth: 1,
            verificationRate: 100,
          },
        }));
      }

      return Promise.resolve(okJson({ success: true, data: [] }));
    });

    vi.stubGlobal('fetch', fetchMock);

    await import('../../../frontend/resources/js/super-admin-dashboard-data.js');
    await flush();

    expect(document.querySelector('#companies_table tbody')?.textContent || '').toContain('Rp 49.000');
  });
});
