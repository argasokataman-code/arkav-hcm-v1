import { beforeEach, describe, expect, it, vi } from 'vitest';

async function loadTerminationModule() {
  vi.resetModules();
  await import('../../../frontend/resources/js/termination-data.js');
}

function flush(times) {
  var total = typeof times === 'number' ? times : 6;
  var promise = Promise.resolve();
  for (var index = 0; index < total; index += 1) {
    promise = promise.then(function () {
      return Promise.resolve();
    });
  }
  return promise;
}

function buildTerminationDom() {
  document.body.innerHTML = '' +
    '<button data-arcav-termination-add>Add Termination</button>' +
    '<table><tbody data-arcav-terminations-tbody></tbody></table>' +
    '<div id="arcav_termination_modal">' +
      '<form data-arcav-termination-form>' +
        '<div data-arcav-termination-flash class="d-none"></div>' +
        '<input data-arcav-termination-id />' +
        '<select data-arcav-termination-user required></select>' +
        '<input data-arcav-termination-type required />' +
        '<input data-arcav-termination-notice-date required />' +
        '<input data-arcav-termination-termination-date required />' +
        '<input data-arcav-termination-department />' +
        '<textarea data-arcav-termination-reason required></textarea>' +
        '<textarea data-arcav-termination-notes></textarea>' +
        '<select data-arcav-termination-status>' +
          '<option value="pending">pending</option>' +
          '<option value="finalized">finalized</option>' +
        '</select>' +
        '<div data-arcav-termination-finalization-fields class="d-none"></div>' +
        '<input data-arcav-termination-settlement-payroll-period />' +
        '<input data-arcav-termination-final-salary-amount />' +
        '<input data-arcav-termination-final-allowance-amount />' +
        '<input data-arcav-termination-final-deduction-amount />' +
        '<textarea data-arcav-termination-asset-return-notes></textarea>' +
        '<textarea data-arcav-termination-clearance-notes></textarea>' +
        '<button data-arcav-termination-preview-settlement type="button">Refresh from payroll & assets</button>' +
        '<div data-arcav-termination-preview-flash class="d-none"></div>' +
        '<div data-arcav-termination-preview-wrap class="d-none"></div>' +
        '<div data-arcav-termination-preview-period></div>' +
        '<div data-arcav-termination-preview-source></div>' +
        '<div data-arcav-termination-preview-net></div>' +
        '<div data-arcav-termination-preview-breakdown></div>' +
        '<div data-arcav-termination-preview-clearance></div>' +
      '</form>' +
    '</div>' +
    '<div id="arcav_termination_detail_modal">' +
      '<div data-arcav-termination-detail-error class="d-none"></div>' +
      '<div data-arcav-termination-detail-body class="d-none"></div>' +
      '<div data-arcav-termination-detail-loading class="d-none"></div>' +
      '<a data-arcav-termination-detail-profile href="/employee-details"></a>' +
      '<div data-arcav-termination-detail-settlement-wrap class="d-none"></div>' +
      '<div data-arcav-termination-detail-employee></div>' +
      '<div data-arcav-termination-detail-email></div>' +
      '<div data-arcav-termination-detail-department></div>' +
      '<div data-arcav-termination-detail-type></div>' +
      '<div data-arcav-termination-detail-status></div>' +
      '<div data-arcav-termination-detail-notice-date></div>' +
      '<div data-arcav-termination-detail-termination-date></div>' +
      '<div data-arcav-termination-detail-reason></div>' +
      '<div data-arcav-termination-detail-notes></div>' +
      '<div data-arcav-termination-detail-created></div>' +
      '<div data-arcav-termination-detail-settlement-period></div>' +
      '<div data-arcav-termination-detail-final-salary></div>' +
      '<div data-arcav-termination-detail-final-allowance></div>' +
      '<div data-arcav-termination-detail-final-deduction></div>' +
      '<div data-arcav-termination-detail-final-net></div>' +
      '<div data-arcav-termination-detail-asset-return-notes></div>' +
      '<div data-arcav-termination-detail-clearance-notes></div>' +
      '<div data-arcav-termination-detail-breakdown></div>' +
      '<div data-arcav-termination-detail-clearance-items></div>' +
    '</div>';

  var form = document.querySelector('[data-arcav-termination-form]');
  form.checkValidity = function () { return true; };
  form.reportValidity = function () { return true; };
}

describe('Termination wiring', function () {
  beforeEach(function () {
    localStorage.clear();
    buildTerminationDom();
    window.__ARCAV_DISABLE_REDIRECTS__ = true;
    window.bootstrap = {
      Modal: class {
        static getOrCreateInstance() {
          return new window.bootstrap.Modal();
        }
        show() {}
        hide() {}
      },
    };
    window.ArcavUi = {
      toast: vi.fn(),
      confirmDelete: vi.fn().mockResolvedValue(true),
    };
  });

  it('submits selected employee UUID from employee source, not numeric id', async function () {
    var fetchMock = vi.fn(function (url, options) {
      var requestOptions = options || {};
      var urlString = String(url);

      if (urlString === '/v1/identity/auth/me') {
        return Promise.resolve({
          ok: true,
          status: 200,
          json: async function () {
            return {
              success: true,
              data: {
                permissions: {
                  'termination.view': true,
                  'termination.manage': true,
                },
              },
            };
          },
        });
      }

      if (urlString === '/v1/hcm/terminations' && requestOptions.method === 'GET') {
        return Promise.resolve({
          ok: true,
          status: 200,
          json: async function () { return { success: true, data: [] }; },
        });
      }

      if (urlString === '/v1/hcm/employees?perPage=100') {
        return Promise.resolve({
          ok: true,
          status: 200,
          json: async function () {
            return {
              success: true,
              data: [
                {
                  id: 9,
                  uuid: '550e8400-e29b-41d4-a716-446655440000',
                  fullName: 'Nadia',
                  email: 'nadia@example.com',
                },
              ],
            };
          },
        });
      }

      if (urlString === '/v1/hcm/terminations' && requestOptions.method === 'POST') {
        return Promise.resolve({
          ok: true,
          status: 201,
          json: async function () { return { success: true, data: { id: 99 } }; },
        });
      }

      if (urlString === '/v1/hcm/employees/9') {
        return Promise.resolve({
          ok: true,
          status: 200,
          json: async function () {
            return {
              success: true,
              data: {
                id: 9,
                uuid: '550e8400-e29b-41d4-a716-446655440000',
                team: 'Finance',
              },
            };
          },
        });
      }

      throw new Error('Unexpected fetch: ' + urlString);
    });

    vi.stubGlobal('fetch', fetchMock);
    window.AuthApi = {
      handleUnauthorizedFromApi: function () { return false; },
    };

    await loadTerminationModule();
    await flush();

    document.querySelector('[data-arcav-termination-add]').click();
    await flush();

    var userSelect = document.querySelector('[data-arcav-termination-user]');
    userSelect.value = '9';
    userSelect.dispatchEvent(new Event('change'));
    await flush();

    document.querySelector('[data-arcav-termination-type]').value = 'Layoff';
    document.querySelector('[data-arcav-termination-notice-date]').value = '2026-04-01';
    document.querySelector('[data-arcav-termination-termination-date]').value = '2026-04-30';
    document.querySelector('[data-arcav-termination-reason]').value = 'Workforce reduction';
    document.querySelector('[data-arcav-termination-notes]').value = 'Approved';
    document.querySelector('[data-arcav-termination-status]').value = 'finalized';
    document.querySelector('[data-arcav-termination-status]').dispatchEvent(new Event('change'));
    document.querySelector('[data-arcav-termination-settlement-payroll-period]').value = '2026-05';
    document.querySelector('[data-arcav-termination-final-salary-amount]').value = '4500000';
    document.querySelector('[data-arcav-termination-final-allowance-amount]').value = '750000';
    document.querySelector('[data-arcav-termination-final-deduction-amount]').value = '500000';
    document.querySelector('[data-arcav-termination-asset-return-notes]').value = 'Laptop returned';
    document.querySelector('[data-arcav-termination-clearance-notes]').value = 'Settlement goes to nearest payroll';

    document.querySelector('[data-arcav-termination-form]').dispatchEvent(new Event('submit', { cancelable: true }));
    await flush();

    var postCall = fetchMock.mock.calls.find(function (call) {
      return String(call[0]) === '/v1/hcm/terminations' && call[1] && call[1].method === 'POST';
    });

    expect(postCall).toBeTruthy();
    expect(JSON.parse(postCall[1].body)).toMatchObject({
      userId: '550e8400-e29b-41d4-a716-446655440000',
      department: 'Finance',
      terminationType: 'Layoff',
      status: 'finalized',
      settlementPayrollPeriod: '2026-05',
      finalSalaryAmount: '4500000',
      finalAllowanceAmount: '750000',
      finalDeductionAmount: '500000',
    });
  });

  it('refreshes settlement preview and auto-fills finalized form fields', async function () {
    var fetchMock = vi.fn(function (url, options) {
      var requestOptions = options || {};
      var urlString = String(url);

      if (urlString === '/v1/identity/auth/me') {
        return Promise.resolve({
          ok: true,
          status: 200,
          json: async function () {
            return {
              success: true,
              data: {
                permissions: {
                  'termination.view': true,
                  'termination.manage': true,
                },
              },
            };
          },
        });
      }

      if (urlString === '/v1/hcm/terminations' && requestOptions.method === 'GET') {
        return Promise.resolve({
          ok: true,
          status: 200,
          json: async function () { return { success: true, data: [] }; },
        });
      }

      if (urlString === '/v1/hcm/employees?perPage=100') {
        return Promise.resolve({
          ok: true,
          status: 200,
          json: async function () {
            return {
              success: true,
              data: [
                {
                  id: 9,
                  uuid: '550e8400-e29b-41d4-a716-446655440000',
                  fullName: 'Nadia',
                  email: 'nadia@example.com',
                },
              ],
            };
          },
        });
      }

      if (urlString === '/v1/hcm/employees/9') {
        return Promise.resolve({
          ok: true,
          status: 200,
          json: async function () {
            return {
              success: true,
              data: {
                id: 9,
                uuid: '550e8400-e29b-41d4-a716-446655440000',
                team: 'Finance',
              },
            };
          },
        });
      }

      if (urlString.indexOf('/v1/hcm/terminations/settlement-preview?') === 0) {
        return Promise.resolve({
          ok: true,
          status: 200,
          json: async function () {
            return {
              success: true,
              data: {
                resolvedPeriod: {
                  label: '2026-05',
                  status: 'open',
                },
                source: 'termination_policy_prorated',
                summary: {
                  finalSalaryAmount: '2419354.84',
                  finalAllowanceAmount: '362903.23',
                  finalDeductionAmount: '0.00',
                  finalNetAmount: '2782258.07',
                },
                breakdown: [
                  {
                    componentCode: 'termination_prorated_salary',
                    componentName: 'Prorated final salary',
                    amount: '2419354.84',
                    bucket: 'salary',
                  },
                ],
                clearance: {
                  summaryNotes: 'Outstanding asset clearance: LAP-001 Laptop Kerja',
                  items: [
                    {
                      assignmentId: 7,
                      assetCode: 'LAP-001',
                      assetName: 'Laptop Kerja',
                      assignedDate: '2026-03-10',
                      status: 'pending_return',
                    },
                  ],
                },
              },
            };
          },
        });
      }

      throw new Error('Unexpected fetch: ' + urlString);
    });

    vi.stubGlobal('fetch', fetchMock);
    window.AuthApi = {
      handleUnauthorizedFromApi: function () { return false; },
    };

    await loadTerminationModule();
    await flush();

    document.querySelector('[data-arcav-termination-add]').click();
    await flush();

    var userSelect = document.querySelector('[data-arcav-termination-user]');
    userSelect.value = '9';
    userSelect.dispatchEvent(new Event('change'));
    await flush();

    document.querySelector('[data-arcav-termination-status]').value = 'finalized';
    document.querySelector('[data-arcav-termination-status]').dispatchEvent(new Event('change'));
    document.querySelector('[data-arcav-termination-termination-date]').value = '2026-05-15';
    document.querySelector('[data-arcav-termination-preview-settlement]').click();
    await flush(8);

    expect(document.querySelector('[data-arcav-termination-settlement-payroll-period]').value).toBe('2026-05');
    expect(document.querySelector('[data-arcav-termination-final-salary-amount]').value).toBe('2419354.84');
    expect(document.querySelector('[data-arcav-termination-final-allowance-amount]').value).toBe('362903.23');
    expect(document.querySelector('[data-arcav-termination-final-deduction-amount]').value).toBe('0.00');
    expect(document.querySelector('[data-arcav-termination-asset-return-notes]').value).toContain('LAP-001');
    expect(document.querySelector('[data-arcav-termination-preview-breakdown]').textContent).toContain('Prorated final salary');
    expect(document.querySelector('[data-arcav-termination-preview-clearance]').textContent).toContain('Laptop Kerja');
  });

  it('returns a clearance item from an existing termination row', async function () {
    var fetchMock = vi.fn(function (url, options) {
      var requestOptions = options || {};
      var urlString = String(url);

      if (urlString === '/v1/identity/auth/me') {
        return Promise.resolve({
          ok: true,
          status: 200,
          json: async function () {
            return {
              success: true,
              data: {
                permissions: {
                  'termination.view': true,
                  'termination.manage': true,
                },
              },
            };
          },
        });
      }

      if (urlString === '/v1/hcm/terminations' && requestOptions.method === 'GET') {
        return Promise.resolve({
          ok: true,
          status: 200,
          json: async function () {
            return {
              success: true,
              data: [
                {
                  id: 19,
                  employee: { id: 9, name: 'Nadia', email: 'nadia@example.com' },
                  department: 'Finance',
                  terminationType: 'Layoff',
                  reason: 'Workforce reduction',
                  noticeDate: '2026-04-01',
                  terminationDate: '2026-05-15',
                  status: 'finalized',
                  notes: 'Approved',
                  settlement: {
                    payrollPeriod: '2026-05',
                    payrollPeriodStatus: 'open',
                    finalSalaryAmount: '2419354.84',
                    finalAllowanceAmount: '362903.23',
                    finalDeductionAmount: '0.00',
                    finalNetAmount: '2782258.07',
                    assetReturnNotes: 'Outstanding asset clearance: LAP-001 Laptop Kerja',
                    clearanceNotes: 'Must be returned before posting',
                    breakdown: [],
                    clearanceItems: [
                      {
                        assignmentId: 7,
                        assetCode: 'LAP-001',
                        assetName: 'Laptop Kerja',
                        assignedDate: '2026-03-10',
                        status: 'pending_return',
                      },
                    ],
                  },
                },
              ],
            };
          },
        });
      }

      if (urlString === '/v1/hcm/terminations?perPage=100') {
        return Promise.resolve({
          ok: true,
          status: 200,
          json: async function () {
            return {
              success: true,
              data: [
                {
                  id: 19,
                  employee: { id: 9, name: 'Nadia', email: 'nadia@example.com' },
                  department: 'Finance',
                  terminationType: 'Layoff',
                  reason: 'Workforce reduction',
                  noticeDate: '2026-04-01',
                  terminationDate: '2026-05-15',
                  status: 'finalized',
                  notes: 'Approved',
                  settlement: {
                    payrollPeriod: '2026-05',
                    payrollPeriodStatus: 'open',
                    finalSalaryAmount: '2419354.84',
                    finalAllowanceAmount: '362903.23',
                    finalDeductionAmount: '0.00',
                    finalNetAmount: '2782258.07',
                    assetReturnNotes: 'Outstanding asset clearance: LAP-001 Laptop Kerja',
                    clearanceNotes: 'Must be returned before posting',
                    breakdown: [],
                    clearanceItems: [
                      {
                        assignmentId: 7,
                        assetCode: 'LAP-001',
                        assetName: 'Laptop Kerja',
                        assignedDate: '2026-03-10',
                        status: 'pending_return',
                      },
                    ],
                  },
                },
              ],
            };
          },
        });
      }

      if (urlString === '/v1/hcm/terminations/19/clearance-items/7/return' && requestOptions.method === 'POST') {
        return Promise.resolve({
          ok: true,
          status: 200,
          json: async function () {
            return {
              success: true,
              data: {
                termination: {
                  id: 19,
                  settlement: {
                    payrollPeriod: '2026-05',
                    payrollPeriodStatus: 'open',
                    finalSalaryAmount: '2419354.84',
                    finalAllowanceAmount: '362903.23',
                    finalDeductionAmount: '0.00',
                    finalNetAmount: '2782258.07',
                    assetReturnNotes: 'No outstanding asset assignments.',
                    clearanceItems: [],
                    breakdown: [],
                  },
                },
              },
            };
          },
        });
      }

      throw new Error('Unexpected fetch: ' + urlString);
    });

    vi.stubGlobal('fetch', fetchMock);
    window.AuthApi = {
      handleUnauthorizedFromApi: function () { return false; },
    };

    await loadTerminationModule();
    await flush();

    document.querySelector('[data-arcav-termination-edit="19"]').click();
    await flush(8);

    document.querySelector('[data-arcav-termination-clearance-return="7"]').click();
    await flush(8);

    var postCall = fetchMock.mock.calls.find(function (call) {
      return String(call[0]) === '/v1/hcm/terminations/19/clearance-items/7/return' && call[1] && call[1].method === 'POST';
    });

    expect(postCall).toBeTruthy();
    expect(document.querySelector('[data-arcav-termination-preview-clearance]').textContent).toContain('No outstanding clearance items');
  });

  it('sends auth and tenant headers and hides manage actions for view-only users', async function () {
    var fetchMock = vi.fn(function (url, options) {
      var requestOptions = options || {};
      var urlString = String(url);

      if (urlString === '/v1/identity/auth/me') {
        return Promise.resolve({
          ok: true,
          status: 200,
          json: async function () {
            return {
              success: true,
              data: {
                permissions: {
                  'termination.view': true,
                },
              },
            };
          },
        });
      }

      if (urlString === '/v1/hcm/terminations' && requestOptions.method === 'GET') {
        return Promise.resolve({
          ok: true,
          status: 200,
          json: async function () {
            return {
              success: true,
              data: [
                {
                  id: 7,
                  employee: { id: 9, name: 'Nadia', email: 'nadia@example.com' },
                  department: 'Finance',
                  terminationType: 'Layoff',
                  reason: 'Workforce reduction',
                  noticeDate: '2026-04-01',
                  terminationDate: '2026-04-30',
                  status: 'finalized',
                  settlement: {
                    payrollPeriod: '2026-05',
                    finalNetAmount: '4750000.00',
                    assetReturnNotes: 'Laptop returned',
                  },
                },
              ],
            };
          },
        });
      }

      throw new Error('Unexpected fetch: ' + urlString);
    });

    vi.stubGlobal('fetch', fetchMock);
    window.AuthApi = {
      getToken: function () { return 'token-abc'; },
      getTenantContext: function () {
        return {
          companyCode: 'ACME',
          companyId: 44,
          companyUuid: '11111111-2222-3333-4444-555555555555',
        };
      },
      handleUnauthorizedFromApi: function () { return false; },
    };

    await loadTerminationModule();
    await flush();

    expect(fetchMock).toHaveBeenCalledTimes(2);

    var meCall = fetchMock.mock.calls[0];
    expect(String(meCall[0])).toBe('/v1/identity/auth/me');
    expect(meCall[1].headers.Authorization).toBe('Bearer token-abc');
    expect(meCall[1].headers['X-Company-Code']).toBe('ACME');
    expect(meCall[1].headers['X-Company-Id']).toBe('44');
    expect(meCall[1].headers['X-Company-UUID']).toBe('11111111-2222-3333-4444-555555555555');

    var listCall = fetchMock.mock.calls[1];
    expect(String(listCall[0])).toBe('/v1/hcm/terminations');
    expect(listCall[1].headers.Authorization).toBe('Bearer token-abc');
    expect(listCall[1].headers['X-Company-Id']).toBe('44');

    expect(document.querySelector('[data-arcav-termination-add]').classList.contains('d-none')).toBe(true);
    var html = document.querySelector('[data-arcav-terminations-tbody]').innerHTML;
    expect(html).toContain('data-arcav-termination-view="7"');
    expect(html).toContain('Payroll 2026-05');
    expect(html).toContain('Asset: Laptop returned');
    expect(html).not.toContain('data-arcav-termination-edit="7"');
    expect(html).not.toContain('data-arcav-termination-delete="7"');
  });
});