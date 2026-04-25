(function () {
    'use strict';

    var form = document.querySelector('[data-company-profile-form]');
    if (!form) {
        return;
    }

    var feedback = document.querySelector('[data-company-profile-feedback]');
    var submitButton = document.querySelector('[data-company-profile-submit]');
    var resetButton = document.querySelector('[data-company-profile-reset]');
    var companyFields = Array.prototype.slice.call(document.querySelectorAll('[data-company-field]'));
    var ownerOnlySection = document.querySelector('[data-company-profile-owner-only]');
    var notOwnerSection = document.querySelector('[data-company-profile-not-owner]');
    var snapshot = {};

    function normalize(value) {
        return (value || '').toString().trim();
    }

    function getTenantContext() {
        try {
            if (window.AuthApi && typeof window.AuthApi.getTenantContext === 'function') {
                return window.AuthApi.getTenantContext() || {};
            }
        } catch (_e) {}
        return {};
    }

    function getApiToken() {
        try {
            if (window.AuthApi && typeof window.AuthApi.getToken === 'function') {
                return window.AuthApi.getToken() || null;
            }
        } catch (_e) {}
        try {
            return window.localStorage.getItem((window.AuthApi && window.AuthApi.tokenKey) || 'arcav_access_token');
        } catch (_e) {
            return null;
        }
    }

    function buildHeaders(extra) {
        var headers = {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        };
        var token = getApiToken();
        if (token) {
            headers['Authorization'] = 'Bearer ' + String(token);
        }
        var tenant = getTenantContext();
        if (tenant && tenant.companyCode) {
            headers['X-Company-Code'] = String(tenant.companyCode);
        }
        if (tenant && tenant.companyId) {
            headers['X-Company-Id'] = String(tenant.companyId);
        }
        if (tenant && tenant.companyUuid) {
            headers['X-Company-UUID'] = String(tenant.companyUuid);
        }
        if (extra) {
            Object.keys(extra).forEach(function (k) { headers[k] = extra[k]; });
        }
        return headers;
    }

    function showFeedback(type, message) {
        if (!feedback) { return; }
        feedback.classList.remove('d-none', 'alert-success', 'alert-danger', 'alert-warning');
        feedback.classList.add('alert-' + type);
        feedback.textContent = message;
    }

    function clearFeedback() {
        if (!feedback) { return; }
        feedback.classList.add('d-none');
        feedback.classList.remove('alert-success', 'alert-danger', 'alert-warning');
        feedback.textContent = '';
    }

    function setLoading(isLoading) {
        if (!submitButton) { return; }
        submitButton.disabled = isLoading;
        submitButton.textContent = isLoading ? 'Menyimpan...' : 'Simpan';
    }

    function parseError(payload) {
        if (!payload) { return 'Terjadi kesalahan saat memproses data.'; }
        if (payload.error && payload.error.message) { return payload.error.message; }
        if (payload.message && typeof payload.message === 'string') { return payload.message; }
        if (payload.errors && typeof payload.errors === 'object') {
            var firstKey = Object.keys(payload.errors)[0];
            if (firstKey && Array.isArray(payload.errors[firstKey]) && payload.errors[firstKey][0]) {
                return payload.errors[firstKey][0];
            }
        }
        return 'Terjadi kesalahan saat memproses data.';
    }

    function applyData(companyProfile) {
        var profile = companyProfile || {};
        companyFields.forEach(function (field) {
            var key = field.getAttribute('data-company-field');
            var value = profile[key];
            if (value == null) {
                if (key === 'legal_name') { value = profile.legalName; }
                if (key === 'postal_code') { value = profile.postalCode; }
            }
            value = value == null ? '' : String(value);
            field.value = value;
            snapshot[key] = value;
        });
    }

    function resetFields() {
        companyFields.forEach(function (field) {
            var key = field.getAttribute('data-company-field');
            field.value = snapshot[key] || '';
        });
        clearFeedback();
    }

    function collectPayload() {
        var result = {};
        companyFields.forEach(function (field) {
            var key = field.getAttribute('data-company-field');
            result[key] = normalize(field.value);
        });
        return result;
    }

    async function fetchIdentityContext() {
        var meRes = await fetch('/v1/identity/auth/me', {
            method: 'GET',
            headers: buildHeaders(),
            credentials: 'same-origin'
        });
        var meBody = await meRes.json().catch(function () { return null; });
        if (!meRes.ok || !meBody || !meBody.success || !meBody.data) {
            throw new Error(parseError(meBody));
        }

        return meBody.data;
    }

    async function loadCompanyProfile() {
        clearFeedback();
        var data = await fetchIdentityContext();

        var role = normalize((data.activeCompany && data.activeCompany.role) || '').toLowerCase();
        var isOwner = role === 'owner';

        if (ownerOnlySection) {
            ownerOnlySection.classList.toggle('d-none', !isOwner);
        }
        if (notOwnerSection) {
            notOwnerSection.classList.toggle('d-none', isOwner);
        }
        if (submitButton) {
            submitButton.closest('[data-company-profile-actions]') && (submitButton.closest('[data-company-profile-actions]').classList.toggle('d-none', !isOwner));
        }

        if (isOwner) {
            applyData(data.companyProfile || {});
        }
    }

    async function saveCompanyProfile(event) {
        event.preventDefault();
        clearFeedback();

        var raw = collectPayload();
        if (!normalize(raw.name)) {
            showFeedback('warning', 'Company Name wajib diisi.');
            return;
        }

        var payload = {
            name: '',
            email: normalize(document.querySelector('[data-identity-email]') && document.querySelector('[data-identity-email]').dataset.value || ''),
            companyName: normalize(raw.name) || null,
            companyLegalName: normalize(raw.legal_name) || null,
            companyAddress: normalize(raw.address) || null,
            companyCity: normalize(raw.city) || null,
            companyState: normalize(raw.state) || null,
            companyCountry: normalize(raw.country) || null,
            companyPostalCode: normalize(raw.postal_code) || null
        };

        try {
            setLoading(true);
            var identity = await fetchIdentityContext();
            payload.name = normalize(identity.name) || 'Owner';
            payload.email = payload.email || normalize(identity.email);

            var res = await fetch('/v1/identity/auth/profile', {
                method: 'PUT',
                headers: buildHeaders({ 'Content-Type': 'application/json' }),
                credentials: 'same-origin',
                body: JSON.stringify(payload)
            });
            var body = await res.json().catch(function () { return null; });
            if (!res.ok || !body || body.success !== true) {
                throw new Error(parseError(body));
            }

            // update snapshot
            if (body.data && body.data.companyProfile) {
                applyData(body.data.companyProfile);
            }

            showFeedback('success', 'Company profile berhasil disimpan.');
        } catch (err) {
            showFeedback('danger', err && err.message ? err.message : 'Gagal menyimpan company profile.');
        } finally {
            setLoading(false);
        }
    }

    form.addEventListener('submit', saveCompanyProfile);
    if (resetButton) {
        resetButton.addEventListener('click', resetFields);
    }

    loadCompanyProfile().catch(function (err) {
        showFeedback('danger', err && err.message ? err.message : 'Gagal memuat data company profile.');
    });
})();
