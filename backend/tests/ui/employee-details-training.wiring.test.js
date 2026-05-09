import { beforeEach, describe, expect, it, vi } from 'vitest';

async function loadEmployeePagesModule() {
  vi.resetModules();
  await import('../../../frontend/resources/js/hcm-pages-data.js');
}

function flush(times = 8) {
  return Array.from({ length: times }).reduce((promise) => promise.then(() => Promise.resolve()), Promise.resolve());
}

function buildEmployeeDetailDom() {
  document.body.innerHTML = `
    <a data-employee-back-link href="/employees"></a>
    <div data-employee-name></div>
    <div data-employee-id></div>
    <div data-employee-email></div>
    <div data-employee-department></div>
    <div data-employee-designation></div>
    <div data-employee-team></div>
    <div data-employee-join-date></div>
    <div data-employee-phone></div>
    <div data-employee-address></div>
    <div data-employee-bio></div>
    <div data-employee-office></div>
    <div data-employee-salary></div>
    <div data-employee-allowance></div>
    <div data-employee-schedule-display></div>
    <div data-employee-schedule-source></div>
    <div data-employee-schedule-shift></div>
    <div data-employee-details-sections></div>
  `;
}

function employeePayload(id, name) {
  return {
    success: true,
    data: {
      id,
      fullName: name,
      employeeNo: `EMP-${id}`,
      email: `${name.toLowerCase().replace(/\s+/g, '.')}@example.com`,
      departmentName: 'Operations',
      designation: 'Staff',
      team: 'People Ops',
      joinDate: '2026-04-01',
      phone: '08123456789',
      address: 'Jakarta',
      bio: 'Bio employee',
      reportOffice: 'HQ',
      bank: {},
      schedule: { display: 'Mon-Fri', sourceLabel: 'Policy', shiftName: 'Regular' },
      educationItems: [],
      experienceItems: [],
      employmentHistory: [],
      assignmentHistory: [],
      compensationHistory: [],
      contractHistory: [],
      bankAccounts: [],
      documents: [],
      emergencyContacts: [],
    },
  };
}

function trainingPayload(row) {
  return {
    success: true,
    data: [row],
  };
}

describe('Employee detail training integration', () => {
  beforeEach(() => {
    buildEmployeeDetailDom();
    window.__ARCAV_DISABLE_REDIRECTS__ = true;
  });

  it('renders employee training history for admin review via per-user endpoint', async () => {
    window.history.pushState({}, '', '/employee-details?id=42&returnTo=%2Femployees');

    const fetchMock = vi.fn((url, options = {}) => {
      const urlString = String(url);

      if (urlString.startsWith('/v1/identity/auth/me')) {
        return Promise.resolve({
          ok: true,
          status: 200,
          json: async () => ({ success: true, data: { id: 42, role: 'admin' } }),
        });
      }

      if (urlString === '/v1/hcm/employees/42') {
        return Promise.resolve({
          ok: true,
          status: 200,
          json: async () => employeePayload(42, 'Admin Reviewee'),
        });
      }

      if (urlString === '/v1/hcm/training/users/42/trainings?perPage=20') {
        return Promise.resolve({
          ok: true,
          status: 200,
          json: async () => trainingPayload({
            id: 9,
            type: { name: 'Safety Training' },
            trainerName: 'Trainer Admin',
            startDate: '2026-04-09',
            endDate: '2026-04-10',
            status: 'completed',
            description: 'Completed by admin review',
          }),
        });
      }

      if (urlString.includes('/v1/hcm/promotions/users/42/promotions') || urlString.includes('/v1/hcm/resignations/users/42/resignations') || urlString.includes('/v1/hcm/terminations/users/42/terminations')) {
        return Promise.resolve({
          ok: true,
          status: 200,
          json: async () => ({ success: true, data: [] }),
        });
      }

      throw new Error(`Unexpected fetch: ${url}`);
    });

    vi.stubGlobal('fetch', fetchMock);
    window.AuthApi = {
      getToken: () => 'token-admin',
      getTenantContext: () => ({
        companyCode: 'ACME',
        companyId: 77,
        companyUuid: '11111111-2222-3333-4444-555555555555',
      }),
      handleUnauthorizedFromApi: () => false,
    };

    await loadEmployeePagesModule();
    document.dispatchEvent(new Event('DOMContentLoaded'));
    await flush();

    expect(fetchMock).toHaveBeenCalled();

    const trainingCall = fetchMock.mock.calls.find(([url]) => String(url).includes('/v1/hcm/training/users/42/trainings'));
    expect(trainingCall).toBeTruthy();
    expect(trainingCall[1].headers.Authorization).toBe('Bearer token-admin');
    expect(trainingCall[1].headers['X-Company-Code']).toBe('ACME');
    expect(trainingCall[1].headers['X-Company-Id']).toBe('77');
    expect(trainingCall[1].headers['X-Company-UUID']).toBe('11111111-2222-3333-4444-555555555555');

    const html = document.querySelector('[data-employee-details-sections]').innerHTML;
    expect(html).toContain('Safety Training');
    expect(html).toContain('Trainer Admin');
    expect(html).toContain('Completed by admin review');
  });

  it('renders self training history from the same employee-detail page contract', async () => {
    window.history.pushState({}, '', '/employee-details?id=17');

    const fetchMock = vi.fn((url) => {
      const urlString = String(url);

      if (urlString.startsWith('/v1/identity/auth/me')) {
        return Promise.resolve({
          ok: true,
          status: 200,
          json: async () => ({ success: true, data: { id: 17, role: 'employee' } }),
        });
      }

      if (urlString === '/v1/hcm/employees/17') {
        return Promise.resolve({
          ok: true,
          status: 200,
          json: async () => employeePayload(17, 'Self Employee'),
        });
      }

      if (urlString === '/v1/hcm/training/users/17/trainings?perPage=20') {
        return Promise.resolve({
          ok: true,
          status: 200,
          json: async () => trainingPayload({
            id: 10,
            type: { name: 'Onboarding Training' },
            trainerName: 'Trainer Self',
            startDate: '2026-04-11',
            endDate: '2026-04-11',
            status: 'active',
            description: 'Visible in self detail',
          }),
        });
      }

      if (urlString.includes('/v1/hcm/promotions/users/17/promotions') || urlString.includes('/v1/hcm/resignations/users/17/resignations') || urlString.includes('/v1/hcm/terminations/users/17/terminations')) {
        return Promise.resolve({
          ok: true,
          status: 200,
          json: async () => ({ success: true, data: [] }),
        });
      }

      throw new Error(`Unexpected fetch: ${url}`);
    });

    vi.stubGlobal('fetch', fetchMock);
    window.AuthApi = {
      getToken: () => 'token-self',
      getTenantContext: () => ({
        companyCode: 'SELF',
        companyId: 12,
        companyUuid: 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee',
      }),
      handleUnauthorizedFromApi: () => false,
    };

    await loadEmployeePagesModule();
    document.dispatchEvent(new Event('DOMContentLoaded'));
    await flush();

    const calls = fetchMock.mock.calls.map(([url]) => String(url));
    expect(calls).toContain('/v1/hcm/employees/17');
    expect(calls).toContain('/v1/hcm/training/users/17/trainings?perPage=20');

    const html = document.querySelector('[data-employee-details-sections]').innerHTML;
    expect(html).toContain('Onboarding Training');
    expect(html).toContain('Trainer Self');
    expect(html).toContain('Visible in self detail');
  });

  it('renders termination history from the employee-detail relation contract', async () => {
    window.history.pushState({}, '', '/employee-details?id=61');

    const fetchMock = vi.fn((url) => {
      const urlString = String(url);

      if (urlString.startsWith('/v1/identity/auth/me')) {
        return Promise.resolve({
          ok: true,
          status: 200,
          json: async () => ({ success: true, data: { id: 61, role: 'admin' } }),
        });
      }

      if (urlString === '/v1/hcm/employees/61') {
        return Promise.resolve({
          ok: true,
          status: 200,
          json: async () => employeePayload(61, 'Termination Reviewee'),
        });
      }

      if (urlString === '/v1/hcm/training/users/61/trainings?perPage=20' || urlString === '/v1/hcm/promotions/users/61/promotions?perPage=20' || urlString === '/v1/hcm/resignations/users/61/resignations?perPage=20') {
        return Promise.resolve({
          ok: true,
          status: 200,
          json: async () => ({ success: true, data: [] }),
        });
      }

      if (urlString === '/v1/hcm/terminations/users/61/terminations?perPage=20') {
        return Promise.resolve({
          ok: true,
          status: 200,
          json: async () => ({
            success: true,
            data: [
              {
                id: 13,
                noticeDate: '2026-04-01',
                terminationDate: '2026-04-30',
                terminationType: 'Layoff',
                reason: 'Role redundancy after restructuring',
                status: 'approved',
              },
            ],
          }),
        });
      }

      throw new Error(`Unexpected fetch: ${url}`);
    });

    vi.stubGlobal('fetch', fetchMock);
    window.AuthApi = {
      getToken: () => 'token-termination',
      getTenantContext: () => ({
        companyCode: 'TERM',
        companyId: 61,
        companyUuid: 'bbbbbbbb-cccc-dddd-eeee-ffffffffffff',
      }),
      handleUnauthorizedFromApi: () => false,
    };

    await loadEmployeePagesModule();
    document.dispatchEvent(new Event('DOMContentLoaded'));
    await flush();

    const calls = fetchMock.mock.calls.map(([url]) => String(url));
    expect(calls).toContain('/v1/hcm/terminations/users/61/terminations?perPage=20');

    const html = document.querySelector('[data-employee-details-sections]').innerHTML;
    expect(html).toContain('Layoff');
    expect(html).toContain('Role redundancy after restructuring');
    expect(html).toContain('data-arcav-termination-view="13"');
  });
});