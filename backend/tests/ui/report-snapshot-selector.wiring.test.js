import { beforeEach, describe, expect, it, vi } from 'vitest';

function jsonResponse(body, status = 200) {
  return Promise.resolve({
    ok: status >= 200 && status < 300,
    status,
    json: async () => body,
  });
}

function flushPromises() {
  return new Promise((resolve) => setTimeout(resolve, 0));
}

async function loadModule(modulePath, pathname) {
  vi.resetModules();
  window.history.replaceState({}, '', pathname);
  if (modulePath === 'employees') {
    await import('../../../frontend/resources/js/employees-data.js');
  } else if (modulePath === 'leave') {
    await import('../../../frontend/resources/js/hcm-extras-data.js');
  } else if (modulePath === 'payslip') {
    await import('../../../frontend/resources/js/payslip-admin-data.js');
  } else {
    throw new Error(`Unknown module: ${modulePath}`);
  }
  await flushPromises();
}

describe('Manual snapshot selector reporting', () => {
  beforeEach(() => {
    document.body.innerHTML = '';
    localStorage.clear();
    vi.restoreAllMocks();
    vi.stubGlobal('fetch', vi.fn());
    window.axios = undefined;
    window.ApexCharts = undefined;
    window.AuthApi = {
      handleUnauthorizedFromApi: vi.fn(() => false),
      request: vi.fn().mockResolvedValue({
        success: true,
        data: {
          permissions: {
            'payroll.view': true,
          },
        },
      }),
    };
  });

  it('rejects employee archive snapshots that are not completed', async () => {
    document.body.innerHTML = `
      <select data-employee-report-source>
        <option value="live">Live</option>
        <option value="archive" selected>Archive</option>
      </select>
      <div data-employee-report-snapshot-wrap></div>
      <input data-employee-report-snapshot-id value="51">
      <button data-employee-report-load></button>
      <span data-employee-report-source-badge></span>
      <div data-employee-report-total></div>
      <div data-employee-report-active></div>
      <div data-employee-report-inactive></div>
      <div data-employee-report-new></div>
      <table><tbody data-employee-report-body></tbody></table>
    `;

    const fetchMock = vi.mocked(fetch);
    fetchMock.mockImplementation((url) => {
      if (url === '/v1/identity/auth/me') {
        return jsonResponse({
          success: true,
          data: {
            permissions: {
              'employee.view': true,
            },
          },
        });
      }
      if (url === '/v1/hcm/reports/snapshots/51') {
        return jsonResponse({
          success: true,
          data: {
            id: 51,
            reportType: 'employee',
            status: 'processing',
            dataByModule: {},
          },
        });
      }

      throw new Error(`Unhandled fetch: ${String(url)}`);
    });

    await loadModule('employees', '/employee-report');

    expect(document.querySelector('[data-employee-report-body]').textContent).toContain('belum siap digunakan');
    expect(document.querySelector('[data-employee-report-total]').textContent).toBe('0');
  });

  it('rejects leave archive snapshots that are not completed', async () => {
    document.body.innerHTML = `
      <select data-leave-report-source>
        <option value="live">Live</option>
        <option value="archive" selected>Archive</option>
      </select>
      <div data-leave-report-snapshot-wrap></div>
      <input data-leave-report-snapshot-id value="61">
      <button data-leave-report-load></button>
      <span data-leave-report-source-badge></span>
      <div data-leave-report-total-requests></div>
      <div data-leave-report-total-days></div>
      <div data-leave-report-approved></div>
      <div data-leave-report-pending></div>
      <div id="leave-report-chart"></div>
      <table><tbody data-leave-report-body></tbody></table>
    `;

    const fetchMock = vi.mocked(fetch);
    fetchMock.mockImplementation((url) => {
      if (url === '/v1/identity/auth/me') {
        return jsonResponse({
          success: true,
          data: {
            permissions: {
              'leave.view': true,
            },
          },
        });
      }
      if (url === '/v1/hcm/reports/snapshots/61') {
        return jsonResponse({
          success: true,
          data: {
            id: 61,
            reportType: 'leave',
            status: 'pending',
            dataByModule: {},
          },
        });
      }

      throw new Error(`Unhandled fetch: ${String(url)}`);
    });

    await loadModule('leave', '/leave-report');

    expect(document.querySelector('[data-leave-report-body]').textContent).toContain('Tidak ada data leave report');
    expect(document.querySelector('[data-hcm-toast-container]').textContent).toContain('belum siap digunakan');
  });

  it('aggregates live leave report across paginated API pages', async () => {
    document.body.innerHTML = `
      <select data-leave-report-source>
        <option value="live" selected>Live</option>
        <option value="archive">Archive</option>
      </select>
      <div data-leave-report-snapshot-wrap class="d-none"></div>
      <input data-leave-report-snapshot-id value="0">
      <button data-leave-report-load></button>
      <span data-leave-report-source-badge></span>
      <div data-leave-report-total-requests></div>
      <div data-leave-report-total-days></div>
      <div data-leave-report-approved></div>
      <div data-leave-report-pending></div>
      <div id="leave-report-chart"></div>
      <table><tbody data-leave-report-body></tbody></table>
    `;

    const fetchMock = vi.mocked(fetch);
    fetchMock.mockImplementation((url) => {
      if (url === '/v1/identity/auth/me') {
        return jsonResponse({
          success: true,
          data: {
            permissions: {
              'leave.view': true,
            },
          },
        });
      }
      if (url === '/v1/hcm/leave-requests?perPage=100&page=1') {
        return jsonResponse({
          success: true,
          data: [
            {
              employeeName: 'Ayu',
              leaveType: 'annual_leave',
              leaveTypeLabel: 'Annual Leave',
              dateFrom: '2026-04-01',
              dateTo: '2026-04-01',
              days: 1,
              status: 'approved',
            },
          ],
          meta: {
            summary: { totalRequests: 2 },
            pagination: { page: 1, perPage: 100, total: 2, totalPages: 2 },
          },
        });
      }
      if (url === '/v1/hcm/leave-requests?perPage=100&page=2') {
        return jsonResponse({
          success: true,
          data: [
            {
              employeeName: 'Bima',
              leaveType: 'sick_leave',
              leaveTypeLabel: 'Sick Leave',
              dateFrom: '2026-04-02',
              dateTo: '2026-04-03',
              days: 2,
              status: 'pending',
            },
          ],
          meta: {
            pagination: { page: 2, perPage: 100, total: 2, totalPages: 2 },
          },
        });
      }

      throw new Error(`Unhandled fetch: ${String(url)}`);
    });

    await loadModule('leave', '/leave-report');

    expect(document.querySelector('[data-leave-report-total-requests]').textContent).toBe('2');
    expect(document.querySelector('[data-leave-report-total-days]').textContent).toBe('3');
    expect(document.querySelector('[data-leave-report-approved]').textContent).toBe('1');
    expect(document.querySelector('[data-leave-report-pending]').textContent).toBe('1');
    expect(document.querySelector('[data-leave-report-body]').textContent).toContain('Ayu');
    expect(document.querySelector('[data-leave-report-body]').textContent).toContain('Bima');
  });

  it('rejects payslip archive snapshots with the wrong report type', async () => {
    document.body.innerHTML = `
      <select data-payslip-admin-source>
        <option value="live">Live</option>
        <option value="archive" selected>Archive</option>
      </select>
      <div data-payslip-admin-snapshot-wrap></div>
      <input data-payslip-admin-snapshot-id value="71">
      <select data-payslip-admin-year><option value="2026" selected>2026</option></select>
      <select data-payslip-admin-month><option value="4" selected>4</option></select>
      <button data-payslip-admin-load></button>
      <span data-payslip-admin-source-badge></span>
      <span data-payslip-admin-run-info></span>
      <div data-payslip-admin-error class="d-none"></div>
      <div data-payslip-admin-summary style="display:none!important;">
        <div data-payslip-admin-count></div>
        <div data-payslip-admin-employees></div>
        <div data-payslip-admin-periods></div>
        <div data-payslip-admin-total-net></div>
      </div>
      <button data-payslip-admin-send-selected disabled></button>
      <table><tbody data-payslip-admin-body></tbody></table>
    `;

    const fetchMock = vi.mocked(fetch);
    fetchMock.mockImplementation((url) => {
      if (url === '/v1/hcm/reports/snapshots/71') {
        return jsonResponse({
          success: true,
          data: {
            id: 71,
            reportType: 'attendance',
            status: 'completed',
            dataByModule: {},
          },
        });
      }

      throw new Error(`Unhandled fetch: ${String(url)}`);
    });

    await loadModule('payslip', '/payslip-report');

    expect(window.AuthApi.request).toHaveBeenCalled();
    expect(document.querySelector('[data-payslip-admin-error]').textContent).toContain('bukan payroll report');
    expect(document.querySelector('[data-payslip-admin-body]').textContent).toContain('bukan payroll report');
  });
});
