(function (window, document) {
    "use strict";

    // ─── Helpers ────────────────────────────────────────────────────────────────
    function esc(v) {
        return String(v || "")
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#39;");
    }

    function notify(msg, isError) {
        var c = document.querySelector("[data-hcm-toast-container]") ||
            document.body.appendChild(Object.assign(document.createElement("div"), {
                style: "position:fixed;top:16px;right:16px;z-index:1080"
            }));
        c.setAttribute("data-hcm-toast-container", "1");
        var t = document.createElement("div");
        t.className = "alert " + (isError ? "alert-danger" : "alert-success") + " shadow-sm mb-2";
        t.textContent = msg;
        c.appendChild(t);
        window.setTimeout(function () { t.remove(); }, 3000);
    }

    function apiRequest(method, url, body) {
        var headers = { Accept: "application/json" };
        if (!(body instanceof FormData)) {
            headers["Content-Type"] = "application/json";
        }
        var token = (window.AuthApi && window.AuthApi.getToken()) || localStorage.getItem('arcav_access_token');
        if (token) { headers['Authorization'] = 'Bearer ' + token; }
        if (window.axios) {
            return window.axios({
                method: method,
                url: url,
                data: body instanceof FormData ? body : (body || null),
                headers: headers,
                withCredentials: true,
            }).then(function (res) { return res.data; })
              .catch(function (err) {
                var st = err && err.response ? err.response.status : 0;
                var d = err && err.response ? err.response.data : null;
                if (window.AuthApi && window.AuthApi.handleUnauthorizedFromApi) {
                    if (window.AuthApi.handleUnauthorizedFromApi(st, d)) return null;
                }
                if (window.AuthApi && window.AuthApi.handleForbiddenFromApi) {
                    if (window.AuthApi.handleForbiddenFromApi(st, d)) return null;
                }
                return Promise.reject({ status: st, data: d });
              });
        }
        var opts = { method: method.toUpperCase(), headers: headers, credentials: "same-origin" };
        if (body && method.toUpperCase() !== "GET") {
            opts.body = body instanceof FormData ? body : JSON.stringify(body);
        }
        return fetch(url, opts).then(function (res) {
            return res.json().catch(function () { return {}; }).then(function (data) {
                if (!res.ok) {
                    if (window.AuthApi && window.AuthApi.handleUnauthorizedFromApi) {
                        if (window.AuthApi.handleUnauthorizedFromApi(res.status, data)) return null;
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
        return d.toLocaleDateString("id-ID", { year: "numeric", month: "short", day: "numeric" });
    }

    function statusBadge(s) {
        var map = {
            detected: "danger",
            notified: "warning",
            resolved: "success"
        };
        var label = { detected: "Terdeteksi", notified: "Notifikasi Terkirim", resolved: "Selesai" };
        return '<span class="badge bg-' + (map[s] || "secondary") + '">' + esc(label[s] || s) + "</span>";
    }

    // ─── State ───────────────────────────────────────────────────────────────────
    var state = { rows: [], currentDetail: null };

    // ─── List / load ─────────────────────────────────────────────────────────────
    function loadList() {
        var container = document.querySelector("[data-si-list]");
        if (!container) return;
        container.innerHTML = '<div class="text-center text-muted py-4">Memuat data...</div>';
        apiRequest("get", "/v1/admin/security-incidents?per_page=50")
            .then(function (p) {
                if (!p || p.success !== true) return;
                var items = (p.data && p.data.data) ? p.data.data : (Array.isArray(p.data) ? p.data : []);
                state.rows = items;
                renderList(items, container);
                updateSummary(items);
            })
            .catch(function (err) {
                container.innerHTML = '<div class="alert alert-danger">Gagal memuat data: ' + esc(apiError(err)) + "</div>";
            });
    }

    function updateSummary(rows) {
        var total = document.querySelector("[data-si-stat='total']");
        var detected = document.querySelector("[data-si-stat='detected']");
        var notified = document.querySelector("[data-si-stat='notified']");
        var resolved = document.querySelector("[data-si-stat='resolved']");
        if (total) total.textContent = String(rows.length);
        if (detected) detected.textContent = String(rows.filter(function (r) { return r.status === "detected"; }).length);
        if (notified) notified.textContent = String(rows.filter(function (r) { return r.status === "notified"; }).length);
        if (resolved) resolved.textContent = String(rows.filter(function (r) { return r.status === "resolved"; }).length);
    }

    function renderList(rows, container) {
        if (!rows.length) {
            container.innerHTML = '<div class="alert alert-info">Tidak ada insiden keamanan tercatat.</div>';
            return;
        }
        container.innerHTML = rows.map(function (r) {
            var actions = "";
            if (r.status === "detected") {
                actions += '<button class="btn btn-sm btn-warning me-1" data-si-action="notify" data-uuid="' + esc(r.uuid) + '"><i class="ti ti-bell me-1"></i>Notif Subjek</button>';
            }
            if (r.status !== "resolved") {
                actions += '<button class="btn btn-sm btn-success me-1" data-si-action="resolve" data-uuid="' + esc(r.uuid) + '"><i class="ti ti-check me-1"></i>Selesaikan</button>';
            }
            actions += '<button class="btn btn-sm btn-outline-secondary" data-si-action="detail" data-uuid="' + esc(r.uuid) + '"><i class="ti ti-eye me-1"></i>Detail</button>';
            return '<div class="card mb-2">'
                + '<div class="card-body">'
                + '<div class="d-flex justify-content-between align-items-start gap-2 flex-wrap">'
                + '<div class="flex-grow-1">'
                + '<div class="mb-1">' + statusBadge(r.status) + '</div>'
                + '<h6 class="mb-1">' + esc(r.title) + '</h6>'
                + '<p class="text-muted mb-2 small">' + esc((r.description || "").substring(0, 200)) + (r.description && r.description.length > 200 ? "..." : "") + '</p>'
                + '<p class="mb-0 small text-muted">'
                + 'Terdeteksi: <strong>' + esc(fmtDate(r.detected_at)) + '</strong>'
                + ' &nbsp;|&nbsp; Subjek terdampak: <strong>' + esc(String(r.affected_subjects_count || 0)) + '</strong>'
                + (r.reported_to_bssn_at ? ' &nbsp;|&nbsp; Dilaporkan BSSN: <strong>' + esc(fmtDate(r.reported_to_bssn_at)) + '</strong>' : '')
                + '</p>'
                + '</div>'
                + '<div class="d-flex gap-1 flex-wrap align-items-center">' + actions + '</div>'
                + '</div>'
                + '</div>'
                + '</div>';
        }).join("");
    }

    // ─── Detail modal ────────────────────────────────────────────────────────────
    function openDetail(uuid) {
        apiRequest("get", "/v1/admin/security-incidents/" + uuid)
            .then(function (p) {
                if (!p || p.success !== true) return;
                var r = p.data;
                state.currentDetail = r;
                var body = document.querySelector("#siDetailModalBody");
                if (!body) return;
                var dataTypes = Array.isArray(r.affected_data_types) ? r.affected_data_types.join(", ") : "-";
                var uuids = Array.isArray(r.affected_user_uuids) && r.affected_user_uuids.length
                    ? r.affected_user_uuids.slice(0, 5).map(function (u) { return esc(u); }).join(", ") + (r.affected_user_uuids.length > 5 ? "..." : "")
                    : "-";
                body.innerHTML = '<dl class="row mb-0">'
                    + '<dt class="col-sm-4">Judul</dt><dd class="col-sm-8">' + esc(r.title) + '</dd>'
                    + '<dt class="col-sm-4">Status</dt><dd class="col-sm-8">' + statusBadge(r.status) + '</dd>'
                    + '<dt class="col-sm-4">Deskripsi</dt><dd class="col-sm-8">' + esc(r.description || "-") + '</dd>'
                    + '<dt class="col-sm-4">Jenis Data Terdampak</dt><dd class="col-sm-8">' + esc(dataTypes) + '</dd>'
                    + '<dt class="col-sm-4">Jumlah Subjek</dt><dd class="col-sm-8">' + esc(String(r.affected_subjects_count || 0)) + '</dd>'
                    + '<dt class="col-sm-4">UUID Subjek (maks 5)</dt><dd class="col-sm-8">' + uuids + '</dd>'
                    + '<dt class="col-sm-4">Tanggal Terdeteksi</dt><dd class="col-sm-8">' + esc(fmtDate(r.detected_at)) + '</dd>'
                    + '<dt class="col-sm-4">Laporan BSSN</dt><dd class="col-sm-8">' + esc(fmtDate(r.reported_to_bssn_at)) + '</dd>'
                    + '<dt class="col-sm-4">Notifikasi Terkirim</dt><dd class="col-sm-8">' + esc(fmtDate(r.notifications_sent_at)) + '</dd>'
                    + '<dt class="col-sm-4">Diselesaikan</dt><dd class="col-sm-8">' + esc(fmtDate(r.resolved_at)) + '</dd>'
                    + '</dl>';
                var modal = document.getElementById("siDetailModal");
                if (modal && window.bootstrap) {
                    window.bootstrap.Modal.getOrCreateInstance(modal).show();
                }
            })
            .catch(function (err) { notify(apiError(err), true); });
    }

    // ─── Notify subjects ─────────────────────────────────────────────────────────
    function notifySubjects(uuid) {
        if (!window.confirm("Kirim notifikasi pelanggaran data ke semua subjek terdampak?")) return;
        apiRequest("post", "/v1/admin/security-incidents/" + uuid + "/notify-subjects")
            .then(function (p) {
                if (!p || p.success !== true) return;
                notify("Notifikasi dijadwalkan untuk dikirim ke subjek terdampak.");
                loadList();
            })
            .catch(function (err) { notify(apiError(err), true); });
    }

    // ─── Resolve ─────────────────────────────────────────────────────────────────
    function resolveIncident(uuid) {
        if (!window.confirm("Tandai insiden ini sebagai selesai/resolved?")) return;
        apiRequest("post", "/v1/admin/security-incidents/" + uuid + "/resolve")
            .then(function (p) {
                if (!p || p.success !== true) return;
                notify("Insiden berhasil ditandai selesai.");
                loadList();
            })
            .catch(function (err) { notify(apiError(err), true); });
    }

    // ─── Create form ─────────────────────────────────────────────────────────────
    function bindCreateForm() {
        var form = document.querySelector("[data-si-create-form]");
        if (!form) return;
        form.addEventListener("submit", function (e) {
            e.preventDefault();
            if (!ArcavValidation.validateForm(form)) { return; }
            var fd = new FormData(form);
            var dataTypesRaw = String(fd.get("affected_data_types") || "").trim();
            var body = {
                title: String(fd.get("title") || "").trim(),
                description: String(fd.get("description") || "").trim(),
                detected_at: String(fd.get("detected_at") || "").trim(),
                affected_subjects_count: parseInt(fd.get("affected_subjects_count") || "0", 10),
                affected_data_types: dataTypesRaw ? dataTypesRaw.split(",").map(function (s) { return s.trim(); }).filter(Boolean) : [],
                reported_to_bssn_at: String(fd.get("reported_to_bssn_at") || "").trim() || null,
            };
            apiRequest("post", "/v1/admin/security-incidents", body)
                .then(function (p) {
                    if (!p || p.success !== true) return;
                    notify("Insiden keamanan berhasil dicatat.");
                    form.reset();
                    var modal = document.getElementById("siCreateModal");
                    if (modal && window.bootstrap) {
                        window.bootstrap.Modal.getOrCreateInstance(modal).hide();
                    }
                    loadList();
                })
                .catch(function (err) { notify(apiError(err), true); });
        });

        var siModal = document.getElementById("siCreateModal");
        if (siModal) {
            siModal.addEventListener("shown.bs.modal", function () {
                var firstInput = document.querySelector("#siCreateModal input:not([type=hidden]):not([type=password]), #siCreateModal select");
                if (firstInput) setTimeout(function () { firstInput.focus(); }, 100);
            });
        }
    }

    // ─── Event delegation ────────────────────────────────────────────────────────
    function bindListActions() {
        document.addEventListener("click", function (e) {
            var btn = e.target.closest("[data-si-action]");
            if (!btn) return;
            var action = btn.getAttribute("data-si-action");
            var uuid = btn.getAttribute("data-uuid");
            if (!uuid) return;
            if (action === "detail") openDetail(uuid);
            else if (action === "notify") notifySubjects(uuid);
            else if (action === "resolve") resolveIncident(uuid);
        });
    }

    // ─── Filter ──────────────────────────────────────────────────────────────────
    function bindFilter() {
        var btn = document.querySelector("[data-si-filter-apply]");
        if (btn) {
            btn.addEventListener("click", function () {
                var q = String((document.querySelector("[data-si-filter-q]") || {}).value || "").toLowerCase().trim();
                var status = String((document.querySelector("[data-si-filter-status]") || {}).value || "");
                var filtered = state.rows.filter(function (r) {
                    var matchQ = !q || r.title.toLowerCase().includes(q) || (r.description || "").toLowerCase().includes(q);
                    var matchStatus = !status || r.status === status;
                    return matchQ && matchStatus;
                });
                var container = document.querySelector("[data-si-list]");
                if (container) renderList(filtered, container);
            });
        }
        var qInput = document.querySelector("[data-si-filter-q]");
        if (qInput) {
            qInput.addEventListener("keyup", function (e) {
                if (e.key === "Enter") {
                    var applyBtn = document.querySelector("[data-si-filter-apply]");
                    if (applyBtn) applyBtn.click();
                }
            });
        }
    }

    // ─── Init ────────────────────────────────────────────────────────────────────
    function init() {
        if (!document.querySelector("[data-si-page]")) return;
        loadList();
        bindListActions();
        bindCreateForm();
        bindFilter();
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", init);
    } else {
        init();
    }

}(window, document));
