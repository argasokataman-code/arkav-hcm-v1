import { API_PACKAGE_COMPLIANCE_CHECK, FEATURE_LIMIT_INPUT_CODE, apiRequest, esc } from "../../shared.js";

const featureComplianceMethods = {
  collectEffectiveSelectedFeatureCodes: function () {
    const selectedCodes = this.getSelectedFeatureCodes();
    const maxEmployees = String(document.getElementById("input_package_max_employees")?.value || "").trim();
    const dedup = new Set(selectedCodes.filter(Boolean));

    if (maxEmployees !== "") {
      dedup.add(FEATURE_LIMIT_INPUT_CODE);
    } else {
      dedup.delete(FEATURE_LIMIT_INPUT_CODE);
    }

    return Array.from(dedup.values());
  },

  queueComplianceSnapshotRefresh: function (force) {
    const self = this;
    window.clearTimeout(this.packageComplianceRefreshTimer);
    this.packageComplianceRefreshTimer = window.setTimeout(function () {
      self.runPackageComplianceSnapshot(!!force);
    }, force ? 0 : 180);
  },

  runPackageComplianceSnapshot: function (force) {
    const self = this;
    if (force) {
      self.packageComplianceSnapshot = null;
    }

    const selectedCodes = this.collectEffectiveSelectedFeatureCodes();
    const params = new URLSearchParams();
    selectedCodes.forEach(function (code) {
      params.append("feature_codes[]", code);
    });

    this.renderPackageComplianceSnapshot({
      loading: true,
      selected_feature_codes: selectedCodes,
    });

    return apiRequest("GET", API_PACKAGE_COMPLIANCE_CHECK + "?" + params.toString(), null)
      .then(function (response) {
        if (!response || response.success !== true || !response.data) {
          throw new Error("Invalid compliance snapshot response");
        }

        self.packageComplianceSnapshot = response.data;
        self.renderPackageComplianceSnapshot(self.packageComplianceSnapshot);
        return self.packageComplianceSnapshot;
      })
      .catch(function (err) {
        const message = err?.data?.error?.message || err?.message || "request failed";
        self.renderPackageComplianceSnapshot({
          error: message,
          selected_feature_codes: selectedCodes,
        });
        return null;
      });
  },

  renderPackageComplianceSnapshot: function (payload) {
    const root = document.querySelector("[data-package-compliance-snapshot]");
    if (!root) {
      return;
    }
    const self = this;

    const overallBadge = root.querySelector("[data-package-compliance-overall]");
    if (overallBadge) {
      if (payload?.loading) {
        overallBadge.className = "badge text-bg-light";
        overallBadge.textContent = "Checking...";
      } else if (payload?.error) {
        overallBadge.className = "badge text-bg-danger";
        overallBadge.textContent = "Error";
      } else {
        const overall = payload?.summary?.overall || "ok";
        overallBadge.className = overall === "error"
          ? "badge text-bg-danger"
          : overall === "warning"
            ? "badge text-bg-warning"
            : "badge text-bg-success";
        overallBadge.textContent = overall === "error" ? "Not Compliant" : overall === "warning" ? "Warning" : "Compliant";
      }
    }

    if (payload?.loading) {
      root.innerHTML =
        '<div class="d-flex align-items-center justify-content-between mb-2">' +
          '<h6 class="fw-bold mb-0">Package Compliance</h6>' +
          '<span class="badge text-bg-light" data-package-compliance-overall>Checking...</span>' +
        '</div>' +
        '<p class="text-muted small mb-0">Menganalisis compliance package berdasarkan fitur terpilih...</p>';
      return;
    }

    if (payload?.error) {
      root.innerHTML =
        '<div class="d-flex align-items-center justify-content-between mb-2">' +
          '<h6 class="fw-bold mb-0">Package Compliance</h6>' +
          '<span class="badge text-bg-danger" data-package-compliance-overall>Error</span>' +
        '</div>' +
        '<p class="text-danger small mb-0">Compliance snapshot gagal dimuat: ' + esc(payload.error) + '</p>';
      return;
    }

    const sections = Array.isArray(payload?.sections) ? payload.sections : [];
    const summary = payload?.summary || {};

    root.innerHTML =
      '<div class="d-flex align-items-center justify-content-between mb-2">' +
        '<h6 class="fw-bold mb-0">Package Compliance</h6>' +
        '<span class="badge ' +
          (summary.overall === "error" ? 'text-bg-danger' : summary.overall === "warning" ? 'text-bg-warning' : 'text-bg-success') +
          '" data-package-compliance-overall>' +
          (summary.overall === "error" ? 'Not Compliant' : summary.overall === "warning" ? 'Warning' : 'Compliant') +
        '</span>' +
      '</div>' +
      sections.map(function (section) {
        const items = Array.isArray(section.items) ? section.items : [];
        return (
          '<div class="package-compliance-section">' +
            '<div class="package-compliance-section-title">' + esc(section.title || "Section") + '</div>' +
            (items.length
              ? items.map(function (item) {
                  const statusPill = item.status === "missing"
                    ? '<span class="badge text-bg-danger">Missing</span>'
                    : item.status === "warning"
                      ? '<span class="badge text-bg-warning">Warning</span>'
                      : '<span class="badge text-bg-success">OK</span>';

                  const addBtn = (item.status === "missing" && item.feature_code)
                    ? '<button type="button" class="btn btn-sm btn-outline-primary py-0 px-1 ms-1" style="font-size:0.7rem;line-height:1.5" data-add-missing-feature="' + esc(item.feature_code) + '" title="Klik untuk menambahkan fitur ini ke paket">+ Tambah</button>'
                    : '';

                  return (
                    '<div class="package-compliance-item">' +
                      '<div class="d-flex justify-content-between gap-2 align-items-start">' +
                        '<div class="package-compliance-item-label">' + esc(item.label || item.code || "Rule") + '</div>' +
                        '<div class="d-flex align-items-center gap-1 flex-shrink-0">' + statusPill + addBtn + '</div>' +
                      '</div>' +
                      '<div class="package-compliance-item-note">' + esc(item.message || "") + '</div>' +
                    '</div>'
                  );
                }).join("")
              : '<div class="text-muted small">Tidak ada aturan yang terpicu untuk section ini.</div>') +
          '</div>'
        );
      }).join("") +
      '<div class="mt-2 p-2 border rounded bg-white small">' +
        '<strong>Overall:</strong> ' +
        esc(String(summary.errors || 0)) + ' error, ' +
        esc(String(summary.warnings || 0)) + ' warning, ' +
        esc(String(summary.passes || 0)) + ' pass.' +
      '</div>';

    // Attach click handlers for "Tambah" buttons (auto-add missing feature to package)
    root.querySelectorAll('[data-add-missing-feature]').forEach(function (btn) {
      btn.addEventListener('click', function (e) {
        e.preventDefault();
        const featureCode = btn.getAttribute('data-add-missing-feature');
        const checkbox = document.querySelector(
          'input[type="checkbox"][name="package_feature_codes"][value="' + featureCode + '"]'
        );
        if (!checkbox) {
          self.showError('Fitur "' + featureCode + '" tidak tersedia dalam katalog saat ini.');
          return;
        }
        if (!checkbox.checked) {
          checkbox.checked = true;
          const featureItem = checkbox.closest('[data-feature-item]');
          if (featureItem) {
            featureItem.scrollIntoView({ behavior: 'smooth', block: 'center' });
            featureItem.style.outline = '2px solid #0d6efd';
            featureItem.style.borderRadius = '4px';
            window.setTimeout(function () {
              featureItem.style.outline = '';
              featureItem.style.borderRadius = '';
            }, 2000);
          }
          self.handleFeatureCheckboxChange(checkbox);
          self.updateFeatureSelectionSummary();
          self.queueComplianceSnapshotRefresh();
        }
      });
    });
  },
};

export default featureComplianceMethods;
