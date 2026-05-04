(function () {
    'use strict';

    var form = document.querySelector('[data-profile-settings-form]');
    if (!form) {
        return;
    }

    var feedback = document.querySelector('[data-profile-settings-feedback]');
    var submitButton = document.querySelector('[data-profile-settings-submit]');
    var resetButton = document.querySelector('[data-profile-settings-reset]');
    var fields = Array.prototype.slice.call(document.querySelectorAll('[data-general-setting]'));
    var companyProfileHintNode = document.querySelector('[data-company-profile-hint]');
    var currentPasswordField = document.querySelector('[data-profile-settings-current-password]');
    var newPasswordField = document.querySelector('[data-profile-settings-new-password]');
    var confirmPasswordField = document.querySelector('[data-profile-settings-confirm-password]');
    var companyContextCardNode = document.querySelector('[data-company-context-card]');
    var companyModeNode = companyContextCardNode ? companyContextCardNode.querySelector('[data-company-context-mode]') : null;
    var companyNameNode = companyContextCardNode ? companyContextCardNode.querySelector('[data-company-name]') : null;
    var companyIdNode = companyContextCardNode ? companyContextCardNode.querySelector('[data-company-id]') : null;
    var companyCodeNode = companyContextCardNode ? companyContextCardNode.querySelector('[data-company-code]') : null;
    var copyCompanyCodeBtn = companyContextCardNode ? companyContextCardNode.querySelector('[data-copy-company-code]') : null;
    var subscriptionCardNode = document.querySelector('[data-subscription-summary-card]');
    var subscriptionStatusNode = subscriptionCardNode ? subscriptionCardNode.querySelector('[data-subscription-status]') : null;
    var subscriptionPackageNode = subscriptionCardNode ? subscriptionCardNode.querySelector('[data-subscription-package]') : null;
    var subscriptionBillingCycleNode = subscriptionCardNode ? subscriptionCardNode.querySelector('[data-subscription-billing-cycle]') : null;
    var subscriptionPeriodNode = subscriptionCardNode ? subscriptionCardNode.querySelector('[data-subscription-period]') : null;
    var subscriptionNextPaymentDateNode = subscriptionCardNode ? subscriptionCardNode.querySelector('[data-subscription-next-payment-date]') : null;
    var subscriptionNextPaymentAmountNode = subscriptionCardNode ? subscriptionCardNode.querySelector('[data-subscription-next-payment-amount]') : null;
    var subscriptionEmployeeSlotsNode = subscriptionCardNode ? subscriptionCardNode.querySelector('[data-subscription-employee-slots]') : null;
    var subscriptionEmployeeUsageNode = subscriptionCardNode ? subscriptionCardNode.querySelector('[data-subscription-employee-usage]') : null;
    var snapshot = {};

    function normalize(value) {
        return (value || '').toString().trim();
    }

    function formatCompanyReference(company) {
        var companyCode = normalize(company && company.code);
        if (companyCode) {
            return companyCode;
        }
        var companyId = Number(company && company.id);
        if (Number.isFinite(companyId) && companyId > 0) {
            return 'CMP-' + String(Math.trunc(companyId));
        }
        return '—';
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

    function formatDate(value) {
        var raw = normalize(value);
        if (!raw) {
            return '—';
        }

        try {
            return new Intl.DateTimeFormat('id-ID', {
                day: '2-digit',
                month: 'short',
                year: 'numeric'
            }).format(new Date(raw));
        } catch (_e) {
            return raw;
        }
    }

    function formatMoney(value) {
        var amount = Number(value || 0);
        if (!Number.isFinite(amount) || amount <= 0) {
            return '—';
        }

        try {
            return new Intl.NumberFormat('id-ID', {
                style: 'currency',
                currency: 'IDR',
                maximumFractionDigits: 0
            }).format(amount);
        } catch (_e) {
            return 'Rp ' + String(amount);
        }
    }

    function renderEmployeeSlots(subscription) {
        if (!subscriptionEmployeeSlotsNode && !subscriptionEmployeeUsageNode) {
            return;
        }

        var slots = subscription && subscription.employeeSlots ? subscription.employeeSlots : null;
        if (!slots || slots.isConfigured !== true) {
            if (subscriptionEmployeeSlotsNode) {
                subscriptionEmployeeSlotsNode.textContent = '—';
            }
            if (subscriptionEmployeeUsageNode) {
                subscriptionEmployeeUsageNode.textContent = 'Paket ini belum mengirim limit employee.';
            }
            return;
        }

        if (subscriptionEmployeeSlotsNode) {
            subscriptionEmployeeSlotsNode.textContent = slots.isUnlimited ? 'Unlimited employees' : ('Max ' + String(slots.limit) + ' employees');
        }
        if (subscriptionEmployeeUsageNode) {
            subscriptionEmployeeUsageNode.textContent = slots.isUnlimited
                ? (String(slots.used || 0) + ' employee terdaftar')
                : (String(slots.used || 0) + ' dipakai • ' + String(slots.remaining || 0) + ' slot tersisa');
        }
    }

    function renderCompanyContext(mePayload) {
        if (!mePayload || !mePayload.success || !mePayload.data) {
            return;
        }
        var activeCompany = mePayload.data.activeCompany || null;
        var role = normalize(activeCompany && activeCompany.role).toLowerCase();
        var isOwnerCompanyMode = role === 'owner'; // eslint-disable-line no-unused-vars
        var tenant = getTenantContext();
        var mode = tenant && tenant.companyCode ? 'Login Company' : 'Login Employee';
        if (companyModeNode) {
            companyModeNode.textContent = mode;
            companyModeNode.classList.toggle('bg-warning-subtle', mode === 'Login Employee');
            companyModeNode.classList.toggle('text-warning', mode === 'Login Employee');
        }
        if (companyNameNode) {
            companyNameNode.textContent = activeCompany && activeCompany.name ? String(activeCompany.name) : '—';
        }
        if (companyIdNode) {
            companyIdNode.textContent = formatCompanyReference(activeCompany);
        }
        if (companyCodeNode) {
            companyCodeNode.textContent = activeCompany && activeCompany.code ? String(activeCompany.code) : (tenant && tenant.companyCode ? String(tenant.companyCode) : '—');
        }
        if (copyCompanyCodeBtn) {
            var code = activeCompany && activeCompany.code ? String(activeCompany.code) : (tenant && tenant.companyCode ? String(tenant.companyCode) : '');
            copyCompanyCodeBtn.classList.toggle('d-none', !code);
            copyCompanyCodeBtn.onclick = function () {
                if (!code) return;
                try {
                    navigator.clipboard.writeText(code);
                    showFeedback('success', 'Company code berhasil disalin.');
                } catch (_e) {
                    showFeedback('warning', 'Gagal menyalin otomatis. Salin manual company code di atas.');
                }
            };
        }

        if (companyProfileHintNode) {
            var isOwner = normalize((activeCompany && activeCompany.role) || '').toLowerCase() === 'owner';
            companyProfileHintNode.classList.toggle('d-none', !isOwner);
        }
    }

    function renderSubscriptionSummary(mePayload) {
        if (!subscriptionCardNode) {
            return;
        }

        var subscription = mePayload && mePayload.data ? (mePayload.data.subscription || null) : null;
        if (!subscription) {
            subscriptionCardNode.classList.add('d-none');
            return;
        }

        subscriptionCardNode.classList.remove('d-none');

        if (subscriptionStatusNode) {
            subscriptionStatusNode.textContent = normalize(subscription.status).replace(/_/g, ' ') || '—';
        }
        if (subscriptionPackageNode) {
            var packageLabel = normalize(subscription.packageName) || normalize(subscription.packageCode) || normalize(subscription.planCode);
            subscriptionPackageNode.textContent = packageLabel || '—';
        }
        if (subscriptionBillingCycleNode) {
            subscriptionBillingCycleNode.textContent = normalize(subscription.billingCycle) || '—';
        }
        if (subscriptionPeriodNode) {
            subscriptionPeriodNode.textContent = formatDate(subscription.startsAt) + ' - ' + formatDate(subscription.endsAt);
        }

        var nextPayment = subscription.nextPayment || {};
        if (subscriptionNextPaymentDateNode) {
            subscriptionNextPaymentDateNode.textContent = formatDate(nextPayment.date);
        }
        if (subscriptionNextPaymentAmountNode) {
            var amountLabel = formatMoney(nextPayment.amount);
            if (nextPayment.invoiceNumber) {
                amountLabel = amountLabel + ' • ' + String(nextPayment.invoiceNumber);
            }
            subscriptionNextPaymentAmountNode.textContent = amountLabel;
        }

        renderEmployeeSlots(subscription);
    }

    function showFeedback(type, message) {
        if (!feedback) {
            return;
        }

        feedback.classList.remove('d-none', 'alert-success', 'alert-danger', 'alert-warning');
        feedback.classList.add('alert-' + type);
        feedback.textContent = message;
    }

    function clearFeedback() {
        if (!feedback) {
            return;
        }

        feedback.classList.add('d-none');
        feedback.classList.remove('alert-success', 'alert-danger', 'alert-warning');
        feedback.textContent = '';
    }

    function setLoading(isLoading) {
        if (!submitButton) {
            return;
        }

        submitButton.disabled = isLoading;
        submitButton.textContent = isLoading ? 'Saving...' : 'Save';
    }

    function parseError(payload) {
        if (!payload) {
            return 'Terjadi kesalahan saat memproses data.';
        }

        if (payload.message && typeof payload.message === 'string') {
            return payload.message;
        }

        if (payload.error && payload.error.message && typeof payload.error.message === 'string') {
            return payload.error.message;
        }

        if (payload.errors && typeof payload.errors === 'object') {
            var firstKey = Object.keys(payload.errors)[0];
            if (firstKey && Array.isArray(payload.errors[firstKey]) && payload.errors[firstKey][0]) {
                return payload.errors[firstKey][0];
            }
        }

        return 'Terjadi kesalahan saat memproses data.';
    }

    function splitName(fullName) {
        var clean = normalize(fullName);
        if (!clean) {
            return { firstName: '', lastName: '' };
        }

        var parts = clean.split(/\s+/);
        var firstName = parts.shift() || '';
        return {
            firstName: firstName,
            lastName: parts.join(' ')
        };
    }

    function collectPayload() {
        var payload = {};
        fields.forEach(function (field) {
            var key = field.getAttribute('data-general-setting');
            payload[key] = normalize(field.value);
        });
        return payload;
    }

    function applyData(data) {
        var profile = data || {};
        var firstName = profile.general_first_name;
        var lastName = profile.general_last_name;

        if (!firstName && !lastName && profile.general_name) {
            var split = splitName(profile.general_name);
            firstName = split.firstName;
            lastName = split.lastName;
        }

        fields.forEach(function (field) {
            var key = field.getAttribute('data-general-setting');
            var settingKey = 'general_' + key;
            var value = profile[settingKey];

            if (key === 'first_name') {
                value = firstName || '';
            }
            if (key === 'last_name') {
                value = lastName || '';
            }
            if (key === 'email' && !value && data.identityEmail) {
                value = data.identityEmail;
            }

            field.value = value == null ? '' : String(value);
            snapshot[key] = field.value;
        });
    }

    function resetFields() {
        fields.forEach(function (field) {
            var key = field.getAttribute('data-general-setting');
            field.value = snapshot[key] || '';
        });
        if (currentPasswordField) {
            currentPasswordField.value = '';
        }
        if (newPasswordField) {
            newPasswordField.value = '';
        }
        if (confirmPasswordField) {
            confirmPasswordField.value = '';
        }
        clearFeedback();
    }

    function collectPasswordPayload() {
        return {
            currentPassword: normalize(currentPasswordField && currentPasswordField.value),
            newPassword: normalize(newPasswordField && newPasswordField.value),
            confirmPassword: normalize(confirmPasswordField && confirmPasswordField.value)
        };
    }

    function extractSettingsObject(payloadData) {
        if (!payloadData || typeof payloadData !== 'object') {
            return {};
        }

        if (payloadData.settings && typeof payloadData.settings === 'object') {
            return payloadData.settings;
        }

        return payloadData;
    }

    function shouldUpdatePassword(passwordPayload) {
        return Boolean(passwordPayload.currentPassword || passwordPayload.newPassword || passwordPayload.confirmPassword);
    }

    async function updateIdentityProfile(settingsPayload, passwordPayload) {
        if (shouldUpdatePassword(passwordPayload)) {
            if (!passwordPayload.currentPassword) {
                throw new Error('Current Password wajib diisi untuk mengganti password.');
            }
            if (!passwordPayload.newPassword) {
                throw new Error('New Password wajib diisi.');
            }
            if (!passwordPayload.confirmPassword) {
                throw new Error('Confirm Password wajib diisi.');
            }
            if (passwordPayload.newPassword !== passwordPayload.confirmPassword) {
                throw new Error('Konfirmasi password tidak cocok.');
            }
        }

        var identityPayload = {
            name: normalize((settingsPayload.first_name || '') + ' ' + (settingsPayload.last_name || '')),
            email: normalize(settingsPayload.email),
            phone: normalize(settingsPayload.phone) || null,
            address: normalize(settingsPayload.address) || null,
            addressDetail: normalize(settingsPayload.city) || null,
            currentPassword: passwordPayload.currentPassword,
            newPassword: passwordPayload.newPassword,
            confirmPassword: passwordPayload.confirmPassword
        };

        var identityResponse = await fetch('/v1/identity/auth/profile', {
            method: 'PUT',
            headers: buildHeaders({
                'Content-Type': 'application/json'
            }),
            credentials: 'same-origin',
            body: JSON.stringify(identityPayload)
        });

        var identityBody = await identityResponse.json().catch(function () { return null; });
        if (!identityResponse.ok || !identityBody || identityBody.success !== true) {
            throw new Error(parseError(identityBody));
        }

        if (currentPasswordField) {
            currentPasswordField.value = '';
        }
        if (newPasswordField) {
            newPasswordField.value = '';
        }
        if (confirmPasswordField) {
            confirmPasswordField.value = '';
        }

        return {
            passwordUpdated: shouldUpdatePassword(passwordPayload),
            profile: identityBody.data || {}
        };
    }

    async function loadSettings() {
        clearFeedback();

        var merged = {};
        var settingsAvailable = true; // eslint-disable-line no-unused-vars

        try {
            var settingsResponse = await fetch('/v1/hcm/settings?group=general', {
                method: 'GET',
                headers: buildHeaders(),
                credentials: 'same-origin'
            });

            var settingsPayload = await settingsResponse.json().catch(function () { return null; });
            if (settingsResponse.ok && settingsPayload && settingsPayload.success === true) {
                merged = extractSettingsObject(settingsPayload.data);
            } else {
                settingsAvailable = false;
            }
        } catch (_e) {
            settingsAvailable = false;
        }

        var meResponse = await fetch('/v1/identity/auth/me', {
            method: 'GET',
            headers: buildHeaders(),
            credentials: 'same-origin'
        });
        var mePayload = await meResponse.json().catch(function () { return null; });
        if (meResponse.ok && mePayload && mePayload.success && mePayload.data) {
            renderCompanyContext(mePayload);
            renderSubscriptionSummary(mePayload);
            merged.identityEmail = normalize(mePayload.data.email || '');
            merged.companyProfile = mePayload.data.companyProfile || {};
            if (!merged.general_name && mePayload.data.name) {
                merged.general_name = mePayload.data.name;
            }
            if (!merged.general_phone && mePayload.data.profile && mePayload.data.profile.phone) {
                merged.general_phone = mePayload.data.profile.phone;
            }
            if (!merged.general_address && mePayload.data.profile && mePayload.data.profile.address) {
                merged.general_address = mePayload.data.profile.address;
            }
            if (!merged.general_city && mePayload.data.profile && mePayload.data.profile.addressDetail) {
                merged.general_city = mePayload.data.profile.addressDetail;
            }
        } else {
            throw new Error(parseError(mePayload));
        }

        applyData(merged);
    }

    async function saveSettings(event) {
        event.preventDefault();
        clearFeedback();

        var payload = collectPayload();
        if (!payload.first_name) {
            showFeedback('warning', 'First Name wajib diisi.');
            return;
        }
        if (!payload.email) {
            showFeedback('warning', 'Email wajib diisi.');
            return;
        }

        payload.name = [payload.first_name, payload.last_name].filter(Boolean).join(' ').trim();
        var passwordPayload = collectPasswordPayload();

        try {
            setLoading(true);

            var identityResult = await updateIdentityProfile(payload, passwordPayload);

            var settingsSaved = false;
            try {
                var response = await fetch('/v1/hcm/settings', {
                    method: 'POST',
                    headers: buildHeaders({
                        'Content-Type': 'application/json'
                    }),
                    credentials: 'same-origin',
                    body: JSON.stringify({
                        group: 'general',
                        settings: payload
                    })
                });

                var body = await response.json().catch(function () { return null; });
                if (response.ok && body && body.success === true) {
                    settingsSaved = true;
                } else if (response.status !== 401 && response.status !== 403) {
                    throw new Error(parseError(body));
                }
            } catch (settingsError) {
                if (settingsError && settingsError.message) {
                    throw settingsError;
                }
            }

            applyData(Object.assign({}, extractSettingsObject((identityResult && identityResult.profile) || {}), {
                general_first_name: payload.first_name,
                general_last_name: payload.last_name,
                general_email: payload.email,
                general_phone: payload.phone,
                general_address: payload.address,
                general_city: payload.city,
                general_name: payload.name
            }));

            if (settingsSaved) {
                showFeedback('success', identityResult.passwordUpdated ? 'Profile settings dan password berhasil disimpan.' : 'Profile settings berhasil disimpan.');
            } else {
                showFeedback('success', identityResult.passwordUpdated ? 'Profil dan password berhasil disimpan. Beberapa settings admin dilewati karena keterbatasan akses.' : 'Profil berhasil disimpan. Beberapa settings admin dilewati karena keterbatasan akses.');
            }
        } catch (error) {
            showFeedback('danger', error && error.message ? error.message : 'Gagal menyimpan profile settings.');
        } finally {
            setLoading(false);
        }
    }

    form.addEventListener('submit', saveSettings);
    if (resetButton) {
        resetButton.addEventListener('click', resetFields);
    }

    loadSettings().catch(function (error) {
        showFeedback('danger', error && error.message ? error.message : 'Gagal memuat data profile settings.');
    });
})();
