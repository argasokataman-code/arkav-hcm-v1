import { beforeEach, describe, expect, it, vi } from 'vitest';

const flush = async () => {
  await Promise.resolve();
  await new Promise((resolve) => setTimeout(resolve, 0));
};

describe('email settings wiring', () => {
  beforeEach(() => {
    vi.resetModules();

    document.body.innerHTML = `
      <meta name="csrf-token" content="csrf-token-test">
      <div class="alert d-none" data-email-settings-feedback></div>
      <form data-email-settings-shell>
        <button type="submit" data-email-settings-save disabled>Save</button>
      </form>

      <div data-mailtrap-status-card>
        <span data-mailtrap-status-badge class="badge badge-secondary">Unknown</span>
        <span data-mailtrap-status-text>Checking configuration...</span>
        <button type="button" data-mailtrap-status-refresh>Refresh</button>
      </div>

      <input data-provider-switch="mailtrap" type="checkbox" checked>
      <input data-provider-switch="smtp" type="checkbox">

      <span data-provider-status="mailtrap" class="btn btn-light"></span>
      <span data-provider-status="smtp" class="btn btn-light"></span>

      <div data-email-settings-modal-feedback="mailtrap" class="alert d-none"></div>
      <form data-email-settings-form="mailtrap">
        <input data-email-settings-field="fromAddress" data-provider="mailtrap" type="email">
        <input data-email-settings-field="fromName" data-provider="mailtrap" type="text">
        <input data-email-settings-field="mailtrap.accountId" data-provider="mailtrap" type="number">
        <input data-email-settings-field="mailtrap.apiToken" data-provider="mailtrap" type="password">
        <div data-email-settings-mask="mailtrap.apiToken"></div>
        <div data-email-settings-test-result="mailtrap" class="d-none"></div>
        <button type="button" data-email-settings-test-button="mailtrap">Test Connection</button>
        <button type="submit" data-email-settings-submit="mailtrap">Save</button>
      </form>

      <div data-email-settings-modal-feedback="smtp" class="alert d-none"></div>
      <form data-email-settings-form="smtp">
        <input data-email-settings-field="fromAddress" data-provider="smtp" type="email">
        <input data-email-settings-field="fromName" data-provider="smtp" type="text">
        <input data-email-settings-field="smtp.host" data-provider="smtp" type="text">
        <input data-email-settings-field="smtp.port" data-provider="smtp" type="number">
        <select data-email-settings-field="smtp.encryption" data-provider="smtp">
          <option value="tls">TLS</option>
          <option value="ssl">SSL</option>
          <option value="none">None</option>
        </select>
        <input data-email-settings-field="smtp.username" data-provider="smtp" type="text">
        <input data-email-settings-field="smtp.password" data-provider="smtp" type="password">
        <div data-email-settings-mask="smtp.password"></div>
        <div data-email-settings-test-result="smtp" class="d-none"></div>
        <button type="button" data-email-settings-test-button="smtp">Test Connection</button>
        <button type="submit" data-email-settings-submit="smtp">Save</button>
      </form>
    `;

    window.AuthApi = {
      getToken: vi.fn(() => 'email-token'),
      getTenantContext: vi.fn(() => ({ companyCode: 'tenant-email', companyId: 77, companyUuid: 'tenant-uuid-77' })),
    };

    global.fetch = vi.fn((url, options = {}) => {
      if (url === '/v1/hcm/email-settings/mailtrap-status') {
        return Promise.resolve({
          ok: true,
          json: () => Promise.resolve({
            success: true,
            data: {
              accountId: 3229,
              tokenConfigured: true,
              connected: true,
              visibleTokenCount: 2,
              tokenLast4: '9012',
            },
          }),
        });
      }

      if (url === '/v1/hcm/email-settings' && (!options.method || options.method === 'GET')) {
        return Promise.resolve({
          ok: true,
          json: () => Promise.resolve({
            success: true,
            data: {
              provider: 'mailtrap',
              fromAddress: 'noreply@example.com',
              fromName: 'Arkav Mail',
              smtp: {
                host: 'smtp.example.com',
                port: 587,
                encryption: 'tls',
                username: 'smtp-user',
                passwordMasked: '****1234',
                configured: true,
              },
              mailtrap: {
                accountId: 3229,
                apiTokenMasked: '****5678',
                configured: true,
              },
            },
          }),
        });
      }

      if (url === '/v1/hcm/email-settings/test-connection') {
        return Promise.resolve({
          ok: true,
          json: () => Promise.resolve({
            success: true,
            data: {
              connected: true,
              error: null,
            },
          }),
        });
      }

      if (url === '/v1/hcm/email-settings' && options.method === 'PUT') {
        return Promise.resolve({
          ok: true,
          json: () => Promise.resolve({
            success: true,
            data: {
              provider: 'smtp',
              fromAddress: 'noreply@example.com',
              fromName: 'Arkav Mail',
              smtp: {
                host: 'smtp.example.com',
                port: 587,
                encryption: 'tls',
                username: 'smtp-user',
                passwordMasked: '****1234',
                configured: true,
              },
              mailtrap: {
                accountId: 3229,
                apiTokenMasked: '****5678',
                configured: true,
              },
            },
          }),
        });
      }

      return Promise.reject(new Error(`Unexpected fetch: ${url}`));
    });
  });

  it('loads profile and mailtrap status on init', async () => {
    await import('../../../frontend/resources/js/email-settings-data.js');
    await flush();

    expect(document.querySelector('[data-mailtrap-status-badge]')?.textContent).toContain('Connected');
    expect(document.querySelector('[data-mailtrap-status-text]')?.textContent).toContain('Account #3229 connected');
    expect(document.querySelector('[data-email-settings-field="fromAddress"][data-provider="smtp"]')?.value).toBe('noreply@example.com');
    expect(document.querySelector('[data-email-settings-mask="mailtrap.apiToken"]')?.textContent).toContain('****5678');
  });

  it('sends smtp test connection payload and renders result state', async () => {
    await import('../../../frontend/resources/js/email-settings-data.js');
    await flush();

    document.querySelector('[data-email-settings-field="smtp.password"][data-provider="smtp"]').value = 'smtp-secret';
    document.querySelector('[data-email-settings-test-button="smtp"]').click();
    await flush();

    const testCall = global.fetch.mock.calls.find(([target, options]) => target === '/v1/hcm/email-settings/test-connection' && options?.method === 'POST');
    expect(testCall).toBeTruthy();

    const body = JSON.parse(testCall[1].body);
    expect(body.provider).toBe('smtp');
    expect(body.smtp.password).toBe('smtp-secret');

    expect(document.querySelector('[data-email-settings-test-result="smtp"]')?.textContent).toContain('Connection successful');
  });

  it('saves provider settings with tenant headers and keeps secret omitted when unchanged', async () => {
    await import('../../../frontend/resources/js/email-settings-data.js');
    await flush();

    document.querySelector('[data-email-settings-form="smtp"]').dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
    await flush();

    const saveCall = global.fetch.mock.calls.find(([target, options]) => target === '/v1/hcm/email-settings' && options?.method === 'PUT');
    expect(saveCall).toBeTruthy();
    expect(saveCall[1].headers.Authorization).toBe('Bearer email-token');
    expect(saveCall[1].headers['X-Company-Code']).toBe('tenant-email');
    expect(saveCall[1].headers['X-Company-Id']).toBe('77');

    const body = JSON.parse(saveCall[1].body);
    expect(body.provider).toBe('smtp');
    expect(body.smtp.host).toBe('smtp.example.com');
    expect(body.smtp.password).toBeUndefined();

    expect(document.querySelector('[data-email-settings-modal-feedback="smtp"]')?.textContent).toContain('berhasil disimpan');
  });

  it('shows validation error feedback when save request fails', async () => {
    global.fetch = vi.fn((url, options = {}) => {
      if (url === '/v1/hcm/email-settings/mailtrap-status') {
        return Promise.resolve({
          ok: true,
          json: () => Promise.resolve({
            success: true,
            data: { accountId: 3229, tokenConfigured: true, connected: true, visibleTokenCount: 1 },
          }),
        });
      }

      if (url === '/v1/hcm/email-settings' && (!options.method || options.method === 'GET')) {
        return Promise.resolve({
          ok: true,
          json: () => Promise.resolve({
            success: true,
            data: {
              provider: 'smtp',
              fromAddress: 'noreply@example.com',
              fromName: 'Arkav Mail',
              smtp: {
                host: 'smtp.example.com',
                port: 587,
                encryption: 'tls',
                username: 'smtp-user',
                passwordMasked: '****1234',
                configured: true,
              },
              mailtrap: {
                accountId: 3229,
                apiTokenMasked: '****5678',
                configured: true,
              },
            },
          }),
        });
      }

      if (url === '/v1/hcm/email-settings' && options.method === 'PUT') {
        return Promise.resolve({
          ok: false,
          status: 422,
          json: () => Promise.resolve({
            success: false,
            error: {
              code: 'VALIDATION_ERROR',
              message: 'SMTP host and username are required when provider is smtp.',
            },
          }),
        });
      }

      return Promise.reject(new Error(`Unexpected fetch: ${url}`));
    });

    await import('../../../frontend/resources/js/email-settings-data.js');
    await flush();

    document.querySelector('[data-email-settings-field="smtp.host"][data-provider="smtp"]').value = '';
    document.querySelector('[data-email-settings-field="smtp.username"][data-provider="smtp"]').value = '';

    document.querySelector('[data-email-settings-form="smtp"]').dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
    await flush();

    expect(document.querySelector('[data-email-settings-modal-feedback="smtp"]')?.textContent).toContain('required when provider is smtp');
    expect(document.querySelector('[data-email-settings-feedback]')?.textContent).toContain('required when provider is smtp');
  });
});
