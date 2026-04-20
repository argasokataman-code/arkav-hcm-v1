import { beforeEach, describe, expect, it, vi } from 'vitest';

async function loadReportsHub() {
  vi.resetModules();
  await import('../../../frontend/resources/js/reports-hub.js');
  return window.ReportsHub;
}

describe('ReportsHub wiring', () => {
  beforeEach(() => {
    localStorage.clear();
    document.body.innerHTML = '<div class="main-wrapper" data-subscription-status="trial" data-role-scope="hcm-admin"></div>';
    window.AuthApi = {
      getToken: () => 'token-xyz',
      getTenantContext: () => ({
        companyCode: 'ACME',
        companyId: 77,
        companyUuid: '11111111-2222-3333-4444-555555555555',
      }),
      handleUnauthorizedFromApi: () => false,
    };

    document.body.innerHTML += `
      <div id="generate_modal"></div>
      <form id="generate_form">
        <input name="reportType" value="employee" />
        <input name="periodStart" value="2026-03-20" />
        <input name="periodEnd" value="2026-04-19" />
        <input name="async" type="checkbox" checked />
      </form>
      <div id="snapshots_loading" style="display:none"></div>
      <table id="snapshots_table" style="display:none"><tbody id="snapshots_tbody"></tbody></table>
      <div id="snapshots_empty" style="display:none"></div>
      <div id="alerts_container"></div>
      <button id="gen_submit"></button>
      <button id="refresh_snapshots"></button>
    `;

    window.bootstrap = {
      Modal: class {
        static getOrCreateInstance() {
          return new window.bootstrap.Modal();
        }

        show() {}
        hide() {}
      },
    };
  });

  it('exposes ReportsHub globally and sends tenant headers on snapshot calls', async () => {
    const fetchMock = vi.fn((url) => {
      if (String(url).includes('/v1/hcm/reports/snapshots')) {
        return Promise.resolve({
          ok: true,
          status: 200,
          json: async () => ({ success: true, data: [], meta: { pagination: { page: 1, perPage: 20, total: 0, lastPage: 1 } } }),
        });
      }

      throw new Error(`Unexpected fetch: ${url}`);
    });

    vi.stubGlobal('fetch', fetchMock);

    const hub = await loadReportsHub();
    expect(hub).toBeTruthy();
    expect(window.ReportsHub).toBe(hub);

    await hub.apiRequest('post', '/v1/hcm/reports/snapshots', {
      reportType: 'employee',
      periodStart: '2026-03-20',
      periodEnd: '2026-04-19',
      async: true,
    });

    expect(fetchMock).toHaveBeenCalledTimes(1);
    const [url, options] = fetchMock.mock.calls[0];
    expect(String(url)).toBe('/v1/hcm/reports/snapshots');
    expect(options.headers.Authorization).toBe('Bearer token-xyz');
    expect(options.headers['X-Company-Id']).toBe('77');
    expect(options.headers['X-Company-Code']).toBe('ACME');
    expect(options.headers['X-Company-UUID']).toBe('11111111-2222-3333-4444-555555555555');
    expect(JSON.parse(options.body)).toEqual({
      reportType: 'employee',
      periodStart: '2026-03-20',
      periodEnd: '2026-04-19',
      async: true,
    });
  });

  it('renders backend validation message when snapshot generation fails', async () => {
    const fetchMock = vi.fn((url, options = {}) => {
      if (String(url) === '/v1/hcm/reports/snapshots' && String(options.method || '').toUpperCase() === 'POST') {
        return Promise.resolve({
          ok: false,
          status: 422,
          json: async () => ({
            success: false,
            error: {
              code: 'VALIDATION_ERROR',
              message: 'Period end must be after or equal to period start.',
            },
          }),
        });
      }

      throw new Error(`Unexpected fetch: ${url}`);
    });

    vi.stubGlobal('fetch', fetchMock);

    const hub = await loadReportsHub();
    await hub.submitGenerate();

    expect(document.querySelector('#alerts_container').textContent).toContain('Period end must be after or equal to period start.');
  });
});