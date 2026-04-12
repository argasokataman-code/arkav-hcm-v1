(function (window, document) {
    "use strict";

    var CATEGORY_LABELS = {
        basic_wage: "Upah pokok",
        fixed_allowance: "Tunjangan tetap",
        irregular_allowance: "Tunjangan tidak tetap / insentif",
        overtime: "Upah lembur",
        thr: "THR",
        bonus: "Bonus (luar THR)",
        natura_taxable: "Natura kena pajak",
        natura_non_taxable: "Natura tidak kena pajak",
        special_allowance: "Tunjangan khusus / insidentil",
        reimbursement: "Reimbursement",
        termination_benefit: "Kompensasi terminasi",
        employer_cost_display: "Beban perusahaan (info slip)",
        other_addition: "Pendapatan lain",
        bpjs_health_employee: "BPJS Kesehatan (pekerja)",
        bpjs_jht_employee: "JHT (pekerja)",
        bpjs_jp_employee: "JP / pensiun (pekerja)",
        pension_employee: "Iuran pensiun pekerja",
        pph21_ter: "PPh 21 — TER bulanan",
        pph21_december_recon: "PPh 21 — rekonsiliasi",
        other_statutory: "Potongan wajib lain",
        internal_advance: "Kasbon / uang muka",
        internal_loan: "Pinjaman internal",
        internal_cooperative: "Koperasi / internal",
        internal_other: "Potongan internal lain",
        other_deduction: "Potongan lain",
    };

    var PERCENT_BASIS_LABELS = {
        basic_wage: "Upah pokok",
        wage_bpjs_health: "Dasar BPJS Kes",
        wage_bpjs_tk: "Dasar BPJS TK",
        gross_monthly_ter: "Bruto TER",
        thr_calculation_base: "Basis THR",
    };

    var ADDITION_CATS = [
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

    var DEDUCTION_CATS = [
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

    var rowCache = {};

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

    function esc(v) {
        return String(v || "")
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#39;");
    }

    function notify(message, isError) {
        if (window.ApiClient && typeof window.ApiClient.toast === "function") {
            window.ApiClient.toast(message, isError);
            return;
        }
        var c = document.querySelector("[data-hcm-toast-container]") || document.body.appendChild(Object.assign(document.createElement("div"), { style: "position:fixed;top:16px;right:16px;z-index:3000" }));
        c.setAttribute("data-hcm-toast-container", "1");
        var t = document.createElement("div");
        t.className = "alert " + (isError ? "alert-danger" : "alert-success") + " shadow-sm mb-2";
        t.textContent = message;
        c.appendChild(t);
        window.setTimeout(function () {
            t.remove();
        }, 2600);
    }

    function formatApiError(data, status) {
        if (window.ApiErrorHelper && typeof window.ApiErrorHelper.format === "function") {
            return window.ApiErrorHelper.format(data, status);
        }
        if (data && data.error && data.error.message) {
            return data.error.message;
        }
        return status ? "Error " + status : "Request failed";
    }

    function fillCategorySelect(selectEl, kind, current) {
        if (!selectEl) {
            return;
        }
        var list = kind === "deduction" ? DEDUCTION_CATS : ADDITION_CATS;
        selectEl.innerHTML = list
            .map(function (c) {
                return '<option value="' + esc(c) + '">' + esc(CATEGORY_LABELS[c] || c) + "</option>";
            })
            .join("");
        if (current && list.indexOf(current) >= 0) {
            selectEl.value = current;
        } else {
            selectEl.selectedIndex = 0;
        }
    }

    function setBool(form, field, v) {
        var el = form.querySelector('[data-hcm-field="' + field + '"]');
        if (el && el.type === "checkbox") {
            el.checked = !!v;
        }
    }

    function getBool(form, field) {
        var el = form.querySelector('[data-hcm-field="' + field + '"]');
        return el && el.type === "checkbox" ? el.checked : false;
    }

    function setLockedFields(form, locked) {
        var note = document.querySelector("[data-hcm-salary-component-locked-note]");
        if (note) {
            note.classList.toggle("d-none", !locked);
        }
        var names = [
            "code",
            "kind",
            "category",
            "legalBasis",
            "legalNotes",
            "includeBpjsHealthWageBase",
            "includeBpjsTkWageBase",
            "includeThrCalculationBase",
            "subjectOvertimeRegulation",
            "includePph21TerGross",
            "includePph21AnnualReconciliation",
            "affectsNetPay",
            "employerCostLine",
        ];
        names.forEach(function (n) {
            var el = form.querySelector('[data-hcm-field="' + n + '"]');
            if (el) {
                el.disabled = !!locked;
            }
        });
    }

    function truncate(s, n) {
        s = String(s || "");
        if (s.length <= n) {
            return s;
        }
        return s.slice(0, n - 1) + "…";
    }

    /** Tampilan/input: hilangkan trailing zero dari penyimpanan decimal(8,4), e.g. 1.0000 → "1", 12.5 → "12.5" */
    function formatDefaultPercentDisplay(v) {
        if (v == null || v === "") {
            return "";
        }
        var n = parseFloat(String(v).replace(",", "."));
        if (isNaN(n)) {
            return "";
        }
        var t = Math.round(n * 10000) / 10000;
        return String(parseFloat(t.toFixed(4)));
    }

    function readPercentPayload(form) {
        var pctEl = form.querySelector('[data-hcm-field="defaultPercent"]');
        var basisEl = form.querySelector('[data-hcm-field="percentBasis"]');
        var pctRaw = pctEl ? String(pctEl.value || "").trim().replace(",", ".") : "";
        var basis = basisEl ? String(basisEl.value || "").trim() : "";
        if (!pctRaw) {
            return { defaultPercent: null, percentBasis: null };
        }
        var n = parseFloat(pctRaw);
        if (isNaN(n) || n < 0 || n > 100) {
            return { invalid: true, message: "Persen harus antara 0 dan 100." };
        }
        if (!basis) {
            return { invalid: true, message: "Pilih dasar perhitungan jika mengisi persen." };
        }
        return { defaultPercent: n, percentBasis: basis };
    }

    function formatPercentCell(r) {
        if (r.defaultPercent == null || r.defaultPercent === "") {
            return "—";
        }
        var b = r.percentBasis ? PERCENT_BASIS_LABELS[r.percentBasis] || r.percentBasis : "";
        return esc(formatDefaultPercentDisplay(r.defaultPercent)) + "%" + (b ? " <span class=\"text-muted\">(" + esc(b) + ")</span>" : "");
    }

    function formatIntegrationCell(r) {
        var list = Array.isArray(r.integrations) ? r.integrations : [];
        if (!list.length) {
            return '<span class="text-muted small">Manual / belum terhubung</span>';
        }
        return list.map(function (item) {
            var badge = item.locked ? "warning" : "info";
            var prefix = item.locked ? "Managed" : "Used";
            return '<span class="badge badge-' + badge + ' badge-xs me-1 mb-1">' + esc(prefix + ': ' + (item.label || item.key || 'Integration')) + '</span>';
        }).join("");
    }

    function bind() {
        var body = document.querySelector("[data-hcm-salary-components-body]");
        var addForm = document.querySelector('[data-hcm-salary-component-form="add"]');
        var editForm = document.querySelector('[data-hcm-salary-component-form="edit"]');
        var addKind = addForm && addForm.querySelector('[data-hcm-field="kind"]');
        var editKind = editForm && editForm.querySelector('[data-hcm-field="kind"]');

        function render(rows) {
            rowCache = {};
            (rows || []).forEach(function (r) {
                rowCache[r.id] = r;
            });
            if (!body) {
                return;
            }
            body.innerHTML =
                (rows || [])
                    .map(function (r) {
                        var badge = r.isActive ? "success" : "danger";
                        var st = r.isActive ? "Active" : "Inactive";
                        var kindLabel = r.kind === "deduction" ? "Deduction" : "Addition";
                        if (r.employerCostLine) {
                            kindLabel = "Info (Employer cost)";
                        }
                        var lockBadge = r.isSystemLocked
                            ? ' <span class="badge badge-secondary badge-xs ms-1" title="System">System</span>'
                            : "";
                        var legal = truncate(r.legalBasis || "—", 56);
                        var canEdit = !r.integrationLocked;
                        var del =
                            r.isSystemLocked || r.integrationLocked
                                ? ""
                                : '<a href="#" class="ms-2" data-hcm-salary-component-delete="' +
                                  esc(String(r.id)) +
                                  '"><i class="ti ti-trash"></i></a>';
                        var edit = canEdit
                            ? '<a href="#" class="me-2" data-hcm-salary-component-edit="' +
                              esc(String(r.id)) +
                              '" data-bs-toggle="modal" data-bs-target="#arcav_edit_salary_component"><i class="ti ti-edit"></i></a>'
                            : '<span class="text-muted me-2" title="Kelola dari modul sumber"><i class="ti ti-lock"></i></span>';
                        return (
                            "<tr><td><div class=\"form-check form-check-md\"><input class=\"form-check-input\" type=\"checkbox\"></div></td><td><code>" +
                            esc(r.code) +
                            "</code></td><td><h6 class=\"fw-medium mb-0\">" +
                            esc(r.name) +
                            lockBadge +
                            "</h6></td><td>" +
                            esc(kindLabel) +
                            "</td><td><span class=\"text-muted small\">" +
                            esc(CATEGORY_LABELS[r.category] || r.category) +
                            "</span></td><td>" +
                            formatIntegrationCell(r) +
                            "</td><td class=\"small\">" +
                            formatPercentCell(r) +
                            "</td><td><span class=\"small\" title=\"" +
                            esc(r.legalBasis || "") +
                            "\">" +
                            esc(legal) +
                            "</span></td><td><span class=\"badge badge-" +
                            badge +
                            ' d-inline-flex align-items-center badge-xs"><i class="ti ti-point-filled me-1"></i>' +
                            esc(st) +
                            "</span></td><td><div class=\"action-icon d-inline-flex\">" +
                            edit +
                            del +
                            "</div></td></tr>"
                        );
                    })
                    .join("") || '<tr><td colspan="10" class="text-center py-4 text-muted">No salary components yet.</td></tr>';
        }

        function reload() {
            apiRequest("get", "/v1/hcm/salary-components", null)
                .then(function (p) {
                    if (!p) {
                        notify("Silakan masuk kembali.", true);
                        return;
                    }
                    if (p.success !== true) {
                        notify(formatApiError(p, 0) || "Gagal memuat data.", true);
                        return;
                    }
                    render(p.data || []);
                })
                .catch(function (e) {
                    notify(formatApiError(e.data, e.status), true);
                });
        }

        if (addKind && addForm) {
            addKind.addEventListener("change", function () {
                fillCategorySelect(addForm.querySelector('[data-hcm-field="category"]'), addKind.value, null);
            });
            fillCategorySelect(addForm.querySelector('[data-hcm-field="category"]'), addKind.value, null);
        }

        if (editKind && editForm) {
            editKind.addEventListener("change", function () {
                var cur = editForm.querySelector('[data-hcm-field="category"]').value;
                fillCategorySelect(editForm.querySelector('[data-hcm-field="category"]'), editKind.value, cur);
            });
        }

        if (addForm) {
            addForm.addEventListener("submit", function (e) {
                e.preventDefault();
                var name = addForm.querySelector('[data-hcm-field="name"]').value.trim();
                var code = addForm.querySelector('[data-hcm-field="code"]').value.trim();
                var kind = addForm.querySelector('[data-hcm-field="kind"]').value;
                var category = addForm.querySelector('[data-hcm-field="category"]').value;
                if (!name || !kind || !category) {
                    notify("Lengkapi nama, jenis, dan kategori.", true);
                    return;
                }
                var pctAdd = readPercentPayload(addForm);
                if (pctAdd.invalid) {
                    notify(pctAdd.message || "Periksa kolom persen.", true);
                    return;
                }
                var payload = {
                    name: name,
                    kind: kind,
                    category: category,
                    description: addForm.querySelector('[data-hcm-field="description"]').value.trim() || null,
                    legalBasis: addForm.querySelector('[data-hcm-field="legalBasis"]').value.trim() || null,
                    legalNotes: addForm.querySelector('[data-hcm-field="legalNotes"]').value.trim() || null,
                    defaultPercent: pctAdd.defaultPercent,
                    percentBasis: pctAdd.percentBasis,
                    includeBpjsHealthWageBase: getBool(addForm, "includeBpjsHealthWageBase"),
                    includeBpjsTkWageBase: getBool(addForm, "includeBpjsTkWageBase"),
                    includeThrCalculationBase: getBool(addForm, "includeThrCalculationBase"),
                    includePph21TerGross: getBool(addForm, "includePph21TerGross"),
                    includePph21AnnualReconciliation: getBool(addForm, "includePph21AnnualReconciliation"),
                    subjectOvertimeRegulation: getBool(addForm, "subjectOvertimeRegulation"),
                    affectsNetPay: getBool(addForm, "affectsNetPay"),
                    employerCostLine: getBool(addForm, "employerCostLine"),
                    isActive: getBool(addForm, "isActive"),
                    sortOrder: parseInt(addForm.querySelector('[data-hcm-field="sortOrder"]').value, 10) || 0,
                };
                if (code) {
                    payload.code = code;
                }
                apiRequest("post", "/v1/hcm/salary-components", payload)
                    .then(function (p) {
                        if (!p || p.success !== true) {
                            notify(formatApiError(p, 0) || "Gagal menyimpan.", true);
                            return;
                        }
                        notify("Komponen tersimpan.", false);
                        var el = document.getElementById("arcav_add_salary_component");
                        var mi = el && window.bootstrap && window.bootstrap.Modal.getInstance(el);
                        if (mi) {
                            mi.hide();
                        }
                        addForm.reset();
                        setBool(addForm, "isActive", true);
                        setBool(addForm, "includePph21TerGross", true);
                        setBool(addForm, "affectsNetPay", true);
                        if (addKind) {
                            fillCategorySelect(addForm.querySelector('[data-hcm-field="category"]'), addKind.value, null);
                        }
                        var dp = addForm.querySelector('[data-hcm-field="defaultPercent"]');
                        var pb = addForm.querySelector('[data-hcm-field="percentBasis"]');
                        if (dp) {
                            dp.value = "";
                        }
                        if (pb) {
                            pb.value = "";
                        }
                        reload();
                    })
                    .catch(function (err) {
                        notify(formatApiError(err.data, err.status), true);
                    });
            });
        }

        document.addEventListener("click", function (e) {
            var btn = e.target.closest("[data-hcm-salary-component-edit]");
            if (!btn || !editForm) {
                return;
            }
            var id = parseInt(btn.getAttribute("data-hcm-salary-component-edit"), 10);
            var r = rowCache[id];
            if (!r) {
                return;
            }
            setLockedFields(editForm, r.isSystemLocked);
            editForm.querySelector('[data-hcm-field="id"]').value = String(r.id);
            editForm.querySelector('[data-hcm-field="name"]').value = r.name || "";
            editForm.querySelector('[data-hcm-field="code"]').value = r.code || "";
            editForm.querySelector('[data-hcm-field="kind"]').value = r.kind || "addition";
            fillCategorySelect(editForm.querySelector('[data-hcm-field="category"]'), r.kind, r.category);
            editForm.querySelector('[data-hcm-field="description"]').value = r.description || "";
            editForm.querySelector('[data-hcm-field="legalBasis"]').value = r.legalBasis || "";
            editForm.querySelector('[data-hcm-field="legalNotes"]').value = r.legalNotes || "";
            setBool(editForm, "includeBpjsHealthWageBase", r.includeBpjsHealthWageBase);
            setBool(editForm, "includeBpjsTkWageBase", r.includeBpjsTkWageBase);
            setBool(editForm, "includeThrCalculationBase", r.includeThrCalculationBase);
            setBool(editForm, "includePph21TerGross", r.includePph21TerGross);
            setBool(editForm, "includePph21AnnualReconciliation", r.includePph21AnnualReconciliation);
            setBool(editForm, "subjectOvertimeRegulation", r.subjectOvertimeRegulation);
            setBool(editForm, "affectsNetPay", r.affectsNetPay);
            setBool(editForm, "employerCostLine", r.employerCostLine);
            setBool(editForm, "isActive", r.isActive);
            editForm.querySelector('[data-hcm-field="sortOrder"]').value = String(r.sortOrder != null ? r.sortOrder : 0);
            var dpe = editForm.querySelector('[data-hcm-field="defaultPercent"]');
            var pbe = editForm.querySelector('[data-hcm-field="percentBasis"]');
            if (dpe) {
                dpe.value =
                    r.defaultPercent != null && r.defaultPercent !== ""
                        ? formatDefaultPercentDisplay(r.defaultPercent)
                        : "";
            }
            if (pbe) {
                pbe.value = r.percentBasis || "";
            }
        });

        var editModal = document.getElementById("arcav_edit_salary_component");
        if (editModal && editForm) {
            editModal.addEventListener("hidden.bs.modal", function () {
                setLockedFields(editForm, false);
            });
        }

        if (editForm) {
            editForm.addEventListener("submit", function (e) {
                e.preventDefault();
                var id = editForm.querySelector('[data-hcm-field="id"]').value;
                if (!id) {
                    return;
                }
                var r = rowCache[parseInt(id, 10)];
                var pctEd = readPercentPayload(editForm);
                if (pctEd.invalid) {
                    notify(pctEd.message || "Periksa kolom persen.", true);
                    return;
                }
                var payload;
                if (r && r.isSystemLocked) {
                    payload = {
                        name: editForm.querySelector('[data-hcm-field="name"]').value.trim(),
                        description: editForm.querySelector('[data-hcm-field="description"]').value.trim() || null,
                        isActive: getBool(editForm, "isActive"),
                        sortOrder: parseInt(editForm.querySelector('[data-hcm-field="sortOrder"]').value, 10) || 0,
                        defaultPercent: pctEd.defaultPercent,
                        percentBasis: pctEd.percentBasis,
                    };
                } else {
                    payload = {
                        code: editForm.querySelector('[data-hcm-field="code"]').value.trim(),
                        name: editForm.querySelector('[data-hcm-field="name"]').value.trim(),
                        kind: editForm.querySelector('[data-hcm-field="kind"]').value,
                        category: editForm.querySelector('[data-hcm-field="category"]').value,
                        description: editForm.querySelector('[data-hcm-field="description"]').value.trim() || null,
                        legalBasis: editForm.querySelector('[data-hcm-field="legalBasis"]').value.trim() || null,
                        legalNotes: editForm.querySelector('[data-hcm-field="legalNotes"]').value.trim() || null,
                        includeBpjsHealthWageBase: getBool(editForm, "includeBpjsHealthWageBase"),
                        includeBpjsTkWageBase: getBool(editForm, "includeBpjsTkWageBase"),
                        includeThrCalculationBase: getBool(editForm, "includeThrCalculationBase"),
                        includePph21TerGross: getBool(editForm, "includePph21TerGross"),
                        includePph21AnnualReconciliation: getBool(editForm, "includePph21AnnualReconciliation"),
                        subjectOvertimeRegulation: getBool(editForm, "subjectOvertimeRegulation"),
                        affectsNetPay: getBool(editForm, "affectsNetPay"),
                        employerCostLine: getBool(editForm, "employerCostLine"),
                        isActive: getBool(editForm, "isActive"),
                        sortOrder: parseInt(editForm.querySelector('[data-hcm-field="sortOrder"]').value, 10) || 0,
                        defaultPercent: pctEd.defaultPercent,
                        percentBasis: pctEd.percentBasis,
                    };
                }
                apiRequest("put", "/v1/hcm/salary-components/" + encodeURIComponent(id), payload)
                    .then(function (p) {
                        if (!p || p.success !== true) {
                            notify(formatApiError(p, 0) || "Gagal memperbarui.", true);
                            return;
                        }
                        notify("Komponen diperbarui.", false);
                        var el = document.getElementById("arcav_edit_salary_component");
                        var mi = el && window.bootstrap && window.bootstrap.Modal.getInstance(el);
                        if (mi) {
                            mi.hide();
                        }
                        setLockedFields(editForm, false);
                        reload();
                    })
                    .catch(function (err) {
                        notify(formatApiError(err.data, err.status), true);
                    });
            });
        }

        document.addEventListener("click", function (e) {
            var del = e.target.closest("[data-hcm-salary-component-delete]");
            if (!del) {
                return;
            }
            e.preventDefault();
            var sid = del.getAttribute("data-hcm-salary-component-delete");
            var run =
                window.ArcavUi && typeof window.ArcavUi.confirmDelete === "function"
                    ? window.ArcavUi.confirmDelete("Hapus komponen gaji ini? Tindakan tidak memengaruhi slip lama.", "Hapus komponen")
                    : Promise.resolve(false);
            run.then(function (ok) {
                if (!ok) {
                    return;
                }
                apiRequest("delete", "/v1/hcm/salary-components/" + encodeURIComponent(sid), null)
                    .then(function (p) {
                        if (!p || p.success !== true) {
                            notify(formatApiError(p, 0) || "Gagal menghapus.", true);
                            return;
                        }
                        notify("Komponen dihapus.", false);
                        reload();
                    })
                    .catch(function (err) {
                        notify(formatApiError(err.data, err.status), true);
                    });
            });
        });

        document.addEventListener(
            "blur",
            function (e) {
                var el = e.target;
                if (!el || el.getAttribute("data-hcm-field") !== "defaultPercent") {
                    return;
                }
                var raw = String(el.value || "").trim();
                if (!raw) {
                    return;
                }
                var norm = formatDefaultPercentDisplay(raw);
                if (norm !== "") {
                    el.value = norm;
                }
            },
            true
        );

        reload();
    }

    function init() {
        if (document.querySelector("[data-hcm-salary-components-body]")) {
            bind();
        }
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", init);
    } else {
        init();
    }
})(window, document);
