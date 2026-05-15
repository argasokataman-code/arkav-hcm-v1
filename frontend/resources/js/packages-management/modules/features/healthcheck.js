import { API_FEATURE_CATALOG_HEALTHCHECK, apiRequest } from "../../shared.js";

const featureHealthcheckMethods = {
  renderFeatureHealthcheckStatus: function (payload) {
    const statusEl = document.querySelector("[data-feature-healthcheck-status]");
    if (!statusEl) {
      return;
    }

    if (!payload || payload.loading) {
      statusEl.className = "small text-muted mt-2";
      statusEl.textContent = "Healthcheck: memeriksa sinkronisasi route/docs/catalog...";
      return;
    }

    if (payload.error) {
      statusEl.className = "small text-danger mt-2";
      statusEl.textContent = "Healthcheck tidak tersedia: " + payload.error;
      return;
    }

    const counts = payload.counts || {};
    const routeOnly = Number(counts.route_only || 0);
    const docsOnly = Number(counts.docs_only || 0);
    const hasDrift = !!payload.has_drift;

    statusEl.className = hasDrift ? "small text-warning mt-2" : "small text-success mt-2";
    statusEl.textContent = hasDrift
      ? "Healthcheck drift terdeteksi (route-only: " + routeOnly + ", docs-only: " + docsOnly + ")."
      : "Healthcheck OK: route/docs/catalog sinkron.";
  },

  runFeatureCatalogHealthcheck: function (force) {
    const self = this;
    if (self.featureHealthcheckSummary && !force) {
      self.renderFeatureHealthcheckStatus(self.featureHealthcheckSummary);
      return Promise.resolve(self.featureHealthcheckSummary);
    }

    self.renderFeatureHealthcheckStatus({ loading: true });

    return apiRequest("GET", API_FEATURE_CATALOG_HEALTHCHECK)
      .then(function (response) {
        if (!response || response.success !== true || !response.data) {
          throw new Error("Invalid healthcheck response");
        }

        self.featureHealthcheckSummary = response.data;
        self.renderFeatureHealthcheckStatus(self.featureHealthcheckSummary);
        return self.featureHealthcheckSummary;
      })
      .catch(function (err) {
        const message = err?.data?.error?.message || err?.message || "request failed";
        self.featureHealthcheckSummary = { error: message };
        self.renderFeatureHealthcheckStatus(self.featureHealthcheckSummary);
        return self.featureHealthcheckSummary;
      });
  },
};

export default featureHealthcheckMethods;
