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

        var payload = {
            package_uuid: String(fd.get("package_uuid") || "").trim(),
            billing_cycle: String(fd.get("billing_cycle") || "monthly"),
            start_mode: String(fd.get("start_mode") || "trial"),
            turnstile_token: String(fd.get("cf-turnstile-response") || "").trim() || null,
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
                postal_code: String(fd.get("company_postal_code") || "").trim() || null,
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
                opt.value = String(p.id || p.uuid || "");
                opt.textContent = p.name + " (" + p.code + ")";
                packageSelect.appendChild(opt);
            });
        }

        // Handle billing cycle disable state based on package selection
        if (packageSelect && billingCycleSelect) {
            var updateBillingCycleState = function () {
                var selectedOption = packageSelect.options[packageSelect.selectedIndex];
                var packageText = selectedOption ? selectedOption.text : "";
                var isTrialPackage = packageText.toLowerCase().includes("(trial)");

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

        form.addEventListener("submit", function (e) {
            e.preventDefault();
            hide(errorBox);

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
                    modal.hide();
                }
                var companyCode = data && data.data && data.data.company ? data.data.company.code : null;
                var ownerEmail = data && data.data && data.data.owner ? data.data.owner.email : null;

                var message = "Onboarding berhasil.";
                if (companyCode) {
                    message += "\n\nCompany code: " + String(companyCode);
                }
                if (ownerEmail) {
                    message += "\nLogin email: " + String(ownerEmail);
                }
                message += "\n\nKlik “Login sekarang” untuk masuk.";

                if (window.ArcavUi && typeof window.ArcavUi.selectOption === "function") {
                    window.ArcavUi.selectOption({
                        title: "Onboarding berhasil",
                        message: message,
                        options: [{ value: "login", label: "Login sekarang" }],
                    }).then(function () {
                        window.location.href = "/login";
                    });
                    return;
                }

                // Fallback: minimal UX if modal helper isn't available
                try {
                    window.alert(message);
                } catch (_e) {}
                window.location.href = "/login";
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

