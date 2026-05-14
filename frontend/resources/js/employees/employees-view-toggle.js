(function (window, document) {
    "use strict";

    var VIEW_KEY = "employees_view_mode";
    var LIST_PATH = "/employees";
    var GRID_PATH = "/employees-grid";
    var currentView = "list";
    var isLoading = false;

    function setActive(buttons, view) {
        buttons.forEach(function (btn) {
            var isActive = btn.dataset.view === view;
            btn.classList.toggle("active", isActive);
            btn.classList.toggle("bg-primary", isActive);
            btn.classList.toggle("text-white", isActive);
        });
    }

    function getViewButtons() {
        return Array.prototype.slice.call(document.querySelectorAll("[data-employees-view-toggle] [data-view]"));
    }

    function normalizeButtons(view) {
        var buttons = getViewButtons();
        buttons.forEach(function (btn) {
            var nextView = btn.dataset.view === "grid" ? "grid" : "list";
            var target = new URL(window.location.origin + LIST_PATH);
            var currentParams = new URL(window.location.href).searchParams;
            currentParams.forEach(function (value, key) {
                if (key !== "view") {
                    target.searchParams.set(key, value);
                }
            });
            target.searchParams.set("view", nextView);
            btn.setAttribute("href", target.toString());
        });
        setActive(buttons, view);
    }

    function updateUrl(view) {
        var target = new URL(window.location.origin + LIST_PATH);
        var currentParams = new URL(window.location.href).searchParams;
        currentParams.forEach(function (value, key) {
            if (key !== "view") {
                target.searchParams.set(key, value);
            }
        });
        target.searchParams.set("view", view);
        window.history.replaceState({}, "", target.toString());
    }

    function swapContentFrom(url) {
        return fetch(url, { headers: { "X-Requested-With": "XMLHttpRequest" } })
            .then(function (response) {
                if (!response.ok) {
                    throw new Error("Failed loading employees view.");
                }
                return response.text();
            })
            .then(function (html) {
                var parser = new DOMParser();
                var nextDoc = parser.parseFromString(html, "text/html");
                var nextContent = nextDoc.querySelector(".page-wrapper .content");
                var currentContent = document.querySelector(".page-wrapper .content");

                if (!nextContent || !currentContent) {
                    throw new Error("Unable to swap employees content.");
                }

                // Avoid flashing static template rows/cards before API hydration.
                var nextListBody = nextContent.querySelector("[data-employees-list-body]");
                if (nextListBody) {
                    nextListBody.innerHTML = '<tr><td class="text-center text-muted py-4">Loading employees...</td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td></tr>';
                }
                var nextGridBody = nextContent.querySelector("[data-employees-grid-body]");
                if (nextGridBody) {
                    nextGridBody.innerHTML = '<div class="col-12"><div class="alert alert-light text-center mb-0">Loading employees...</div></div>';
                }

                currentContent.innerHTML = nextContent.innerHTML;
            });
    }

    function setView(view) {
        if (isLoading || view === currentView) {
            return;
        }

        isLoading = true;
        var sourcePath = view === "grid" ? GRID_PATH : LIST_PATH;
        swapContentFrom(sourcePath)
            .then(function () {
                currentView = view;
                window.localStorage.setItem(VIEW_KEY, currentView);
                updateUrl(currentView);
                normalizeButtons(currentView);
                        document.dispatchEvent(new CustomEvent("employees:view-swapped"));
            })
            .catch(function (error) {
                console.error(error);
            })
            .finally(function () {
                isLoading = false;
            });
    }

    function init() {
        var hasToggle = !!document.querySelector("[data-employees-view-toggle]");
        if (!hasToggle) {
            return;
        }

        var url = new URL(window.location.href);
        var pathname = url.pathname;
        currentView = "list";

        if (pathname.indexOf(GRID_PATH) === 0) {
            currentView = "grid";
            updateUrl("grid");
        } else {
            updateUrl("list");
            window.localStorage.setItem(VIEW_KEY, "list");
        }

        normalizeButtons(currentView);

        document.addEventListener("click", function (event) {
            var button = event.target.closest("[data-employees-view-toggle] [data-view]");
            if (!button) {
                return;
            }

            event.preventDefault();
            var nextView = button.dataset.view === "grid" ? "grid" : "list";
            setView(nextView);
        });

        if (pathname.indexOf(LIST_PATH) === 0) {
            setActive(getViewButtons(), "list");
        }
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", init);
    } else {
        init();
    }
})(window, document);
