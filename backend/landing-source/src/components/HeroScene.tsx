import React, { useState } from "react";
import { motion } from "motion/react";
import { 
  Play, 
  Sparkles, 
  Clock, 
  ArrowRight,
  ShieldCheck
} from "lucide-react";

interface HeroSceneProps {
  onNavigate: (index: number) => void;
  packages?: any[];
  onOpenOnboarding?: () => void;
}

export default function HeroScene({ onNavigate, packages = [], onOpenOnboarding }: HeroSceneProps) {
  const [email, setEmail] = useState("");
  const [subscribed, setSubscribed] = useState(false);

  const handleSubscribe = (e: React.FormEvent) => {
    e.preventDefault();
    if (email.trim()) {
      setSubscribed(true);
      // In production: send to API
    }
  };
  return (
    <div className="w-full h-full min-h-[90vh] flex flex-col lg:flex-row items-center justify-center px-4 md:px-16 lg:px-24 py-12 lg:py-16 gap-8 lg:gap-16 relative select-none tech-grid">
      {/* Background Soft Gradients (Warm Corporate Accent Layer) */}
      <div className="absolute inset-0 overflow-hidden pointer-events-none">
        <div className="absolute top-[10%] left-[25%] -translate-x-1/2 -translate-y-1/2 w-[600px] h-[600px] rounded-full bg-gradient-to-r from-[#FF6600]/3 to-transparent blur-[110px] animate-pulse" />
        <div className="absolute bottom-1/4 right-1/4 w-[500px] h-[500px] rounded-full bg-orange-100/15 blur-[130px]" />
      </div>

      {/* LEFT CONTENT COLUMN: Executive Typographic Hierarchy */}
      <motion.div 
        initial={{ opacity: 0, x: -50 }}
        animate={{ opacity: 1, x: 0 }}
        transition={{ duration: 1, ease: "easeOut" }}
        className="w-full lg:w-1/2 flex flex-col justify-center text-left space-y-6 z-10"
        id="hero-content-left"
      >
        <div className="inline-flex items-center gap-1.5 px-3 py-1 rounded-none bg-[#FF6600]/5 border border-[#FF6600]/15 text-[#FF6600] text-xs font-mono tracking-wider w-fit">
          <Sparkles className="w-3.5 h-3.5 animate-pulse" />
          <span className="font-semibold uppercase">INTEGRATED HUMAN CAPITAL SYSTEM</span>
        </div>

        <h1 className="font-display font-extrabold text-3xl sm:text-5xl xl:text-6.5xl text-gray-950 leading-[1.08] tracking-tight">
          Operasional HR <br />
          <span className="text-[#FF6600]">
            yang Terasa Ringan
          </span> <br />
          Saat Tim Bertumbuh.
        </h1>

        <p className="text-sm sm:text-base text-gray-500 max-w-lg font-sans leading-relaxed">
          Absensi berbasis GPS, administrasi cuti, proses payroll otomatis terintegrasi PPh 21, dan pusat data karyawan dalam satu platform korporat yang tangguh, andal, dan siap pakai.
        </p>

        {/* Premium Corporate CTA Cluster */}
        <div className="flex flex-wrap items-center gap-4 pt-3">
          <button
            onClick={() => {
              if (onOpenOnboarding) onOpenOnboarding();
              else onNavigate(6);
            }}
            className="px-6 sm:px-8 py-4 rounded-none bg-[#FF6600] hover:bg-[#E05300] text-white font-bold text-xs uppercase tracking-widest relative group overflow-hidden transition-all duration-200 shadow-sm active:scale-95 cursor-pointer"
            id="hero-primary-cta"
          >
            <span className="flex items-center gap-2">
              Daftar Uji Coba Gratis
              <ArrowRight className="w-4 h-4" />
            </span>
          </button>
          
          <button
            onClick={() => onNavigate(1)} // Goes to Absensi (index 1)
            className="px-5 sm:px-6 py-4 rounded-none border border-gray-200 bg-white hover:bg-gray-50 text-gray-800 font-bold text-xs transition-all duration-200 flex items-center gap-2.5 active:scale-95 group cursor-pointer shadow-sm"
            id="hero-secondary-cta"
          >
            <div className="w-7 h-7 rounded-none bg-orange-50 flex items-center justify-center group-hover:bg-[#FF6600]/10 transition-colors">
              <Play className="w-3.5 h-3.5 text-[#FF6600] fill-[#FF6600] translate-x-[1px]" />
            </div>
            Lihat Alur Kerja Fitur
          </button>
        </div>

        {/* Lead Gen — Email Capture */}
        <form onSubmit={handleSubscribe} className="flex items-center gap-2 max-w-sm">
          <input
            type="email"
            placeholder="email@perusahaan.com"
            value={email}
            onChange={(e) => setEmail(e.target.value)}
            required
            className="flex-1 px-3 py-2.5 text-xs border border-gray-200 bg-white text-gray-900 placeholder:text-gray-300 font-sans focus:outline-none focus:border-[#FF6600] transition-colors"
          />
          <button
            type="submit"
            disabled={subscribed}
            className={`px-4 py-2.5 text-xs font-bold uppercase tracking-wider transition-all cursor-pointer ${
              subscribed
                ? "bg-emerald-500 text-white cursor-default"
                : "bg-gray-900 text-white hover:bg-gray-800"
            }`}
          >
            {subscribed ? "Terkirim ✓" : "Dapatkan Info"}
          </button>
        </form>

        {/* Micro Badges of Trust */}
        <div className="flex flex-wrap items-center gap-4 sm:gap-6 pt-6 border-t border-gray-100 text-xs font-mono text-gray-400">
          <div className="flex items-center gap-1.5">
            <ShieldCheck className="w-4 h-4 text-emerald-600 animate-pulse" />
            <span className="font-medium text-gray-500">SLA 100% Cloud Server Jakarta</span>
          </div>
          <div className="flex items-center gap-1.5">
            <span className="w-1.5 h-1.5 bg-[#FF6600] inline-block" />
            <span className="font-medium text-gray-500">Laporan Terenkripsi & Siap Audit</span>
          </div>
        </div>
      </motion.div>

      {/* RIGHT CORPORATE DASHBOARD WORKSPACE */}
      <motion.div 
        initial={{ opacity: 0, scale: 0.95 }}
        animate={{ opacity: 1, scale: 1 }}
        transition={{ duration: 1.2, delay: 0.2, ease: "easeOut" }}
        className="w-full lg:w-1/2 flex items-center justify-center relative min-h-[380px] lg:h-auto lg:py-8 z-10 mt-2 lg:mt-0"
        id="hero-dashboard-column"
      >
        <div className="relative w-full max-w-[500px] rounded-none bg-white border border-gray-200 p-4 sm:p-5 flex flex-col space-y-3 shadow-sm">
          {/* Header */}
          <div className="flex items-center justify-between pb-2 border-b border-gray-100">
            <div>
              <p className="text-[10px] font-mono text-gray-400 font-bold uppercase tracking-wider">Arkav Home Dashboard</p>
              <p className="text-[9px] text-gray-400 font-mono">Dashboard &gt; Admin Dashboard</p>
            </div>
            <span className="text-[9px] font-mono bg-emerald-50 text-emerald-700 px-2 py-0.5 border border-emerald-100 font-bold rounded-none whitespace-nowrap">SYSTEM ACTIVE</span>
          </div>

          {/* KPI Cards Row (4 cards like runtime) */}
          <div className="grid grid-cols-4 gap-2">
            <div className="bg-white border border-gray-200 p-2.5 rounded-none">
              <span className="text-[8px] font-mono font-bold text-gray-400 uppercase">Hadir</span>
              <p className="text-base font-extrabold text-gray-900 mt-0.5">124</p>
              <span className="text-[7px] text-emerald-600 font-mono font-bold">Hari Ini</span>
            </div>
            <div className="bg-white border border-gray-200 p-2.5 rounded-none">
              <span className="text-[8px] font-mono font-bold text-gray-400 uppercase">Terlambat</span>
              <p className="text-base font-extrabold text-amber-600 mt-0.5">4</p>
              <span className="text-[7px] text-gray-400 font-mono">Hari Ini</span>
            </div>
            <div className="bg-white border border-gray-200 p-2.5 rounded-none">
              <span className="text-[8px] font-mono font-bold text-gray-400 uppercase">Total Karyawan</span>
              <p className="text-base font-extrabold text-gray-900 mt-0.5">128</p>
              <span className="text-[7px] text-blue-600 font-mono font-bold">Aktif</span>
            </div>
            <div className="bg-white border border-gray-200 p-2.5 rounded-none">
              <span className="text-[8px] font-mono font-bold text-gray-400 uppercase">Cuti</span>
              <p className="text-base font-extrabold text-info mt-0.5">3</p>
              <span className="text-[7px] text-gray-400 font-mono">Menunggu</span>
            </div>
          </div>

          {/* Middle Row: Employee Status Bar + Attendance % */}
          <div className="grid grid-cols-2 gap-3">
            {/* Employee Status */}
            <div className="bg-gray-50 border border-gray-100 p-3 rounded-none">
              <p className="text-[8px] font-mono font-bold text-gray-400 uppercase tracking-wider mb-1.5">Status Karyawan</p>
              <div className="flex h-2 gap-0.5 mb-2 overflow-hidden rounded-none">
                <div className="bg-blue-500 h-full" style={{width: '72%'}} />
                <div className="bg-yellow-400 h-full" style={{width: '12%'}} />
                <div className="bg-red-400 h-full" style={{width: '8%'}} />
                <div className="bg-pink-400 h-full" style={{width: '8%'}} />
              </div>
              <div className="grid grid-cols-2 gap-x-3 gap-y-1 text-[8px] font-mono">
                <span className="text-gray-600"><span className="inline-block w-1.5 h-1.5 bg-blue-500 mr-1" />Aktif <strong>92</strong></span>
                <span className="text-gray-600"><span className="inline-block w-1.5 h-1.5 bg-yellow-400 mr-1" />Probation <strong>15</strong></span>
                <span className="text-gray-600"><span className="inline-block w-1.5 h-1.5 bg-red-400 mr-1" />Tidak Aktif <strong>10</strong></span>
                <span className="text-gray-600"><span className="inline-block w-1.5 h-1.5 bg-pink-400 mr-1" />PKWT Due <strong>11</strong></span>
              </div>
            </div>

            {/* Attendance Breakdown */}
            <div className="bg-gray-50 border border-gray-100 p-3 rounded-none">
              <p className="text-[8px] font-mono font-bold text-gray-400 uppercase tracking-wider mb-1.5">Overview Kehadiran</p>
              <div className="flex items-center gap-2 mb-2">
                <div className="w-10 h-10 rounded-full border-2 border-blue-400 flex items-center justify-center text-xs font-extrabold text-gray-900">124</div>
                <div className="text-[8px] font-mono leading-tight">
                  <span className="text-gray-400">Total Attendance</span>
                </div>
              </div>
              <div className="space-y-1 text-[7px] font-mono">
                <div className="flex justify-between"><span className="text-gray-500">Present</span><span className="text-blue-600 font-bold">78%</span></div>
                <div className="flex justify-between"><span className="text-gray-500">Late</span><span className="text-amber-600 font-bold">8%</span></div>
                <div className="flex justify-between"><span className="text-gray-500">Permission</span><span className="text-emerald-600 font-bold">10%</span></div>
                <div className="flex justify-between"><span className="text-gray-500">Absent</span><span className="text-red-500 font-bold">4%</span></div>
              </div>
            </div>
          </div>

          {/* Clock-In/Out Activity Feed */}
          <div className="space-y-1.5 border-t border-gray-100 pt-2">
            <span className="text-[8px] uppercase font-mono tracking-widest text-gray-400 font-bold block">Absensi Terkini</span>
            <div className="space-y-1.5">
              <div className="flex items-center justify-between text-[10px] bg-gray-50 border border-gray-100 p-2 rounded-none">
                <div className="flex items-center gap-2 min-w-0">
                  <span className="w-1.5 h-1.5 bg-emerald-500 rounded-full shrink-0" />
                  <span className="text-gray-800 font-semibold truncate">Budi Setiawan (Tech Lead)</span>
                </div>
                <span className="text-[8px] text-gray-500 font-mono whitespace-nowrap ml-2">08:29 → 17:01</span>
              </div>
              <div className="flex items-center justify-between text-[10px] bg-gray-50 border border-gray-100 p-2 rounded-none">
                <div className="flex items-center gap-2 min-w-0">
                  <span className="w-1.5 h-1.5 bg-blue-500 rounded-full shrink-0" />
                  <span className="text-gray-800 font-semibold truncate">Siska Amalia (Finance)</span>
                </div>
                <span className="text-[8px] text-gray-500 font-mono whitespace-nowrap ml-2">08:32 → 17:05</span>
              </div>
              <div className="flex items-center justify-between text-[10px] bg-gray-50 border border-gray-100 p-2 rounded-none">
                <div className="flex items-center gap-2 min-w-0">
                  <span className="w-1.5 h-1.5 bg-amber-500 rounded-full shrink-0" />
                  <span className="text-gray-800 font-semibold truncate">Ahmad Rizki (Designer)</span>
                </div>
                <span className="text-[8px] text-amber-600 font-mono whitespace-nowrap ml-2">Terlambat 12m</span>
              </div>
            </div>
          </div>
        </div>

        {/* Floating decoration */}
        <div className="absolute -top-3 -right-3 bg-white p-2 border border-gray-200/80 shadow-sm max-w-[120px] text-left rounded-none hidden sm:block">
          <p className="text-[8px] font-bold text-gray-900">🎯 Top Performer</p>
          <p className="text-[8px] text-gray-500 font-mono">Budi S. • Score 96</p>
        </div>
      </motion.div>
    </div>
  );
}
