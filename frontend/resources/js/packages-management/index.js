import bootstrapMethods from "./modules/bootstrap.js";
import dataMethods from "./modules/data.js";
import featureMethods from "./modules/features.js";
import modalMethods from "./modules/modals.js";
import feedbackMethods from "./modules/feedback.js";

const PackagesManager = {
  isInitialized: false,
  currentPage: 1,
  totalPages: 1,
  totalItems: 0,
  packages: [],
  addons: [],
  currentEditId: null,
  currentEditSnapshot: null,
  currentPricingDirty: false,
  currentAddonEditId: null,
  currentStatus: "all",
  currentSearch: "",
  currentAddonPage: 1,
  totalAddonPages: 1,
  totalAddonItems: 0,
  addonStatus: "all",
  addonSearch: "",
  featureLimitDrafts: {},
  featureHealthcheckSummary: null,
  packageComplianceSnapshot: null,
  packageComplianceRefreshTimer: null,
  compareSelectionLimit: 3,
  packageModalInstance: null,
  addonModalInstance: null,
};

Object.assign(PackagesManager, bootstrapMethods, dataMethods, featureMethods, modalMethods, feedbackMethods);

if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", function () {
    PackagesManager.init();
  });
} else {
  PackagesManager.init();
}

window.PackagesManager = PackagesManager;

export default PackagesManager;
