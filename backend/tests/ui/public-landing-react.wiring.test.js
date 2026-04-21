import React, { act, useState } from 'react';
import { createRoot } from 'react-dom/client';
import { describe, expect, it, vi } from 'vitest';

import { buildLandingOnboardingPayload, isTrialPackage, readLandingBootstrapData } from '../../../frontend/resources/js/public-landing-contract.js';
import { OnboardingModal } from '../../public/build/js/components/public-landing-reference-app.jsx';

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

  it('normalizes onboarding values to the backend runtime contract', () => {
    expect(buildLandingOnboardingPayload({
      packageUuid: 'pkg-1',
      billingCycle: 'monthly',
      startMode: 'Pending Payment',
      companyName: 'PT Arcav',
      companyTimezone: 'Asia/Jakarta',
      companyCurrency: 'IDR',
      companyCountryCode: 'ID',
      companyAddress: 'Jl. Sudirman',
      companyCity: 'Jakarta',
      companyPostalCode: '12-190A99999999',
      ownerName: 'Ayu',
      ownerEmail: 'ayu@example.com',
      ownerPassword: 'StrongPass1',
      ownerConfirmPassword: 'StrongPass1',
    })).toMatchObject({
      start_mode: 'pending_payment',
      company: {
        postal_code: '121909999999',
      },
    });
  });

  it('does not remount turnstile on every onboarding keystroke', async () => {
    globalThis.IS_REACT_ACT_ENVIRONMENT = true;
    const renderSpy = vi.fn(() => 'widget-1');
    const removeSpy = vi.fn();
    window.turnstile = {
      render: renderSpy,
      remove: removeSpy,
    };

    const rootNode = document.createElement('div');
    document.body.appendChild(rootNode);
    const root = createRoot(rootNode);

    function Harness() {
      const [formState, setFormState] = useState({
        packageUuid: 'pkg-1',
        billingCycle: 'monthly',
        startMode: 'pending_payment',
        companyName: '',
        companyLegalName: '',
        companyContactPersonName: '',
        companyContactPersonRole: '',
        companyContactPhone: '',
        companyAddress: 'Jl. Sudirman',
        companyCity: 'Jakarta',
        companyPostalCode: '',
        companyCountryCode: 'ID',
        companyTimezone: 'Asia/Jakarta',
        companyCurrency: 'IDR',
        ownerName: 'Ayu',
        ownerEmail: 'ayu@example.com',
        ownerPhone: '',
        ownerPassword: 'StrongPass1',
        ownerConfirmPassword: 'StrongPass1',
        billingEmail: '',
        website: '',
      });

      return React.createElement(OnboardingModal, {
        error: null,
        formState,
        onChange: (event) => {
          const { name, value } = event.target;
          setFormState((current) => ({
            ...current,
            [name]: value,
          }));
        },
        onClose: () => {},
        onSubmit: (event) => event.preventDefault(),
        packages: [{ uuid: 'pkg-1', code: 'basic', name: 'Basic' }],
        submitting: false,
        turnstileEnabled: true,
        turnstileSiteKey: 'site-key',
        onTurnstileTokenChange: () => {},
      });
    }

    await act(async () => {
      root.render(React.createElement(Harness));
    });

    expect(renderSpy).toHaveBeenCalledTimes(1);

    const companyNameInput = rootNode.querySelector('input[name="companyName"]');
    expect(companyNameInput).not.toBeNull();

    await act(async () => {
      companyNameInput.value = 'PT Arcav Baru';
      companyNameInput.dispatchEvent(new Event('input', { bubbles: true }));
    });

    expect(renderSpy).toHaveBeenCalledTimes(1);
    expect(removeSpy).not.toHaveBeenCalled();

    await act(async () => {
      root.unmount();
    });

    expect(removeSpy).toHaveBeenCalledTimes(1);
    delete window.turnstile;
  });
});