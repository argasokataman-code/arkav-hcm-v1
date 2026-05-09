(function (window, document) {
    "use strict";

    var root = document.querySelector("[data-company-invoices-page]");
    if (!root) return;

    var listContainer = document.querySelector("[data-company-invoices-list-container]");
    var feedback = document.querySelector("[data-company-invoices-feedback]");
    var modalEl = document.querySelector("[data-company-invoice-modal]");
    var searchInput = document.getElementById("search_invoices");
    var statusFilter = document.getElementById("filter_invoice_status");
    var paidFilter = document.getElementById("filter_invoice_paid");
    var resetFiltersBtn = document.getElementById("btn_reset_invoice_filters");
    var totalDueNode = document.getElementById("total_due");
    var countUnpaidNode = document.getElementById("count_unpaid");
    var countOverdueNode = document.getElementById("count_overdue");
    var paidThisMonthNode = document.getElementById("paid_this_month");
    var downloadBtn = document.querySelector("[data-company-invoice-download]");
    var printBtn = document.querySelector("[data-company-invoice-print]");
    var modal = null;
    var currentInvoice = null;
    var searchTimer = null;
    var manualModalOpen = false;
    var invoiceSettingsCache = null;
    var invoiceSettingsRequest = null;
    try {
        if (modalEl && window.bootstrap) {
            modal = window.bootstrap.Modal.getOrCreateInstance(modalEl);
        }
    } catch (_e) {}

    function esc(v) {
        return String(v || "")
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#39;");
    }

    function fmtMoney(v) {
        var n = Number(v || 0);
        try {
            return new Intl.NumberFormat("id-ID", { style: "currency", currency: "IDR", maximumFractionDigits: 0 }).format(n);
        } catch (_e) {
            return "Rp " + String(n);
        }
    }

    function fmtDate(v) {
        if (!v) return "-";
        try {
            return new Intl.DateTimeFormat("id-ID", { day: "2-digit", month: "short", year: "numeric" }).format(new Date(v));
        } catch (_e) {
            return String(v);
        }
    }

    function badge(status) {
        var s = String(status || "").toLowerCase();
        var map = { draft: "secondary", sent: "info", viewed: "info", paid: "success", expired: "danger" };
        return '<span class="badge bg-' + (map[s] || "secondary") + '">' + esc(s || "-") + "</span>";
    }

    function parseInvoiceNotes(inv) {
        if (!inv || !inv.notes) return null;
        var raw = String(inv.notes || "").trim();
        if (!raw || raw.charAt(0) !== "{") return null;
        try {
            return JSON.parse(raw);
        } catch (_e) {
            return null;
        }
    }

    function invoiceSource(inv) {
        var notes = parseInvoiceNotes(inv);
        return notes && notes.source ? String(notes.source) : "";
    }

    function addonMeta(inv) {
        var notes = parseInvoiceNotes(inv);
        var pricing = notes && notes.pricing_breakdown && typeof notes.pricing_breakdown === "object"
            ? notes.pricing_breakdown
            : null;
        if (!pricing) return null;
        if (!pricing.addon_code && !pricing.addon_name) return null;
        return {
            code: String(pricing.addon_code || "").trim(),
            name: String(pricing.addon_name || "Add-on").trim(),
            unitName: String(pricing.unit_name || "tenant / month").trim(),
        };
    }

    function isAddonInvoice(inv) {
        var source = invoiceSource(inv);
        if (source === "tenant_addon_checkout") return true;
        return !!addonMeta(inv);
    }

    function previewPill(label, tone) {
        return '<span class="company-invoice-pill company-invoice-pill--' + esc(tone || "muted") + '">' + esc(label || "-") + "</span>";
    }

    function invoiceStatusTone(status) {
        var s = String(status || "").toLowerCase();
        if (s === "paid") return "success";
        if (s === "sent" || s === "viewed") return "info";
        if (s === "expired") return "danger";
        if (s === "draft") return "muted";
        return "muted";
    }

    function paymentTone(isPaid) {
        return isPaid ? "success" : "warning";
    }

    function invoiceLineLabel(inv) {
        if (isAddonInvoice(inv)) {
            var addon = addonMeta(inv);
            return (addon && addon.name ? addon.name : "Add-on") + " billing";
        }
        if (inv && inv.packageName) {
            return inv.packageName + " billing";
        }
        return inv && inv.subscriptionId ? "Subscription billing" : "Company invoice";
    }

    function invoiceLineCaption(inv) {
        if (isAddonInvoice(inv)) {
            var addon = addonMeta(inv);
            var addonName = addon && addon.name ? addon.name : "Add-on";
            var unit = addon && addon.unitName ? addon.unitName : "tenant / month";
            var renewalText = inv && inv.subscriptionId
                ? " Setelah paid, add-on akan ikut pada tagihan renewal berikutnya."
                : "";
            return "Tagihan aktivasi add-on " + addonName + " (" + unit + ")." + renewalText;
        }
        if (inv && inv.subscriptionId) {
            var detail = [];
            if (inv.packageName) detail.push("Package " + inv.packageName);
            if (inv.billingCycleLabel) detail.push("cycle " + inv.billingCycleLabel.toLowerCase());
            if (inv.nextBillingDate) detail.push("next payment " + fmtDate(inv.nextBillingDate));
            return "Tagihan untuk aktivasi atau perpanjangan subscription company yang sedang berjalan." + (detail.length ? " " + detail.join(" · ") + "." : "");
        }
        return "Tagihan layanan company sesuai invoice yang diterbitkan oleh sistem billing.";
    }

    function invoiceGuidance(inv) {
        if (isAddonInvoice(inv)) {
            if (inv && inv.isPaid) {
                return "Pembayaran add-on sudah diterima. Fitur add-on aktif untuk tenant saat ini dan akan diperhitungkan pada billing renewal berikutnya.";
            }
            return "Selesaikan pembayaran add-on agar fitur aktif. Invoice add-on dipisah dari invoice bulanan agar detail pembelian tetap jelas.";
        }
        if (inv && inv.isPaid) {
            if (inv.nextBillingDate) {
                return "Pembayaran sudah diterima. Simpan invoice ini sebagai bukti billing resmi. Penagihan berikutnya dijadwalkan pada " + fmtDate(inv.nextBillingDate) + ".";
            }
            return "Pembayaran sudah diterima. Simpan invoice ini sebagai bukti billing resmi untuk company Anda.";
        }
        if (inv && inv.nextBillingDate) {
            return "Segera selesaikan pembayaran sebelum jatuh tempo agar layanan company tetap aktif. Siklus pembayaran berikutnya dijadwalkan pada " + fmtDate(inv.nextBillingDate) + ".";
        }
        return "Segera selesaikan pembayaran sebelum jatuh tempo agar layanan company tetap aktif tanpa gangguan.";
    }

    function packageSummary(inv) {
        if (isAddonInvoice(inv)) {
            var addon = addonMeta(inv);
            var addonName = addon && addon.name ? addon.name : "Add-on";
            return addonName + " · Add-on";
        }
        var bits = [];
        if (inv && inv.packageName) bits.push(inv.packageName);
        if (inv && inv.billingCycleLabel) bits.push(inv.billingCycleLabel);
        if (!bits.length && inv && inv.subscriptionId) bits.push("Recurring billing");
        if (!bits.length) bits.push("One-time invoice");
        return bits.join(" · ");
    }

    function nextBillingSummary(inv) {
        if (isAddonInvoice(inv)) {
            if (inv && inv.subscriptionId && inv.nextBillingDate) {
                return "Masuk renewal " + fmtDate(inv.nextBillingDate);
            }
            return "Tagihan add-on terpisah";
        }
        if (inv && inv.nextBillingDate) {
            return "Next payment " + fmtDate(inv.nextBillingDate);
        }
        return inv && inv.subscriptionId ? "Next payment belum terjadwal" : "One-time invoice";
    }

    function currentPeriodLabel(inv) {
        if (inv && inv.currentPeriodStart && inv.currentPeriodEnd) {
            return fmtDate(inv.currentPeriodStart) + " - " + fmtDate(inv.currentPeriodEnd);
        }
        if (inv && inv.currentPeriodEnd) {
            return "Until " + fmtDate(inv.currentPeriodEnd);
        }
        return "-";
    }

    function parseBool(v, fallback) {
        if (v === true || v === 1 || v === "1") return true;
        if (v === false || v === 0 || v === "0") return false;
        return !!fallback;
    }

    function defaultInvoiceSettings() {
        return {
            invoice_prefix: "INV-",
            invoice_due_days: "30",
            invoice_show_tax: "1",
            invoice_round_off_enabled: "0",
            invoice_round_off: "none",
            invoice_header_terms: "",
            invoice_footer_terms: "",
        };
    }

    function fetchInvoiceSettings() {
        if (invoiceSettingsCache) {
            return Promise.resolve(invoiceSettingsCache);
        }
        if (invoiceSettingsRequest) {
            return invoiceSettingsRequest;
        }

        invoiceSettingsRequest = api("get", "/hcm/invoice-settings")
            .then(function (payload) {
                if (payload && payload.success === true && payload.data) {
                    invoiceSettingsCache = Object.assign(defaultInvoiceSettings(), payload.data);
                } else {
                    invoiceSettingsCache = defaultInvoiceSettings();
                }
                return invoiceSettingsCache;
            })
            .catch(function () {
                invoiceSettingsCache = defaultInvoiceSettings();
                return invoiceSettingsCache;
            })
            .finally(function () {
                invoiceSettingsRequest = null;
            });

        return invoiceSettingsRequest;
    }

    function paidDateValue(inv) {
        return inv && (inv.paidDate || inv.updatedAt) ? (inv.paidDate || inv.updatedAt) : "-";
    }

    function extractNotesFromObject(obj) {
        if (!obj || typeof obj !== "object") return "";
        var preferredKeys = ["notes", "note", "message", "summary", "description", "reason", "title"];
        for (var i = 0; i < preferredKeys.length; i += 1) {
            var key = preferredKeys[i];
            var value = obj[key];
            if (typeof value === "string" && value.trim()) {
                return value.trim();
            }
        }
        return "";
    }

    function normalizeInvoiceNotes(rawNotes) {
        var fallback = "Tidak ada catatan tambahan untuk invoice ini.";
        if (rawNotes == null) return fallback;

        var text = String(rawNotes).trim();
        if (!text) return fallback;

        var parsed = null;
        var startsLikeJson = (text.charAt(0) === "{" && text.charAt(text.length - 1) === "}") ||
            (text.charAt(0) === "[" && text.charAt(text.length - 1) === "]");
        if (startsLikeJson) {
            try {
                parsed = JSON.parse(text);
            } catch (_e) {
                parsed = null;
            }
        }

        if (parsed && typeof parsed === "object") {
            var fromObject = extractNotesFromObject(parsed);
            if (fromObject) {
                text = fromObject;
            } else {
                return "Catatan teknis tersimpan pada metadata invoice.";
            }
        }

        var looksTechnical = /(select\s+.+from|insert\s+into|update\s+.+set|delete\s+from|\bwhere\b|https?:\/\/|\?.+=.+|\b(v1|api)\/|[{\["].*[:=].*[}\]"])/i.test(text);
        if (looksTechnical) {
            return "Catatan teknis tersimpan pada metadata invoice.";
        }

        return text.length > 280 ? text.slice(0, 280).trim() + "..." : text;
    }

    function showFeedback(message) {
        if (!feedback) return;
        feedback.textContent = message;
        feedback.classList.remove("d-none");
    }

    function clearFeedback() {
        if (!feedback) return;
        feedback.textContent = "";
        feedback.classList.add("d-none");
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
        if (extra) Object.keys(extra).forEach(function (key) { headers[key] = extra[key]; });
        return headers;
    }

    function buildUrl(path, payload) {
        var url = "/v1" + String(path || "");
        if (!payload || typeof payload !== "object") return url;
        var params = new URLSearchParams();
        Object.keys(payload).forEach(function (key) {
            var value = payload[key];
            if (value == null || value === "") return;
            params.append(key, String(value));
        });
        var query = params.toString();
        return query ? (url + "?" + query) : url;
    }

    function triggerBlobDownload(blob, filename) {
        var link = document.createElement("a");
        link.href = URL.createObjectURL(blob);
        link.download = filename || "download";
        document.body.appendChild(link);
        link.click();
        link.remove();
        window.setTimeout(function () {
            try {
                URL.revokeObjectURL(link.href);
            } catch (_e) {}
        }, 2000);
    }

    function downloadBinary(path, filename) {
        var url = buildUrl(path);
        return fetch(url, {
            method: "GET",
            headers: buildHeaders({ Accept: "application/octet-stream, */*" }),
            credentials: "same-origin",
        }).then(function (response) {
            return response.blob().then(function (blob) {
                var ct = response.headers.get("content-type") || "";
                if (!response.ok) {
                    if (ct.indexOf("application/json") !== -1) {
                        return blob.text().then(function (text) {
                            var data = {};
                            try {
                                data = JSON.parse(text);
                            } catch (_e) {}
                            throw { response: { status: response.status, data: data } };
                        });
                    }
                    throw { response: { status: response.status } };
                }
                if (ct.indexOf("application/json") !== -1) {
                    return blob.text().then(function (text) {
                        var data = {};
                        try {
                            data = JSON.parse(text);
                        } catch (_e) {}
                        throw { response: { status: response.status, data: data } };
                    });
                }
                triggerBlobDownload(blob, filename);
            });
        });
    }

    function parseError(err) {
        if (err && err.response && err.response.data && err.response.data.error && err.response.data.error.message) {
            return String(err.response.data.error.message);
        }
        if (err && err.data && err.data.error && err.data.error.message) {
            return String(err.data.error.message);
        }
        if (err && err.message) {
            return String(err.message);
        }
        return "Gagal memuat invoice company.";
    }

    function redirectTo(url) {
        var target = String(url || "").trim();
        if (!target) return;
        if (typeof window.__ARCAV_TEST_REDIRECT__ === "function") {
            window.__ARCAV_TEST_REDIRECT__(target);
            return;
        }
        window.location.href = target;
    }

    function isSameMonth(dateValue) {
        if (!dateValue) return false;
        var date = new Date(dateValue);
        var now = new Date();
        return date.getFullYear() === now.getFullYear() && date.getMonth() === now.getMonth();
    }

    function isPastDue(dateValue) {
        if (!dateValue) return false;
        return new Date(dateValue) < new Date();
    }

    function applySummary(rows) {
        var items = Array.isArray(rows) ? rows : [];
        var totalDue = 0;
        var unpaidCount = 0;
        var overdueCount = 0;
        var paidThisMonth = 0;

        items.forEach(function (row) {
            var amount = Number(row && row.amountDue ? row.amountDue : 0);
            if (!row || row.isPaid) {
                if (row && row.isPaid && isSameMonth(row.paidDate || row.updatedAt)) {
                    paidThisMonth += amount;
                }
                return;
            }

            totalDue += amount;
            unpaidCount += 1;
            if (isPastDue(row.dueDate)) {
                overdueCount += 1;
            }
        });

        if (totalDueNode) totalDueNode.textContent = fmtMoney(totalDue);
        if (countUnpaidNode) countUnpaidNode.textContent = String(unpaidCount);
        if (countOverdueNode) countOverdueNode.textContent = String(overdueCount);
        if (paidThisMonthNode) paidThisMonthNode.textContent = fmtMoney(paidThisMonth);
    }

    function renderState(message) {
        if (!listContainer) return;
        listContainer.innerHTML = '<div class="card"><div class="card-body text-center text-muted py-4">' + esc(message) + '</div></div>';
    }

    function openModal() {
        if (modal && typeof modal.show === "function") {
            modal.show();
            return;
        }
        if (!modalEl) return;
        manualModalOpen = true;
        modalEl.classList.add("show");
        modalEl.style.display = "block";
        modalEl.removeAttribute("aria-hidden");
        modalEl.setAttribute("aria-modal", "true");
        document.body.classList.add("modal-open");
    }

    function closeModal() {
        if (modal && typeof modal.hide === "function") {
            modal.hide();
            return;
        }
        if (!modalEl) return;
        manualModalOpen = false;
        modalEl.classList.remove("show");
        modalEl.style.display = "none";
        modalEl.setAttribute("aria-hidden", "true");
        modalEl.removeAttribute("aria-modal");
        document.body.classList.remove("modal-open");
    }

    function api(method, path, payload) {
        if (!window.AuthApi || typeof window.AuthApi.request !== "function") {
            var httpMethod = String(method || "GET").toUpperCase();
            var options = {
                method: httpMethod,
                headers: buildHeaders(),
                credentials: "same-origin",
            };

            var requestUrl = buildUrl(path, httpMethod === "GET" ? payload : null);
            if (httpMethod !== "GET" && payload != null) {
                options.headers["Content-Type"] = "application/json";
                options.body = JSON.stringify(payload);
            }

            return fetch(requestUrl, options).then(async function (res) {
                var body = await res.json().catch(function () { return null; });
                if (!res.ok) {
                    throw { status: res.status, data: body };
                }
                return body;
            });
        }
        return window.AuthApi.request(method, path, payload).then(function (res) {
            return res && res.data ? res.data : res;
        }).catch(function (err) {
            // AuthApi.request already handles 401/403 modals globally.
            throw err;
        });
    }

    function renderTable(rows) {
        if (!listContainer) return;
        var html = `
          <div class="card">
            <div class="custom-datatable-filter table-responsive">
              <table class="table">
                <thead class="thead-light">
                  <tr>
                    <th>Invoice</th>
                    <th>Issue</th>
                    <th>Due</th>
                    <th>Amount</th>
                    <th>Status</th>
                    <th>Paid</th>
                    <th class="text-end">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  ${(rows || []).map(function (r) {
                      var paid = r.isPaid ? '<span class="badge bg-success">paid</span>' : '<span class="badge bg-warning text-dark">unpaid</span>';
                                            var typeBadge = isAddonInvoice(r)
                                                    ? '<span class="badge bg-primary-subtle text-primary">addon</span>'
                                                    : '<span class="badge bg-light text-dark">subscription</span>';
                      return `
                        <tr>
                          <td>
                            <div class="fw-semibold">${esc(r.invoiceNumber || ("INV-" + r.id))}</div>
                                                        <div class="text-muted small d-flex align-items-center gap-2 flex-wrap">${esc(packageSummary(r))} ${typeBadge}</div>
                            <div class="text-muted small">#${esc(r.id)}</div>
                          </td>
                          <td>${esc(r.issueDate || "-")}</td>
                                                    <td>
                                                        <div>${esc(r.dueDate || "-")}</div>
                                                        <div class="text-muted small">${esc(nextBillingSummary(r))}</div>
                                                    </td>
                          <td class="fw-semibold">${esc(fmtMoney(r.amountDue))}</td>
                          <td>${badge(r.status)}</td>
                          <td>${paid}</td>
                          <td class="text-end">
                            <div class="d-inline-flex gap-2">
                              <button class="btn btn-sm btn-white" data-invoice-view="${esc(r.id)}"><i class="ti ti-eye"></i></button>
                                                            <button class="btn btn-sm btn-white" type="button" data-invoice-download="${esc(r.id)}" title="Download PDF">
                                <i class="ti ti-download"></i>
                                                            </button>
                              ${r.isPaid ? "" : `<button class="btn btn-sm btn-primary" data-invoice-mock-pay="${esc(r.id)}">Mock Pay</button>`}
                            </div>
                          </td>
                        </tr>
                      `;
                  }).join("") || '<tr><td colspan="7" class="text-center text-muted py-4">No invoices found.</td></tr>'}
                </tbody>
              </table>
            </div>
          </div>
        `;
        listContainer.innerHTML = html;
        applySummary(rows);
    }

    function fillModal(inv, invoiceSettings) {
        currentInvoice = inv || null;
        var settings = Object.assign(defaultInvoiceSettings(), invoiceSettings || {});

        var prefix = settings.invoice_prefix || "INV-";
        var dueDays = String(settings.invoice_due_days || "30");
        var taxShown = parseBool(settings.invoice_show_tax, true);
        var roundOffEnabled = parseBool(settings.invoice_round_off_enabled, false);
        var roundOffMode = settings.invoice_round_off || "none";
        var headerTerms = settings.invoice_header_terms || "-";
        var footerTerms = settings.invoice_footer_terms || "-";

        var termsSummary = [
            "Prefix " + prefix,
            "Due in " + dueDays + " days",
            "Tax " + (taxShown ? "shown" : "hidden"),
            "Round-off " + (roundOffEnabled ? roundOffMode : "disabled"),
        ].join(" | ");

        function set(sel, val) {
            var el = document.querySelector(sel);
            if (el) el.textContent = val;
        }
        set("[data-invoice-modal-number]", inv.invoiceNumber || ("INV-" + inv.id));
        var statusWrap = document.querySelector("[data-invoice-modal-status-wrap]");
        if (statusWrap) {
            statusWrap.innerHTML = previewPill(String(inv.status || "draft"), invoiceStatusTone(inv.status));
            var statusNode = statusWrap.querySelector(".company-invoice-pill");
            if (statusNode) statusNode.setAttribute("data-invoice-modal-status", "");
        }
        set("[data-invoice-modal-company]", inv.company || "-");
        set("[data-invoice-modal-package-name]", inv.packageName || (inv.subscriptionId ? "Subscription package" : "One-time invoice"));
        set("[data-invoice-modal-package-summary]", packageSummary(inv));
        set("[data-invoice-modal-summary]", invoiceLineCaption(inv));
        var paidWrap = document.querySelector("[data-invoice-modal-payment-status-wrap]");
        if (paidWrap) {
            paidWrap.innerHTML = previewPill(inv.isPaid ? "Paid" : "Unpaid", paymentTone(inv.isPaid));
            var paidNode = paidWrap.querySelector(".company-invoice-pill");
            if (paidNode) paidNode.setAttribute("data-invoice-modal-payment-status", "");
        }
        set("[data-invoice-modal-issue-date]", inv.issueDate || "-");
        set("[data-invoice-modal-due-date]", inv.dueDate || "-");
        set("[data-invoice-modal-paid-date]", paidDateValue(inv));
        set("[data-invoice-modal-billing-cycle]", inv.billingCycleLabel || "-");
        set("[data-invoice-modal-next-billing-date]", inv.nextBillingDate ? fmtDate(inv.nextBillingDate) : "-");
        set("[data-invoice-modal-current-period]", currentPeriodLabel(inv));
        set("[data-invoice-modal-amount]", fmtMoney(inv.amountDue));
        set("[data-invoice-modal-line-label]", invoiceLineLabel(inv));
        set("[data-invoice-modal-line-caption]", invoiceLineCaption(inv));
        var chargeStatusWrap = document.querySelector("[data-invoice-modal-charge-status-wrap]");
        if (chargeStatusWrap) {
            chargeStatusWrap.innerHTML = previewPill(String(inv.status || "draft"), invoiceStatusTone(inv.status));
            var chargeStatusNode = chargeStatusWrap.querySelector(".company-invoice-pill");
            if (chargeStatusNode) chargeStatusNode.setAttribute("data-invoice-modal-charge-status", "");
        }
        // Tax breakdown: show subtotal + tax line when billingTaxRateSnapshot > 0 and tax is shown in settings
        var taxRow = document.querySelector("[data-invoice-modal-tax-row]");
        var taxRateSnapshot = (inv && typeof inv.billingTaxRateSnapshot === "number") ? inv.billingTaxRateSnapshot : 0;
        var pb = (inv && inv.pricingBreakdown && typeof inv.pricingBreakdown === "object") ? inv.pricingBreakdown : null;
        var baseAmount = null;
        var taxAmount = null;
        if (taxShown && taxRateSnapshot > 0) {
            if (pb && pb.baseAmount != null && pb.subscriptionTaxAmount != null) {
                baseAmount = pb.baseAmount;
                taxAmount = pb.subscriptionTaxAmount;
            } else {
                var factor = 1 + taxRateSnapshot / 100;
                baseAmount = Math.round((inv.amountDue / factor) * 100) / 100;
                taxAmount = Math.round((inv.amountDue - baseAmount) * 100) / 100;
            }
        }
        if (baseAmount !== null && taxAmount !== null) {
            set("[data-invoice-modal-table-amount]", fmtMoney(baseAmount));
            set("[data-invoice-modal-tax-label]", "Pajak (PPN " + taxRateSnapshot + "%)");
            set("[data-invoice-modal-tax-amount]", fmtMoney(taxAmount));
            if (taxRow) taxRow.style.display = "";
        } else {
            set("[data-invoice-modal-table-amount]", fmtMoney(inv.amountDue));
            if (taxRow) taxRow.style.display = "none";
        }
        set("[data-invoice-modal-table-total]", fmtMoney(inv.amountDue));

        set("[data-invoice-modal-guidance]", invoiceGuidance(inv));
        set("[data-invoice-modal-terms-summary]", termsSummary);
        set("[data-invoice-modal-header-terms]", headerTerms);
        set("[data-invoice-modal-footer-terms]", footerTerms);
        set("[data-invoice-modal-notes]", normalizeInvoiceNotes(inv && inv.notes));
        if (downloadBtn) {
            downloadBtn.disabled = !inv || !inv.id;
        }
        if (printBtn) {
            printBtn.disabled = !inv || !inv.id;
        }
    }

    function printCurrentInvoice() {
        var rootNode = document.querySelector("[data-company-invoice-print-root]");
        if (!rootNode || !currentInvoice) return;
        var printWindow = window.open("", "_blank", "width=960,height=720");
        if (!printWindow) {
            showFeedback("Popup print diblokir browser. Izinkan popup lalu coba lagi.");
            return;
        }

        var styles = Array.prototype.slice.call(document.querySelectorAll("style"))
            .map(function (node) { return node.outerHTML; })
            .join("\n");

        printWindow.document.open();
        printWindow.document.write(
            '<!DOCTYPE html><html><head><title>' + esc(currentInvoice.invoiceNumber || ("invoice-" + currentInvoice.id)) + '</title>' +
            '<meta charset="UTF-8">' + styles +
            '<style>body{background:#fff;margin:0;padding:20px;font-family:Inter,Arial,sans-serif;} .modal-footer,.btn,.btn-close{display:none!important;} .company-invoice-preview{background:#fff;border:none;padding:0;} .company-invoice-preview__sheet{box-shadow:none;border:1px solid #e6ecf5;} @page{margin:16mm;} </style>' +
            '</head><body>' + rootNode.outerHTML + '</body></html>'
        );
        printWindow.document.close();
        printWindow.focus();
        printWindow.print();
        printWindow.close();
    }

    function buildQuery() {
        var query = { perPage: 50 };
        if (searchInput && searchInput.value.trim()) {
            query.search = searchInput.value.trim();
        }
        if (statusFilter && statusFilter.value) {
            query.status = statusFilter.value;
        }
        if (paidFilter && paidFilter.value !== "") {
            query.is_paid = paidFilter.value;
        }
        return query;
    }

    function triggerDownload(inv) {
        if (!inv || !inv.id) return;
        if (window.AuthApi && typeof window.AuthApi.downloadV1Binary === "function") {
            window.AuthApi.downloadV1Binary(
                "/hcm/billing/invoices/" + encodeURIComponent(inv.id) + "/download",
                (inv.invoiceNumber || ("invoice-" + inv.id)) + ".pdf"
            ).catch(function (err) {
                showFeedback(parseError(err));
            });
            return;
        }
        downloadBinary(
            "/hcm/billing/invoices/" + encodeURIComponent(inv.id) + "/download",
            (inv.invoiceNumber || ("invoice-" + inv.id)) + ".pdf"
        ).catch(function (err) {
            showFeedback(parseError(err));
        });
    }

    function bindActions() {
        if (!listContainer) return;
        listContainer.addEventListener("click", function (e) {
            var viewBtn = e.target.closest("[data-invoice-view]");
            if (viewBtn) {
                var id = viewBtn.getAttribute("data-invoice-view");
                Promise.all([
                    api("get", "/hcm/billing/invoices/" + encodeURIComponent(id)),
                    fetchInvoiceSettings(),
                ]).then(function (results) {
                    var payload = results[0];
                    var settings = results[1];
                    if (!payload || payload.success !== true) return;
                    fillModal(payload.data, settings);
                    openModal();
                }).catch(function (err) {
                    showFeedback(parseError(err));
                });
                return;
            }
            var downloadActionBtn = e.target.closest("[data-invoice-download]");
            if (downloadActionBtn) {
                var idDownload = downloadActionBtn.getAttribute("data-invoice-download");
                var invoiceNumber = downloadActionBtn.closest("tr")?.querySelector(".fw-semibold")?.textContent || ("invoice-" + idDownload);
                triggerDownload({ id: idDownload, invoiceNumber: invoiceNumber });
                return;
            }
            var payBtn = e.target.closest("[data-invoice-mock-pay]");
            if (payBtn) {
                var id2 = payBtn.getAttribute("data-invoice-mock-pay");
                api("post", "/hcm/billing/invoices/" + encodeURIComponent(id2) + "/mock-hosted-checkout", {}).then(function (payload) {
                    var hostedCheckoutUrl = payload && payload.flow ? String(payload.flow.hostedCheckoutUrl || "").trim() : "";
                    if (!payload || payload.success !== true || !hostedCheckoutUrl) {
                        throw new Error("Gagal membuka halaman mock payment.");
                    }
                    clearFeedback();
                    redirectTo(hostedCheckoutUrl);
                }).catch(function (err) {
                    showFeedback(parseError(err));
                });
                return;
            }
        });

        if (downloadBtn) {
            downloadBtn.addEventListener("click", function () {
                triggerDownload(currentInvoice);
            });
        }
        if (printBtn) {
            printBtn.addEventListener("click", function () {
                printCurrentInvoice();
            });
        }
        if (modalEl) {
            modalEl.addEventListener("click", function (event) {
                if (event.target && event.target.closest("[data-bs-dismiss='modal']")) {
                    closeModal();
                    return;
                }
                if (event.target === modalEl && manualModalOpen) {
                    closeModal();
                }
            });
        }
        document.addEventListener("keydown", function (event) {
            if (event.key === "Escape" && manualModalOpen) {
                closeModal();
            }
        });

        if (searchInput) {
            searchInput.addEventListener("input", function () {
                window.clearTimeout(searchTimer);
                searchTimer = window.setTimeout(load, 250);
            });
        }
        if (statusFilter) {
            statusFilter.addEventListener("change", load);
        }
        if (paidFilter) {
            paidFilter.addEventListener("change", load);
        }
        if (resetFiltersBtn) {
            resetFiltersBtn.addEventListener("click", function () {
                if (searchInput) searchInput.value = "";
                if (statusFilter) statusFilter.value = "";
                if (paidFilter) paidFilter.value = "";
                load();
            });
        }
    }

    function load() {
        clearFeedback();
        if (listContainer) {
            listContainer.innerHTML = '<div class="card"><div class="card-body text-center text-muted py-4"><i class="ti ti-loader-quarter fs-1 spin"></i> Loading invoices...</div></div>';
        }
        api("get", "/hcm/billing/invoices", buildQuery()).then(function (payload) {
            if (!payload || payload.success !== true) {
                renderTable([]);
                return;
            }
            renderTable(payload.data || []);
        }).catch(function (err) {
            renderState("Invoice belum tersedia atau gagal dimuat.");
            applySummary([]);
            showFeedback(parseError(err));
        });
    }

    bindActions();
    load();
})(window, document);

