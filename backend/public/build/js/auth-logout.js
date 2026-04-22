(function (window, document) {
    "use strict";

    function bindLogoutAction() {
        if (!window.AuthApi) {
            return;
        }

        var items = document.querySelectorAll("[data-auth-logout]");
        items.forEach(function (item) {
            if (item.dataset.logoutBound === "1") {
                return;
            }

            item.dataset.logoutBound = "1";
            item.addEventListener("click", async function (event) {
                event.preventDefault();

                try {
                    await window.AuthApi.logout();
                } catch (error) {
                    // Ignore API logout errors and continue local cleanup.
                } finally {
                    window.AuthApi.clearToken();
                    window.AuthApi.clearTenantContext();
                    window.location.href = "/login";
                }
            });
        });
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", bindLogoutAction);
    } else {
        bindLogoutAction();
    }
})(window, document);
