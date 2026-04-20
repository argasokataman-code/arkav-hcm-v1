import { beforeEach, describe, expect, it, vi } from 'vitest';

async function loadResignationModule() {
  vi.resetModules();
  await import('../../../frontend/resources/js/resignation-data.js');
}

async function loadResignationModuleWithDom() {
  await loadResignationModule();
  document.dispatchEvent(new Event('DOMContentLoaded'));
  await flush();
}

function flush() {
  return Promise.resolve()
    .then(() => Promise.resolve())
    .then(() => Promise.resolve())
    .then(() => Promise.resolve())
    .then(() => Promise.resolve())
    .then(() => Promise.resolve());
}

function buildResignationDom() {
  document.body.innerHTML = `
    <div class="main-wrapper" data-subscription-status="trial" data-role-scope="hcm-admin"></div>
    <table><tbody data-arcav-resignations-tbody></tbody></table>
    <button data-arcav-resignation-add></button>
    <div id="arcav_resignation_modal"></div>
    <div id="arcav_resignation_detail_modal"></div>
  `;
}

function buildManageableResignationDom() {
  document.body.innerHTML = `
    <div class="main-wrapper"></div>
    <table><tbody data-arcav-resignations-tbody></tbody></table>
    <button data-arcav-resignation-add></button>
    <div id="arcav_resignation_modal">
      <form data-arcav-resignation-form>
        <div data-arcav-resignation-flash class="d-none"></div>
        <input data-arcav-resignation-id />
        <select data-arcav-resignation-user></select>
        <input data-arcav-resignation-notice-date />
        <input data-arcav-resignation-resignation-date />
        <input data-arcav-resignation-department />
        <textarea data-arcav-resignation-reason></textarea>
        <textarea data-arcav-resignation-notes></textarea>
        <select data-arcav-resignation-status><option value="pending">pending</option><option value="approved">approved</option></select>
        <button type="submit">Save</button>
      </form>
      <div data-arcav-resignation-modal-title></div>
    </div>
    <div id="arcav_resignation_detail_modal">
      <div data-arcav-resignation-detail-loading class="d-none"></div>
      <div data-arcav-resignation-detail-error class="d-none"></div>
      <div data-arcav-resignation-detail-body class="d-none"></div>
      <a data-arcav-resignation-detail-profile href="/employee-details"></a>
      <div data-arcav-resignation-detail-employee></div>
      <div data-arcav-resignation-detail-email></div>
      <div data-arcav-resignation-detail-department></div>
      <div data-arcav-resignation-detail-status></div>
      <div data-arcav-resignation-detail-notice-date></div>
      <div data-arcav-resignation-detail-resignation-date></div>
      <div data-arcav-resignation-detail-reason></div>
      <div data-arcav-resignation-detail-notes></div>
      <div data-arcav-resignation-detail-created></div>
    </div>
  `;
}

function installBootstrapModalStub() {
  window.bootstrap = {
    Modal: {
      getOrCreateInstance: () => ({ show() {}, hide() {} }),
    },
  };
}

describe('Resignation wiring', () => {
  beforeEach(() => {
    localStorage.clear();
    buildResignationDom();
    window.__ARCAV_DISABLE_REDIRECTS__ = true;
    delete window.__ARCAV_LAST_REDIRECT__;
    window.AuthApi = {
      getToken: () => 'token-abc',
      getTenantContext: () => ({
        companyCode: 'ACME',
        companyId: 99,
        companyUuid: '11111111-2222-3333-4444-555555555555',
      }),
    };
  });

  it('redirects away when resignation.view permission is missing', async () => {
    const fetchMock = vi.fn((url) => {
      if (String(url).includes('/v1/identity/auth/me')) {
        return Promise.resolve({
          ok: true,
          status: 200,
          json: async () => ({ success: true, data: { permissions: {} } }),
        });
      }

      throw new Error(`Unexpected fetch: ${url}`);
    });

    vi.stubGlobal('fetch', fetchMock);

    await loadResignationModule();
    document.dispatchEvent(new Event('DOMContentLoaded'));
    await flush();

    expect(fetchMock).toHaveBeenCalledTimes(1);
    expect(window.__ARCAV_LAST_REDIRECT__).toBe('/employee-dashboard');
    expect(document.querySelector('[data-arcav-resignations-tbody]').textContent).toBe('');
  });

  it('loads resignation list with auth and tenant headers when permission exists', async () => {
    const fetchMock = vi.fn((url, options = {}) => {
      if (String(url).includes('/v1/identity/auth/me')) {
        return Promise.resolve({
          ok: true,
          status: 200,
          json: async () => ({ success: true, data: { permissions: { 'resignation.view': true } } }),
        });
      }

      if (String(url).includes('/v1/hcm/resignations')) {
        return Promise.resolve({
          ok: true,
          status: 200,
          json: async () => ({ success: true, data: [], meta: { currentPage: 1, lastPage: 1, perPage: 20, total: 0 } }),
        });
      }

      throw new Error(`Unexpected fetch: ${url}`);
    });

    vi.stubGlobal('fetch', fetchMock);

    await loadResignationModule();
    document.dispatchEvent(new Event('DOMContentLoaded'));
    await flush();

    expect(fetchMock).toHaveBeenCalledTimes(2);
    const [meUrl, meOptions] = fetchMock.mock.calls[0];
    expect(String(meUrl)).toBe('/v1/identity/auth/me');
    expect(meOptions.headers.Authorization).toBe('Bearer token-abc');
    expect(meOptions.headers['X-Company-Code']).toBe('ACME');
    expect(meOptions.headers['X-Company-Id']).toBe('99');
    expect(meOptions.headers['X-Company-UUID']).toBe('11111111-2222-3333-4444-555555555555');

    const [listUrl, listOptions] = fetchMock.mock.calls[1];
    expect(String(listUrl)).toContain('/v1/hcm/resignations');
    expect(listOptions.headers.Authorization).toBe('Bearer token-abc');
    expect(listOptions.headers['X-Company-Id']).toBe('99');
    expect(document.querySelector('[data-arcav-resignations-tbody]').textContent).toContain('No resignations found');
  });

  it('submits create payload with employee uuid from the employee dropdown', async () => {
    buildManageableResignationDom();
    installBootstrapModalStub();

    const fetchMock = vi.fn((url, options = {}) => {
      if (String(url).includes('/v1/identity/auth/me')) {
        return Promise.resolve({
          ok: true,
          status: 200,
          json: async () => ({ success: true, data: { permissions: { 'resignation.view': true, 'resignation.manage': true } } }),
        });
      }

      if (String(url).includes('/v1/hcm/resignations') && (!options.method || options.method === 'GET')) {
        return Promise.resolve({
          ok: true,
          status: 200,
          json: async () => ({ success: true, data: [], meta: { currentPage: 1, lastPage: 1, perPage: 20, total: 0 } }),
        });
      }

      if (String(url).includes('/v1/hcm/employees?perPage=100')) {
        return Promise.resolve({
          ok: true,
          status: 200,
          json: async () => ({
            success: true,
            data: [
              { id: 7, uuid: 'emp-uuid-7', fullName: 'Emp Seven', email: 'emp7@example.com' },
            ],
          }),
        });
      }

      if (String(url).includes('/v1/hcm/employees/emp-uuid-7')) {
        return Promise.resolve({
          ok: true,
          status: 200,
          json: async () => ({ success: true, data: { team: 'Finance' } }),
        });
      }

      if (String(url) === '/v1/hcm/resignations' && String(options.method).toUpperCase() === 'POST') {
        return Promise.resolve({
          ok: true,
          status: 201,
          json: async () => ({ success: true, data: { id: 55 } }),
        });
      }

      throw new Error(`Unexpected fetch: ${url}`);
    });

    vi.stubGlobal('fetch', fetchMock);

    await loadResignationModuleWithDom();

    const userSelect = document.querySelector('[data-arcav-resignation-user]');
    userSelect.innerHTML = '<option value="emp-uuid-7">Emp Seven</option>';
    userSelect.value = 'emp-uuid-7';
    userSelect.dispatchEvent(new Event('change'));
    await flush();

    document.querySelector('[data-arcav-resignation-notice-date]').value = '2026-04-01';
    document.querySelector('[data-arcav-resignation-resignation-date]').value = '2026-04-30';
    document.querySelector('[data-arcav-resignation-reason]').value = 'Career change';
    document.querySelector('[data-arcav-resignation-status]').value = 'pending';

    document.querySelector('[data-arcav-resignation-form]').dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
    await flush();

    const createCall = fetchMock.mock.calls.find(([url, options]) => String(url) === '/v1/hcm/resignations' && String(options?.method).toUpperCase() === 'POST');
    expect(createCall).toBeTruthy();
    expect(JSON.parse(createCall[1].body)).toMatchObject({
      userId: 'emp-uuid-7',
      department: 'Finance',
      reason: 'Career change',
      noticeDate: '2026-04-01',
      resignationDate: '2026-04-30',
      status: 'pending',
    });
  });

  it('submits edit flow through UUID route identifiers and keeps employee selector on UUID', async () => {
    buildManageableResignationDom();
    installBootstrapModalStub();

    const fetchMock = vi.fn((url, options = {}) => {
      if (String(url).includes('/v1/identity/auth/me')) {
        return Promise.resolve({
          ok: true,
          status: 200,
          json: async () => ({ success: true, data: { permissions: { 'resignation.view': true, 'resignation.manage': true } } }),
        });
      }

      if ((String(url) === '/v1/hcm/resignations' || String(url).includes('/v1/hcm/resignations?perPage=100')) && (!options.method || options.method === 'GET')) {
        return Promise.resolve({
          ok: true,
          status: 200,
          json: async () => ({ success: true, data: [{
            id: 12,
            uuid: 'res-uuid-12',
            employee: { id: 7, uuid: 'emp-uuid-7', name: 'Emp Seven', email: 'emp7@example.com' },
            department: 'Finance',
            reason: 'Career change',
            noticeDate: '2026-04-01',
            resignationDate: '2026-04-30',
            status: 'pending',
            notes: 'Old note',
          }], meta: { currentPage: 1, lastPage: 1, perPage: 20, total: 1 } }),
        });
      }

      if (String(url).includes('/v1/hcm/employees?perPage=100')) {
        return Promise.resolve({
          ok: true,
          status: 200,
          json: async () => ({ success: true, data: [{ id: 7, uuid: 'emp-uuid-7', fullName: 'Emp Seven', email: 'emp7@example.com' }] }),
        });
      }

      if (String(url) === '/v1/hcm/resignations/res-uuid-12' && String(options.method).toUpperCase() === 'PUT') {
        return Promise.resolve({ ok: true, status: 200, json: async () => ({ success: true }) });
      }

      throw new Error(`Unexpected fetch: ${url}`);
    });

    vi.stubGlobal('fetch', fetchMock);
    await loadResignationModuleWithDom();

    document.querySelector('[data-arcav-resignation-edit="res-uuid-12"]').dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true }));
    await flush();

    expect(document.querySelector('[data-arcav-resignation-user]').value).toBe('emp-uuid-7');
    document.querySelector('[data-arcav-resignation-reason]').value = 'Updated reason';
    document.querySelector('[data-arcav-resignation-form]').dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
    await flush();

    const putCall = fetchMock.mock.calls.find(([url, options]) => String(url) === '/v1/hcm/resignations/res-uuid-12' && String(options?.method).toUpperCase() === 'PUT');
    expect(putCall).toBeTruthy();
    expect(JSON.parse(putCall[1].body)).toMatchObject({ reason: 'Updated reason' });
  });

  it('renders backend 422 errors inside the form flash area', async () => {
    buildManageableResignationDom();
    installBootstrapModalStub();

    const fetchMock = vi.fn((url, options = {}) => {
      if (String(url).includes('/v1/identity/auth/me')) {
        return Promise.resolve({ ok: true, status: 200, json: async () => ({ success: true, data: { permissions: { 'resignation.view': true, 'resignation.manage': true } } }) });
      }
      if (String(url).includes('/v1/hcm/resignations') && (!options.method || options.method === 'GET')) {
        return Promise.resolve({ ok: true, status: 200, json: async () => ({ success: true, data: [], meta: { currentPage: 1, lastPage: 1, perPage: 20, total: 0 } }) });
      }
      if (String(url).includes('/v1/hcm/employees?perPage=100')) {
        return Promise.resolve({ ok: true, status: 200, json: async () => ({ success: true, data: [{ id: 7, uuid: 'emp-uuid-7', fullName: 'Emp Seven', email: 'emp7@example.com' }] }) });
      }
      if (String(url).includes('/v1/hcm/employees/emp-uuid-7')) {
        return Promise.resolve({ ok: true, status: 200, json: async () => ({ success: true, data: { team: 'Finance' } }) });
      }
      if (String(url) === '/v1/hcm/resignations' && String(options.method).toUpperCase() === 'POST') {
        return Promise.resolve({ ok: false, status: 422, json: async () => ({ success: false, error: { code: 'VALIDATION_ERROR', message: 'The selected user id is invalid for the active company.' } }) });
      }
      throw new Error(`Unexpected fetch: ${url}`);
    });

    vi.stubGlobal('fetch', fetchMock);
    await loadResignationModuleWithDom();

    const userSelect = document.querySelector('[data-arcav-resignation-user]');
    userSelect.innerHTML = '<option value="emp-uuid-7">Emp Seven</option>';
    userSelect.value = 'emp-uuid-7';
    userSelect.dispatchEvent(new Event('change'));
    await flush();

    document.querySelector('[data-arcav-resignation-notice-date]').value = '2026-04-01';
    document.querySelector('[data-arcav-resignation-resignation-date]').value = '2026-04-30';
    document.querySelector('[data-arcav-resignation-reason]').value = 'Career change';
    document.querySelector('[data-arcav-resignation-form]').dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
    await flush();

    expect(document.querySelector('[data-arcav-resignation-flash]').textContent).toContain('The selected user id is invalid for the active company.');
  });

  it('keeps create flow working when employee detail autofill fails', async () => {
    buildManageableResignationDom();
    installBootstrapModalStub();

    const fetchMock = vi.fn((url, options = {}) => {
      if (String(url).includes('/v1/identity/auth/me')) {
        return Promise.resolve({ ok: true, status: 200, json: async () => ({ success: true, data: { permissions: { 'resignation.view': true, 'resignation.manage': true } } }) });
      }
      if (String(url).includes('/v1/hcm/resignations') && (!options.method || options.method === 'GET')) {
        return Promise.resolve({ ok: true, status: 200, json: async () => ({ success: true, data: [], meta: { currentPage: 1, lastPage: 1, perPage: 20, total: 0 } }) });
      }
      if (String(url).includes('/v1/hcm/employees?perPage=100')) {
        return Promise.resolve({ ok: true, status: 200, json: async () => ({ success: true, data: [{ id: 7, uuid: 'emp-uuid-7', fullName: 'Emp Seven', email: 'emp7@example.com' }] }) });
      }
      if (String(url).includes('/v1/hcm/employees/emp-uuid-7')) {
        return Promise.resolve({ ok: false, status: 500, json: async () => ({ success: false }) });
      }
      if (String(url) === '/v1/hcm/resignations' && String(options.method).toUpperCase() === 'POST') {
        return Promise.resolve({ ok: true, status: 201, json: async () => ({ success: true, data: { id: 91 } }) });
      }
      throw new Error(`Unexpected fetch: ${url}`);
    });

    vi.stubGlobal('fetch', fetchMock);
    await loadResignationModuleWithDom();

    const userSelect = document.querySelector('[data-arcav-resignation-user]');
    userSelect.innerHTML = '<option value="emp-uuid-7">Emp Seven</option>';
    userSelect.value = 'emp-uuid-7';
    userSelect.dispatchEvent(new Event('change'));
    await flush();

    expect(document.querySelector('[data-arcav-resignation-department]').value).toBe('');
    document.querySelector('[data-arcav-resignation-notice-date]').value = '2026-04-01';
    document.querySelector('[data-arcav-resignation-resignation-date]').value = '2026-04-30';
    document.querySelector('[data-arcav-resignation-reason]').value = 'Career change';
    document.querySelector('[data-arcav-resignation-form]').dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
    await flush();

    const createCall = fetchMock.mock.calls.find(([url, options]) => String(url) === '/v1/hcm/resignations' && String(options?.method).toUpperCase() === 'POST');
    expect(createCall).toBeTruthy();
    expect(JSON.parse(createCall[1].body)).toMatchObject({ userId: 'emp-uuid-7' });
  });

  it('shows delete failure feedback when the delete request fails', async () => {
    buildManageableResignationDom();
    installBootstrapModalStub();
    window.ArcavUi = {
      confirmDelete: vi.fn(() => Promise.resolve(true)),
      toast: vi.fn(),
    };

    const fetchMock = vi.fn((url, options = {}) => {
      if (String(url).includes('/v1/identity/auth/me')) {
        return Promise.resolve({ ok: true, status: 200, json: async () => ({ success: true, data: { permissions: { 'resignation.view': true, 'resignation.manage': true } } }) });
      }
      if ((String(url) === '/v1/hcm/resignations' || String(url).includes('/v1/hcm/resignations?perPage=100')) && (!options.method || options.method === 'GET')) {
        return Promise.resolve({
          ok: true,
          status: 200,
          json: async () => ({ success: true, data: [{ id: 12, uuid: 'res-uuid-12', employee: { id: 7, uuid: 'emp-uuid-7', name: 'Emp Seven', email: 'emp7@example.com' }, department: 'Finance', reason: 'Career change', noticeDate: '2026-04-01', resignationDate: '2026-04-30', status: 'pending', notes: 'Old note' }], meta: { currentPage: 1, lastPage: 1, perPage: 20, total: 1 } }),
        });
      }
      if (String(url) === '/v1/hcm/resignations/res-uuid-12' && String(options.method).toUpperCase() === 'DELETE') {
        return Promise.resolve({ ok: false, status: 500, json: async () => ({ success: false }) });
      }
      throw new Error(`Unexpected fetch: ${url}`);
    });

    vi.stubGlobal('fetch', fetchMock);
    await loadResignationModuleWithDom();

    document.querySelector('[data-arcav-resignation-delete="res-uuid-12"]').dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true }));
    await flush();

    expect(window.ArcavUi.toast).toHaveBeenCalledWith('Failed to delete resignation.', 'danger');
  });
});