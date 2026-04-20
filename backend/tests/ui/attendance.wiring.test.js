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
      this.visible = false;
      instances.set(element, this);
    }

    show() {
      this.visible = true;
    }

    hide() {
      this.visible = false;
    }

    static getOrCreateInstance(element) {
      return instances.get(element) || new ModalStub(element);
    }

    static getInstance(element) {
      return instances.get(element) || null;
    }
  }

  window.bootstrap = { Modal: ModalStub };
}

async function loadAttendanceModule(pathname) {
  vi.resetModules();
  window.history.replaceState({}, '', pathname);
  await import('../../../frontend/resources/js/attendance-data.js');
  await flushPromises();
}

describe('Attendance UI wiring', () => {
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
    window.L = undefined;
    window.jQuery = undefined;
    installBootstrapModalStub();
  });

  it('submits attendance admin edits with numeric userId', async () => {
    document.body.innerHTML = `
      <input type="date" data-attendance-admin-date value="2026-04-20">
      <select data-attendance-admin-filter-department><option value=""></option></select>
      <select data-attendance-admin-filter-status><option value=""></option></select>
      <select data-attendance-admin-sort><option value="name_asc">name_asc</option></select>
      <div data-attendance-admin-heading></div>
      <div data-attendance-admin-subtitle></div>
      <div data-attendance-admin-present-quick></div>
      <div data-attendance-admin-absentees></div>
      <div data-attendance-admin-stat="present"></div>
      <div data-attendance-admin-stat="late"></div>
      <div data-attendance-admin-stat="uninformed"></div>
      <div data-attendance-admin-stat="permission"></div>
      <div data-attendance-admin-stat="absent"></div>
      <table><tbody data-attendance-admin-body></tbody></table>
      <div data-attendance-admin-pagination style="display:none;">
        <span data-attendance-admin-page-info></span>
        <button data-attendance-admin-prev></button>
        <button data-attendance-admin-next></button>
      </div>
      <div id="arcav_edit_attendance"></div>
      <form data-attendance-admin-edit-form>
        <input data-attendance-admin-field="userId" value="">
        <input data-attendance-admin-field="workDate" value="">
        <input data-attendance-admin-field="workDateInput" value="">
        <input data-attendance-admin-field="checkInTime" value="">
        <input data-attendance-admin-field="checkOutTime" value="">
        <input data-attendance-admin-field="breakMinutes" value="0">
        <input data-attendance-admin-field="lateMinutes" value="0">
      </form>
      <div data-attendance-admin-edit-employee></div>
    `;

    const fetchMock = vi.mocked(fetch);
    fetchMock.mockImplementation((url, options = {}) => {
      if (String(url).startsWith('/v1/hcm/attendance/admin?')) {
        return jsonResponse({
          success: true,
          data: [
            {
              userId: 42,
              employeeName: 'Ayu Admin',
              department: 'Ops',
              checkIn: '08:55',
              checkInTime24: '08:55',
              checkOut: '17:10',
              checkOutTime24: '17:10',
              checkInLocation: 'HQ',
              checkOutLocation: 'HQ',
              break: '60 min',
              breakMinutesRaw: 60,
              late: '0 min',
              lateMinutesRaw: 0,
              productionLabel: '8.25h',
              productionBadgeClass: 'success',
              statusLabel: 'Present',
              statusBadgeClass: 'success',
              statusKey: 'present',
              correctionStatus: 'none',
              correctionReason: '',
              correctionRequestedAtLabel: '',
              correctionRequestedAt: '',
              team: 'Ops',
              initial: 'A',
              selfieStatusLabel: 'Available',
              selfieBadgeClass: 'success',
              selfieDownloadUrl: '/v1/hcm/attendance/admin/records/10/selfie/download',
            },
          ],
          meta: {
            summary: {
              totalEmployees: 1,
              present: 1,
              absent: 0,
              lateLogin: 0,
              uninformed: 0,
              permission: 0,
            },
            filters: { departments: ['Ops'] },
            pagination: { total: 1, page: 1, perPage: 50, totalPages: 1 },
          },
        });
      }

      if (url === '/v1/hcm/attendance/admin/record') {
        return jsonResponse({ success: true, data: { id: 10 } });
      }

      throw new Error(`Unhandled fetch: ${String(url)} ${options.method || 'GET'}`);
    });

    await loadAttendanceModule('/attendance-admin');

    const editTrigger = document.querySelector('[data-attendance-admin-open-edit]');
    expect(editTrigger).toBeTruthy();
    editTrigger.dispatchEvent(new MouseEvent('click', { bubbles: true }));

    const form = document.querySelector('[data-attendance-admin-edit-form]');
    form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
    await flushPromises();

    const putCall = fetchMock.mock.calls.find(([, options]) => options?.method === 'PUT');
    expect(putCall).toBeTruthy();
    expect(putCall[0]).toBe('/v1/hcm/attendance/admin/record');
    expect(JSON.parse(putCall[1].body)).toMatchObject({
      userId: 42,
      workDate: '2026-04-20',
      checkInTime: '08:55',
      checkOutTime: '17:10',
      breakMinutes: 60,
      lateMinutes: 0,
    });
  });

  it('submits schedule timing edits with numeric shiftId', async () => {
    document.body.innerHTML = `
      <input data-schedule-timing-search value="">
      <select data-schedule-timing-sort><option value="name_asc">name_asc</option></select>
      <table><tbody data-schedule-timing-body></tbody></table>
      <div data-schedule-timing-pagination style="display:none;">
        <span data-schedule-timing-page-info></span>
        <button data-schedule-timing-prev></button>
        <button data-schedule-timing-next></button>
      </div>
      <div id="arcav_schedule_timing_edit"></div>
      <form data-schedule-timing-edit-form>
        <p data-st-edit-employee></p>
        <input data-st-edit-user-id value="">
        <select data-st-edit-shift></select>
        <input data-st-edit-start value="09:00">
        <input data-st-edit-end value="18:00">
        <button type="button" data-st-edit-reset class="d-none"></button>
        <button type="submit" data-st-edit-submit></button>
      </form>
    `;

    const fetchMock = vi.mocked(fetch);
    fetchMock.mockImplementation((url, options = {}) => {
      if (String(url).startsWith('/v1/hcm/schedule-timing?')) {
        return jsonResponse({
          success: true,
          data: [
            {
              userId: 91,
              name: 'Bima Shift',
              jobTitle: 'Operator',
              availableTimings: '09:00 - 18:00',
              startMinutes: 540,
              endMinutes: 1080,
              shiftId: '',
              source: 'manual',
            },
          ],
          meta: { pagination: { total: 1, page: 1, perPage: 50, totalPages: 1 } },
        });
      }

      if (url === '/v1/hcm/shifts') {
        return jsonResponse({
          success: true,
          data: [
            { id: 7, name: 'Morning', slotLabel: '09:00 - 18:00', startTime: '09:00', endTime: '18:00', isActive: true },
          ],
        });
      }

      if (url === '/v1/hcm/schedule-timing/91') {
        return jsonResponse({ success: true, data: { userId: 91 } });
      }

      throw new Error(`Unhandled fetch: ${String(url)} ${options.method || 'GET'}`);
    });

    await loadAttendanceModule('/schedule-timing');

    const editTrigger = document.querySelector('[data-schedule-timing-edit]');
    expect(editTrigger).toBeTruthy();
    editTrigger.dispatchEvent(new MouseEvent('click', { bubbles: true }));
    await flushPromises();

    const shiftSelect = document.querySelector('[data-st-edit-shift]');
    shiftSelect.value = '7';
    shiftSelect.dispatchEvent(new Event('change', { bubbles: true }));

    const form = document.querySelector('[data-schedule-timing-edit-form]');
    form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
    await flushPromises();

    const putCall = fetchMock.mock.calls.find(([, options]) => options?.method === 'PUT');
    expect(putCall).toBeTruthy();
    expect(putCall[0]).toBe('/v1/hcm/schedule-timing/91');
    expect(JSON.parse(putCall[1].body)).toEqual({ shiftId: 7 });
  });

  it('locks selfie button until employee has started attendance', async () => {
    document.body.innerHTML = `
      <button data-attendance-me-selfie-btn></button>
      <button data-attendance-me-punch-btn></button>
      <button data-attendance-me-break-btn></button>
      <button data-attendance-me-request-correction class="d-none"></button>
      <div data-attendance-me-break-indicator class="d-none"><span data-attendance-me-break-duration></span></div>
      <div data-attendance-me-alert class="d-none"></div>
      <div data-attendance-me-map-hint></div>
      <div data-attendance-me-clockin></div>
      <div data-attendance-me-clockout></div>
      <div data-attendance-me-workinghours></div>
      <div data-attendance-me-status></div>
      <div data-attendance-me-location></div>
      <div data-attendance-me-summary-total></div>
      <div data-attendance-me-summary-productive></div>
      <div data-attendance-me-summary-break></div>
      <div data-attendance-me-summary-ot></div>
      <div data-attendance-stat-today-hours></div>
      <div data-attendance-stat-today-target></div>
      <div data-attendance-stat-week-hours></div>
      <div data-attendance-stat-week-target></div>
      <div data-attendance-stat-month-hours></div>
      <div data-attendance-stat-month-target></div>
      <div data-attendance-stat-ot-hours></div>
      <div data-attendance-stat-ot-target></div>
      <div data-attendance-me-stat-foot-today></div>
      <div data-attendance-me-stat-foot-week></div>
      <div data-attendance-me-stat-foot-month></div>
      <div data-attendance-me-stat-foot-ot></div>
      <table><tbody data-attendance-me-history-body></tbody></table>
    `;

    const fetchMock = vi.mocked(fetch);
    fetchMock.mockImplementation((url) => {
      if (url === '/v1/hcm/attendance/me/today') {
        return jsonResponse({
          success: true,
          data: {
            punchState: 'out',
            punchButtonLabel: 'Punch In',
            punchButtonDisabled: false,
            breakButtonLabel: 'Start Break',
            breakButtonDisabled: true,
            breakInProgress: false,
            needsReview: false,
            correctionStatus: 'none',
            summaryTotalWorking: '0h',
            summaryProductive: '0h',
            summaryBreak: '0m',
            summaryOvertime: '0h',
            userName: 'Cici Employee',
            productionProgressPercent: 0,
          },
        });
      }

      if (url === '/v1/hcm/attendance/me/stats') {
        return jsonResponse({ success: true, data: {} });
      }

      if (url === '/v1/hcm/attendance/me/history?days=30') {
        return jsonResponse({ success: true, data: [] });
      }

      throw new Error(`Unhandled fetch: ${String(url)}`);
    });

    await loadAttendanceModule('/attendance-employee');

    const selfieBtn = document.querySelector('[data-attendance-me-selfie-btn]');
    expect(selfieBtn.getAttribute('data-arcav-selfie-allowed')).toBe('0');
    expect(selfieBtn.getAttribute('title')).toContain('punch masuk');
  });

  it('shows an explicit archive error when snapshot is not an attendance report', async () => {
    document.body.innerHTML = `
      <select data-attendance-report-source>
        <option value="live">Live</option>
        <option value="archive" selected>Archive</option>
      </select>
      <div data-attendance-report-snapshot-wrap></div>
      <input data-attendance-report-snapshot-id value="77">
      <span data-attendance-report-source-badge></span>
      <input type="date" data-attendance-report-date value="2026-04-20">
      <button data-attendance-report-load></button>
      <select data-attendance-report-filter-department><option value=""></option></select>
      <select data-attendance-report-filter-status><option value=""></option></select>
      <select data-attendance-report-sort><option value="name_asc">name_asc</option></select>
      <div data-attendance-report-stat-working></div>
      <div data-attendance-report-stat-leave></div>
      <div data-attendance-report-stat-holiday></div>
      <div data-attendance-report-stat-halfday></div>
      <div data-attendance-report-stat-foot-working></div>
      <div data-attendance-report-stat-foot-leave></div>
      <div data-attendance-report-stat-foot-holiday></div>
      <div data-attendance-report-stat-foot-halfday></div>
      <div id="attendance-report-chart"></div>
      <table><tbody data-attendance-report-body></tbody></table>
    `;

    const fetchMock = vi.mocked(fetch);
    fetchMock.mockImplementation((url) => {
      if (url === '/v1/hcm/reports/snapshots/77') {
        return jsonResponse({
          success: true,
          data: {
            id: 77,
            reportType: 'payroll',
            status: 'completed',
            periodEnd: '2026-04-20',
            dataByModule: {},
          },
        });
      }

      throw new Error(`Unhandled fetch: ${String(url)}`);
    });

    await loadAttendanceModule('/attendance-report');

    expect(document.querySelector('[data-attendance-report-body]').textContent).toContain('bukan report attendance');
    expect(document.querySelector('#attendance-report-chart').textContent).toContain('Chart library not available');
  });

  it('blocks timesheet fetch when date range is reversed', async () => {
    document.body.innerHTML = `
      <input type="date" data-timesheets-date-from value="2026-04-30">
      <input type="date" data-timesheets-date-to value="2026-04-01">
      <select data-timesheets-filter-project><option value=""></option></select>
      <select data-timesheets-sort><option value="date_desc">date_desc</option></select>
      <table><tbody data-timesheets-body></tbody></table>
      <div data-timesheets-pagination style="display:none;">
        <span data-timesheets-page-info></span>
        <button data-timesheets-prev></button>
        <button data-timesheets-next></button>
      </div>
    `;

    const fetchMock = vi.mocked(fetch);

    await loadAttendanceModule('/timesheets');

    expect(fetchMock).not.toHaveBeenCalled();
    expect(document.querySelector('[data-timesheets-body]').textContent).toContain('Date to harus sama atau setelah Date from');
  });
});
