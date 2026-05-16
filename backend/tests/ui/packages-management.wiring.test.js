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
      <input id="input_package_max_employees" />
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

  it('updates max employee limit when editing package feature limits', async () => {
    const packageUpdateBodies = [];
    const featureUpdateBodies = [];

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
                code: 'growth',
                name: 'Growth',
                description: 'Growth package',
                monthlyPrice: 200,
                yearlyPrice: 2200,
                billingUnit: 'company',
                status: 'active',
                features: [
                  { id: 1, code: 'payroll', name: 'Payroll', limit: null },
                  { id: 2, code: 'max_employees', name: 'Maximum Employees', limit: 25 },
                ],
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
              code: 'growth',
              name: 'Growth',
              description: 'Growth package',
              monthlyPrice: 200,
              yearlyPrice: 2200,
              billingUnit: 'company',
              status: 'active',
              features: [
                { id: 1, code: 'payroll', name: 'Payroll', limit: null },
                { id: 2, code: 'max_employees', name: 'Maximum Employees', limit: 25 },
              ],
            },
          }),
        };
      }

      if (target === '/v1/saas/packages/pkg-uuid' && options.method === 'PUT') {
        packageUpdateBodies.push(JSON.parse(options.body));
        return {
          ok: true,
          status: 200,
          json: async () => ({ success: true, data: { id: 'pkg-uuid' } }),
        };
      }

      if (target === '/v1/saas/packages/features/2' && options.method === 'PUT') {
        featureUpdateBodies.push(JSON.parse(options.body));
        return {
          ok: true,
          status: 200,
          json: async () => ({ success: true, data: { id: 2 } }),
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

    const topLimitInput = document.getElementById('input_package_max_employees');
    const limitInput = document.querySelector('[data-feature-limit-code="max_employees"]');
    expect(topLimitInput).toBeTruthy();
    expect(limitInput).toBeNull();
    expect(topLimitInput.value).toBe('25');

    topLimitInput.value = '80';
    topLimitInput.dispatchEvent(new Event('input', { bubbles: true }));
    expect(topLimitInput.value).toBe('80');

    manager.handleSavePackage(document.getElementById('packageForm'));
    await flush();

    expect(packageUpdateBodies).toHaveLength(1);
    expect(featureUpdateBodies).toHaveLength(1);
    expect(featureUpdateBodies[0]).toMatchObject({
      feature_name: 'Maximum Employees',
      limit: 80,
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

  it('renders active subscribers column and counts from API payload', async () => {
    const fetchMock = vi.fn(async (url) => {
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
                code: 'starter',
                name: 'Starter',
                description: 'Starter package',
                monthlyPrice: 50,
                yearlyPrice: 500,
                billingUnit: 'company',
                status: 'active',
                activeSubscriptionsCount: 12,
                totalSubscriptionsCount: 19,
                features: [],
              },
            ],
            pagination: { last_page: 1 },
          }),
        };
      }

      return {
        ok: true,
        status: 200,
        json: async () => ({ success: true, data: [], pagination: { last_page: 1 } }),
      };
    });

    await loadPackagesManager(fetchMock);

    const headText = Array.from(document.querySelectorAll('thead th'))
      .map((th) => th.textContent.trim())
      .join(' | ');
    expect(headText).toContain('Active Subscribers');

    const rowText = document.querySelector('tbody tr')?.textContent || '';
    expect(rowText).toContain('12');
    expect(rowText).toContain('Total riwayat: 19');
  });

  it('renders package features modal badges without losing manager context', async () => {
    const fetchMock = vi.fn(async (url) => {
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
                code: 'growth',
                name: 'Growth',
                description: 'Growth package',
                monthlyPrice: 200,
                yearlyPrice: 2200,
                billingUnit: 'company',
                status: 'active',
                features: [
                  { id: 1, code: 'payroll', name: 'Payroll', limit: null },
                  { id: 2, code: 'max_employees', name: 'Maximum Employees', limit: 25 },
                ],
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

      return {
        ok: true,
        status: 200,
        json: async () => ({ success: true, data: [], pagination: { last_page: 1 } }),
      };
    });

    const manager = await loadPackagesManager(fetchMock);

    expect(() => manager.showFeaturesModal('pkg-uuid')).not.toThrow();
    expect(document.getElementById('features_container').textContent).toContain('Growth');
    expect(document.getElementById('features_container').textContent).toContain('Included: 2');
    expect(document.getElementById('features_container').textContent).toContain('Maximum Employees: 25 org');
  });

  it('blurs focused controls before features modal hides', async () => {
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

    await loadPackagesManager(fetchMock);

    const featuresModal = document.getElementById('featuresModal');
    featuresModal.innerHTML = '<button type="button" class="btn-close">Close</button>';
    const closeButton = featuresModal.querySelector('button');
    closeButton.focus();

    expect(document.activeElement).toBe(closeButton);

    featuresModal.dispatchEvent(new Event('hide.bs.modal'));

    expect(document.activeElement).not.toBe(closeButton);
  });

  it('resets package modal scroll containers when modal state is refreshed', async () => {
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
    const packageModal = document.getElementById('packageModal');

    packageModal.innerHTML = `
      <div class="modal-body">
        <div class="package-modal-panel"></div>
        <div class="package-modal-panel">
          <div class="package-feature-catalog"></div>
          <div data-package-compliance-snapshot></div>
        </div>
      </div>
    `;

    const scrollables = packageModal.querySelectorAll('.modal-body, .package-modal-panel, .package-feature-catalog, [data-package-compliance-snapshot]');
    scrollables.forEach((element) => {
      element.scrollTop = 48;
    });

    manager.resetPackageModalState();

    scrollables.forEach((element) => {
      expect(element.scrollTop).toBe(0);
    });
  });

  it('resets package modal scroll containers on modal lifecycle events', async () => {
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

    await loadPackagesManager(fetchMock);

    const packageModal = document.getElementById('packageModal');
    packageModal.innerHTML = `
      <div class="modal-body">
        <div class="package-modal-panel"></div>
        <div class="package-modal-panel">
          <div class="package-feature-catalog"></div>
          <div data-package-compliance-snapshot></div>
        </div>
      </div>
    `;

    const scrollables = packageModal.querySelectorAll('.modal-body, .package-modal-panel, .package-feature-catalog, [data-package-compliance-snapshot]');
    scrollables.forEach((element) => {
      element.scrollTop = 64;
    });

    packageModal.dispatchEvent(new Event('show.bs.modal'));

    scrollables.forEach((element) => {
      expect(element.scrollTop).toBe(0);
      element.scrollTop = 64;
    });

    packageModal.dispatchEvent(new Event('shown.bs.modal'));
    scrollables.forEach((element) => {
      expect(element.scrollTop).toBe(0);
      element.scrollTop = 64;
    });

    packageModal.dispatchEvent(new Event('hidden.bs.modal'));
    scrollables.forEach((element) => {
      expect(element.scrollTop).toBe(0);
    });
  });

  it('shows info popup when package delete is blocked with PACKAGE_IN_USE', async () => {
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
                code: 'starter',
                name: 'Starter',
                description: 'Starter package',
                monthlyPrice: 50,
                yearlyPrice: 500,
                billingUnit: 'company',
                status: 'active',
                activeSubscriptionsCount: 3,
                totalSubscriptionsCount: 5,
                features: [],
              },
            ],
            pagination: { last_page: 1 },
          }),
        };
      }

      if (target === '/v1/saas/packages/pkg-uuid' && options.method === 'DELETE') {
        return {
          ok: false,
          status: 422,
          json: async () => ({
            success: false,
            error: {
              code: 'PACKAGE_IN_USE',
              message: 'Package cannot be deleted while subscription history still references it.',
            },
          }),
        };
      }

      return {
        ok: true,
        status: 200,
        json: async () => ({ success: true, data: [], pagination: { last_page: 1 } }),
      };
    });

    const confirmDelete = vi.fn(async () => true);
    const showInfo = vi.fn();
    window.ArcavUi = {
      confirmDelete,
      showInfo,
    };

    const manager = await loadPackagesManager(fetchMock);

    await manager.deletePackage('pkg-uuid');
    await flush();

    expect(confirmDelete).toHaveBeenCalled();
    expect(showInfo).toHaveBeenCalledWith(
      'Package Masih Digunakan',
      'Package cannot be deleted while subscription history still references it.'
    );

    const alert = document.querySelector('.alert');
    expect(alert).toBeTruthy();
    expect(alert.textContent).toContain('Package cannot be deleted while subscription history still references it.');
  });

  it('prefers backend feature catalog so latest feature codes appear in package composer', async () => {
    const fetchMock = vi.fn(async (url) => {
      const target = String(url);

      if (target === '/api-token') {
        return {
          ok: true,
          status: 200,
          json: async () => ({ success: true, data: { token: 'packages-token' } }),
        };
      }

      if (target === '/v1/saas/packages/feature-catalog') {
        return {
          ok: true,
          status: 200,
          json: async () => ({
            success: true,
            data: [
              {
                module: 'support',
                title: 'Support',
                description: 'Support and internal workflow',
                features: [
                  { code: 'tickets', name: 'Tickets', description: 'Internal helpdesk module' },
                  { code: 'asset_management', name: 'Asset Management', description: 'Asset inventory module' },
                ],
              },
            ],
          }),
        };
      }

      if (target.startsWith('/v1/saas/packages?')) {
        return {
          ok: true,
          status: 200,
          json: async () => ({ success: true, data: [], pagination: { last_page: 1 } }),
        };
      }

      if (target.startsWith('/v1/saas/package-addons?')) {
        return {
          ok: true,
          status: 200,
          json: async () => ({ success: true, data: [], pagination: { last_page: 1 } }),
        };
      }

      return {
        ok: true,
        status: 200,
        json: async () => ({ success: true, data: [] }),
      };
    });

    await loadPackagesManager(fetchMock);

    expect(document.getElementById('input_package_feature_chips').textContent).toContain('Tickets');
    expect(document.getElementById('input_package_feature_chips').textContent).toContain('Asset Management');
  });

  it('syncPackageFeatures only deletes unchecked codes from active catalog', async () => {
    const deletedFeatureIds = [];

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
          json: async () => ({ success: true, data: [], pagination: { last_page: 1 } }),
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
              code: 'enterprise',
              name: 'Enterprise',
              features: [
                { id: 11, code: 'legacy_enterprise_feature', name: 'Legacy Enterprise Feature', limit: null },
                { id: 12, code: 'tickets', name: 'Tickets', limit: null },
              ],
            },
          }),
        };
      }

      if (target === '/v1/saas/packages/features/12' && options.method === 'DELETE') {
        deletedFeatureIds.push(12);
        return {
          ok: true,
          status: 200,
          json: async () => ({ success: true }),
        };
      }

      if (target === '/v1/saas/packages/features/11' && options.method === 'DELETE') {
        deletedFeatureIds.push(11);
        return {
          ok: true,
          status: 200,
          json: async () => ({ success: true }),
        };
      }

      return {
        ok: true,
        status: 200,
        json: async () => ({ success: true, data: [] }),
      };
    });

    const manager = await loadPackagesManager(fetchMock);

    await manager.syncPackageFeatures('pkg-uuid', []);
    await flush();

    expect(deletedFeatureIds).toContain(12);
    expect(deletedFeatureIds).not.toContain(11);
  });

  it('editPackage only checks features that are truly included', async () => {
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
          json: async () => ({ success: true, data: [], pagination: { last_page: 1 } }),
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
              code: 'enterprise',
              name: 'Enterprise',
              description: 'Enterprise package',
              monthlyPrice: 999,
              yearlyPrice: 9999,
              billingUnit: 'company',
              status: 'active',
              features: [
                { id: 21, code: 'tickets', name: 'Tickets', isIncluded: false, limit: null },
                { id: 22, code: 'asset_management', name: 'Asset Management', isIncluded: true, limit: 0 },
                { id: 23, code: 'payroll', name: 'Payroll', isIncluded: true, limit: null },
              ],
            },
          }),
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

    const checked = Array.from(
      document.querySelectorAll("input[type='checkbox'][name='package_feature_codes']:checked")
    ).map((el) => el.value);

    expect(checked).toContain('payroll');
    expect(checked).not.toContain('tickets');
    expect(checked).not.toContain('asset_management');
  });

  it('shows Included count based on catalog-backed included features', async () => {
    const fetchMock = vi.fn(async (url) => {
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
                code: 'enterprise',
                name: 'Enterprise',
                description: 'Enterprise package',
                monthlyPrice: 999,
                yearlyPrice: 9999,
                billingUnit: 'company',
                status: 'active',
                features: [
                  { id: 31, code: 'payroll', name: 'Payroll', isIncluded: true, limit: null },
                  { id: 32, code: 'tickets', name: 'Tickets', isIncluded: false, limit: null },
                  { id: 33, code: 'legacy_enterprise_feature', name: 'Legacy Enterprise Feature', isIncluded: true, limit: null },
                ],
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

      return {
        ok: true,
        status: 200,
        json: async () => ({ success: true, data: [] }),
      };
    });

    await loadPackagesManager(fetchMock);

    const pageText = document.body.textContent || '';
    expect(pageText).toContain('Included: 1');
  });
});