(function (window, document) {
    "use strict";

    function byId(id) {
        return document.getElementById(id);
    }

    function esc(value) {
        return String(value == null ? "" : value)
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/\"/g, "&quot;")
            .replace(/'/g, "&#39;");
    }

    function money(value) {
        var amount = Number(value || 0);
        if (Number.isNaN(amount)) {
            return String(value || "-");
        }

        return new Intl.NumberFormat("id-ID", {
            style: "currency",
            currency: "IDR",
            maximumFractionDigits: 0,
        }).format(amount);
    }

    function formatDateTime(value) {
        if (!value) {
            return "-";
        }

        var date = new Date(value);
        if (Number.isNaN(date.getTime())) {
            return String(value);
        }

        return new Intl.DateTimeFormat("id-ID", {
            day: "2-digit",
            month: "short",
            year: "numeric",
            hour: "2-digit",
            minute: "2-digit",
            hour12: false,
            timeZone: "Asia/Jakarta",
        }).format(date) + " WIB";
    }

    function normalizeActionLabel(action) {
        var map = {
            upgrade: "Upgrade",
            downgrade: "Downgrade",
            cancel: "Cancel",
        };

        return map[String(action || "").toLowerCase()] || String(action || "-");
    }

    function normalizeStatusBadge(status) {
        var value = String(status || "").toLowerCase();
        var cls = "bg-secondary-subtle text-secondary";

        if (value === "pending") cls = "bg-warning-subtle text-warning";
        if (value === "approved") cls = "bg-info-subtle text-info";
        if (value === "applied") cls = "bg-success-subtle text-success";
        if (value === "rejected") cls = "bg-danger-subtle text-danger";
        if (value === "cancelled") cls = "bg-dark-subtle text-dark";

        return '<span class="badge ' + cls + '">' + esc(value || "unknown") + "</span>";
    }

    function normalizeAnomalyBadges(flags) {
        var list = Array.isArray(flags) ? flags : [];
        if (!list.length) {
            return '<span class="badge bg-success-subtle text-success">Tidak ada anomali</span>';
        }

        return list.map(function (rawFlag) {
            var flag = String(rawFlag || "").toUpperCase();
            var cls = 'bg-warning-subtle text-warning';
            var label = flag;

            if (flag === 'BILLING_OVERDUE_INVOICE') {
                cls = 'bg-danger-subtle text-danger';
                label = 'Invoice overdue';
            } else if (flag === 'BILLING_PARTIAL_PAYMENT') {
                cls = 'bg-warning-subtle text-warning';
                label = 'Partial payment';
            } else if (flag === 'BILLING_UNPAID_INVOICE') {
                cls = 'bg-warning-subtle text-warning';
                label = 'Invoice unpaid';
            } else if (flag === 'SUBSCRIPTION_NOT_ACTIVE') {
                cls = 'bg-info-subtle text-info';
                label = 'Subscription non-aktif';
            }

            return '<span class="badge ' + cls + '">' + esc(label) + '</span>';
        }).join(' ');
    }

    function normalizePackage(pkg) {
        var featureRows = Array.isArray(pkg && pkg.features) ? pkg.features : [];
        var featureCodes = Array.isArray(pkg && pkg.feature_codes) ? pkg.feature_codes : [];

        if (!featureRows.length && featureCodes.length) {
            featureRows = featureCodes.map(function (code) {
                return { code: code, isIncluded: true };
            });
        }

        return {
            uuid: String((pkg && (pkg.uuid || pkg.id)) || ""),
            code: String((pkg && pkg.code) || ""),
            name: String((pkg && pkg.name) || (pkg && pkg.code) || "Paket"),
            description: String((pkg && pkg.description) || ""),
            monthlyPrice: Number((pkg && (pkg.monthlyPrice != null ? pkg.monthlyPrice : pkg.monthly_price)) || 0),
            yearlyPrice: Number((pkg && (pkg.yearlyPrice != null ? pkg.yearlyPrice : pkg.yearly_price)) || 0),
            features: featureRows.map(function (feature) {
                return {
                    code: String((feature && (feature.code || feature.feature_code)) || ""),
                    name: String((feature && (feature.name || feature.feature_name)) || ""),
                    limit: feature && feature.limit != null ? Number(feature.limit) : null,
                    isIncluded: feature ? feature.isIncluded !== false : true,
                };
            }),
        };
    }

    function packageHasFeature(pkg, featureCode) {
        var wanted = String(featureCode || "").trim().toLowerCase();
        if (!wanted) {
            return false;
        }

        return (pkg.features || []).some(function (feature) {
            return String(feature.code || "").trim().toLowerCase() === wanted && feature.isIncluded !== false;
        });
    }

    function packageFeatureLimit(pkg, featureCode) {
        var wanted = String(featureCode || "").trim().toLowerCase();
        var match = (pkg.features || []).find(function (feature) {
            return String(feature.code || "").trim().toLowerCase() === wanted;
        });

        return match ? match.limit : null;
    }

    function findPackageByUuid(packages, uuid) {
        var wanted = String(uuid || "");
        return (packages || []).find(function (pkg) {
            return String(pkg.uuid || "") === wanted;
        }) || null;
    }

    function packageFromPreview(previewPkg, packages) {
        if (!previewPkg || typeof previewPkg !== "object") {
            return null;
        }

        var matched = findPackageByUuid(packages || [], previewPkg.uuid || "");
        if (matched) {
            return matched;
        }

        return normalizePackage({
            uuid: previewPkg.uuid,
            code: previewPkg.code,
            name: previewPkg.name,
            monthly_price: previewPkg.price,
            yearly_price: previewPkg.price,
            feature_codes: [],
            description: "",
        });
    }

    function apiRequest(method, path, payload) {
        if (!window.AuthApi || typeof window.AuthApi.request !== "function") {
            return Promise.reject(new Error("AuthApi is unavailable."));
        }

        return window.AuthApi.request(method, path, payload).then(function (response) {
            return response && response.data ? response.data : {};
        });
    }

    function setBlockedRecommendationHint(ctx, packageSelect) {
        var blockedFeature = String(ctx.blockedFeature || "").trim();
        if (!blockedFeature) {
            return;
        }

        var suggested = Array.isArray(ctx.recommendedPackages) ? ctx.recommendedPackages : [];
        if (!suggested.length) {
            return;
        }

        var candidate = suggested[0];
        var targetValue = String(candidate.uuid || "");
        var options = packageSelect.options || [];
        var found = false;
        for (var i = 0; i < options.length; i += 1) {
            if (String(options[i].value || "") === targetValue) {
                found = true;
                break;
            }
        }

        if (found) {
            packageSelect.value = targetValue;
        }
    }

    function renderSelectionSummary(target, action, currentPackage, targetPackage, blockedFeature, blockedFeatureLabel) {
        if (!target) {
            return;
        }

        var actionLabel = normalizeActionLabel(action);
        if (String(action || "") === "cancel") {
            target.innerHTML = '<div class="upgrade-state-empty">'
                + '<div class="fw-semibold mb-1">Aksi pembatalan subscription</div>'
                + '<div class="text-muted">Subscription akan dihentikan pada akhir periode aktif. Paket target tidak diperlukan.</div>'
                + '</div>';
            return;
        }

        if (!targetPackage) {
            target.innerHTML = '<div class="upgrade-state-empty">'
                + '<div class="fw-semibold mb-1">Belum ada paket target</div>'
                + '<div class="text-muted">Pilih salah satu paket dari katalog untuk melihat ringkasan perubahan.</div>'
                + '</div>';
            return;
        }

        var blockedChip = "";
        if (blockedFeature) {
            blockedChip = packageHasFeature(targetPackage, blockedFeature)
                ? '<span class="upgrade-feature-chip is-match"><i class="ti ti-circle-check"></i>Mendukung ' + esc(blockedFeatureLabel || blockedFeature) + '</span>'
                : '<span class="upgrade-feature-chip"><i class="ti ti-alert-circle"></i>Belum mendukung ' + esc(blockedFeatureLabel || blockedFeature) + '</span>';
        }

        var employeeLimit = packageFeatureLimit(targetPackage, "max_employees");
        var employeeChip = employeeLimit != null
            ? '<span class="upgrade-feature-chip"><i class="ti ti-users"></i>Batas employee: ' + esc(employeeLimit) + '</span>'
            : '';

        target.innerHTML = '<div class="upgrade-summary-list">'
            + '<div class="upgrade-summary-item">'
            + '<div class="text-muted small mb-1">Aksi</div>'
            + '<div class="fw-semibold">' + esc(actionLabel) + '</div>'
            + '</div>'
            + '<div class="upgrade-summary-item">'
            + '<div class="text-muted small mb-1">Paket saat ini</div>'
            + '<div class="fw-semibold">' + esc(currentPackage ? currentPackage.name : "Belum ada paket aktif") + '</div>'
            + '<div class="text-muted small">' + esc(currentPackage ? currentPackage.code : "-") + '</div>'
            + '</div>'
            + '<div class="upgrade-summary-item">'
            + '<div class="text-muted small mb-1">Paket target</div>'
            + '<div class="fw-semibold">' + esc(targetPackage.name) + '</div>'
            + '<div class="text-muted small mb-2">' + esc(targetPackage.code) + ' · ' + esc(money(targetPackage.monthlyPrice)) + '/bulan</div>'
            + '<div class="d-flex flex-wrap gap-2">' + blockedChip + employeeChip + '</div>'
            + '</div>'
            + '</div>';
    }

    function renderPackageCatalog(target, packages, selectedUuid, blockedFeature, blockedFeatureLabel, action) {
        if (!target) {
            return;
        }

        if (!Array.isArray(packages) || packages.length === 0) {
            target.innerHTML = '<div class="col-12"><div class="upgrade-state-empty text-muted small">Tidak ada paket aktif yang bisa dipilih.</div></div>';
            return;
        }

        var orderedPackages = packages.slice().sort(function (a, b) {
            var priceA = Number(a && a.monthlyPrice != null ? a.monthlyPrice : 0);
            var priceB = Number(b && b.monthlyPrice != null ? b.monthlyPrice : 0);
            if (priceA !== priceB) {
                return priceA - priceB;
            }

            var nameA = String((a && a.name) || '').toLowerCase();
            var nameB = String((b && b.name) || '').toLowerCase();
            return nameA.localeCompare(nameB);
        });

        var isCancel = String(action || "") === "cancel";

        target.innerHTML = orderedPackages.map(function (pkg) {
            var isSelected = String(pkg.uuid || "") === String(selectedUuid || "");
            var supportsBlocked = blockedFeature ? packageHasFeature(pkg, blockedFeature) : false;
            var limit = packageFeatureLimit(pkg, "max_employees");
            var chips = [];

            if (blockedFeature) {
                chips.push(
                    '<span class="upgrade-feature-chip' + (supportsBlocked ? ' is-match' : '') + '">'
                    + '<i class="ti ' + (supportsBlocked ? 'ti-circle-check' : 'ti-alert-circle') + '"></i>'
                    + esc(supportsBlocked ? ('Mendukung ' + (blockedFeatureLabel || blockedFeature)) : ('Tidak mendukung ' + (blockedFeatureLabel || blockedFeature)))
                    + '</span>'
                );
            }

            if (limit != null) {
                chips.push('<span class="upgrade-feature-chip"><i class="ti ti-users"></i>Maks ' + esc(limit) + ' employee</span>');
            }

            return '<div class="col-12 col-lg-6">'
                + '<div class="card h-100 upgrade-package-card ' + (isSelected ? 'is-selected' : '') + ' ' + (isCancel ? 'is-disabled' : '') + '" data-upgrade-package-card="' + esc(pkg.uuid) + '" role="button" tabindex="0" aria-pressed="' + (isSelected ? 'true' : 'false') + '">'
                + '<div class="card-body">'
                + '<div class="upgrade-package-head">'
                + '<div class="min-w-0">'
                + '<div class="upgrade-package-name">' + esc(pkg.name) + '</div>'
                + '<div class="upgrade-package-code">' + esc(pkg.code) + '</div>'
                + '</div>'
                + '<div class="upgrade-package-price-wrap">'
                + '<div class="upgrade-price text-primary fw-bold">' + esc(money(pkg.monthlyPrice)) + '</div>'
                + '<div class="upgrade-price-meta">per bulan</div>'
                + '</div>'
                + '</div>'
                + '<div class="upgrade-package-description">' + esc(pkg.description || 'Paket aktif untuk kebutuhan pertumbuhan tenant.') + '</div>'
                + '<div class="d-flex flex-wrap gap-2">' + chips.join('') + '</div>'
                + '</div>'
                + '</div>'
                + '</div>';
        }).join("");
    }

    function renderPreviewPane(preview, pane, content) {
        var delta = Number(preview.price_delta || 0);
        var isDowngrade = preview.action === 'downgrade';
        var isUpgrade = preview.action === 'upgrade';
        var deltaLabel = isDowngrade ? 'Penghematan per bulan' : (isUpgrade ? 'Tambahan biaya per bulan' : 'Selisih harga');
        var deltaClass = delta > 0 ? "text-warning" : (delta < 0 ? "text-success" : "text-body");
        var deltaDisplay = isDowngrade
            ? ('+' + money(Math.abs(delta)))
            : (delta > 0 ? ('+' + money(delta)) : money(delta));

        var toPrice = preview.to_package && preview.to_package.price != null
            ? money(Number(preview.to_package.price))
            : null;
        var anomalyFlags = Array.isArray(preview.anomaly_flags) ? preview.anomaly_flags : [];
        var anomalyDetails = preview.anomaly_details && typeof preview.anomaly_details === 'object'
            ? preview.anomaly_details
            : null;

        var anomalyDetailsLine = '';
        if (anomalyDetails) {
            var parts = [];
            if (anomalyDetails.invoice_number) {
                parts.push('Invoice: ' + String(anomalyDetails.invoice_number));
            }
            if (anomalyDetails.invoice_remaining_due != null) {
                parts.push('Sisa: ' + money(Number(anomalyDetails.invoice_remaining_due)));
            }
            if (anomalyDetails.subscription_status) {
                parts.push('Status subscription: ' + String(anomalyDetails.subscription_status));
            }

            if (parts.length) {
                anomalyDetailsLine = '<div class="small text-muted mt-2">' + esc(parts.join(' · ')) + '</div>';
            }
        }

        content.innerHTML = '<div class="upgrade-summary-list">'
            + '<div class="upgrade-summary-item">'
            + '<div class="text-muted small mb-1">Aksi</div>'
            + '<div class="fw-semibold">' + esc(normalizeActionLabel(preview.action)) + '</div>'
            + '</div>'
            + '<div class="upgrade-summary-item">'
            + '<div class="text-muted small mb-1">Perubahan paket</div>'
            + '<div class="fw-semibold">' + esc(preview.from_package ? preview.from_package.name + ' (' + preview.from_package.code + ')' : '-') + '</div>'
            + '<div class="small text-muted my-1">menjadi</div>'
            + '<div class="fw-semibold">' + esc(preview.to_package ? preview.to_package.name + ' (' + preview.to_package.code + ')' : '-') + '</div>'
            + '</div>'
            + (toPrice
                ? '<div class="upgrade-summary-item">'
                    + '<div class="text-muted small mb-1">Harga paket target (per bulan)</div>'
                    + '<div class="upgrade-price fw-bold text-primary">' + esc(toPrice) + '</div>'
                    + '</div>'
                : '')
            + '<div class="upgrade-summary-item">'
            + '<div class="text-muted small mb-1">' + deltaLabel + '</div>'
            + '<div class="fw-semibold ' + deltaClass + '">' + deltaDisplay + '</div>'
            + '<div class="small text-muted">Efektif ' + esc(formatDateTime(preview.effective_at)) + '</div>'
            + '</div>'
            + '<div class="upgrade-summary-item">'
            + '<div class="text-muted small mb-1">Catatan sistem</div>'
            + '<div class="small">' + esc(preview.notes || '-') + '</div>'
            + '</div>'
            + '<div class="upgrade-summary-item">'
            + '<div class="text-muted small mb-1">Anomali billing</div>'
            + '<div class="d-flex flex-wrap gap-2">' + normalizeAnomalyBadges(anomalyFlags) + '</div>'
            + anomalyDetailsLine
            + '</div>'
            + '</div>';
        pane.classList.remove("d-none");
    }

    function renderTenantRequestList(target, rows) {
        if (!Array.isArray(rows) || rows.length === 0) {
            target.innerHTML = '<div class="upgrade-history-empty text-muted">Belum ada pengajuan perubahan paket.</div>';
            return;
        }

        var html = '<div class="table-responsive"><table class="table table-sm align-middle mb-0">'
            + '<thead><tr><th>Aksi</th><th>Status</th><th>Dibuat</th><th>Efektif</th><th>Paket Target</th></tr></thead><tbody>';

        html += rows.map(function (row) {
            var preview = row.preview || {};
            var toPackage = preview.to_package || null;
            return '<tr>'
                + '<td>' + esc(normalizeActionLabel(row.action)) + '</td>'
                + '<td>' + normalizeStatusBadge(row.status) + '</td>'
                + '<td>' + esc(formatDateTime(row.created_at)) + '</td>'
                + '<td>' + esc(formatDateTime(row.effective_at)) + '</td>'
                + '<td>' + esc(toPackage ? (toPackage.name + ' (' + toPackage.code + ')') : '-') + '</td>'
                + '</tr>';
        }).join("");

        html += "</tbody></table></div>";
        target.innerHTML = html;
    }

    function renderAdminQueue(target, rows) {
        if (!Array.isArray(rows) || rows.length === 0) {
            target.innerHTML = '<div class="upgrade-history-empty text-muted">Tidak ada pengajuan upgrade/downgrade baru saat ini.</div>';
            return;
        }

        var pendingRows = rows.filter(function (row) {
            return String(row.status || "").toLowerCase() === "pending";
        });

        if (!pendingRows.length) {
            target.innerHTML = '<div class="alert alert-success mb-0">Tidak ada pengajuan pending baru.</div>';
            return;
        }

        var html = '<div class="alert alert-warning py-2 mb-2">Ada <strong>' + pendingRows.length + '</strong> pengajuan pending baru.</div>';
        html += '<div class="table-responsive"><table class="table table-sm align-middle mb-0">'
            + '<thead><tr><th>Company</th><th>Aksi</th><th>Target</th><th>Dibuat</th><th>Status</th></tr></thead><tbody>';

        html += pendingRows.map(function (row) {
            var preview = row.preview || {};
            var toPackage = preview.to_package || null;
            var flags = Array.isArray(preview.anomaly_flags) ? preview.anomaly_flags : [];
            return '<tr>'
                + '<td>' + esc(row.company_uuid || "-") + '</td>'
                + '<td>' + esc(normalizeActionLabel(row.action)) + '</td>'
                + '<td>' + esc(toPackage ? (toPackage.name + ' (' + toPackage.code + ')') : '-') + '</td>'
                + '<td>' + esc(formatDateTime(row.created_at)) + '</td>'
                + '<td>' + normalizeStatusBadge(row.status)
                + '<div class="mt-1 d-flex flex-wrap gap-1">' + normalizeAnomalyBadges(flags) + '</div>'
                + '</td>'
                + '</tr>';
        }).join("");

        html += "</tbody></table></div>";
        target.innerHTML = html;
    }

    function init() {
        var actionEl = byId("upgrade-action");
        var packageEl = byId("upgrade-target-package");
        var notesEl = byId("upgrade-notes");
        var previewBtn = byId("upgrade-preview-btn");
        var submitBtn = byId("upgrade-submit-btn");
        var previewPane = byId("upgrade-preview-pane");
        var previewContent = byId("upgrade-preview-content");
        var statusPane = byId("upgrade-status-pane");
        var requestList = byId("upgrade-request-list");
        var adminQueue = byId("upgrade-admin-queue");
        var contextEl = byId("upgrade-page-context");
        var packageCatalog = byId("upgrade-package-catalog");
        var packageCountBadge = byId("upgrade-package-count-badge");
        var selectionSummary = byId("upgrade-selection-summary");
        var currentPackageName = byId("upgrade-current-package-name");
        var currentPackageCode = byId("upgrade-current-package-code");
        var currentPackageMonthly = byId("upgrade-current-package-monthly");
        var currentFeatureStatus = byId("upgrade-current-feature-status");
        var currentChangeStatus = byId("upgrade-current-change-status");
        var earlyActivateWrap = byId("upgrade-early-activate-wrap");
        var earlyActivateBtn = byId("upgrade-early-activate-btn");
        var modalEarlyActivateEl = byId("modalEarlyActivate");
        var modalTargetName = byId("modal-early-target-name");
        var modalRiskCheck = byId("earlyActivateRiskCheck");
        var modalConfirmBtn = byId("modal-early-activate-confirm-btn");

        // Track which request ID to activate early
        var pendingEarlyActivateId = null;

        // Bootstrap modal instance (lazy)
        var earlyActivateModal = null;
        function getEarlyActivateModal() {
            if (!earlyActivateModal && modalEarlyActivateEl && window.bootstrap && window.bootstrap.Modal) {
                earlyActivateModal = new window.bootstrap.Modal(modalEarlyActivateEl);
            }
            return earlyActivateModal;
        }

        // Checkbox enables confirm button
        if (modalRiskCheck && modalConfirmBtn) {
            modalRiskCheck.addEventListener("change", function () {
                modalConfirmBtn.disabled = !modalRiskCheck.checked;
            });
        }

        // Reset modal state on hide
        if (modalEarlyActivateEl) {
            modalEarlyActivateEl.addEventListener("hidden.bs.modal", function () {
                if (modalRiskCheck) modalRiskCheck.checked = false;
                if (modalConfirmBtn) modalConfirmBtn.disabled = true;
                pendingEarlyActivateId = null;
            });
        }

        // Open modal when "Aktifkan Sekarang" clicked
        if (earlyActivateBtn) {
            earlyActivateBtn.addEventListener("click", function () {
                if (!pendingEarlyActivateId) return;
                var modal = getEarlyActivateModal();
                if (modal) modal.show();
            });
        }

        // Confirm early activation
        if (modalConfirmBtn) {
            modalConfirmBtn.addEventListener("click", function () {
                if (!pendingEarlyActivateId) return;
                var modal = getEarlyActivateModal();
                var requestId = pendingEarlyActivateId;

                modalConfirmBtn.disabled = true;
                modalConfirmBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Memproses...';

                apiRequest("post", "/hcm/subscriptions/change-requests/" + encodeURIComponent(requestId) + "/activate-early", { risk_accepted: true })
                    .then(function (res) {
                        if (modal) modal.hide();
                        renderChangeStatus("Aktivasi berhasil! Invoice baru telah diterbitkan. Silakan selesaikan pembayaran untuk mulai menggunakan paket baru.", "success");
                        showEarlyActivateButton(false, null, null);
                        loadTenantRequests();
                    })
                    .catch(function (err) {
                        if (modal) modal.hide();
                        var msg = err && err.response && err.response.data && err.response.data.error
                            ? err.response.data.error.message
                            : "Aktivasi gagal. Silakan coba lagi.";
                        showStatus(msg, "error");
                    })
                    .finally(function () {
                        modalConfirmBtn.innerHTML = '<i class="ti ti-bolt me-1"></i>Ya, Aktifkan Sekarang';
                        if (modalRiskCheck && !modalRiskCheck.checked) {
                            modalConfirmBtn.disabled = true;
                        }
                    });
            });
        }

        function showEarlyActivateButton(show, requestId, targetName) {
            pendingEarlyActivateId = show ? requestId : null;
            if (earlyActivateWrap) {
                earlyActivateWrap.classList.toggle("d-none", !show);
            }
            if (modalTargetName && targetName) {
                modalTargetName.textContent = targetName;
            }
        }

        if (!actionEl || !packageEl || !previewBtn || !submitBtn || !statusPane || !contextEl) {
            return;
        }

        var ctx = {
            blockedFeature: contextEl.dataset.blockedFeature || "",
            blockedFeatureLabel: contextEl.dataset.blockedFeatureLabel || "",
            isPrimarySuperAdmin: String(contextEl.dataset.isPrimarySuperAdmin || "0") === "1",
            recommendedPackages: [],
            currentPackage: null,
            packages: [],
        };

        try {
            ctx.recommendedPackages = JSON.parse(contextEl.dataset.recommendedPackages || "[]").map(normalizePackage);
        } catch (_e) {
            ctx.recommendedPackages = [];
        }

        try {
            ctx.currentPackage = contextEl.dataset.currentPackage
                ? normalizePackage(JSON.parse(contextEl.dataset.currentPackage))
                : null;
        } catch (_e2) {
            ctx.currentPackage = null;
        }

        function showStatus(message, kind) {
            var cls = "alert-info";
            if (kind === "success") cls = "alert-success";
            if (kind === "error") cls = "alert-danger";
            if (kind === "warning") cls = "alert-warning";
            statusPane.innerHTML = '<div class="alert ' + cls + ' py-2 mb-0">' + esc(message) + "</div>";
        }

        function setLoading(button, loading) {
            if (!button) return;
            button.disabled = !!loading;
        }

        function syncSelectionState() {
            renderPackageCatalog(packageCatalog, ctx.packages, packageEl.value, ctx.blockedFeature, ctx.blockedFeatureLabel, actionEl.value);
            renderSelectionSummary(selectionSummary, actionEl.value, ctx.currentPackage, findPackageByUuid(ctx.packages, packageEl.value), ctx.blockedFeature, ctx.blockedFeatureLabel);
        }

        function renderCurrentPackagePanel() {
            if (currentPackageName) {
                currentPackageName.textContent = ctx.currentPackage ? (ctx.currentPackage.name || "Belum ada paket aktif") : "Belum ada paket aktif";
            }

            if (currentPackageCode) {
                currentPackageCode.textContent = ctx.currentPackage
                    ? (ctx.currentPackage.code || "-")
                    : "Aktifkan paket dulu untuk mulai berlangganan.";
            }

            if (currentPackageMonthly) {
                currentPackageMonthly.textContent = money(ctx.currentPackage ? (ctx.currentPackage.monthlyPrice || 0) : 0);
            }

            if (currentFeatureStatus) {
                var supports = ctx.blockedFeature ? packageHasFeature(ctx.currentPackage || {}, ctx.blockedFeature) : false;
                currentFeatureStatus.textContent = supports ? "Sudah tersedia" : "Belum termasuk di paket aktif";
                currentFeatureStatus.classList.toggle("text-success", supports);
                currentFeatureStatus.classList.toggle("text-warning", !supports);
            }
        }

        function renderChangeStatus(message, type) {
            if (!currentChangeStatus) {
                return;
            }

            if (!message) {
                currentChangeStatus.classList.add("d-none");
                currentChangeStatus.textContent = "";
                currentChangeStatus.classList.remove("alert-info", "alert-success", "alert-warning");
                currentChangeStatus.classList.add("alert-info");
                return;
            }

            var alertType = type === "success" ? "alert-success" : (type === "warning" ? "alert-warning" : "alert-info");
            currentChangeStatus.classList.remove("d-none", "alert-info", "alert-success", "alert-warning");
            currentChangeStatus.classList.add(alertType);
            currentChangeStatus.textContent = message;
        }

        function applyLatestRequestState(rows) {
            var list = Array.isArray(rows) ? rows : [];

            if (!list.length) {
                renderCurrentPackagePanel();
                renderChangeStatus("", "info");
                showEarlyActivateButton(false, null, null);
                return;
            }

            var latestApplied = list.find(function (row) {
                return String(row && row.status || "").toLowerCase() === "applied";
            });

            var latestApproved = list.find(function (row) {
                return String(row && row.status || "").toLowerCase() === "approved";
            });

            var source = latestApplied || latestApproved || null;

            if (source && source.preview && source.preview.to_package) {
                var dynamicTarget = packageFromPreview(source.preview.to_package, ctx.packages);
                if (dynamicTarget) {
                    if (latestApplied) {
                        ctx.currentPackage = dynamicTarget;
                    }

                    if (packageEl && dynamicTarget.uuid) {
                        packageEl.value = dynamicTarget.uuid;
                    }

                    if (actionEl && source.action) {
                        actionEl.value = String(source.action);
                    }

                    if (previewPane && previewContent) {
                        renderPreviewPane(source.preview, previewPane, previewContent);
                    }
                }
            }

            renderCurrentPackagePanel();
            syncSelectionState();

            if (latestApplied) {
                renderChangeStatus("Perubahan paket terakhir sudah diterapkan.", "success");
                showEarlyActivateButton(false, null, null);
                return;
            }

            if (latestApproved) {
                var approvedTarget = latestApproved.preview && latestApproved.preview.to_package
                    ? (latestApproved.preview.to_package.name || latestApproved.preview.to_package.code || "paket target")
                    : "paket target";
                var effectiveAt = latestApproved.effective_at ? formatDateTime(latestApproved.effective_at) : "jadwal berikutnya";
                renderChangeStatus("Request sudah disetujui. Paket akan berlaku pada " + effectiveAt + " (target: " + approvedTarget + ").", "info");
                showEarlyActivateButton(true, latestApproved.id, approvedTarget);
                return;
            }

            renderChangeStatus("", "info");
            showEarlyActivateButton(false, null, null);
        }

        function loadPackages() {
            return apiRequest("get", "/saas/packages", { status: "active", per_page: 200 }).then(function (payload) {
                var rows = Array.isArray(payload.data) ? payload.data.map(normalizePackage) : [];

                if (ctx.blockedFeature) {
                    rows = rows.filter(function (pkg) {
                        return packageHasFeature(pkg, ctx.blockedFeature);
                    });
                }

                ctx.packages = rows;

                if (!rows.length) {
                    packageEl.innerHTML = '<option value="">Tidak ada paket aktif</option>';
                    if (packageCountBadge) {
                        packageCountBadge.textContent = "0 paket";
                    }
                    syncSelectionState();
                    return;
                }


                rows.sort(function (a, b) {
                    var priceA = Number(a && a.monthlyPrice != null ? a.monthlyPrice : 0);
                    var priceB = Number(b && b.monthlyPrice != null ? b.monthlyPrice : 0);
                    if (priceA !== priceB) {
                        return priceA - priceB;
                    }

                    var nameA = String((a && a.name) || '').toLowerCase();
                    var nameB = String((b && b.name) || '').toLowerCase();
                    return nameA.localeCompare(nameB);
                });
                packageEl.innerHTML = rows.map(function (pkg) {
                    return '<option value="' + esc(pkg.uuid) + '">' + esc(pkg.name) + ' - ' + esc(money(pkg.monthlyPrice)) + '</option>';
                }).join("");

                setBlockedRecommendationHint(ctx, packageEl);
                if (packageCountBadge) {
                    packageCountBadge.textContent = rows.length + " paket";
                }
                syncSelectionState();
            });
        }

        function loadTenantRequests() {
            if (!requestList) {
                return Promise.resolve();
            }

            return apiRequest("get", "/hcm/subscriptions/change-requests", null)
                .then(function (payload) {
                    var rows = Array.isArray(payload.data) ? payload.data : [];
                    renderTenantRequestList(requestList, rows);
                    applyLatestRequestState(rows);
                })
                .catch(function (err) {
                    var message = err && err.response && err.response.data && err.response.data.error
                        ? err.response.data.error.message
                        : "Gagal memuat riwayat pengajuan.";
                    requestList.innerHTML = '<div class="text-danger">' + esc(message) + '</div>';
                    renderChangeStatus("", "info");
                });
        }

        function loadAdminQueue() {
            if (!ctx.isPrimarySuperAdmin || !adminQueue) {
                return Promise.resolve();
            }

            return apiRequest("get", "/saas/subscription-change-requests", { status: "pending" })
                .then(function (payload) {
                    renderAdminQueue(adminQueue, payload.data || []);
                })
                .catch(function (err) {
                    var code = err && err.response && err.response.data && err.response.data.error
                        ? err.response.data.error.code
                        : "";
                    if (code === "PRIMARY_SUPER_ADMIN_REQUIRED") {
                        adminQueue.innerHTML = '<div class="text-muted">Akses queue hanya untuk admin code 1.</div>';
                        return;
                    }
                    adminQueue.innerHTML = '<div class="text-danger">Gagal memuat queue pengajuan admin.</div>';
                });
        }

        function buildPayload() {
            var action = String(actionEl.value || "").trim();
            var payload = {
                action: action,
            };

            if (action !== "cancel") {
                payload.to_package_uuid = String(packageEl.value || "").trim();
            }

            return payload;
        }

        if (packageCatalog) {
            packageCatalog.addEventListener("click", function (event) {
                var card = event.target.closest("[data-upgrade-package-card]");
                if (!card || String(actionEl.value || "") === "cancel") {
                    return;
                }

                packageEl.value = String(card.getAttribute("data-upgrade-package-card") || "");
                syncSelectionState();
            });

            packageCatalog.addEventListener("keydown", function (event) {
                if (event.key !== "Enter" && event.key !== " ") {
                    return;
                }

                var card = event.target.closest("[data-upgrade-package-card]");
                if (!card || String(actionEl.value || "") === "cancel") {
                    return;
                }

                event.preventDefault();
                packageEl.value = String(card.getAttribute("data-upgrade-package-card") || "");
                syncSelectionState();
            });
        }

        actionEl.addEventListener("change", function () {
            packageEl.disabled = String(actionEl.value || "") === "cancel";
            syncSelectionState();
        });

        packageEl.addEventListener("change", syncSelectionState);

        previewBtn.addEventListener("click", function () {
            var payload = buildPayload();
            setLoading(previewBtn, true);

            apiRequest("post", "/hcm/subscriptions/preview-change", payload)
                .then(function (response) {
                    var preview = response && response.data ? response.data.preview : null;
                    if (!preview) {
                        showStatus("Preview tidak tersedia.", "warning");
                        return;
                    }

                    if (previewPane && previewContent) {
                        renderPreviewPane(preview, previewPane, previewContent);
                    }
                    showStatus("Preview berhasil dimuat.", "success");
                })
                .catch(function (err) {
                    var message = err && err.response && err.response.data && err.response.data.error
                        ? err.response.data.error.message
                        : "Gagal memuat preview perubahan paket.";
                    showStatus(message, "error");
                })
                .finally(function () {
                    setLoading(previewBtn, false);
                });
        });

        submitBtn.addEventListener("click", function () {
            var payload = buildPayload();
            var notes = String(notesEl && notesEl.value ? notesEl.value : "").trim();
            if (notes) {
                payload.notes = notes;
            }

            setLoading(submitBtn, true);
            apiRequest("post", "/hcm/subscriptions/change-plan", payload)
                .then(function () {
                    showStatus("Pengajuan perubahan paket berhasil dikirim.", "success");
                    return Promise.all([loadTenantRequests(), loadAdminQueue()]);
                })
                .catch(function (err) {
                    var message = err && err.response && err.response.data && err.response.data.error
                        ? err.response.data.error.message
                        : "Gagal mengirim pengajuan perubahan paket.";
                    showStatus(message, "error");
                })
                .finally(function () {
                    setLoading(submitBtn, false);
                });
        });

        Promise.all([loadPackages(), loadTenantRequests(), loadAdminQueue()]).catch(function () {
            showStatus("Sebagian data gagal dimuat. Coba refresh halaman.", "warning");
        });

        renderCurrentPackagePanel();
        syncSelectionState();

        // Keep package state panel fresh when request status changes externally (e.g. approved by super-admin)
        window.setInterval(function () {
            loadTenantRequests();
        }, 30000);
    }

    document.addEventListener("DOMContentLoaded", init);
})(window, document);
