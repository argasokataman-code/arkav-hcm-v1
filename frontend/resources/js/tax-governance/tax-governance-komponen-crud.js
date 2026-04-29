var komponentCategoryRows = [];

var KOMPONEN_CATEGORY_LABELS = {
    basic_wage: "Upah pokok", fixed_allowance: "Tunjangan tetap", common_allowance: "Tunjangan umum",
    family_allowance: "Tunjangan keluarga", irregular_allowance: "Tunjangan tidak tetap / insentif",
    overtime: "Upah lembur", thr: "THR", bonus: "Bonus (luar THR)",
    natura_taxable: "Natura kena pajak", natura_non_taxable: "Natura tidak kena pajak",
    special_allowance: "Tunjangan khusus / insidentil", reimbursement: "Reimbursement",
    termination_benefit: "Kompensasi terminasi", employer_cost_display: "Beban perusahaan (info slip)",
    other_addition: "Pendapatan lain", bpjs_health_employee: "BPJS Kesehatan (pekerja)",
    bpjs_jht_employee: "JHT (pekerja)", bpjs_jp_employee: "JP / pensiun (pekerja)",
    pension_employee: "Iuran pensiun pekerja", pph21_ter: "PPh 21 — TER bulanan",
    pph21_december_recon: "PPh 21 — rekonsiliasi", other_statutory: "Potongan wajib lain",
    internal_advance: "Kasbon / uang muka", internal_loan: "Pinjaman internal",
    internal_cooperative: "Koperasi / internal", internal_other: "Potongan internal lain",
    other_deduction: "Potongan lain",
};

var KOMPONEN_ADDITION_CATS = [
    "basic_wage", "fixed_allowance", "common_allowance", "family_allowance",
    "irregular_allowance", "overtime", "thr", "bonus", "natura_taxable", "natura_non_taxable",
    "special_allowance", "reimbursement", "termination_benefit", "employer_cost_display", "other_addition",
];

var KOMPONEN_DEDUCTION_CATS = [
    "bpjs_health_employee", "bpjs_jht_employee", "bpjs_jp_employee", "pension_employee",
    "pph21_ter", "pph21_december_recon", "other_statutory", "internal_advance",
    "internal_loan", "internal_cooperative", "internal_other", "other_deduction",
];

function notifyKomponen(message, isError) {
    if (window.ApiClient && typeof window.ApiClient.toast === "function") {
        window.ApiClient.toast(message, isError);
        return;
    }
    if (window.notify && typeof window.notify === "function") {
        window.notify(message, isError);
    }
}

function fillKomponentCategorySelect(escapeHtml, selectEl, kind, current) {
    if (!selectEl) { return; }
    var dynamic = komponentCategoryRows
        .filter(function (c) { return c && c.kind === kind && c.isActive !== false; })
        .sort(function (a, b) { return Number(a.sortOrder || 0) - Number(b.sortOrder || 0); })
        .map(function (c) { return String(c.code || ""); })
        .filter(Boolean);
    var fallback = kind === "deduction" ? KOMPONEN_DEDUCTION_CATS : KOMPONEN_ADDITION_CATS;
    var items = dynamic.length ? dynamic : fallback;
    selectEl.innerHTML = items.map(function (code) {
        var match = null;
        for (var i = 0; i < komponentCategoryRows.length; i++) {
            if (komponentCategoryRows[i].code === code) { match = komponentCategoryRows[i]; break; }
        }
        var label = (match && match.name) ? match.name : (KOMPONEN_CATEGORY_LABELS[code] || code);
        return '<option value="' + escapeHtml(code) + '">' + escapeHtml(label) + '</option>';
    }).join("");
    if (current) { selectEl.value = current; }
}

function getKomponentBool(form, field) {
    var el = form.querySelector('[data-hcm-field="' + field + '"]');
    if (!el) { return false; }
    if (el.type === "checkbox") { return el.checked; }
    if (el.type === "hidden") { return el.value === "1" || el.value === "true"; }
    return false;
}

function setKomponentBool(form, field, v) {
    var el = form.querySelector('[data-hcm-field="' + field + '"]');
    if (!el) { return; }
    if (el.type === "checkbox") { el.checked = !!v; }
    else if (el.type === "hidden") { el.value = v ? "1" : "0"; }
}

function buildKomponentPayload(form) {
    var pctEl = form.querySelector('[data-hcm-field="defaultPercent"]');
    var basisEl = form.querySelector('[data-hcm-field="percentBasis"]');
    var pctRaw = pctEl ? String(pctEl.value || "").trim().replace(",", ".") : "";
    var basis = basisEl ? String(basisEl.value || "").trim() : "";
    var defaultPercent = null;
    var percentBasis = null;
    if (pctRaw) {
        var n = parseFloat(pctRaw);
        if (isNaN(n) || n < 0 || n > 100) { return { invalid: true, message: "Persen harus antara 0 dan 100." }; }
        if (!basis) { return { invalid: true, message: "Pilih dasar perhitungan jika mengisi persen." }; }
        defaultPercent = n;
        percentBasis = basis;
    }
    function fieldVal(name) {
        var el = form.querySelector('[data-hcm-field="' + name + '"]');
        return el ? el.value : "";
    }
    return {
        name: fieldVal("name").trim(),
        code: fieldVal("code").trim(),
        kind: fieldVal("kind") || "addition",
        category: fieldVal("category"),
        description: fieldVal("description").trim() || null,
        legalBasis: fieldVal("legalBasis").trim() || null,
        legalNotes: fieldVal("legalNotes").trim() || null,
        defaultPercent: defaultPercent,
        percentBasis: percentBasis,
        includeBpjsHealthWageBase: getKomponentBool(form, "includeBpjsHealthWageBase"),
        includeBpjsTkWageBase: getKomponentBool(form, "includeBpjsTkWageBase"),
        includeThrCalculationBase: getKomponentBool(form, "includeThrCalculationBase"),
        includePph21TerGross: getKomponentBool(form, "includePph21TerGross"),
        includePph21AnnualReconciliation: getKomponentBool(form, "includePph21AnnualReconciliation"),
        subjectOvertimeRegulation: getKomponentBool(form, "subjectOvertimeRegulation"),
        affectsNetPay: getKomponentBool(form, "affectsNetPay"),
        employerCostLine: getKomponentBool(form, "employerCostLine"),
        isActive: getKomponentBool(form, "isActive"),
        sortOrder: parseInt(fieldVal("sortOrder") || "0", 10) || 0,
    };
}

export function bindKomponentCrudModule(deps, root) {
    if (root._komponentCrudBound) { return; }
    root._komponentCrudBound = true;

    var escapeHtml = deps.escapeHtml;
    var apiGet = deps.apiGet;
    var apiPost = deps.apiPost;
    var apiPut = deps.apiPut;
    var apiDelete = deps.apiDelete;
    var parseApiError = deps.parseApiError;
    var loadKomponenPajak = deps.loadKomponenPajak;
    var getKomponentData = deps.getKomponentData;

    apiGet("/hcm/salary-component-categories", { per_page: 200 }).then(function (resp) {
        if (resp && resp.success && Array.isArray(resp.data)) {
            komponentCategoryRows = resp.data;
        }
        var addForm = document.querySelector('[data-hcm-salary-component-form="add"]');
        var editForm = document.querySelector('[data-hcm-salary-component-form="edit"]');
        if (addForm) {
            var addKind = addForm.querySelector('[data-hcm-field="kind"]');
            if (addKind) {
                fillKomponentCategorySelect(escapeHtml, addForm.querySelector('[data-hcm-field="category"]'), addKind.value, null);
                if (!addKind._komponentKindBound) {
                    addKind._komponentKindBound = true;
                    addKind.addEventListener("change", function () {
                        fillKomponentCategorySelect(escapeHtml, addForm.querySelector('[data-hcm-field="category"]'), addKind.value, null);
                    });
                }
            }
        }
        if (editForm) {
            var editKind = editForm.querySelector('[data-hcm-field="kind"]');
            if (editKind && !editKind._komponentKindBound) {
                editKind._komponentKindBound = true;
                editKind.addEventListener("change", function () {
                    var cur = editForm.querySelector('[data-hcm-field="category"]') ? editForm.querySelector('[data-hcm-field="category"]').value : null;
                    fillKomponentCategorySelect(escapeHtml, editForm.querySelector('[data-hcm-field="category"]'), editKind.value, cur);
                });
            }
        }
    }).catch(function () {});

    var addForm = document.querySelector('[data-hcm-salary-component-form="add"]');
    if (addForm && !addForm._komponentCrudBound) {
        addForm._komponentCrudBound = true;
        addForm.addEventListener("submit", function (e) {
            e.preventDefault();
            var payload = buildKomponentPayload(addForm);
            if (payload.invalid) { notifyKomponen(payload.message, true); return; }
            if (!payload.name || !payload.kind || !payload.category) {
                notifyKomponen("Lengkapi nama, jenis, dan kategori.", true);
                return;
            }
            if (!payload.code) { delete payload.code; }
            apiPost("/hcm/salary-components", payload).then(function (resp) {
                if (!resp || !resp.success) {
                    notifyKomponen((resp && resp.message) || "Gagal menyimpan.", true);
                    return;
                }
                notifyKomponen("Komponen tersimpan.", false);
                var el = document.getElementById("arcav_add_salary_component");
                var mi = el && window.bootstrap && window.bootstrap.Modal.getInstance(el);
                if (mi) { mi.hide(); }
                addForm.reset();
                loadKomponenPajak(root);
            }).catch(function (err) {
                notifyKomponen(parseApiError(err, "Gagal menyimpan komponen.").message, true);
            });
        });
    }

    var editForm = document.querySelector('[data-hcm-salary-component-form="edit"]');
    if (editForm && !editForm._komponentCrudBound) {
        editForm._komponentCrudBound = true;
        editForm.addEventListener("submit", function (e) {
            e.preventDefault();
            var idField = editForm.querySelector('[data-hcm-field="id"]');
            var id = idField ? String(idField.value || "").trim() : "";
            if (!id) { return; }
            var payload = buildKomponentPayload(editForm);
            if (payload.invalid) { notifyKomponen(payload.message, true); return; }
            apiPut("/hcm/salary-components/" + encodeURIComponent(id), payload).then(function (resp) {
                if (!resp || !resp.success) {
                    notifyKomponen((resp && resp.message) || "Gagal memperbarui.", true);
                    return;
                }
                notifyKomponen("Komponen diperbarui.", false);
                var el = document.getElementById("arcav_edit_salary_component");
                var mi = el && window.bootstrap && window.bootstrap.Modal.getInstance(el);
                if (mi) { mi.hide(); }
                loadKomponenPajak(root);
            }).catch(function (err) {
                notifyKomponen(parseApiError(err, "Gagal memperbarui komponen.").message, true);
            });
        });
    }

    document.addEventListener("click", function (e) {
        var editBtn = e.target.closest("[data-komponen-edit-id]");
        if (editBtn) {
            var id = parseInt(editBtn.getAttribute("data-komponen-edit-id"), 10);
            var rows = getKomponentData();
            var raw = null;
            for (var i = 0; i < rows.length; i++) {
                if (Number(rows[i].id) === id) { raw = rows[i]; break; }
            }
            if (!raw) { return; }
            var ef = document.querySelector('[data-hcm-salary-component-form="edit"]');
            if (!ef) { return; }
            var idF = ef.querySelector('[data-hcm-field="id"]');
            if (idF) { idF.value = String(raw.id); }
            function setF(name, val) {
                var el = ef.querySelector('[data-hcm-field="' + name + '"]');
                if (el && el.type !== "checkbox" && el.type !== "hidden") { el.value = val != null ? String(val) : ""; }
            }
            setF("name", raw.name || "");
            setF("code", raw.code || "");
            setF("kind", raw.kind || "addition");
            setF("description", raw.description || "");
            setF("legalBasis", raw.legalBasis || "");
            setF("legalNotes", raw.legalNotes || "");
            setF("sortOrder", raw.sortOrder != null ? raw.sortOrder : 0);
            var dpe = ef.querySelector('[data-hcm-field="defaultPercent"]');
            if (dpe) { dpe.value = raw.defaultPercent != null && raw.defaultPercent !== "" ? String(parseFloat(parseFloat(raw.defaultPercent).toFixed(4))) : ""; }
            var pbe = ef.querySelector('[data-hcm-field="percentBasis"]');
            if (pbe) { pbe.value = raw.percentBasis || ""; }
            var kindEl = ef.querySelector('[data-hcm-field="kind"]');
            fillKomponentCategorySelect(escapeHtml, ef.querySelector('[data-hcm-field="category"]'), kindEl ? kindEl.value : "addition", raw.category);
            ["includeBpjsHealthWageBase", "includeBpjsTkWageBase", "includeThrCalculationBase",
                "includePph21TerGross", "includePph21AnnualReconciliation", "subjectOvertimeRegulation",
                "affectsNetPay", "employerCostLine", "isActive"].forEach(function (f) {
                setKomponentBool(ef, f, raw[f]);
            });
            return;
        }

        var delBtn = e.target.closest("[data-komponen-delete-id]");
        if (delBtn) {
            e.preventDefault();
            var delId = delBtn.getAttribute("data-komponen-delete-id");
            var confirmFn = window.ArcavUi && typeof window.ArcavUi.confirmDelete === "function"
                ? window.ArcavUi.confirmDelete("Hapus komponen gaji ini? Tindakan tidak memengaruhi slip lama.", "Hapus komponen")
                : Promise.resolve(window.confirm("Hapus komponen gaji ini?"));
            confirmFn.then(function (ok) {
                if (!ok) { return; }
                apiDelete("/hcm/salary-components/" + encodeURIComponent(delId)).then(function (resp) {
                    if (!resp || !resp.success) {
                        notifyKomponen((resp && resp.message) || "Gagal menghapus.", true);
                        return;
                    }
                    notifyKomponen("Komponen dihapus.", false);
                    loadKomponenPajak(root);
                }).catch(function (err) {
                    notifyKomponen(parseApiError(err, "Gagal menghapus komponen.").message, true);
                });
            });
        }
    });
}