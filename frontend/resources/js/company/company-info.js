/**
 * Company info card: fetch and display active company details
 * Usage: call loadCompanyData() on page load, bind event if needed
 */
(function (window, document) {
    "use strict";

    var companyDataCache = null;

    /**
     * Fetch active company data from API
     */
    async function fetchCompanyData() {
        if (!window.AuthApi) {
            console.warn("AuthApi not available");
            return null;
        }

        try {
            var response = await window.AuthApi.request("get", "/company/active");
            if (response && response.data && response.data.success === true) {
                companyDataCache = response.data.data;
                return companyDataCache;
            }
        } catch (error) {
            console.error("Failed to fetch company data:", error);
        }
        return null;
    }

    /**
     * Format date to local string
     */
    function formatDate(isoString) {
        if (!isoString) return "—";
        try {
            var date = new Date(isoString);
            return date.toLocaleDateString("id-ID", { year: "numeric", month: "long", day: "numeric" });
        } catch (_e) {
            return isoString;
        }
    }

    /**
     * Render company card HTML
     */
    function renderCompanyCard(data) {
        if (!data) {
            return '<div class="alert alert-warning">Company data not available</div>';
        }

        var html = '<div class="card card-company-info">\n';
        html += '  <div class="card-header bg-primary text-white">\n';
        html += '    <h5 class="card-title mb-0">\n';
        html += '      <i class="bi bi-buildings me-2"></i>' + (data.name || "—") + '\n';
        html += '    </h5>\n';
        html += '  </div>\n';
        html += '  <div class="card-body">\n';
        html += '    <div class="row mb-3">\n';
        html += '      <div class="col-md-6">\n';
        html += '        <p class="text-muted small mb-2">Kode Perusahaan</p>\n';
        html += '        <p class="fw-bold">' + (data.code || "—") + '</p>\n';
        html += '      </div>\n';
        html += '      <div class="col-md-6">\n';
        html += '        <p class="text-muted small mb-2">Status</p>\n';
        html += '        <p><span class="badge bg-success">' + (data.status || "—") + '</span></p>\n';
        html += '      </div>\n';
        html += '    </div>\n';

        if (data.legalName) {
            html += '    <div class="row mb-3">\n';
            html += '      <div class="col-12">\n';
            html += '        <p class="text-muted small mb-2">Nama Hukum</p>\n';
            html += '        <p>' + data.legalName + '</p>\n';
            html += '      </div>\n';
            html += '    </div>\n';
        }

        html += '    <div class="row mb-3">\n';
        html += '      <div class="col-md-6">\n';
        html += '        <p class="text-muted small mb-2">Zona Waktu</p>\n';
        html += '        <p>' + (data.timezone || "—") + '</p>\n';
        html += '      </div>\n';
        html += '      <div class="col-md-6">\n';
        html += '        <p class="text-muted small mb-2">Mata Uang</p>\n';
        html += '        <p>' + (data.currency || "—") + '</p>\n';
        html += '      </div>\n';
        html += '    </div>\n';

        if (data.currentUserJoinedAt) {
            html += '    <div class="row mb-3">\n';
            html += '      <div class="col-md-6">\n';
            html += '        <p class="text-muted small mb-2">Bergabung Sejak</p>\n';
            html += '        <p>' + formatDate(data.currentUserJoinedAt) + '</p>\n';
            html += '      </div>\n';
            html += '      <div class="col-md-6">\n';
            html += '        <p class="text-muted small mb-2">Role Anda</p>\n';
            html += '        <p><span class="badge bg-info">' + (data.currentUserRole || "—") + '</span></p>\n';
            html += '      </div>\n';
            html += '    </div>\n';
        }

        html += '    <hr class="my-3">\n';

        html += '    <div class="row mb-3">\n';
        html += '      <div class="col-md-6">\n';
        html += '        <p class="text-muted small mb-2">Jumlah Anggota</p>\n';
        html += '        <p class="fw-bold fs-5">' + (data.memberCount || 0) + '</p>\n';
        html += '      </div>\n';
        html += '      <div class="col-md-6">\n';
        html += '        <p class="text-muted small mb-2">Pemilik</p>\n';
        if (data.owner) {
            html += '        <p>' + data.owner.name + '</p>\n';
            html += '        <p class="text-muted small">' + data.owner.email + '</p>\n';
        } else {
            html += '        <p>—</p>\n';
        }
        html += '      </div>\n';
        html += '    </div>\n';

        if (data.subscription) {
            html += '    <hr class="my-3">\n';
            html += '    <div class="row">\n';
            html += '      <div class="col-12">\n';
            html += '        <p class="text-muted small mb-2">Status Langganan</p>\n';
            var planBadge = data.subscription.status === "trial" ? "warning" : "success";\n';
            html += '        <p><span class="badge bg-' + planBadge + '">' + (data.subscription.planCode || "—") + ' (' + data.subscription.status + ')</span></p>\n';
            if (data.subscription.endsAt) {
                html += '        <p class="text-muted small">Berakhir: ' + formatDate(data.subscription.endsAt) + '</p>\n';
            }
            html += '      </div>\n';
            html += '    </div>\n';
        }

        html += '  </div>\n';
        html += '</div>\n';

        return html;
    }

    /**
     * Load and render company card in target element
     */
    async function loadCompanyCard(elementSelector) {
        var el = document.querySelector(elementSelector);
        if (!el) {
            console.warn("Company card element not found:", elementSelector);
            return;
        }

        el.innerHTML = '<div class="spinner-border spinner-border-sm" role="status"><span class="visually-hidden">Loading...</span></div> Memuat data perusahaan...';

        var data = await fetchCompanyData();
        el.innerHTML = renderCompanyCard(data);
    }

    /**
     * Get cached company data
     */
    function getCompanyData() {
        return companyDataCache;
    }

    /**
     * Clear cache
     */
    function clearCompanyCache() {
        companyDataCache = null;
    }

    window.CompanyInfo = {
        fetchCompanyData: fetchCompanyData,
        loadCompanyCard: loadCompanyCard,
        getCompanyData: getCompanyData,
        clearCompanyCache: clearCompanyCache,
        renderCompanyCard: renderCompanyCard,
    };

    // Auto-load on DOMContentLoaded if marked element exists
    function init() {
        var autoLoadEl = document.querySelector("[data-company-card-auto-load]");
        if (autoLoadEl) {
            var selector = autoLoadEl.getAttribute("data-company-card-auto-load") || "#company-card-container";
            loadCompanyCard(selector);
        }
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", init);
    } else {
        init();
    }
})(window, document);
