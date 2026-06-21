import React, { useState } from "react";
import { motion, AnimatePresence } from "motion/react";
import { 
  Calendar, 
  Clock, 
  CheckCircle, 
  Info
} from "lucide-react";

export default function LeaveScene() {
  const [activeStep, setActiveStep] = useState(0);

  const steps = [
    {
      id: 0,
      role: "Pegawai",
      action: "Mengajukan Permohonan",
      desc: "Karyawan mengajukan permohonan cuti melalui sistem. Pengajuan langsung tercatat dan sisa kuota terpantau.",
      bubble: "Izin Cuti - 3 Hari",
      status: "Submitted",
      statusColor: "text-blue-700 bg-blue-50 border-blue-100",
      quotaText: "Sisa Kuota: 12 hari"
    },
    {
      id: 1,
      role: "Admin/HR",
      action: "Review & Verifikasi",
      desc: "Admin memverifikasi pengajuan cuti. Sistem secara otomatis memperbarui sisa kuota cuti karyawan.",
      bubble: "Pengajuan Diverifikasi",
      status: "Approved",
      statusColor: "text-[#FF6600] bg-orange-50 border-orange-100",
      quotaText: "Sisa Kuota: 12 → 9 Hari"
    }
  ];

  return (
    <div className="w-full h-full min-h-[90vh] flex flex-col lg:flex-row items-center justify-center px-4 md:px-16 lg:px-24 py-16 gap-12 relative select-none tech-grid">
      {/* Background World Glow Layer (Approval Flow Network - Soft Warm Ivory) */}
      <div className="absolute inset-0 overflow-hidden pointer-events-none">
        <div className="absolute bottom-1/4 left-1/3 w-[450px] h-[450px] bg-orange-50/20 blur-[140px] rounded-none" />
        <div className="absolute top-1/4 right-1/4 w-[400px] h-[400px] bg-orange-100/10 blur-[120px] rounded-none" />
      </div>

      {/* LEFT COLUMN: Narrative details */}
      <motion.div 
        initial={{ opacity: 0, y: 30 }}
        whileInView={{ opacity: 1, y: 0 }}
        viewport={{ once: true }}
        transition={{ duration: 0.8 }}
        className="w-full lg:w-1/2 text-left space-y-6 z-10"
        id="leave-content-left"
      >
        <div className="inline-flex items-center gap-1.5 px-3 py-1 rounded-none bg-orange-50 border border-orange-100 text-[#FF6600] text-xs font-semibold tracking-wider font-mono">
          <Calendar className="w-3.5 h-3.5" />
          <span>LEAVE & APPROVAL WORKFLOWS</span>
        </div>

        <h2 className="font-display font-extrabold text-3xl sm:text-4xl lg:text-5xl text-gray-950 leading-[1.08] tracking-tight">
          Permohonan Cuti Tidak <br />
          <span className="text-[#FF6600]">
            Lagi Hilang di Grup Chat.
          </span>
        </h2>

        <p className="text-sm sm:text-base text-gray-500 max-w-lg font-sans leading-relaxed">
          Sistem manajemen cuti digital di Arkav memproses setiap pengajuan secara terpusat. Saldo jatah cuti dapat dipantau langsung oleh karyawan dan admin dalam satu tampilan.
        </p>

        {/* Dynamic selector interface */}
        <div className="flex flex-col gap-2 pt-3">
          <span className="text-[10px] font-mono font-bold text-gray-400 uppercase tracking-widest">PILIH TAHAPAN PIPELINE:</span>
          <div className="grid grid-cols-2 sm:grid-cols-4 gap-2">
            {steps.map((st, i) => (
              <button
                key={i}
                onClick={() => setActiveStep(i)}
                className={`py-2 px-1 text-[10px] font-bold font-mono tracking-wide border transition-all cursor-pointer active:scale-95 rounded-none ${
                  activeStep === i 
                    ? "bg-[#FF6600]/10 border-[#FF6600] text-[#FF6600] shadow-sm" 
                    : "bg-white border-gray-200 text-gray-500 hover:bg-gray-50 hover:text-[#FF6600]"
                }`}
                id={`leave-step-selector-${i}`}
              >
                0{i + 1} {st.role.split(" ")[0]}
              </button>
            ))}
          </div>
        </div>

        <div className="text-xs bg-white border border-gray-200 p-4 rounded-none flex items-start gap-3 shadow-xs">
          <Info className="w-4 h-4 text-[#FF6600] shrink-0 mt-0.5" />
          <p className="text-gray-500 leading-relaxed font-sans font-medium">
            Pilih atau navigasikan tombol tahapan di atas untuk mengaktifkan fokus visual sirkuit sistem di sebelah kanan dan melacak pergerakan data.
          </p>
        </div>
      </motion.div>

      {/* RIGHT COLUMN: The Interactive Flowchart circuitry (Elegant Light Frame - SQUARE) */}
      <div 
        className="w-full lg:w-1/2 flex items-center justify-center relative min-h-[360px] lg:min-h-[480px] z-10 mt-6 lg:mt-0"
        id="leave-flow-column"
      >
        <div className="w-full max-w-[450px] p-6 bg-white border border-gray-200 relative flex flex-col justify-between h-auto lg:h-[380px] min-h-[380px] overflow-hidden shadow-sm rounded-none">
          
          {/* Simulated circuit lines between nodes */}
          <div className="absolute top-[80px] left-12 right-12 h-0.5 bg-gray-100 z-0 hidden xs:block rounded-none">
            {/* Pulsing visual energy moving on active step */}
            <motion.div 
              animate={{ left: ["0%", "100%"] }}
              transition={{ repeat: Infinity, duration: 4, ease: "linear" }}
              className="absolute top-0 w-12 h-full bg-gradient-to-r from-transparent via-[#FF6600]/60 to-transparent"
            />
          </div>

          {/* Interactive Steps Circles Network - SQUARE nodes */}
          <div className="relative z-10 flex justify-between gap-1 xs:gap-2 border-b border-gray-100 pb-6">
            {steps.map((step, idx) => {
              const isActive = activeStep === idx;
              const isPast = idx < activeStep;
              return (
                <div 
                  key={step.id} 
                  className="flex flex-col items-center flex-1 cursor-pointer"
                  onClick={() => setActiveStep(idx)}
                >
                  <div 
                    className={`w-10 h-10 border flex items-center justify-center transition-all duration-500 relative rounded-none ${
                      isActive 
                        ? "bg-orange-50 border-2 border-[#FF6600] text-[#FF6600] scale-105 shadow-sm" 
                        : isPast 
                          ? "bg-emerald-50 border-2 border-emerald-500/50 text-emerald-700"
                          : "bg-gray-50 border border-gray-200 text-gray-400 hover:border-gray-400 hover:text-gray-800"
                    }`}
                  >
                    {isPast ? (
                      <CheckCircle className="w-4 h-4 text-emerald-600 animate-pulse" />
                    ) : (
                      <span className="text-xs font-mono font-bold">0{idx + 1}</span>
                    )}

                    {/* Miniature pulse indicator - SQUARE */}
                    {isActive && (
                      <span className="absolute -top-1 -right-1 flex h-2 w-2">
                        <span className="animate-ping absolute inline-flex h-full w-full bg-[#FF6600] opacity-75 rounded-none"></span>
                        <span className="relative inline-flex h-2 w-2 bg-[#FF6600] rounded-none"></span>
                      </span>
                    )}
                  </div>
                  
                  <span className={`text-[9px] font-bold mt-2 font-display uppercase tracking-wide truncate max-w-[80px] ${
                    isActive ? "text-[#FF6600]" : isPast ? "text-emerald-700" : "text-gray-400"
                  }`}>
                    {step.role.split(" ")[0]}
                  </span>
                </div>
              );
            })}
          </div>

          {/* Large display card showing focus steps details */}
          <div className="flex-1 flex flex-col justify-center py-4 relative z-10">
            <AnimatePresence mode="wait">
              <motion.div
                key={activeStep}
                initial={{ opacity: 0, x: 20 }}
                animate={{ opacity: 1, x: 0 }}
                exit={{ opacity: 0, x: -20 }}
                transition={{ duration: 0.3 }}
                className="space-y-4 text-left"
              >
                <div className="flex items-center justify-between">
                  <div>
                    <span className="text-[9px] font-mono text-[#FF6600] tracking-widest uppercase font-bold">AKTIVITAS ALUR KERJA</span>
                    <h4 className="text-sm font-extrabold text-gray-900 font-display mt-0.5">
                      {steps[activeStep].role} &rarr; {steps[activeStep].action}
                    </h4>
                  </div>
                  <span className={`text-[9px] font-mono font-bold px-2.5 py-0.5 border rounded-none ${steps[activeStep].statusColor}`}>
                    {steps[activeStep].status}
                  </span>
                </div>

                <p className="text-xs text-gray-500 leading-relaxed font-sans font-medium">
                  {steps[activeStep].desc}
                </p>

                {/* Internal sub-card reporting stats */}
                <div className="bg-gray-50 border border-gray-100 p-3 flex items-center justify-between rounded-none">
                  <div className="flex items-center gap-2">
                    <div className="w-2 h-2 bg-orange-500 rounded-none" />
                    <span className="text-[10px] text-gray-400 font-mono font-semibold">Informasi Terkait System:</span>
                  </div>
                  <span className="text-xs font-mono font-bold text-gray-800">
                    {steps[activeStep].quotaText}
                  </span>
                </div>
              </motion.div>
            </AnimatePresence>
          </div>

          {/* Miniature calendar check footer */}
          <div className="border-t border-gray-100 pt-3.5 flex items-center justify-between text-[10px] font-mono text-gray-400">
            <span className="flex items-center gap-1.5 uppercase tracking-wide font-bold">
              <Clock className="w-3.5 h-3.5 text-gray-400" /> Proses: <span className="text-[#FF6600] font-extrabold">Cepat & Terpusat</span>
            </span>
            <span className="text-emerald-600 font-bold">100% PAPERLESS</span>
          </div>
        </div>
      </div>
    </div>
  );
}
