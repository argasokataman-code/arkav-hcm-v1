import React from "react";
import { motion } from "motion/react";
import { Star, Quote, Users, Building2, Clock, TrendingUp } from "lucide-react";

const stats = [
  { icon: <Building2 className="w-5 h-5" />, value: "150+", label: "Perusahaan Aktif" },
  { icon: <Users className="w-5 h-5" />, value: "12.500+", label: "Karyawan Terkelola" },
  { icon: <Clock className="w-5 h-5" />, value: "4.200+", label: "Jam Kerja Terekam/Hari" },
  { icon: <TrendingUp className="w-5 h-5" />, value: "98%", label: "Retensi Pelanggan" },
];

const testimonials = [
  {
    name: "Rina Wijaya",
    role: "HR Manager — PT Maju Sejahtera",
    avatar: "RW",
    text: "Dulu payroll 50 karyawan makan 3-4 hari kerja. Sekarang cukup 2 jam. THR, BPJS, PPh 21 semuanya otomatis. ROI-nya langsung kerasa di bulan pertama.",
    rating: 5,
  },
  {
    name: "Dimas Ardiansyah",
    role: "Finance Director — CV Karya Digital",
    avatar: "DA",
    text: "Absensi GPS + payroll terintegrasi jadi game changer buat tim sales lapangan kami. Gak ada lagi sengketa lembur atau izin yang gak jelas.",
    rating: 5,
  },
  {
    name: "Sari Purnama",
    role: "CEO — StartupOS Indonesia",
    avatar: "SP",
    text: "Pas masih 15 karyawan, nyari sistem HR yang sesuai skala itu susah. Arkav harganya masuk, fiturnya pas, dan support-nya cepat. Cocok buat startup.",
    rating: 5,
  },
];

const logos = [
  "MAJU SEJAHTERA", "KARYA DIGITAL", "STARTUPOS", "BANGUN BERSAMA",
  "GLOBAL TEKNIK", "MITRA UTAMA", "SUMBER ALAM", "CIPTA KARYA"
];

export default function TestimonialScene({ isMobile = false }: { isMobile?: boolean }) {
  const [activeIdx, setActiveIdx] = React.useState(0);

  return (
    <div className="w-full h-full min-h-[90vh] flex flex-col lg:flex-row items-center justify-center px-4 md:px-16 lg:px-24 py-16 gap-12 relative select-none tech-grid">
      {/* Background */}
      <div className="absolute inset-0 overflow-hidden pointer-events-none">
        <div className="absolute top-[20%] left-[20%] w-[500px] h-[500px] rounded-none bg-gradient-to-br from-emerald-50/30 to-transparent blur-[120px]" />
        <div className="absolute bottom-[20%] right-[20%] w-[400px] h-[400px] rounded-none bg-gradient-to-l from-[#FF6600]/5 to-transparent blur-[100px]" />
      </div>

      {/* LEFT: Numbers + Logos */}
      <motion.div
        initial={{ opacity: 0, x: -40 }}
        whileInView={{ opacity: 1, x: 0 }}
        viewport={{ once: true }}
        transition={{ duration: 0.8, ease: "easeOut" }}
        className="w-full lg:w-1/2 flex flex-col justify-center text-left space-y-8 z-10"
      >
        {/* Badge */}
        <div className="inline-flex items-center gap-1.5 px-3 py-1 rounded-none bg-[#FF6600]/5 border border-[#FF6600]/15 text-[#FF6600] text-xs font-semibold tracking-wider font-mono w-fit uppercase">
          <Star className="w-3.5 h-3.5" />
          <span>Social Proof</span>
        </div>

        <h2 className="font-display font-extrabold text-3xl sm:text-4xl lg:text-5xl text-gray-950 leading-[1.08] tracking-tight">
          Dipercaya oleh <br />
          <span className="text-[#FF6600]">150+ Perusahaan</span> <br />
          di Indonesia
        </h2>

        <p className="text-sm sm:text-base text-gray-500 max-w-lg font-sans leading-relaxed">
          Ribuan HR profesional dan pemilik bisnis sudah beralih ke Arkav HCM. 
          Ini cerita mereka.
        </p>

        {/* Stats Grid */}
        <div className="grid grid-cols-2 gap-4 max-w-md">
          {stats.map((s, i) => (
            <motion.div
              key={i}
              initial={{ opacity: 0, y: 20 }}
              whileInView={{ opacity: 1, y: 0 }}
              viewport={{ once: true }}
              transition={{ delay: i * 0.1 }}
              className="bg-white border border-gray-200 p-4 flex flex-col gap-1.5"
            >
              <div className="text-[#FF6600]">{s.icon}</div>
              <span className="text-2xl font-extrabold font-display text-gray-900">{s.value}</span>
              <span className="text-[10px] font-mono text-gray-500 uppercase tracking-wider">{s.label}</span>
            </motion.div>
          ))}
        </div>

        {/* Logo Cloud */}
        <div className="pt-2">
          <p className="text-[10px] font-mono text-gray-400 uppercase tracking-wider mb-3">Digunakan oleh berbagai industri</p>
          <div className="flex flex-wrap gap-3">
            {logos.map((name, i) => (
              <span key={i} className="text-[11px] font-mono font-bold text-gray-300 tracking-widest bg-white/50 border border-gray-100 px-3 py-1.5">
                {name}
              </span>
            ))}
          </div>
        </div>
      </motion.div>

      {/* RIGHT: Testimonials */}
      <motion.div
        initial={{ opacity: 0, x: 40 }}
        whileInView={{ opacity: 1, x: 0 }}
        viewport={{ once: true }}
        transition={{ duration: 0.8, delay: 0.15, ease: "easeOut" }}
        className="w-full lg:w-1/2 flex flex-col gap-6 z-10"
      >
        {/* Desktop: show all 3 cards */}
        {!isMobile ? (
          <div className="space-y-4">
            {testimonials.map((t, i) => (
              <motion.div
                key={i}
                initial={{ opacity: 0, y: 20 }}
                whileInView={{ opacity: 1, y: 0 }}
                viewport={{ once: true }}
                transition={{ delay: i * 0.1 }}
                className="bg-white border border-gray-200 p-5 flex gap-4 shadow-sm"
                onMouseEnter={() => setActiveIdx(i)}
              >
                <div className="w-10 h-10 shrink-0 bg-[#FF6600]/10 border border-[#FF6600]/20 flex items-center justify-center text-xs font-bold text-[#FF6600] font-mono">
                  {t.avatar}
                </div>
                <div className="flex-1 min-w-0">
                  <div className="flex items-center gap-1 mb-1">
                    {Array.from({ length: t.rating }).map((_, si) => (
                      <Star key={si} className="w-3 h-3 fill-amber-400 text-amber-400" />
                    ))}
                  </div>
                  <p className="text-sm text-gray-600 leading-relaxed mb-2 italic">"{t.text}"</p>
                  <div>
                    <span className="text-xs font-bold text-gray-900">{t.name}</span>
                    <span className="text-[10px] text-gray-400 font-mono block">{t.role}</span>
                  </div>
                </div>
              </motion.div>
            ))}
          </div>
        ) : (
          /* Mobile: single card with dots */
          <div className="space-y-4">
            <div className="bg-white border border-gray-200 p-5 shadow-sm">
              <div className="flex items-center gap-1 mb-2">
                {Array.from({ length: testimonials[activeIdx].rating }).map((_, si) => (
                  <Star key={si} className="w-3 h-3 fill-amber-400 text-amber-400" />
                ))}
              </div>
              <p className="text-sm text-gray-600 leading-relaxed mb-2 italic">"{testimonials[activeIdx].text}"</p>
              <div className="flex items-center gap-3">
                <div className="w-9 h-9 bg-[#FF6600]/10 border border-[#FF6600]/20 flex items-center justify-center text-xs font-bold text-[#FF6600] font-mono">
                  {testimonials[activeIdx].avatar}
                </div>
                <div>
                  <span className="text-xs font-bold text-gray-900">{testimonials[activeIdx].name}</span>
                  <span className="text-[10px] text-gray-400 font-mono block">{testimonials[activeIdx].role}</span>
                </div>
              </div>
            </div>
            {/* Dots */}
            <div className="flex items-center justify-center gap-2">
              {testimonials.map((_, i) => (
                <button
                  key={i}
                  onClick={() => setActiveIdx(i)}
                  className={`w-2 h-2 transition-all duration-200 cursor-pointer ${
                    i === activeIdx ? "bg-[#FF6600] w-4" : "bg-gray-300 hover:bg-gray-400"
                  }`}
                />
              ))}
            </div>
          </div>
        )}
      </motion.div>
    </div>
  );
}
