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

  it('generates smart planner and renders recommendation summary', async () => {
    document.body.innerHTML = `
      <input data-schedule-timing-search value="">
      <select data-schedule-timing-sort><option value="name_asc">name_asc</option></select>
      <table><tbody data-schedule-timing-body></tbody></table>
      <div data-schedule-timing-pagination style="display:none;">
        <span data-schedule-timing-page-info></span>
        <button data-schedule-timing-prev></button>
        <button data-schedule-timing-next></button>
      </div>
      <form data-smart-planner-form>
        <select data-smart-planner-shift-category>
          <option value="office_hour" selected>office</option>
          <option value="shifting_24h">shift</option>
          <option value="hybrid">hybrid</option>
        </select>
        <div data-smart-planner-mode-hint></div>
        <input type="date" data-smart-planner-week-start value="2026-04-20">
        <input data-smart-planner-max-work-days value="5">
        <input data-smart-planner-min-days-off value="2">
        <div data-smart-planner-field="rest-rule"><input data-smart-planner-min-rest value="12"></div>
        <div data-smart-planner-field="night-rule"><input data-smart-planner-max-night value="3"></div>
        <button type="submit" data-smart-planner-submit>Generate</button>
      </form>
      <div class="d-none" data-smart-planner-feedback></div>
      <div class="d-none" data-smart-planner-result>
        <span data-smart-planner-validation></span>
        <span data-smart-planner-fairness></span>
        <span data-smart-planner-fatigue></span>
        <span data-smart-planner-unmet></span>
        <p data-smart-planner-explanation></p>
        <ul data-smart-planner-violations></ul>
        <ul data-smart-planner-suggestions></ul>
      </div>
    `;

    const fetchMock = vi.mocked(fetch);
    fetchMock.mockImplementation((url, options = {}) => {
      if (String(url).startsWith('/v1/hcm/schedule-timing?')) {
        return jsonResponse({
          success: true,
          data: [],
          meta: { pagination: { total: 0, page: 1, perPage: 50, totalPages: 1 } },
        });
      }

      if (url === '/v1/hcm/smart-attendance-shifting/settings') {
        return jsonResponse({
          success: true,
          data: {
            defaultRules: {
              max_work_days_per_week: 5,
              min_days_off_per_week: 2,
              min_rest_hours_between_shifts: 12,
              max_consecutive_night_shifts: 3,
            },
            forbiddenTransitions: ['night:morning'],
            transitionCatalog: ['night:morning'],
          },
        });
      }

      if (url === '/v1/hcm/shifts') {
        return jsonResponse({ success: true, data: [] });
      }

      if (url === '/v1/hcm/smart-attendance-shifting/generate') {
        return jsonResponse({
          success: true,
          data: {
            schedule_generation: {
              validation_status: 'valid',
              weekly_schedule: [],
              violations: [],
              unmet_coverage: [{ date: '2026-04-20', shift_id: '7', required: 2, assigned: 1 }],
            },
            attendance_analysis: {
              employee_summaries: [],
              flags: [],
            },
            recommendation: {
              fairness_score: 91.5,
              fatigue_risk_score: 32.0,
              improvement_suggestions: [
                {
                  title: 'Rebalance night shift distribution',
                  reason: 'Night shifts are unevenly distributed.',
                  data: { fairness_score: 91.5, employee_count: 5 },
                },
              ],
            },
            explanation: 'Schedule generated successfully.',
          },
        });
      }

      throw new Error(`Unhandled fetch: ${String(url)} ${options.method || 'GET'}`);
    });

    await loadAttendanceModule('/schedule-timing');

    const form = document.querySelector('[data-smart-planner-form]');
    form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
    await flushPromises();

    const postCall = fetchMock.mock.calls.find(([, options]) => options?.method === 'POST');
    expect(postCall).toBeTruthy();
    expect(postCall[0]).toBe('/v1/hcm/smart-attendance-shifting/generate');
    expect(JSON.parse(postCall[1].body)).toMatchObject({
      shiftCategory: 'office_hour',
      weekStart: '2026-04-20',
      rules: {
        max_work_days_per_week: 5,
        min_days_off_per_week: 2,
        min_rest_hours_between_shifts: 12,
        max_consecutive_night_shifts: 1,
      },
    });

    expect(document.querySelector('[data-smart-planner-result]')?.classList.contains('d-none')).toBe(false);
    expect(document.querySelector('[data-smart-planner-validation]')?.textContent).toContain('VALID');
    expect(document.querySelector('[data-smart-planner-fairness]')?.textContent).toContain('91.5');
    expect(document.querySelector('[data-smart-planner-explanation]')?.textContent).toContain('Schedule generated successfully');
    expect(document.querySelector('[data-smart-planner-suggestions]')?.textContent).toContain('Distribusi shift malam tidak merata');
  });

  it('loads all employee pages when planner scope is all employees', async () => {
    document.body.innerHTML = `
      <input data-schedule-timing-search value="">
      <select data-schedule-timing-sort><option value="name_asc">name_asc</option></select>
      <table><tbody data-schedule-timing-body></tbody></table>
      <div data-schedule-timing-pagination style="display:none;">
        <span data-schedule-timing-page-info></span>
        <button data-schedule-timing-prev></button>
        <button data-schedule-timing-next></button>
      </div>
      <form data-smart-planner-form>
        <select data-smart-planner-shift-category><option value="shifting_24h" selected>shift</option></select>
        <select data-smart-planner-scope><option value="all" selected>all</option></select>
        <div data-smart-planner-scope-hint></div>
        <small data-smart-planner-scope-meta></small>
        <div data-smart-planner-field="department" class="d-none"><select data-smart-planner-department><option value="">Pilih departemen</option></select></div>
        <div data-smart-planner-field="custom-ids" class="d-none"><input data-smart-planner-custom-ids value=""></div>
        <div data-smart-planner-mode-hint></div>
        <input type="date" data-smart-planner-week-start value="2026-04-20">
        <input data-smart-planner-max-work-days value="5">
        <input data-smart-planner-min-days-off value="2">
        <div data-smart-planner-field="rest-rule"><input data-smart-planner-min-rest value="12"></div>
        <div data-smart-planner-field="night-rule"><input data-smart-planner-max-night value="3"></div>
        <button type="submit" data-smart-planner-submit>Generate</button>
      </form>
      <div class="d-none" data-smart-planner-feedback></div>
      <div class="d-none" data-smart-planner-result>
        <span data-smart-planner-validation></span>
        <span data-smart-planner-fairness></span>
        <span data-smart-planner-fatigue></span>
        <span data-smart-planner-unmet></span>
        <p data-smart-planner-explanation></p>
        <ul data-smart-planner-violations></ul>
        <ul data-smart-planner-suggestions></ul>
      </div>
    `;

    const fetchMock = vi.mocked(fetch);
    fetchMock.mockImplementation((url, options = {}) => {
      if (String(url).startsWith('/v1/hcm/schedule-timing?')) {
        return jsonResponse({ success: true, data: [], meta: { pagination: { total: 0, page: 1, perPage: 50, totalPages: 1 } } });
      }
      if (url === '/v1/hcm/smart-attendance-shifting/settings') {
        return jsonResponse({
          success: true,
          data: {
            defaultRules: {
              max_work_days_per_week: 5,
              min_days_off_per_week: 2,
              min_rest_hours_between_shifts: 12,
              max_consecutive_night_shifts: 3,
            },
            forbiddenTransitions: ['night:morning'],
            transitionCatalog: ['night:morning'],
          },
        });
      }
      if (url === '/v1/hcm/shifts') {
        return jsonResponse({ success: true, data: [] });
      }
      if (url === '/v1/hcm/employees?perPage=100&page=1') {
        return jsonResponse({ success: true, data: [{ userId: 91 }, { userId: 92 }], meta: { page: 1, perPage: 100, total: 3 } });
      }
      if (url === '/v1/hcm/employees?perPage=100&page=2') {
        return jsonResponse({ success: true, data: [{ userId: 93 }], meta: { page: 2, perPage: 100, total: 3 } });
      }
      if (url === '/v1/hcm/smart-attendance-shifting/generate') {
        return jsonResponse({
          success: true,
          data: {
            schedule_generation: { validation_status: 'valid', weekly_schedule: [], violations: [], unmet_coverage: [] },
            attendance_analysis: { employee_summaries: [], flags: [] },
            recommendation: { fairness_score: 88, fatigue_risk_score: 20, improvement_suggestions: [] },
            explanation: 'ok',
          },
        });
      }

      throw new Error(`Unhandled fetch: ${String(url)} ${options.method || 'GET'}`);
    });

    await loadAttendanceModule('/schedule-timing');

    const form = document.querySelector('[data-smart-planner-form]');
    form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
    await flushPromises();

    const postCall = fetchMock.mock.calls.find((entry) => entry[0] === '/v1/hcm/smart-attendance-shifting/generate');
    expect(postCall).toBeTruthy();
    expect(JSON.parse(postCall[1].body).employeeIds).toEqual([91, 92, 93]);
  });

  it('generates planner in weekly batches until end of year', async () => {
    document.body.innerHTML = `
      <input data-schedule-timing-search value="">
      <select data-schedule-timing-sort><option value="name_asc">name_asc</option></select>
      <table><tbody data-schedule-timing-body></tbody></table>
      <div data-schedule-timing-pagination style="display:none;">
        <span data-schedule-timing-page-info></span>
        <button data-schedule-timing-prev></button>
        <button data-schedule-timing-next></button>
      </div>
      <form data-smart-planner-form>
        <select data-smart-planner-shift-category>
          <option value="office_hour">office</option>
          <option value="shifting_24h" selected>shift</option>
          <option value="hybrid">hybrid</option>
        </select>
        <select data-smart-planner-horizon>
          <option value="single_week">single</option>
          <option value="end_of_year" selected>end-year</option>
        </select>
        <div data-smart-planner-horizon-hint></div>
        <div data-smart-planner-field="horizon-end-date"><input type="date" data-smart-planner-end-date value="2026-12-31"></div>
        <div data-smart-planner-mode-hint></div>
        <input type="date" data-smart-planner-week-start value="2026-12-22">
        <input data-smart-planner-max-work-days value="5">
        <input data-smart-planner-min-days-off value="2">
        <div data-smart-planner-field="rest-rule"><input data-smart-planner-min-rest value="12"></div>
        <div data-smart-planner-field="night-rule"><input data-smart-planner-max-night value="3"></div>
        <button type="submit" data-smart-planner-submit>Generate</button>
      </form>
      <div class="d-none" data-smart-planner-feedback></div>
      <div class="d-none" data-smart-planner-result>
        <span data-smart-planner-validation></span>
        <span data-smart-planner-fairness></span>
        <span data-smart-planner-fatigue></span>
        <span data-smart-planner-unmet></span>
        <p data-smart-planner-explanation></p>
        <ul data-smart-planner-violations></ul>
        <ul data-smart-planner-suggestions></ul>
      </div>
    `;

    const fetchMock = vi.mocked(fetch);
    fetchMock.mockImplementation((url, options = {}) => {
      if (String(url).startsWith('/v1/hcm/schedule-timing?')) {
        return jsonResponse({
          success: true,
          data: [],
          meta: { pagination: { total: 0, page: 1, perPage: 50, totalPages: 1 } },
        });
      }

      if (url === '/v1/hcm/smart-attendance-shifting/settings') {
        return jsonResponse({
          success: true,
          data: {
            defaultRules: {
              max_work_days_per_week: 5,
              min_days_off_per_week: 2,
              min_rest_hours_between_shifts: 12,
              max_consecutive_night_shifts: 3,
            },
            forbiddenTransitions: ['night:morning'],
            transitionCatalog: ['night:morning'],
          },
        });
      }

      if (url === '/v1/hcm/shifts') {
        return jsonResponse({ success: true, data: [] });
      }

      if (url === '/v1/hcm/smart-attendance-shifting/generate') {
        const payload = JSON.parse(options.body || '{}');
        if (payload.weekStart === '2026-12-22') {
          return jsonResponse({
            success: true,
            data: {
              schedule_generation: {
                validation_status: 'valid',
                weekly_schedule: [
                  {
                    employee_id: '91',
                    employee_name: 'Agent A',
                    assignments: [
                      { date: '2026-12-22', shift_id: '7', start_time: '07:00', end_time: '15:00', cross_day: false },
                    ],
                  },
                ],
                violations: [],
                unmet_coverage: [],
              },
              attendance_analysis: {
                employee_summaries: [
                  { employee_id: '91', total_work_days: 1, late_count: 0, early_leave_count: 0, absent_count: 0, overtime_minutes: 0, compliance_score: 100 },
                ],
                flags: [],
              },
              recommendation: {
                fairness_score: 90,
                fatigue_risk_score: 30,
                improvement_suggestions: [
                  { title: 'Keep coverage stable', reason: 'Coverage good this week.' },
                ],
              },
              explanation: 'Week A generated.',
            },
          });
        }

        if (payload.weekStart === '2026-12-29') {
          return jsonResponse({
            success: true,
            data: {
              schedule_generation: {
                validation_status: 'invalid',
                weekly_schedule: [
                  {
                    employee_id: '91',
                    employee_name: 'Agent A',
                    assignments: [
                      { date: '2026-12-29', shift_id: '8', start_time: '15:00', end_time: '23:00', cross_day: false },
                    ],
                  },
                ],
                violations: [{ code: 'COVERAGE_UNMET', message: 'Need more headcount.' }],
                unmet_coverage: [{ date: '2026-12-29', shift_id: '8', required: 2, assigned: 1 }],
              },
              attendance_analysis: {
                employee_summaries: [
                  { employee_id: '91', total_work_days: 1, late_count: 0, early_leave_count: 0, absent_count: 0, overtime_minutes: 0, compliance_score: 95 },
                ],
                flags: [],
              },
              recommendation: {
                fairness_score: 80,
                fatigue_risk_score: 40,
                improvement_suggestions: [
                  { title: 'Add backup agent', reason: 'Coverage gap on year-end week.' },
                ],
              },
              explanation: 'Week B generated.',
            },
          });
        }
      }

      throw new Error(`Unhandled fetch: ${String(url)} ${options.method || 'GET'}`);
    });

    await loadAttendanceModule('/schedule-timing');

    const form = document.querySelector('[data-smart-planner-form]');
    form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
    await flushPromises();
    await flushPromises();

    const postCalls = fetchMock.mock.calls.filter(([, options]) => options?.method === 'POST');
    expect(postCalls).toHaveLength(2);
    expect(JSON.parse(postCalls[0][1].body)).toMatchObject({ weekStart: '2026-12-22' });
    expect(JSON.parse(postCalls[1][1].body)).toMatchObject({ weekStart: '2026-12-29' });

    expect(document.querySelector('[data-smart-planner-feedback]')?.textContent).toContain('2 minggu');
    expect(document.querySelector('[data-smart-planner-result]')?.classList.contains('d-none')).toBe(false);
    expect(document.querySelector('[data-smart-planner-validation]')?.textContent).toContain('INVALID');
    expect(document.querySelector('[data-smart-planner-explanation]')?.textContent).toContain('Batch planner selesai untuk 2 minggu');
    expect(document.querySelector('[data-smart-planner-suggestions]')?.textContent).toContain('Add backup agent');
  });

  it('publishes dominant shifts from planner draft into schedule timing', async () => {
    document.body.innerHTML = `
      <input data-schedule-timing-search value="">
      <select data-schedule-timing-sort><option value="name_asc">name_asc</option></select>
      <table><tbody data-schedule-timing-body></tbody></table>
      <div data-schedule-timing-pagination style="display:none;">
        <span data-schedule-timing-page-info></span>
        <button data-schedule-timing-prev></button>
        <button data-schedule-timing-next></button>
      </div>
      <form data-smart-planner-form>
        <select data-smart-planner-shift-category>
          <option value="office_hour">office</option>
          <option value="shifting_24h" selected>shift</option>
          <option value="hybrid">hybrid</option>
        </select>
        <select data-smart-planner-scope><option value="department" selected>department</option></select>
        <div data-smart-planner-scope-hint></div>
        <small data-smart-planner-scope-meta></small>
        <div data-smart-planner-field="department"><select data-smart-planner-department><option value="11" selected>Customer Care</option></select></div>
        <div data-smart-planner-field="custom-ids" class="d-none"><input data-smart-planner-custom-ids value=""></div>
        <div data-smart-planner-mode-hint></div>
        <input type="date" data-smart-planner-week-start value="2026-04-20">
        <input data-smart-planner-max-work-days value="5">
        <input data-smart-planner-min-days-off value="2">
        <div data-smart-planner-field="rest-rule"><input data-smart-planner-min-rest value="12"></div>
        <div data-smart-planner-field="night-rule"><input data-smart-planner-max-night value="3"></div>
        <button type="submit" data-smart-planner-submit>Generate</button>
      </form>
      <div class="d-none" data-smart-planner-feedback></div>
      <div class="d-none" data-smart-planner-result>
        <span data-smart-planner-validation></span>
        <span data-smart-planner-fairness></span>
        <span data-smart-planner-fatigue></span>
        <span data-smart-planner-unmet></span>
        <p data-smart-planner-explanation></p>
        <ul data-smart-planner-violations></ul>
        <ul data-smart-planner-suggestions></ul>
        <small data-smart-planner-assignment-meta></small>
        <table><tbody data-smart-planner-assignment-body></tbody></table>
        <small data-smart-planner-apply-meta></small>
        <button type="button" data-smart-planner-apply-dominant disabled>Apply</button>
      </div>
    `;

    const fetchMock = vi.mocked(fetch);
    fetchMock.mockImplementation((url, options = {}) => {
      if (String(url).startsWith('/v1/hcm/schedule-timing?')) {
        return jsonResponse({
          success: true,
          data: [],
          meta: { pagination: { total: 0, page: 1, perPage: 50, totalPages: 1 } },
        });
      }

      if (url === '/v1/hcm/smart-attendance-shifting/settings') {
        return jsonResponse({
          success: true,
          data: {
            defaultRules: {
              max_work_days_per_week: 5,
              min_days_off_per_week: 2,
              min_rest_hours_between_shifts: 12,
              max_consecutive_night_shifts: 3,
            },
            forbiddenTransitions: ['night:morning'],
            transitionCatalog: ['night:morning'],
          },
        });
      }

      if (url === '/v1/hcm/shifts') {
        return jsonResponse({
          success: true,
          data: [
            { id: 7, name: 'Morning', startTime: '07:00', endTime: '15:00', slotLabel: '07:00 - 15:00', isActive: true },
            { id: 9, name: 'Night', startTime: '23:00', endTime: '07:00', slotLabel: '23:00 - 07:00 (+1d)', isActive: true },
          ],
        });
      }

      if (url === '/v1/hcm/employees?perPage=100&page=1') {
        return jsonResponse({
          success: true,
          data: [
            { userId: 91, departmentId: 11, departmentName: 'Customer Care' },
            { userId: 92, departmentId: 11, departmentName: 'Customer Care' },
          ],
          meta: { page: 1, perPage: 100, total: 2 },
        });
      }

      if (url === '/v1/hcm/smart-attendance-shifting/generate') {
        return jsonResponse({
          success: true,
          data: {
            schedule_generation: {
              validation_status: 'valid',
              weekly_schedule: [
                {
                  employee_id: '91',
                  employee_name: 'Agent A',
                  assignments: [
                    { date: '2026-04-20', shift_id: '7', start_time: '07:00', end_time: '15:00', cross_day: false },
                    { date: '2026-04-21', shift_id: '7', start_time: '07:00', end_time: '15:00', cross_day: false },
                    { date: '2026-04-22', shift_id: '8', start_time: '15:00', end_time: '23:00', cross_day: false },
                  ],
                },
                {
                  employee_id: '92',
                  employee_name: 'Agent B',
                  assignments: [
                    { date: '2026-04-20', shift_id: '9', start_time: '23:00', end_time: '07:00', cross_day: true },
                    { date: '2026-04-21', shift_id: '9', start_time: '23:00', end_time: '07:00', cross_day: true },
                    { date: '2026-04-22', shift_id: 'OFF', start_time: null, end_time: null, cross_day: false },
                  ],
                },
              ],
              violations: [],
              unmet_coverage: [],
            },
            attendance_analysis: {
              employee_summaries: [],
              flags: [],
            },
            recommendation: {
              fairness_score: 88,
              fatigue_risk_score: 36,
              improvement_suggestions: [],
            },
            explanation: 'ok',
          },
        });
      }

      if (url === '/v1/hcm/schedule-timing/91' || url === '/v1/hcm/schedule-timing/92') {
        return jsonResponse({ success: true, data: { id: 1 } });
      }

      throw new Error(`Unhandled fetch: ${String(url)} ${options.method || 'GET'}`);
    });

    await loadAttendanceModule('/schedule-timing');

    const form = document.querySelector('[data-smart-planner-form]');
    form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
    await flushPromises();

    const applyBtn = document.querySelector('[data-smart-planner-apply-dominant]');
    expect(applyBtn.disabled).toBe(false);

    applyBtn.click();
    await flushPromises();
    await flushPromises();

    const putCalls = fetchMock.mock.calls.filter(([, options]) => options?.method === 'PUT');
    expect(putCalls).toHaveLength(2);
    expect(putCalls[0][0]).toBe('/v1/hcm/schedule-timing/91');
    expect(putCalls[1][0]).toBe('/v1/hcm/schedule-timing/92');
    expect(JSON.parse(putCalls[0][1].body)).toEqual({ shiftId: 7 });
    expect(JSON.parse(putCalls[1][1].body)).toEqual({ shiftId: 9 });

    expect(document.querySelector('[data-smart-planner-feedback]')?.textContent).toContain('berhasil untuk 2 user');
  });

  it('publishes daily roster per date from planner draft', async () => {
    document.body.innerHTML = `
      <input data-schedule-timing-search value="">
      <select data-schedule-timing-sort><option value="name_asc">name_asc</option></select>
      <table><tbody data-schedule-timing-body></tbody></table>
      <div data-schedule-timing-pagination style="display:none;">
        <span data-schedule-timing-page-info></span>
        <button data-schedule-timing-prev></button>
        <button data-schedule-timing-next></button>
      </div>
      <form data-smart-planner-form>
        <select data-smart-planner-shift-category><option value="shifting_24h" selected>shift</option></select>
        <select data-smart-planner-scope><option value="all" selected>all</option></select>
        <div data-smart-planner-scope-hint></div>
        <small data-smart-planner-scope-meta></small>
        <div data-smart-planner-field="team-query" class="d-none"><input data-smart-planner-team-query value=""></div>
        <div data-smart-planner-field="custom-ids" class="d-none"><input data-smart-planner-custom-ids value=""></div>
        <div data-smart-planner-mode-hint></div>
        <input type="date" data-smart-planner-week-start value="2026-04-20">
        <input data-smart-planner-max-work-days value="5">
        <input data-smart-planner-min-days-off value="2">
        <div data-smart-planner-field="rest-rule"><input data-smart-planner-min-rest value="12"></div>
        <div data-smart-planner-field="night-rule"><input data-smart-planner-max-night value="3"></div>
        <button type="button" data-smart-planner-save-settings>save</button>
        <div data-smart-planner-transition-matrix><label><input type="checkbox" data-smart-planner-transition-key="night:morning" checked></label></div>
        <button type="submit" data-smart-planner-submit>Generate</button>
      </form>
      <div class="d-none" data-smart-planner-feedback></div>
      <div class="d-none" data-smart-planner-result>
        <span data-smart-planner-validation></span>
        <span data-smart-planner-fairness></span>
        <span data-smart-planner-fatigue></span>
        <span data-smart-planner-unmet></span>
        <p data-smart-planner-explanation></p>
        <ul data-smart-planner-violations></ul>
        <ul data-smart-planner-suggestions></ul>
        <small data-smart-planner-assignment-meta></small>
        <table><tbody data-smart-planner-assignment-body></tbody></table>
        <small data-smart-planner-diff-meta></small>
        <table><tbody data-smart-planner-diff-body></tbody></table>
        <small data-smart-planner-conflict-meta></small>
        <ul data-smart-planner-conflict-list></ul>
        <input type="checkbox" data-smart-planner-force-apply>
        <small data-smart-planner-apply-meta></small>
        <button type="button" data-smart-planner-apply-dominant disabled>Apply Dominant</button>
        <button type="button" data-smart-planner-apply-daily disabled>Apply Daily</button>
      </div>
    `;

    const fetchMock = vi.mocked(fetch);
    fetchMock.mockImplementation((url, options = {}) => {
      if (String(url).startsWith('/v1/hcm/schedule-timing?')) {
        return jsonResponse({ success: true, data: [], meta: { pagination: { total: 0, page: 1, perPage: 50, totalPages: 1 } } });
      }
      if (url === '/v1/hcm/smart-attendance-shifting/settings') {
        return jsonResponse({
          success: true,
          data: {
            defaultRules: {
              max_work_days_per_week: 5,
              min_days_off_per_week: 2,
              min_rest_hours_between_shifts: 12,
              max_consecutive_night_shifts: 3,
            },
            forbiddenTransitions: ['night:morning'],
            transitionCatalog: ['night:morning'],
          },
        });
      }
      if (url === '/v1/hcm/shifts') {
        return jsonResponse({ success: true, data: [{ id: 7, name: 'Morning', shiftType: 'morning', startTime: '07:00', endTime: '15:00', slotLabel: '07:00 - 15:00', isActive: true }] });
      }
      if (url === '/v1/hcm/employees?perPage=100&page=1') {
        return jsonResponse({ success: true, data: [{ userId: 91 }] });
      }
      if (url === '/v1/hcm/smart-attendance-shifting/generate') {
        return jsonResponse({
          success: true,
          data: {
            schedule_generation: {
              validation_status: 'valid',
              weekly_schedule: [
                {
                  employee_id: '91',
                  employee_name: 'Agent A',
                  assignments: [
                    { date: '2026-04-20', shift_id: '7', start_time: '07:00', end_time: '15:00', cross_day: false },
                    { date: '2026-04-21', shift_id: 'OFF', start_time: null, end_time: null, cross_day: false },
                  ],
                },
              ],
              violations: [],
              unmet_coverage: [],
            },
            attendance_analysis: { employee_summaries: [], flags: [] },
            recommendation: { fairness_score: 90, fatigue_risk_score: 20, improvement_suggestions: [] },
            explanation: 'ok',
          },
        });
      }
      if (url === '/v1/hcm/smart-attendance-shifting/publish-roster') {
        return jsonResponse({ success: true, data: { created: 2, updated: 0, offDays: 1, total: 2 } });
      }
      throw new Error(`Unhandled fetch: ${String(url)} ${options.method || 'GET'}`);
    });

    await loadAttendanceModule('/schedule-timing');

    const form = document.querySelector('[data-smart-planner-form]');
    form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
    await flushPromises();

    const applyDailyBtn = document.querySelector('[data-smart-planner-apply-daily]');
    expect(applyDailyBtn.disabled).toBe(false);

    applyDailyBtn.click();
    await flushPromises();

    const postCall = fetchMock.mock.calls.find((entry) => entry[0] === '/v1/hcm/smart-attendance-shifting/publish-roster');
    expect(postCall).toBeTruthy();
    expect(document.querySelector('[data-smart-planner-feedback]')?.textContent).toContain('Publish roster harian berhasil');
  });

  it('requires force apply when critical conflicts are detected before publish', async () => {
    document.body.innerHTML = `
      <input data-schedule-timing-search value="">
      <select data-schedule-timing-sort><option value="name_asc">name_asc</option></select>
      <table><tbody data-schedule-timing-body></tbody></table>
      <div data-schedule-timing-pagination style="display:none;">
        <span data-schedule-timing-page-info></span>
        <button data-schedule-timing-prev></button>
        <button data-schedule-timing-next></button>
      </div>
      <form data-smart-planner-form>
        <select data-smart-planner-shift-category><option value="shifting_24h" selected>shift</option></select>
        <select data-smart-planner-scope><option value="all" selected>all</option></select>
        <div data-smart-planner-scope-hint></div>
        <small data-smart-planner-scope-meta></small>
        <div data-smart-planner-field="team-query" class="d-none"><input data-smart-planner-team-query value=""></div>
        <div data-smart-planner-field="custom-ids" class="d-none"><input data-smart-planner-custom-ids value=""></div>
        <div data-smart-planner-mode-hint></div>
        <input type="date" data-smart-planner-week-start value="2026-04-20">
        <input data-smart-planner-max-work-days value="5">
        <input data-smart-planner-min-days-off value="2">
        <div data-smart-planner-field="rest-rule"><input data-smart-planner-min-rest value="12"></div>
        <div data-smart-planner-field="night-rule"><input data-smart-planner-max-night value="3"></div>
        <button type="submit" data-smart-planner-submit>Generate</button>
      </form>
      <div class="d-none" data-smart-planner-feedback></div>
      <div class="d-none" data-smart-planner-result>
        <span data-smart-planner-validation></span>
        <span data-smart-planner-fairness></span>
        <span data-smart-planner-fatigue></span>
        <span data-smart-planner-unmet></span>
        <p data-smart-planner-explanation></p>
        <ul data-smart-planner-violations></ul>
        <ul data-smart-planner-suggestions></ul>
        <small data-smart-planner-assignment-meta></small>
        <table><tbody data-smart-planner-assignment-body></tbody></table>
        <small data-smart-planner-diff-meta></small>
        <table><tbody data-smart-planner-diff-body></tbody></table>
        <small data-smart-planner-conflict-meta></small>
        <ul data-smart-planner-conflict-list></ul>
        <input type="checkbox" data-smart-planner-force-apply>
        <small data-smart-planner-apply-meta></small>
        <button type="button" data-smart-planner-apply-dominant disabled>Apply</button>
      </div>
    `;

    const fetchMock = vi.mocked(fetch);
    fetchMock.mockImplementation((url, options = {}) => {
      if (String(url).startsWith('/v1/hcm/schedule-timing?')) {
        return jsonResponse({ success: true, data: [], meta: { pagination: { total: 0, page: 1, perPage: 50, totalPages: 1 } } });
      }
      if (url === '/v1/hcm/smart-attendance-shifting/settings') {
        return jsonResponse({
          success: true,
          data: {
            defaultRules: {
              max_work_days_per_week: 5,
              min_days_off_per_week: 2,
              min_rest_hours_between_shifts: 12,
              max_consecutive_night_shifts: 3,
            },
            forbiddenTransitions: ['night:morning'],
            transitionCatalog: ['night:morning'],
          },
        });
      }
      if (url === '/v1/hcm/holidays') {
        return jsonResponse({ success: true, data: [] });
      }
      if (url === '/v1/hcm/shifts') {
        return jsonResponse({ success: true, data: [{ id: 7, name: 'Morning', startTime: '07:00', endTime: '15:00', slotLabel: '07:00 - 15:00', isActive: true }] });
      }
      if (url === '/v1/hcm/employees?perPage=100&page=1') {
        return jsonResponse({ success: true, data: [{ userId: 91 }] });
      }
      if (url === '/v1/hcm/smart-attendance-shifting/generate') {
        return jsonResponse({
          success: true,
          data: {
            schedule_generation: {
              validation_status: 'invalid',
              weekly_schedule: [
                {
                  employee_id: '91',
                  employee_name: 'Agent A',
                  assignments: [
                    { date: '2026-04-20', shift_id: '7', start_time: '07:00', end_time: '15:00', cross_day: false },
                  ],
                },
              ],
              violations: [{ code: 'COVERAGE_UNMET', message: 'Need staff.' }],
              unmet_coverage: [],
            },
            attendance_analysis: { employee_summaries: [], flags: [] },
            recommendation: { fairness_score: 70, fatigue_risk_score: 40, improvement_suggestions: [] },
            explanation: 'conflict',
          },
        });
      }
      if (url === '/v1/hcm/schedule-timing/91') {
        return jsonResponse({ success: true, data: { id: 1 } });
      }
      throw new Error(`Unhandled fetch: ${String(url)} ${options.method || 'GET'}`);
    });

    await loadAttendanceModule('/schedule-timing');

    const form = document.querySelector('[data-smart-planner-form]');
    form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
    await flushPromises();

    const applyBtn = document.querySelector('[data-smart-planner-apply-dominant]');
    const forceApply = document.querySelector('[data-smart-planner-force-apply]');

    expect(applyBtn.disabled).toBe(true);
    forceApply.checked = true;
    forceApply.dispatchEvent(new Event('change', { bubbles: true }));
    expect(applyBtn.disabled).toBe(false);
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

  it('toggles planner settings edit mode and shows state indicator', async () => {
    document.body.innerHTML = `
      <input data-schedule-timing-search value="">
      <select data-schedule-timing-sort><option value="name_asc">name_asc</option></select>
      <table><tbody data-schedule-timing-body></tbody></table>
      <div data-schedule-timing-pagination style="display:none;"><span data-schedule-timing-page-info></span></div>
      <form data-smart-planner-form>
        <select data-smart-planner-shift-category><option value="office_hour" selected>office</option></select>
        <select data-smart-planner-scope><option value="legacy" selected>legacy</option></select>
        <div data-smart-planner-scope-hint></div>
        <small data-smart-planner-scope-meta></small>
        <input type="date" data-smart-planner-week-start value="2026-04-20">
        <input data-smart-planner-max-work-days value="5">
        <input data-smart-planner-min-days-off value="2">
        <div data-smart-planner-field="rest-rule"><input data-smart-planner-min-rest value="12"></div>
        <div data-smart-planner-field="night-rule"><input data-smart-planner-max-night value="3"></div>
        <button type="submit" data-smart-planner-submit>Generate</button>
      </form>
      <div data-smart-planner-settings-panel>
        <div data-smart-planner-mode-indicator>Viewing</div>
        <button type="button" class="" data-smart-planner-edit-mode-btn>Edit</button>
        <button type="button" class="d-none" data-smart-planner-cancel-edit-btn>Cancel</button>
        <button type="button" class="d-none" data-smart-planner-save-settings>Simpan</button>
        <button type="button" class="d-none" data-smart-planner-reset-defaults-btn>Reset</button>
        <input data-smart-planner-default-max-work-days value="5" disabled>
        <input data-smart-planner-default-min-days-off value="2" disabled>
        <input data-smart-planner-default-min-rest value="12" disabled>
        <input data-smart-planner-default-max-night value="3" disabled>
        <div data-smart-planner-transition-matrix><label><input type="checkbox" data-smart-planner-transition-key="night:morning" checked disabled></label></div>
        <small data-smart-planner-settings-feedback></small>
      </div>
    `;

    const fetchMock = vi.mocked(fetch);
    fetchMock.mockImplementation((url) => {
      if (String(url).startsWith('/v1/hcm/schedule-timing?')) {
        return jsonResponse({ success: true, data: [], meta: { pagination: { total: 0, page: 1, perPage: 50, totalPages: 1 } } });
      }
      if (url === '/v1/hcm/smart-attendance-shifting/settings') {
        return jsonResponse({
          success: true,
          data: {
            defaultRules: { max_work_days_per_week: 5, min_days_off_per_week: 2, min_rest_hours_between_shifts: 12, max_consecutive_night_shifts: 3 },
            forbiddenTransitions: ['night:morning'],
            transitionCatalog: ['night:morning'],
          },
        });
      }
      if (url === '/v1/hcm/shifts') {
        return jsonResponse({ success: true, data: [] });
      }
      throw new Error(`Unhandled fetch: ${String(url)}`);
    });

    await loadAttendanceModule('/schedule-timing');
    await flushPromises();

    const editBtn = document.querySelector('[data-smart-planner-edit-mode-btn]');
    const cancelBtn = document.querySelector('[data-smart-planner-cancel-edit-btn]');
    const saveBtn = document.querySelector('[data-smart-planner-save-settings]');
    const resetBtn = document.querySelector('[data-smart-planner-reset-defaults-btn]');
    const modeIndicator = document.querySelector('[data-smart-planner-mode-indicator]');
    const maxWorkDaysInput = document.querySelector('[data-smart-planner-default-max-work-days]');
    const transitionCheckbox = document.querySelector('[data-smart-planner-transition-key]');
    const submitBtn = document.querySelector('[data-smart-planner-submit]');

    // Initial state: View mode
    expect(editBtn.classList.contains('d-none')).toBe(false);
    expect(cancelBtn.classList.contains('d-none')).toBe(true);
    expect(saveBtn.classList.contains('d-none')).toBe(true);
    expect(resetBtn.classList.contains('d-none')).toBe(true);
    expect(maxWorkDaysInput.disabled).toBe(true);
    expect(modeIndicator.textContent).toContain('View mode');
    expect(submitBtn.disabled).toBe(false);

    // Click Edit button
    editBtn.click();

    // After edit mode: inputs enabled, action buttons visible, indicator changed
    expect(editBtn.classList.contains('d-none')).toBe(true);
    expect(cancelBtn.classList.contains('d-none')).toBe(false);
    expect(saveBtn.classList.contains('d-none')).toBe(false);
    expect(resetBtn.classList.contains('d-none')).toBe(false);
    expect(maxWorkDaysInput.disabled).toBe(false);
    expect(modeIndicator.textContent).toContain('Edit mode');
    expect(submitBtn.disabled).toBe(true);

    // Modify a value
    maxWorkDaysInput.value = '6';

    // Click Cancel button
    cancelBtn.click();

    // Should restore to view mode with original value
    expect(editBtn.classList.contains('d-none')).toBe(false);
    expect(cancelBtn.classList.contains('d-none')).toBe(true);
    expect(maxWorkDaysInput.value).toBe('5');
    expect(maxWorkDaysInput.disabled).toBe(true);
    expect(modeIndicator.textContent).toContain('View mode');
    expect(submitBtn.disabled).toBe(false);
  });
});
