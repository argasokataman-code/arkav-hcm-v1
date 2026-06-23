// Minimal shim: delegate initialization to the new modular entry or fall back
// to the legacy bundle. To opt into the modular version, set
// `window.ArcavEmployeesUseLegacy = false` before this script executes.
(async function () {
    'use strict';

    var baseUrl = (function () {
        try {
            var scripts = document.getElementsByTagName('script');
            for (var i = scripts.length - 1; i >= 0; i--) {
                var s = scripts[i];
                if (!s || !s.src) continue;
                var m = s.src.match(/(.*\/)(?:employees-data)(?:\.js)?(?:\?.*)?$/);
                if (m && m[1]) return m[1];
            }
        } catch (e) {}
        return '/build/js/employees/';
    })();

    var preferLegacy = typeof window !== 'undefined' && window.ArcavEmployeesUseLegacy !== false;

    if (preferLegacy) {
        try {
            await import(baseUrl + 'employees-data.legacy.js');
        } catch (err) {
            console.error('Failed loading legacy employees module', err);
        }
        return;
    }

    try {
        var mod = await import(baseUrl + 'index.js');
        try {
            if (mod && typeof mod.initEmployeesModule === 'function') {
                mod.initEmployeesModule(document);
            } else if (mod && typeof mod.init === 'function') {
                mod.init(document);
            }
        } catch (err) {
            console.error('employees index init error', err);
            try { await import(baseUrl + 'employees-data.legacy.js'); } catch (_) {}
        }
    } catch (err) {
        console.error('Failed loading employees index', err);
        try { await import(baseUrl + 'employees-data.legacy.js'); } catch (_) {}
    }
})();
