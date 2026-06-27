import React, { useState } from "react";
import { motion } from "motion/react";
import { 
  Zap, 
  Sparkles,
  Users
} from "lucide-react";

export default function PayrollScene() {
  const [employeeCount, setEmployeeCount] = useState(25);

  // Constants for re-calculating financial metrics
  const avgSalary = 7500000; // IDR 7.500.000 average salary
  const baseTunjangan = 1200000;
  const bpjsPercentage = 0.04; // 4%
  const pph21Percentage = 0.05; // 5% average

  const totalGajiBersih = employeeCount * avgSalary;
  const totalTunjangan = employeeCount * baseTunjangan;
  const totalBpjs = totalGajiBersih * bpjsPercentage;
  const totalPph21 = totalGajiBersih * pph21Percentage;
  const totalKomitmen = totalGajiBersih + totalTunjangan - totalBpjs - totalPph21;

  // Formatting utility
  const formatRupiah = (num: number) => {
    return "Rp " + new Intl.NumberFormat("id-ID", {
      maximumFractionDigits: 0
    }).format(num);
  };

  const getProcessingTime = (count: number) => {
    if (count <= 10) return "0.2 detik";
    if (count <= 50) return "1.5 detik";
    if (count <= 150) return "5.4 detik";
    return "12.8 detik";
  };

  const manualTimeDays = (count: number) => {
    if (count <= 10) return "1 hari kerja";
    if (count <= 50) return "3 hari kerja";
    if (count <= 150) return "5 hari kerja";
    return "8+ hari kerja";
  };

  const manualHours = employeeCount * 0.75; // 45 mins per employee manual
  const arkavHours = employeeCount * 0.05; // 3 mins per employee automated
  const hoursSaved = Math.round(manualHours - arkavHours);
  const hrSalaryPerHour = 35000; // ~Rp 7jt/month / 200 hours
  const monthlySavings = Math.round(hoursSaved * hrSalaryPerHour);

  return (
    <div className="w-full h-full min-h-[90vh] flex flex-col lg:flex-row items-center justify-center px-4 md:px-16 lg:px-24 py-16 gap-12 relative select-none tech-grid">
      {/* Background World Glow Layer (Financial Energy Stream - Soft warm white) */}
      <div className="absolute inset-0 overflow-hidden pointer-events-none">
        <div className="absolute top-[20%] right-[10%] w-[500px] h-[500px] rounded-none bg-orange-50/15 blur-[140px] animate-pulse duration-[6000ms]" />
        <div className="absolute bottom-[20%] left-[20%] w-[400px] h-[400px] rounded-none bg-orange-100/5 blur-[120px]" />
      </div>

      {/* LEFT COLUMN: Narrative & Slider control */}
      <motion.div 
        initial={{ opacity: 0, x: -50 }}
        whileInView={{ opacity: 1, x: 0 }}
        viewport={{ once: true }}
        transition={{ duration: 0.8 }}
        className="w-full lg:w-1/2 text-left space-y-6 z-10"
        id="payroll-content-left"
      >
        <div className="inline-flex items-center gap-1.5 px-3 py-1 rounded-none bg-orange-50 border border-orange-100 text-[#FF6600] text-xs font-semibold tracking-wider font-mono">
          <Zap className="w-3.5 h-3.5" />
          <span>ARKAV PAYROLL ENGINE V3</span>
        </div>

        <h2 className="font-display font-extrabold text-3xl sm:text-4xl lg:text-5xl text-gray-950 leading-[1.08] tracking-tight">
          Selesai Dalam <br />
          <span className="text-[#FF6600]">
            Hitungan Menit,
          </span> <br />
          Bukan Hari.
        </h2>

        <p className="text-sm sm:text-base text-gray-500 max-w-lg font-sans leading-relaxed">
          Kalkulasi lembur harian, BPJS, PPh 21, bonus performa, jaminan, dan pay-transfer karyawan terhitung berkala secara instan. Katakan selamat tinggal pada pengerjaan manual berekstensi panjang di spreadsheet.
        </p>

        {/* Interactive Employee Scale Adjuster */}
        <div className="bg-white border border-gray-200 p-5 rounded-none max-w-md space-y-4 shadow-sm">
          <div className="flex justify-between items-center text-xs font-mono">
            <span className="text-gray-500 font-bold flex items-center gap-1.5">
              <Users className="w-4 h-4 text-[#FF6600]" /> JUMLAH TIM AKTIF:
            </span>
            <span className="text-[#FF6600] font-extrabold text-sm bg-orange-50 px-2.5 py-0.5 border border-orange-100 rounded-none">{employeeCount} Orang</span>
          </div>

          <input 
            type="range" 
            min="5" 
            max="300" 
            value={employeeCount} 
            onChange={(e) => setEmployeeCount(Number(e.target.value))}
            className="w-full h-1 bg-gray-200 rounded-none appearance-none cursor-pointer accent-[#FF6600]"
            id="payroll-employee-slider"
          />

          <div className="grid grid-cols-2 gap-3 pt-2 border-t border-gray-100">
            <div className="text-left space-y-0.5">
              <span className="text-[9px] font-mono font-bold text-gray-400 uppercase">Waktu Rekap Manual</span>
              <p className="text-sm font-semibold text-red-500 line-through font-display">{manualTimeDays(employeeCount)}</p>
            </div>
            <div className="text-left space-y-0.5">
              <span className="text-[9px] font-mono font-extrabold text-[#FF6600] uppercase tracking-wider flex items-center gap-1">
                <Sparkles className="w-3 h-3 text-[#FF6600]" /> SINKRONISASI INSTAN:
              </span>
              <p className="text-sm font-bold text-emerald-600 font-mono">{getProcessingTime(employeeCount)}</p>
            </div>
          </div>

          {/* ROI SAVINGS */}
          <div className="bg-emerald-50 border border-emerald-100 p-3 text-left">
            <span className="text-[9px] font-mono font-bold text-emerald-700 uppercase">Estimasi Penghematan/bulan</span>
            <div className="flex items-baseline justify-between mt-1">
              <span className="text-lg font-extrabold font-display text-emerald-700">{hoursSaved} jam</span>
              <span className="text-sm font-bold text-emerald-600">≈ {formatRupiah(monthlySavings)}/bln</span>
            </div>
            <p className="text-[8px] text-emerald-500 font-mono mt-0.5">Berdasarkan rata-rata gaji staf HR Rp 35.000/jam</p>
          </div>
        </div>
      </motion.div>

      {/* RIGHT COLUMN: Interactive Premium Payroll Engine Simulator */}
      <div 
        className="w-full lg:w-1/2 flex items-center justify-center relative z-10 mt-6 lg:mt-0"
        id="payroll-simulator-column"
      >
        <div className="w-full max-w-[500px] bg-white border border-gray-200 relative shadow-sm rounded-none">
          
          {/* Header */}
          <div className="flex items-center justify-between p-4 border-b border-gray-100">
            <div>
              <p className="text-[10px] font-mono text-gray-400 font-bold uppercase tracking-wider">Payroll — Run Bulanan</p>
              <p className="text-[9px] text-gray-400 font-mono">HR &gt; Payroll / Run Bulanan</p>
            </div>
            <span className="text-[9px] font-mono bg-orange-50 text-[#FF6600] px-2.5 py-0.5 border border-orange-100 rounded-none font-bold">Periode Juni 2026</span>
          </div>

          {/* Summary bar */}
          <div className="grid grid-cols-4 gap-0 border-b border-gray-100">
            <div className="p-3 text-center border-r border-gray-100">
              <span className="text-[8px] font-mono font-bold text-gray-400 uppercase">Total Karyawan</span>
              <p className="text-base font-extrabold text-gray-900 mt-0.5">{employeeCount}</p>
            </div>
            <div className="p-3 text-center border-r border-gray-100">
              <span className="text-[8px] font-mono font-bold text-gray-400 uppercase">Total Line</span>
              <p className="text-base font-extrabold text-gray-900 mt-0.5">{employeeCount * 3}</p>
            </div>
            <div className="p-3 text-center border-r border-gray-100">
              <span className="text-[8px] font-mono font-bold text-gray-400 uppercase">Status Periode</span>
              <span className="inline-block mt-1 text-[8px] font-mono font-bold bg-blue-50 text-blue-700 px-1.5 py-0.5 border border-blue-100">Draft</span>
            </div>
            <div className="p-3 text-center">
              <span className="text-[8px] font-mono font-bold text-gray-400 uppercase">Pembayaran</span>
              <span className="inline-block mt-1 text-[8px] font-mono font-bold bg-amber-50 text-amber-700 px-1.5 py-0.5 border border-amber-100">Unpaid</span>
            </div>
          </div>

          {/* Employee Table (compact) */}
          <div className="p-3">
            <div className="text-[8px] font-mono font-bold text-gray-400 uppercase tracking-wider mb-2">Preview Karyawan</div>
            <table className="w-full text-[9px] font-mono">
              <thead>
                <tr className="border-b border-gray-100 text-gray-400">
                  <th className="text-left py-1.5 font-semibold">Karyawan</th>
                  <th className="text-right py-1.5 font-semibold">Bruto</th>
                  <th className="text-right py-1.5 font-semibold">Potongan</th>
                  <th className="text-right py-1.5 font-semibold">Netto</th>
                </tr>
              </thead>
              <tbody>
                <tr className="border-b border-gray-50">
                  <td className="py-1.5 text-gray-900 font-bold">Budi Setiawan</td>
                  <td className="py-1.5 text-right text-gray-800">{formatRupiah(Math.round(avgSalary * 1.1))}</td>
                  <td className="py-1.5 text-right text-red-500">{formatRupiah(Math.round(avgSalary * 0.12))}</td>
                  <td className="py-1.5 text-right text-emerald-700 font-bold">{formatRupiah(Math.round(avgSalary * 0.98))}</td>
                </tr>
                <tr className="border-b border-gray-50">
                  <td className="py-1.5 text-gray-900 font-bold">Siska Amalia</td>
                  <td className="py-1.5 text-right text-gray-800">{formatRupiah(Math.round(avgSalary * 1.2))}</td>
                  <td className="py-1.5 text-right text-red-500">{formatRupiah(Math.round(avgSalary * 0.14))}</td>
                  <td className="py-1.5 text-right text-emerald-700 font-bold">{formatRupiah(Math.round(avgSalary * 1.06))}</td>
                </tr>
                <tr className="border-b border-gray-50">
                  <td className="py-1.5 text-gray-900 font-bold">Ahmad Rizki</td>
                  <td className="py-1.5 text-right text-gray-800">{formatRupiah(Math.round(avgSalary * 0.85))}</td>
                  <td className="py-1.5 text-right text-red-500">{formatRupiah(Math.round(avgSalary * 0.10))}</td>
                  <td className="py-1.5 text-right text-emerald-700 font-bold">{formatRupiah(Math.round(avgSalary * 0.75))}</td>
                </tr>
              </tbody>
            </table>
            <p className="text-[8px] text-gray-400 font-mono mt-1.5">+ {employeeCount - 3} karyawan lainnya</p>
          </div>

          {/* Workflow + Action Buttons */}
          <div className="border-t border-gray-100 p-3 space-y-2">
            <div className="flex items-center gap-2">
              <span className="text-[8px] font-mono font-bold text-gray-400 uppercase tracking-wider">Workflow:</span>
              <span className="text-[8px] font-mono text-gray-500">Periode aktif → Calculate draft → Review → Export → Catat pembayaran</span>
            </div>
            <div className="flex gap-2">
              <button className="flex-1 py-2 bg-[#FF6600] hover:bg-orange-600 text-white font-extrabold text-[9px] font-sans uppercase tracking-widest transition-colors rounded-none cursor-pointer">
                Calculate Draft
              </button>
              <button className="flex-1 py-2 bg-white border border-gray-200 text-gray-600 font-bold text-[9px] font-sans uppercase tracking-widest hover:bg-gray-50 transition-colors rounded-none cursor-pointer">
                Export
              </button>
              <button className="flex-1 py-2 bg-emerald-50 border border-emerald-200 text-emerald-700 font-bold text-[9px] font-sans uppercase tracking-widest hover:bg-emerald-100 transition-colors rounded-none cursor-pointer">
                Tandai Dibayar
              </button>
            </div>
          </div>

          {/* Tax policy note */}
          <div className="bg-gray-50 border-t border-gray-100 px-3 py-2 flex justify-between text-[8px] font-mono text-gray-400">
            <span>Policy Pajak: PPh 21 TER • BPJS</span>
            <span>Tenant Aktif: {employeeCount} org</span>
          </div>
        </div>
      </div>
    </div>
  );
}
