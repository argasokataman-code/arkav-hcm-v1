/**
 * platform-tax.js
 *
 * Platform Tax Reporting — SPT PPN & PPh 23 untuk Global Super Admin.
 * Konsumsi endpoint:
 *   GET /v1/saas/tax/dashboard?month=YYYY-MM&ppn_rate=11
 *   GET /v1/saas/tax/spt-ppn?month=YYYY-MM&ppn_rate=11
 *   GET /v1/saas/tax/spt-pph23?month=YYYY-MM
 */
(function (window, document) {
  "use strict";

  const API_BASE = "/v1/saas/tax";

  // ─── Utilities ──────────────────────────────────────────────────────────────

  function esc(str) {
    return String(str ?? "")
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#39;");
  }

  function fmtRp(amount) {
    const n = Number(amount ?? 0);
    return "Rp " + n.toLocaleString("id-ID", { minimumFractionDigits: 0, maximumFractionDigits: 0 });
  }

  function fmtDate(dateStr) {
    if (!dateStr) return "—";
    const d = new Date(dateStr);
    if (isNaN(d.getTime())) return String(dateStr);
    return d.toLocaleDateString("id-ID", { day: "2-digit", month: "short", year: "numeric" });
  }

  function apiRequest(method, url) {
    const token = (function () {
      const m = document.cookie.match(/(?:^|;\s*)api_token=([^;]+)/);
      if (m) return decodeURIComponent(m[1]);
      const el = document.querySelector('[data-api-token]');
      return el ? el.getAttribute('data-api-token') : null;
    }());

    const headers = { "Content-Type": "application/json", "Accept": "application/json" };
    if (token) headers["Authorization"] = "Bearer " + token;

    return fetch(url, { method: method, headers: headers })
      .then(function (res) { return res.json(); });
  }

  function showError(msg) {
    const el = document.querySelector('[data-platform-tax-page]');
    if (!el) return;
    const existing = el.querySelector('.tax-error-alert');
    if (existing) existing.remove();
    const div = document.createElement('div');
    div.className = 'alert alert-danger alert-dismissible fade show tax-error-alert mt-2';
    div.innerHTML = '<i class="ti ti-alert-circle me-2"></i>' + esc(msg) +
      '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
    el.insertBefore(div, el.children[1] || null);
  }

  // ─── State ──────────────────────────────────────────────────────────────────

  var state = {
    month: document.getElementById('input_tax_month')
      ? document.getElementById('input_tax_month').value
      : new Date().toISOString().slice(0, 7),
    ppnRate: 11,
    dashboardData: null,
    sptPpnData: null,
    sptPph23Data: null,
    loading: false,
  };

  // ─── Load All ────────────────────────────────────────────────────────────────

  function loadAll() {
    if (state.loading) return;
    state.loading = true;

    var month = state.month;
    var rate = state.ppnRate;

    var dashboardUrl = API_BASE + "/dashboard?month=" + encodeURIComponent(month) + "&ppn_rate=" + encodeURIComponent(rate);
    var ppnUrl = API_BASE + "/spt-ppn?month=" + encodeURIComponent(month) + "&ppn_rate=" + encodeURIComponent(rate);
    var pph23Url = API_BASE + "/spt-pph23?month=" + encodeURIComponent(month);

    setLoadingState(true);

    Promise.all([
      apiRequest("GET", dashboardUrl),
      apiRequest("GET", ppnUrl),
      apiRequest("GET", pph23Url),
    ]).then(function (results) {
      state.loading = false;
      setLoadingState(false);

      var dashRes = results[0];
      var ppnRes = results[1];
      var pph23Res = results[2];

      if (dashRes && dashRes.success) {
        state.dashboardData = dashRes.data;
        renderDashboard(dashRes.data);
      } else {
        showError("Gagal memuat dashboard pajak: " + (dashRes && dashRes.error ? dashRes.error : "Unknown error"));
      }

      if (ppnRes && ppnRes.success) {
        state.sptPpnData = ppnRes.data;
        renderSptPpn(ppnRes.data);
      } else {
        showError("Gagal memuat data SPT PPN.");
      }

      if (pph23Res && pph23Res.success) {
        state.sptPph23Data = pph23Res.data;
        renderSptPph23(pph23Res.data);
      } else {
        showError("Gagal memuat data SPT PPh 23.");
      }

      var printBtn = document.getElementById('btn_print_tax');
      if (printBtn) printBtn.disabled = false;

    }).catch(function (err) {
      state.loading = false;
      setLoadingState(false);
      console.error(err);
      showError("Terjadi kesalahan saat menghitung pajak. Coba lagi.");
    });
  }

  function setLoadingState(isLoading) {
    var btn = document.getElementById('btn_load_tax_data');
    if (!btn) return;
    btn.disabled = isLoading;
    btn.innerHTML = isLoading
      ? '<span class="spinner-border spinner-border-sm me-2"></span>Menghitung...'
      : '<i class="ti ti-calculator me-2"></i>Hitung Kewajiban Pajak';
  }

  // ─── Render: Dashboard ───────────────────────────────────────────────────────

  function renderDashboard(data) {
    var rev = data.revenue_summary || {};
    var taxes = data.tax_obligations || {};

    // KPI Cards
    var cardsHtml = [
      {
        label: "Gross Revenue Bulan Ini",
        value: fmtRp(rev.gross_revenue),
        icon: "ti-coin",
        color: "text-bg-primary",
        note: rev.tenant_count + " tenant, " + rev.invoice_count + " invoice"
      },
      {
        label: "PPN Terutang (" + data.ppn_rate + "%)",
        value: fmtRp(taxes.ppn && taxes.ppn.amount),
        icon: "ti-file-invoice",
        color: "text-bg-danger",
        note: "DPP: " + fmtRp(taxes.ppn && taxes.ppn.dpp)
      },
      {
        label: "PPh 23 Estimasi (2%)",
        value: fmtRp(taxes.pph23 && taxes.pph23.amount),
        icon: "ti-receipt",
        color: "text-bg-warning",
        note: "Dipotong oleh tenant"
      },
      {
        label: "Total Kewajiban Pajak",
        value: fmtRp(data.total_kewajiban_pajak),
        icon: "ti-scale",
        color: "text-bg-dark",
        note: "PPN + PPh 23 (est.)"
      },
    ].map(function (c) {
      return '<div class="col-md-3">' +
        '<div class="card h-100">' +
        '<div class="card-body">' +
        '<div class="d-flex align-items-center justify-content-between mb-2">' +
        '<small class="text-muted">' + esc(c.label) + '</small>' +
        '<span class="badge ' + c.color + ' p-2"><i class="ti ' + c.icon + ' fs-5"></i></span>' +
        '</div>' +
        '<h4 class="fw-bold mb-0">' + esc(c.value) + '</h4>' +
        '<small class="text-muted">' + esc(c.note) + '</small>' +
        '</div></div></div>';
    }).join('');

    var kpiContainer = document.getElementById('kpi_cards_container');
    if (kpiContainer) kpiContainer.innerHTML = cardsHtml;

    // Obligations table
    var tbodyEl = document.getElementById('tax_obligations_tbody');
    if (tbodyEl) {
      var rows = Object.keys(taxes).map(function (key, idx) {
        var t = taxes[key];
        return '<tr>' +
          '<td><strong>' + esc(t.label) + '</strong><br><small class="text-muted">' + esc(t.catatan || '') + '</small></td>' +
          '<td><small>' + esc(t.dasar_hukum || '—') + '</small></td>' +
          '<td class="text-center"><span class="badge text-bg-secondary">' + esc(t.rate) + '%</span></td>' +
          '<td class="text-end">' + fmtRp(t.dpp) + '</td>' +
          '<td class="text-end fw-bold text-danger">' + fmtRp(t.amount) + '</td>' +
          '<td><small>' + esc(t.batas_setor || '—') + '</small></td>' +
          '<td><small>' + esc(t.batas_lapor || '—') + '</small></td>' +
          '<td><code>' + esc(t.kode_akun_pajak || '—') + '</code></td>' +
          '</tr>';
      });
      tbodyEl.innerHTML = rows.join('');
    }

    var totalRow = document.getElementById('tax_total_row');
    var totalAmount = document.getElementById('tax_total_amount');
    if (totalRow) totalRow.style.display = '';
    if (totalAmount) totalAmount.textContent = fmtRp(data.total_kewajiban_pajak);

    // Revenue breakdown
    var revBody = document.getElementById('revenue_breakdown_body');
    if (revBody) {
      revBody.innerHTML =
        '<div class="row g-3">' +
        '<div class="col-md-4">' +
        '<div class="d-flex justify-content-between align-items-center border-bottom py-2"><span>Total Revenue (DPP PPN)</span><strong>' + fmtRp(rev.dpp_ppn) + '</strong></div>' +
        '<div class="d-flex justify-content-between align-items-center border-bottom py-2"><span>Revenue Dibayar</span><strong class="text-success">' + fmtRp(rev.paid_revenue) + '</strong></div>' +
        '<div class="d-flex justify-content-between align-items-center py-2"><span>Revenue Belum Dibayar</span><strong class="text-warning">' + fmtRp(rev.pending_revenue) + '</strong></div>' +
        '</div>' +
        '<div class="col-md-5">' +
        '<div class="alert alert-secondary mb-0 py-2 fs-12">' +
        '<strong>Catatan Regulasi:</strong>' +
        '<ul class="mb-0 mt-1">' +
        '<li>PPN 11% (UU HPP No. 7/2021) berlaku mulai April 2022. Kenaikan ke 12% diamanatkan UU namun masih ditunda pemerintah per Mei 2026.</li>' +
        '<li>PPh 23 (2%) atas jasa dipotong oleh pembayar (tenant badan).</li>' +
        '<li>PPh Final 0,5% (PP 23/2018): ambang omzet tahunan yang relevan adalah Rp 4.800.000.000 (4,8 miliar). Verifikasi kriteria sebelum menerapkan PPh Final.</li>' +
        '<li>PPh Badan 22%: dihitung tahunan oleh akuntan/konsultan.</li>' +
        '</ul>' +
        '</div>' +
        '</div>' +
        '</div>';
    }
  }

  // ─── Render: SPT PPN ────────────────────────────────────────────────────────

  function renderSptPpn(data) {
    var summary = data.summary || {};
    var detail = data.detail_penyerahan || [];

    // Update badges & labels
    setText('ppn_period_label', 'Masa Pajak: ' + (data.masa_pajak || data.period));
    setText('ppn_batas_lapor_badge', 'Batas lapor: ' + fmtDate(data.batas_lapor));
    setText('ppn_total_dpp', fmtRp(summary.total_penyerahan_dpp));
    setText('ppn_total_keluaran', fmtRp(summary.total_ppn_keluaran));
    setText('ppn_total_masukan', fmtRp(summary.total_ppn_masukan));
    setText('ppn_kurang_bayar', fmtRp(summary.ppn_kurang_bayar));

    var tbody = document.getElementById('ppn_detail_tbody');
    if (!tbody) return;

    if (detail.length === 0) {
      tbody.innerHTML = '<tr><td colspan="9" class="text-center text-muted py-4">Tidak ada invoice pada periode ini.</td></tr>';
      return;
    }

    tbody.innerHTML = detail.map(function (row) {
      var statusBadge = row.invoice_status === 'paid'
        ? '<span class="badge text-bg-success">Lunas</span>'
        : '<span class="badge text-bg-warning">Belum Bayar</span>';
      return '<tr>' +
        '<td>' + esc(row.no) + '</td>' +
        '<td><code>' + esc(row.invoice_number || '—') + '</code></td>' +
        '<td>' + fmtDate(row.invoice_date) + '</td>' +
        '<td>' + esc(row.nama_pembeli || '—') + '</td>' +
        '<td><small class="text-muted">' + esc(row.npwp_pembeli || '—') + '</small></td>' +
        '<td class="text-end">' + fmtRp(row.dpp) + '</td>' +
        '<td class="text-center">' + esc(row.ppn_rate) + '%</td>' +
        '<td class="text-end fw-bold">' + fmtRp(row.ppn) + '</td>' +
        '<td class="text-center">' + statusBadge + '</td>' +
        '</tr>';
    }).join('');
  }

  // ─── Render: SPT PPh 23 ─────────────────────────────────────────────────────

  function renderSptPph23(data) {
    var summary = data.summary || {};
    var detail = data.detail_pemotongan || [];

    setText('pph23_period_label', 'Masa Pajak: ' + (data.masa_pajak || data.period));
    setText('pph23_batas_setor_badge', 'Batas setor: ' + fmtDate(data.batas_setor));
    setText('pph23_batas_lapor_badge', 'Batas lapor: ' + fmtDate(data.batas_lapor));
    setText('pph23_total_bruto', fmtRp(summary.total_bruto));
    setText('pph23_total_terutang', fmtRp(summary.total_pph23_terutang));
    setText('pph23_payment_count', (summary.payment_count || 0) + ' pembayaran');

    var tbody = document.getElementById('pph23_detail_tbody');
    if (!tbody) return;

    if (detail.length === 0) {
      tbody.innerHTML = '<tr><td colspan="9" class="text-center text-muted py-4">Tidak ada pembayaran completed pada periode ini.</td></tr>';
      return;
    }

    tbody.innerHTML = detail.map(function (row) {
      return '<tr>' +
        '<td>' + esc(row.no) + '</td>' +
        '<td>' + esc(row.nama_pemotong || '—') + '</td>' +
        '<td><small class="text-muted">' + esc(row.npwp_pemotong || '—') + '</small></td>' +
        '<td><small>' + esc(row.jenis_penghasilan || '—') + '</small></td>' +
        '<td><code>' + esc(row.kode_objek_pajak || '—') + '</code></td>' +
        '<td>' + fmtDate(row.tanggal_bayar) + '</td>' +
        '<td class="text-end">' + fmtRp(row.jumlah_bruto) + '</td>' +
        '<td class="text-center">' + esc(row.tarif_pph23) + '%</td>' +
        '<td class="text-end fw-bold text-danger">' + fmtRp(row.pph23_dipotong) + '</td>' +
        '</tr>';
    }).join('');
  }

  // ─── Helpers ────────────────────────────────────────────────────────────────

  function setText(id, text) {
    var el = document.getElementById(id);
    if (el) el.textContent = text;
  }

  // ─── Events ─────────────────────────────────────────────────────────────────

  document.addEventListener('DOMContentLoaded', function () {
    var monthInput = document.getElementById('input_tax_month');
    var rateInput = document.getElementById('input_ppn_rate');
    var loadBtn = document.getElementById('btn_load_tax_data');
    var printBtn = document.getElementById('btn_print_tax');

    if (monthInput) {
      monthInput.addEventListener('change', function () {
        state.month = this.value || new Date().toISOString().slice(0, 7);
      });
    }

    if (rateInput) {
      rateInput.addEventListener('change', function () {
        var r = parseFloat(this.value) || 11;
        state.ppnRate = Math.max(7, Math.min(15, r));
      });
    }

    if (loadBtn) {
      loadBtn.addEventListener('click', function () {
        if (monthInput) state.month = monthInput.value || new Date().toISOString().slice(0, 7);
        if (rateInput) state.ppnRate = Math.max(7, Math.min(15, parseFloat(rateInput.value) || 11));
        loadAll();
      });
    }

    if (printBtn) {
      printBtn.addEventListener('click', function () {
        window.print();
      });
    }

    // Auto-load on page open
    loadAll();
  });

}(window, document));
