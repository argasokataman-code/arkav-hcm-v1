import featureSelectionMethods from "./features/selection.js";
import featureCatalogUiMethods from "./features/catalog-ui.js";
import featureHealthcheckMethods from "./features/healthcheck.js";
import featureComplianceMethods from "./features/compliance.js";

const featureMethods = Object.assign(
  {},
  featureSelectionMethods,
  featureCatalogUiMethods,
  featureHealthcheckMethods,
  featureComplianceMethods
);

export default featureMethods;
