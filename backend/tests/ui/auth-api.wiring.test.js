import { beforeEach, describe, expect, it, vi } from 'vitest';

async function loadAuthApi() {
  vi.resetModules();
  await import('../../../frontend/resources/js/api-client.js');
  return window.AuthApi;
}

describe('AuthApi wiring', () => {
  beforeEach(() => {
    document.body.innerHTML = '<div class="main-wrapper" data-subscription-status="trial" data-role-scope="hcm-admin"></div>';
    localStorage.clear();
    delete window.AuthUser;
    delete window.__ARCAV_LAST_REDIRECT__;
    delete window.__ARCAV_AUTH_SESSION_MONITOR_INTERVAL_MS__;
    window.__ARCAV_DISABLE_REDIRECTS__ = true;
  });

  it('exposes AuthApi on window after script load', async () => {
    const api = await loadAuthApi();
    expect(api).toBeTruthy();
    expect(typeof api.request).toBe('function');
    expect(typeof api.setTenantContext).toBe('function');
  });

  it('sends auth and tenant headers for training API request', async () => {
    const fetchMock = vi.fn().mockResolvedValue({
      ok: true,
      status: 200,
      json: async () => ({ success: true, data: [] }),
    });
    vi.stubGlobal('fetch', fetchMock);

    const api = await loadAuthApi();

    localStorage.setItem(api.tokenKey, 'token-abc');
    api.setTenantContext({
      companyCode: 'ACME',
      companyId: 99,
      companyUuid: '11111111-2222-3333-4444-555555555555',
    });

    await api.request('get', '/hcm/training/types', {
      perPage: 20,
      status: 'active',
    });

    expect(fetchMock).toHaveBeenCalledTimes(1);

    const [url, options] = fetchMock.mock.calls[0];
    expect(url).toContain('/v1/hcm/training/types');
    expect(url).toContain('perPage=20');
    expect(url).toContain('status=active');

    expect(options.method).toBe('GET');
    expect(options.credentials).toBe('same-origin');
    expect(options.headers.Authorization).toBe('Bearer token-abc');
    expect(options.headers['X-Company-Code']).toBe('ACME');
    expect(options.headers['X-Company-Id']).toBe('99');
    expect(options.headers['X-Company-UUID']).toBe('11111111-2222-3333-4444-555555555555');
  });

  it('flags unauthorized payload correctly for API contract checks', async () => {
    const api = await loadAuthApi();

    expect(api.isUnauthorizedApiPayload(401, { error: { code: 'AUTH_UNAUTHORIZED' } })).toBe(true);
    expect(api.isUnauthorizedApiPayload(401, { error: { code: 'AUTH_INVALID_CREDENTIALS' } })).toBe(false);
    expect(api.isUnauthorizedApiPayload(403, { error: { code: 'AUTH_FORBIDDEN' } })).toBe(false);
  });

  it('redirects idle protected pages to login when heartbeat sees expired auth', async () => {
    window.AuthUser = { id: 99 };
    localStorage.setItem('arcav_active_tenant', JSON.stringify({ companyCode: 'ACME' }));
    Object.defineProperty(document, 'hidden', {
      configurable: true,
      value: false,
    });

    const fetchMock = vi.fn().mockResolvedValue({
      ok: false,
      status: 401,
      json: async () => ({ success: false, error: { code: 'AUTH_UNAUTHORIZED', message: 'Unauthorized.' } }),
    });
    vi.stubGlobal('fetch', fetchMock);

    const api = await loadAuthApi();
    await api.probeAuthSession();

    expect(fetchMock).toHaveBeenCalledWith(
      '/v1/identity/auth/me',
      expect.objectContaining({
        method: 'GET',
        credentials: 'same-origin',
      }),
    );
    expect(window.__ARCAV_LAST_REDIRECT__).toBe('/login');
    expect(localStorage.getItem('arcav_active_tenant')).toBeNull();
  });
});
