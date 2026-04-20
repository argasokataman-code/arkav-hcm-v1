import { beforeEach, describe, expect, it, vi } from 'vitest';

async function loadAuthApi() {
  vi.resetModules();
  await import('../../../frontend/resources/js/api-client.js');
  return window.AuthApi;
}

function mockFetchOk(data = { success: true, data: [] }) {
  const fetchMock = vi.fn().mockResolvedValue({
    ok: true,
    status: 200,
    json: async () => data,
  });
  vi.stubGlobal('fetch', fetchMock);
  return fetchMock;
}

async function loadSubscriptionsManager(fetchImpl) {
  vi.resetModules();
  vi.stubGlobal('fetch', fetchImpl);
  delete window.SubscriptionsManager;

  Object.defineProperty(document, 'readyState', {
    configurable: true,
    value: 'loading',
  });

  await import('../../../frontend/resources/js/subscriptions-management.js');
  document.dispatchEvent(new Event('DOMContentLoaded'));
  await Promise.resolve();
  await Promise.resolve();

  return window.SubscriptionsManager;
}

describe('Subscriptions API contract wiring', () => {
  beforeEach(() => {
    document.body.innerHTML = '<div class="main-wrapper" data-subscription-status="trial" data-role-scope="hcm-admin"></div>';
    localStorage.clear();
  });

  it('maps GET subscriptions list with filters to /v1/saas/subscriptions', async () => {
    const fetchMock = mockFetchOk();
    const api = await loadAuthApi();

    await api.request('get', '/saas/subscriptions', {
      status: 'active',
      billing_cycle: 'monthly',
      search: 'company',
      per_page: 50,
    });

    const [url, options] = fetchMock.mock.calls[0];
    expect(url).toContain('/v1/saas/subscriptions?');
    expect(url).toContain('status=active');
    expect(url).toContain('billing_cycle=monthly');
    expect(url).toContain('search=company');
    expect(url).toContain('per_page=50');
    expect(options.method).toBe('GET');
  });

  it('maps POST create subscription to /v1/saas/subscriptions', async () => {
    const fetchMock = mockFetchOk({ success: true, data: { id: 42 } });
    const api = await loadAuthApi();

    await api.request('post', '/saas/subscriptions', {
      company_id: 'company-uuid',
      package_uuid: 'package-uuid',
      status: 'active',
      starts_at: '2026-04-01',
      ends_at: '2026-05-01',
      billing_cycle: 'monthly',
      amount: 99.99,
    });

    const [url, options] = fetchMock.mock.calls[0];
    expect(url).toBe('/v1/saas/subscriptions');
    expect(options.method).toBe('POST');
    expect(JSON.parse(options.body)).toEqual({
      company_id: 'company-uuid',
      package_uuid: 'package-uuid',
      status: 'active',
      starts_at: '2026-04-01',
      ends_at: '2026-05-01',
      billing_cycle: 'monthly',
      amount: 99.99,
    });
  });

  it('maps show and update endpoints to /v1/saas/subscriptions/{id}', async () => {
    const fetchMock = mockFetchOk({ success: true, data: { id: 42 } });
    const api = await loadAuthApi();

    await api.request('get', '/saas/subscriptions/42');
    await api.request('put', '/saas/subscriptions/42', {
      status: 'cancelled',
      ends_at: '2026-06-01',
    });

    const [showUrl, showOptions] = fetchMock.mock.calls[0];
    expect(showUrl).toBe('/v1/saas/subscriptions/42');
    expect(showOptions.method).toBe('GET');

    const [updateUrl, updateOptions] = fetchMock.mock.calls[1];
    expect(updateUrl).toBe('/v1/saas/subscriptions/42');
    expect(updateOptions.method).toBe('PUT');
    expect(JSON.parse(updateOptions.body)).toEqual({
      status: 'cancelled',
      ends_at: '2026-06-01',
    });
  });

  it('maps delete endpoint to /v1/saas/subscriptions/{id}', async () => {
    const fetchMock = mockFetchOk({ success: true });
    const api = await loadAuthApi();

    await api.request('delete', '/saas/subscriptions/42');

    const [url, options] = fetchMock.mock.calls[0];
    expect(url).toBe('/v1/saas/subscriptions/42');
    expect(options.method).toBe('DELETE');
  });

  it('maps renew endpoint to /v1/saas/subscriptions/{id}/renew', async () => {
    const fetchMock = mockFetchOk({ success: true });
    const api = await loadAuthApi();

    await api.request('post', '/saas/subscriptions/42/renew', {
      ends_at: '2026-07-01',
    });

    const [url, options] = fetchMock.mock.calls[0];
    expect(url).toBe('/v1/saas/subscriptions/42/renew');
    expect(options.method).toBe('POST');
    expect(JSON.parse(options.body)).toEqual({
      ends_at: '2026-07-01',
    });
  });

  it('posts create payload from subscriptions manager using company uuid and package uuid', async () => {
    document.body.innerHTML = `
      <div data-subscriptions-list-container></div>
      <button id="btn_add_subscription"></button>
      <button id="btn_open_renew_by_id"></button>
      <div id="subscription_readonly_notice" data-subscription-readonly-notice class="d-none"></div>
      <form id="subscriptionForm"><button type="submit">Save</button></form>
      <select id="input_subscription_company"></select>
      <select id="input_subscription_package"></select>
      <input id="input_subscription_start" />
      <select id="input_subscription_cycle"><option value="monthly">Monthly</option></select>
      <input id="input_subscription_end" />
      <select id="input_subscription_status"><option value="active">Active</option></select>
      <input id="input_subscription_trial_end" />
      <div id="subscription_trial_row" class="d-none"></div>
      <div id="subscriptionModal"></div>
      <div id="subscriptionRenewModal"></div>
      <div id="subscriptionRenewByIdModal"></div>
      <input id="search_subscriptions" />
      <select id="filter_status"></select>
      <select id="filter_cycle"></select>
      <button id="btn_reset_filters"></button>
    `;

    const fetchMock = vi.fn(async (url, options = {}) => {
      const target = String(url);
      if (target === '/api-token') {
        return {
          ok: true,
          status: 200,
          json: async () => ({ success: true, data: { token: 'test-token' } }),
        };
      }

      if (target === '/v1/identity/auth/me') {
        return {
          ok: true,
          status: 200,
          json: async () => ({
            success: true,
            data: { permissions: { 'subscription.manage': true } },
          }),
        };
      }

      if (target.startsWith('/v1/company')) {
        return {
          ok: true,
          status: 200,
          json: async () => ({
            success: true,
            data: {
              companies: [
                { id: 7, uuid: 'company-uuid', name: 'Acme Corp' },
              ],
            },
          }),
        };
      }

      if (target.startsWith('/v1/saas/packages')) {
        return {
          ok: true,
          status: 200,
          json: async () => ({
            success: true,
            data: [
              { id: 'package-uuid', name: 'Pro Plan' },
            ],
          }),
        };
      }

      if (target.startsWith('/v1/saas/subscriptions?')) {
        return {
          ok: true,
          status: 200,
          json: async () => ({ success: true, data: [], pagination: { last_page: 1 } }),
        };
      }

      if (target === '/v1/saas/subscriptions' && options.method === 'POST') {
        return {
          ok: true,
          status: 201,
          json: async () => ({ success: true, data: { id: 101 } }),
        };
      }

      return {
        ok: false,
        status: 404,
        json: async () => ({ success: false }),
      };
    });

    const manager = await loadSubscriptionsManager(fetchMock);
    manager.canManageSubscriptions = true;

    document.getElementById('input_subscription_company').innerHTML = '<option value="company-uuid">Acme Corp</option>';
    document.getElementById('input_subscription_package').innerHTML = '<option value="package-uuid">Pro Plan</option>';
    document.getElementById('input_subscription_company').value = 'company-uuid';
    document.getElementById('input_subscription_package').value = 'package-uuid';
    document.getElementById('input_subscription_start').value = '2026-04-01';
    document.getElementById('input_subscription_cycle').value = 'monthly';
    document.getElementById('input_subscription_end').value = '2026-05-01';
    document.getElementById('input_subscription_status').value = 'active';

    manager.handleSaveSubscription();
    await Promise.resolve();
    await Promise.resolve();

    const createCall = fetchMock.mock.calls.find(([url, options]) => String(url) === '/v1/saas/subscriptions' && options?.method === 'POST');
    expect(createCall).toBeTruthy();
    expect(JSON.parse(createCall[1].body)).toMatchObject({
      company_id: 'company-uuid',
      package_uuid: 'package-uuid',
      status: 'active',
      starts_at: '2026-04-01',
      ends_at: '2026-05-01',
      billing_cycle: 'monthly',
    });
  });
});
