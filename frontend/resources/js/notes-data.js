/**
 * notes-data.js
 * Handles all CRUD operations for the /notes page.
 * API: GET/POST/PUT/DELETE /v1/hcm/notes
 */
(function () {
    "use strict";

    /* ------------------------------------------------------------------ */
    /* Tenant helpers (same pattern as hcm-extras-data.js)                  */
    /* ------------------------------------------------------------------ */
    function noteTenantHeaders() {
        var ctx = window.AuthApi && typeof window.AuthApi.getTenantContext === "function"
            ? window.AuthApi.getTenantContext()
            : {};
        var h = { Accept: "application/json", "Content-Type": "application/json" };
        if (ctx.companyId)   h["X-Company-Id"]   = ctx.companyId;
        if (ctx.companyCode) h["X-Company-Code"] = ctx.companyCode;
        if (ctx.companyUuid) h["X-Company-Uuid"] = ctx.companyUuid;
        return h;
    }

    function noteApiRequest(method, url, body) {
        var opts = { method: method, headers: noteTenantHeaders(), credentials: "same-origin" };
        if (body !== undefined) opts.body = JSON.stringify(body);
        return fetch(url, opts).then(function (r) { return r.json(); });
    }

    /* ------------------------------------------------------------------ */
    /* State                                                                */
    /* ------------------------------------------------------------------ */
    var state = {
        all:       [],
        important: [],
        trash:     [],
        editId:    null,
        deleteId:  null
    };

    /* ------------------------------------------------------------------ */
    /* Priority / Tag badge helpers                                         */
    /* ------------------------------------------------------------------ */
    var PRIORITY_BADGE = {
        low:    "bg-outline-danger",
        medium: "bg-outline-warning",
        high:   "bg-outline-success"
    };
    var TAG_COLOR = {
        personal: "text-info",
        social:   "text-warning",
        work:     "text-primary",
        others:   "text-secondary"
    };

    function capitalize(s) { return s.charAt(0).toUpperCase() + s.slice(1); }

    function noteCard(note, inTrash) {
        var priorityBadge = PRIORITY_BADGE[note.priority] || "bg-outline-warning";
        var tagColor      = TAG_COLOR[note.tag] || "text-info";
        var date          = note.updatedAt ? new Date(note.updatedAt).toLocaleDateString("en-GB", { day: "2-digit", month: "short", year: "numeric" }) : "";
        var starIcon      = note.isImportant ? "fas fa-star text-warning" : "far fa-star";

        var trashAction = inTrash
            ? '<a href="javascript:void(0);" class="dropdown-item note-restore-btn" data-id="' + note.id + '"><span><i data-feather="refresh-ccw"></i></span>Restore</a>'
            : '<a href="javascript:void(0);" class="dropdown-item note-trash-btn" data-id="' + note.id + '"><span><i data-feather="trash-2"></i></span>Move to Trash</a>';

        var importantAction = inTrash ? "" :
            '<a href="javascript:void(0);" class="me-2 note-star-btn" data-id="' + note.id + '" data-important="' + (note.isImportant ? "1" : "0") + '">' +
            '<span><i class="' + starIcon + '"></i></span></a>';

        return '<div class="col-xl-4 col-md-6 note-card" data-note-id="' + note.id + '">' +
            '<div class="card rounded-3 mb-4">' +
            '<div class="card-body p-4">' +
            '<div class="d-flex align-items-center justify-content-between">' +
            '<span class="badge ' + priorityBadge + ' d-inline-flex align-items-center">' +
            '<i class="fas fa-circle fs-6 me-1"></i>' + capitalize(note.priority) + '</span>' +
            '<div>' +
            '<a href="javascript:void(0);" data-bs-toggle="dropdown" aria-expanded="false"><i class="fas fa-ellipsis-v"></i></a>' +
            '<div class="dropdown-menu notes-menu dropdown-menu-end">' +
            (inTrash ? "" : '<a href="javascript:void(0);" class="dropdown-item note-edit-btn" data-id="' + note.id + '" data-bs-toggle="modal" data-bs-target="#edit-note-units"><span><i data-feather="edit"></i></span>Edit</a>') +
            '<a href="javascript:void(0);" class="dropdown-item note-delete-btn" data-id="' + note.id + '" data-bs-toggle="modal" data-bs-target="#delete_modal"><span><i data-feather="trash-2"></i></span>Delete</a>' +
            trashAction +
            '<a href="javascript:void(0);" class="dropdown-item note-view-btn" data-id="' + note.id + '" data-bs-toggle="modal" data-bs-target="#view-note-units"><span><i data-feather="eye"></i></span>View</a>' +
            '</div></div></div>' +
            '<div class="my-3">' +
            '<h5 class="text-truncate mb-1"><a href="javascript:void(0);" class="note-view-btn" data-id="' + note.id + '" data-bs-toggle="modal" data-bs-target="#view-note-units">' + escHtml(note.title) + '</a></h5>' +
            '<p class="mb-3 d-flex align-items-center text-dark"><i class="ti ti-calendar me-1"></i>' + date + '</p>' +
            '<p class="text-truncate line-clamb-2 text-wrap">' + escHtml(note.content || "") + '</p>' +
            '</div>' +
            '<div class="d-flex align-items-center justify-content-between border-top pt-3">' +
            '<span class="' + tagColor + ' d-flex align-items-center"><i class="fas fa-square square-rotate fs-10 me-1"></i>' + capitalize(note.tag) + '</span>' +
            '<div class="d-flex align-items-center">' + importantAction +
            '<a href="javascript:void(0);" class="note-trash-btn" data-id="' + note.id + '">' +
            (inTrash ? '<span><i class="ti ti-refresh text-success"></i></span>' : '<span><i class="ti ti-trash text-danger note-trash-btn" data-id="' + note.id + '"></i></span>') +
            '</a></div></div></div></div></div>';
    }

    function escHtml(str) {
        return String(str).replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;");
    }

    function emptyState(message) {
        return '<div class="col-12 text-center text-muted py-5"><i class="ti ti-notes fs-1 mb-2 d-block"></i>' + message + '</div>';
    }

    /* ------------------------------------------------------------------ */
    /* Render                                                               */
    /* ------------------------------------------------------------------ */
    function renderNotes(containerId, notes, inTrash) {
        var el = document.getElementById(containerId);
        if (!el) return;
        if (!notes || notes.length === 0) {
            el.innerHTML = emptyState("No notes found.");
            return;
        }
        el.innerHTML = notes.map(function (n) { return noteCard(n, inTrash); }).join("");
        if (typeof feather !== "undefined") feather.replace();
        bindCardActions(el, inTrash);
    }

    function renderImportantCarousel(notes) {
        var el = document.getElementById("notes-important-section");
        if (!el) return;
        if (!notes || notes.length === 0) {
            el.innerHTML = "";
            return;
        }
        el.innerHTML =
            '<div class="border-bottom mb-4 pb-4">' +
            '<div class="row"><div class="col-md-12">' +
            '<div class="d-flex align-items-center justify-content-between flex-wrap mb-2">' +
            '<div class="d-flex align-items-center mb-3"><h4>Important Notes</h4></div>' +
            '</div></div>' +
            '<div class="col-md-12"><div class="row" id="notes-important-cards">' +
            notes.map(function (n) { return noteCard(n, false); }).join("") +
            '</div></div></div></div>';
        if (typeof feather !== "undefined") feather.replace();
        var cardsEl = document.getElementById("notes-important-cards");
        if (cardsEl) bindCardActions(cardsEl, false);
    }

    function updateAllNoteCount() {
        var badge = document.querySelector("#v-pills-profile-tab .ms-2");
        if (badge) badge.textContent = state.all.length;
    }

    /* ------------------------------------------------------------------ */
    /* Fetch                                                                */
    /* ------------------------------------------------------------------ */
    function loadAll() {
        noteApiRequest("GET", "/v1/hcm/notes?tab=all").then(function (res) {
            if (res.success) {
                state.all = res.data;
                renderNotes("notes-all-grid", state.all, false);
                // Important subset for carousel
                var imp = state.all.filter(function (n) { return n.isImportant; });
                renderImportantCarousel(imp);
                updateAllNoteCount();
            }
        }).catch(console.error);
    }

    function loadImportant() {
        noteApiRequest("GET", "/v1/hcm/notes?tab=important").then(function (res) {
            if (res.success) {
                state.important = res.data;
                renderNotes("notes-important-grid", state.important, false);
            }
        }).catch(console.error);
    }

    function loadTrash() {
        noteApiRequest("GET", "/v1/hcm/notes?tab=trash").then(function (res) {
            if (res.success) {
                state.trash = res.data;
                renderNotes("notes-trash-grid", state.trash, true);
            }
        }).catch(console.error);
    }

    function reloadAll() { loadAll(); loadImportant(); loadTrash(); }

    /* ------------------------------------------------------------------ */
    /* Add Note                                                             */
    /* ------------------------------------------------------------------ */
    function setupAddForm() {
        var btn = document.getElementById("note-add-submit");
        if (!btn) return;
        btn.addEventListener("click", function () {
            var title    = (document.getElementById("note-add-title") || {}).value || "";
            var content  = (document.getElementById("note-add-content") || {}).value || "";
            var tag      = (document.getElementById("note-add-tag") || {}).value || "personal";
            var priority = (document.getElementById("note-add-priority") || {}).value || "medium";

            if (!title.trim()) {
                alert("Note title is required.");
                return;
            }
            btn.disabled = true;
            noteApiRequest("POST", "/v1/hcm/notes", { title: title.trim(), content: content.trim(), tag: tag, priority: priority })
                .then(function (res) {
                    btn.disabled = false;
                    if (res.success) {
                        var modal = bootstrap.Modal.getInstance(document.getElementById("add_note"));
                        if (modal) modal.hide();
                        document.getElementById("note-add-title").value = "";
                        document.getElementById("note-add-content").value = "";
                        reloadAll();
                    } else {
                        alert((res.error && res.error.message) || "Failed to add note.");
                    }
                })
                .catch(function () { btn.disabled = false; alert("Error adding note."); });
        });
    }

    /* ------------------------------------------------------------------ */
    /* Edit Note                                                            */
    /* ------------------------------------------------------------------ */
    function openEditModal(noteId) {
        var all = state.all.concat(state.important).concat(state.trash);
        var note = null;
        for (var i = 0; i < all.length; i++) {
            if (all[i].id === noteId) { note = all[i]; break; }
        }
        if (!note) return;
        state.editId = noteId;
        var titleEl    = document.getElementById("note-edit-title");
        var contentEl  = document.getElementById("note-edit-content");
        var tagEl      = document.getElementById("note-edit-tag");
        var priorityEl = document.getElementById("note-edit-priority");
        if (titleEl)    titleEl.value    = note.title;
        if (contentEl)  contentEl.value  = note.content || "";
        if (tagEl)      tagEl.value      = note.tag;
        if (priorityEl) priorityEl.value = note.priority;
    }

    function setupEditForm() {
        var btn = document.getElementById("note-edit-submit");
        if (!btn) return;
        btn.addEventListener("click", function () {
            if (!state.editId) return;
            var title    = (document.getElementById("note-edit-title") || {}).value || "";
            var content  = (document.getElementById("note-edit-content") || {}).value || "";
            var tag      = (document.getElementById("note-edit-tag") || {}).value || "personal";
            var priority = (document.getElementById("note-edit-priority") || {}).value || "medium";

            if (!title.trim()) { alert("Note title is required."); return; }
            btn.disabled = true;
            noteApiRequest("PUT", "/v1/hcm/notes/" + state.editId, { title: title.trim(), content: content.trim(), tag: tag, priority: priority })
                .then(function (res) {
                    btn.disabled = false;
                    if (res.success) {
                        var modal = bootstrap.Modal.getInstance(document.getElementById("edit-note-units"));
                        if (modal) modal.hide();
                        state.editId = null;
                        reloadAll();
                    } else {
                        alert((res.error && res.error.message) || "Failed to update note.");
                    }
                })
                .catch(function () { btn.disabled = false; alert("Error updating note."); });
        });
    }

    /* ------------------------------------------------------------------ */
    /* Delete Note                                                          */
    /* ------------------------------------------------------------------ */
    function setupDeleteModal() {
        var btn = document.getElementById("note-delete-confirm");
        if (!btn) return;
        btn.addEventListener("click", function () {
            if (!state.deleteId) return;
            btn.disabled = true;
            noteApiRequest("DELETE", "/v1/hcm/notes/" + state.deleteId)
                .then(function (res) {
                    btn.disabled = false;
                    if (res.success) {
                        var modal = bootstrap.Modal.getInstance(document.getElementById("delete_modal"));
                        if (modal) modal.hide();
                        state.deleteId = null;
                        reloadAll();
                    }
                })
                .catch(function () { btn.disabled = false; });
        });
    }

    /* ------------------------------------------------------------------ */
    /* View Note                                                            */
    /* ------------------------------------------------------------------ */
    function openViewModal(noteId) {
        var all = state.all.concat(state.important).concat(state.trash);
        var note = null;
        for (var i = 0; i < all.length; i++) {
            if (all[i].id === noteId) { note = all[i]; break; }
        }
        if (!note) return;
        var titleEl    = document.getElementById("note-view-title");
        var contentEl  = document.getElementById("note-view-content");
        var priorityEl = document.getElementById("note-view-priority");
        var tagEl      = document.getElementById("note-view-tag");
        if (titleEl)    titleEl.textContent = note.title;
        if (contentEl)  contentEl.textContent = note.content || "";
        if (tagEl)      tagEl.textContent = capitalize(note.tag);
        if (priorityEl) {
            priorityEl.textContent = capitalize(note.priority);
            priorityEl.className = "badge " + (PRIORITY_BADGE[note.priority] || "bg-outline-warning") + " d-inline-flex align-items-center";
        }
    }

    /* ------------------------------------------------------------------ */
    /* Toggle important / trash                                             */
    /* ------------------------------------------------------------------ */
    function toggleImportant(noteId) {
        var all = state.all.concat(state.important).concat(state.trash);
        var note = null;
        for (var i = 0; i < all.length; i++) {
            if (all[i].id === noteId) { note = all[i]; break; }
        }
        if (!note) return;
        noteApiRequest("PUT", "/v1/hcm/notes/" + noteId, { is_important: !note.isImportant })
            .then(function (res) { if (res.success) reloadAll(); })
            .catch(console.error);
    }

    function moveToTrash(noteId) {
        noteApiRequest("PUT", "/v1/hcm/notes/" + noteId, { is_trashed: true })
            .then(function (res) { if (res.success) reloadAll(); })
            .catch(console.error);
    }

    function restoreFromTrash(noteId) {
        noteApiRequest("PUT", "/v1/hcm/notes/" + noteId, { is_trashed: false })
            .then(function (res) { if (res.success) reloadAll(); })
            .catch(console.error);
    }

    /* ------------------------------------------------------------------ */
    /* Card action delegation                                               */
    /* ------------------------------------------------------------------ */
    function bindCardActions(container, inTrash) {
        container.addEventListener("click", function (e) {
            var target = e.target.closest("[data-id]");
            if (!target) return;
            var noteId = parseInt(target.getAttribute("data-id"), 10);
            if (!noteId) return;

            if (target.classList.contains("note-edit-btn")) {
                openEditModal(noteId);
            } else if (target.classList.contains("note-delete-btn")) {
                state.deleteId = noteId;
            } else if (target.classList.contains("note-trash-btn")) {
                e.preventDefault();
                if (inTrash) {
                    restoreFromTrash(noteId);
                } else {
                    moveToTrash(noteId);
                }
            } else if (target.classList.contains("note-restore-btn")) {
                e.preventDefault();
                restoreFromTrash(noteId);
            } else if (target.classList.contains("note-star-btn")) {
                e.preventDefault();
                toggleImportant(noteId);
            } else if (target.classList.contains("note-view-btn")) {
                openViewModal(noteId);
            }
        });
    }

    /* ------------------------------------------------------------------ */
    /* Tab reload on click                                                  */
    /* ------------------------------------------------------------------ */
    function bindTabReload() {
        var importantTab = document.getElementById("v-pills-messages-tab");
        var trashTab     = document.getElementById("v-pills-settings-tab");
        if (importantTab) importantTab.addEventListener("click", loadImportant);
        if (trashTab)     trashTab.addEventListener("click", loadTrash);
    }

    /* ------------------------------------------------------------------ */
    /* Init                                                                 */
    /* ------------------------------------------------------------------ */
    document.addEventListener("DOMContentLoaded", function () {
        loadAll();
        bindTabReload();
        setupAddForm();
        setupEditForm();
        setupDeleteModal();
    });
})();
