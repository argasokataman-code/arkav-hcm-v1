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

function buildWizardDom() {
  document.body.innerHTML = `
    <div id="add_employee"></div>
    <form data-employee-add-form data-employee-step-index="0">
      <div data-employee-step-pane="0">
        <select data-employee-add-field="employeeType">
          <option value="">Select</option>
          <option value="permanent">Permanent</option>
          <option value="contract">Contract</option>
          <option value="intern">Intern</option>
        </select>
      </div>
      <div class="d-none" data-employee-step-pane="1"></div>
      <div class="d-none" data-employee-step-pane="2">
        <input data-employee-add-field="startDate" type="date" />
        <select data-employee-add-field="contractType">
          <option value="permanent">Permanent</option>
          <option value="contract">Contract</option>
        </select>
        <input data-employee-add-field="contractStartDate" type="date" />
        <div class="d-none" data-employee-contract-end-wrap>
          <input data-employee-add-field="contractEndDate" type="date" />
        </div>
      </div>
      <div class="d-none" data-employee-step-pane="3"></div>
      <div class="d-none" data-employee-step-pane="4"></div>
      <button type="button" data-employee-step-prev class="d-none">Back</button>
      <button type="button" data-employee-step-next>Next</button>
      <button type="submit" data-employee-step-submit class="d-none">Submit</button>
    </form>
  `;
}

function buildBackendValidationDom() {
  document.body.innerHTML = `
    <div id="add_employee"></div>
    <form data-employee-add-form data-employee-step-index="0">
      <input data-employee-add-field="name" />
      <input data-employee-add-field="email" />
      <input data-employee-add-field="password" />
      <button type="submit" data-employee-step-submit>Submit</button>
    </form>
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

  it('keeps employeeType and contractType consistent in wizard', async () => {
    buildWizardDom();

    bindEmployeeCompensationFormsModule({
      requestJson: vi.fn(() => Promise.resolve({ success: true, data: {} })),
      requestEmployeeDetail: vi.fn(),
      fillDesignationSelectForDepartment: vi.fn(),
      loadTeamsDropdown: vi.fn(),
      formatApiError: () => 'error',
      loadEmployeesData: vi.fn(),
    });

    const form = document.querySelector('[data-employee-add-form]');
    const employeeType = form.querySelector('[data-employee-add-field="employeeType"]');
    const contractType = form.querySelector('[data-employee-add-field="contractType"]');
    const startDate = form.querySelector('[data-employee-add-field="startDate"]');
    const contractStartDate = form.querySelector('[data-employee-add-field="contractStartDate"]');

    employeeType.value = 'contract';
    employeeType.dispatchEvent(new Event('change', { bubbles: true }));
    expect(contractType.value).toBe('contract');

    startDate.value = '2026-05-08';
    startDate.dispatchEvent(new Event('change', { bubbles: true }));
    expect(contractStartDate.value).toBe('2026-05-08');

    contractStartDate.value = '2026-06-01';
    contractStartDate.dispatchEvent(new Event('change', { bubbles: true }));
    startDate.value = '2026-07-01';
    startDate.dispatchEvent(new Event('change', { bubbles: true }));
    expect(contractStartDate.value).toBe('2026-06-01');

    contractType.value = 'permanent';
    contractType.dispatchEvent(new Event('change', { bubbles: true }));
    expect(contractType.value).toBe('contract');

    employeeType.value = 'permanent';
    employeeType.dispatchEvent(new Event('change', { bubbles: true }));
    expect(contractType.value).toBe('permanent');
  });

  it('maps backend validation errors to form fields and focuses first invalid field', async () => {
    buildBackendValidationDom();

    const requestJson = vi.fn(() =>
      Promise.resolve({
        success: false,
        errors: {
          email: ['The email has already been taken.'],
          data_disclosure_acknowledged: ['The data disclosure acknowledged field is required.'],
        },
        error: {
          code: 'VALIDATION_ERROR',
          message: 'Validation failed',
        },
      })
    );

    bindEmployeeCompensationFormsModule({
      requestJson,
      requestEmployeeDetail: vi.fn(),
      fillDesignationSelectForDepartment: vi.fn(),
      loadTeamsDropdown: vi.fn(),
      formatApiError: () => 'error',
      loadEmployeesData: vi.fn(),
    });

    const form = document.querySelector('[data-employee-add-form]');
    const nameInput = form.querySelector('[data-employee-add-field="name"]');
    const emailInput = form.querySelector('[data-employee-add-field="email"]');
    const passwordInput = form.querySelector('[data-employee-add-field="password"]');

    nameInput.value = 'Valid Name';
    emailInput.value = 'duplicate@example.com';
    passwordInput.value = 'StrongPass1';

    const reportSpy = vi.fn(() => false);
    emailInput.reportValidity = reportSpy;

    form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
    await flush();

    expect(emailInput.getAttribute('data-employee-backend-error')).toBe('1');
    expect(reportSpy).toHaveBeenCalledTimes(1);
    expect(window.ArcavUi.showToast).toHaveBeenCalledWith(
      'The data disclosure acknowledged field is required.',
      'warning'
    );
  });

  it('disables submit button during API call and re-enables on error response', async () => {
    buildBackendValidationDom();

    let resolveRequest;
    const requestJson = vi.fn(() => new Promise((resolve) => { resolveRequest = resolve; }));

    bindEmployeeCompensationFormsModule({
      requestJson,
      requestEmployeeDetail: vi.fn(),
      fillDesignationSelectForDepartment: vi.fn(),
      loadTeamsDropdown: vi.fn(),
      formatApiError: () => 'error',
      loadEmployeesData: vi.fn(),
    });

    const form = document.querySelector('[data-employee-add-form]');
    const submitBtn = form.querySelector('[data-employee-step-submit]');
    const nameInput = form.querySelector('[data-employee-add-field="name"]');
    const emailInput = form.querySelector('[data-employee-add-field="email"]');
    const passwordInput = form.querySelector('[data-employee-add-field="password"]');

    nameInput.value = 'Valid Name';
    emailInput.value = 'test@example.com';
    passwordInput.value = 'StrongPass1';

    form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));

    // While request is in-flight, button should be disabled.
    expect(submitBtn.disabled).toBe(true);
    expect(submitBtn.textContent).toBe('Menyimpan...');

    resolveRequest({ success: false, error: { code: 'SOME_ERROR', message: 'fail' } });
    await flush();

    // After error response, button should be re-enabled.
    expect(submitBtn.disabled).toBe(false);
  });

  it('clears stale backend errors from previous submit when resetFormState is called', async () => {
    buildBackendValidationDom();

    let callCount = 0;
    const requestJson = vi.fn(() => {
      callCount++;
      if (callCount === 1) {
        return Promise.resolve({
          success: false,
          errors: { email: ['Already taken.'] },
          error: { code: 'VALIDATION_ERROR', message: 'Validation failed' },
        });
      }
      return Promise.resolve({ success: true, data: {} });
    });

    bindEmployeeCompensationFormsModule({
      requestJson,
      requestEmployeeDetail: vi.fn(),
      fillDesignationSelectForDepartment: vi.fn(),
      loadTeamsDropdown: vi.fn(),
      formatApiError: () => 'error',
      loadEmployeesData: vi.fn(),
    });

    const form = document.querySelector('[data-employee-add-form]');
    const emailInput = form.querySelector('[data-employee-add-field="email"]');
    const nameInput = form.querySelector('[data-employee-add-field="name"]');
    const passwordInput = form.querySelector('[data-employee-add-field="password"]');

    nameInput.value = 'Valid Name';
    emailInput.value = 'dup@example.com';
    passwordInput.value = 'StrongPass1';

    // First submit: backend returns validation error on email.
    form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
    await flush();
    expect(emailInput.getAttribute('data-employee-backend-error')).toBe('1');
    expect(emailInput.validity.customError).toBe(true);

    // Simulate typing in email field (input event) to clear the backend error.
    emailInput.value = 'new@example.com';
    emailInput.dispatchEvent(new Event('input', { bubbles: true }));
    expect(emailInput.getAttribute('data-employee-backend-error')).toBeNull();
    expect(emailInput.validity.customError).toBe(false);
  });

  it('does not show contract end date error when user is on step 0', () => {
    // Step 0 cross-step contract checks should NOT fire, even if contractType is contract.
    document.body.innerHTML = `
      <div id="add_employee"></div>
      <form data-employee-add-form data-employee-step-index="0">
        <div data-employee-step-pane="0">
          <input data-employee-add-field="name" value="Test" />
        </div>
        <div class="d-none" data-employee-step-pane="1"></div>
        <div class="d-none" data-employee-step-pane="2">
          <select data-employee-add-field="contractType">
            <option value="contract" selected>Contract</option>
          </select>
          <div data-employee-contract-end-wrap>
            <input data-employee-add-field="contractEndDate" type="date" value="" />
          </div>
        </div>
        <div class="d-none" data-employee-step-pane="3"></div>
        <div class="d-none" data-employee-step-pane="4"></div>
        <button type="button" data-employee-step-next>Next</button>
        <button type="button" class="d-none" data-employee-step-prev>Back</button>
        <button type="submit" class="d-none" data-employee-step-submit>Submit</button>
      </form>
    `;

    bindEmployeeCompensationFormsModule({
      requestJson: vi.fn(),
      requestEmployeeDetail: vi.fn(),
      fillDesignationSelectForDepartment: vi.fn(),
      loadTeamsDropdown: vi.fn(),
      formatApiError: () => 'error',
      loadEmployeesData: vi.fn(),
    });

    const form = document.querySelector('[data-employee-add-form]');
    const nameInput = form.querySelector('[data-employee-add-field="name"]');
    nameInput.reportValidity = vi.fn(() => true);

    const nextBtn = form.querySelector('[data-employee-step-next]');
    nextBtn.click();

    // Should NOT have shown a toast about contract end date — user is on step 0.
    const toastCalls = window.ArcavUi.showToast.mock.calls;
    const contractToast = toastCalls.find((args) => String(args[0]).includes('contract end'));
    expect(contractToast).toBeUndefined();
  });
});
