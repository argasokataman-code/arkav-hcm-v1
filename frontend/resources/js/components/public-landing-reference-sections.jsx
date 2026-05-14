import React, { useRef, useState } from 'react';
import { AnimatePresence, motion, useInView } from 'framer-motion';
import {
    ArrowRight,
    CalendarBlank,
    CalendarCheck,
    ChartBar,
    CheckCircle,
    CurrencyCircleDollar,
    FileText,
    Lightning,
    SquaresFour,
    User,
    Users,
    X,
} from '@phosphor-icons/react';

import { formatIdr, isTrialPackage } from '../public/public-landing-contract.js';

export const featureCards = [
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
        icon: ChartBar,
        title: 'Payroll Auto',
        description: 'Proses payroll lebih tertata dari draft sampai final, lengkap dengan ringkasan biaya yang jelas.',
    },
    {
        icon: Users,
        title: 'Employee Portal',
        description: 'Karyawan dan owner punya akses mandiri untuk kebutuhan harian tanpa alur yang membingungkan.',
    },
];

export const solutionCards = [
    {
        icon: Lightning,
        title: 'Setup Instan',
        description: 'Aktivasi akun perusahaan bisa dimulai cepat, tanpa proses setup yang rumit.',
    },
    {
        icon: Users,
        title: 'Role-Based Access',
        description: 'Setiap peran mendapatkan akses yang sesuai, sehingga data tetap aman dan relevan.',
    },
    {
        icon: CheckCircle,
        title: 'Audit Trail',
        description: 'Riwayat aktivitas dan dokumen penting tersimpan rapi untuk kebutuhan kontrol dan audit.',
    },
];

export const steps = [
    {
        step: '01',
        title: 'Pilih Paket',
        subtitle: 'Aktifkan sesuai kebutuhan tim',
        description: 'Pilih paket yang paling sesuai kebutuhan perusahaan dan kesiapan operasional tim.',
        tag: 'Setup',
    },
    {
        step: '02',
        title: 'Setup Company',
        subtitle: 'Isi data owner dan perusahaan',
        description: 'Lengkapi profil perusahaan, data owner, dan konfirmasi persetujuan untuk mulai operasional.',
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

export const navLinks = [
    { href: '#features', label: 'Fitur' },
    { href: '#solutions', label: 'Solusi' },
    { href: '#how', label: 'Cara kerja' },
    { href: '#pricing', label: 'Paket' },
];

export const heroProofs = [
    { icon: CheckCircle, text: 'Aktivasi cepat' },
    { icon: Users, text: 'Akses per peran' },
    { icon: FileText, text: 'Laporan siap pakai' },
];

export const heroStats = [
    { label: 'Modul inti', value: '6+' },
    { label: 'Siap onboarding', value: '24/7' },
    { label: 'Langkah mulai', value: '4 tahap' },
];

export function FadeInSection({ children, delay = 0, className = '' }) {
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

export function getRecommendedPackageUuid(packages) {
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

export function BrandMark({ bootstrap, className = '' }) {
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

export function DashboardMockup({ onOpenOnboarding, packageUuid }) {
    const [activeTab, setActiveTab] = useState('overview');
    const activePreview = previewContent[activeTab] || previewContent.overview;
    const activeTabMeta = previewTabs.find((tab) => tab.key === activeTab) || previewTabs[0];
    const ActiveTabIcon = activePreview.icon;
    const ActivitiesIcon = activePreview.activitiesIcon;

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
                                    <p className="small mb-1 d-flex align-items-center gap-2"><ActivitiesIcon size={14} weight="duotone" /> {activePreview.activitiesSubheading}</p>
                                    <strong>{activePreview.activitiesHeading}</strong>
                                </div>
                            </div>

                            <div className="mpl-activity-list">
                                {activePreview.activities.map((activity, index) => {
                                    const ActivityIcon = activity.icon;

                                    return (
                                        <motion.div
                                            key={activity.title}
                                            className="mpl-activity-item"
                                            initial={{ opacity: 0, x: 16 }}
                                            whileInView={{ opacity: 1, x: 0 }}
                                            viewport={{ once: true }}
                                            transition={{ delay: 0.25 + (index * 0.08), duration: 0.45 }}
                                        >
                                            <span className="mpl-icon-box"><ActivityIcon size={18} weight="duotone" /></span>
                                            <div>
                                                <strong className="d-block mb-1">{activity.title}</strong>
                                                <p className="small mb-0">{activity.subtitle}</p>
                                            </div>
                                        </motion.div>
                                    );
                                })}
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

export function PackageCard({ isHighlighted, onOpenOnboarding, packageItem, trialUrl }) {
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

export function DemoOverlay({ onClose, onOpenOnboarding, packageUuid }) {
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
                            <h2 className="h3 mt-3 mb-2">Lihat pengalaman dashboard Anda</h2>
                            <p className="text-muted mb-0">Gunakan demo ini untuk melihat alur kerja utama sebelum memulai onboarding.</p>
                        </div>
                        <button type="button" className="mpl-demo-close" aria-label="Close demo" onClick={onClose}>
                            <X size={20} />
                        </button>
                    </div>

                    <div className="mpl-demo-grid">
                        <DashboardMockup onOpenOnboarding={onOpenOnboarding} packageUuid={packageUuid} />
                        <div className="mpl-demo-summary">
                            {solutionCards.map((item) => {
                                const ItemIcon = item.icon;

                                return (
                                    <div key={item.title} className="mpl-card">
                                        <span className="mpl-solution-icon"><ItemIcon size={24} weight="duotone" /></span>
                                        <h3>{item.title}</h3>
                                        <p className="mb-0">{item.description}</p>
                                    </div>
                                );
                            })}
                        </div>
                    </div>
                </motion.div>
            </motion.div>
        </AnimatePresence>
    );
}