import React, { useState, useEffect, useRef, useMemo, useCallback } from 'react';
import { X, Check, AlertCircle } from 'lucide-react';
import { formatIdr, isTrialPackage, sanitizePostalCode, sanitizePhone, buildPayload } from './onboarding-api';

interface OnboardingModalProps {
  packages: any[];
  onClose: () => void;
  loginUrl?: string;
  turnstileEnabled?: boolean;
  turnstileSiteKey?: string;
  turnstileHideTestNotice?: boolean;
  requestedPackageUuid?: string;
  requestedStartMode?: string;
  hasActiveTrialPackages?: boolean;
}

interface FormState {
  packageUuid: string;
  billingCycle: string;
  startMode: string;
  companyName: string;
  companyLegalName: string;
  companyTimezone: string;
  companyCurrency: string;
  companyCountryCode: string;
  companyContactPhone: string;
  companyContactPersonName: string;
  companyContactPersonRole: string;
  companyAddress: string;
  companyCity: string;
  companyPostalCode: string;
  ownerName: string;
  ownerEmail: string;
  ownerPhone: string;
  ownerPassword: string;
  ownerConfirmPassword: string;
  billingEmail: string;
  website: string;
  consentAccepted: boolean;
  _confirmMismatch: boolean;
}

function getInitialFormState(packages: any[], requestedUuid = '', requestedMode = '', hasTrial = false): FormState {
  // If a specific package was requested, find it; otherwise use first package
  const preselectedPackage = requestedUuid
    ? packages.find((p: any) => p.uuid === requestedUuid) || null
    : null;
  const defaultPackage = preselectedPackage || packages[0] || null;

  // startMode: requested > trial (if available) > pending_payment
  const startMode = requestedMode || (hasTrial ? 'trial' : 'pending_payment');

  return {
    packageUuid: defaultPackage?.uuid || (packages.length > 0 ? packages[0]?.uuid || '' : ''),
    billingCycle: 'monthly',
    startMode,
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
    consentAccepted: false,
    _confirmMismatch: false,
  };
}

export default function OnboardingModal({
  packages,
  onClose,
  loginUrl = '/login',
  turnstileEnabled = false,
  turnstileSiteKey = '',
  turnstileHideTestNotice = false,
  requestedPackageUuid = '',
  requestedStartMode = '',
  hasActiveTrialPackages = false,
}: OnboardingModalProps) {
  const [formState, setFormState] = useState<FormState>(() => getInitialFormState(packages, requestedPackageUuid, requestedStartMode, hasActiveTrialPackages));
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<{ message: string; details: any[] } | null>(null);
  const [turnstileToken, setTurnstileToken] = useState('');
  const turnstileContainerRef = useRef<HTMLDivElement>(null);
  const turnstileWidgetIdRef = useRef<string | null>(null);
  const [showPdpModal, setShowPdpModal] = useState(false);
  const [pdpTocScrolledToEnd, setPdpTocScrolledToEnd] = useState(false);
  const [onboardingSuccess, setOnboardingSuccess] = useState(false);
  const [successData, setSuccessData] = useState<{
    companyCode: string;
    ownerEmail: string;
    invoice: any;
    isPendingPayment: boolean;
  } | null>(null);

  const selectedPackage = packages.find((pkg: any) => pkg.uuid === formState.packageUuid) || null;
  const isTrialSelected = isTrialPackage(selectedPackage);
  const lockBillingCycle = isTrialSelected;
  const isPendingPaymentRegistration = false; // Will be set if needed

  const handlePdpTocBodyScroll = (e: React.UIEvent<HTMLDivElement>) => {
    const el = e.currentTarget;
    if (!pdpTocScrolledToEnd && el.scrollTop + el.clientHeight >= el.scrollHeight - 60) {
      setPdpTocScrolledToEnd(true);
    }
  };

  const handleAgreeAndClose = () => {
    setFormState((prev) => ({ ...prev, consentAccepted: true }));
    setShowPdpModal(false);
  };

  // Turnstile integration
  useEffect(() => {
    if (!turnstileEnabled || !turnstileSiteKey || !turnstileContainerRef.current) {
      return;
    }

    let cancelled = false;
    let attempts = 0;
    let visualCheckTimer: ReturnType<typeof setTimeout> | null = null;

    const mountWidget = () => {
      if (cancelled) return;
      if (!(window as any).turnstile || typeof (window as any).turnstile.render !== 'function') {
        if (attempts >= 40) return;
        attempts += 1;
        setTimeout(mountWidget, 250);
        return;
      }

      if (turnstileContainerRef.current) {
        turnstileContainerRef.current.innerHTML = '';
      }

      turnstileWidgetIdRef.current = (window as any).turnstile.render(turnstileContainerRef.current, {
        sitekey: turnstileSiteKey,
        callback: (token: string) => {
          setTurnstileToken(token || '');
        },
        'expired-callback': () => {
          setTurnstileToken('');
        },
        'error-callback': () => {
          setTurnstileToken('');
          return true;
        },
      });
    };

    mountWidget();

    return () => {
      cancelled = true;
      if (visualCheckTimer) clearTimeout(visualCheckTimer);
      if (turnstileWidgetIdRef.current != null && (window as any).turnstile && typeof (window as any).turnstile.remove === 'function') {
        try {
          (window as any).turnstile.remove(turnstileWidgetIdRef.current);
        } catch (_) {
          if (turnstileContainerRef.current) turnstileContainerRef.current.innerHTML = '';
        }
      }
      turnstileWidgetIdRef.current = null;
      if (turnstileContainerRef.current) turnstileContainerRef.current.innerHTML = '';
    };
  }, [turnstileEnabled, turnstileSiteKey]);

  const handleChange = (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement>) => {
    const { name, value, type } = e.target;
    const checked = (e.target as HTMLInputElement).checked;

    let sanitizedValue = value;

    // Sanitize phone fields
    if (['companyContactPhone', 'ownerPhone'].includes(name)) {
      sanitizedValue = sanitizePhone(value);
    }

    // Sanitize postal code
    if (name === 'companyPostalCode') {
      sanitizedValue = sanitizePostalCode(value);
    }

    setFormState((prev) => {
      const updated = {
        ...prev,
        [name]: type === 'checkbox' ? checked : sanitizedValue,
      };

      // Check password mismatch
      if (name === 'ownerPassword' || name === 'ownerConfirmPassword') {
        const pw = name === 'ownerPassword' ? sanitizedValue : prev.ownerPassword;
        const cpw = name === 'ownerConfirmPassword' ? sanitizedValue : prev.ownerConfirmPassword;
        updated._confirmMismatch = cpw.length > 0 && pw !== cpw;
      }

      // Auto-fill billing email from owner email
      if (name === 'ownerEmail' && !prev.billingEmail) {
        updated.billingEmail = sanitizedValue;
      }

      return updated;
    });
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setSubmitting(true);
    setError(null);

    try {
      const payload = buildPayload(formState, turnstileToken);
      const response = await fetch('/v1/public/onboarding', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify(payload),
      });

      const data = await response.json();
      if (!response.ok || !data?.success) {
        throw { response: { status: response.status, data } };
      }

      const companyCode = data?.data?.company?.code || '';
      const ownerEmail = data?.data?.owner?.email || '';
      const invoice = data?.data?.invoice || null;
      const isPendingPayment = data?.data?.subscription?.status === 'pending_payment';

      setOnboardingSuccess(true);
      setSuccessData({ companyCode, ownerEmail, invoice, isPendingPayment });
    } catch (err: any) {
      const errorData = err?.response?.data;
      const message = errorData?.error?.message || 'Terjadi kesalahan. Coba lagi.';
      const details = errorData?.error?.details || [];
      setError({ message, details });
    } finally {
      setSubmitting(false);
    }
  };

  const handleSuccessAction = () => {
    if (!successData) return;
    const { companyCode, isPendingPayment } = successData;
    if (isPendingPayment) {
      window.location.href = `${loginUrl}?mode=company&next=%2Fsubscription&companyCode=${encodeURIComponent(companyCode)}`;
    } else {
      window.location.href = loginUrl;
    }
  };

  const fieldErrors = useMemo(() => {
    const map: Record<string, string> = {};
    (error?.details || []).forEach((detail: any) => {
      if (!detail.field) return;
      const key = String(detail.field || '')
        .split('.')
        .map((seg, si) =>
          seg
            .split('_')
            .map((part, pi) => (si === 0 && pi === 0 ? part : part.charAt(0).toUpperCase() + part.slice(1)))
            .join('')
        )
        .join('');
      if (!map[key]) map[key] = detail.message || 'Nilai tidak valid';
    });
    return map;
  }, [error?.details]);

  const fe = (name: string) => fieldErrors[name] || null;

  return (
    <>
      {onboardingSuccess && successData ? (
        <div className="fixed inset-0 z-[100] flex items-center justify-center">
          <div className="absolute inset-0 bg-black/60 backdrop-blur-sm" onClick={onClose} />
          <div className="relative z-10 w-full max-w-md mx-4 bg-white border border-gray-200 shadow-xl">
            <div className="px-6 py-8 text-center">
              <div className="w-16 h-16 mx-auto mb-4 bg-emerald-100 rounded-full flex items-center justify-center">
                <Check className="w-8 h-8 text-emerald-600" />
              </div>
              <h2 className="text-lg font-display font-extrabold text-gray-900 mb-2">
                {successData.isPendingPayment ? 'Registrasi Berhasil' : 'Akun Siap Digunakan'}
              </h2>
              <p className="text-sm text-gray-500 mb-6">
                {successData.isPendingPayment
                  ? 'Registrasi selesai. Langkah berikutnya adalah menyelesaikan pembayaran agar akun langsung aktif.'
                  : 'Registrasi selesai. Akun company Anda siap digunakan.'}
              </p>
              <div className="bg-gray-50 border border-gray-100 p-4 mb-6 text-left space-y-2">
                <div className="flex justify-between text-sm">
                  <span className="text-gray-500">Company code</span>
                  <span className="font-bold text-gray-900">{successData.companyCode}</span>
                </div>
                <div className="flex justify-between text-sm">
                  <span className="text-gray-500">Email login</span>
                  <span className="font-bold text-gray-900">{successData.ownerEmail}</span>
                </div>
                {successData.invoice && (
                  <>
                    <hr className="border-gray-200" />
                    <div className="flex justify-between text-sm">
                      <span className="text-gray-500">Total tagihan</span>
                      <span className="font-bold text-[#FF6600]">{formatIdr(successData.invoice.amountDue || 0)}</span>
                    </div>
                    <div className="flex justify-between text-sm">
                      <span className="text-gray-500">Jatuh tempo</span>
                      <span className="font-bold text-gray-900">{successData.invoice.dueDate || '-'}</span>
                    </div>
                  </>
                )}
              </div>
              <button
                onClick={handleSuccessAction}
                className="w-full py-3 bg-[#FF6600] hover:bg-orange-600 text-white font-bold text-xs uppercase tracking-widest transition-colors cursor-pointer"
              >
                {successData.isPendingPayment ? 'Login untuk lanjut bayar' : 'Login sekarang'}
              </button>
            </div>
          </div>
        </div>
      ) : (<>
      <div className="fixed inset-0 z-[100] flex items-center justify-center">
        <div className="absolute inset-0 bg-black/60 backdrop-blur-sm" onClick={onClose} />
        <div className="relative z-10 w-full max-w-5xl mx-4 my-4 bg-white border border-gray-200 shadow-xl">
          {/* Header */}
          <div className="sticky top-0 z-20 bg-white border-b border-gray-100 px-6 py-4 flex items-center justify-between">
            <div>
              <h2 className="text-lg font-display font-extrabold text-gray-900">Aktifkan workspace perusahaan</h2>
              <p className="text-xs text-gray-500 mt-0.5">Lengkapi data perusahaan dan owner untuk mulai menggunakan platform.</p>
            </div>
            <button onClick={onClose} className="p-1 text-gray-400 hover:text-gray-600 transition-colors cursor-pointer">
              <X className="w-5 h-5" />
            </button>
          </div>

          {/* Error alert */}
          {error && (
            <div className="mx-6 mt-4 px-4 py-3 bg-red-50 border border-red-200 text-red-700 text-xs font-semibold">
              {error.details.length
                ? 'Beberapa field tidak valid — periksa field yang ditandai merah di bawah ini.'
                : error.message}
            </div>
          )}

          <form onSubmit={handleSubmit} className="px-6 py-4">
            {/* Package Selection Section */}
            <div className="mb-4 p-3 bg-gray-50 border border-gray-100">
              <h3 className="text-sm font-display font-extrabold text-gray-900 mb-3">Paket & Penagihan</h3>
              <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                  <label className="block text-xs font-semibold text-gray-600 mb-1">Paket</label>
                  <select
                    name="packageUuid"
                    value={formState.packageUuid}
                    onChange={handleChange}
                    required
                    className="w-full border border-gray-200 bg-white px-3 py-2 text-sm text-gray-900 focus:outline-none focus:border-[#FF6600]"
                  >
                    {packages.map((pkg: any) => (
                      <option key={pkg.uuid} value={pkg.uuid}>
                        {pkg.name} ({pkg.code})
                      </option>
                    ))}
                  </select>
                </div>
                <div>
                  <label className="block text-xs font-semibold text-gray-600 mb-1">Billing cycle</label>
                  <select
                    name="billingCycle"
                    value={formState.billingCycle}
                    onChange={handleChange}
                    disabled={lockBillingCycle}
                    className="w-full border border-gray-200 bg-white px-3 py-2 text-sm text-gray-900 focus:outline-none focus:border-[#FF6600] disabled:bg-gray-100 disabled:text-gray-400"
                  >
                    <option value="monthly">Monthly</option>
                    <option value="yearly">Yearly</option>
                  </select>
                </div>
                <div>
                  <label className="block text-xs font-semibold text-gray-600 mb-1">Mode aktivasi</label>
                  <select
                    name="startMode"
                    value={formState.startMode}
                    onChange={handleChange}
                    disabled
                    className="w-full border border-gray-200 bg-gray-100 px-3 py-2 text-sm text-gray-400 focus:outline-none cursor-not-allowed"
                  >
                    {isTrialSelected ? (
                      <option value="trial">Trial</option>
                    ) : (
                      <option value="pending_payment">Aktivasi subscription</option>
                    )}
                  </select>
                </div>
              </div>
              <p className="mt-2 text-[10px] text-gray-400 font-mono">
                {isTrialSelected
                  ? 'Paket trial otomatis memakai billing bulanan dan aktivasi trial.'
                  : 'Paket berbayar akan lanjut ke aktivasi subscription setelah onboarding selesai.'}
              </p>
            </div>

            {/* Company + Owner Columns */}
            <div className="grid grid-cols-1 lg:grid-cols-7 gap-4 mb-4">
              {/* Company Data */}
              <div className="lg:col-span-4 p-3 bg-white border border-gray-100">
                <h3 className="text-sm font-display font-extrabold text-gray-900 mb-2">Data company</h3>
                <div className="grid grid-cols-1 md:grid-cols-2 gap-2">
                  <div>
                    <label className="block text-xs font-semibold text-gray-600 mb-1">Nama company</label>
                    <input
                      name="companyName"
                      value={formState.companyName}
                      onChange={handleChange}
                      maxLength={255}
                      required
                      className={`w-full border px-3 py-2 text-sm text-gray-900 focus:outline-none focus:border-[#FF6600] ${fe('companyName') ? 'border-red-400 bg-red-50' : 'border-gray-200'}`}
                    />
                    {fe('companyName') && <p className="text-[10px] text-red-500 mt-0.5">{fe('companyName')}</p>}
                  </div>
                  <div>
                    <label className="block text-xs font-semibold text-gray-600 mb-1">Nama legal</label>
                    <input
                      name="companyLegalName"
                      value={formState.companyLegalName}
                      onChange={handleChange}
                      maxLength={255}
                      className={`w-full border px-3 py-2 text-sm text-gray-900 focus:outline-none focus:border-[#FF6600] ${fe('companyLegalName') ? 'border-red-400 bg-red-50' : 'border-gray-200'}`}
                    />
                    {fe('companyLegalName') && <p className="text-[10px] text-red-500 mt-0.5">{fe('companyLegalName')}</p>}
                  </div>
                  <div>
                    <label className="block text-xs font-semibold text-gray-600 mb-1">Contact person</label>
                    <input
                      name="companyContactPersonName"
                      value={formState.companyContactPersonName}
                      onChange={handleChange}
                      maxLength={120}
                      placeholder="Cth: Budi Santoso"
                      className={`w-full border px-3 py-2 text-sm text-gray-900 focus:outline-none focus:border-[#FF6600] ${fe('companyContactPersonName') ? 'border-red-400 bg-red-50' : 'border-gray-200'}`}
                    />
                    {fe('companyContactPersonName') ? (
                      <p className="text-[10px] text-red-500 mt-0.5">{fe('companyContactPersonName')}</p>
                    ) : (
                      <p className="text-[9px] text-gray-400 mt-0.5">{formState.companyContactPersonName.length}/120</p>
                    )}
                  </div>
                  <div>
                    <label className="block text-xs font-semibold text-gray-600 mb-1">Peran contact person</label>
                    <input
                      name="companyContactPersonRole"
                      value={formState.companyContactPersonRole}
                      onChange={handleChange}
                      maxLength={120}
                      placeholder="Cth: HR Manager"
                      className={`w-full border px-3 py-2 text-sm text-gray-900 focus:outline-none focus:border-[#FF6600] ${fe('companyContactPersonRole') ? 'border-red-400 bg-red-50' : 'border-gray-200'}`}
                    />
                    {fe('companyContactPersonRole') ? (
                      <p className="text-[10px] text-red-500 mt-0.5">{fe('companyContactPersonRole')}</p>
                    ) : (
                      <p className="text-[9px] text-gray-400 mt-0.5">{formState.companyContactPersonRole.length}/120</p>
                    )}
                  </div>
                  <div>
                    <label className="block text-xs font-semibold text-gray-600 mb-1">Nomor kontak company</label>
                    <input
                      name="companyContactPhone"
                      value={formState.companyContactPhone}
                      onChange={handleChange}
                      type="tel"
                      inputMode="tel"
                      placeholder="Contoh: +62811234567"
                      maxLength={20}
                      className={`w-full border px-3 py-2 text-sm text-gray-900 focus:outline-none focus:border-[#FF6600] ${fe('companyContactPhone') ? 'border-red-400 bg-red-50' : 'border-gray-200'}`}
                    />
                    {fe('companyContactPhone') && <p className="text-[10px] text-red-500 mt-0.5">{fe('companyContactPhone')}</p>}
                  </div>
                  <div>
                    <label className="block text-xs font-semibold text-gray-600 mb-1">Kota</label>
                    <input
                      name="companyCity"
                      value={formState.companyCity}
                      onChange={handleChange}
                      maxLength={120}
                      required
                      className={`w-full border px-3 py-2 text-sm text-gray-900 focus:outline-none focus:border-[#FF6600] ${fe('companyCity') ? 'border-red-400 bg-red-50' : 'border-gray-200'}`}
                    />
                    {fe('companyCity') && <p className="text-[10px] text-red-500 mt-0.5">{fe('companyCity')}</p>}
                  </div>
                  <div className="md:col-span-2">
                    <label className="block text-xs font-semibold text-gray-600 mb-1">Alamat</label>
                    <textarea
                      name="companyAddress"
                      rows={3}
                      value={formState.companyAddress}
                      onChange={handleChange}
                      maxLength={500}
                      required
                      className={`w-full border px-3 py-2 text-sm text-gray-900 focus:outline-none focus:border-[#FF6600] ${fe('companyAddress') ? 'border-red-400 bg-red-50' : 'border-gray-200'}`}
                    />
                    {fe('companyAddress') && <p className="text-[10px] text-red-500 mt-0.5">{fe('companyAddress')}</p>}
                  </div>
                  <div>
                    <label className="block text-xs font-semibold text-gray-600 mb-1">Kode pos</label>
                    <input
                      name="companyPostalCode"
                      value={formState.companyPostalCode}
                      onChange={handleChange}
                      inputMode="numeric"
                      maxLength={12}
                      className={`w-full border px-3 py-2 text-sm text-gray-900 focus:outline-none focus:border-[#FF6600] ${fe('companyPostalCode') ? 'border-red-400 bg-red-50' : 'border-gray-200'}`}
                    />
                    {fe('companyPostalCode') && <p className="text-[10px] text-red-500 mt-0.5">{fe('companyPostalCode')}</p>}
                  </div>
                  <div>
                    <label className="block text-xs font-semibold text-gray-600 mb-1">Country code</label>
                    <input
                      value={formState.companyCountryCode}
                      readOnly
                      disabled
                      className="w-full border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-400"
                    />
                  </div>
                  <div>
                    <label className="block text-xs font-semibold text-gray-600 mb-1">Timezone</label>
                    <input
                      value={formState.companyTimezone}
                      readOnly
                      disabled
                      className="w-full border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-400"
                    />
                  </div>
                  <div>
                    <label className="block text-xs font-semibold text-gray-600 mb-1">Currency</label>
                    <input
                      value={formState.companyCurrency}
                      readOnly
                      disabled
                      className="w-full border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-400"
                    />
                  </div>
                </div>
              </div>

              {/* Owner Data */}
              <div className="lg:col-span-3 p-3 bg-white border border-gray-100">
                <h3 className="text-sm font-display font-extrabold text-gray-900 mb-2">Data owner</h3>
                <div className="space-y-2">
                  <div>
                    <label className="block text-xs font-semibold text-gray-600 mb-1">Nama owner</label>
                    <input
                      name="ownerName"
                      value={formState.ownerName}
                      onChange={handleChange}
                      minLength={2}
                      maxLength={150}
                      placeholder="Cth: Budi Santoso"
                      required
                      className={`w-full border px-3 py-2 text-sm text-gray-900 focus:outline-none focus:border-[#FF6600] ${fe('ownerName') ? 'border-red-400 bg-red-50' : 'border-gray-200'}`}
                    />
                    {fe('ownerName') ? (
                      <p className="text-[10px] text-red-500 mt-0.5">{fe('ownerName')}</p>
                    ) : (
                      <p className="text-[9px] text-gray-400 mt-0.5">Nama lengkap sesuai identitas.</p>
                    )}
                  </div>
                  <div>
                    <label className="block text-xs font-semibold text-gray-600 mb-1">Email owner</label>
                    <input
                      name="ownerEmail"
                      type="email"
                      value={formState.ownerEmail}
                      onChange={handleChange}
                      maxLength={255}
                      required
                      className={`w-full border px-3 py-2 text-sm text-gray-900 focus:outline-none focus:border-[#FF6600] ${fe('ownerEmail') ? 'border-red-400 bg-red-50' : 'border-gray-200'}`}
                    />
                    {fe('ownerEmail') && <p className="text-[10px] text-red-500 mt-0.5">{fe('ownerEmail')}</p>}
                  </div>
                  <div>
                    <label className="block text-xs font-semibold text-gray-600 mb-1">Nomor owner</label>
                    <input
                      name="ownerPhone"
                      value={formState.ownerPhone}
                      onChange={handleChange}
                      type="tel"
                      inputMode="tel"
                      placeholder="Contoh: +62811234567"
                      maxLength={20}
                      className={`w-full border px-3 py-2 text-sm text-gray-900 focus:outline-none focus:border-[#FF6600] ${fe('ownerPhone') ? 'border-red-400 bg-red-50' : 'border-gray-200'}`}
                    />
                    {fe('ownerPhone') && <p className="text-[10px] text-red-500 mt-0.5">{fe('ownerPhone')}</p>}
                  </div>
                  <div>
                    <label className="block text-xs font-semibold text-gray-600 mb-1">Password</label>
                    <input
                      name="ownerPassword"
                      type="password"
                      value={formState.ownerPassword}
                      onChange={handleChange}
                      minLength={8}
                      maxLength={64}
                      required
                      className={`w-full border px-3 py-2 text-sm text-gray-900 focus:outline-none focus:border-[#FF6600] ${fe('ownerPassword') ? 'border-red-400 bg-red-50' : 'border-gray-200'}`}
                    />
                    {fe('ownerPassword') ? (
                      <p className="text-[10px] text-red-500 mt-0.5">{fe('ownerPassword')}</p>
                    ) : (
                      <p className="text-[9px] text-gray-400 mt-0.5">Min. 8 karakter, huruf besar, huruf kecil, dan angka.</p>
                    )}
                  </div>
                  <div>
                    <label className="block text-xs font-semibold text-gray-600 mb-1">Konfirmasi password</label>
                    <input
                      name="ownerConfirmPassword"
                      type="password"
                      value={formState.ownerConfirmPassword}
                      onChange={handleChange}
                      minLength={8}
                      maxLength={64}
                      required
                      className={`w-full border px-3 py-2 text-sm text-gray-900 focus:outline-none focus:border-[#FF6600] ${fe('ownerConfirmPassword') || formState._confirmMismatch ? 'border-red-400 bg-red-50' : 'border-gray-200'}`}
                    />
                    {fe('ownerConfirmPassword') ? (
                      <p className="text-[10px] text-red-500 mt-0.5">{fe('ownerConfirmPassword')}</p>
                    ) : formState._confirmMismatch ? (
                      <p className="text-[10px] text-red-500 mt-0.5">Password tidak cocok.</p>
                    ) : null}
                  </div>
                  <div>
                    <label className="block text-xs font-semibold text-gray-600 mb-1">Billing email</label>
                    <input
                      name="billingEmail"
                      type="email"
                      value={formState.billingEmail}
                      onChange={handleChange}
                      maxLength={255}
                      className={`w-full border px-3 py-2 text-sm text-gray-900 focus:outline-none focus:border-[#FF6600] ${fe('billingEmail') ? 'border-red-400 bg-red-50' : 'border-gray-200'}`}
                    />
                    {fe('billingEmail') && <p className="text-[10px] text-red-500 mt-0.5">{fe('billingEmail')}</p>}
                  </div>

                  {/* Honeypot */}
                  <div className="hidden">
                    <input name="website" value={formState.website} onChange={handleChange} tabIndex={-1} autoComplete="off" />
                  </div>

                  {/* Turnstile */}
                  {turnstileEnabled && turnstileSiteKey && (
                    <div>
                      <label className="block text-xs font-semibold text-gray-600 mb-1">Verifikasi keamanan</label>
                      <div ref={turnstileContainerRef} className={turnstileHideTestNotice ? 'opacity-90' : ''} />
                      <p className="text-[9px] text-gray-400 mt-0.5">Selesaikan captcha sebelum submit onboarding.</p>
                    </div>
                  )}
                </div>
              </div>
            </div>

            {/* Consent + Submit */}
            <div className="border-t border-gray-100 pt-4 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
               <div className="flex items-start gap-2">
                <input
                  type="checkbox"
                  id="consentAccepted"
                  name="consentAccepted"
                  checked={formState.consentAccepted}
                  onChange={handleChange}
                  className="mt-0.5 accent-[#FF6600]"
                />
                <label htmlFor="consentAccepted" className="text-xs text-gray-500">
                  Saya menyetujui{' '}
                  <a href="/privacy-policy" target="_blank" rel="noopener noreferrer" className="text-[#FF6600] font-semibold hover:underline">
                    Kebijakan Privasi
                  </a>{' '}
                  dan{' '}
                  <a href="/terms-condition" target="_blank" rel="noopener noreferrer" className="text-[#FF6600] font-semibold hover:underline">
                    Syarat &amp; Ketentuan
                  </a>{' '}
                  ARCAV HCM.
                </label>
              </div>
              <div className="flex items-center gap-2 w-full sm:w-auto">
                <button
                  type="button"
                  onClick={onClose}
                  className="px-4 py-2 text-xs font-semibold text-gray-500 border border-gray-200 hover:bg-gray-50 transition-colors cursor-pointer"
                >
                  Tutup
                </button>
                <button
                  type="submit"
                  disabled={submitting || !formState.consentAccepted || formState._confirmMismatch}
                  className="px-5 py-2 text-xs font-bold text-white bg-[#FF6600] hover:bg-orange-600 disabled:bg-gray-300 disabled:cursor-not-allowed transition-colors uppercase tracking-wider cursor-pointer"
                >
                  {submitting ? 'Memproses...' : 'Lanjutkan pendaftaran'}
                </button>
              </div>
            </div>
          </form>
        </div>
      </div>

      {/* PDP/TOC Modal */}
      {showPdpModal && (
        <>
          <div
            className="fixed inset-0 z-[110] bg-black/60 backdrop-blur-sm"
            onClick={() => setShowPdpModal(false)}
          />
          <div className="fixed inset-0 z-[120] flex items-center justify-center p-4">
            <div className="bg-white border border-gray-200 shadow-xl w-full max-w-2xl max-h-[80vh] flex flex-col">
              <div className="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                <div>
                  <h5 className="text-sm font-display font-extrabold text-gray-900">Kebijakan Privasi dan Syarat &amp; Ketentuan</h5>
                  <p className="text-[10px] text-gray-400 mt-0.5">
                    {pdpTocScrolledToEnd ? (
                      <span className="text-emerald-600 font-semibold">✓ Telah membaca seluruh dokumen</span>
                    ) : (
                      'Scroll ke bawah untuk membaca seluruh dokumen'
                    )}
                  </p>
                </div>
                <button onClick={() => setShowPdpModal(false)} className="p-1 text-gray-400 hover:text-gray-600 cursor-pointer">
                  <X className="w-4 h-4" />
                </button>
              </div>
              <div
                className="flex-1 overflow-y-auto px-6 py-4 text-xs text-gray-600 space-y-3 leading-relaxed"
                onScroll={handlePdpTocBodyScroll}
              >
                <p className="text-gray-400">Berlaku efektif: 1 Mei 2026 — Terakhir diperbarui: 1 Mei 2026</p>
                <p>Ringkasan singkat: Kami mengumpulkan data yang diperlukan untuk menjalankan layanan HR, memenuhi kewajiban hukum, dan menjaga keamanan platform. Kami hanya memproses data yang relevan dan meminimalkan akses pihak ketiga.</p>

                <h6 className="font-extrabold text-gray-800 text-xs mt-4">1. Data yang kami kumpulkan</h6>
                <ul className="list-disc pl-4 space-y-1">
                  <li>Informasi kontak dasar: nama, email, nomor telepon</li>
                  <li>Data perusahaan dan pekerjaan: jabatan, departemen, tanggal mulai</li>
                  <li>Data administratif: NIK/KTP, NPWP, rekening bank (untuk payroll)</li>
                  <li>Log akses dan keamanan: IP, user-agent, waktu akses</li>
                  <li>Data opsional yang memerlukan persetujuan: foto selfie biometrik, lokasi GPS</li>
                </ul>

                <h6 className="font-extrabold text-gray-800 text-xs mt-4">2. Untuk apa data digunakan</h6>
                <ul className="list-disc pl-4 space-y-1">
                  <li>Menjalankan layanan HR dan manajemen akun</li>
                  <li>Memproses penggajian dan memenuhi kewajiban pajak</li>
                  <li>Mengelola BPJS dan kewajiban hukum lainnya</li>
                  <li>Meningkatkan keamanan dan mencegah penyalahgunaan</li>
                  <li>Menyediakan fitur tambahan seperti asisten AI (tanpa PII) jika diaktifkan</li>
                </ul>

                <h6 className="font-extrabold text-gray-800 text-xs mt-4">3. Pihak ketiga yang kami gunakan</h6>
                <table className="w-full text-[10px] border-collapse">
                  <thead>
                    <tr className="bg-gray-50">
                      <th className="border border-gray-100 px-2 py-1 text-left font-semibold">Penyedia</th>
                      <th className="border border-gray-100 px-2 py-1 text-left font-semibold">Data (kategori)</th>
                      <th className="border border-gray-100 px-2 py-1 text-left font-semibold">Tujuan singkat</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr>
                      <td className="border border-gray-100 px-2 py-1">Midtrans</td>
                      <td className="border border-gray-100 px-2 py-1">Nama, email, data tagihan</td>
                      <td className="border border-gray-100 px-2 py-1">Pemrosesan pembayaran</td>
                    </tr>
                    <tr>
                      <td className="border border-gray-100 px-2 py-1">Penyedia AI</td>
                      <td className="border border-gray-100 px-2 py-1">Teks intent (non-PII)</td>
                      <td className="border border-gray-100 px-2 py-1">Fitur asisten AI (opsional)</td>
                    </tr>
                    <tr>
                      <td className="border border-gray-100 px-2 py-1">Cloudflare Turnstile</td>
                      <td className="border border-gray-100 px-2 py-1">Token captcha</td>
                      <td className="border border-gray-100 px-2 py-1">Pencegahan bot &amp; keamanan</td>
                    </tr>
                  </tbody>
                </table>

                <h6 className="font-extrabold text-gray-800 text-xs mt-4">4. Lama penyimpanan</h6>
                <ul className="list-disc pl-4 space-y-1">
                  <li>Data karyawan aktif: selama hubungan kerja</li>
                  <li>Data mantan karyawan: hingga 5 tahun sesuai kebutuhan administrasi</li>
                  <li>Data payroll &amp; pajak: sesuai kewajiban hukum hingga 10 tahun</li>
                  <li>Log keamanan: disimpan sementara (biasanya &lt; 1 tahun)</li>
                </ul>

                <h6 className="font-extrabold text-gray-800 text-xs mt-4">5. Hak Anda</h6>
                <ul className="list-disc pl-4 space-y-1">
                  <li>Hak akses, perbaikan, dan portabilitas: ajukan ke DPO (respon dalam 14 hari kerja)</li>
                  <li>Hak penghapusan: kami proses sesuai hukum (estimasi 30 hari kerja)</li>
                  <li>Hak mencabut persetujuan: dapat dilakukan kapan saja untuk pemrosesan berbasis persetujuan</li>
                </ul>

                <h6 className="font-extrabold text-gray-800 text-xs mt-4">6. Keamanan &amp; kontak</h6>
                <ul className="list-disc pl-4 space-y-1">
                  <li>Komunikasi terenkripsi (HTTPS/TLS)</li>
                  <li>Kata sandi disimpan aman (hashing modern)</li>
                  <li>RBAC dan audit log untuk operasi sensitif</li>
                  <li>Laporkan masalah atau permintaan data ke: dpo@arcav.id</li>
                </ul>

                <hr className="border-gray-100" />
                <p>Dengan menggunakan platform ini atau menyelesaikan pendaftaran, Anda menyetujui Kebijakan Privasi dan Syarat &amp; Ketentuan ARCAV HCM. Jika Anda tidak setuju, mohon jangan lanjutkan pendaftaran.</p>
              </div>
              <div className="px-6 py-3 border-t border-gray-100 flex items-center justify-between">
                <div className="flex items-center gap-2 text-[10px] text-gray-400">
                  <span className={`w-4 h-4 flex items-center justify-center text-white text-[8px] font-bold ${pdpTocScrolledToEnd ? 'bg-emerald-500' : 'bg-gray-300'}`}>
                    {pdpTocScrolledToEnd ? '✓' : ''}
                  </span>
                  Telah membaca Kebijakan Privasi &amp; Syarat
                </div>
                <div className="flex gap-2">
                  <button
                    type="button"
                    onClick={() => setShowPdpModal(false)}
                    className="px-3 py-1.5 text-xs text-gray-500 border border-gray-200 hover:bg-gray-50 cursor-pointer"
                  >
                    Tutup
                  </button>
                  <button
                    type="button"
                    disabled={!pdpTocScrolledToEnd}
                    onClick={handleAgreeAndClose}
                    className="px-3 py-1.5 text-xs font-semibold text-white bg-emerald-600 hover:bg-emerald-700 disabled:bg-gray-300 disabled:cursor-not-allowed cursor-pointer"
                  >
                    Setuju dan Lanjutkan
                  </button>
                </div>
              </div>
            </div>
          </div>
        </>
      )}
      </>
      )}
    </>
  );
}
