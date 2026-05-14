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
    var ownerFields = Array.prototype.slice.call(document.querySelectorAll('[data-owner-field]'));
    var ownerOnlySection = document.querySelector('[data-company-profile-owner-only]');
    var notOwnerSection = document.querySelector('[data-company-profile-not-owner]');
    var ownerPhotoPreviewNode = document.querySelector('[data-owner-photo-preview]');
    var ownerPhotoPlaceholderNode = document.querySelector('[data-owner-photo-placeholder]');
    var ownerPhotoInputNode = document.querySelector('[data-owner-photo-input]');
    var ownerPhotoRemoveButton = document.querySelector('[data-owner-photo-remove]');
    var ownerPhotoErrorNode = document.querySelector('[data-owner-photo-error]');
    var snapshot = {};
    var currentUserId = null;
    var currentPhotoUrl = null;
    var isPhotoUploading = false;
    var isPhotoRemoving = false;
    var PROFILE_PHOTO_MAX_SIZE_BYTES = 2 * 1024 * 1024;
    var PROFILE_PHOTO_ALLOWED_MIME_TYPES = ['image/jpeg', 'image/png', 'image/gif'];
    var OWNER_FIELD_RULES = {
        first_name: {
            label: 'First Name',
            maxLength: 50,
            regex: /^[A-Za-z][A-Za-z\s'.-]{1,49}$/,
            message: 'First Name hanya boleh berisi huruf, spasi, apostrof, titik, atau strip (2-50 karakter).'
        },
        last_name: {
            label: 'Last Name',
            maxLength: 50,
            regex: /^[A-Za-z][A-Za-z\s'.-]{1,49}$/,
            message: 'Last Name hanya boleh berisi huruf, spasi, apostrof, titik, atau strip (2-50 karakter).'
        },
        email: {
            label: 'Email',
            maxLength: 255,
            regex: /^[^\s@]+@[^\s@]+\.[^\s@]+$/,
            message: 'Format Email tidak valid.'
        },
        phone: {
            label: 'Phone',
            maxLength: 20,
            regex: /^\+?(?=(?:\D*\d){8,15}\D*$)[0-9\s\-()]+$/,
            message: 'Format Phone tidak valid. Gunakan 8-15 digit angka (boleh +, spasi, -, atau ()).'
        }
    };
    var COMPANY_FIELD_RULES = {
        legal_name: {
            label: 'Legal Name',
            maxLength: 255
        },
        address: {
            label: 'Alamat',
            maxLength: 180,
            regex: /^[A-Za-z0-9\s.,'\/-]{3,180}$/,
            message: 'Alamat hanya boleh huruf/angka dan simbol . , \' / - (3-180 karakter).'
        },
        city: {
            label: 'Kota',
            maxLength: 60,
            regex: /^[A-Za-z][A-Za-z\s'.-]{1,59}$/,
            message: 'Kota hanya boleh huruf, spasi, apostrof, titik, atau strip (2-60 karakter).'
        },
        state: {
            label: 'Provinsi',
            maxLength: 60,
            regex: /^[A-Za-z][A-Za-z\s'.-]{1,59}$/,
            message: 'Provinsi hanya boleh huruf, spasi, apostrof, titik, atau strip (2-60 karakter).'
        },
        country: {
            label: 'Negara',
            maxLength: 60,
            regex: /^[A-Za-z][A-Za-z\s'.-]{1,59}$/,
            message: 'Negara hanya boleh huruf, spasi, apostrof, titik, atau strip (2-60 karakter).'
        },
        postal_code: {
            label: 'Kode Pos',
            maxLength: 10,
            regex: /^[A-Za-z0-9][A-Za-z0-9\s-]{2,9}$/,
            message: 'Format Kode Pos tidak valid.'
        },
        npwp: {
            label: 'NPWP',
            maxLength: 32,
            regex: /^[0-9.\-\s]{15,32}$/,
            message: 'Format NPWP tidak valid. Gunakan 15-16 digit angka (boleh titik/strip).'
        }
    };

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

    function setPhotoPreview(url) {
        if (!ownerPhotoPreviewNode || !ownerPhotoPlaceholderNode) {
            return;
        }

        if (url) {
            ownerPhotoPreviewNode.src = url;
            ownerPhotoPreviewNode.classList.remove('d-none');
            ownerPhotoPlaceholderNode.classList.add('d-none');
            if (ownerPhotoRemoveButton) {
                ownerPhotoRemoveButton.classList.remove('d-none');
            }
        } else {
            ownerPhotoPreviewNode.src = '';
            ownerPhotoPreviewNode.classList.add('d-none');
            ownerPhotoPlaceholderNode.classList.remove('d-none');
            if (ownerPhotoRemoveButton) {
                ownerPhotoRemoveButton.classList.add('d-none');
            }
        }

        document.querySelectorAll('[data-nav-profile-photo], [data-nav-profile-photo-lg]').forEach(function (img) {
            if (url) {
                img.src = url;
            }
        });
    }

    function normalizeFileExtension(fileName) {
        var raw = normalize(fileName);
        if (!raw || raw.indexOf('.') < 0) {
            return '';
        }

        return raw.split('.').pop().toLowerCase();
    }

    function isAllowedPhotoType(file) {
        if (!file) {
            return false;
        }

        if (PROFILE_PHOTO_ALLOWED_MIME_TYPES.indexOf(normalize(file.type).toLowerCase()) >= 0) {
            return true;
        }

        var ext = normalizeFileExtension(file.name);

        return ext === 'jpg' || ext === 'jpeg' || ext === 'png' || ext === 'gif';
    }

    function showPhotoError(message) {
        if (!ownerPhotoErrorNode) {
            return;
        }

        if (message) {
            ownerPhotoErrorNode.textContent = message;
            ownerPhotoErrorNode.classList.remove('d-none');
            return;
        }

        ownerPhotoErrorNode.textContent = '';
        ownerPhotoErrorNode.classList.add('d-none');
    }

    function formatNpwpDisplay(digits) {
        // digits: up to 16 chars of [0-9]
        if (digits.length === 0) return '';
        if (digits.length === 16) {
            // 16-digit NIK-based NPWP — display raw
            return digits;
        }
        // Old NPWP format: XX.XXX.XXX.X-XXX.XXX (15 digits)
        var d = digits;
        var out = d.substring(0, Math.min(2, d.length));
        if (d.length > 2) out += '.' + d.substring(2, Math.min(5, d.length));
        if (d.length > 5) out += '.' + d.substring(5, Math.min(8, d.length));
        if (d.length > 8) out += '.' + d.substring(8, Math.min(9, d.length));
        if (d.length > 9) out += '-' + d.substring(9, Math.min(12, d.length));
        if (d.length > 12) out += '.' + d.substring(12, 15);
        return out;
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
            // Auto-format NPWP digits from backend into display format
            if (key === 'npwp' && value !== '') {
                value = formatNpwpDisplay(value.replace(/\D/g, '').substring(0, 16));
            }
            field.value = value;
            snapshot[key] = value;
        });
    }

    function applyOwnerData(identityData) {
        var data = identityData || {};
        var profile = data.profile || {};
        var fullName = normalize(data.name);
        var firstName = normalize(profile.firstName);
        var lastName = normalize(profile.lastName);

        if (!firstName && fullName) {
            var parts = fullName.split(/\s+/);
            firstName = parts.shift() || '';
            lastName = parts.join(' ');
        }

        ownerFields.forEach(function (field) {
            var key = field.getAttribute('data-owner-field');
            var value = '';

            if (key === 'first_name') {
                value = firstName;
            } else if (key === 'last_name') {
                value = lastName;
            } else if (key === 'email') {
                value = normalize(data.email);
            } else if (key === 'phone') {
                value = normalize(profile.phone);
            }

            field.value = value;
            snapshot['owner_' + key] = value;
        });
    }

    function resetFields() {
        companyFields.forEach(function (field) {
            var key = field.getAttribute('data-company-field');
            field.value = snapshot[key] || '';
        });
        ownerFields.forEach(function (field) {
            var key = field.getAttribute('data-owner-field');
            field.value = snapshot['owner_' + key] || '';
        });
        clearFeedback();
        showPhotoError('');
    }

    function collectPayload() {
        var result = {};
        companyFields.forEach(function (field) {
            var key = field.getAttribute('data-company-field');
            result[key] = normalize(field.value);
        });
        return result;
    }

    function normalizeNpwp(value) {
        return normalize(value).replace(/\D+/g, '');
    }

    function validateCompanyPayload(payload) {
        var companyName = normalize(payload && payload.name);
        if (!companyName) {
            return 'Company Name wajib diisi.';
        }
        if (companyName.length < 2) {
            return 'Company Name minimal 2 karakter.';
        }
        if (companyName.length > 255) {
            return 'Company Name maksimal 255 karakter.';
        }

        var keys = Object.keys(COMPANY_FIELD_RULES);
        for (var i = 0; i < keys.length; i += 1) {
            var key = keys[i];
            var rule = COMPANY_FIELD_RULES[key];
            var value = normalize(payload[key]);

            if (!value) {
                continue;
            }

            if (rule.maxLength && value.length > rule.maxLength) {
                return rule.label + ' maksimal ' + String(rule.maxLength) + ' karakter.';
            }

            if (rule.regex && !rule.regex.test(value)) {
                return rule.message;
            }

            if (key === 'npwp') {
                var npwpDigits = normalizeNpwp(value);
                if (!/^[0-9]{15,16}$/.test(npwpDigits)) {
                    return 'Format NPWP tidak valid. Gunakan 15-16 digit angka.';
                }
            }
        }

        return null;
    }

    function collectOwnerPayload() {
        var result = {};
        ownerFields.forEach(function (field) {
            var key = field.getAttribute('data-owner-field');
            result[key] = normalize(field.value);
        });
        return result;
    }

    function validateOwnerPayload(payload) {
        var keys = Object.keys(OWNER_FIELD_RULES);

        for (var i = 0; i < keys.length; i += 1) {
            var key = keys[i];
            var rule = OWNER_FIELD_RULES[key];
            var value = normalize(payload[key]);
            var isRequired = key === 'first_name' || key === 'email';

            if (isRequired && !value) {
                return rule.label + ' wajib diisi.';
            }

            if (!value) {
                continue;
            }

            if (rule.maxLength && value.length > rule.maxLength) {
                return rule.label + ' maksimal ' + String(rule.maxLength) + ' karakter.';
            }

            if (rule.regex && !rule.regex.test(value)) {
                return rule.message;
            }
        }

        return null;
    }

    async function uploadPhoto(file) {
        if (!currentUserId) {
            showPhotoError('User ID belum dimuat, coba refresh halaman.');
            return;
        }
        if (isPhotoUploading) {
            return;
        }
        if (!isAllowedPhotoType(file)) {
            showPhotoError('Format foto harus JPG, PNG, atau GIF.');
            return;
        }
        if (file.size > PROFILE_PHOTO_MAX_SIZE_BYTES) {
            showPhotoError('Ukuran foto maks 2MB.');
            return;
        }

        showPhotoError('');
        isPhotoUploading = true;
        if (ownerPhotoInputNode) {
            ownerPhotoInputNode.disabled = true;
        }

        var reader = new FileReader();
        reader.onload = function (event) {
            setPhotoPreview(event.target.result);
        };
        reader.readAsDataURL(file);

        var formData = new FormData();
        formData.append('photo', file);

        var headers = buildHeaders();
        delete headers['Content-Type'];

        try {
            var response = await fetch('/v1/hcm/employees/' + encodeURIComponent(currentUserId) + '/profile-photo', {
                method: 'POST',
                headers: headers,
                credentials: 'same-origin',
                body: formData
            });
            var body = await response.json().catch(function () { return null; });

            if (!response.ok || !body || body.success !== true) {
                setPhotoPreview(currentPhotoUrl);
                showPhotoError((body && body.error && body.error.message) ? body.error.message : 'Gagal mengupload foto.');
                return;
            }

            currentPhotoUrl = body.data && body.data.profilePhotoUrl ? body.data.profilePhotoUrl : null;
            setPhotoPreview(currentPhotoUrl);
            showFeedback('success', 'Foto owner berhasil diperbarui.');
        } catch (_error) {
            setPhotoPreview(currentPhotoUrl);
            showPhotoError('Gagal mengupload foto.');
        } finally {
            isPhotoUploading = false;
            if (ownerPhotoInputNode) {
                ownerPhotoInputNode.disabled = false;
            }
        }
    }

    async function removePhoto() {
        if (!currentUserId) {
            showPhotoError('User ID belum dimuat, coba refresh halaman.');
            return;
        }
        if (isPhotoRemoving || isPhotoUploading) {
            return;
        }

        var previousPhoto = currentPhotoUrl;
        isPhotoRemoving = true;
        showPhotoError('');
        setPhotoPreview(null);

        if (ownerPhotoRemoveButton) {
            ownerPhotoRemoveButton.disabled = true;
        }

        try {
            var response = await fetch('/v1/hcm/employees/' + encodeURIComponent(currentUserId) + '/profile-photo', {
                method: 'DELETE',
                headers: buildHeaders(),
                credentials: 'same-origin'
            });
            var body = await response.json().catch(function () { return null; });

            if (!response.ok || !body || body.success !== true) {
                currentPhotoUrl = previousPhoto;
                setPhotoPreview(previousPhoto);
                showPhotoError((body && body.error && body.error.message) ? body.error.message : 'Gagal menghapus foto.');
                return;
            }

            currentPhotoUrl = null;
            setPhotoPreview(null);
            showFeedback('success', 'Foto owner berhasil dihapus.');
        } catch (_error) {
            currentPhotoUrl = previousPhoto;
            setPhotoPreview(previousPhoto);
            showPhotoError('Gagal menghapus foto.');
        } finally {
            isPhotoRemoving = false;
            if (ownerPhotoRemoveButton) {
                ownerPhotoRemoveButton.disabled = false;
            }
        }
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
        currentUserId = data.id || null;

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
            applyOwnerData(data);
            currentPhotoUrl = data.profile && data.profile.profilePhotoUrl ? data.profile.profilePhotoUrl : null;
            setPhotoPreview(currentPhotoUrl);
        }
    }

    async function saveCompanyProfile(event) {
        event.preventDefault();
        clearFeedback();

        var raw = collectPayload();
        var ownerPayload = collectOwnerPayload();
        var ownerPayloadError = validateOwnerPayload(ownerPayload);
        if (ownerPayloadError) {
            showFeedback('warning', ownerPayloadError);
            return;
        }
        var companyPayloadError = validateCompanyPayload(raw);
        if (companyPayloadError) {
            showFeedback('warning', companyPayloadError);
            return;
        }

        var payload = {
            name: [ownerPayload.first_name, ownerPayload.last_name].filter(Boolean).join(' ').trim(),
            email: ownerPayload.email,
            phone: ownerPayload.phone || null,
            companyName: normalize(raw.name) || null,
            companyLegalName: normalize(raw.legal_name) || null,
            companyAddress: normalize(raw.address) || null,
            companyCity: normalize(raw.city) || null,
            companyState: normalize(raw.state) || null,
            companyCountry: normalize(raw.country) || null,
            companyPostalCode: normalize(raw.postal_code) || null,
            companyNpwp: normalize(raw.npwp) || null
        };

        if (payload.companyNpwp) {
            payload.companyNpwp = normalizeNpwp(payload.companyNpwp);
        }

        try {
            setLoading(true);

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
            if (body.data) {
                applyOwnerData(body.data);
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
    if (ownerPhotoInputNode) {
        ownerPhotoInputNode.addEventListener('change', function () {
            var file = ownerPhotoInputNode.files && ownerPhotoInputNode.files[0];
            if (file) {
                uploadPhoto(file);
                ownerPhotoInputNode.value = '';
            }
        });
    }
    if (ownerPhotoRemoveButton) {
        ownerPhotoRemoveButton.addEventListener('click', function () {
            removePhoto();
        });
    }

    var npwpInput = document.querySelector('[data-company-field="npwp"]');
    if (npwpInput) {
        npwpInput.addEventListener('input', function () {
            var raw = npwpInput.value.replace(/\D/g, '').substring(0, 16);
            npwpInput.value = formatNpwpDisplay(raw);
        });
    }

    loadCompanyProfile().catch(function (err) {
        showFeedback('danger', err && err.message ? err.message : 'Gagal memuat data company profile.');
    });
})();
