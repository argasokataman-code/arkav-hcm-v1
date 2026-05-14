import { formatIdr, isTrialPackage } from '../public/public-landing-contract.js';

export function sanitizePostalCode(value) {
    return String(value ?? '').replace(/\D+/g, '').slice(0, 12);
}

export function sanitizePhone(value) {
    return String(value ?? '').replace(/[^0-9+\-\s().]/g, '').slice(0, 20);
}

export function sanitizePersonName(value) {
    return String(value ?? '').replace(/[^A-Za-z\s'.\-]/g, '').slice(0, 120);
}

export function sanitizePersonRole(value) {
    return String(value ?? '').replace(/[^A-Za-z0-9\s'.\-\/&,]/g, '').slice(0, 120);
}

export const PHONE_FIELDS = ['companyContactPhone', 'ownerPhone'];
export const PERSON_NAME_FIELDS = ['companyContactPersonName', 'ownerName'];
export const PERSON_ROLE_FIELDS = ['companyContactPersonRole'];
export const MAX_LENGTHS = {
    companyName: 255, companyLegalName: 255,
    companyContactPersonName: 120, companyContactPersonRole: 120,
    companyContactPhone: 20, companyCity: 120, companyAddress: 500,
    companyPostalCode: 12, companyCountryCode: 10, companyTimezone: 100,
    companyCurrency: 10, ownerName: 150, ownerEmail: 255, ownerPhone: 20,
    ownerPassword: 64, ownerConfirmPassword: 64, billingEmail: 255,
};

export function getE2ETurnstileToken() {
    if (typeof window === 'undefined') {
        return '';
    }

    return String(window.__ARCAV_E2E_TURNSTILE_TOKEN || '').trim();
}

export function parseError(error) {
    const payload = error?.response?.data || error?.data || null;
    if (!payload || typeof payload !== 'object') {
        return { message: 'Terjadi kesalahan. Coba lagi.', details: [] };
    }

    const errorNode = payload.error || {};
    return {
        message: String(errorNode.message || 'Terjadi kesalahan. Coba lagi.'),
        details: Array.isArray(errorNode.details) ? errorNode.details : [],
    };
}

export function escapeHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/\"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

function isInternalBillingComponent(component) {
    const key = String(component?.key || '').toLowerCase();
    const label = String(component?.label || '').toLowerCase();
    return key === 'addon_markup_rate'
        || key === 'payroll_service_fee'
        || key === 'service_fee'
        || label.includes('corporate tax')
        || label.includes('payroll service fee');
}

export function buildInvoiceBreakdownMessage(invoice) {
    const amountDue = Number(invoice?.amountDue || 0);
    const breakdown = invoice?.pricingBreakdown && typeof invoice.pricingBreakdown === 'object'
        ? invoice.pricingBreakdown
        : null;

    if (!breakdown) {
        return amountDue > 0 ? `Total tagihan: ${formatIdr(amountDue)}` : '';
    }

    const subtotal = Number(breakdown.base_amount || 0);
    const components = Array.isArray(breakdown.components)
        ? breakdown.components.filter((component) => !isInternalBillingComponent(component))
        : [];
    const total = Number(breakdown.total_amount || amountDue || 0);

    const lines = [`Harga paket: ${formatIdr(subtotal)}`];

    if (components.length > 0) {
        components.forEach((component) => {
            const label = String(component?.label || 'Komponen');
            const rate = Number(component?.rate || 0);
            const compAmount = Number(component?.amount || 0);
            lines.push(`${label} ${rate}%: ${formatIdr(compAmount)}`);
        });
    } else {
        const taxRate = Number(breakdown.subscription_tax_rate || 0);
        const taxAmount = Number(breakdown.subscription_tax_amount || 0);
        if (taxAmount > 0 || taxRate > 0) {
            lines.push(`Pajak ${taxRate}%: ${formatIdr(taxAmount)}`);
        }
    }

    lines.push(`Total tagihan: ${formatIdr(total)}`);
    return lines.join('\n');
}

export function buildOnboardingSuccessMessageHtml(params) {
    const companyCode = params?.companyCode ? String(params.companyCode) : '-';
    const ownerEmail = params?.ownerEmail ? String(params.ownerEmail) : '-';
    const invoice = params?.invoice || null;
    const isPendingPayment = Boolean(params?.isPendingPayment);

    let html = '';
    html += '<div class="small text-muted mb-3">';
    html += isPendingPayment
        ? 'Registrasi selesai. Langkah berikutnya adalah menyelesaikan pembayaran agar akun langsung aktif.'
        : 'Registrasi selesai. Akun company Anda siap digunakan.';
    html += '</div>';

    html += '<div class="border rounded-3 p-3 mb-3 bg-light">';
    html += '<div class="fw-semibold mb-2">Informasi akun</div>';
    html += '<div class="d-flex justify-content-between small mb-1"><span class="text-muted">Company code</span><span class="fw-semibold">' + escapeHtml(companyCode) + '</span></div>';
    html += '<div class="d-flex justify-content-between small"><span class="text-muted">Email login</span><span class="fw-semibold">' + escapeHtml(ownerEmail) + '</span></div>';
    html += '</div>';

    if (invoice) {
        const invoiceNumber = invoice.invoiceNumber ? String(invoice.invoiceNumber) : '-';
        const dueDate = invoice.dueDate ? String(invoice.dueDate) : '-';
        html += '<div class="border rounded-3 p-3 mb-3">';
        html += '<div class="fw-semibold mb-2">Ringkasan invoice</div>';
        html += '<div class="d-flex justify-content-between small mb-1"><span class="text-muted">Nomor invoice</span><span class="fw-semibold">' + escapeHtml(invoiceNumber) + '</span></div>';
        html += '<div class="d-flex justify-content-between small mb-1"><span class="text-muted">Total tagihan</span><span class="fw-semibold">' + escapeHtml(formatIdr(invoice.amountDue || 0)) + '</span></div>';
        html += '<div class="d-flex justify-content-between small"><span class="text-muted">Jatuh tempo</span><span class="fw-semibold">' + escapeHtml(dueDate) + '</span></div>';
        html += '</div>';
    }

    html += '<div class="small text-muted">';
    html += isPendingPayment
        ? 'Klik tombol di bawah untuk login dan lanjutkan ke checkout pembayaran.'
        : 'Klik tombol di bawah untuk login ke aplikasi.';
    html += '</div>';

    return html;
}

function apiFieldToFormKey(apiField) {
    return String(apiField || '')
        .split('.')
        .map((segment, segIndex) =>
            segment
                .split('_')
                .map((part, partIndex) =>
                    segIndex === 0 && partIndex === 0 ? part : part.charAt(0).toUpperCase() + part.slice(1)
                )
                .join('')
        )
        .join('');
}

export function buildFieldErrors(details) {
    const map = {};
    (details || []).forEach((detail) => {
        if (!detail.field) return;
        const key = apiFieldToFormKey(detail.field);
        if (!map[key]) map[key] = detail.message || 'Nilai tidak valid';
    });
    return map;
}

export function getInitialFormState(defaultPackage) {
    const useTrialDefaults = isTrialPackage(defaultPackage);

    return {
        packageUuid: defaultPackage?.uuid || '',
        billingCycle: useTrialDefaults ? 'monthly' : 'monthly',
        startMode: useTrialDefaults ? 'trial' : 'trial',
        companyName: '',
        companyLegalName: '',
        companyTimezone: 'Asia/Jakarta',
        companyCurrency: 'IDR',
        companyCountryCode: 'ID',
        companyContactPhone: '',
        companyContactPersonName: '',
        companyContactPersonRole: '',
        companyAddress: '',
        companyCity: '',
        companyPostalCode: '',
        ownerName: '',
        ownerEmail: '',
        ownerPhone: '',
        ownerPassword: '',
        ownerConfirmPassword: '',
        billingEmail: '',
        website: '',
        turnstileToken: '',
        consentAccepted: false,
        _confirmMismatch: false,
    };
}