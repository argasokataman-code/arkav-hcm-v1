import * as Api from './api';
import * as Helpers from './helpers';

export { Api, Helpers };

export function initEmployeesModule(rootEl) {
    // placeholder initialization; existing employees-data.js will bootstrap as before
    if (!rootEl) return;
    // if the original global init function existed, call it if present
    if (typeof window.initEmployeesData === 'function') {
        try {
            window.initEmployeesData(rootEl);
        } catch (e) {
            // swallow to avoid breaking pages during incremental migration
            console.error('initEmployeesData error', e);
        }
    }
}

export default {
    init: initEmployeesModule,
};
