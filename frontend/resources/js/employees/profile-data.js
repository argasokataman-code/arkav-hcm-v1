(function () {
    'use strict';

    const form = document.querySelector('[data-profile-form]');
    if (!form) {
        return;
    }

    const feedback = document.querySelector('[data-profile-feedback]');
    const submitButton = document.querySelector('[data-profile-submit]');
    const resetButton = document.querySelector('[data-profile-reset]');

    const fields = {
        firstName: document.querySelector('[data-profile-first-name]'),
        lastName: document.querySelector('[data-profile-last-name]'),
        email: document.querySelector('[data-profile-email]'),
        phone: document.querySelector('[data-profile-phone]'),
        address: document.querySelector('[data-profile-address]'),
        addressDetail: document.querySelector('[data-profile-address-detail]'),
        currentPassword: form.querySelector('input[name="currentPassword"]'),
        newPassword: form.querySelector('input[name="newPassword"]'),
        confirmPassword: form.querySelector('input[name="confirmPassword"]'),
        photo: document.querySelector('[data-profile-photo]')
    };

    let snapshot = null;

    function showFeedback(type, message) {
        if (!feedback) {
            return;
        }

        feedback.classList.remove('d-none', 'alert-success', 'alert-danger', 'alert-warning');
        feedback.classList.add(`alert-${type}`);
        feedback.textContent = message;
    }

    function clearFeedback() {
        if (!feedback) {
            return;
        }

        feedback.classList.add('d-none');
        feedback.textContent = '';
        feedback.classList.remove('alert-success', 'alert-danger', 'alert-warning');
    }

    function setLoading(loading) {
        if (submitButton) {
            submitButton.disabled = loading;
            submitButton.textContent = loading ? 'Saving...' : 'Save';
        }
    }

    function normalize(value) {
        return (value || '').toString().trim();
    }

    function parseApiError(payload) {
        if (!payload) {
            return 'Terjadi kesalahan saat memproses permintaan.';
        }

        if (payload.message && typeof payload.message === 'string') {
            return payload.message;
        }

        if (payload.errors && typeof payload.errors === 'object') {
            const firstKey = Object.keys(payload.errors)[0];
            if (firstKey && Array.isArray(payload.errors[firstKey])) {
                return payload.errors[firstKey][0];
            }
        }

        return 'Terjadi kesalahan saat memproses permintaan.';
    }

    function splitName(fullName) {
        const value = normalize(fullName);
        if (!value) {
            return { firstName: '', lastName: '' };
        }

        const parts = value.split(/\s+/);
        const firstName = parts.shift() || '';
        const lastName = parts.join(' ');

        return { firstName, lastName };
    }

    function applyProfile(profile) {
        const name = splitName(profile.name || profile.profile?.name || '');

        fields.firstName.value = name.firstName;
        fields.lastName.value = name.lastName;
        fields.email.value = normalize(profile.email || profile.profile?.email || '');
        fields.phone.value = normalize(profile.profile?.phone || '');
        fields.address.value = normalize(profile.profile?.address || '');
        fields.addressDetail.value = normalize(profile.profile?.addressDetail || '');

        if (fields.photo && profile.profile?.profilePhotoUrl) {
            fields.photo.src = profile.profile.profilePhotoUrl;
        }

        snapshot = {
            firstName: fields.firstName.value,
            lastName: fields.lastName.value,
            email: fields.email.value,
            phone: fields.phone.value,
            address: fields.address.value,
            addressDetail: fields.addressDetail.value
        };
    }

    function collectPayload() {
        const firstName = normalize(fields.firstName.value);
        const lastName = normalize(fields.lastName.value);
        const name = [firstName, lastName].filter(Boolean).join(' ').trim();

        const payload = {
            name,
            email: normalize(fields.email.value),
            phone: normalize(fields.phone.value) || null,
            address: normalize(fields.address.value) || null,
            addressDetail: normalize(fields.addressDetail.value) || null
        };

        const currentPassword = normalize(fields.currentPassword.value);
        const newPassword = normalize(fields.newPassword.value);
        const confirmPassword = normalize(fields.confirmPassword.value);

        if (newPassword || confirmPassword || currentPassword) {
            payload.currentPassword = currentPassword;
            payload.newPassword = newPassword;
            payload.confirmPassword = confirmPassword;
        }

        return payload;
    }

    async function loadProfile() {
        clearFeedback();

        const token = (window.AuthApi && window.AuthApi.getToken()) || localStorage.getItem('arcav_access_token');
        const response = await fetch('/v1/identity/auth/me', {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                ...(token ? { 'Authorization': 'Bearer ' + token } : {})
            },
            credentials: 'same-origin'
        });

        const payload = await response.json().catch(() => null);

        if (!response.ok || !payload?.success) {
            throw new Error(parseApiError(payload));
        }

        applyProfile(payload.data || {});
    }

    async function submitProfile(event) {
        event.preventDefault();
        clearFeedback();

        const payload = collectPayload();

        if (!payload.name) {
            showFeedback('warning', 'Nama wajib diisi.');
            return;
        }

        if (!payload.email) {
            showFeedback('warning', 'Email wajib diisi.');
            return;
        }

        if ((payload.newPassword || payload.confirmPassword) && payload.newPassword !== payload.confirmPassword) {
            showFeedback('warning', 'Konfirmasi password tidak cocok.');
            return;
        }

        try {
            setLoading(true);

            const token = (window.AuthApi && window.AuthApi.getToken()) || localStorage.getItem('arcav_access_token');
            const response = await fetch('/v1/identity/auth/profile', {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    ...(token ? { 'Authorization': 'Bearer ' + token } : {})
                },
                credentials: 'same-origin',
                body: JSON.stringify(payload)
            });

            const body = await response.json().catch(() => null);
            if (!response.ok || !body?.success) {
                throw new Error(parseApiError(body));
            }

            applyProfile(body.data || payload);
            fields.currentPassword.value = '';
            fields.newPassword.value = '';
            fields.confirmPassword.value = '';
            showFeedback('success', body.message || 'Profile berhasil diperbarui.');
        } catch (error) {
            showFeedback('danger', error.message || 'Gagal memperbarui profile.');
        } finally {
            setLoading(false);
        }
    }

    function resetForm() {
        if (!snapshot) {
            return;
        }

        fields.firstName.value = snapshot.firstName;
        fields.lastName.value = snapshot.lastName;
        fields.email.value = snapshot.email;
        fields.phone.value = snapshot.phone;
        fields.address.value = snapshot.address;
        fields.addressDetail.value = snapshot.addressDetail;
        fields.currentPassword.value = '';
        fields.newPassword.value = '';
        fields.confirmPassword.value = '';
        clearFeedback();
    }

    form.addEventListener('submit', submitProfile);
    if (resetButton) {
        resetButton.addEventListener('click', resetForm);
    }

    loadProfile().catch((error) => {
        showFeedback('danger', error.message || 'Gagal memuat profile.');
    });
})();
