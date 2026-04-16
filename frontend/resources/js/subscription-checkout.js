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

    function renderInvoice(data, reused) {
        if (!data || !data.invoice) return;
        if (invoiceHint) invoiceHint.classList.add("d-none");
        if (invoiceBox) invoiceBox.classList.remove("d-none");
        if (invoiceTitle) invoiceTitle.textContent = reused ? "Invoice pending ditemukan" : "Invoice dibuat";
        if (invoiceSubtitle) {
            invoiceSubtitle.textContent = "Invoice #" + (data.invoice.invoiceNumber || data.invoice.id);
        }
        if (invoiceAmount) invoiceAmount.textContent = formatRupiah(data.invoice.amountDue);
        if (invoiceDue) invoiceDue.textContent = data.invoice.dueDate ? ("Jatuh tempo: " + data.invoice.dueDate) : "—";
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

        if (companyNameInput) companyNameInput.value = activeCompany.name || "";
        if (companyIdInput) companyIdInput.value = String(activeCompany.id || "");
        if (companyCodeInput) companyCodeInput.value = String(activeCompany.code || "");
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
            return '<option value="' + String(p.id) + '" data-code="' + String(p.code || "") + '">' +
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
            package_id: Number(packageId),
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
        } catch (e) {
            showFeedback("danger", e && e.message ? e.message : "Gagal memuat halaman checkout.");
        }
    }

    boot();
})(window, document);

