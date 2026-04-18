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
    var currentPasswordField = document.querySelector('[data-profile-settings-current-password]');
    var newPasswordField = document.querySelector('[data-profile-settings-new-password]');
    var confirmPasswordField = document.querySelector('[data-profile-settings-confirm-password]');
    var companyModeNode = document.querySelector('[data-company-context-mode]');
    var companyNameNode = document.querySelector('[data-company-name]');
    var companyIdNode = document.querySelector('[data-company-id]');
    var companyCodeNode = document.querySelector('[data-company-code]');
    var copyCompanyCodeBtn = document.querySelector('[data-copy-company-code]');
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

    function buildHeaders(extra) {
        var headers = {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        };
        var tenant = getTenantContext();
        if (tenant && tenant.companyCode) {
            headers['X-Company-Code'] = String(tenant.companyCode);
        }
        if (tenant && tenant.companyId) {
            headers['X-Company-Id'] = String(tenant.companyId);
        }
        if (extra) {
            Object.keys(extra).forEach(function (k) { headers[k] = extra[k]; });
        }
        return headers;
    }

    function renderCompanyContext(mePayload) {
        if (!mePayload || !mePayload.success || !mePayload.data) {
            return;
        }
        var activeCompany = mePayload.data.activeCompany || null;
        var tenant = getTenantContext();
        var mode = tenant && tenant.companyCode ? 'Login as Company' : 'Regular Login';
        if (companyModeNode) {
            companyModeNode.textContent = mode;
            companyModeNode.classList.toggle('bg-warning-subtle', mode === 'Regular Login');
            companyModeNode.classList.toggle('text-warning', mode === 'Regular Login');
        }
        if (companyNameNode) {
            companyNameNode.textContent = activeCompany && activeCompany.name ? String(activeCompany.name) : '—';
        }
        if (companyIdNode) {
            companyIdNode.textContent = activeCompany && activeCompany.id ? String(activeCompany.id) : '—';
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
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
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
        var settingsAvailable = true;

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
            merged.identityEmail = normalize(mePayload.data.email || '');
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
        if (!settingsAvailable) {
            showFeedback('warning', 'Profile dimuat dari data akun. Akses settings admin dibatasi untuk role tertentu.');
        }
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
