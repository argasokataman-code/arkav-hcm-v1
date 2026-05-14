(function (window, document) {
    "use strict";

    var salaryPage = 1;
    var salaryPerPage = 20;
    var searchTerm = "";
    var statusFilter = "";
    var searchTimer = null;
    var rowById = {};
    var payrollItemOptionCache = null;
    var payrollItemOptionById = {};
    var currentAssignmentUserId = null;

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

    function requestAuthMe() {
        return apiRequest("get", "/v1/identity/auth/me", null);
    }

    function buildListUrl(page, perPage) {
        var q = "page=" + encodeURIComponent(String(page)) + "&perPage=" + encodeURIComponent(String(perPage));
        // Payroll compensation page must stay on active tenant scope.
        q += "&scope=active_company";
        if (searchTerm) {
            q += "&search=" + encodeURIComponent(searchTerm);
        }
        if (statusFilter) {
            q += "&status=" + encodeURIComponent(statusFilter);
        }
        return "/v1/hcm/employees?" + q;
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

    function formatRupiah(value) {
        var n = isFinite(value) ? Number(value) : 0;
        return "Rp\u00a0" + n.toLocaleString("id-ID", { minimumFractionDigits: 0, maximumFractionDigits: 2 });
    }

    function formatEmployeeCode(value) {
        var n = Number(value);
        if (!Number.isFinite(n) || n <= 0) {
            return "-";
        }
        return "EMP-" + String(Math.trunc(n));
    }

    function formatJoinLabel(iso) {
        if (!iso || iso === "—") {
            return "—";
        }
        var d = new Date(iso + "T12:00:00");
        if (isNaN(d.getTime())) {
            return esc(iso);
        }
        return d.toLocaleDateString("id-ID", { day: "numeric", month: "short", year: "numeric" });
    }

    function statusBadgeClass(st) {
        if (st === "inactive") {
            return "bg-secondary";
        }
        if (st === "probation") {
            return "bg-warning text-dark";
        }
        return "bg-success";
    }

    function normalizeContractType(value) {
        var raw = String(value || "").toLowerCase();
        if (raw === "pkwt") {
            return "contract";
        }
        if (raw === "pkwtt" || raw === "") {
            return "permanent";
        }
        return raw === "contract" ? "contract" : "permanent";
    }

    function renderContractInfo(r) {
        var type = normalizeContractType(r.contractType) === "contract" ? "Contract" : "Permanent";
        var badgeClass = type === "Contract"
            ? (r.pkwtDueThisMonth ? "bg-warning text-dark" : "bg-info text-dark")
            : "bg-light text-dark border";
        var details = [];
        if (r.contractEndDate) {
            details.push("End: " + r.contractEndDate);
        }
        if (r.pkwtDueThisMonth) {
            details.push(formatRupiah(r.estimatedPkwtCompensationThisMonth || 0));
        }
        if (!details.length && type === "Contract") {
            details.push("Lengkapi tanggal kontrak");
        }
        return '<div><span class="badge ' + badgeClass + ' badge-xs">' + esc(type) + '</span>' +
            (details.length ? '<div class="small text-muted mt-1">' + esc(details.join(' • ')) + '</div>' : '') +
            '</div>';
    }

    function employeeDetailsUrl(id) {
        var ret = encodeURIComponent(window.location.pathname + window.location.search);
        return "/employee-details?id=" + encodeURIComponent(String(id)) + "&returnTo=" + ret;
    }

    function assignmentKindBadge(kind) {
        var k = String(kind || "").toLowerCase();
        if (k === "deduction") {
            return '<span class="badge bg-danger-subtle text-danger border border-danger-subtle">Potongan</span>';
        }
        return '<span class="badge bg-success-subtle text-success border border-success-subtle">Tunjangan</span>';
    }

    function assignmentStatusBadge(active) {
        return active
            ? '<span class="badge bg-success-subtle text-success border border-success-subtle">Aktif</span>'
            : '<span class="badge bg-secondary-subtle text-dark border border-secondary-subtle">Nonaktif</span>';
    }

    function renderAssignmentItemOptions(items) {
        var select = document.querySelector("[data-hcm-assignment-item]");
        var amountInput = document.querySelector("[data-hcm-assignment-amount]");
        if (!select) {
            return;
        }
        payrollItemOptionById = {};
        (items || []).forEach(function (item) {
            payrollItemOptionById[String(item.id)] = item;
        });
        var current = select.value;
        select.innerHTML = '<option value="">Pilih payroll item aktif</option>' +
            (items || []).map(function (item) {
                var govBadge = item.linkedToMaster ? " [GOV]" : "";
                var label = (item.name || "Payroll item") + " [" + String(item.kind || "-").toUpperCase() + "]" + govBadge;
                var code = item.code ? " — " + item.code : "";
                return '<option value="' + esc(String(item.id)) + '">' + esc(label + code) + '</option>';
            }).join("");
        if (current) {
            select.value = current;
        }
        if (amountInput) {
            amountInput.readOnly = true;
        }
        updateGovernanceAmountFromSelection();
    }

    function updateGovernanceAmountFromSelection() {
        var select = document.querySelector("[data-hcm-assignment-item]");
        var amountInput = document.querySelector("[data-hcm-assignment-amount]");
        if (!select || !amountInput) {
            return;
        }
        var selectedId = String(select.value || "").trim();
        if (!selectedId) {
            amountInput.value = "";
            return;
        }

        var item = payrollItemOptionById[selectedId] || null;
        var governanceAmount = item && typeof item.allowanceDefaultAmount === "number"
            ? Number(item.allowanceDefaultAmount)
            : NaN;

        if (isNaN(governanceAmount) || governanceAmount < 0) {
            amountInput.value = "0.00";
            return;
        }

        amountInput.value = String(governanceAmount.toFixed(2));
    }

    function loadPayrollItemOptions() {
        if (Array.isArray(payrollItemOptionCache)) {
            renderAssignmentItemOptions(payrollItemOptionCache);
            return Promise.resolve(payrollItemOptionCache);
        }

        return apiRequest("get", "/v1/hcm/payroll-items", null).then(function (resp) {
            if (!resp || resp.success !== true) {
                payrollItemOptionCache = [];
                renderAssignmentItemOptions([]);
                return [];
            }

            var items = Array.isArray(resp.data && resp.data.payrollItems) ? resp.data.payrollItems : [];
            payrollItemOptionCache = items.filter(function (item) {
                return !!item
                    && item.isActive === true
                    && item.sourceModule === "allowance"
                    && item.linkedToMaster === true
                    && item.inAllowanceGovernance === true;
            });
            renderAssignmentItemOptions(payrollItemOptionCache);
            return payrollItemOptionCache;
        }).catch(function () {
            payrollItemOptionCache = [];
            renderAssignmentItemOptions([]);
            return [];
        });
    }

    function renderAssignments(rows) {
        var body = document.querySelector("[data-hcm-assignment-body]");
        if (!body) {
            return;
        }

        if (!rows || !rows.length) {
            body.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-3">Belum ada custom item untuk karyawan ini.</td></tr>';
            return;
        }

        body.innerHTML = rows.map(function (row) {
            var item = row.payrollItem || {};
            return "" +
                "<tr data-hcm-assignment-row=\"" + esc(String(row.id)) + "\">" +
                "<td>" +
                    '<div class="fw-medium">' + esc(item.name || "Payroll item") + (item.linkedToMaster ? ' <span class="badge bg-primary-subtle text-primary border border-primary-subtle ms-1" title="Komponen governance">GOV</span>' : '') + "</div>" +
                    '<div class="small text-muted">' + esc((item.code || "-") + " • " + (item.category || "-")) + "</div>" +
                "</td>" +
                "<td>" + assignmentKindBadge(item.kind) + "</td>" +
                "<td class=\"text-end\"><input type=\"number\" min=\"0.01\" step=\"0.01\" class=\"form-control form-control-sm text-end\" data-hcm-assignment-edit-amount readonly value=\"" + esc(String(Number(row.amount || 0))) + "\"></td>" +
                "<td>" + assignmentStatusBadge(row.isActive === true) + "</td>" +
                "<td>" +
                    '<div class="d-inline-flex align-items-center gap-1">' +
                        '<button type="button" class="btn btn-sm btn-outline-' + (row.isActive === true ? 'secondary' : 'success') + '" data-hcm-assignment-toggle="' + esc(String(row.id)) + '" data-hcm-next-active="' + (row.isActive === true ? '0' : '1') + '">' + (row.isActive === true ? 'Nonaktifkan' : 'Aktifkan') + '</button>' +
                        '<button type="button" class="btn btn-sm btn-outline-danger" data-hcm-assignment-delete="' + esc(String(row.id)) + '">Hapus</button>' +
                    '</div>' +
                "</td>" +
                "</tr>";
        }).join("");
    }

    function loadAssignments(userId) {
        var body = document.querySelector("[data-hcm-assignment-body]");
        if (body) {
            body.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-3">Memuat assignment custom…</td></tr>';
        }
        return apiRequest("get", "/v1/hcm/payroll-item-assignments?userId=" + encodeURIComponent(String(userId)), null)
            .then(function (resp) {
                if (!resp || resp.success !== true) {
                    renderAssignments([]);
                    return [];
                }
                var rows = Array.isArray(resp.data && resp.data.assignments) ? resp.data.assignments : [];
                renderAssignments(rows);
                return rows;
            })
            .catch(function () {
                renderAssignments([]);
                return [];
            });
    }

    function addAssignment() {
        if (!currentAssignmentUserId) {
            notify("Pilih karyawan terlebih dahulu.", true);
            return;
        }

        var itemSelect = document.querySelector("[data-hcm-assignment-item]");
        var amountInput = document.querySelector("[data-hcm-assignment-amount]");
        var addBtn = document.querySelector("[data-hcm-assignment-add]");
        var payrollItemId = itemSelect ? String(itemSelect.value || "").trim() : "";
        var selectedItem = payrollItemOptionById[payrollItemId] || null;
        var amount = selectedItem && typeof selectedItem.allowanceDefaultAmount === "number"
            ? Number(selectedItem.allowanceDefaultAmount)
            : NaN;

        if (!payrollItemId) {
            notify("Pilih payroll item custom terlebih dahulu.", true);
            return;
        }
        if (isNaN(amount) || amount <= 0) {
            notify("Default amount belum diatur di modul Allowance Governance untuk item ini. Atur dulu di sana, baru bisa di-assign.", true);
            return;
        }

        if (addBtn) {
            addBtn.disabled = true;
        }

        apiRequest("post", "/v1/hcm/payroll-item-assignments", {
            userId: currentAssignmentUserId,
            payrollItemId: Number(payrollItemId),
            amount: amount,
            isActive: true,
        }).then(function (resp) {
            if (!resp || resp.success !== true) {
                notify(formatApiError(resp, 0) || "Gagal menambah assignment.", true);
                return;
            }
            updateGovernanceAmountFromSelection();
            notify("Custom item berhasil di-assign.", false);
            return loadAssignments(currentAssignmentUserId);
        }).catch(function (err) {
            notify(formatApiError(err && err.data, err && err.status) || "Gagal menambah assignment.", true);
        }).finally(function () {
            if (addBtn) {
                addBtn.disabled = false;
            }
        });
    }

    function renderPagination(meta) {
        var foot = document.querySelector("[data-hcm-employee-salary-pagination]");
        var info = document.querySelector("[data-hcm-employee-salary-page-info]");
        if (!foot) {
            return;
        }
        var total = meta && typeof meta.total === "number" ? meta.total : 0;
        var page = meta && typeof meta.page === "number" ? meta.page : salaryPage;
        var perPage = meta && typeof meta.perPage === "number" ? meta.perPage : salaryPerPage;
        var totalPages = Math.max(1, Math.ceil(total / perPage) || 1);
        if (totalPages <= 1) {
            foot.style.display = "none";
            return;
        }
        foot.style.display = "";
        if (info) {
            var from = total === 0 ? 0 : (page - 1) * perPage + 1;
            var to = Math.min(page * perPage, total);
            info.textContent = "Menampilkan " + from + "–" + to + " dari " + total;
        }
        var prev = foot.querySelector("[data-hcm-employee-salary-prev]");
        var next = foot.querySelector("[data-hcm-employee-salary-next]");
        if (prev) {
            prev.disabled = page <= 1;
        }
        if (next) {
            next.disabled = page >= totalPages;
        }
    }

    function renderRows(rows) {
        var body = document.querySelector("[data-hcm-employee-salary-body]");
        if (!body) {
            return;
        }
        rowById = {};
        if (!rows || !rows.length) {
            body.innerHTML = '<tr><td colspan="12" class="text-center py-4 text-muted">Tidak ada data.</td></tr>';
            return;
        }
        body.innerHTML = rows
            .map(function (r) {
                rowById[String(r.id)] = r;
                var base = Number(r.baseSalary) || 0;
                var st = r.employmentStatus || "active";
                return (
                    "<tr>" +
                    "<td>" +
                    esc(formatEmployeeCode(r.id)) +
                    "</td><td><div class=\"fw-medium\"><a href=\"" +
                    esc(employeeDetailsUrl(r.id)) +
                    "\">" +
                    esc(r.fullName) +
                    "</a></div></td><td>" +
                    esc(r.email) +
                    "</td><td>" +
                    esc(r.phone || "—") +
                    "</td><td>" +
                    esc(r.designation) +
                    "</td><td>" +
                    esc(r.team) +
                    "</td><td>" +
                    esc(r.departmentName || "—") +
                    "</td><td>" +
                    formatJoinLabel(r.joinDate) +
                    '</td><td class="text-end">' +
                    esc(formatRupiah(base)) +
                    "</td><td>" +
                    renderContractInfo(r) +
                    "</td><td><div class=\"action-icon d-inline-flex\"><a href=\"#\" class=\"me-2\" data-hcm-employee-salary-edit=\"" +
                    esc(String(r.id)) +
                    '"><i class="ti ti-edit"></i></a></div></td></tr>'
                );
            })
            .join("");
    }

    function loadList() {
        var body = document.querySelector("[data-hcm-employee-salary-body]");
        if (body) {
            body.innerHTML = '<tr><td colspan="12" class="text-center text-muted py-4">Memuat data…</td></tr>';
        }
        return apiRequest("get", buildListUrl(salaryPage, salaryPerPage), null).then(function (p) {
            if (p === null) {
                return;
            }
            if (!p || p.success !== true) {
                notify(formatApiError(p, 0) || "Gagal memuat daftar.", true);
                renderRows([]);
                return;
            }
            var meta = p.meta || {};
            var total = typeof meta.total === "number" ? meta.total : 0;
            var perPage = typeof meta.perPage === "number" ? meta.perPage : salaryPerPage;
            var totalPages = Math.max(1, Math.ceil(total / perPage) || 1);
            if (totalPages > 1 && salaryPage > totalPages) {
                salaryPage = totalPages;
                return loadList();
            }
            renderRows(Array.isArray(p.data) ? p.data : []);
            renderPagination(meta);
        });
    }

    function getModal() {
        var el = document.getElementById("arcav_employee_salary_compensation_modal");
        if (!el || !window.bootstrap || !window.bootstrap.Modal) {
            return null;
        }
        return window.bootstrap.Modal.getOrCreateInstance(el);
    }

    function readField(form, name) {
        var el = form.querySelector('[data-hcm-field="' + name + '"]');
        return el ? el.value : "";
    }

    function writeField(form, name, val) {
        var el = form.querySelector('[data-hcm-field="' + name + '"]');
        if (el) {
            el.value = val != null ? String(val) : "";
        }
        // sync a display-only element if present (e.g. contractType-display)
        var disp = form.querySelector('[data-hcm-field="' + name + '-display"]');
        if (disp) {
            var v = val != null ? String(val) : "";
            if (name === "contractType") {
                disp.textContent = normalizeContractType(v) === "contract" ? "Contract" : "Permanent";
            } else {
                disp.textContent = v;
            }
        }
    }

    function openModalEdit(id) {
        var r = rowById[String(id)];
        if (!r) {
            notify("Data baris tidak ditemukan. Muat ulang halaman.", true);
            return;
        }
        var form = document.querySelector("[data-hcm-employee-salary-form]");
        if (!form) {
            return;
        }
        var title = document.querySelector("[data-hcm-employee-salary-modal-title]");
        if (title) {

            var assignmentSelect = form.querySelector("[data-hcm-assignment-item]");
            if (assignmentSelect) {
                assignmentSelect.addEventListener("change", function () {
                    updateGovernanceAmountFromSelection();
                });
            }
            title.textContent = "Edit gaji bulanan";
        }
        writeField(form, "userId", String(r.id));
        writeField(form, "fullNameDisplay", r.fullName || "");
        writeField(form, "baseSalary", String(Math.round((Number(r.baseSalary) || 0) * 100) / 100));
        writeField(form, "contractType", normalizeContractType(r.contractType));
        var m = getModal();
        if (m) {
            m.show();
        }

        currentAssignmentUserId = String(r.uuid || "").trim();
        if (!currentAssignmentUserId) {
            notify("Identifier teknis karyawan tidak tersedia. Assignment payroll item tidak bisa dimuat.", true);
            renderAssignments([]);
            return;
        }

        loadPayrollItemOptions().then(function () {
            return loadAssignments(currentAssignmentUserId);
        });
    }

    function bindForm() {
        var form = document.querySelector("[data-hcm-employee-salary-form]");
        if (!form || form.getAttribute("data-bound")) {
            return;
        }
        form.setAttribute("data-bound", "1");
        form.addEventListener("submit", function (e) {
            e.preventDefault();
            var uid = readField(form, "userId").trim();
            if (!uid) {
                notify("Pilih karyawan terlebih dahulu.", true);
                return;
            }
            var base = parseFloat(readField(form, "baseSalary"));
            if (isNaN(base) || base < 0) {
                notify("Nilai gaji pokok harus angka >= 0.", true);
                return;
            }
            var btn = form.querySelector("[data-hcm-employee-salary-submit]");
            if (btn) {
                btn.disabled = true;
            }
            apiRequest("put", "/v1/hcm/employees/" + encodeURIComponent(uid), {
                baseSalary: base,
            })
                .then(function (p) {
                    if (btn) {
                        btn.disabled = false;
                    }
                    if (p === null) {
                        return;
                    }
                    if (!p || p.success !== true) {
                        notify(formatApiError(p, 0) || "Gagal menyimpan.", true);
                        return;
                    }
                    notify("Data gaji disimpan.", false);
                    var m = getModal();
                    if (m) {
                        m.hide();
                    }
                    return loadList();
                })
                .catch(function (err) {
                    if (btn) {
                        btn.disabled = false;
                    }
                    notify(formatApiError(err && err.data, err && err.status) || "Gagal menyimpan.", true);
                });
        });

        var addBtn = form.querySelector("[data-hcm-assignment-add]");
        if (addBtn) {
            addBtn.addEventListener("click", function () {
                addAssignment();
            });
        }

        form.addEventListener("click", function (e) {
            var saveBtn = e.target.closest("[data-hcm-assignment-save]");
            if (saveBtn) {
                e.preventDefault();
                var id = Number(saveBtn.getAttribute("data-hcm-assignment-save") || 0);
                if (!id) {
                    return;
                }
                var row = form.querySelector('[data-hcm-assignment-row="' + id + '"]');
                var amountInput = row ? row.querySelector("[data-hcm-assignment-edit-amount]") : null;
                var amount = amountInput ? parseFloat(amountInput.value || "") : NaN;
                if (isNaN(amount) || amount <= 0) {
                    notify("Nominal assignment harus lebih besar dari 0.", true);
                    return;
                }
                saveBtn.disabled = true;
                apiRequest("put", "/v1/hcm/payroll-item-assignments/" + encodeURIComponent(String(id)), {
                    amount: amount,
                }).then(function (resp) {
                    if (!resp || resp.success !== true) {
                        notify(formatApiError(resp, 0) || "Gagal memperbarui assignment.", true);
                        return;
                    }
                    notify("Nominal assignment diperbarui.", false);
                    return loadAssignments(currentAssignmentUserId);
                }).catch(function (err) {
                    notify(formatApiError(err && err.data, err && err.status) || "Gagal memperbarui assignment.", true);
                }).finally(function () {
                    saveBtn.disabled = false;
                });
                return;
            }

            var toggleBtn = e.target.closest("[data-hcm-assignment-toggle]");
            if (toggleBtn) {
                e.preventDefault();
                var toggleId = Number(toggleBtn.getAttribute("data-hcm-assignment-toggle") || 0);
                var next = String(toggleBtn.getAttribute("data-hcm-next-active") || "0") === "1";
                if (!toggleId) {
                    return;
                }
                toggleBtn.disabled = true;
                apiRequest("put", "/v1/hcm/payroll-item-assignments/" + encodeURIComponent(String(toggleId)), {
                    isActive: next,
                }).then(function (resp) {
                    if (!resp || resp.success !== true) {
                        notify(formatApiError(resp, 0) || "Gagal mengubah status assignment.", true);
                        return;
                    }
                    notify("Status assignment diperbarui.", false);
                    return loadAssignments(currentAssignmentUserId);
                }).catch(function (err) {
                    notify(formatApiError(err && err.data, err && err.status) || "Gagal mengubah status assignment.", true);
                }).finally(function () {
                    toggleBtn.disabled = false;
                });
                return;
            }

            var delBtn = e.target.closest("[data-hcm-assignment-delete]");
            if (delBtn) {
                e.preventDefault();
                var delId = Number(delBtn.getAttribute("data-hcm-assignment-delete") || 0);
                if (!delId) {
                    return;
                }
                if (!window.ArcavUi || typeof window.ArcavUi.confirmDelete !== "function") {
                    notify("Komponen konfirmasi tidak tersedia. Muat ulang halaman lalu coba lagi.", true);
                    return;
                }
                window.ArcavUi.confirmDelete("Hapus assignment custom ini dari karyawan?", "Hapus assignment")
                    .then(function (ok) {
                        if (!ok) {
                            return;
                        }
                        delBtn.disabled = true;
                        return apiRequest("delete", "/v1/hcm/payroll-item-assignments/" + encodeURIComponent(String(delId)), null)
                            .then(function (resp) {
                                if (!resp || resp.success !== true) {
                                    notify(formatApiError(resp, 0) || "Gagal menghapus assignment.", true);
                                    return;
                                }
                                notify("Assignment custom dihapus.", false);
                                return loadAssignments(currentAssignmentUserId);
                            })
                            .catch(function (err) {
                                notify(formatApiError(err && err.data, err && err.status) || "Gagal menghapus assignment.", true);
                            })
                            .finally(function () {
                                delBtn.disabled = false;
                            });
                    });
            }
        });
    }

    function bindTableClicks() {
        document.addEventListener("click", function (e) {
            var ed = e.target.closest("[data-hcm-employee-salary-edit]");
            if (ed) {
                e.preventDefault();
                var id = ed.getAttribute("data-hcm-employee-salary-edit");
                if (id) {
                    openModalEdit(id);
                }
            }
        });
    }

    function bindToolbar() {
        var search = document.querySelector("[data-hcm-employee-salary-search]");
        if (search && !search.getAttribute("data-bound")) {
            search.setAttribute("data-bound", "1");
            var onSearch = function () {
                var v = search.value.trim();
                if (searchTimer) {
                    window.clearTimeout(searchTimer);
                }
                searchTimer = window.setTimeout(function () {
                    searchTerm = v.slice(0, 100);
                    salaryPage = 1;
                    loadList();
                }, 400);
            };
            search.addEventListener("input", onSearch);
            // also handle X-clear on type="search" (fires "search" event, not "input")
            search.addEventListener("search", function () {
                if (searchTimer) {
                    window.clearTimeout(searchTimer);
                }
                searchTerm = search.value.trim().slice(0, 100);
                salaryPage = 1;
                loadList();
            });
        }
        var st = document.querySelector("[data-hcm-employee-salary-status]");
        if (st && !st.getAttribute("data-bound")) {
            st.setAttribute("data-bound", "1");
            st.addEventListener("change", function () {
                statusFilter = st.value || "";
                salaryPage = 1;
                loadList();
            });
        }
        var foot = document.querySelector("[data-hcm-employee-salary-pagination]");
        if (foot && !foot.getAttribute("data-bound")) {
            foot.setAttribute("data-bound", "1");
            var prev = foot.querySelector("[data-hcm-employee-salary-prev]");
            var next = foot.querySelector("[data-hcm-employee-salary-next]");
            if (prev) {
                prev.addEventListener("click", function () {
                    if (salaryPage > 1) {
                        salaryPage -= 1;
                        loadList();
                    }
                });
            }
            if (next) {
                next.addEventListener("click", function () {
                    salaryPage += 1;
                    loadList();
                });
            }
        }
    }

    function init() {
        if (!document.querySelector("[data-hcm-employee-salary-body]")) {
            return;
        }
        bindForm();
        bindTableClicks();
        bindToolbar();
        requestAuthMe()
            .then(function (me) {
                if (!me || !me.success || !me.data || !me.data.permissions || !me.data.permissions['payroll.view']) {
                    window.location.replace("/employee-dashboard");
                    return null;
                }
                return loadList();
            })
            .catch(function (err) {
                if (err && window.AuthApi && window.AuthApi.handleUnauthorizedFromApi(err.status, err.data)) {
                    return;
                }
                window.location.replace("/employee-dashboard");
            });
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", init);
    } else {
        init();
    }
})(window, document);
