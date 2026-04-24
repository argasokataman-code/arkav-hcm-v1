import { beforeEach, describe, expect, it, vi } from 'vitest';

const flush = async () => {
  await Promise.resolve();
  await new Promise((resolve) => setTimeout(resolve, 0));
};

describe('notification inbox header wiring', () => {
  beforeEach(() => {
    vi.resetModules();

    document.body.innerHTML = `
      <a id="notification_popup"></a>
      <span data-notification-unread-badge class="d-none">0</span>
      <h4 data-notification-title>Notifications (0)</h4>
      <a href="#" data-notification-mark-all>Mark all as read</a>
      <a href="#" data-notification-refresh>Refresh</a>
      <div data-notification-content></div>
    `;

    window.AuthApi = {
      getTenantContext: vi.fn(() => ({ companyCode: 'inbox_company', companyId: 88, companyUuid: 'company-uuid-88' })),
      getToken: vi.fn(() => 'inbox-token'),
    };

    window.setInterval = vi.fn(() => 1);

    global.fetch = vi.fn((target, options = {}) => {
      if (target === '/v1/hcm/notifications/unread-count') {
        return Promise.resolve({
          ok: true,
          json: () => Promise.resolve({ success: true, data: { unreadCount: 2 } }),
        });
      }

      if (String(target).startsWith('/v1/hcm/notifications?page=1&perPage=5')) {
        return Promise.resolve({
          ok: true,
          json: () => Promise.resolve({
            success: true,
            data: {
              items: [
                {
                  uuid: 'notif-1',
                  eventKey: 'asset.assigned',
                  title: 'Asset assigned',
                  body: 'Laptop assigned to you',
                  severity: 'important',
                  isRead: false,
                  createdAt: new Date().toISOString(),
                },
              ],
              meta: { unreadCount: 2 },
            },
          }),
        });
      }

      if (target === '/v1/hcm/notifications/read-all' && options.method === 'POST') {
        return Promise.resolve({
          ok: true,
          json: () => Promise.resolve({ success: true, data: { updated: 2 } }),
        });
      }

      return Promise.reject(new Error(`Unexpected fetch: ${target}`));
    });
  });

  it('loads unread count and latest notification into header dropdown', async () => {
    await import('../../../frontend/resources/js/notification-inbox-data.js');
    await flush();

    expect(document.querySelector('[data-notification-title]')?.textContent).toContain('(2)');
    expect(document.querySelector('[data-notification-unread-badge]')?.textContent).toBe('2');
    expect(document.querySelector('[data-notification-unread-badge]')?.classList.contains('d-none')).toBe(false);
    expect(document.querySelector('[data-notification-content]')?.textContent).toContain('Asset assigned');
  });

  it('calls mark-all endpoint with auth and tenant headers', async () => {
    await import('../../../frontend/resources/js/notification-inbox-data.js');
    await flush();

    document.querySelector('[data-notification-mark-all]').dispatchEvent(new Event('click', { bubbles: true, cancelable: true }));
    await flush();

    const markAllCall = global.fetch.mock.calls.find(([target, options]) => target === '/v1/hcm/notifications/read-all' && options?.method === 'POST');
    expect(markAllCall).toBeTruthy();
    expect(markAllCall[1].headers.Authorization).toBe('Bearer inbox-token');
    expect(markAllCall[1].headers['X-Company-Code']).toBe('inbox_company');
    expect(markAllCall[1].headers['X-Company-Id']).toBe('88');
  });

  it('falls back to /api-token response data.token schema', async () => {
    window.AuthApi = {
      getTenantContext: vi.fn(() => ({ companyCode: 'inbox_company', companyId: 88, companyUuid: 'company-uuid-88' })),
      getToken: vi.fn(() => null),
    };

    global.fetch = vi.fn((target, options = {}) => {
      if (target === '/api-token') {
        return Promise.resolve({
          ok: true,
          json: () => Promise.resolve({ success: true, data: { token: 'fallback-token' } }),
        });
      }

      if (target === '/v1/hcm/notifications/unread-count') {
        return Promise.resolve({
          ok: true,
          json: () => Promise.resolve({ success: true, data: { unreadCount: 1 } }),
        });
      }

      if (String(target).startsWith('/v1/hcm/notifications?page=1&perPage=5')) {
        return Promise.resolve({
          ok: true,
          json: () => Promise.resolve({
            success: true,
            data: {
              items: [],
              meta: { unreadCount: 1 },
            },
          }),
        });
      }

      if (target === '/v1/hcm/notifications/read-all' && options.method === 'POST') {
        return Promise.resolve({
          ok: true,
          json: () => Promise.resolve({ success: true, data: { updated: 0 } }),
        });
      }

      return Promise.reject(new Error(`Unexpected fetch: ${target}`));
    });

    await import('../../../frontend/resources/js/notification-inbox-data.js');
    await flush();

    document.querySelector('[data-notification-mark-all]').dispatchEvent(new Event('click', { bubbles: true, cancelable: true }));
    await flush();

    const markAllCall = global.fetch.mock.calls.find(([target, options]) => target === '/v1/hcm/notifications/read-all' && options?.method === 'POST');
    expect(markAllCall).toBeTruthy();
    expect(markAllCall[1].headers.Authorization).toBe('Bearer fallback-token');
  });
});
