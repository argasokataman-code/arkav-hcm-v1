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

    function searchParams() {
        try {
            return new URLSearchParams(window.location.search || "");
        } catch (_e) {
            return new URLSearchParams();
        }
    }

    function sanitizeNextRedirect(rawPath) {
        var value = String(rawPath || "").trim();
        if (!value || value.charAt(0) !== "/" || value.slice(0, 2) === "//") {
            return "/index";
        }
        if (value === "/login" || value.indexOf("/login?") === 0) {
            return "/index";
        }

        return value;
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
        var params = searchParams();

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

        var companyCodeFromQuery = String(params.get("companyCode") || "").trim();
        if (companyCodeInput && companyCodeFromQuery) {
            companyCodeInput.value = companyCodeFromQuery;
        }

        if (companyModeInput && regularModeInput) {
            var requestedMode = String(params.get("mode") || "").trim().toLowerCase();
            if (requestedMode === "company" || companyCodeFromQuery) {
                regularModeInput.checked = false;
                companyModeInput.checked = true;
            }
        }
        syncModeUi();

        form.addEventListener("submit", async function (event) {
            event.preventDefault();
            if (!ArcavValidation.validateForm(form)) { return; }
            console.log('Form submission initiated');
            setError("");
            submitButton.disabled = true;

            try {
                var companyModeActive = isCompanyMode();
                var companyCode = companyModeActive && companyCodeInput
                    ? companyCodeInput.value.trim()
                    : "";

                console.log('Company mode:', companyModeActive, 'Code:', companyCode);

                if (window.AuthApi && typeof window.AuthApi.clearTenantContext === "function") {
                    window.AuthApi.clearTenantContext();
                }
                // Clear any stale token from a previous session so that after login,
                // /api-token probe uses the fresh HttpOnly cookie instead of a revoked Bearer.
                if (window.AuthApi && typeof window.AuthApi.clearToken === "function") {
                    window.AuthApi.clearToken();
                }

                if (companyModeActive && !companyCode) {
                    throw new Error("Company code wajib diisi untuk Login Company.");
                }

                console.log('About to call AuthApi.login');
                var response = await window.AuthApi.login({
                    email: emailInput.value.trim(),
                    password: passwordInput.value,
                    rememberMe: !!(rememberMeInput && rememberMeInput.checked),
                    companyCode: companyModeActive ? companyCode : undefined,
                });
                console.log('AuthApi.login response:', response);
                var payload = response && response.data ? response.data : null;
                var loginData = payload && payload.data ? payload.data : null;

                if (!payload || payload.success !== true || !loginData) {
                    throw new Error("Login failed.");
                }

                // Token is delivered via HttpOnly cookie by the server.
                // Do NOT store in sessionStorage here — the /api-token bridge on each
                // authenticated page will replenish it after page load, keeping it
                // out of browser storage at login time (reduces XSS token-theft surface).

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

                // "Login Employee" mode has no company context → always go to employee dashboard.
                // "Login Company" mode → go to index (admin dashboard) or `next` param.
                var defaultAfterLogin = companyModeActive ? "/index" : "/employee-dashboard";
                console.log('Redirecting to:', params.get("next") ? sanitizeNextRedirect(params.get("next")) : defaultAfterLogin);
                safeRedirect(params.get("next") ? sanitizeNextRedirect(params.get("next")) : defaultAfterLogin);
            } catch (error) {
                console.error('Login error:', error);
                var apiMessage = error?.response?.data?.error?.message;
                setError(apiMessage || error.message || "Login gagal. Periksa email/password.");
            } finally {
                console.log('Form submission finally block');
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
