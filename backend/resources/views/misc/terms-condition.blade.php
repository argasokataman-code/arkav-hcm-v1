@extends('layout.guest-legal')

@php
	$page = 'terms-condition';
	$companyName = \App\Support\WebsiteSettings::businessCompanyName();
	$dpoEmail = (string) config('pdp.dpo_email', 'dpo@arcav.id');
@endphp

@section('title', 'Syarat dan Ketentuan')
@section('content')

<div class="mb-4">
	<span class="badge badge-soft-primary mb-3">Dokumen Legal</span>
	<h1 class="mb-2">Syarat dan Ketentuan {{ $companyName }}</h1>
	<p class="text-muted mb-0">Syarat ini mengatur penggunaan layanan SaaS ARCAV HCM, termasuk onboarding tenant, berlangganan, perlindungan data, dan batas tanggung jawab.</p>
</div>

<p class="text-muted small">Berlaku efektif: 19 Mei 2026 &mdash; Terakhir diperbarui: 19 Mei 2026</p>

<div class="mb-4">
	<p>
		Dokumen ini merupakan perjanjian antara Anda dan {{ $companyName }} untuk penggunaan platform Human Capital Management berbasis cloud,
		termasuk modul absensi, payroll, cuti, penilaian kinerja, billing langganan, dan layanan pendukung lainnya.
		Dengan mengakses, mendaftar, atau menggunakan layanan, Anda menyatakan telah membaca, memahami, dan menyetujui seluruh syarat berikut.
	</p>
</div>

<div class="mb-4">
	<h5 class="mb-2">1. Definisi</h5>
	<ul class="list-styled mb-0">
		<li class="d-flex align-items-baseline mb-2"><i class="ti ti-point-filled fs-10 me-1"></i><strong>Layanan</strong>&nbsp;berarti platform {{ $companyName }} dan seluruh modul pendukungnya.</li>
		<li class="d-flex align-items-baseline mb-2"><i class="ti ti-point-filled fs-10 me-1"></i><strong>Pelanggan</strong>&nbsp;berarti badan usaha, organisasi, atau pihak yang mendaftarkan company pada platform.</li>
		<li class="d-flex align-items-baseline mb-2"><i class="ti ti-point-filled fs-10 me-1"></i><strong>Owner</strong>&nbsp;berarti pengguna pertama yang mendaftarkan tenant dan bertanggung jawab atas administrasi awal akun.</li>
		<li class="d-flex align-items-baseline"><i class="ti ti-point-filled fs-10 me-1"></i><strong>Data Pribadi</strong>&nbsp;memiliki arti sebagaimana diatur dalam UU No. 27 Tahun 2022 tentang Pelindungan Data Pribadi.</li>
	</ul>
</div>

<div class="mb-4">
	<h5 class="mb-2">2. Penerimaan dan Perubahan Syarat</h5>
	<p class="mb-0">
		Kami dapat memperbarui syarat ini dari waktu ke waktu untuk menyesuaikan perkembangan produk, regulasi, atau kebutuhan operasional.
		Versi terbaru akan dipublikasikan pada halaman ini. Penggunaan berkelanjutan atas layanan setelah perubahan berlaku dianggap sebagai persetujuan atas versi terbaru.
	</p>
</div>

<div class="mb-4">
	<h5 class="mb-2">3. Pendaftaran dan Akun</h5>
	<ul class="list-styled mb-0">
		<li class="d-flex align-items-baseline mb-2"><i class="ti ti-point-filled fs-10 me-1"></i>Pendaftaran tenant hanya boleh dilakukan oleh pihak yang berwenang mewakili perusahaan atau organisasi terkait.</li>
		<li class="d-flex align-items-baseline mb-2"><i class="ti ti-point-filled fs-10 me-1"></i>Anda wajib memberikan data yang akurat, mutakhir, dan dapat dipertanggungjawabkan saat onboarding.</li>
		<li class="d-flex align-items-baseline mb-2"><i class="ti ti-point-filled fs-10 me-1"></i>Anda bertanggung jawab menjaga kerahasiaan kredensial login, termasuk password, kode company, dan akses admin internal.</li>
		<li class="d-flex align-items-baseline"><i class="ti ti-point-filled fs-10 me-1"></i>Anda wajib segera memberi tahu kami apabila ada dugaan akses tidak sah, kebocoran kredensial, atau penyalahgunaan akun.</li>
	</ul>
</div>

<div class="mb-4">
	<h5 class="mb-2">4. Langganan, Invoicing, dan Pembayaran</h5>
	<ul class="list-styled mb-0">
		<li class="d-flex align-items-baseline mb-2"><i class="ti ti-point-filled fs-10 me-1"></i>Paket, harga, limit fitur, dan siklus billing mengikuti katalog aktif yang dipublikasikan pada flow onboarding dan invoice resmi.</li>
		<li class="d-flex align-items-baseline mb-2"><i class="ti ti-point-filled fs-10 me-1"></i>Mode <code>pending_payment</code> berarti akun company belum aktif penuh sampai invoice terkait dibayar atau divalidasi sesuai flow billing yang berlaku.</li>
		<li class="d-flex align-items-baseline mb-2"><i class="ti ti-point-filled fs-10 me-1"></i>Keterlambatan pembayaran dapat menyebabkan pembatasan akses billing-only, penangguhan fitur, atau penghentian layanan sesuai kebijakan operasional kami.</li>
		<li class="d-flex align-items-baseline"><i class="ti ti-point-filled fs-10 me-1"></i>Biaya yang telah jatuh tempo dan sudah diproses melalui payment gateway tidak dapat dibatalkan secara sepihak, kecuali diwajibkan oleh hukum atau disetujui tertulis oleh kami.</li>
	</ul>
</div>

<div class="mb-4">
	<h5 class="mb-2">5. Kewajiban Pengguna</h5>
	<ul class="list-styled mb-0">
		<li class="d-flex align-items-baseline mb-2"><i class="ti ti-point-filled fs-10 me-1"></i>Menggunakan layanan hanya untuk tujuan bisnis, administrasi SDM, dan aktivitas yang sah secara hukum.</li>
		<li class="d-flex align-items-baseline mb-2"><i class="ti ti-point-filled fs-10 me-1"></i>Tidak mengunggah malware, skrip berbahaya, data palsu, atau materi yang melanggar hak pihak lain.</li>
		<li class="d-flex align-items-baseline mb-2"><i class="ti ti-point-filled fs-10 me-1"></i>Tidak melakukan reverse engineering, scraping massal, atau percobaan mengakses tenant lain tanpa izin.</li>
		<li class="d-flex align-items-baseline"><i class="ti ti-point-filled fs-10 me-1"></i>Memastikan dasar hukum internal perusahaan tersedia untuk pemrosesan data karyawan yang dimasukkan ke sistem.</li>
	</ul>
</div>

<div class="mb-4">
	<h5 class="mb-2">6. Data Pribadi dan Kepatuhan UU PDP</h5>
	<p>
		Pemrosesan data pribadi pada layanan ini tunduk pada <strong>UU No. 27 Tahun 2022 tentang Pelindungan Data Pribadi</strong>,
		serta kebijakan privasi {{ $companyName }}. Dengan menggunakan layanan, Anda mengakui bahwa pemrosesan data untuk onboarding,
		manajemen karyawan, payroll, absensi, penilaian kinerja, dan billing dilakukan sesuai tujuan yang dijelaskan dalam
		<a href="{{ route('privacy-policy') }}">Kebijakan Privasi</a>.
	</p>
	<p class="mb-0">
		Sebagai tenant, Anda bertanggung jawab memastikan persetujuan, pemberitahuan, atau dasar hukum lain telah dipenuhi sebelum memasukkan data karyawan atau pihak ketiga ke dalam platform.
	</p>
</div>

<div class="mb-4">
	<h5 class="mb-2">7. Hak Kekayaan Intelektual</h5>
	<p class="mb-0">
		Seluruh kode sumber, desain, dokumentasi, merek, logo, konfigurasi, dan materi pendukung layanan merupakan milik {{ $companyName }} atau pemberi lisensinya.
		Anda tidak diperkenankan menyalin, menjual ulang, mendistribusikan ulang, atau membuat turunan layanan tanpa persetujuan tertulis sebelumnya.
	</p>
</div>

<div class="mb-4">
	<h5 class="mb-2">8. Ketersediaan Layanan dan SLA</h5>
	<p>
		Kami berupaya menjaga layanan tetap tersedia dan aman, namun tidak menjamin layanan bebas gangguan setiap saat.
		Pemeliharaan terjadwal, force majeure, gangguan vendor pihak ketiga, dan insiden keamanan dapat memengaruhi ketersediaan secara sementara.
	</p>
	<p class="mb-0">
		Apabila kami memberlakukan SLA komersial tertentu, SLA tersebut hanya berlaku jika dinyatakan secara tertulis pada kontrak atau paket enterprise terkait.
	</p>
</div>

<div class="mb-4">
	<h5 class="mb-2">9. Pembatasan Tanggung Jawab</h5>
	<p>
		Sepanjang diizinkan hukum yang berlaku, {{ $companyName }} tidak bertanggung jawab atas kerugian tidak langsung, kehilangan keuntungan, kehilangan data akibat kesalahan input tenant,
		atau gangguan bisnis yang timbul dari penggunaan layanan, termasuk integrasi atau layanan pihak ketiga.
	</p>
	<p class="mb-0">
		Tanggung jawab kami secara agregat atas klaim yang timbul dari layanan dibatasi sebesar total biaya langganan yang dibayarkan tenant dalam 12 bulan terakhir sebelum kejadian,
		kecuali jika hukum mewajibkan batas yang berbeda.
	</p>
</div>

<div class="mb-4">
	<h5 class="mb-2">10. Hukum yang Berlaku</h5>
	<p class="mb-0">
		Syarat ini diatur dan ditafsirkan berdasarkan hukum Republik Indonesia, tanpa memperhatikan pertentangan asas hukum antar yurisdiksi.
	</p>
</div>

<div class="mb-4">
	<h5 class="mb-2">11. Penyelesaian Sengketa</h5>
	<p>
		Setiap sengketa akan diselesaikan terlebih dahulu melalui musyawarah dalam itikad baik paling lama 30 hari kalender sejak pemberitahuan tertulis diterima.
	</p>
	<p class="mb-0">
		Jika tidak tercapai penyelesaian, para pihak sepakat membawa sengketa ke forum yang berwenang di Indonesia sesuai ketentuan hukum yang berlaku.
	</p>
</div>

<div class="mb-4">
	<h5 class="mb-2">12. Pengakhiran</h5>
	<ul class="list-styled mb-0">
		<li class="d-flex align-items-baseline mb-2"><i class="ti ti-point-filled fs-10 me-1"></i>Anda dapat menghentikan penggunaan layanan sesuai flow cancellation atau dengan menghubungi tim kami.</li>
		<li class="d-flex align-items-baseline mb-2"><i class="ti ti-point-filled fs-10 me-1"></i>Kami berhak menangguhkan atau mengakhiri layanan jika terjadi pelanggaran material, penyalahgunaan sistem, atau tunggakan pembayaran yang tidak diselesaikan.</li>
		<li class="d-flex align-items-baseline"><i class="ti ti-point-filled fs-10 me-1"></i>Kewajiban pembayaran yang sudah jatuh tempo dan kewajiban hukum terkait retensi data tetap berlaku setelah pengakhiran sejauh diwajibkan hukum.</li>
	</ul>
</div>

<div>
	<h5 class="mb-2">13. Hubungi Kami</h5>
	<ul class="list-styled mb-0">
		<li class="d-flex align-items-baseline mb-2"><i class="ti ti-point-filled fs-10 me-1"></i><strong>Email DPO:</strong>&nbsp;<a href="mailto:{{ $dpoEmail }}">{{ $dpoEmail }}</a></li>
		<li class="d-flex align-items-baseline mb-2"><i class="ti ti-point-filled fs-10 me-1"></i><strong>Halaman privasi:</strong>&nbsp;<a href="{{ route('privacy-policy') }}">{{ route('privacy-policy') }}</a></li>
		<li class="d-flex align-items-baseline"><i class="ti ti-point-filled fs-10 me-1"></i><strong>Halaman onboarding:</strong>&nbsp;<a href="{{ route('landing', ['openOnboarding' => 1]) }}">{{ route('landing', ['openOnboarding' => 1]) }}</a></li>
	</ul>
</div>

@endsection