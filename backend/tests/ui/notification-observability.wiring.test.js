import { beforeEach, describe, expect, it, vi } from 'vitest';

const flush = async () => {
  await Promise.resolve();
  await new Promise((resolve) => setTimeout(resolve, 0));
};

describe('notification observability wiring', () => {
  beforeEach(() => {
    vi.resetModules();

    document.body.innerHTML = `
      <div data-notification-observability-page></div>
      <select data-notification-observability-hours>
        <option value="24" selected>24</option>
        <option value="72">72</option>
      </select>
      <select data-notification-observability-channel>
        <option value="">all</option>
        <option value="mail">mail</option>
      </select>
      <button type="button" data-notification-observability-refresh>Refresh</button>
      <div data-observability-total-all></div>
      <div data-observability-total-sent></div>
      <div data-observability-total-failed></div>
      <div data-observability-total-dropped></div>
      <div data-observability-status-breakdown></div>
      <div data-observability-top-failed></div>
      <div data-observability-last-updated></div>
    `;

    window.AuthApi = {
      getTenantContext: vi.fn(() => ({ companyCode: 'notify_company', companyId: 55, companyUuid: 'company-uuid-55' })),
      getToken: vi.fn(() => 'notif-token'),
    };

    global.fetch = vi.fn((target) => {
      if (String(target).startsWith('/v1/hcm/notifications/delivery-summary')) {
        return Promise.resolve({
          ok: true,
          json: () => Promise.resolve({
            success: true,
            data: {
              totals: { all: 9, sent: 6, failed: 2, dropped: 1 },
              breakdown: { byStatus: [{ status: 'sent', total: 6 }, { status: 'failed', total: 2 }] },
              topFailedEvents: [{ eventKey: 'billing.invoice.reminder_failed', total: 2 }],
            },
          }),
        });
      }

      return Promise.reject(new Error(`Unexpected fetch: ${target}`));
    });
  });

  it('loads and renders observability metrics on page init', async () => {
    await import('../../../frontend/resources/js/notification-observability-data.js');
    await flush();

    expect(document.querySelector('[data-observability-total-all]')?.textContent).toBe('9');
    expect(document.querySelector('[data-observability-total-failed]')?.textContent).toBe('2');
    expect(document.querySelector('[data-observability-status-breakdown]')?.textContent).toContain('SENT');
    expect(document.querySelector('[data-observability-top-failed]')?.textContent).toContain('billing.invoice.reminder_failed');
  });

  it('restores and persists selected filters', async () => {
    localStorage.setItem('hcm.notifications.observability.hours', '72');
    localStorage.setItem('hcm.notifications.observability.channel', 'mail');

    await import('../../../frontend/resources/js/notification-observability-data.js');
    await flush();

    const hours = document.querySelector('[data-notification-observability-hours]');
    const channel = document.querySelector('[data-notification-observability-channel]');

    expect(hours?.value).toBe('72');
    expect(channel?.value).toBe('mail');

    hours.value = '24';
    channel.value = '';
    hours.dispatchEvent(new Event('change'));
    channel.dispatchEvent(new Event('change'));
    await flush();

    expect(localStorage.getItem('hcm.notifications.observability.hours')).toBe('24');
    expect(localStorage.getItem('hcm.notifications.observability.channel')).toBe('');
  });
});
