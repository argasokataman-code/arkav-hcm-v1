(function (window, document) {
    "use strict";

    var CATEGORY_LABELS = {
        basic_wage: "Upah pokok",
        fixed_allowance: "Tunjangan tetap",
        common_allowance: "Tunjangan umum",
        family_allowance: "Tunjangan keluarga",
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
    var categoryRows = [];
    var categoryLabelByCode = Object.assign({}, CATEGORY_LABELS);
    var selectedCategoryTabKey = "all";
    var componentRows = [];

    var CATEGORY_UI_GROUPS = {
        basic_wage: "earning_routine",
        fixed_allowance: "earning_routine",
        common_allowance: "earning_routine",
        family_allowance: "earning_routine",
        irregular_allowance: "earning_non_routine",
        overtime: "earning_non_routine",
        thr: "earning_non_routine",
        bonus: "earning_non_routine",
        natura_taxable: "benefit",
        natura_non_taxable: "benefit",
        special_allowance: "benefit",
        reimbursement: "benefit",
        termination_benefit: "benefit",
        employer_cost_display: "benefit",
        other_addition: "earning_non_routine",
        bpjs_health_employee: "statutory_deduction",
        bpjs_jht_employee: "statutory_deduction",
        bpjs_jp_employee: "statutory_deduction",
        pension_employee: "statutory_deduction",
        pph21_ter: "statutory_deduction",
        pph21_december_recon: "statutory_deduction",
        other_statutory: "statutory_deduction",
        internal_advance: "internal_deduction",
        internal_loan: "internal_deduction",
        internal_cooperative: "internal_deduction",
        internal_other: "internal_deduction",
        other_deduction: "internal_deduction",
    };

    var UI_GROUP_LABELS = {
        earning_routine: "Penghasilan Rutin",
        earning_non_routine: "Penghasilan Tidak Rutin",
        benefit: "Benefit & Reimbursement",
        statutory_deduction: "Potongan Wajib (PPh21/BPJS)",
        internal_deduction: "Potongan Internal",
        other: "Lainnya",
    };

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
        var dynamic = categoryRows
            .filter(function (c) {
                return c && c.kind === kind && c.isActive !== false;
            })
            .sort(function (a, b) {
                var ao = Number(a.sortOrder || 0);
                var bo = Number(b.sortOrder || 0);
                if (ao !== bo) {
                    return ao - bo;
                }
                return String(a.name || "").localeCompare(String(b.name || ""));
            })
            .map(function (c) {
                return String(c.code || "");
            })
            .filter(function (code) {
                return code !== "";
            });
        var fallback = kind === "deduction" ? DEDUCTION_CATS : ADDITION_CATS;
        var list = dynamic.length ? dynamic : fallback;
        selectEl.innerHTML = list
            .map(function (c) {
                return '<option value="' + esc(c) + '">' + esc(categoryLabelByCode[c] || CATEGORY_LABELS[c] || c) + "</option>";
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
        if (!el) return;
        if (el.type === "checkbox") {
            el.checked = !!v;
        } else if (el.type === "hidden") {
            el.value = v ? "1" : "0";
        }
    }

    function getBool(form, field) {
        var el = form.querySelector('[data-hcm-field="' + field + '"]');
        if (!el) return false;
        if (el.type === "checkbox") return el.checked;
        if (el.type === "hidden") return el.value === "1" || el.value === "true";
        return false;
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

    function kindLabel(kind) {
        return kind === "deduction" ? "Potongan" : "Pendapatan";
    }

    function categoryLabel(code, fallbackName) {
        if (fallbackName) {
            return String(fallbackName);
        }
        return categoryLabelByCode[code] || CATEGORY_LABELS[code] || code;
    }

    function categorySortMap(kind) {
        var map = {};
        categoryRows
            .filter(function (c) {
                return c && c.kind === kind;
            })
            .forEach(function (c) {
                map[String(c.code)] = Number(c.sortOrder || 0);
            });
        return map;
    }

    function hydrateCategoryLabelMap(rows) {
        categoryLabelByCode = Object.assign({}, CATEGORY_LABELS);
        (rows || []).forEach(function (c) {
            if (c && c.code && c.name) {
                categoryLabelByCode[String(c.code)] = String(c.name);
            }
        });
    }

    function groupTabKey(group) {
        return String(group.kind || "") + "::" + String(group.category || "");
    }

    function uiGroupKeyForCategory(categoryCode, kind) {
        var key = CATEGORY_UI_GROUPS[String(categoryCode || "")];
        if (key) {
            return key;
        }
        return kind === "deduction" ? "internal_deduction" : "other";
    }

    function uiGroupSortOrder(key) {
        return {
            earning_routine: 10,
            earning_non_routine: 20,
            benefit: 30,
            statutory_deduction: 40,
            internal_deduction: 50,
            other: 60,
        }[key] || 999;
    }

    function renderCategoryTabs(groups, totalRows) {
        var host = document.querySelector("[data-hcm-salary-category-tabs]");
        if (!host) {
            return;
        }

        var aggregate = {};
        (groups || []).forEach(function (g) {
            var key = uiGroupKeyForCategory(g.category, g.kind);
            if (!aggregate[key]) {
                aggregate[key] = { key: key, count: 0 };
            }
            aggregate[key].count += (g.rows || []).length;
        });

        var groupTabs = Object.keys(aggregate)
            .map(function (key) {
                return aggregate[key];
            })
            .sort(function (a, b) {
                return uiGroupSortOrder(a.key) - uiGroupSortOrder(b.key);
            });

        var exists = groupTabs.some(function (g) {
            return g.key === selectedCategoryTabKey;
        });
        if (selectedCategoryTabKey !== "all" && !exists) {
            selectedCategoryTabKey = "all";
        }

        var tabs = [];
        tabs.push(
            '<button type="button" class="btn btn-sm ' +
                (selectedCategoryTabKey === "all" ? "btn-primary" : "btn-outline-primary") +
                '" data-hcm-tab-category="all" role="tab" aria-selected="' +
                (selectedCategoryTabKey === "all" ? "true" : "false") +
                '" tabindex="' +
                (selectedCategoryTabKey === "all" ? "0" : "-1") +
                '">Semua kategori (' +
                esc(String(totalRows || 0)) +
                ")</button>"
        );

        groupTabs.forEach(function (group) {
            var key = group.key;
            var label = UI_GROUP_LABELS[key] || key;
            tabs.push(
                '<button type="button" class="btn btn-sm ' +
                    (selectedCategoryTabKey === key ? "btn-primary" : "btn-outline-primary") +
                    '" data-hcm-tab-category="' +
                    esc(key) +
                    '" role="tab" aria-selected="' +
                    (selectedCategoryTabKey === key ? "true" : "false") +
                    '" tabindex="' +
                    (selectedCategoryTabKey === key ? "0" : "-1") +
                    '">' +
                    esc(label) +
                    " (" +
                    esc(String(group.count || 0)) +
                    ")</button>"
            );
        });

        host.innerHTML = tabs.join("");
    }

    function bind() {
        var body = document.querySelector("[data-hcm-salary-components-body]");
        var addForm = document.querySelector('[data-hcm-salary-component-form="add"]');
        var editForm = document.querySelector('[data-hcm-salary-component-form="edit"]');
        var categoryForm = document.querySelector('[data-hcm-salary-category-form="edit"]');
        var categoryBody = document.querySelector("[data-hcm-salary-category-body]");
        var addKind = addForm && addForm.querySelector('[data-hcm-field="kind"]');
        var editKind = editForm && editForm.querySelector('[data-hcm-field="kind"]');

        function render(rows) {
            componentRows = Array.isArray(rows) ? rows.slice() : [];
            rowCache = {};
            componentRows.forEach(function (r) {
                rowCache[r.id] = r;
            });
            if (!body) {
                return;
            }
            var grouped = {};
            componentRows.forEach(function (r) {
                var key = String(r.kind || "") + "::" + String(r.category || "");
                if (!grouped[key]) {
                    grouped[key] = { kind: r.kind, category: r.category, rows: [] };
                }
                grouped[key].rows.push(r);
            });

            var additionSort = categorySortMap("addition");
            var deductionSort = categorySortMap("deduction");

            var groups = Object.keys(grouped)
                .map(function (key) {
                    return grouped[key];
                })
                .sort(function (a, b) {
                    if (a.kind !== b.kind) {
                        return a.kind === "addition" ? -1 : 1;
                    }
                    var sortMap = a.kind === "deduction" ? deductionSort : additionSort;
                    var as = sortMap[String(a.category)] != null ? sortMap[String(a.category)] : 9999;
                    var bs = sortMap[String(b.category)] != null ? sortMap[String(b.category)] : 9999;
                    if (as !== bs) {
                        return as - bs;
                    }
                    return categoryLabel(String(a.category)).localeCompare(categoryLabel(String(b.category)));
                });

            renderCategoryTabs(groups, componentRows.length);

            var visibleRows = componentRows.filter(function (row) {
                if (selectedCategoryTabKey === "all") {
                    return true;
                }
                return uiGroupKeyForCategory(row.category, row.kind) === selectedCategoryTabKey;
            });

            visibleRows.sort(function (a, b) {
                var ag = uiGroupKeyForCategory(a.category, a.kind);
                var bg = uiGroupKeyForCategory(b.category, b.kind);
                if (ag !== bg) {
                    return uiGroupSortOrder(ag) - uiGroupSortOrder(bg);
                }
                if (a.kind !== b.kind) {
                    return a.kind === "addition" ? -1 : 1;
                }
                var sortMap = a.kind === "deduction" ? deductionSort : additionSort;
                var as = sortMap[String(a.category)] != null ? sortMap[String(a.category)] : 9999;
                var bs = sortMap[String(b.category)] != null ? sortMap[String(b.category)] : 9999;
                if (as !== bs) {
                    return as - bs;
                }
                return String(a.name || "").localeCompare(String(b.name || ""));
            });

            var currentSection = "";
            var html = visibleRows
                .map(function (r) {
                    var badge = r.isActive ? "success" : "danger";
                    var st = r.isActive ? "Aktif" : "Nonaktif";
                    var kindLabelText = r.kind === "deduction" ? "Potongan" : "Pendapatan";
                    if (r.employerCostLine) {
                        kindLabelText = "Info beban perusahaan";
                    }
                    var lockBadge = r.isSystemLocked
                        ? ' <span class="badge badge-secondary badge-xs ms-1" title="System">System</span>'
                        : "";
                    var legal = truncate(r.legalBasis || "—", 56);
                    var del =
                        r.isSystemLocked || r.integrationLocked
                            ? ""
                            : '<a href="#" class="ms-2" data-hcm-salary-component-delete="' +
                              esc(String(r.id)) +
                              '"><i class="ti ti-trash"></i></a>';
                    var edit = '<a href="#" class="me-2" data-hcm-salary-component-edit="' +
                        esc(String(r.id)) +
                        '" data-bs-toggle="modal" data-bs-target="#arcav_edit_salary_component"><i class="ti ti-edit"></i></a>';
                    var sectionHtml = "";
                    var sectionKey = uiGroupKeyForCategory(r.category, r.kind);
                    if (selectedCategoryTabKey === "all" && sectionKey !== currentSection) {
                        currentSection = sectionKey;
                        sectionHtml = '<tr class="table-secondary"><td colspan="9" class="fw-semibold">' +
                            esc(UI_GROUP_LABELS[sectionKey] || "Lainnya") +
                            '</td></tr>';
                    }
                    return sectionHtml + (
                        "<tr><td><code>" +
                        esc(r.code) +
                        "</code></td><td><h6 class=\"fw-medium mb-0\">" +
                        esc(r.name) +
                        lockBadge +
                        "</h6></td><td>" +
                        esc(kindLabelText) +
                        "</td><td><span class=\"text-muted small\">" +
                        esc(categoryLabel(r.category, r.categoryName)) +
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
                .join("");

            body.innerHTML = html || '<tr><td colspan="9" class="text-center py-4 text-muted">Belum ada komponen pada filter ini.</td></tr>';
        }

        function resetCategoryForm() {
            if (!categoryForm) {
                return;
            }
            categoryForm.querySelector('[data-hcm-category-field="id"]').value = "";
            categoryForm.querySelector('[data-hcm-category-field="kind"]').value = "addition";
            categoryForm.querySelector('[data-hcm-category-field="code"]').value = "";
            categoryForm.querySelector('[data-hcm-category-field="name"]').value = "";
            categoryForm.querySelector('[data-hcm-category-field="description"]').value = "";
            categoryForm.querySelector('[data-hcm-category-field="sortOrder"]').value = "0";
            categoryForm.querySelector('[data-hcm-category-field="isActive"]').checked = true;
        }

        function renderCategories(rows) {
            categoryRows = Array.isArray(rows) ? rows : [];
            hydrateCategoryLabelMap(categoryRows);

            if (addForm && addKind) {
                fillCategorySelect(addForm.querySelector('[data-hcm-field="category"]'), addKind.value, addForm.querySelector('[data-hcm-field="category"]').value);
            }
            if (editForm && editKind) {
                fillCategorySelect(editForm.querySelector('[data-hcm-field="category"]'), editKind.value, editForm.querySelector('[data-hcm-field="category"]').value);
            }

            if (!categoryBody) {
                return;
            }

            categoryBody.innerHTML =
                categoryRows
                    .map(function (c) {
                        var status = c.isActive ? "Active" : "Inactive";
                        var statusBadge = c.isActive ? "success" : "danger";
                        var lock = c.isSystem
                            ? ' <span class="badge badge-secondary badge-xs ms-1">System</span>'
                            : "";
                        var del = '<a href="#" data-hcm-category-delete="' + esc(String(c.id)) + '" class="text-danger"><i class="ti ti-trash"></i></a>';
                        return (
                            "<tr><td>" +
                            esc(kindLabel(c.kind)) +
                            "</td><td><code>" +
                            esc(c.code) +
                            "</code></td><td>" +
                            esc(c.name) +
                            lock +
                            (c.description ? '<div class="text-muted small">' + esc(c.description) + "</div>" : "") +
                            "</td><td>" +
                            esc(String(c.usageCount || 0)) +
                            "</td><td><span class=\"badge badge-" +
                            statusBadge +
                            '\">' +
                            esc(status) +
                            "</span></td><td><div class=\"d-inline-flex gap-2\"><a href=\"#\" data-hcm-category-edit=\"" +
                            esc(String(c.id)) +
                            '\"><i class=\"ti ti-edit\"></i></a>' +
                            del +
                            "</div></td></tr>"
                        );
                    })
                    .join("") || '<tr><td colspan="6" class="text-center text-muted py-3">Belum ada kategori.</td></tr>';
        }

        function reloadCategories() {
            return apiRequest("get", "/v1/hcm/salary-component-categories", null)
                .then(function (p) {
                    if (!p || p.success !== true) {
                        throw { status: 0, data: p };
                    }
                    renderCategories(p.data || []);
                });
        }

        function reload() {
            Promise.all([
                reloadCategories(),
                apiRequest("get", "/v1/hcm/salary-components", null),
            ])
                .then(function (result) {
                    var p = result[1];
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

        function rerenderCurrentRows() {
            render(componentRows);
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

        if (categoryForm) {
            var resetBtn = categoryForm.querySelector('[data-hcm-category-action="reset"]');
            if (resetBtn) {
                resetBtn.addEventListener("click", function () {
                    resetCategoryForm();
                });
            }

            categoryForm.addEventListener("submit", function (e) {
                e.preventDefault();
                var id = categoryForm.querySelector('[data-hcm-category-field="id"]').value.trim();
                var kind = categoryForm.querySelector('[data-hcm-category-field="kind"]').value;
                var code = categoryForm.querySelector('[data-hcm-category-field="code"]').value.trim();
                var name = categoryForm.querySelector('[data-hcm-category-field="name"]').value.trim();
                if (!kind || !code || !name) {
                    notify("Lengkapi jenis, kode, dan nama kategori.", true);
                    return;
                }
                var payload = {
                    kind: kind,
                    code: code,
                    name: name,
                    description: categoryForm.querySelector('[data-hcm-category-field="description"]').value.trim() || null,
                    sortOrder: parseInt(categoryForm.querySelector('[data-hcm-category-field="sortOrder"]').value, 10) || 0,
                    isActive: !!categoryForm.querySelector('[data-hcm-category-field="isActive"]').checked,
                };
                var method = id ? "put" : "post";
                var url = id
                    ? "/v1/hcm/salary-component-categories/" + encodeURIComponent(id)
                    : "/v1/hcm/salary-component-categories";
                apiRequest(method, url, payload)
                    .then(function (p) {
                        if (!p || p.success !== true) {
                            notify(formatApiError(p, 0) || "Gagal menyimpan kategori.", true);
                            return;
                        }
                        notify(id ? "Kategori diperbarui." : "Kategori ditambahkan.", false);
                        resetCategoryForm();
                        reload();
                    })
                    .catch(function (err) {
                        notify(formatApiError(err.data, err.status), true);
                    });
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
                        setBool(addForm, "affectsNetPay", true);
                        // PPh21 flags ada di hidden input; reset ke default (TerGross=1, AnnualRecon=0)
                        setBool(addForm, "includePph21TerGross", true);
                        setBool(addForm, "includePph21AnnualReconciliation", false);
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
            var tabBtn = e.target.closest("[data-hcm-tab-category]");
            if (tabBtn) {
                e.preventDefault();
                selectedCategoryTabKey = String(tabBtn.getAttribute("data-hcm-tab-category") || "all");
                rerenderCurrentRows();
                return;
            }

            var catEdit = e.target.closest("[data-hcm-category-edit]");
            if (catEdit && categoryForm) {
                e.preventDefault();
                var cid = parseInt(catEdit.getAttribute("data-hcm-category-edit"), 10);
                var c = (categoryRows || []).find(function (row) {
                    return Number(row.id) === cid;
                });
                if (!c) {
                    return;
                }
                categoryForm.querySelector('[data-hcm-category-field="id"]').value = String(c.id);
                categoryForm.querySelector('[data-hcm-category-field="kind"]').value = c.kind || "addition";
                categoryForm.querySelector('[data-hcm-category-field="code"]').value = c.code || "";
                categoryForm.querySelector('[data-hcm-category-field="name"]').value = c.name || "";
                categoryForm.querySelector('[data-hcm-category-field="description"]').value = c.description || "";
                categoryForm.querySelector('[data-hcm-category-field="sortOrder"]').value = String(c.sortOrder != null ? c.sortOrder : 0);
                categoryForm.querySelector('[data-hcm-category-field="isActive"]').checked = !!c.isActive;
                return;
            }

            var catDel = e.target.closest("[data-hcm-category-delete]");
            if (catDel) {
                e.preventDefault();
                var delId = catDel.getAttribute("data-hcm-category-delete");
                var confirmDeleteCategory =
                    window.ArcavUi && typeof window.ArcavUi.confirmDelete === "function"
                        ? window.ArcavUi.confirmDelete("Hapus kategori ini? Semua komponen di kategori ini ikut dihapus dan link runtime akan dilepas otomatis.", "Hapus kategori")
                        : Promise.resolve(false);
                confirmDeleteCategory.then(function (ok) {
                    if (!ok) {
                        return;
                    }
                    apiRequest("delete", "/v1/hcm/salary-component-categories/" + encodeURIComponent(delId), null)
                        .then(function (p) {
                            if (!p || p.success !== true) {
                                notify(formatApiError(p, 0) || "Gagal menghapus kategori.", true);
                                return;
                            }
                            notify("Kategori dihapus.", false);
                            resetCategoryForm();
                            reload();
                        })
                        .catch(function (err) {
                            notify(formatApiError(err.data, err.status), true);
                        });
                });
                return;
            }

            var btn = e.target.closest("[data-hcm-salary-component-edit]");
            if (!btn || !editForm) {
                return;
            }
            var id = parseInt(btn.getAttribute("data-hcm-salary-component-edit"), 10);
            var r = rowCache[id];
            if (!r) {
                return;
            }
            setLockedFields(editForm, false);
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

        var categoryModal = document.getElementById("arcav_salary_component_category_master");
        if (categoryModal && categoryForm) {
            categoryModal.addEventListener("hidden.bs.modal", function () {
                resetCategoryForm();
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
                var payload = {
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
