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

function installBootstrapModalStub() {
  const instances = new WeakMap();

  class ModalStub {
    constructor(element) {
      this.element = element;
      instances.set(element, this);
    }

    hide() {}

    static getInstance(element) {
      return instances.get(element) || null;
    }

    static getOrCreateInstance(element) {
      return instances.get(element) || new ModalStub(element);
    }
  }

  window.bootstrap = { Modal: ModalStub };
}

async function loadLeaveModule(pathname) {
  vi.resetModules();
  window.history.replaceState({}, '', pathname);
  await import('../../../frontend/resources/js/hcm-extras-data.js');
  document.dispatchEvent(new Event('DOMContentLoaded'));
  await flushPromises();
  await flushPromises();
}

describe('Leave UI wiring', () => {
  beforeEach(() => {
    document.body.innerHTML = '';
    localStorage.clear();
    vi.restoreAllMocks();
    vi.stubGlobal('fetch', vi.fn());
    window.axios = undefined;
    window.AuthApi = {
      handleUnauthorizedFromApi: vi.fn(() => false),
    };
    window.ApiErrorHelper = undefined;
    window.ArcavUi = undefined;
    window.flatpickr = undefined;
    installBootstrapModalStub();
  });

  it('submits admin leave creation with numeric userId from employee options', async () => {
    document.body.innerHTML = `
      <table><tbody data-hcm-leaves-admin-body></tbody></table>
      <div data-hcm-leaves-pagination style="display:none;">
        <span data-hcm-leaves-page-info></span>
        <button data-hcm-leaves-prev></button>
        <button data-hcm-leaves-next></button>
      </div>
      <div data-hcm-leaves-filters></div>
      <div id="arcav_add_leave"></div>
      <form data-hcm-leave-form="add">
        <select data-hcm-field="userId"></select>
        <select data-hcm-field="leaveType"></select>
        <input data-hcm-field="dateFrom" value="2026-04-14">
        <input data-hcm-field="dateTo" value="2026-04-15">
        <input data-hcm-field="days" value="2">
        <textarea data-hcm-field="notes">Created from UI</textarea>
        <div data-hcm-leave-balance-card class="d-none">
          <span data-hcm-leave-balance-value></span>
          <span data-hcm-leave-balance-total></span>
        </div>
        <div data-hcm-leave-error-add class="d-none">
          <span data-hcm-error-title></span>
          <span data-hcm-error-message></span>
        </div>
      </form>
      <div id="arcav_edit_leave"></div>
    `;

    const fetchMock = vi.mocked(fetch);
    fetchMock.mockImplementation((url, options = {}) => {
      const urlString = String(url);

      if (urlString === '/v1/identity/auth/me') {
        return jsonResponse({
          success: true,
          data: {
            id: 10,
            permissions: {
              'leave.view': true,
            },
          },
        });
      }

      if (urlString.startsWith('/v1/hcm/leave-requests?')) {
        return jsonResponse({
          success: true,
          data: [],
          meta: {
            summary: { totalRequests: 0, approved: 0, declined: 0, pending: 0 },
            holidays: [],
            pagination: { total: 0, page: 1, perPage: 20, totalPages: 1 },
          },
        });
      }

      if (urlString === '/v1/hcm/leave-type-options') {
        return jsonResponse({
          success: true,
          data: [
            { code: 'annual_leave', name: 'Annual Leave', isPaid: true, deductFromBalance: true },
          ],
        });
      }

      if (urlString.startsWith('/v1/hcm/employees?')) {
        return jsonResponse({
          success: true,
          data: [
            { id: 42, fullName: 'Budi', email: 'budi@example.com', baseSalary: 0, fixedAllowance: 0 },
          ],
          meta: { total: 1 },
        });
      }

      if (urlString === '/v1/hcm/leave-requests' && String(options.method || '').toLowerCase() === 'post') {
        return jsonResponse({ success: true, data: { id: 501 } }, 201);
      }

      throw new Error(`Unhandled fetch: ${urlString} ${options.method || 'GET'}`);
    });

    await loadLeaveModule('/leaves');

    const userSelect = document.querySelector('[data-hcm-field="userId"]');
    const leaveTypeSelect = document.querySelector('[data-hcm-field="leaveType"]');
    userSelect.value = '42';
    leaveTypeSelect.innerHTML = '<option value="Annual Leave">Annual Leave</option>';
    leaveTypeSelect.value = 'Annual Leave';

    const form = document.querySelector('[data-hcm-leave-form="add"]');
    form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
    await flushPromises();

    const postCall = fetchMock.mock.calls.find(([, opts]) => String(opts?.method || '').toLowerCase() === 'post');
    expect(postCall).toBeTruthy();
    expect(postCall[0]).toBe('/v1/hcm/leave-requests');
    expect(JSON.parse(postCall[1].body)).toMatchObject({
      userId: 42,
      leaveType: 'Annual Leave',
      dateFrom: '2026-04-14',
      dateTo: '2026-04-15',
      days: 2,
    });
  });

  it('shows leave actions only for backend-allowed ownership and pending states', async () => {
    document.body.innerHTML = `
      <table><tbody data-hcm-leaves-admin-body></tbody></table>
      <div data-hcm-leaves-pagination style="display:none;">
        <span data-hcm-leaves-page-info></span>
        <button data-hcm-leaves-prev></button>
        <button data-hcm-leaves-next></button>
      </div>
      <div data-hcm-leaves-filters></div>
    `;

    const fetchMock = vi.mocked(fetch);
    fetchMock.mockImplementation((url) => {
      const urlString = String(url);

      if (urlString === '/v1/identity/auth/me') {
        return jsonResponse({
          success: true,
          data: {
            id: 10,
            permissions: {
              'leave.view': true,
            },
          },
        });
      }

      if (urlString.startsWith('/v1/hcm/leave-requests?')) {
        return jsonResponse({
          success: true,
          data: [
            {
              id: 1,
              userId: 10,
              employeeName: 'Admin Self',
              email: 'self@example.com',
              leaveType: 'Annual Leave',
              dateFrom: '2026-04-10',
              dateTo: '2026-04-10',
              days: 1,
              status: 'approved',
              notes: '',
            },
            {
              id: 2,
              userId: 42,
              employeeName: 'Other Pending',
              email: 'other@example.com',
              leaveType: 'Annual Leave',
              dateFrom: '2026-04-11',
              dateTo: '2026-04-11',
              days: 1,
              status: 'pending',
              notes: '',
            },
          ],
          meta: {
            summary: { totalRequests: 2, approved: 1, declined: 0, pending: 1 },
            holidays: [],
            pagination: { total: 2, page: 1, perPage: 20, totalPages: 1 },
          },
        });
      }

      if (urlString === '/v1/hcm/leave-type-options') {
        return jsonResponse({ success: true, data: [] });
      }

      throw new Error(`Unhandled fetch: ${urlString}`);
    });

    await loadLeaveModule('/leaves');

    const rows = Array.from(document.querySelectorAll('[data-hcm-leaves-admin-body] tr'));
    expect(rows).toHaveLength(2);
    expect(rows[0].querySelector('[data-hcm-leave-edit]')).toBeNull();
    expect(rows[0].querySelector('[data-hcm-leave-delete]')).toBeNull();
    expect(rows[1].querySelector('[data-hcm-leave-edit]')).toBeTruthy();
    expect(rows[1].querySelector('[data-hcm-leave-delete]')).toBeNull();
  });
});