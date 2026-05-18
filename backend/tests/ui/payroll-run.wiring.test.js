import { beforeEach, describe, expect, it, vi } from 'vitest';

async function loadPayrollRunModule() {
  vi.resetModules();
  await import('../../../frontend/resources/ts/payroll-run.ts');
}

function flush(times) {
  var total = typeof times === 'number' ? times : 8;
  var promise = Promise.resolve();
  for (var index = 0; index < total; index += 1) {
    promise = promise.then(function () {
      return Promise.resolve();
    });
  }
  return promise;
}

function buildPayrollRunDom() {
  document.body.innerHTML = '' +
    '<div data-payroll-run-panel>' +
      '<input data-payroll-run-year value="2026" />' +
      '<select data-payroll-run-month><option value="8" selected>8</option></select>' +
      '<div data-payroll-run-error class="d-none"></div>' +
      '<div data-payroll-run-stage-title></div>' +
      '<div data-payroll-run-stage-description></div>' +
      '<div data-payroll-run-stage-badge></div>' +
      '<div data-payroll-run-primary-action-title></div>' +
      '<div data-payroll-run-primary-action-note></div>' +
      '<div data-payroll-run-primary-action-state></div>' +
      '<div data-payroll-run-action-guidance></div>' +
      '<div data-payroll-run-readiness-badge></div>' +
      '<div data-payroll-step="period"><span data-payroll-step-status></span></div>' +
      '<div data-payroll-step="calculate"><span data-payroll-step-status></span></div>' +
      '<div data-payroll-step="review"><span data-payroll-step-status></span></div>' +
      '<div data-payroll-step="export"><span data-payroll-step-status></span></div>' +
      '<div data-payroll-step="pay"><span data-payroll-step-status></span></div>' +
      '<div data-payroll-checklist-tenant-note></div>' +
      '<div data-payroll-checklist-policy-note></div>' +
      '<div data-payroll-checklist-evidence-note></div>' +
      '<div data-payroll-checklist-disburse-note></div>' +
      '<div data-payroll-checklist-overtime-note></div>' +
      '<div data-payroll-checklist-tenant></div>' +
      '<div data-payroll-checklist-policy></div>' +
      '<div data-payroll-checklist-evidence></div>' +
      '<div data-payroll-checklist-disburse></div>' +
      '<div data-payroll-checklist-overtime></div>' +
      '<div data-payroll-run-reconciliation-hint class="d-none"></div>' +
      '<div data-payroll-run-tenant-hint class="d-none"></div>' +
      '<div data-payroll-run-tax-policy-hint class="d-none"></div>' +
      '<div data-payroll-run-void-hint class="d-none"></div>' +
      '<div data-payroll-run-evidence-indicator class="d-none">' +
        '<span data-evidence-status></span>' +
        '<span data-evidence-timestamp></span>' +
      '</div>' +
      '<button type="button" data-payroll-run-calculate disabled>Calculate Draft</button>' +
      '<button type="button" data-payroll-run-void disabled>Void</button>' +
      '<button type="button" data-payroll-run-export-evidence disabled>Export Reconciliation</button>' +
      '<button type="button" data-payroll-run-disburse disabled>Tandai Dibayar Manual</button>' +
      '<button type="button" data-payroll-run-reset-payments>Reset</button>' +
      '<span data-payroll-run-emp-count>0</span>' +
      '<span data-payroll-run-selected-count>0</span>' +
      '<span data-payroll-run-line-count>0</span>' +
      '<span data-payroll-run-status></span>' +
      '<span data-payroll-run-payment-status></span>' +
      '<div data-payroll-run-empty></div>' +
      '<div data-payroll-run-grid class="d-none">' +
        '<table><tbody></tbody></table>' +
      '</div>' +
      '<input type="checkbox" data-payroll-run-select-all />' +
    '</div>' +
    '<div id="payroll_gateway_modal">' +
      '<div data-payroll-gateway-period></div>' +
      '<div data-payroll-gateway-count></div>' +
      '<div data-payroll-gateway-gross></div>' +
      '<div data-payroll-gateway-overtime></div>' +
      '<div data-payroll-gateway-deductions></div>' +
      '<div data-payroll-gateway-total></div>' +
      '<div data-payroll-gateway-status></div>' +
      '<div data-payroll-gateway-list></div>' +
      '<button type="button" data-payroll-gateway-pay>Simpan pembayaran manual</button>' +
    '</div>' +
    '<div id="payroll_detail_modal">' +
      '<div data-payroll-detail-name></div>' +
      '<div data-payroll-detail-meta></div>' +
      '<div data-payroll-detail-period></div>' +
      '<div data-payroll-detail-payment-status></div>' +
      '<div data-payroll-detail-eligibility></div>' +
      '<div data-payroll-detail-thr></div>' +
      '<div data-payroll-detail-compensation></div>' +
      '<div data-payroll-detail-gross></div>' +
      '<div data-payroll-detail-overtime></div>' +
      '<div data-payroll-detail-deductions></div>' +
      '<div data-payroll-detail-net></div>' +
      '<div data-payroll-detail-line-count></div>' +
      '<table><tbody data-payroll-detail-lines></tbody></table>' +
    '</div>' +
    '<div id="payroll_reconciliation_preview_modal">' +
      '<div data-recon-preview-period></div>' +
      '<div data-recon-preview-count></div>' +
      '<div data-recon-preview-net></div>' +
      '<div data-recon-preview-gross></div>' +
      '<div data-recon-preview-overtime></div>' +
      '<tbody data-recon-preview-body></tbody>' +
      '<button type="button" data-recon-preview-download>Download XLSX</button>' +
    '</div>';
}

describe('Payroll run wiring', function () {
  beforeEach(function () {
    vi.useRealTimers();
    buildPayrollRunDom();
    window.bootstrap = {
      Modal: class {
        static getOrCreateInstance() {
          return new window.bootstrap.Modal();
        }
        static getInstance() {
          return new window.bootstrap.Modal();
        }
        show() {}
        hide() {}
      },
    };
    window.ArcavUi = {
      showToast: vi.fn(),
      confirm: vi.fn().mockResolvedValue(true),
    };
  });

  it('posts selected numeric user ids after reconciliation export is downloaded', async function () {
    function wrap(payload) {
      return { data: payload };
    }

    var requestMock = vi.fn(async function (method, path, data) {
      var verb = String(method).toLowerCase();

      if (verb === 'get' && path === '/hcm/payroll-periods/active') {
        return wrap({
          success: true,
          data: { id: 11, periodYear: 2026, periodMonth: 8 },
        });
      }

      if (verb === 'get' && path === '/hcm/payroll-periods/11') {
        return wrap({
          success: true,
          data: {
            id: 11,
            status: 'posted',
            latestRun: { id: 44, status: 'draft', paymentStatus: 'unpaid', period: { status: 'posted' } },
          },
        });
      }

      if (verb === 'get' && path === '/hcm/payroll-runs/44') {
        return wrap({
          success: true,
          data: {
            run: { id: 44, status: 'draft', paymentStatus: 'unpaid', period: { periodYear: 2026, periodMonth: 8, status: 'posted' } },
            lines: [
              {
                userId: 7,
                userName: 'Nadia',
                kind: 'addition',
                componentName: 'Gaji Pokok',
                componentCode: 'gaji_pokok',
                category: 'salary',
                amount: 5000000,
                sortOrder: 1,
                affectsNetPay: true,
                paymentStatus: 'unpaid',
                meta: { userName: 'Nadia' },
              },
              {
                userId: 7,
                userName: 'Nadia',
                kind: 'addition',
                componentName: 'Upah Lembur',
                componentCode: 'upah_lembur',
                category: 'overtime',
                amount: 125000,
                sortOrder: 2,
                affectsNetPay: true,
                paymentStatus: 'unpaid',
                meta: { userName: 'Nadia' },
              },
            ],
            specialRecipients: { thrUserIds: [], compensationUserIds: [] },
          },
        });
      }

      if (verb === 'get' && path === '/reconciliation/exports') {
        return wrap({ success: true, data: [] });
      }

      if (verb === 'post' && path === '/reconciliation/exports') {
        return wrap({
          success: true,
          data: {
            id: 9,
            filePath: 'reconciliation/company_1/payroll-run-44.xlsx',
          },
        });
      }

      if (verb === 'post' && path === '/hcm/payroll-runs/44/disburse') {
        return wrap({
          success: true,
          data: {
            gatewayReference: 'MANUAL-REF-44',
          },
        });
      }

      throw new Error('Unexpected request: ' + method + ' ' + path + ' ' + JSON.stringify(data || null));
    });

    var downloadMock = vi.fn().mockResolvedValue(undefined);

    window.AuthApi = {
      request: requestMock,
      downloadV1Binary: downloadMock,
    };

    await loadPayrollRunModule();
    await flush();

    var disburseButton = document.querySelector('[data-payroll-run-disburse]');
    expect(disburseButton.disabled).toBe(true);

    // Click export button → opens preview modal (does not trigger download yet)
    document.querySelector('[data-payroll-run-export-evidence]').click();
    await flush();

    // Download mock should NOT have been called yet
    expect(downloadMock).not.toHaveBeenCalled();

    // Click the Download button inside the preview modal → triggers actual export + download
    document.querySelector('[data-recon-preview-download]').click();
    await flush();

    expect(document.querySelector('[data-payroll-checklist-overtime]').textContent).toBe('ADA');
    expect(document.querySelector('[data-payroll-checklist-overtime-note]').textContent).toContain('overtime_total');
    expect(document.querySelector('[data-recon-preview-overtime]').textContent).toContain('125');
    expect(downloadMock).toHaveBeenCalledWith('/reconciliation/exports/9/download', 'payroll-run-44.xlsx');
    expect(disburseButton.disabled).toBe(false);
    expect(document.querySelector('[data-payroll-run-stage-title]').textContent).toContain('Tandai Dibayar Manual');
    expect(document.querySelector('[data-payroll-step="pay"] [data-payroll-step-status]').textContent).toBe('ACTIVE');
    expect(document.querySelector('[data-payroll-checklist-evidence]').textContent).toBe('SIAP');

    disburseButton.click();
    await flush();

    expect(document.querySelector('[data-payroll-gateway-overtime]').textContent).toContain('125');
    document.querySelector('[data-payroll-gateway-pay]').click();
    await flush();

    var disburseCall = requestMock.mock.calls.find(function (call) {
      return call[0] === 'post' && call[1] === '/hcm/payroll-runs/44/disburse';
    });

    expect(disburseCall).toBeTruthy();
    expect(disburseCall[2]).toEqual({ userIds: [7] });
  });

  it('keeps calculate draft enabled for finalized unpaid run and still supports void', async function () {
    function wrap(payload) {
      return { data: payload };
    }

    var requestMock = vi.fn(async function (method, path) {
      var verb = String(method).toLowerCase();

      if (verb === 'get' && path === '/hcm/payroll-periods/active') {
        return wrap({ success: true, data: { id: 11, periodYear: 2026, periodMonth: 8 } });
      }

      if (verb === 'get' && path === '/hcm/payroll-periods/11') {
        return wrap({
          success: true,
          data: {
            id: 11,
            status: 'posted',
            latestRun: { id: 44, status: 'finalized', paymentStatus: 'unpaid', period: { status: 'posted' } },
          },
        });
      }

      if (verb === 'get' && path === '/hcm/payroll-runs/44') {
        return wrap({
          success: true,
          data: {
            run: { id: 44, status: 'finalized', paymentStatus: 'unpaid', period: { periodYear: 2026, periodMonth: 8, status: 'posted' } },
            lines: [
              {
                userId: 7,
                userName: 'Nadia',
                kind: 'addition',
                componentName: 'Gaji Pokok',
                componentCode: 'gaji_pokok',
                category: 'salary',
                amount: 5000000,
                sortOrder: 1,
                affectsNetPay: true,
                paymentStatus: 'unpaid',
                meta: { userName: 'Nadia' },
              },
            ],
            specialRecipients: { thrUserIds: [], compensationUserIds: [] },
          },
        });
      }

      if (verb === 'get' && path === '/reconciliation/exports') {
        return wrap({ success: true, data: [] });
      }

      if (verb === 'post' && path === '/hcm/payroll-runs/44/void') {
        return wrap({
          success: true,
          data: {
            id: 44,
            status: 'void',
            paymentStatus: 'unpaid',
            period: { periodYear: 2026, periodMonth: 8, status: 'open' },
          },
        });
      }

      throw new Error('Unexpected request: ' + method + ' ' + path);
    });

    window.AuthApi = {
      request: requestMock,
      downloadV1Binary: vi.fn(),
    };

    await loadPayrollRunModule();
    await flush();

    var calculateButton = document.querySelector('[data-payroll-run-calculate]');
    var disburseButton = document.querySelector('[data-payroll-run-disburse]');

    expect(calculateButton.disabled).toBe(false);
    expect(disburseButton.disabled).toBe(true);
    expect(document.querySelector('[data-payroll-run-stage-title]').textContent).toContain('Export Reconciliation');
    expect(document.querySelector('[data-payroll-step="export"] [data-payroll-step-status]').textContent).toBe('LOCKED');
  });

  it('renders workflow checklist and stage emphasis for draft review before export', async function () {
    function wrap(payload) {
      return { data: payload };
    }

    window.AuthApi = {
      request: vi.fn(async function (method, path) {
        var verb = String(method).toLowerCase();

        if (verb === 'get' && path === '/hcm/payroll-periods/active') {
          return wrap({ success: true, data: { id: 11, periodYear: 2026, periodMonth: 8 } });
        }

        if (verb === 'get' && path === '/hcm/payroll-periods/11') {
          return wrap({
            success: true,
            data: {
              id: 11,
              status: 'posted',
              latestRun: { id: 44, status: 'draft', paymentStatus: 'unpaid', period: { status: 'posted' } },
            },
          });
        }

        if (verb === 'get' && path === '/hcm/payroll-runs/44') {
          return wrap({
            success: true,
            data: {
              run: {
                id: 44,
                status: 'draft',
                paymentStatus: 'unpaid',
                period: { periodYear: 2026, periodMonth: 8, status: 'posted' },
                taxGovernancePolicy: { policyCode: 'TER-A', version: 3 },
              },
              lines: [
                {
                  userId: 7,
                  userName: 'Nadia',
                  kind: 'addition',
                  componentName: 'Gaji Pokok',
                  componentCode: 'gaji_pokok',
                  category: 'salary',
                  amount: 5000000,
                  sortOrder: 1,
                  affectsNetPay: true,
                  paymentStatus: 'unpaid',
                  meta: { userName: 'Nadia' },
                },
              ],
              specialRecipients: { thrUserIds: [], compensationUserIds: [] },
            },
          });
        }

        if (verb === 'get' && path === '/reconciliation/exports') {
          return wrap({ success: true, data: [] });
        }

        throw new Error('Unexpected request: ' + method + ' ' + path);
      }),
      downloadV1Binary: vi.fn(),
      getTenantContext: vi.fn(function () {
        return { activeCompanyId: 7, activeCompanyName: 'PT Demo Tenant' };
      }),
    };

    await loadPayrollRunModule();
    await flush();

    expect(document.querySelector('[data-payroll-run-stage-title]').textContent).toContain('Export Reconciliation');
    expect(document.querySelector('[data-payroll-run-primary-action-title]').textContent).toContain('evidence reconciliation');
    expect(document.querySelector('[data-payroll-step="calculate"] [data-payroll-step-status]').textContent).toBe('DONE');
    expect(document.querySelector('[data-payroll-step="review"] [data-payroll-step-status]').textContent).toBe('ACTIVE');
    expect(document.querySelector('[data-payroll-step="export"] [data-payroll-step-status]').textContent).toBe('ACTIVE');
    expect(document.querySelector('[data-payroll-checklist-tenant]').textContent).toBe('SIAP');
    expect(document.querySelector('[data-payroll-checklist-policy]').textContent).toBe('SIAP');
    expect(document.querySelector('[data-payroll-checklist-disburse]').textContent).toBe('SIAP');
    expect(document.querySelector('[data-payroll-checklist-tenant-note]').textContent).toContain('PT Demo Tenant');
  });

  it('loads and submits payroll work configurator forms', async function () {
    function wrap(payload) {
      return { data: payload };
    }

    var panel = document.createElement('div');
    panel.setAttribute('data-payroll-work-config-panel', '1');
    panel.innerHTML = '' +
      '<div data-payroll-work-error class="d-none"></div>' +
      '<button type="button" data-payroll-work-auto-generate>Auto</button>' +
      '<button type="button" data-payroll-work-refresh>Refresh</button>' +
      '<form data-payroll-work-profile-form>' +
        '<input data-payroll-work-profile-code />' +
        '<input data-payroll-work-profile-name />' +
        '<select data-payroll-work-profile-mode><option value="office_hour" selected>Office Hour</option><option value="shift_worker">Shift</option></select>' +
        '<select data-payroll-work-profile-day-type><option value="workday" selected>Workday</option><option value="public_holiday">Holiday</option></select>' +
        '<select data-payroll-work-profile-weekly-days><option value="5" selected>5</option><option value="6">6</option></select>' +
        '<input type="checkbox" data-payroll-work-profile-default />' +
        '<button type="submit" data-payroll-work-profile-submit>Simpan Profile</button>' +
      '</form>' +
      '<table><tbody data-payroll-work-profiles-body></tbody></table>' +
      '<form data-payroll-work-arrangement-form>' +
        '<select data-payroll-work-arrangement-user></select>' +
        '<select data-payroll-work-arrangement-profile></select>' +
        '<select data-payroll-work-arrangement-mode><option value="office_hour" selected>Office Hour</option><option value="shift_worker">Shift</option></select>' +
        '<select data-payroll-work-arrangement-day-type><option value="" selected>Auto</option><option value="workday">Workday</option></select>' +
        '<select data-payroll-work-arrangement-weekly-days><option value="" selected>Default</option><option value="5">5</option></select>' +
        '<input type="date" data-payroll-work-arrangement-effective-from />' +
        '<input type="date" data-payroll-work-arrangement-effective-to />' +
        '<button type="submit" data-payroll-work-arrangement-submit>Simpan Assignment</button>' +
      '</form>' +
      '<table><tbody data-payroll-work-arrangements-body></tbody></table>';
    document.body.appendChild(panel);

    var requestMock = vi.fn(async function (method, path, data) {
      var verb = String(method).toLowerCase();

      if (verb === 'get' && path === '/hcm/payroll-periods/active') {
        return wrap({ success: true, data: { id: 11, periodYear: 2026, periodMonth: 8 } });
      }

      if (verb === 'get' && path === '/hcm/payroll-periods/11') {
        return wrap({
          success: true,
          data: {
            id: 11,
            status: 'posted',
            latestRun: { id: 44, status: 'draft', paymentStatus: 'unpaid', period: { status: 'posted' } },
          },
        });
      }

      if (verb === 'get' && path === '/hcm/payroll-runs/44') {
        return wrap({
          success: true,
          data: {
            run: { id: 44, status: 'draft', paymentStatus: 'unpaid', period: { periodYear: 2026, periodMonth: 8, status: 'posted' } },
            lines: [],
            specialRecipients: { thrUserIds: [], compensationUserIds: [] },
          },
        });
      }

      if (verb === 'get' && path === '/reconciliation/exports') {
        return wrap({ success: true, data: [] });
      }

      if (verb === 'get' && path === '/hcm/payroll/work-profiles') {
        return wrap({
          success: true,
          data: [
            {
              id: 1,
              code: 'OFFICE_5',
              name: 'Office 5 Hari',
              arrangementMode: 'office_hour',
              defaultDayType: 'workday',
              weeklyWorkDays: 5,
              isDefault: true,
            },
          ],
        });
      }

      if (verb === 'get' && path === '/hcm/payroll/work-arrangements?perPage=25') {
        return wrap({ success: true, data: [] });
      }

      if (verb === 'get' && path === '/hcm/employees?page=1&perPage=100') {
        return wrap({
          success: true,
          data: [{ id: 9, userId: 9, name: 'Dina', email: 'dina@example.test' }],
        });
      }

      if (verb === 'post' && path === '/hcm/payroll/work-profiles') {
        return wrap({ success: true, data: data });
      }

      if (verb === 'post' && path === '/hcm/payroll/work-arrangements') {
        return wrap({ success: true, data: data });
      }

      throw new Error('Unexpected request: ' + method + ' ' + path + ' ' + JSON.stringify(data || null));
    });

    window.AuthApi = {
      request: requestMock,
      downloadV1Binary: vi.fn(),
    };

    await loadPayrollRunModule();
    await flush();

    var userSelect = document.querySelector('[data-payroll-work-arrangement-user]');
    var profileSelect = document.querySelector('[data-payroll-work-arrangement-profile]');
    expect(userSelect.options.length).toBeGreaterThan(1);
    expect(profileSelect.options.length).toBeGreaterThan(1);

    document.querySelector('[data-payroll-work-profile-code]').value = 'SHIFT_6';
    document.querySelector('[data-payroll-work-profile-name]').value = 'Shift 6 Hari';
    document.querySelector('[data-payroll-work-profile-form]').dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
    await flush();

    userSelect.value = '9';
    profileSelect.value = '1';
    document.querySelector('[data-payroll-work-arrangement-effective-from]').value = '2026-08-01';
    document.querySelector('[data-payroll-work-arrangement-form]').dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
    await flush();

    var profilePost = requestMock.mock.calls.find(function (call) {
      return call[0] === 'post' && call[1] === '/hcm/payroll/work-profiles';
    });
    var arrangementPost = requestMock.mock.calls.find(function (call) {
      return call[0] === 'post' && call[1] === '/hcm/payroll/work-arrangements';
    });

    expect(profilePost).toBeTruthy();
    expect(profilePost[2]).toMatchObject({
      code: 'SHIFT_6',
      name: 'Shift 6 Hari',
    });

    expect(arrangementPost).toBeTruthy();
    expect(arrangementPost[2]).toMatchObject({
      userId: 9,
      profileId: 1,
      effectiveFrom: '2026-08-01',
    });
  });

  it('uses monthly payroll rows as employee fallback when employee api is unavailable', async function () {
    function wrap(payload) {
      return { data: payload };
    }

    var panel = document.createElement('div');
    panel.setAttribute('data-payroll-work-config-panel', '1');
    panel.innerHTML = '' +
      '<div data-payroll-work-error class="d-none"></div>' +
      '<button type="button" data-payroll-work-auto-generate>Auto</button>' +
      '<button type="button" data-payroll-work-refresh>Refresh</button>' +
      '<form data-payroll-work-profile-form>' +
        '<input data-payroll-work-profile-code />' +
        '<input data-payroll-work-profile-name />' +
        '<select data-payroll-work-profile-mode><option value="office_hour" selected>Office Hour</option></select>' +
        '<select data-payroll-work-profile-day-type><option value="workday" selected>Workday</option></select>' +
        '<select data-payroll-work-profile-weekly-days><option value="5" selected>5</option></select>' +
        '<input type="checkbox" data-payroll-work-profile-default />' +
        '<button type="submit" data-payroll-work-profile-submit>Simpan Profile</button>' +
      '</form>' +
      '<table><tbody data-payroll-work-profiles-body></tbody></table>' +
      '<form data-payroll-work-arrangement-form>' +
        '<select data-payroll-work-arrangement-user></select>' +
        '<select data-payroll-work-arrangement-profile></select>' +
        '<select data-payroll-work-arrangement-mode><option value="office_hour" selected>Office Hour</option></select>' +
        '<select data-payroll-work-arrangement-day-type><option value="" selected>Auto</option></select>' +
        '<select data-payroll-work-arrangement-weekly-days><option value="" selected>Default</option></select>' +
        '<input type="date" data-payroll-work-arrangement-effective-from />' +
        '<input type="date" data-payroll-work-arrangement-effective-to />' +
        '<button type="submit" data-payroll-work-arrangement-submit>Simpan Assignment</button>' +
      '</form>' +
      '<table><tbody data-payroll-work-arrangements-body></tbody></table>';
    document.body.appendChild(panel);

    var requestMock = vi.fn(async function (method, path) {
      var verb = String(method).toLowerCase();

      if (verb === 'get' && path === '/hcm/payroll-periods/active') {
        return wrap({ success: true, data: { id: 11, periodYear: 2026, periodMonth: 8 } });
      }

      if (verb === 'get' && path === '/hcm/payroll-periods/11') {
        return wrap({
          success: true,
          data: {
            id: 11,
            status: 'posted',
            latestRun: { id: 44, status: 'draft', paymentStatus: 'unpaid', period: { status: 'posted' } },
          },
        });
      }

      if (verb === 'get' && path === '/hcm/payroll-runs/44') {
        return wrap({
          success: true,
          data: {
            run: { id: 44, status: 'draft', paymentStatus: 'unpaid', period: { periodYear: 2026, periodMonth: 8, status: 'posted' } },
            lines: [
              {
                userId: 77,
                userName: 'Fallback Dina',
                kind: 'addition',
                componentName: 'Gaji Pokok',
                componentCode: 'gaji_pokok',
                category: 'salary',
                amount: 5000000,
                sortOrder: 1,
                affectsNetPay: true,
                paymentStatus: 'unpaid',
                meta: { userName: 'Fallback Dina' },
              },
            ],
            specialRecipients: { thrUserIds: [], compensationUserIds: [] },
          },
        });
      }

      if (verb === 'get' && path === '/hcm/payroll/work-profiles') {
        return wrap({ success: true, data: [] });
      }

      if (verb === 'get' && path === '/hcm/payroll/work-arrangements?perPage=25') {
        return wrap({ success: true, data: [] });
      }

      if (verb === 'get' && path === '/hcm/employees?page=1&perPage=100') {
        throw new Error('Forbidden employee directory');
      }

      if (verb === 'get' && path === '/reconciliation/exports') {
        return wrap({ success: true, data: [] });
      }

      throw new Error('Unexpected request: ' + method + ' ' + path);
    });

    window.AuthApi = {
      request: requestMock,
      downloadV1Binary: vi.fn(),
    };

    await loadPayrollRunModule();
    await flush();

    var userSelect = document.querySelector('[data-payroll-work-arrangement-user]');
    var optionTexts = Array.from(userSelect.options).map(function (opt) { return opt.textContent || ''; }).join(' | ');
    expect(optionTexts).toContain('Fallback Dina');
  });

  it('auto generates work arrangements from calculate draft without config panel', async function () {
    function wrap(payload) {
      return { data: payload };
    }

    var requestMock = vi.fn(async function (method, path, data) {
      var verb = String(method).toLowerCase();

      if (verb === 'get' && path === '/hcm/payroll-periods/active') {
        return wrap({ success: true, data: { id: 11, periodYear: 2026, periodMonth: 8 } });
      }

      if (verb === 'get' && path === '/hcm/payroll-periods/11') {
        return wrap({ success: true, data: { id: 11, status: 'posted', latestRun: null } });
      }

      if (verb === 'post' && path === '/hcm/payroll-periods/11/calculate-draft') {
        return wrap({
          success: true,
          data: {
            run: { id: 44, status: 'draft', paymentStatus: 'unpaid', period: { status: 'posted' } },
          },
        });
      }

      if (verb === 'get' && path === '/hcm/payroll-runs/44') {
        return wrap({
          success: true,
          data: {
            run: { id: 44, status: 'draft', paymentStatus: 'unpaid', period: { periodYear: 2026, periodMonth: 8, status: 'posted' } },
            lines: [
              { userId: 9, userName: 'Dina', kind: 'addition', amount: 1000, paymentStatus: 'unpaid', meta: { userName: 'Dina' } },
              { userId: 10, userName: 'Raka', kind: 'addition', amount: 1000, paymentStatus: 'unpaid', meta: { userName: 'Raka' } },
            ],
            specialRecipients: { thrUserIds: [], compensationUserIds: [] },
          },
        });
      }

      if (verb === 'get' && path === '/hcm/payroll/work-profiles') {
        return wrap({
          success: true,
          data: [
            {
              id: 1,
              code: 'OFFICE_5',
              name: 'Office 5 Hari',
              arrangementMode: 'office_hour',
              defaultDayType: 'workday',
              weeklyWorkDays: 5,
              isDefault: true,
            },
          ],
        });
      }

      if (verb === 'get' && path === '/hcm/payroll/work-arrangements?perPage=25') {
        return wrap({
          success: true,
          data: [
            {
              id: 91,
              userId: 9,
              userName: 'Dina',
              profileId: 1,
              arrangementMode: 'office_hour',
              defaultDayType: 'workday',
              weeklyWorkDays: 5,
              effectiveFrom: '2026-07-01',
              effectiveTo: null,
            },
          ],
        });
      }

      if (verb === 'get' && path === '/hcm/employees?page=1&perPage=100') {
        return wrap({
          success: true,
          data: [
            { id: 9, userId: 9, name: 'Dina', email: 'dina@example.test' },
            { id: 10, userId: 10, name: 'Raka', email: 'raka@example.test' },
          ],
        });
      }

      if (verb === 'get' && path === '/reconciliation/exports') {
        return wrap({ success: true, data: [] });
      }

      if (verb === 'post' && path === '/hcm/payroll/work-arrangements') {
        return wrap({ success: true, data: data });
      }

      throw new Error('Unexpected request: ' + method + ' ' + path + ' ' + JSON.stringify(data || null));
    });

    window.AuthApi = {
      request: requestMock,
      downloadV1Binary: vi.fn(),
    };

    await loadPayrollRunModule();
    await flush();

    document.querySelector('[data-payroll-run-calculate]').click();
    await flush();

    var postCalls = requestMock.mock.calls.filter(function (call) {
      return call[0] === 'post' && call[1] === '/hcm/payroll/work-arrangements';
    });

    expect(postCalls.length).toBe(1);
    expect(postCalls[0][2]).toMatchObject({
      userId: 10,
      profileId: 1,
      arrangementMode: 'office_hour',
      effectiveFrom: '2026-08-01',
    });
  });

  it('auto generates arrangements from active run users and skips active ones', async function () {
    function wrap(payload) {
      return { data: payload };
    }

    var panel = document.createElement('div');
    panel.setAttribute('data-payroll-work-config-panel', '1');
    panel.innerHTML = '' +
      '<div data-payroll-work-error class="d-none"></div>' +
      '<button type="button" data-payroll-work-auto-generate>Auto</button>' +
      '<button type="button" data-payroll-work-refresh>Refresh</button>' +
      '<form data-payroll-work-profile-form>' +
        '<input data-payroll-work-profile-code />' +
        '<input data-payroll-work-profile-name />' +
        '<select data-payroll-work-profile-mode><option value="office_hour" selected>Office Hour</option><option value="shift_worker">Shift</option></select>' +
        '<select data-payroll-work-profile-day-type><option value="workday" selected>Workday</option><option value="public_holiday">Holiday</option></select>' +
        '<select data-payroll-work-profile-weekly-days><option value="5" selected>5</option><option value="6">6</option></select>' +
        '<input type="checkbox" data-payroll-work-profile-default />' +
        '<button type="submit" data-payroll-work-profile-submit>Simpan Profile</button>' +
      '</form>' +
      '<table><tbody data-payroll-work-profiles-body></tbody></table>' +
      '<form data-payroll-work-arrangement-form>' +
        '<select data-payroll-work-arrangement-user></select>' +
        '<select data-payroll-work-arrangement-profile></select>' +
        '<select data-payroll-work-arrangement-mode><option value="office_hour" selected>Office Hour</option><option value="shift_worker">Shift</option></select>' +
        '<select data-payroll-work-arrangement-day-type><option value="" selected>Auto</option><option value="workday">Workday</option></select>' +
        '<select data-payroll-work-arrangement-weekly-days><option value="" selected>Default</option><option value="5">5</option></select>' +
        '<input type="date" data-payroll-work-arrangement-effective-from />' +
        '<input type="date" data-payroll-work-arrangement-effective-to />' +
        '<button type="submit" data-payroll-work-arrangement-submit>Simpan Assignment</button>' +
      '</form>' +
      '<table><tbody data-payroll-work-arrangements-body></tbody></table>';
    document.body.appendChild(panel);

    var requestMock = vi.fn(async function (method, path, data) {
      var verb = String(method).toLowerCase();

      if (verb === 'get' && path === '/hcm/payroll-periods/active') {
        return wrap({ success: true, data: { id: 11, periodYear: 2026, periodMonth: 8 } });
      }

      if (verb === 'get' && path === '/hcm/payroll-periods/11') {
        return wrap({
          success: true,
          data: {
            id: 11,
            status: 'posted',
            latestRun: { id: 44, status: 'draft', paymentStatus: 'unpaid', period: { status: 'posted' } },
          },
        });
      }

      if (verb === 'get' && path === '/hcm/payroll-runs/44') {
        return wrap({
          success: true,
          data: {
            run: { id: 44, status: 'draft', paymentStatus: 'unpaid', period: { periodYear: 2026, periodMonth: 8, status: 'posted' } },
            lines: [
              { userId: 9, userName: 'Dina', kind: 'addition', amount: 1000, paymentStatus: 'unpaid', meta: { userName: 'Dina' } },
              { userId: 10, userName: 'Raka', kind: 'addition', amount: 1000, paymentStatus: 'unpaid', meta: { userName: 'Raka' } },
            ],
            specialRecipients: { thrUserIds: [], compensationUserIds: [] },
          },
        });
      }

      if (verb === 'get' && path === '/reconciliation/exports') {
        return wrap({ success: true, data: [] });
      }

      if (verb === 'get' && path === '/hcm/payroll/work-profiles') {
        return wrap({
          success: true,
          data: [
            {
              id: 1,
              code: 'OFFICE_5',
              name: 'Office 5 Hari',
              arrangementMode: 'office_hour',
              defaultDayType: 'workday',
              weeklyWorkDays: 5,
              isDefault: true,
            },
          ],
        });
      }

      if (verb === 'get' && path === '/hcm/payroll/work-arrangements?perPage=25') {
        return wrap({
          success: true,
          data: [
            {
              id: 91,
              userId: 9,
              userName: 'Dina',
              profileId: 1,
              arrangementMode: 'office_hour',
              defaultDayType: 'workday',
              weeklyWorkDays: 5,
              effectiveFrom: '2026-07-01',
              effectiveTo: null,
            },
          ],
        });
      }

      if (verb === 'get' && path === '/hcm/employees?page=1&perPage=100') {
        return wrap({
          success: true,
          data: [
            { id: 9, userId: 9, name: 'Dina', email: 'dina@example.test' },
            { id: 10, userId: 10, name: 'Raka', email: 'raka@example.test' },
          ],
        });
      }

      if (verb === 'post' && path === '/hcm/payroll/work-arrangements') {
        return wrap({ success: true, data: data });
      }

      throw new Error('Unexpected request: ' + method + ' ' + path + ' ' + JSON.stringify(data || null));
    });

    window.AuthApi = {
      request: requestMock,
      downloadV1Binary: vi.fn(),
    };

    await loadPayrollRunModule();
    await flush();

    document.querySelector('[data-payroll-work-auto-generate]').click();
    await flush();

    var postCalls = requestMock.mock.calls.filter(function (call) {
      return call[0] === 'post' && call[1] === '/hcm/payroll/work-arrangements';
    });

    expect(postCalls.length).toBe(1);
    expect(postCalls[0][2]).toMatchObject({
      userId: 10,
      profileId: 1,
      arrangementMode: 'office_hour',
      effectiveFrom: '2026-08-01',
    });
  });

  it('loads payroll settings preview and submits updated policy', async function () {
    function wrap(payload) {
      return { data: payload };
    }

    var panel = document.createElement('div');
    panel.setAttribute('data-payroll-settings-panel', '1');
    panel.innerHTML = '' +
      '<div data-payroll-settings-feedback class="d-none"></div>' +
      '<span data-payroll-settings-stage></span>' +
      '<form data-payroll-settings-form>' +
        '<input type="number" data-payroll-settings-payday-day />' +
        '<input type="number" data-payroll-settings-cutoff-offset />' +
        '<select data-payroll-settings-timezone>' +
          '<option value="Asia/Jakarta">Asia/Jakarta</option>' +
          '<option value="Asia/Makassar">Asia/Makassar</option>' +
          '<option value="Asia/Jayapura">Asia/Jayapura</option>' +
        '</select>' +
        '<select data-payroll-settings-holiday-strategy>' +
          '<option value="previous_working_day">previous_working_day</option>' +
          '<option value="next_working_day">next_working_day</option>' +
          '<option value="exact_calendar_day">exact_calendar_day</option>' +
        '</select>' +
        '<input type="checkbox" data-payroll-settings-disburse-early />' +
        '<button type="button" data-payroll-settings-confirm>Simpan policy payroll</button>' +
      '</form>' +
      '<div data-payroll-settings-preview-period></div>' +
      '<div data-payroll-settings-preview-payday></div>' +
      '<div data-payroll-settings-preview-cutoff></div>' +
      '<div data-payroll-settings-preview-note></div>' +
      '<div id="payroll_settings_confirm_modal" class="modal">' +
        '<button type="button" data-payroll-settings-save>Ya Simpan</button>' +
      '</div>';
    document.body.appendChild(panel);

    var requestMock = vi.fn(async function (method, path, data) {
      var verb = String(method).toLowerCase();

      if (verb === 'get' && path === '/hcm/payroll/settings') {
        return wrap({
          success: true,
          data: {
            paydayDay: 28,
            cutoffOffsetDays: 3,
            payrollTimezone: 'Asia/Jakarta',
            disburseBeforePaydayAllowed: false,
            paydayHolidayStrategy: 'previous_working_day',
          },
        });
      }

      if (verb === 'get' && path === '/hcm/payroll-periods/active') {
        return wrap({ success: true, data: { id: 11, periodYear: 2026, periodMonth: 8 } });
      }

      if (verb === 'get' && path === '/hcm/payroll-periods/11') {
        return wrap({ success: true, data: { id: 11, status: 'open', latestRun: null } });
      }

      if (verb === 'put' && path === '/hcm/payroll/settings') {
        return wrap({ success: true, data: data });
      }

      throw new Error('Unexpected request: ' + method + ' ' + path + ' ' + JSON.stringify(data || null));
    });

    window.AuthApi = {
      request: requestMock,
      downloadV1Binary: vi.fn(),
    };

    await loadPayrollRunModule();
    await flush();

    expect(document.querySelector('[data-payroll-settings-preview-period]').textContent).toContain('08/2026');
    expect(document.querySelector('[data-payroll-settings-preview-payday]').textContent.length).toBeGreaterThan(0);
    expect(document.querySelector('[data-payroll-settings-stage]').textContent.length).toBeGreaterThan(0);

    document.querySelector('[data-payroll-settings-payday-day]').value = '27';
    document.querySelector('[data-payroll-settings-cutoff-offset]').value = '2';
    document.querySelector('[data-payroll-settings-timezone]').value = 'Asia/Makassar';
    document.querySelector('[data-payroll-settings-holiday-strategy]').value = 'next_working_day';
    document.querySelector('[data-payroll-settings-disburse-early]').checked = true;
    // Click confirm button (opens modal), then click save inside modal
    document.querySelector('[data-payroll-settings-confirm]').click();
    await flush();
    document.querySelector('[data-payroll-settings-save]').click();
    await flush();

    var settingsPut = requestMock.mock.calls.find(function (call) {
      return call[0] === 'put' && call[1] === '/hcm/payroll/settings';
    });

    expect(settingsPut).toBeTruthy();
    expect(settingsPut[2]).toEqual({
      paydayDay: 27,
      cutoffOffsetDays: 2,
      payrollTimezone: 'Asia/Makassar',
      disburseBeforePaydayAllowed: true,
      paydayHolidayStrategy: 'next_working_day',
    });
    expect(document.querySelector('[data-payroll-settings-feedback]').textContent).toContain('snapshot lama');
  });

  it('renders pre-cutoff and post-cutoff policy preview for short month and leap year periods', async function () {
    function wrap(payload) {
      return { data: payload };
    }

    var panel = document.createElement('div');
    panel.setAttribute('data-payroll-settings-panel', '1');
    panel.innerHTML = '' +
      '<div data-payroll-settings-feedback class="d-none"></div>' +
      '<span data-payroll-settings-stage></span>' +
      '<form data-payroll-settings-form>' +
        '<input type="number" data-payroll-settings-payday-day />' +
        '<input type="number" data-payroll-settings-cutoff-offset />' +
        '<select data-payroll-settings-timezone><option value="Asia/Jakarta">Asia/Jakarta</option></select>' +
        '<select data-payroll-settings-holiday-strategy><option value="previous_working_day">previous_working_day</option></select>' +
        '<input type="checkbox" data-payroll-settings-disburse-early />' +
        '<button type="submit" data-payroll-settings-save>Simpan policy payroll</button>' +
      '</form>' +
      '<div data-payroll-settings-preview-period></div>' +
      '<div data-payroll-settings-preview-payday></div>' +
      '<div data-payroll-settings-preview-cutoff></div>' +
      '<div data-payroll-settings-preview-note></div>';
    document.body.appendChild(panel);

    vi.useFakeTimers();
    vi.setSystemTime(new Date('2026-04-26T10:00:00Z'));

    document.querySelector('[data-payroll-run-year]').value = '2026';
    document.querySelector('[data-payroll-run-month]').innerHTML = '<option value="4" selected>4</option>';

    var settingsPayload = {
      paydayDay: 31,
      cutoffOffsetDays: 3,
      payrollTimezone: 'Asia/Jakarta',
      disburseBeforePaydayAllowed: false,
      paydayHolidayStrategy: 'previous_working_day',
    };

    var requestMock = vi.fn(async function (method, path) {
      var verb = String(method).toLowerCase();

      if (verb === 'get' && path === '/hcm/payroll/settings') {
        return wrap({ success: true, data: settingsPayload });
      }

      if (verb === 'get' && path === '/hcm/payroll-periods/active') {
        return wrap({ success: true, data: { id: 11, periodYear: 2026, periodMonth: 4 } });
      }

      if (verb === 'get' && path === '/hcm/payroll-periods/11') {
        return wrap({ success: true, data: { id: 11, status: 'open', latestRun: null } });
      }

      throw new Error('Unexpected request: ' + method + ' ' + path);
    });

    window.AuthApi = {
      request: requestMock,
      downloadV1Binary: vi.fn(),
    };

    await loadPayrollRunModule();
    await flush();

    document.querySelector('[data-payroll-settings-cutoff-offset]').dispatchEvent(new Event('input', { bubbles: true }));
    await flush();

    expect(document.querySelector('[data-payroll-settings-stage]').textContent).toContain('Pre-cutoff');
    expect(document.querySelector('[data-payroll-settings-preview-period]').textContent).toContain('04/2026');
    expect(document.querySelector('[data-payroll-settings-preview-note]').textContent).toContain('masih bisa direfresh');

    vi.setSystemTime(new Date('2028-02-28T10:00:00Z'));
    document.querySelector('[data-payroll-run-year]').value = '2028';
    document.querySelector('[data-payroll-run-month]').innerHTML = '<option value="2" selected>2</option>';
    document.querySelector('[data-payroll-settings-cutoff-offset]').dispatchEvent(new Event('change', { bubbles: true }));
    await flush();

    expect(document.querySelector('[data-payroll-settings-stage]').textContent).toContain('Post-cutoff');
    expect(document.querySelector('[data-payroll-settings-preview-period]').textContent).toContain('02/2028');
    expect(document.querySelector('[data-payroll-settings-preview-note]').textContent).toContain('periode berikutnya');
  });

  it('enforces post-cutoff review-only guardrail on export and disburse actions', async function () {
    function wrap(payload) {
      return { data: payload };
    }

    vi.useFakeTimers();
    vi.setSystemTime(new Date('2026-08-26T03:00:00Z'));

    var requestMock = vi.fn(async function (method, path) {
      var verb = String(method).toLowerCase();

      if (verb === 'get' && path === '/hcm/payroll-periods/active') {
        return wrap({ success: true, data: { id: 11, periodYear: 2026, periodMonth: 8 } });
      }

      if (verb === 'get' && path === '/hcm/payroll-periods/11') {
        return wrap({
          success: true,
          data: {
            id: 11,
            status: 'posted',
            latestRun: { id: 44, status: 'draft', paymentStatus: 'unpaid', period: { status: 'posted' } },
          },
        });
      }

      if (verb === 'get' && path === '/hcm/payroll-runs/44') {
        return wrap({
          success: true,
          data: {
            run: {
              id: 44,
              status: 'draft',
              paymentStatus: 'unpaid',
              period: { periodYear: 2026, periodMonth: 8, status: 'posted' },
              policySnapshot: {
                paydayDay: 28,
                cutoffOffsetDays: 3,
                payrollTimezone: 'Asia/Jakarta',
                disburseBeforePaydayAllowed: false,
                resolvedPaydayDate: '2026-08-28',
                resolvedCutoffDate: '2026-08-25',
              },
            },
            lines: [
              {
                userId: 7,
                userName: 'Nadia',
                kind: 'addition',
                componentName: 'Gaji Pokok',
                componentCode: 'gaji_pokok',
                category: 'salary',
                amount: 5000000,
                sortOrder: 1,
                affectsNetPay: true,
                paymentStatus: 'unpaid',
                meta: { userName: 'Nadia' },
              },
            ],
            specialRecipients: { thrUserIds: [], compensationUserIds: [] },
          },
        });
      }

      if (verb === 'get' && path === '/reconciliation/exports') {
        return wrap({ success: true, data: [] });
      }

      if (verb === 'post' && path === '/reconciliation/exports') {
        throw new Error('Export reconciliation should be blocked in review-only mode');
      }

      if (verb === 'post' && path === '/hcm/payroll-runs/44/disburse') {
        throw new Error('Disburse request should be blocked in review-only mode');
      }

      throw new Error('Unexpected request: ' + method + ' ' + path);
    });

    window.AuthApi = {
      request: requestMock,
      downloadV1Binary: vi.fn().mockResolvedValue(undefined),
    };

    await loadPayrollRunModule();
    await flush();

    expect(document.querySelector('[data-payroll-run-export-evidence]').disabled).toBe(true);
    expect(document.querySelector('[data-payroll-run-reconciliation-hint]').textContent).toContain('review-only');

    document.querySelector('[data-payroll-run-pay-one="7"]').click();
    await flush();

    expect(window.ArcavUi.showToast).toHaveBeenCalledWith(
      expect.stringContaining('review-only'),
      'danger'
    );

    expect(requestMock.mock.calls.some(function (call) {
      return call[0] === 'post' && call[1] === '/hcm/payroll-runs/44/disburse';
    })).toBe(false);
  });

  it('resets helper returns workflow to fresh calculate-draft state', async function () {
    function wrap(payload) {
      return { data: payload };
    }

    var requestMock = vi.fn(async function (method, path) {
      var verb = String(method).toLowerCase();

      if (verb === 'get' && path === '/hcm/payroll-periods/active') {
        return wrap({ success: true, data: { id: 11, periodYear: 2026, periodMonth: 8 } });
      }

      if (verb === 'get' && path === '/hcm/payroll-periods/11') {
        return wrap({
          success: true,
          data: {
            id: 11,
            status: 'posted',
            latestRun: { id: 44, status: 'finalized', paymentStatus: 'paid', period: { status: 'posted' } },
          },
        });
      }

      if (verb === 'get' && path === '/hcm/payroll-runs/44') {
        return wrap({
          success: true,
          data: {
            run: { id: 44, status: 'finalized', paymentStatus: 'paid', period: { periodYear: 2026, periodMonth: 8, status: 'posted' } },
            lines: [
              {
                userId: 7,
                userName: 'Nadia',
                kind: 'addition',
                componentName: 'Gaji Pokok',
                componentCode: 'gaji_pokok',
                category: 'salary',
                amount: 5000000,
                sortOrder: 1,
                affectsNetPay: true,
                paymentStatus: 'paid',
                paidAt: '2026-08-28T03:21:00+07:00',
                gatewayReference: 'PAY-REF-00044',
                meta: { userName: 'Nadia' },
              },
            ],
            specialRecipients: { thrUserIds: [], compensationUserIds: [] },
          },
        });
      }

      if (verb === 'get' && path === '/reconciliation/exports') {
        return wrap({ success: true, data: [] });
      }

      if (verb === 'post' && path === '/hcm/payroll-runs/44/reset-payments') {
        return wrap({
          success: true,
          data: {
            run: {
              id: 44,
              status: 'finalized',
              paymentStatus: 'unpaid',
              period: { periodYear: 2026, periodMonth: 8, status: 'posted' },
            },
            resetLineCount: 1,
          },
        });
      }

      throw new Error('Unexpected request: ' + method + ' ' + path);
    });

    window.AuthApi = {
      request: requestMock,
      downloadV1Binary: vi.fn(),
    };

    await loadPayrollRunModule();
    await flush();

    var rowCheckBefore = document.querySelector('[data-payroll-run-row-check]');
    expect(rowCheckBefore.disabled).toBe(true);
    expect(document.querySelector('[data-payroll-run-selected-count]').textContent).toBe('0');

    document.querySelector('[data-payroll-run-reset-payments]').click();
    await flush();

    var resetCall = requestMock.mock.calls.find(function (call) {
      return call[0] === 'post' && call[1] === '/hcm/payroll-runs/44/reset-payments';
    });

    expect(resetCall).toBeTruthy();
    expect(window.ArcavUi.confirm).toHaveBeenCalledWith(
      expect.stringContaining('helper development'),
      'Reset Payments'
    );
    expect(window.ArcavUi.showToast).toHaveBeenCalledWith(
      'Reset pembayaran selesai (1 line direset). Jalankan Calculate Draft untuk membuat run baru.',
      'success'
    );

    expect(document.querySelector('[data-payroll-run-selected-count]').textContent).toBe('0');
    expect(document.querySelector('[data-payroll-run-stage-title]').textContent).toContain('Calculate Draft');
    expect(document.querySelector('[data-payroll-step="calculate"] [data-payroll-step-status]').textContent).toBe('ACTIVE');
    expect(document.querySelector('[data-payroll-run-empty]').textContent).toContain('Klik Calculate Draft');
    expect(document.querySelector('[data-payroll-run-reset-payments]').textContent).toBe('Reset Pembayaran (DEV)');
  });

  it('shows before-payday policy error from manual payment response', async function () {
    function wrap(payload) {
      return { data: payload };
    }

    var requestMock = vi.fn(async function (method, path, data) {
      var verb = String(method).toLowerCase();

      if (verb === 'get' && path === '/hcm/payroll-periods/active') {
        return wrap({ success: true, data: { id: 11, periodYear: 2026, periodMonth: 8 } });
      }

      if (verb === 'get' && path === '/hcm/payroll-periods/11') {
        return wrap({
          success: true,
          data: {
            id: 11,
            status: 'posted',
            latestRun: { id: 44, status: 'draft', paymentStatus: 'unpaid', period: { status: 'posted' } },
          },
        });
      }

      if (verb === 'get' && path === '/hcm/payroll-runs/44') {
        return wrap({
          success: true,
          data: {
            run: { id: 44, status: 'draft', paymentStatus: 'unpaid', period: { periodYear: 2026, periodMonth: 8, status: 'posted' } },
            lines: [
              {
                userId: 7,
                userName: 'Nadia',
                kind: 'addition',
                componentName: 'Gaji Pokok',
                componentCode: 'gaji_pokok',
                category: 'salary',
                amount: 5000000,
                sortOrder: 1,
                affectsNetPay: true,
                paymentStatus: 'unpaid',
                meta: { userName: 'Nadia' },
              },
            ],
            specialRecipients: { thrUserIds: [], compensationUserIds: [] },
          },
        });
      }

      if (verb === 'get' && path === '/reconciliation/exports') {
        return wrap({ success: true, data: [] });
      }

      if (verb === 'post' && path === '/reconciliation/exports') {
        return wrap({
          success: true,
          data: {
            id: 9,
            filePath: 'reconciliation/company_1/payroll-run-44.xlsx',
          },
        });
      }

      if (verb === 'post' && path === '/hcm/payroll-runs/44/disburse') {
        return wrap({
          success: false,
          error: {
            code: 'PAYROLL_DISBURSE_BEFORE_PAYDAY_FORBIDDEN',
            message: 'Payroll tidak bisa dibayarkan sebelum payday 2026-08-27 sesuai policy tenant aktif.',
          },
        });
      }

      throw new Error('Unexpected request: ' + method + ' ' + path + ' ' + JSON.stringify(data || null));
    });

    window.AuthApi = {
      request: requestMock,
      downloadV1Binary: vi.fn().mockResolvedValue(undefined),
    };

    await loadPayrollRunModule();
    await flush();

    document.querySelector('[data-payroll-run-export-evidence]').click();
    await flush();

    document.querySelector('[data-recon-preview-download]').click();
    await flush();

    document.querySelector('[data-payroll-run-disburse]').click();
    await flush();

    document.querySelector('[data-payroll-gateway-pay]').click();
    await flush();

    expect(window.ArcavUi.showToast).toHaveBeenCalledWith(
      'Payroll tidak bisa dibayarkan sebelum payday 2026-08-27 sesuai policy tenant aktif.',
      'danger'
    );
    expect(document.querySelector('[data-payroll-run-reconciliation-hint]').textContent).toBe('');
  });
});