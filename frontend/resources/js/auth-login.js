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
        var errorNode = document.getElementById("login-error");
        var submitButton = document.getElementById("login-submit");

        function setError(message) {
            errorNode.textContent = message || "";
            errorNode.classList.toggle("d-none", !message);
        }

        form.addEventListener("submit", async function (event) {
            event.preventDefault();
            setError("");
            submitButton.disabled = true;

            try {
                var response = await window.AuthApi.login({
                    email: emailInput.value.trim(),
                    password: passwordInput.value,
                    rememberMe: !!(rememberMeInput && rememberMeInput.checked),
                });
                if (!response || !response.data || response.data.success !== true) {
                    throw new Error("Login failed.");
                }
                window.location.href = "/index";
            } catch (error) {
                var apiMessage = error?.response?.data?.error?.message;
                setError(apiMessage || "Login gagal. Periksa email/password.");
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
