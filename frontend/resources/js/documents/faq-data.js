/**
 * faq-data.js
 * Handles server-backed FAQ workspace behavior for /faq.
 * API: GET/POST/PUT/DELETE /v1/hcm/faqs + POST /v1/hcm/faqs/bulk-delete
 */
(function () {
    "use strict";

    var state = {
        entries: [],
        searchQuery: "",
        category: "all",
        sortOrder: "recent",
        editId: null,
        deleteQueue: [],
        loading: false,
        selected: {}
    };

    function faqTenantHeaders() {
        var context = window.AuthApi && typeof window.AuthApi.getTenantContext === "function"
            ? window.AuthApi.getTenantContext()
            : {};

        var headers = {
            Accept: "application/json",
            "Content-Type": "application/json"
        };
        var token = (window.AuthApi && window.AuthApi.getToken()) || localStorage.getItem('arcav_access_token');
        if (token) { headers['Authorization'] = 'Bearer ' + token; }

        if (context.companyId) {
            headers["X-Company-Id"] = String(context.companyId);
        }
        if (context.companyCode) {
            headers["X-Company-Code"] = String(context.companyCode);
        }
        if (context.companyUuid) {
            headers["X-Company-Uuid"] = String(context.companyUuid);
        }

        return headers;
    }

    function faqApiRequest(method, url, body) {
        var options = {
            method: method,
            headers: faqTenantHeaders(),
            credentials: "same-origin"
        };

        if (body !== undefined) {
            options.body = JSON.stringify(body);
        }

        return fetch(url, options).then(function (response) {
            return response.json().catch(function () {
                return { success: false, error: { message: "Invalid API response." } };
            }).then(function (payload) {
                if (!response.ok || !payload.success) {
                    var message = payload && payload.error && payload.error.message ? payload.error.message : "Request failed.";
                    throw new Error(message);
                }

                return payload;
            });
        });
    }

    function faqById(id) {
        for (var index = 0; index < state.entries.length; index += 1) {
            if (state.entries[index].id === id) {
                return state.entries[index];
            }
        }

        return null;
    }

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/\"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    function formatDate(isoDate) {
        if (!isoDate) {
            return "-";
        }

        return new Date(isoDate).toLocaleDateString("en-GB", {
            day: "2-digit",
            month: "short",
            year: "numeric"
        });
    }

    function shortText(value, maxLength) {
        var text = String(value || "").trim();
        if (text.length <= maxLength) {
            return text;
        }

        return text.slice(0, maxLength - 1).trim() + "...";
    }

    function syncSelectionWithEntries() {
        var current = {};
        state.entries.forEach(function (entry) {
            current[entry.id] = true;
        });

        Object.keys(state.selected).forEach(function (id) {
            if (!current[Number(id)]) {
                delete state.selected[id];
            }
        });
    }

    function loadEntries() {
        state.loading = true;
        return faqApiRequest("GET", "/v1/hcm/faqs").then(function (result) {
            state.entries = Array.isArray(result.data) ? result.data : [];
            syncSelectionWithEntries();
            state.loading = false;
            return state.entries;
        }).catch(function (error) {
            state.entries = [];
            state.loading = false;
            throw error;
        });
    }

    function uniqueCategories() {
        var categories = {};
        state.entries.forEach(function (entry) {
            categories[entry.category] = true;
        });
        return Object.keys(categories).sort(function (left, right) {
            return left.localeCompare(right);
        });
    }

    function filteredEntries() {
        var query = state.searchQuery.toLowerCase();
        return state.entries.filter(function (entry) {
            if (state.category !== "all" && entry.category !== state.category) {
                return false;
            }
            if (!query) {
                return true;
            }
            return entry.question.toLowerCase().indexOf(query) !== -1 || entry.answer.toLowerCase().indexOf(query) !== -1;
        }).sort(function (left, right) {
            if (state.sortOrder === "az") {
                return left.question.localeCompare(right.question);
            }
            if (state.sortOrder === "category") {
                if (left.category === right.category) {
                    return left.question.localeCompare(right.question);
                }
                return left.category.localeCompare(right.category);
            }
            return new Date(right.updatedAt).getTime() - new Date(left.updatedAt).getTime();
        });
    }

    function selectedIds() {
        return Object.keys(state.selected).map(function (value) {
            return Number(value);
        });
    }

    function updateStats(entries) {
        var totalEl = document.getElementById("faq-total-count");
        var categoryCountEl = document.getElementById("faq-category-count");
        var lastUpdatedEl = document.getElementById("faq-last-updated");
        var latest = null;

        state.entries.forEach(function (entry) {
            if (!latest || new Date(entry.updatedAt).getTime() > new Date(latest.updatedAt).getTime()) {
                latest = entry;
            }
        });

        if (totalEl) {
            totalEl.textContent = String(entries.length);
        }
        if (categoryCountEl) {
            categoryCountEl.textContent = String(uniqueCategories().length);
        }
        if (lastUpdatedEl) {
            lastUpdatedEl.textContent = latest ? formatDate(latest.updatedAt) : "-";
        }
    }

    function updateCategoryFilter() {
        var filter = document.getElementById("faq-category-filter");
        var current = state.category;
        if (!filter) {
            return;
        }

        filter.innerHTML = '<option value="all">All Categories</option>' + uniqueCategories().map(function (category) {
            return '<option value="' + escapeHtml(category) + '">' + escapeHtml(category) + '</option>';
        }).join("");

        filter.value = current;
        if (filter.value !== current) {
            state.category = "all";
            filter.value = "all";
        }
    }

    function categoryBadge(category) {
        return '<span class="badge bg-soft-primary text-primary border border-primary-subtle">' + escapeHtml(category) + '</span>';
    }

    function renderSelectionToolbar() {
        var toolbar = document.getElementById("faq-selection-toolbar");
        var countEl = document.getElementById("faq-selected-count");
        var deleteBtn = document.getElementById("faq-delete-selected");
        var count = selectedIds().length;

        if (!toolbar || !countEl || !deleteBtn) {
            return;
        }

        countEl.textContent = count + (count === 1 ? " item selected" : " items selected");
        toolbar.classList.toggle("d-none", count === 0);
        deleteBtn.disabled = count === 0;
    }

    function rowMarkup(entry) {
        var checked = state.selected[entry.id] ? "checked" : "";
        return '<tr data-faq-id="' + entry.id + '">' +
            '<td><div class="form-check form-check-md"><input class="form-check-input faq-row-checkbox" type="checkbox" data-id="' + entry.id + '" ' + checked + '></div></td>' +
            '<td><div class="fw-semibold text-dark">' + escapeHtml(entry.question) + '</div></td>' +
            '<td class="text-muted" style="max-width: 420px;">' + escapeHtml(shortText(entry.answer, 130)) + '</td>' +
            '<td>' + categoryBadge(entry.category) + '</td>' +
            '<td class="text-muted">' + formatDate(entry.updatedAt) + '</td>' +
            '<td class="text-end"><div class="action-icon d-inline-flex align-items-center">' +
            '<a href="javascript:void(0);" class="me-2 faq-edit-trigger" data-id="' + entry.id + '" data-bs-toggle="modal" data-bs-target="#edit_faq"><i class="ti ti-edit"></i></a>' +
            '<a href="javascript:void(0);" class="faq-delete-trigger" data-id="' + entry.id + '" data-bs-toggle="modal" data-bs-target="#delete_modal"><i class="ti ti-trash"></i></a>' +
            '</div></td>' +
            '</tr>';
    }

    function renderTable() {
        var tbody = document.getElementById("faq-table-body");
        var entries = filteredEntries();
        var selectAll = document.getElementById("select-all");

        if (!tbody) {
            return;
        }

        updateCategoryFilter();
        updateStats(entries);
        renderSelectionToolbar();

        if (state.loading) {
            tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-5"><i class="ti ti-loader-2 fs-24 d-block mb-2"></i>Loading FAQ entries...</td></tr>';
            if (selectAll) {
                selectAll.checked = false;
                selectAll.indeterminate = false;
            }
            return;
        }

        if (!entries.length) {
            tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-5"><i class="ti ti-help-octagon fs-24 d-block mb-2"></i>No FAQ matched your current filters.</td></tr>';
            if (selectAll) {
                selectAll.checked = false;
                selectAll.indeterminate = false;
            }
            return;
        }

        tbody.innerHTML = entries.map(rowMarkup).join("");

        if (selectAll) {
            var checkedCount = entries.filter(function (entry) {
                return !!state.selected[entry.id];
            }).length;
            selectAll.checked = checkedCount === entries.length;
            selectAll.indeterminate = checkedCount > 0 && checkedCount < entries.length;
        }
    }

    function setFormError(elementId, message) {
        var el = document.getElementById(elementId);
        if (!el) {
            return;
        }
        if (message) {
            el.textContent = message;
            el.classList.remove("d-none");
            return;
        }
        el.textContent = "";
        el.classList.add("d-none");
    }

    function hideModal(modalId) {
        var element = document.getElementById(modalId);
        var modal = null;

        if (!element || !window.bootstrap || !window.bootstrap.Modal) {
            return;
        }

        if (typeof window.bootstrap.Modal.getInstance === "function") {
            modal = window.bootstrap.Modal.getInstance(element);
        }
        if (!modal && typeof window.bootstrap.Modal.getOrCreateInstance === "function") {
            modal = window.bootstrap.Modal.getOrCreateInstance(element);
        }
        if (modal && typeof modal.hide === "function") {
            modal.hide();
        }
    }

    function resetAddForm() {
        var form = document.getElementById("faq-add-form");
        if (form) {
            form.reset();
        }
        setFormError("faq-add-error", "");
    }

    function fillEditForm(id) {
        var faq = faqById(id);
        if (!faq) {
            return;
        }

        state.editId = id;
        document.getElementById("faq-edit-id").value = String(id);
        document.getElementById("faq-edit-category").value = faq.category;
        document.getElementById("faq-edit-question").value = faq.question;
        document.getElementById("faq-edit-answer").value = faq.answer;
        setFormError("faq-edit-error", "");
    }

    function queueDelete(ids) {
        var message = document.getElementById("faq-delete-message");
        state.deleteQueue = ids.slice();
        if (!message) {
            return;
        }
        if (ids.length === 1) {
            var faq = faqById(ids[0]);
            message.textContent = faq ? 'Delete FAQ "' + faq.question + '" permanently?' : "Delete the selected FAQ permanently?";
            return;
        }
        message.textContent = "Delete " + ids.length + " selected FAQ entries permanently?";
    }

    function readAddPayload() {
        return {
            category: (document.getElementById("faq-add-category").value || "").trim(),
            question: (document.getElementById("faq-add-question").value || "").trim(),
            answer: (document.getElementById("faq-add-answer").value || "").trim()
        };
    }

    function readEditPayload() {
        return {
            category: (document.getElementById("faq-edit-category").value || "").trim(),
            question: (document.getElementById("faq-edit-question").value || "").trim(),
            answer: (document.getElementById("faq-edit-answer").value || "").trim()
        };
    }

    function validatePayload(payload) {
        if (!payload.category || !payload.question || !payload.answer) {
            return "Category, question, and answer are required.";
        }
        return "";
    }

    function attachDownloads(filename, content, mimeType) {
        if (!window.URL || typeof window.URL.createObjectURL !== "function") {
            return;
        }

        var blob = new Blob([content], { type: mimeType });
        var link = document.createElement("a");
        var objectUrl = window.URL.createObjectURL(blob);
        link.href = objectUrl;
        link.download = filename;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        if (typeof window.URL.revokeObjectURL === "function") {
            window.URL.revokeObjectURL(objectUrl);
        }
    }

    function toCsv(entries) {
        var headers = ["Category", "Question", "Answer", "Updated At"];
        var rows = entries.map(function (entry) {
            return [entry.category, entry.question, entry.answer, formatDate(entry.updatedAt)].map(function (cell) {
                var value = String(cell || "").replace(/\"/g, '\"\"');
                return '"' + value + '"';
            }).join(",");
        });
        return headers.join(",") + "\n" + rows.join("\n");
    }

    function bindStaticEvents() {
        var search = document.getElementById("faq-search-input");
        var category = document.getElementById("faq-category-filter");
        var sort = document.getElementById("faq-sort-select");
        var reset = document.getElementById("faq-reset-filters");
        var selectAll = document.getElementById("select-all");
        var addForm = document.getElementById("faq-add-form");
        var editForm = document.getElementById("faq-edit-form");
        var deleteConfirm = document.getElementById("faq-confirm-delete");
        var deleteSelected = document.getElementById("faq-delete-selected");
        var exportCsv = document.getElementById("faq-export-csv");
        var exportJson = document.getElementById("faq-export-json");
        var tbody = document.getElementById("faq-table-body");
        var addModal = document.getElementById("add_faq");

        if (search) {
            search.addEventListener("input", function (event) {
                state.searchQuery = event.target.value || "";
                renderTable();
            });
        }

        if (category) {
            category.addEventListener("change", function (event) {
                state.category = event.target.value || "all";
                renderTable();
            });
        }

        if (sort) {
            sort.addEventListener("change", function (event) {
                state.sortOrder = event.target.value || "recent";
                renderTable();
            });
        }

        if (reset) {
            reset.addEventListener("click", function () {
                state.searchQuery = "";
                state.category = "all";
                state.sortOrder = "recent";
                if (search) {
                    search.value = "";
                }
                if (category) {
                    category.value = "all";
                }
                if (sort) {
                    sort.value = "recent";
                }
                renderTable();
            });
        }

        if (selectAll) {
            selectAll.addEventListener("change", function (event) {
                var checked = !!event.target.checked;
                filteredEntries().forEach(function (entry) {
                    if (checked) {
                        state.selected[entry.id] = true;
                    } else {
                        delete state.selected[entry.id];
                    }
                });
                renderTable();
            });
        }

        if (tbody) {
            tbody.addEventListener("change", function (event) {
                var target = event.target;
                if (!target.classList.contains("faq-row-checkbox")) {
                    return;
                }

                if (target.checked) {
                    state.selected[Number(target.getAttribute("data-id"))] = true;
                } else {
                    delete state.selected[Number(target.getAttribute("data-id"))];
                }
                renderTable();
            });

            tbody.addEventListener("click", function (event) {
                var editTrigger = event.target.closest(".faq-edit-trigger");
                var deleteTrigger = event.target.closest(".faq-delete-trigger");

                if (editTrigger) {
                    fillEditForm(Number(editTrigger.getAttribute("data-id")));
                    return;
                }

                if (deleteTrigger) {
                    queueDelete([Number(deleteTrigger.getAttribute("data-id"))]);
                }
            });
        }

        if (addModal) {
            addModal.addEventListener("hidden.bs.modal", resetAddForm);
            addModal.addEventListener("shown.bs.modal", function () {
                var firstInput = document.querySelector("#add_faq input:not([type=hidden]):not([type=password]), #add_faq select");
                if (firstInput) setTimeout(function () { firstInput.focus(); }, 100);
            });
        }

        if (addForm) {
            addForm.addEventListener("submit", function (event) {
                var payload;
                var error;
                event.preventDefault();
                if (!ArcavValidation.validateForm(addForm)) { return; }
                payload = readAddPayload();
                error = validatePayload(payload);
                if (error) {
                    setFormError("faq-add-error", error);
                    return;
                }

                faqApiRequest("POST", "/v1/hcm/faqs", payload).then(function () {
                    return loadEntries();
                }).then(function () {
                    resetAddForm();
                    hideModal("add_faq");
                    renderTable();
                }).catch(function (requestError) {
                    setFormError("faq-add-error", requestError.message || "Failed to add FAQ.");
                });
            });
        }

        var editModal = document.getElementById("edit_faq");
        if (editModal) {
            editModal.addEventListener("shown.bs.modal", function () {
                var firstInput = document.querySelector("#edit_faq input:not([type=hidden]):not([type=password]), #edit_faq select");
                if (firstInput) setTimeout(function () { firstInput.focus(); }, 100);
            });
        }

        if (editForm) {
            editForm.addEventListener("submit", function (event) {
                var payload;
                var error;
                var faq;
                event.preventDefault();
                if (!ArcavValidation.validateForm(editForm)) { return; }
                payload = readEditPayload();
                error = validatePayload(payload);
                if (error) {
                    setFormError("faq-edit-error", error);
                    return;
                }

                faq = faqById(state.editId);
                if (!faq) {
                    setFormError("faq-edit-error", "FAQ entry not found.");
                    return;
                }

                faqApiRequest("PUT", "/v1/hcm/faqs/" + String(state.editId), payload).then(function () {
                    return loadEntries();
                }).then(function () {
                    hideModal("edit_faq");
                    renderTable();
                }).catch(function (requestError) {
                    setFormError("faq-edit-error", requestError.message || "Failed to update FAQ.");
                });
            });
        }

        if (deleteSelected) {
            deleteSelected.addEventListener("click", function () {
                var ids = selectedIds();
                if (!ids.length) {
                    return;
                }
                queueDelete(ids);
            });
        }

        if (deleteConfirm) {
            deleteConfirm.addEventListener("click", function () {
                if (!state.deleteQueue.length) {
                    return;
                }

                var queue = state.deleteQueue.slice();
                var request = queue.length === 1
                    ? faqApiRequest("DELETE", "/v1/hcm/faqs/" + String(queue[0]))
                    : faqApiRequest("POST", "/v1/hcm/faqs/bulk-delete", { ids: queue });

                request.then(function () {
                    queue.forEach(function (id) {
                        delete state.selected[id];
                    });
                    state.deleteQueue = [];
                    return loadEntries();
                }).then(function () {
                    hideModal("delete_modal");
                    renderTable();
                }).catch(function (requestError) {
                    setFormError("faq-edit-error", requestError.message || "Failed to delete FAQ.");
                });
            });
        }

        if (exportCsv) {
            exportCsv.addEventListener("click", function () {
                attachDownloads("faq-workspace.csv", toCsv(filteredEntries()), "text/csv;charset=utf-8");
            });
        }

        if (exportJson) {
            exportJson.addEventListener("click", function () {
                attachDownloads("faq-workspace.json", JSON.stringify(filteredEntries(), null, 2), "application/json;charset=utf-8");
            });
        }
    }

    function initFaqPage() {
        if (!document.getElementById("faq-table-body")) {
            return;
        }

        renderTable();
        bindStaticEvents();
        loadEntries().then(function () {
            renderTable();
        }).catch(function () {
            renderTable();
        });
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", initFaqPage);
    } else {
        initFaqPage();
    }
})();