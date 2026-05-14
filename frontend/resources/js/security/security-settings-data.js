(function () {
    'use strict';

    var saveBtn = document.querySelector('[data-security-save-password]');
    if (!saveBtn) {
        return;
    }

    var currentPasswordField = document.querySelector('[data-security-current-password]');
    var newPasswordField = document.querySelector('[data-security-new-password]');
    var confirmPasswordField = document.querySelector('[data-security-confirm-password]');
    var feedbackEl = document.querySelector('[data-security-password-feedback]');
    var collapseEl = document.querySelector('#changePasswordForm');

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
            return window.localStorage.getItem(
                (window.AuthApi && window.AuthApi.tokenKey) || 'arcav_access_token'
            );
        } catch (_e) {
            return null;
        }
    }

    function buildHeaders() {
        var headers = {
            'Accept': 'application/json',
            'Content-Type': 'application/json',
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
        return headers;
    }

    function showFeedback(message, type) {
        if (!feedbackEl) return;
        feedbackEl.classList.remove('d-none', 'alert-success', 'alert-danger', 'alert-warning');
        feedbackEl.classList.add('alert-' + (type || 'danger'));
        feedbackEl.textContent = message;
    }

    function hideFeedback() {
        if (!feedbackEl) return;
        feedbackEl.classList.add('d-none');
        feedbackEl.classList.remove('alert-success', 'alert-danger', 'alert-warning');
        feedbackEl.textContent = '';
    }

    function clearFields() {
        if (currentPasswordField) currentPasswordField.value = '';
        if (newPasswordField) newPasswordField.value = '';
        if (confirmPasswordField) confirmPasswordField.value = '';
    }

    function collapseForm() {
        if (!collapseEl) return;
        try {
            var bsCollapse = window.bootstrap && window.bootstrap.Collapse.getInstance(collapseEl);
            if (bsCollapse) {
                bsCollapse.hide();
            } else {
                collapseEl.classList.remove('show');
            }
        } catch (_e) {
            collapseEl.classList.remove('show');
        }
    }

    saveBtn.addEventListener('click', function () {
        hideFeedback();

        var currentPassword = currentPasswordField ? currentPasswordField.value : '';
        var newPassword = newPasswordField ? newPasswordField.value : '';
        var confirmPassword = confirmPasswordField ? confirmPasswordField.value : '';

        if (!currentPassword || !newPassword || !confirmPassword) {
            showFeedback('Please fill in all password fields.', 'warning');
            return;
        }

        if (newPassword !== confirmPassword) {
            showFeedback('New password and confirmation do not match.', 'warning');
            return;
        }

        saveBtn.disabled = true;

        fetch('/v1/identity/auth/change-password', {
            method: 'POST',
            headers: buildHeaders(),
            body: JSON.stringify({
                currentPassword: currentPassword,
                newPassword: newPassword,
                confirmPassword: confirmPassword
            })
        })
            .then(function (res) {
                return res.json().then(function (data) {
                    return { status: res.status, data: data };
                });
            })
            .then(function (result) {
                if (result.status === 200 && result.data && result.data.success) {
                    clearFields();
                    collapseForm();
                    showFeedback('Password changed successfully.', 'success');
                } else {
                    var msg = (result.data && result.data.error && result.data.error.message)
                        ? result.data.error.message
                        : 'Failed to change password.';
                    showFeedback(msg, 'danger');
                }
            })
            .catch(function () {
                showFeedback('Network error. Please try again.', 'danger');
            })
            .finally(function () {
                saveBtn.disabled = false;
            });
    });
})();
