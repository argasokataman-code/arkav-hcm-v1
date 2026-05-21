import { requestJson } from './api';

export let orgDepartmentsFlat = [];
export let orgDesignationsFlat = [];
export let orgTeamsFlat = [];
export let orgMastersPromise = null;

export function fillDesignationSelectForDepartment(selectEl, departmentId, preferredValue) {
    var pref = preferredValue != null ? String(preferredValue) : "";
    selectEl.innerHTML = '<option value="">— Pilih —</option>';
    orgDesignationsFlat.forEach(function (d) {
        var deptId = d.departmentId != null ? String(d.departmentId) : "";
        if (!departmentId || !deptId || deptId === String(departmentId)) {
            var opt = document.createElement("option");
            opt.value = String(d.id);
            opt.textContent = d.name || d.code || String(d.id);
            selectEl.appendChild(opt);
        }
    });
    if (pref) {
        var match = Array.prototype.slice.call(selectEl.options).some(function (o) {
            return o.value === pref;
        });
        if (match) {
            selectEl.value = pref;
        }
    }
}

export function loadTeamsDropdown(selectEl, preferredValue) {
    var pref = preferredValue != null ? String(preferredValue) : "";
    selectEl.innerHTML = '<option value="">— Pilih Team (opsional) —</option>';
    selectEl.removeAttribute("data-inactive-team-pref");
    orgTeamsFlat.forEach(function (t) {
        if (t.is_active) {
            var opt = document.createElement("option");
            opt.value = String(t.id);
            opt.textContent = t.name || String(t.id);
            selectEl.appendChild(opt);
        }
    });
    if (pref) {
        var match = Array.prototype.slice.call(selectEl.options).some(function (o) {
            return o.value === pref;
        });
        if (match) {
            selectEl.value = pref;
            return;
        }

        var inactiveCurrent = orgTeamsFlat.find(function (t) {
            return String(t.id) === pref && !t.is_active;
        });

        if (inactiveCurrent) {
            var inactiveInfo = document.createElement("option");
            inactiveInfo.value = "";
            inactiveInfo.textContent = "Current team inactive: " + (inactiveCurrent.name || pref) + " (reassign to active team)";
            selectEl.insertBefore(inactiveInfo, selectEl.options[1] || null);
            selectEl.value = "";
            selectEl.setAttribute("data-inactive-team-pref", pref);
        }
    }
}

export function rebuildDepartmentSelectOptions() {
    document.querySelectorAll("[data-employee-org-department]").forEach(function (sel) {
        var cur = sel.value;
        sel.innerHTML = '<option value="">— Pilih —</option>';
        orgDepartmentsFlat.forEach(function (d) {
            var opt = document.createElement("option");
            opt.value = String(d.id);
            opt.textContent = d.name || d.code || String(d.id);
            sel.appendChild(opt);
        });
        if (cur) {
            var ok = Array.prototype.slice.call(sel.options).some(function (o) {
                return o.value === cur;
            });
            if (ok) {
                sel.value = cur;
            }
        }
        var form = sel.closest("form");
        var des = form ? form.querySelector("[data-employee-org-designation]") : null;
        if (des) {
            fillDesignationSelectForDepartment(des, sel.value, des.value);
        }
    });
}

export function hydrateEmployeeOrgMasters() {
    return Promise.all([
        requestJson("get", "/v1/hcm/departments", null),
        requestJson("get", "/v1/hcm/designations", null),
        requestJson("get", "/v1/hcm/teams", null),
    ]).then(function (results) {
        orgDepartmentsFlat = results[0] && results[0].success && Array.isArray(results[0].data) ? results[0].data : [];
        orgDesignationsFlat = results[1] && results[1].success && Array.isArray(results[1].data) ? results[1].data : [];
        orgTeamsFlat = results[2] && results[2].success && Array.isArray(results[2].data) ? results[2].data : [];
        rebuildDepartmentSelectOptions();
        // After masters are hydrated, ensure bulk template/upload links are actionable
        try {
            var templateLinks = Array.prototype.slice.call(document.querySelectorAll('[data-employee-bulk-template-link]'));
            var uploadOpeners = Array.prototype.slice.call(document.querySelectorAll('[data-employee-bulk-upload-open]'));
            templateLinks.concat(uploadOpeners).forEach(function (el) {
                if (!el) return;
                el.classList.remove('disabled');
                el.setAttribute('aria-disabled', 'false');
                el.style.pointerEvents = 'auto';
                el.removeAttribute('title');
            });
            if (window.ArcavEmployeesModuleLoaders && typeof window.ArcavEmployeesModuleLoaders.loadBindSalaryBulkUploadModule === 'function') {
                try { window.ArcavEmployeesModuleLoaders.loadBindSalaryBulkUploadModule(); } catch (_e) { }
            }
        } catch (_e) { }
    });
}

export function ensureEmployeeOrgMastersLoaded() {
    if (orgMastersPromise) {
        return orgMastersPromise;
    }
    orgMastersPromise = hydrateEmployeeOrgMasters().catch(function () {
        orgMastersPromise = null;
        return null;
    });
    return orgMastersPromise;
}

export function bindEmployeeOrgDepartmentChange() {
    if (document.body.getAttribute("data-employee-org-dept-bound")) {
        return;
    }
    document.body.setAttribute("data-employee-org-dept-bound", "1");
    document.addEventListener("change", function (e) {
        var el = e.target && e.target.closest ? e.target.closest("[data-employee-org-department]") : null;
        if (!el) {
            return;
        }
        var form = el.closest("form");
        if (!form) {
            return;
        }
        var des = form.querySelector("[data-employee-org-designation]");
        if (!des) {
            return;
        }
        var keep = des.value;
        fillDesignationSelectForDepartment(des, el.value, keep);
    });
}

export function getOrgSnapshot() {
    return {
        departments: orgDepartmentsFlat.slice(),
        designations: orgDesignationsFlat.slice(),
        teams: orgTeamsFlat.slice(),
    };
}
