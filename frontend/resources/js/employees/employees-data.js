// Minimal shim: delegate initialization to the new modular entry or fall back
// to the legacy bundle. To opt into the modular version, set
// `window.ArcavEmployeesUseLegacy = false` before this script executes.
(function () {
    'use strict';

    var preferLegacy = typeof window !== 'undefined' && window.ArcavEmployeesUseLegacy !== false;

    if (preferLegacy) {
        // dynamic import of legacy UMD-like module (safe fallback)
        import('./employees-data.legacy.js').catch(function (err) {
            console.error('Failed loading legacy employees module', err);
        });
        return;
    }

    // Try modular index first
    import('./index.js').then(function (mod) {
        try {
            if (mod && typeof mod.initEmployeesModule === 'function') {
                mod.initEmployeesModule(document);
            } else if (mod && typeof mod.init === 'function') {
                mod.init(document);
            }
        } catch (err) {
            console.error('employees index init error', err);
            // fallback to legacy
            import('./employees-data.legacy.js').catch(function () {});
        }
    }).catch(function (err) {
        console.error('Failed loading employees index', err);
        import('./employees-data.legacy.js').catch(function () {});
    });
})();
