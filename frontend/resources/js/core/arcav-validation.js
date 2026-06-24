/**
 * ArcavValidation
 * Small global helper to keep FE validation consistent with API spec constraints.
 *
 * Usage (example):
 *   ArcavValidation.applyConstraints(inputEl, {
 *     required: true,
 *     pattern: ArcavValidation.patterns.codeSlug, // or your own RegExp / pattern string
 *     maxLength: 64,
 *     message: 'Code hanya boleh huruf kecil, angka, underscore, dan dash.',
 *   });
 */
(function () {
  'use strict';

  function toRegExp(pattern) {
    if (!pattern) return null;
    if (pattern instanceof RegExp) return pattern;
    if (typeof pattern === 'string') {
      // Accept raw pattern without slashes
      return new RegExp(pattern);
    }
    return null;
  }

  function testPattern(re, value) {
    if (!re) return true;
    return re.test(String(value ?? ''));
  }

  function clearValidity(el) {
    if (!el) return;
    if (typeof el.setCustomValidity === 'function') el.setCustomValidity('');
  }

  function setInvalid(el, message) {
    if (!el) return;
    if (typeof el.setCustomValidity === 'function') el.setCustomValidity(message || 'Invalid value');
  }

  /**
   * Apply constraints to a single input element.
   * Supported: required, pattern, min, max, maxLength, minLength, message.
   */
  function applyConstraints(el, opts) {
    if (!el || !opts) return;

    if (opts.required != null) {
      if (opts.required) el.setAttribute('required', 'required');
      else el.removeAttribute('required');
    }
    if (opts.maxLength != null) el.setAttribute('maxlength', String(opts.maxLength));
    if (opts.minLength != null) el.setAttribute('minlength', String(opts.minLength));
    if (opts.min != null) el.setAttribute('min', String(opts.min));
    if (opts.max != null) el.setAttribute('max', String(opts.max));

    const re = toRegExp(opts.pattern);
    if (re) el.setAttribute('pattern', re.source);

    const msg = opts.message || 'Format tidak sesuai.';

    const validate = () => {
      clearValidity(el);
      const v = el.value;
      if (opts.required && String(v || '').trim() === '') {
        setInvalid(el, opts.requiredMessage || 'Field wajib diisi.');
        return false;
      }
      if (re && v && !testPattern(re, v)) {
        setInvalid(el, msg);
        return false;
      }
      return true;
    };

    el.addEventListener('input', validate);
    el.addEventListener('blur', validate);
    validate();
  }

  // Safe shared patterns (must match backend regex rules when referenced)
  const patterns = {
    // Matches backend: /^[a-z0-9_\-]+$/ — salary component, payroll item, shift, overtime code
    codeSlug: /^[a-z0-9_\-]+$/,
    // Matches backend: /^[a-z0-9_]+$/ — leave type code (no dash)
    codeSlugNoDash: /^[a-z0-9_]+$/,
    // Matches backend: /^[A-Za-z0-9_-]+$/ — company code
    companyCode: /^[A-Za-z0-9_-]+$/,
    // Matches backend: /^(?=.*[A-Z])(?=.*[a-z])(?=.*\d)[A-Za-z\d@$!%*?&._-]{8,64}$/
    password: /^(?=.*[A-Z])(?=.*[a-z])(?=.*\d)[A-Za-z\d@$!%*?&._-]{8,64}$/,
    // Matches backend: /^[A-Za-z][A-Za-z\s\'.-]{1,149}$/ — name
    nameLatin: /^[A-Za-z][A-Za-z\s\'.-]{1,149}$/,
    // Matches backend: /^[\p{L}\p{M} .,\'-]{2,150}$/u — employee name (Unicode)
    nameUnicode: /^[\p{L}\p{M} .,'-]{2,150}$/u,
    // Matches backend: /^\+?(?=(?:\D*\d){8,15}\D*$)[0-9\s\-()]+$/ — profile phone
    phoneProfile: /^\+?(?=(?:\D*\d){8,15}\D*$)[0-9\s\-()]+$/,
    // Matches backend: /^\+?[0-9]{10,15}$/ — employee phone
    phoneEmployee: /^\+?[0-9]{10,15}$/,
    // Matches backend: /^[0-9+\-\s().]{6,20}$/ — onboarding phone
    phoneOnboarding: /^[0-9+\-\s().]{6,20}$/,
    // Matches backend: /^[0-9]{16}$/ — NIK/KTP
    nik: /^[0-9]{16}$/,
    // Matches backend: /^[0-9]{13}$/ — BPJS Kesehatan
    bpjsKesehatan: /^[0-9]{13}$/,
    // Matches backend: /^[0-9]{11}$/ — BPJS Ketenagakerjaan
    bpjsKetenagakerjaan: /^[0-9]{11}$/,
    // Matches backend: /^[0-9]{8,30}$/ — bank account number
    bankAccount: /^[0-9]{8,30}$/,
    // Matches backend: none (FE only) — NPWP format
    npwp: /^[0-9.\-]{15,20}$/,
    // Matches backend: /^[A-Za-z0-9\s.,\'\/-]{3,180}$/ — address
    address: /^[A-Za-z0-9\s.,\'\/-]{3,180}$/,
    // Matches backend: /^[A-Za-z0-9][A-Za-z0-9\s-]{2,9}$/ — postal code
    postalCode: /^[A-Za-z0-9][A-Za-z0-9\s-]{2,9}$/,
    // Matches backend: /^[0-9]{3,12}$/ — onboarding postal code
    postalCodeNumeric: /^[0-9]{3,12}$/,
    // Matches backend: /^\d{4}-\d{2}$/ — period YYYY-MM
    period: /^\d{4}-\d{2}$/,
    // Matches backend: /^(?=.{1,253}$)(?!-)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/ — domain
    domain: /^(?=.{1,253}$)(?!-)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/,
  };

  // Feedback messages per pattern key
  const messages = {
    codeSlug: 'Hanya huruf kecil, angka, underscore, dan dash.',
    codeSlugNoDash: 'Hanya huruf kecil, angka, dan underscore.',
    companyCode: 'Hanya huruf, angka, underscore, dan dash.',
    password: 'Minimal 8 karakter, kombinasi huruf besar, kecil, dan angka.',
    nameLatin: 'Hanya huruf, spasi, dan tanda kutip.',
    nameUnicode: 'Nama tidak valid.',
    phoneProfile: 'Nomor telepon tidak valid.',
    phoneEmployee: 'Nomor telepon tidak valid (10-15 digit).',
    phoneOnboarding: 'Nomor telepon tidak valid.',
    nik: 'NIK harus 16 digit angka.',
    bpjsKesehatan: 'Nomor BPJS Kesehatan harus 13 digit.',
    bpjsKetenagakerjaan: 'Nomor BPJS Ketenagakerjaan harus 11 digit.',
    bankAccount: 'Nomor rekening tidak valid (8-30 digit).',
    npwp: 'Format NPWP tidak valid (15-20 karakter).',
    address: 'Alamat tidak valid.',
    postalCode: 'Kode pos tidak valid.',
    postalCodeNumeric: 'Kode pos hanya angka (3-12 digit).',
    period: 'Format periode YYYY-MM tidak valid.',
    domain: 'Domain tidak valid.',
  };

  /**
   * Validate an entire form. Adds .is-invalid to invalid fields, removes from valid ones.
   * Returns true if the form is valid, false otherwise.
   */
  function validateForm(formEl) {
    if (!formEl) return true;
    formEl.classList.add('was-validated');
    var fields = formEl.querySelectorAll('input,select,textarea');
    var valid = true;
    for (var i = 0; i < fields.length; i++) {
      var el = fields[i];
      if (el.type === 'hidden' || el.type === 'button' || el.type === 'submit') continue;
      if (!el.checkValidity()) {
        el.classList.add('is-invalid');
        valid = false;
      } else {
        el.classList.remove('is-invalid');
      }
    }
    return valid;
  }

  window.ArcavValidation = {
    patterns,
    messages,
    toRegExp,
    applyConstraints,
    clearValidity,
    setInvalid,
    validateForm,
  };
})();

