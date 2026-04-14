const RemindersManager = {
    token: null,
    baseUrl: '/v1/saas',

    init() {
        this.token = null;
        this.bindEvents();
        this.loadReminders();
        this.loadSummary();
    },

    bindEvents() {
        document.getElementById('btn_send_reminders').addEventListener('click', () => this.sendRemindersNow());
        document.getElementById('filter_reminder_type').addEventListener('change', () => this.loadReminders());
        document.getElementById('search_reminders').addEventListener('input', () => this.loadReminders());
        document.getElementById('btn_reset_reminder_filters').addEventListener('click', () => this.resetFilters());
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

    async loadSummary() {
        const token = await this.getApiToken();
        if (!token) return;

        try {
            // Get aging report for summary
            const response = await fetch(`${this.baseUrl}/reports/aging`, {
                headers: {
                    'Authorization': `Bearer ${token}`,
                    'Accept': 'application/json',
                },
            });

            if (response.ok) {
                const result = await response.json();
                if (result.success) {
                    const data = result.data;
                    document.getElementById('count_overdue').textContent = data.totalInvoices || '0';
                    document.getElementById('amount_overdue').textContent = 'Rp ' + this.formatCurrency(data.totalOverdue);
                }
            }
        } catch (error) {
            console.error('Error loading summary:', error);
        }

        // Get due soon count from invoices
        try {
            const tomorrow = new Date();
            const in7days = new Date();
            in7days.setDate(in7days.getDate() + 7);

            const response = await fetch(`${this.baseUrl}/invoices?from_date=${tomorrow.toISOString().split('T')[0]}&to_date=${in7days.toISOString().split('T')[0]}`, {
                headers: {
                    'Authorization': `Bearer ${token}`,
                    'Accept': 'application/json',
                },
            });

            if (response.ok) {
                const result = await response.json();
                if (result.success) {
                    document.getElementById('count_due_soon').textContent = result.pagination?.total || '0';
                }
            }
        } catch (error) {
            console.error('Error loading due soon count:', error);
        }
    },

    async loadReminders() {
        const token = await this.getApiToken();
        if (!token) {
            this.showToast('Failed to load reminders', 'danger');
            return;
        }

        try {
            // Get all unpaid invoices for reminders
            const response = await fetch(`${this.baseUrl}/invoices?is_paid=0`, {
                headers: {
                    'Authorization': `Bearer ${token}`,
                    'Accept': 'application/json',
                },
            });

            if (!response.ok) throw new Error(`HTTP ${response.status}`);

            const result = await response.json();

            if (result.success) {
                const reminders = this.processReminders(result.data);
                this.renderReminders(reminders);
            } else {
                this.showToast('Failed to load reminders', 'danger');
            }
        } catch (error) {
            console.error('Error loading reminders:', error);
            this.showToast('Error loading reminders', 'danger');
        }
    },

    processReminders(invoices) {
        const now = new Date();
        const reminders = [];

        invoices.forEach(invoice => {
            const dueDate = new Date(invoice.dueDate);
            const daysUntilDue = Math.ceil((dueDate - now) / (1000 * 60 * 60 * 24));

            if (daysUntilDue < 0) {
                // Overdue
                reminders.push({
                    type: 'overdue',
                    invoice: invoice,
                    daysOverdue: Math.abs(daysUntilDue),
                    urgency: Math.abs(daysUntilDue) > 90 ? 'critical' : Math.abs(daysUntilDue) > 30 ? 'high' : 'medium'
                });
            } else if (daysUntilDue <= 7) {
                // Due soon
                reminders.push({
                    type: 'due_soon',
                    invoice: invoice,
                    daysDue: daysUntilDue,
                    urgency: daysUntilDue <= 1 ? 'high' : 'medium'
                });
            }
        });

        return reminders;
    },

    renderReminders(reminders) {
        const container = document.querySelector('[data-reminders-list-container]');
        const filterType = document.getElementById('filter_reminder_type').value;
        const search = document.getElementById('search_reminders').value.toLowerCase();

        let filtered = reminders;
        if (filterType) {
            filtered = reminders.filter(r => r.type === filterType);
        }
        if (search) {
            filtered = filtered.filter(r => r.invoice.companyName.toLowerCase().includes(search));
        }

        if (filtered.length === 0) {
            container.innerHTML = `<div class="card"><div class="card-body text-center text-muted py-4">No reminders at this time</div></div>`;
            return;
        }

        let html = '<div class="card">';
        html += '<div class="table-responsive">';
        html += '<table class="table table-hover mb-0">';
        html += `
            <thead>
                <tr>
                    <th>Invoice #</th>
                    <th>Company</th>
                    <th>Amount</th>
                    <th>Due Date</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
        `;

        filtered.forEach(reminder => {
            const invoice = reminder.invoice;
            let statusBadge = '';
            let statusLabel = '';

            if (reminder.type === 'overdue') {
                statusBadge = `<span class="badge bg-danger">${reminder.daysOverdue} days overdue</span>`;
                statusLabel = 'Overdue';
            } else {
                statusBadge = `<span class="badge bg-warning">${reminder.daysDue} days due</span>`;
                statusLabel = 'Due Soon';
            }

            html += `
                <tr class="${reminder.urgency === 'critical' ? 'table-danger' : reminder.urgency === 'high' ? 'table-warning' : ''}">
                    <td><strong>${invoice.invoiceNumber}</strong></td>
                    <td>${invoice.companyName}</td>
                    <td>Rp ${this.formatCurrency(invoice.amountDue)}</td>
                    <td>${invoice.dueDate}</td>
                    <td>${statusBadge}</td>
                    <td>
                        <div class="btn-group btn-group-sm">
                            <button class="btn btn-outline-primary btn-sm" onclick="RemindersManager.viewInvoice(${invoice.id})">
                                <i class="ti ti-eye"></i> View
                            </button>
                            <button class="btn btn-outline-info btn-sm" onclick="RemindersManager.sendReminder(${invoice.id})">
                                <i class="ti ti-send"></i> Send
                            </button>
                        </div>
                    </td>
                </tr>
            `;
        });

        html += '</tbody></table></div></div>';
        container.innerHTML = html;
    },

    async sendReminder(invoiceId) {
        const token = await this.getApiToken();
        if (!token) {
            this.showToast('Authentication failed', 'danger');
            return;
        }

        try {
            const response = await fetch(`${this.baseUrl}/invoices/${invoiceId}/send-email`, {
                method: 'POST',
                headers: {
                    'Authorization': `Bearer ${token}`,
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
            });

            if (response.ok) {
                this.showToast('Reminder sent successfully', 'success');
                setTimeout(() => this.loadReminders(), 1000);
            } else {
                this.showToast('Failed to send reminder', 'danger');
            }
        } catch (error) {
            console.error('Error sending reminder:', error);
            this.showToast('Error sending reminder', 'danger');
        }
    },

    async sendRemindersNow() {
        const token = await this.getApiToken();
        if (!token) {
            this.showToast('Authentication failed', 'danger');
            return;
        }

        try {
            // In production, this would call an admin endpoint to dispatch the job
            // For now, show a message that the scheduler will run automatically at 08:00
            this.showToast('Payment reminders are scheduled to be sent daily at 08:00 AM. Manual triggering coming soon.', 'info');
        } catch (error) {
            console.error('Error:', error);
            this.showToast('Error', 'danger');
        }
    },

    async viewInvoice(invoiceId) {
        window.location.href = `/saas/invoices?id=${invoiceId}`;
    },

    resetFilters() {
        document.getElementById('filter_reminder_type').value = '';
        document.getElementById('search_reminders').value = '';
        this.loadReminders();
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
                <i class="ti ti-${type === 'success' ? 'check' : type === 'danger' ? 'alert-circle' : 'info-circle'} me-2"></i>
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        `;

        const container = document.getElementById('toast-container') || document.body;
        const toastEl = document.createElement('div');
        toastEl.className = 'position-fixed bottom-0 end-0 p-3';
        toastEl.style.zIndex = '11';
        toastEl.innerHTML = toastHtml;
        document.body.appendChild(toastEl);

        setTimeout(() => toastEl.remove(), 5000);
    }
};

document.addEventListener('DOMContentLoaded', () => RemindersManager.init());
