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
        description: 'Clock in/out dengan shift scheduling, monitoring keterlambatan real-time, dan lokasi berbasis GPS.',
    },
    {
        icon: FileText,
        title: 'Manajemen Cuti',
        description: 'Approval flow multi-level yang dapat dikonfigurasi per role. Lengkap dengan tracking dan notifikasi real-time.',
    },
    {
        icon: ChartBar,
        title: 'Payroll + THR',
        description: 'Proses payroll terintegrasi dari draft hingga final, termasuk kalkulasi THR dan PPh 21 otomatis.',
    },
    {
        icon: Users,
        title: 'Employee Portal',
        description: 'Self-service untuk karyawan, manager, dan finance. Akses terbatas sesuai peran dan kebutuhan operasional.',
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
    { label: 'Modul aktif', value: '8+' },
    { label: 'Siap onboarding', value: '24/7' },
    { label: 'Langkah mulai', value: '3 tahap' },
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
    { key: 'leave', label: 'Leave', icon: FileText, badge: 'Approval queue' },
    { key: 'payroll', label: 'Payroll', icon: CurrencyCircleDollar, badge: 'Ready to close' },
];

const previewContent = {
    overview: {
        heading: 'Module status & aktivitas minggu ini',
        subheading: 'Dashboard ringkasan',
        icon: ChartBar,
        status: 'Live',
        useModuleGrid: true,
        moduleStatuses: [
            { name: 'Employee', status: 'live', count: 124 },
            { name: 'Attendance', status: 'live', count: 156 },
            { name: 'Leave', status: 'live', count: 8 },
            { name: 'Payroll', status: 'active', count: 3 },
            { name: 'Approval', status: 'active', count: 12 },
            { name: 'Notifications', status: 'live', count: 'auto' },
        ],
        stats: [
            { label: 'Modul aktif', value: '6+' },
            { label: 'Health', value: '98%' },
        ],
        activitiesHeading: 'Highlight minggu ini',
        activitiesSubheading: 'Cross-module',
        activitiesIcon: Lightning,
        activities: [
            { icon: Users, title: '14 karyawan baru disetujui', subtitle: 'Employee onboarding' },
            { icon: CalendarCheck, title: 'Attendance 98% kehadiran', subtitle: 'Weekly average' },
            { icon: CheckCircle, title: 'Approval queue selesai', subtitle: '100% processed' },
        ],
        nextTitle: 'Mulai menggunakan HCM',
        nextDescription: 'Buat akun company dan owner, lalu pilih paket sesuai kebutuhan.',
    },
    employees: {
        heading: 'Direktori karyawan & struktur organisasi',
        subheading: 'Employee management',
        icon: Users,
        status: '124 aktif',
        useOrgTree: true,
        chartData: ['Employee', 'Manager', 'Team Lead', 'Intern'],
        stats: [
            { label: 'Departemen', value: '8' },
            { label: 'Manager seats', value: '14' },
        ],
        activitiesHeading: 'Perubahan struktur terbaru',
        activitiesSubheading: 'Org records',
        activitiesIcon: Users,
        activities: [
            { icon: Users, title: 'Tim Finance ditambah 3 member', subtitle: 'Approved & activated' },
            { icon: CheckCircle, title: 'Self-service portal ready', subtitle: 'All employee roles' },
            { icon: User, title: 'HR Manager promosi level', subtitle: 'Role reassignment' },
        ],
        nextTitle: 'Setup employee lengkap',
        nextDescription: 'Import data, assign role, dan publikasikan akses portal untuk semua.',
    },
    attendance: {
        heading: 'Monitoring kehadiran real-time',
        subheading: 'Attendance daily',
        icon: CalendarCheck,
        status: 'Realtime',
        useTimeline: true,
        timelineData: ['07:30', '08:15', '08:45', '09:00', '10:15', '11:30', '16:30'],
        stats: [
            { label: 'On time', value: '91%' },
            { label: 'Late today', value: '3 orang' },
        ],
        activitiesHeading: 'Queue approval & alerts',
        activitiesSubheading: 'Real-time',
        activitiesIcon: CalendarCheck,
        activities: [
            { icon: CalendarCheck, title: '23 check-in validated', subtitle: 'Dari 3 lokasi' },
            { icon: CalendarBlank, title: '2 cuti menunggu approval', subtitle: 'Manager review queue' },
            { icon: Lightning, title: 'Late alert: 3 karyawan', subtitle: 'Automasi notifikasi' },
        ],
        nextTitle: 'Setup attendance flow',
        nextDescription: 'Konfigurasi shift, holiday calendar, dan approval chain per manager.',
    },
    leave: {
        heading: 'Manajemen cuti & approval chain',
        subheading: 'Leave requests',
        icon: FileText,
        status: 'Approval queue',
        useApprovalQueue: true,
        stats: [
            { label: 'Pending', value: '5' },
            { label: 'This month', value: '12' },
        ],
        activitiesHeading: 'Approval requests',
        activitiesSubheading: 'Configured flow',
        activitiesIcon: FileText,
        activities: [
            { icon: FileText, title: 'Cuti 3 hari - Budi Santoso', subtitle: 'Waiting for manager' },
            { icon: CheckCircle, title: 'Sakit 1 hari - Approved', subtitle: 'By: Manajer Finance' },
            { icon: FileText, title: 'Cuti khusus - Pending HR', subtitle: 'Manager approved' },
        ],
        nextTitle: 'Aktifkan approval settings',
        nextDescription: 'Konfigurasi multi-level approval, notifikasi, dan escalation rule.',
    },
    payroll: {
        heading: 'Payroll & billing workspace',
        subheading: 'Payroll period',
        icon: CurrencyCircleDollar,
        status: 'May 2026',
        usePayrollBreakdown: true,
        payrollBreakdown: [
            { label: 'Basic salary', value: '60%', color: '#4F46E5' },
            { label: 'Allowance', value: '20%', color: '#06B6D4' },
            { label: 'Deduction', value: '-8%', color: '#EF4444' },
            { label: 'Net', value: '72%', color: '#10B981' },
        ],
        stats: [
            { label: 'Draft payroll', value: '3' },
            { label: 'Invoice pending', value: '11' },
        ],
        activitiesHeading: 'Closing checklist',
        activitiesSubheading: 'Payroll status',
        activitiesIcon: CurrencyCircleDollar,
        activities: [
            { icon: FileText, title: 'Payroll draft ready', subtitle: 'Calculated & validated' },
            { icon: CurrencyCircleDollar, title: 'THR calculation done', subtitle: 'PPh 21 auto-deducted' },
            { icon: CheckCircle, title: 'Invoice generated', subtitle: 'Ready to send' },
        ],
        nextTitle: 'Finalize & distribute',
        nextDescription: 'Review breakdown, publish slip digital, dan kirim invoice ke payment gateway.',
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
    const ActivitiesIcon = activePreview.activitiesIcon || activePreview.activitiesIcon;

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

                            {activePreview.useModuleGrid ? (
                                <div className="mpl-module-grid" style={{ display: 'grid', gridTemplateColumns: 'repeat(3, 1fr)', gap: 6, marginTop: 16, marginBottom: 12 }}>
                                    {(activePreview.moduleStatuses || []).map((mod) => (
                                        <div key={mod.name} style={{ padding: 8, borderRadius: 6, background: mod.status === 'live' ? '#D4FC7E' : '#FEF08A', fontSize: '0.75rem' }}>
                                            <p style={{ margin: 0, fontWeight: 600 }}>{mod.name}</p>
                                            <p style={{ margin: '2px 0 0', opacity: 0.7 }}>{mod.count} items</p>
                                        </div>
                                    ))}
                                </div>
                            ) : activePreview.useTimeline ? (
                                <div style={{ marginTop: 12, display: 'flex', gap: 6, overflowX: 'auto' }}>
                                    {(activePreview.timelineData || []).map((time, idx) => (
                                        <motion.div
                                            key={time}
                                            initial={{ opacity: 0 }}
                                            whileInView={{ opacity: 1 }}
                                            transition={{ delay: idx * 0.04 }}
                                            style={{ padding: '6px 10px', borderRadius: 4, background: idx < 4 ? '#10B981' : '#DBEAFE', fontSize: '0.75rem', fontWeight: 500, whiteSpace: 'nowrap' }}
                                        >
                                            {time}
                                        </motion.div>
                                    ))}
                                </div>
                            ) : activePreview.usePayrollBreakdown ? (
                                <div style={{ marginTop: 12 }}>
                                    {(activePreview.payrollBreakdown || []).map((item, idx) => (
                                        <div key={item.label} style={{ marginBottom: 8 }}>
                                            <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: '0.75rem', marginBottom: 2 }}>
                                                <span>{item.label}</span>
                                                <span style={{ fontWeight: 600 }}>{item.value}</span>
                                            </div>
                                            <div style={{ height: 6, background: '#E5E7EB', borderRadius: 3, overflow: 'hidden' }}>
                                                <motion.div
                                                    initial={{ width: 0 }}
                                                    whileInView={{ width: item.value }}
                                                    viewport={{ once: true }}
                                                    transition={{ delay: idx * 0.05, duration: 0.4 }}
                                                    style={{ height: '100%', background: item.color }}
                                                />
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            ) : activePreview.useApprovalQueue ? (
                                <div style={{ marginTop: 12, display: 'flex', flexDirection: 'column', gap: 8 }}>
                                    {[1, 2, 3].map((n) => (
                                        <motion.div
                                            key={n}
                                            initial={{ opacity: 0, x: -8 }}
                                            whileInView={{ opacity: 1, x: 0 }}
                                            transition={{ delay: n * 0.06 }}
                                            style={{ padding: 8, borderRadius: 6, background: '#F3E8FF', borderLeft: '3px solid #A855F7', fontSize: '0.75rem' }}
                                        >
                                            <p style={{ margin: 0, fontWeight: 600 }}>Request #{n}</p>
                                            <p style={{ margin: '2px 0 0', opacity: 0.7 }}>Waiting approval</p>
                                        </motion.div>
                                    ))}
                                </div>
                            ) : (
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
                            )}

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
            <div className="mpl-price-meta">{trialPackage ? 'Aktivasi trial' : '/bulan'}{!trialPackage ? ` • ${packageItem.billingUnit || 'company'}` : ''}</div>

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
                    Lihat detail paket
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