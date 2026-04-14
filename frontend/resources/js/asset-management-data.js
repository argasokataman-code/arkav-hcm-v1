(function (window, document) {
    "use strict";

    var assetState = {
        page: 1,
        perPage: 20,
        q: "",
        status: "",
        categoryId: "",
        lastPage: 1,
        map: {},
    };

    var categoryState = {
        rows: [],
        filtered: [],
        map: {},
        search: "",
    };

    var employeeState = {
        rows: [],
        map: {},
    };

    var searchTimer = null;

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
            .replace(/\"/g, "&quot;")
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

    function formatDate(iso) {
        if (!iso) {
            return "-";
        }
        var d = new Date(iso + "T12:00:00");
        if (isNaN(d.getTime())) {
            return esc(iso);
        }
        return d.toLocaleDateString("id-ID", { day: "2-digit", month: "short", year: "numeric" });
    }

    function formatCurrency(value) {
        var n = isFinite(value) ? Number(value) : 0;
        return "Rp\u00a0" + n.toLocaleString("id-ID", { minimumFractionDigits: 0, maximumFractionDigits: 2 });
    }

    function statusBadge(status) {
        var cls = "bg-secondary";
        var label = status || "unknown";
        if (status === "available") {
            cls = "bg-success";
            label = "Available";
        } else if (status === "assigned") {
            cls = "bg-primary";
            label = "Assigned";
        } else if (status === "maintenance") {
            cls = "bg-warning text-dark";
            label = "Maintenance";
        } else if (status === "retired") {
            cls = "bg-danger";
            label = "Retired";
        }
        return '<span class="badge badge-xs ' + cls + '">' + esc(label) + "</span>";
    }

    function setButtonLoading(form, loading) {
        if (!form) {
            return;
        }
        var btn = form.querySelector("[data-hcm-submit-btn]");
        if (!btn) {
            return;
        }
        if (loading) {
            btn.dataset.origin = btn.textContent;
            btn.disabled = true;
            btn.textContent = "Saving...";
        } else {
            btn.disabled = false;
            if (btn.dataset.origin) {
                btn.textContent = btn.dataset.origin;
            }
        }
    }

    function getModal(id) {
        if (!window.bootstrap || !window.bootstrap.Modal) {
            return null;
        }
        var el = document.getElementById(id);
        if (!el) {
            return null;
        }
        return window.bootstrap.Modal.getOrCreateInstance(el);
    }

    function setFormValue(form, field, value) {
        var el = form ? form.querySelector('[data-hcm-field="' + field + '"]') : null;
        if (!el) {
            return;
        }
        el.value = value == null ? "" : String(value);
    }

    function getFormValue(form, field) {
        var el = form ? form.querySelector('[data-hcm-field="' + field + '"]') : null;
        if (!el) {
            return "";
        }
        return typeof el.value === "string" ? el.value.trim() : el.value;
    }

    function injectCategoryOptions() {
        var html = '<option value="">Select category</option>';
        categoryState.rows.forEach(function (row) {
            html += '<option value="' + esc(row.id) + '">' + esc(row.name) + "</option>";
        });

        document.querySelectorAll('[data-hcm-field="asset_category_id"]').forEach(function (select) {
            var previous = select.value || "";
            select.innerHTML = html;
            if (previous) {
                select.value = previous;
            }
        });

        var filter = document.querySelector("[data-hcm-assets-category]");
        if (filter) {
            var fHtml = '<option value="">All Categories</option>';
            categoryState.rows.forEach(function (row) {
                fHtml += '<option value="' + esc(row.id) + '">' + esc(row.name) + "</option>";
            });
            var selected = filter.value || assetState.categoryId || "";
            filter.innerHTML = fHtml;
            filter.value = selected;
        }
    }

    function loadAssignableEmployees() {
        return apiRequest("get", "/v1/hcm/employees?perPage=100")
            .then(function (payload) {
                if (!payload || !payload.success || !Array.isArray(payload.data)) {
                    return;
                }

                var rows = payload.data
                    .map(function (row) {
                        var profileId = Number(row.employeeProfileId || 0);
                        if (!profileId) {
                            return null;
                        }
                        return {
                            employeeProfileId: profileId,
                            fullName: row.fullName || row.email || ("Employee #" + profileId),
                            employmentStatus: row.employmentStatus || "active",
                        };
                    })
                    .filter(function (row) {
                        return !!row;
                    });

                employeeState.rows = rows;
                employeeState.map = {};
                rows.forEach(function (row) {
                    employeeState.map[String(row.employeeProfileId)] = row;
                });

                var html = '<option value="">Select employee</option>';
                rows.forEach(function (row) {
                    html += '<option value="' + esc(row.employeeProfileId) + '">' + esc(row.fullName) + "</option>";
                });

                var select = document.querySelector('[data-hcm-asset-assign-form] [data-hcm-field="employee_id"]');
                if (select) {
                    select.innerHTML = html;
                }
            })
            .catch(function (err) {
                notify(formatApiError(err && err.data, err && err.status), true);
            });
    }

    function fetchCategories() {
        return apiRequest("get", "/v1/hcm/asset-categories")
            .then(function (payload) {
                if (!payload || !payload.success || !Array.isArray(payload.data)) {
                    return;
                }
                categoryState.rows = payload.data.slice();
                categoryState.map = {};
                payload.data.forEach(function (row) {
                    categoryState.map[String(row.id)] = row;
                });
                injectCategoryOptions();
                renderCategoryRows();
            })
            .catch(function (err) {
                notify(formatApiError(err && err.data, err && err.status), true);
            });
    }

    function renderAssetsLoading(text) {
        var body = document.querySelector("[data-hcm-assets-body]");
        if (!body) {
            return;
        }
        body.innerHTML = '<tr><td colspan="9" class="text-center text-muted py-4">' + esc(text || "Loading assets...") + "</td></tr>";
    }

    function renderAssetPagination(meta) {
        var foot = document.querySelector("[data-hcm-assets-pagination]");
        var info = document.querySelector("[data-hcm-assets-page-info]");
        if (!foot || !info) {
            return;
        }

        var page = Number(meta && meta.currentPage ? meta.currentPage : assetState.page) || 1;
        var lastPage = Number(meta && meta.lastPage ? meta.lastPage : 1) || 1;
        var total = Number(meta && meta.total ? meta.total : 0) || 0;

        assetState.page = page;
        assetState.lastPage = lastPage;

        if (lastPage <= 1) {
            foot.style.display = "none";
            return;
        }

        foot.style.display = "flex";
        var start = total === 0 ? 0 : ((page - 1) * assetState.perPage) + 1;
        var end = Math.min(total, page * assetState.perPage);
        info.textContent = "Showing " + start + " - " + end + " of " + total + " assets";

        var prev = foot.querySelector("[data-hcm-assets-prev]");
        var next = foot.querySelector("[data-hcm-assets-next]");
        if (prev) {
            prev.disabled = page <= 1;
        }
        if (next) {
            next.disabled = page >= lastPage;
        }
    }

    function renderAssetsRows(rows) {
        var body = document.querySelector("[data-hcm-assets-body]");
        if (!body) {
            return;
        }

        if (!rows.length) {
            body.innerHTML = '<tr><td colspan="9" class="text-center text-muted py-4">No asset data found.</td></tr>';
            return;
        }

        var html = "";
        rows.forEach(function (row) {
            var assignmentName = row.currentAssignment && row.currentAssignment.employeeName ? row.currentAssignment.employeeName : "-";
            var lifecycleAction = row.status === "assigned"
                ? '<a href="#" class="me-2" data-hcm-asset-return="' + esc(row.id) + '"><i class="ti ti-logout"></i></a>'
                : '<a href="#" class="me-2" data-hcm-asset-assign="' + esc(row.id) + '"><i class="ti ti-user-plus"></i></a>';
            html += "<tr>" +
                "<td><span class=\"fw-medium\">" + esc(row.assetCode || "-") + "</span></td>" +
                "<td><div class=\"fw-medium\">" + esc(row.name || "-") + "</div><div class=\"small text-muted\">" + esc(row.serialNumber || "-") + "</div></td>" +
                "<td>" + esc(row.category && row.category.name ? row.category.name : "-") + "</td>" +
                "<td>" + esc(assignmentName) + "</td>" +
                "<td>" + formatDate(row.purchaseDate) + "</td>" +
                "<td class=\"text-nowrap\">" + formatCurrency(row.purchasePrice || 0) + "</td>" +
                "<td>" + formatDate(row.warrantyEndDate) + "</td>" +
                "<td>" + statusBadge(row.status) + "</td>" +
                '<td><div class="action-icon d-inline-flex">' + lifecycleAction + '<a href="#" class="me-2" data-hcm-asset-edit="' + esc(row.id) + '"><i class="ti ti-edit"></i></a><a href="#" data-hcm-asset-delete="' + esc(row.id) + '"><i class="ti ti-trash"></i></a></div></td>' +
                "</tr>";
        });

        body.innerHTML = html;
    }

    function openAssignModal(assetId) {
        var row = assetState.map[String(assetId)];
        var form = document.querySelector('[data-hcm-asset-assign-form]');
        if (!row || !form) {
            return;
        }

        setFormValue(form, "asset_id", row.id);
        setFormValue(form, "asset_name", row.name || row.assetCode || "");
        setFormValue(form, "assigned_date", new Date().toISOString().slice(0, 10));
        setFormValue(form, "condition_at_assign", row.condition || "good");
        setFormValue(form, "notes", "");
        setFormValue(form, "employee_id", "");

        var modal = getModal("asset_assign_modal");
        if (modal) {
            modal.show();
        }
    }

    function openReturnModal(assetId) {
        var row = assetState.map[String(assetId)];
        var form = document.querySelector('[data-hcm-asset-return-form]');
        if (!row || !form) {
            return;
        }

        setFormValue(form, "asset_id", row.id);
        setFormValue(form, "asset_name", row.name || row.assetCode || "");
        setFormValue(form, "returned_date", new Date().toISOString().slice(0, 10));
        setFormValue(form, "condition_at_return", row.condition || "good");
        setFormValue(form, "notes", "");

        var modal = getModal("asset_return_modal");
        if (modal) {
            modal.show();
        }
    }

    function fetchAssets() {
        var params = [
            "page=" + encodeURIComponent(String(assetState.page)),
            "perPage=" + encodeURIComponent(String(assetState.perPage)),
        ];

        if (assetState.q) {
            params.push("q=" + encodeURIComponent(assetState.q));
        }
        if (assetState.status) {
            params.push("status=" + encodeURIComponent(assetState.status));
        }
        if (assetState.categoryId) {
            params.push("categoryId=" + encodeURIComponent(assetState.categoryId));
        }

        renderAssetsLoading("Loading assets...");

        return apiRequest("get", "/v1/hcm/assets?" + params.join("&"))
            .then(function (payload) {
                if (!payload || !payload.success) {
                    return;
                }

                var rows = Array.isArray(payload.data) ? payload.data : [];
                assetState.map = {};
                rows.forEach(function (row) {
                    assetState.map[String(row.id)] = row;
                });

                renderAssetsRows(rows);
                renderAssetPagination(payload.meta || {});
            })
            .catch(function (err) {
                renderAssetsLoading("Failed to load assets.");
                notify(formatApiError(err && err.data, err && err.status), true);
            });
    }

    function extractAssetPayload(form) {
        return {
            asset_category_id: Number(getFormValue(form, "asset_category_id")) || null,
            name: getFormValue(form, "name"),
            brand: getFormValue(form, "brand") || null,
            model: getFormValue(form, "model") || null,
            serial_number: getFormValue(form, "serial_number") || null,
            purchase_date: getFormValue(form, "purchase_date"),
            purchase_price: Number(getFormValue(form, "purchase_price") || 0),
            condition: getFormValue(form, "condition") || "good",
            status: getFormValue(form, "status") || "available",
            location: getFormValue(form, "location") || null,
            notes: getFormValue(form, "notes") || null,
            warranty_start_date: getFormValue(form, "warranty_start_date") || null,
            warranty_end_date: getFormValue(form, "warranty_end_date") || null,
        };
    }

    function resetAssetAddForm() {
        var form = document.querySelector('[data-hcm-asset-form="add"]');
        if (!form) {
            return;
        }
        form.reset();
        setFormValue(form, "condition", "good");
        setFormValue(form, "status", "available");
        if (categoryState.rows.length > 0) {
            setFormValue(form, "asset_category_id", categoryState.rows[0].id);
        }
    }

    function openEditAssetModal(id) {
        var row = assetState.map[String(id)];
        var form = document.querySelector('[data-hcm-asset-form="edit"]');
        if (!row || !form) {
            return;
        }

        setFormValue(form, "id", row.id);
        setFormValue(form, "asset_category_id", row.assetCategoryId);
        setFormValue(form, "name", row.name || "");
        setFormValue(form, "brand", row.brand || "");
        setFormValue(form, "model", row.model || "");
        setFormValue(form, "serial_number", row.serialNumber || "");
        setFormValue(form, "purchase_date", row.purchaseDate || "");
        setFormValue(form, "purchase_price", row.purchasePrice || 0);
        setFormValue(form, "condition", row.condition || "good");
        setFormValue(form, "status", row.status || "available");
        setFormValue(form, "location", row.location || "");
        setFormValue(form, "warranty_start_date", row.warrantyStartDate || "");
        setFormValue(form, "warranty_end_date", row.warrantyEndDate || "");
        setFormValue(form, "notes", row.notes || "");

        var modal = getModal("asset_edit_modal");
        if (modal) {
            modal.show();
        }
    }

    function bindAssetEvents() {
        var searchInput = document.querySelector("[data-hcm-assets-search]");
        if (searchInput) {
            searchInput.addEventListener("input", function () {
                window.clearTimeout(searchTimer);
                searchTimer = window.setTimeout(function () {
                    assetState.q = searchInput.value.trim();
                    assetState.page = 1;
                    fetchAssets();
                }, 300);
            });
        }

        var statusFilter = document.querySelector("[data-hcm-assets-status]");
        if (statusFilter) {
            statusFilter.addEventListener("change", function () {
                assetState.status = statusFilter.value || "";
                assetState.page = 1;
                fetchAssets();
            });
        }

        var categoryFilter = document.querySelector("[data-hcm-assets-category]");
        if (categoryFilter) {
            categoryFilter.addEventListener("change", function () {
                assetState.categoryId = categoryFilter.value || "";
                assetState.page = 1;
                fetchAssets();
            });
        }

        var pager = document.querySelector("[data-hcm-assets-pagination]");
        if (pager) {
            var prev = pager.querySelector("[data-hcm-assets-prev]");
            var next = pager.querySelector("[data-hcm-assets-next]");
            if (prev) {
                prev.addEventListener("click", function () {
                    if (assetState.page <= 1) {
                        return;
                    }
                    assetState.page -= 1;
                    fetchAssets();
                });
            }
            if (next) {
                next.addEventListener("click", function () {
                    if (assetState.page >= assetState.lastPage) {
                        return;
                    }
                    assetState.page += 1;
                    fetchAssets();
                });
            }
        }

        var addForm = document.querySelector('[data-hcm-asset-form="add"]');
        if (addForm) {
            addForm.addEventListener("submit", function (e) {
                e.preventDefault();
                var payload = extractAssetPayload(addForm);
                setButtonLoading(addForm, true);
                apiRequest("post", "/v1/hcm/assets", payload)
                    .then(function (resp) {
                        if (!resp || !resp.success) {
                            return;
                        }
                        notify("Asset berhasil ditambahkan.", false);
                        var modal = getModal("asset_add_modal");
                        if (modal) {
                            modal.hide();
                        }
                        resetAssetAddForm();
                        fetchAssets();
                    })
                    .catch(function (err) {
                        notify(formatApiError(err && err.data, err && err.status), true);
                    })
                    .finally(function () {
                        setButtonLoading(addForm, false);
                    });
            });
        }

        var editForm = document.querySelector('[data-hcm-asset-form="edit"]');
        if (editForm) {
            editForm.addEventListener("submit", function (e) {
                e.preventDefault();
                var id = getFormValue(editForm, "id");
                if (!id) {
                    return;
                }

                var payload = extractAssetPayload(editForm);
                setButtonLoading(editForm, true);
                apiRequest("put", "/v1/hcm/assets/" + encodeURIComponent(String(id)), payload)
                    .then(function (resp) {
                        if (!resp || !resp.success) {
                            return;
                        }
                        notify("Asset berhasil diperbarui.", false);
                        var modal = getModal("asset_edit_modal");
                        if (modal) {
                            modal.hide();
                        }
                        fetchAssets();
                    })
                    .catch(function (err) {
                        notify(formatApiError(err && err.data, err && err.status), true);
                    })
                    .finally(function () {
                        setButtonLoading(editForm, false);
                    });
            });
        }

        var assignForm = document.querySelector('[data-hcm-asset-assign-form]');
        if (assignForm) {
            assignForm.addEventListener("submit", function (e) {
                e.preventDefault();
                var assetId = getFormValue(assignForm, "asset_id");
                if (!assetId) {
                    return;
                }

                var payload = {
                    employee_id: Number(getFormValue(assignForm, "employee_id") || 0),
                    assigned_date: getFormValue(assignForm, "assigned_date") || null,
                    condition_at_assign: getFormValue(assignForm, "condition_at_assign") || null,
                    notes: getFormValue(assignForm, "notes") || null,
                };

                if (!payload.employee_id) {
                    notify("Pilih employee terlebih dulu.", true);
                    return;
                }

                setButtonLoading(assignForm, true);
                apiRequest("post", "/v1/hcm/assets/" + encodeURIComponent(String(assetId)) + "/assign", payload)
                    .then(function (resp) {
                        if (!resp || !resp.success) {
                            return;
                        }
                        notify("Asset berhasil di-assign.", false);
                        var modal = getModal("asset_assign_modal");
                        if (modal) {
                            modal.hide();
                        }
                        fetchAssets();
                    })
                    .catch(function (err) {
                        notify(formatApiError(err && err.data, err && err.status), true);
                    })
                    .finally(function () {
                        setButtonLoading(assignForm, false);
                    });
            });
        }

        var returnForm = document.querySelector('[data-hcm-asset-return-form]');
        if (returnForm) {
            returnForm.addEventListener("submit", function (e) {
                e.preventDefault();
                var assetId = getFormValue(returnForm, "asset_id");
                if (!assetId) {
                    return;
                }

                var payload = {
                    returned_date: getFormValue(returnForm, "returned_date") || null,
                    condition_at_return: getFormValue(returnForm, "condition_at_return") || null,
                    notes: getFormValue(returnForm, "notes") || null,
                };

                setButtonLoading(returnForm, true);
                apiRequest("post", "/v1/hcm/assets/" + encodeURIComponent(String(assetId)) + "/return", payload)
                    .then(function (resp) {
                        if (!resp || !resp.success) {
                            return;
                        }
                        notify("Asset berhasil di-return.", false);
                        var modal = getModal("asset_return_modal");
                        if (modal) {
                            modal.hide();
                        }
                        fetchAssets();
                    })
                    .catch(function (err) {
                        notify(formatApiError(err && err.data, err && err.status), true);
                    })
                    .finally(function () {
                        setButtonLoading(returnForm, false);
                    });
            });
        }

        document.addEventListener("click", function (e) {
            var editBtn = e.target.closest("[data-hcm-asset-edit]");
            if (editBtn) {
                e.preventDefault();
                openEditAssetModal(editBtn.getAttribute("data-hcm-asset-edit"));
                return;
            }

            var assignBtn = e.target.closest("[data-hcm-asset-assign]");
            if (assignBtn) {
                e.preventDefault();
                openAssignModal(assignBtn.getAttribute("data-hcm-asset-assign"));
                return;
            }

            var returnBtn = e.target.closest("[data-hcm-asset-return]");
            if (returnBtn) {
                e.preventDefault();
                openReturnModal(returnBtn.getAttribute("data-hcm-asset-return"));
                return;
            }

            var deleteBtn = e.target.closest("[data-hcm-asset-delete]");
            if (!deleteBtn) {
                return;
            }

            e.preventDefault();
            var id = deleteBtn.getAttribute("data-hcm-asset-delete");
            if (!id) {
                return;
            }

            var asset = assetState.map[String(id)] || {};
            if (!window.ArcavUi || typeof window.ArcavUi.confirmDelete !== "function") {
                notify("Konfirmasi hapus tidak tersedia.", true);
                return;
            }

            window.ArcavUi.confirmDelete("Hapus asset \"" + (asset.name || id) + "\"?", "Hapus asset")
                .then(function (ok) {
                    if (!ok) {
                        return;
                    }
                    return apiRequest("delete", "/v1/hcm/assets/" + encodeURIComponent(String(id)))
                        .then(function (resp) {
                            if (!resp || !resp.success) {
                                return;
                            }
                            notify("Asset berhasil dihapus.", false);
                            fetchAssets();
                        })
                        .catch(function (err) {
                            notify(formatApiError(err && err.data, err && err.status), true);
                        });
                });
        });

        var addModalEl = document.getElementById("asset_add_modal");
        if (addModalEl) {
            addModalEl.addEventListener("show.bs.modal", function () {
                resetAssetAddForm();
            });
        }
    }

    function renderCategoryRows() {
        var body = document.querySelector("[data-hcm-asset-categories-body]");
        if (!body) {
            return;
        }

        var rows = categoryState.rows;
        var keyword = categoryState.search;
        if (keyword) {
            var lower = keyword.toLowerCase();
            rows = rows.filter(function (row) {
                var source = [row.code, row.name, row.description].join(" ").toLowerCase();
                return source.indexOf(lower) !== -1;
            });
        }

        categoryState.filtered = rows;

        if (!rows.length) {
            body.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-4">No category data found.</td></tr>';
            return;
        }

        var html = "";
        rows.forEach(function (row) {
            var status = row.isActive
                ? '<span class="badge badge-xs bg-success">Active</span>'
                : '<span class="badge badge-xs bg-secondary">Inactive</span>';
            html += "<tr>" +
                "<td>" + esc(row.code || "-") + "</td>" +
                "<td><div class=\"fw-medium\">" + esc(row.name || "-") + "</div><div class=\"small text-muted\">" + esc(row.description || "-") + "</div></td>" +
                "<td>" + esc(row.assetsCount || 0) + "</td>" +
                "<td>" + status + "</td>" +
                '<td><div class="action-icon d-inline-flex"><a href="#" class="me-2" data-hcm-asset-category-edit="' + esc(row.id) + '"><i class="ti ti-edit"></i></a><a href="#" data-hcm-asset-category-delete="' + esc(row.id) + '"><i class="ti ti-trash"></i></a></div></td>' +
                "</tr>";
        });

        body.innerHTML = html;
    }

    function buildCategoryPayload(form) {
        return {
            code: getFormValue(form, "code") || null,
            name: getFormValue(form, "name"),
            description: getFormValue(form, "description") || null,
            is_active: getFormValue(form, "is_active") === "1",
        };
    }

    function bindCategoryEvents() {
        var search = document.querySelector("[data-hcm-asset-category-search]");
        if (search) {
            search.addEventListener("input", function () {
                categoryState.search = search.value.trim();
                renderCategoryRows();
            });
        }

        var addForm = document.querySelector('[data-hcm-asset-category-form="add"]');
        if (addForm) {
            addForm.addEventListener("submit", function (e) {
                e.preventDefault();
                var payload = buildCategoryPayload(addForm);
                setButtonLoading(addForm, true);
                apiRequest("post", "/v1/hcm/asset-categories", payload)
                    .then(function (resp) {
                        if (!resp || !resp.success) {
                            return;
                        }
                        notify("Kategori berhasil ditambahkan.", false);
                        addForm.reset();
                        setFormValue(addForm, "is_active", "1");
                        var modal = getModal("asset_category_add_modal");
                        if (modal) {
                            modal.hide();
                        }
                        return fetchCategories();
                    })
                    .catch(function (err) {
                        notify(formatApiError(err && err.data, err && err.status), true);
                    })
                    .finally(function () {
                        setButtonLoading(addForm, false);
                    });
            });
        }

        var editForm = document.querySelector('[data-hcm-asset-category-form="edit"]');
        if (editForm) {
            editForm.addEventListener("submit", function (e) {
                e.preventDefault();
                var id = getFormValue(editForm, "id");
                if (!id) {
                    return;
                }

                var payload = buildCategoryPayload(editForm);
                setButtonLoading(editForm, true);
                apiRequest("put", "/v1/hcm/asset-categories/" + encodeURIComponent(String(id)), payload)
                    .then(function (resp) {
                        if (!resp || !resp.success) {
                            return;
                        }
                        notify("Kategori berhasil diperbarui.", false);
                        var modal = getModal("asset_category_edit_modal");
                        if (modal) {
                            modal.hide();
                        }
                        return fetchCategories();
                    })
                    .catch(function (err) {
                        notify(formatApiError(err && err.data, err && err.status), true);
                    })
                    .finally(function () {
                        setButtonLoading(editForm, false);
                    });
            });
        }

        document.addEventListener("click", function (e) {
            var editBtn = e.target.closest("[data-hcm-asset-category-edit]");
            if (editBtn) {
                e.preventDefault();
                var id = editBtn.getAttribute("data-hcm-asset-category-edit");
                var row = categoryState.map[String(id)];
                if (!row || !editForm) {
                    return;
                }
                setFormValue(editForm, "id", row.id);
                setFormValue(editForm, "code", row.code || "");
                setFormValue(editForm, "name", row.name || "");
                setFormValue(editForm, "description", row.description || "");
                setFormValue(editForm, "is_active", row.isActive ? "1" : "0");
                var editModal = getModal("asset_category_edit_modal");
                if (editModal) {
                    editModal.show();
                }
                return;
            }

            var deleteBtn = e.target.closest("[data-hcm-asset-category-delete]");
            if (!deleteBtn) {
                return;
            }
            e.preventDefault();
            var did = deleteBtn.getAttribute("data-hcm-asset-category-delete");
            if (!did) {
                return;
            }

            var category = categoryState.map[String(did)] || {};
            if (!window.ArcavUi || typeof window.ArcavUi.confirmDelete !== "function") {
                notify("Konfirmasi hapus tidak tersedia.", true);
                return;
            }

            window.ArcavUi.confirmDelete("Hapus kategori \"" + (category.name || did) + "\"?", "Hapus kategori")
                .then(function (ok) {
                    if (!ok) {
                        return;
                    }

                    return apiRequest("delete", "/v1/hcm/asset-categories/" + encodeURIComponent(String(did)))
                        .then(function (resp) {
                            if (!resp || !resp.success) {
                                return;
                            }
                            notify("Kategori berhasil dihapus.", false);
                            return fetchCategories();
                        })
                        .catch(function (err) {
                            notify(formatApiError(err && err.data, err && err.status), true);
                        });
                });
        });
    }

    function initAssetsPage() {
        bindAssetEvents();
        Promise.all([fetchCategories(), loadAssignableEmployees()]).finally(function () {
            fetchAssets();
        });
    }

    function initCategoriesPage() {
        bindCategoryEvents();
        fetchCategories();
    }

    document.addEventListener("DOMContentLoaded", function () {
        var hasAssetsPage = !!document.querySelector("[data-hcm-assets-body]");
        var hasCategoriesPage = !!document.querySelector("[data-hcm-asset-categories-body]");

        if (hasAssetsPage) {
            initAssetsPage();
        }
        if (hasCategoriesPage) {
            initCategoriesPage();
        }
    });
})(window, document);
