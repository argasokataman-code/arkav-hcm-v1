import { beforeEach, describe, expect, it, vi } from 'vitest';

async function loadPromotionModule() {
  vi.resetModules();
  await import('../../../frontend/resources/js/promotion-data.js');
}

function flush(times = 6) {
  return Array.from({ length: times }).reduce((promise) => promise.then(() => Promise.resolve()), Promise.resolve());
}

function buildPromotionDom() {
  document.body.innerHTML = `
    <button data-arcav-promotion-add>Add Promotion</button>
    <table><tbody data-arcav-promotions-tbody></tbody></table>
    <div id="arcav_promotion_modal">
      <form data-arcav-promotion-form>
        <div data-arcav-promotion-flash class="d-none"></div>
        <input data-arcav-promotion-id />
        <select data-arcav-promotion-user></select>
        <input data-arcav-promotion-date />
        <input data-arcav-promotion-department />
        <input data-arcav-promotion-from />
        <select data-arcav-promotion-to></select>
        <textarea data-arcav-promotion-notes></textarea>
      </form>
    </div>
    <div id="arcav_promotion_detail_modal">
      <div data-arcav-promotion-detail-loading></div>
      <div data-arcav-promotion-detail-error class="d-none"></div>
      <div data-arcav-promotion-detail-body class="d-none"></div>
      <a data-arcav-promotion-detail-profile href="/employee-details"></a>
      <div data-arcav-promotion-detail-employee></div>
      <div data-arcav-promotion-detail-email></div>
      <div data-arcav-promotion-detail-department></div>
      <div data-arcav-promotion-detail-from></div>
      <div data-arcav-promotion-detail-to></div>
      <div data-arcav-promotion-detail-date></div>
      <div data-arcav-promotion-detail-notes></div>
      <div data-arcav-promotion-detail-created></div>
    </div>
  `;
}

describe('Promotion wiring', () => {
  beforeEach(() => {
    localStorage.clear();
    buildPromotionDom();
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
  });

  it('sends auth and tenant headers and hides manage actions for view-only users', async () => {
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
                'promotion.view': true,
              },
            },
          }),
        });
      }

      if (urlString.includes('/v1/hcm/promotions')) {
        return Promise.resolve({
          ok: true,
          status: 200,
          json: async () => ({
            success: true,
            data: [
              {
                id: 7,
                employee: { id: 9, name: 'Nadia', email: 'nadia@example.com' },
                department: 'Finance',
                designationFrom: 'Staff',
                designationTo: 'Senior Staff',
                promotionDate: '2026-04-19',
                notes: 'Great work',
              },
            ],
          }),
        });
      }

      throw new Error(`Unexpected fetch: ${url}`);
    });

    vi.stubGlobal('fetch', fetchMock);
    window.AuthApi = {
      getToken: () => 'token-abc',
      getTenantContext: () => ({
        companyCode: 'ACME',
        companyId: 44,
        companyUuid: '11111111-2222-3333-4444-555555555555',
      }),
      handleUnauthorizedFromApi: () => false,
    };

    await loadPromotionModule();
    document.dispatchEvent(new Event('DOMContentLoaded'));
    await flush();

    expect(fetchMock).toHaveBeenCalledTimes(2);
    const [meUrl, meOptions] = fetchMock.mock.calls[0];
    expect(String(meUrl)).toBe('/v1/identity/auth/me');
    expect(meOptions.headers.Authorization).toBe('Bearer token-abc');
    expect(meOptions.headers['X-Company-Code']).toBe('ACME');
    expect(meOptions.headers['X-Company-Id']).toBe('44');
    expect(meOptions.headers['X-Company-UUID']).toBe('11111111-2222-3333-4444-555555555555');

    const [listUrl, listOptions] = fetchMock.mock.calls[1];
    expect(String(listUrl)).toBe('/v1/hcm/promotions');
    expect(listOptions.headers.Authorization).toBe('Bearer token-abc');
    expect(listOptions.headers['X-Company-Id']).toBe('44');

    expect(document.querySelector('[data-arcav-promotion-add]').classList.contains('d-none')).toBe(true);
    expect(document.querySelector('[data-arcav-promotions-tbody]').innerHTML).toContain('data-arcav-promotion-view="7"');
    expect(document.querySelector('[data-arcav-promotions-tbody]').innerHTML).not.toContain('data-arcav-promotion-edit="7"');
    expect(document.querySelector('[data-arcav-promotions-tbody]').innerHTML).not.toContain('data-arcav-promotion-delete="7"');
  });

  it('keeps manage actions visible when promotion.manage is granted', async () => {
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
                'promotion.view': true,
                'promotion.manage': true,
              },
            },
          }),
        });
      }

      if (urlString.includes('/v1/hcm/promotions')) {
        return Promise.resolve({
          ok: true,
          status: 200,
          json: async () => ({
            success: true,
            data: [
              {
                id: 8,
                employee: { id: 10, name: 'Arif', email: 'arif@example.com' },
                department: 'Engineering',
                designationFrom: 'Engineer',
                designationTo: 'Lead Engineer',
                promotionDate: '2026-04-19',
                notes: 'Promotion approved',
              },
            ],
          }),
        });
      }

      throw new Error(`Unexpected fetch: ${url}`);
    });

    vi.stubGlobal('fetch', fetchMock);
    window.AuthApi = {
      getToken: () => 'token-xyz',
      getTenantContext: () => ({
        companyCode: 'BETA',
        companyId: 55,
        companyUuid: '22222222-3333-4444-5555-666666666666',
      }),
      handleUnauthorizedFromApi: () => false,
    };

    await loadPromotionModule();
    document.dispatchEvent(new Event('DOMContentLoaded'));
    await flush();

    expect(document.querySelector('[data-arcav-promotion-add]').classList.contains('d-none')).toBe(false);
    const html = document.querySelector('[data-arcav-promotions-tbody]').innerHTML;
    expect(html).toContain('data-arcav-promotion-view="8"');
    expect(html).toContain('data-arcav-promotion-edit="8"');
    expect(html).toContain('data-arcav-promotion-delete="8"');
  });
});