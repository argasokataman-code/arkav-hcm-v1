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
    const catalogRoot = document.getElementById("input_package_feature_chips");
    const drafts = Object.assign({}, this.featureLimitDrafts || {});
    if (!catalogRoot) return drafts;

    catalogRoot.querySelectorAll("[data-feature-limit-input]").forEach(function (input) {
      const code = input.getAttribute("data-feature-limit-code") || "";
      drafts[code] = input.value;
    });

    return drafts;
  },

  getSelectedFeatureCodes: function () {
    const catalogRoot = document.getElementById("input_package_feature_chips");
    if (!catalogRoot) return [];
    return Array.from(
      catalogRoot.querySelectorAll("input[type='checkbox'][name='package_feature_codes']:checked")
    ).map(function (input) {
      return input.value;
    });
  },

  getSelectedFeatureConfigs: function () {
    const catalogRoot = document.getElementById("input_package_feature_chips");
    if (!catalogRoot) return [];

    return Array.from(
      catalogRoot.querySelectorAll("input[type='checkbox'][name='package_feature_codes']:checked")
    ).map(
      function (input) {
        const code = input.value;
        const featureMeta = this.featureMetaFromCode(code);
        const featureConfig = {
          code: code,
          name: input.getAttribute("data-feature-name") || this.featureLabelFromCode(code),
          limit: null,
          limitError: "",
        };

        if (featureMeta?.requiresLimit) {
          const limitInput = catalogRoot.querySelector('[data-feature-limit-code="' + code + '"]');
          const rawValue = String(limitInput?.value || "").trim();

          if (rawValue === "") {
            featureConfig.limitError = featureMeta.limitLabel + " wajib diisi untuk " + featureConfig.name + ".";
            return featureConfig;
          }

          if (!/^\d+$/.test(rawValue)) {
            featureConfig.limitError = featureMeta.limitLabel + " harus berupa angka bulat positif.";
            return featureConfig;
          }

          const limit = Number(rawValue);
          if (!Number.isInteger(limit) || limit < 1) {
            featureConfig.limitError = featureMeta.limitLabel + " minimal 1.";
            return featureConfig;
          }

          featureConfig.limit = limit;
        }

        return featureConfig;
      }.bind(this)
    );
  },

  handleFeatureCheckboxChange: function (checkbox) {
    const code = checkbox?.value || "";
    if (!code) {
      return;
    }

    this.featureLimitDrafts = this.collectFeatureLimitDrafts();

    const limitInput = document.querySelector('[data-feature-limit-code="' + code + '"]');
    if (!limitInput) {
      return;
    }

    limitInput.disabled = !checkbox.checked;
    if (checkbox.checked && !String(limitInput.value || "").trim()) {
      limitInput.focus();
    }

    if (code === FEATURE_LIMIT_INPUT_CODE) {
      this.syncTopFieldFromMaxEmployeesFeature();
    }
  },

  getMaxEmployeesFeatureControls: function () {
    return {
      checkbox: document.querySelector("input[type='checkbox'][name='package_feature_codes'][value='" + FEATURE_LIMIT_INPUT_CODE + "']"),
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
