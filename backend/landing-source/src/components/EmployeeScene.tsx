import React, { useState } from "react";
import { motion, AnimatePresence } from "motion/react";
import { 
  Laptop, 
  Smartphone, 
  Tablet, 
  Users, 
  Tv,
  Check
} from "lucide-react";

export default function EmployeeScene() {
  const [selectedRole, setSelectedRole] = useState<"employee" | "manager" | "finance" | "owner">("employee");

  const rolesData = {
    employee: {
      title: "Pegawai Lapangan & Staf",
      device: "Mockup Smartphone Mobile",
      icon: <Smartphone className="w-5 h-5 text-amber-600" />,
      features: [
        "Akses cepat untuk Clock In / Clock Out GPS mandiri",
        "Pengajuan cuti & sakit instan paperless via mobile",
        "E-Payslip bulanan terenkripsi rahasia dan aman",
        "Informasi detail sisa kuota cuti tahunan real-time"
      ],
      mockContent: (
        <div className="space-y-3.5 text-left h-full flex flex-col justify-between">
          <div className="flex items-center justify-between border-b border-gray-200/60 pb-2">
            <span className="text-[9px] font-mono text-gray-400 font-bold uppercase">Aplikasi Mobile Arkav</span>
            <span className="text-[8px] bg-orange-50 text-[#FF6600] border border-orange-100 px-1.5 py-0.2 rounded-none font-bold font-mono">AKTIF</span>
          </div>
          
          <div className="space-y-2 flex-grow flex flex-col justify-center">
            <div className="bg-white p-2.5 rounded-none border border-gray-200 text-xs shadow-xs">
              <p className="text-[9px] text-gray-400 font-bold font-mono uppercase">STATUS PRESENSI HARI INI</p>
              <p className="font-extrabold text-gray-900 mt-0.5">Sudah Clock In</p>
              <div className="w-full bg-emerald-50 text-emerald-700 text-[9px] border border-emerald-100 px-2 py-0.5 rounded-none mt-1.5 flex items-center justify-between font-mono font-bold">
                <span>08:28 WIB (HQ)</span>
                <span>ON-TIME</span>
              </div>
            </div>

            <div className="bg-white p-2.5 rounded-none border border-gray-200 text-xs flex justify-between items-center shadow-xs">
              <div>
                <p className="text-[9px] text-gray-400 font-bold font-mono uppercase">SLIP GAJI TERBARU</p>
                <p className="font-bold text-gray-800 mt-0.5 text-[11px]">Bulan Mei 2026</p>
              </div>
              <button className="text-[9px] bg-white border border-gray-200 px-2 py-1 rounded-none text-gray-700 hover:bg-gray-50 font-mono font-bold">
                BUKA
              </button>
            </div>
          </div>

          <div className="bg-[#FF6600] text-center py-2 rounded-none text-[10px] text-white font-extrabold font-sans uppercase tracking-wider shadow-xs hover:bg-orange-600 transition-colors">
            AJUKAN CUTI HARIAN
          </div>
        </div>
      )
    },
    manager: {
      title: "Line Managers & Supervisors",
      device: "Tablet Workspace Component",
      icon: <Tablet className="w-5 h-5 text-purple-600" />,
      features: [
        "Dashboard personil tim & rekap harian kehadiran",
        "Pantau pengajuan cuti tim dalam satu tampilan",
        "Kelola log shift kerja, lembur, dan roster tim harian",
        "Notifikasi instan jika personil berhalangan hadir"
      ],
      mockContent: (
        <div className="space-y-4 text-left h-full flex flex-col justify-between">
          <div className="flex items-center justify-between border-b border-gray-200/60 pb-2">
            <div className="flex items-center gap-1.5">
              <span className="text-[9px] font-mono text-gray-400 font-bold uppercase">Manager Control Hub</span>
              <span className="text-[8px] bg-purple-50 text-purple-700 px-1.5 py-0.2 rounded-none font-mono font-bold">TIM: 8 STAF</span>
            </div>
            <span className="text-[9.5px] font-mono text-red-600 font-bold">1 PERLU TINDAKAN</span>
          </div>

          {/* Table List of team members pending leaves inside manager UI */}
          <div className="space-y-2 flex-grow flex flex-col justify-center">
            <span className="text-[9px] font-mono text-gray-400 font-bold uppercase">PENGAJUAN CUTI (WAITING):</span>
            
            <div className="bg-white p-2.5 rounded-none border border-gray-200 flex items-center justify-between shadow-xs">
              <div className="max-w-[140px]">
                <p className="font-extrabold text-gray-900 text-[11px] truncate">Budi Saputra (Designer)</p>
                <p className="text-[9px] text-[#FF6600] font-bold font-mono">Cuti Sakit • 2 Hari</p>
              </div>
              <div className="flex gap-1 shrink-0">
                <button className="px-2 py-1 bg-red-50 hover:bg-red-100 text-red-600 border border-red-100 rounded-none text-[9px] font-bold">TOLAK</button>
                <button className="px-3 py-1 bg-[#FF6600] hover:bg-orange-600 text-white rounded-none text-[9px] font-bold">SETUJU</button>
              </div>
            </div>

            <div className="flex items-center justify-between text-[10px] text-gray-500 bg-gray-50 p-2 rounded-none border border-gray-200">
              <span className="truncate">Kehadiran Tim Hari Ini:</span>
              <span className="font-extrabold text-gray-900">100% Terdata</span>
            </div>
          </div>

          <p className="text-[8.5px] text-gray-400 font-mono text-center font-bold">NOTIFIKASI OUTBOX TIM TERINTEGRASI</p>
        </div>
      )
    },
    finance: {
      title: "HR, Admin & Finance Specialist",
      device: "Laptop Desktop Console",
      icon: <Laptop className="w-5 h-5 text-cyan-600" />,
      features: [
        "Konsolidasi payroll bulanan menyeluruh multi-cabang",
        "Auto-generate slip gaji & pelaporan pajak PPh 21",
        "Slip gaji digital terkirim otomatis ke karyawan",
        "Atur grading kelompok gaji & kompensasi terstrukur"
      ],
      mockContent: (
        <div className="space-y-3.5 text-left h-full flex flex-col justify-between">
          <div className="flex items-center justify-between border-b border-gray-200/60 pb-2">
            <span className="text-[9px] font-mono text-gray-400 font-bold uppercase">Console Keuangan Arkav</span>
            <span className="text-[9px] font-mono font-bold text-emerald-600">ENCRYPTION SECURE PORT</span>
          </div>

          <div className="space-y-2.5 flex-grow flex flex-col justify-center text-xs">
            {/* Simulation of a ledger balance */}
            <div className="p-2.5 bg-white border border-gray-200/80 rounded-none shadow-xs">
              <span className="text-[9px] font-mono text-gray-400 font-bold uppercase block">Periode Pembayaran Pajak</span>
              <p className="font-bold text-gray-900 mt-0.5 text-xs">Masa Mei 2026 (PPh 21 Selesai)</p>
              <div className="flex justify-between items-center mt-1">
                <span className="text-[9px] text-gray-500 font-mono">Disampaikan ke SPT:</span>
                <span className="text-[9px] text-emerald-600 font-extrabold font-mono font-semibold">SUKSES GENERATE ✅</span>
              </div>
            </div>

            <div className="flex gap-1.5">
              <div className="flex-1 p-2 bg-gray-50 rounded-none border border-gray-200">
                <span className="text-[8px] text-gray-400 font-bold font-mono block">BANK EXPORT</span>
                <p className="text-[10px] font-extrabold text-gray-800 mt-0.5">BCA Transfer</p>
              </div>
              <div className="flex-1 p-2 bg-gray-50 rounded-none border border-gray-200">
                <span className="text-[8px] text-gray-400 font-bold font-mono block">THR ENGINE</span>
                <p className="text-[10px] font-extrabold text-[#FF6600] mt-0.5">Proporsional OK</p>
              </div>
            </div>
          </div>

          <div className="text-center text-[9px] bg-orange-50 text-[#FF6600] border border-orange-100 py-1.5 rounded-none font-mono font-bold uppercase">
            PENGATURAN STRUKTUR KOMPENSASI
          </div>
        </div>
      )
    },
    owner: {
      title: "Business Owner, CEO & Executive",
      device: "Premium Dashboard Monitoring",
      icon: <Tv className="w-5 h-5 text-emerald-600" />,
      features: [
        "Metrik kinerja & produktivitas organisasi makro",
        "Visualisasi pengeluaran bulanan headcount korporasi",
        "Ringkasan data karyawan & status kepegawaian",
        "Akses ringkasan eksekutif strategis ramah mobile"
      ],
      mockContent: (
        <div className="space-y-4 text-left h-full flex flex-col justify-between">
          <div className="flex items-center justify-between border-b border-gray-200/60 pb-2">
            <span className="text-[9px] font-mono text-gray-400 font-bold uppercase">Executive Command Matrix</span>
            <span className="text-[8px] bg-emerald-50 text-emerald-700 px-1.5 py-0.2 rounded-none font-mono font-bold">COMPREHENSIVE VIEW</span>
          </div>

          <div className="space-y-2 flex-grow flex flex-col justify-center">
            {/* Analytical gauge */}
            <div className="bg-white p-2.5 rounded-none border border-gray-200 shadow-xs">
              <div className="flex justify-between items-center text-[9px] font-mono text-gray-400 font-bold">
                <span>Rasio Kinerja Kepegawaian</span>
                <span className="text-emerald-600 font-bold font-mono">+18.5% Y-o-Y</span>
              </div>
              <div className="w-full bg-gray-100 h-1.5 rounded-none mt-1.5 overflow-hidden">
                <div className="bg-[#FF6600] h-full w-[88%] rounded-none" />
              </div>
              <p className="text-[9px] text-gray-500 mt-1 font-medium">Efektivitas operasional meningkat pasca integrasi Arkav</p>
            </div>

            <div className="bg-gray-50 p-2 rounded-none border border-gray-200 flex justify-between items-center text-[10px]">
              <span className="text-gray-500">Anggaran Headcount SDM:</span>
              <span className="font-extrabold text-gray-900 font-mono">Stabil & Optimal</span>
            </div>
          </div>

          <p className="text-[9px] text-[#FF6600] font-mono text-center font-bold uppercase tracking-wider">
            LAPORAN RINGKASAN MANAJEMEN
          </p>
        </div>
      )
    }
  };

  return (
    <div className="w-full h-full min-h-[90vh] flex flex-col lg:flex-row items-center justify-center px-4 md:px-16 lg:px-24 py-16 gap-12 relative select-none tech-grid">
      {/* Background World Glow Layer (Connected Device Universe - Clean warm white) */}
      <div className="absolute inset-0 overflow-hidden pointer-events-none">
        <div className="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[700px] h-[700px] rounded-none border border-gray-100 flex items-center justify-center">
          <div className="w-[500px] h-[500px] rounded-none border border-gray-200/50 flex items-center justify-center animate-spin" style={{ animationDuration: "35s" }}>
            <div className="absolute top-0 w-3 h-3 bg-[#FF6600]/20 rounded-none animate-pulse" />
            <div className="absolute right-0 w-3 h-3 bg-cyan-400/20 rounded-none" />
          </div>
        </div>
        <div className="absolute top-1/4 right-1/3 w-[300px] h-[300px] bg-gradient-to-r from-orange-100/10 to-transparent blur-[120px] rounded-none" />
      </div>

      {/* LEFT COLUMN: Narrative and Role Selector Buttons */}
      <motion.div 
        initial={{ opacity: 0, x: -40 }}
        whileInView={{ opacity: 1, x: 0 }}
        viewport={{ once: true }}
        transition={{ duration: 0.8 }}
        className="w-full lg:w-1/2 text-left space-y-6 z-10"
        id="employee-content-left"
      >
        <div className="inline-flex items-center gap-1.5 px-3 py-1 rounded-none bg-orange-50 border border-orange-100 text-[#FF6600] text-xs font-semibold tracking-wider font-mono">
          <Users className="w-3.5 h-3.5" />
          <span>PORTAL BERBASIS PERAN (RBAC)</span>
        </div>

        <h2 className="font-display font-extrabold text-3xl sm:text-4xl lg:text-5xl text-gray-950 leading-[1.08] tracking-tight">
          Setiap Orang Melihat Hal <br />
          <span className="text-[#FF6600]">
            yang Mereka Butuhkan.
          </span>
        </h2>

        <p className="text-sm sm:text-base text-gray-500 max-w-lg font-sans leading-relaxed">
          Arkav mengusung antarmuka cerdas adaptif yang disesuaikan murni bagi wewenang karyawan di organisasi. Kurangi distraksi manajemen, pastikan kerahasiaan & aksesibilitas terjaga penuh.
        </p>

        {/* Roles interactive triggers */}
        <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 max-w-md pt-2">
          {(["employee", "manager", "finance", "owner"] as const).map((role) => {
            const isSelected = selectedRole === role;
            const item = rolesData[role];
            return (
              <button
                key={role}
                onClick={() => setSelectedRole(role)}
                className={`p-3.5 text-left border flex items-center gap-3 transition-all duration-200 active:scale-95 cursor-pointer rounded-none ${
                  isSelected 
                    ? "bg-[#FF6600]/10 border-[#FF6600] shadow-sm text-gray-950 font-bold" 
                    : "bg-white border-gray-200 text-gray-500 hover:bg-gray-50 hover:border-gray-300"
                }`}
                id={`role-btn-${role}`}
              >
                <div className={`p-1.5 rounded-none ${isSelected ? "bg-white" : "bg-gray-50 border border-gray-200"}`}>
                  {item.icon}
                </div>
                <div className="leading-tight">
                  <p className="text-[11px] font-bold font-display uppercase tracking-wider">{role}</p>
                  <p className="text-[8.5px] text-gray-400 font-mono uppercase font-bold">PORTAL AKSES</p>
                </div>
              </button>
            );
          })}
        </div>
      </motion.div>

      {/* RIGHT COLUMN: Interactive Adaptive Device Viewer (Elegant White Frame - SQUARE) */}
      <div 
        className="w-full lg:w-1/2 flex items-center justify-center relative z-10 mt-6 lg:mt-0"
        id="employee-device-column"
      >
        <div className="w-full max-w-[390px] p-5 bg-white border border-gray-200 text-left relative flex flex-col justify-between h-auto lg:h-[410px] min-h-[410px] shadow-sm rounded-none">
          
          {/* Dynamic Details block */}
          <div className="space-y-4">
            <div className="flex items-center gap-2">
              <span className="p-2.5 bg-orange-50 border border-orange-100 text-[#FF6600] rounded-none">
                {rolesData[selectedRole].icon}
              </span>
              <div>
                <span className="text-[9px] font-mono text-gray-400 font-bold uppercase tracking-widest">SISTEM HAK AKSES PERAN:</span>
                <p className="text-sm font-extrabold font-display text-gray-900">{rolesData[selectedRole].title}</p>
              </div>
            </div>

            {/* List bullet features */}
            <div className="space-y-2 border-t border-gray-100 pt-4">
              {rolesData[selectedRole].features.map((feat, idx) => (
                <div key={idx} className="flex items-start gap-2 text-xs">
                  <span className="w-4 h-4 bg-emerald-50 border border-emerald-100 flex items-center justify-center text-emerald-700 text-[9px] shrink-0 font-bold mt-0.5 rounded-none">
                    <Check className="w-2.5 h-2.5 text-emerald-600" />
                  </span>
                  <span className="text-gray-500 font-sans leading-normal font-medium">{feat}</span>
                </div>
              ))}
            </div>
          </div>

          {/* Interactive Screen Preview Container - SQUARE */}
          <div className="bg-gray-50 border border-gray-200 p-4 flex-grow max-h-[190px] h-[190px] mt-4 relative overflow-hidden flex flex-col justify-between rounded-none">
            <AnimatePresence mode="wait">
              <motion.div
                key={selectedRole}
                initial={{ opacity: 0, y: 15 }}
                animate={{ opacity: 1, y: 0 }}
                exit={{ opacity: 0, y: -15 }}
                transition={{ duration: 0.3 }}
                className="w-full h-full"
              >
                {rolesData[selectedRole].mockContent}
              </motion.div>
            </AnimatePresence>
          </div>
        </div>
      </div>
    </div>
  );
}
