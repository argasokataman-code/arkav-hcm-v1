const ReportsManager = {
    token: null,
    baseUrl: '/v1/saas',

    init() {
        this.token = null; // Will be loaded on demand
        this.bindEvents();
        this.loadReports();
    },

    bindEvents() {
        document.getElementById('period-filter').addEventListener('change', () => this.loadReports());
        document.getElementById('report-type').addEventListener('change', () => this.loadReports());
    },

    async getApiToken() {
        if (this.token) return this.token;

        try {
            const response = await fetch('/api-token', { credentials: 'include' });
            const data = await response.json();
            if (data.token) {
                this.token = data.token;
                return this.token;
            }
        } catch (error) {
            console.error('Failed to get API token:', error);
        }
        return null;
    },

    async loadReports() {
        const period = document.getElementById('period-filter').value || 'monthly';
        const reportType = document.getElementById('report-type').value || 'revenue';
        const token = await this.getApiToken();

        if (!token) {
            this.showToast('Failed to load reports', 'danger');
            return;
        }

        try {
            let endpoint = `${this.baseUrl}/reports/${reportType}?period=${period}`;
            let response = await fetch(endpoint, {
                headers: {
                    'Authorization': `Bearer ${token}`,
                    'Accept': 'application/json',
                },
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            const result = await response.json();

            if (result.success) {
                this.renderReport(reportType, result.data);
            } else {
                this.showToast('Failed to load report: ' + result.error, 'danger');
            }
        } catch (error) {
            console.error('Error loading reports:', error);
            this.showToast('Error loading reports', 'danger');
        }
    },

    renderReport(reportType, data) {
        const container = document.getElementById('report-container');
        container.innerHTML = '';

        switch (reportType) {
            case 'revenue':
                this.renderRevenueReport(data, container);
                break;
            case 'aging':
                this.renderAgingReport(data, container);
                break;
            case 'churn':
                this.renderChurnReport(data, container);
                break;
        }
    },

    renderRevenueReport(data, container) {
        let html = `
            <div class="row mb-4">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">Revenue Report</h5>
                            <div class="row">
                                <div class="col-md-6">
                                    <p class="text-muted">Total Revenue</p>
                                    <h3 class="mb-0">Rp ${this.formatCurrency(data.totalRevenue)}</h3>
                                </div>
                                <div class="col-md-6">
                                    <p class="text-muted">Period</p>
                                    <h3 class="mb-0">${data.period === 'yearly' ? 'Year ' + data.year : 'Month ' + new Date().getMonth() + 1}</h3>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Revenue Breakdown</h5>
                        </div>
                        <div class="card-body">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>${data.period === 'yearly' ? 'Month' : 'Date'}</th>
                                        <th>Revenue</th>
                                        <th>Payment Count</th>
                                    </tr>
                                </thead>
                                <tbody>
        `;

        data.breakdown.forEach(item => {
            html += `
                                    <tr>
                                        <td>${item[data.period === 'yearly' ? 'month' : 'date']}</td>
                                        <td>Rp ${this.formatCurrency(item.total)}</td>
                                        <td>${item.count}</td>
                                    </tr>
            `;
        });

        html += `
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        `;

        container.innerHTML = html;
    },

    renderAgingReport(data, container) {
        let html = `
            <div class="row mb-4">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">Aging Report</h5>
                            <div class="row">
                                <div class="col-md-6">
                                    <p class="text-muted">Total Overdue Amount</p>
                                    <h3 class="mb-0 text-danger">Rp ${this.formatCurrency(data.totalOverdue)}</h3>
                                </div>
                                <div class="col-md-6">
                                    <p class="text-muted">Total Overdue Invoices</p>
                                    <h3 class="mb-0">${data.totalInvoices}</h3>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row mb-4">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Aging Buckets</h5>
                        </div>
                        <div class="card-body">
                            <div class="row text-center">
                                <div class="col-md-3">
                                    <p class="text-muted">Current</p>
                                    <h4>${data.buckets.current}</h4>
                                </div>
                                <div class="col-md-3">
                                    <p class="text-muted">30-60 Days</p>
                                    <h4>${data.buckets['30-60']}</h4>
                                </div>
                                <div class="col-md-3">
                                    <p class="text-muted">60-90 Days</p>
                                    <h4>${data.buckets['60-90']}</h4>
                                </div>
                                <div class="col-md-3">
                                    <p class="text-muted">90+ Days</p>
                                    <h4 class="text-danger">${data.buckets['90+']}</h4>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Overdue Invoices</h5>
                        </div>
                        <div class="card-body">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Invoice #</th>
                                        <th>Company</th>
                                        <th>Amount</th>
                                        <th>Due Date</th>
                                        <th>Days Overdue</th>
                                    </tr>
                                </thead>
                                <tbody>
        `;

        data.invoices.forEach(invoice => {
            html += `
                                    <tr>
                                        <td>${invoice.invoiceNumber}</td>
                                        <td>${invoice.company}</td>
                                        <td>Rp ${this.formatCurrency(invoice.amountDue)}</td>
                                        <td>${invoice.dueDate}</td>
                                        <td><span class="badge bg-danger">${invoice.daysOverdue} days</span></td>
                                    </tr>
            `;
        });

        html += `
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        `;

        container.innerHTML = html;
    },

    renderChurnReport(data, container) {
        let html = `
            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-body">
                            <p class="text-muted mb-2">Active Subscriptions</p>
                            <h3 class="mb-0 text-success">${data.activeSubscriptions}</h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-body">
                            <p class="text-muted mb-2">Cancelled Subscriptions</p>
                            <h3 class="mb-0 text-danger">${data.cancelledSubscriptions}</h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-body">
                            <p class="text-muted mb-2">Churn Rate</p>
                            <h3 class="mb-0 text-warning">${data.churnRate}%</h3>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Churn Breakdown</h5>
                        </div>
                        <div class="card-body">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>${data.period === 'yearly' ? 'Month' : 'Date'}</th>
                                        <th>Cancelled Count</th>
                                        <th>Cancelled Value</th>
                                    </tr>
                                </thead>
                                <tbody>
        `;

        data.breakdown.forEach(item => {
            html += `
                                    <tr>
                                        <td>${item[data.period === 'yearly' ? 'month' : 'date']}</td>
                                        <td>${item.churnCount}</td>
                                        <td>Rp ${this.formatCurrency(item.churnValue)}</td>
                                    </tr>
            `;
        });

        html += `
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        `;

        container.innerHTML = html;
    },

    formatCurrency(value) {
        if (!value) return '0';
        return new Intl.NumberFormat('id-ID', { 
            minimumFractionDigits: 0,
            maximumFractionDigits: 0 
        }).format(value);
    },

    showToast(message, type = 'success') {
        const toastHtml = `
            <div class="alert alert-${type} alert-dismissible fade show" role="alert">
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        `;
        
        const container = document.getElementById('toast-container') || document.body;
        const toastEl = document.createElement('div');
        toastEl.innerHTML = toastHtml;
        container.appendChild(toastEl);

        setTimeout(() => toastEl.remove(), 5000);
    }
};

document.addEventListener('DOMContentLoaded', () => ReportsManager.init());
