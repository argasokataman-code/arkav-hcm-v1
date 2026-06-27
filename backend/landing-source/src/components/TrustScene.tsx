import React from "react";
import { motion } from "motion/react";
import { ShieldCheck, Lock, Server, FileCheck, Eye, Key, ArrowRight } from "lucide-react";

const items = [
  {
    icon: <Lock className="w-4 h-4" />,
    title: "Enkripsi AES-256",
    desc: "Data sensitif (gaji, identitas, kesehatan) dienkripsi di database dengan standar AES-256.",
  },
  {
    icon: <Eye className="w-4 h-4" />,
    title: "Audit Log Lengkap",
    desc: "Setiap akses ke data pribadi tercatat — siapa, kapan, data apa yang dilihat atau diubah.",
  },
  {
    icon: <Key className="w-4 h-4" />,
    title: "RBAC Multi-Level",
    desc: "Akses berbasis peran (admin, manager, karyawan). Data hanya bisa diakses pihak yang berwenang.",
  },
  {
    icon: <Server className="w-4 h-4" />,
    title: "Server Jakarta",
    desc: "Infrastruktur server di Indonesia, tunduk pada hukum Indonesia. Isolasi tenant penuh antar perusahaan.",
  },
  {
    icon: <FileCheck className="w-4 h-4" />,
    title: "UU PDP Compliance",
    desc: "Kepatuhan terhadap UU No. 27/2022. Hak subjek data (akses, hapus, koreksi) difasilitasi penuh.",
  },
  {
    icon: <ShieldCheck className="w-4 h-4" />,
    title: "SSL/TLS Seluruh Koneksi",
    desc: "Semua transmisi data diamankan dengan SSL/TLS. API dilindungi token autentikasi.",
  },
];

export default function TrustScene() {
  return (
    <div className="w-full h-full min-h-[90vh] flex flex-col lg:flex-row items-center justify-center px-4 md:px-16 lg:px-24 py-16 gap-12 relative select-none tech-grid">
      {/* Background */}
      <div className="absolute inset-0 overflow-hidden pointer-events-none">
        <div className="absolute top-[10%] left-[20%] w-[500px] h-[500px] rounded-none bg-gradient-to-br from-blue-50/30 to-transparent blur-[130px]" />
        <div className="absolute bottom-[20%] right-[20%] w-[450px] h-[450px] rounded-none bg-gradient-to-l from-emerald-50/20 to-transparent blur-[110px]" />
        <div className="absolute top-[50%] left-[50%] w-[300px] h-[300px] rounded-none border border-blue-200/20 animate-pulse" style={{ animationDuration: '5s' }} />
      </div>

      {/* LEFT: Badge + Headline + CTA */}
      <motion.div
        initial={{ opacity: 0, x: -40 }}
        whileInView={{ opacity: 1, x: 0 }}
        viewport={{ once: true }}
        transition={{ duration: 0.8, ease: "easeOut" }}
        className="w-full lg:w-1/2 flex flex-col justify-center text-left space-y-6 z-10"
      >
        <div className="inline-flex items-center gap-1.5 px-3 py-1 rounded-none bg-emerald-50/60 border border-emerald-200/60 text-emerald-700 text-xs font-semibold tracking-wider font-mono w-fit uppercase">
          <ShieldCheck className="w-3.5 h-3.5" />
          <span>Keamanan & Kepatuhan</span>
        </div>

        <h2 className="font-display font-extrabold text-3xl sm:text-4xl lg:text-5xl text-gray-950 leading-[1.08] tracking-tight">
          Data Anda <br />
          <span className="text-[#FF6600]">Terlindungi</span> <br />
          Sesuai Standar
        </h2>

        <p className="text-sm sm:text-base text-gray-500 max-w-lg font-sans leading-relaxed">
          Arkav HCM menerapkan keamanan berlapis — dari enkripsi database hingga akses berbasis peran — 
          untuk melindungi data karyawan dan perusahaan Anda.
        </p>

        {/* Trust badges */}
        <div className="flex flex-wrap items-center gap-3 pt-2">
          <span className="text-[10px] font-mono font-bold text-emerald-600 bg-emerald-50 border border-emerald-100 px-3 py-1.5 flex items-center gap-1.5">
            <ShieldCheck className="w-3 h-3" /> ISO 27001
          </span>
          <span className="text-[10px] font-mono font-bold text-blue-600 bg-blue-50 border border-blue-100 px-3 py-1.5 flex items-center gap-1.5">
            <FileCheck className="w-3 h-3" /> UU PDP
          </span>
          <span className="text-[10px] font-mono font-bold text-gray-600 bg-gray-50 border border-gray-200 px-3 py-1.5 flex items-center gap-1.5">
            <Lock className="w-3 h-3" /> AES-256
          </span>
        </div>
      </motion.div>

      {/* RIGHT: Compliance Grid */}
      <motion.div
        initial={{ opacity: 0, x: 40 }}
        whileInView={{ opacity: 1, x: 0 }}
        viewport={{ once: true }}
        transition={{ duration: 0.8, delay: 0.15, ease: "easeOut" }}
        className="w-full lg:w-1/2 z-10"
      >
        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
          {items.map((item, i) => (
            <motion.div
              key={i}
              initial={{ opacity: 0, y: 20 }}
              whileInView={{ opacity: 1, y: 0 }}
              viewport={{ once: true }}
              transition={{ delay: i * 0.08 }}
              className="bg-white border border-gray-200 p-4 flex gap-3 shadow-sm hover:border-[#FF6600]/20 hover:shadow-md transition-all duration-200"
            >
              <div className="w-9 h-9 shrink-0 bg-emerald-50 border border-emerald-100 flex items-center justify-center text-emerald-600">
                {item.icon}
              </div>
              <div className="min-w-0">
                <h3 className="text-sm font-bold text-gray-900 mb-0.5">{item.title}</h3>
                <p className="text-[11px] text-gray-500 font-sans leading-relaxed">{item.desc}</p>
              </div>
            </motion.div>
          ))}
        </div>
      </motion.div>
    </div>
  );
}
