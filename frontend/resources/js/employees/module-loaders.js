(function (window) {
    "use strict";

    // Compute base URL for employee modules (resolves issues when this file
    // is included as a non-module script and dynamic import() would resolve
    // relative to document location). Falls back to the build path.
    var ___arcav_employees_base = (function () {
        try {
            var scripts = document.getElementsByTagName('script');
            for (var i = scripts.length - 1; i >= 0; i--) {
                var s = scripts[i];
                if (!s || !s.src) continue;
                var m = s.src.match(/(.*\/)(?:module-loaders)(?:\.js)?(?:\?.*)?$/);
                if (m && m[1]) return m[1];
            }
        }
        catch (e) { }
        return '/build/js/employees/';
    })();


    var bindEmployeeCompensationFormsModuleRef = null;
    var bindEmployeeCompensationFormsModulePromise = null;
    var bindQuickPreviewModuleRef = null;
    var bindQuickPreviewModulePromise = null;
    var bindEmployeePhotoModalPreviewModuleRef = null;
    var bindEmployeePhotoModalPreviewModulePromise = null;
    var bindSalaryBulkUploadModuleRef = null;
    var bindSalaryBulkUploadModulePromise = null;

    function resolveBindEmployeeCompensationFormsModule() {
        if (typeof bindEmployeeCompensationFormsModuleRef === "function") {
            return bindEmployeeCompensationFormsModuleRef;
        }
        if (window.ArcavEmployeesModules && typeof window.ArcavEmployeesModules.bindEmployeeCompensationFormsModule === "function") {
            bindEmployeeCompensationFormsModuleRef = window.ArcavEmployeesModules.bindEmployeeCompensationFormsModule;
            return bindEmployeeCompensationFormsModuleRef;
        }
        return null;
    }

    function loadBindEmployeeCompensationFormsModule() {
        var resolved = resolveBindEmployeeCompensationFormsModule();
        if (resolved) {
            return Promise.resolve(resolved);
        }
        if (bindEmployeeCompensationFormsModulePromise) {
            return bindEmployeeCompensationFormsModulePromise;
        }
        try {
            var dynamicImport = function (path) { return import(path); };
            bindEmployeeCompensationFormsModulePromise = dynamicImport(___arcav_employees_base + "employees-compensation-forms.js")
                .then(function (mod) {
                    if (mod && typeof mod.bindEmployeeCompensationFormsModule === "function") {
                        bindEmployeeCompensationFormsModuleRef = mod.bindEmployeeCompensationFormsModule;
                    }
                    return resolveBindEmployeeCompensationFormsModule();
                })
                .catch(function () {
                    return null;
                });
        } catch (_error) {
            bindEmployeeCompensationFormsModulePromise = Promise.resolve(null);
        }
        return bindEmployeeCompensationFormsModulePromise;
    }

    function resolveBindQuickPreviewModule() {
        if (typeof bindQuickPreviewModuleRef === "function") {
            return bindQuickPreviewModuleRef;
        }
        if (window.ArcavEmployeesModules && typeof window.ArcavEmployeesModules.bindQuickPreviewModule === "function") {
            bindQuickPreviewModuleRef = window.ArcavEmployeesModules.bindQuickPreviewModule;
            return bindQuickPreviewModuleRef;
        }
        return null;
    }

    function loadBindQuickPreviewModule() {
        var resolved = resolveBindQuickPreviewModule();
        if (resolved) {
            return Promise.resolve(resolved);
        }
        if (bindQuickPreviewModulePromise) {
            return bindQuickPreviewModulePromise;
        }
        try {
            var dynamicImport = function (path) { return import(path); };
            bindQuickPreviewModulePromise = dynamicImport(___arcav_employees_base + "employees-quick-preview.js")
                .then(function (mod) {
                    if (mod && typeof mod.bindQuickPreviewModule === "function") {
                        bindQuickPreviewModuleRef = mod.bindQuickPreviewModule;
                    }
                    return resolveBindQuickPreviewModule();
                })
                .catch(function () {
                    return null;
                });
        } catch (_error) {
            bindQuickPreviewModulePromise = Promise.resolve(null);
        }
        return bindQuickPreviewModulePromise;
    }

    function resolveBindEmployeePhotoModalPreviewModule() {
        if (typeof bindEmployeePhotoModalPreviewModuleRef === "function") {
            return bindEmployeePhotoModalPreviewModuleRef;
        }
        if (window.ArcavEmployeesModules && typeof window.ArcavEmployeesModules.bindEmployeePhotoModalPreviewModule === "function") {
            bindEmployeePhotoModalPreviewModuleRef = window.ArcavEmployeesModules.bindEmployeePhotoModalPreviewModule;
            return bindEmployeePhotoModalPreviewModuleRef;
        }
        return null;
    }

    function loadBindEmployeePhotoModalPreviewModule() {
        var resolved = resolveBindEmployeePhotoModalPreviewModule();
        if (resolved) {
            return Promise.resolve(resolved);
        }
        if (bindEmployeePhotoModalPreviewModulePromise) {
            return bindEmployeePhotoModalPreviewModulePromise;
        }
        try {
            var dynamicImport = function (path) { return import(path); };
            bindEmployeePhotoModalPreviewModulePromise = dynamicImport(___arcav_employees_base + "employees-photo-modal-preview.js")
                .then(function (mod) {
                    if (mod && typeof mod.bindEmployeePhotoModalPreviewModule === "function") {
                        bindEmployeePhotoModalPreviewModuleRef = mod.bindEmployeePhotoModalPreviewModule;
                    }
                    return resolveBindEmployeePhotoModalPreviewModule();
                })
                .catch(function () {
                    return null;
                });
        } catch (_error) {
            bindEmployeePhotoModalPreviewModulePromise = Promise.resolve(null);
        }
        return bindEmployeePhotoModalPreviewModulePromise;
    }

    function resolveBindSalaryBulkUploadModule() {
        if (typeof bindSalaryBulkUploadModuleRef === "function") {
            return bindSalaryBulkUploadModuleRef;
        }
        if (window.ArcavEmployeesModules && typeof window.ArcavEmployeesModules.bindSalaryBulkUploadModule === "function") {
            bindSalaryBulkUploadModuleRef = window.ArcavEmployeesModules.bindSalaryBulkUploadModule;
            return bindSalaryBulkUploadModuleRef;
        }
        return null;
    }

    function loadBindSalaryBulkUploadModule() {
        var resolved = resolveBindSalaryBulkUploadModule();
        if (resolved) {
            return Promise.resolve(resolved);
        }
        if (bindSalaryBulkUploadModulePromise) {
            return bindSalaryBulkUploadModulePromise;
        }
        try {
            var dynamicImport = function (path) { return import(path); };
            bindSalaryBulkUploadModulePromise = dynamicImport(___arcav_employees_base + "employees-salary-bulk-upload.js")
                .then(function (mod) {
                    if (mod && typeof mod.bindSalaryBulkUploadModule === "function") {
                        bindSalaryBulkUploadModuleRef = mod.bindSalaryBulkUploadModule;
                    }
                    return resolveBindSalaryBulkUploadModule();
                })
                .catch(function () {
                    return null;
                });
        } catch (_error) {
            bindSalaryBulkUploadModulePromise = Promise.resolve(null);
        }
        return bindSalaryBulkUploadModulePromise;
    }

    window.ArcavEmployeesModuleLoaders = {
        loadBindEmployeeCompensationFormsModule: loadBindEmployeeCompensationFormsModule,
        loadBindEmployeePhotoModalPreviewModule: loadBindEmployeePhotoModalPreviewModule,
        loadBindQuickPreviewModule: loadBindQuickPreviewModule,
        loadBindSalaryBulkUploadModule: loadBindSalaryBulkUploadModule,
        resolveBindEmployeeCompensationFormsModule: resolveBindEmployeeCompensationFormsModule,
        resolveBindEmployeePhotoModalPreviewModule: resolveBindEmployeePhotoModalPreviewModule,
        resolveBindQuickPreviewModule: resolveBindQuickPreviewModule,
        resolveBindSalaryBulkUploadModule: resolveBindSalaryBulkUploadModule,
    };
})(window);
