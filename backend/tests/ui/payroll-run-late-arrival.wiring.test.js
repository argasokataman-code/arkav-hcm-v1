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
      '<select data-payroll-run-month><option value="3" selected>3</option></select>' +
      '<div data-payroll-run-error class="d-none"></div>' +
      '<div data-payroll-run-reconciliation-hint class="d-none"></div>' +
      '<div data-payroll-run-void-hint class="d-none"></div>' +
      '<div data-payroll-run-evidence-indicator class="d-none">' +
        '<span data-evidence-status></span>' +
        '<span data-evidence-timestamp></span>' +
      '</div>' +
      '<button type="button" data-payroll-run-calculate disabled>Calculate Draft</button>' +
      '<button type="button" data-payroll-run-void disabled>Void</button>' +
      '<button type="button" data-payroll-run-export-evidence disabled>Export Reconciliation</button>' +
      '<button type="button" data-payroll-run-disburse disabled>Pay via Gateway</button>' +
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
      '<div data-payroll-gateway-deductions></div>' +
      '<div data-payroll-gateway-total></div>' +
      '<div data-payroll-gateway-status></div>' +
      '<div data-payroll-gateway-list></div>' +
      '<button type="button" data-payroll-gateway-pay>Pay now</button>' +
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
      '<tbody data-recon-preview-body></tbody>' +
      '<button type="button" data-recon-preview-download>Download CSV</button>' +
    '</div>';
}

describe('Payroll run late-arrival migration wiring', function () {
  beforeEach(function () {
    vi.useRealTimers();
    buildPayrollRunDom();
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
      showToast: vi.fn(),
      confirm: vi.fn().mockResolvedValue(true),
    };
  });

  it('opens hosted checkout flow when disburse is confirmed from gateway modal', async function () {
    function wrap(payload) {
      return { data: payload };
    }

    var requestMock = vi.fn(async function (method, path, data) {
      var verb = String(method).toLowerCase();

      if (verb === 'get' && path === '/hcm/payroll-periods/active') {
        return wrap({ success: true, data: { id: 31, periodYear: 2026, periodMonth: 3 } });
      }

      if (verb === 'get' && path === '/hcm/payroll-periods/31') {
        return wrap({
          success: true,
          data: {
            id: 31,
            status: 'posted',
            latestRun: { id: 88, status: 'draft', paymentStatus: 'unpaid', period: { status: 'posted' } },
          },
        });
      }

      if (verb === 'get' && path === '/hcm/payroll-runs/88') {
        return wrap({
          success: true,
          data: {
            run: { id: 88, status: 'draft', paymentStatus: 'unpaid', period: { periodYear: 2026, periodMonth: 3, status: 'posted' } },
            lines: [
              {
                userId: 77,
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
            id: 19,
            filePath: 'reconciliation/company_1/payroll-run-88.csv',
          },
        });
      }

      if (verb === 'post' && path === '/hcm/payroll-runs/88/mock-hosted-checkout') {
        return {
          data: {
            success: true,
            flow: {
              hostedCheckoutUrl: 'https://example.test/payroll/mock-checkout?payroll_run_id=88',
            },
          },
        };
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

    // Click export button → opens preview modal
    document.querySelector('[data-payroll-run-export-evidence]').click();
    await flush();

    // Click Download button inside preview modal → triggers actual export + download
    document.querySelector('[data-recon-preview-download]').click();
    await flush();

    document.querySelector('[data-payroll-run-disburse]').click();
    await flush();

    document.querySelector('[data-payroll-gateway-pay]').click();
    await flush();

    expect(requestMock).toHaveBeenCalledWith(
      'post',
      '/hcm/payroll-runs/88/mock-hosted-checkout',
      { userIds: [77] },
    );
    expect(window.ArcavUi.showToast).not.toHaveBeenCalledWith(
      expect.stringContaining('Terjadi kesalahan'),
      'danger',
    );
  });
});