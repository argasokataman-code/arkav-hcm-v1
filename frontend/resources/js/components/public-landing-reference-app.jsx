import React, { useEffect, useEffectEvent, useLayoutEffect, useMemo, useRef, useState } from 'react';
import { motion, useScroll, useTransform } from 'framer-motion';
import {
    ArrowRight,
    CalendarCheck,
    ChartLineUp,
    CheckCircle,
    CurrencyCircleDollar,
    FileText,
    Lightning,
    SquaresFour,
    Target,
    Users,
    UsersFour,
} from '@phosphor-icons/react';

import { buildLandingOnboardingPayload, formatIdr, isTrialPackage } from '../public/public-landing-contract.js';
import {
    buildFieldErrors,
    buildInvoiceBreakdownMessage,
    buildOnboardingSuccessMessageHtml,
    escapeHtml,
    getE2ETurnstileToken,
    getInitialFormState,
    MAX_LENGTHS,
    parseError,
    PERSON_NAME_FIELDS,
    PERSON_ROLE_FIELDS,
    PHONE_FIELDS,
    sanitizePersonName,
    sanitizePersonRole,
    sanitizePhone,
    sanitizePostalCode,
} from './public-landing-reference-helpers.js';
import {
    BrandMark,
    DashboardMockup,
    DemoOverlay,
    FadeInSection,
    featureCards,
    getRecommendedPackageUuid,
    heroProofs,
    heroStats,
    navLinks,
    PackageCard,
    solutionCards,
    steps,
} from './public-landing-reference-sections.jsx';

export function OnboardingModal({ error, formState, onChange, onChangeConsent, onClose, onSubmit, packages, submitting, turnstileEnabled, turnstileHideTestNotice, turnstileSiteKey, onTurnstileTokenChange, requestedStartMode }) {
    const selectedPackage = packages.find((packageItem) => packageItem.uuid === formState.packageUuid) || null;
    const isTrialSelected = isTrialPackage(selectedPackage);
    const lockBillingCycle = isTrialSelected;
    const isPendingPaymentRegistration = requestedStartMode === 'pending_payment' && !isTrialSelected;
    const fieldErrors = useMemo(() => buildFieldErrors(error?.details), [error]);
    const fe = (name) => fieldErrors[name] || null;
    const [showPdpModal, setShowPdpModal] = useState(false);
    const [activeConsentTab, setActiveConsentTab] = useState('pdp');
    const [pdpScrolledToEnd, setPdpScrolledToEnd] = useState(false);
    const [tocScrolledToEnd, setTocScrolledToEnd] = useState(false);
    const [pdpTocScrolledToEnd, setPdpTocScrolledToEnd] = useState(false);
    const [pdpRead, setPdpRead] = useState(false);
    const canAgree = pdpTocScrolledToEnd;
    const [turnstileFallback, setTurnstileFallback] = useState(null);

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
    const handlePdpTocBodyScroll = (e) => {
        const el = e.currentTarget;
        if (!pdpTocScrolledToEnd && el.scrollTop + el.clientHeight >= el.scrollHeight - 60) {
            setPdpTocScrolledToEnd(true);
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
    const turnstileFallbackMeta = useMemo(() => {
        if (!turnstileFallback) {
            return null;
        }

        if (turnstileFallback.kind === 'error') {
            if (turnstileFallback.code === '110200') {
                return {
                    variant: 'error',
                    title: 'Cloudflare Turnstile belum bisa dipakai di local',
                    message: 'Key yang aktif tidak mengizinkan domain ini. Tambahkan localhost/127.0.0.1 ke hostname widget Cloudflare atau pakai test key khusus local.',
                };
            }

            return {
                variant: 'error',
                title: 'Cloudflare Turnstile gagal dimuat',
                message: 'Widget verifikasi belum tersedia di halaman ini. Periksa key, hostname yang diizinkan, atau koneksi ke layanan Cloudflare.',
            };
        }

        return {
            variant: 'info',
            title: 'Cloudflare Turnstile aktif',
            message: 'Mode test lokal sedang digunakan. Verifikasi keamanan sudah disiapkan untuk submit onboarding.',
        };
    }, [turnstileFallback]);

    useEffect(() => {
        if (!turnstileEnabled || !turnstileSiteKey || !turnstileContainerRef.current) {
            setTurnstileFallback(null);
            return undefined;
        }

        if (e2eTurnstileToken) {
            setTurnstileFallback(null);
            emitTurnstileTokenChange(e2eTurnstileToken);
            return () => {
                emitTurnstileTokenChange('');
            };
        }

        let cancelled = false;
        let attempts = 0;
        let visualCheckTimer = null;

        const scheduleVisualCheck = () => {
            if (visualCheckTimer) {
                window.clearTimeout(visualCheckTimer);
            }

            visualCheckTimer = window.setTimeout(() => {
                if (cancelled || !turnstileContainerRef.current) {
                    return;
                }

                const hasIframe = Boolean(turnstileContainerRef.current.querySelector('iframe'));
                const hiddenInput = turnstileContainerRef.current.querySelector('input[name="cf-turnstile-response"]');
                const hasToken = Boolean(hiddenInput && String(hiddenInput.value || '').trim());

                if (hasIframe) {
                    setTurnstileFallback(null);
                    return;
                }

                if (hasToken) {
                    setTurnstileFallback({ kind: 'test' });
                }
            }, 900);
        };

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
                    if (turnstileContainerRef.current?.querySelector('iframe')) {
                        setTurnstileFallback(null);
                    } else if (String(token || '').trim()) {
                        setTurnstileFallback({ kind: 'test' });
                    }

                    emitTurnstileTokenChange(token || '');
                },
                'expired-callback': () => {
                    if (turnstileContainerRef.current?.querySelector('iframe')) {
                        setTurnstileFallback(null);
                    }

                    emitTurnstileTokenChange('');
                },
                'error-callback': (errorCode) => {
                    setTurnstileFallback({ kind: 'error', code: String(errorCode || '') });
                    emitTurnstileTokenChange('');

                    return true;
                },
            });

            scheduleVisualCheck();
        };

        mountWidget();

        return () => {
            cancelled = true;
            setTurnstileFallback(null);
            emitTurnstileTokenChange('');

            if (visualCheckTimer) {
                window.clearTimeout(visualCheckTimer);
            }

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
                                <p className="text-muted mb-0">Lengkapi data perusahaan dan owner untuk mulai menggunakan platform.</p>
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
                                                {isPendingPaymentRegistration ? null : (
                                                    <div className="col-md-3">
                                                        <label className="form-label fw-semibold">Mode aktivasi</label>
                                                        <select className="form-select" name="startMode" value={formState.startMode} onChange={onChange} disabled>
                                                            {isTrialSelected ? (
                                                                <option value="trial">Trial</option>
                                                            ) : (
                                                                <option value="pending_payment">Aktivasi subscription</option>
                                                            )}
                                                        </select>
                                                    </div>
                                                )}
                                                <div className="col-12">
                                                    <div className="small text-muted">
                                                        {isPendingPaymentRegistration
                                                            ? 'Registrasi company baru ini akan lanjut ke aktivasi subscription setelah onboarding selesai.'
                                                            : isTrialSelected
                                                            ? 'Paket trial otomatis memakai billing bulanan dan aktivasi trial.'
                                                            : 'Paket berbayar akan lanjut ke aktivasi subscription setelah onboarding selesai.'}
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
                                                    <input className={`form-control${fe('ownerPassword') ? ' is-invalid' : ''}`} name="ownerPassword" type="password" value={formState.ownerPassword} onChange={onChange} minLength={8} maxLength={64} pattern="(?=.*[A-Z])(?=.*[a-z])(?=.*\d)[A-Za-z\d@$!%*?&._-]{8,64}" title="Password minimal 8 karakter, wajib ada huruf besar, huruf kecil, dan angka." required />
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
                                                        <div className={turnstileHideTestNotice ? 'mpl-turnstile-shell mpl-turnstile-shell--hide-test-notice' : 'mpl-turnstile-shell'}>
                                                            <div ref={turnstileContainerRef}></div>
                                                            {turnstileFallbackMeta ? (
                                                                <div className={`mpl-turnstile-fallback mpl-turnstile-fallback--${turnstileFallbackMeta.variant}`} role="status" aria-live="polite">
                                                                    <strong>{turnstileFallbackMeta.title}</strong>
                                                                    <span>{turnstileFallbackMeta.message}</span>
                                                                </div>
                                                            ) : null}
                                                        </div>
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
                                <label className="form-check-label small text-muted" htmlFor="consentAcceptedReact">
                                    {pdpRead
                                        ? <>Saya menyetujui <strong>Kebijakan Privasi</strong> dan <strong>Syarat &amp; Ketentuan</strong> ARCAV HCM.</>
                                        : <><button type="button" className="btn btn-link btn-sm p-0 align-baseline fw-semibold" onClick={() => setShowPdpModal(true)}>Lihat Kebijakan Privasi &amp; Syarat</button> sebelum menyetujui.</>
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
                                    {submitting ? 'Memproses...' : 'Lanjutkan pendaftaran'}
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
                                        <h5 className="modal-title">Kebijakan Privasi dan Syarat &amp; Ketentuan</h5>
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
                                    <style>{`@keyframes arcavBounce{0%,100%{transform:translateY(0)}50%{transform:translateY(5px)}}`}</style>
                                    <div style={{ position: 'relative' }}>
                                        <div
                                            className="px-4 py-3"
                                            style={{ overflowY: 'auto', height: '55vh', whiteSpace: 'pre-wrap' }}
                                            onScroll={handlePdpTocBodyScroll}
                                        >
                                            <p className="text-muted small">Berlaku efektif: 1 Mei 2026 &mdash; Terakhir diperbarui: 1 Mei 2026</p>
                                            <p className="lead small">Ringkasan singkat: Kami mengumpulkan data yang diperlukan untuk menjalankan layanan HR, memenuhi kewajiban hukum, dan menjaga keamanan platform. Kami hanya memproses data yang relevan dan meminimalkan akses pihak ketiga.</p>

                                            <h6 className="mt-3 mb-1">1. Data yang kami kumpulkan</h6>
                                            <ul className="small ps-3">
                                                <li>Informasi kontak dasar: nama, email, nomor telepon</li>
                                                <li>Data perusahaan dan pekerjaan: jabatan, departemen, tanggal mulai</li>
                                                <li>Data administratif: NIK/KTP, NPWP, rekening bank (untuk payroll)</li>
                                                <li>Log akses dan keamanan: IP, user-agent, waktu akses</li>
                                                <li>Data opsional yang memerlukan persetujuan: foto selfie biometrik, lokasi GPS</li>
                                            </ul>

                                            <h6 className="mt-3 mb-1">2. Untuk apa data digunakan</h6>
                                            <ul className="small ps-3">
                                                <li>Menjalankan layanan HR dan manajemen akun</li>
                                                <li>Memproses penggajian dan memenuhi kewajiban pajak</li>
                                                <li>Mengelola BPJS dan kewajiban hukum lainnya</li>
                                                <li>Meningkatkan keamanan dan mencegah penyalahgunaan</li>
                                                <li>Menyediakan fitur tambahan seperti asisten AI (tanpa PII) jika diaktifkan</li>
                                            </ul>

                                            <h6 className="mt-3 mb-1">3. Pihak ketiga yang kami gunakan</h6>
                                            <div className="table-responsive">
                                                <table className="table table-sm table-borderless small">
                                                    <thead className="table-light"><tr><th>Penyedia</th><th>Data (kategori)</th><th>Tujuan singkat</th></tr></thead>
                                                    <tbody>
                                                        <tr><td>Midtrans</td><td>Nama, email, data tagihan</td><td>Pemrosesan pembayaran</td></tr>
                                                        <tr><td>Penyedia AI</td><td>Teks intent (non-PII)</td><td>Fitur asisten AI (opsional)</td></tr>
                                                        <tr><td>Cloudflare Turnstile</td><td>Token captcha</td><td>Pencegahan bot &amp; keamanan</td></tr>
                                                    </tbody>
                                                </table>
                                            </div>
                                            <p className="small text-muted">Catatan: Kami mengikat penyedia melalui perjanjian perlindungan data (DPA) bila diperlukan dan hanya membagikan data yang relevan untuk tujuan layanan. Untuk detail kebijakan masing-masing penyedia, lihat tautan kebijakan penyedia terkait.</p>

                                            <h6 className="mt-3 mb-1">4. Lama penyimpanan</h6>
                                            <ul className="small ps-3">
                                                <li>Data karyawan aktif: selama hubungan kerja</li>
                                                <li>Data mantan karyawan: hingga 5 tahun sesuai kebutuhan administrasi</li>
                                                <li>Data payroll &amp; pajak: sesuai kewajiban hukum hingga 10 tahun</li>
                                                <li>Log keamanan: disimpan sementara (biasanya &lt; 1 tahun)</li>
                                            </ul>

                                            <h6 className="mt-3 mb-1">5. Hak Anda</h6>
                                            <ul className="small ps-3">
                                                <li>Hak akses, perbaikan, dan portabilitas: ajukan ke DPO (respon dalam 14 hari kerja)</li>
                                                <li>Hak penghapusan: kami proses sesuai hukum (estimasi 30 hari kerja)</li>
                                                <li>Hak mencabut persetujuan: dapat dilakukan kapan saja untuk pemrosesan berbasis persetujuan</li>
                                            </ul>

                                            <h6 className="mt-3 mb-1">6. Keamanan &amp; kontak</h6>
                                            <ul className="small ps-3">
                                                <li>Komunikasi terenkripsi (HTTPS/TLS)</li>
                                                <li>Kata sandi disimpan aman (hashing modern)</li>
                                                <li>RBAC dan audit log untuk operasi sensitif</li>
                                                <li>Laporkan masalah atau permintaan data ke: <a href="mailto:dpo@arcav.id">dpo@arcav.id</a></li>
                                            </ul>

                                            <hr />

                                            <p className="small">Dengan menggunakan platform ini atau menyelesaikan pendaftaran, Anda menyetujui Kebijakan Privasi dan Syarat &amp; Ketentuan ARCAV HCM. Jika Anda tidak setuju, mohon jangan lanjutkan pendaftaran.</p>
                                        </div>
                                        {!pdpTocScrolledToEnd && (
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
                                </div>
                                <div className="modal-footer flex-column align-items-stretch gap-1">
                                    <div style={{ display: 'flex', gap: 8, alignItems: 'center', justifyContent: 'center', marginBottom: 4 }}>
                                        <div style={{
                                            display: 'flex', alignItems: 'center', gap: 5,
                                            fontSize: '0.78rem',
                                            color: pdpTocScrolledToEnd ? '#198754' : '#adb5bd',
                                            fontWeight: pdpTocScrolledToEnd ? 600 : 400,
                                            transition: 'color 0.35s'
                                        }}>
                                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none" style={{ flexShrink: 0 }}>
                                                <circle cx="8" cy="8" r="7.5" stroke={pdpTocScrolledToEnd ? '#198754' : '#dee2e6'}
                                                    strokeWidth="1.5"
                                                    style={{ transition: 'stroke 0.35s' }} />
                                                <path d="M4.5 8.3l2.2 2.2 4.5-4.5" stroke={pdpTocScrolledToEnd ? '#198754' : '#dee2e6'}
                                                    strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round"
                                                    style={{
                                                        transition: 'stroke 0.35s, stroke-dashoffset 0.4s',
                                                        strokeDasharray: 9,
                                                        strokeDashoffset: pdpTocScrolledToEnd ? 0 : 9
                                                    }} />
                                            </svg>
                                            Telah membaca Kebijakan Privasi &amp; Syarat
                                        </div>
                                    </div>
                                    <div className="d-flex justify-content-end gap-2 w-100">
                                        <button type="button" className="btn btn-outline-secondary" onClick={() => setShowPdpModal(false)}>Tutup</button>
                                        <button
                                            type="button"
                                            className="btn btn-success"
                                            disabled={!canAgree}
                                            onClick={handleAgreeAndClose}
                                        >
                                            Setuju dan Lanjutkan
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
    const hasActiveTrialPackages = useMemo(() => packages.some((item) => isTrialPackage(item)), [packages]);
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
            const invoice = data?.data?.invoice || null;
            const subscriptionStatus = String(data?.data?.subscription?.status || '').trim();
            const isPendingPayment = subscriptionStatus === 'pending_payment';

            const loginBase = String(bootstrap.loginUrl || '/login');
            const separator = loginBase.includes('?') ? '&' : '?';
            const pendingPaymentUrl = `${loginBase}${separator}mode=company&next=%2Fsubscription&companyCode=${encodeURIComponent(String(companyCode || ''))}`;

            setOnboardingOpen(false);

            if (isPendingPayment) {
                const pendingMessage = 'Registrasi selesai. Lanjutkan ke login untuk menyelesaikan pembayaran.';
                const pendingMessageHtml = buildOnboardingSuccessMessageHtml({
                    companyCode,
                    ownerEmail,
                    invoice,
                    isPendingPayment: true,
                });

                try {
                    if (window.ArcavUi && typeof window.ArcavUi.selectOption === 'function') {
                        await window.ArcavUi.selectOption({
                            title: 'Onboarding berhasil',
                            message: pendingMessage,
                            messageHtml: pendingMessageHtml,
                            optionLayout: 'buttons',
                            hideCancel: true,
                            options: [{ value: 'proceed', label: 'Login untuk lanjut bayar' }],
                        });
                    } else {
                        window.alert(pendingMessage);
                    }
                } catch (_uiError) {
                    // Continue redirect even if helper modal fails.
                }

                window.location.href = pendingPaymentUrl;
                return;
            }

            const message = 'Registrasi selesai. Login untuk masuk ke aplikasi.';
            const messageHtml = buildOnboardingSuccessMessageHtml({
                companyCode,
                ownerEmail,
                invoice,
                isPendingPayment: false,
            });

            try {
                if (window.ArcavUi && typeof window.ArcavUi.selectOption === 'function') {
                    await window.ArcavUi.selectOption({
                        title: 'Onboarding berhasil',
                        message,
                        messageHtml,
                        optionLayout: 'buttons',
                        hideCancel: true,
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
                                    {hasActiveTrialPackages ? 'Mulai Trial' : 'Mulai Sekarang'} <ArrowRight size={18} weight="bold" />
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
                                {hasActiveTrialPackages ? 'Mulai Trial' : 'Mulai Sekarang'} <ArrowRight size={18} weight="bold" />
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
                            Platform HCM lengkap untuk absensi, cuti, payroll, dan laporan. Dirancang agar tim HR, pimpinan, dan finance bisa bekerja lebih cepat dalam satu alur.
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
                        <p>Pantau aktivitas tim, kelola absensi, proses cuti, hingga payroll dalam satu dashboard yang mudah dipahami.</p>
                    </FadeInSection>
                    <DashboardMockup onOpenOnboarding={openOnboarding} packageUuid={primaryPackageUuid} />
                </div>
            </section>

            <section className="mpl-section" id="features">
                <div className="mpl-container">
                    <FadeInSection className="mpl-section-head">
                        <span className="mpl-badge">Features</span>
                        <h2>Semua yang Tim Anda Butuhkan</h2>
                        <p>Fitur inti HR tersedia dalam satu tempat agar operasional harian lebih cepat, konsisten, dan minim kesalahan.</p>
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
                        <p>Alur pendaftaran singkat, jelas, dan mudah diikuti sampai akun siap digunakan.</p>
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
                        <p>Pilih paket sesuai ukuran dan kebutuhan tim Anda, lalu mulai onboarding tanpa proses berbelit.</p>
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
                            <p className="mb-3">Belum ada paket aktif. Aktifkan paket terlebih dulu agar pilihan harga tampil di halaman ini.</p>
                            <a className="mpl-btn" href={bootstrap.trialUrl}>Buka halaman onboarding</a>
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
                            <p>Bergabung dengan perusahaan yang ingin operasional HR lebih rapi, payroll lebih tenang, dan onboarding lebih cepat.</p>
                            <div className="mpl-cta-actions">
                                <button type="button" className="mpl-btn" onClick={() => openOnboarding(primaryPackageUuid)}>
                                    {hasActiveTrialPackages ? 'Mulai Trial' : 'Mulai Sekarang'} <ArrowRight size={18} weight="bold" />
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
                            <p className="mpl-footer-copy">Platform HCM untuk perusahaan Indonesia yang ingin proses HR lebih rapi, cepat, dan mudah dijalankan dari satu tempat.</p>
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
                                <li><a href={bootstrap.trialUrl}>{hasActiveTrialPackages ? 'Mulai Trial' : 'Mulai Sekarang'}</a></li>
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
                    requestedStartMode={autoOpenSpec?.requestedStartMode || null}
                    turnstileEnabled={Boolean(bootstrap?.turnstileEnabled && bootstrap?.turnstileSiteKey)}
                    turnstileHideTestNotice={Boolean(bootstrap?.turnstileHideTestNotice)}
                    turnstileSiteKey={String(bootstrap?.turnstileSiteKey || '').trim()}
                />
            ) : null}
        </div>
    );
}