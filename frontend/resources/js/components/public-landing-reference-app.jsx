import React, { useEffect, useEffectEvent, useLayoutEffect, useMemo, useRef, useState } from 'react';
import { AnimatePresence, motion, useInView, useScroll, useTransform } from 'framer-motion';
import {
    ArrowRight,
    CalendarBlank,
    CalendarCheck,
    ChartBar,
    ChartLineUp,
    CheckCircle,
    CurrencyCircleDollar,
    FileText,
    Lightning,
    SquaresFour,
    Target,
    User,
    Users,
    UsersFour,
    X,
} from '@phosphor-icons/react';

import { buildLandingOnboardingPayload, formatIdr, isTrialPackage } from '../public-landing-contract.js';

function sanitizePostalCode(value) {
    return String(value ?? '').replace(/\D+/g, '').slice(0, 12);
}

function sanitizePhone(value) {
    // Allow only: digits, +, -, space, (, ), dot — max 20 (E.164 + formatting)
    return String(value ?? '').replace(/[^0-9+\-\s().]/g, '').slice(0, 20);
}

function sanitizePersonName(value) {
    // Letters, spaces, apostrophe, dot, dash only
    return String(value ?? '').replace(/[^A-Za-z\s'.\-]/g, '').slice(0, 120);
}

function sanitizePersonRole(value) {
    // Letters, digits, spaces, common punctuation for job titles
    return String(value ?? '').replace(/[^A-Za-z0-9\s'.\-\/&,]/g, '').slice(0, 120);
}

const PHONE_FIELDS = ['companyContactPhone', 'ownerPhone'];
const PERSON_NAME_FIELDS = ['companyContactPersonName', 'ownerName'];
const PERSON_ROLE_FIELDS = ['companyContactPersonRole'];
const MAX_LENGTHS = {
    companyName: 255, companyLegalName: 255,
    companyContactPersonName: 120, companyContactPersonRole: 120,
    companyContactPhone: 20, companyCity: 120, companyAddress: 500,
    companyPostalCode: 12, companyCountryCode: 10, companyTimezone: 100,
    companyCurrency: 10, ownerName: 150, ownerEmail: 255, ownerPhone: 20,
    ownerPassword: 64, ownerConfirmPassword: 64, billingEmail: 255,
};

function getE2ETurnstileToken() {
    if (typeof window === 'undefined') {
        return '';
    }

    return String(window.__ARCAV_E2E_TURNSTILE_TOKEN || '').trim();
}

const featureCards = [
    {
        icon: CalendarCheck,
        title: 'Absensi Digital',
        description: 'Clock in/out, monitoring keterlambatan, dan ringkasan kehadiran dalam satu dashboard.',
    },
    {
        icon: FileText,
        title: 'Manajemen Cuti',
        description: 'Approval flow yang jelas untuk employee, manager, dan admin tanpa pindah jalur kerja.',
    },
    {
        icon: ChartLineUp,
        title: 'Payroll Auto',
        description: 'Draft payroll, review, invoice, dan status billing tetap terbaca dari flow yang sama.',
    },
    {
        icon: UsersFour,
        title: 'Employee Portal',
        description: 'Self-service karyawan dan owner login tetap terhubung ke konteks tenant yang benar.',
    },
];

const solutionCards = [
    {
        icon: Lightning,
        title: 'Setup Instan',
        description: 'Trial, paket, dan company onboarding tetap bisa dimulai dalam hitungan menit.',
    },
    {
        icon: Users,
        title: 'Role-Based Access',
        description: 'Landing membantu keputusan awal, sedangkan akses tetap dijaga di backend sesuai role.',
    },
    {
        icon: Target,
        title: 'Audit Trail',
        description: 'Status onboarding, billing, dan operasional tetap rapi untuk kebutuhan audit dan compliance.',
    },
];

const steps = [
    {
        step: '01',
        title: 'Pilih Paket',
        subtitle: 'Aktifkan sesuai kebutuhan tim',
        description: 'Mulai dari trial atau langsung pilih paket berbayar. Semua paket tetap datang dari backend aktif.',
        tag: 'Setup',
    },
    {
        step: '02',
        title: 'Setup Company',
        subtitle: 'Isi data owner dan perusahaan',
        description: 'Flow onboarding publik tetap dipakai: company profile, owner credentials, dan pilihan trial atau subscribe.',
        tag: 'Configure',
    },
    {
        step: '03',
        title: 'Langsung Pakai',
        subtitle: 'Owner login dan mulai kerja',
        description: 'Setelah onboarding sukses, owner diarahkan ke login dengan company code yang sudah aktif.',
        tag: 'Launch',
    },
];

const navLinks = [
    { href: '#features', label: 'Fitur' },
    { href: '#solutions', label: 'Solusi' },
    { href: '#how', label: 'Cara kerja' },
    { href: '#pricing', label: 'Paket' },
];

const heroProofs = [
    { icon: CheckCircle, text: 'Setup < 1 jam' },
    { icon: Users, text: 'Multi-role access' },
    { icon: FileText, text: 'Auto reports' },
];

const heroStats = [
    { label: 'Modul inti', value: '6+' },
    { label: 'Masa trial', value: '30 hari' },
    { label: 'State utama', value: '4 step' },
];

function parseError(error) {
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

function apiFieldToFormKey(apiField) {
    // "company.contact_phone" → "companyContactPhone"
    // "owner.name" → "ownerName"
    // "billing_email" → "billingEmail"
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

function buildFieldErrors(details) {
    const map = {};
    (details || []).forEach((detail) => {
        if (!detail.field) return;
        const key = apiFieldToFormKey(detail.field);
        if (!map[key]) map[key] = detail.message || 'Nilai tidak valid';
    });
    return map;
}

function getInitialFormState(defaultPackage) {
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

function FadeInSection({ children, delay = 0, className = '' }) {
    const ref = useRef(null);
    const isInView = useInView(ref, { once: true, amount: 0.2 });

    return (
        <motion.div
            ref={ref}
            className={className}
            initial={{ opacity: 0, y: 28 }}
            animate={isInView ? { opacity: 1, y: 0 } : undefined}
            transition={{ duration: 0.65, delay, ease: [0.16, 1, 0.3, 1] }}
        >
            {children}
        </motion.div>
    );
}

function getRecommendedPackageUuid(packages) {
    const paidPackages = (packages || []).filter((packageItem) => !isTrialPackage(packageItem));
    const pool = paidPackages.length ? paidPackages : (packages || []);

    if (!pool.length) {
        return null;
    }

    return pool[Math.floor(pool.length / 2)]?.uuid || pool[0]?.uuid || null;
}

function getPackageEmoji(packageItem) {
    const code = String(packageItem?.code || '').toLowerCase();

    if (code.includes('trial') || code.includes('starter')) {
        return '📦';
    }

    if (code.includes('growth') || code.includes('pro') || code.includes('professional') || code.includes('business')) {
        return '🚀';
    }

    return '🏢';
}

const previewTabs = [
    { key: 'overview', label: 'Overview', icon: SquaresFour, badge: 'HCM ready' },
    { key: 'employees', label: 'Employees', icon: Users, badge: 'Directory live' },
    { key: 'attendance', label: 'Attendance', icon: CalendarCheck, badge: 'Realtime sync' },
    { key: 'payroll', label: 'Payroll', icon: CurrencyCircleDollar, badge: 'Ready to close' },
];

const previewContent = {
    overview: {
        heading: 'Aktivitas & progress modul HCM',
        subheading: 'Ringkasan minggu ini',
        icon: ChartBar,
        status: 'Live',
        chartData: ['60%', '85%', '95%', '75%', '90%', '65%', '80%'],
        stats: [
            { label: 'Employees', value: '124' },
            { label: 'Attendance', value: '98%' },
        ],
        activitiesHeading: 'Queue operasional',
        activitiesSubheading: 'Aktivitas terbaru',
        activitiesIcon: CalendarBlank,
        activities: [
            { icon: User, title: 'Employee ditambahkan', subtitle: 'Directory update' },
            { icon: CalendarBlank, title: 'Leave request', subtitle: 'Approval flow' },
            { icon: FileText, title: 'Payroll draft', subtitle: 'Ready to finalize' },
        ],
        nextTitle: 'Mulai onboarding',
        nextDescription: 'Buat company + owner, pilih trial atau subscribe.',
    },
    employees: {
        heading: 'Kesiapan struktur tim dan role',
        subheading: 'Employee directory',
        icon: Users,
        status: '124 aktif',
        chartData: ['52%', '58%', '65%', '72%', '76%', '82%', '88%'],
        stats: [
            { label: 'Dept aktif', value: '8' },
            { label: 'Manager seats', value: '14' },
        ],
        activitiesHeading: 'Perubahan terbaru',
        activitiesSubheading: 'Employee records',
        activitiesIcon: User,
        activities: [
            { icon: User, title: 'HR Manager onboarded', subtitle: 'Access role assigned' },
            { icon: Users, title: '3 divisi disinkronkan', subtitle: 'Org structure updated' },
            { icon: CheckCircle, title: 'Self-service aktif', subtitle: 'Employee portal ready' },
        ],
        nextTitle: 'Lengkapi struktur tim',
        nextDescription: 'Import employee, set approver, dan publish akses portal.',
    },
    attendance: {
        heading: 'Kehadiran, shift, dan approval harian',
        subheading: 'Attendance monitoring',
        icon: CalendarCheck,
        status: 'Realtime',
        chartData: ['72%', '78%', '80%', '76%', '88%', '92%', '90%'],
        stats: [
            { label: 'On time', value: '91%' },
            { label: 'Pending cuti', value: '6' },
        ],
        activitiesHeading: 'Approval & absensi',
        activitiesSubheading: 'Today queue',
        activitiesIcon: CalendarBlank,
        activities: [
            { icon: CalendarCheck, title: 'Clock-in tervalidasi', subtitle: '3 lokasi aktif' },
            { icon: CalendarBlank, title: '2 cuti menunggu approval', subtitle: 'Manager review' },
            { icon: CheckCircle, title: 'Late alerts terkirim', subtitle: 'Policy automation' },
        ],
        nextTitle: 'Aktifkan aturan attendance',
        nextDescription: 'Set shift, holiday, dan approval matrix untuk tim inti.',
    },
    payroll: {
        heading: 'Draft payroll, invoice, dan close period',
        subheading: 'Payroll workspace',
        icon: CurrencyCircleDollar,
        status: 'Ready',
        chartData: ['48%', '55%', '62%', '69%', '74%', '83%', '94%'],
        stats: [
            { label: 'Draft payroll', value: '3' },
            { label: 'Invoice queued', value: '11' },
        ],
        activitiesHeading: 'Billing & payroll',
        activitiesSubheading: 'Closing checklist',
        activitiesIcon: FileText,
        activities: [
            { icon: FileText, title: 'Payroll draft siap review', subtitle: 'Period April 2026' },
            { icon: CurrencyCircleDollar, title: 'Invoice pending issue', subtitle: 'Billing automation' },
            { icon: CheckCircle, title: 'Allowance rules validated', subtitle: 'Ready to finalize' },
        ],
        nextTitle: 'Finalize payroll',
        nextDescription: 'Review komponen gaji, publish slip, lalu kirim invoice.',
    },
};

function BrandMark({ bootstrap, className = '' }) {
    const logoUrl = String(bootstrap?.companyLogoUrl || bootstrap?.companyMiniLogoUrl || '').trim();
    const logoAlt = String(bootstrap?.companyName || 'Company logo').trim() || 'Company logo';

    return (
        <span className={`mpl-brand-mark ${className}`.trim()}>
            {logoUrl ? (
                <img src={logoUrl} alt={logoAlt} className="mpl-brand-mark__image" />
            ) : (
                <Lightning size={20} weight="fill" />
            )}
        </span>
    );
}

function DashboardMockup({ onOpenOnboarding, packageUuid }) {
    const [activeTab, setActiveTab] = useState('overview');
    const activePreview = previewContent[activeTab] || previewContent.overview;
    const activeTabMeta = previewTabs.find((tab) => tab.key === activeTab) || previewTabs[0];
    const ActiveTabIcon = activePreview.icon;

    return (
        <motion.div
            initial={{ opacity: 0, y: 40, rotateX: 12 }}
            whileInView={{ opacity: 1, y: 0, rotateX: 0 }}
            viewport={{ once: true }}
            transition={{ duration: 0.8, ease: [0.16, 1, 0.3, 1] }}
            className="mpl-preview-shell"
            style={{ perspective: '2000px' }}
        >
            <div className="mpl-preview-glow" aria-hidden="true"></div>
            <div className="mpl-preview-card">
                <div className="mpl-preview-head">
                    <div className="d-flex align-items-center gap-3">
                        <div className="mpl-browser-dots" aria-hidden="true">
                            <span></span>
                            <span></span>
                            <span></span>
                        </div>
                        <strong>{activeTabMeta.label} Demo</strong>
                    </div>
                    <span className="mpl-preview-badge"><CheckCircle size={14} weight="fill" /> {activeTabMeta.badge}</span>
                </div>
                <div className="mpl-preview-body">
                    <div className="mpl-preview-tabs">
                        {previewTabs.map((tab) => {
                            const TabIcon = tab.icon;

                            return (
                                <button
                                    key={tab.key}
                                    type="button"
                                    className={`mpl-preview-tab ${activeTab === tab.key ? 'is-active' : ''}`}
                                    onClick={() => setActiveTab(tab.key)}
                                >
                                    <TabIcon size={14} weight="fill" /> {tab.label}
                                </button>
                            );
                        })}
                    </div>

                    <div className="mpl-preview-grid">
                        <div className="mpl-analytics-card">
                            <div className="mpl-analytics-top">
                                <div>
                                    <p className="small mb-1 d-flex align-items-center gap-2"><ActiveTabIcon size={14} weight="duotone" /> {activePreview.subheading}</p>
                                    <strong>{activePreview.heading}</strong>
                                </div>
                                <span className="mpl-preview-badge">{activePreview.status}</span>
                            </div>

                            <div className="mpl-mini-chart" aria-hidden="true">
                                {activePreview.chartData.map((height, index) => (
                                    <motion.span
                                        key={`${height}-${index}`}
                                        initial={{ height: 0 }}
                                        whileInView={{ height }}
                                        viewport={{ once: true }}
                                        transition={{ delay: 0.1 + (index * 0.06), duration: 0.55, ease: [0.16, 1, 0.3, 1] }}
                                    ></motion.span>
                                ))}
                            </div>

                            <div className="mpl-stats-row">
                                {activePreview.stats.map((stat) => (
                                    <div key={stat.label} className="mpl-stat-box">
                                        <p className="small mb-0">{stat.label}</p>
                                        <strong>{stat.value}</strong>
                                    </div>
                                ))}
                            </div>
                        </div>

                        <div className="mpl-activity-card">
                            <div className="mpl-activity-top">
                                <div>
                                    <p className="small mb-1 d-flex align-items-center gap-2"><activePreview.activitiesIcon size={14} weight="duotone" /> {activePreview.activitiesSubheading}</p>
                                    <strong>{activePreview.activitiesHeading}</strong>
                                </div>
                            </div>

                            <div className="mpl-activity-list">
                                {activePreview.activities.map((activity, index) => (
                                    <motion.div
                                        key={activity.title}
                                        className="mpl-activity-item"
                                        initial={{ opacity: 0, x: 16 }}
                                        whileInView={{ opacity: 1, x: 0 }}
                                        viewport={{ once: true }}
                                        transition={{ delay: 0.25 + (index * 0.08), duration: 0.45 }}
                                    >
                                        <span className="mpl-icon-box"><activity.icon size={18} weight="duotone" /></span>
                                        <div>
                                            <strong className="d-block mb-1">{activity.title}</strong>
                                            <p className="small mb-0">{activity.subtitle}</p>
                                        </div>
                                    </motion.div>
                                ))}
                            </div>

                            <div className="mpl-next-card">
                                <div>
                                    <p className="small mb-1">Next step</p>
                                    <strong className="d-block mb-1">{activePreview.nextTitle}</strong>
                                    <p className="small mb-0">{activePreview.nextDescription}</p>
                                </div>
                                <button type="button" className="mpl-btn" onClick={() => onOpenOnboarding(packageUuid || '')}>
                                    Mulai <ArrowRight size={18} weight="bold" />
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </motion.div>
    );
}

function PackageCard({ isHighlighted, onOpenOnboarding, packageItem, trialUrl }) {
    const trialPackage = isTrialPackage(packageItem);
    const featureItems = (packageItem.featureHighlights || []).slice(0, 5);

    return (
        <motion.div
            whileHover={{ y: isHighlighted ? 0 : -12, scale: isHighlighted ? 1 : 1.03 }}
            transition={{ type: 'spring', stiffness: 300 }}
            className={`mpl-price-card ${isHighlighted ? 'is-highlighted' : ''}`}
            style={{ '--mpl-price-accent': packageItem.color || '#2563eb' }}
            data-package-card
            data-package-code={String(packageItem.code || '').toLowerCase()}
            data-package-uuid={packageItem.uuid}
        >
            {isHighlighted ? <span className="mpl-price-badge">Popular</span> : null}
            <div className="mpl-price-emoji" aria-hidden="true">{getPackageEmoji(packageItem)}</div>
            <h3>{packageItem.name}</h3>
            <p>{packageItem.description || 'Paket aktif untuk flow onboarding tenant baru.'}</p>
            <div className="mpl-price-value">{trialPackage ? 'Gratis' : formatIdr(packageItem.monthlyPrice)}</div>
            <div className="mpl-price-meta">{trialPackage ? 'Trial onboarding' : '/bulan'}{!trialPackage ? ` • ${packageItem.billingUnit || 'company'}` : ''}</div>

            <ul className="mpl-price-list">
                {featureItems.length ? featureItems.map((feature) => (
                    <li key={`${packageItem.uuid}-${feature.code || feature.name}`}>
                        <CheckCircle size={18} weight="fill" />
                        <span>{feature.name || feature.code}</span>
                    </li>
                )) : (
                    <>
                        <li><CheckCircle size={18} weight="fill" /><span>Employee management</span></li>
                        <li><CheckCircle size={18} weight="fill" /><span>Attendance</span></li>
                        <li><CheckCircle size={18} weight="fill" /><span>Payroll & billing</span></li>
                    </>
                )}
            </ul>

            <div className="mpl-price-actions">
                <button type="button" className="mpl-btn" onClick={() => onOpenOnboarding(packageItem.uuid)}>
                    {trialPackage ? 'Mulai Trial' : 'Mulai Sekarang'} <ArrowRight size={18} weight="bold" />
                </button>
                <a className="mpl-btn-outline" href={`${trialUrl}?packageId=${encodeURIComponent(packageItem.uuid)}`} data-package-plan-link>
                    Pilih plan
                </a>
            </div>
        </motion.div>
    );
}

function DemoOverlay({ onClose, onOpenOnboarding, packageUuid }) {
    return (
        <AnimatePresence>
            <motion.div
                className="mpl-demo-overlay"
                initial={{ opacity: 0 }}
                animate={{ opacity: 1 }}
                exit={{ opacity: 0 }}
            >
                <motion.div
                    className="mpl-demo-modal"
                    initial={{ opacity: 0, y: 32, scale: 0.96 }}
                    animate={{ opacity: 1, y: 0, scale: 1 }}
                    exit={{ opacity: 0, y: 24, scale: 0.97 }}
                    transition={{ duration: 0.35, ease: [0.16, 1, 0.3, 1] }}
                >
                    <div className="mpl-demo-head">
                        <div>
                            <span className="mpl-badge"><Lightning size={14} weight="fill" /> Demo Dashboard</span>
                            <h2 className="h3 mt-3 mb-2">Preview layout dari repo referensi</h2>
                            <p className="text-muted mb-0">Ini dipakai sebagai showcase visual, sementara CTA tetap diarahkan ke flow onboarding publik yang sudah hidup.</p>
                        </div>
                        <button type="button" className="mpl-demo-close" aria-label="Close demo" onClick={onClose}>
                            <X size={20} />
                        </button>
                    </div>

                    <div className="mpl-demo-grid">
                        <DashboardMockup onOpenOnboarding={onOpenOnboarding} packageUuid={packageUuid} />
                        <div className="mpl-demo-summary">
                            {solutionCards.map((item) => (
                                <div key={item.title} className="mpl-card">
                                    <span className="mpl-solution-icon"><item.icon size={24} weight="duotone" /></span>
                                    <h3>{item.title}</h3>
                                    <p className="mb-0">{item.description}</p>
                                </div>
                            ))}
                        </div>
                    </div>
                </motion.div>
            </motion.div>
        </AnimatePresence>
    );
}

export function OnboardingModal({ error, formState, onChange, onChangeConsent, onClose, onSubmit, packages, submitting, turnstileEnabled, turnstileSiteKey, onTurnstileTokenChange }) {
    const selectedPackage = packages.find((packageItem) => packageItem.uuid === formState.packageUuid) || null;
    const isTrialSelected = isTrialPackage(selectedPackage);
    const lockBillingCycle = isTrialSelected;
    const fieldErrors = useMemo(() => buildFieldErrors(error?.details), [error]);
    const fe = (name) => fieldErrors[name] || null;
    const [showPdpModal, setShowPdpModal] = useState(false);
    const [activeConsentTab, setActiveConsentTab] = useState('pdp');
    const [pdpScrolledToEnd, setPdpScrolledToEnd] = useState(false);
    const [tocScrolledToEnd, setTocScrolledToEnd] = useState(false);
    const [pdpRead, setPdpRead] = useState(false);
    const canAgree = pdpScrolledToEnd && tocScrolledToEnd;

    const handlePdpBodyScroll = (e) => {
        const el = e.currentTarget;
        if (!pdpScrolledToEnd && el.scrollTop + el.clientHeight >= el.scrollHeight - 60) {
            setPdpScrolledToEnd(true);
        }
    };
    const handleTocBodyScroll = (e) => {
        const el = e.currentTarget;
        if (!tocScrolledToEnd && el.scrollTop + el.clientHeight >= el.scrollHeight - 60) {
            setTocScrolledToEnd(true);
        }
    };
    const handleAgreeAndClose = () => {
        setPdpRead(true);
        onChangeConsent({ target: { checked: true } });
        setShowPdpModal(false);
    };
    const turnstileContainerRef = useRef(null);
    const turnstileWidgetIdRef = useRef(null);
    const e2eTurnstileToken = getE2ETurnstileToken();
    const emitTurnstileTokenChange = useEffectEvent((token) => {
        onTurnstileTokenChange(String(token || '').trim());
    });

    useEffect(() => {
        if (!turnstileEnabled || !turnstileSiteKey || !turnstileContainerRef.current) {
            return undefined;
        }

        if (e2eTurnstileToken) {
            emitTurnstileTokenChange(e2eTurnstileToken);
            return () => {
                emitTurnstileTokenChange('');
            };
        }

        let cancelled = false;
        let attempts = 0;

        const mountWidget = () => {
            if (cancelled) {
                return;
            }

            if (!window.turnstile || typeof window.turnstile.render !== 'function') {
                if (attempts >= 40) {
                    return;
                }

                attempts += 1;
                window.setTimeout(mountWidget, 250);
                return;
            }

            turnstileContainerRef.current.innerHTML = '';
            turnstileWidgetIdRef.current = window.turnstile.render(turnstileContainerRef.current, {
                sitekey: turnstileSiteKey,
                callback: (token) => {
                    emitTurnstileTokenChange(token || '');
                },
                'expired-callback': () => {
                    emitTurnstileTokenChange('');
                },
                'error-callback': () => {
                    emitTurnstileTokenChange('');
                },
            });
        };

        mountWidget();

        return () => {
            cancelled = true;
            emitTurnstileTokenChange('');

            if (turnstileWidgetIdRef.current != null && window.turnstile && typeof window.turnstile.remove === 'function') {
                try {
                    window.turnstile.remove(turnstileWidgetIdRef.current);
                } catch (_error) {
                    if (turnstileContainerRef.current) {
                        turnstileContainerRef.current.innerHTML = '';
                    }
                }
            }

            turnstileWidgetIdRef.current = null;
            if (turnstileContainerRef.current) {
                turnstileContainerRef.current.innerHTML = '';
            }
        };
    }, [e2eTurnstileToken, turnstileEnabled, turnstileSiteKey]);

    return (
        <>
            <div className="modal fade show d-block" role="dialog" aria-modal="true" aria-labelledby="landingOnboardingModalLabel">
                <div className="modal-dialog modal-dialog-centered modal-xl modal-dialog-scrollable">
                    <div className="modal-content border-0 shadow-lg overflow-hidden">
                        <div className="modal-header border-0 pb-0">
                            <div>
                                <h2 className="h4 mb-1" id="landingOnboardingModalLabel">Aktifkan workspace perusahaan</h2>
                                <p className="text-muted mb-0">Flow onboarding publik tetap dipakai. Yang berubah hanya visual landing-nya.</p>
                            </div>
                            <button type="button" className="btn-close" aria-label="Close" onClick={onClose}></button>
                        </div>
                        <div className="modal-body pt-3">
                            {error ? (
                                <div className="alert alert-danger py-2 small" role="alert">
                                    {error.details.length
                                        ? 'Beberapa field tidak valid — periksa field yang ditandai merah di bawah ini.'
                                        : error.message}
                                </div>
                            ) : null}

                            <form id="onboardingReactForm" onSubmit={onSubmit}>
                                <div className="row g-3">
                                    <div className="col-12">
                                        <div className="rounded-4 p-3 p-lg-4 border bg-light-subtle">
                                            <div className="row g-3 align-items-end">
                                                <div className="col-md-6">
                                                    <label className="form-label fw-semibold">Paket</label>
                                                    <select className="form-select" name="packageUuid" value={formState.packageUuid} onChange={onChange} required>
                                                        {packages.map((packageItem) => (
                                                            <option key={packageItem.uuid} value={packageItem.uuid}>{packageItem.name} ({packageItem.code})</option>
                                                        ))}
                                                    </select>
                                                </div>
                                                <div className="col-md-3">
                                                    <label className="form-label fw-semibold">Billing cycle</label>
                                                    <select className="form-select" name="billingCycle" value={formState.billingCycle} onChange={onChange} disabled={lockBillingCycle}>
                                                        <option value="monthly">Monthly</option>
                                                        <option value="yearly">Yearly</option>
                                                    </select>
                                                </div>
                                                <div className="col-md-3">
                                                    <label className="form-label fw-semibold">Mulai sebagai</label>
                                                    <select className="form-select" name="startMode" value={formState.startMode} onChange={onChange} disabled>
                                                        {isTrialSelected ? (
                                                            <option value="trial">Trial</option>
                                                        ) : (
                                                            <option value="pending_payment">Langsung subscribe</option>
                                                        )}
                                                    </select>
                                                </div>
                                                <div className="col-12">
                                                    <div className="small text-muted">
                                                        {isTrialSelected
                                                            ? 'Paket trial dipaksa ke billing monthly dan mode trial.'
                                                            : 'Paket berbayar langsung subscribe (pending payment). Untuk mencoba trial dulu, pilih paket Trial.'}
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div className="col-lg-7">
                                        <div className="rounded-4 p-3 p-lg-4 h-100 border bg-white">
                                            <h3 className="h5 mb-3">Data company</h3>
                                            <div className="row g-3">
                                                <div className="col-md-6">
                                                    <label className="form-label">Nama company</label>
                                                    <input className={`form-control${fe('companyName') ? ' is-invalid' : ''}`} name="companyName" value={formState.companyName} onChange={onChange} maxLength={255} required />
                                                    {fe('companyName') ? <div className="invalid-feedback">{fe('companyName')}</div> : null}
                                                </div>
                                                <div className="col-md-6">
                                                    <label className="form-label">Nama legal</label>
                                                    <input className={`form-control${fe('companyLegalName') ? ' is-invalid' : ''}`} name="companyLegalName" value={formState.companyLegalName} onChange={onChange} maxLength={255} />
                                                    {fe('companyLegalName') ? <div className="invalid-feedback">{fe('companyLegalName')}</div> : null}
                                                </div>
                                                <div className="col-md-6">
                                                    <label className="form-label">Contact person</label>
                                                    <input className={`form-control${fe('companyContactPersonName') ? ' is-invalid' : ''}`} name="companyContactPersonName" value={formState.companyContactPersonName} onChange={onChange} maxLength={120} placeholder="Cth: Budi Santoso" pattern="[A-Za-z\s'.\-]+" title="Hanya huruf, spasi, titik, atau tanda hubung." />
                                                    {fe('companyContactPersonName')
                                                        ? <div className="invalid-feedback">{fe('companyContactPersonName')}</div>
                                                        : <div className="form-text d-flex justify-content-between"><span>Nama lengkap contact person.</span><span className={formState.companyContactPersonName.length > 100 ? 'text-warning' : 'text-muted'}>{formState.companyContactPersonName.length}/120</span></div>}
                                                </div>
                                                <div className="col-md-6">
                                                    <label className="form-label">Peran contact person</label>
                                                    <input className={`form-control${fe('companyContactPersonRole') ? ' is-invalid' : ''}`} name="companyContactPersonRole" value={formState.companyContactPersonRole} onChange={onChange} maxLength={120} placeholder="Cth: HR Manager" pattern="[A-Za-z0-9\s'.\-\/&,]+" title="Jabatan/peran. Hanya huruf, angka, spasi, atau tanda baca umum." />
                                                    {fe('companyContactPersonRole')
                                                        ? <div className="invalid-feedback">{fe('companyContactPersonRole')}</div>
                                                        : <div className="form-text d-flex justify-content-between"><span>Jabatan di perusahaan.</span><span className={formState.companyContactPersonRole.length > 100 ? 'text-warning' : 'text-muted'}>{formState.companyContactPersonRole.length}/120</span></div>}
                                                </div>
                                                <div className="col-md-6">
                                                    <label className="form-label">Nomor kontak company</label>
                                                    <input className={`form-control${fe('companyContactPhone') ? ' is-invalid' : ''}`} name="companyContactPhone" value={formState.companyContactPhone} onChange={onChange} type="tel" inputMode="tel" placeholder="Contoh: +62811234567" maxLength={20} pattern="[0-9+\-\s().]{6,20}" title="Gunakan angka, +, -, spasi, titik, atau kurung. Min. 6 karakter." />
                                                    {fe('companyContactPhone') ? <div className="invalid-feedback">{fe('companyContactPhone')}</div> : <div className="form-text">Gunakan format angka, +, atau tanda hubung.</div>}
                                                </div>
                                                <div className="col-md-6">
                                                    <label className="form-label">Kota</label>
                                                    <input className={`form-control${fe('companyCity') ? ' is-invalid' : ''}`} name="companyCity" value={formState.companyCity} onChange={onChange} maxLength={120} required />
                                                    {fe('companyCity') ? <div className="invalid-feedback">{fe('companyCity')}</div> : null}
                                                </div>
                                                <div className="col-12">
                                                    <label className="form-label">Alamat</label>
                                                    <textarea className={`form-control${fe('companyAddress') ? ' is-invalid' : ''}`} name="companyAddress" rows="3" value={formState.companyAddress} onChange={onChange} maxLength={500} required />
                                                    {fe('companyAddress') ? <div className="invalid-feedback">{fe('companyAddress')}</div> : null}
                                                </div>
                                                <div className="col-md-4">
                                                    <label className="form-label">Kode pos</label>
                                                    <input className={`form-control${fe('companyPostalCode') ? ' is-invalid' : ''}`} name="companyPostalCode" value={formState.companyPostalCode} onChange={onChange} inputMode="numeric" maxLength={12} pattern="[0-9]{3,12}" title="Kode pos hanya berisi angka, 3–12 digit." />
                                                    {fe('companyPostalCode') ? <div className="invalid-feedback">{fe('companyPostalCode')}</div> : null}
                                                </div>
                                                <div className="col-md-4">
                                                    <label className="form-label">Country code</label>
                                                    <input className="form-control-plaintext border rounded px-2 bg-light" name="companyCountryCode" value={formState.companyCountryCode} readOnly disabled />
                                                </div>
                                                <div className="col-md-4">
                                                    <label className="form-label">Timezone</label>
                                                    <input className="form-control-plaintext border rounded px-2 bg-light" name="companyTimezone" value={formState.companyTimezone} readOnly disabled />
                                                </div>
                                                <div className="col-md-4">
                                                    <label className="form-label">Currency</label>
                                                    <input className="form-control-plaintext border rounded px-2 bg-light" name="companyCurrency" value={formState.companyCurrency} readOnly disabled />
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div className="col-lg-5">
                                        <div className="rounded-4 p-3 p-lg-4 h-100 border bg-white">
                                            <h3 className="h5 mb-3">Data owner</h3>
                                            <div className="row g-3">
                                                <div className="col-12">
                                                    <label className="form-label">Nama owner</label>
                                                    <input className={`form-control${fe('ownerName') ? ' is-invalid' : ''}`} name="ownerName" value={formState.ownerName} onChange={onChange} minLength={2} maxLength={150} placeholder="Cth: Budi Santoso" pattern="[A-Za-z][A-Za-z .'\-]{1,149}" title="Diawali huruf. Hanya huruf, spasi, titik, tanda petik, atau tanda hubung." required />
                                                    {fe('ownerName')
                                                        ? <div className="invalid-feedback">{fe('ownerName')}</div>
                                                        : <div className="form-text">Nama lengkap sesuai identitas.</div>}
                                                </div>
                                                <div className="col-12">
                                                    <label className="form-label">Email owner</label>
                                                    <input className={`form-control${fe('ownerEmail') ? ' is-invalid' : ''}`} name="ownerEmail" type="email" value={formState.ownerEmail} onChange={onChange} maxLength={255} required />
                                                    {fe('ownerEmail') ? <div className="invalid-feedback">{fe('ownerEmail')}</div> : null}
                                                </div>
                                                <div className="col-12">
                                                    <label className="form-label">Nomor owner</label>
                                                    <input className={`form-control${fe('ownerPhone') ? ' is-invalid' : ''}`} name="ownerPhone" value={formState.ownerPhone} onChange={onChange} type="tel" inputMode="tel" placeholder="Contoh: +62811234567" maxLength={20} pattern="[0-9+\-\s().]{6,20}" title="Gunakan angka, +, -, spasi, titik, atau kurung. Min. 6 karakter." />
                                                    {fe('ownerPhone') ? <div className="invalid-feedback">{fe('ownerPhone')}</div> : null}
                                                </div>
                                                <div className="col-12">
                                                    <label className="form-label">Password</label>
                                                    <input className={`form-control${fe('ownerPassword') ? ' is-invalid' : ''}`} name="ownerPassword" type="password" value={formState.ownerPassword} onChange={onChange} minLength={8} maxLength={64} required />
                                                    {fe('ownerPassword') ? <div className="invalid-feedback">{fe('ownerPassword')}</div> : <div className="form-text">Min. 8 karakter, mengandung huruf besar, huruf kecil, dan angka.</div>}
                                                </div>
                                                <div className="col-12">
                                                    <label className="form-label">Konfirmasi password</label>
                                                    <input className={`form-control${fe('ownerConfirmPassword') || formState._confirmMismatch ? ' is-invalid' : ''}`} name="ownerConfirmPassword" type="password" value={formState.ownerConfirmPassword} onChange={onChange} minLength={8} maxLength={64} required />
                                                    {fe('ownerConfirmPassword') ? <div className="invalid-feedback">{fe('ownerConfirmPassword')}</div> : formState._confirmMismatch ? <div className="invalid-feedback">Password tidak cocok.</div> : null}
                                                </div>
                                                <div className="col-12">
                                                    <label className="form-label">Billing email</label>
                                                    <input className={`form-control${fe('billingEmail') ? ' is-invalid' : ''}`} name="billingEmail" type="email" value={formState.billingEmail} onChange={onChange} maxLength={255} />
                                                    {fe('billingEmail') ? <div className="invalid-feedback">{fe('billingEmail')}</div> : null}
                                                </div>
                                                <div className="col-12 d-none">
                                                    <label className="form-label">Website</label>
                                                    <input className="form-control" name="website" value={formState.website} onChange={onChange} tabIndex="-1" autoComplete="off" />
                                                </div>

                                                {turnstileEnabled && turnstileSiteKey ? (
                                                    <div className="col-12">
                                                        <label className="form-label">Verifikasi keamanan</label>
                                                        <div ref={turnstileContainerRef}></div>
                                                        <div className="form-text">Selesaikan captcha sebelum submit onboarding.</div>
                                                    </div>
                                                ) : null}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                        <div className="modal-footer flex-column align-items-stretch gap-2 border-top">
                            <div className="form-check">
                                <input
                                    className="form-check-input"
                                    type="checkbox"
                                    id="consentAcceptedReact"
                                    name="consentAccepted"
                                    checked={Boolean(formState.consentAccepted)}
                                    onChange={onChangeConsent}
                                    disabled={!pdpRead}
                                    required
                                />
                                <label className="form-check-label small" htmlFor="consentAcceptedReact">
                                    {pdpRead
                                        ? <>Saya telah membaca dan menyetujui <strong>Kebijakan Privasi &amp; Syarat Ketentuan</strong> ARCAV HCM.</>
                                        : <><button type="button" className="btn btn-link btn-sm p-0 align-baseline fw-semibold" onClick={() => setShowPdpModal(true)}>Baca Kebijakan Privasi &amp; Syarat Ketentuan</button> terlebih dahulu sebelum melanjutkan.</>
                                    }
                                </label>
                            </div>
                            <div className="d-flex flex-wrap justify-content-end gap-2">
                                <button type="button" className="btn btn-outline-secondary" onClick={onClose}>Tutup</button>
                                <button
                                    type="submit"
                                    form="onboardingReactForm"
                                    className="btn btn-primary"
                                    disabled={submitting || !formState.consentAccepted || formState._confirmMismatch}
                                >
                                    {submitting ? 'Memproses...' : 'Proses onboarding'}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div className="modal-backdrop fade show"></div>

            {showPdpModal ? (
                <>
                    <div
                        className="modal fade show d-block"
                        tabIndex="-1"
                        role="dialog"
                        style={{ zIndex: 1060 }}
                        onClick={(e) => { if (e.target === e.currentTarget) setShowPdpModal(false); }}
                    >
                        <div className="modal-dialog modal-dialog-centered modal-lg">
                            <div className="modal-content border-0 shadow-lg">
                                <div className="modal-header pb-0 border-0">
                                    <div>
                                        <h5 className="modal-title">Kebijakan Privasi &amp; Syarat Ketentuan</h5>
                                        <div className="d-flex align-items-center gap-2 mt-1">
                                            <span style={{
                                                display: 'inline-flex', alignItems: 'center', gap: '4px',
                                                fontSize: '0.75rem', color: pdpScrolledToEnd ? '#198754' : '#6c757d',
                                                transition: 'color 0.4s'
                                            }}>
                                                <span style={{
                                                    width: 16, height: 16, borderRadius: '50%',
                                                    background: pdpScrolledToEnd ? '#198754' : '#dee2e6',
                                                    display: 'inline-flex', alignItems: 'center', justifyContent: 'center',
                                                    transition: 'background 0.4s',
                                                    fontSize: '0.65rem', color: '#fff', fontWeight: 700
                                                }}>✓</span>
                                                Kebijakan Privasi
                                            </span>
                                            <span style={{ color: '#dee2e6', fontSize: '0.75rem' }}>·</span>
                                            <span style={{
                                                display: 'inline-flex', alignItems: 'center', gap: '4px',
                                                fontSize: '0.75rem', color: tocScrolledToEnd ? '#198754' : '#6c757d',
                                                transition: 'color 0.4s'
                                            }}>
                                                <span style={{
                                                    width: 16, height: 16, borderRadius: '50%',
                                                    background: tocScrolledToEnd ? '#198754' : '#dee2e6',
                                                    display: 'inline-flex', alignItems: 'center', justifyContent: 'center',
                                                    transition: 'background 0.4s',
                                                    fontSize: '0.65rem', color: '#fff', fontWeight: 700
                                                }}>✓</span>
                                                Syarat &amp; Ketentuan
                                            </span>
                                        </div>
                                    </div>
                                    <button type="button" className="btn-close" aria-label="Tutup" onClick={() => setShowPdpModal(false)}></button>
                                </div>
                                <div className="modal-body p-0">
                                    <ul className="nav nav-tabs px-3 pt-2">
                                        <li className="nav-item">
                                            <button
                                                type="button"
                                                className={`nav-link d-flex align-items-center gap-2${activeConsentTab === 'pdp' ? ' active' : ''}`}
                                                onClick={() => setActiveConsentTab('pdp')}
                                            >
                                                Kebijakan Privasi
                                                <span style={{
                                                    width: 18, height: 18, borderRadius: '50%',
                                                    background: pdpScrolledToEnd ? '#198754' : '#dee2e6',
                                                    display: 'inline-flex', alignItems: 'center', justifyContent: 'center',
                                                    fontSize: '0.6rem', color: '#fff', fontWeight: 700,
                                                    transition: 'background 0.35s, transform 0.35s',
                                                    transform: pdpScrolledToEnd ? 'scale(1.15)' : 'scale(1)'
                                                }}>✓</span>
                                            </button>
                                        </li>
                                        <li className="nav-item">
                                            <button
                                                type="button"
                                                className={`nav-link d-flex align-items-center gap-2${activeConsentTab === 'toc' ? ' active' : ''}`}
                                                onClick={() => setActiveConsentTab('toc')}
                                            >
                                                Syarat &amp; Ketentuan
                                                <span style={{
                                                    width: 18, height: 18, borderRadius: '50%',
                                                    background: tocScrolledToEnd ? '#198754' : '#dee2e6',
                                                    display: 'inline-flex', alignItems: 'center', justifyContent: 'center',
                                                    fontSize: '0.6rem', color: '#fff', fontWeight: 700,
                                                    transition: 'background 0.35s, transform 0.35s',
                                                    transform: tocScrolledToEnd ? 'scale(1.15)' : 'scale(1)'
                                                }}>✓</span>
                                            </button>
                                        </li>
                                    </ul>

                                    <style>{`@keyframes arcavBounce{0%,100%{transform:translateY(0)}50%{transform:translateY(5px)}}`}</style>
                                    {activeConsentTab === 'pdp' ? (
                                        <div style={{ position: 'relative' }}>
                                            <div
                                                className="px-4 py-3"
                                                style={{ overflowY: 'auto', height: '55vh' }}
                                                onScroll={handlePdpBodyScroll}
                                            >
                                            <p className="text-muted small">Berlaku efektif: 1 Mei 2026 &mdash; Terakhir diperbarui: 1 Mei 2026</p>
                                            <p className="small">ARCAV HCM ("kami") berkomitmen melindungi data pribadi Anda sesuai <strong>UU No. 27 Tahun 2022 tentang Perlindungan Data Pribadi (UU PDP)</strong> Republik Indonesia.</p>

                                            <h6 className="mt-3 mb-1">1. Identitas Pengendali Data Pribadi</h6>
                                            <ul className="small ps-3">
                                                <li><strong>Nama Pengendali:</strong> ARCAV HCM</li>
                                                <li><strong>DPO:</strong> Tim Data Protection ARCAV HCM</li>
                                                <li><strong>Kontak DPO:</strong> <a href="mailto:dpo@arcav.id">dpo@arcav.id</a></li>
                                            </ul>

                                            <h6 className="mt-3 mb-1">2. Data Pribadi yang Kami Kumpulkan</h6>
                                            <p className="small fw-semibold mb-1">a. Data Umum</p>
                                            <ul className="small ps-3">
                                                <li>Nama lengkap, email, nomor telepon, alamat domisili</li>
                                                <li>Data ketenagakerjaan: jabatan, departemen, tanggal bergabung, status kontrak</li>
                                                <li>Log akses: IP, user-agent, waktu akses</li>
                                                <li>Absensi: waktu masuk/keluar, lokasi GPS (dengan persetujuan)</li>
                                            </ul>
                                            <p className="small fw-semibold mb-1">b. Data Spesifik (Pasal 4 ayat 2 UU PDP)</p>
                                            <ul className="small ps-3">
                                                <li><strong>NIK/KTP</strong> — verifikasi identitas karyawan</li>
                                                <li><strong>NPWP, status pajak (PTKP)</strong> — pemotongan PPh 21</li>
                                                <li><strong>Data rekening bank</strong> — transfer gaji</li>
                                                <li><strong>Nomor BPJS Kesehatan &amp; Ketenagakerjaan</strong> — administrasi jaminan sosial</li>
                                                <li><strong>Data biometrik (foto selfie)</strong> — opsional, dengan persetujuan eksplisit</li>
                                                <li><strong>Lokasi GPS</strong> — opsional, dengan persetujuan eksplisit</li>
                                                <li><strong>Agama, status perkawinan, kewarganegaraan</strong> — penghitungan tunjangan/pajak</li>
                                                <li><strong>Data gaji dan kompensasi</strong> — pemrosesan payroll</li>
                                            </ul>

                                            <h6 className="mt-3 mb-1">3. Dasar dan Tujuan Pemrosesan (Pasal 20–21 UU PDP)</h6>
                                            <div className="table-responsive">
                                                <table className="table table-bordered table-sm small">
                                                    <thead className="table-light"><tr><th>Tujuan</th><th>Dasar Hukum</th></tr></thead>
                                                    <tbody>
                                                        <tr><td>Manajemen data karyawan</td><td>Pelaksanaan kontrak kerja (Pasal 20 b)</td></tr>
                                                        <tr><td>Penggajian &amp; pemotongan PPh 21</td><td>Kewajiban hukum (Pasal 20 c)</td></tr>
                                                        <tr><td>Administrasi BPJS</td><td>Kewajiban hukum (Pasal 20 c)</td></tr>
                                                        <tr><td>Absensi foto selfie &amp; GPS</td><td>Persetujuan eksplisit (Pasal 20 a)</td></tr>
                                                        <tr><td>Keamanan platform</td><td>Kepentingan sah (Pasal 20 f)</td></tr>
                                                        <tr><td>Onboarding akun perusahaan</td><td>Persetujuan (Pasal 20 a)</td></tr>
                                                    </tbody>
                                                </table>
                                            </div>

                                            <h6 className="mt-3 mb-1">4. Pihak Ketiga</h6>
                                            <div className="table-responsive">
                                                <table className="table table-bordered table-sm small">
                                                    <thead className="table-light"><tr><th>Pihak</th><th>Data</th><th>Tujuan</th></tr></thead>
                                                    <tbody>
                                                        <tr><td>Xendit / Stripe</td><td>Nama, email, tagihan</td><td>Pembayaran langganan</td></tr>
                                                        <tr><td>Penyedia AI</td><td>Teks intent (tanpa PII)</td><td>Asisten AI HCM</td></tr>
                                                        <tr><td>Cloudflare Turnstile</td><td>Token captcha</td><td>Pencegahan bot</td></tr>
                                                    </tbody>
                                                </table>
                                            </div>

                                            <h6 className="mt-3 mb-1">5. Retensi Data</h6>
                                            <ul className="small ps-3">
                                                <li>Karyawan aktif: selama hubungan kerja</li>
                                                <li>Karyawan berhenti: maks. <strong>5 tahun</strong></li>
                                                <li>Payroll &amp; perpajakan: <strong>10 tahun</strong></li>
                                                <li>Log akses keamanan: maks. <strong>1 tahun</strong></li>
                                            </ul>

                                            <h6 className="mt-3 mb-1">6. Hak Subjek Data (Pasal 5–13 UU PDP)</h6>
                                            <ul className="small ps-3">
                                                <li><strong>Hak Akses</strong> — 14 hari kerja</li>
                                                <li><strong>Hak Perbaikan</strong> — 14 hari kerja</li>
                                                <li><strong>Hak Penghapusan</strong> — 30 hari kerja</li>
                                                <li><strong>Hak Portabilitas Data</strong> — 14 hari kerja</li>
                                                <li><strong>Hak Mencabut Persetujuan</strong> — Segera</li>
                                            </ul>

                                            <h6 className="mt-3 mb-1">7. Keamanan Data</h6>
                                            <ul className="small ps-3">
                                                <li>Enkripsi HTTPS/TLS</li>
                                                <li>Hashing kata sandi (Bcrypt)</li>
                                                <li>RBAC per tenant</li>
                                                <li>Audit log ekspor data massal</li>
                                            </ul>

                                            <h6 className="mt-3 mb-1">8. Notifikasi Insiden (Pasal 46 UU PDP)</h6>
                                            <p className="small">Notifikasi kepada pihak berwenang dan subjek data dalam <strong>3 × 24 jam</strong> setelah insiden terdeteksi.</p>

                                            <h6 className="mt-3 mb-1">9. Hubungi DPO</h6>
                                            <ul className="small ps-3">
                                                <li><strong>Email:</strong> <a href="mailto:dpo@arcav.id">dpo@arcav.id</a></li>
                                                <li>Respons dalam 3 hari kerja</li>
                                            </ul>
                                        </div>
                                        {!pdpScrolledToEnd && (
                                            <div style={{
                                                position: 'absolute', bottom: 0, left: 0, right: 0,
                                                height: 56, pointerEvents: 'none',
                                                background: 'linear-gradient(to bottom, transparent, rgba(255,255,255,0.95))',
                                                display: 'flex', alignItems: 'flex-end', justifyContent: 'center',
                                                paddingBottom: 6
                                            }}>
                                                <svg style={{ animation: 'arcavBounce 1s ease-in-out infinite' }}
                                                    width="22" height="22" viewBox="0 0 24 24" fill="none"
                                                    stroke="#6c757d" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                                                    <polyline points="6 9 12 15 18 9" />
                                                </svg>
                                            </div>
                                        )}
                                    </div>
                                    ) : (
                                        <div style={{ position: 'relative' }}>
                                        <div
                                            className="px-4 py-3"
                                            style={{ overflowY: 'auto', height: '55vh' }}
                                            onScroll={handleTocBodyScroll}
                                        >
                                            <p className="small">Selamat datang di platform ARCAV HCM. Dengan mengakses atau menggunakan platform ini, Anda menyetujui syarat dan ketentuan berikut.</p>

                                            <h6 className="mt-3 mb-1">Penerimaan Syarat</h6>
                                            <p className="small">Dengan menggunakan platform ini, Anda terikat oleh Syarat &amp; Ketentuan ini beserta panduan tambahan yang berlaku. Jika tidak setuju, jangan gunakan platform ini.</p>

                                            <h6 className="mt-3 mb-1">Tanggung Jawab Pengguna</h6>
                                            <p className="small">Sebagai pengguna resmi, Anda setuju untuk:</p>
                                            <ul className="small ps-3">
                                                <li>Menjaga kerahasiaan kredensial login Anda</li>
                                                <li>Menggunakan platform hanya untuk keperluan HR yang sah</li>
                                                <li>Tidak membagikan informasi karyawan sensitif tanpa otorisasi</li>
                                                <li>Melaporkan insiden keamanan atau aktivitas mencurigakan segera</li>
                                            </ul>

                                            <h6 className="mt-3 mb-1">Penggunaan Platform</h6>
                                            <p className="small">Platform memungkinkan Anda mengelola data karyawan, laporan, payroll, dan fungsi HR lainnya. Anda bertanggung jawab atas keakuratan dan legalitas informasi yang dimasukkan.</p>

                                            <h6 className="mt-3 mb-1">Larangan</h6>
                                            <p className="small">Anda dilarang:</p>
                                            <ul className="small ps-3">
                                                <li>Mengakses platform untuk tujuan melanggar hukum</li>
                                                <li>Menggunakan platform untuk merugikan individu atau perusahaan</li>
                                                <li>Melakukan reverse engineering atau ekstraksi kode sumber</li>
                                                <li>Mengganggu fungsionalitas atau memasukkan perangkat lunak berbahaya</li>
                                            </ul>

                                            <h6 className="mt-3 mb-1">Kekayaan Intelektual</h6>
                                            <p className="small">Semua konten, perangkat lunak, dan kekayaan intelektual platform dimiliki oleh ARCAV HCM. Reproduksi atau distribusi tanpa izin tertulis dilarang.</p>

                                            <h6 className="mt-3 mb-1">Penghentian Akses</h6>
                                            <p className="small">Kami berhak menangguhkan atau mengakhiri akses Anda kapan saja tanpa pemberitahuan jika Anda melanggar syarat ini.</p>

                                            <h6 className="mt-3 mb-1">Pernyataan Penyangkalan</h6>
                                            <p className="small">Platform disediakan "apa adanya". Kami tidak menjamin platform bebas dari kesalahan atau gangguan. Semua jaminan tersirat dikecualikan sejauh diizinkan hukum.</p>

                                            <h6 className="mt-3 mb-1">Batasan Tanggung Jawab</h6>
                                            <p className="small">ARCAV HCM tidak bertanggung jawab atas kerugian langsung maupun tidak langsung akibat penggunaan atau ketidakmampuan menggunakan platform.</p>

                                            <h6 className="mt-3 mb-1">Perubahan Syarat</h6>
                                            <p className="small">Syarat &amp; Ketentuan dapat diubah sewaktu-waktu. Penggunaan berkelanjutan setelah perubahan berarti penerimaan syarat yang diperbarui.</p>
                                        </div>
                                        {!tocScrolledToEnd && (
                                            <div style={{
                                                position: 'absolute', bottom: 0, left: 0, right: 0,
                                                height: 56, pointerEvents: 'none',
                                                background: 'linear-gradient(to bottom, transparent, rgba(255,255,255,0.95))',
                                                display: 'flex', alignItems: 'flex-end', justifyContent: 'center',
                                                paddingBottom: 6
                                            }}>
                                                <svg style={{ animation: 'arcavBounce 1s ease-in-out infinite' }}
                                                    width="22" height="22" viewBox="0 0 24 24" fill="none"
                                                    stroke="#6c757d" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                                                    <polyline points="6 9 12 15 18 9" />
                                                </svg>
                                            </div>
                                        )}
                                        </div>
                                    )}
                                </div>
                                <div className="modal-footer flex-column align-items-stretch gap-1">
                                    <div style={{ display: 'flex', gap: 8, alignItems: 'center', justifyContent: 'center', marginBottom: 4 }}>
                                        {[{ label: 'Kebijakan Privasi', done: pdpScrolledToEnd }, { label: 'Syarat & Ketentuan', done: tocScrolledToEnd }].map(({ label, done }) => (
                                            <div key={label} style={{
                                                display: 'flex', alignItems: 'center', gap: 5,
                                                fontSize: '0.78rem',
                                                color: done ? '#198754' : '#adb5bd',
                                                fontWeight: done ? 600 : 400,
                                                transition: 'color 0.35s'
                                            }}>
                                                <svg width="16" height="16" viewBox="0 0 16 16" fill="none" style={{ flexShrink: 0 }}>
                                                    <circle cx="8" cy="8" r="7.5" stroke={done ? '#198754' : '#dee2e6'}
                                                        strokeWidth="1.5"
                                                        style={{ transition: 'stroke 0.35s' }} />
                                                    <path d="M4.5 8.3l2.2 2.2 4.5-4.5" stroke={done ? '#198754' : '#dee2e6'}
                                                        strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round"
                                                        style={{
                                                            transition: 'stroke 0.35s, stroke-dashoffset 0.4s',
                                                            strokeDasharray: 9,
                                                            strokeDashoffset: done ? 0 : 9
                                                        }} />
                                                </svg>
                                                {label}
                                            </div>
                                        ))}
                                    </div>
                                    <div className="d-flex justify-content-end gap-2 w-100">
                                        <button type="button" className="btn btn-outline-secondary" onClick={() => setShowPdpModal(false)}>Tutup</button>
                                        <button
                                            type="button"
                                            className="btn btn-success"
                                            disabled={!canAgree}
                                            onClick={handleAgreeAndClose}
                                        >
                                            Saya Mengerti &amp; Setuju
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div className="modal-backdrop fade show" style={{ zIndex: 1055 }}></div>
                </>
            ) : null}
        </>
    );
}

export function PublicLandingReferenceApp({ bootstrap }) {
    const packages = useMemo(() => Array.isArray(bootstrap?.packages) ? bootstrap.packages : [], [bootstrap]);
    const defaultPackage = packages[0] || null;
    const recommendedPackageUuid = useMemo(() => getRecommendedPackageUuid(packages), [packages]);
    const autoOpenSpec = useMemo(() => {
        if (typeof window === 'undefined') return null;
        const params = new URLSearchParams(window.location.search);
        if (params.get('openOnboarding') !== '1') return null;
        const requestedStartMode = params.get('startMode') === 'pending_payment' ? 'pending_payment' : null;
        const requestedPackageUuid = String(params.get('package') || '').trim();
        const picked =
            (requestedPackageUuid && packages.find((item) => item.uuid === requestedPackageUuid)) ||
            (requestedStartMode === 'pending_payment'
                ? (packages.find((item) => !isTrialPackage(item)) || null)
                : null) ||
            defaultPackage ||
            packages[0] ||
            null;
        return { requestedStartMode, pickedPackage: picked };
    }, [packages, defaultPackage]);
    const [mobileNavOpen, setMobileNavOpen] = useState(false);
    const [showDemo, setShowDemo] = useState(false);
    const [onboardingOpen, setOnboardingOpen] = useState(() => Boolean(autoOpenSpec));
    const [submitting, setSubmitting] = useState(false);
    const [submitError, setSubmitError] = useState(null);
    const [formState, setFormState] = useState(() => {
        const base = getInitialFormState(defaultPackage);
        if (!autoOpenSpec) return base;
        const { pickedPackage, requestedStartMode } = autoOpenSpec;
        const trialPackage = isTrialPackage(pickedPackage);
        return {
            ...base,
            packageUuid: pickedPackage?.uuid || base.packageUuid,
            billingCycle: trialPackage ? 'monthly' : base.billingCycle,
            startMode: trialPackage ? 'trial' : (requestedStartMode || base.startMode),
        };
    });
    const containerRef = useRef(null);
    const heroRef = useRef(null);

    const selectedPackage = packages.find((packageItem) => packageItem.uuid === formState.packageUuid) || defaultPackage;
    const primaryPackageUuid = recommendedPackageUuid || defaultPackage?.uuid || '';

    const { scrollYProgress } = useScroll({
        target: containerRef,
        offset: ['start start', 'end end'],
    });

    const heroY = useTransform(scrollYProgress, [0, 0.3], ['0%', '20%']);
    const heroOpacity = useTransform(scrollYProgress, [0, 0.22], [1, 0]);
    const heroScale = useTransform(scrollYProgress, [0, 0.3], [1, 0.96]);

    useLayoutEffect(() => {
        document.documentElement.classList.add('landing-react-ready');
        return () => {
            document.documentElement.classList.remove('landing-react-ready');
        };
    }, []);

    const autoOpenAppliedRef = useRef(Boolean(autoOpenSpec));
    useEffect(() => {
        if (!autoOpenSpec) return;
        setShowDemo(false);
    }, [autoOpenSpec]);

    useEffect(() => {
        if (!onboardingOpen && !showDemo) {
            document.body.classList.remove('modal-open');
            document.body.style.removeProperty('overflow');
            return undefined;
        }

        document.body.classList.add('modal-open');
        document.body.style.overflow = 'hidden';

        return () => {
            document.body.classList.remove('modal-open');
            document.body.style.removeProperty('overflow');
        };
    }, [onboardingOpen, showDemo]);

    useEffect(() => {
        if (!selectedPackage) return;
        const trialPackage = isTrialPackage(selectedPackage);
        setFormState((current) => {
            if (trialPackage) {
                if (current.billingCycle === 'monthly' && current.startMode === 'trial') return current;
                return { ...current, billingCycle: 'monthly', startMode: 'trial' };
            }
            if (current.startMode === 'pending_payment') return current;
            return { ...current, startMode: 'pending_payment' };
        });
    }, [selectedPackage]);

    const handleAnchorClick = () => {
        setMobileNavOpen(false);
    };

    const openOnboarding = (packageUuid) => {
        if (!packages.length) {
            window.location.href = bootstrap.trialUrl;
            return;
        }

        setSubmitError(null);
        setFormState((current) => {
            const nextPackage = packages.find((packageItem) => packageItem.uuid === packageUuid) || defaultPackage;
            const trialPackage = isTrialPackage(nextPackage);

            return {
                ...current,
                packageUuid: packageUuid || defaultPackage?.uuid || '',
                billingCycle: trialPackage ? 'monthly' : current.billingCycle,
                startMode: trialPackage ? 'trial' : current.startMode,
            };
        });
        setOnboardingOpen(true);
        setShowDemo(false);
    };

    const handleChange = (event) => {
        const { name, value } = event.target;
        setSubmitError(null);

        let sanitized = value;
        if (name === 'companyPostalCode') {
            sanitized = sanitizePostalCode(value);
        } else if (PHONE_FIELDS.includes(name)) {
            sanitized = sanitizePhone(value);
        } else if (PERSON_NAME_FIELDS.includes(name)) {
            sanitized = sanitizePersonName(value);
        } else if (PERSON_ROLE_FIELDS.includes(name)) {
            sanitized = sanitizePersonRole(value);
        } else if (name in MAX_LENGTHS) {
            sanitized = String(value ?? '').slice(0, MAX_LENGTHS[name]);
        }

        setFormState((current) => {
            const next = { ...current, [name]: sanitized };
            // Real-time confirm password mismatch
            if (name === 'ownerPassword' || name === 'ownerConfirmPassword') {
                const pwd = name === 'ownerPassword' ? sanitized : current.ownerPassword;
                const cpwd = name === 'ownerConfirmPassword' ? sanitized : current.ownerConfirmPassword;
                next._confirmMismatch = cpwd.length > 0 && pwd !== cpwd;
            }
            return next;
        });
    };

    const handleChangeConsent = (event) => {
        setFormState((current) => ({
            ...current,
            consentAccepted: event.target.checked,
        }));
    };

    const handleTurnstileTokenChange = (token) => {
        setFormState((current) => ({
            ...current,
            turnstileToken: String(token || '').trim(),
        }));
    };

    const handleSubmit = async (event) => {
        event.preventDefault();
        setSubmitting(true);
        setSubmitError(null);

        try {
            const response = await fetch('/v1/public/onboarding', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                },
                credentials: 'same-origin',
                body: JSON.stringify(buildLandingOnboardingPayload(formState)),
            });

            const data = await response.json();
            if (!response.ok || !data?.success) {
                throw { response: { status: response.status, data } };
            }

            const companyCode = data?.data?.company?.code || null;
            const ownerEmail = data?.data?.owner?.email || null;
            const subscriptionStatus = String(data?.data?.subscription?.status || '').trim();
            const isPendingPayment = subscriptionStatus === 'pending_payment';

            const loginBase = String(bootstrap.loginUrl || '/login');
            const separator = loginBase.includes('?') ? '&' : '?';
            const pendingPaymentUrl = `${loginBase}${separator}mode=company&next=%2Fsubscription&companyCode=${encodeURIComponent(String(companyCode || ''))}`;

            setOnboardingOpen(false);

            if (isPendingPayment) {
                window.location.href = pendingPaymentUrl;
                return;
            }

            let message = 'Onboarding berhasil.';

            if (companyCode) {
                message += `\n\nCompany code: ${companyCode}`;
            }

            if (ownerEmail) {
                message += `\nLogin email: ${ownerEmail}`;
            }

            message += '\n\nKlik "Login sekarang" untuk masuk.';

            try {
                if (window.ArcavUi && typeof window.ArcavUi.selectOption === 'function') {
                    await window.ArcavUi.selectOption({
                        title: 'Onboarding berhasil',
                        message,
                        options: [{ value: 'login', label: 'Login sekarang' }],
                    });
                } else {
                    window.alert(message);
                }
            } catch (_uiError) {
                // Fall through: never treat a post-success UI helper failure as a submit error.
            }

            window.location.href = bootstrap.loginUrl;
        } catch (error) {
            setSubmitError(parseError(error));
        } finally {
            setSubmitting(false);
        }
    };

    return (
        <div ref={containerRef} className="landing-react-root mpl-app">
            <div className="mpl-grid" aria-hidden="true"></div>
            <div className="mpl-orb mpl-orb-one" aria-hidden="true"></div>
            <div className="mpl-orb mpl-orb-two" aria-hidden="true"></div>

            <motion.header
                className="mpl-header"
                initial={{ y: -100 }}
                animate={{ y: 0 }}
                transition={{ duration: 0.6, ease: [0.16, 1, 0.3, 1] }}
            >
                <div className="mpl-container">
                    <div className="mpl-header-row">
                        <a className="mpl-brand" href={bootstrap.landingUrl}>
                            <BrandMark bootstrap={bootstrap} />
                            <span>
                                <small>Modern HCM</small>
                                <span>{bootstrap.companyName}</span>
                            </span>
                        </a>

                        <div className="mpl-nav">
                            <nav className="mpl-nav-links">
                                {navLinks.map((item) => (
                                    <a key={item.href} href={item.href}>{item.label}</a>
                                ))}
                            </nav>
                            <div className="mpl-nav-actions">
                                <a href={bootstrap.loginUrl} className="mpl-btn-ghost">Login</a>
                                <button type="button" className="mpl-btn" onClick={() => openOnboarding(primaryPackageUuid)}>
                                    Mulai Gratis <ArrowRight size={18} weight="bold" />
                                </button>
                            </div>
                        </div>

                        <button
                            type="button"
                            className="mpl-toggler"
                            aria-expanded={mobileNavOpen ? 'true' : 'false'}
                            aria-label="Toggle navigation"
                            onClick={() => setMobileNavOpen((current) => !current)}
                        >
                            <SquaresFour size={20} weight="fill" />
                        </button>
                    </div>

                    <div className={`mpl-mobile-nav ${mobileNavOpen ? 'show' : ''}`}>
                        <nav className="mpl-nav-links">
                            {navLinks.map((item) => (
                                <a key={item.href} href={item.href} onClick={handleAnchorClick}>{item.label}</a>
                            ))}
                        </nav>
                        <div className="mpl-nav-actions">
                            <a href={bootstrap.loginUrl} className="mpl-btn-ghost">Login</a>
                            <button type="button" className="mpl-btn" onClick={() => openOnboarding(primaryPackageUuid)}>
                                Mulai Gratis <ArrowRight size={18} weight="bold" />
                            </button>
                        </div>
                    </div>
                </div>
            </motion.header>

            <section ref={heroRef} className="mpl-hero">
                <motion.div style={{ y: heroY, opacity: heroOpacity, scale: heroScale }} className="mpl-container">
                    <div className="mpl-hero-inner">
                        <motion.span
                            className="mpl-badge"
                            initial={{ opacity: 0, scale: 0.92 }}
                            animate={{ opacity: 1, scale: 1 }}
                            transition={{ duration: 0.45, delay: 0.1 }}
                        >
                            <Lightning size={14} weight="fill" /> Modern HR Platform
                        </motion.span>
                        <motion.h1
                            className="mpl-title"
                            initial={{ opacity: 0, y: 30 }}
                            animate={{ opacity: 1, y: 0 }}
                            transition={{ duration: 0.7, delay: 0.2, ease: [0.16, 1, 0.3, 1] }}
                        >
                            Kelola Tim dengan<br />
                            <span className="mpl-gradient-text">Lebih Efisien</span>
                        </motion.h1>
                        <motion.p
                            className="mpl-subtitle"
                            initial={{ opacity: 0, y: 28 }}
                            animate={{ opacity: 1, y: 0 }}
                            transition={{ duration: 0.7, delay: 0.34, ease: [0.16, 1, 0.3, 1] }}
                        >
                            Platform HCM lengkap untuk absensi, cuti, payroll, dan laporan. Structure dan ritme section sekarang mengikuti repo referensi, sementara paket dan flow onboarding tetap diambil dari sistem aktif.
                        </motion.p>

                        <motion.div
                            className="mpl-hero-actions"
                            initial={{ opacity: 0, y: 28 }}
                            animate={{ opacity: 1, y: 0 }}
                            transition={{ duration: 0.7, delay: 0.48, ease: [0.16, 1, 0.3, 1] }}
                        >
                            <button type="button" className="mpl-btn" onClick={() => openOnboarding(primaryPackageUuid)}>
                                Mulai Sekarang <ArrowRight size={18} weight="bold" />
                            </button>
                            <button type="button" className="mpl-btn-outline" onClick={() => setShowDemo(true)}>
                                Lihat Demo
                            </button>
                        </motion.div>

                        <motion.div
                            className="mpl-proof-list"
                            initial={{ opacity: 0 }}
                            animate={{ opacity: 1 }}
                            transition={{ duration: 0.75, delay: 0.62 }}
                        >
                            {heroProofs.map((item) => (
                                <span key={item.text}><item.icon size={18} weight="duotone" /> {item.text}</span>
                            ))}
                        </motion.div>

                        <FadeInSection delay={0.75} className="mpl-hero-stats">
                            {heroStats.map((stat) => (
                                <div key={stat.label} className="mpl-hero-stat">
                                    <p className="small mb-0">{stat.label}</p>
                                    <strong>{stat.value}</strong>
                                </div>
                            ))}
                        </FadeInSection>
                    </div>
                </motion.div>
            </section>

            <section className="mpl-section mpl-section-alt" id="preview">
                <div className="mpl-container">
                    <FadeInSection className="mpl-section-head">
                        <span className="mpl-badge">Dashboard Preview</span>
                        <h2>Dashboard yang <span className="mpl-gradient-text">Intuitif</span></h2>
                        <p>Pantau aktivitas tim, kelola absensi, approval cuti, hingga payroll. Section ini mengikuti komposisi repo referensi dan hanya disambungkan ke CTA onboarding kita.</p>
                    </FadeInSection>
                    <DashboardMockup onOpenOnboarding={openOnboarding} packageUuid={primaryPackageUuid} />
                </div>
            </section>

            <section className="mpl-section" id="features">
                <div className="mpl-container">
                    <FadeInSection className="mpl-section-head">
                        <span className="mpl-badge">Features</span>
                        <h2>Semua yang Tim Anda Butuhkan</h2>
                        <p>Tools lengkap untuk mengelola HR dengan lebih efektif dan terstruktur, tanpa memutus kontrak package dan onboarding yang sudah ada.</p>
                    </FadeInSection>

                    <div className="mpl-grid-four">
                        {featureCards.map((feature, index) => (
                            <FadeInSection key={feature.title} delay={index * 0.08}>
                                <motion.div whileHover={{ y: -8, scale: 1.02 }} transition={{ type: 'spring', stiffness: 300 }} className="mpl-card">
                                    <span className="mpl-feature-icon"><feature.icon size={24} weight="duotone" /></span>
                                    <h3>{feature.title}</h3>
                                    <p className="mb-0">{feature.description}</p>
                                </motion.div>
                            </FadeInSection>
                        ))}
                    </div>

                    <div className="mpl-grid-three mt-4 mt-lg-5" id="solutions">
                        {solutionCards.map((item, index) => (
                            <FadeInSection key={item.title} delay={0.2 + (index * 0.08)}>
                                <motion.div whileHover={{ scale: 1.03 }} className="mpl-card text-center">
                                    <span className="mpl-solution-icon"><item.icon size={26} weight="duotone" /></span>
                                    <h3>{item.title}</h3>
                                    <p className="mb-0">{item.description}</p>
                                </motion.div>
                            </FadeInSection>
                        ))}
                    </div>
                </div>
            </section>

            <section className="mpl-section mpl-section-alt" id="how">
                <div className="mpl-container">
                    <FadeInSection className="mpl-section-head">
                        <span className="mpl-badge">How it Works</span>
                        <h2>Mulai dalam 3 Langkah</h2>
                        <p>Jalur onboarding tetap pendek, sekarang dibungkus ulang mengikuti ritme visual repo referensi.</p>
                    </FadeInSection>

                    <div className="mpl-step-wrap">
                        <div className="mpl-step-line" aria-hidden="true"></div>
                        {steps.map((item, index) => (
                            <FadeInSection key={item.step} delay={index * 0.1} className="mpl-step-row">
                                <span className="mpl-step-chip">{item.step}</span>
                                <motion.div whileHover={{ x: 8 }} className="mpl-step-card">
                                    <div className="mpl-step-meta">
                                        <h3 className="mb-0">{item.title}</h3>
                                        <span className="mpl-step-tag">{item.tag}</span>
                                    </div>
                                    <strong className="d-block mb-2">{item.subtitle}</strong>
                                    <p className="mb-0">{item.description}</p>
                                </motion.div>
                            </FadeInSection>
                        ))}
                    </div>
                </div>
            </section>

            <section className="mpl-section" id="pricing">
                <div className="mpl-container">
                    <FadeInSection className="mpl-section-head">
                        <span className="mpl-badge">Pricing</span>
                        <h2>Paket yang <span className="mpl-gradient-text">Fleksibel</span></h2>
                        <p>Pilih paket sesuai ukuran dan kebutuhan tim Anda. Card pricing sekarang mengikuti hirarki visual repo referensi, tetapi datanya tetap berasal dari paket aktif backend.</p>
                    </FadeInSection>

                    {packages.length ? (
                        <div className="mpl-pricing-grid" data-react-packages-grid>
                            {packages.map((packageItem) => (
                                <FadeInSection key={packageItem.uuid}>
                                    <PackageCard
                                        isHighlighted={packageItem.uuid === recommendedPackageUuid}
                                        onOpenOnboarding={openOnboarding}
                                        packageItem={packageItem}
                                        trialUrl={bootstrap.trialUrl}
                                    />
                                </FadeInSection>
                            ))}
                        </div>
                    ) : (
                        <div className="mpl-card text-center">
                            <h3 className="mb-2">Paket belum tersedia</h3>
                            <p className="mb-3">Seed atau aktifkan paket terlebih dulu agar pricing section bisa merender data dinamis.</p>
                            <a className="mpl-btn" href={bootstrap.trialUrl}>Buka halaman trial</a>
                        </div>
                    )}
                </div>
            </section>

            <section className="mpl-section">
                <div className="mpl-container">
                    <FadeInSection>
                        <div className="mpl-cta-card">
                            <span className="mpl-badge"><Lightning size={14} weight="fill" /> Ready to launch</span>
                            <h2>Siap Transformasi HR Anda?</h2>
                            <p>Bergabung dengan perusahaan yang butuh landing lebih dekat ke repo referensi, tapi tetap ingin menjaga flow onboarding, package selection, dan owner login yang sudah hidup di sistem ini.</p>
                            <div className="mpl-cta-actions">
                                <button type="button" className="mpl-btn" onClick={() => openOnboarding(primaryPackageUuid)}>
                                    Coba Gratis 30 Hari <ArrowRight size={18} weight="bold" />
                                </button>
                                <button type="button" className="mpl-btn-outline" onClick={() => setShowDemo(true)}>
                                    Lihat Demo
                                </button>
                            </div>
                            <div className="mpl-cta-checks">
                                <span><CheckCircle size={16} weight="fill" /> Tanpa kartu kredit</span>
                                <span><CheckCircle size={16} weight="fill" /> Setup dalam 1 jam</span>
                                <span><CheckCircle size={16} weight="fill" /> Support tim lokal</span>
                            </div>
                        </div>
                    </FadeInSection>
                </div>
            </section>

            <footer className="mpl-footer">
                <div className="mpl-container">
                    <div className="mpl-footer-grid">
                        <div>
                            <div className="mpl-footer-brand-row">
                                <BrandMark bootstrap={bootstrap} />
                                <div className="mpl-footer-brand">{bootstrap.companyName}</div>
                            </div>
                            <p className="mpl-footer-copy">Platform HCM modern untuk perusahaan Indonesia. Kelola tim dengan lebih efisien dan terstruktur sambil menjaga trial, package, dan onboarding flow tetap utuh.</p>
                        </div>
                        <div>
                            <ul className="mpl-footer-links">
                                <li><strong>Produk</strong></li>
                                {navLinks.map((item) => (
                                    <li key={item.href}><a href={item.href}>{item.label}</a></li>
                                ))}
                            </ul>
                        </div>
                        <div>
                            <ul className="mpl-footer-links">
                                <li><strong>Aksi</strong></li>
                                <li><a href={bootstrap.loginUrl}>Login</a></li>
                                <li><a href={bootstrap.trialUrl}>Mulai Trial</a></li>
                                <li><button type="button" className="btn btn-link p-0 text-decoration-none" onClick={() => setShowDemo(true)}>Lihat Demo</button></li>
                            </ul>
                        </div>
                    </div>

                    <div className="mpl-footer-bottom">
                        <span>© 2026 Arkav HCM. All rights reserved.</span>
                        <div className="mpl-footer-bottom-links">
                            <a href={bootstrap.landingUrl}>Landing</a>
                            <a href={bootstrap.loginUrl}>Login</a>
                        </div>
                    </div>
                </div>
            </footer>

            {showDemo ? <DemoOverlay onClose={() => setShowDemo(false)} onOpenOnboarding={openOnboarding} packageUuid={primaryPackageUuid} /> : null}
            {onboardingOpen ? (
                <OnboardingModal
                    error={submitError}
                    formState={formState}
                    onChange={handleChange}
                    onChangeConsent={handleChangeConsent}
                    onClose={() => setOnboardingOpen(false)}
                    onSubmit={handleSubmit}
                    onTurnstileTokenChange={handleTurnstileTokenChange}
                    packages={packages}
                    submitting={submitting}
                    turnstileEnabled={Boolean(bootstrap?.turnstileEnabled && bootstrap?.turnstileSiteKey)}
                    turnstileSiteKey={String(bootstrap?.turnstileSiteKey || '').trim()}
                />
            ) : null}
        </div>
    );
}