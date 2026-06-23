<style>
[data-subscription-checkout-page] {
    --co-primary: #2563eb;
    --co-primary-dark: #1d4ed8;
    --co-primary-rgb: 37, 99, 235;
    --co-primary-subtle: rgba(37, 99, 235, 0.06);
    --co-primary-glow: rgba(37, 99, 235, 0.15);
    --co-ink: #0f172a;
    --co-muted: #64748b;
    --co-soft: #f1f5f9;
    --co-border: rgba(15, 23, 42, 0.07);
    --co-card-shadow: 0 1px 3px rgba(15, 23, 42, 0.06), 0 20px 60px rgba(15, 23, 42, 0.06);
    --co-card-shadow-hover: 0 1px 3px rgba(15, 23, 42, 0.06), 0 28px 64px rgba(15, 23, 42, 0.1);
    --co-radius: 1rem;
    --co-radius-sm: 0.75rem;
}
[data-subscription-checkout-page] .co-card { border:1px solid var(--co-border); box-shadow:var(--co-card-shadow); border-radius:var(--co-radius); transition:box-shadow .25s ease, transform .25s ease; }
[data-subscription-checkout-page] .co-card:hover { box-shadow:var(--co-card-shadow-hover); }
[data-subscription-checkout-page] .co-card-sm { border:1px solid var(--co-border); box-shadow:var(--co-card-shadow); border-radius:var(--co-radius-sm); transition:box-shadow .2s ease; }
[data-subscription-checkout-page] .co-gradient-bar { height:4px; background:linear-gradient(90deg,var(--co-primary),#7c3aed,#0891b2); border-radius:2px; margin-bottom:0; }
[data-subscription-checkout-page] .co-section-title { font-size:1.1rem; font-weight:700; color:var(--co-ink); margin-bottom:.25rem; letter-spacing:-.01em; }
[data-subscription-checkout-page] .co-section-lead { font-size:.92rem; color:var(--co-muted); line-height:1.5; }
[data-subscription-checkout-page] .co-form .form-label { font-size:.88rem; font-weight:600; color:var(--co-ink); margin-bottom:.35rem; }
[data-subscription-checkout-page] .co-form .form-control,
[data-subscription-checkout-page] .co-form .form-select { font-size:.92rem; border-radius:.65rem; border-color:#e2e8f0; transition:border-color .2s ease,box-shadow .2s ease; }
[data-subscription-checkout-page] .co-form .form-control:focus,
[data-subscription-checkout-page] .co-form .form-select:focus { border-color:var(--co-primary); box-shadow:0 0 0 3px var(--co-primary-subtle); }
[data-subscription-checkout-page] .co-meta-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:.75rem; }
@media (max-width:575.98px) { [data-subscription-checkout-page] .co-meta-grid { grid-template-columns:1fr; } }
[data-subscription-checkout-page] .co-meta-item { padding:.85rem 1rem; border-radius:var(--co-radius-sm); background:var(--co-soft); border:1px solid var(--co-border); }
[data-subscription-checkout-page] .co-meta-label { font-size:.72rem; font-weight:700; letter-spacing:.06em; text-transform:uppercase; color:var(--co-muted); margin-bottom:.3rem; }
[data-subscription-checkout-page] .co-meta-value { font-size:.95rem; font-weight:600; color:var(--co-ink); word-break:break-word; }
[data-subscription-checkout-page] .co-meta-row { display:flex; align-items:center; gap:.4rem; }
[data-subscription-checkout-page] .co-invoice-amount { font-size:1.4rem; font-weight:800; color:var(--co-ink); letter-spacing:-.02em; }
[data-subscription-checkout-page] .co-invoice-title { font-size:1rem; font-weight:700; color:var(--co-ink); }
[data-subscription-checkout-page] .co-invoice-due { font-size:.85rem; color:var(--co-muted); }
[data-subscription-checkout-page] .co-invoice-statebar { display:flex; align-items:flex-start; justify-content:space-between; gap:.75rem; padding:.85rem 1rem; border-radius:var(--co-radius-sm); background:var(--co-soft); border:1px solid var(--co-border); margin-top:.85rem; }
[data-subscription-checkout-page] .co-invoice-statecopy { min-width:0; }
[data-subscription-checkout-page] .co-invoice-statecopy .title { font-size:.78rem; font-weight:700; letter-spacing:.04em; text-transform:uppercase; color:#475569; margin-bottom:.2rem; }
[data-subscription-checkout-page] .co-invoice-statecopy .note { font-size:.85rem; color:var(--co-muted); margin-bottom:0; }
[data-subscription-checkout-page] .co-notes { font-size:.9rem; color:var(--co-muted); }
[data-subscription-checkout-page] .co-notes li+li { margin-top:.35rem; }
[data-subscription-checkout-page] .co-focus-shell { max-width:960px; margin:0 auto; }
[data-subscription-checkout-page] .co-focus-card { border:1px solid var(--co-border); box-shadow:0 20px 64px rgba(15,23,42,.08); border-radius:1.25rem; overflow:hidden; }
[data-subscription-checkout-page] .co-focus-hero { display:flex; align-items:flex-start; justify-content:space-between; gap:1rem; margin-bottom:1.25rem; }
[data-subscription-checkout-page] .co-focus-kicker { font-size:.75rem; font-weight:700; letter-spacing:.1em; text-transform:uppercase; color:var(--co-primary); margin-bottom:.35rem; }
[data-subscription-checkout-page] .co-focus-title { font-size:1.6rem; font-weight:800; color:var(--co-ink); margin-bottom:.4rem; letter-spacing:-.02em; line-height:1.25; }
[data-subscription-checkout-page] .co-focus-lead { font-size:.95rem; color:var(--co-muted); margin-bottom:0; max-width:56ch; line-height:1.6; }
[data-subscription-checkout-page] .co-company-pill { display:inline-flex; align-items:center; gap:.45rem; padding:.5rem .85rem; border-radius:999px; background:var(--co-soft); border:1px solid var(--co-border); font-weight:600; font-size:.88rem; color:var(--co-ink); white-space:nowrap; }
[data-subscription-checkout-page] .co-focus-meta { padding:1rem; border-radius:var(--co-radius-sm); background:var(--co-soft); margin-bottom:1rem; }
[data-subscription-checkout-page] .co-focus-actions { margin-top:1rem; display:flex; align-items:center; justify-content:space-between; gap:.75rem; flex-wrap:wrap; }
[data-subscription-checkout-page] .co-focus-footnote { font-size:.88rem; color:var(--co-muted); }
[data-subscription-checkout-page] .co-block-card { border:1px solid var(--co-border); border-radius:var(--co-radius); padding:1.25rem; background:linear-gradient(180deg,#f8fafc 0,#fff 100%); box-shadow:var(--co-card-shadow); }
[data-subscription-checkout-page] .co-block-kicker { font-size:.72rem; font-weight:700; letter-spacing:.1em; text-transform:uppercase; color:var(--co-primary); margin-bottom:.45rem; }
[data-subscription-checkout-page] .co-block-title { font-size:1.1rem; font-weight:700; color:var(--co-ink); margin-bottom:.35rem; }
[data-subscription-checkout-page] .co-block-lead { font-size:.9rem; color:var(--co-muted); margin-bottom:1rem; line-height:1.55; }
[data-subscription-checkout-page] .co-block-steps { display:grid; gap:.75rem; margin:0; padding:0; list-style:none; }
[data-subscription-checkout-page] .co-block-step { display:grid; grid-template-columns:auto 1fr; gap:.75rem; align-items:start; padding:.75rem .85rem; border-radius:var(--co-radius-sm); background:#fff; border:1px solid var(--co-border); transition:transform .2s ease,box-shadow .2s ease; }
[data-subscription-checkout-page] .co-block-step:hover { transform:translateY(-1px); box-shadow:0 4px 12px rgba(15,23,42,.05); }
[data-subscription-checkout-page] .co-block-step-index { width:1.9rem; height:1.9rem; border-radius:999px; background:linear-gradient(135deg,var(--co-primary),var(--co-primary-dark)); color:#fff; font-size:.82rem; font-weight:700; display:inline-flex; align-items:center; justify-content:center; flex-shrink:0; }
[data-subscription-checkout-page] .co-block-step-title { font-size:.9rem; font-weight:700; color:var(--co-ink); margin-bottom:.15rem; }
[data-subscription-checkout-page] .co-block-step-note { font-size:.85rem; color:var(--co-muted); margin-bottom:0; line-height:1.45; }
[data-subscription-checkout-page] .co-block-footnote { margin-top:.9rem; padding-top:.9rem; border-top:1px dashed rgba(15,23,42,.1); font-size:.85rem; color:var(--co-muted); }
[data-subscription-checkout-page] .co-active-card { border:1px solid var(--co-border); box-shadow:var(--co-card-shadow); border-radius:1.25rem; overflow:hidden; }
[data-subscription-checkout-page] .co-active-hero { display:flex; align-items:flex-start; justify-content:space-between; gap:1rem; margin-bottom:1.25rem; }
[data-subscription-checkout-page] .co-active-kicker { font-size:.72rem; letter-spacing:.1em; text-transform:uppercase; font-weight:700; color:var(--co-primary); margin-bottom:.25rem; }
[data-subscription-checkout-page] .co-active-title { font-size:1.65rem; line-height:1.2; margin-bottom:.35rem; color:var(--co-ink); font-weight:800; letter-spacing:-.02em; }
[data-subscription-checkout-page] .co-active-lead { color:var(--co-muted); margin-bottom:0; max-width:62ch; line-height:1.55; }
[data-subscription-checkout-page] .co-active-meta-item { border:1px solid var(--co-border); border-radius:var(--co-radius-sm); padding:.85rem 1rem; background:#fff; min-height:88px; }
[data-subscription-checkout-page] .co-active-plan-shell { border:1px solid var(--co-border); border-radius:var(--co-radius); padding:1.15rem; background:linear-gradient(180deg,rgba(37,99,235,.04),#fff 50%); position:relative; }
[data-subscription-checkout-page] .co-active-plan-shell::before { content:''; position:absolute; top:0; left:0; right:0; height:3px; background:linear-gradient(90deg,var(--co-primary),#7c3aed); border-radius:var(--co-radius) var(--co-radius) 0 0; }
[data-subscription-checkout-page] .co-active-plan-name { font-size:1.2rem; font-weight:700; color:var(--co-ink); }
[data-subscription-checkout-page] .co-active-plan-price { font-size:1.65rem; line-height:1.1; font-weight:800; color:var(--co-primary); letter-spacing:-.02em; }
[data-subscription-checkout-page] .co-active-divider { border-top:1px dashed rgba(15,23,42,.12); margin-top:.85rem; padding-top:.85rem; }
[data-subscription-checkout-page] .co-active-addon-grid { display:grid; grid-template-columns:repeat(2,1fr); gap:.65rem; }
[data-subscription-checkout-page] .co-active-addon-item { border:1px solid var(--co-border); border-radius:var(--co-radius-sm); background:#fff; padding:.7rem .8rem; transition:box-shadow .2s ease; }
[data-subscription-checkout-page] .co-active-addon-item:hover { box-shadow:0 2px 8px rgba(15,23,42,.05); }
[data-subscription-checkout-page] .co-active-addon-name { font-weight:700; color:var(--co-ink); line-height:1.2; font-size:.92rem; }
[data-subscription-checkout-page] .co-active-addon-meta { font-size:.78rem; color:var(--co-muted); }
[data-subscription-checkout-page] .co-active-addon-price { font-weight:700; color:var(--co-primary); font-size:.88rem; }
[data-subscription-checkout-page] .co-btn-gradient { background:linear-gradient(135deg,var(--co-primary),var(--co-primary-dark)); color:#fff; border:none; transition:opacity .2s ease,transform .2s ease,box-shadow .2s ease; }
[data-subscription-checkout-page] .co-btn-gradient:hover { opacity:.92; transform:translateY(-1px); box-shadow:0 8px 24px rgba(37,99,235,.25); color:#fff; }
[data-subscription-checkout-page] .co-btn-gradient:active { transform:translateY(0); }
[data-subscription-checkout-page] .co-success-banner { border:1px solid rgba(16,185,129,.2); background:linear-gradient(135deg,rgba(16,185,129,.06),rgba(16,185,129,.02)); border-radius:var(--co-radius); }
[data-subscription-checkout-page] .co-pulse-dot { width:8px; height:8px; border-radius:50%; background:#10b981; display:inline-block; animation:co-pulse 2s infinite; }
@keyframes co-pulse { 0%,100%{opacity:1;transform:scale(1)} 50%{opacity:.6;transform:scale(1.3)} }

/* ── Package cards ── */
[data-subscription-checkout-page] .co-pkg-card { cursor:pointer; border:1.5px solid var(--co-border,#e2e8f0); border-radius:var(--co-radius-sm); overflow:hidden; background:#fff; transition:all .25s cubic-bezier(.4,0,.2,1); position:relative; }
[data-subscription-checkout-page] .co-pkg-card:hover { transform:translateY(-3px); border-color:rgba(37,99,235,.25); box-shadow:0 12px 28px rgba(15,23,42,.1); }
[data-subscription-checkout-page] .co-pkg-card.is-selected { border-color:var(--co-primary); box-shadow:0 0 0 3px rgba(37,99,235,.12),0 8px 24px rgba(15,23,42,.06); background:linear-gradient(180deg,rgba(37,99,235,.03),#fff 50%); }
[data-subscription-checkout-page] .co-pkg-card.is-selected .co-pkg-card-check { display:flex; }
[data-subscription-checkout-page] .co-pkg-card-top { padding:.85rem .85rem .5rem; border-bottom:1px solid var(--co-soft); }
[data-subscription-checkout-page] .co-pkg-name { font-size:1rem; font-weight:700; color:var(--co-ink); margin-bottom:.15rem; }
[data-subscription-checkout-page] .co-pkg-code { font-size:.7rem; font-weight:600; letter-spacing:.08em; text-transform:uppercase; color:var(--co-muted); }
[data-subscription-checkout-page] .co-pkg-card-body { padding:.65rem .85rem .85rem; }
[data-subscription-checkout-page] .co-pkg-price { margin-bottom:.25rem; display:flex; align-items:baseline; gap:.4rem; flex-wrap:wrap; }
[data-subscription-checkout-page] .co-pkg-price-num { font-size:1.15rem; font-weight:800; color:var(--co-primary); letter-spacing:-.02em; }
[data-subscription-checkout-page] .co-pkg-price-unit { font-size:.72rem; font-weight:600; color:var(--co-muted); }
[data-subscription-checkout-page] .co-pkg-yearly-badge { font-size:.65rem; font-weight:700; background:rgba(16,185,129,.12); color:#059669; padding:.15rem .45rem; border-radius:999px; white-space:nowrap; }
[data-subscription-checkout-page] .co-pkg-desc { font-size:.8rem; color:var(--co-muted); line-height:1.4; margin-bottom:.5rem; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden; }
[data-subscription-checkout-page] .co-pkg-features { list-style:none; padding:0; margin:0; display:grid; gap:.25rem; }
[data-subscription-checkout-page] .co-pkg-features li { font-size:.75rem; color:var(--co-ink); display:flex; align-items:center; gap:.3rem; }
[data-subscription-checkout-page] .co-pkg-features li .ti { font-size:.7rem; color:#10b981; flex-shrink:0; }
[data-subscription-checkout-page] .co-pkg-card-check { position:absolute; top:.5rem; right:.5rem; width:1.3rem; height:1.3rem; background:var(--co-primary); border-radius:50%; display:none; align-items:center; justify-content:center; color:#fff; font-size:.75rem; font-weight:700; box-shadow:0 2px 6px rgba(37,99,235,.3); }

/* ── Addon cards ── */
[data-subscription-checkout-page] .addon-card { cursor:pointer; transition:all .25s cubic-bezier(.4,0,.2,1); border:1.5px solid var(--co-border,#e2e8f0); border-radius:var(--co-radius-sm); padding:1.15rem; background:#fff; position:relative; overflow:hidden; }
[data-subscription-checkout-page] .addon-card::before { content:''; position:absolute; inset:0; background:linear-gradient(135deg,rgba(37,99,235,.03),transparent); opacity:0; transition:opacity .3s ease; }
[data-subscription-checkout-page] .addon-card:hover { transform:translateY(-2px); border-color:rgba(37,99,235,.25); box-shadow:0 12px 28px rgba(15,23,42,.08); }
[data-subscription-checkout-page] .addon-card:hover::before { opacity:1; }
[data-subscription-checkout-page] .addon-card.is-selected { border-color:var(--co-primary); box-shadow:0 0 0 3px rgba(37,99,235,.12),0 8px 24px rgba(15,23,42,.06); background:linear-gradient(180deg,rgba(37,99,235,.04),#fff 60%); }
[data-subscription-checkout-page] .addon-card.is-selected::after { content:'✓'; position:absolute; top:.65rem; right:.65rem; width:1.5rem; height:1.5rem; background:var(--co-primary); border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:.85rem; color:#fff; font-weight:700; box-shadow:0 2px 8px rgba(37,99,235,.3); }
[data-subscription-checkout-page] .addon-card-check { position:absolute; top:.65rem; right:.65rem; width:1.5rem; height:1.5rem; background:#10b981; border-radius:50%; display:none; align-items:center; justify-content:center; color:#fff; font-size:.85rem; z-index:10; box-shadow:0 2px 8px rgba(16,185,129,.3); }
[data-subscription-checkout-page] .addon-card.is-selected .addon-card-check { display:flex; }
[data-subscription-checkout-page] .addon-card-icon { font-size:1.75rem; margin-bottom:.5rem; color:var(--co-primary); display:block; }
[data-subscription-checkout-page] .addon-card-name { font-size:.92rem; font-weight:700; color:var(--co-ink); margin-bottom:.35rem; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden; line-height:1.3; }
[data-subscription-checkout-page] .addon-card-price { font-size:1.1rem; font-weight:800; color:var(--co-primary); margin-bottom:.2rem; letter-spacing:-.01em; }
[data-subscription-checkout-page] .addon-card-price-unit { font-size:.72rem; color:var(--co-muted); font-weight:600; text-transform:uppercase; letter-spacing:.05em; }
[data-subscription-checkout-page] .addon-card-desc { font-size:.8rem; color:var(--co-muted); line-height:1.4; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden; min-height:2.25rem; }

@media (max-width:767.98px) {
    [data-subscription-checkout-page] .co-active-hero,
    [data-subscription-checkout-page] .co-focus-hero { flex-direction:column; }
    [data-subscription-checkout-page] .co-active-title,
    [data-subscription-checkout-page] .co-focus-title { font-size:1.35rem; }
    [data-subscription-checkout-page] .co-active-addon-grid { grid-template-columns:1fr; }
}
</style>
