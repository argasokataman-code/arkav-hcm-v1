(function (window, document) {
    "use strict";

    function init() {
        var root = document.getElementById("auth-guard-root");
        if (!root || !window.AuthApi) {
            return;
        }

        var welcomeNode = document.getElementById("welcome-user-name");

        async function bootstrap() {
            try {
                var response = await window.AuthApi.me();
                var user = response?.data?.data;
                if (welcomeNode && user?.name) {
                    welcomeNode.textContent = user.name;
                }
                root.classList.remove("d-none");
            } catch (error) {
                var status = error.response && error.response.status;
                var data = error.response && error.response.data;
                if (window.AuthApi.handleUnauthorizedFromApi(status, data)) {
                    return;
                }
                window.AuthApi.redirectToLoginAfterAuthFailure();
            }
        }

        bootstrap();
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", init);
    } else {
        init();
    }
})(window, document);
