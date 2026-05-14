(function (window) {
    "use strict";

    var bindHolidaysModuleRef = null;
    var bindHolidaysModulePromise = null;
    var bindOvertimeCalculatorModuleRef = null;
    var bindOvertimeCalculatorModulePromise = null;
    var bindOvertimeModuleRef = null;
    var bindOvertimeModulePromise = null;

    function resolveBindHolidaysModule() {
        if (typeof bindHolidaysModuleRef === "function") {
            return bindHolidaysModuleRef;
        }
        if (window.ArcavHcmExtrasModules && typeof window.ArcavHcmExtrasModules.bindHolidaysModule === "function") {
            bindHolidaysModuleRef = window.ArcavHcmExtrasModules.bindHolidaysModule;
            return bindHolidaysModuleRef;
        }
        return null;
    }

    function loadBindHolidaysModule() {
        var resolved = resolveBindHolidaysModule();
        if (resolved) {
            return Promise.resolve(resolved);
        }
        if (bindHolidaysModulePromise) {
            return bindHolidaysModulePromise;
        }
        try {
            var dynamicImport = new Function("modulePath", "return import(modulePath);");
            bindHolidaysModulePromise = dynamicImport("./hcm-extras/hcm-extras-holidays.js")
                .then(function (mod) {
                    if (mod && typeof mod.bindHolidaysModule === "function") {
                        bindHolidaysModuleRef = mod.bindHolidaysModule;
                    }
                    return resolveBindHolidaysModule();
                })
                .catch(function () {
                    return null;
                });
        } catch (_error) {
            bindHolidaysModulePromise = Promise.resolve(null);
        }
        return bindHolidaysModulePromise;
    }

    function resolveBindOvertimeCalculatorModule() {
        if (typeof bindOvertimeCalculatorModuleRef === "function") {
            return bindOvertimeCalculatorModuleRef;
        }
        if (window.ArcavHcmExtrasModules && typeof window.ArcavHcmExtrasModules.bindOvertimeCalculatorModule === "function") {
            bindOvertimeCalculatorModuleRef = window.ArcavHcmExtrasModules.bindOvertimeCalculatorModule;
            return bindOvertimeCalculatorModuleRef;
        }
        return null;
    }

    function loadBindOvertimeCalculatorModule() {
        var resolved = resolveBindOvertimeCalculatorModule();
        if (resolved) {
            return Promise.resolve(resolved);
        }
        if (bindOvertimeCalculatorModulePromise) {
            return bindOvertimeCalculatorModulePromise;
        }
        try {
            var dynamicImport = new Function("modulePath", "return import(modulePath);");
            bindOvertimeCalculatorModulePromise = dynamicImport("./hcm-extras/hcm-extras-overtime-calculator.js")
                .then(function (mod) {
                    if (mod && typeof mod.bindOvertimeCalculatorModule === "function") {
                        bindOvertimeCalculatorModuleRef = mod.bindOvertimeCalculatorModule;
                    }
                    return resolveBindOvertimeCalculatorModule();
                })
                .catch(function () {
                    return null;
                });
        } catch (_error) {
            bindOvertimeCalculatorModulePromise = Promise.resolve(null);
        }
        return bindOvertimeCalculatorModulePromise;
    }

    function resolveBindOvertimeModule() {
        if (typeof bindOvertimeModuleRef === "function") {
            return bindOvertimeModuleRef;
        }
        if (window.ArcavHcmExtrasModules && typeof window.ArcavHcmExtrasModules.bindOvertimeModule === "function") {
            bindOvertimeModuleRef = window.ArcavHcmExtrasModules.bindOvertimeModule;
            return bindOvertimeModuleRef;
        }
        return null;
    }

    function loadBindOvertimeModule() {
        var resolved = resolveBindOvertimeModule();
        if (resolved) {
            return Promise.resolve(resolved);
        }
        if (bindOvertimeModulePromise) {
            return bindOvertimeModulePromise;
        }
        try {
            var dynamicImport = new Function("modulePath", "return import(modulePath);");
            bindOvertimeModulePromise = dynamicImport("./hcm-extras/hcm-extras-overtime.js")
                .then(function (mod) {
                    if (mod && typeof mod.bindOvertimeModule === "function") {
                        bindOvertimeModuleRef = mod.bindOvertimeModule;
                    }
                    return resolveBindOvertimeModule();
                })
                .catch(function () {
                    return null;
                });
        } catch (_error) {
            bindOvertimeModulePromise = Promise.resolve(null);
        }
        return bindOvertimeModulePromise;
    }

    window.ArcavHcmExtrasModuleLoaders = {
        loadBindHolidaysModule: loadBindHolidaysModule,
        loadBindOvertimeCalculatorModule: loadBindOvertimeCalculatorModule,
        loadBindOvertimeModule: loadBindOvertimeModule,
        resolveBindHolidaysModule: resolveBindHolidaysModule,
        resolveBindOvertimeCalculatorModule: resolveBindOvertimeCalculatorModule,
        resolveBindOvertimeModule: resolveBindOvertimeModule,
    };
})(window);
