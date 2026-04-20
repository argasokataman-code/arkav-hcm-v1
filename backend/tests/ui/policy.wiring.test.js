import { beforeEach, describe, expect, it, vi } from 'vitest';

async function loadPolicyPage() {
  vi.resetModules();
  await import('../../../frontend/resources/js/hcm-pages-data.js');
}

function flush(times = 8) {
  return Array.from({ length: times }).reduce((promise) => promise.then(() => Promise.resolve()), Promise.resolve());
}

describe('Policy page wiring', () => {
  beforeEach(() => {
    localStorage.clear();
    document.body.innerHTML = `
      <div class="main-wrapper" data-subscription-status="trial" data-role-scope="hcm-admin"></div>
      <button data-hcm-export="pdf" data-hcm-export-module="policies"></button>
      <button data-hcm-export="xlsx" data-hcm-export-module="policies"></button>
      <a href="#" data-bs-target="#add_policy"></a>
      <input data-hcm-search-input="policies" value="" />
      <select data-hcm-policy-department-filter><option value="">All Departments</option></select>
      <select data-hcm-per-page="policies"><option value="20" selected>20</option></select>
      <table><tbody data-policies-body></tbody></table>
      <div data-hcm-pagination-wrap="policies"></div>
      <small data-hcm-showing="policies"></small>
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
    window.AuthApi = {
      getToken: () => 'policy-token',
      getTenantContext: () => ({
        companyCode: 'ACME',
        companyId: 77,
        companyUuid: '11111111-2222-3333-4444-555555555555',
      }),
      handleUnauthorizedFromApi: () => false,
    };
  });

  it('loads policies with auth and tenant headers on the active policy page', async () => {
    window.history.pushState({}, '', '/policy');

    const fetchMock = vi.fn((url) => {
      const urlString = String(url);

      if (urlString.includes('/v1/identity/auth/me')) {
        return Promise.resolve({
          ok: true,
          status: 200,
          json: async () => ({
            success: true,
            data: {
              permissions: {
                'policy.manage': true,
              },
            },
          }),
        });
      }

      if (urlString.includes('/v1/hcm/departments')) {
        return Promise.resolve({
          ok: true,
          status: 200,
          json: async () => ({
            success: true,
            data: [
              { id: 3, name: 'Finance' },
            ],
          }),
        });
      }

      if (urlString.includes('/v1/hcm/policies')) {
        return Promise.resolve({
          ok: true,
          status: 200,
          json: async () => ({
            success: true,
            data: [
              {
                id: 12,
                name: 'Remote Work Policy',
                department: 'Finance',
                description: 'Hybrid work schedule',
                effectiveDate: '2026-04-19',
                attachmentUrl: 'https://example.test/policy.pdf',
              },
            ],
            meta: { total: 1, perPage: 20, page: 1, lastPage: 1 },
          }),
        });
      }

      throw new Error(`Unexpected fetch: ${url}`);
    });

    vi.stubGlobal('fetch', fetchMock);

    await loadPolicyPage();
    document.dispatchEvent(new Event('DOMContentLoaded'));
    await flush();

    expect(fetchMock).toHaveBeenCalledTimes(3);

    const [meUrl, meOptions] = fetchMock.mock.calls[0];
    expect(String(meUrl)).toBe('/v1/identity/auth/me');
    expect(meOptions.headers.Authorization).toBe('Bearer policy-token');
    expect(meOptions.headers['X-Company-Code']).toBe('ACME');
    expect(meOptions.headers['X-Company-Id']).toBe('77');
    expect(meOptions.headers['X-Company-UUID']).toBe('11111111-2222-3333-4444-555555555555');

    const [deptUrl, deptOptions] = fetchMock.mock.calls[1];
    expect(String(deptUrl)).toContain('/v1/hcm/departments?perPage=200');
    expect(deptOptions.headers.Authorization).toBe('Bearer policy-token');

    const [policyUrl, policyOptions] = fetchMock.mock.calls[2];
    expect(String(policyUrl)).toContain('/v1/hcm/policies');
    expect(policyOptions.headers.Authorization).toBe('Bearer policy-token');
    expect(policyOptions.headers['X-Company-Id']).toBe('77');

    const html = document.querySelector('[data-policies-body]').innerHTML;
    expect(html).toContain('Remote Work Policy');
    expect(html).toContain('data-hcm-edit="policy"');
    expect(html).toContain('data-hcm-delete="policy"');
    expect(document.querySelector('[data-hcm-policy-department-filter]').innerHTML).toContain('Finance');
  });
});