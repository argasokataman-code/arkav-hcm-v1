// Minimal shim: delegate initialization to the new modular entry or fall back
// to the legacy bundle. To opt into the modular version, set
// `window.ArcavEmployeesUseLegacy = false` before this script executes.
(async function () {
    'use strict';

    var preferLegacy = typeof window !== 'undefined' && window.ArcavEmployeesUseLegacy !== false;

    if (preferLegacy) {
        // dynamic import of legacy UMD-like module (safe fallback)
        try {
            await import('./employees-data.legacy.js');
        } catch (err) {
            console.error('Failed loading legacy employees module', err);
        }
        return;
    }

    // Try modular index first
    try {
        var mod = await import('./index.js');
        try {
            if (mod && typeof mod.initEmployeesModule === 'function') {
                mod.initEmployeesModule(document);
            } else if (mod && typeof mod.init === 'function') {
                mod.init(document);
            }
        } catch (err) {
            console.error('employees index init error', err);
            try { await import('./employees-data.legacy.js'); } catch (_) {}
        }
    } catch (err) {
        console.error('Failed loading employees index', err);
        try { await import('./employees-data.legacy.js'); } catch (_) {}
    }
})();
