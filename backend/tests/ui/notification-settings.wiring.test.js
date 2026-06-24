import { beforeEach, describe, expect, it, vi } from 'vitest';

const flush = async () => {
  await Promise.resolve();
  await new Promise((resolve) => setTimeout(resolve, 0));
};

describe('notification settings wiring', () => {
  beforeEach(() => {
    vi.resetModules();

    document.body.innerHTML = `
      <div class="alert d-none" data-notification-settings-feedback></div>
      <form data-notification-settings-form>
        <table>
          <tbody data-notification-settings-rows></tbody>
        </table>
        <small data-notification-settings-status></small>
        <button type="button" data-notification-settings-reset>Reset</button>
        <button type="submit" data-notification-settings-submit>Save Preferences</button>
      </form>
      <div data-notification-observability-panel>
        <select data-notification-observability-hours><option value="24" selected>24</option></select>
        <select data-notification-observability-channel><option value="">all</option></select>
        <button type="button" data-notification-observability-refresh>Refresh</button>
        <div data-observability-total-all></div>
        <div data-observability-total-sent></div>
        <div data-observability-total-failed></div>
        <div data-observability-total-dropped></div>
        <div data-observability-status-breakdown></div>
        <div data-observability-top-failed></div>
        <div data-observability-last-updated></div>
      </div>
    `;

    window.AuthApi = {
      getTenantContext: vi.fn(() => ({ companyCode: 'notify_company', companyId: 55, companyUuid: 'company-uuid-55' })),
      getToken: vi.fn(() => 'notif-token'),
    };

    window.AuthUser = { hcmGlobalAdmin: false };

    window.ArcavValidation = { validateForm: vi.fn().mockReturnValue(true) };
    global.fetch = vi.fn((target) => {
      if (target === '/v1/hcm/notification-preferences') {
        return Promise.resolve({
          ok: true,
          json: () => Promise.resolve({
            success: true,
            data: {
              items: [
                { eventKey: 'asset.assigned', channel: 'database', enabled: false, digestMode: 'instant' },
                { eventKey: 'billing.payment_failed', channel: 'mail', enabled: true, digestMode: 'daily' },
              ],
            },
          }),
        });
      }

      return Promise.reject(new Error(`Unexpected fetch: ${target}`));
    });
  });

  it('loads preferences and applies values to rendered toggles', async () => {
    await import('../../../frontend/resources/js/notification-settings-data.js');
    await flush();

    const databaseToggle = document.querySelector('[data-preference-checkbox="asset.assigned|database"]');
    const emailToggle = document.querySelector('[data-preference-checkbox="billing.payment_failed|mail"]');

    expect(databaseToggle).toBeTruthy();
    expect(emailToggle).toBeTruthy();
    expect(databaseToggle.checked).toBe(false);
    expect(emailToggle.checked).toBe(true);
    expect(document.querySelector('[data-notification-settings-status]')?.textContent).toContain('loaded');
  });

  it('saves preferences with tenant and auth headers', async () => {
    global.fetch = vi.fn((target, options = {}) => {
      if (target === '/v1/hcm/notification-preferences' && (!options.method || options.method === 'GET')) {
        return Promise.resolve({
          ok: true,
          json: () => Promise.resolve({ success: true, data: { items: [] } }),
        });
      }

      if (target === '/v1/hcm/notification-preferences' && options.method === 'PUT') {
        return Promise.resolve({
          ok: true,
          json: () => Promise.resolve({ success: true, data: { items: [] } }),
        });
      }

      return Promise.reject(new Error(`Unexpected fetch: ${target}`));
    });

    await import('../../../frontend/resources/js/notification-settings-data.js');
    await flush();

    const form = document.querySelector('[data-notification-settings-form]');
    form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
    await flush();

    const saveCall = global.fetch.mock.calls.find(([target, options]) => target === '/v1/hcm/notification-preferences' && options?.method === 'PUT');
    expect(saveCall).toBeTruthy();
    expect(saveCall[1].headers.Authorization).toBe('Bearer notif-token');
    expect(saveCall[1].headers['X-Company-Code']).toBe('notify_company');
    expect(saveCall[1].headers['X-Company-Id']).toBe('55');

    const body = JSON.parse(saveCall[1].body);
    expect(Array.isArray(body.preferences)).toBe(true);
    expect(body.preferences.length).toBeGreaterThan(0);
  });

  it('loads delivery observability summary for global admin', async () => {
    window.AuthUser = { hcmGlobalAdmin: true };

    global.fetch = vi.fn((target) => {
      if (String(target).startsWith('/v1/hcm/notifications/delivery-summary')) {
        return Promise.resolve({
          ok: true,
          json: () => Promise.resolve({
            success: true,
            data: {
              totals: { all: 5, sent: 3, failed: 1, dropped: 1 },
              breakdown: { byStatus: [{ status: 'sent', total: 3 }] },
              topFailedEvents: [{ eventKey: 'billing.invoice.email_failed', total: 1 }],
            },
          }),
        });
      }

      if (target === '/v1/hcm/notification-preferences') {
        return Promise.resolve({
          ok: true,
          json: () => Promise.resolve({ success: true, data: { items: [] } }),
        });
      }

      return Promise.reject(new Error(`Unexpected fetch: ${target}`));
    });

    await import('../../../frontend/resources/js/notification-settings-data.js');
    await flush();

    expect(document.querySelector('[data-observability-total-all]')?.textContent).toBe('5');
    expect(document.querySelector('[data-observability-total-sent]')?.textContent).toBe('3');
    expect(document.querySelector('[data-observability-top-failed]')?.textContent).toContain('billing.invoice.email_failed');
  });
});
