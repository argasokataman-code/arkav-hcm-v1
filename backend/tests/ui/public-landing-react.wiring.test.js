import { describe, expect, it } from 'vitest';

import { buildLandingOnboardingPayload, isTrialPackage, readLandingBootstrapData } from '../../../frontend/resources/js/public-landing-contract.js';

describe('public landing react wiring', () => {
  it('reads bootstrap data from the server rendered JSON node', () => {
    document.body.innerHTML = '<script id="landing-app-data" type="application/json">{"companyName":"Arcav","packages":[{"uuid":"pkg-1"}]}</script>';

    expect(readLandingBootstrapData()).toEqual({
      companyName: 'Arcav',
      packages: [{ uuid: 'pkg-1' }],
    });
  });

  it('treats trial packages as trial-only defaults', () => {
    expect(isTrialPackage({ code: 'trial' })).toBe(true);
    expect(isTrialPackage({ code: 'enterprise' })).toBe(false);
  });

  it('builds the public onboarding payload with the existing API contract shape', () => {
    expect(buildLandingOnboardingPayload({
      packageUuid: 'pkg-1',
      billingCycle: 'monthly',
      startMode: 'trial',
      companyName: 'PT Arcav',
      companyLegalName: 'PT Arcav Teknologi',
      companyTimezone: 'Asia/Jakarta',
      companyCurrency: 'IDR',
      companyCountryCode: 'ID',
      companyContactPhone: '',
      companyContactPersonName: 'Ayu',
      companyContactPersonRole: 'HR Lead',
      companyAddress: 'Jl. Sudirman',
      companyCity: 'Jakarta',
      companyPostalCode: '',
      ownerName: 'Ayu',
      ownerEmail: 'ayu@example.com',
      ownerPhone: '',
      ownerPassword: 'StrongPass1',
      ownerConfirmPassword: 'StrongPass1',
      billingEmail: 'billing@example.com',
      website: '',
      turnstileToken: '',
    })).toEqual({
      package_uuid: 'pkg-1',
      billing_cycle: 'monthly',
      start_mode: 'trial',
      company: {
        name: 'PT Arcav',
        legal_name: 'PT Arcav Teknologi',
        timezone: 'Asia/Jakarta',
        currency: 'IDR',
        country_code: 'ID',
        contact_person_name: 'Ayu',
        contact_person_role: 'HR Lead',
        address: 'Jl. Sudirman',
        city: 'Jakarta',
      },
      owner: {
        name: 'Ayu',
        email: 'ayu@example.com',
        password: 'StrongPass1',
        confirmPassword: 'StrongPass1',
      },
      billingEmail: 'billing@example.com',
    });
  });
});