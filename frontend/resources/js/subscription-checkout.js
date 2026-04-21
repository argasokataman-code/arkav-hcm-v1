(function (window, document) {
    "use strict";

    var root = document.querySelector("[data-subscription-checkout-page]");
    if (!root) return;

    var form = document.querySelector("[data-checkout-form]");
    var feedback = document.querySelector("[data-checkout-feedback]");
    var submitBtn = document.querySelector("[data-checkout-submit]");
    var pkgSelect = document.querySelector("[data-checkout-package-select]");
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
    var openInvoicesBtn = document.querySelector("[data-checkout-open-invoices]");
    var payNowBtn = document.querySelector("[data-checkout-pay-now]");
    var goDashboardBtn = document.querySelector("[data-checkout-go-dashboard]");
    var mockPayEnabled = String(root.getAttribute("data-checkout-mock-pay-enabled") || "0") === "1";
    var isPendingLock = String(root.getAttribute("data-checkout-pending-lock") || "0") === "1";
    var upgradeForm = document.querySelector("[data-checkout-form].checkout-upgrade-form") || form;
    var currentInvoice = null;

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

    function setPaying(isPaying) {
        if (!payNowBtn) return;
        payNowBtn.disabled = isPaying;
    }

    function formatRupiah(num) {
        var n = Number(num || 0);
        try {
            return new Intl.NumberFormat("id-ID", { style: "currency", currency: "IDR", maximumFractionDigits: 0 }).format(n);
        } catch (_e) {
            return "Rp " + String(n);
        }
    }

    function billingCycleValue() {
        var checked = document.querySelector("input[name='billing_cycle']:checked");
        return checked ? String(checked.value || "monthly") : "monthly";
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

    function updateInvoiceActions(invoice) {
        var unpaid = !!(invoice && !invoice.isPaid);

        if (openInvoicesBtn) {
            openInvoicesBtn.classList.remove("d-none");
        }

        if (payNowBtn) {
            payNowBtn.classList.toggle("d-none", !unpaid || !mockPayEnabled);
            payNowBtn.textContent = unpaid ? "Bayar sekarang" : "Sudah dibayar";
        }

        if (goDashboardBtn) {
            goDashboardBtn.classList.toggle("d-none", !(invoice && invoice.isPaid));
        }
    }

    function renderInvoice(data, reused) {
        if (!data || !data.invoice) return;
        currentInvoice = data.invoice;
        if (invoiceHint) invoiceHint.classList.add("d-none");
        if (invoiceBox) invoiceBox.classList.remove("d-none");
        if (invoiceTitle) {
            if (currentInvoice.isPaid) {
                invoiceTitle.textContent = "Invoice sudah dibayar";
            } else {
                invoiceTitle.textContent = reused ? "Invoice pending ditemukan" : "Invoice dibuat";
            }
        }
        if (invoiceSubtitle) {
            invoiceSubtitle.textContent = "Invoice #" + invoiceLabel(currentInvoice);
        }
        if (invoiceAmount) invoiceAmount.textContent = formatRupiah(currentInvoice.amountDue);
        if (invoiceDue) {
            invoiceDue.textContent = currentInvoice.isPaid
                ? (currentInvoice.paidDate ? ("Dibayar: " + currentInvoice.paidDate) : "Status: paid")
                : (currentInvoice.dueDate ? ("Jatuh tempo: " + currentInvoice.dueDate) : "—");
        }
        updateInvoiceActions(currentInvoice);

        // When tenant is pending_payment lock and a pending invoice already exists,
        // hide the "Buat invoice baru" form so user doesn't see two competing CTAs.
        if (isPendingLock && currentInvoice && !currentInvoice.isPaid && upgradeForm) {
            upgradeForm.classList.add("d-none");
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
                showFeedback("info", "Invoice pending ditemukan.");
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
        var status = String(params.get("mock_payment_status") || "").trim().toLowerCase();
        var invoiceId = String(params.get("invoice_id") || "").trim();
        if (!status || !invoiceId) {
            return false;
        }

        if (status === "completed") {
            await loadInvoiceById(invoiceId, "Pembayaran berhasil.", "success");
            return true;
        }

        if (status === "failed") {
            await loadInvoiceById(invoiceId, "Pembayaran belum berhasil. Coba lagi.", "warning");
            return true;
        }

        if (status === "pending") {
            await loadInvoiceById(invoiceId, "Pembayaran belum selesai.", "info");
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
            var hostedCheckoutUrl = payload && payload.flow ? String(payload.flow.hostedCheckoutUrl || "").trim() : "";
            if (!payload || payload.success !== true || !hostedCheckoutUrl) {
                throw new Error("Gagal membuka hosted payment gateway.");
            }
            clearFeedback();
            redirectTo(hostedCheckoutUrl);
        } catch (err) {
            var msg = err && err.data && err.data.error && err.data.error.message
                ? err.data.error.message
                : (err && err.message ? err.message : "Gagal membuka hosted payment gateway.");
            showFeedback("danger", msg);
        } finally {
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

            showFeedback("success", body.data && body.data.reused ? "Invoice pending ditemukan. Silakan lanjut bayar." : "Invoice berhasil dibuat. Silakan lanjut bayar.");
            renderInvoice(body.data, !!(body.data && body.data.reused));
        } catch (e) {
            showFeedback("danger", e && e.message ? e.message : "Gagal membuat invoice.");
        } finally {
            setLoading(false);
        }
    }

    async function boot() {
        try {
            await loadMeAndRenderContext();
            await loadPackages();
            if (form) form.addEventListener("submit", submitCheckout);
            if (payNowBtn) payNowBtn.addEventListener("click", payCurrentInvoice);
            var handledHostedReturn = await handleHostedReturn();
            if (!handledHostedReturn) {
                await loadPendingInvoice();
            }
        } catch (e) {
            showFeedback("danger", e && e.message ? e.message : "Gagal memuat halaman checkout.");
        }
    }

    boot();
})(window, document);

