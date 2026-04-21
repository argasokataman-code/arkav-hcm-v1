import { beforeEach, describe, expect, it, vi } from 'vitest';

const flush = async () => {
  await Promise.resolve();
  await new Promise((resolve) => setTimeout(resolve, 0));
};

describe('profile settings wiring', () => {
  beforeEach(() => {
    vi.resetModules();
    document.body.innerHTML = `
      <div data-company-context-mode></div>
      <div data-company-name></div>
      <div data-company-id></div>
      <div data-company-code></div>
      <button type="button" class="d-none" data-copy-company-code></button>
      <div class="d-none" data-subscription-summary-card>
        <span data-subscription-status></span>
        <span data-subscription-package></span>
        <span data-subscription-billing-cycle></span>
        <span data-subscription-period></span>
        <span data-subscription-next-payment-date></span>
        <span data-subscription-next-payment-amount></span>
        <span data-subscription-employee-slots></span>
        <span data-subscription-employee-usage></span>
      </div>
      <div class="alert d-none" data-profile-settings-feedback></div>
      <form data-profile-settings-form>
        <input data-general-setting="first_name" />
        <input data-general-setting="last_name" />
        <input data-general-setting="email" />
        <input data-general-setting="phone" />
        <input data-general-setting="address" />
        <input data-general-setting="city" />
        <input data-general-setting="state" />
        <input data-general-setting="country" />
        <input data-general-setting="postal_code" />
        <input data-profile-settings-current-password />
        <input data-profile-settings-new-password />
        <input data-profile-settings-confirm-password />
        <button type="button" data-profile-settings-reset></button>
        <button type="submit" data-profile-settings-submit>Save</button>
      </form>
    `;

    window.AuthApi = {
      getTenantContext: vi.fn(() => ({ companyCode: 'owner_profile_company', companyId: 19, companyUuid: 'company-uuid-19' })),
      getToken: vi.fn(() => 'tenant-token'),
    };

    global.fetch = vi.fn((url, options = {}) => {
      if (url === '/v1/hcm/settings?group=general') {
        return Promise.resolve({
          ok: false,
          status: 403,
          json: () => Promise.resolve({ success: false, error: { message: 'Forbidden' } }),
        });
      }

      if (url === '/v1/identity/auth/me') {
        return Promise.resolve({
          ok: true,
          json: () => Promise.resolve({
            success: true,
            data: {
              name: 'Owner Profile',
              email: 'owner.profile@example.com',
              activeCompany: {
                id: 19,
                uuid: 'company-uuid-19',
                code: 'owner_profile_company',
                name: 'Owner Profile Company',
                role: 'owner',
              },
              subscription: {
                status: 'active',
                packageName: 'Professional',
                billingCycle: 'yearly',
                startsAt: '2026-04-01T00:00:00Z',
                endsAt: '2027-04-01T00:00:00Z',
                employeeSlots: {
                  limit: 50,
                  used: 12,
                  remaining: 38,
                  isUnlimited: false,
                  isConfigured: true,
                },
                nextPayment: {
                  date: '2027-04-01',
                  amount: 2400000,
                  invoiceNumber: 'INV-202604-0001',
                },
              },
              profile: {
                phone: '081234567890',
                address: 'Jl. Owner 1',
                addressDetail: 'Bandung',
              },
            },
          }),
        });
      }

      if (url === '/v1/identity/auth/profile') {
        return Promise.resolve({
          ok: true,
          json: () => Promise.resolve({
            success: true,
            data: {
              id: 10,
              name: 'Owner Profile Updated',
              email: 'owner.profile.updated@example.com',
              currentUserRole: 'owner',
              profile: {
                firstName: 'Owner',
                lastName: 'Profile Updated',
                phone: '081111111111',
                address: 'Jl. Owner 2',
                addressDetail: 'Jakarta',
                source: 'company_owner_profile',
              },
            },
          }),
        });
      }

      return Promise.reject(new Error(`Unexpected fetch: ${url}`));
    });
  });

  it('renders subscription summary from auth me and keeps owner profile page usable without admin settings access', async () => {
    await import('../../../frontend/resources/js/profile-settings-data.js');
    await flush();

    expect(document.querySelector('[data-company-name]')?.textContent).toContain('Owner Profile Company');
    expect(document.querySelector('[data-subscription-summary-card]')?.classList.contains('d-none')).toBe(false);
    expect(document.querySelector('[data-subscription-package]')?.textContent).toContain('Professional');
    expect(document.querySelector('[data-subscription-next-payment-amount]')?.textContent).toContain('INV-202604-0001');
    expect(document.querySelector('[data-subscription-employee-slots]')?.textContent).toContain('Max 50 employees');
    expect(document.querySelector('[data-subscription-employee-usage]')?.textContent).toContain('38 slot tersisa');
    expect(document.querySelector('[data-profile-settings-feedback]')?.classList.contains('d-none')).toBe(true);
  });

  it('sends tenant context headers when updating the identity profile', async () => {
    await import('../../../frontend/resources/js/profile-settings-data.js');
    await flush();

    document.querySelector('[data-general-setting="first_name"]').value = 'Owner';
    document.querySelector('[data-general-setting="last_name"]').value = 'Profile Updated';
    document.querySelector('[data-general-setting="email"]').value = 'owner.profile.updated@example.com';
    document.querySelector('[data-general-setting="phone"]').value = '081111111111';
    document.querySelector('[data-general-setting="address"]').value = 'Jl. Owner 2';
    document.querySelector('[data-general-setting="city"]').value = 'Jakarta';

    document.querySelector('[data-profile-settings-form]').dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
    await flush();

    const updateCall = global.fetch.mock.calls.find(([target]) => target === '/v1/identity/auth/profile');
    expect(updateCall).toBeTruthy();
    expect(updateCall[1].headers.Authorization).toBe('Bearer tenant-token');
    expect(updateCall[1].headers['X-Company-Code']).toBe('owner_profile_company');
    expect(updateCall[1].headers['X-Company-Id']).toBe('19');
  });
});