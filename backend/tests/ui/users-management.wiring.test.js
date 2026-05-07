import { beforeEach, describe, expect, it, vi } from 'vitest';

async function loadUsersManagement() {
  vi.resetModules();
  await import('../../../frontend/resources/js/users-management.js');
}

function flush() {
  return Promise.resolve().then(() => Promise.resolve());
}

function buildUsersDom() {
  document.body.innerHTML = `
    <div class="main-wrapper" data-subscription-status="trial" data-role-scope="hcm-admin"></div>
    <button type="button" data-bs-target="#um_user_modal">Create</button>
    <input id="um_search" />
    <select id="um_status_filter"><option value="active">Active</option></select>
    <select id="um_role_filter"></select>
    <button id="um_reset_filters"></button>
    <button id="um_prev_page"></button>
    <button id="um_next_page"></button>
    <button id="btn_um_export_csv"></button>
    <div id="um_alert" class="d-none"></div>
    <table><tbody id="um_users_tbody"></tbody></table>
    <form id="um_user_form">
      <input id="um_name" />
      <input id="um_email" />
      <input id="um_password" />
      <select id="um_status"></select>
      <select id="um_role_codes" multiple></select>
      <div id="um_password_wrap"></div>
      <div id="um_roles_wrap"></div>
      <button type="submit"></button>
    </form>
    <button id="um_assign_role_btn"></button>
    <select id="um_assign_role_code"></select>
    <div id="um_role_modal"></div>
    <input id="um_role_user_id" />
    <div id="um_role_user_name"></div>
    <div id="um_role_assignment_list"></div>
    <div id="um_role_empty"></div>
    <div id="um_role_loading"></div>
    <div id="um_pagination_meta"></div>
  `;
}

describe('Users management wiring', () => {
  beforeEach(() => {
    localStorage.clear();
    buildUsersDom();
    window.AuthApi = {
      getToken: () => 'token-abc',
      getTenantContext: () => ({
        companyCode: 'ACME',
        companyId: 99,
        companyUuid: '11111111-2222-3333-4444-555555555555',
      }),
    };
  });

  it('exports user list with auth and tenant headers', async () => {
    window.AuthPermissions = { hasPermission: () => true };
    const fetchMock = vi.fn((url) => {
      if (String(url).includes('/hcm/user-management/roles')) {
        return Promise.resolve({
          ok: true,
          status: 200,
          json: async () => ({ success: true, data: [{ code: 'HR_ADMIN', name: 'HR Admin' }] }),
        });
      }

      if (String(url).includes('/hcm/user-management/users?')) {
        return Promise.resolve({
          ok: true,
          status: 200,
          json: async () => ({ success: true, data: [], meta: { pagination: { page: 1, perPage: 20, total: 0, lastPage: 1 } } }),
        });
      }

      if (String(url).includes('/hcm/user-management/users/export?')) {
        return Promise.resolve({
          ok: true,
          status: 200,
          blob: async () => new Blob(['id,name\n1,Alice\n']),
        });
      }

      throw new Error(`Unexpected fetch: ${url}`);
    });

    Object.defineProperty(window.URL, 'createObjectURL', {
      configurable: true,
      value: vi.fn(() => 'blob:mock'),
    });
    Object.defineProperty(window.URL, 'revokeObjectURL', {
      configurable: true,
      value: vi.fn(() => {}),
    });
    const createObjectUrlSpy = window.URL.createObjectURL;
    const revokeObjectUrlSpy = window.URL.revokeObjectURL;
    const anchorClickSpy = vi.spyOn(HTMLAnchorElement.prototype, 'click').mockImplementation(() => {});

    vi.stubGlobal('fetch', fetchMock);

    await loadUsersManagement();
    document.dispatchEvent(new Event('DOMContentLoaded'));
    await flush();

    document.getElementById('btn_um_export_csv').click();
    await flush();

    const exportCall = fetchMock.mock.calls.find(([url]) => String(url).includes('/hcm/user-management/users/export?'));
    expect(exportCall).toBeTruthy();
    expect(String(exportCall[0])).toContain('/v1/hcm/user-management/users/export?');
    expect(String(exportCall[0])).toContain('status=active');
    expect(String(exportCall[0])).toContain('format=xlsx');
    expect(exportCall[1].headers.Authorization).toBe('Bearer token-abc');
    expect(exportCall[1].headers['X-Company-Code']).toBe('ACME');
    expect(exportCall[1].headers['X-Company-Id']).toBe('99');
    expect(exportCall[1].headers['X-Company-UUID']).toBe('11111111-2222-3333-4444-555555555555');
    expect(createObjectUrlSpy).toHaveBeenCalled();
    expect(revokeObjectUrlSpy).toHaveBeenCalled();
    expect(anchorClickSpy).toHaveBeenCalled();
  });

  it('loads roles and users with tenant headers and hides create when users.manage is missing', async () => {
    const fetchMock = vi.fn((url) => {
      if (String(url).includes('/hcm/user-management/roles')) {
        return Promise.resolve({
          ok: true,
          status: 200,
          json: async () => ({ success: true, data: [] }),
        });
      }

      if (String(url).includes('/hcm/user-management/users?')) {
        return Promise.resolve({
          ok: true,
          status: 200,
          json: async () => ({ success: true, data: [], meta: { pagination: { page: 1, perPage: 20, total: 0, lastPage: 1 } } }),
        });
      }

      throw new Error(`Unexpected fetch: ${url}`);
    });

    vi.stubGlobal('fetch', fetchMock);
    window.AuthPermissions = { hasPermission: () => false };

    await loadUsersManagement();
    document.dispatchEvent(new Event('DOMContentLoaded'));
    await flush();

    const rolesCall = fetchMock.mock.calls.find(([url]) => String(url).includes('/hcm/user-management/roles?scope=company&status=active'));
    const usersCall = fetchMock.mock.calls.find(([url]) => String(url).includes('/hcm/user-management/users?page=1&perPage=20&status=active'));
    expect(rolesCall).toBeTruthy();
    expect(usersCall).toBeTruthy();

    const [rolesUrl, rolesOptions] = rolesCall;
    expect(String(rolesUrl)).toContain('/v1/hcm/user-management/roles?scope=company&status=active');
    expect(rolesOptions.headers.Authorization).toBe('Bearer token-abc');
    expect(rolesOptions.headers['X-Company-Code']).toBe('ACME');
    expect(rolesOptions.headers['X-Company-Id']).toBe('99');
    expect(rolesOptions.headers['X-Company-UUID']).toBe('11111111-2222-3333-4444-555555555555');

    const [usersUrl, usersOptions] = usersCall;
    expect(String(usersUrl)).toContain('/v1/hcm/user-management/users?page=1&perPage=20&status=active');
    expect(usersOptions.headers.Authorization).toBe('Bearer token-abc');

    expect(document.querySelector("[data-bs-target='#um_user_modal']").classList.contains('d-none')).toBe(true);
    expect(document.getElementById('um_user_form').querySelector('input').disabled).toBe(true);
  });

  it('keeps create flow enabled when users.manage is granted', async () => {
    const fetchMock = vi.fn((url) => {
      if (String(url).includes('/hcm/user-management/roles')) {
        return Promise.resolve({
          ok: true,
          status: 200,
          json: async () => ({ success: true, data: [{ code: 'HR_ADMIN', name: 'HR Admin' }] }),
        });
      }

      if (String(url).includes('/hcm/user-management/users?')) {
        return Promise.resolve({
          ok: true,
          status: 200,
          json: async () => ({ success: true, data: [], meta: { pagination: { page: 1, perPage: 20, total: 0, lastPage: 1 } } }),
        });
      }

      throw new Error(`Unexpected fetch: ${url}`);
    });

    vi.stubGlobal('fetch', fetchMock);
    window.AuthPermissions = { hasPermission: () => true };

    await loadUsersManagement();
    document.dispatchEvent(new Event('DOMContentLoaded'));
    await flush();

    expect(document.querySelector("[data-bs-target='#um_user_modal']").classList.contains('d-none')).toBe(false);
    expect(document.getElementById('um_user_form').querySelector('input').disabled).toBe(false);
  });
});