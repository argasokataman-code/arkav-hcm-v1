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

    function formatCompanyReference(row) {
        var companyCode = row && row.company_code ? String(row.company_code).trim() : "";
        if (companyCode) {
            return companyCode;
        }
        var companyId = Number(row && row.company_id);
        if (Number.isFinite(companyId) && companyId > 0) {
            return "CMP-" + String(Math.trunc(companyId));
        }
        return "Company tercatat";
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

    window.ArcavUpgradeUtils = Object.freeze({
        apiRequest: apiRequest,
        byId: byId,
        esc: esc,
        findPackageByUuid: findPackageByUuid,
        formatCompanyReference: formatCompanyReference,
        formatDateTime: formatDateTime,
        money: money,
        normalizeActionLabel: normalizeActionLabel,
        normalizeAnomalyBadges: normalizeAnomalyBadges,
        normalizePackage: normalizePackage,
        normalizeStatusBadge: normalizeStatusBadge,
        packageFeatureLimit: packageFeatureLimit,
        packageFromPreview: packageFromPreview,
        packageHasFeature: packageHasFeature,
    });
})(window, document);