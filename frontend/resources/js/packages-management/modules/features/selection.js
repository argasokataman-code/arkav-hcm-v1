import { FEATURE_LIMIT_INPUT_CODE, getFeatureLibrary } from "../../shared.js";

const featureSelectionMethods = {
  featureMetaFromCode: function (code) {
    let found = null;
    getFeatureLibrary().some(function (group) {
      return (group.features || []).some(function (feature) {
        if (feature.code === code) {
          found = feature;
          return true;
        }
        return false;
      });
    });

    return found;
  },

  featureLabelFromCode: function (code) {
    const meta = this.featureMetaFromCode(code);
    const foundName = meta?.name || "";

    if (foundName) {
      return foundName;
    }

    return String(code || "")
      .split("_")
      .map(function (part) {
        return part.charAt(0).toUpperCase() + part.slice(1);
      })
      .join(" ");
  },

  collectFeatureLimitDrafts: function () {
    const drafts = Object.assign({}, this.featureLimitDrafts || {});
    const root = document.getElementById('input_package_feature_chips');
    if (!root) return drafts;
    root.querySelectorAll('[data-feature-limit-input]').forEach(function (input) {
      const code = input.getAttribute('data-feature-limit-code') || '';
      if (code) drafts[code] = input.value;
    });
    return drafts;
  },

  // Returns selected feature codes. Pass tier='core'|'addon' to filter by tier radio.
  getSelectedFeatureCodes: function (tier) {
    const root = document.getElementById('input_package_feature_chips');
    if (!root) return [];
    const included = Array.from(
      root.querySelectorAll("input[name='package_feature_include']:checked")
    );
    if (!tier) return included.map(function (el) { return el.value; });
    return included.filter(function (el) {
      const safeCode = el.value.replace(/[^a-zA-Z0-9_]/g, '_');
      const tierInput = root.querySelector("input[name='pkg_feat_tier_" + safeCode + "']:checked");
      return (tierInput ? tierInput.value : 'core') === tier;
    }).map(function (el) { return el.value; });
  },

  // Returns feature configs with tier property for save
  getSelectedFeatureConfigs: function () {
    const self = this;
    const root = document.getElementById('input_package_feature_chips');
    if (!root) return [];
    return Array.from(
      root.querySelectorAll("input[name='package_feature_include']:checked")
    ).map(function (el) {
      const code = el.value;
      const safeCode = code.replace(/[^a-zA-Z0-9_]/g, '_');
      const tierInput = root.querySelector("input[name='pkg_feat_tier_" + safeCode + "']:checked");
      const tier = tierInput ? tierInput.value : 'core';
      const featureMeta = self.featureMetaFromCode(code);
      const featureConfig = {
        code: code,
        name: el.getAttribute('data-feature-name') || self.featureLabelFromCode(code),
        limit: null,
        limitError: '',
        tier: tier,
      };

      if (featureMeta?.requiresLimit) {
        const limitInput = root.querySelector('[data-feature-limit-code="' + code + '"]');
        const rawValue = String(limitInput?.value || '').trim();
        if (rawValue === '') {
          featureConfig.limitError = (featureMeta.limitLabel || 'Limit') + ' wajib diisi untuk ' + featureConfig.name + '.';
          return featureConfig;
        }
        if (!/^\d+$/.test(rawValue)) {
          featureConfig.limitError = (featureMeta.limitLabel || 'Limit') + ' harus berupa angka bulat positif.';
          return featureConfig;
        }
        const limit = Number(rawValue);
        if (!Number.isInteger(limit) || limit < 1) {
          featureConfig.limitError = (featureMeta.limitLabel || 'Limit') + ' minimal 1.';
          return featureConfig;
        }
        featureConfig.limit = limit;
      }
      return featureConfig;
    });
  },

  handleFeatureCheckboxChange: function (checkbox) {
    const code = checkbox?.value || "";
    if (!code) return;

    this.featureLimitDrafts = this.collectFeatureLimitDrafts();

    // Show/hide tier selector
    const safeCode = code.replace(/[^a-zA-Z0-9_]/g, '_');
    const tierRow = document.getElementById('pkg_feat_' + safeCode + '_tier_row');
    if (tierRow) {
      tierRow.style.display = checkbox.checked ? 'block' : 'none';
    }

    // Enable/disable limit input
    const limitInput = document.querySelector('[data-feature-limit-code="' + code + '"]');
    if (limitInput) {
      limitInput.disabled = !checkbox.checked;
      if (checkbox.checked && !String(limitInput.value || "").trim()) {
        limitInput.focus();
      }
    }

    if (code === FEATURE_LIMIT_INPUT_CODE) {
      this.syncTopFieldFromMaxEmployeesFeature();
    }
  },

  getMaxEmployeesFeatureControls: function () {
    return {
      checkbox: document.querySelector("input[type='checkbox'][name='package_feature_include'][value='" + FEATURE_LIMIT_INPUT_CODE + "']"),
      limitInput: document.querySelector("[data-feature-limit-code='" + FEATURE_LIMIT_INPUT_CODE + "']"),
    };
  },

  syncMaxEmployeesFeatureFromTopField: function () {
    const topInput = document.getElementById("input_package_max_employees");
    if (!topInput) {
      return;
    }

    const rawValue = String(topInput.value || "").trim();
    this.featureLimitDrafts[FEATURE_LIMIT_INPUT_CODE] = rawValue;

    const controls = this.getMaxEmployeesFeatureControls();
    if (!controls.checkbox || !controls.limitInput) {
      return;
    }

    const hasValue = rawValue !== "";

    controls.checkbox.checked = hasValue;
    controls.limitInput.disabled = !hasValue;

    if (hasValue) {
      controls.limitInput.value = rawValue;
      this.featureLimitDrafts[FEATURE_LIMIT_INPUT_CODE] = rawValue;
    } else {
      controls.limitInput.value = "";
      this.featureLimitDrafts[FEATURE_LIMIT_INPUT_CODE] = "";
    }
  },

  syncTopFieldFromMaxEmployeesFeature: function () {
    const topInput = document.getElementById("input_package_max_employees");
    if (!topInput) {
      return;
    }

    const controls = this.getMaxEmployeesFeatureControls();
    if (!controls.checkbox || !controls.limitInput) {
      return;
    }

    if (!controls.checkbox.checked) {
      topInput.value = "";
      return;
    }

    topInput.value = String(controls.limitInput.value || "").trim();
  },

  describeFeatureBadge: function (feature) {
    const code = typeof feature === "string" ? feature : feature?.code;
    const label = this.featureLabelFromCode(code || feature?.name || "Feature");
    const limit = typeof feature === "string" ? null : feature?.limit;
    if (code === FEATURE_LIMIT_INPUT_CODE && limit !== null && limit !== undefined && limit !== "") {
      return label + ": " + String(limit) + " org";
    }

    return label;
  },
};

export default featureSelectionMethods;
