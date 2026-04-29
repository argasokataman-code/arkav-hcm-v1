export function loadPricingPlansScreenModule(deps, root) {
    var qs = deps.qs;
    var apiGet = deps.apiGet;
    var apiPost = deps.apiPost;
    var apiPut = deps.apiPut;
    var parseApiError = deps.parseApiError;
    var showError = deps.showError;
    var getCurrentMonthValue = deps.getCurrentMonthValue;
    var formatMoney = deps.formatMoney;
    var formatDate = deps.formatDate;
    var toTitleCase = deps.toTitleCase;
    var escapeHtml = deps.escapeHtml;
    var renderPlatformReport = deps.renderPlatformReport;

    var addonEditState = { id: null };

    function renderSubscriptionPlansTable(plans) {
        var tbody = qs("[data-pricing-plans-table]", root);
        if (!tbody) { return; }
        if (!plans.length) {
            tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-4">Belum ada subscription plan. <a href="/saas/packages">Buat plan baru</a> di halaman Packages.</td></tr>';
            return;
        }
        tbody.innerHTML = plans.map(function (plan) {
            var statusClass = plan.status === "active"
                ? "badge bg-success-subtle text-success"
                : "badge bg-secondary-subtle text-secondary";
            var monthlyLabel = plan.monthlyPrice > 0 ? formatMoney(plan.monthlyPrice) : "-";
            var yearlyLabel = plan.yearlyPrice > 0 ? formatMoney(plan.yearlyPrice) : "-";
            var featureCount = Array.isArray(plan.features) ? plan.features.length : 0;
            var featureLabel = featureCount > 0 ? featureCount + " fitur" : "-";
            return "<tr>"
                + '<td><div class="fw-semibold">' + escapeHtml(plan.name || "-") + '</div><small class="text-muted">' + escapeHtml(plan.code || "") + "</small></td>"
                + "<td>" + escapeHtml(monthlyLabel) + "</td>"
                + "<td>" + escapeHtml(yearlyLabel) + "</td>"
                + '<td><span class="text-muted small">' + escapeHtml(toTitleCase(plan.billingUnit || "flat")) + "</span></td>"
                + '<td><span class="text-muted small">' + escapeHtml(featureLabel) + "</span></td>"
                + '<td><span class="' + statusClass + '\">' + escapeHtml(toTitleCase(plan.status || "-")) + "</span></td>"
                + '<td><a href="/saas/packages" class="btn btn-sm btn-outline-primary"><i class="ti ti-external-link me-1"></i>Edit Plan</a></td>'
                + "</tr>";
        }).join("");
    }

    function renderAddonCatalogTable(addons, refreshFn) {
        var tbody = qs("[data-pricing-addons-table]", root);
        if (!tbody) { return; }
        if (!addons.length) {
            tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">Belum ada add-on. Klik <strong>Tambah Add-on</strong> untuk membuat yang baru.</td></tr>';
            return;
        }
        tbody.innerHTML = addons.map(function (addon) {
            var statusClass = addon.status === "active"
                ? "badge bg-success-subtle text-success"
                : "badge bg-secondary-subtle text-secondary";
            var toggleLabel = addon.status === "active" ? "Nonaktifkan" : "Aktifkan";
            var toggleCls = addon.status === "active" ? "btn-outline-warning" : "btn-outline-success";
            return "<tr>"
                + '<td><div class="fw-semibold">' + escapeHtml(addon.name || "-") + '</div><small class="text-muted">' + escapeHtml(addon.code || "") + "</small></td>"
                + "<td>" + escapeHtml(formatMoney(addon.pricePerUnit || 0)) + "</td>"
                + '<td><span class="text-muted small">' + escapeHtml(addon.unitName || "-") + "</span></td>"
                + '<td><span class="' + statusClass + '\">' + escapeHtml(toTitleCase(addon.status || "-")) + "</span></td>"
                + '<td><span class="text-muted small">' + escapeHtml(formatDate(addon.createdAt)) + "</span></td>"
                + '<td><div class="d-flex gap-1">'
                + '<button type="button" class="btn btn-sm btn-outline-primary"'
                + ' data-pricing-addon-edit="' + escapeHtml(String(addon.id)) + '"'
                + ' data-addon-code="' + escapeHtml(addon.code || "") + '"'
                + ' data-addon-name="' + escapeHtml(addon.name || "") + '"'
                + ' data-addon-description="' + escapeHtml(addon.description || "") + '"'
                + ' data-addon-price="' + escapeHtml(String(addon.pricePerUnit || 0)) + '"'
                + ' data-addon-unit-name="' + escapeHtml(addon.unitName || "") + '"'
                + ' data-addon-status="' + escapeHtml(addon.status || "active") + '"'
                + ' data-bs-toggle="modal" data-bs-target="#addonCrudModal">Edit</button>'
                + '<button type="button" class="btn btn-sm ' + toggleCls + '"'
                + ' data-pricing-addon-toggle="' + escapeHtml(String(addon.id)) + '"'
                + ' data-addon-status="' + escapeHtml(addon.status || "active") + '">'
                + escapeHtml(toggleLabel) + "</button>"
                + "</div></td>"
                + "</tr>";
        }).join("");

        Array.prototype.slice.call(tbody.querySelectorAll("[data-pricing-addon-toggle]")).forEach(function (btn) {
            btn.addEventListener("click", function () {
                var addonId = btn.getAttribute("data-pricing-addon-toggle");
                var currentStatus = btn.getAttribute("data-addon-status");
                var newStatus = currentStatus === "active" ? "inactive" : "active";
                btn.disabled = true;
                apiPut("/saas/package-addons/" + addonId, { status: newStatus }).then(function () {
                    refreshFn();
                }).catch(function (error) {
                    var parsed = parseApiError(error, "Gagal mengubah status add-on.");
                    showError(root, parsed.message);
                    btn.disabled = false;
                });
            });
        });

        Array.prototype.slice.call(tbody.querySelectorAll("[data-pricing-addon-edit]")).forEach(function (btn) {
            btn.addEventListener("click", function () {
                var addonId = btn.getAttribute("data-pricing-addon-edit");
                var form = qs("[data-pricing-addon-form]", root);
                if (!form) { return; }
                addonEditState.id = addonId;
                var modalTitle = document.getElementById("addonCrudModalLabel");
                if (modalTitle) { modalTitle.textContent = "Edit Add-on"; }
                var codeField = document.getElementById("addonCodeField");
                if (codeField) { codeField.style.display = "none"; }
                form.querySelector('[name="addon_id"]').value = addonId;
                form.querySelector('[name="code"]').value = btn.getAttribute("data-addon-code") || "";
                form.querySelector('[name="name"]').value = btn.getAttribute("data-addon-name") || "";
                form.querySelector('[name="description"]').value = btn.getAttribute("data-addon-description") || "";
                form.querySelector('[name="price_per_unit"]').value = btn.getAttribute("data-addon-price") || "";
                form.querySelector('[name="unit_name"]').value = btn.getAttribute("data-addon-unit-name") || "";
                form.querySelector('[name="status"]').value = btn.getAttribute("data-addon-status") || "active";
                var errorNode = qs("[data-pricing-addon-form-error]", form);
                if (errorNode) { errorNode.classList.add("d-none"); }
            });
        });
    }

    function bindAddonCRUD(refreshFn) {
        var form = qs("[data-pricing-addon-form]", root);
        var createBtn = qs("[data-pricing-addon-create]", root);
        if (!form) { return; }

        if (createBtn) {
            createBtn.addEventListener("click", function () {
                addonEditState.id = null;
                form.reset();
                form.querySelector('[name="addon_id"]').value = "";
                var modalTitle = document.getElementById("addonCrudModalLabel");
                if (modalTitle) { modalTitle.textContent = "Tambah Add-on"; }
                var codeField = document.getElementById("addonCodeField");
                if (codeField) { codeField.style.display = ""; }
                var errorNode = qs("[data-pricing-addon-form-error]", form);
                if (errorNode) { errorNode.classList.add("d-none"); }
            });
        }

        form.addEventListener("submit", function (event) {
            event.preventDefault();
            var errorNode = qs("[data-pricing-addon-form-error]", form);
            if (errorNode) { errorNode.classList.add("d-none"); }
            var submitBtn = qs("[data-pricing-addon-submit]", root);
            if (submitBtn) { submitBtn.disabled = true; }

            var addonId = String((form.querySelector('[name="addon_id"]') || {}).value || "").trim();
            var isCreate = !addonId;

            var nameVal = String((form.querySelector('[name="name"]') || {}).value || "").trim();
            var descVal = String((form.querySelector('[name="description"]') || {}).value || "").trim();
            var priceVal = Number((form.querySelector('[name="price_per_unit"]') || {}).value || 0);
            var unitVal = String((form.querySelector('[name="unit_name"]') || {}).value || "").trim();
            var statusVal = String((form.querySelector('[name="status"]') || {}).value || "active");

            if (!nameVal || !unitVal) {
                if (errorNode) {
                    errorNode.textContent = "Nama add-on dan nama unit wajib diisi.";
                    errorNode.classList.remove("d-none");
                }
                if (submitBtn) { submitBtn.disabled = false; }
                return;
            }

            var payload = {
                name: nameVal,
                description: descVal || null,
                price_per_unit: priceVal,
                unit_name: unitVal,
                status: statusVal,
            };

            if (isCreate) {
                var codeVal = String((form.querySelector('[name="code"]') || {}).value || "").trim();
                if (!codeVal) {
                    if (errorNode) {
                        errorNode.textContent = "Kode add-on wajib diisi saat membuat baru.";
                        errorNode.classList.remove("d-none");
                    }
                    if (submitBtn) { submitBtn.disabled = false; }
                    return;
                }
                payload.code = codeVal;
            }

            var request = isCreate
                ? apiPost("/saas/package-addons", payload)
                : apiPut("/saas/package-addons/" + addonId, payload);

            request.then(function (response) {
                if (!response.success) {
                    throw new Error(isCreate ? "Gagal membuat add-on." : "Gagal memperbarui add-on.");
                }
                form.reset();
                addonEditState.id = null;
                var modal = document.getElementById("addonCrudModal");
                if (modal && window.bootstrap && window.bootstrap.Modal) {
                    var bsModal = window.bootstrap.Modal.getInstance(modal);
                    if (bsModal) { bsModal.hide(); }
                }
                refreshFn();
            }).catch(function (error) {
                var parsed = parseApiError(error, isCreate ? "Gagal membuat add-on." : "Gagal memperbarui add-on.");
                if (errorNode) {
                    errorNode.textContent = parsed.message;
                    errorNode.classList.remove("d-none");
                } else {
                    showError(root, parsed.message);
                }
            }).finally(function () {
                if (submitBtn) { submitBtn.disabled = false; }
            });
        });
    }

    var reportMonthInput = qs("[data-tax-platform-report-month]", root);
    var reportMonth = reportMonthInput && reportMonthInput.value ? reportMonthInput.value : getCurrentMonthValue();

    Promise.allSettled([
        apiGet("/saas/packages", { status: "all", per_page: 100 }),
        apiGet("/saas/package-addons", { status: "all", per_page: 100 }),
        apiGet("/hcm/tax-governance/platform-billing/reports", { month: reportMonth }),
    ]).then(function (results) {
        var plansResult = results[0];
        var addonsResult = results[1];
        var reportResult = results[2];

        if (plansResult.status === "fulfilled" && plansResult.value && plansResult.value.success) {
            renderSubscriptionPlansTable(Array.isArray(plansResult.value.data) ? plansResult.value.data : []);
        } else {
            var plansTbody = qs("[data-pricing-plans-table]", root);
            if (plansTbody) {
                plansTbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-4">Gagal memuat subscription plans.</td></tr>';
            }
        }

        if (addonsResult.status === "fulfilled" && addonsResult.value && addonsResult.value.success) {
            var addons = Array.isArray(addonsResult.value.data) ? addonsResult.value.data : [];
            renderAddonCatalogTable(addons, function () { loadPricingPlansScreenModule(deps, root); });
            bindAddonCRUD(function () { loadPricingPlansScreenModule(deps, root); });
        } else {
            var addonsTbody = qs("[data-pricing-addons-table]", root);
            if (addonsTbody) {
                addonsTbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">Gagal memuat add-on catalog.</td></tr>';
            }
        }

        if (reportResult.status === "fulfilled" && reportResult.value && reportResult.value.success) {
            renderPlatformReport(root, reportResult.value);
        }
    }).catch(function (error) {
        var parsed = parseApiError(error, "Gagal memuat Pricing & Plans.");
        showError(root, parsed.message);
    });
}