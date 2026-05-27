(function (window, document) {
    "use strict";

    var root = document.querySelector("[data-subscription-checkout-page]");
    if (!root) return;

    var form = document.querySelector("[data-checkout-form]");
    var feedback = document.querySelector("[data-checkout-feedback]");
    var submitBtn = document.querySelector("[data-checkout-submit]");
    var addonSubmitBtn = document.querySelector("[data-checkout-addon-submit]");
    var pkgSelect = document.querySelector("[data-checkout-package-select]");
    var addonSelect = document.querySelector("[data-checkout-addon-select]");
    var billingEmailInput = document.querySelector("[data-checkout-billing-email]");
    var companyNameInput = document.querySelector("[data-checkout-company-name]");
    var companyIdInput = document.querySelector("[data-checkout-company-id]");
    var companyCodeInput = document.querySelector("[data-checkout-company-code]");
    var companyBadge = document.querySelector("[data-checkout-company-badge]");
    var trialBadge = document.querySelector("[data-checkout-trial-badge]");
    var copyCodeBtn = document.querySelector("[data-checkout-copy-code]");
    var invoiceBox = document.querySelector("[data-checkout-invoice-box]");
    var invoiceHint = document.querySelector("[data-checkout-invoice-hint]");
    var invoiceTitle = document.querySelector("[data-checkout-invoice-title]");
    var invoiceSubtitle = document.querySelector("[data-checkout-invoice-subtitle]");
    var invoiceAmount = document.querySelector("[data-checkout-invoice-amount]");
    var invoiceDue = document.querySelector("[data-checkout-invoice-due]");
    var invoiceBreakdowns = Array.prototype.slice.call(document.querySelectorAll("[data-checkout-invoice-breakdown]"));
    var openInvoicesBtn = document.querySelector("[data-checkout-open-invoices]");
    var payNowBtn = document.querySelector("[data-checkout-pay-now]");
    var goDashboardBtn = document.querySelector("[data-checkout-go-dashboard]");
    var successState = document.querySelector("[data-checkout-success-state]");
    var successMessage = document.querySelector("[data-checkout-success-message]");
    var activePackageName = document.querySelector("[data-checkout-active-package-name]");
    var activePackageCode = document.querySelector("[data-checkout-active-package-code]");
    var activePackagePrice = document.querySelector("[data-checkout-active-package-price]");
    var activePackageUnit = document.querySelector("[data-checkout-active-package-unit]");
    var hostedPayEnabled = String(
        root.getAttribute("data-checkout-hosted-pay-enabled")
        || root.getAttribute("data-checkout-mock-pay-enabled")
        || "0"
    ) === "1";
    var isPendingLock = String(root.getAttribute("data-checkout-pending-lock") || "0") === "1";
    var isActiveOnly = String(root.getAttribute("data-checkout-active-only") || "0") === "1";
    var isInactiveContext = String(root.getAttribute("data-checkout-inactive-context") || "0") === "1";
    var hasPreloadedPendingInvoice = String(root.getAttribute("data-checkout-preloaded-pending-invoice") || "0") === "1";
    var isCreationLocked = String(root.getAttribute("data-checkout-creation-locked") || "0") === "1";
    var upgradeForm = document.querySelector("[data-checkout-form].checkout-upgrade-form") || form;
    var currentInvoice = null;
    var invoiceStateBar = document.querySelector("[data-checkout-invoice-statebar]");
    var invoiceStateBadge = document.querySelector("[data-checkout-invoice-state-badge]");
    var invoiceStateNote = document.querySelector("[data-checkout-invoice-state-note]");

    function setFieldValue(el, value) {
        if (!el) return;
        var tag = String(el.tagName || "").toLowerCase();
        if (tag === "input" || tag === "textarea" || tag === "select") {
            el.value = value == null ? "" : value;
        } else {
            el.textContent = value == null || value === "" ? "—" : value;
        }
    }

    function searchParams() {
        try {
            return new URLSearchParams(window.location.search || "");
        } catch (_e) {
            return new URLSearchParams();
        }
    }

    function redirectTo(url) {
        if (!url) return;
        if (window.__ARCAV_DISABLE_REDIRECTS__) {
            window.__ARCAV_LAST_REDIRECT__ = String(url);
            return;
        }
        window.location.assign(String(url));
    }

    function getTenantContext() {
        try {
            if (window.AuthApi && typeof window.AuthApi.getTenantContext === "function") {
                return window.AuthApi.getTenantContext() || {};
            }
        } catch (_e) {}
        return {};
    }

    function buildHeaders(extra) {
        var headers = {
            Accept: "application/json",
            "X-Requested-With": "XMLHttpRequest",
        };
        var token = (window.AuthApi && window.AuthApi.getToken()) || localStorage.getItem('arcav_access_token');
        if (token) { headers['Authorization'] = 'Bearer ' + token; }
        var tenant = getTenantContext();
        if (tenant && tenant.companyCode) headers["X-Company-Code"] = String(tenant.companyCode);
        if (tenant && tenant.companyId) headers["X-Company-Id"] = String(tenant.companyId);
        if (extra) Object.keys(extra).forEach(function (k) { headers[k] = extra[k]; });
        return headers;
    }

    function showFeedback(type, message) {
        if (!feedback) return;
        feedback.classList.remove("d-none", "alert-success", "alert-danger", "alert-warning", "alert-info");
        feedback.classList.add("alert-" + type);
        feedback.textContent = message || "";
    }

    function clearFeedback() {
        if (!feedback) return;
        feedback.classList.add("d-none");
        feedback.textContent = "";
    }

    function setLoading(isLoading) {
        if (!submitBtn) return;
        submitBtn.disabled = isLoading;
    }

    function setAddonLoading(isLoading) {
        if (!addonSubmitBtn) return;
        addonSubmitBtn.disabled = isLoading;
    }

    function setPaying(isPaying) {
        if (!payNowBtn) return;
        payNowBtn.disabled = isPaying;
    }

    function isInternalBillingComponent(component) {
        var key = String(component && component.key ? component.key : "").toLowerCase();
        var label = String(component && component.label ? component.label : "").toLowerCase();
        return key === "addon_markup_rate"
            || key === "payroll_service_fee"
            || key === "service_fee"
            || label.indexOf("corporate tax") !== -1
            || label.indexOf("payroll service fee") !== -1;
    }

    function formatRupiah(num) {
        var n = Number(num || 0);
        try {
            return new Intl.NumberFormat("id-ID", { style: "currency", currency: "IDR", maximumFractionDigits: 0 }).format(n);
        } catch (_e) {
            return "Rp " + String(n);
        }
    }

    function formatRate(num) {
        var n = Number(num || 0);
        if (!Number.isFinite(n)) return "0%";
        var rounded = Math.round(n * 100) / 100;
        var text = String(rounded);
        if (text.indexOf(".") !== -1) {
            text = text.replace(/\.0+$/, "").replace(/(\.\d*[1-9])0+$/, "$1");
        }
        return text + "%";
    }

    function formatDateLabel(value) {
        if (!value) return "-";
        try {
            return new Intl.DateTimeFormat("id-ID", {
                day: "2-digit",
                month: "short",
                year: "numeric",
            }).format(new Date(value));
        } catch (_e) {
            return String(value);
        }
    }

    function calendarDayDiff(value) {
        if (!value) return 0;
        var target = new Date(value);
        var today = new Date();
        if (!Number.isFinite(target.getTime()) || !Number.isFinite(today.getTime())) return 0;
        target.setHours(0, 0, 0, 0);
        today.setHours(0, 0, 0, 0);
        return Math.round((target.getTime() - today.getTime()) / 86400000);
    }

    function billingCycleValue() {
        var checked = document.querySelector("input[name='billing_cycle']:checked");
        return checked ? String(checked.value || "monthly") : "monthly";
    }

    function parsePricingBreakdown(invoice) {
        if (!invoice) return null;

        var normalized = invoice.pricingBreakdown && typeof invoice.pricingBreakdown === "object"
            ? invoice.pricingBreakdown
            : null;

        if (normalized) {
            var normalizedBaseAmount = Number(normalized.baseAmount || 0);
            var normalizedTaxRate = Number(normalized.subscriptionTaxRate || 0);
            var normalizedTaxAmount = Number(normalized.subscriptionTaxAmount || 0);
            var normalizedTotalAmount = Number(normalized.totalAmount || 0);
            var normalizedComponents = Array.isArray(normalized.components)
                ? normalized.components
                    .map(function (item) {
                        var rate = Number(item && item.rate);
                        var amount = Number(item && item.amount);
                        if (!Number.isFinite(rate) || !Number.isFinite(amount)) return null;
                        return {
                            key: item && item.key ? String(item.key) : "",
                            label: item && item.label ? String(item.label) : "Komponen",
                            rate: rate,
                            amount: amount,
                        };
                    })
                    .filter(Boolean)
                    .filter(function (item) { return !isInternalBillingComponent(item); })
                : [];

            if (Number.isFinite(normalizedBaseAmount) && Number.isFinite(normalizedTaxRate) && Number.isFinite(normalizedTaxAmount) && Number.isFinite(normalizedTotalAmount)) {
                return {
                    baseAmount: normalizedBaseAmount,
                    taxRate: normalizedTaxRate,
                    taxAmount: normalizedTaxAmount,
                    totalAmount: normalizedTotalAmount,
                    components: normalizedComponents,
                };
            }
        }

        var amountDue = Number(invoice.amountDue || 0);
        var taxRateSnapshot = Number(invoice.billingTaxRateSnapshot || 0);

        if (Number.isFinite(amountDue) && amountDue > 0 && Number.isFinite(taxRateSnapshot) && taxRateSnapshot > 0) {
            var taxAmountSnapshot = Math.round(amountDue * (taxRateSnapshot / (100 + taxRateSnapshot)));
            var baseAmountSnapshot = Math.max(0, Math.round(amountDue - taxAmountSnapshot));

            return {
                baseAmount: baseAmountSnapshot,
                taxRate: taxRateSnapshot,
                taxAmount: taxAmountSnapshot,
                totalAmount: amountDue,
                components: [
                    {
                        key: "subscription_tax_rate",
                        label: "Pajak",
                        rate: taxRateSnapshot,
                        amount: taxAmountSnapshot,
                    },
                ],
            };
        }

        if (!invoice.notes) return null;

        try {
            var payload = typeof invoice.notes === "string" ? JSON.parse(invoice.notes) : invoice.notes;
            var breakdown = payload && payload.pricing_breakdown ? payload.pricing_breakdown : null;
            if (!breakdown) return null;

            var baseAmount = Number(breakdown.base_amount || 0);
            var taxRate = Number(breakdown.subscription_tax_rate || 0);
            var taxAmount = Number(breakdown.subscription_tax_amount || 0);
            var totalAmount = Number(breakdown.total_amount || 0);

            if (!Number.isFinite(baseAmount) || !Number.isFinite(taxRate) || !Number.isFinite(taxAmount) || !Number.isFinite(totalAmount)) {
                return null;
            }

            var components = Array.isArray(breakdown.components) ? breakdown.components
                .map(function (item) {
                    var rate = Number(item && item.rate);
                    var amount = Number(item && item.amount);
                    if (!Number.isFinite(rate) || !Number.isFinite(amount)) return null;
                    return {
                        key: item && item.key ? String(item.key) : "",
                        label: item && item.label ? String(item.label) : "Komponen",
                        rate: rate,
                        amount: amount,
                    };
                })
                .filter(Boolean)
                .filter(function (item) { return !isInternalBillingComponent(item); })
                : [];

            return {
                baseAmount: baseAmount,
                taxRate: taxRate,
                taxAmount: taxAmount,
                totalAmount: totalAmount,
                components: components,
            };
        } catch (_e) {
            return null;
        }
    }

    function api(method, path, payload) {
        if (window.AuthApi && typeof window.AuthApi.request === "function") {
            return window.AuthApi.request(method, path, payload).then(function (res) {
                return res && res.data ? res.data : res;
            });
        }

        var options = {
            method: String(method || "GET").toUpperCase(),
            headers: buildHeaders({ Accept: "application/json" }),
            credentials: "same-origin",
        };

        if (payload != null) {
            options.headers["Content-Type"] = "application/json";
            options.body = JSON.stringify(payload);
        }

        return fetch("/v1" + path, options).then(async function (res) {
            var body = await res.json().catch(function () { return null; });
            if (!res.ok) {
                throw { status: res.status, data: body };
            }
            return body;
        });
    }

    function invoiceLabel(invoice) {
        if (!invoice) return "—";
        return invoice.invoiceNumber || invoice.id || "—";
    }

    function resolveInvoiceContext(invoice, reused) {
        var unpaid = !!(invoice && !invoice.isPaid);
        var overdue = !!(invoice && invoice.isOverdue);
        var dueSoon = !!(invoice && invoice.isDueSoon);
        var dueDiff = calendarDayDiff(invoice && invoice.dueDate);

        if (invoice && invoice.isPaid) {
            return {
                title: "Invoice sudah dibayar",
                subtitle: "Invoice #" + invoiceLabel(invoice),
                dueText: invoice.paidDate ? ("Dibayar pada " + formatDateLabel(invoice.paidDate)) : "Pembayaran berhasil diverifikasi.",
                note: isInactiveContext
                    ? "Pembayaran reaktivasi selesai. Kamu bisa lanjut masuk lagi ke aplikasi."
                    : "Pembayaran berhasil dan invoice ini sudah ditutup.",
                badgeClass: "bg-success-subtle text-success border border-success-subtle",
                badgeLabel: isInactiveContext ? "Reaktivasi selesai" : "Lunas",
            };
        }

        if (isInactiveContext && unpaid) {
            var dueText = invoice && invoice.dueDate ? ("Jatuh tempo " + formatDateLabel(invoice.dueDate)) : "Menunggu jadwal jatuh tempo invoice.";
            if (overdue || dueDiff < 0) {
                var overdueDays = Math.max(1, Math.abs(dueDiff));
                dueText = "Lewat jatuh tempo " + overdueDays + " hari";
            } else if (dueDiff === 0) {
                dueText = "Jatuh tempo hari ini";
            } else if (dueSoon || dueDiff <= 7) {
                dueText = "Jatuh tempo " + dueDiff + " hari lagi";
            }

            return {
                title: reused ? "Invoice reaktivasi aktif" : "Invoice reaktivasi dibuat",
                subtitle: "Invoice #" + invoiceLabel(invoice) + " untuk mengaktifkan akses kembali.",
                dueText: dueText,
                note: overdue || dueDiff < 0
                    ? "Tagihan ini masih jadi penghalang utama akses. Bayar sekarang untuk memulihkan langganan."
                    : "Begitu invoice ini dibayar, akses company akan aktif kembali tanpa perlu bikin tagihan lain.",
                badgeClass: overdue || dueDiff < 0
                    ? "bg-danger-subtle text-danger border border-danger-subtle"
                    : "bg-warning-subtle text-warning border border-warning-subtle",
                badgeLabel: overdue || dueDiff < 0 ? "Reaktivasi tertunda" : "Menunggu reaktivasi",
            };
        }

        return {
            title: reused ? "Invoice pending ditemukan" : "Invoice dibuat",
            subtitle: "Invoice #" + invoiceLabel(invoice),
            dueText: invoice && invoice.isPaid
                ? (invoice.paidDate ? ("Dibayar: " + formatDateLabel(invoice.paidDate)) : "Status: paid")
                : (invoice && invoice.dueDate ? ("Jatuh tempo: " + formatDateLabel(invoice.dueDate)) : "—"),
            note: "Selesaikan invoice ini sebelum membuat invoice baru.",
            badgeClass: "bg-light text-secondary border",
            badgeLabel: reused ? "Pending aktif" : "Invoice baru",
        };
    }

    function updateInvoiceActions(invoice) {
        var unpaid = !!(invoice && !invoice.isPaid);

        if (openInvoicesBtn) {
            openInvoicesBtn.classList.toggle("d-none", isInactiveContext && unpaid);
        }

        if (payNowBtn) {
            payNowBtn.classList.toggle("d-none", !unpaid || !hostedPayEnabled);
            payNowBtn.textContent = unpaid
                ? (isInactiveContext ? "Bayar & aktifkan kembali" : "Bayar sekarang")
                : "Sudah dibayar";
        }

        if (goDashboardBtn) {
            goDashboardBtn.classList.toggle("d-none", !(invoice && invoice.isPaid));
        }
    }

    function renderInvoice(data, reused) {
        if (!data || !data.invoice) return;
        currentInvoice = data.invoice;
        var contextCopy = resolveInvoiceContext(currentInvoice, reused);
        if (invoiceHint) invoiceHint.classList.add("d-none");
        if (invoiceBox) invoiceBox.classList.remove("d-none");
        if (invoiceTitle) invoiceTitle.textContent = contextCopy.title;
        if (invoiceSubtitle) {
            invoiceSubtitle.textContent = contextCopy.subtitle;
        }
        if (invoiceAmount) invoiceAmount.textContent = formatRupiah(currentInvoice.amountDue);
        if (invoiceDue) invoiceDue.textContent = contextCopy.dueText;
        if (invoiceStateBar) invoiceStateBar.classList.remove("d-none");
        if (invoiceStateBadge) {
            invoiceStateBadge.className = "badge " + contextCopy.badgeClass;
            invoiceStateBadge.textContent = contextCopy.badgeLabel;
        }
        if (invoiceStateNote) invoiceStateNote.textContent = contextCopy.note;

        if (invoiceBreakdowns.length > 0) {
            var breakdown = parsePricingBreakdown(currentInvoice);
                var breakdownHtml = '';
            if (breakdown) {
                var lineItems = Array.isArray(breakdown.components) && breakdown.components.length > 0
                    ? breakdown.components
                    : [
                        {
                            key: "subscription_tax_rate",
                            label: "Pajak",
                            rate: breakdown.taxRate,
                            amount: breakdown.taxAmount,
                        },
                    ];

                    breakdownHtml += ''
                        + '<div class="d-flex align-items-center justify-content-between gap-3 flex-wrap">'
                        + '  <div class="fw-semibold text-dark">Rincian pembayaran</div>'
                        + '  <span class="badge bg-light text-secondary border">Tax rate ' + formatRate(breakdown.taxRate) + '</span>'
                        + '</div>'
                        + '<div class="mt-2 small">'
                        + '  <div class="d-flex justify-content-between gap-3"><span>Subtotal</span><strong>' + formatRupiah(breakdown.baseAmount) + '</strong></div>'
                        + lineItems.map(function (item) {
                            return '  <div class="d-flex justify-content-between gap-3"><span>'
                                + String(item.label || "Komponen")
                                + ' ' + formatRate(item.rate) + '</span><strong>'
                                + formatRupiah(item.amount)
                                + '</strong></div>';
                        }).join("")
                        + '  <div class="d-flex justify-content-between gap-3 border-top pt-2 mt-2"><span class="fw-semibold">Total</span><strong class="text-dark">' + formatRupiah(breakdown.totalAmount) + '</strong></div>'
                        + '</div>';
            }

            invoiceBreakdowns.forEach(function (node) {
                if (!node) return;
                if (breakdown) {
                    node.classList.remove("d-none");
                        node.innerHTML = breakdownHtml;
                } else {
                    node.classList.add("d-none");
                        node.innerHTML = "";
                }
            });
        }

        updateInvoiceActions(currentInvoice);

        // Load and display active subscription in success state
        loadAndShowActiveSubscription();

        // Once an invoice is the active focus of this page, keep the creation form locked.
        // Pending invoices must be paid first, and paid-return states should not invite users
        // to immediately create another invoice from the same success screen.
        if (currentInvoice && upgradeForm) {
            Array.prototype.slice.call(document.querySelectorAll("[data-checkout-form]")).forEach(function (checkoutForm) {
                checkoutForm.classList.add("d-none");
            });
        }
    }

    async function loadAndShowActiveSubscription() {
        if (!successState) return;
        try {
            var payload = await api("get", "/v1/hcm/subscriptions/current");
            if (!payload || payload.success !== true || !payload.data) {
                successState.classList.add("d-none");
                return;
            }

            var subscription = payload.data;
            var pkg = subscription && subscription.package ? subscription.package : null;
            if (!pkg) {
                successState.classList.add("d-none");
                return;
            }

            // Populate active package info
            if (activePackageName) activePackageName.textContent = String(pkg.name || "—");
            if (activePackageCode) activePackageCode.textContent = String(pkg.code || "—");
            if (activePackagePrice) activePackagePrice.textContent = formatRupiah(Number(pkg.monthlyPrice || pkg.monthly_price || 0));
            if (activePackageUnit) activePackageUnit.textContent = "per bulan";

            // Show success state
            successState.classList.remove("d-none");
        } catch (_e) {
            // Silently fail - don't break the page if fetch fails
            successState.classList.add("d-none");
        }
    }

    function toTimestamp(value) {
        if (!value) return 0;
        var time = new Date(value).getTime();
        return Number.isFinite(time) ? time : 0;
    }

    function pickPendingInvoice(rows) {
        return (Array.isArray(rows) ? rows : [])
            .filter(function (invoice) { return invoice && !invoice.isPaid; })
            .sort(function (left, right) {
                return toTimestamp(right && (right.updatedAt || right.createdAt || right.issueDate)) - toTimestamp(left && (left.updatedAt || left.createdAt || left.issueDate));
            })[0] || null;
    }

    async function loadPendingInvoice() {
        try {
            var payload = await api("get", "/hcm/billing/invoices?perPage=20&is_paid=0");
            if (!payload || payload.success !== true) return null;
            var pendingInvoice = pickPendingInvoice(payload.data || []);
            if (!pendingInvoice) return null;
            renderInvoice({ invoice: pendingInvoice }, true);
            if (!isPendingLock) {
                showFeedback("warning", isInactiveContext
                    ? "Ada invoice reaktivasi yang belum dibayar. Selesaikan tagihan ini dulu sebelum lanjut ke langkah lain."
                    : "Ada invoice pending yang belum dibayar. Selesaikan pembayaran ini sebelum membuat invoice baru.");
            } else {
                clearFeedback();
            }
            return pendingInvoice;
        } catch (_e) {
            return null;
        }
    }

    async function loadInvoiceById(invoiceId, feedbackMessage, feedbackType) {
        if (!invoiceId) return null;
        try {
            var payload = await api("get", "/hcm/billing/invoices/" + encodeURIComponent(invoiceId));
            if (!payload || payload.success !== true || !payload.data) {
                return null;
            }
            renderInvoice({ invoice: payload.data }, true);
            if (feedbackMessage) {
                showFeedback(feedbackType || "info", feedbackMessage);
            }
            return payload.data;
        } catch (_e) {
            return null;
        }
    }

    async function handleHostedReturn() {
        var params = searchParams();
        // Support both mock_payment_status (legacy mock) and payment_status (Midtrans)
        var status = String(params.get("mock_payment_status") || params.get("payment_status") || "").trim().toLowerCase();
        var invoiceId = String(params.get("invoice_id") || "").trim();
        if (!status || !invoiceId) {
            return false;
        }

        if (status === "completed") {
            var completedMsg = isInactiveContext
                ? "Pembayaran berhasil. Akses company sedang dipulihkan. Mengarahkan ke dashboard dalam 3 detik..."
                : "Pembayaran berhasil! Mengarahkan ke dashboard dalam 3 detik...";
            await loadInvoiceById(invoiceId, completedMsg, "success");
            window.setTimeout(function () { redirectTo("/index"); }, 3000);
            return true;
        }

        if (status === "failed") {
            await loadInvoiceById(invoiceId, isInactiveContext ? "Pembayaran reaktivasi belum berhasil. Coba lagi dari tagihan yang sama." : "Pembayaran belum berhasil. Coba lagi.", "warning");
            return true;
        }

        if (status === "pending") {
            await loadInvoiceById(invoiceId, isInactiveContext ? "Pembayaran reaktivasi belum selesai. Lanjutkan dari invoice aktif ini." : "Pembayaran belum selesai.", "info");
            return true;
        }

        return false;
    }

    async function payCurrentInvoice() {
        if (!currentInvoice || !currentInvoice.id) {
            showFeedback("warning", "Belum ada invoice yang bisa dibayar.");
            return;
        }

        try {
            setPaying(true);
            var payload = await api("post", "/hcm/billing/invoices/" + encodeURIComponent(currentInvoice.id) + "/mock-hosted-checkout", {});
            var snapToken = payload && payload.flow ? String(payload.flow.snapToken || "").trim() : "";
            var hostedCheckoutUrl = payload && payload.flow ? String(payload.flow.hostedCheckoutUrl || "").trim() : "";
            if (!payload || payload.success !== true || (!snapToken && !hostedCheckoutUrl)) {
                throw new Error("Gagal membuka hosted payment gateway.");
            }
            clearFeedback();
            // Prefer Snap popup (better UX), fallback to redirect URL
            if (snapToken && window.snap && typeof window.snap.pay === "function") {
                setPaying(false);
                window.snap.pay(snapToken, {
                    onSuccess: function (result) {
                        var invoiceId = String(currentInvoice.id || "");
                        loadInvoiceById(invoiceId, "Pembayaran berhasil! Mengarahkan ke dashboard dalam 3 detik...", "success");
                        window.setTimeout(function () { redirectTo("/index"); }, 3000);
                    },
                    onPending: function (result) {
                        var invoiceId = String(currentInvoice.id || "");
                        loadInvoiceById(invoiceId, "Pembayaran sedang diproses.", "info");
                    },
                    onError: function (result) {
                        showFeedback("danger", "Pembayaran gagal. Silakan coba lagi.");
                    },
                    onClose: function () {
                        // user closed snap without paying - no feedback needed
                    }
                });
            } else if (hostedCheckoutUrl) {
                redirectTo(hostedCheckoutUrl);
            } else {
                throw new Error("Gagal membuka halaman pembayaran.");
            }
        } catch (err) {
            var msg = err && err.data && err.data.error && err.data.error.message
                ? err.data.error.message
                : (err && err.message ? err.message : "Gagal membuka hosted payment gateway.");
            showFeedback("danger", msg);
            setPaying(false);
        }
    }

    async function loadMeAndRenderContext() {
        var meRes = await fetch("/v1/identity/auth/me", { method: "GET", headers: buildHeaders(), credentials: "same-origin" });
        var me = await meRes.json().catch(function () { return null; });
        if (!meRes.ok || !me || !me.success || !me.data) {
            throw new Error("Gagal memuat profile. Silakan login ulang.");
        }

        var activeCompany = me.data.activeCompany || null;
        if (!activeCompany) {
            throw new Error("Company context belum aktif. Login ulang dengan mode Login as Company (company code).");
        }

        if (companyNameInput) setFieldValue(companyNameInput, activeCompany.name || "");
        if (companyIdInput) setFieldValue(companyIdInput, String(activeCompany.id || ""));
        if (companyCodeInput) setFieldValue(companyCodeInput, String(activeCompany.code || ""));
        if (companyBadge) companyBadge.textContent = String(activeCompany.code || activeCompany.name || "Company");

        // Best-effort: show trial badge if shell dataset says trial.
        try {
            var wrapper = document.querySelector("[data-subscription-status]");
            var status = wrapper ? wrapper.getAttribute("data-subscription-status") : "";
            if (trialBadge && status === "trial") trialBadge.classList.remove("d-none");
        } catch (_e) {}

        if (billingEmailInput && !billingEmailInput.value) {
            billingEmailInput.value = String(me.data.email || "");
        }

        if (copyCodeBtn) {
            copyCodeBtn.addEventListener("click", function () {
                var code = String(activeCompany.code || "");
                if (!code) return;
                try {
                    navigator.clipboard.writeText(code);
                    showFeedback("success", "Company code berhasil disalin.");
                } catch (_e) {
                    showFeedback("warning", "Gagal menyalin otomatis. Salin manual company code di atas.");
                }
            });
        }
    }

    async function loadPackages() {
        if (!pkgSelect) return;
        pkgSelect.innerHTML = '<option value="">Loading packages…</option>';

        var res = await fetch("/v1/saas/packages?status=active&per_page=100", { method: "GET", headers: { Accept: "application/json" } });
        var body = await res.json().catch(function () { return null; });
        if (!res.ok || !body || body.success !== true) {
            pkgSelect.innerHTML = '<option value="">Gagal memuat paket</option>';
            return;
        }

        var items = Array.isArray(body.data) ? body.data : [];
        // Exclude trial package from checkout
        items = items.filter(function (p) { return String(p.code || "") !== "trial"; });

        if (!items.length) {
            pkgSelect.innerHTML = '<option value="">Tidak ada paket aktif</option>';
            return;
        }

        pkgSelect.innerHTML = '<option value="">Pilih paket…</option>' + items.map(function (p) {
            var packageId = String(p.id || p.uuid || "");
            return '<option value="' + packageId + '" data-code="' + String(p.code || "") + '">' +
                String(p.name || p.code) + "</option>";
        }).join("");
    }

    async function loadAddons() {
        if (!addonSelect) return;

        var res = await fetch("/v1/saas/package-addons?status=active&per_page=100", { method: "GET", headers: { Accept: "application/json" } });
        var body = await res.json().catch(function () { return null; });
        if (!res.ok || !body || body.success !== true) {
            if (addonSelect) addonSelect.innerHTML = '<option value="">Gagal memuat add-on</option>';
            return;
        }

        var items = Array.isArray(body.data) ? body.data : [];
        if (!items.length) {
            if (addonSelect) addonSelect.innerHTML = '<option value="">Tidak ada add-on aktif</option>';
            return;
        }

        // Render hidden select for form compatibility
        addonSelect.innerHTML = '<option value="">Pilih add-on…</option>' + items.map(function (item) {
            var itemId = String(item.id || "");
            var price = formatRupiah(item.pricePerUnit || 0);
            var label = String(item.name || item.code || "Add-on") + " (" + price + "/" + String(item.unitName || "unit") + ")";
            return '<option value="' + itemId + '">' + label + '</option>';
        }).join("");

        // Render addon cards grid
        var cardsGrid = document.getElementById("addon-cards-grid");
        if (cardsGrid) {
            var iconMap = {
                'asset_management': 'ti-archive',
                'employee_lifecycle': 'ti-user-check',
                'performance': 'ti-chart-bar',
                'goal_tracking': 'ti-target',
                'training': 'ti-book',
                'tickets': 'ti-help',
                'holiday_calendar': 'ti-calendar-event',
                'shift_scheduling': 'ti-clock',
                'leave_approval_flow': 'ti-square-check',
                'performance_goal_tracking': 'ti-trophy'
            };

            cardsGrid.innerHTML = items.map(function (item) {
                var itemId = String(item.id || "");
                var icon = iconMap[String(item.code || "")] || 'ti-puzzle';
                var price = formatRupiah(item.pricePerUnit || 0);
                var unitName = String(item.unitName || "unit");
                var cardClasses = "col-12 col-sm-6 col-lg-4";

                return '<div class="' + cardClasses + '">\
                    <div class="addon-card" role="button" tabindex="0" data-addon-id="' + itemId + '">\
                        <div class="addon-card-check">\
                            <i class="ti ti-check"></i>\
                        </div>\
                        <div class="addon-card-icon">\
                            <i class="ti ' + icon + '"></i>\
                        </div>\
                        <div class="addon-card-name">' + (item.name || item.code || "Add-on") + '</div>\
                        <div class="addon-card-price">' + price + '</div>\
                        <div class="addon-card-price-unit">/ ' + unitName + '</div>\
                        <div class="addon-card-description">' + (item.description || "") + '</div>\
                    </div>\
                </div>';
            }).join("");

            // Attach card click handlers
            var cards = cardsGrid.querySelectorAll(".addon-card");
            cards.forEach(function (card) {
                card.addEventListener("click", function () {
                    var addonId = String(card.getAttribute("data-addon-id") || "");
                    selectAddon(addonId);
                });
                card.addEventListener("keydown", function (e) {
                    if (e.key === "Enter" || e.key === " ") {
                        e.preventDefault();
                        card.click();
                    }
                });
            });
        }
    }

    function selectAddon(addonId) {
        var cardsGrid = document.getElementById("addon-cards-grid");
        if (cardsGrid) {
            // Remove previous selection
            var previousSelected = cardsGrid.querySelector(".addon-card.is-selected");
            if (previousSelected) {
                previousSelected.classList.remove("is-selected");
            }
            // Select new card
            var selectedCard = cardsGrid.querySelector('[data-addon-id="' + addonId + '"]');
            if (selectedCard) {
                selectedCard.classList.add("is-selected");
            }
        }

        // Update hidden select value
        if (addonSelect) {
            addonSelect.value = addonId;
        }

        // Enable submit button
        var submitBtn = document.querySelector("[data-checkout-addon-submit]");
        if (submitBtn) {
            submitBtn.disabled = !addonId;
        }
    }

    async function submitCheckout(event) {
        event.preventDefault();
        clearFeedback();

        var packageId = pkgSelect ? String(pkgSelect.value || "").trim() : "";
        if (!packageId) {
            showFeedback("warning", "Pilih paket terlebih dulu.");
            return;
        }

        var payload = {
            package_uuid: packageId,
            billing_cycle: billingCycleValue(),
        };
        var billingEmail = billingEmailInput ? String(billingEmailInput.value || "").trim() : "";
        if (billingEmail) payload.billingEmail = billingEmail;

        try {
            setLoading(true);
            var res = await fetch("/v1/hcm/billing/checkout", {
                method: "POST",
                headers: buildHeaders({ "Content-Type": "application/json" }),
                credentials: "same-origin",
                body: JSON.stringify(payload),
            });
            var body = await res.json().catch(function () { return null; });
            if (!res.ok || !body || body.success !== true) {
                var msg = (body && body.error && body.error.message) ? body.error.message : "Gagal membuat invoice.";
                throw new Error(msg);
            }

            var paidInstantly = !!(body && body.data && body.data.invoice && body.data.invoice.isPaid);
            if (paidInstantly) {
                showFeedback("success", "Paket berhasil diaktifkan. Tidak ada pembayaran yang perlu dilakukan.");
            } else {
                showFeedback("success", body.data && body.data.reused ? "Invoice pending ditemukan. Silakan lanjut bayar." : "Invoice berhasil dibuat. Silakan lanjut bayar.");
            }
            renderInvoice(body.data, !!(body.data && body.data.reused));
            if (body.data && body.data.invoice && body.data.invoice.id) {
                await loadInvoiceById(body.data.invoice.id);
            }
            // Lock the form after invoice is shown — user must pay, not create another.
        } catch (e) {
            showFeedback("danger", e && e.message ? e.message : "Gagal membuat invoice.");
        } finally {
            setLoading(false);
        }
    }

    async function submitAddonCheckout(event) {
        event.preventDefault();
        clearFeedback();

        var addonId = addonSelect ? String(addonSelect.value || "").trim() : "";
        if (!addonId) {
            showFeedback("warning", "Pilih add-on terlebih dulu.");
            return;
        }

        var payload = {
            addon_id: Number(addonId),
        };
        var billingEmail = billingEmailInput ? String(billingEmailInput.value || "").trim() : "";
        if (billingEmail) payload.billingEmail = billingEmail;

        try {
            setAddonLoading(true);
            var res = await fetch("/v1/hcm/billing/addons/checkout", {
                method: "POST",
                headers: buildHeaders({ "Content-Type": "application/json" }),
                credentials: "same-origin",
                body: JSON.stringify(payload),
            });
            var body = await res.json().catch(function () { return null; });
            if (!res.ok || !body || body.success !== true) {
                var msg = (body && body.error && body.error.message) ? body.error.message : "Gagal membuat invoice add-on.";
                throw new Error(msg);
            }

            var paidInstantly = !!(body && body.data && body.data.invoice && body.data.invoice.isPaid);
            if (paidInstantly) {
                showFeedback("success", "Add-on berhasil diaktifkan. Tidak ada pembayaran yang perlu dilakukan.");
            } else {
                showFeedback("success", body.data && body.data.reused ? "Invoice pending ditemukan. Silakan lanjut bayar." : "Invoice add-on berhasil dibuat. Silakan lanjut bayar.");
            }

            renderInvoice(body.data, !!(body.data && body.data.reused));
            if (body.data && body.data.invoice && body.data.invoice.id) {
                await loadInvoiceById(body.data.invoice.id);
            }
        } catch (e) {
            showFeedback("danger", e && e.message ? e.message : "Gagal membuat invoice add-on.");
        } finally {
            setAddonLoading(false);
        }
    }

    async function boot() {
        try {
            await loadMeAndRenderContext();
            if (isActiveOnly) {
                clearFeedback();
                return;
            }
            if (payNowBtn) payNowBtn.addEventListener("click", payCurrentInvoice);
            var handledHostedReturn = await handleHostedReturn();
            if (handledHostedReturn) {
                return;
            }

            var pendingInvoice = await loadPendingInvoice();
            if (pendingInvoice || hasPreloadedPendingInvoice || isCreationLocked) {
                return;
            }

            await loadPackages();
            await loadAddons();
            if (form) form.addEventListener("submit", submitCheckout);
            var addonForm = document.querySelector("[data-checkout-form].checkout-addon-form");
            if (addonForm) addonForm.addEventListener("submit", submitAddonCheckout);
        } catch (e) {
            showFeedback("danger", e && e.message ? e.message : "Gagal memuat halaman checkout.");
        }
    }

    boot();
})(window, document);

