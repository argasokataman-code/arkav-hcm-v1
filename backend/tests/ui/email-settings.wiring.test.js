import { beforeEach, describe, expect, it, vi } from 'vitest';

const flush = async () => {
    await Promise.resolve();
    await new Promise((resolve) => setTimeout(resolve, 0));
};

const SMTP_PROFILE = {
    provider: 'smtp',
    fromAddress: 'no-reply@example.com',
    fromName: 'Test App',
    smtp: {
        host: 'smtp.example.com',
        port: 587,
        encryption: 'tls',
        username: 'user@example.com',
        passwordMasked: '●●●●',
    },
};

const MAILTRAP_PROFILE = {
    provider: 'mailtrap',
    fromAddress: 'no-reply@example.com',
    fromName: 'Test App',
    mailtrap: {
        accountId: 12345,
        apiTokenMasked: '●●●●',
    },
};

function buildDom() {
    document.body.innerHTML = `
        <div class="alert d-none" data-email-settings-feedback></div>
        <small data-email-settings-status></small>
        <div class="d-none" data-email-settings-empty></div>
        <div data-email-settings-loaded>
            <form data-email-settings-form>
                <input type="email" data-field="fromAddress">
                <input type="text" data-field="fromName">
                <select data-email-settings-provider data-field="provider">
                    <option value="smtp">SMTP</option>
                    <option value="mailtrap">Mailtrap</option>
                </select>
                <div data-email-settings-section="smtp">
                    <input type="text" data-field="smtp.host">
                    <input type="number" data-field="smtp.port">
                    <select data-field="smtp.encryption">
                        <option value="tls">TLS</option>
                        <option value="ssl">SSL</option>
                        <option value="none">None</option>
                    </select>
                    <input type="text" data-field="smtp.username">
                    <input type="password" data-field="smtp.password">
                </div>
                <div data-email-settings-section="mailtrap" style="display:none">
                    <input type="number" data-field="mailtrap.accountId">
                    <input type="password" data-field="mailtrap.apiToken">
                </div>
                <div class="d-none" data-email-settings-test-result></div>
                <button type="button" data-email-settings-test-conn>Test Connection</button>
                <button type="button" data-email-settings-cancel>Cancel</button>
                <button type="submit" data-email-settings-submit>Save Settings</button>
            </form>
        </div>
    `;
}

describe('email settings wiring', () => {
    beforeEach(() => {
        vi.resetModules();
        buildDom();

        window.AuthApi = {
            getTenantContext: vi.fn(() => ({})),
            getToken: vi.fn(() => 'test-token-abc'),
        };

        global.fetch = vi.fn((url) => {
            if (url === '/v1/hcm/email-settings') {
                return Promise.resolve({
                    ok: true,
                    status: 200,
                    json: () => Promise.resolve({ success: true, data: SMTP_PROFILE }),
                });
            }
            return Promise.reject(new Error('Unexpected fetch: ' + url));
        });
    });

    it('loads current SMTP profile and populates form fields', async () => {
        await import('../../../frontend/resources/js/email-settings-data.js');
        await flush();

        const host = document.querySelector('[data-field="smtp.host"]');
        const username = document.querySelector('[data-field="smtp.username"]');
        const provider = document.querySelector('[data-email-settings-provider]');

        expect(host).toBeTruthy();
        expect(host.value).toBe('smtp.example.com');
        expect(username.value).toBe('user@example.com');
        expect(provider.value).toBe('smtp');
        expect(document.querySelector('[data-email-settings-status]').textContent).toContain('loaded');
    });

    it('shows SMTP section and hides Mailtrap section when provider is smtp', async () => {
        await import('../../../frontend/resources/js/email-settings-data.js');
        await flush();

        const smtpSection = document.querySelector('[data-email-settings-section="smtp"]');
        const mailtrapSection = document.querySelector('[data-email-settings-section="mailtrap"]');

        expect(smtpSection.style.display).not.toBe('none');
        expect(mailtrapSection.style.display).toBe('none');
    });

    it('switches to Mailtrap section when provider selector changes', async () => {
        await import('../../../frontend/resources/js/email-settings-data.js');
        await flush();

        const providerSelect = document.querySelector('[data-email-settings-provider]');
        providerSelect.value = 'mailtrap';
        providerSelect.dispatchEvent(new Event('change', { bubbles: true }));

        const smtpSection = document.querySelector('[data-email-settings-section="smtp"]');
        const mailtrapSection = document.querySelector('[data-email-settings-section="mailtrap"]');

        expect(smtpSection.style.display).toBe('none');
        expect(mailtrapSection.style.display).not.toBe('none');
    });

    it('saves settings on form submit with auth header', async () => {
        global.fetch = vi.fn((url, options = {}) => {
            if (url === '/v1/hcm/email-settings' && (!options.method || options.method === 'GET')) {
                return Promise.resolve({
                    ok: true,
                    status: 200,
                    json: () => Promise.resolve({ success: true, data: SMTP_PROFILE }),
                });
            }
            if (url === '/v1/hcm/email-settings' && options.method === 'PUT') {
                return Promise.resolve({
                    ok: true,
                    status: 200,
                    json: () => Promise.resolve({ success: true, data: SMTP_PROFILE }),
                });
            }
            return Promise.reject(new Error('Unexpected fetch: ' + url));
        });

        await import('../../../frontend/resources/js/email-settings-data.js');
        await flush();

        const form = document.querySelector('[data-email-settings-form]');
        form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
        await flush();

        const saveCall = global.fetch.mock.calls.find(
            ([url, opts]) => url === '/v1/hcm/email-settings' && opts?.method === 'PUT'
        );
        expect(saveCall).toBeTruthy();
        expect(saveCall[1].headers.Authorization).toBe('Bearer test-token-abc');

        const feedback = document.querySelector('[data-email-settings-feedback]');
        expect(feedback.classList.contains('d-none')).toBe(false);
        expect(feedback.classList.contains('alert-success')).toBe(true);
    });

    it('shows error feedback on save failure', async () => {
        global.fetch = vi.fn((url, options = {}) => {
            if (url === '/v1/hcm/email-settings' && (!options.method || options.method === 'GET')) {
                return Promise.resolve({
                    ok: true,
                    status: 200,
                    json: () => Promise.resolve({ success: true, data: SMTP_PROFILE }),
                });
            }
            if (url === '/v1/hcm/email-settings' && options.method === 'PUT') {
                return Promise.resolve({
                    ok: false,
                    status: 422,
                    json: () => Promise.resolve({
                        success: false,
                        error: { code: 'VALIDATION_ERROR', message: 'SMTP host is required.' },
                    }),
                });
            }
            return Promise.reject(new Error('Unexpected fetch: ' + url));
        });

        await import('../../../frontend/resources/js/email-settings-data.js');
        await flush();

        const form = document.querySelector('[data-email-settings-form]');
        form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
        await flush();

        const feedback = document.querySelector('[data-email-settings-feedback]');
        expect(feedback.classList.contains('alert-danger')).toBe(true);
        expect(feedback.textContent).toContain('SMTP host');
    });

    it('shows access denied feedback on 403', async () => {
        global.fetch = vi.fn(() =>
            Promise.resolve({
                ok: false,
                status: 403,
                json: () => Promise.resolve({ success: false, error: { message: 'Forbidden' } }),
            })
        );

        await import('../../../frontend/resources/js/email-settings-data.js');
        await flush();

        const feedback = document.querySelector('[data-email-settings-feedback]');
        expect(feedback.classList.contains('alert-danger')).toBe(true);
        expect(feedback.textContent).toContain('Access denied');
    });

    it('shows test connection success result', async () => {
        global.fetch = vi.fn((url, options = {}) => {
            if (url === '/v1/hcm/email-settings' && (!options.method || options.method === 'GET')) {
                return Promise.resolve({
                    ok: true,
                    status: 200,
                    json: () => Promise.resolve({ success: true, data: SMTP_PROFILE }),
                });
            }
            if (url === '/v1/hcm/email-settings/test-connection') {
                return Promise.resolve({
                    ok: true,
                    status: 200,
                    json: () => Promise.resolve({
                        success: true,
                        data: { connected: true, latencyMs: 42 },
                    }),
                });
            }
            return Promise.reject(new Error('Unexpected fetch: ' + url));
        });

        await import('../../../frontend/resources/js/email-settings-data.js');
        await flush();

        const testBtn = document.querySelector('[data-email-settings-test-conn]');
        testBtn.click();
        await flush();

        const resultNode = document.querySelector('[data-email-settings-test-result]');
        expect(resultNode.classList.contains('d-none')).toBe(false);
        expect(resultNode.classList.contains('text-success')).toBe(true);
        expect(resultNode.textContent).toContain('✓');
    });

    it('shows test connection failure result', async () => {
        global.fetch = vi.fn((url, options = {}) => {
            if (url === '/v1/hcm/email-settings' && (!options.method || options.method === 'GET')) {
                return Promise.resolve({
                    ok: true,
                    status: 200,
                    json: () => Promise.resolve({ success: true, data: SMTP_PROFILE }),
                });
            }
            if (url === '/v1/hcm/email-settings/test-connection') {
                return Promise.resolve({
                    ok: true,
                    status: 200,
                    json: () => Promise.resolve({
                        success: true,
                        data: { connected: false, error: 'Connection refused' },
                    }),
                });
            }
            return Promise.reject(new Error('Unexpected fetch: ' + url));
        });

        await import('../../../frontend/resources/js/email-settings-data.js');
        await flush();

        const testBtn = document.querySelector('[data-email-settings-test-conn]');
        testBtn.click();
        await flush();

        const resultNode = document.querySelector('[data-email-settings-test-result]');
        expect(resultNode.classList.contains('text-danger')).toBe(true);
        expect(resultNode.textContent).toContain('✗');
        expect(resultNode.textContent).toContain('Connection refused');
    });

    it('shows empty state when no profile is configured', async () => {
        global.fetch = vi.fn(() =>
            Promise.resolve({
                ok: true,
                status: 200,
                json: () => Promise.resolve({ success: true, data: null }),
            })
        );

        await import('../../../frontend/resources/js/email-settings-data.js');
        await flush();

        const emptyState = document.querySelector('[data-email-settings-empty]');
        expect(emptyState.classList.contains('d-none')).toBe(false);
    });

    it('masks existing secret — password field stays empty with placeholder', async () => {
        await import('../../../frontend/resources/js/email-settings-data.js');
        await flush();

        const pwEl = document.querySelector('[data-field="smtp.password"]');
        expect(pwEl.value).toBe('');
        expect(pwEl.placeholder).toContain('leave blank');
        expect(pwEl.dataset.hasExisting).toBe('1');
    });
});
