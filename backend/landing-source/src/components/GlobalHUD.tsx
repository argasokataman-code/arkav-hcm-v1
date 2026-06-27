import React from "react";
import { 
  ChevronLeft, 
  ChevronRight, 
  ArrowRight, 
  Sparkles
} from "lucide-react";
import { motion } from "motion/react";
import arkavLogo from "../assets/arkav-logo.png";

interface GlobalHUDProps {
  currentSlide: number;
  totalSlides: number;
  slideTitles: string[];
  onNavigate: (index: number) => void;
  onNext: () => void;
  onPrev: () => void;
  isMobile?: boolean;
  loginUrl?: string;
}

export default function GlobalHUD({
  currentSlide,
  totalSlides,
  slideTitles,
  onNavigate,
  onNext,
  onPrev,
  isMobile = false,
  loginUrl = '/login'
}: GlobalHUDProps) {
  // Translate system names for visual luxury
  const getSubLabel = (index: number) => {
    const subLabels = [
      "Introduction",
      "Real-time GPS Tracking",
      "Workflow Network",
      "Secure Automated Payroll",
      "Role-Based Portals",
      "Command Center Analytics",
      "Flexible Pricing",
      "Pusat Bantuan",
      "Testimonial & Statistik",
      "Keamanan & Kepatuhan",
      "Cara Mulai",
      "Demo Produk",
      "Unified Ecosystem"
    ];
    return subLabels[index] || "";
  };

  return (
    <>
      {/* Top Header Panel - Clean Corporate Navbar */}
      <header className="fixed top-0 left-0 right-0 z-50 h-16 px-4 md:px-16 flex items-center justify-between pointer-events-auto bg-[#FAFAF8]/90 backdrop-blur-md border-b border-gray-200/40 transition-all duration-300 select-none">
        {/* Left: Brand Logo and Title */}
        <div 
          onClick={() => onNavigate(0)} 
          className="flex items-center gap-1.5 sm:gap-2.5 cursor-pointer group"
          id="hud-logo-container"
        >
          <div className="relative w-8 h-8 flex items-center justify-center transition-transform duration-300 group-hover:scale-105">
            <img 
              src={arkavLogo} 
              alt="Arkav HCM" 
              className="w-full h-full object-contain"
            />
          </div>
          <div className="flex items-baseline">
            <span className="font-display font-black text-lg tracking-tight text-[#1A1D24] uppercase">
              ARKAV
            </span>
            <span className="text-[10px] text-[#FF6600] font-mono font-bold tracking-wider ml-1">
              HCM
            </span>
          </div>
        </div>

        {/* Center: Sleek Professional Nav Menu (Synchronized 1:1 with modular sections) */}
        {!isMobile && (
          <nav className="hidden lg:flex items-center gap-4">
            {[
              { name: "Beranda", index: 0 },
              { name: "Absensi", index: 1 },
              { name: "Cuti", index: 2 },
              { name: "Payroll", index: 3 },
              { name: "Employee", index: 4 },
              { name: "Analytics", index: 5 },
              { name: "Paket", index: 6 },
              { name: "Q&A", index: 7 },
              { name: "Testimoni", index: 8 },
              { name: "Kepatuhan", index: 9 },
              { name: "Cara Pakai", index: 10 },
              { name: "Demo", index: 11 }
            ].map((item) => {
              const isActive = currentSlide === item.index;
              return (
                <button 
                  key={item.index}
                  onClick={() => onNavigate(item.index)}
                  className={`text-xs font-semibold tracking-wide transition-all relative py-1.5 px-3 hover:text-[#FF6600] cursor-pointer rounded-none ${
                    isActive 
                      ? "text-[#FF6600] bg-orange-50/60" 
                      : "text-gray-600 hover:bg-gray-100/50"
                  }`}
                  id={`nav-${item.name.toLowerCase()}`}
                >
                  {item.name}
                  {isActive && (
                    <motion.div 
                      layoutId="activeNavUnderline" 
                      className="absolute bottom-0 left-2 right-2 h-0.5 bg-[#FF6600] rounded-none" 
                    />
                  )}
                </button>
              );
            })}
          </nav>
        )}

        {/* Right: Login and CTA Area */}
        <div className="flex items-center gap-3 md:gap-6">
          <button 
            onClick={() => { window.location.href = loginUrl; }}
            className="text-xs sm:text-sm font-semibold text-gray-500 hover:text-gray-900 transition-colors cursor-pointer"
            id="hud-login-btn"
          >
            Login
          </button>
          
          <button
            onClick={() => onNavigate(12)} // Jumps to client-closing grand finale screen
            className="px-2 py-1.5 sm:px-3.5 sm:py-2.2 rounded-none bg-[#FF6600] hover:bg-orange-600 text-white text-xs font-semibold tracking-wide transition-all duration-200 active:scale-95 cursor-pointer flex items-center gap-1.5"
            id="hud-cta-start"
          >
            <span className="hidden sm:inline">Mulai Sekarang</span>
            <span className="sm:hidden">Mulai</span>
            <ArrowRight className="w-3.5 h-3.5" />
          </button>
        </div>
      </header>

      {/* Side-floating Navigation Buttons (Crisp Light Borders - Desktop only) */}
      {!isMobile && (
        <>
          <div className="fixed inset-y-0 left-0 w-16 md:w-24 z-40 flex items-center justify-start pl-4 pointer-events-none">
            {currentSlide > 0 && (
              <motion.button
                aria-label="Previous scene"
                initial={{ opacity: 0, x: -10 }}
                animate={{ opacity: 1, x: 0 }}
                onClick={onPrev}
                className="pointer-events-auto w-12 h-12 rounded-none bg-white border border-gray-200/80 flex items-center justify-center text-gray-500 hover:text-[#FF6600] hover:border-[#FF6600]/40 transition-all hover:scale-110 active:scale-95 group focus:outline-none cursor-pointer shadow-sm"
                id="side-prev-button"
              >
                <ChevronLeft className="w-6 h-6 transition-transform duration-300 group-hover:-translate-x-0.5" />
              </motion.button>
            )}
          </div>

          <div className="fixed inset-y-0 right-0 w-16 md:w-24 z-40 flex items-center justify-end pr-4 pointer-events-none">
            {currentSlide < totalSlides - 1 && (
              <motion.button
                aria-label="Next scene"
                initial={{ opacity: 0, x: 10 }}
                animate={{ opacity: 1, x: 0 }}
                onClick={onNext}
                className="pointer-events-auto w-12 h-12 rounded-none bg-white border border-gray-200/80 flex items-center justify-center text-gray-500 hover:text-[#FF6600] hover:border-[#FF6600]/40 transition-all hover:scale-110 active:scale-95 group focus:outline-none cursor-pointer shadow-sm"
                id="side-next-button"
              >
                <ChevronRight className="w-6 h-6 transition-transform duration-300 group-hover:translate-x-0.5" />
              </motion.button>
            )}
          </div>
        </>
      )}

      {/* Floating Interactive Guide HUD (Bottom Right - Desktop only) */}
      {!isMobile && (
        <div className="fixed bottom-24 right-6 md:right-12 z-50 pointer-events-auto hidden md:block">
          <div className="bg-white border border-gray-200/80 text-gray-500 px-4 py-2.5 rounded-none flex items-center gap-2.5 text-[10px] font-mono shadow-sm">
            <span className="flex h-2 w-2 relative">
              <span className="animate-ping absolute inline-flex h-full w-full rounded-none bg-[#FF6600] opacity-75"></span>
              <span className="relative inline-flex rounded-none h-2 w-2 bg-[#FF6600]"></span>
            </span>
            <span className="font-semibold text-gray-700 uppercase tracking-wider">NAVIGASI:</span>
            <span>MOUSE WHEEL</span>
            <span className="text-gray-300">•</span>
            <span>◀ ▶ ARROWS</span>
            <span className="text-gray-300">•</span>
            <span>SWIPE</span>
          </div>
        </div>
      )}

      {/* Bottom Cinematic HUD - Footer progress gauge */}
      <footer className="fixed bottom-0 left-0 right-0 z-50 h-20 px-4 md:px-12 flex items-center justify-between pointer-events-auto bg-[#FAFAF8] md:bg-gradient-to-t md:from-[#FAFAF8] md:via-[#FAFAF8]/95 p-3 border-t border-gray-200/20 md:border-t-0">
        {/* Slide Counter Indicator */}
        <div className="flex items-center gap-3">
          <div className="text-xs font-mono font-bold text-[#FF6600] bg-[#FF6600]/5 px-2.5 py-1 rounded-none border border-[#FF6600]/15 tracking-wider">
            SCENE 0{currentSlide + 1}
          </div>
          <div className="flex flex-col text-left">
            <p className="text-xs font-display font-extrabold tracking-wide text-gray-900 uppercase">
              {slideTitles[currentSlide]}
            </p>
            <p className="text-[10px] text-gray-400 font-mono uppercase tracking-widest hidden sm:block">
              {getSubLabel(currentSlide)}
            </p>
          </div>
        </div>

        {/* Horizontal Seamless Progress Line segments (Desktop only) */}
        {!isMobile && (
          <div className="flex-1 max-w-xl mx-8 hidden sm:flex flex-col gap-2">
            <div className="w-full flex justify-between text-[9px] font-mono text-gray-400">
              <span>01 BERANDA</span>
              <span>04 PAYROLL</span>
              <span>12 SELESAI</span>
            </div>
            <div className="grid grid-cols-8 gap-1.5 w-full h-1.5 bg-gray-200/80 rounded-none overflow-hidden">
              {Array.from({ length: totalSlides }).map((_, idx) => {
                const isFilled = idx <= currentSlide;
                return (
                  <div
                    key={idx}
                    onClick={() => onNavigate(idx)}
                    className={`h-full rounded-none cursor-pointer transition-all duration-200 relative ${
                      isFilled 
                        ? "bg-[#FF6600]" 
                        : "bg-gray-200 hover:bg-gray-300"
                    }`}
                    title={slideTitles[idx]}
                    id={`bottom-progress-seg-${idx}`}
                  />
                );
              })}
            </div>
          </div>
        )}

        {/* Quick status information */}
        <div className="flex items-center gap-2 text-right">
          <div className="hidden md:flex flex-col">
            <div className="flex items-center gap-1.5 justify-end">
              <Sparkles className="w-3 h-3 text-[#FF6600]" />
              <span className="text-[10px] text-gray-500 font-mono tracking-wider font-semibold uppercase">SECURE CLOUD OPERATED</span>
            </div>
            <span className="text-[9px] text-gray-400 font-mono">ISO 27001 SECURED</span>
          </div>
          
          <div className="w-7 h-7 rounded-none bg-emerald-500/10 border border-emerald-500/25 flex items-center justify-center" title="Sistem Aktif 100%">
            <span className="block h-2 w-2 rounded-none bg-emerald-500 animate-pulse" />
          </div>
        </div>
      </footer>
    </>
  );
}
