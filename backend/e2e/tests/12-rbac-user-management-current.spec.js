import { test, expect } from '@playwright/test';

const ADMIN_CREDENTIALS = {
  email: 'qa.login@example.com',
  password: 'StrongPass1',
  companyCode: 'default_company',
};

const MEMBER_CREDENTIALS = {
  email: 'qa.member@example.com',
  password: 'StrongPass1',
};

async function ensureAdminUserExists(page) {
  const registerResponse = await page.request.post('/v1/identity/auth/register', {
    headers: {
      Accept: 'application/json',
      'Content-Type': 'application/json',
    },
    data: {
      name: 'QA Admin',
      email: ADMIN_CREDENTIALS.email,
      password: ADMIN_CREDENTIALS.password,
      confirmPassword: ADMIN_CREDENTIALS.password,
    },
  });

  if (registerResponse.ok()) {
    return;
  }

  const payload = await registerResponse.json().catch(() => ({}));
  const details = Array.isArray(payload?.error?.details) ? payload.error.details : [];
  const message = [
    payload?.error?.message,
    payload?.message,
    ...details.map((detail) => detail?.message),
  ]
    .filter(Boolean)
    .join(' ');

  expect(
    registerResponse.status() === 422 && /taken|exists|already/i.test(message)
  ).toBeTruthy();
}

async function ensureMemberUserExists(page) {
  const registerResponse = await page.request.post('/v1/identity/auth/register', {
    headers: {
      Accept: 'application/json',
      'Content-Type': 'application/json',
    },
    data: {
      name: 'QA Member',
      email: MEMBER_CREDENTIALS.email,
      password: MEMBER_CREDENTIALS.password,
      confirmPassword: MEMBER_CREDENTIALS.password,
    },
  });

  if (registerResponse.ok()) {
    return;
  }

  const payload = await registerResponse.json().catch(() => ({}));
  const details = Array.isArray(payload?.error?.details) ? payload.error.details : [];
  const message = [
    payload?.error?.message,
    payload?.message,
    ...details.map((detail) => detail?.message),
  ]
    .filter(Boolean)
    .join(' ');

  expect(
    registerResponse.status() === 422 && /taken|exists|already/i.test(message)
  ).toBeTruthy();
}

async function loginAsAdmin(page) {
  await ensureAdminUserExists(page);

  const loginResponse = await page.request.post('/v1/identity/auth/login', {
    headers: {
      Accept: 'application/json',
      'Content-Type': 'application/json',
    },
    data: {
      email: ADMIN_CREDENTIALS.email,
      password: ADMIN_CREDENTIALS.password,
      companyCode: ADMIN_CREDENTIALS.companyCode,
    },
  });

  expect(loginResponse.ok()).toBeTruthy();

  const loginPayload = await loginResponse.json();
  const activeCompany = loginPayload?.data?.activeCompany;

  expect(activeCompany?.code).toBe(ADMIN_CREDENTIALS.companyCode);
  expect(activeCompany?.id).toBeTruthy();

  await page.addInitScript((tenant) => {
    window.localStorage.setItem('arcav_active_tenant', JSON.stringify(tenant));
  }, {
    companyCode: activeCompany.code,
    companyId: activeCompany.id,
    companyUuid: activeCompany.uuid || undefined,
  });

  await page.goto('/index');
  await page.waitForURL(/index|dashboard|employee-dashboard/);
}

async function getTenantHeaders(page) {
  return page.evaluate(async () => {
    const tenant = window.AuthApi && typeof window.AuthApi.getTenantContext === 'function'
      ? (window.AuthApi.getTenantContext() || {})
      : {};

    const tokenResponse = await fetch('/api-token', {
      method: 'GET',
      headers: { Accept: 'application/json' },
      credentials: 'same-origin',
    });

    const tokenPayload = await tokenResponse.json().catch(() => ({}));
    const token = tokenPayload && tokenPayload.success && tokenPayload.data
      ? tokenPayload.data.token
      : null;

    return {
      token,
      companyId: tenant.companyId ? String(tenant.companyId) : null,
      companyCode: tenant.companyCode ? String(tenant.companyCode) : null,
      companyUuid: tenant.companyUuid ? String(tenant.companyUuid) : null,
    };
  });
}

function buildApiHeaders(session) {
  const headers = {
    Accept: 'application/json',
    Authorization: `Bearer ${session.token}`,
  };

  if (session.companyId) {
    headers['X-Company-Id'] = session.companyId;
  }
  if (session.companyCode) {
    headers['X-Company-Code'] = session.companyCode;
  }
  if (session.companyUuid) {
    headers['X-Company-UUID'] = session.companyUuid;
  }

  return headers;
}

test.describe('RBAC user-management current flow', () => {
  test('global admin can manage role permissions and user assignment end-to-end', async ({ page }) => {
    await loginAsAdmin(page);
    await ensureMemberUserExists(page);

    await page.goto('/users');
    await expect(page.getByRole('heading', { name: 'Users', exact: true })).toBeVisible();
    await expect(page.locator('#um_users_tbody')).toBeVisible();
    await expect(page.locator('[data-bs-target="#um_user_modal"]')).toBeVisible();
    await expect(page.locator('#um_assign_role_btn')).toHaveCount(1);

    await page.goto('/roles-permissions');
    await expect(page.getByRole('heading', { name: 'Roles', exact: true })).toBeVisible();
    await expect(page.locator('#rp_roles_tbody')).toBeVisible();
    await expect(page.locator('#rp_open_create_modal')).toBeVisible();
    await expect(page.locator('#rp_save_permissions')).toHaveCount(1);

    const session = await getTenantHeaders(page);
    expect(session.token).toBeTruthy();

    const headers = buildApiHeaders(session);
    const roleCode = `COPILOT_E2E_${Date.now()}`;
    let createdRoleId = null;
    let assignedUserId = null;
    let assignmentId = null;

    try {
      const meResponse = await page.request.get('/v1/identity/auth/me', { headers });
      expect(meResponse.ok()).toBeTruthy();

      const mePayload = await meResponse.json();
      expect(mePayload.success).toBeTruthy();
      expect(mePayload.data.hcmAdmin).toBeTruthy();
      expect(mePayload.data.hcmGlobalAdmin).toBeTruthy();
      expect(mePayload.data.permissions['role.sync_permission']).toBeTruthy();
      expect(mePayload.data.permissions['user.assign_role']).toBeTruthy();

      const createRoleResponse = await page.request.post('/v1/hcm/user-management/roles', {
        headers: {
          ...headers,
          'Content-Type': 'application/json',
        },
        data: {
          code: roleCode,
          name: 'Copilot E2E Role',
          description: 'Temporary validation role',
          status: 'active',
        },
      });
      expect(createRoleResponse.ok()).toBeTruthy();

      const createdRolePayload = await createRoleResponse.json();
      createdRoleId = createdRolePayload.data.id;
      expect(createdRoleId).toBeTruthy();

      const permissionsResponse = await page.request.get('/v1/hcm/user-management/permissions', { headers });
      expect(permissionsResponse.ok()).toBeTruthy();
      const permissionsPayload = await permissionsResponse.json();
      const permissionCode = permissionsPayload.data[0]?.code;
      expect(permissionCode).toBeTruthy();

      const syncResponse = await page.request.post(`/v1/hcm/user-management/roles/${createdRoleId}/permissions:sync`, {
        headers: {
          ...headers,
          'Content-Type': 'application/json',
        },
        data: {
          permissionCodes: [permissionCode],
        },
      });
      expect(syncResponse.ok()).toBeTruthy();

      const usersResponse = await page.request.get('/v1/hcm/user-management/users?page=1&perPage=10', { headers });
      expect(usersResponse.ok()).toBeTruthy();
      const usersPayload = await usersResponse.json();
      const targetUser = usersPayload.data.find((user) => String(user.email).toLowerCase() === MEMBER_CREDENTIALS.email);
      expect(targetUser).toBeTruthy();
      assignedUserId = targetUser.id;

      const assignResponse = await page.request.post(`/v1/hcm/user-management/users/${assignedUserId}/roles`, {
        headers: {
          ...headers,
          'Content-Type': 'application/json',
        },
        data: {
          roleCode,
        },
      });
      expect(assignResponse.ok()).toBeTruthy();

      const assignPayload = await assignResponse.json();
      assignmentId = assignPayload.data.assignmentId;
      expect(assignmentId).toBeTruthy();

      const assignmentsResponse = await page.request.get(`/v1/hcm/user-management/users/${assignedUserId}/roles`, { headers });
      expect(assignmentsResponse.ok()).toBeTruthy();
      const assignmentsPayload = await assignmentsResponse.json();
      const activeAssignment = assignmentsPayload.data.find((item) => item.role?.code === roleCode && item.status === 'active');
      expect(activeAssignment).toBeTruthy();
    } finally {
      if (assignedUserId && assignmentId) {
        await page.request.delete(`/v1/hcm/user-management/users/${assignedUserId}/roles/${assignmentId}`, {
          headers,
        });
      }

      if (createdRoleId) {
        await page.request.delete(`/v1/hcm/user-management/roles/${createdRoleId}`, {
          headers,
        });
      }
    }
  });
});