import { beforeEach, describe, expect, it, vi } from 'vitest';

import { bindSalaryBulkUploadModule } from '../../../frontend/resources/js/employees/employees-salary-bulk-upload.js';

function buildDom() {
  document.body.innerHTML = `
    <a href="#" data-employee-bulk-template-link>Template</a>
    <a href="#" data-employee-bulk-upload-open>Bulk Upload</a>
    <div id="employee_bulk_org_required">
      <div data-employee-bulk-org-required-message></div>
    </div>
    <div id="employee_bulk_upload"></div>
    <form data-employee-bulk-upload-form>
      <input type="file" data-employee-bulk-upload-file />
      <div class="alert d-none" data-employee-bulk-upload-results></div>
    </form>
  `;
}

describe('Employee bulk upload wiring', () => {
  beforeEach(() => {
    vi.restoreAllMocks();
    buildDom();

    window.ArcavUi = {
      showToast: vi.fn(),
    };

    window.AuthApi = {
      handleUnauthorizedFromApi: vi.fn(() => false),
    };

    window.bootstrap = {
      Modal: {
        getOrCreateInstance: vi.fn(() => ({
          show: vi.fn(),
          hide: vi.fn(),
        })),
      },
    };
  });

  it('blocks template download and opens prerequisite modal when org masters are missing', () => {
    bindSalaryBulkUploadModule({
      requestFormData: vi.fn(),
      formatApiError: () => 'error',
      escapeHtml: (value) => String(value),
      loadEmployeesData: vi.fn(),
      getOrganizationReferenceSnapshot: () => ({
        departments: [],
        designations: [],
      }),
    });

    const link = document.querySelector('[data-employee-bulk-template-link]');
    const dispatched = link.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true }));
    const prerequisiteModal = window.bootstrap.Modal.getOrCreateInstance.mock.results[0].value;

    expect(dispatched).toBe(false);
    expect(prerequisiteModal.show).toHaveBeenCalledTimes(1);
    expect(document.querySelector('[data-employee-bulk-org-required-message]').textContent).toContain('department');
    expect(window.ArcavUi.showToast).toHaveBeenCalledWith(
      'Isi minimal 1 department dan 1 designation sebelum download template atau upload bulk employee.',
      'warning'
    );
  });

  it('prevents bulk upload modal from opening when org masters are missing', () => {
    bindSalaryBulkUploadModule({
      requestFormData: vi.fn(),
      formatApiError: () => 'error',
      escapeHtml: (value) => String(value),
      loadEmployeesData: vi.fn(),
      getOrganizationReferenceSnapshot: () => ({
        departments: [],
        designations: [],
      }),
    });

    const bulkModal = document.getElementById('employee_bulk_upload');
    const event = new Event('show.bs.modal', { bubbles: true, cancelable: true });

    bulkModal.dispatchEvent(event);

    const prerequisiteModal = window.bootstrap.Modal.getOrCreateInstance.mock.results[0].value;

    expect(event.defaultPrevented).toBe(true);
    expect(prerequisiteModal.show).toHaveBeenCalledTimes(1);
  });

  it('allows template click when department and designation masters already exist', () => {
    bindSalaryBulkUploadModule({
      requestFormData: vi.fn(),
      formatApiError: () => 'error',
      escapeHtml: (value) => String(value),
      loadEmployeesData: vi.fn(),
      getOrganizationReferenceSnapshot: () => ({
        departments: [{ id: 1, name: 'People Operations' }],
        designations: [{ id: 10, name: 'HR Generalist' }],
      }),
    });

    const link = document.querySelector('[data-employee-bulk-template-link]');
    const dispatched = link.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true }));

    expect(dispatched).toBe(true);
    expect(window.bootstrap.Modal.getOrCreateInstance).not.toHaveBeenCalled();
  });
});