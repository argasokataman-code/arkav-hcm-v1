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
    // Matches backend: /^[a-z0-9_\-]+$/
    codeSlug: /^[a-z0-9_\-]+$/,
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
    toRegExp,
    applyConstraints,
    clearValidity,
    setInvalid,
    validateForm,
  };
})();

