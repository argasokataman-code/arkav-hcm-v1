/**
 * Arkav AI Chat Widget
 * Floating chat button + slide-up panel.
 * Uses window.AuthApi.request (already loaded via api-client.js).
 *
 * No external dependencies — plain ES5-compatible JS with Bootstrap 5 classes.
 */
(function (window, document) {
    "use strict";

    var WIDGET_ID       = "arcav-ai-chat";
    var SESSION_KEY     = "arcav_ai_session_id";
    var MAX_HISTORY     = 50; // messages kept in DOM

    // ─── State ──────────────────────────────────────────────────────────────
    var sessionId   = null;
    var isOpen      = false;
    var isThinking  = false;

    // ─── Quick-reply suggestions shown at start ──────────────────────────────
    var SUGGESTIONS_EMPLOYEE = [
        "Berapa sisa cuti saya?",
        "Sudah absen hari ini?",
        "Gaji bulan ini berapa?",
        "Status tiket saya?",
    ];
    var SUGGESTIONS_GLOBAL_ADMIN = [
        "Berapa company aktif?",
        "Total revenue bulan ini?",
        "Invoice belum dibayar?",
        "Jumlah karyawan semua company?",
    ];
    // Resolved inside buildWidget() after AuthUser is injected by Blade
    var SUGGESTIONS = SUGGESTIONS_EMPLOYEE;

    // ─── Helpers ─────────────────────────────────────────────────────────────

    function getOrCreateSession() {
        if (!sessionId) {
            try {
                sessionId = sessionStorage.getItem(SESSION_KEY);
            } catch (_e) {}
        }
        if (!sessionId) {
            sessionId = "xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx".replace(/[xy]/g, function (c) {
                var r = (Math.random() * 16) | 0;
                return (c === "x" ? r : (r & 0x3) | 0x8).toString(16);
            });
            try {
                sessionStorage.setItem(SESSION_KEY, sessionId);
            } catch (_e) {}
        }
        return sessionId;
    }

    function escapeHtml(str) {
        return String(str)
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#39;");
    }

    /** Convert newlines + basic markdown **bold** to HTML. */
    function formatReply(text) {
        var safe = escapeHtml(text);
        // **bold**
        safe = safe.replace(/\*\*([^*]+)\*\*/g, "<strong>$1</strong>");
        // bullet lines starting with - or •
        safe = safe.replace(/(^|\n)([-•])\s+(.+)/g, "$1<li>$3</li>");
        // wrap consecutive <li> blocks
        safe = safe.replace(/(<li>[\s\S]*?<\/li>)+/g, function (m) {
            return '<ul class="ai-reply-list mb-1">' + m + "</ul>";
        });
        // newlines → <br>
        safe = safe.replace(/\n/g, "<br>");
        return safe;
    }

    function el(id) {
        return document.getElementById(id);
    }

    // ─── Build widget HTML ───────────────────────────────────────────────────

    function buildWidget() {
        // Resolve correct suggestions based on user role (AuthUser injected by Blade)
        SUGGESTIONS = (window.AuthUser && window.AuthUser.hcmGlobalAdmin)
            ? SUGGESTIONS_GLOBAL_ADMIN
            : SUGGESTIONS_EMPLOYEE;

        var wrapper = document.createElement("div");
        wrapper.id = WIDGET_ID;
        wrapper.innerHTML = [
            // Floating toggle button
            '<button id="ai-chat-toggle" class="ai-chat-toggle" aria-label="AI Assistant" title="Tanya AI Arkav">',
            '  <span class="ai-chat-toggle-icon">',
            '    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">',
            '      <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>',
            '    </svg>',
            '  </span>',
            '  <span class="ai-chat-toggle-close d-none">',
            '    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">',
            '      <line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line>',
            '    </svg>',
            '  </span>',
            '</button>',

            // Chat panel
            '<div id="ai-chat-panel" class="ai-chat-panel" role="dialog" aria-label="AI Assistant" aria-hidden="true">',
            '  <div class="ai-chat-header">',
            '    <div class="ai-chat-header-info">',
            '      <span class="ai-chat-avatar"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 8v4l3 3"/></svg></span>',
            '      <div>',
            '        <div class="ai-chat-header-title">Arkav AI</div>',
            '        <div class="ai-chat-header-sub">Asisten HRMS kamu</div>',
            '      </div>',
            '    </div>',
            '    <button class="ai-chat-clear-btn" id="ai-chat-clear" title="Mulai chat baru">',
            '      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 .49-3.1"/></svg>',
            '    </button>',
            '  </div>',

            '  <div class="ai-chat-messages" id="ai-chat-messages" role="log" aria-live="polite">',
            '    <div id="ai-chat-welcome" class="ai-chat-welcome">',
            '      <p class="ai-chat-welcome-text">' + ((window.AuthUser && window.AuthUser.hcmGlobalAdmin) ? 'Hai! Saya bisa bantu tampilkan data SaaS: company aktif, billing, revenue, dan statistik platform.' : 'Hai! Saya bisa bantu jawab pertanyaan seputar cuti, absensi, payslip, dan data HR kamu.') + '</p>',
            '      <div class="ai-chat-suggestions" id="ai-chat-suggestions">',
            SUGGESTIONS.map(function (s) {
                return '<button class="ai-suggestion-chip" data-msg="' + escapeHtml(s) + '">' + escapeHtml(s) + '</button>';
            }).join(""),
            '      </div>',
            '    </div>',
            '  </div>',

            '  <div class="ai-chat-footer">',
            '    <form id="ai-chat-form" class="ai-chat-form" autocomplete="off">',
            '      <input id="ai-chat-input" class="ai-chat-input" type="text" placeholder="Tanya sesuatu..." maxlength="500" autocomplete="off" />',
            '      <button type="submit" id="ai-chat-send" class="ai-chat-send-btn" aria-label="Kirim">',
            '        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>',
            '      </button>',
            '    </form>',
            '    <div class="ai-chat-disclaimer">Jawaban berdasarkan data HRMS kamu. Verifikasi ke HR jika perlu.</div>',
            '  </div>',
            '</div>',
        ].join("\n");

        document.body.appendChild(wrapper);
    }

    // ─── CSS ─────────────────────────────────────────────────────────────────

    function injectStyles() {
        var css = [
            "#arcav-ai-chat { position: fixed; bottom: 24px; right: 24px; z-index: 9050; font-family: inherit; }",

            // Toggle button
            ".ai-chat-toggle {",
            "  width: 52px; height: 52px; border-radius: 50%;",
            "  background: #1b5e91; color: #fff; border: none;",
            "  box-shadow: 0 4px 16px rgba(27,94,145,.35);",
            "  cursor: pointer; display: flex; align-items: center; justify-content: center;",
            "  transition: background .2s, transform .2s, box-shadow .2s;",
            "  position: relative;",
            "}",
            ".ai-chat-toggle:hover { background: #154d78; transform: scale(1.07); box-shadow: 0 6px 20px rgba(27,94,145,.45); }",
            ".ai-chat-toggle:focus-visible { outline: 3px solid #5ba3d9; outline-offset: 3px; }",

            // Panel
            ".ai-chat-panel {",
            "  position: absolute; bottom: 64px; right: 0;",
            "  width: 340px; max-height: 520px;",
            "  background: #fff; border-radius: 16px;",
            "  box-shadow: 0 8px 40px rgba(0,0,0,.18);",
            "  display: flex; flex-direction: column; overflow: hidden;",
            "  transform: translateY(12px) scale(.97); opacity: 0; pointer-events: none;",
            "  transition: transform .22s ease, opacity .22s ease;",
            "}",
            ".ai-chat-panel.ai-panel-open { transform: translateY(0) scale(1); opacity: 1; pointer-events: auto; }",

            // Header
            ".ai-chat-header {",
            "  background: #1b5e91; color: #fff; padding: 14px 16px;",
            "  display: flex; align-items: center; justify-content: space-between; flex-shrink: 0;",
            "}",
            ".ai-chat-header-info { display: flex; align-items: center; gap: 10px; }",
            ".ai-chat-avatar { width: 30px; height: 30px; border-radius: 50%; background: rgba(255,255,255,.2);",
            "  display: flex; align-items: center; justify-content: center; flex-shrink: 0; }",
            ".ai-chat-header-title { font-size: .88rem; font-weight: 700; line-height: 1.2; }",
            ".ai-chat-header-sub { font-size: .72rem; opacity: .75; }",
            ".ai-chat-clear-btn { background: none; border: none; color: rgba(255,255,255,.7); cursor: pointer; padding: 4px; border-radius: 6px; transition: color .15s; }",
            ".ai-chat-clear-btn:hover { color: #fff; background: rgba(255,255,255,.12); }",

            // Messages area
            ".ai-chat-messages {",
            "  flex: 1; overflow-y: auto; padding: 14px 14px 8px;",
            "  display: flex; flex-direction: column; gap: 10px;",
            "  scroll-behavior: smooth;",
            "}",
            ".ai-chat-messages::-webkit-scrollbar { width: 4px; }",
            ".ai-chat-messages::-webkit-scrollbar-track { background: transparent; }",
            ".ai-chat-messages::-webkit-scrollbar-thumb { background: #d0d7de; border-radius: 4px; }",

            // Bubble
            ".ai-bubble { max-width: 85%; padding: 8px 12px; border-radius: 12px; font-size: .82rem; line-height: 1.5; word-break: break-word; }",
            ".ai-bubble-user { align-self: flex-end; background: #1b5e91; color: #fff; border-bottom-right-radius: 4px; }",
            ".ai-bubble-bot  { align-self: flex-start; background: #f0f4f8; color: #1a2533; border-bottom-left-radius: 4px; }",
            ".ai-reply-list  { padding-left: 18px; margin: 4px 0; }",
            ".ai-reply-list li { margin-bottom: 2px; }",

            // Thinking dots
            ".ai-thinking { align-self: flex-start; display: flex; gap: 5px; padding: 10px 14px; background: #f0f4f8; border-radius: 12px; border-bottom-left-radius: 4px; }",
            ".ai-thinking span { width: 7px; height: 7px; border-radius: 50%; background: #8ea9c1; display: inline-block; animation: aiDot 1.2s infinite ease-in-out; }",
            ".ai-thinking span:nth-child(2) { animation-delay: .2s; }",
            ".ai-thinking span:nth-child(3) { animation-delay: .4s; }",
            "@keyframes aiDot { 0%,80%,100% { transform: scale(.7); opacity: .5; } 40% { transform: scale(1); opacity: 1; } }",

            // Welcome
            ".ai-chat-welcome { display: flex; flex-direction: column; gap: 10px; }",
            ".ai-chat-welcome-text { font-size: .82rem; color: #4b5a68; margin: 0; }",
            ".ai-chat-suggestions { display: flex; flex-wrap: wrap; gap: 6px; }",
            ".ai-suggestion-chip { font-size: .75rem; padding: 5px 10px; border-radius: 20px;",
            "  border: 1.5px solid #c2d4e5; background: #f4f8fb; color: #1b5e91;",
            "  cursor: pointer; transition: background .15s, border-color .15s; white-space: nowrap; }",
            ".ai-suggestion-chip:hover { background: #ddeaf5; border-color: #1b5e91; }",

            // Footer
            ".ai-chat-footer { padding: 10px 12px 12px; border-top: 1px solid #e8edf2; flex-shrink: 0; }",
            ".ai-chat-form { display: flex; gap: 8px; align-items: center; }",
            ".ai-chat-input {",
            "  flex: 1; border: 1.5px solid #d0d7de; border-radius: 20px;",
            "  padding: 7px 14px; font-size: .82rem; outline: none; background: #f8fafc;",
            "  transition: border-color .15s;",
            "}",
            ".ai-chat-input:focus { border-color: #1b5e91; background: #fff; }",
            ".ai-chat-send-btn {",
            "  width: 34px; height: 34px; flex-shrink: 0; border-radius: 50%;",
            "  background: #1b5e91; color: #fff; border: none; cursor: pointer;",
            "  display: flex; align-items: center; justify-content: center;",
            "  transition: background .15s;",
            "}",
            ".ai-chat-send-btn:hover { background: #154d78; }",
            ".ai-chat-send-btn:disabled { background: #a0b5c5; cursor: default; }",
            ".ai-chat-disclaimer { font-size: .68rem; color: #9aabba; text-align: center; margin-top: 6px; }",

            // Denied bubble style
            ".ai-bubble-denied { background: #fff8e1; color: #7a5500; border-left: 3px solid #f5c518; }",

            // Responsive
            "@media (max-width: 400px) {",
            "  #arcav-ai-chat { bottom: 16px; right: 12px; }",
            "  .ai-chat-panel { width: calc(100vw - 24px); right: 0; }",
            "}",
        ].join("\n");

        var style = document.createElement("style");
        style.id = "arcav-ai-chat-styles";
        style.textContent = css;
        document.head.appendChild(style);
    }

    // ─── DOM helpers ─────────────────────────────────────────────────────────

    function appendBubble(role, htmlContent) {
        var messages = el("ai-chat-messages");
        if (!messages) { return; }

        // Hide welcome on first real message
        var welcome = el("ai-chat-welcome");
        if (welcome) { welcome.style.display = "none"; }

        var div = document.createElement("div");
        if (role === "user") {
            div.className = "ai-bubble ai-bubble-user";
            div.textContent = htmlContent; // user input is plain text (safe)
        } else if (role === "denied") {
            div.className = "ai-bubble ai-bubble-bot ai-bubble-denied";
            div.innerHTML = htmlContent;
        } else {
            div.className = "ai-bubble ai-bubble-bot";
            div.innerHTML = htmlContent;
        }
        messages.appendChild(div);

        // Prune old messages
        var bubbles = messages.querySelectorAll(".ai-bubble");
        if (bubbles.length > MAX_HISTORY) {
            bubbles[0].remove();
        }

        messages.scrollTop = messages.scrollHeight;
    }

    function showThinking() {
        var messages = el("ai-chat-messages");
        if (!messages) { return; }
        var dot = document.createElement("div");
        dot.className = "ai-thinking";
        dot.id = "ai-thinking-dots";
        dot.innerHTML = "<span></span><span></span><span></span>";
        messages.appendChild(dot);
        messages.scrollTop = messages.scrollHeight;
    }

    function hideThinking() {
        var dot = el("ai-thinking-dots");
        if (dot) { dot.remove(); }
    }

    function setInputDisabled(disabled) {
        var input = el("ai-chat-input");
        var btn   = el("ai-chat-send");
        if (input) { input.disabled = disabled; }
        if (btn)   { btn.disabled = disabled; }
    }

    // ─── Panel open/close ─────────────────────────────────────────────────────

    function openPanel() {
        var panel   = el("ai-chat-panel");
        var iconOpen  = document.querySelector(".ai-chat-toggle-icon");
        var iconClose = document.querySelector(".ai-chat-toggle-close");
        if (!panel) { return; }
        isOpen = true;
        panel.classList.add("ai-panel-open");
        panel.setAttribute("aria-hidden", "false");
        if (iconOpen)  { iconOpen.classList.add("d-none"); }
        if (iconClose) { iconClose.classList.remove("d-none"); }
        var input = el("ai-chat-input");
        if (input) { setTimeout(function () { input.focus(); }, 220); }
    }

    function closePanel() {
        var panel   = el("ai-chat-panel");
        var iconOpen  = document.querySelector(".ai-chat-toggle-icon");
        var iconClose = document.querySelector(".ai-chat-toggle-close");
        if (!panel) { return; }
        isOpen = false;
        panel.classList.remove("ai-panel-open");
        panel.setAttribute("aria-hidden", "true");
        if (iconOpen)  { iconOpen.classList.remove("d-none"); }
        if (iconClose) { iconClose.classList.add("d-none"); }
    }

    function clearChat() {
        var messages = el("ai-chat-messages");
        if (!messages) { return; }
        // Remove all bubbles
        var bubbles = messages.querySelectorAll(".ai-bubble, .ai-thinking");
        bubbles.forEach(function (b) { b.remove(); });
        // Restore welcome
        var welcome = el("ai-chat-welcome");
        if (welcome) { welcome.style.display = ""; }
        // New session
        sessionId = null;
        try { sessionStorage.removeItem(SESSION_KEY); } catch (_e) {}
    }

    // ─── Send message ─────────────────────────────────────────────────────────

    function sendMessage(text) {
        text = String(text || "").trim();
        if (!text || isThinking) { return; }

        appendBubble("user", text);
        setInputDisabled(true);
        isThinking = true;
        showThinking();

        var payload = {
            message:    text,
            session_id: getOrCreateSession(),
        };

        if (!window.AuthApi || typeof window.AuthApi.request !== "function") {
            hideThinking();
            appendBubble("bot", "Maaf, client tidak tersedia.");
            setInputDisabled(false);
            isThinking = false;
            return;
        }

        window.AuthApi.request("post", "/hcm/ai/chat", payload)
            .then(function (res) {
                var data = (res && res.data && res.data.data) ? res.data.data : (res && res.data) ? res.data : {};
                var reply   = data.reply    || "Maaf, tidak ada jawaban dari AI.";
                var allowed = data.allowed  !== false;

                hideThinking();

                if (!allowed) {
                    appendBubble("denied", escapeHtml(reply));
                } else {
                    appendBubble("bot", formatReply(reply));
                }
            })
            .catch(function (err) {
                hideThinking();
                var msg = "Maaf, terjadi kesalahan. Silakan coba lagi.";
                if (err && err.response && err.response.status === 429) {
                    msg = "Terlalu banyak permintaan. Coba lagi sebentar ya.";
                }
                appendBubble("denied", escapeHtml(msg));
            })
            .finally(function () {
                setInputDisabled(false);
                isThinking = false;
                var input = el("ai-chat-input");
                if (input) { input.focus(); }
            });
    }

    // ─── Event wiring ─────────────────────────────────────────────────────────

    function bindEvents() {
        // Toggle
        document.addEventListener("click", function (e) {
            var toggle = e.target.closest("#ai-chat-toggle");
            if (toggle) {
                if (isOpen) { closePanel(); } else { openPanel(); }
                return;
            }

            // Clear
            var clear = e.target.closest("#ai-chat-clear");
            if (clear) { clearChat(); return; }

            // Suggestion chips
            var chip = e.target.closest(".ai-suggestion-chip");
            if (chip) {
                var msg = chip.getAttribute("data-msg");
                if (msg) { sendMessage(msg); }
                return;
            }

            // Close on outside click
            var panel  = el("ai-chat-panel");
            var widget = el(WIDGET_ID);
            if (isOpen && panel && widget && !widget.contains(e.target)) {
                closePanel();
            }
        });

        // Form submit
        var form = el("ai-chat-form");
        if (form) {
            form.addEventListener("submit", function (e) {
                e.preventDefault();
                var input = el("ai-chat-input");
                if (input) {
                    sendMessage(input.value);
                    input.value = "";
                }
            });
        }

        // Enter key (not shift+enter)
        var input = el("ai-chat-input");
        if (input) {
            input.addEventListener("keydown", function (e) {
                if (e.key === "Enter" && !e.shiftKey) {
                    e.preventDefault();
                    var form2 = el("ai-chat-form");
                    if (form2) { form2.dispatchEvent(new Event("submit")); }
                }
            });
        }

        // Escape closes panel
        document.addEventListener("keydown", function (e) {
            if (e.key === "Escape" && isOpen) { closePanel(); }
        });
    }

    // ─── Init ─────────────────────────────────────────────────────────────────

    function init() {
        // Show widget when user is logged in:
        // 1. localStorage token (primary — set by JS login flow)
        // 2. window.AuthUser.id injected by Blade (session/cookie auth fallback)
        var hasToken = false;
        try {
            hasToken = !!window.localStorage.getItem("arcav_access_token");
        } catch (_e) {}
        var hasSession = !!(window.AuthUser && window.AuthUser.id);
        if (!hasToken && !hasSession) { return; }

        injectStyles();
        buildWidget();
        bindEvents();
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", init);
    } else {
        init();
    }

})(window, document);
