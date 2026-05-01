(function (window, document) {
    "use strict";

    var INPUT_SELECTOR = "[data-hcm-global-search-input]";
    var DEBOUNCE_MS = 220;
    var QUICK_LIMIT = 8;
    var FULL_LIMIT = 30;

    var activeInput = null;
    var quickTimer = null;

    function getAuthHeaders() {
        var headers = { Accept: "application/json" };
        var token = window.AuthApi && typeof window.AuthApi.getToken === "function" ? window.AuthApi.getToken() : "";
        var tenant = window.AuthApi && typeof window.AuthApi.getTenantContext === "function" ? window.AuthApi.getTenantContext() : null;

        if (token) {
            headers.Authorization = "Bearer " + token;
        }
        if (tenant && tenant.companyCode) {
            headers["X-Company-Code"] = String(tenant.companyCode);
        }
        if (tenant && tenant.companyId !== undefined && tenant.companyId !== null && tenant.companyId !== "") {
            headers["X-Company-Id"] = String(tenant.companyId);
        }
        if (tenant && tenant.companyUuid) {
            headers["X-Company-UUID"] = String(tenant.companyUuid);
        }

        return headers;
    }

    function esc(value) {
        return String(value || "")
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/\"/g, "&quot;")
            .replace(/'/g, "&#39;");
    }

    function firstVisibleSearchInput() {
        var inputs = document.querySelectorAll(INPUT_SELECTOR);
        for (var i = 0; i < inputs.length; i += 1) {
            var input = inputs[i];
            if (!input || input.disabled) {
                continue;
            }
            var rect = input.getBoundingClientRect();
            if (rect.width > 0 && rect.height > 0) {
                return input;
            }
        }

        return null;
    }

    function buildQuickBox() {
        var box = document.createElement("div");
        box.setAttribute("data-hcm-global-search-quick", "1");
        box.style.position = "fixed";
        box.style.zIndex = "1085";
        box.style.minWidth = "320px";
        box.style.maxWidth = "520px";
        box.style.maxHeight = "420px";
        box.style.overflowY = "auto";
        box.style.background = "#fff";
        box.style.border = "1px solid #dee2e6";
        box.style.borderRadius = "10px";
        box.style.boxShadow = "0 14px 34px rgba(18, 38, 63, 0.16)";
        box.style.display = "none";
        box.style.padding = "0";
        document.body.appendChild(box);

        return box;
    }

    function buildFullPanel() {
        var wrap = document.createElement("div");
        wrap.setAttribute("data-hcm-global-search-panel", "1");
        wrap.style.position = "fixed";
        wrap.style.left = "0";
        wrap.style.top = "0";
        wrap.style.width = "100%";
        wrap.style.height = "100%";
        wrap.style.background = "rgba(17, 24, 39, 0.45)";
        wrap.style.zIndex = "1090";
        wrap.style.display = "none";
        wrap.innerHTML = ""
            + '<div style="width:min(840px,92vw);max-height:82vh;overflow:auto;background:#fff;border-radius:14px;margin:7vh auto;padding:0;box-shadow:0 24px 60px rgba(0,0,0,.24);">'
            + '  <div style="position:sticky;top:0;background:#fff;border-bottom:1px solid #e9ecef;padding:14px 16px;display:flex;align-items:center;justify-content:space-between;gap:12px;">'
            + '    <div>'
            + '      <div style="font-weight:700;font-size:15px;line-height:1.2;">HRMS Search Results</div>'
            + '      <div data-hcm-global-search-panel-subtitle style="font-size:12px;color:#6c757d;margin-top:4px;"></div>'
            + '    </div>'
            + '    <button type="button" data-hcm-global-search-panel-close class="btn btn-sm btn-outline-secondary">Close</button>'
            + '  </div>'
            + '  <div data-hcm-global-search-panel-body style="padding:14px 16px;"></div>'
            + '</div>';

        document.body.appendChild(wrap);

        wrap.addEventListener("click", function (event) {
            if (event.target === wrap) {
                hideFullPanel();
            }
        });

        var close = wrap.querySelector("[data-hcm-global-search-panel-close]");
        if (close) {
            close.addEventListener("click", hideFullPanel);
        }

        return wrap;
    }

    var quickBox = buildQuickBox();
    var fullPanel = buildFullPanel();

    function hideQuickBox() {
        quickBox.style.display = "none";
        quickBox.innerHTML = "";
    }

    function placeQuickBox(input) {
        var rect = input.getBoundingClientRect();
        quickBox.style.left = Math.max(12, rect.left) + "px";
        quickBox.style.top = (rect.bottom + 6) + "px";
        quickBox.style.width = Math.max(320, Math.min(520, rect.width + 180)) + "px";
    }

    function hideFullPanel() {
        fullPanel.style.display = "none";
        document.body.style.overflow = "";
    }

    function showFullPanel(query, items) {
        var subtitle = fullPanel.querySelector("[data-hcm-global-search-panel-subtitle]");
        var body = fullPanel.querySelector("[data-hcm-global-search-panel-body]");

        if (subtitle) {
            subtitle.textContent = 'Query: "' + query + '" • ' + items.length + " item(s)";
        }

        if (!items.length) {
            body.innerHTML = '<div class="text-muted">No matching page found.</div>';
        } else {
            var grouped = {};
            for (var i = 0; i < items.length; i += 1) {
                var section = String(items[i].section || "Other");
                if (!grouped[section]) {
                    grouped[section] = [];
                }
                grouped[section].push(items[i]);
            }

            var html = "";
            Object.keys(grouped).forEach(function (section) {
                html += '<div style="margin-bottom:16px;">';
                html += '  <div style="font-size:12px;font-weight:700;color:#6c757d;text-transform:uppercase;letter-spacing:.04em;margin-bottom:8px;">' + esc(section) + "</div>";
                html += '  <div class="list-group">';
                grouped[section].forEach(function (item) {
                    html += ''
                        + '<a href="' + esc(item.href) + '" class="list-group-item list-group-item-action" style="padding:10px 12px;">'
                        + '  <div style="display:flex;justify-content:space-between;gap:8px;align-items:flex-start;">'
                        + '    <span style="font-weight:600;color:#111827;">' + esc(item.label) + '</span>'
                        + '    <code style="font-size:11px;color:#6b7280;">' + esc(item.path) + '</code>'
                        + "  </div>"
                        + (item.description ? '<div style="margin-top:4px;font-size:12px;color:#6c757d;">' + esc(item.description) + "</div>" : "")
                        + "</a>";
                });
                html += "  </div>";
                html += "</div>";
            });
            body.innerHTML = html;
        }

        fullPanel.style.display = "block";
        document.body.style.overflow = "hidden";
    }

    function apiSearch(query, limit) {
        var url = "/v1/hcm/search?q=" + encodeURIComponent(query) + "&limit=" + encodeURIComponent(String(limit));

        return fetch(url, {
            method: "GET",
            credentials: "same-origin",
            headers: getAuthHeaders(),
        }).then(function (response) {
            return response.json().catch(function () {
                return {};
            }).then(function (payload) {
                if (!response.ok || !payload || payload.success !== true) {
                    throw new Error("search_failed");
                }

                var items = payload.data && Array.isArray(payload.data.items) ? payload.data.items : [];
                return items;
            });
        });
    }

    function renderQuickResults(query, items) {
        if (!activeInput) {
            hideQuickBox();
            return;
        }

        placeQuickBox(activeInput);

        if (!items.length) {
            quickBox.innerHTML = '<div style="padding:11px 12px;font-size:13px;color:#6c757d;">No result for "' + esc(query) + '"</div>';
            quickBox.style.display = "block";
            return;
        }

        var html = '<div style="padding:8px 0;">';
        for (var i = 0; i < items.length; i += 1) {
            var item = items[i];
            html += ''
                + '<a href="' + esc(item.href) + '" style="display:block;padding:9px 12px;text-decoration:none;border-bottom:1px solid #f1f3f5;">'
                + '  <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:8px;">'
                + '    <span style="font-size:13px;font-weight:600;color:#111827;">' + esc(item.label) + '</span>'
                + '    <code style="font-size:11px;color:#6b7280;">' + esc(item.path) + '</code>'
                + "  </div>"
                + (item.description ? '<div style="margin-top:3px;font-size:12px;color:#6c757d;">' + esc(item.description) + "</div>" : "")
                + "</a>";
        }
        html += ''
            + '<button type="button" data-hcm-global-search-more style="width:100%;padding:10px 12px;border:0;background:#f8f9fa;font-size:12px;font-weight:600;color:#0d6efd;text-align:left;">'
            + 'Press Enter to open full results'
            + "</button>";
        html += "</div>";

        quickBox.innerHTML = html;
        quickBox.style.display = "block";

        var more = quickBox.querySelector("[data-hcm-global-search-more]");
        if (more) {
            more.addEventListener("click", function () {
                openFullResults(query);
            });
        }
    }

    function runQuickSearch() {
        if (!activeInput) {
            hideQuickBox();
            return;
        }

        var query = String(activeInput.value || "").trim();
        if (query.length < 2) {
            hideQuickBox();
            return;
        }

        apiSearch(query, QUICK_LIMIT)
            .then(function (items) {
                if (!activeInput) {
                    return;
                }
                renderQuickResults(query, items);
            })
            .catch(function () {
                hideQuickBox();
            });
    }

    function openFullResults(query) {
        apiSearch(query, FULL_LIMIT)
            .then(function (items) {
                showFullPanel(query, items);
            })
            .catch(function () {
                showFullPanel(query, []);
            });
    }

    function onInputChanged() {
        if (quickTimer) {
            window.clearTimeout(quickTimer);
        }
        quickTimer = window.setTimeout(runQuickSearch, DEBOUNCE_MS);
    }

    function bindInput(input) {
        input.addEventListener("focus", function () {
            activeInput = input;
            onInputChanged();
        });

        input.addEventListener("input", function () {
            activeInput = input;
            onInputChanged();
        });

        input.addEventListener("keydown", function (event) {
            if (event.key === "Escape") {
                hideQuickBox();
                return;
            }

            if (event.key === "Enter") {
                var query = String(input.value || "").trim();
                if (query.length >= 2) {
                    event.preventDefault();
                    openFullResults(query);
                }
            }
        });
    }

    function bindGlobalShortcut() {
        document.addEventListener("keydown", function (event) {
            var isSlash = event.key === "/";
            if (!isSlash) {
                return;
            }

            if (!(event.ctrlKey || event.metaKey)) {
                return;
            }

            var tag = event.target && event.target.tagName ? String(event.target.tagName).toLowerCase() : "";
            if (tag === "input" || tag === "textarea") {
                return;
            }

            event.preventDefault();
            var input = firstVisibleSearchInput();
            if (!input) {
                return;
            }

            input.focus();
            activeInput = input;
        });

        document.addEventListener("click", function (event) {
            if (!quickBox.contains(event.target) && (!activeInput || event.target !== activeInput)) {
                hideQuickBox();
            }
        });

        document.addEventListener("keydown", function (event) {
            if (event.key === "Escape") {
                hideQuickBox();
                hideFullPanel();
            }
        });

        window.addEventListener("resize", function () {
            if (activeInput && quickBox.style.display !== "none") {
                placeQuickBox(activeInput);
            }
        });

        window.addEventListener("scroll", function () {
            if (activeInput && quickBox.style.display !== "none") {
                placeQuickBox(activeInput);
            }
        }, true);
    }

    function init() {
        var inputs = document.querySelectorAll(INPUT_SELECTOR);
        if (!inputs.length) {
            return;
        }

        for (var i = 0; i < inputs.length; i += 1) {
            bindInput(inputs[i]);
        }
        bindGlobalShortcut();
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", init);
    } else {
        init();
    }
})(window, document);
