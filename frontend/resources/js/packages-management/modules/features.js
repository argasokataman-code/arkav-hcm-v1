import featureSelectionMethods from "./features/selection.js";
import featureCatalogUiMethods from "./features/catalog-ui.js";
import featureHealthcheckMethods from "./features/healthcheck.js";
import featureComplianceMethods from "./features/compliance.js";
import featureClassificationMethods from "./features/classifications.js";

const featureMethods = Object.assign(
  {},
  featureSelectionMethods,
  featureCatalogUiMethods,
  featureHealthcheckMethods,
  featureComplianceMethods,
  featureClassificationMethods
);

export default featureMethods;
