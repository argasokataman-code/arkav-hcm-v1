import featureSelectionMethods from "./features/selection";
import featureCatalogUiMethods from "./features/catalog-ui";
import featureHealthcheckMethods from "./features/healthcheck";
import featureComplianceMethods from "./features/compliance";

const featureMethods = Object.assign(
  {},
  featureSelectionMethods,
  featureCatalogUiMethods,
  featureHealthcheckMethods,
  featureComplianceMethods
);

export default featureMethods;
