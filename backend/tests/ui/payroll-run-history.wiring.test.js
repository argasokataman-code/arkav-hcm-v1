import { beforeEach, describe, expect, it, vi } from 'vitest';

async function loadModule() {
  vi.resetModules();
  await import('../../../frontend/resources/js/payroll-run-history-data.js');
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

function buildDom() {
  document.body.innerHTML = '' +
    '<div data-payroll-run-history-panel>' +
      '<input data-payroll-history-year value="2026" />' +
      '<select data-payroll-history-month><option value="8" selected>8</option></select>' +
      '<select data-payroll-history-status>' +
        '<option value="">Semua</option>' +
        '<option value="draft">Draft</option>' +
        '<option value="finalized">Finalized</option>' +
        '<option value="void">Void</option>' +
      '</select>' +
      '<button type="button" data-payroll-history-refresh>Refresh</button>' +
      '<table><tbody data-payroll-history-body></tbody></table>' +
      '<div data-payroll-history-pagination style="display:none;">' +
        '<span data-payroll-history-page-info></span>' +
        '<button type="button" data-payroll-history-prev></button>' +
        '<button type="button" data-payroll-history-next></button>' +
      '</div>' +
      '<div id="payroll_history_detail_modal"></div>' +
      '<div data-payroll-history-detail></div>' +
    '</div>';
}

describe('Payroll run history wiring', function () {
  beforeEach(function () {
    buildDom();
    window.bootstrap = {
      Modal: class {
        static getOrCreateInstance() {
          return new window.bootstrap.Modal();
        }
        show() {}
      },
    };
  });

  it('sends void status filter and shows void badge in detail modal', async function () {
    function wrap(payload) {
      return { data: payload };
    }

    var requestMock = vi.fn(async function (method, url) {
      if (method === 'get' && url.indexOf('/v1/hcm/payroll-runs/history?') === 0) {
        return wrap({
          success: true,
          data: [
            {
              id: 44,
              status: 'void',
              paymentStatus: 'unpaid',
              paidEmployeeCount: 0,
              employeeCount: 3,
              totals: { netPay: 15000000 },
              period: { periodMonth: 8, periodYear: 2026 },
              auditTrail: [{ event: 'finalized', at: '2026-08-25T09:00:00Z' }],
            },
          ],
          meta: {
            pagination: { page: 1, perPage: 20, total: 1, totalPages: 1 },
          },
        });
      }

      if (method === 'get' && url === '/v1/hcm/payroll-runs/44') {
        return wrap({
          success: true,
          data: {
            run: { id: 44, status: 'void', paymentStatus: 'unpaid', finalizedByUserName: 'Admin Payroll' },
            lines: [
              {
                userId: 7,
                userName: 'Nadia',
                kind: 'addition',
                componentCode: 'upah_lembur',
                componentName: 'Upah Lembur',
                category: 'overtime',
                amount: 125000,
                affectsNetPay: true,
              },
            ],
            auditTrail: [{ event: 'finalized', at: '2026-08-25T09:00:00Z' }],
            summary: {
              totals: { earningsTotal: 20000000, overtimeTotal: 125000, deductionsTotal: 5000000, netPay: 15000000, lineCount: 1 },
              employeeBreakdown: [
                {
                  userId: 7,
                  userName: 'Nadia',
                  lineCount: 1,
                  earningsTotal: 20000000,
                  deductionsTotal: 5000000,
                  netPay: 15000000,
                },
              ],
              componentBreakdown: [],
            },
          },
        });
      }

      throw new Error('Unexpected request: ' + method + ' ' + url);
    });

    window.axios = vi.fn(function (config) {
      return Promise.resolve(requestMock(config.method, config.url));
    });

    await loadModule();
    await flush();

    var statusSelect = document.querySelector('[data-payroll-history-status]');
    statusSelect.value = 'void';
    document.querySelector('[data-payroll-history-refresh]').click();
    await flush();

    var historyCall = requestMock.mock.calls.find(function (call) {
      return call[0] === 'get' && String(call[1]).indexOf('status=void') !== -1;
    });

    expect(historyCall).toBeTruthy();
    expect(document.querySelector('[data-payroll-history-body]').innerHTML).toContain('VOID');

    document.querySelector('[data-payroll-history-detail-open]').click();
    await flush();

    expect(document.querySelector('[data-payroll-history-detail]').innerHTML).toContain('badge bg-danger">VOID');
    expect(document.querySelector('[data-payroll-history-detail]').innerHTML).toContain('badge bg-secondary">UNPAID');
    expect(document.querySelector('[data-payroll-history-detail]').innerHTML).toContain('Total Overtime');
    expect(document.querySelector('[data-payroll-history-detail]').innerHTML).toContain('Overtime');
    expect(document.querySelector('[data-payroll-history-detail]').innerHTML).toContain('125.000');
  });
});