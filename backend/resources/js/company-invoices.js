const CompanyInvoicesManager = {
    token: null,
    baseUrl: '/v1/saas',
    isCompanyView: true, // Different permissions than admin view

    init() {
        this.token = null;
        this.bindEvents();
        this.loadInvoices();
    },

    bindEvents() {
        document.getElementById('filter_invoice_status').addEventListener('change', () => this.loadInvoices());
        document.getElementById('filter_invoice_paid').addEventListener('change', () => this.loadInvoices());
        document.getElementById('search_invoices').addEventListener('input', () => this.loadInvoices());
        document.getElementById('btn_reset_invoice_filters').addEventListener('change', () => this.resetFilters());
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

    async loadInvoices() {
        const token = await this.getApiToken();
        if (!token) {
            this.showToast('Failed to load invoices', 'danger');
            return;
        }

        try {
            let url = `${this.baseUrl}/invoices`;
            const params = [];

            const status = document.getElementById('filter_invoice_status').value;
            if (status) params.push(`status=${status}`);

            const isPaid = document.getElementById('filter_invoice_paid').value;
            if (isPaid) params.push(`is_paid=${isPaid}`);

            const search = document.getElementById('search_invoices').value;
            if (search) params.push(`search=${encodeURIComponent(search)}`);

            if (params.length) url += '?' + params.join('&');

            const response = await fetch(url, {
                headers: {
                    'Authorization': `Bearer ${token}`,
                    'Accept': 'application/json',
                },
            });

            if (!response.ok) throw new Error(`HTTP ${response.status}`);

            const result = await response.json();
            if (result.success) {
                this.renderInvoices(result.data, result.pagination);
            } else {
                this.showToast('Failed to load invoices', 'danger');
            }
        } catch (error) {
            console.error('Error loading invoices:', error);
            this.showToast('Error loading invoices', 'danger');
        }
    },

    renderInvoices(invoices, pagination) {
        const container = document.querySelector('[data-company-invoices-list-container]');

        if (invoices.length === 0) {
            container.innerHTML = `
                <div class="card">
                    <div class="card-body text-center text-muted py-4">
                        <i class="ti ti-inbox fs-1 mb-3 d-block opacity-50"></i>
                        No invoices found
                    </div>
                </div>
            `;
            return;
        }

        let html = `<div class="card"><div class="table-responsive"><table class="table table-hover mb-0"><thead><tr>
            <th>Invoice #</th>
            <th>Issue Date</th>
            <th>Due Date</th>
            <th>Amount</th>
            <th>Status</th>
            <th>Payment Status</th>
            <th>Actions</th>
        </tr></thead><tbody>`;

        invoices.forEach(invoice => {
            const statusBadgeColor = {
                'draft': 'secondary',
                'sent': 'primary',
                'viewed': 'info',
                'paid': 'success',
                'expired': 'danger'
            }[invoice.status] || 'secondary';

            const paymentBadge = invoice.isPaid 
                ? `<span class="badge bg-success">Paid on ${invoice.paidDate}</span>`
                : invoice.isOverdue
                ? `<span class="badge bg-danger">Overdue by ${this.calculateDaysOverdue(invoice.dueDate)} days</span>`
                : invoice.isDueSoon
                ? `<span class="badge bg-warning">Due in ${this.calculateDaysDue(invoice.dueDate)} days</span>`
                : `<span class="badge bg-secondary">Not yet due</span>`;

            html += `
                <tr>
                    <td><strong>${invoice.invoiceNumber}</strong></td>
                    <td>${invoice.issueDate}</td>
                    <td>${invoice.dueDate}</td>
                    <td>Rp ${this.formatCurrency(invoice.amountDue)}</td>
                    <td><span class="badge bg-${statusBadgeColor}">${invoice.status}</span></td>
                    <td>${paymentBadge}</td>
                    <td>
                        <div class="btn-group btn-group-sm">
                            <button class="btn btn-outline-primary btn-sm" onclick="CompanyInvoicesManager.viewInvoiceDetails(${invoice.id})">
                                <i class="ti ti-eye"></i> View
                            </button>
                            ${!invoice.isPaid ? `<button class="btn btn-outline-success btn-sm" onclick="CompanyInvoicesManager.recordPayment(${invoice.id})">
                                <i class="ti ti-check"></i> Pay
                            </button>` : ''}
                        </div>
                    </td>
                </tr>
            `;
        });

        html += `</tbody></table></div></div>`;

        // Add pagination if needed
        if (pagination && pagination.last_page > 1) {
            html += `<div class="card mt-3"><div class="card-body text-center">
                <p class="text-muted">Page ${pagination.current_page} of ${pagination.last_page} (${pagination.total} total)</p>
            </div></div>`;
        }

        container.innerHTML = html;
    },

    async viewInvoiceDetails(invoiceId) {
        const token = await this.getApiToken();
        if (!token) return;

        try {
            const response = await fetch(`${this.baseUrl}/invoices/${invoiceId}`, {
                headers: {
                    'Authorization': `Bearer ${token}`,
                    'Accept': 'application/json',
                },
            });

            if (response.ok) {
                const result = await response.json();
                if (result.success) {
                    this.showInvoiceModal(result.data);
                }
            }
        } catch (error) {
            console.error('Error viewing invoice:', error);
        }
    },

    showInvoiceModal(invoice) {
        const modal = document.querySelector('[data-company-invoice-modal]');
        if (!modal) return;

        document.querySelector('[data-invoice-modal-number]').textContent = invoice.invoiceNumber;
        document.querySelector('[data-invoice-modal-company]').textContent = invoice.companyName;
        document.querySelector('[data-invoice-modal-amount]').textContent = 'Rp ' + this.formatCurrency(invoice.amountDue);
        document.querySelector('[data-invoice-modal-issue-date]').textContent = invoice.issueDate;
        document.querySelector('[data-invoice-modal-due-date]').textContent = invoice.dueDate;
        document.querySelector('[data-invoice-modal-status]').textContent = invoice.status;
        
        const paymentStatus = invoice.isPaid ? 'Paid' : 'Unpaid';
        document.querySelector('[data-invoice-modal-payment-status]').textContent = paymentStatus;
        document.querySelector('[data-invoice-modal-notes]').textContent = invoice.notes || 'No notes';

        const bs = new bootstrap.Modal(modal);
        bs.show();
    },

    async recordPayment(invoiceId) {
        // This would open a payment modal for the company to record payment
        this.showToast('Payment recording feature coming soon', 'info');
    },

    calculateDaysOverdue(dueDate) {
        const due = new Date(dueDate);
        const now = new Date();
        return Math.ceil((now - due) / (1000 * 60 * 60 * 24));
    },

    calculateDaysDue(dueDate) {
        const due = new Date(dueDate);
        const now = new Date();
        return Math.ceil((due - now) / (1000 * 60 * 60 * 24));
    },

    resetFilters() {
        document.getElementById('filter_invoice_status').value = '';
        document.getElementById('filter_invoice_paid').value = '';
        document.getElementById('search_invoices').value = '';
        this.loadInvoices();
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
                <i class="ti ti-${type === 'success' ? 'check' : 'info-circle'} me-2"></i>
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        `;

        const container = document.body;
        const toastEl = document.createElement('div');
        toastEl.className = 'position-fixed bottom-0 end-0 p-3';
        toastEl.style.zIndex = '11';
        toastEl.innerHTML = toastHtml;
        container.appendChild(toastEl);

        setTimeout(() => toastEl.remove(), 5000);
    }
};

document.addEventListener('DOMContentLoaded', () => CompanyInvoicesManager.init());
