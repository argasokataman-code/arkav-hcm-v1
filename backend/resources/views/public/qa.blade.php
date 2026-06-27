@extends('layout.guest-legal')

@php
	$companyName = \App\Support\WebsiteSettings::businessCompanyName();
@endphp

@section('title', 'Pertanyaan Umum')
@section('content')

<div class="legal-badge">Pusat Bantuan</div>
<h1>Pertanyaan Umum (Q&A)</h1>
<p class="lead">Kumpulan pertanyaan dan jawaban seputar platform {{ $companyName }} — mulai dari absensi, cuti, payroll, hingga pengaturan akun.</p>
<p class="meta">Terakhir diperbarui: Juni 2026</p>

{{-- ================================================================= --}}
{{-- PLATFORM & UMUM --}}
{{-- ================================================================= --}}
<div class="section">
	<h2>Platform & Umum</h2>

	<div class="qa-item">
		<p class="q"><strong>Q: Apa itu {{ $companyName }}?</strong></p>
		<p class="a">A: {{ $companyName }} adalah platform Human Capital Management (HCM) berbasis cloud untuk perusahaan Indonesia. Fitur utama meliputi absensi (GPS & selfie), cuti online, payroll otomatis, penilaian kinerja, dan laporan analitik HR — semua dalam satu sistem terintegrasi.</p>
	</div>

	<div class="qa-item">
		<p class="q"><strong>Q: Apakah {{ $companyName }} berbasis web atau perlu instalasi?</strong></p>
		<p class="a">A: {{ $companyName }} adalah platform SaaS (Software as a Service) berbasis web. Tidak perlu instalasi server. Cukup daftar, atur perusahaan, dan karyawan bisa langsung menggunakan via browser di laptop atau smartphone.</p>
	</div>

	<div class="qa-item">
		<p class="q"><strong>Q: Browser apa yang didukung?</strong></p>
		<p class="a">A: {{ $companyName }} mendukung Google Chrome, Mozilla Firefox, Safari, dan Microsoft Edge versi terbaru. Pastikan GPS dan kamera aktif di browser untuk fitur absensi.</p>
	</div>

	<div class="qa-item">
		<p class="q"><strong>Q: Apakah ada aplikasi mobile?</strong></p>
		<p class="a">A: Saat ini {{ $companyName }} dapat diakses melalui browser di smartphone (mobile web) dengan pengalaman yang dioptimalkan untuk layar kecil. Aplikasi mobile native sedang dalam pengembangan.</p>
	</div>
</div>

{{-- ================================================================= --}}
{{-- ABSENSI --}}
{{-- ================================================================= --}}
<div class="section">
	<h2>Absensi</h2>

	<div class="qa-item">
		<p class="q"><strong>Q: Bagaimana cara absen masuk/pulang?</strong></p>
		<p class="a">A: Buka halaman absensi, pastikan GPS aktif, lalu klik tombol "Absen Masuk" atau "Absen Pulang". Sistem akan merekam lokasi, waktu, dan foto selfie Anda. Jika GPS tidak tersedia, Anda bisa memilih lokasi manual di peta.</p>
	</div>

	<div class="qa-item">
		<p class="q"><strong>Q: GPS tidak berfungsi, bagaimana cara absen?</strong></p>
		<p class="a">A: Pastikan browser Anda sudah mengizinkan akses lokasi. Jika masih tidak bisa, Anda bisa mengklik titik di peta sebagai lokasi manual. Atau, gunakan opsi "Absen Tanpa GPS" jika admin mengaktifkan fitur ini.</p>
	</div>

	<div class="qa-item">
		<p class="q"><strong>Q: Saya sudah absen tapi kenapa tidak tercatat?</strong></p>
		<p class="a">A: Kemungkinan koneksi internet terputus saat absen. Coba refresh halaman dan periksa apakah status sudah berubah. Jika masih tidak muncul, hubungi admin HR Anda.</p>
	</div>

	<div class="qa-item">
		<p class="q"><strong>Q: Apakah ada fitur lembur?</strong></p>
		<p class="a">A: Ya. Admin bisa mengaktifkan aturan lembur di pengaturan. Karyawan bisa mengajukan lembur, dan sistem akan menghitung otomatis berdasarkan tarif yang ditentukan perusahaan.</p>
	</div>

	<div class="qa-item">
		<p class="q"><strong>Q: Bisakah absen dari luar kantor (remote)?</strong></p>
		<p class="a">A: Bisa, jika admin mengaktifkan lokasi kerja remote atau tidak membatasi radius GPS. Pastikan Anda terhubung ke internet saat absen.</p>
	</div>
</div>

{{-- ================================================================= --}}
{{-- CUTI --}}
{{-- ================================================================= --}}
<div class="section">
	<h2>Cuti & Izin</h2>

	<div class="qa-item">
		<p class="q"><strong>Q: Bagaimana cara mengajukan cuti?</strong></p>
		<p class="a">A: Buka menu Cuti, klik "Ajukan Cuti", pilih jenis cuti (tahunan, sakit, dll), isi tanggal dan alasan, lalu kirim. Cuti akan diproses oleh atasan atau admin HR.</p>
	</div>

	<div class="qa-item">
		<p class="q"><strong>Q: Jenis cuti apa saja yang tersedia?</strong></p>
		<p class="a">A: Tergantung kebijakan perusahaan. Umumnya: cuti tahunan, cuti sakit, cuti melahirkan, cuti pernikahan, cuti penting, dan izin khusus. Admin bisa menambah jenis cuti sesuai kebutuhan.</p>
	</div>

	<div class="qa-item">
		<p class="q"><strong>Q: Kok pilihan jenis cuti kosong?</strong></p>
		<p class="a">A: Admin perusahaan belum mengaktifkan jenis cuti yang dibutuhkan. Hubungi admin HR untuk meminta diaktifkan.</p>
	</div>

	<div class="qa-item">
		<p class="q"><strong>Q: Kenapa cuti saya ditolak?</strong></p>
		<p class="a">A: Beberapa kemungkinan: kuota cuti habis, tanggal yang dipilih sudah termasuk hari libur nasional, atau aturan perusahaan tidak terpenuhi. Cek pesan penolakan di halaman cuti Anda.</p>
	</div>

	<div class="qa-item">
		<p class="q"><strong>Q: Berapa sisa cuti saya?</strong></p>
		<p class="a">A: Buka halaman Cuti, sisa kuota cuti Anda akan tampil di bagian atas halaman. Admin HR juga bisa melihat riwayat cuti seluruh karyawan.</p>
	</div>
</div>

{{-- ================================================================= --}}
{{-- PAYROLL & SLIP GAJI --}}
{{-- ================================================================= --}}
<div class="section">
	<h2>Payroll & Slip Gaji</h2>

	<div class="qa-item">
		<p class="q"><strong>Q: Bagaimana cara melihat slip gaji?</strong></p>
		<p class="a">A: Buka menu Payslip / Slip Gaji di dashboard. Pilih periode gaji yang ingin dilihat. Slip gaji akan muncul setelah admin melakukan finalisasi payroll bulan tersebut.</p>
	</div>

	<div class="qa-item">
		<p class="q"><strong>Q: Slip gaji saya kosong, kenapa?</strong></p>
		<p class="a">A: Penggajian bulan itu belum dikunci (finalisasi) oleh admin. Tunggu sampai admin menyelesaikan proses payroll. Jika sudah final tapi masih kosong, hubungi admin HR.</p>
	</div>

	<div class="qa-item">
		<p class="q"><strong>Q: Angka di slip gaji tidak sesuai?</strong></p>
		<p class="a">A: Hubungi admin HR atau bagian keuangan untuk klarifikasi. Data gaji hanya bisa diubah oleh admin — jangan mengubah data sendiri.</p>
	</div>

	<div class="qa-item">
		<p class="q"><strong>Q: Fitur payroll apa saja yang tersedia?</strong></p>
		<p class="a">A: {{ $companyName }} mendukung komponen gaji tetap & variabel, potongan BPJS, PPh 21, lembur, THR, PKWT compensation, dan payroll batch untuk perusahaan besar. Semua perhitungan dilakukan otomatis.</p>
	</div>

	<div class="qa-item">
		<p class="q"><strong>Q: Apakah ada fitur THR?</strong></p>
		<p class="a">A: Ya. {{ $companyName }} memiliki modul THR yang menghitung otomatis berdasarkan masa kerja dan gaji karyawan, sesuai aturan ketenagakerjaan Indonesia.</p>
	</div>
</div>

{{-- ================================================================= --}}
{{-- KARYAWAN & DATA --}}
{{-- ================================================================= --}}
<div class="section">
	<h2>Karyawan & Data Profil</h2>

	<div class="qa-item">
		<p class="q"><strong>Q: Bagaimana cara mengubah data pribadi (alamat, no telepon)?</strong></p>
		<p class="a">A: Buka profil Anda di menu Detail Karyawan. Beberapa data bisa diubah sendiri, seperti alamat dan nomor telepon. Data gaji, jabatan, dan data sensitif lainnya hanya bisa diubah admin.</p>
	</div>

	<div class="qa-item">
		<p class="q"><strong>Q: Data pribadi saya aman?</strong></p>
		<p class="a">A: {{ $companyName }} menerapkan enkripsi data, akses berbasis peran (RBAC), dan kepatuhan terhadap UU PDP. Data Anda hanya bisa diakses oleh pihak yang berwenang di perusahaan Anda.</p>
	</div>

	<div class="qa-item">
		<p class="q"><strong>Q: Bagaimana jika ada data karyawan yang salah?</strong></p>
		<p class="a">A: Hubungi admin HR untuk perbaikan data. Admin bisa mengedit data karyawan di halaman manajemen karyawan.</p>
	</div>
</div>

{{-- ================================================================= --}}
{{-- KINERJA & PENGEMBANGAN --}}
{{-- ================================================================= --}}
<div class="section">
	<h2>Kinerja & Pengembangan</h2>

	<div class="qa-item">
		<p class="q"><strong>Q: Apakah ada fitur penilaian kinerja?</strong></p>
		<p class="a">A: Ya. {{ $companyName }} memiliki modul Performance Appraisal, Performance Indicator, dan Performance Review. Admin bisa membuat siklus penilaian berkala untuk seluruh karyawan.</p>
	</div>

	<div class="qa-item">
		<p class="q"><strong>Q: Apa itu Goal Tracking?</strong></p>
		<p class="a">A: Goal Tracking memungkinkan karyawan dan atasan menetapkan, melacak, dan mengevaluasi sasaran kerja secara berkala. Cocok untuk menerapkan sistem OKR atau KPI di perusahaan.</p>
	</div>

	<div class="qa-item">
		<p class="q"><strong>Q: Apakah ada fitur pelatihan?</strong></p>
		<p class="a">A: Ya. Modul Training & Pengembangan memungkinkan admin membuat jadwal pelatihan, mendaftarkan peserta, dan mengevaluasi hasil pelatihan.</p>
	</div>
</div>

{{-- ================================================================= --}}
{{-- AKUN & LOGIN --}}
{{-- ================================================================= --}}
<div class="section">
	<h2>Akun & Login</h2>

	<div class="qa-item">
		<p class="q"><strong>Q: Tiba-tiba diminta login lagi?</strong></p>
		<p class="a">A: Itu wajar — sesi login biasanya habis dalam beberapa jam atau setelah browser ditutup untuk keamanan. Cukup login ulang.</p>
	</div>

	<div class="qa-item">
		<p class="q"><strong>Q: Lupa password, bagaimana?</strong></p>
		<p class="a">A: Klik "Lupa Kata Sandi" di halaman login. Email reset password akan dikirim ke alamat email terdaftar Anda.</p>
	</div>

	<div class="qa-item">
		<p class="q"><strong>Q: Muncul tulisan "Akses Ditolak" atau 403?</strong></p>
		<p class="a">A: Ini berarti halaman yang Anda buka khusus untuk admin atau role tertentu. Bukan error — ini perlindungan data. Hubungi admin jika Anda merasa seharusnya punya akses.</p>
	</div>

	<div class="qa-item">
		<p class="q"><strong>Q: Saya tidak bisa login sama sekali?</strong></p>
		<p class="a">A: Pastikan email dan password benar. Jika lupa password, gunakan fitur reset. Jika masih terkendala, hubungi admin perusahaan Anda — akun mungkin belum diaktifkan.</p>
	</div>
</div>

{{-- ================================================================= --}}
{{-- BILLING & LANGGANAN --}}
{{-- ================================================================= --}}
<div class="section">
	<h2>Billing & Langganan</h2>

	<div class="qa-item">
		<p class="q"><strong>Q: Bagaimana cara berlangganan {{ $companyName }}?</strong></p>
		<p class="a">A: Kunjungi halaman Paket & Harga di landing page, pilih paket yang sesuai, lalu ikuti proses pendaftaran. Anda bisa memilih billing bulanan atau tahunan.</p>
	</div>

	<div class="qa-item">
		<p class="q"><strong>Q: Apakah ada masa percobaan (trial)?</strong></p>
		<p class="a">A: Ya, jika tersedia paket trial. Anda bisa mencoba {{ $companyName }} secara gratis selama periode trial sebelum memutuskan berlangganan.</p>
	</div>

	<div class="qa-item">
		<p class="q"><strong>Q: Bagaimana cara upgrade paket?</strong></p>
		<p class="a">A: Buka menu langganan di dashboard admin. Pilih paket baru, dan sistem akan menghitung selisih harga secara prorata. Upgrade langsung aktif setelah pembayaran.</p>
	</div>

	<div class="qa-item">
		<p class="q"><strong>Q: Metode pembayaran apa yang didukung?</strong></p>
		<p class="a">A: {{ $companyName }} mendukung transfer bank, virtual account, dan metode pembayaran digital lainnya. Detail metode tersedia saat proses checkout.</p>
	</div>
</div>

{{-- ================================================================= --}}
{{-- DATA & KEAMANAN --}}
{{-- ================================================================= --}}
<div class="section">
	<h2>Data & Keamanan</h2>

	<div class="qa-item">
		<p class="q"><strong>Q: Bagaimana {{ $companyName }} melindungi data karyawan?</strong></p>
		<p class="a">A: Kami menerapkan enkripsi SSL/TLS untuk semua transmisi data, enkripsi data sensitif di database, akses berbasis peran (RBAC), dan audit log. Platform kami dirancang sesuai prinsip UU PDP.</p>
	</div>

	<div class="qa-item">
		<p class="q"><strong>Q: Apakah data saya bisa diekspor?</strong></p>
		<p class="a">A: Ya. {{ $companyName }} menyediakan fitur ekspor data ke format CSV dan Excel untuk sebagian besar modul — termasuk data karyawan, absensi, cuti, dan payroll. Admin juga bisa mengakses laporan analitik.</p>
	</div>

	<div class="qa-item">
		<p class="q"><strong>Q: Apakah ada backup data?</strong></p>
		<p class="a">A: Ya. Sistem melakukan backup database secara berkala. Namun, kami tetap merekomendasikan perusahaan untuk menyimpan arsip data penting secara mandiri.</p>
	</div>

	<div class="qa-item">
		<p class="q"><strong>Q: Siapa yang bisa melihat data saya?</strong></p>
		<p class="a">A: Data Anda hanya bisa diakses oleh Anda dan admin HR perusahaan Anda. {{ $companyName }} sebagai platform tidak mengakses data pengguna tanpa izin. Lihat Kebijakan Privasi untuk detail lebih lanjut.</p>
	</div>
</div>

{{-- ================================================================= --}}
{{-- TROUBLESHOOTING --}}
{{-- ================================================================= --}}
<div class="section">
	<h2>Troubleshooting & Dukungan</h2>

	<div class="qa-item">
		<p class="q"><strong>Q: Halaman tidak bisa diakses atau loading terus?</strong></p>
		<p class="a">A: Coba refresh halaman, periksa koneksi internet, atau gunakan browser lain. Jika masih bermasalah, coba clear cache browser atau hubungi dukungan teknis.</p>
	</div>

	<div class="qa-item">
		<p class="q"><strong>Q: Ada masalah teknis lain?</strong></p>
		<p class="a">A: Buat tiket bantuan lewat menu Tiket Bantuan di dashboard, atau hubungi admin perusahaan Anda. Tim dukungan {{ $companyName }} akan membantu menyelesaikan masalah.</p>
	</div>

	<div class="qa-item">
		<p class="q"><strong>Q: Ada pertanyaan yang tidak tercantum di sini?</strong></p>
		<p class="a">A: Silakan buka Pusat Bantuan di dashboard {{ $companyName }} setelah login, atau hubungi admin HR perusahaan Anda.</p>
	</div>
</div>

{{-- Additional inline styles for Q&A --}}
<style>
.qa-item {
	margin-bottom: 20px;
	padding-bottom: 20px;
	border-bottom: 1px solid #f0f0f0;
}
.qa-item:last-child {
	border-bottom: none;
	margin-bottom: 0;
	padding-bottom: 0;
}
.qa-item .q {
	font-size: 14px;
	color: #1A1D24;
	margin-bottom: 4px;
	line-height: 1.6;
}
.qa-item .a {
	font-size: 14px;
	color: #5b6474;
	margin-bottom: 0;
	line-height: 1.7;
	padding-left: 0;
}
.qa-item .a a {
	color: #FF6600;
	text-decoration: underline;
	text-underline-offset: 2px;
}
.section h2 {
	font-family: 'Plus Jakarta Sans', sans-serif;
	font-size: 22px;
	font-weight: 700;
	letter-spacing: -0.02em;
	color: #1A1D24;
	margin-bottom: 20px;
	padding-bottom: 8px;
	border-bottom: 2px solid rgba(255,102,0,0.12);
}
</style>

@endsection
