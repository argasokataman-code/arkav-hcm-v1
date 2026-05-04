import { beforeEach, describe, expect, it, vi } from 'vitest';

import { bindEmployeeCompensationFormsModule } from '../../../frontend/resources/js/employees/employees-compensation-forms.js';

function flush(times = 6) {
  return Array.from({ length: times }).reduce((promise) => promise.then(() => Promise.resolve()), Promise.resolve());
}

function buildDom() {
  document.body.innerHTML = `
    <div id="add_employee"></div>
    <form data-employee-add-form data-employee-step-index="0"></form>
  `;
}

describe('Employee compensation forms wiring', () => {
  beforeEach(() => {
    vi.restoreAllMocks();
    buildDom();

    window.ArcavUi = {
      showToast: vi.fn(),
      showInfo: vi.fn(),
    };

    window.AuthApi = {
      handleUnauthorizedFromApi: vi.fn(() => false),
    };
  });

  it('shows info popup when add employee exceeds plan slot limit', async () => {
    const requestJson = vi.fn(() =>
      Promise.reject({
        status: 422,
        data: {
          success: false,
          error: {
            code: 'EMPLOYEE_COUNT_EXCEEDED',
            message: 'Cannot add 1 employee(s). Only 0 slot(s) available. Plan limit: 20',
          },
        },
      })
    );

    bindEmployeeCompensationFormsModule({
      requestJson,
      requestEmployeeDetail: vi.fn(),
      fillDesignationSelectForDepartment: vi.fn(),
      loadTeamsDropdown: vi.fn(),
      formatApiError: (payload) => payload?.error?.message || 'error',
      loadEmployeesData: vi.fn(),
    });

    const form = document.querySelector('[data-employee-add-form]');
    form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));

    await flush();

    expect(window.ArcavUi.showInfo).toHaveBeenCalledWith(
      'Kapasitas Karyawan Penuh',
      'Cannot add 1 employee(s). Only 0 slot(s) available. Plan limit: 20'
    );
    expect(window.ArcavUi.showToast).toHaveBeenCalled();
  });
});
