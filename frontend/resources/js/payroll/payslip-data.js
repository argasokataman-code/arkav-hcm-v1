(function () {
  const audienceState = {
    isGlobalAdmin: false,
    canViewPayroll: false,
  };

  const formatIdr = (value) => new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    maximumFractionDigits: 2,
  }).format(Number(value || 0));

  async function apiGet(url) {
    if (window.axios) {
      const response = await window.axios.get(url, {
        withCredentials: true,
        headers: { Accept: 'application/json' },
      });
      return response.data;
    }

    const response = await fetch(url, {
      credentials: 'same-origin',
      headers: { Accept: 'application/json' },
    });

    const payload = await response.json().catch(() => ({}));
    if (!response.ok) {
      const error = new Error(payload?.error?.message || 'Request failed');
      error.payload = payload;
      throw error;
    }

    return payload;
  }

  function setText(el, value) {
    if (el) el.textContent = value;
  }

  function isOvertimeRow(row) {
    return String(row?.componentCode || '') === 'upah_lembur';
  }

  function renderRows(container, rows, emptyLabel) {
    if (!container) return;
    if (!Array.isArray(rows) || rows.length === 0) {
      container.innerHTML = '<div class="list-group-item text-muted">' + emptyLabel + '</div>';
      return;
    }

    container.innerHTML = rows.map((row) => {
      const label = row.componentName || row.componentCode || 'Komponen';
      const overtimeBadge = isOvertimeRow(row)
        ? '<span class="badge bg-info-subtle text-info border border-info-subtle ms-2">Overtime</span>'
        : '';
      return '<div class="list-group-item">'
        + '<div class="d-flex align-items-center justify-content-between gap-3">'
        + '<span>' + label + overtimeBadge + '</span>'
        + '<strong>' + formatIdr(row.amount || 0) + '</strong>'
        + '</div>'
        + '</div>';
    }).join('');
  }

  async function fetchLatestPayslipPeriod() {
    const payload = await apiGet('/v1/hcm/payroll/my-slip-latest-period');
    return payload?.data?.period || null;
  }

  async function ensureSelfPayslipAudience() {
    try {
      const payload = await apiGet('/v1/identity/auth/me');
      audienceState.isGlobalAdmin = payload?.success && payload?.data?.hcmGlobalAdmin === true;
      audienceState.canViewPayroll = !!payload?.data?.permissions?.['payroll.view'];
    } catch (_error) {
      // Ignore me-check failure and let API authorization handle page data access.
    }

    return true;
  }

  async function loadPayslip(options = {}) {
    const allowLatestFallback = options.allowLatestFallback === true;
    const yearInput = document.querySelector('[data-payslip-year]');
    const monthInput = document.querySelector('[data-payslip-month]');
    const errorBox = document.querySelector('[data-payslip-error]');
    const emptyBox = document.querySelector('[data-payslip-empty]');
    const contextHint = document.querySelector('[data-payslip-context-hint]');
    const content = document.querySelector('[data-payslip-content]');
    const downloadBtn = document.querySelector('[data-payslip-download]');

    const year = Number(yearInput?.value || 0);
    const month = Number(monthInput?.value || 0);

    if (!year || !month) {
      if (errorBox) {
        errorBox.classList.remove('d-none');
        errorBox.textContent = 'Pilih tahun dan bulan terlebih dahulu.';
      }
      return;
    }

    if (errorBox) {
      errorBox.classList.add('d-none');
      errorBox.textContent = '';
    }

    if (downloadBtn) {
      downloadBtn.classList.add('disabled');
      downloadBtn.setAttribute('aria-disabled', 'true');
      downloadBtn.removeAttribute('href');
    }

    try {
      const payload = await apiGet('/v1/hcm/payroll/my-slip?periodYear=' + year + '&periodMonth=' + month);
      const data = payload?.data || {};
      const period = data.period;
      const run = data.run;
      const employee = data.employee || {};
      const totals = data.totals || {};

      setText(document.querySelector('[data-payslip-slip-no]'), data.slipNumber || '—');
      setText(document.querySelector('[data-payslip-period-label]'), period ? (String(period.periodMonth).padStart(2, '0') + '/' + period.periodYear) : '—');
      setText(document.querySelector('[data-payslip-status]'), run?.status || 'Belum ada run final');
      setText(document.querySelector('[data-payslip-employee-name]'), employee.name || '—');
      setText(document.querySelector('[data-payslip-employee-email]'), employee.email || '—');
      setText(document.querySelector('[data-payslip-employee-designation]'), employee.designation || '—');
      setText(document.querySelector('[data-payslip-employee-team]'), employee.team || '—');

      setText(document.querySelector('[data-payslip-earnings-total]'), formatIdr(totals.earningsTotal || 0));
  setText(document.querySelector('[data-payslip-overtime-total]'), formatIdr(totals.overtimeTotal || data.overtime?.amountTotal || 0));
      setText(document.querySelector('[data-payslip-deductions-total]'), formatIdr(totals.deductionsTotal || 0));
      setText(document.querySelector('[data-payslip-net-pay]'), formatIdr(totals.netPay || 0));

      renderRows(document.querySelector('[data-payslip-earnings]'), data.earnings || [], 'Belum ada komponen pendapatan.');
      renderRows(document.querySelector('[data-payslip-deductions]'), data.deductions || [], 'Belum ada komponen potongan.');

      const hasSlip = !!run;
      if (!hasSlip && allowLatestFallback) {
        const latestPeriod = await fetchLatestPayslipPeriod();
        const latestYear = Number(latestPeriod?.periodYear || 0);
        const latestMonth = Number(latestPeriod?.periodMonth || 0);

        if (latestYear && latestMonth && (latestYear !== year || latestMonth !== month)) {
          if (yearInput) yearInput.value = String(latestYear);
          if (monthInput) monthInput.value = String(latestMonth);
          return loadPayslip({ allowLatestFallback: false });
        }
      }

      if (emptyBox) emptyBox.classList.toggle('d-none', hasSlip);
      if (contextHint) {
        if (!hasSlip && audienceState.isGlobalAdmin && audienceState.canViewPayroll) {
          contextHint.classList.remove('d-none');
          contextHint.textContent = 'Tidak ada slip di company aktif untuk periode ini. Gunakan "Payslip Report" untuk mengecek data seluruh karyawan lintas payroll run.';
        } else {
          contextHint.classList.add('d-none');
          contextHint.textContent = '';
        }
      }
      if (content) content.classList.toggle('d-none', !hasSlip);

      if (hasSlip && downloadBtn && data.downloadUrl) {
        downloadBtn.href = data.downloadUrl;
        downloadBtn.classList.remove('disabled');
        downloadBtn.removeAttribute('aria-disabled');
      }
    } catch (error) {
      if (emptyBox) emptyBox.classList.remove('d-none');
      if (contextHint) {
        contextHint.classList.add('d-none');
        contextHint.textContent = '';
      }
      if (content) content.classList.add('d-none');
      if (errorBox) {
        errorBox.classList.remove('d-none');
        errorBox.textContent = error?.payload?.error?.message || error?.message || 'Gagal memuat slip gaji.';
      }
    }
  }

  window.payslipLoad = loadPayslip;

  function syncAdminShortcut() {
    const shortcut = document.querySelector('[data-payslip-admin-shortcut]');
    if (!shortcut) return;
    shortcut.classList.toggle('d-none', !(audienceState.isGlobalAdmin && audienceState.canViewPayroll));
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', async () => {
      const isSelfAudience = await ensureSelfPayslipAudience();
      if (!isSelfAudience) return;
      syncAdminShortcut();
      loadPayslip({ allowLatestFallback: true });
    });
  } else {
    ensureSelfPayslipAudience().then((isSelfAudience) => {
      if (!isSelfAudience) return;
      syncAdminShortcut();
      loadPayslip({ allowLatestFallback: true });
    });
  }
})();
