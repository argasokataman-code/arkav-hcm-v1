import React, { useState } from "react";
import { motion, AnimatePresence } from "motion/react";
import { 
  BarChart3, 
  Users, 
  Clock, 
  Database
} from "lucide-react";

export default function AnalyticsScene() {
  const [activeTab, setActiveTab] = useState<"departemen" | "status" | "kehadiran">("departemen");

  return (
    <div className="w-full h-full min-h-[90vh] flex flex-col lg:flex-row items-center justify-center px-4 md:px-16 lg:px-24 py-16 gap-12 relative select-none tech-grid">
      {/* Background World Glow Layer (Data Galaxy - Soft warm white) */}
      <div className="absolute inset-0 overflow-hidden pointer-events-none">
        <div className="absolute top-1/3 left-1/3 w-[550px] h-[550px] rounded-none bg-orange-100/10 blur-[150px]" />
        <div className="absolute bottom-1/4 right-1/4 w-[450px] h-[450px] bg-cyan-100/5 blur-[130px] rounded-none" />
      </div>

      {/* LEFT COLUMN: Narrative on data decisions */}
      <motion.div 
        initial={{ opacity: 0, y: 30 }}
        whileInView={{ opacity: 1, y: 0 }}
        viewport={{ once: true }}
        transition={{ duration: 0.8 }}
        className="w-full lg:w-1/2 text-left space-y-6 z-10"
        id="analytics-content-left"
      >
        <div className="inline-flex items-center gap-1.5 px-3 py-1 rounded-none bg-orange-50 border border-orange-100 text-[#FF6600] text-xs font-semibold tracking-wider font-mono">
          <BarChart3 className="w-3.5 h-3.5" />
          <span>EXECUTIVE ANALYTICS CENTER</span>
        </div>

        <h2 className="font-display font-extrabold text-3xl sm:text-4xl lg:text-5xl text-gray-950 leading-[1.08] tracking-tight">
          Keputusan Cepat Mulai <br />
          <span className="text-[#FF6600]">
            Dari Data Terbaca.
          </span>
        </h2>

        <p className="text-sm sm:text-base text-gray-500 max-w-lg font-sans leading-relaxed">
          Jangan menebak-nebak produktivitas atau beban anggaran karyawan Anda berdasarkan intuisi semata. Hub Analitik Eksekutif Arkav menyajikan keputusan beralih data terpusat, menghitung laju turnover, efisiensi payroll, serta kehadiran secara akurat.
        </p>

        {/* Dynamic selector navigation blocks */}
        <div className="flex flex-col gap-2 pt-2 max-w-md">
          <span className="text-[10px] font-mono font-bold text-gray-400 uppercase tracking-widest">PILIH TAMPILAN:</span>
          <div className="grid grid-cols-3 gap-2">
            {(["departemen", "status", "kehadiran"] as const).map((type) => (
              <button
                key={type}
                onClick={() => setActiveTab(type)}
                className={`py-3 border font-mono text-[10px] font-bold tracking-tight transition-all duration-200 active:scale-95 cursor-pointer flex flex-col items-center justify-center gap-1 rounded-none ${
                  activeTab === type
                    ? "bg-orange-50 text-gray-900 border-[#FF6600] shadow-sm"
                    : "bg-white border-gray-200 text-gray-500 hover:text-gray-800 hover:bg-gray-100"
                }`}
                id={`chart-btn-${type}`}
              >
                <span>{type === "departemen" ? "DEPARTEMEN" : type === "status" ? "STATUS" : "KEHADIRAN"}</span>
              </button>
            ))}
          </div>
        </div>
      </motion.div>

      {/* RIGHT COLUMN: Dashboard widget mock — matches runtime */}
      <div 
        className="w-full lg:w-1/2 flex items-center justify-center relative z-10 mt-6 lg:mt-0"
        id="analytics-dashboard-column"
      >
        <div className="w-full max-w-[500px] bg-white border border-gray-200 relative flex flex-col shadow-sm rounded-none">
          
          {/* Header */}
          <div className="flex items-center justify-between p-4 border-b border-gray-100">
            <div>
              <p className="text-[10px] font-mono text-gray-400 font-bold uppercase tracking-wider">Dashboard Overview</p>
              <p className="text-[9px] text-gray-400 font-mono">Ringkasan data kepegawaian & kehadiran</p>
            </div>
            <span className="text-[9px] font-mono bg-emerald-50 text-emerald-700 px-2 py-0.5 border border-emerald-100 font-bold rounded-none">LIVE</span>
          </div>

          {/* Top metric boxes */}
          <div className="grid grid-cols-2 gap-0 border-b border-gray-100">
            <div className="p-4 border-r border-gray-100">
              <div className="flex items-center justify-between text-[9px] font-mono font-bold text-gray-400">
                <span>TOTAL KARYAWAN</span>
                <Users className="w-3.5 h-3.5 text-[#FF6600]" />
              </div>
              <p className="text-xl font-extrabold text-gray-900 font-display mt-1">128</p>
              <div className="flex gap-2 mt-1 text-[8px] font-mono">
                <span className="text-gray-500">Aktif <strong className="text-gray-800">92</strong></span>
                <span className="text-gray-500">Probation <strong className="text-gray-800">15</strong></span>
              </div>
            </div>
            <div className="p-4">
              <div className="flex items-center justify-between text-[9px] font-mono font-bold text-gray-400">
                <span>KEHADIRAN HARI INI</span>
                <Clock className="w-3.5 h-3.5 text-cyan-600" />
              </div>
              <p className="text-xl font-extrabold text-gray-900 font-display mt-1">124</p>
              <div className="flex gap-2 mt-1 text-[8px] font-mono">
                <span className="text-emerald-600">Hadir <strong>96</strong></span>
                <span className="text-amber-600">Terlambat <strong>4</strong></span>
              </div>
            </div>
          </div>

          {/* Tab section */}
          <div className="p-4">
            <div className="flex gap-2 mb-3">
              {(["departemen", "status", "kehadiran"] as const).map((tab) => (
                <button
                  key={tab}
                  onClick={() => setActiveTab(tab)}
                  className={`text-[8px] font-mono font-bold px-2.5 py-1.5 border transition-all rounded-none cursor-pointer ${
                    activeTab === tab
                      ? 'bg-[#FF6600]/10 border-[#FF6600] text-[#FF6600]'
                      : 'bg-white border-gray-200 text-gray-500 hover:bg-gray-50'
                  }`}
                >
                  {tab === 'departemen' ? 'DEPARTEMEN' : tab === 'status' ? 'STATUS KARYAWAN' : 'KEHADIRAN'}
                </button>
              ))}
            </div>

            <AnimatePresence mode="wait">
              <motion.div
                key={activeTab}
                initial={{ opacity: 0, y: 8 }}
                animate={{ opacity: 1, y: 0 }}
                exit={{ opacity: 0, y: -8 }}
                transition={{ duration: 0.2 }}
              >
                {activeTab === 'departemen' && (
                  <div className="space-y-2">
                    {[
                      { name: 'Engineering', count: 38 },
                      { name: 'Finance', count: 22 },
                      { name: 'HR', count: 18 },
                      { name: 'Marketing', count: 25 },
                      { name: 'Operations', count: 15 },
                      { name: 'Design', count: 10 },
                    ].map((dept) => (
                      <div key={dept.name} className="flex items-center gap-2 text-[10px]">
                        <span className="w-24 text-gray-600 font-semibold font-sans">{dept.name}</span>
                        <div className="flex-1 h-3 bg-gray-100 rounded-none overflow-hidden">
                          <motion.div
                            initial={{ width: 0 }}
                            animate={{ width: `${(dept.count / 38) * 100}%` }}
                            transition={{ duration: 0.6, delay: 0.1 }}
                            className="h-full bg-[#FF6600] rounded-none"
                          />
                        </div>
                        <span className="w-8 text-right font-mono font-bold text-gray-800">{dept.count}</span>
                      </div>
                    ))}
                  </div>
                )}

                {activeTab === 'status' && (
                  <div className="space-y-3">
                    <div>
                      <p className="text-[9px] font-mono text-gray-400 font-bold mb-1">Komposisi Karyawan</p>
                      <div className="flex h-3 gap-0.5 overflow-hidden rounded-none">
                        <div className="bg-blue-500 h-full" style={{width: '72%'}} />
                        <div className="bg-yellow-400 h-full" style={{width: '12%'}} />
                        <div className="bg-red-400 h-full" style={{width: '8%'}} />
                        <div className="bg-pink-400 h-full" style={{width: '8%'}} />
                      </div>
                    </div>
                    <div className="grid grid-cols-2 gap-2 text-[9px] font-mono">
                      <div className="bg-blue-50 border border-blue-100 p-2 rounded-none">
                        <span className="text-blue-700 font-bold">Aktif</span>
                        <p className="text-lg font-extrabold text-gray-900">92</p>
                        <span className="text-gray-500">72%</span>
                      </div>
                      <div className="bg-yellow-50 border border-yellow-100 p-2 rounded-none">
                        <span className="text-yellow-700 font-bold">Probation</span>
                        <p className="text-lg font-extrabold text-gray-900">15</p>
                        <span className="text-gray-500">12%</span>
                      </div>
                      <div className="bg-red-50 border border-red-100 p-2 rounded-none">
                        <span className="text-red-700 font-bold">Tidak Aktif</span>
                        <p className="text-lg font-extrabold text-gray-900">10</p>
                        <span className="text-gray-500">8%</span>
                      </div>
                      <div className="bg-pink-50 border border-pink-100 p-2 rounded-none">
                        <span className="text-pink-700 font-bold">PKWT Due</span>
                        <p className="text-lg font-extrabold text-gray-900">11</p>
                        <span className="text-gray-500">8%</span>
                      </div>
                    </div>
                  </div>
                )}

                {activeTab === 'kehadiran' && (
                  <div className="space-y-2.5">
                    <p className="text-[9px] font-mono text-gray-400 font-bold mb-1">Overview Kehadiran Hari Ini</p>
                    <div className="flex items-center gap-3 mb-2">
                      <div className="w-12 h-12 rounded-full border-2 border-[#FF6600] flex items-center justify-center">
                        <span className="text-lg font-extrabold text-gray-900">124</span>
                      </div>
                      <div className="text-[9px] font-mono leading-tight">
                        <span className="text-gray-400">dari 128 karyawan</span>
                        <p className="text-emerald-600 font-bold mt-0.5">Total Attendance</p>
                      </div>
                    </div>
                    <div className="space-y-1.5">
                      {[
                        { label: 'Present', pct: 78, color: 'bg-blue-500' },
                        { label: 'Late', pct: 8, color: 'bg-amber-400' },
                        { label: 'Permission', pct: 10, color: 'bg-emerald-500' },
                        { label: 'Absent', pct: 4, color: 'bg-red-500' },
                      ].map((item) => (
                        <div key={item.label} className="flex items-center gap-2 text-[9px] font-mono">
                          <span className="w-16 text-gray-600">{item.label}</span>
                          <div className="flex-1 h-2.5 bg-gray-100 rounded-none overflow-hidden">
                            <motion.div
                              initial={{ width: 0 }}
                              animate={{ width: `${item.pct}%` }}
                              transition={{ duration: 0.5 }}
                              className={`h-full ${item.color} rounded-none`}
                            />
                          </div>
                          <span className="w-8 text-right font-bold text-gray-800">{item.pct}%</span>
                        </div>
                      ))}
                    </div>
                  </div>
                )}
              </motion.div>
            </AnimatePresence>
          </div>

          {/* Footer note */}
          <div className="border-t border-gray-100 px-4 py-2.5 flex justify-between text-[8px] font-mono text-gray-400">
            <span>Data diupdate otomatis dari sistem absensi & payroll</span>
            <Database className="w-3 h-3 text-gray-300" />
          </div>
        </div>
      </div>
    </div>
  );
}
