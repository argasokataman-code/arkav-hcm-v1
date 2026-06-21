import React, { useState } from "react";
import { motion, AnimatePresence } from "motion/react";
import { 
  Orbit, 
  MapPin, 
  FileCheck, 
  Cpu, 
  Calendar, 
  TrendingUp,
  ArrowRight
} from "lucide-react";
import arkavLogo from "../assets/arkav-logo.png";

export default function FinaleScene({ onNavigate, isMobile = false, onOpenOnboarding }: { 
  onNavigate: (index: number) => void; 
  isMobile?: boolean;
  onOpenOnboarding?: () => void;
}) {
  const [selectedModule, setSelectedModule] = useState<number | null>(null);

  const modules = [
    {
      id: 0,
      name: "Attendance GPS",
      desc: "Rekap kehadiran dengan data lokasi GPS",
      icon: <MapPin className="w-4 h-4 text-[#FF6600]" />,
      angle: "0deg",
      top: "15%",
      left: "15%",
    },
    {
      id: 1,
      name: "Payroll PPh21",
      desc: "Hitung otomatis pajak masa progresif, bonus, bpjs, dan denda.",
      icon: <Cpu className="w-4 h-4 text-emerald-600" />,
      angle: "60deg",
      top: "10%",
      left: "70%",
    },
    {
      id: 2,
      name: "Leave Manager",
      desc: "Sirkuit permohonan digital paperless yang terpadu ke kalender.",
      icon: <Calendar className="w-4 h-4 text-cyan-600" />,
      angle: "120deg",
      top: "75%",
      left: "12%",
    },
    {
      id: 3,
      name: "Executive Charts",
      desc: "Visualisasi analitik pengeluaran overhead dan loyalitas tim.",
      icon: <TrendingUp className="w-4 h-4 text-amber-600" />,
      angle: "180deg",
      top: "78%",
      left: "72%",
    },
    {
      id: 4,
      name: "Approval Cuti",
      desc: "Hierarki persetujuan bertingkat hingga tingkat direksi finansial.",
      icon: <FileCheck className="w-4 h-4 text-purple-600" />,
      angle: "240deg",
      top: "45%",
      left: "83%",
    }
  ];

  return (
    <div className="w-full h-full min-h-[90vh] flex flex-col lg:flex-row items-center justify-center px-4 md:px-16 lg:px-24 py-16 gap-12 relative select-none tech-grid">
      {/* Background World Glow Layer (Arkav Ecosystem Universe - Majestic pulsing core - Soft warm white) */}
      <div className="absolute inset-0 overflow-hidden pointer-events-none">
        <div className="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[700px] h-[700px] rounded-none bg-orange-100/10 blur-[180px] animate-pulse duration-[6000ms]" />
        
        {/* Concentric subtle line circles to simulate space orbit - SQUARES for high tech feel */}
        <div className="hidden lg:block absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[600px] h-[600px] rounded-none border border-gray-200/25" />
        <div className="hidden lg:block absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[400px] h-[400px] rounded-none border border-gray-200/40" />
        <div className="hidden lg:block absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[200px] h-[200px] rounded-none border border-orange-200/50" />
      </div>

      {/* LEFT COLUMN: Narrative & Final Pitch Call to Actions */}
      <motion.div 
        initial={{ opacity: 0, x: -50 }}
        whileInView={{ opacity: 1, x: 0 }}
        viewport={{ once: true }}
        transition={{ duration: 0.8 }}
        className="w-full lg:w-1/2 flex flex-col justify-center text-left space-y-6 z-10"
        id="finale-content-left"
      >
        <div className="inline-flex items-center gap-1.5 px-3 py-1 rounded-none bg-orange-50 border border-orange-100 text-[#FF6600] text-xs font-semibold tracking-wider font-mono w-fit">
          <Orbit className="w-3.5 h-3.5 animate-spin duration-[6000ms]" />
          <span>ARKAV HCM COMPLETE PLATFORM</span>
        </div>

        <h2 className="font-display font-extrabold text-3xl sm:text-4xl lg:text-5xl text-gray-950 leading-[1.08] tracking-tight">
          Saat Operasional Berjalan Rapi, <br />
          <span className="text-[#FF6600]">
            Tim Fokus Bertumbuh.
          </span>
        </h2>

        <p className="text-sm sm:text-base text-gray-500 max-w-lg font-sans leading-relaxed">
          Bangun iklim kerja operasional SDM yang transparan, otomatis, cepat, dan siap mempercepat pertumbuhan perusahaan Anda. Bergabung bersama puluhan perusahaan modern berskala nasional.
        </p>

        {/* Grand CTA Cluster */}
        <div className="flex flex-wrap items-center gap-4 pt-4">
          <button
            onClick={() => {
              if (onOpenOnboarding) onOpenOnboarding();
              else onNavigate(6);
            }}
            className="px-8 py-4.5 bg-[#FF6600] hover:bg-orange-600 text-white font-extrabold text-xs uppercase tracking-widest relative group overflow-hidden transition-all duration-300 active:scale-95 cursor-pointer shadow-sm rounded-none"
            id="grand-primary-cta"
          >
            <span className="relative z-10 flex items-center gap-2">
              Daftar Uji Coba Gratis
              <ArrowRight className="w-4 h-4" />
            </span>
          </button>
          
          <button
            onClick={() => onNavigate(0)} // Reset back to start
            className="px-6 py-4 border border-gray-200 bg-white hover:bg-gray-50 text-gray-700 font-bold text-xs transition-all uppercase tracking-wider font-mono flex items-center gap-2 active:scale-95 cursor-pointer shadow-sm rounded-none"
            id="grand-secondary-cta"
          >
            <span>KEMBALI KE HULU</span>
          </button>
        </div>

        {/* Micro trusted review tag */}
        <p className="text-[10px] text-gray-400 font-mono font-bold uppercase tracking-widest">
          Sistem Cloud Dilindungi Enkripsi AES-256 • Server Cepat Jakarta Core
        </p>
        
        {/* Legal links */}
        <div className="flex items-center gap-4 pt-2 text-[9px] font-mono text-gray-400">
          <a href="/privacy-policy" target="_blank" rel="noopener noreferrer" className="hover:text-[#FF6600] transition-colors underline underline-offset-2">Kebijakan Privasi</a>
          <span className="text-gray-300">|</span>
          <a href="/terms-condition" target="_blank" rel="noopener noreferrer" className="hover:text-[#FF6600] transition-colors underline underline-offset-2">Syarat &amp; Ketentuan</a>
        </div>
      </motion.div>

      {/* RIGHT COLUMN: Interactive space orbital visualization */}
      <div 
        className="w-full lg:w-1/2 flex items-center justify-center relative min-h-[380px] lg:min-h-[480px] z-10 mt-6 lg:mt-0"
        id="finale-orbit-column"
      >
        <div className="relative w-full max-w-[450px] h-auto lg:h-[390px] flex flex-col lg:block items-center justify-center gap-4 lg:gap-0">
          
          {/* Glowing central core emblem */}
          <div className="relative lg:absolute lg:top-1/2 lg:left-1/2 lg:-translate-x-1/2 lg:-translate-y-1/2 z-20 w-20 h-20 sm:w-24 sm:h-24 flex items-center justify-center select-none shadow-sm rounded-none overflow-hidden">
            <img 
              src={arkavLogo} 
              alt="Arkav HCM" 
              className="w-full h-full object-contain"
            />
          </div>

          {/* SVG Relation Lines connecting each orbital module node to the central ARK core (Desktop only) */}
          {!isMobile && (
            <svg className="absolute inset-0 w-full h-full pointer-events-none z-10 hidden lg:block" viewBox="0 0 450 390">
              {/* Central Core center coordinates in the 450x390 SVG viewbox are approximately (225, 195) */}
              
              {/* Line to Attendance GPS (15%, 15% -> approx 67, 58) */}
              <line x1="225" y1="195" x2="80" y2="78" stroke="#FF6600" strokeWidth="1.5" strokeOpacity="0.4" strokeDasharray="4 4" />
              
              {/* Line to Payroll PPh21 (70%, 10% -> approx 315, 39) */}
              <line x1="225" y1="195" x2="335" y2="58" stroke="#FF6600" strokeWidth="1.5" strokeOpacity="0.4" strokeDasharray="4 4" />
              
              {/* Line to Leave Manager (12%, 75% -> approx 54, 292) */}
              <line x1="225" y1="195" x2="70" y2="305" stroke="#FF6600" strokeWidth="1.5" strokeOpacity="0.4" strokeDasharray="4 4" />
              
              {/* Line to Executive Charts (72%, 78% -> approx 324, 304) */}
              <line x1="225" y1="195" x2="340" y2="315" stroke="#FF6600" strokeWidth="1.5" strokeOpacity="0.4" strokeDasharray="4 4" />
              
              {/* Line to Multi Approval (83%, 45% -> approx 373, 175) */}
              <line x1="225" y1="195" x2="385" y2="185" stroke="#FF6600" strokeWidth="1.5" strokeOpacity="0.4" strokeDasharray="4 4" />
            </svg>
          )}

          {/* Modules: mobile card list, tablet grid, desktop space orbit */}
          {isMobile ? (
            <div className="w-full flex flex-col gap-3 px-4 z-30 mt-4">
              {modules.map((mod) => (
                <div 
                  key={mod.id}
                  className="bg-white border border-gray-200 p-4 flex gap-4 text-left shadow-xs rounded-none"
                >
                  <div className="p-2 bg-orange-50 border border-orange-100 text-[#FF6600] shrink-0 self-center rounded-none flex items-center justify-center">
                    {mod.icon}
                  </div>
                  <div className="space-y-1">
                    <h3 className="text-xs font-extrabold font-display uppercase tracking-widest text-gray-900 leading-none">
                      {mod.name}
                    </h3>
                    <p className="text-[10.5px] text-gray-500 font-sans leading-relaxed font-semibold">
                      {mod.desc}
                    </p>
                  </div>
                </div>
              ))}
            </div>
          ) : (
            <>
              {/* Tablet: 3-col grid (hidden on lg+) */}
              <div className="w-full grid grid-cols-2 sm:grid-cols-3 lg:hidden gap-3 px-4 z-30 mt-6">
                {modules.map((mod) => (
                  <div
                    key={mod.id}
                    className="bg-white border border-gray-200 p-3 flex flex-col gap-2 text-left shadow-xs rounded-none hover:border-[#FF6600] transition-colors"
                  >
                    <div className="p-1.5 bg-orange-50 border border-orange-100 text-[#FF6600] self-start rounded-none">
                      {mod.icon}
                    </div>
                    <h3 className="text-[10px] font-extrabold font-display uppercase tracking-widest text-gray-900 leading-tight">
                      {mod.name}
                    </h3>
                    <p className="text-[9px] text-gray-500 font-sans leading-relaxed">
                      {mod.desc}
                    </p>
                  </div>
                ))}
              </div>

              {/* Desktop: space orbit with absolute positioned modules (lg+) */}
              <div className="w-full hidden lg:flex lg:absolute lg:inset-0 lg:p-0">
                {modules.map((mod) => {
                  const isSelected = selectedModule === mod.id;
                  return (
                    <div
                      key={mod.id}
                      className="relative lg:absolute flex flex-col items-center transition-all duration-300 z-30"
                      style={{
                        top: mod.top,
                        left: mod.left
                      }}
                    >
                      <button
                        onMouseEnter={() => setSelectedModule(mod.id)}
                        onMouseLeave={() => setSelectedModule(null)}
                        className={`p-3 border flex items-center gap-2.5 transition-all duration-200 relative cursor-pointer rounded-none ${
                          isSelected
                            ? "bg-white border-[#FF6600] text-gray-900 scale-105 shadow-sm"
                            : "bg-white border-gray-200 text-gray-500 hover:border-gray-300 hover:text-gray-800 shadow-xs"
                        }`}
                        id={`finale-module-node-${mod.id}`}
                      >
                        <div className={`p-1.5 rounded-none ${isSelected ? "bg-orange-50/60" : "bg-gray-50"}`}>
                          {mod.icon}
                        </div>
                        <span className="text-[10.5px] font-extrabold font-display uppercase tracking-widest leading-none">
                          {mod.name}
                        </span>
                      </button>

                      {isSelected && (
                        <AnimatePresence>
                          <motion.div 
                            key={`popup-${mod.id}`}
                            initial={{ opacity: 0, y: 5 }}
                            animate={{ opacity: 1, y: 0 }}
                            className="absolute top-14 bg-white text-left border border-gray-200 p-3 shadow-md max-w-[180px] text-[10px] text-gray-500 leading-normal font-sans z-50 rounded-none"
                          >
                            {mod.desc}
                          </motion.div>
                        </AnimatePresence>
                      )}
                    </div>
                  );
                })}
              </div>
            </>
          )}

        </div>
      </div>
    </div>
  );
}
