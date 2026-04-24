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

    function renderPreviewPane(preview, pane, content) {
        var rows = [
            "Aksi: " + normalizeActionLabel(preview.action),
            "Paket saat ini: " + (preview.from_package ? preview.from_package.name + " (" + preview.from_package.code + ")" : "-") + " / " + money(preview.from_package ? preview.from_package.price : 0),
            "Paket target: " + (preview.to_package ? preview.to_package.name + " (" + preview.to_package.code + ")" : "-") + " / " + money(preview.to_package ? preview.to_package.price : 0),
            "Selisih harga: " + money(preview.price_delta || 0),
            "Efektif: " + formatDateTime(preview.effective_at),
            "Catatan sistem: " + (preview.notes || "-"),
        ];

        content.textContent = rows.join("\n");
        pane.classList.remove("d-none");
    }

    function renderTenantRequestList(target, rows) {
        if (!Array.isArray(rows) || rows.length === 0) {
            target.innerHTML = '<div class="text-muted">Belum ada pengajuan perubahan paket.</div>';
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
                + '<td>' + esc(toPackage ? (toPackage.name + " (" + toPackage.code + ")") : "-") + '</td>'
                + '</tr>';
        }).join("");

        html += "</tbody></table></div>";
        target.innerHTML = html;
    }

    function renderAdminQueue(target, rows) {
        if (!Array.isArray(rows) || rows.length === 0) {
            target.innerHTML = '<div class="text-muted">Tidak ada pengajuan upgrade/downgrade baru saat ini.</div>';
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
            return '<tr>'
                + '<td>' + esc(row.company_uuid || "-") + '</td>'
                + '<td>' + esc(normalizeActionLabel(row.action)) + '</td>'
                + '<td>' + esc(toPackage ? (toPackage.name + " (" + toPackage.code + ")") : "-") + '</td>'
                + '<td>' + esc(formatDateTime(row.created_at)) + '</td>'
                + '<td>' + normalizeStatusBadge(row.status) + '</td>'
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

        if (!actionEl || !packageEl || !previewBtn || !submitBtn || !statusPane || !contextEl) {
            return;
        }

        var ctx = {
            blockedFeature: contextEl.dataset.blockedFeature || "",
            isPrimarySuperAdmin: String(contextEl.dataset.isPrimarySuperAdmin || "0") === "1",
            recommendedPackages: [],
        };

        try {
            ctx.recommendedPackages = JSON.parse(contextEl.dataset.recommendedPackages || "[]");
        } catch (_e) {
            ctx.recommendedPackages = [];
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

        function loadPackages() {
            return apiRequest("get", "/saas/packages", { status: "active", per_page: 200 }).then(function (payload) {
                var rows = Array.isArray(payload.data) ? payload.data : [];
                if (!rows.length) {
                    packageEl.innerHTML = '<option value="">Tidak ada paket aktif</option>';
                    return;
                }

                packageEl.innerHTML = rows.map(function (pkg) {
                    var price = pkg.monthly_price != null ? money(pkg.monthly_price) : "-";
                    return '<option value="' + esc(pkg.uuid) + '">' + esc(pkg.name || pkg.code || "Paket") + " - " + esc(price) + "</option>";
                }).join("");

                setBlockedRecommendationHint(ctx, packageEl);
            });
        }

        function loadTenantRequests() {
            if (!requestList) {
                return Promise.resolve();
            }

            return apiRequest("get", "/hcm/subscriptions/change-requests", null)
                .then(function (payload) {
                    renderTenantRequestList(requestList, payload.data || []);
                })
                .catch(function () {
                    requestList.innerHTML = '<div class="text-danger">Gagal memuat riwayat pengajuan.</div>';
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

        actionEl.addEventListener("change", function () {
            var isCancel = String(actionEl.value || "") === "cancel";
            packageEl.disabled = isCancel;
        });

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
    }

    document.addEventListener("DOMContentLoaded", init);
})(window, document);
