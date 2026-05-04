(function (window, document) {
    "use strict";

    var PERCENT_BASIS_LABELS = {
        basic_wage: "Upah pokok",
        wage_bpjs_health: "Dasar BPJS Kes",
        wage_bpjs_tk: "Dasar BPJS TK",
        gross_monthly_ter: "Bruto TER",
        thr_calculation_base: "Basis THR",
    };

    var rowCache = {};
    var categoryRows = [];
    var categoryLabelByCode = {};
    var selectedCategoryTabKey = "all";
    var selectedViewTabKey = "components";
    var componentRows = [];
    var employeeProfileRows = [];
    var complianceSeverityRank = { high: 1, medium: 2, low: 3 };

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
        var list = categoryRows
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
            .filter(function (c) {
                return String(c.code || "") !== "";
            })
            .map(function (c) {
                return {
                    code: String(c.code || ""),
                    name: String(c.name || c.code || ""),
                };
            });
        if (!list.length) {
            selectEl.innerHTML = '<option value="">Tidak ada kategori aktif</option>';
            selectEl.value = "";
            return;
        }
        selectEl.innerHTML = list.map(function (item) {
            return '<option value="' + esc(item.code) + '">' + esc(item.name) + '</option>';
        }).join("");
        var availableCodes = list.map(function (item) { return item.code; });
        if (current && availableCodes.indexOf(current) >= 0) {
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

    var SOURCE_MODULE_BADGE_MAP = {
        salary:    { cls: "badge-orange",   label: "Gaji Pokok" },
        bpjs:      { cls: "badge-info",     label: "BPJS" },
        allowance: { cls: "badge-success",  label: "Tunjangan" },
        pph21:     { cls: "badge-warning",  label: "PPh 21" },
        overtime:  { cls: "badge-primary",  label: "Lembur" },
        thr:       { cls: "badge-danger",   label: "THR" },
        pkwt:      { cls: "badge-dark",     label: "PKWT" },
    };
    function sourceModuleBadge(row) {
        var module = row ? row.sourceModule : null;
        var m = module ? SOURCE_MODULE_BADGE_MAP[module] : null;
        if (!m) {
            return "";
        }
        return '<span class="badge ' + m.cls + ' badge-xs ms-1" title="Dikelola oleh: ' + m.label + '">' + m.label + '</span>';
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
        if (categoryLabelByCode[code]) {
            return categoryLabelByCode[code];
        }
        return String(code || "")
            .replace(/_/g, " ")
            .replace(/\b\w/g, function (ch) { return ch.toUpperCase(); });
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
        categoryLabelByCode = {};
        (rows || []).forEach(function (c) {
            if (c && c.code && c.name) {
                categoryLabelByCode[String(c.code)] = String(c.name);
            }
        });
    }

    function severityBadge(severity) {
        if (severity === "high") {
            return { cls: "danger", label: "High" };
        }
        if (severity === "medium") {
            return { cls: "warning", label: "Medium" };
        }
        return { cls: "secondary", label: "Low" };
    }

    function findComplianceIssues(rows) {
        var issues = [];
        var codeCount = {};

        rows.forEach(function (row) {
            var code = String(row.code || "").trim().toLowerCase();
            if (!code) {
                return;
            }
            codeCount[code] = (codeCount[code] || 0) + 1;
        });

        rows.forEach(function (row) {
            var code = String(row.code || "").trim().toLowerCase();
            var hasPercent = row.defaultPercent != null && String(row.defaultPercent) !== "";
            var hasBasis = !!String(row.percentBasis || "").trim();
            var legalBasis = String(row.legalBasis || "").trim();

            if (code && codeCount[code] > 1) {
                issues.push({
                    severity: "high",
                    code: row.code || "-",
                    name: row.name || "Untitled",
                    detail: "Kode komponen duplikat pada master.",
                    recommendation: "Gunakan kode unik per komponen untuk mencegah konflik referensi.",
                });
            }

            if (hasPercent && !hasBasis) {
                issues.push({
                    severity: "high",
                    code: row.code || "-",
                    name: row.name || "Untitled",
                    detail: "Default persen terisi tetapi dasar perhitungan kosong.",
                    recommendation: "Isi percent basis atau kosongkan persen default.",
                });
            }

            if (!hasPercent && hasBasis) {
                issues.push({
                    severity: "medium",
                    code: row.code || "-",
                    name: row.name || "Untitled",
                    detail: "Dasar perhitungan persen terisi tanpa nilai persen.",
                    recommendation: "Kosongkan percent basis bila komponen bersifat nominal.",
                });
            }

            if (row.isSystemLocked && !row.isActive) {
                issues.push({
                    severity: "high",
                    code: row.code || "-",
                    name: row.name || "Untitled",
                    detail: "Komponen governance terkunci berstatus nonaktif.",
                    recommendation: "Aktifkan kembali dari modul governance asal agar kalkulasi tidak drift.",
                });
            }

            if (row.integrationLocked && !row.sourceModule) {
                issues.push({
                    severity: "medium",
                    code: row.code || "-",
                    name: row.name || "Untitled",
                    detail: "Komponen terkunci integrasi tanpa informasi source module.",
                    recommendation: "Sinkronkan metadata source module pada registry governance.",
                });
            }
        });

        issues.sort(function (a, b) {
            var aRank = complianceSeverityRank[a.severity] || 99;
            var bRank = complianceSeverityRank[b.severity] || 99;
            if (aRank !== bRank) {
                return aRank - bRank;
            }
            return String(a.code || "").localeCompare(String(b.code || ""));
        });

        return issues;
    }

    function renderCompliance(rows) {
        var summaryHost = document.querySelector("[data-hcm-salary-compliance-summary]");
        var bodyHost = document.querySelector("[data-hcm-salary-compliance-body]");
        if (!summaryHost || !bodyHost) {
            return;
        }

        var dataRows = Array.isArray(rows) ? rows : [];
        var issues = findComplianceIssues(dataRows);
        var high = issues.filter(function (item) { return item.severity === "high"; }).length;
        var medium = issues.filter(function (item) { return item.severity === "medium"; }).length;
        var low = issues.filter(function (item) { return item.severity === "low"; }).length;
        var weighted = (high * 3) + (medium * 2) + (low * 1);
        var denominator = Math.max(dataRows.length * 3, 1);
        var score = Math.max(0, 100 - Math.round((weighted / denominator) * 100));

        summaryHost.innerHTML = [
            '<div class="col-md-3"><div class="border rounded p-3 h-100 bg-light" style="min-height:132px;"><div class="text-muted small">Compliance Score</div><div class="fw-bold" style="display:block !important;font-size:36px !important;line-height:1.05 !important;letter-spacing:-0.02em !important;font-variant-numeric:tabular-nums !important;color:#111827 !important;">' + esc(String(score)) + '%</div><div class="small text-muted">Snapshot konfigurasi saat ini</div></div></div>',
            '<div class="col-md-3"><div class="border rounded p-3 h-100" style="min-height:132px;"><div class="text-muted small">High Severity</div><div class="fw-bold" style="display:block !important;font-size:36px !important;line-height:1.05 !important;letter-spacing:-0.02em !important;font-variant-numeric:tabular-nums !important;color:#dc2626 !important;">' + esc(String(high)) + '</div><div class="small text-muted">Perlu tindakan prioritas</div></div></div>',
            '<div class="col-md-3"><div class="border rounded p-3 h-100" style="min-height:132px;"><div class="text-muted small">Medium Severity</div><div class="fw-bold" style="display:block !important;font-size:36px !important;line-height:1.05 !important;letter-spacing:-0.02em !important;font-variant-numeric:tabular-nums !important;color:#d97706 !important;">' + esc(String(medium)) + '</div><div class="small text-muted">Perlu dirapikan</div></div></div>',
            '<div class="col-md-3"><div class="border rounded p-3 h-100" style="min-height:132px;"><div class="text-muted small">Komponen Dipantau</div><div class="fw-bold" style="display:block !important;font-size:36px !important;line-height:1.05 !important;letter-spacing:-0.02em !important;font-variant-numeric:tabular-nums !important;color:#111827 !important;">' + esc(String(dataRows.length)) + '</div><div class="small text-muted">Total salary component</div></div></div>'
        ].join("");

        if (!issues.length) {
            bodyHost.innerHTML = '<tr><td colspan="4" class="text-center text-success py-3">Tidak ada anomali compliance terdeteksi.</td></tr>';
            return;
        }

        bodyHost.innerHTML = issues.slice(0, 12).map(function (issue) {
            var badge = severityBadge(issue.severity);
            return '<tr>' +
                '<td><span class="badge badge-' + esc(badge.cls) + '">' + esc(badge.label) + '</span></td>' +
                '<td><div class="fw-medium">' + esc(issue.name) + '</div><div class="text-muted small"><code>' + esc(issue.code) + '</code></div></td>' +
                '<td class="small">' + esc(issue.detail) + '</td>' +
                '<td class="small text-muted">' + esc(issue.recommendation) + '</td>' +
                '</tr>';
        }).join("");
    }

    function renderCategoryTabs(rows) {
        var host = document.querySelector("[data-hcm-salary-category-tabs]");
        if (!host) {
            return;
        }

        var data = Array.isArray(rows) ? rows : [];
        var counts = {
            all: data.length,
            addition: data.filter(function (row) { return row.kind === "addition"; }).length,
            deduction: data.filter(function (row) { return row.kind === "deduction"; }).length,
        };
        if (!["all", "addition", "deduction"].includes(selectedCategoryTabKey)) {
            selectedCategoryTabKey = "all";
        }

        var tabs = [
            { key: "all", label: "Semua" },
            { key: "addition", label: "Pendapatan" },
            { key: "deduction", label: "Potongan" },
        ];

        host.innerHTML = tabs.map(function (tab) {
            var active = selectedCategoryTabKey === tab.key;
            return '<button type="button" class="btn btn-sm ' +
                (active ? "btn-primary" : "btn-outline-primary") +
                '" data-hcm-tab-category="' + esc(tab.key) + '" role="tab" aria-selected="' +
                (active ? "true" : "false") + '" tabindex="' + (active ? "0" : "-1") + '">' +
                esc(tab.label) + ' (' + esc(String(counts[tab.key] || 0)) + ')</button>';
        }).join("");
    }

    function integrationStatusBadge(status) {
        if (status === "ready") {
            return '<span class="badge badge-success badge-xs">Ready</span>';
        }
        if (status === "partial") {
            return '<span class="badge badge-warning badge-xs">Partial</span>';
        }
        return '<span class="badge badge-danger badge-xs">Missing</span>';
    }

    function integrationGapLabel(gapKey) {
        var map = {
            pph21Policy: "Policy PPh21 belum aktif",
            pph21Profile: "Profil pajak karyawan belum lengkap",
            bpjsPolicy: "Policy BPJS tenant belum lengkap",
            bpjsMembership: "Membership BPJS karyawan belum lengkap",
            allowancePolicy: "Policy allowance belum aktif",
            allowanceAssignment: "Assignment allowance governance belum ada",
            payrollAssignment: "Belum ada payroll assignment aktif",
        };
        return map[String(gapKey || "")] || String(gapKey || "-");
    }

    function fmtRupiah(amount) {
        return "Rp " + Number(amount || 0).toLocaleString("id-ID", { minimumFractionDigits: 0, maximumFractionDigits: 0 });
    }

    function sourceModuleLabel(mod) {
        var map = {
            pph21: "PPh 21",
            bpjs: "BPJS",
            allowance: "Allowance",
            overtime: "Lembur",
            thr: "THR",
            pkwt: "PKWT",
            system: "System",
            manual: "Manual",
        };
        return map[String(mod || "")] || String(mod || "-");
    }

    function openEmployeeProfileDetail(row) {
        var modalEl = document.getElementById("arcav_employee_profile_detail");
        var titleEl = document.getElementById("arcav_employee_profile_detail_title");
        var bodyEl = document.getElementById("arcav_employee_profile_detail_body");
        if (!modalEl || !bodyEl) { return; }

        if (titleEl) {
            titleEl.textContent = (row.fullName || "-") + " — Profil Integrasi";
        }

        var identityClean = row.hasCleanIdentity === true;
        var identityGaps = Array.isArray(row.identityGaps) ? row.identityGaps : [];
        var integrationChecks = Array.isArray(row.integrationSummary && row.integrationSummary.checks) ? row.integrationSummary.checks : [];
        var integrationGaps = Array.isArray(row.integrationSummary && row.integrationSummary.gaps) ? row.integrationSummary.gaps : [];
        var componentDetails = Array.isArray(row.componentDetails) ? row.componentDetails : [];
        var governanceComponents = Array.isArray(row.governanceComponents) ? row.governanceComponents : [];
        var baseSalary = Number(row.baseSalary || 0);
        var totalAdditions = Number(row.totalAdditions || 0);
        var totalDeductions = Number(row.totalDeductions || 0);

        // Compute governance BPJS deductions total for THP display
        var govDeductions = governanceComponents.filter(function (g) { return g.kind === "deduction" && g.amount !== null && g.amount > 0; });
        var govDeductionTotal = govDeductions.reduce(function (sum, g) { return sum + Number(g.amount || 0); }, 0);
        var pph21Pending = governanceComponents.some(function (g) {
            return g && g.sourceModule === "pph21" && (g.amount === null || g.amount === undefined);
        });
        var estimatedTakeHome = baseSalary + totalAdditions - totalDeductions - govDeductionTotal;

        // Identity section
        var identityHtml = '<div class="mb-3">' +
            '<h6 class="fw-semibold mb-2 text-primary">Identitas Karyawan</h6>' +
            '<div class="row g-2 small">' +
                '<div class="col-sm-6"><span class="text-muted">Nama:</span> ' + esc(row.fullName || "-") + '</div>' +
                '<div class="col-sm-6"><span class="text-muted">Kode:</span> ' + esc(row.employeeCode || "-") + '</div>' +
                '<div class="col-sm-6"><span class="text-muted">Email:</span> ' + esc(row.email || "-") + '</div>' +
                '<div class="col-sm-6"><span class="text-muted">Telepon:</span> ' + esc(row.phone || "-") + '</div>' +
                '<div class="col-sm-6"><span class="text-muted">Departemen:</span> ' + esc(row.departmentName || "-") + '</div>' +
                '<div class="col-sm-6"><span class="text-muted">Jabatan:</span> ' + esc(row.designationName || "-") + '</div>' +
            '</div>' +
            '<div class="mt-2">' +
                (identityClean
                    ? '<span class="badge badge-success badge-xs">Identitas Lengkap</span>'
                    : '<span class="badge badge-danger badge-xs me-1">Identitas Tidak Lengkap</span><span class="small text-danger">' + esc(identityGaps.join(", ")) + '</span>') +
            '</div>' +
        '</div>';

        // Governance checks section
        var checksHtml = '<div class="mb-3">' +
            '<h6 class="fw-semibold mb-2 text-primary">Status Integrasi Governance</h6>' +
            '<div class="d-flex flex-wrap gap-2">' +
            integrationChecks.map(function (check) {
                var ready = !!(check && check.ready);
                var label = check && check.label ? String(check.label) : "Integration";
                return '<div class="border rounded p-2 text-center" style="min-width:130px">' +
                    '<div class="small fw-medium">' + esc(label) + '</div>' +
                    '<div class="mt-1">' +
                        (ready
                            ? '<span class="badge badge-success badge-xs">Ready</span>'
                            : '<span class="badge badge-danger badge-xs">Gap</span>') +
                    '</div>' +
                '</div>';
            }).join("") +
            '</div>' +
            (integrationGaps.length
                ? '<div class="mt-2 small text-danger"><strong>Gap terdeteksi:</strong> ' + esc(integrationGaps.map(integrationGapLabel).join("; ")) + '</div>'
                : '<div class="mt-2 small text-success">Tidak ada gap governance.</div>') +
        '</div>';

        // Take Home Pay breakdown (includes governance deductions)
        var thpHtml = '<div class="mb-3">' +
            '<h6 class="fw-semibold mb-2 text-primary">Estimasi Take Home Pay</h6>' +
            '<table class="table table-sm table-borderless mb-1" style="max-width:420px">' +
                '<tbody>' +
                    '<tr><td class="text-muted small">Gaji Pokok</td><td class="text-end small fw-medium">' + esc(fmtRupiah(baseSalary)) + '</td></tr>' +
                    (totalAdditions > 0 ? '<tr><td class="text-muted small text-success">+ Tunjangan/Tambahan</td><td class="text-end small text-success">' + esc(fmtRupiah(totalAdditions)) + '</td></tr>' : '') +
                    (totalDeductions > 0 ? '<tr><td class="text-muted small text-danger">- Potongan Lainnya</td><td class="text-end small text-danger">' + esc(fmtRupiah(totalDeductions)) + '</td></tr>' : '') +
                    govDeductions.map(function (g) {
                        return '<tr><td class="text-muted small text-danger">- ' + esc(g.name) + (g.ratePercent ? ' (' + g.ratePercent + '%)' : '') + '</td><td class="text-end small text-danger">' + esc(fmtRupiah(g.amount)) + '</td></tr>';
                    }).join("") +
                    governanceComponents.filter(function (g) { return g.amount === null; }).map(function (g) {
                        return '<tr><td class="text-muted small text-danger">- ' + esc(g.name) + '</td><td class="text-end small text-muted fst-italic">' + esc(g.note || "Dihitung saat payroll run") + '</td></tr>';
                    }).join("") +
                    '<tr class="border-top"><td class="small fw-semibold">Est. Take Home Pay</td><td class="text-end fw-semibold">' + esc(fmtRupiah(estimatedTakeHome)) + '</td></tr>' +
                '</tbody>' +
            '</table>' +
            '<div class="small text-muted fst-italic">' +
                (pph21Pending
                    ? '* PPh 21 belum terestimasi karena status PTKP belum lengkap.'
                    : '* Estimasi sudah memasukkan PPh 21 TER sesuai status PTKP aktif.') +
            '</div>' +
        '</div>';

        // Active components table (assignment-based)
        var compHtml = '<div class="mb-2">' +
            '<h6 class="fw-semibold mb-2 text-primary">Komponen Aktif</h6>';

        var allCompRows = [].concat(
            componentDetails.map(function (cd) { return Object.assign({}, cd, { isEstimated: false }); }),
            governanceComponents
        );

        if (!allCompRows.length) {
            compHtml += '<p class="text-muted small">Belum ada komponen aktif yang di-assign.</p>';
        } else {
            compHtml += '<div class="table-responsive"><table class="table table-sm small">' +
                '<thead class="thead-light"><tr>' +
                    '<th>Komponen</th><th>Kode</th><th>Modul</th><th>Jenis</th><th class="text-end">Nominal</th>' +
                '</tr></thead><tbody>' +
                allCompRows.map(function (cd) {
                    var isAdd = cd.kind === "addition";
                    var isDed = cd.kind === "deduction";
                    var nominalCell;
                    if (cd.amount === null || cd.amount === undefined) {
                        nominalCell = '<span class="text-muted fst-italic small">' + esc(cd.note || "TER lookup") + '</span>';
                    } else {
                        nominalCell = '<span class="' + (isAdd ? 'text-success' : (isDed ? 'text-danger' : '')) + '">' + esc(fmtRupiah(cd.amount)) + '</span>' + (cd.ratePercent ? ' <span class="text-muted small">(' + cd.ratePercent + '%)</span>' : '') + (cd.isEstimated ? ' <span class="badge badge-soft-secondary badge-xs">Est.</span>' : '');
                    }
                    return '<tr>' +
                        '<td>' + esc(cd.name || "-") + '</td>' +
                        '<td class="text-muted">' + esc(cd.code || "-") + '</td>' +
                        '<td><span class="badge badge-soft-secondary badge-xs">' + esc(sourceModuleLabel(cd.sourceModule)) + '</span></td>' +
                        '<td>' + (isAdd ? '<span class="badge badge-success badge-xs">+ Tambahan</span>' : (isDed ? '<span class="badge badge-danger badge-xs">- Potongan</span>' : '<span class="badge badge-secondary badge-xs">' + esc(cd.kind) + '</span>')) + '</td>' +
                        '<td class="text-end">' + nominalCell + '</td>' +
                    '</tr>';
                }).join("") +
                '</tbody></table></div>';
        }
        compHtml += '</div>';

        bodyEl.innerHTML = identityHtml + checksHtml + thpHtml + compHtml;

        if (window.bootstrap && window.bootstrap.Modal) {
            var bsModal = window.bootstrap.Modal.getOrCreateInstance(modalEl);
            bsModal.show();
        }
    }

    function renderProfileSummary(rows, metaSummary) {
        var host = document.querySelector("[data-hcm-salary-profile-summary]");
        if (!host) {
            return;
        }

        var data = Array.isArray(rows) ? rows : [];
        var ready = metaSummary && Number.isFinite(metaSummary.ready)
            ? Number(metaSummary.ready)
            : data.filter(function (row) { return row.integrationStatus === "ready"; }).length;
        var partial = metaSummary && Number.isFinite(metaSummary.partial)
            ? Number(metaSummary.partial)
            : data.filter(function (row) { return row.integrationStatus === "partial"; }).length;
        var missing = metaSummary && Number.isFinite(metaSummary.missing)
            ? Number(metaSummary.missing)
            : data.filter(function (row) { return row.integrationStatus === "missing"; }).length;

        host.innerHTML = [
            '<div class="col-6 col-lg-3"><div class="border rounded p-3 bg-white h-100"><div class="text-muted small">Total karyawan</div><div class="fw-bold fs-2 lh-1 mt-2">' + esc(String(data.length)) + '</div></div></div>',
            '<div class="col-6 col-lg-3"><div class="border rounded p-3 bg-white h-100"><div class="text-muted small">Ready</div><div class="fw-bold fs-2 lh-1 mt-2 text-success">' + esc(String(ready)) + '</div></div></div>',
            '<div class="col-6 col-lg-3"><div class="border rounded p-3 bg-white h-100"><div class="text-muted small">Partial</div><div class="fw-bold fs-2 lh-1 mt-2 text-warning">' + esc(String(partial)) + '</div></div></div>',
            '<div class="col-6 col-lg-3"><div class="border rounded p-3 bg-white h-100"><div class="text-muted small">Missing</div><div class="fw-bold fs-2 lh-1 mt-2 text-danger">' + esc(String(missing)) + '</div></div></div>'
        ].join("");
    }

    function renderEmployeeProfiles(rows, metaSummary) {
        employeeProfileRows = Array.isArray(rows) ? rows.slice() : [];
        renderProfileSummary(employeeProfileRows, metaSummary);

        var body = document.querySelector("[data-hcm-salary-profile-body]");
        if (!body) {
            return;
        }

        if (!employeeProfileRows.length) {
            body.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-4">Belum ada data profil karyawan pada tenant aktif.</td></tr>';
            return;
        }

        body.innerHTML = employeeProfileRows.map(function (row, idx) {
            var takeHomePay = Number(row.takeHomePay || 0);

            return '<tr>' +
                '<td>' +
                    '<div class="fw-medium">' + esc(row.fullName || "-") + '</div>' +
                    '<div class="small text-muted">' + esc(row.employeeCode || "-") + '</div>' +
                '</td>' +
                '<td>' +
                    '<div class="small">' + esc(row.departmentName || "-") + '</div>' +
                    '<div class="small text-muted">' + esc(row.designationName || "-") + '</div>' +
                '</td>' +
                '<td class="text-nowrap fw-medium">' + esc(fmtRupiah(takeHomePay)) + '</td>' +
                '<td>' + integrationStatusBadge(row.integrationStatus) + '</td>' +
                '<td class="text-end"><button type="button" class="btn btn-outline-primary btn-xs" data-hcm-profile-detail-idx="' + idx + '">Preview Detail</button></td>' +
            '</tr>';
        }).join("");

        body.querySelectorAll("[data-hcm-profile-detail-idx]").forEach(function (btn) {
            btn.addEventListener("click", function () {
                var idx = parseInt(btn.getAttribute("data-hcm-profile-detail-idx"), 10);
                var row = employeeProfileRows[idx];
                if (row) { openEmployeeProfileDetail(row); }
            });
        });
    }

    function renderViewTabs() {
        var host = document.querySelector("[data-hcm-salary-view-tabs]");
        if (!host) {
            return;
        }

        var tabs = host.querySelectorAll("[data-hcm-tab-view]");
        tabs.forEach(function (btn) {
            var key = String(btn.getAttribute("data-hcm-tab-view") || "components");
            var active = key === selectedViewTabKey;
            btn.classList.toggle("btn-primary", active);
            btn.classList.toggle("btn-outline-primary", !active);
            btn.setAttribute("aria-selected", active ? "true" : "false");
        });

        var views = document.querySelectorAll("[data-hcm-salary-view]");
        views.forEach(function (pane) {
            var key = String(pane.getAttribute("data-hcm-salary-view") || "components");
            pane.classList.toggle("d-none", key !== selectedViewTabKey);
        });
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
            renderCompliance(componentRows);
            if (!body) {
                return;
            }
            var additionSort = categorySortMap("addition");
            var deductionSort = categorySortMap("deduction");

            renderCategoryTabs(componentRows);

            var visibleRows = componentRows.filter(function (row) {
                if (selectedCategoryTabKey === "all") {
                    return true;
                }
                return row.kind === selectedCategoryTabKey;
            });

            visibleRows.sort(function (a, b) {
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
                        ? ' ' + sourceModuleBadge(r)
                        : "";
                    var legal = truncate(r.legalBasis || "—", 56);
                    var del =
                        r.isSystemLocked || r.integrationLocked
                            ? ""
                            : '<a href="#" class="ms-2" data-hcm-salary-component-delete="' +
                              esc(String(r.id)) +
                              '"><i class="ti ti-trash"></i></a>';
                    var edit = (r.isSystemLocked || r.integrationLocked)
                        ? ''
                        : '<a href="#" class="me-2" data-hcm-salary-component-edit="' +
                            esc(String(r.id)) +
                            '" data-bs-toggle="modal" data-bs-target="#arcav_edit_salary_component"><i class="ti ti-edit"></i></a>';
                    var sectionHtml = "";
                    var sectionKey = String(r.kind || "");
                    if (selectedCategoryTabKey === "all" && sectionKey !== currentSection) {
                        currentSection = sectionKey;
                        sectionHtml = '<tr class="table-secondary"><td colspan="9" class="fw-semibold">' +
                            esc(kindLabel(sectionKey)) +
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
                apiRequest("get", "/v1/hcm/salary-components/employee-profiles?page=1&perPage=200", null),
            ])
                .then(function (result) {
                    var p = result[1];
                    var profileResp = result[2];
                    if (!p) {
                        notify("Silakan masuk kembali.", true);
                        return;
                    }
                    if (p.success !== true) {
                        notify(formatApiError(p, 0) || "Gagal memuat data.", true);
                        return;
                    }
                    render(p.data || []);

                    if (profileResp && profileResp.success === true) {
                        var rows = profileResp.data && Array.isArray(profileResp.data.rows)
                            ? profileResp.data.rows
                            : [];
                        var statusSummary = profileResp.meta && profileResp.meta.statusSummary
                            ? profileResp.meta.statusSummary
                            : null;
                        renderEmployeeProfiles(rows, statusSummary);
                    } else {
                        renderEmployeeProfiles([], null);
                    }
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
            var viewTabBtn = e.target.closest("[data-hcm-tab-view]");
            if (viewTabBtn) {
                e.preventDefault();
                selectedViewTabKey = String(viewTabBtn.getAttribute("data-hcm-tab-view") || "components");
                renderViewTabs();
                return;
            }

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
        renderViewTabs();
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
