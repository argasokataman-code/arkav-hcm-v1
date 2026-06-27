import React, { useState, useEffect } from "react";
import { motion, AnimatePresence } from "motion/react";
import { Play, MapPin, Calendar, FileText, BarChart3, ArrowRight } from "lucide-react";

const slides = [
  {
    icon: <MapPin className="w-4 h-4" />,
    title: "Absensi GPS",
    desc: "Karyawan absen dari HP dengan lokasi realtime. Selfie + timestamp otomatis.",
    color: "text-orange-600",
    bgColor: "bg-orange-50 border-orange-100",
    chart: {
      labels: ["Hadir", "Terlambat", "Izin", "Absen"],
      values: [78, 12, 7, 3],
      colors: ["bg-emerald-500", "bg-amber-400", "bg-blue-400", "bg-red-400"],
    },
  },
  {
    icon: <Calendar className="w-4 h-4" />,
    title: "Cuti Digital",
    desc: "Ajukan cuti, approval atasan, kuota otomatis terupdate. Semua paperless.",
    color: "text-cyan-600",
    bgColor: "bg-cyan-50 border-cyan-100",
    chart: null,
    statLine: "Sisa Cuti: 8 hari • Proses: 2 pending • Riwayat: 12 hari",
  },
  {
    icon: <FileText className="w-4 h-4" />,
    title: "Payroll Otomatis",
    desc: "PPh 21, BPJS, THR, lembur — semua dihitung otomatis. Slip gaji PDF + email.",
    color: "text-purple-600",
    bgColor: "bg-purple-50 border-purple-100",
    chart: null,
    statLine: "Rp 450jt • 48 karyawan • 18 komponen • Finalisasi: 3 menit",
  },
  {
    icon: <BarChart3 className="w-4 h-4" />,
    title: "Analitik HR",
    desc: "Rekap kehadiran, biaya payroll, tren cuti — dashboard realtime untuk manajemen.",
    color: "text-emerald-600",
    bgColor: "bg-emerald-50 border-emerald-100",
    chart: null,
    statLine: "12 laporan • 6 filter • Export CSV/XLS • Realtime update",
  },
];

export default function DemoScene() {
  const [activeIndex, setActiveIndex] = useState(0);
  const [autoplay, setAutoplay] = useState(true);

  useEffect(() => {
    if (!autoplay) return;
    const timer = setInterval(() => {
      setActiveIndex((prev) => (prev + 1) % slides.length);
    }, 3500);
    return () => clearInterval(timer);
  }, [autoplay]);

  const slide = slides[activeIndex];

  return (
    <div className="w-full h-full min-h-[90vh] flex flex-col lg:flex-row items-center justify-center px-4 md:px-16 lg:px-24 py-16 gap-12 relative select-none tech-grid">
      {/* Background */}
      <div className="absolute inset-0 overflow-hidden pointer-events-none">
        <div className="absolute top-[20%] left-[20%] w-[500px] h-[500px] rounded-none bg-gradient-to-br from-blue-50/20 to-transparent blur-[130px]" />
        <div className="absolute bottom-[20%] right-[20%] w-[450px] h-[450px] rounded-none bg-gradient-to-l from-[#FF6600]/5 to-transparent blur-[110px]" />
      </div>

      {/* LEFT: Content + Controls */}
      <motion.div
        initial={{ opacity: 0, x: -40 }}
        whileInView={{ opacity: 1, x: 0 }}
        viewport={{ once: true }}
        transition={{ duration: 0.8, ease: "easeOut" }}
        className="w-full lg:w-1/2 flex flex-col justify-center text-left space-y-6 z-10"
      >
        <div className="inline-flex items-center gap-1.5 px-3 py-1 rounded-none bg-[#FF6600]/5 border border-[#FF6600]/15 text-[#FF6600] text-xs font-semibold tracking-wider font-mono w-fit uppercase">
          <Play className="w-3.5 h-3.5" />
          <span>Demo Produk</span>
        </div>

        <h2 className="font-display font-extrabold text-3xl sm:text-4xl lg:text-5xl text-gray-950 leading-[1.08] tracking-tight">
          Lihat Langsung <br />
          <span className="text-[#FF6600]">Cara Kerjanya</span>
        </h2>

        <p className="text-sm sm:text-base text-gray-500 max-w-lg font-sans leading-relaxed">
          Simulasi interaktif fitur utama Arkav HCM. Geser atau klik untuk lihat demo masing-masing modul.
        </p>

        {/* Feature tabs */}
        <div className="flex flex-wrap gap-2">
          {slides.map((s, i) => (
            <button
              key={i}
              onClick={() => { setActiveIndex(i); setAutoplay(false); }}
              className={`flex items-center gap-1.5 text-[10px] font-mono font-semibold px-3 py-1.5 border transition-all cursor-pointer ${
                activeIndex === i
                  ? "bg-[#FF6600] border-[#FF6600] text-white"
                  : "border-gray-200 text-gray-500 bg-white/60 hover:border-[#FF6600]/30 hover:text-[#FF6600]"
              }`}
            >
              {s.icon}
              {s.title}
            </button>
          ))}
          <button
            onClick={() => setAutoplay(!autoplay)}
            className={`text-[10px] font-mono px-2 py-1.5 border transition-all cursor-pointer ${
              autoplay ? "bg-emerald-50 border-emerald-200 text-emerald-600" : "border-gray-200 text-gray-400"
            }`}
          >
            {autoplay ? "AUTO ON" : "AUTO OFF"}
          </button>
        </div>

        {/* Active description */}
        <AnimatePresence mode="wait">
          <motion.div
            key={activeIndex}
            initial={{ opacity: 0, y: 10 }}
            animate={{ opacity: 1, y: 0 }}
            exit={{ opacity: 0, y: -10 }}
            className="space-y-2"
          >
            <div className={`inline-flex items-center gap-1.5 px-2 py-1 text-[10px] font-mono font-bold ${slide.bgColor} ${slide.color} border`}>
              {slide.icon}
              <span>{slide.title}</span>
            </div>
            <p className="text-sm text-gray-600 leading-relaxed">{slide.desc}</p>
            {slide.statLine && (
              <p className="text-[10px] font-mono text-gray-400 bg-gray-50 border border-gray-100 px-3 py-2">{slide.statLine}</p>
            )}
          </motion.div>
        </AnimatePresence>

        {/* Dot navigation */}
        <div className="flex items-center gap-2">
          {slides.map((_, i) => (
            <button
              key={i}
              onClick={() => { setActiveIndex(i); setAutoplay(false); }}
              className={`h-1.5 transition-all duration-300 cursor-pointer ${
                i === activeIndex ? "w-6 bg-[#FF6600]" : "w-3 bg-gray-300 hover:bg-gray-400"
              }`}
            />
          ))}
        </div>
      </motion.div>

      {/* RIGHT: Interactive display mockup */}
      <motion.div
        initial={{ opacity: 0, x: 40 }}
        whileInView={{ opacity: 1, x: 0 }}
        viewport={{ once: true }}
        transition={{ duration: 0.8, delay: 0.15, ease: "easeOut" }}
        className="w-full lg:w-1/2 z-10 flex items-center justify-center"
      >
        <div className="w-full max-w-[480px] bg-white border border-gray-200 shadow-sm overflow-hidden">
          {/* Mock browser chrome */}
          <div className="flex items-center gap-1.5 px-3 py-2 border-b border-gray-100 bg-gray-50">
            <span className="w-2.5 h-2.5 rounded-full bg-red-300" />
            <span className="w-2.5 h-2.5 rounded-full bg-amber-300" />
            <span className="w-2.5 h-2.5 rounded-full bg-emerald-300" />
            <span className="text-[8px] font-mono text-gray-400 ml-2">app.arkav.id/dashboard</span>
          </div>

          {/* Demo content */}
          <AnimatePresence mode="wait">
            <motion.div
              key={activeIndex}
              initial={{ opacity: 0, y: 12 }}
              animate={{ opacity: 1, y: 0 }}
              exit={{ opacity: 0, y: -12 }}
              transition={{ duration: 0.3 }}
              className="p-5 space-y-4"
            >
              {/* Feature specific mockup */}
              {activeIndex === 0 && (
                <div className="space-y-3">
                  <div className="flex items-center justify-between">
                    <span className="text-[10px] font-mono font-bold text-gray-400 uppercase">Absensi Hari Ini</span>
                    <span className="text-[10px] font-mono text-emerald-600 font-bold bg-emerald-50 border border-emerald-100 px-2 py-0.5">LIVE</span>
                  </div>
                  <div className="grid grid-cols-2 gap-2">
                    {[
                      { label: "Masuk", time: "08:12", name: "Budi S." },
                      { label: "Pulang", time: "17:05", name: "Sari P." },
                      { label: "Izin", status: "Pending", name: "Dimas A." },
                      { label: "Terlambat", time: "08:47", name: "Rina W." },
                    ].map((d, i) => (
                      <div key={i} className="border border-gray-100 bg-gray-50 p-2.5 text-left">
                        <span className="text-[9px] font-mono text-gray-400 block">{d.label}</span>
                        <span className="text-xs font-bold text-gray-900">{d.time || d.status}</span>
                        <span className="text-[9px] text-gray-500 font-mono block">{d.name}</span>
                      </div>
                    ))}
                  </div>
                  {/* Bar chart */}
                  <div className="space-y-1 pt-1">
                    <span className="text-[9px] font-mono text-gray-400">Overview hari ini</span>
                    <div className="flex h-2 gap-0.5">
                      <div className="bg-emerald-500 h-full" style={{ width: '78%' }} />
                      <div className="bg-amber-400 h-full" style={{ width: '12%' }} />
                      <div className="bg-blue-400 h-full" style={{ width: '7%' }} />
                      <div className="bg-red-400 h-full" style={{ width: '3%' }} />
                    </div>
                    <div className="flex text-[7px] font-mono text-gray-400 justify-between">
                      <span>Hadir 78%</span>
                      <span>Terlambat 12%</span>
                      <span>Izin 7%</span>
                      <span>Absen 3%</span>
                    </div>
                  </div>
                </div>
              )}

              {activeIndex === 1 && (
                <div className="space-y-3">
                  <div className="flex items-center justify-between">
                    <span className="text-[10px] font-mono font-bold text-gray-400 uppercase">Pengajuan Cuti</span>
                    <span className="text-[10px] font-mono text-cyan-600 font-bold">{Date().includes('Jun') ? 'Juni 2026' : '2026'}</span>
                  </div>
                  <div className="border border-gray-100 divide-y divide-gray-100">
                    {[
                      { name: "Rina W.", type: "Cuti Tahunan", days: 3, status: "Approved", color: "text-emerald-600 bg-emerald-50" },
                      { name: "Dimas A.", type: "Cuti Sakit", days: 1, status: "Pending", color: "text-amber-600 bg-amber-50" },
                      { name: "Budi S.", type: "Izin", days: 0.5, status: "Approved", color: "text-emerald-600 bg-emerald-50" },
                    ].map((d, i) => (
                      <div key={i} className="flex items-center justify-between p-2.5 text-left">
                        <div>
                          <span className="text-xs font-bold text-gray-900 block">{d.name}</span>
                          <span className="text-[9px] text-gray-400 font-mono">{d.type} • {d.days} hari</span>
                        </div>
                        <span className={`text-[9px] font-mono font-bold px-1.5 py-0.5 border ${d.color} border-current/20`}>{d.status}</span>
                      </div>
                    ))}
                  </div>
                  <p className="text-[9px] font-mono text-gray-400 text-right">Sisa kuota: 12 hari</p>
                </div>
              )}

              {activeIndex === 2 && (
                <div className="space-y-3">
                  <div className="flex items-center justify-between">
                    <span className="text-[10px] font-mono font-bold text-gray-400 uppercase">Ringkasan Payroll</span>
                    <span className="text-[10px] font-mono text-purple-600 font-bold">Mei 2026</span>
                  </div>
                  <div className="grid grid-cols-2 gap-2">
                    <div className="bg-purple-50 border border-purple-100 p-3 text-left">
                      <span className="text-[9px] font-mono text-purple-600 font-bold block">Take Home Pay</span>
                      <span className="text-sm font-extrabold text-gray-900">Rp 358.5jt</span>
                    </div>
                    <div className="bg-amber-50 border border-amber-100 p-3 text-left">
                      <span className="text-[9px] font-mono text-amber-600 font-bold block">Potongan</span>
                      <span className="text-sm font-extrabold text-gray-900">Rp 91.2jt</span>
                    </div>
                  </div>
                  <div className="space-y-1">
                    <span className="text-[9px] font-mono text-gray-400">Komponen</span>
                    {["Gaji Pokok", "Tunjangan", "BPJS", "PPh 21", "Lembur"].map((c, i) => (
                      <div key={i} className="flex items-center justify-between text-[10px] border-b border-gray-50 pb-1">
                        <span className="text-gray-600">{c}</span>
                        <span className="font-mono font-bold text-gray-800">Rp {(i + 1) * 12}jt</span>
                      </div>
                    ))}
                  </div>
                </div>
              )}

              {activeIndex === 3 && (
                <div className="space-y-3">
                  <div className="flex items-center justify-between">
                    <span className="text-[10px] font-mono font-bold text-gray-400 uppercase">Dashboard Analytics</span>
                    <span className="text-[10px] font-mono text-emerald-600 font-bold">Realtime</span>
                  </div>
                  <div className="grid grid-cols-3 gap-2">
                    {[
                      { label: "Total Karyawan", value: "128", sub: "+3 bulan ini" },
                      { label: "Rata-rata Absensi", value: "95.2%", sub: "Naik 2.1%" },
                      { label: "Biaya Payroll", value: "Rp 449jt", sub: "Bulan berjalan" },
                    ].map((d, i) => (
                      <div key={i} className="border border-gray-100 p-2 text-left">
                        <span className="text-[7px] font-mono text-gray-400 block">{d.label}</span>
                        <span className="text-xs font-extrabold text-gray-900">{d.value}</span>
                        <span className="text-[7px] text-gray-500 font-mono block">{d.sub}</span>
                      </div>
                    ))}
                  </div>
                  {/* Mini trend line */}
                  <div className="border border-gray-100 p-3">
                    <span className="text-[9px] font-mono text-gray-400 block mb-1">Tren Kehadiran (6 bulan)</span>
                    <div className="flex items-end gap-1 h-10">
                      {[65, 72, 78, 82, 88, 95].map((v, i) => (
                        <div key={i} className="flex-1 bg-[#FF6600]/20 border-t border-[#FF6600]" style={{ height: `${v}%` }} />
                      ))}
                    </div>
                    <div className="flex justify-between text-[7px] font-mono text-gray-400 mt-1">
                      <span>Jan</span><span>Feb</span><span>Mar</span><span>Apr</span><span>Mei</span><span>Jun</span>
                    </div>
                  </div>
                </div>
              )}
            </motion.div>
          </AnimatePresence>
        </div>
      </motion.div>
    </div>
  );
}
