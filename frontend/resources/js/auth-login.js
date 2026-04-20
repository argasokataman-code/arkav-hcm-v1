(function (window, document) {
    "use strict";

    function safeRedirect(path) {
        window.__ARCAV_LAST_REDIRECT__ = path;
        if (window.__ARCAV_DISABLE_REDIRECTS__ === true) {
            return;
        }

        try {
            window.location.replace(path);
        } catch (_e) {
            try {
                window.location.href = path;
            } catch (__e) {}
        }
    }

    function init() {
        var form = document.getElementById("api-login-form");
        if (!form || !window.AuthApi || form.dataset.authBound === "1") {
            return;
        }

        form.dataset.authBound = "1";

        var emailInput = document.getElementById("login-email");
        var passwordInput = document.getElementById("login-password");
        var rememberMeInput = document.getElementById("remember_me");
        var regularModeInput = document.getElementById("login_mode_regular");
        var companyModeInput = document.getElementById("login_mode_company");
        var companyCodeWrapper = document.getElementById("company-code-wrapper");
        var companyCodeInput = document.getElementById("login-company-code");
        var errorNode = document.getElementById("login-error");
        var submitButton = document.getElementById("login-submit");

        function setError(message) {
            errorNode.textContent = message || "";
            errorNode.classList.toggle("d-none", !message);
        }

        function isCompanyMode() {
            return !!(companyModeInput && companyModeInput.checked);
        }

        function syncModeUi() {
            if (!companyCodeWrapper) {
                return;
            }
            var companyModeActive = isCompanyMode();
            companyCodeWrapper.classList.toggle("d-none", !companyModeActive);
            if (companyCodeInput) {
                companyCodeInput.required = companyModeActive;
            }
        }

        if (regularModeInput) {
            regularModeInput.addEventListener("change", syncModeUi);
        }
        if (companyModeInput) {
            companyModeInput.addEventListener("change", syncModeUi);
        }
        syncModeUi();

        form.addEventListener("submit", async function (event) {
            event.preventDefault();
            setError("");
            submitButton.disabled = true;

            try {
                var companyModeActive = isCompanyMode();
                var companyCode = companyModeActive && companyCodeInput
                    ? companyCodeInput.value.trim()
                    : "";

                if (window.AuthApi && typeof window.AuthApi.clearTenantContext === "function") {
                    window.AuthApi.clearTenantContext();
                }

                if (companyModeActive && !companyCode) {
                    throw new Error("Company code wajib diisi untuk Login Company.");
                }

                var response = await window.AuthApi.login({
                    email: emailInput.value.trim(),
                    password: passwordInput.value,
                    rememberMe: !!(rememberMeInput && rememberMeInput.checked),
                    companyCode: companyModeActive ? companyCode : undefined,
                });
                var payload = response && response.data ? response.data : null;
                var loginData = payload && payload.data ? payload.data : null;

                if (!payload || payload.success !== true || !loginData) {
                    throw new Error("Login failed.");
                }

                if (companyModeActive) {
                    var activeCompany = loginData.activeCompany || null;
                    if (!activeCompany || !activeCompany.code || !activeCompany.id) {
                        throw new Error("Login company berhasil tetapi context tenant tidak valid.");
                    }
                    window.AuthApi.setTenantContext({
                        companyCode: activeCompany.code,
                        companyId: activeCompany.id,
                        companyUuid: activeCompany.uuid ? activeCompany.uuid : undefined,
                    });
                }

                safeRedirect("/index");
            } catch (error) {
                var apiMessage = error?.response?.data?.error?.message;
                setError(apiMessage || error.message || "Login gagal. Periksa email/password.");
            } finally {
                submitButton.disabled = false;
            }
        });
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", init);
    } else {
        init();
    }
})(window, document);
