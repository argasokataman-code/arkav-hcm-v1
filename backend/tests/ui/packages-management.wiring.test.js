import { beforeEach, describe, expect, it, vi } from 'vitest';

function flush(times = 8) {
  return Array.from({ length: times }).reduce((promise) => promise.then(() => Promise.resolve()), Promise.resolve());
}

function buildPackagesDom() {
  document.body.innerHTML = `
    <div class="content" data-saas-packages-page>
      <button id="btn_add_package"></button>
      <button id="btn_reset_filters"></button>
      <input id="search_packages" />
      <select id="filter_status"><option value="all">All</option><option value="active">Active</option></select>
      <div data-packages-list-container></div>
      <div data-package-addons-list-container></div>
      <form id="packageForm"><button type="submit">Save</button></form>
      <div id="packageModal"></div>
      <div id="addonModal"></div>
      <div id="featuresModal"></div>
      <div id="features_container"></div>
      <input id="input_package_name" />
      <textarea id="input_package_description"></textarea>
      <input id="input_package_price" />
      <select id="input_package_cycle">
        <option value="monthly">Monthly</option>
        <option value="yearly">Yearly</option>
      </select>
      <input id="input_package_active" type="checkbox" checked />
      <div id="input_package_feature_chips"></div>
      <input id="input_package_feature_search" />
      <div data-feature-selected-count></div>
      <div data-feature-selected-preview></div>
      <form id="addonForm"><button type="submit">Save Addon</button></form>
      <input id="input_addon_code" />
      <input id="input_addon_name" />
      <textarea id="input_addon_description"></textarea>
      <input id="input_addon_price" />
      <input id="input_addon_unit" />
      <input id="input_addon_active" type="checkbox" checked />
      <div id="addonModalTitle"></div>
      <div id="packageModalTitle"></div>
    </div>
  `;
}

async function loadPackagesManager(fetchImpl) {
  vi.resetModules();
  vi.stubGlobal('fetch', fetchImpl);
  delete window.PackagesManager;

  Object.defineProperty(document, 'readyState', {
    configurable: true,
    value: 'loading',
  });

  window.bootstrap = {
    Modal: class {
      static getOrCreateInstance() {
        return {
          show() {},
          hide() {},
        };
      }
    },
  };

  await import('../../../frontend/resources/js/packages-management.js');
  document.dispatchEvent(new Event('DOMContentLoaded'));
  await flush();

  return window.PackagesManager;
}

describe('Packages management wiring', () => {
  beforeEach(() => {
    vi.restoreAllMocks();
    vi.unstubAllGlobals();
    localStorage.clear();
    buildPackagesDom();
  });

  it('keeps existing yearly price when editing package details without changing price', async () => {
    const updateBodies = [];
    const fetchMock = vi.fn(async (url, options = {}) => {
      const target = String(url);

      if (target === '/api-token') {
        return {
          ok: true,
          status: 200,
          json: async () => ({ success: true, data: { token: 'packages-token' } }),
        };
      }

      if (target.startsWith('/v1/saas/packages?')) {
        return {
          ok: true,
          status: 200,
          json: async () => ({
            success: true,
            data: [
              {
                id: 'pkg-uuid',
                code: 'pro',
                name: 'Pro',
                description: 'Existing package',
                monthlyPrice: 100,
                yearlyPrice: 1500,
                billingUnit: 'company',
                status: 'active',
                features: [{ id: 1, code: 'payroll', name: 'Payroll', limit: null }],
              },
            ],
            pagination: { last_page: 1 },
          }),
        };
      }

      if (target.startsWith('/v1/saas/package-addons?')) {
        return {
          ok: true,
          status: 200,
          json: async () => ({ success: true, data: [], pagination: { last_page: 1 } }),
        };
      }

      if (target === '/v1/saas/packages/pkg-uuid' && options.method === 'GET') {
        return {
          ok: true,
          status: 200,
          json: async () => ({
            success: true,
            data: {
              id: 'pkg-uuid',
              code: 'pro',
              name: 'Pro',
              description: 'Existing package',
              monthlyPrice: 100,
              yearlyPrice: 1500,
              billingUnit: 'company',
              status: 'active',
              features: [{ id: 1, code: 'payroll', name: 'Payroll', limit: null }],
            },
          }),
        };
      }

      if (target === '/v1/saas/packages/pkg-uuid' && options.method === 'PUT') {
        updateBodies.push(JSON.parse(options.body));
        return {
          ok: true,
          status: 200,
          json: async () => ({ success: true, data: { id: 'pkg-uuid' } }),
        };
      }

      return {
        ok: true,
        status: 200,
        json: async () => ({ success: true, data: [] }),
      };
    });

    const manager = await loadPackagesManager(fetchMock);

    await manager.editPackage('pkg-uuid');
    await flush();

    document.getElementById('input_package_name').value = 'Pro Updated';
    manager.handleSavePackage(document.getElementById('packageForm'));
    await flush();

    expect(updateBodies).toHaveLength(1);
    expect(updateBodies[0]).toMatchObject({
      name: 'Pro Updated',
      monthly_price: 100,
      yearly_price: 1500,
    });
  });

  it('renders backend error messages as text, not executable html', async () => {
    const fetchMock = vi.fn(async (url) => {
      const target = String(url);

      if (target === '/api-token') {
        return {
          ok: true,
          status: 200,
          json: async () => ({ success: true, data: { token: 'packages-token' } }),
        };
      }

      return {
        ok: true,
        status: 200,
        json: async () => ({ success: true, data: [], pagination: { last_page: 1 } }),
      };
    });

    const manager = await loadPackagesManager(fetchMock);
    manager.showError('<img src=x onerror="window.__toastExecuted = true">Invalid package');

    const alert = document.querySelector('.alert');
    expect(alert).toBeTruthy();
    expect(alert.querySelector('img')).toBeNull();
    expect(alert.textContent).toContain('Invalid package');
    expect(window.__toastExecuted).toBeUndefined();
  });
});