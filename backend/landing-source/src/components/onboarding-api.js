export function formatIdr(value) {
  const roundedValue = Math.round(Number(value || 0));
  return `Rp ${String(roundedValue).replace(/\B(?=(\d{3})+(?!\d))/g, '.')}`;
}

export function isTrialPackage(pkg) {
  if (!pkg || typeof pkg !== 'object') return false;
  const packageCode = String(pkg.code || '').toLowerCase();
  return packageCode === 'trial' || packageCode.includes('trial');
}

export function sanitizePostalCode(value) {
  return String(value ?? '').replace(/\D+/g, '').slice(0, 12);
}

export function sanitizePhone(value) {
  return String(value ?? '').replace(/[^0-9+\-\s().]/g, '').slice(0, 20);
}

function compactObject(input) {
  return Object.entries(input).reduce((carry, [key, value]) => {
    if (value === null || value === undefined || value === '') return carry;
    carry[key] = value;
    return carry;
  }, {});
}

function normalizeStartMode(value) {
  const normalized = String(value ?? '').trim().toLowerCase().replace(/[\s-]+/g, '_');
  if (!normalized || normalized === 'trial') return 'trial';
  if (['pending_payment', 'pendingpayment', 'paid', 'subscribe', 'subscription'].includes(normalized)) return 'pending_payment';
  return 'trial';
}

export function buildPayload(formState, turnstileToken) {
  const payload = {
    package_uuid: String(formState.packageUuid || '').trim(),
    billing_cycle: String(formState.billingCycle || 'monthly'),
    start_mode: normalizeStartMode(formState.startMode || 'trial'),
    consent_accepted: Boolean(formState.consentAccepted),
    company: compactObject({
      name: String(formState.companyName || '').trim(),
      legal_name: String(formState.companyLegalName || '').trim(),
      timezone: String(formState.companyTimezone || 'Asia/Jakarta').trim(),
      currency: String(formState.companyCurrency || 'IDR').trim(),
      country_code: String(formState.companyCountryCode || 'ID').trim(),
      contact_phone: String(formState.companyContactPhone || '').trim(),
      contact_person_name: String(formState.companyContactPersonName || '').trim(),
      contact_person_role: String(formState.companyContactPersonRole || '').trim(),
      address: String(formState.companyAddress || '').trim(),
      city: String(formState.companyCity || '').trim(),
      postal_code: sanitizePostalCode(formState.companyPostalCode || ''),
    }),
    owner: compactObject({
      name: String(formState.ownerName || '').trim(),
      email: String(formState.ownerEmail || '').trim(),
      phone: String(formState.ownerPhone || '').trim(),
      password: String(formState.ownerPassword || ''),
      confirmPassword: String(formState.ownerConfirmPassword || ''),
    }),
  };

  if (formState.billingEmail) {
    payload.billingEmail = String(formState.billingEmail).trim();
  }

  if (formState.website) {
    payload.website = String(formState.website).trim();
  }

  if (turnstileToken) {
    payload.turnstile_token = String(turnstileToken).trim();
  }

  return payload;
}
