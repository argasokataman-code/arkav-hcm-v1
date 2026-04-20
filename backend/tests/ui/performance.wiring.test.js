import { beforeEach, describe, expect, it, vi } from 'vitest';

async function loadPerformanceModule() {
  vi.resetModules();
  await import('../../../frontend/resources/js/performance-data.js');
}

function flush(times = 10) {
  return Array.from({ length: times }).reduce((promise) => promise.then(() => Promise.resolve()), Promise.resolve());
}

function makeBootstrapStub() {
  window.bootstrap = {
    Modal: class {
      static getOrCreateInstance() {
        return new window.bootstrap.Modal();
      }

      show() {}
      hide() {}
    },
  };
}

function baseAuthApi(overrides = {}) {
  return {
    getToken: () => 'perf-token',
    getTenantContext: () => ({
      companyCode: 'ACME',
      companyId: 88,
      companyUuid: '11111111-2222-3333-4444-555555555555',
    }),
    handleUnauthorizedFromApi: () => false,
    ...overrides,
  };
}

function okJson(data) {
  return {
    ok: true,
    status: 200,
    text: async () => JSON.stringify(data),
  };
}

describe('Performance wiring', () => {
  beforeEach(() => {
    localStorage.clear();
    delete window.location.__ARCAV_DISABLE_REDIRECTS__;
    makeBootstrapStub();
  });

  it('sends auth and tenant headers for admin performance flows', async () => {
    document.body.innerHTML = `
      <div class="main-wrapper" data-subscription-status="trial" data-role-scope="hcm-admin"></div>
      <button type="button" data-bs-target="#arcav_perf_cycle_modal"></button>
      <button type="button" data-bs-target="#arcav_perf_review_create_modal"></button>
      <div data-arcav-perf-template-items-section></div>
      <div id="arcav_perf_template_modal">
        <div data-arcav-perf-template-modal-title></div>
        <input data-arcav-perf-template-id />
        <form data-arcav-perf-template-form>
          <input name="name" />
          <input name="department" />
          <input name="designation" />
          <input name="isActive" type="checkbox" checked />
        </form>
      </div>
      <div id="arcav_perf_item_modal">
        <div data-arcav-perf-item-modal-title></div>
        <input data-arcav-perf-item-template-id />
        <input data-arcav-perf-item-id />
        <form data-arcav-perf-item-form>
          <select name="section"><option value="kpi">kpi</option><option value="behavioral">behavioral</option></select>
          <input name="title" />
          <textarea name="description"></textarea>
          <input name="weight" />
          <input name="sortOrder" />
          <input name="ratingScaleMin" />
          <input name="ratingScaleMax" />
        </form>
      </div>
      <table><tbody data-arcav-perf-templates-tbody></tbody></table>
      <select data-arcav-perf-template-department></select>
      <select data-arcav-perf-template-designation></select>
      <div id="arcav_perf_template_detail_modal">
        <div data-arcav-perf-template-detail-name></div>
        <div data-arcav-perf-template-detail-department></div>
        <div data-arcav-perf-template-detail-designation></div>
        <div data-arcav-perf-template-detail-status></div>
        <table><tbody data-arcav-perf-template-detail-items-tbody></tbody></table>
      </div>
    `;

    window.AuthApi = baseAuthApi();

    const fetchMock = vi.fn((url) => {
      const urlString = String(url);

      if (urlString.includes('/v1/identity/auth/me')) {
        return Promise.resolve(okJson({
          success: true,
          data: {
            permissions: {
              'performance.manage': true,
            },
          },
        }));
      }

      if (urlString.includes('/v1/hcm/performance/indicator-templates')) {
        return Promise.resolve(okJson({
          success: true,
          data: [
            {
              id: 1,
              name: 'Engineering Template',
              department: 'Finance',
              designation: 'Staff',
              isActive: true,
            },
          ],
        }));
      }

      if (urlString.includes('/v1/hcm/departments')) {
        return Promise.resolve(okJson({
          success: true,
          data: [{ id: 2, name: 'Finance', isActive: true }],
        }));
      }

      if (urlString.includes('/v1/hcm/designations')) {
        return Promise.resolve(okJson({
          success: true,
          data: [{ id: 3, name: 'Staff', isActive: true }],
        }));
      }

      throw new Error(`Unexpected fetch: ${url}`);
    });

    vi.stubGlobal('fetch', fetchMock);

    await loadPerformanceModule();
    document.dispatchEvent(new Event('DOMContentLoaded'));
    await flush();

    expect(fetchMock).toHaveBeenCalledTimes(2);

    const [meUrl, meOptions] = fetchMock.mock.calls[0];
    expect(String(meUrl)).toBe('/v1/identity/auth/me');
    expect(meOptions.headers.Authorization).toBe('Bearer perf-token');
    expect(meOptions.headers['X-Company-Code']).toBe('ACME');
    expect(meOptions.headers['X-Company-Id']).toBe('88');
    expect(meOptions.headers['X-Company-UUID']).toBe('11111111-2222-3333-4444-555555555555');

    const [templatesUrl, templatesOptions] = fetchMock.mock.calls[1];
    expect(String(templatesUrl)).toBe('/v1/hcm/performance/indicator-templates');
    expect(templatesOptions.headers.Authorization).toBe('Bearer perf-token');
    expect(templatesOptions.headers['X-Company-Id']).toBe('88');

    const row = document.querySelector('[data-arcav-perf-templates-tbody] button[data-action="edit"]');
    expect(row).toBeTruthy();

    row.click();
    await flush();

    const urls = fetchMock.mock.calls.map(([url]) => String(url));
    expect(urls).toEqual(expect.arrayContaining([
      '/v1/identity/auth/me',
      '/v1/hcm/performance/indicator-templates',
      '/v1/hcm/departments',
      '/v1/hcm/designations',
    ]));

    const departmentCall = fetchMock.mock.calls.find(([url]) => String(url) === '/v1/hcm/departments');
    expect(String(departmentCall[0])).toBe('/v1/hcm/departments');
    expect(departmentCall[1].headers.Authorization).toBe('Bearer perf-token');

    const designationCall = fetchMock.mock.calls.find(([url]) => String(url) === '/v1/hcm/designations');
    expect(String(designationCall[0])).toBe('/v1/hcm/designations');
    expect(designationCall[1].headers['X-Company-Code']).toBe('ACME');

    expect(document.querySelector('[data-arcav-perf-template-department]').innerHTML).toContain('Finance');
    expect(document.querySelector('[data-arcav-perf-template-designation]').innerHTML).toContain('Staff');
  });

  it('removes admin review scope when manage permission is missing', async () => {
    document.body.innerHTML = `
      <div class="main-wrapper" data-subscription-status="trial" data-role-scope="hcm-admin"></div>
      <div data-arcav-perf-review-detail></div>
      <table data-arcav-perf-reviews-mode="compact"><tbody data-arcav-perf-reviews-tbody></tbody></table>
      <select data-arcav-perf-review-scope>
        <option value="me">Me</option>
        <option value="team">Team</option>
        <option value="all">All (Admin)</option>
      </select>
      <button type="button" data-arcav-perf-review-reload></button>
      <button type="button" data-arcav-perf-review-refresh-detail></button>
      <button type="button" data-arcav-perf-review-primary-action></button>
      <button type="button" data-arcav-perf-review-secondary-action></button>
    `;

    window.AuthApi = baseAuthApi();

    const fetchMock = vi.fn((url) => {
      const urlString = String(url);

      if (urlString.includes('/v1/identity/auth/me')) {
        return Promise.resolve(okJson({
          success: true,
          data: {
            permissions: {
              'performance.view': true,
            },
          },
        }));
      }

      if (urlString.includes('/v1/hcm/performance/reviews')) {
        return Promise.resolve(okJson({
          success: true,
          data: [
            {
              id: 9,
              cycle: { name: '2026 H1' },
              employee: { id: 7, name: 'Nadia' },
              status: 'draft',
              finalTotalScore: 91.5,
            },
          ],
        }));
      }

      throw new Error(`Unexpected fetch: ${url}`);
    });

    vi.stubGlobal('fetch', fetchMock);

    await loadPerformanceModule();
    document.dispatchEvent(new Event('DOMContentLoaded'));
    await flush();

    expect(fetchMock).toHaveBeenCalledTimes(2);
    const [meUrl, meOptions] = fetchMock.mock.calls[0];
    expect(String(meUrl)).toBe('/v1/identity/auth/me');
    expect(meOptions.headers.Authorization).toBe('Bearer perf-token');

    const [reviewsUrl, reviewsOptions] = fetchMock.mock.calls[1];
    expect(String(reviewsUrl)).toContain('/v1/hcm/performance/reviews?scope=me&perPage=50');
    expect(reviewsOptions.headers['X-Company-Id']).toBe('88');
    expect(document.querySelector('[data-arcav-perf-review-scope]').querySelector('option[value="all"]')).toBeNull();
    expect(document.querySelector('[data-arcav-perf-reviews-tbody]').innerHTML).toContain('Nadia');
  });
});