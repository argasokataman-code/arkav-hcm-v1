(function (window, document) {
    "use strict";

    function esc(v) {
        return String(v || "")
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#39;");
    }

    function notify(msg, isError) {
        var c = document.querySelector("[data-hcm-toast-container]") || document.body.appendChild(Object.assign(document.createElement("div"), { style: "position:fixed;top:16px;right:16px;z-index:1080" }));
        c.setAttribute("data-hcm-toast-container", "1");
        var t = document.createElement("div");
        t.className = "alert " + (isError ? "alert-danger" : "alert-success") + " shadow-sm mb-2";
        t.textContent = msg;
        c.appendChild(t);
        window.setTimeout(function () { t.remove(); }, 2500);
    }

    function apiRequest(method, url, body) {
        var headers = { Accept: "application/json" };
        if (!(body instanceof FormData)) {
            headers["Content-Type"] = "application/json";
        }
        if (window.axios) {
            return window.axios({
                method: method,
                url: url,
                data: body instanceof FormData ? body : (body || null),
                headers: headers,
                withCredentials: true,
            }).then(function (res) {
                return res.data;
            }).catch(function (err) {
                var st = err && err.response ? err.response.status : 0;
                var d = err && err.response ? err.response.data : null;
                if (window.AuthApi && window.AuthApi.handleUnauthorizedFromApi && window.AuthApi.handleUnauthorizedFromApi(st, d)) {
                    return null;
                }
                return Promise.reject({ status: st, data: d });
            });
        }
        var opts = { method: method.toUpperCase(), headers: headers, credentials: "same-origin" };
        if (body && method.toUpperCase() !== "GET") {
            opts.body = body instanceof FormData ? body : JSON.stringify(body);
        }
        return fetch(url, opts).then(function (res) {
            return res.json().catch(function () {
                return {};
            }).then(function (data) {
                if (!res.ok) {
                    if (window.AuthApi && window.AuthApi.handleUnauthorizedFromApi && window.AuthApi.handleUnauthorizedFromApi(res.status, data)) {
                        return null;
                    }
                    return Promise.reject({ status: res.status, data: data });
                }
                return data;
            });
        });
    }

    function apiError(err) {
        var data = err && err.data;
        if (window.ApiErrorHelper && typeof window.ApiErrorHelper.format === "function") {
            return window.ApiErrorHelper.format(data, err && err.status);
        }
        if (data && data.error && data.error.message) return data.error.message;
        return "Request gagal.";
    }

    function fmtDate(s) {
        if (!s) return "-";
        var d = new Date(s);
        if (Number.isNaN(d.getTime())) return String(s);
        return d.toLocaleString("id-ID");
    }

    function prioBadge(p) {
        var map = { low: "success", medium: "warning", high: "danger", urgent: "dark" };
        return '<span class="badge badge-' + (map[p] || "secondary") + '">' + esc(String(p || "").replace("_", " ")) + "</span>";
    }

    function statusBadge(s) {
        var map = { open: "outline-primary", in_progress: "outline-warning", resolved: "outline-success", closed: "outline-dark" };
        return '<span class="badge bg-' + (map[s] || "outline-secondary") + '">' + esc(String(s || "").replace("_", " ")) + "</span>";
    }

    function toCsv(filename, headers, rows) {
        var csv = [headers.join(",")].concat((rows || []).map(function (r) {
            return r.map(function (v) {
                var s = String(v == null ? "" : v);
                return /[",\n]/.test(s) ? '"' + s.replace(/"/g, '""') + '"' : s;
            }).join(",");
        })).join("\n");
        var blob = new Blob([csv], { type: "text/csv;charset=utf-8;" });
        var url = window.URL.createObjectURL(blob);
        var a = document.createElement("a");
        a.href = url;
        a.download = filename;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        window.URL.revokeObjectURL(url);
    }

    var state = { rows: [], summary: null, meAdmin: false, assignables: [], categoryOptions: [] };

    function currentMode() {
        var pageEl = document.querySelector("[data-tickets-page]");
        return pageEl ? String(pageEl.getAttribute("data-ticket-mode") || "") : "";
    }

    function loadMe() {
        return apiRequest("get", "/v1/identity/auth/me").then(function (p) {
            if (!p || p.success !== true) return;
            state.meAdmin = !!(p.data && p.data.hcmAdmin);
            document.querySelectorAll("[data-ticket-admin-only]").forEach(function (el) {
                el.style.display = state.meAdmin ? "" : "none";
            });
            var mode = currentMode();
            if (mode === "admin" && !state.meAdmin) {
                window.location.replace("/tickets-employee");
                return;
            }
        });
    }

    function loadCategoryOptions() {
        return apiRequest("get", "/v1/hcm/tickets/category-options").then(function (p) {
            if (!p || p.success !== true) return;
            var list = Array.isArray(p.data) ? p.data : [];
            state.categoryOptions = list;
            var sel = document.querySelector('[data-ticket-form="create"] select[name="category"]');
            if (!sel) return;
            var html = '<option value="">-- Pilih kategori --</option>';
            list.forEach(function (x) {
                html += '<option value="' + esc(x.id) + '">' + esc(x.name) + "</option>";
            });
            sel.innerHTML = html;
        }).catch(function () {});
    }

    function loadAssignableUsers() {
        if (!state.meAdmin) return Promise.resolve();
        return apiRequest("get", "/v1/hcm/tickets/assignable-users").then(function (p) {
            if (!p || p.success !== true) return;
            state.assignables = Array.isArray(p.data) ? p.data : [];
            var sel = document.querySelector('[data-ticket-form="create"] select[name="assigneeUserId"]');
            if (sel) {
                var html = '<option value="">Unassigned</option>';
                state.assignables.forEach(function (u) {
                    html += '<option value="' + esc(u.id) + '">' + esc(u.name + " (" + u.email + ")") + "</option>";
                });
                sel.innerHTML = html;
            }
        }).catch(function () {});
    }

    function buildQuery() {
        var q = document.querySelector("[data-ticket-filter-q]");
        var status = document.querySelector("[data-ticket-filter-status]");
        var priority = document.querySelector("[data-ticket-filter-priority]");
        var params = [];
        if (q && q.value.trim()) params.push("q=" + encodeURIComponent(q.value.trim()));
        if (status && status.value) params.push("status=" + encodeURIComponent(status.value));
        if (priority && priority.value) params.push("priority=" + encodeURIComponent(priority.value));
        params.push("perPage=100");
        return "/v1/hcm/tickets?" + params.join("&");
    }

    function renderSummary(summary) {
        var total = document.querySelector("[data-ticket-summary-total]");
        var open = document.querySelector("[data-ticket-summary-open]");
        var prog = document.querySelector("[data-ticket-summary-progress]");
        var done = document.querySelector("[data-ticket-summary-done]");
        if (total) total.textContent = String(summary.total || 0);
        if (open) open.textContent = String(summary.open || 0);
        if (prog) prog.textContent = String(summary.inProgress || 0);
        if (done) done.textContent = String((summary.resolved || 0) + (summary.closed || 0));
    }

    function renderList(rows) {
        var c = document.querySelector("[data-ticket-list-container]");
        if (!c) return;
        if (!rows.length) {
            c.innerHTML = '<div class="card"><div class="card-body text-center text-muted py-4">Belum ada ticket.</div></div>';
            return;
        }
        c.innerHTML = rows.map(function (t) {
            var quickAction = "";
            if (state.meAdmin) {
                if (t.status === "closed") {
                    quickAction = '<button type="button" class="btn btn-sm btn-outline-success" data-ticket-quick-status="' + esc(t.id) + '" data-next-status="open">Reopen</button>';
                } else {
                    quickAction = '<button type="button" class="btn btn-sm btn-outline-danger" data-ticket-quick-status="' + esc(t.id) + '" data-next-status="closed">Close</button>';
                }
            }
            return '<div class="card"><div class="card-body"><div class="d-flex justify-content-between align-items-start gap-2"><div class="flex-grow-1"><div class="mb-1"><span class="badge badge-soft-dark me-2">' + esc(t.code) + "</span>" + statusBadge(t.status) + '</div><h5 class="mb-1"><a href=\"/ticket-details/" + esc(t.id) + '">' + esc(t.subject) + '</a></h5><p class="text-muted mb-2">' + esc(t.description || "") + '</p><p class="mb-2 fs-12 text-muted">Reporter: ' + esc(t.reporter ? t.reporter.name : "-") + " | Assignee: " + esc(t.assignee ? t.assignee.name : "Unassigned") + " | Updated: " + esc(fmtDate(t.updatedAt)) + '</p><div class="d-flex gap-2 flex-wrap"><a href="/ticket-details/' + esc(t.id) + '" class="btn btn-sm btn-primary">Lihat detail</a><a href="/ticket-details/' + esc(t.id) + '#komentar" class="btn btn-sm btn-outline-secondary">Komentar</a>' + quickAction + '</div></div><div class="text-end">' + prioBadge(t.priority) + '<div class="mt-2 fs-12 text-muted">' + esc(t.commentsCount) + " komentar</div></div></div></div></div>";
        }).join("");
    }

    function renderGrid(rows) {
        var c = document.querySelector("[data-ticket-grid-container]");
        if (!c) return;
        if (!rows.length) {
            c.innerHTML = '<div class="col-12"><div class="card"><div class="card-body text-center text-muted py-4">Belum ada ticket.</div></div></div>';
            return;
        }
        c.innerHTML = rows.map(function (t) {
            var quickAction = "";
            if (state.meAdmin) {
                if (t.status === "closed") {
                    quickAction = '<button type="button" class="btn btn-sm btn-outline-success" data-ticket-quick-status="' + esc(t.id) + '" data-next-status="open">Reopen</button>';
                } else {
                    quickAction = '<button type="button" class="btn btn-sm btn-outline-danger" data-ticket-quick-status="' + esc(t.id) + '" data-next-status="closed">Close</button>';
                }
            }
            return '<div class="col-xl-4 col-md-6"><div class="card h-100"><div class="card-body"><div class="d-flex justify-content-between align-items-center mb-2"><span class="badge badge-soft-dark">' + esc(t.code) + "</span>" + prioBadge(t.priority) + '</div><h5 class="mb-1"><a href="/ticket-details/' + esc(t.id) + '">' + esc(t.subject) + '</a></h5><p class="text-muted mb-2">' + esc(t.description || "") + '</p><div class="d-flex justify-content-between align-items-center mb-2"><div>' + statusBadge(t.status) + '</div><small class="text-muted">' + esc(fmtDate(t.updatedAt)) + '</small></div><div class="d-flex gap-2"><a href="/ticket-details/' + esc(t.id) + '" class="btn btn-sm btn-primary">Detail</a><a href="/ticket-details/' + esc(t.id) + '#komentar" class="btn btn-sm btn-outline-secondary">Komentar</a>' + quickAction + '</div></div></div></div>';
        }).join("");
    }

    function adminActionsHtml(ticket) {
        if (!state.meAdmin) return "";
        var users = state.assignables || [];
        var opts = '<option value="">Unassigned</option>';
        users.forEach(function (u) {
            var sel = ticket.assignee && Number(ticket.assignee.id) === Number(u.id) ? " selected" : "";
            opts += '<option value="' + esc(u.id) + '"' + sel + ">" + esc(u.name) + "</option>";
        });
        return '<div class="card"><div class="card-body"><h6 class="mb-3">Admin Actions</h6><form data-ticket-manage-form class="row g-2"><div class="col-md-4"><label class="form-label">Status</label><select class="form-select" name="status"><option value="open"' + (ticket.status === "open" ? " selected" : "") + '>Open</option><option value="in_progress"' + (ticket.status === "in_progress" ? " selected" : "") + '>In Progress</option><option value="resolved"' + (ticket.status === "resolved" ? " selected" : "") + '>Resolved</option><option value="closed"' + (ticket.status === "closed" ? " selected" : "") + '>Closed</option></select></div><div class="col-md-4"><label class="form-label">Assignee</label><select class="form-select" name="assigneeUserId">' + opts + '</select></div><div class="col-md-4"><label class="form-label">SLA Due</label><input type="datetime-local" class="form-control" name="slaDueAt" value="' + esc(ticket.slaDueAt ? new Date(ticket.slaDueAt).toISOString().slice(0, 16) : "") + '"></div><div class="col-12"><button type="submit" class="btn btn-primary btn-sm me-2">Update Ticket</button><button type="button" class="btn btn-outline-danger btn-sm" data-ticket-delete>Delete</button></div></form></div></div>';
    }

    function renderDetail(ticket) {
        var main = document.querySelector("[data-ticket-detail-main]");
        var side = document.querySelector("[data-ticket-detail-side]");
        if (!main || !side) return;
        var comments = (ticket.comments || []).map(function (c) {
            return '<div class="border-bottom pb-2 mb-2"><div class="fw-medium">' + esc(c.user ? c.user.name : "User") + '</div><div class="fs-12 text-muted mb-1">' + esc(fmtDate(c.createdAt)) + '</div><div>' + esc(c.body) + "</div></div>";
        }).join("") || '<div class="text-muted">Belum ada komentar.</div>';
        var atts = (ticket.attachments || []).map(function (a) {
            return '<div class="d-flex justify-content-between align-items-center border-bottom py-2"><span>' + esc(a.name) + '</span><div class="d-flex gap-2"><a href="' + esc(a.previewUrl || a.downloadUrl) + '" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary">Preview</a><a href="' + esc(a.downloadUrl) + '" class="btn btn-sm btn-light">Download</a></div></div>';
        }).join("") || '<div class="text-muted">Belum ada attachment.</div>';
        var history = (ticket.assignmentHistory || []).map(function (h) {
            return '<li class="mb-2"><div class="small text-muted">' + esc(fmtDate(h.createdAt)) + '</div><div class="fw-medium">' + esc(h.actor ? h.actor.name : "System") + '</div><div>' + esc((h.fromAssignee ? h.fromAssignee.name : "Unassigned") + " -> " + (h.toAssignee ? h.toAssignee.name : "Unassigned")) + "</div></li>";
        }).join("") || "<li>-</li>";
        main.innerHTML = '<div class="card"><div class="card-header d-flex justify-content-between align-items-center"><div><h5 class="mb-1">' + esc(ticket.subject) + '</h5><div><span class="badge badge-soft-dark me-2">' + esc(ticket.code) + "</span>" + statusBadge(ticket.status) + '</div></div><div>' + prioBadge(ticket.priority) + '</div></div><div class="card-body"><p>' + esc(ticket.description || "") + '</p><hr><h6 id="komentar">Komentar</h6><div>' + comments + '</div><form data-ticket-comment-form class="mt-3"><textarea class="form-control mb-2" name="body" rows="3" maxlength="5000" required placeholder="Tulis komentar update ticket..."></textarea><button class="btn btn-primary btn-sm" type="submit">Tambah Komentar</button></form><hr><h6>Attachment</h6><div>' + atts + '</div><form data-ticket-attachment-form class="mt-3"><input type="file" name="file" class="form-control mb-2" required><button class="btn btn-outline-primary btn-sm" type="submit">Upload</button></form></div></div>';
        side.innerHTML = '<div class="card"><div class="card-body"><h6 class="mb-3">Metadata</h6><div class="border rounded p-2 mb-2"><div class="small text-muted">Reporter</div><div class="fw-semibold text-break">' + esc(ticket.reporter ? ticket.reporter.name : "-") + '</div></div><div class="border rounded p-2 mb-2"><div class="small text-muted">Assignee</div><div class="fw-semibold text-break">' + esc(ticket.assignee ? ticket.assignee.name : "Unassigned") + '</div></div><div class="border rounded p-2 mb-2"><div class="small text-muted">SLA Due</div><div class="fw-semibold">' + esc(fmtDate(ticket.slaDueAt)) + '</div></div><div class="border rounded p-2 mb-2"><div class="small text-muted">Resolved</div><div class="fw-semibold">' + esc(fmtDate(ticket.resolvedAt)) + '</div></div><div class="border rounded p-2 mb-3"><div class="small text-muted">Closed</div><div class="fw-semibold">' + esc(fmtDate(ticket.closedAt)) + '</div></div><h6 class="mb-2">Assignment History</h6><ul class="ps-3 mb-0">' + history + '</ul></div></div>';
        var actionWrap = document.querySelector("[data-ticket-detail-actions]");
        if (actionWrap) {
            actionWrap.innerHTML = adminActionsHtml(ticket);
        }
    }

    function bindCreateForm() {
        var form = document.querySelector('[data-ticket-form="create"]');
        if (!form) return;
        form.addEventListener("submit", function (e) {
            e.preventDefault();
            var fd = new FormData(form);
            var categoryRaw = String(fd.get("category") || "").trim();
            var categoryId = categoryRaw ? Number(categoryRaw) : null;
            var selectedCategory = state.categoryOptions.find(function (x) {
                return Number(x.id) === categoryId;
            });
            var body = {
                subject: String(fd.get("subject") || "").trim(),
                categoryId: Number.isFinite(categoryId) && categoryId > 0 ? categoryId : null,
                category: selectedCategory ? String(selectedCategory.name || "") : null,
                priority: String(fd.get("priority") || "medium"),
                description: String(fd.get("description") || "").trim(),
                slaDueAt: String(fd.get("slaDueAt") || "").trim() || null,
            };
            if (state.meAdmin) {
                var v = String(fd.get("assigneeUserId") || "").trim();
                body.assigneeUserId = v ? Number(v) : null;
            }
            apiRequest("post", "/v1/hcm/tickets", body).then(function (p) {
                if (!p || p.success !== true) return;
                notify("Ticket berhasil dibuat.");
                form.reset();
                var m = document.getElementById("arcav_ticket_create_modal");
                if (m && window.bootstrap) window.bootstrap.Modal.getOrCreateInstance(m).hide();
                refreshPageData();
            }).catch(function (err) { notify(apiError(err), true); });
        });
    }

    function bindFilters() {
        var b = document.querySelector("[data-ticket-filter-apply]");
        if (b) b.addEventListener("click", function () { refreshPageData(); });
    }

    function bindExports() {
        document.querySelectorAll("[data-ticket-export]").forEach(function (btn) {
            btn.addEventListener("click", function () {
                var rows = state.rows || [];
                if (!rows.length) {
                    notify("Tidak ada data untuk diexport.", true);
                    return;
                }
                toCsv("tickets-" + new Date().toISOString().slice(0, 10) + ".csv", ["Code", "Subject", "Status", "Priority", "Reporter", "Assignee", "UpdatedAt"], rows.map(function (t) {
                    return [t.code, t.subject, t.status, t.priority, t.reporter ? t.reporter.name : "", t.assignee ? t.assignee.name : "", t.updatedAt];
                }));
            });
        });
    }

    function bindDetailActions(ticketId) {
        document.addEventListener("submit", function (e) {
            var cForm = e.target.closest("[data-ticket-comment-form]");
            if (cForm) {
                e.preventDefault();
                var body = String(new FormData(cForm).get("body") || "").trim();
                apiRequest("post", "/v1/hcm/tickets/" + ticketId + "/comments", { body: body })
                    .then(function () { notify("Komentar ditambahkan."); refreshPageData(); })
                    .catch(function (err) { notify(apiError(err), true); });
                return;
            }
            var aForm = e.target.closest("[data-ticket-attachment-form]");
            if (aForm) {
                e.preventDefault();
                var fd = new FormData(aForm);
                apiRequest("post", "/v1/hcm/tickets/" + ticketId + "/attachments", fd)
                    .then(function () { notify("Attachment diupload."); refreshPageData(); })
                    .catch(function (err) { notify(apiError(err), true); });
            }
            var mForm = e.target.closest("[data-ticket-manage-form]");
            if (mForm) {
                e.preventDefault();
                var fd = new FormData(mForm);
                var assigneeRaw = String(fd.get("assigneeUserId") || "").trim();
                var payload = {
                    status: String(fd.get("status") || ""),
                    assigneeUserId: assigneeRaw ? Number(assigneeRaw) : null,
                    slaDueAt: String(fd.get("slaDueAt") || "").trim() || null,
                };
                apiRequest("put", "/v1/hcm/tickets/" + ticketId, payload)
                    .then(function () { notify("Ticket diupdate."); refreshPageData(); })
                    .catch(function (err) { notify(apiError(err), true); });
                return;
            }
        });

        document.addEventListener("click", function (e) {
            var delBtn = e.target.closest("[data-ticket-delete]");
            if (!delBtn) return;
            if (!window.ArcavUi || typeof window.ArcavUi.confirmDelete !== "function") return;
            window.ArcavUi.confirmDelete("Hapus ticket ini?", "Hapus ticket").then(function (ok) {
                if (!ok) return;
                apiRequest("delete", "/v1/hcm/tickets/" + ticketId)
                    .then(function () {
                        notify("Ticket dihapus.");
                        window.location.href = "/tickets";
                    })
                    .catch(function (err) { notify(apiError(err), true); });
            });
        });
    }

    function bindMasterCategoryPage() {
        var pageEl = document.querySelector('[data-tickets-page="master"]');
        if (!pageEl) return;
        var wrap = document.querySelector("[data-ticket-category-table]");
        var addForm = document.querySelector('[data-ticket-category-form="add"]');

        function render(rows) {
            if (!wrap) return;
            if (!rows.length) {
                wrap.innerHTML = '<div class="text-muted">Belum ada kategori.</div>';
                return;
            }
            wrap.innerHTML = '<div class="table-responsive"><table class="table"><thead><tr><th>Nama</th><th>Status</th><th>Sort</th><th>Aksi</th></tr></thead><tbody>' + rows.map(function (r) {
                return '<tr><td>' + esc(r.name) + '</td><td>' + esc(r.isActive ? "Active" : "Inactive") + '</td><td>' + esc(r.sortOrder) + '</td><td><button class="btn btn-sm btn-outline-danger" data-ticket-cat-del="' + esc(r.id) + '">Hapus</button></td></tr>';
            }).join("") + '</tbody></table></div>';
        }

        function reload() {
            apiRequest("get", "/v1/hcm/tickets/categories")
                .then(function (p) { render((p && p.success && Array.isArray(p.data)) ? p.data : []); })
                .catch(function (err) { notify(apiError(err), true); });
        }

        if (addForm) {
            addForm.addEventListener("submit", function (e) {
                e.preventDefault();
                var fd = new FormData(addForm);
                apiRequest("post", "/v1/hcm/tickets/categories", {
                    name: String(fd.get("name") || "").trim(),
                    sortOrder: Number(fd.get("sortOrder") || 0),
                    isActive: String(fd.get("isActive") || "1") === "1",
                }).then(function () {
                    notify("Kategori ditambahkan.");
                    addForm.reset();
                    reload();
                    loadCategoryOptions();
                }).catch(function (err) { notify(apiError(err), true); });
            });
        }

        document.addEventListener("click", function (e) {
            var del = e.target.closest("[data-ticket-cat-del]");
            if (!del) return;
            var id = del.getAttribute("data-ticket-cat-del");
            if (!window.ArcavUi || typeof window.ArcavUi.confirmDelete !== "function") return;
            window.ArcavUi.confirmDelete("Hapus kategori ticket?", "Hapus kategori").then(function (ok) {
                if (!ok) return;
                apiRequest("delete", "/v1/hcm/tickets/categories/" + id)
                    .then(function () {
                        notify("Kategori dihapus.");
                        reload();
                        loadCategoryOptions();
                    })
                    .catch(function (err) { notify(apiError(err), true); });
            });
        });

        reload();
    }

    function bindQuickStatusActions() {
        document.addEventListener("click", function (e) {
            var btn = e.target.closest("[data-ticket-quick-status]");
            if (!btn) return;
            var id = btn.getAttribute("data-ticket-quick-status");
            var nextStatus = btn.getAttribute("data-next-status");
            if (!id || !nextStatus) return;
            apiRequest("put", "/v1/hcm/tickets/" + id, { status: nextStatus })
                .then(function () {
                    notify(nextStatus === "closed" ? "Ticket ditutup." : "Ticket dibuka kembali.");
                    refreshPageData();
                })
                .catch(function (err) { notify(apiError(err), true); });
        });
    }

    function refreshPageData() {
        var pageEl = document.querySelector("[data-tickets-page]");
        if (!pageEl) return;
        var page = pageEl.getAttribute("data-tickets-page");
        if (page === "detail") {
            var ticketId = Number(pageEl.getAttribute("data-ticket-id") || 0);
            if (!ticketId) return;
            apiRequest("get", "/v1/hcm/tickets/" + ticketId).then(function (p) {
                if (!p || p.success !== true) return;
                renderDetail(p.data || {});
            }).catch(function (err) { notify(apiError(err), true); });
            return;
        }
        apiRequest("get", buildQuery()).then(function (p) {
            if (!p || p.success !== true) return;
            state.rows = Array.isArray(p.data) ? p.data : [];
            state.summary = p.meta && p.meta.summary ? p.meta.summary : {};
            renderSummary(state.summary);
            if (page === "grid") {
                renderGrid(state.rows);
            } else {
                renderList(state.rows);
            }
        }).catch(function (err) { notify(apiError(err), true); });
    }

    function init() {
        if (!document.querySelector("[data-tickets-page]")) return;
        loadMe().then(function () { return Promise.all([loadAssignableUsers(), loadCategoryOptions()]); }).finally(function () {
            bindCreateForm();
            bindFilters();
            bindExports();
            bindMasterCategoryPage();
            bindQuickStatusActions();
            var pageEl = document.querySelector("[data-tickets-page]");
            if (pageEl && pageEl.getAttribute("data-tickets-page") === "detail") {
                bindDetailActions(Number(pageEl.getAttribute("data-ticket-id") || 0));
            }
            refreshPageData();
        });
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", init);
    } else {
        init();
    }
})(window, document);
