import { beforeEach, describe, expect, it, vi } from 'vitest';

async function loadSubscriptionsManager(fetchImpl) {
  vi.resetModules();
  vi.stubGlobal('fetch', fetchImpl);
  delete window.SubscriptionsManager;

  Object.defineProperty(document, 'readyState', {
    configurable: true,
    value: 'loading',
  });

  await import('../../../frontend/resources/js/subscriptions-management.js');
  document.dispatchEvent(new Event('DOMContentLoaded'));
  await Promise.resolve();
  await Promise.resolve();

  return window.SubscriptionsManager;
}

describe('Subscriptions management wiring', () => {
  beforeEach(() => {
    document.body.innerHTML = '';
    localStorage.clear();
    document.body.innerHTML = `
      <div class="main-wrapper" data-primary-super-admin="1"></div>
      <div data-subscriptions-list-container></div>
      <div data-subscription-readonly-notice class="d-none"></div>
      <div data-subscription-change-queue-card class="d-none"></div>
      <select data-subscription-change-queue-filter>
        <option value="all" selected>All</option>
        <option value="pending">Pending</option>
      </select>
      <div data-subscription-change-queue-content></div>
      <div data-subscription-change-queue-count></div>
      <button id="btn_add_subscription" data-subscription-add-button></button>
      <button id="btn_open_renew_by_id"></button>
      <form id="subscriptionForm"><button type="submit">Save</button></form>
      <div data-subscription-company-select-group><select id="input_subscription_company"></select></div>
      <div data-subscription-company-readonly-group class="d-none"><input id="input_subscription_company_readonly" /></div>
      <select id="input_subscription_package"></select>
      <input id="input_subscription_start" />
      <select id="input_subscription_cycle"><option value="monthly">Monthly</option></select>
      <input id="input_subscription_end" />
      <select id="input_subscription_status">
        <option value="active">Active</option>
        <option value="trial">Trial</option>
        <option value="inactive">Inactive</option>
      </select>
      <input id="input_subscription_trial_end" />
      <div id="subscription_trial_row" class="d-none"></div>
      <div data-subscription-edit-impact-note class="d-none"></div>
      <div id="subscriptionModal"></div>
      <div id="subscriptionRenewModal"></div>
      <div id="subscriptionRenewByIdModal"></div>
      <div id="subscriptionReactivateConfirmModal"></div>
      <input id="search_subscriptions" />
      <select id="filter_status"></select>
      <select id="filter_cycle"></select>
      <button id="btn_reset_filters"></button>
    `;

    window.bootstrap = {
      Modal: {
        getOrCreateInstance() {
          return {
            show() {},
            hide() {},
          };
        },
      },
    };
    window.ArcavUi = {
      toast: vi.fn(),
      confirmDelete: vi.fn().mockResolvedValue(true),
      confirm: vi.fn().mockResolvedValue(true),
      showInfo: vi.fn(),
    };
  });

  it('renders visible ID, amount, and normalized pending payment label in the table', async () => {
    const fetchMock = vi.fn(async (url) => {
      const target = String(url);
      if (target === '/api-token') {
        return {
          ok: true,
          status: 200,
          json: async () => ({ success: true, data: { token: 'test-token' } }),
        };
      }
      if (target === '/v1/identity/auth/me') {
        return {
          ok: true,
          status: 200,
          json: async () => ({ success: true, data: { hcmGlobalAdmin: true, permissions: { 'subscription.manage': true } } }),
        };
      }
      if (target.startsWith('/v1/company')) {
        return { ok: true, status: 200, json: async () => ({ success: true, data: { companies: [] } }) };
      }
      if (target.startsWith('/v1/saas/packages')) {
        return { ok: true, status: 200, json: async () => ({ success: true, data: [] }) };
      }
      if (target.startsWith('/v1/saas/subscription-change-requests')) {
        return { ok: true, status: 200, json: async () => ({ success: true, data: [] }) };
      }
      if (target.startsWith('/v1/saas/subscriptions?')) {
        return {
          ok: true,
          status: 200,
          json: async () => ({
            success: true,
            data: [
              {
                id: 42,
                uuid: 'sub-uuid-42',
                companyName: 'Acme Corp',
                company: { code: 'ACME', name: 'Acme Corp' },
                packageName: 'Pro Plan',
                planCode: 'pro',
                status: 'pending_payment',
                startDate: '2026-05-01',
                endDate: '2026-06-01',
                autoRenew: true,
                billingCycle: 'monthly',
                amount: 199000,
              },
            ],
            pagination: { total: 1, per_page: 10, current_page: 1, last_page: 1 },
          }),
        };
      }

      return { ok: false, status: 404, json: async () => ({ success: false }) };
    });

    await loadSubscriptionsManager(fetchMock);

    await vi.waitFor(() => {
      const tableHtml = document.querySelector('[data-subscriptions-list-container]').innerHTML;
      expect(tableHtml).toContain('#42');
      expect(tableHtml).toContain('sub-uuid-42');
      expect(tableHtml).toContain('Rp');
      expect(tableHtml).toContain('Pending Payment');
      expect(tableHtml).not.toContain('data-edit-subscription="sub-uuid-42"');
      expect(tableHtml).not.toContain('data-delete-subscription="sub-uuid-42"');
    });
  });

  it('does not expose pending_payment as a selectable manual status', async () => {
    const fetchMock = vi.fn(async (url) => {
      const target = String(url);
      if (target === '/api-token') {
        return {
          ok: true,
          status: 200,
          json: async () => ({ success: true, data: { token: 'test-token' } }),
        };
      }
      if (target === '/v1/identity/auth/me') {
        return {
          ok: true,
          status: 200,
          json: async () => ({ success: true, data: { hcmGlobalAdmin: true, permissions: { 'subscription.manage': true } } }),
        };
      }
      if (target.startsWith('/v1/company')) {
        return { ok: true, status: 200, json: async () => ({ success: true, data: { companies: [] } }) };
      }
      if (target.startsWith('/v1/saas/packages')) {
        return { ok: true, status: 200, json: async () => ({ success: true, data: [] }) };
      }
      if (target.startsWith('/v1/saas/subscription-change-requests')) {
        return { ok: true, status: 200, json: async () => ({ success: true, data: [] }) };
      }
      if (target.startsWith('/v1/saas/subscriptions?')) {
        return { ok: true, status: 200, json: async () => ({ success: true, data: [], pagination: { total: 0, per_page: 10, current_page: 1, last_page: 1 } }) };
      }

      return { ok: false, status: 404, json: async () => ({ success: false }) };
    });

    await loadSubscriptionsManager(fetchMock);
    const statusValues = Array.from(document.querySelectorAll('#input_subscription_status option')).map((option) => option.value);

    expect(statusValues).not.toContain('pending_payment');
  });

  it('loads change request history without pending filter by default and renders notes', async () => {
    const fetchMock = vi.fn(async (url) => {
      const target = String(url);
      if (target === '/api-token') {
        return {
          ok: true,
          status: 200,
          json: async () => ({ success: true, data: { token: 'test-token' } }),
        };
      }
      if (target === '/v1/identity/auth/me') {
        return {
          ok: true,
          status: 200,
          json: async () => ({ success: true, data: { hcmGlobalAdmin: true, permissions: { 'subscription.manage': true } } }),
        };
      }
      if (target.startsWith('/v1/company')) {
        return { ok: true, status: 200, json: async () => ({ success: true, data: { companies: [] } }) };
      }
      if (target.startsWith('/v1/saas/packages')) {
        return { ok: true, status: 200, json: async () => ({ success: true, data: [] }) };
      }
      if (target === '/v1/saas/subscription-change-requests') {
        return {
          ok: true,
          status: 200,
          json: async () => ({
            success: true,
            data: [
              {
                id: 'req-1',
                company_code: 'ACME',
                action: 'cancel',
                status: 'rejected',
                notes: 'Cancel ditolak karena invoice masih overdue.',
                created_at: '2026-05-15T10:00:00Z',
                preview: { to_package: null, anomaly_flags: [], anomaly_details: {} },
              },
            ],
          }),
        };
      }
      if (target.startsWith('/v1/saas/subscriptions?')) {
        return { ok: true, status: 200, json: async () => ({ success: true, data: [], pagination: { total: 0, per_page: 10, current_page: 1, last_page: 1 } }) };
      }

      return { ok: false, status: 404, json: async () => ({ success: false }) };
    });

    await loadSubscriptionsManager(fetchMock);

    await vi.waitFor(() => {
      expect(
        fetchMock.mock.calls.some(function (call) {
          return call[0] === '/v1/saas/subscription-change-requests';
        })
      ).toBe(true);
    });

    await vi.waitFor(() => {
      const queueHtml = document.querySelector('[data-subscription-change-queue-content]').innerHTML;
      expect(queueHtml).toContain('Cancel ditolak karena invoice masih overdue.');
      expect(queueHtml).toContain('Rejected');
      expect(queueHtml).toContain('Tidak ada aksi lanjutan');
      expect(document.querySelector('[data-subscription-change-queue-count]').textContent).toContain('1 records');
    });
  });

  it('shows company as read-only text in edit mode', async () => {
    const fetchMock = vi.fn(async (url) => {
      const target = String(url);
      if (target === '/api-token') {
        return {
          ok: true,
          status: 200,
          json: async () => ({ success: true, data: { token: 'test-token' } }),
        };
      }
      if (target === '/v1/identity/auth/me') {
        return {
          ok: true,
          status: 200,
          json: async () => ({ success: true, data: { hcmGlobalAdmin: true, permissions: { 'subscription.manage': true } } }),
        };
      }
      if (target.startsWith('/v1/company')) {
        return {
          ok: true,
          status: 200,
          json: async () => ({ success: true, data: { companies: [{ id: 7, uuid: 'company-uuid-7', name: 'Renewal Anomaly Co' }] } }),
        };
      }
      if (target.startsWith('/v1/saas/packages')) {
        return { ok: true, status: 200, json: async () => ({ success: true, data: [{ id: 'pkg-1', name: 'UMKM' }] }) };
      }
      if (target.startsWith('/v1/saas/subscription-change-requests')) {
        return { ok: true, status: 200, json: async () => ({ success: true, data: [] }) };
      }
      if (target.startsWith('/v1/saas/subscriptions?')) {
        return { ok: true, status: 200, json: async () => ({ success: true, data: [], pagination: { total: 0, per_page: 10, current_page: 1, last_page: 1 } }) };
      }

      return { ok: false, status: 404, json: async () => ({ success: false }) };
    });

    const manager = await loadSubscriptionsManager(fetchMock);
    manager.setSubscriptionModalMode('edit', 'Renewal Anomaly Co');

    expect(document.querySelector('[data-subscription-company-select-group]').classList.contains('d-none')).toBe(true);
    expect(document.querySelector('[data-subscription-company-readonly-group]').classList.contains('d-none')).toBe(false);
    expect(document.getElementById('input_subscription_company_readonly').value).toBe('Renewal Anomaly Co');
    expect(document.getElementById('input_subscription_company').disabled).toBe(true);
    expect(document.querySelector('[data-subscription-edit-impact-note]').classList.contains('d-none')).toBe(false);
  });
});
