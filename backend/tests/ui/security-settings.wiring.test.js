import { beforeEach, describe, expect, it, vi } from 'vitest';

async function loadSecuritySettingsModule() {
    vi.resetModules();
    await import('../../../frontend/resources/js/security-settings-data.js');
}

function buildSecurityDom() {
    document.body.innerHTML = `
        <div id="changePasswordForm" class="collapse">
            <input data-security-current-password type="password" />
            <input data-security-new-password type="password" />
            <input data-security-confirm-password type="password" />
            <button type="button" data-security-save-password>Save Password</button>
            <div class="alert d-none" data-security-password-feedback></div>
        </div>
    `;
}

describe('Security Settings – change password wiring', () => {
    let fetchMock;

    beforeEach(() => {
        buildSecurityDom();

        fetchMock = vi.fn();
        global.fetch = fetchMock;

        window.AuthApi = {
            getToken: vi.fn().mockReturnValue('test-token-abc'),
            getTenantContext: vi.fn().mockReturnValue({ companyId: '42', companyCode: 'CO42' }),
        };

        window.bootstrap = {
            Collapse: {
                getInstance: vi.fn().mockReturnValue(null),
            },
        };
    });

    it('calls the change-password endpoint with correct payload on save', async () => {
        fetchMock.mockResolvedValueOnce({
            status: 200,
            json: vi.fn().mockResolvedValueOnce({ success: true, data: { message: 'Password changed successfully.' } }),
        });

        await loadSecuritySettingsModule();

        document.querySelector('[data-security-current-password]').value = 'OldPass123!';
        document.querySelector('[data-security-new-password]').value = 'NewPass456@';
        document.querySelector('[data-security-confirm-password]').value = 'NewPass456@';

        document.querySelector('[data-security-save-password]').click();
        await Promise.resolve();
        await Promise.resolve();
        await Promise.resolve();

        expect(fetchMock).toHaveBeenCalledOnce();
        const [url, opts] = fetchMock.mock.calls[0];
        expect(url).toBe('/v1/identity/auth/change-password');
        expect(opts.method).toBe('POST');
        const body = JSON.parse(opts.body);
        expect(body.currentPassword).toBe('OldPass123!');
        expect(body.newPassword).toBe('NewPass456@');
        expect(body.confirmPassword).toBe('NewPass456@');
        expect(opts.headers['Authorization']).toBe('Bearer test-token-abc');
    });

    it('shows success feedback and clears fields on 200 response', async () => {
        fetchMock.mockResolvedValueOnce({
            status: 200,
            json: vi.fn().mockResolvedValueOnce({ success: true, data: { message: 'Password changed successfully.' } }),
        });

        await loadSecuritySettingsModule();

        document.querySelector('[data-security-current-password]').value = 'OldPass123!';
        document.querySelector('[data-security-new-password]').value = 'NewPass456@';
        document.querySelector('[data-security-confirm-password]').value = 'NewPass456@';

        document.querySelector('[data-security-save-password]').click();

        // Wait for all promise microtasks
        for (let i = 0; i < 8; i++) {
            await Promise.resolve();
        }

        const feedback = document.querySelector('[data-security-password-feedback]');
        expect(feedback.classList.contains('d-none')).toBe(false);
        expect(feedback.classList.contains('alert-success')).toBe(true);
        expect(feedback.textContent).toBe('Password changed successfully.');

        // Fields should be cleared
        expect(document.querySelector('[data-security-current-password]').value).toBe('');
        expect(document.querySelector('[data-security-new-password]').value).toBe('');
        expect(document.querySelector('[data-security-confirm-password]').value).toBe('');
    });

    it('shows error feedback on invalid current password (422)', async () => {
        fetchMock.mockResolvedValueOnce({
            status: 422,
            json: vi.fn().mockResolvedValueOnce({
                success: false,
                error: { code: 'AUTH_INVALID_CREDENTIALS', message: 'Current password is incorrect.' },
            }),
        });

        await loadSecuritySettingsModule();

        document.querySelector('[data-security-current-password]').value = 'WrongPass999!';
        document.querySelector('[data-security-new-password]').value = 'NewPass456@';
        document.querySelector('[data-security-confirm-password]').value = 'NewPass456@';

        document.querySelector('[data-security-save-password]').click();

        for (let i = 0; i < 8; i++) {
            await Promise.resolve();
        }

        const feedback = document.querySelector('[data-security-password-feedback]');
        expect(feedback.classList.contains('d-none')).toBe(false);
        expect(feedback.classList.contains('alert-danger')).toBe(true);
        expect(feedback.textContent).toBe('Current password is incorrect.');
    });

    it('does not call fetch when fields are empty', async () => {
        await loadSecuritySettingsModule();

        // All fields empty
        document.querySelector('[data-security-save-password]').click();
        await Promise.resolve();

        expect(fetchMock).not.toHaveBeenCalled();

        const feedback = document.querySelector('[data-security-password-feedback]');
        expect(feedback.classList.contains('d-none')).toBe(false);
        expect(feedback.classList.contains('alert-warning')).toBe(true);
    });

    it('does not call fetch when new password and confirm do not match', async () => {
        await loadSecuritySettingsModule();

        document.querySelector('[data-security-current-password]').value = 'OldPass123!';
        document.querySelector('[data-security-new-password]').value = 'NewPass456@';
        document.querySelector('[data-security-confirm-password]').value = 'DifferentPass!';

        document.querySelector('[data-security-save-password]').click();
        await Promise.resolve();

        expect(fetchMock).not.toHaveBeenCalled();

        const feedback = document.querySelector('[data-security-password-feedback]');
        expect(feedback.classList.contains('alert-warning')).toBe(true);
        expect(feedback.textContent).toContain('do not match');
    });
});
