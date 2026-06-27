import React, { useState } from "react";
import { motion } from "motion/react";
import { 
  Check, 
  CreditCard,
  ChevronLeft,
  ChevronRight
} from "lucide-react";

interface PricingSceneProps {
  packages?: any[];
  onOpenOnboarding?: () => void;
  turnstileEnabled?: boolean;
  turnstileSiteKey?: string;
  turnstileHideTestNotice?: boolean;
  hasActiveTrialPackages?: boolean;
}

export default function PricingScene({ 
  packages: externalPackages = [],
  onOpenOnboarding,
  turnstileEnabled = false,
  turnstileSiteKey = '',
  turnstileHideTestNotice = false,
  hasActiveTrialPackages = false
}: PricingSceneProps) {
  const [hoveredCard, setHoveredCard] = useState<number | null>(null);
  const [pricingPage, setPricingPage] = useState(0);
  const [annual, setAnnual] = useState(false);

  const hasExternalPackages = externalPackages.length > 0;

  const formatIDR = (val: number) => {
    return "Rp " + new Intl.NumberFormat("id-ID").format(val);
  };

  const hardcodedTiers = [
    {
      id: 0,
      name: "Starter",
      desc: "Ideal untuk tim kecil atau startup pra-pola.",
      rateDesc: "Perusahaan / Bulan",
      unitPrice: 199000,
      features: [
        "Presensi dengan Pencatatan GPS",
        "Pengajuan Cuti Digital",
        "3 Admin Level Pengelola",
        "Slip Gaji PDF Keamanan Standar"
      ],
      tag: "EFISIEN",
      glowBg: "from-blue-50/10 to-transparent",
      borderColor: "border-gray-200",
      buttonText: "Mulai Paket Starter"
    },
    {
      id: 1,
      name: "Growth",
      desc: "Fondasi operasional lengkap bagi perusahaan bertumbuh.",
      rateDesc: "Perusahaan / Bulan",
      unitPrice: 499000,
      features: [
        "Seluruh fitur paket Starter",
        "Roster kerja shift & Pengaturan Lembur",
        "Kalkulasi PPh 21 & BPJS Terbaca",
        "Dukungan Prioritas",
        "Akses Web Dashboard + Mobile App Terbuka"
      ],
      tag: "PALING POPULER",
      glowBg: "from-orange-50/40 to-transparent animate-pulse",
      borderColor: "border-[#FF6600]",
      buttonText: "Rekomendasi Utama"
    },
    {
      id: 2,
      name: "Enterprise",
      desc: "Kustomisasi total & integrasi khusus skala korporasi.",
      rateDesc: "Harga Kustomisasi Komprehensif",
      unitPrice: 999000,
      features: [
        "Seluruh fitur paket Growth",
        "Integrasi Data Karyawan Existing",
        "Kustomisasi Pengaturan Aplikasi",
        "Dukungan Dedicated Account Manager",
        "Akses API HRIS & Kustomisasi Laporan"
      ],
      tag: "PREMIUM ELEKTIF",
      glowBg: "from-amber-50/10 to-transparent",
      borderColor: "border-gray-200",
      buttonText: "Hubungi Penjualan"
    }
  ];

  const displayTiers = hasExternalPackages
    ? externalPackages.map((pkg: any, idx: number) => ({
        id: idx,
        name: pkg.name || pkg.code || `Package ${idx + 1}`,
        desc: pkg.description || '',
        rateDesc: 'Perusahaan / Bulan',
        unitPrice: pkg.monthlyPrice || 0,
        yearlyPrice: pkg.yearlyPrice || 0,
        features: (pkg.featureHighlights || []).map((f: any) => f.name || f.code),
        tag: idx === 1 ? 'PALING POPULER' : (idx === 0 ? 'EFISIEN' : 'PREMIUM'),
        glowBg: idx === 1 ? 'from-orange-50/40 to-transparent' : 'from-blue-50/10 to-transparent',
        borderColor: idx === 1 ? 'border-[#FF6600]' : 'border-gray-200',
        buttonText: idx === 1 ? (hasActiveTrialPackages ? 'Mulai Trial Gratis' : 'Pilih Paket') : 'Pilih Paket',
      }))
    : hardcodedTiers;

  return (
    <div className="w-full h-full min-h-[90vh] flex flex-col lg:flex-row items-center justify-center px-4 md:px-12 lg:px-24 py-16 lg:py-20 gap-8 lg:gap-12 relative select-none tech-grid">
      {/* Background World Glow Layer (Package Showroom Lights - Soft warm white) */}
      <div className="absolute inset-0 overflow-hidden pointer-events-none">
        <div className="absolute top-1/4 left-1/4 w-[400px] h-[400px] bg-orange-50/10 blur-[120px] rounded-none" />
        <div className="absolute bottom-1/3 right-1/4 w-[500px] h-[500px] bg-orange-100/5 blur-[150px] rounded-none animate-pulse duration-[8000ms]" />
      </div>

      {/* LEFT COLUMN: Section Description and Kalkulator Instan */}
      <motion.div 
        initial={{ opacity: 0, x: -50 }}
        whileInView={{ opacity: 1, x: 0 }}
        viewport={{ once: true }}
        transition={{ duration: 0.8 }}
        className="w-full lg:w-2/5 text-center lg:text-left space-y-6 z-10 flex flex-col items-center lg:items-start justify-center max-w-lg lg:max-w-none mx-auto lg:mx-0"
        id="pricing-content-left"
      >
        <div className="inline-flex items-center gap-1.5 px-3 py-1 rounded-none bg-orange-50 border border-orange-100 text-[#FF6600] text-xs font-semibold tracking-wider font-mono">
          <CreditCard className="w-3.5 h-3.5" />
          <span>FONDASI INVESTASI EFISIEN</span>
        </div>

        <h2 className="font-display font-extrabold text-3xl sm:text-4xl lg:text-5xl text-gray-950 leading-[1.08] tracking-tight">
          Pilih Fondasi Sesuai <br />
          <span className="text-[#FF6600]">
            Tahap Pertumbuhan.
          </span>
        </h2>

        <p className="text-sm text-gray-500 max-w-md font-sans leading-relaxed">
          Arkav menawarkan skema penetapan tarif fleksibel. Tidak ada biaya siluman, komitmen tersembunyi, atau instalasi mahal di muka. Pilih paket yang paling sesuai dengan kebutuhan perusahaan Anda.
        </p>

        {/* Key highlights */}
        <div className="space-y-2.5 w-full max-w-sm">
          <div className="flex items-center gap-2.5 bg-white border border-gray-200 p-3 rounded-none">
            <div className="w-8 h-8 bg-[#FF6600]/10 flex items-center justify-center text-[#FF6600] font-bold text-sm rounded-none">✓</div>
            <div>
              <p className="text-[11px] font-bold text-gray-900">Tanpa biaya setup</p>
              <p className="text-[9px] text-gray-500 font-medium">Aktivasi instan, langsung bisa dipakai</p>
            </div>
          </div>
          {hasActiveTrialPackages && (
          <div className="flex items-center gap-2.5 bg-emerald-50 border border-emerald-100 p-3 rounded-none">
            <div className="w-8 h-8 bg-emerald-100 flex items-center justify-center text-emerald-600 font-bold text-sm rounded-none">✓</div>
            <div>
              <p className="text-[11px] font-bold text-gray-900">Free trial tersedia</p>
              <p className="text-[9px] text-gray-500 font-medium">Coba gratis sebelum berlangganan</p>
            </div>
          </div>
          )}
          <div className="flex items-center gap-2.5 bg-white border border-gray-200 p-3 rounded-none">
            <div className="w-8 h-8 bg-blue-50 flex items-center justify-center text-blue-600 font-bold text-sm rounded-none">✓</div>
            <div>
              <p className="text-[11px] font-bold text-gray-900">Upgrade/downgrade kapan saja</p>
              <p className="text-[9px] text-gray-500 font-medium">Sesuaikan paket dengan pertumbuhan tim</p>
            </div>
          </div>
        </div>

        {/* Billing toggle */}
        <div className="flex items-center gap-3 pt-1">
          <button
            onClick={() => setAnnual(false)}
            className={`text-xs font-mono font-bold px-3 py-1.5 border transition-all cursor-pointer ${
              !annual ? "bg-[#FF6600] border-[#FF6600] text-white" : "border-gray-200 text-gray-500 bg-white hover:border-gray-300"
            }`}
          >
            Monthly
          </button>
          <button
            onClick={() => setAnnual(true)}
            className={`text-xs font-mono font-bold px-3 py-1.5 border transition-all cursor-pointer flex items-center gap-1.5 ${
              annual ? "bg-[#FF6600] border-[#FF6600] text-white" : "border-gray-200 text-gray-500 bg-white hover:border-gray-300"
            }`}
          >
            Annual
            <span className="text-[8px] bg-white/20 px-1 py-0.5">Hemat s.d. 15%</span>
          </button>
        </div>
      </motion.div>

      {/* RIGHT COLUMN: SaaS Product Showroom — paginated cards */}
      <div className="w-full lg:w-3/5 relative z-10 mt-6 lg:mt-0 mx-auto lg:mx-0 flex flex-col" id="pricing-showroom-column">
        <div className="flex gap-4 lg:gap-5 items-stretch justify-center">
          {displayTiers.slice(pricingPage * 3, pricingPage * 3 + 3).map((tier) => {
            const globalMid = Math.floor(displayTiers.length / 2);
            const isGrowth = tier.id === globalMid;

            return (
              <div
                key={tier.id}
                className={`flex-1 min-w-0 p-4 lg:p-5 flex flex-col justify-between cursor-pointer transition-all duration-300 h-auto min-h-[360px] relative overflow-hidden text-left rounded-none bg-white border ${
                  isGrowth ? 'border-2 border-[#FF6600] shadow-[0_12px_30px_rgba(255,102,0,0.1)] lg:-translate-y-1 z-20' : 'border border-gray-200 shadow-md z-10'
                } ${hoveredCard === tier.id ? "border-[#FF6600]" : ""}`}
                onMouseEnter={() => setHoveredCard(tier.id)}
                onMouseLeave={() => setHoveredCard(null)}
                id={`pricing-card-${tier.id}`}
              >
                {/* Tag marker */}
                <div className="flex justify-between items-center relative z-10 mb-2">
                  <span className={`text-[8px] font-mono font-bold px-2 py-0.5 border rounded-none ${
                    isGrowth ? "bg-orange-50 text-[#FF6600] border-orange-100" : "bg-gray-100 border-gray-200 text-gray-500"
                  }`}>{tier.tag}</span>
                  {isGrowth && (
                    <span className="flex h-1.5 w-1.5 relative">
                      <span className="animate-ping absolute inline-flex h-full w-full bg-[#FF6600] opacity-75 rounded-none"></span>
                      <span className="relative inline-flex h-1.5 w-1.5 bg-[#FF6600] rounded-none"></span>
                    </span>
                  )}
                </div>

                  {/* Title & price */}
                <div className="relative z-10 space-y-1">
                  <h3 className="text-lg font-extrabold font-display text-gray-900">{tier.name}</h3>
                  <p className="text-[10px] text-gray-500 leading-relaxed font-medium line-clamp-2">{tier.desc}</p>
                  <div className="pt-1">
                    {annual ? (
                      <div className="flex items-baseline gap-1">
                        <span className="text-xl font-extrabold font-mono tracking-tight text-gray-950">
                          {formatIDR(Math.round(((tier as any).yearlyPrice || tier.unitPrice * 12 * 0.85) / 12))}
                        </span>
                        <span className="text-[8px] text-gray-400 font-mono font-bold">/bln</span>
                        <span className="text-[8px] font-mono font-bold text-emerald-600 bg-emerald-50 border border-emerald-100 px-1 py-0.5">HEMAT 15%</span>
                      </div>
                    ) : (
                      <div className="flex items-baseline gap-1">
                        <span className="text-xl font-extrabold font-mono tracking-tight text-gray-950">{formatIDR(tier.unitPrice)}</span>
                        <span className="text-[8px] text-gray-400 font-mono font-bold">/bln</span>
                      </div>
                    )}
                    <p className="text-[7px] text-gray-400 font-mono font-bold uppercase">
                      {annual
                        ? formatIDR(Math.round((tier as any).yearlyPrice || tier.unitPrice * 12 * 0.85)) + '/thn'
                        : tier.rateDesc}
                    </p>
                  </div>
                </div>

                {/* Features */}
                <div className="relative z-10 flex-grow py-3 space-y-1.5 border-t border-gray-100 mt-2">
                  {tier.features.map((feat, index) => (
                    <div key={index} className="flex items-start gap-1.5 text-[9.5px]">
                      <span className="w-3 h-3 bg-emerald-50 border border-emerald-100 flex items-center justify-center text-emerald-700 text-[7px] shrink-0 font-bold mt-0.5 rounded-none">
                        <Check className="w-2 h-2 text-emerald-600" />
                      </span>
                      <span className="text-gray-500 leading-relaxed font-medium">{feat}</span>
                    </div>
                  ))}
                </div>

                {/* CTA */}
                <button className={`w-full py-2.5 font-extrabold text-[9px] font-sans uppercase tracking-widest relative z-10 transition-colors rounded-none ${
                  isGrowth ? "bg-[#FF6600] hover:bg-orange-600 text-white" : "bg-gray-50 text-gray-700 border border-gray-200/80 hover:bg-gray-100 shadow-xs"
                }`} onClick={() => { if (onOpenOnboarding) onOpenOnboarding(); }}>
                  {tier.buttonText}
                </button>
              </div>
            );
          })}
        </div>

        {/* Pagination arrows */}
        {displayTiers.length > 3 && (
          <div className="flex items-center justify-center gap-4 mt-3 lg:mt-4 text-xs">
            <button
              onClick={() => setPricingPage(p => Math.max(0, p - 1))}
              disabled={pricingPage === 0}
              className={`p-1.5 border rounded-none transition-colors ${pricingPage === 0 ? 'border-gray-100 text-gray-300 cursor-not-allowed' : 'border-gray-200 text-gray-600 hover:border-[#FF6600] hover:text-[#FF6600] cursor-pointer'}`}
            ><ChevronLeft className="w-4 h-4" /></button>
            <span className="text-[10px] font-mono text-gray-400">
              {pricingPage + 1} / {Math.ceil(displayTiers.length / 3)}
            </span>
            <button
              onClick={() => setPricingPage(p => Math.min(Math.ceil(displayTiers.length / 3) - 1, p + 1))}
              disabled={pricingPage >= Math.ceil(displayTiers.length / 3) - 1}
              className={`p-1.5 border rounded-none transition-colors ${pricingPage >= Math.ceil(displayTiers.length / 3) - 1 ? 'border-gray-100 text-gray-300 cursor-not-allowed' : 'border-gray-200 text-gray-600 hover:border-[#FF6600] hover:text-[#FF6600] cursor-pointer'}`}
            ><ChevronRight className="w-4 h-4" /></button>
          </div>
        )}
      </div>
    </div>
  );
}
