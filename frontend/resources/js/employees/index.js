// Modular entry point for employees module.
// Imports and re-exports all modular sub-modules.
// The legacy bundle is loaded for bootstrapping; once all init logic
// is extracted, this file will bootstrap directly from modular files.

import {
    employeesListUrl, requestAuthMe, requestEmployees,
    requestEmployeesByState, requestAllEmployeesAggregated,
    requestJson, requestFormData, requestEmployeeDetail,
} from './api.js';

import {
    escapeHtml, formatEmployeeCode, formatApiError,
    formatRupiah, getCurrentListUrl, buildEmployeeDetailUrl,
    downloadBlob, toCsv, normalizeEmployeeScope,
} from './helpers.js';

import * as State from './state.js';
import * as Org from './org.js';

export { makeListHandlers } from './list.js';
export { makeReportHandlers } from './report.js';
export { makeRenderers } from './renderers.js';
export { makeBinders } from './binders.js';
export { makeBulkHandlers } from './bulk.js';

export { Api, Helpers };
import * as Api from './api.js';
import * as Helpers from './helpers.js';

// Boot the application by importing the legacy bundle.
// The IIFE runs on import and initializes everything.
import './employees-data.legacy.js';

export function init(rootEl) {
    // Legacy IIFE already booted on import.
    // This hook is kept for future modular init when all logic
    // has been extracted from the legacy bundle.
}

export function initEmployeesModule(rootEl) {
    return init(rootEl);
}

export default {
    init,
    Api,
    Helpers,
    State,
    Org,
};
