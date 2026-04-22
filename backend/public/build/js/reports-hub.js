/**
 * Reports Hub - Snapshot generation and management
 */

const ReportsHub = {
    modal: null,
    selectedReportType: null,

    getTenantContext() {
        if (window.AuthApi && typeof window.AuthApi.getTenantContext === 'function') {
            return window.AuthApi.getTenantContext() || {};
        }
        return {};
    },

    buildHeaders(payload) {
        const headers = { Accept: 'application/json' };
        const token = window.AuthApi && typeof window.AuthApi.getToken === 'function' ? window.AuthApi.getToken() : null;

        if (token) {
            headers.Authorization = `Bearer ${token}`;
        }

        const tenant = this.getTenantContext();
        if (tenant.companyCode) {
            headers['X-Company-Code'] = tenant.companyCode;
        }
        if (tenant.companyId) {
            headers['X-Company-Id'] = String(tenant.companyId);
        }
        if (tenant.companyUuid) {
            headers['X-Company-UUID'] = String(tenant.companyUuid);
        }

        if (payload && typeof payload === 'object') {
            headers['Content-Type'] = 'application/json';
        }

        return headers;
    },

    apiRequest(method, url, payload) {
        const headers = this.buildHeaders(payload);

        if (window.axios) {
            return window.axios({ method, url, headers, data: payload, withCredentials: true })
                .then((r) => r.data)
                .catch((err) => {
                    const status = err && err.response ? err.response.status : 0;
                    const data = err && err.response ? err.response.data : null;
                    if (window.AuthApi && typeof window.AuthApi.handleUnauthorizedFromApi === 'function') {
                        if (window.AuthApi.handleUnauthorizedFromApi(status, data)) {
                            return null;
                        }
                    }
                    return Promise.reject({ status, data });
                });
        }

        return fetch(url, {
            method: String(method || 'get').toUpperCase(),
            headers,
            credentials: 'same-origin',
            body: payload ? JSON.stringify(payload) : undefined,
        }).then((res) => res.json().catch(() => ({})).then((data) => {
            if (!res.ok) {
                if (window.AuthApi && typeof window.AuthApi.handleUnauthorizedFromApi === 'function') {
                    if (window.AuthApi.handleUnauthorizedFromApi(res.status, data)) {
                        return null;
                    }
                }
                return Promise.reject({ status: res.status, data });
            }
            return data;
        }));
    },

    extractErrorMessage(error, fallback) {
        if (error && error.data && error.data.error && error.data.error.message) {
            return error.data.error.message;
        }

        if (error && error.message) {
            return error.message;
        }

        return fallback;
    },

    init() {
        this.modal = new bootstrap.Modal(document.getElementById('generate_modal'));

        document.querySelectorAll('[data-report-generate]').forEach(btn => {
            btn.addEventListener('click', () => this.openGenerateModal(btn.dataset.reportGenerate));
        });

        document.getElementById('gen_submit').addEventListener('click', () => this.submitGenerate());
        document.getElementById('refresh_snapshots').addEventListener('click', () => this.loadSnapshots());

        // Set default dates (last 30 days)
        const today = new Date().toISOString().split('T')[0];
        const thirtyDaysAgo = new Date(Date.now() - 30 * 24 * 60 * 60 * 1000).toISOString().split('T')[0];

        document.getElementById('gen_period_start').value = thirtyDaysAgo;
        document.getElementById('gen_period_end').value = today;

        // Load initial snapshots
        this.loadSnapshots();
    },

    openGenerateModal(reportType) {
        this.selectedReportType = reportType;
        document.getElementById('gen_report_type').value = reportType;
        this.modal.show();
    },

    async submitGenerate() {
        const form = document.getElementById('generate_form');
        const formData = new FormData(form);

        const payload = {
            reportType: formData.get('reportType'),
            periodStart: formData.get('periodStart'),
            periodEnd: formData.get('periodEnd'),
            async: formData.get('async') ? true : false,
            filters: {},
        };

        try {
            const result = await this.apiRequest('post', '/v1/hcm/reports/snapshots', payload);
            if (!result) {
                return;
            }

            if (result.success) {
                this.showAlert('success', `Snapshot ${payload.async ? 'queued' : 'generated'} successfully!`);
                this.modal.hide();
                setTimeout(() => this.loadSnapshots(), 1000);
            } else {
                this.showAlert('error', result.error?.message || 'Failed to generate snapshot');
            }
        } catch (error) {
            this.showAlert('error', this.extractErrorMessage(error, 'Failed to generate snapshot'));
        }
    },

    async loadSnapshots() {
        const loading = document.getElementById('snapshots_loading');
        const table = document.getElementById('snapshots_table');
        const empty = document.getElementById('snapshots_empty');
        const tbody = document.getElementById('snapshots_tbody');

        loading.style.display = 'block';
        table.style.display = 'none';
        empty.style.display = 'none';

        try {
            const result = await this.apiRequest('get', '/v1/hcm/reports/snapshots?perPage=20&sortBy=generated_at&sortDir=desc');
            if (!result) {
                return;
            }

            loading.style.display = 'none';

            if (result.success && result.data.length > 0) {
                tbody.innerHTML = result.data.map(snapshot => this.renderSnapshotRow(snapshot)).join('');
                table.style.display = 'table';
            } else {
                empty.style.display = 'block';
            }
        } catch (error) {
            loading.style.display = 'none';
            this.showAlert('error', this.extractErrorMessage(error, 'Failed to load snapshots'));
            empty.style.display = 'block';
        }
    },

    renderSnapshotRow(snapshot) {
        const statusBadgeClass = {
            completed: 'bg-success',
            processing: 'bg-warning',
            pending: 'bg-info',
            failed: 'bg-danger',
        }[snapshot.status] || 'bg-secondary';

        const periodText = snapshot.periodStart && snapshot.periodEnd 
            ? `${snapshot.periodStart} to ${snapshot.periodEnd}`
            : 'All';

        return `
            <tr>
                <td>
                    <span class="fw-normal">${this.formatReportType(snapshot.reportType)}</span>
                </td>
                <td>
                    <small class="text-muted">${periodText}</small>
                </td>
                <td>
                    <span class="badge ${statusBadgeClass}">${snapshot.status}</span>
                </td>
                <td>
                    <small class="text-muted">${this.formatDate(snapshot.generatedAt)}</small>
                </td>
                <td>
                    <small class="text-muted">${snapshot.rowCount}</small>
                </td>
                <td class="text-end">
                    <div class="btn-group btn-group-sm" role="group">
                        <button class="btn btn-light" onclick="ReportsHub.viewSnapshot(${snapshot.id})" title="View">
                            <i class="ti ti-eye"></i>
                        </button>
                        ${snapshot.status === 'completed' ? `
                            <button class="btn btn-light" onclick="ReportsHub.exportSnapshot(${snapshot.id})" title="Export">
                                <i class="ti ti-download"></i>
                            </button>
                        ` : ''}
                    </div>
                </td>
            </tr>
        `;
    },

    async viewSnapshot(id) {
        try {
            const result = await this.apiRequest('get', `/v1/hcm/reports/snapshots/${id}`);
            if (!result) {
                return;
            }

            if (result.success) {
                // Display snapshot details in a modal or navigate to detail page
                console.log('Snapshot data:', result.data);
                this.showAlert('info', `Snapshot ID ${id}: ${result.data.rowCount} rows`);
            }
        } catch (error) {
            this.showAlert('error', this.extractErrorMessage(error, 'Failed to load snapshot detail'));
        }
    },

    async exportSnapshot(id) {
        let fileType = null;
        if (window.ArcavUi && typeof window.ArcavUi.selectOption === 'function') {
            fileType = await window.ArcavUi.selectOption({
                title: 'Export Snapshot',
                message: 'Pilih format file untuk export.',
                options: [
                    { value: 'csv', label: 'CSV' },
                    { value: 'excel', label: 'Excel' },
                    { value: 'pdf', label: 'PDF' },
                ],
            });
        }
        if (!fileType) {
            return;
        }

        try {
            const result = await this.apiRequest('post', `/v1/hcm/reports/snapshots/${id}/export`, { fileType });
            if (!result) {
                return;
            }

            if (result.success) {
                this.showAlert('success', 'Export file generated');
                // Could open the file URL here
                if (result.data.fileUrl) {
                    window.open(result.data.fileUrl, '_blank');
                }
            }
        } catch (error) {
            this.showAlert('error', this.extractErrorMessage(error, 'Failed to export snapshot'));
        }
    },

    showAlert(type, message) {
        const container = document.getElementById('alerts_container');
        const alertClass = {
            'success': 'alert-success',
            'error': 'alert-danger',
            'info': 'alert-info',
            'warning': 'alert-warning',
        }[type] || 'alert-info';

        const alert = document.createElement('div');
        alert.className = `alert ${alertClass} alert-dismissible fade show`;
        alert.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;

        container.appendChild(alert);

        setTimeout(() => alert.remove(), 5000);
    },

    formatReportType(type) {
        const map = {
            'attendance': 'Attendance',
            'payroll': 'Payroll',
            'employee': 'Employee',
            'leave': 'Leave',
            'finance': 'Finance',
        };
        return map[type] || type;
    },

    formatDate(isoDate) {
        if (!isoDate) return 'N/A';
        return new Date(isoDate).toLocaleString();
    },
};

window.ReportsHub = ReportsHub;

// Initialize on page load
document.addEventListener('DOMContentLoaded', () => ReportsHub.init());
