import React, { useState } from "react";
import { motion } from "motion/react";
import { HelpCircle, ChevronDown, Sparkles } from "lucide-react";

const faqData = [
  {
    category: "Platform & Cara Kerja",
    items: [
      { q: "Apa bedanya Arkav HCM dengan rekap Excel atau aplikasi payroll terpisah?", a: "Excel rawan error dan gak realtime — apalagi kalau sudah 50+ karyawan dengan data absensi, lembur, BPJS, PPh 21 yang harus nyambung satu sama lain. Arkav HCM mengintegrasikan semua modul dalam satu platform: absensi otomatis terhubung ke payroll, potongan BPJS dan PPh 21 dihitung otomatis, slip gaji langsung terbit. Administrator cukup input data sekali, sisanya sistem yang kerja. Realtime, audit trail, dan semua data bisa diekspor kapan aja." },
      { q: "Perusahaan kami hybrid — karyawan lapangan dan kantor. Cocok?", a: "Sangat cocok. Arkav HCM mendukung multi-metode absensi: GPS tracking untuk karyawan lapangan (radius bisa diatur per lokasi), QR code/wifi untuk karyawan kantor. Admin cukup atur radius masing-masing lokasi. Semua metode absen tetap terrekam dengan timestamp dan foto selfie, jadi data kehadiran tetap akurat." },
      { q: "Perusahaan kami masih 10 karyawan. Apakah worth it?", a: "Justru untuk skala 10-50 karyawan, Arkav HCM paling terasa dampaknya. Dengan satu admin, Anda bisa handle absensi, cuti, payroll, BPJS, PPh 21, dan laporan — tanpa perlu tim HR khusus. Harganya juga scale sesuai jumlah karyawan, jadi gak perlu bayar fitur yang gak dipakai. Banyak perusahaan startup dan UKM mulai dari 5 karyawan sudah pakai." },
    ]
  },
  {
    category: "Absensi & Kehadiran",
    items: [
      { q: "Kalau karyawan lupa absen, bisa diinput manual oleh admin?", a: "Bisa. Admin punya akses koreksi absen untuk menambahkan data kehadiran yang terlewat. Setiap koreksi tercatat dengan siapa yang mengubah dan kapan — audit trail tetap rapi. Tapi best practice-nya dorong karyawan untuk absen mandiri lewat HP, karena semakin banyak koreksi manual, semakin besar potensi human error." },
      { q: "Apakah ada verifikasi wajah atau selfie untuk cegah absen titip?", a: "Ada. Admin bisa mewajibkan foto selfie setiap kali absen. Sistem menyimpan foto lengkap dengan timestamp dan lokasi GPS. Jadi kalau ada sengketa kehadiran, manajer bisa langsung melihat bukti: apakah yang bersangkutan benar-benar ada di lokasi pada jam tersebut. Ini efektif mencegah praktik absen titip atau manipulasi kehadiran." },
      { q: "Bagaimana sistem menangani lembur?", a: "Admin mengatur aturan lembur di awal: tarif per jam (misal 1.5x gaji normal untuk jam pertama, 2x untuk jam berikutnya), batas maksimal lembur per hari/minggu, dan siapa yang berhak lembur. Karyawan mengajukan lembur melalui sistem, atasan menyetujui, dan perhitungan otomatis masuk ke payroll pada periode gaji berjalan. Tidak perlu kalkulasi manual." },
      { q: "Ada fitur cuti bersama atau mass leave?", a: "Ada. Admin bisa menetapkan cuti bersama untuk periode tertentu (misal: libur lebaran, cuti nasional). Sistem otomatis mengurangi kuota cuti semua karyawan yang terdampak pada tanggal yang ditentukan. Ini sangat membantu perusahaan yang punya kebijakan cuti bersama wajib." },
    ]
  },
  {
    category: "Cuti & Izin",
    items: [
      { q: "Jenis cuti apa saja yang tersedia dan bisa dikustom?", a: "Default: cuti tahunan, cuti sakit, cuti melahirkan, cuti pernikahan, cuti penting, izin khusus, dan cuti bersama. Masing-masing bisa diatur: kuota per tahun, minimal pengajuan (dari 1 hari sebelumnya), maksimal hari berturut-turut, dokumen pendukung (surat dokter, undangan), dan siapa yang berhak (level jabatan tertentu). Admin bisa menambah jenis cuti baru sesuai kebijakan perusahaan." },
      { q: "Cuti ditolak tanpa alasan, bagaimana cara cek?", a: "Cek notifikasi di halaman cuti Anda — sistem mencatat alasan penolakan dari atasan atau admin. Umumnya ditolak karena: (1) kuota cuti tidak cukup, (2) tanggal bentrok dengan karyawan lain yang sudah ambil cuti, (3) ada aturan perusahaan yang belum dipenuhi (misal: harus ada jeda 1 hari antara pengajuan dan tanggal cuti), (4) masa percobaan belum selesai. Kalau kurang jelas, hubungi admin HR." },
      { q: "Sisa cuti tidak bertambah setelah setahun — kenapa?", a: "Beberapa perusahaan menerapkan sistem kuota tetap — misal 12 hari per tahun, hangus di akhir tahun. Bukan akumulasi. Cek kebijakan cuti perusahaan Anda di pengaturan. Kalau ada perbedaan dengan yang seharusnya, segera laporkan ke admin HR agar diperbaiki." },
    ]
  },
  {
    category: "Payroll & Slip Gaji",
    items: [
      { q: "Apakah perhitungan PPh 21 otomatis termasuk tarif progresif dan PTKP?", a: "Sistem menghitung PPh 21 sesuai tarif Pasal 17 UU PPh — lima lapisan tarif progresif (5% sampai 35%). Termasuk perhitungan PTKP berdasarkan status karyawan: TK/0, K/0, K/1, K/2, K/3. Admin cukup memilih status PTKP di data karyawan, dan sistem otomatis menghitung penghasilan bruto, pengurangan, PKP, dan PPh 21 terutang. Untuk masa pajak Desember, sistem juga otomatis melakukan gross-up atau koreksi tahunan." },
      { q: "THR dihitung otomatis atau manual?", a: "Modul THR menghitung otomatis berdasarkan masa kerja dan gaji bulanan sesuai aturan ketenagakerjaan. Karyawan dengan masa kerja 12+ bulan mendapat THR 1 bulan gaji, kurang dari 12 bulan dihitung proporsional. Hasil perhitungan masuk sebagai komponen payroll di periode THR. Admin cukup melakukan review dan finalisasi." },
      { q: "Gaji berbeda tiap bulan karena lembur dan bonus — bagaimana penanganannya?", a: "Sistem memisahkan komponen gaji tetap (gaji pokok, tunjangan tetap) dan komponen variabel (lembur, bonus insidentil, potongan). Komponen tetap dihitung otomatis setiap bulan, sedangkan komponen variabel menyesuaikan input absensi dan pengajuan. Admin melihat total sebelum finalisasi — jika ada yang perlu disesuaikan, bisa diedit sebelum dikunci." },
      { q: "Apa yang perlu diperhatikan saat finalisasi payroll?", a: "Sebelum finalisasi, pastikan: (1) semua data absensi dan lembur periode tersebut sudah lengkap, (2) komponen gaji variabel sudah sesuai, (3) tidak ada cuti atau resignasi yang belum diproses, (4) rekonsiliasi sudah diekspor jika diperlukan. Setelah finalisasi, data tidak bisa diubah — karyawan akan melihat slip gaji mereka. Proses ini juga mencatat audit trail siapa yang melakukan finalisasi dan kapan." },
    ]
  },
  {
    category: "Data, Privasi & Keamanan",
    items: [
      { q: "Apakah data karyawan saya aman dan sesuai UU PDP?", a: "Kami menerapkan perlindungan data berlapis sesuai UU No. 27 Tahun 2022: (1) setiap akses ke data pribadi tercatat di audit log — siapa, kapan, data apa, (2) data sensitif seperti gaji, nomor identitas, dan data kesehatan dienkripsi AES-256 di database, (3) akses berbasis peran — admin perusahaan hanya bisa melihat data karyawannya sendiri, tidak bisa lintas tenant, (4) infrastruktur server di Indonesia dengan isolasi tenant penuh. Data Anda adalah milik Anda, bukan kami." },
      { q: "Siapa saja yang bisa mengakses data karyawan?", a: "Hanya admin perusahaan dan pengguna yang diberi hak akses spesifik oleh admin. Arkav HCM sebagai penyedia platform tidak memiliki akses ke data isi perusahaan — kami hanya menyediakan infrastruktur dan dukungan teknis. Jika ada permintaan data dari pihak ketiga (misal BPJS, auditor), harus seizin perusahaan Anda. Tidak ada pihak luar yang bisa mengakses data tanpa otorisasi." },
      { q: "Kalau karyawan minta data pribadinya dihapus (hak subjek data UU PDP)?", a: "Sesuai UU PDP Pasal 7, subjek data berhak meminta penghapusan data pribadi. Admin perusahaan bisa menghapus data karyawan yang bersangkutan melalui menu pengelolaan data. Kami juga menyediakan jalur kontak DPO (Data Protection Officer) di halaman Kebijakan Privasi untuk pengaduan terkait perlindungan data. Semua permintaan penghapusan dicatat untuk kepatuhan." },
      { q: "Apa yang terjadi kalau server down? Bagaimana backup data?", a: "Infrastruktur menggunakan arsitektur dengan redundancy: database master-slave, backup otomatis berkala ke storage terpisah. Jika satu server mengalami gangguan, sistem otomatis failover tanpa kehilangan data. Kami tetap merekomendasikan backup mandiri — export Excel/CSV tersedia di modul absensi, cuti, payroll, dan data karyawan. Simpan arsip payroll bulanan untuk keperluan audit jangka panjang." },
    ]
  },
  {
    category: "Billing & Subscription",
    items: [
      { q: "Bagaimana cara perhitungan harga Arkav HCM?", a: "Harga berdasarkan: (1) paket yang dipilih — menentukan fitur yang tersedia, (2) jumlah karyawan aktif. Bisa monthly atau yearly (lebih hemat). Tidak ada biaya setup, instalasi, atau biaya tersembunyi. Perubahan paket (upgrade/downgrade) dihitung prorata. Detail harga dan perbandingan fitur ada di halaman Paket & Harga." },
      { q: "Kalau jumlah karyawan berubah di tengah bulan?", a: "Tagihan prorata otomatis. Karyawan baru dihitung sejak tanggal aktif, karyawan keluar tidak dihitung lagi setelah tanggal efektif. Billing transparan — admin bisa lihat rincian tagihan per karyawan di menu billing. Tidak ada penalti untuk perubahan jumlah karyawan." },
      { q: "Apakah ada masa trial atau demo?", a: "Tergantung ketersediaan paket trial. Biasanya tersedia trial 7-14 hari dengan fitur terbatas untuk mencoba platform. Kalau butuh demo khusus (misal: simulasi payroll dengan data perusahaan Anda), hubungi tim sales kami untuk jadwal. Tim kami akan mendampingi proses trial dan setup awal." },
      { q: "Metode pembayaran apa yang didukung?", a: "Transfer bank, virtual account, dan metode pembayaran digital. Invoice diterbitkan otomatis setiap periode billing. Admin bisa mengunduh invoice dan bukti pembayaran dari dashboard. Untuk perusahaan dengan kebutuhan khusus (misal: PO procurement), bisa diatur dengan tim billing kami." },
    ]
  }
];

export default function QaScene({ isMobile = false }: { isMobile?: boolean }) {
  const [openIndex, setOpenIndex] = useState<number | null>(null);
  const [activeCategory, setActiveCategory] = useState<number | null>(0);
  const rightRef = React.useRef<HTMLDivElement | null>(null);
  const categoryRefs = React.useRef<(HTMLDivElement | null)[]>([]);
  const itemRefs = React.useRef<(HTMLDivElement | null)[]>([]);

  const toggleItem = (id: number) => {
    const next = openIndex === id ? null : id;
    setOpenIndex(next);
    if (next !== null) {
      requestAnimationFrame(() => {
        const el = itemRefs.current[next];
        if (!el || !rightRef.current) return;
        const container = rightRef.current;
        const cTop = container.getBoundingClientRect().top;
        const eTop = el.getBoundingClientRect().top;
        const eBottom = el.getBoundingClientRect().bottom;
        const sTop = container.scrollTop;
        const cHeight = container.clientHeight;
        if (eTop - cTop + sTop < sTop || eBottom - cTop + sTop > sTop + cHeight) {
          container.scrollTo({ top: eTop - cTop + sTop - 20, behavior: 'smooth' });
        }
      });
    }
  };

  const scrollToSection = (sIdx: number) => {
    const el = categoryRefs.current[sIdx];
    if (!el || !rightRef.current) return;
    const container = rightRef.current;
    const top = el.getBoundingClientRect().top - container.getBoundingClientRect().top + container.scrollTop - 16;
    container.scrollTo({ top, behavior: 'smooth' });
  };

  return (
    <div className="w-full h-full min-h-[90vh] flex flex-col lg:flex-row items-center justify-center px-4 md:px-16 lg:px-24 py-16 gap-12 relative select-none tech-grid">
      {/* Background Glow */}
      <div className="absolute inset-0 overflow-hidden pointer-events-none">
        <div className="absolute top-[15%] right-[20%] w-[500px] h-[500px] rounded-none bg-gradient-to-br from-[#FF6600]/5 to-transparent blur-[120px]" />
        <div className="absolute bottom-[15%] left-[15%] w-[400px] h-[400px] rounded-none bg-gradient-to-l from-orange-100/10 to-transparent blur-[100px]" />
        <div className="absolute top-[40%] left-[50%] w-[300px] h-[300px] rounded-none border border-[#FF6600]/5 animate-pulse" style={{ animationDuration: '4s' }} />
      </div>

      {/* LEFT: Headline + Navigator */}
      <motion.div
        initial={{ opacity: 0, x: -40 }}
        animate={{ opacity: 1, x: 0 }}
        transition={{ duration: 0.8, ease: "easeOut" }}
        className="w-full lg:w-1/2 flex flex-col justify-center text-left space-y-6 z-10"
      >
        <div className="inline-flex items-center gap-1.5 px-3 py-1 rounded-none bg-[#FF6600]/5 border border-[#FF6600]/15 text-[#FF6600] text-xs font-semibold tracking-wider font-mono w-fit">
          <Sparkles className="w-3.5 h-3.5 animate-pulse" />
          <span className="uppercase">Pusat Bantuan</span>
        </div>

        <h2 className="font-display font-extrabold text-3xl sm:text-4xl lg:text-5xl text-gray-950 leading-[1.08] tracking-tight">
          Pertanyaan <br />
          <span className="text-[#FF6600]">Umum</span>
        </h2>

        <p className="text-sm sm:text-base text-gray-500 max-w-lg font-sans leading-relaxed">
          Jawaban detail untuk pertanyaan paling sering seputar platform Arkav HCM — dari absensi, payroll, hingga keamanan data sesuai UU PDP.
        </p>

        {/* Category Navigator */}
        {!isMobile && (
          <div className="flex flex-wrap gap-2 pt-2">
            {faqData.map((s, i) => (
              <button
                key={i}
                onClick={() => { setActiveCategory(i); scrollToSection(i); }}
                className={`text-[10px] font-mono font-semibold uppercase tracking-wider px-3 py-1.5 border transition-all duration-200 cursor-pointer ${
                  activeCategory === i
                    ? "bg-[#FF6600] border-[#FF6600] text-white"
                    : "border-gray-200 text-gray-500 bg-white/60 hover:border-[#FF6600]/30 hover:text-[#FF6600]"
                }`}
              >
                {s.category}
              </button>
            ))}
          </div>
        )}

        {/* Stats */}
        <div className="flex items-center gap-4 text-[10px] font-mono text-gray-400 pt-1">
          <span>{faqData.reduce((s, c) => s + c.items.length, 0)} pertanyaan</span>
          <span className="w-1 h-1 bg-gray-300" />
          <span>{faqData.length} kategori</span>
        </div>
      </motion.div>

      {/* RIGHT: Accordion Panel */}
      <motion.div
        initial={{ opacity: 0, x: 40 }}
        animate={{ opacity: 1, x: 0 }}
        transition={{ duration: 0.8, delay: 0.15, ease: "easeOut" }}
        className="w-full lg:w-1/2 z-10 self-stretch min-h-0"
      >
        <div
          ref={rightRef}
          className="h-full max-h-[65vh] lg:max-h-[70vh] overflow-y-auto pr-1 space-y-8 scroll-smooth"
          style={{ scrollbarWidth: 'thin', scrollbarColor: '#e5e7eb transparent' }}
        >
          {faqData.map((section, sIdx) => (
            <div
              key={sIdx}
              ref={(el) => { categoryRefs.current[sIdx] = el; }}
              data-cat-idx={sIdx}
            >
              {/* Category Header */}
              <div className="flex items-center gap-3 mb-4">
                <span className="text-xs font-mono font-bold uppercase tracking-widest text-[#FF6600]">
                  {section.category}
                </span>
                <span className="h-px flex-1 bg-gradient-to-r from-[#FF6600]/20 to-transparent" />
              </div>

              <div className="space-y-2">
                {section.items.map((item, iIdx) => {
                  const id = sIdx * 100 + iIdx;
                  const isOpen = openIndex === id;
                  return (
                    <div
                      key={iIdx}
                      ref={(el) => { itemRefs.current[id] = el; }}
                      className={`border transition-all duration-300 ${
                        isOpen
                          ? "border-[#FF6600]/40 bg-white shadow-md"
                          : "border-gray-200 bg-white/80 hover:border-gray-300 hover:bg-white hover:shadow-xs"
                      }`}
                    >
                      <button
                        type="button"
                        onClick={() => toggleItem(id)}
                        className="w-full flex items-center justify-between gap-3 px-5 py-4 text-left cursor-pointer"
                        aria-expanded={isOpen}
                      >
                        <span className={`text-sm leading-snug pr-2 transition-colors duration-200 ${
                          isOpen ? "text-[#FF6600] font-bold" : "text-gray-900 font-semibold"
                        }`}>
                          {item.q}
                        </span>
                        <span className={`shrink-0 w-6 h-6 flex items-center justify-center border transition-all duration-200 ${
                          isOpen
                            ? "border-[#FF6600]/30 bg-[#FF6600]/5"
                            : "border-gray-200"
                        }`}>
                          <ChevronDown className={`w-3.5 h-3.5 transition-all duration-200 ${
                            isOpen ? "text-[#FF6600] rotate-180" : "text-gray-400"
                          }`} />
                        </span>
                      </button>

                      <div
                        className={`grid transition-all duration-300 ease-[cubic-bezier(0.25,0.46,0.45,0.94)] ${
                          isOpen ? "grid-rows-[1fr] opacity-100" : "grid-rows-[0fr] opacity-0"
                        }`}
                      >
                        <div className="overflow-hidden">
                          <div className="px-5 pb-4 text-sm text-gray-500 leading-relaxed border-t border-[#FF6600]/10 pt-3.5">
                            {item.a}
                          </div>
                        </div>
                      </div>
                    </div>
                  );
                })}
              </div>
            </div>
          ))}

          {/* Footer — all Q&A is here */}
        </div>
      </motion.div>
    </div>
  );
}
