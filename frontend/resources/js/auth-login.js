(function (window, document) {
    "use strict";

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

                if (companyModeActive && !companyCode) {
                    throw new Error("Company code wajib diisi untuk Login as Company.");
                }

                var response = await window.AuthApi.login({
                    email: emailInput.value.trim(),
                    password: passwordInput.value,
                    rememberMe: !!(rememberMeInput && rememberMeInput.checked),
                    companyCode: companyModeActive ? companyCode : undefined,
                });
                if (!response || !response.data || response.data.success !== true) {
                    throw new Error("Login failed.");
                }

                if (companyModeActive) {
                    var activeCompany = response && response.data ? response.data.activeCompany : null;
                    window.AuthApi.setTenantContext({
                        companyCode: companyCode,
                        companyId: activeCompany && activeCompany.id ? activeCompany.id : undefined,
                        companyUuid: activeCompany && activeCompany.uuid ? activeCompany.uuid : undefined,
                    });
                } else {
                    window.AuthApi.clearTenantContext();
                }

                window.location.href = "/index";
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
