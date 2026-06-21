import React, { useState, useEffect } from "react";
import { motion, AnimatePresence } from "motion/react";
import { 
  MapPin, 
  Clock, 
  Map, 
  CheckCircle2, 
  Fingerprint,
  Loader2
} from "lucide-react";

export default function AttendanceScene() {
  const [time, setTime] = useState("");
  const [punchState, setPunchState] = useState<"idle" | "verifying" | "success">("idle");

  // Update clock time on mock phone
  useEffect(() => {
    const formatTime = () => {
      const now = new Date();
      let hours = now.getHours().toString().padStart(2, "0");
      let minutes = now.getMinutes().toString().padStart(2, "0");
      let seconds = now.getSeconds().toString().padStart(2, "0");
      return `${hours}:${minutes}:${seconds}`;
    };
    setTime(formatTime());
    const interval = setInterval(() => setTime(formatTime()), 1000);
    return () => clearInterval(interval);
  }, []);

  const handlePunch = () => {
    setPunchState("verifying");
    setTimeout(() => {
      setPunchState("success");
    }, 1200);
  };

  const resetPunch = () => {
    setPunchState("idle");
  };

  return (
    <div className="w-full h-full min-h-[90vh] flex flex-col lg:flex-row items-center justify-center px-4 md:px-16 lg:px-24 py-16 gap-12 relative select-none tech-grid">
      {/* Background World Glow Layer */}
      <div className="absolute inset-0 overflow-hidden pointer-events-none">
        <div className="absolute top-1/2 left-3/4 -translate-x-1/2 -translate-y-1/2 w-[600px] h-[600px] rounded-none border border-[#FF6600]/3 flex items-center justify-center">
          <div className="w-[400px] h-[400px] rounded-none border border-[#FF6600]/3 flex items-center justify-center animate-ping" style={{ animationDuration: "3.5s" }} />
          <div className="w-[200px] h-[200px] rounded-none border border-orange-100/10" />
        </div>
        <div className="absolute top-1/4 left-1/4 w-[350px] h-[350px] rounded-none bg-gradient-to-r from-orange-100/10 to-transparent blur-[120px]" />
      </div>

      {/* LEFT COLUMN: Narrative details */}
      <motion.div 
        initial={{ opacity: 0, x: -40 }}
        whileInView={{ opacity: 1, x: 0 }}
        viewport={{ once: true }}
        transition={{ duration: 0.8 }}
        className="w-full lg:w-1/2 text-left space-y-6 z-10"
        id="attendance-content-left"
      >
        <div className="inline-flex items-center gap-1.5 px-3 py-1 rounded-none bg-orange-50 border border-orange-100 text-[#FF6600] text-xs font-semibold tracking-wider font-mono">
          <MapPin className="w-3.5 h-3.5" />
          <span>KEHADIRAN GPS PRESISI</span>
        </div>

        <h2 className="font-display font-extrabold text-3xl sm:text-4xl lg:text-5xl text-gray-950 leading-[1.08] tracking-tight">
          Datang, Pulang, Lembur. <br />
          <span className="text-[#FF6600]">
            Semua Tercatat Otomatis.
          </span>
        </h2>

        <p className="text-sm sm:text-base text-gray-500 max-w-lg font-sans leading-relaxed">
          Arkav mencatat koordinat GPS saat clock in/out dan menerima unggahan foto selfie dari ponsel karyawan untuk verifikasi kehadiran. Rekap kehadiran tersaji secara instan tanpa perlu rekap manual.
        </p>

        {/* Spec table */}
        <div className="grid grid-cols-2 gap-4 max-w-md pt-2">
          <div className="border-l-2 border-[#FF6600] pl-3.5">
            <span className="text-[10px] font-mono font-bold text-gray-400 uppercase">Pencatatan Lokasi</span>
            <p className="text-lg sm:text-xl font-bold font-display text-gray-900">Koordinat GPS</p>
          </div>
          <div className="border-l-2 border-emerald-500 pl-3.5">
            <span className="text-[10px] font-mono font-bold text-gray-400 uppercase">Verifikasi</span>
            <p className="text-lg sm:text-xl font-bold font-display text-gray-900">Foto Selfie</p>
          </div>
        </div>

        {/* Quick hint */}
        <div className="text-xs text-gray-400 flex items-center gap-2 font-semibold font-mono">
          <span className="w-1.5 h-1.5 bg-[#FF6600] inline-block animate-pulse" />
          <span>Dashboard presensi real-time siap digunakan.</span>
        </div>
      </motion.div>

      {/* RIGHT COLUMN: Desktop mockup — matches runtime attendance UI */}
      <div 
        className="w-full lg:w-1/2 flex items-center justify-center relative z-10 mt-6 lg:mt-0"
        id="attendance-mock-column"
      >
        <div className="w-full max-w-[580px] bg-white border border-gray-200 p-4 flex flex-col space-y-3 shadow-sm rounded-none">
          
          {/* Header: Avatar + Profile + System Status */}
          <div className="flex items-center gap-3 pb-3 border-b border-gray-100">
            <div className="w-12 h-12 rounded-full bg-[#FF6600]/10 border-2 border-[#FF6600]/20 flex items-center justify-center text-[#FF6600] font-bold text-lg">
              AF
            </div>
            <div className="flex-1 min-w-0">
              <p className="text-sm font-extrabold text-gray-900">Ahmad Fauzi</p>
              <p className="text-[11px] text-gray-500 font-medium">UI Designer • Tech Division</p>
              <p className="text-[10px] text-gray-400 font-mono mt-0.5">
                {new Date().toLocaleDateString("id-ID", { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' })}
                {" • "}{time || "08:29"} WIB
              </p>
            </div>
            <span className="text-[9px] font-mono bg-emerald-50 text-emerald-700 px-2 py-0.5 border border-emerald-100 font-bold rounded-none">SYSTEM ACTIVE</span>
          </div>

          {/* Row: Status Card + Productivity */}
          <div className="flex gap-3">
            {/* Status Card */}
            <div className="flex-1 bg-gray-50 border border-gray-100 p-3 rounded-none flex flex-col items-center justify-center gap-1.5">
              <div className="w-14 h-14 rounded-full bg-orange-50 border-2 border-dashed border-[#FF6600]/30 flex items-center justify-center">
                <Fingerprint className="w-6 h-6 text-[#FF6600]" />
              </div>
              <span className="text-[10px] font-mono font-bold text-gray-400 uppercase">Status hari ini</span>
              <p className="text-sm font-extrabold text-gray-900">Selamat pagi 👋</p>
              <span className="text-[9px] bg-[#FF6600]/5 text-[#FF6600] px-2 py-0.5 border border-[#FF6600]/10 font-bold font-mono rounded-none">Clock In • 08:29 WIB</span>
            </div>

            {/* Productivity + Punch Line */}
            <div className="flex-1 space-y-2">
              <div className="bg-gray-50 border border-gray-100 p-3 rounded-none">
                <span className="text-[9px] font-mono text-gray-400 font-bold uppercase tracking-wider">Produktivitas</span>
                <p className="text-sm font-extrabold text-gray-900 mt-0.5">7.2 jam</p>
                <div className="w-full bg-gray-200 h-1 mt-1.5 rounded-none overflow-hidden">
                  <div className="bg-[#FF6600] h-full w-[90%] rounded-none" />
                </div>
              </div>
              <div className="bg-gray-50 border border-gray-100 p-2.5 rounded-none flex items-center justify-between">
                <span className="text-[10px] font-mono font-bold text-gray-500 uppercase">Absensi</span>
                <span className="text-[10px] text-gray-800 font-bold">08:29 → 17:01</span>
              </div>
            </div>
          </div>

          {/* Stats Cards: 4 columns */}
          <div className="grid grid-cols-4 gap-2">
            <div className="bg-white border border-gray-200 p-2.5 rounded-none">
              <span className="text-[8px] font-mono font-bold text-gray-400 uppercase">Hari Ini</span>
              <p className="text-sm font-extrabold text-gray-900 mt-0.5">7.2</p>
              <span className="text-[8px] text-gray-400 font-mono">/ 8 jam</span>
            </div>
            <div className="bg-white border border-gray-200 p-2.5 rounded-none">
              <span className="text-[8px] font-mono font-bold text-gray-400 uppercase">Minggu Ini</span>
              <p className="text-sm font-extrabold text-gray-900 mt-0.5">38.5</p>
              <span className="text-[8px] text-gray-400 font-mono">/ 40 jam</span>
            </div>
            <div className="bg-white border border-gray-200 p-2.5 rounded-none">
              <span className="text-[8px] font-mono font-bold text-gray-400 uppercase">Bulan Ini</span>
              <p className="text-sm font-extrabold text-gray-900 mt-0.5">142</p>
              <span className="text-[8px] text-gray-400 font-mono">/ — jam</span>
            </div>
            <div className="bg-white border border-gray-200 p-2.5 rounded-none">
              <span className="text-[8px] font-mono font-bold text-gray-400 uppercase">Lembur</span>
              <p className="text-sm font-extrabold text-[#FF6600] mt-0.5">12</p>
              <span className="text-[8px] text-gray-400 font-mono">/ — jam</span>
            </div>
          </div>

          {/* Summary: Ringkasan hari ini */}
          <div className="bg-gray-50 border border-gray-100 p-3 rounded-none">
            <p className="text-[9px] font-mono font-bold text-gray-400 uppercase tracking-wider mb-2">Ringkasan hari ini</p>
            <div className="grid grid-cols-4 gap-2">
              <div className="bg-white border border-gray-200 p-2 rounded-none text-center">
                <span className="text-[8px] text-gray-400 font-mono font-bold block">Total</span>
                <span className="text-xs font-extrabold text-gray-900">7.2 jam</span>
              </div>
              <div className="bg-white border border-gray-200 p-2 rounded-none text-center">
                <span className="text-[8px] text-gray-400 font-mono font-bold block">Produktif</span>
                <span className="text-xs font-extrabold text-emerald-600">6.5 jam</span>
              </div>
              <div className="bg-white border border-gray-200 p-2 rounded-none text-center">
                <span className="text-[8px] text-gray-400 font-mono font-bold block">Istirahat</span>
                <span className="text-xs font-extrabold text-amber-600">0.7 jam</span>
              </div>
              <div className="bg-white border border-gray-200 p-2 rounded-none text-center">
                <span className="text-[8px] text-gray-400 font-mono font-bold block">Lembur</span>
                <span className="text-xs font-extrabold text-blue-600">—</span>
              </div>
            </div>
          </div>

          {/* Location + Action Buttons */}
          <div className="flex gap-2">
            <div className="flex-1 bg-gray-50 border border-gray-100 p-2 rounded-none flex items-center justify-center min-h-[70px] relative overflow-hidden">
              <div className="absolute inset-0 tech-grid opacity-5" />
              <div className="relative flex flex-col items-center gap-1">
                <span className="text-[8px] font-mono font-bold text-gray-400 uppercase">Lokasi</span>
                <span className="text-[9px] font-mono text-gray-700 font-bold">Sudirman HQ • -6.2088, 106.8456</span>
                <span className="text-[7px] font-mono text-emerald-600">GPS • Akurat</span>
              </div>
            </div>
            <div className="flex flex-col gap-1.5 justify-center">
              <AnimatePresence mode="wait">
                {punchState === "idle" && (
                  <motion.button
                    key="punch-btn"
                    initial={{ opacity: 0 }}
                    animate={{ opacity: 1 }}
                    exit={{ opacity: 0 }}
                    onClick={handlePunch}
                    className="text-[9px] bg-gray-50 border border-gray-200 px-3 py-1.5 font-bold font-mono rounded-none hover:bg-gray-100 transition-colors cursor-pointer"
                  >
                    <span className="text-gray-600">Punch In</span>
                  </motion.button>
                )}
                {punchState === "verifying" && (
                  <motion.div
                    key="punch-verifying"
                    initial={{ opacity: 0, scale: 0.95 }}
                    animate={{ opacity: 1, scale: 1 }}
                    exit={{ opacity: 0 }}
                    className="text-[9px] bg-orange-50 border border-orange-200 px-3 py-1.5 font-bold font-mono rounded-none flex items-center gap-1.5 text-[#FF6600]"
                  >
                    <Loader2 className="w-3 h-3 animate-spin" />
                    <span>Memproses</span>
                  </motion.div>
                )}
                {punchState === "success" && (
                  <motion.button
                    key="punch-success"
                    initial={{ opacity: 0, scale: 0.95 }}
                    animate={{ opacity: 1, scale: 1 }}
                    exit={{ opacity: 0 }}
                    onClick={resetPunch}
                    className="text-[9px] bg-emerald-50 border border-emerald-200 px-3 py-1.5 font-bold font-mono rounded-none hover:bg-emerald-100 transition-colors cursor-pointer text-emerald-700"
                  >
                    ✓ Sukses
                  </motion.button>
                )}
              </AnimatePresence>
              <button className="text-[9px] bg-white border border-gray-200 px-3 py-1.5 font-bold font-mono rounded-none hover:bg-gray-100 transition-colors text-gray-500 cursor-pointer">
                Selfie
              </button>
              <button className="text-[9px] bg-white border border-gray-200 px-3 py-1.5 font-bold font-mono rounded-none hover:bg-gray-100 transition-colors text-gray-500 cursor-pointer">
                Istirahat
              </button>
            </div>
          </div>

          {/* Floating GPS indicator */}
          <div className="absolute -bottom-3 -right-3 bg-white p-2.5 border border-gray-200/80 shadow-md max-w-[130px] text-left rounded-none hidden sm:block">
            <span className="text-[7px] font-mono text-emerald-600 tracking-wider font-bold">LIVE</span>
            <p className="text-[9px] font-bold text-gray-900 mt-0.5">GPS Aktif</p>
            <p className="text-[7px] text-gray-500 leading-normal">Koordinat: -6.2088, 106.8456</p>
          </div>
        </div>
      </div>
    </div>
  );
}
