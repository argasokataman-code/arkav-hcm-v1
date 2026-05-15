/**
 * platform-tax.js
 *
 * Platform Tax Reporting — SPT PPN & PPh 23 untuk Global Super Admin.
 * Konsumsi endpoint:
 *   GET /v1/saas/tax/active-ppn-rate
 *   GET /v1/saas/tax/dashboard?month=YYYY-MM&ppn_rate=11
 *   GET /v1/saas/tax/dashboard/export?month=YYYY-MM&ppn_rate=11&format=xlsx
 *   GET /v1/saas/tax/spt-ppn?month=YYYY-MM&ppn_rate=11
 *   GET /v1/saas/tax/spt-ppn/export?month=YYYY-MM&ppn_rate=11&format=xlsx
 *   GET /v1/saas/tax/spt-pph23?month=YYYY-MM
 *   GET /v1/saas/tax/spt-pph23/export?month=YYYY-MM&format=xlsx
 *   GET /v1/saas/tax/spt-pph-badan?year=YYYY
 *   GET /v1/saas/tax/spt-pph-badan/export?year=YYYY&format=xlsx
 */
(function (window, document) {
  "use strict";

  const API_BASE = "/v1/saas/tax";
  const API_PPH_BADAN = "/v1/saas/tax/spt-pph-badan";

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
    sptPphBadanData: null,
    loading: false,
  };

  function setPpnRateDisplay(rate, source) {
    var display = document.getElementById('display_ppn_rate');
    if (!display) return;

    var normalized = Number(rate);
    if (!Number.isFinite(normalized)) normalized = 11;

    var sourceLabel = source === 'compliance_settings'
      ? 'dari Compliance Settings'
      : 'default sistem';

    display.textContent = normalized.toLocaleString('id-ID', {
      minimumFractionDigits: normalized % 1 === 0 ? 0 : 1,
      maximumFractionDigits: 2
    }) + '% (' + sourceLabel + ')';
  }

  function loadActivePpnRate() {
    return apiRequest('GET', API_BASE + '/active-ppn-rate')
      .then(function (res) {
        if (res && res.success && res.data) {
          var rate = parseFloat(res.data.ppn_rate);
          if (!Number.isNaN(rate)) {
            state.ppnRate = Math.max(7, Math.min(15, rate));
          }
          setPpnRateDisplay(state.ppnRate, String(res.data.source || 'default'));
          return;
        }

        setPpnRateDisplay(state.ppnRate, 'default');
      })
      .catch(function () {
        setPpnRateDisplay(state.ppnRate, 'default');
      });
  }

  // ─── Load All ────────────────────────────────────────────────────────────────

  function loadAll() {
    if (state.loading) return;
    state.loading = true;

    setLoadingState(true);

    loadActivePpnRate().then(function () {
      var month = state.month;
      var rate = state.ppnRate;

      var dashboardUrl = API_BASE + "/dashboard?month=" + encodeURIComponent(month) + "&ppn_rate=" + encodeURIComponent(rate);
      var ppnUrl = API_BASE + "/spt-ppn?month=" + encodeURIComponent(month) + "&ppn_rate=" + encodeURIComponent(rate);
      var pph23Url = API_BASE + "/spt-pph23?month=" + encodeURIComponent(month);
      var pphBadanUrl = API_PPH_BADAN + "?year=" + encodeURIComponent(String(month).slice(0, 4));

      return Promise.all([
        apiRequest("GET", dashboardUrl),
        apiRequest("GET", ppnUrl),
        apiRequest("GET", pph23Url),
        apiRequest("GET", pphBadanUrl),
      ]);
    }).then(function (results) {
      state.loading = false;
      setLoadingState(false);

      var dashRes = results[0];
      var ppnRes = results[1];
      var pph23Res = results[2];
      var pphBadanRes = results[3];

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

      if (pphBadanRes && pphBadanRes.success) {
        state.sptPphBadanData = pphBadanRes.data;
        renderSptPphBadan(pphBadanRes.data, state.month);
      } else {
        renderSptPphBadan(null, state.month);
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
        note: "Basis: pembayaran completed"
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
        '<div class="d-flex justify-content-between align-items-center border-bottom py-2"><span>DPP PPN (setelah back-out pajak)</span><strong>' + fmtRp(rev.dpp_ppn) + '</strong></div>' +
        '<div class="d-flex justify-content-between align-items-center border-bottom py-2"><span>Revenue Dibayar</span><strong class="text-success">' + fmtRp(rev.paid_revenue) + '</strong></div>' +
        '<div class="d-flex justify-content-between align-items-center py-2"><span>Revenue Belum Dibayar</span><strong class="text-warning">' + fmtRp(rev.pending_revenue) + '</strong></div>' +
        '</div>' +
        '<div class="col-md-5">' +
        '<div class="alert alert-secondary mb-0 py-2 fs-12">' +
        '<strong>Catatan Regulasi:</strong>' +
        '<ul class="mb-0 mt-1">' +
        '<li>PPN 11% (UU HPP No. 7/2021) berlaku mulai April 2022. Kenaikan ke 12% diamanatkan UU namun masih ditunda pemerintah per Mei 2026.</li>' +
        '<li>PPh 23 (2%) atas jasa dipotong oleh pembayar (tenant badan).</li>' +
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
    setText('ppn_batas_setor_badge', 'Batas setor: ' + fmtDate(data.batas_setor));
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

  function renderSptPphBadan(data, month) {
    var fallbackPeriod = month || state.month || new Date().toISOString().slice(0, 7);
    var fallbackYear = String(fallbackPeriod).slice(0, 4);
    var reportYear = data && data.year ? String(data.year) : fallbackYear;
    setText('pph_badan_period_label', 'Periode acuan: Tahun ' + reportYear);
    setText('pph_badan_batas_pelunasan_badge', 'Pelunasan estimasi: ' + fmtDate(data && data.batas_pelunasan));
    setText('pph_badan_batas_lapor_badge', 'Batas lapor: ' + fmtDate(data && data.batas_lapor));

    var statusBadge = document.getElementById('pph_badan_status_badge');
    var tbody = document.getElementById('pph_badan_detail_tbody');

    if (!data || typeof data !== 'object') {
      setText('pph_badan_taxable_revenue', fmtRp(0));
      setText('pph_badan_tax_payable', fmtRp(0));
      setText('pph_badan_net_revenue', fmtRp(0));
      setText('pph_badan_net_profit', fmtRp(0));
      setText('pph_badan_batas_pelunasan_badge', 'Pelunasan estimasi: —');
      setText('pph_badan_batas_lapor_badge', 'Batas lapor: —');

      if (statusBadge) {
        statusBadge.className = 'badge text-bg-secondary';
        statusBadge.textContent = 'Status: data compliance belum tersedia';
      }

      if (tbody) {
        tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">Data estimasi PPh Badan belum tersedia untuk periode ini.</td></tr>';
      }
      return;
    }

    var summary = data.summary || {};
    var tenants = Array.isArray(data.monthly_breakdown) ? data.monthly_breakdown : [];

    var taxableRevenue = Number(summary.total_taxable_revenue || 0);
    var taxLiability = Number(summary.total_transaction_tax_liability || 0);
    var taxPayable = Number(summary.total_pph_badan_payable || 0);
    var netProfit = Number(summary.total_net_profit_estimate || 0);
    var netRevenue = Math.max(0, taxableRevenue - taxLiability);

    setText('pph_badan_taxable_revenue', fmtRp(taxableRevenue));
    setText('pph_badan_tax_payable', fmtRp(taxPayable));
    setText('pph_badan_net_revenue', fmtRp(netRevenue));
    setText('pph_badan_net_profit', fmtRp(netProfit));

    if (statusBadge) {
      var configured = tenants.some(function (item) { return item && item.policy_configured; });
      statusBadge.className = configured ? 'badge text-bg-success' : 'badge text-bg-warning';
      statusBadge.textContent = configured
        ? 'Status: policy compliance aktif'
        : 'Status: policy compliance belum dikonfigurasi';
    }

    if (!tbody) {
      return;
    }

    if (!tenants.length) {
      tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">Tidak ada data tenant compliance pada periode ini.</td></tr>';
      return;
    }

    tbody.innerHTML = tenants.map(function (item) {
      var tenantName = item.month || '-';
      var tenantTaxableRevenue = Number(item.taxable_revenue || 0);
      var tenantTaxLiability = Number(item.collected_tax_liability || 0);
      if (!tenantTaxLiability) {
        tenantTaxLiability = Number(item.transaction_tax_liability || 0);
      }
      var tenantTaxPayable = Number(item.total_tax_payable || item.tax_amount_due || item.pph_badan_payable || 0);
      var tenantNetProfit = Math.max(0, Number(item.net_profit || item.net_profit_estimate || 0) || (tenantTaxableRevenue - tenantTaxLiability - tenantTaxPayable));
      var statusLabel = tenantTaxPayable > 0 ? 'Terhitung' : 'Tidak Ada Pajak';
      var statusClass = tenantTaxPayable > 0 ? 'badge text-bg-warning' : 'badge text-bg-secondary';

      return '<tr>' +
        '<td>' + esc(tenantName) + '</td>' +
        '<td class="text-end">' + fmtRp(tenantTaxableRevenue) + '</td>' +
        '<td class="text-end">' + fmtRp(tenantTaxLiability) + '</td>' +
        '<td class="text-end fw-semibold">' + fmtRp(tenantTaxPayable) + '</td>' +
        '<td class="text-end">' + fmtRp(tenantNetProfit) + '</td>' +
        '<td><span class="' + statusClass + '">' + esc(statusLabel) + '</span></td>' +
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
    var loadBtn = document.getElementById('btn_load_tax_data');
    var printBtn = document.getElementById('btn_print_tax');

    if (monthInput) {
      monthInput.addEventListener('change', function () {
        state.month = this.value || new Date().toISOString().slice(0, 7);
      });
    }

    if (loadBtn) {
      loadBtn.addEventListener('click', function () {
        if (monthInput) state.month = monthInput.value || new Date().toISOString().slice(0, 7);
        loadAll();
      });
    }

    if (printBtn) {
      printBtn.addEventListener('click', function () {
        if (state.loading) {
          return;
        }

        var activeTabBtn = document.querySelector('#tax-tabs .nav-link.active');
        var activeTarget = activeTabBtn ? activeTabBtn.getAttribute('data-bs-target') : '';
        var month = String(state.month || new Date().toISOString().slice(0, 7));

        if (activeTarget === '#tab-dashboard') {
          var dashboardExportUrl = API_BASE
            + '/dashboard/export?month=' + encodeURIComponent(month)
            + '&ppn_rate=' + encodeURIComponent(state.ppnRate)
            + '&format=xlsx';
          window.open(dashboardExportUrl, '_self');
          return;
        }

        if (activeTarget === '#tab-ppn') {
          var sptPpnExportUrl = API_BASE
            + '/spt-ppn/export?month=' + encodeURIComponent(month)
            + '&ppn_rate=' + encodeURIComponent(state.ppnRate)
            + '&format=xlsx';
          window.open(sptPpnExportUrl, '_self');
          return;
        }

        if (activeTarget === '#tab-pph23') {
          var sptPph23ExportUrl = API_BASE
            + '/spt-pph23/export?month=' + encodeURIComponent(month)
            + '&format=xlsx';
          window.open(sptPph23ExportUrl, '_self');
          return;
        }

        if (activeTarget === '#tab-pph-badan') {
          var year = String(state.month || new Date().toISOString().slice(0, 7)).slice(0, 4);
          var exportUrl = API_PPH_BADAN + '/export?year=' + encodeURIComponent(year) + '&format=xlsx';
          window.open(exportUrl, '_self');
          return;
        }

        var fallbackExportUrl = API_BASE
          + '/dashboard/export?month=' + encodeURIComponent(month)
          + '&ppn_rate=' + encodeURIComponent(state.ppnRate)
          + '&format=xlsx';
        window.open(fallbackExportUrl, '_self');
      });
    }

    // Auto-load on page open
    loadAll();
  });

}(window, document));
