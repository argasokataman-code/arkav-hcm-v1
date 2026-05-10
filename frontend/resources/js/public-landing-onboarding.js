(function (window, document) {
    "use strict";

    function qs(sel, root) {
        return (root || document).querySelector(sel);
    }

    function qsa(sel, root) {
        return Array.prototype.slice.call((root || document).querySelectorAll(sel));
    }

    function setText(el, text) {
        if (!el) return;
        el.textContent = String(text == null ? "" : text);
    }

    function show(el) {
        if (!el) return;
        el.classList.remove("d-none");
    }

    function hide(el) {
        if (!el) return;
        el.classList.add("d-none");
    }

    function normalizeStartMode(value) {
        var normalized = String(value == null ? "" : value).trim().toLowerCase();
        normalized = normalized.replace(/[\s-]+/g, "_");

        if (!normalized || normalized === "trial") {
            return "trial";
        }

        if (
            normalized === "pending_payment" ||
            normalized === "pendingpayment" ||
            normalized === "paid" ||
            normalized === "subscribe" ||
            normalized === "subscription"
        ) {
            return "pending_payment";
        }

        return "trial";
    }

    function sanitizePostalCode(value) {
        return String(value == null ? "" : value)
            .replace(/\D+/g, "")
            .slice(0, 12);
    }

    function ensureTurnstileInput(form) {
        if (!form) return null;

        var input = form.querySelector("input[name='cf-turnstile-response']");
        if (input) {
            return input;
        }

        input = document.createElement("input");
        input.type = "hidden";
        input.name = "cf-turnstile-response";
        form.appendChild(input);
        return input;
    }

    function syncTurnstileToken(form) {
        var hiddenInput = ensureTurnstileInput(form);
        if (!hiddenInput) return "";

        var container = qs("[data-turnstile-container]", form);
        if (!container) {
            return String(hiddenInput.value || "").trim();
        }

        var widgetId = container.getAttribute("data-turnstile-widget-id");
        if (widgetId && window.turnstile && typeof window.turnstile.getResponse === "function") {
            try {
                hiddenInput.value = window.turnstile.getResponse(widgetId) || "";
            } catch (_e) {}
        }

        return String(hiddenInput.value || "").trim();
    }

    function renderTurnstileWidgets(root) {
        if (!root || !window.turnstile || typeof window.turnstile.render !== "function") {
            return;
        }

        qsa("[data-turnstile-container]", root).forEach(function (container) {
            var siteKey = String(container.getAttribute("data-sitekey") || "").trim();
            if (!siteKey) return;

            var form = container.closest("form");
            var hiddenInput = ensureTurnstileInput(form);
            var widgetId = container.getAttribute("data-turnstile-widget-id");
            if (widgetId) {
                if (hiddenInput) {
                    hiddenInput.value = "";
                }
                try {
                    window.turnstile.reset(widgetId);
                } catch (_e) {}
                return;
            }

            try {
                var renderedId = window.turnstile.render(container, {
                    sitekey: siteKey,
                    callback: function (token) {
                        if (hiddenInput) {
                            hiddenInput.value = token || "";
                        }
                    },
                    "expired-callback": function () {
                        if (hiddenInput) {
                            hiddenInput.value = "";
                        }
                    },
                    "error-callback": function () {
                        if (hiddenInput) {
                            hiddenInput.value = "";
                        }
                    },
                });
                container.setAttribute("data-turnstile-widget-id", String(renderedId));
            } catch (_e) {}
        });
    }

    function removeTurnstileWidgets(root) {
        if (!root || !window.turnstile || typeof window.turnstile.remove !== "function") {
            return;
        }

        qsa("[data-turnstile-container]", root).forEach(function (container) {
            var widgetId = container.getAttribute("data-turnstile-widget-id");
            if (!widgetId) {
                container.innerHTML = "";
                return;
            }

            try {
                window.turnstile.remove(widgetId);
            } catch (_e) {
                container.innerHTML = "";
            }

            container.removeAttribute("data-turnstile-widget-id");

            var form = container.closest("form");
            var hiddenInput = ensureTurnstileInput(form);
            if (hiddenInput) {
                hiddenInput.value = "";
            }
        });
    }

    function whenTurnstileReady(callback, attempt) {
        if (window.turnstile && typeof window.turnstile.render === "function") {
            callback();
            return;
        }

        if ((attempt || 0) >= 40) {
            return;
        }

        window.setTimeout(function () {
            whenTurnstileReady(callback, (attempt || 0) + 1);
        }, 250);
    }

    function showInlineError(errorBox, message) {
        if (!errorBox) {
            window.alert(message);
            return;
        }

        setText(errorBox, message);
        show(errorBox);
    }

    function formatIdr(amount) {
        var numericAmount = Number(amount || 0);
        try {
            return new Intl.NumberFormat("id-ID", {
                style: "currency",
                currency: "IDR",
                maximumFractionDigits: 0,
            }).format(numericAmount);
        } catch (_e) {
            return "Rp " + String(Math.round(numericAmount || 0));
        }
    }

    function buildPendingPaymentLoginUrl(companyCode) {
        var params = new URLSearchParams();
        params.set("mode", "company");
        params.set("next", "/subscription");
        if (companyCode) {
            params.set("companyCode", String(companyCode));
        }
        return "/login?" + params.toString();
    }

    function formatApiError(err) {
        try {
            if (window.ApiErrorHelper && typeof window.ApiErrorHelper.format === "function") {
                return window.ApiErrorHelper.format(err);
            }
        } catch (_e) {}

        var data = err && err.response && err.response.data ? err.response.data : null;
        if (data && data.error && data.error.message) {
            return String(data.error.message);
        }
        return "Terjadi kesalahan. Coba lagi.";
    }

    function buildInvoiceBreakdownMessage(invoice) {
        if (!invoice) return "";

        var breakdown = invoice.pricingBreakdown && typeof invoice.pricingBreakdown === "object"
            ? invoice.pricingBreakdown
            : null;

        if (!breakdown) {
            return invoice.amountDue != null ? "Total tagihan: " + formatIdr(invoice.amountDue) : "";
        }

        var lines = [];
        var baseAmount = Number(breakdown.base_amount || 0);
        if (Number.isFinite(baseAmount) && baseAmount > 0) {
            lines.push("Harga paket: " + formatIdr(baseAmount));
        }

        var components = Array.isArray(breakdown.components) ? breakdown.components : [];
        if (components.length > 0) {
            components.forEach(function (component) {
                var label = String(component && component.label ? component.label : "Komponen");
                var rate = Number(component && component.rate ? component.rate : 0);
                var amount = Number(component && component.amount ? component.amount : 0);
                if (Number.isFinite(amount)) {
                    lines.push(label + " " + rate + "%: " + formatIdr(amount));
                }
            });
        } else {
            var taxRate = Number(breakdown.subscription_tax_rate || 0);
            var taxAmount = Number(breakdown.subscription_tax_amount || 0);
            if (Number.isFinite(taxAmount) && (taxAmount > 0 || taxRate > 0)) {
                lines.push("Pajak " + taxRate + "%: " + formatIdr(taxAmount));
            }
        }

        var totalAmount = Number(breakdown.total_amount || invoice.amountDue || 0);
        if (Number.isFinite(totalAmount)) {
            lines.push("Total tagihan: " + formatIdr(totalAmount));
        }

        return lines.join("\n");
    }

    function formatValidationErrors(err) {
        var data = err && err.response && err.response.data ? err.response.data : null;
        if (!data || !data.error) {
            return { title: "Error", message: "Terjadi kesalahan. Coba lagi.", details: [] };
        }

        var title = "Error";
        var message = data.error.message || "Validasi gagal";
        var details = [];

        // Check if there are validation details
        if (data.error.details && Array.isArray(data.error.details)) {
            details = data.error.details;
            title = "Validasi Error";
        }

        return { title: title, message: message, details: details };
    }

    function showErrorModal(err) {
            var errorInfo = formatValidationErrors(err);
            // Use Bootstrap modal for all errors
            var modalEl = qs('#onboardingErrorModal');
            var msgEl = qs('#onboardingErrorModalMsg');
            var listEl = qs('#onboardingErrorModalList');
            if (modalEl && window.bootstrap && window.bootstrap.Modal) {
                // Set modal title
                var titleEl = qs('#onboardingErrorModalLabel');
                if (titleEl) titleEl.textContent = errorInfo.title || 'Terjadi Kesalahan';
                // Set main message
                if (msgEl) msgEl.innerHTML = errorInfo.message || 'Terjadi kesalahan. Coba lagi.';
                // Set error details
                if (listEl) {
                    listEl.innerHTML = '';
                    if (errorInfo.details.length > 0) {
                        errorInfo.details.forEach(function (detail) {
                            var fieldName = detail.field || 'Unknown';
                            var fieldMsg = detail.message || 'Invalid value';
                            var li = document.createElement('li');
                            li.innerHTML = '<strong>' + fieldName + ':</strong> ' + fieldMsg;
                            listEl.appendChild(li);
                        });
                    }
                }
                // Show modal
                var modal = window.bootstrap.Modal.getOrCreateInstance(modalEl);
                modal.show();
                return;
            }
            // Fallback: show in error box
            var errorBox = qs('[data-onboarding-error]');
            if (errorBox) {
                show(errorBox);
                var messageText = errorInfo.message;
                if (errorInfo.details.length > 0) {
                    messageText += '\n\nValidasi Error:\n';
                    errorInfo.details.forEach(function (detail) {
                        messageText += '• ' + (detail.field || 'Field') + ': ' + (detail.message || 'Invalid') + '\n';
                    });
                }
                setText(errorBox, messageText);
                return;
            }
            // Last fallback: alert
            window.alert(errorInfo.message || 'Terjadi kesalahan. Coba lagi.');
    }

    function buildPayload(form) {
        var fd = new FormData(form);
        var postalCode = sanitizePostalCode(fd.get("company_postal_code") || "");
        var turnstileToken = syncTurnstileToken(form);

        var payload = {
            package_uuid: String(fd.get("package_uuid") || "").trim(),
            billing_cycle: String(fd.get("billing_cycle") || "monthly").trim(),
            start_mode: normalizeStartMode(fd.get("start_mode") || "trial"),
            turnstile_token: turnstileToken || null,
            website: String(fd.get("website") || "").trim() || null,
            company: {
                name: String(fd.get("company_name") || "").trim(),
                legal_name: String(fd.get("company_legal_name") || "").trim() || null,
                timezone: String(fd.get("company_timezone") || "").trim(),
                currency: String(fd.get("company_currency") || "").trim(),
                country_code: String(fd.get("company_country_code") || "").trim(),
                contact_phone: String(fd.get("company_contact_phone") || "").trim() || null,
                contact_person_name: String(fd.get("company_contact_person_name") || "").trim() || null,
                contact_person_role: String(fd.get("company_contact_person_role") || "").trim() || null,
                address: String(fd.get("company_address") || "").trim(),
                city: String(fd.get("company_city") || "").trim(),
                postal_code: postalCode || null,
            },
            owner: {
                name: String(fd.get("owner_name") || "").trim(),
                email: String(fd.get("owner_email") || "").trim(),
                phone: String(fd.get("owner_phone") || "").trim() || null,
                password: String(fd.get("owner_password") || ""),
                confirmPassword: String(fd.get("owner_confirm_password") || ""),
            },
            billingEmail: String(fd.get("billing_email") || "").trim() || null,
        };

        if (!payload.billingEmail) {
            delete payload.billingEmail;
        }
        if (!payload.turnstile_token) {
            delete payload.turnstile_token;
        }
        if (!payload.website) {
            delete payload.website;
        }
        if (!payload.company.contact_phone) {
            delete payload.company.contact_phone;
        }
        if (!payload.company.contact_person_name) {
            delete payload.company.contact_person_name;
        }
        if (!payload.company.contact_person_role) {
            delete payload.company.contact_person_role;
        }
        if (!payload.company.postal_code) {
            delete payload.company.postal_code;
        }
        if (!payload.owner.phone) {
            delete payload.owner.phone;
        }

        return payload;
    }

    function validateClientState(form, errorBox) {
        var companyNameInput = form.querySelector("[name='company_name']");
        if (companyNameInput) {
            var companyName = String(companyNameInput.value || "").trim();
            if (companyName.length > 0 && companyName.length < 2) {
                companyNameInput.setCustomValidity("Company name minimal 2 karakter.");
            } else {
                companyNameInput.setCustomValidity("");
            }
        }

        var postalInput = form.querySelector("[name='company_postal_code']");
        if (postalInput) {
            var cleanedPostalCode = sanitizePostalCode(postalInput.value);
            if (postalInput.value !== cleanedPostalCode) {
                postalInput.value = cleanedPostalCode;
            }

            if (cleanedPostalCode && cleanedPostalCode.length < 3) {
                postalInput.setCustomValidity("Kode pos harus terdiri dari 3 sampai 12 digit.");
            } else {
                postalInput.setCustomValidity("");
            }
        }

        var startModeField = form.querySelector("[name='start_mode']");
        if (startModeField) {
            startModeField.value = normalizeStartMode(startModeField.value);
        }

        if (typeof form.reportValidity === "function" && !form.reportValidity()) {
            return false;
        }

        var turnstileContainer = qs("[data-turnstile-container]", form);
        if (turnstileContainer && !syncTurnstileToken(form)) {
            showInlineError(errorBox, "Verifikasi captcha wajib diselesaikan sebelum submit.");
            return false;
        }

        return true;
    }

    function init() {
        // Reveal animations (no extra libs)
        try {
            var reduceMotion = window.matchMedia && window.matchMedia("(prefers-reduced-motion: reduce)").matches;
            if (!reduceMotion && "IntersectionObserver" in window) {
                var io = new IntersectionObserver(function (entries) {
                    entries.forEach(function (entry) {
                        if (entry.isIntersecting) {
                            entry.target.classList.add("is-visible");
                            io.unobserve(entry.target);
                        }
                    });
                }, { threshold: 0.12 });

                qsa("[data-reveal]").forEach(function (el) {
                    io.observe(el);
                });
            } else {
                qsa("[data-reveal]").forEach(function (el) {
                    el.classList.add("is-visible");
                });
            }
        } catch (_e) {}

        // Smooth scroll for in-page anchors + close navbar on click (mobile)
        try {
            qsa('a[href^="#"]').forEach(function (a) {
                a.addEventListener("click", function (e) {
                    var href = a.getAttribute("href");
                    if (!href || href === "#") return;
                    var target = qs(href);
                    if (!target) return;
                    e.preventDefault();
                    target.scrollIntoView({ behavior: "smooth", block: "start" });
                    var navCollapse = qs("#landingNav");
                    if (navCollapse && navCollapse.classList.contains("show") && window.bootstrap && window.bootstrap.Collapse) {
                        new window.bootstrap.Collapse(navCollapse).hide();
                    }
                });
            });
        } catch (_e) {}

        // Pricing toggle monthly/yearly
        try {
            var toggle = qs("[data-billing-toggle]");
            if (toggle) {
                var formatIdr = function (n) {
                    var x = Math.round(Number(n || 0));
                    return "Rp " + String(x).replace(/\B(?=(\d{3})+(?!\d))/g, ".");
                };
                var applyCycle = function (cycle) {
                    qsa("[data-price]").forEach(function (el) {
                        var monthly = Number(el.getAttribute("data-price-monthly") || 0);
                        var yearly = Number(el.getAttribute("data-price-yearly") || 0);
                        var v = cycle === "yearly" ? yearly : monthly;
                        el.setAttribute("data-price-cycle", cycle);
                        var suffix = el.querySelector("[data-price-suffix]");
                        var text = formatIdr(v);
                        // keep suffix node
                        if (suffix) {
                            el.childNodes[0].textContent = text + " ";
                            suffix.textContent = cycle === "yearly" ? "/tahun" : "/bulan";
                        } else {
                            el.textContent = text;
                        }
                    });
                };
                applyCycle(toggle.checked ? "yearly" : "monthly");
                toggle.addEventListener("change", function () {
                    applyCycle(toggle.checked ? "yearly" : "monthly");
                });
            }
        } catch (_e) {}

        var grid = qs("[data-packages-grid]");
        var packages = [];
        if (grid) {
            var packagesRaw = grid.getAttribute("data-packages") || "[]";
            try {
                packages = JSON.parse(packagesRaw) || [];
            } catch (_e) {
                packages = [];
            }
        }

        var modalEl = qs("#onboardingModal");
        var form = qs("#onboardingForm") || qs("[data-onboarding-form]");
        var errorBox = qs("[data-onboarding-error]") || (modalEl ? qs("[data-onboarding-error]", modalEl) : null);
        var submitBtn = qs("[data-onboarding-submit]") || (modalEl ? qs("[data-onboarding-submit]", modalEl) : null);
        var packageSelect = qs("[data-onboarding-package]") || (modalEl ? qs("[data-onboarding-package]", modalEl) : null);
        var billingCycleSelect = form ? form.querySelector("select[name='billing_cycle']") : null;
        var billingCycleWrapper = billingCycleSelect ? billingCycleSelect.closest(".col-md-6") : null;
        var billingCycleHelp = form ? form.querySelector("[data-billing-cycle-help]") : null;
        var billingCycleTrialHelp = form ? form.querySelector("[data-billing-cycle-trial-help]") : null;
        var startModeField = form ? form.querySelector("[name='start_mode']") : null;

        var modal = null;
        if (modalEl && window.bootstrap && window.bootstrap.Modal) {
            modal = new window.bootstrap.Modal(modalEl);
        }

        if (packageSelect && packages.length) {
            // Only populate from JSON when landing provides the dataset.
            // Trial page already renders options server-side.
            packageSelect.innerHTML = "";
            packages.forEach(function (p) {
                var opt = document.createElement("option");
                opt.value = String(p.uuid || p.id || "");
                opt.textContent = p.name + " (" + p.code + ")";
                opt.setAttribute("data-package-code", String(p.code || ""));
                packageSelect.appendChild(opt);
            });
        }

        // Handle billing cycle disable state based on package selection
        if (packageSelect && billingCycleSelect) {
            var getSelectedPackageMeta = function () {
                var selectedValue = String(packageSelect.value || "");
                for (var index = 0; index < packages.length; index += 1) {
                    var candidate = packages[index] || {};
                    if (String(candidate.uuid || candidate.id || "") === selectedValue) {
                        return candidate;
                    }
                }

                var selectedOption = packageSelect.options[packageSelect.selectedIndex];
                if (!selectedOption) {
                    return null;
                }

                return {
                    code: selectedOption.getAttribute("data-package-code") || selectedOption.text || "",
                };
            };

            var updateBillingCycleState = function () {
                var selectedPackage = getSelectedPackageMeta();
                var packageCode = String(selectedPackage && selectedPackage.code ? selectedPackage.code : "").toLowerCase();
                var isTrialPackage = packageCode === "trial" || packageCode.indexOf("trial") !== -1;

                if (isTrialPackage) {
                    // For trial: disable and set to monthly
                    billingCycleSelect.disabled = true;
                    billingCycleSelect.value = "monthly";
                    if (billingCycleWrapper) {
                        billingCycleWrapper.style.opacity = "0.6";
                    }
                    // Toggle helper text
                    if (billingCycleHelp) {
                        billingCycleHelp.classList.add("d-none");
                    }
                    if (billingCycleTrialHelp) {
                        billingCycleTrialHelp.classList.remove("d-none");
                    }
                    if (startModeField) {
                        startModeField.value = "trial";
                        if (String(startModeField.tagName || "").toLowerCase() === "select") {
                            startModeField.disabled = true;
                        }
                    }
                } else {
                    // For paid packages: enable
                    billingCycleSelect.disabled = false;
                    if (billingCycleWrapper) {
                        billingCycleWrapper.style.opacity = "1";
                    }
                    // Toggle helper text
                    if (billingCycleHelp) {
                        billingCycleHelp.classList.remove("d-none");
                    }
                    if (billingCycleTrialHelp) {
                        billingCycleTrialHelp.classList.add("d-none");
                    }
                    if (startModeField && String(startModeField.tagName || "").toLowerCase() === "select") {
                        startModeField.disabled = false;
                    }
                }
            };

            packageSelect.addEventListener("change", updateBillingCycleState);
            // Run on page load to set initial state
            updateBillingCycleState();
        }

        function openWithPackageId(packageId) {
            if (!modal) return;
            hide(errorBox);
            if (packageSelect && packageId) {
                packageSelect.value = String(packageId);
                var changeEvent = new window.Event("change", { bubbles: true });
                packageSelect.dispatchEvent(changeEvent);
            }
            modal.show();
        }

        qsa("[data-open-onboarding]").forEach(function (btn) {
            btn.addEventListener("click", function () {
                if (!modal) {
                    // If we're on dedicated form page, just scroll to it
                    if (form && form.scrollIntoView) {
                        form.scrollIntoView({ behavior: "smooth", block: "start" });
                    }
                    return;
                }
                var pkgId = btn.getAttribute("data-package-id");
                openWithPackageId(pkgId ? String(pkgId) : null);
            });
        });

        if (!form) return;

        if (modalEl) {
            modalEl.addEventListener("shown.bs.modal", function () {
                whenTurnstileReady(function () {
                    removeTurnstileWidgets(modalEl);
                    renderTurnstileWidgets(modalEl);
                });
            });

            modalEl.addEventListener("hidden.bs.modal", function () {
                whenTurnstileReady(function () {
                    removeTurnstileWidgets(modalEl);
                });
            });
        } else {
            whenTurnstileReady(function () {
                renderTurnstileWidgets(form);
            });
        }

        var postalInput = form.querySelector("[name='company_postal_code']");
        if (postalInput) {
            postalInput.addEventListener("input", function () {
                var cleanedPostalCode = sanitizePostalCode(postalInput.value);
                if (postalInput.value !== cleanedPostalCode) {
                    postalInput.value = cleanedPostalCode;
                }
                if (!cleanedPostalCode || cleanedPostalCode.length >= 3) {
                    postalInput.setCustomValidity("");
                }
            });
        }

        form.addEventListener("submit", function (e) {
            e.preventDefault();
            hide(errorBox);

            if (!validateClientState(form, errorBox)) {
                return;
            }

            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.textContent = "Memproses...";
            }

            var payload = buildPayload(form);

            var client = window.ApiClient && typeof window.ApiClient.request === "function" ? window.ApiClient : null;
            var req = client
                ? client.request("post", "/public/onboarding", payload)
                : fetch("/v1/public/onboarding", {
                      method: "POST",
                      headers: { "Content-Type": "application/json", Accept: "application/json" },
                      credentials: "same-origin",
                      body: JSON.stringify(payload),
                  }).then(function (r) {
                      return r.json().then(function (data) {
                          if (!r.ok) {
                              var err = new Error("Request failed");
                              err.response = { status: r.status, data: data };
                              throw err;
                          }
                          return { status: r.status, data: data };
                      });
                  });

            req.then(function (res) {
                var data = res && res.data ? res.data : {};
                if (!data.success) {
                    throw { response: { data: data, status: res.status } };
                }

                if (modal) {
                    try {
                        modal.hide();
                    } catch (_modalHideError) {}
                }
                var companyCode = data && data.data && data.data.company ? data.data.company.code : null;
                var ownerEmail = data && data.data && data.data.owner ? data.data.owner.email : null;
                var subscription = data && data.data ? data.data.subscription : null;
                var invoice = data && data.data ? data.data.invoice : null;
                var isPendingPayment = !!subscription && subscription.status === "pending_payment";
                var redirectUrl = isPendingPayment ? buildPendingPaymentLoginUrl(companyCode) : "/login";
                var actionLabel = isPendingPayment ? "Login untuk lanjut bayar" : "Login sekarang";

                var message = isPendingPayment
                    ? "Registrasi company berhasil, tetapi subscription masih menunggu pembayaran."
                    : "Onboarding berhasil.";
                if (companyCode) {
                    message += "\n\nCompany code: " + String(companyCode);
                }
                if (ownerEmail) {
                    message += "\nLogin email: " + String(ownerEmail);
                }
                if (invoice) {
                    if (invoice.invoiceNumber) {
                        message += "\nInvoice: " + String(invoice.invoiceNumber);
                    }
                    if (invoice.amountDue != null) {
                        message += "\nAmount due: " + formatIdr(invoice.amountDue);
                    }
                    if (invoice.dueDate) {
                        message += "\nDue date: " + String(invoice.dueDate);
                    }

                    var breakdownText = buildInvoiceBreakdownMessage(invoice);
                    if (breakdownText) {
                        message += "\n\n" + breakdownText;
                    }
                }
                message += isPendingPayment
                    ? "\n\nLanjutkan dengan Login Company untuk membuka checkout payment."
                    : "\n\nKlik “Login sekarang” untuk masuk.";

                if (window.ArcavUi && typeof window.ArcavUi.selectOption === "function") {
                    Promise.resolve()
                        .then(function () {
                            return window.ArcavUi.selectOption({
                                title: "Onboarding berhasil",
                                message: message,
                                options: [{ value: "login", label: actionLabel }],
                            });
                        })
                        .catch(function (_modalError) {
                            return null;
                        })
                        .finally(function () {
                            window.location.href = redirectUrl;
                        });
                    return;
                }

                try {
                    window.alert(message);
                } catch (_e) {}
                window.location.href = redirectUrl;
            }).catch(function (err) {
                showErrorModal(err);
            }).finally(function () {
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.textContent = submitBtn.getAttribute("data-default-text") || "Proses";
                }
            });
        });
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", init);
    } else {
        init();
    }
})(window, document);

