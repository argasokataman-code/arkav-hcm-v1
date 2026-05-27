(function (window, document) {
    "use strict";

    var ADDITION_CATEGORIES = [
        "basic_wage",
        "fixed_allowance",
        "irregular_allowance",
        "overtime",
        "thr",
        "bonus",
        "natura_taxable",
        "natura_non_taxable",
        "special_allowance",
        "reimbursement",
        "termination_benefit",
        "employer_cost_display",
        "other_addition",
    ];
    var DEDUCTION_CATEGORIES = [
        "bpjs_health_employee",
        "bpjs_jht_employee",
        "bpjs_jp_employee",
        "pension_employee",
        "pph21_ter",
        "pph21_december_recon",
        "other_statutory",
        "internal_advance",
        "internal_loan",
        "internal_cooperative",
        "internal_other",
        "other_deduction",
    ];

    var CATEGORY_LABELS = {
        basic_wage: "Upah pokok",
        fixed_allowance: "Tunjangan tetap",
        irregular_allowance: "Tunjangan tidak tetap / insentif",
        overtime: "Upah lembur",
        thr: "THR",
        bonus: "Bonus",
        natura_taxable: "Natura kena pajak",
        natura_non_taxable: "Natura tidak kena pajak",
        special_allowance: "Tunjangan khusus / insidentil",
        reimbursement: "Reimbursement",
        termination_benefit: "Kompensasi terminasi",
        employer_cost_display: "Beban perusahaan (info)",
        other_addition: "Pendapatan lain",
        bpjs_health_employee: "BPJS Kesehatan (peserta)",
        bpjs_jht_employee: "BPJS JHT (peserta)",
        bpjs_jp_employee: "BPJS JP (peserta)",
        pension_employee: "Iuran pensiun pekerja",
        pph21_ter: "PPh 21 TER",
        pph21_december_recon: "PPh 21 Desember / rekonsiliasi",
        other_statutory: "Potongan wajib lain",
        internal_advance: "Kasbon / uang muka",
        internal_loan: "Pinjaman internal",
        internal_cooperative: "Koperasi",
        internal_other: "Potongan internal lain",
        other_deduction: "Potongan lain",
    };

    var salaryComponentsCache = [];
    var payrollItemsCache = [];
    var linkedSalaryComponentIdsFromApi = [];
    var editUnlinkMode = false;

    function pageKindFilter() {
        var path = (window.location.pathname || "").replace(/\/+$/, "") || "/";
        if (path === "/payroll-deduction") {
            return "deduction";
        }
        if (path === "/payroll") {
            return "addition";
        }
        return null;
    }

    function applyPageKindToModals() {
        var pk = pageKindFilter();
        var locked = pk === "addition" || pk === "deduction";
        document.querySelectorAll("[data-payroll-item-kind-only]").forEach(function (el) {
            if (locked) {
                el.classList.add("d-none");
            } else {
                el.classList.remove("d-none");
            }
        });
        document.querySelectorAll("[data-payroll-item-category-wrap]").forEach(function (el) {
            el.classList.remove("col-md-6", "col-md-12");
            el.classList.add(locked ? "col-md-12" : "col-md-6");
        });
    }

    function onAuthFailure(status, data) {
        if (window.AuthApi && typeof window.AuthApi.handleUnauthorizedFromApi === "function") {
            return window.AuthApi.handleUnauthorizedFromApi(status, data);
        }
        return false;
    }

    function apiRequest(method, url, body) {
        var headers = { Accept: "application/json" };
        if (body && typeof body === "object" && !(body instanceof FormData)) {
            headers["Content-Type"] = "application/json";
        }
        var token = (window.AuthApi && window.AuthApi.getToken()) || localStorage.getItem('arcav_access_token');
        if (token) { headers['Authorization'] = 'Bearer ' + token; }
        if (window.axios) {
            return window.axios({ method: method, url: url, headers: headers, data: body, withCredentials: true })
                .then(function (res) {
                    return res.data;
                })
                .catch(function (err) {
                    var st = err && err.response ? err.response.status : 0;
                    var d = err && err.response ? err.response.data : null;
                    if (onAuthFailure(st, d)) {
                        return null;
                    }
                    return Promise.reject({ status: st, data: d });
                });
        }
        var opts = { method: method, headers: headers, credentials: "same-origin" };
        if (body && method !== "GET") {
            opts.body = body instanceof FormData ? body : JSON.stringify(body);
        }
        return fetch(url, opts).then(function (res) {
            return res.json().catch(function () {
                return {};
            }).then(function (data) {
                if (!res.ok) {
                    if (onAuthFailure(res.status, data)) {
                        return null;
                    }
                    return Promise.reject({ status: res.status, data: data });
                }
                return data;
            });
        });
    }

    function escapeHtml(s) {
        if (s == null) {
            return "";
        }
        return String(s)
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;");
    }

    function categoryLabel(code) {
        return CATEGORY_LABELS[code] || code || "—";
    }

    function formatPayrollItemDefault(row) {
        if (row.linkedToMaster && row.masterDefaultPercent != null && row.masterDefaultPercent !== "") {
            var basis = row.masterPercentBasis ? " · " + (CATEGORY_LABELS[row.masterPercentBasis] || row.masterPercentBasis) : "";
            return escapeHtml(String(row.masterDefaultPercent)) + "%" + basis;
        }
        if (row.linkedToMaster) {
            return '<span class="text-muted">Dari komponen gaji</span>';
        }
        return '<span class="text-muted">—</span>';
    }

    function categoriesForKind(kind) {
        return kind === "deduction" ? DEDUCTION_CATEGORIES : ADDITION_CATEGORIES;
    }

    function fillCategorySelect(selectEl, kind, selected) {
        if (!selectEl) {
            return;
        }
        var opts = categoriesForKind(kind);
        selectEl.innerHTML = opts
            .map(function (c) {
                return '<option value="' + escapeHtml(c) + '">' + escapeHtml(categoryLabel(c)) + "</option>";
            })
            .join("");
        if (selected && opts.indexOf(selected) >= 0) {
            selectEl.value = selected;
        } else {
            selectEl.value = opts[0] || "";
        }
    }

    function linkedMasterIdsSet() {
        var set = {};
        (linkedSalaryComponentIdsFromApi || []).forEach(function (id) {
            set[String(id)] = true;
        });
        return set;
    }

    function buildMasterOptions(excludeItemId) {
        var taken = linkedMasterIdsSet();
        var pk = pageKindFilter();
        return (salaryComponentsCache || []).filter(function (c) {
            if (!c || !c.id) {
                return false;
            }
            if (pk && c.kind !== pk) {
                return false;
            }
            if (excludeItemId && payrollItemsCache.some(function (p) { return p.id === excludeItemId && p.salaryComponentId === c.id; })) {
                return true;
            }
            return !taken[String(c.id)];
        });
    }

    function refreshMasterSelects(excludeItemId) {
        var addSel = document.querySelector("[data-payroll-item-add-link]");
        var editSel = document.querySelector("[data-payroll-item-edit-link]");
        var options = buildMasterOptions(excludeItemId);
        if (addSel) {
            var v = addSel.value;
            addSel.innerHTML =
                '<option value="">— Tanpa taut — isi manual di bawah</option>' +
                options
                    .map(function (c) {
                        return '<option value="' + escapeHtml(String(c.id)) + '">' + escapeHtml(c.name + " (" + c.code + ")") + "</option>";
                    })
                    .join("");
            if (v && addSel.querySelector('option[value="' + v + '"]')) {
                addSel.value = v;
            }
        }
        if (editSel) {
            var ev = editSel.value;
            editSel.innerHTML =
                '<option value="">— Biarkan taut komponen —</option>' +
                options
                    .map(function (c) {
                        return '<option value="' + escapeHtml(String(c.id)) + '">' + escapeHtml(c.name + " (" + c.code + ")") + "</option>";
                    })
                    .join("");
            if (ev && editSel.querySelector('option[value="' + ev + '"]')) {
                editSel.value = ev;
            }
        }
    }

    function formatApiError(data, status) {
        if (window.ApiErrorHelper && typeof window.ApiErrorHelper.format === "function") {
            return window.ApiErrorHelper.format(data, status);
        }
        return (data && data.error && data.error.message) || "Gagal memuat data.";
    }

    function toast(msg, danger) {
        if (window.ArcavUi && window.ArcavUi.showToast) {
            window.ArcavUi.showToast(msg, danger ? "danger" : "success");
        }
    }

    function renderPayrollItemRows(rows) {
        var tbody = document.querySelector("[data-payroll-items-catalog-body]");
        if (!tbody) {
            return;
        }
        if (!rows || !rows.length) {
            tbody.innerHTML =
                '<tr><td colspan="5" class="text-center text-muted py-4">Belum ada payroll item.</td></tr>';
            return;
        }
        tbody.innerHTML = rows
            .map(function (r) {
                var activeBadge = r.isActive
                    ? '<span class="badge bg-success badge-xs ms-1">Aktif</span>'
                    : '<span class="badge bg-secondary badge-xs ms-1">Nonaktif</span>';
                return (
                    "<tr>" +
                    '<td><h6 class="fs-14 fw-medium text-gray-9 mb-0">' +
                    escapeHtml(r.name) +
                    '</h6><span class="text-muted small">' +
                    escapeHtml(r.code || "—") +
                    activeBadge +
                    "</span></td>" +
                    "<td>" +
                    categoryLabel(r.category) +
                    "</td>" +
                    "<td>" +
                    formatPayrollItemDefault(r) +
                    "</td>" +
                    "<td class=\"small text-muted\">" +
                    escapeHtml((r.notes || "").slice(0, 120)) +
                    (r.notes && r.notes.length > 120 ? "…" : "") +
                    "</td>" +
                    '<td class="text-end"><div class="action-icon d-inline-flex">' +
                    '<a href="#" class="me-2" data-payroll-item-edit data-id="' +
                    escapeHtml(String(r.id)) +
                    '"><i class="ti ti-edit"></i></a>' +
                    '<a href="#" class="text-danger" data-payroll-item-delete data-id="' +
                    escapeHtml(String(r.id)) +
                    '" data-name="' +
                    escapeHtml(r.name) +
                    '"><i class="ti ti-trash"></i></a>' +
                    "</div></td>" +
                    "</tr>"
                );
            })
            .join("");
    }

    function loadSalaryComponents() {
        return apiRequest("get", "/v1/hcm/salary-components?isActive=1").then(function (resp) {
            if (!resp || resp.success !== true) {
                throw { status: 0, data: resp };
            }
            salaryComponentsCache = resp.data || [];
        });
    }

    function payrollItemsListUrl() {
        var pk = pageKindFilter();
        if (pk) {
            return "/v1/hcm/payroll-items?kind=" + encodeURIComponent(pk);
        }
        return "/v1/hcm/payroll-items";
    }

    function loadPayrollItems() {
        return apiRequest("get", payrollItemsListUrl()).then(function (resp) {
            if (!resp || resp.success !== true) {
                throw { status: 0, data: resp };
            }
            var d = resp.data || {};
            payrollItemsCache = d.payrollItems || [];
            linkedSalaryComponentIdsFromApi = (resp.meta && resp.meta.linkedSalaryComponentIds) || [];
            renderPayrollItemRows(payrollItemsCache);
            refreshMasterSelects(null);
        });
    }

    function resetAddForm() {
        var form = document.querySelector('[data-payroll-item-form="add"]');
        if (!form) {
            return;
        }
        var link = form.querySelector("[data-payroll-item-add-link]");
        if (link) {
            link.value = "";
        }
        form.querySelector('[data-payroll-item-field="name"]').value = "";
        form.querySelector('[data-payroll-item-field="code"]').value = "";
        var pk = pageKindFilter();
        var kind = pk || "addition";
        form.querySelector('[data-payroll-item-field="kind"]').value = kind;
        var defaultCat = kind === "deduction" ? "other_deduction" : "other_addition";
        fillCategorySelect(form.querySelector('[data-payroll-item-field="category"]'), kind, defaultCat);
        form.querySelector('[data-payroll-item-field="notes"]').value = "";
        form.querySelector('[data-payroll-item-field="sortOrder"]').value = "0";
        form.querySelector('[data-payroll-item-field="isActive"]').checked = true;
        toggleAddCustomVisibility();
        applyPageKindToModals();
    }

    function toggleAddCustomVisibility() {
        var form = document.querySelector('[data-payroll-item-form="add"]');
        if (!form) {
            return;
        }
        var link = form.querySelector("[data-payroll-item-add-link]");
        var custom = form.querySelector("[data-payroll-item-add-custom]");
        if (!custom) {
            return;
        }
        if (link && link.value) {
            custom.classList.add("d-none");
        } else {
            custom.classList.remove("d-none");
        }
    }

    function bindAddForm() {
        var form = document.querySelector('[data-payroll-item-form="add"]');
        if (!form || form.getAttribute("data-bound")) {
            return;
        }
        form.setAttribute("data-bound", "1");
        var link = form.querySelector("[data-payroll-item-add-link]");
        var kindSel = form.querySelector('[data-payroll-item-field="kind"]');
        if (link) {
            link.addEventListener("change", toggleAddCustomVisibility);
        }
        if (kindSel) {
            kindSel.addEventListener("change", function () {
                var k = pageKindFilter() || kindSel.value;
                fillCategorySelect(form.querySelector('[data-payroll-item-field="category"]'), k, null);
            });
        }
        form.addEventListener("submit", function (e) {
            e.preventDefault();
            var payload;
            if (link && link.value) {
                payload = {
                    salaryComponentId: parseInt(link.value, 10),
                    notes: form.querySelector('[data-payroll-item-field="notes"]').value.trim() || null,
                    sortOrder: parseInt(form.querySelector('[data-payroll-item-field="sortOrder"]').value, 10) || 0,
                    isActive: form.querySelector('[data-payroll-item-field="isActive"]').checked,
                };
            } else {
                var pkAdd = pageKindFilter();
                var kindVal = pkAdd || form.querySelector('[data-payroll-item-field="kind"]').value;
                payload = {
                    name: form.querySelector('[data-payroll-item-field="name"]').value.trim(),
                    code: form.querySelector('[data-payroll-item-field="code"]').value.trim() || null,
                    kind: kindVal,
                    category: form.querySelector('[data-payroll-item-field="category"]').value,
                    notes: form.querySelector('[data-payroll-item-field="notes"]').value.trim() || null,
                    sortOrder: parseInt(form.querySelector('[data-payroll-item-field="sortOrder"]').value, 10) || 0,
                    isActive: form.querySelector('[data-payroll-item-field="isActive"]').checked,
                };
            }
            apiRequest("post", "/v1/hcm/payroll-items", payload)
                .then(function (resp) {
                    if (!resp || resp.success !== true) {
                        throw { status: 0, data: resp };
                    }
                    var modalEl = document.getElementById("arcav_payroll_item_add");
                    if (modalEl && window.bootstrap && window.bootstrap.Modal) {
                        window.bootstrap.Modal.getOrCreateInstance(modalEl).hide();
                    }
                    toast("Payroll item tersimpan.", false);
                    return loadPayrollItems();
                })
                .catch(function (err) {
                    if (err == null) {
                        return;
                    }
                    toast(formatApiError(err.data, err.status), true);
                });
        });
    }

    function setEditLinkedMode(linked) {
        editUnlinkMode = false;
        var wrapLinked = document.querySelector("[data-payroll-item-edit-linked]");
        var wrapCustom = document.querySelector("[data-payroll-item-edit-custom]");
        var linkWrap = document.querySelector("[data-payroll-item-edit-link-wrap]");
        if (!wrapLinked || !wrapCustom) {
            return;
        }
        if (linked) {
            wrapLinked.classList.remove("d-none");
            wrapCustom.classList.add("d-none");
            if (linkWrap) {
                linkWrap.classList.add("d-none");
            }
        } else {
            wrapLinked.classList.add("d-none");
            wrapCustom.classList.remove("d-none");
            if (linkWrap) {
                linkWrap.classList.remove("d-none");
            }
        }
    }

    function openEditModal(row) {
        editUnlinkMode = false;
        var modalEl = document.getElementById("arcav_payroll_item_edit");
        var form = document.querySelector('[data-payroll-item-form="edit"]');
        if (!modalEl || !form) {
            return;
        }
        form.querySelector('[data-payroll-item-field="id"]').value = String(row.id);
        form.querySelector('[data-payroll-item-readonly="name"]').value = row.name || "";
        form.querySelector('[data-payroll-item-readonly="code"]').value = row.code || "";
        form.querySelector('[data-payroll-item-readonly="kindcat"]').value =
            (row.kind === "deduction" ? "Potongan" : "Pendapatan") + " · " + categoryLabel(row.category);
        form.querySelector('[data-payroll-item-field="name"]').value = row.name || "";
        form.querySelector('[data-payroll-item-field="code"]').value = row.code || "";
        var kindSel = form.querySelector('[data-payroll-item-field="kind"]');
        kindSel.value = row.kind || "addition";
        fillCategorySelect(form.querySelector('[data-payroll-item-field="category"]'), kindSel.value, row.category);
        form.querySelector('[data-payroll-item-field="notes"]').value = row.notes || "";
        form.querySelector('[data-payroll-item-field="sortOrder"]').value = String(row.sortOrder != null ? row.sortOrder : 0);
        form.querySelector('[data-payroll-item-field="isActive"]').checked = !!row.isActive;
        var editLink = form.querySelector("[data-payroll-item-edit-link]");
        if (editLink) {
            editLink.value = "";
        }
        refreshMasterSelects(row.id);
        setEditLinkedMode(!!row.linkedToMaster);
        applyPageKindToModals();
        var et = document.querySelector("#arcav_payroll_item_edit .modal-title");
        if (et) {
            et.textContent = pageKindFilter() === "deduction" ? "Ubah item potongan" : "Ubah item penghasilan";
        }
        if (window.bootstrap && window.bootstrap.Modal) {
            window.bootstrap.Modal.getOrCreateInstance(modalEl).show();
        }
    }

    function bindEditForm() {
        var form = document.querySelector('[data-payroll-item-form="edit"]');
        if (!form || form.getAttribute("data-bound")) {
            return;
        }
        form.setAttribute("data-bound", "1");
        var kindSel = form.querySelector('[data-payroll-item-field="kind"]');
        if (kindSel) {
            kindSel.addEventListener("change", function () {
                var k = pageKindFilter() || kindSel.value;
                fillCategorySelect(form.querySelector('[data-payroll-item-field="category"]'), k, null);
            });
        }
        var unlinkBtn = document.querySelector("[data-payroll-item-unlink-start]");
        if (unlinkBtn && !unlinkBtn.getAttribute("data-bound")) {
            unlinkBtn.setAttribute("data-bound", "1");
            unlinkBtn.addEventListener("click", function () {
                editUnlinkMode = true;
                setEditLinkedMode(false);
            });
        }
        form.addEventListener("submit", function (e) {
            e.preventDefault();
            var id = parseInt(form.querySelector('[data-payroll-item-field="id"]').value, 10);
            var linkedSection = document.querySelector("[data-payroll-item-edit-linked]");
            var isLinkedUi = linkedSection && !linkedSection.classList.contains("d-none") && !editUnlinkMode;
            var payload;
            if (isLinkedUi) {
                payload = {
                    notes: form.querySelector('[data-payroll-item-field="notes"]').value.trim() || null,
                    sortOrder: parseInt(form.querySelector('[data-payroll-item-field="sortOrder"]').value, 10) || 0,
                    isActive: form.querySelector('[data-payroll-item-field="isActive"]').checked,
                };
            } else if (editUnlinkMode) {
                var pkUn = pageKindFilter();
                payload = {
                    salaryComponentId: null,
                    name: form.querySelector('[data-payroll-item-field="name"]').value.trim(),
                    code: form.querySelector('[data-payroll-item-field="code"]').value.trim() || null,
                    kind: pkUn || form.querySelector('[data-payroll-item-field="kind"]').value,
                    category: form.querySelector('[data-payroll-item-field="category"]').value,
                    notes: form.querySelector('[data-payroll-item-field="notes"]').value.trim() || null,
                    sortOrder: parseInt(form.querySelector('[data-payroll-item-field="sortOrder"]').value, 10) || 0,
                    isActive: form.querySelector('[data-payroll-item-field="isActive"]').checked,
                };
            } else {
                var pkEd = pageKindFilter();
                payload = {
                    name: form.querySelector('[data-payroll-item-field="name"]').value.trim(),
                    code: form.querySelector('[data-payroll-item-field="code"]').value.trim() || null,
                    kind: pkEd || form.querySelector('[data-payroll-item-field="kind"]').value,
                    category: form.querySelector('[data-payroll-item-field="category"]').value,
                    notes: form.querySelector('[data-payroll-item-field="notes"]').value.trim() || null,
                    sortOrder: parseInt(form.querySelector('[data-payroll-item-field="sortOrder"]').value, 10) || 0,
                    isActive: form.querySelector('[data-payroll-item-field="isActive"]').checked,
                };
                var elink = form.querySelector("[data-payroll-item-edit-link]");
                if (elink && elink.value) {
                    payload.salaryComponentId = parseInt(elink.value, 10);
                }
            }
            apiRequest("put", "/v1/hcm/payroll-items/" + id, payload)
                .then(function (resp) {
                    if (!resp || resp.success !== true) {
                        throw { status: 0, data: resp };
                    }
                    var modalEl = document.getElementById("arcav_payroll_item_edit");
                    if (modalEl && window.bootstrap && window.bootstrap.Modal) {
                        window.bootstrap.Modal.getOrCreateInstance(modalEl).hide();
                    }
                    toast("Perubahan disimpan.", false);
                    editUnlinkMode = false;
                    return loadPayrollItems();
                })
                .catch(function (err) {
                    if (err == null) {
                        return;
                    }
                    toast(formatApiError(err.data, err.status), true);
                });
        });
    }

    function bindTableActions() {
        document.body.addEventListener("click", function (e) {
            var exportBtn = e.target.closest("[data-payroll-items-export]");
            if (exportBtn) {
                e.preventDefault();
                var format = exportBtn.getAttribute("data-payroll-items-export") || "xlsx";
                var pk = pageKindFilter();
                var url = "/v1/hcm/payroll-items/export?format=" + encodeURIComponent(format);
                if (pk) {
                    url += "&kind=" + encodeURIComponent(pk);
                }
                window.location.href = url;
                return;
            }

            var editA = e.target.closest("[data-payroll-item-edit]");
            if (editA) {
                e.preventDefault();
                var eid = parseInt(editA.getAttribute("data-id"), 10);
                var row = payrollItemsCache.filter(function (p) {
                    return p.id === eid;
                })[0];
                if (row) {
                    openEditModal(row);
                }
                return;
            }
            var delA = e.target.closest("[data-payroll-item-delete]");
            if (delA) {
                e.preventDefault();
                var pid = delA.getAttribute("data-id");
                var pname = delA.getAttribute("data-name") || "item ini";
                var runDelete = function () {
                    apiRequest("delete", "/v1/hcm/payroll-items/" + pid, null)
                        .then(function (resp) {
                            if (!resp || resp.success !== true) {
                                throw { status: 0, data: resp };
                            }
                            toast("Payroll item dihapus.", false);
                            return loadPayrollItems();
                        })
                        .catch(function (err) {
                            if (err == null) {
                                return;
                            }
                            toast(formatApiError(err.data, err.status), true);
                        });
                };
                if (window.ArcavUi && typeof window.ArcavUi.confirmDelete === "function") {
                    window.ArcavUi.confirmDelete("Hapus payroll item \"" + pname + "\"?", "Hapus").then(function (ok) {
                        if (ok) {
                            runDelete();
                        }
                    });
                } else {
                    runDelete();
                }
            }
        });
    }

    function loadPayrollItemsPage() {
        var path = (window.location.pathname || "").replace(/\/+$/, "") || "/";
        if (path !== "/payroll" && path !== "/payroll-deduction") {
            return;
        }
        applyPageKindToModals();
        bindAddForm();
        bindEditForm();
        bindTableActions();
        var addModal = document.getElementById("arcav_payroll_item_add");
        if (addModal && !addModal.getAttribute("data-bound")) {
            addModal.setAttribute("data-bound", "1");
            addModal.addEventListener("show.bs.modal", function () {
                resetAddForm();
                refreshMasterSelects(null);
                applyPageKindToModals();
                var t = document.querySelector("#arcav_payroll_item_add .modal-title");
                if (t) {
                    t.textContent =
                        pageKindFilter() === "deduction" ? "Tambah item potongan" : "Tambah item penghasilan";
                }
            });
        }
        var editModal = document.getElementById("arcav_payroll_item_edit");
        if (editModal && !editModal.getAttribute("data-bound")) {
            editModal.setAttribute("data-bound", "1");
            editModal.addEventListener("hidden.bs.modal", function () {
                editUnlinkMode = false;
            });
        }
        loadSalaryComponents()
            .then(function () {
                return loadPayrollItems();
            })
            .catch(function (err) {
                if (err == null) {
                    return;
                }
                var msg = formatApiError(err.data, err.status);
                var tb = document.querySelector("[data-payroll-items-catalog-body]");
                if (tb) {
                    tb.innerHTML =
                        '<tr><td colspan="5" class="text-center text-danger py-4">' + escapeHtml(msg) + "</td></tr>";
                }
                toast(msg, true);
            });
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", loadPayrollItemsPage);
    } else {
        loadPayrollItemsPage();
    }
})(window, document);
