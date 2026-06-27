import React from "react";
import { motion } from "motion/react";
import { ClipboardList, Settings, Rocket, ArrowRight } from "lucide-react";

const steps = [
  {
    icon: <ClipboardList className="w-5 h-5" />,
    step: "01",
    title: "Daftar & Pilih Paket",
    desc: "Daftar gratis, pilih paket sesuai kebutuhan. Tidak perlu kontrak panjang — bisa monthly.",
    badge: "1-2 menit",
    detail: "Isi data perusahaan, pilih paket (Basic/Growth/Enterprise), konfirmasi pembayaran. Tim kami kirim email konfirmasi + akses dashboard.",
  },
  {
    icon: <Settings className="w-5 h-5" />,
    step: "02",
    title: "Import Data Karyawan",
    desc: "Upload data karyawan via Excel atau input manual. Atur struktur organisasi, jabatan, aturan absensi & cuti.",
    badge: "30-60 menit",
    detail: "Template Excel tersedia. Cukup isi nama, jabatan, gaji, status PTKP. Atur radius GPS, jenis cuti, approval flow. Support tim kami siap bantu.",
  },
  {
    icon: <Rocket className="w-5 h-5" />,
    step: "03",
    title: "Undang Tim & Go-Live",
    desc: "Undang karyawan via email. Mereka login, absen, ajukan cuti — langsung aktif dalam hitungan jam.",
    badge: "Selesai",
    detail: "Karyawan terima email undangan + panduan. Cukup login pakai browser (HP/laptop). Admin bisa pantau realtime dari dashboard.",
  },
];

export default function HowItWorksScene() {
  return (
    <div className="w-full h-full min-h-[90vh] flex flex-col items-center justify-center px-4 md:px-16 lg:px-24 py-16 gap-12 relative select-none tech-grid">
      {/* Background */}
      <div className="absolute inset-0 overflow-hidden pointer-events-none">
        <div className="absolute top-[10%] right-[15%] w-[500px] h-[500px] rounded-none bg-gradient-to-br from-[#FF6600]/5 to-transparent blur-[130px]" />
        <div className="absolute bottom-[20%] left-[10%] w-[400px] h-[400px] rounded-none bg-gradient-to-l from-orange-100/10 to-transparent blur-[100px]" />
      </div>

      {/* HEADER */}
      <motion.div
        initial={{ opacity: 0, y: -20 }}
        whileInView={{ opacity: 1, y: 0 }}
        viewport={{ once: true }}
        className="text-center space-y-4 z-10"
      >
        <div className="inline-flex items-center gap-1.5 px-3 py-1 rounded-none bg-[#FF6600]/5 border border-[#FF6600]/15 text-[#FF6600] text-xs font-semibold tracking-wider font-mono w-fit mx-auto uppercase">
          <Rocket className="w-3.5 h-3.5" />
          <span>Cara Mulai</span>
        </div>

        <h2 className="font-display font-extrabold text-3xl sm:text-4xl lg:text-5xl text-gray-950 leading-[1.08] tracking-tight">
          Siap Dalam <span className="text-[#FF6600]">Hitungan Jam</span>
        </h2>

        <p className="text-sm sm:text-base text-gray-500 max-w-lg font-sans leading-relaxed mx-auto">
          Dari daftar sampai go-live — cukup 3 langkah. Tim support kami dampingi setiap tahap.
        </p>
      </motion.div>

      {/* STEPS */}
      <div className="w-full max-w-4xl z-10">
        <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
          {steps.map((s, i) => (
            <motion.div
              key={i}
              initial={{ opacity: 0, y: 30 }}
              whileInView={{ opacity: 1, y: 0 }}
              viewport={{ once: true }}
              transition={{ delay: i * 0.15 }}
              className="bg-white border border-gray-200 p-6 flex flex-col gap-4 shadow-sm relative"
            >
              {/* Step number */}
              <div className="flex items-center justify-between">
                <div className="w-10 h-10 bg-[#FF6600]/5 border border-[#FF6600]/15 flex items-center justify-center text-[#FF6600]">
                  {s.icon}
                </div>
                <span className="text-[10px] font-mono font-bold text-gray-300">{s.step}</span>
              </div>

              {/* Arrow connector (desktop) */}
              {i < steps.length - 1 && (
                <div className="hidden md:block absolute top-8 -right-3 z-20 text-gray-300">
                  <ArrowRight className="w-5 h-5" />
                </div>
              )}

              <div>
                <div className="flex items-center gap-2 mb-2">
                  <h3 className="text-base font-bold font-display text-gray-900">{s.title}</h3>
                  <span className="text-[9px] font-mono font-bold text-emerald-600 bg-emerald-50 border border-emerald-100 px-1.5 py-0.5 whitespace-nowrap">
                    {s.badge}
                  </span>
                </div>
                <p className="text-sm text-gray-500 leading-relaxed">{s.desc}</p>
                <p className="text-[11px] text-gray-400 leading-relaxed mt-2 pt-2 border-t border-gray-100">
                  {s.detail}
                </p>
              </div>
            </motion.div>
          ))}
        </div>
      </div>

      {/* Integrations */}
      <motion.div
        initial={{ opacity: 0, y: 20 }}
        whileInView={{ opacity: 1, y: 0 }}
        viewport={{ once: true }}
        className="z-10 text-center space-y-3 max-w-3xl w-full"
      >
        <div className="flex items-center gap-3 justify-center">
          <span className="h-px flex-1 max-w-[80px] bg-gradient-to-r from-transparent to-gray-200" />
          <span className="text-[9px] font-mono text-gray-400 uppercase tracking-widest font-bold">Terintegrasi Dengan</span>
          <span className="h-px flex-1 max-w-[80px] bg-gradient-to-l from-transparent to-gray-200" />
        </div>
        <div className="flex flex-wrap items-center justify-center gap-2">
          {["Jurnal", "Accurate", "Xendit", "Midtrans", "Slack", "Email", "WhatsApp"].map((name, i) => (
            <span key={i} className="text-[10px] font-mono font-bold text-gray-400 bg-white border border-gray-200 px-3 py-1.5">
              {name}
            </span>
          ))}
        </div>
        <p className="text-[9px] font-mono text-gray-400/60">Integrasi terus bertambah — tanya tim sales untuk kebutuhan spesifik</p>
      </motion.div>

      {/* Bottom CTA */}
      <motion.div
        initial={{ opacity: 0 }}
        whileInView={{ opacity: 1 }}
        viewport={{ once: true }}
        className="z-10"
      >
        <p className="text-[10px] font-mono text-gray-400 text-center">
          Butuh bantuan setup? Tim support kami siap assist via chat & email.
        </p>
      </motion.div>
    </div>
  );
}
