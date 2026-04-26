import { beforeEach, describe, expect, it, vi } from 'vitest';

function mockJson(status, payload) {
  return Promise.resolve({
    ok: status >= 200 && status < 300,
    status,
    json: async () => payload,
    headers: { get: () => null },
  });
}

async function loadHcmExtrasModule() {
  vi.resetModules();
  return import('../../../frontend/resources/js/hcm-extras-data.js');
}

describe('HCM extras overtime calculator wiring', () => {
  beforeEach(() => {
    document.body.innerHTML = `
      <div data-hcm-ot-filters>
        <select data-hcm-ot-filter="status">
          <option value="">all</option>
          <option value="pending">pending</option>
          <option value="approved">approved</option>
          <option value="declined">declined</option>
        </select>
        <select data-hcm-ot-filter="requestType">
          <option value="">all</option>
          <option value="employee_request">employee_request</option>
          <option value="company_assignment">company_assignment</option>
          <option value="missed_log_correction">missed_log_correction</option>
        </select>
        <button type="button" data-hcm-ot-filter-reset>Reset</button>
      </div>
      <div data-hcm-overtime-pagination style="display:none;">
        <button type="button" data-hcm-overtime-prev></button>
        <button type="button" data-hcm-overtime-next></button>
      </div>
      <span data-hcm-overtime-page-info></span>
      <table><tbody data-hcm-overtime-body></tbody></table>
      <input data-hcm-ot-calc="baseSalary" value="6000000" />
      <input data-hcm-ot-calc="fixedAllowance" value="500000" />
      <input data-hcm-ot-calc="minutes" value="180" />
      <select data-hcm-ot-calc="dayType">
        <option value="workday">workday</option>
        <option value="public_holiday" selected>public_holiday</option>
      </select>
      <select data-hcm-ot-calc="weeklyWorkDays">
        <option value="5" selected>5</option>
        <option value="6">6</option>
      </select>
      <button type="button" data-hcm-ot-calc="run">Run</button>
      <div data-hcm-ot-calc="result"></div>
    `;
    window.history.pushState({}, '', '/overtime');
    window.axios = undefined;
  });

  it('submits extended dayType enum to calculate endpoint', async () => {
    global.fetch = vi.fn((url) => {
      if (url === '/v1/identity/auth/me') {
        return mockJson(200, { success: true, data: { id: 7, permissions: { 'overtime.view': true } } });
      }
      if (url.indexOf('/v1/hcm/overtime-types') === 0) {
        return mockJson(200, { success: true, data: [] });
      }
      if (url.indexOf('/v1/hcm/overtime-requests?') === 0) {
        return mockJson(200, { success: true, data: [], meta: { pagination: { page: 1, perPage: 20, total: 0, totalPages: 1 } } });
      }
      if (url === '/v1/hcm/overtime-requests/calculate') {
        return mockJson(200, {
          success: true,
          data: {
            hourlyWage: 37572,
            totalOvertimePay: 180000,
            segments: [{ label: 'Jam 1-8', hours: 3, multiplier: 2 }],
          },
        });
      }
      return mockJson(404, { success: false });
    });

    await loadHcmExtrasModule();

    await vi.waitFor(() => {
      expect(global.fetch).toHaveBeenCalledWith('/v1/identity/auth/me', expect.any(Object));
    });

    document.querySelector('[data-hcm-ot-calc="run"]').click();

    await vi.waitFor(() => {
      const call = global.fetch.mock.calls.find((entry) => entry[0] === '/v1/hcm/overtime-requests/calculate');
      expect(call).toBeTruthy();
      const payload = JSON.parse(call[1].body);
      expect(payload.dayType).toBe('public_holiday');
    });
  });

  it('shows user-friendly legal daily limit message from API error code', async () => {
    global.fetch = vi.fn((url) => {
      if (url === '/v1/identity/auth/me') {
        return mockJson(200, { success: true, data: { id: 7, permissions: { 'overtime.view': true } } });
      }
      if (url.indexOf('/v1/hcm/overtime-types') === 0) {
        return mockJson(200, { success: true, data: [] });
      }
      if (url.indexOf('/v1/hcm/overtime-requests?') === 0) {
        return mockJson(200, { success: true, data: [], meta: { pagination: { page: 1, perPage: 20, total: 0, totalPages: 1 } } });
      }
      if (url === '/v1/hcm/overtime-requests/calculate') {
        return mockJson(422, {
          success: false,
          error: { code: 'OT_DAILY_LIMIT_EXCEEDED', message: 'daily exceeded' },
        });
      }
      return mockJson(404, { success: false });
    });

    await loadHcmExtrasModule();

    document.querySelector('[data-hcm-ot-calc="run"]').click();

    await vi.waitFor(() => {
      expect(document.querySelector('[data-hcm-ot-calc="result"]').textContent).toContain('4 jam per hari');
    });
  });

  it('renders HR-friendly status and policy type microcopy in overtime list', async () => {
    global.fetch = vi.fn((url) => {
      if (url === '/v1/identity/auth/me') {
        return mockJson(200, { success: true, data: { id: 7, permissions: { 'overtime.view': true } } });
      }
      if (url.indexOf('/v1/hcm/overtime-types') === 0) {
        return mockJson(200, { success: true, data: [] });
      }
      if (url.indexOf('/v1/hcm/overtime-requests?') === 0) {
        return mockJson(200, {
          success: true,
          data: [
            {
              id: 81,
              userId: 11,
              employeeName: 'Budi HR',
                workDate: '2026-04-01',
              minutes: 120,
              projectName: 'Payroll close',
              overtimeTypeName: 'Manual',
              salaryComponentCode: 'OT',
              salaryComponentName: 'Overtime Pay',
              notes: 'need approval',
              status: 'pending',
              requestType: 'company_assignment',
              policyNote: 'Peak season',
            },
          ],
          meta: { pagination: { page: 1, perPage: 20, total: 1, totalPages: 1 } },
        });
      }
      if (url === '/v1/hcm/overtime-requests/calculate') {
        return mockJson(200, {
          success: true,
          data: { hourlyWage: 37572, totalOvertimePay: 180000, segments: [] },
        });
      }
      return mockJson(404, { success: false });
    });

    await loadHcmExtrasModule();

    await vi.waitFor(() => {
      expect(document.querySelector('[data-hcm-overtime-body]')?.textContent).toContain('Menunggu');
    });

    const bodyText = document.querySelector('[data-hcm-overtime-body]')?.textContent || '';
    expect(bodyText).toContain('Menunggu review atasan/HR');
    expect(bodyText).toContain('Penugasan perusahaan');
    expect(bodyText).toContain('Peak season');
    expect(bodyText).toContain('Prioritas: pending >24 jam');
  });

  it('filters overtime list by status and request type', async () => {
    global.fetch = vi.fn((url) => {
      if (url === '/v1/identity/auth/me') {
        return mockJson(200, { success: true, data: { id: 7, permissions: { 'overtime.view': true } } });
      }
      if (url.indexOf('/v1/hcm/overtime-types') === 0) {
        return mockJson(200, { success: true, data: [] });
      }
      if (url.indexOf('/v1/hcm/overtime-requests?') === 0) {
        return mockJson(200, {
          success: true,
          data: [
            {
              id: 91,
              userId: 11,
              employeeName: 'A',
              workDate: '2026-04-20',
              minutes: 60,
              projectName: 'A',
              overtimeTypeName: 'Manual',
              salaryComponentCode: 'OT',
              salaryComponentName: 'Overtime Pay',
              notes: 'A',
              status: 'approved',
              requestType: 'employee_request',
              policyNote: null,
            },
            {
              id: 92,
              userId: 12,
              employeeName: 'B',
              workDate: '2026-04-20',
              minutes: 60,
              projectName: 'B',
              overtimeTypeName: 'Manual',
              salaryComponentCode: 'OT',
              salaryComponentName: 'Overtime Pay',
              notes: 'B',
              status: 'pending',
              requestType: 'company_assignment',
              policyNote: 'urgent',
            },
          ],
          meta: { pagination: { page: 1, perPage: 20, total: 2, totalPages: 1 } },
        });
      }
      if (url === '/v1/hcm/overtime-requests/calculate') {
        return mockJson(200, {
          success: true,
          data: { hourlyWage: 37572, totalOvertimePay: 180000, segments: [] },
        });
      }
      return mockJson(404, { success: false });
    });

    await loadHcmExtrasModule();

    const statusSelect = document.querySelector('[data-hcm-ot-filter="status"]');
    const requestTypeSelect = document.querySelector('[data-hcm-ot-filter="requestType"]');
    statusSelect.value = 'pending';
    statusSelect.dispatchEvent(new Event('change'));
    requestTypeSelect.value = 'company_assignment';
    requestTypeSelect.dispatchEvent(new Event('change'));

    await vi.waitFor(() => {
      const bodyText = document.querySelector('[data-hcm-overtime-body]')?.textContent || '';
      expect(bodyText).toContain('B');
      expect(bodyText).not.toContain('A');
    });
  });
});
