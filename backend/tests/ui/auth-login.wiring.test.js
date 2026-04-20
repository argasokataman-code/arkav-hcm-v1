import { beforeEach, describe, expect, it, vi } from 'vitest';

async function loadAuthLoginModule() {
  vi.resetModules();
  await import('../../../frontend/resources/js/auth-login.js');
}

function flush(times = 6) {
  return Array.from({ length: times }).reduce((promise) => promise.then(() => Promise.resolve()), Promise.resolve());
}

function buildLoginDom() {
  document.body.innerHTML = `
    <form id="api-login-form">
      <input id="login-email" type="email" value="user@example.com" />
      <input id="login-password" type="password" value="StrongPass1" />
      <input id="remember_me" type="checkbox" />
      <input id="login_mode_regular" name="login_mode" type="radio" checked />
      <input id="login_mode_company" name="login_mode" type="radio" />
      <div id="company-code-wrapper" class="d-none">
        <input id="login-company-code" type="text" />
      </div>
      <div id="login-error" class="d-none"></div>
      <button id="login-submit" type="submit">Login</button>
    </form>
  `;
}

describe('Auth login wiring', () => {
  beforeEach(() => {
    buildLoginDom();
    window.__ARCAV_DISABLE_REDIRECTS__ = true;
  });

  it('clears stale tenant context before regular login and redirects on success', async () => {
    const login = vi.fn().mockResolvedValue({
      data: {
        success: true,
        data: {
          activeCompany: null,
        },
      },
    });
    const clearTenantContext = vi.fn();

    window.AuthApi = {
      login,
      clearTenantContext,
      setTenantContext: vi.fn(),
    };

    await loadAuthLoginModule();
    document.dispatchEvent(new Event('DOMContentLoaded'));

    document.getElementById('api-login-form').dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
    await flush();

    expect(clearTenantContext).toHaveBeenCalledTimes(1);
    expect(login).toHaveBeenCalledWith({
      email: 'user@example.com',
      password: 'StrongPass1',
      rememberMe: false,
      companyCode: undefined,
    });
    expect(window.__ARCAV_LAST_REDIRECT__).toBe('/index');
  });

  it('requires company code in company mode before calling API', async () => {
    const login = vi.fn();

    window.AuthApi = {
      login,
      clearTenantContext: vi.fn(),
      setTenantContext: vi.fn(),
    };

    document.getElementById('login_mode_regular').checked = false;
    document.getElementById('login_mode_company').checked = true;

    await loadAuthLoginModule();
    document.dispatchEvent(new Event('DOMContentLoaded'));

    document.getElementById('api-login-form').dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
    await flush();

    expect(login).not.toHaveBeenCalled();
    expect(document.getElementById('login-error').textContent).toContain('Company code wajib diisi');
    expect(document.getElementById('login-error').classList.contains('d-none')).toBe(false);
  });

  it('stores tenant context from backend activeCompany after company login', async () => {
    const login = vi.fn().mockResolvedValue({
      data: {
        success: true,
        data: {
          activeCompany: {
            id: 44,
            uuid: '11111111-2222-3333-4444-555555555555',
            code: 'ACME',
          },
        },
      },
    });
    const setTenantContext = vi.fn();

    window.AuthApi = {
      login,
      clearTenantContext: vi.fn(),
      setTenantContext,
    };

    document.getElementById('login_mode_regular').checked = false;
    document.getElementById('login_mode_company').checked = true;
    document.getElementById('login-company-code').value = 'stale-input';

    await loadAuthLoginModule();
    document.dispatchEvent(new Event('DOMContentLoaded'));

    document.getElementById('api-login-form').dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
    await flush();

    expect(login).toHaveBeenCalledWith({
      email: 'user@example.com',
      password: 'StrongPass1',
      rememberMe: false,
      companyCode: 'stale-input',
    });
    expect(setTenantContext).toHaveBeenCalledWith({
      companyCode: 'ACME',
      companyId: 44,
      companyUuid: '11111111-2222-3333-4444-555555555555',
    });
    expect(window.__ARCAV_LAST_REDIRECT__).toBe('/index');
  });

  it('shows error when company login succeeds without active tenant payload', async () => {
    const login = vi.fn().mockResolvedValue({
      data: {
        success: true,
        data: {
          activeCompany: null,
        },
      },
    });
    const setTenantContext = vi.fn();

    window.AuthApi = {
      login,
      clearTenantContext: vi.fn(),
      setTenantContext,
    };

    document.getElementById('login_mode_regular').checked = false;
    document.getElementById('login_mode_company').checked = true;
    document.getElementById('login-company-code').value = 'ACME';

    await loadAuthLoginModule();
    document.dispatchEvent(new Event('DOMContentLoaded'));

    document.getElementById('api-login-form').dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
    await flush();

    expect(setTenantContext).not.toHaveBeenCalled();
    expect(document.getElementById('login-error').textContent).toContain('context tenant tidak valid');
    expect(window.__ARCAV_LAST_REDIRECT__).not.toBe('/index');
  });
});