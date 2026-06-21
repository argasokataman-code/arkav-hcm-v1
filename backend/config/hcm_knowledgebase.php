<?php

/**
 * Bantuan dalam aplikasi (Knowledge Base). Konten statis tepercaya dari repo.
 * Semua konten ditulis dengan bahasa sehari-hari agar mudah dipahami siapa pun.
 * Slug artikel harus unik seluruh kategori.
 */
return [
    'categories' => [
        // ====================================================================
        // KATEGORI: MEMULAI
        // ====================================================================
        [
            'slug' => 'memulai',
            'title' => 'Memulai dan Akun',
            'icon' => 'ti ti-book',
            'visible_to' => ['authenticated'],
            'description' => 'Panduan masuk aplikasi, mengenal perbedaan admin dan karyawan, serta cara mengatasi masalah login.',
            'articles' => [
                [
                    'slug' => 'cara-login',
                    'visible_to' => ['authenticated'],
                    'title' => 'Cara masuk ke Arcav HCM',
                    'reading_minutes' => 4,
                    'excerpt' => 'Langkah mudah masuk aplikasi, memastikan perusahaan yang aktif benar, dan apa yang harus dilakukan kalau tiba-tiba diminta login lagi.',
                    'body_html' => <<<'HTML'
<p class="fs-14 fw-normal mb-3">Masuk ke Arcav HCM lewat halaman <a href="/login">login</a> menggunakan email dan kata sandi yang sudah diberikan oleh admin perusahaan Anda. Setelah berhasil masuk, sistem akan mengingat Anda selama beberapa waktu — jadi tidak perlu login ulang setiap kali membuka halaman.</p>

<h6 class="fs-14 fw-semibold mb-2">Kalau perusahaan Anda punya beberapa cabang atau entitas</h6>
<p class="fs-14 fw-normal mb-3">Pastikan Anda sudah memilih perusahaan yang benar di sudut kanan atas layar. Soalnya, data cuti, absensi, dan gaji yang tampil itu tergantung perusahaan yang sedang aktif. Kalau tiba-tiba data kosong padahal biasanya ada, coba periksa pilihan perusahaan di pojok kanan atas — mungkin sedang milih perusahaan yang salah.</p>

<h6 class="fs-14 fw-semibold mb-2">Tiba-tiba diminta login lagi</h6>
<p class="fs-14 fw-normal mb-3">Ini wajar. Biasanya karena Anda sudah cukup lama tidak memakai aplikasi atau ada pembaruan sistem. Cukup login ulang seperti biasa. Kalau tampilannya terlihat aneh setelah pembaruan, coba muat ulang halaman dengan menekan <strong>Ctrl + Shift + R</strong> (Windows) atau <strong>Cmd + Shift + R</strong> (Mac).</p>

<h6 class="fs-14 fw-semibold mb-2">Tips login yang baik</h6>
<ul class="knowledgebase ps-3 mb-0">
<li class="d-flex align-items-baseline mb-2"><i class="ti ti-point-filled me-1"></i> Gunakan akun yang sesuai peran Anda — akun admin dan akun karyawan biasa menampilkan menu yang berbeda.</li>
<li class="d-flex align-items-baseline mb-2"><i class="ti ti-point-filled me-1"></i> Jangan pinjamkan akun ke orang lain. Setiap orang sebaiknya punya akun sendiri biar data aktivitas tercatat dengan benar.</li>
<li class="d-flex align-items-baseline mb-2"><i class="ti ti-point-filled me-1"></i> Kalau lupa kata sandi, gunakan tautan <strong>Lupa Kata Sandi</strong> di halaman login. Nanti akan dikirim email untuk meresetnya.</li>
</ul>
HTML,
                ],
                [
                    'slug' => 'perbedaan-admin-dan-karyawan',
                    'visible_to' => ['authenticated'],
                    'title' => 'Apa bedanya admin HCM dan karyawan biasa?',
                    'reading_minutes' => 5,
                    'excerpt' => 'Siapa yang bisa mengelola data semua orang, siapa yang hanya bisa melihat data sendiri, dan apa yang terjadi kalau akses ditolak.',
                    'body_html' => <<<'HTML'
<p class="fs-14 fw-normal mb-3">Arcav HCM membagi pengguna menjadi dua kelompok utama: <strong>Admin HCM</strong> dan <strong>Karyawan</strong>. Perbedaan ini menentukan menu apa yang muncul di layar Anda dan apa saja yang bisa dilakukan.</p>

<h6 class="fs-14 fw-semibold mb-2">Admin HCM — bisa mengelola semua data</h6>
<p class="fs-14 fw-normal mb-3">Admin bisa melihat dan mengubah data seluruh karyawan di perusahaan. Mulai dari daftar karyawan, mengatur jadwal shift, menjalankan penggajian, menyetujui cuti, mengubah pengaturan sistem, dan melihat laporan. Kalau menu-menu ini tidak muncul di akun Anda, kemungkinan akun Anda belum diatur sebagai admin oleh admin utama perusahaan.</p>

<h6 class="fs-14 fw-semibold mb-2">Karyawan — hanya data diri sendiri</h6>
<p class="fs-14 fw-normal mb-3">Karyawan biasa hanya bisa mengakses data milik pribadi: absensi, pengajuan cuti, slip gaji, dan tiket bantuan. Halaman seperti daftar seluruh karyawan atau pengaturan sistem tidak bisa dibuka oleh akun karyawan.</p>

<h6 class="fs-14 fw-semibold mb-2">Kalau akses ditolak, itu bukan error</h6>
<p class="fs-14 fw-normal mb-3">Sistem sengaja membatasi halaman tertentu agar data setiap karyawan aman. Misalnya, halaman daftar gaji semua orang hanya boleh dibuka admin. Kalau Anda bukan admin lalu mencoba membukanya, sistem akan menolak atau mengalihkan ke halaman lain. Ini bukan kerusakan — ini perlindungan data.</p>

<div class="table-responsive mb-0"><table class="table table-sm table-bordered fs-13 mb-0"><thead><tr><th>Situasi</th><th>Yang Terjadi</th></tr></thead><tbody>
<tr><td>Karyawan membuka halaman admin</td><td>Ditolak atau dialihkan ke halaman lain</td></tr>
<tr><td>Admin membuka halaman karyawan</td><td>Biasanya dialihkan ke tampilan admin yang setara</td></tr>
<tr><td>Sesi login habis</td><td>Kembali ke halaman login</td></tr>
</tbody></table></div>
HTML,
                ],
                [
                    'slug' => 'daftar-modul-dari-halaman-pages',
                    'visible_to' => ['authenticated'],
                    'title' => 'Mencari fitur tertentu? Coba halaman /pages',
                    'reading_minutes' => 3,
                    'excerpt' => 'Halaman /pages berisi daftar lengkap semua modul dan halaman yang tersedia. Cocok untuk admin baru yang ingin tahu apa saja fitur Arcav HCM.',
                    'body_html' => <<<'HTML'
<p class="fs-14 fw-normal mb-3">Buka halaman <a href="/pages">/pages</a> kalau Anda ingin melihat daftar lengkap semua fitur Arcav HCM yang tersedia. Halaman ini berguna banget buat admin baru yang masih penasaran: "Apa aja sih yang bisa dilakukan aplikasi ini?"</p>
<p class="fs-14 fw-normal mb-0">Cukup klik nama halaman yang ingin dibuka. Tapi ingat: halaman yang khusus admin tidak akan bisa dibuka oleh akun karyawan biasa — nanti otomatis dialihkan.</p>
HTML,
                ],
                [
                    'slug' => 'mengatasi-masalah-akses',
                    'visible_to' => ['authenticated'],
                    'title' => 'Mengatasi masalah: tidak bisa masuk, ditolak, atau data ditolak sistem',
                    'reading_minutes' => 4,
                    'excerpt' => 'Ada tiga masalah yang paling sering dialami: tiba-tiba minta login lagi, akses ditolak, atau isian ditolak. Tenang, semua ada solusinya.',
                    'body_html' => <<<'HTML'
<p class="fs-14 fw-normal mb-3">Tiga masalah ini paling sering dikeluhkan pengguna. Yuk kita bahas satu per satu.</p>

<h6 class="fs-14 fw-semibold mb-2">1. Tiba-tiba kembali ke halaman login</h6>
<p class="fs-14 fw-normal mb-3">Artinya sesi login Anda sudah habis atau Anda belum masuk. Solusinya: login lagi dengan akun Anda. Kalau ini terjadi berulang kali dalam waktu singkat, hubungi admin — mungkin ada masalah dengan akun Anda.</p>

<h6 class="fs-14 fw-semibold mb-2">2. Muncul pesan "Akses ditolak"</h6>
<p class="fs-14 fw-normal mb-3">Artinya halaman atau tombol yang Anda coba butuh wewenang lebih tinggi. Misalnya, halaman penggajian hanya untuk admin HCM. Kalau Anda merasa seharusnya punya akses, hubungi admin HR untuk disesuaikan peran akun Anda.</p>

<h6 class="fs-14 fw-semibold mb-2">3. Data yang diisi ditolak sistem</h6>
<p class="fs-14 fw-normal mb-3">Artinya ada isian yang tidak sesuai aturan, misalnya: tanggal cuti sudah lewat, absen tanpa izin lokasi, atau konfirmasi kata sandi tidak cocok. Tenang, sistem akan menunjukkan bagian mana yang perlu diperbaiki — baca saja pesan yang muncul di layar.</p>

<ul class="knowledgebase ps-3 mb-0">
<li class="d-flex align-items-baseline mb-2"><i class="ti ti-point-filled me-1"></i> Pastikan browser Anda mengizinkan akses lokasi kalau mau absen.</li>
<li class="d-flex align-items-baseline mb-2"><i class="ti ti-point-filled me-1"></i> Jangan lupa isi semua kolom wajib sebelum menekan tombol simpan — biasanya ditandai bintang merah.</li>
<li class="d-flex align-items-baseline mb-2"><i class="ti ti-point-filled me-1"></i> Kalau masih bermasalah setelah mencoba langkah di atas, buat tiket bantuan lewat <a href="/tickets-employee">halaman tiket</a>.</li>
</ul>
HTML,
                ],
                [
                    'slug' => 'checklist-admin-baru',
                    'visible_to' => ['admin'],
                    'title' => 'Checklist untuk admin baru (minggu pertama)',
                    'reading_minutes' => 8,
                    'excerpt' => 'Urutan kerja yang disarankan: siapkan data master dulu, baru isi data karyawan, lalu atur cuti dan absensi, terakhir urusan gaji. Biar tim tidak bolak-balik tanya.',
                    'body_html' => <<<'HTML'
<p class="fs-14 fw-normal mb-3">Panduan ini buat admin baru yang baru pertama kali mengelola Arcav HCM. Ikuti urutannya biar tidak bingung.</p>

<h6 class="fs-14 fw-semibold mb-2">Hari 1 — siapkan fondasi data</h6>
<ol class="fs-14 mb-3">
<li>Buka <a href="/departments">Departemen</a> dan <a href="/designations">Jabatan</a> → buat struktur organisasi minimal satu level (misalnya: HR, Keuangan, Produksi).</li>
<li>Buka <a href="/policy">Kebijakan</a> → masukkan aturan perusahaan yang dipakai modul lain (aturan cuti, aturan disiplin) kalau ada.</li>
<li>Buka <a href="/leave-type">Jenis Cuti</a> dan <a href="/leave-settings">Pengaturan Cuti</a> → aktifkan jenis cuti yang tersedia (cuti tahunan, cuti sakit, dll) dan atur kuota serta siapa yang menyetujuinya.</li>
<li>Buka <a href="/holidays">Hari Libur</a> → isi libur nasional dan libur khusus perusahaan.</li>
</ol>

<h6 class="fs-14 fw-semibold mb-2">Hari 2 — data karyawan dan akses</h6>
<ol class="fs-14 mb-3">
<li>Buka <a href="/employees">Karyawan</a> → tambahkan data karyawan satu per satu atau impor dari file. Cek satu profil di <a href="/employee-details">Detail Karyawan</a> untuk memastikan data benar.</li>
<li>Buka <a href="/shift-master">Master Shift</a> → atur jam kerja. Lalu buka <a href="/schedule-timing">Jadwal Karyawan</a> → pasangkan shift ke setiap karyawan.</li>
<li>Buka <a href="/users">Pengguna</a> dan <a href="/roles-permissions">Peran & Izin</a> → pastikan setiap akun sudah punya wewenang yang tepat.</li>
</ol>

<h6 class="fs-14 fw-semibold mb-2">Hari 3–5 — uji coba operasional</h6>
<ol class="fs-14 mb-3">
<li>Coba absen dengan akun karyawan sungguhan lewat <a href="/attendance-employee">Absensi Karyawan</a> — pastikan GPS dan selfie berfungsi.</li>
<li>Coba ajukan cuti lewat <a href="/leaves-employee">Cuti Karyawan</a>, lalu setujui di halaman <a href="/leaves">Cuti Admin</a>.</li>
<li>Siapkan komponen gaji di <a href="/payroll">Payroll</a>, lalu atur kompensasi tiap karyawan di <a href="/employee-salary">Gaji Karyawan</a>. Terakhir, coba hitung gaji di <a href="/payroll-run">Proses Payroll</a> periode uji coba.</li>
</ol>

<p class="fs-14 fw-normal mb-0">Setelah semuanya stabil, arahkan karyawan ke halaman <strong>Pusat Bantuan</strong> ini kalau mereka punya pertanyaan — semoga pertanyaan berulang berkurang.</p>
HTML,
                ],
                [
                    'slug' => 'panduan-admin-harian',
                    'visible_to' => ['admin'],
                    'title' => 'Panduan lengkap: admin dari login sampai operasional harian',
                    'reading_minutes' => 10,
                    'excerpt' => 'Panduan langkah demi langkah untuk admin baru: masuk aplikasi, cek dashboard, siapkan data master, kelola karyawan, proses cuti dan absensi, sampai urusan gaji.',
                    'body_html' => <<<'HTML'
<p class="fs-14 fw-normal mb-3">Panduan ini khusus untuk <strong>admin HR atau operator HCM</strong> yang baru pertama kali memakai Arcav HCM. Ikuti langkah-langkah di bawah biar Anda paham alur kerja tanpa harus bolak-balik buka menu.</p>

<h6 class="fs-14 fw-semibold mb-2">1. Login dan pastikan perusahaan yang aktif sudah benar</h6>
<ol class="fs-14 mb-3">
<li>Masuk lewat <a href="/login">halaman login</a> dengan akun admin yang sudah diberikan.</li>
<li>Setelah masuk, buka halaman <a href="/index">Beranda</a>. Pastikan menu di samping kiri muncul lengkap. Kalau menu Penggajian, Karyawan, atau Pengaturan tidak muncul, kemungkinan akun Anda belum punya wewenang admin yang penuh.</li>
<li>Kalau data yang terlihat kosong padahal seharusnya ada, periksa perusahaan yang aktif di pojok kanan atas — mungkin salah pilih perusahaan.</li>
</ol>

<h6 class="fs-14 fw-semibold mb-2">2. Dashboard hanya ringkasan — jangan berhenti di situ</h6>
<p class="fs-14 fw-normal mb-3">Halaman <a href="/index">Beranda</a> hanya menampilkan ringkasan. Gunakan untuk lihat gambaran umum, lalu masuk ke modul yang sebenarnya dari menu samping. Banyak admin baru yang bingung karena mengira semua pekerjaan dilakukan dari beranda — padahal tidak.</p>

<h6 class="fs-14 fw-semibold mb-2">3. Siapkan data master dulu sebelum urusan transaksi</h6>
<ol class="fs-14 mb-3">
<li>Buka <a href="/departments">Departemen</a> dan <a href="/designations">Jabatan</a> untuk membuat struktur organisasi.</li>
<li>Buka <a href="/policy">Kebijakan</a> kalau perusahaan punya aturan HR yang perlu dicatat di sistem.</li>
<li>Kalau akan pakai fitur cuti, isi dulu <a href="/leave-type">Jenis Cuti</a>, <a href="/leave-settings">Pengaturan Cuti</a>, dan <a href="/holidays">Hari Libur</a>.</li>
<li>Kalau akan pakai absensi, siapkan <a href="/shift-master">Master Shift</a> dulu, lalu pasangkan ke setiap karyawan lewat <a href="/schedule-timing">Jadwal Karyawan</a>.</li>
</ol>

<h6 class="fs-14 fw-semibold mb-2">4. Kelola data karyawan</h6>
<ol class="fs-14 mb-3">
<li>Masuk ke <a href="/employees">Karyawan</a> untuk melihat daftar utama.</li>
<li>Tambah atau impor data, lalu buka satu profil lewat <a href="/employee-details">Detail Karyawan</a> untuk memeriksa data departemen, jabatan, kontak, dan status kerja.</li>
<li>Kalau karyawan akan langsung digaji, cek juga kompensasinya di <a href="/employee-salary">Gaji Karyawan</a>.</li>
</ol>

<h6 class="fs-14 fw-semibold mb-2">5. Operasional harian</h6>
<ul class="knowledgebase ps-3 mb-3">
<li class="d-flex align-items-baseline mb-2"><i class="ti ti-point-filled me-1"></i> <strong>Cuti:</strong> Pantau pengajuan di <a href="/leaves">Cuti Admin</a>, setujui atau tolak sesuai aturan perusahaan.</li>
<li class="d-flex align-items-baseline mb-2"><i class="ti ti-point-filled me-1"></i> <strong>Absensi:</strong> Cek rekap di <a href="/attendance-admin">Absensi Admin</a>, cocokkan dengan <a href="/timesheets">Timesheet</a> kalau ada selisih jam.</li>
<li class="d-flex align-items-baseline mb-2"><i class="ti ti-point-filled me-1"></i> <strong>Tiket:</strong> Kalau perusahaan pakai fitur bantuan internal, pantau tiket di <a href="/tickets-admin">Tiket Admin</a>.</li>
</ul>

<h6 class="fs-14 fw-semibold mb-2">6. Tutup periode dengan penggajian</h6>
<ol class="fs-14 mb-3">
<li>Pastikan data absensi, lembur, dan cuti periode itu sudah beres.</li>
<li>Masuk ke <a href="/payroll-run">Proses Payroll</a> untuk menghitung draft gaji.</li>
<li>Periksa angka per karyawan. Kalau sudah cocok, kunci (finalisasi) gaji tersebut. Kalau sudah dikunci, data tidak bisa diubah lagi — jadi periksa baik-baik.</li>
<li>Setelah final, lanjutkan ke pencairan gaji sesuai prosedur perusahaan.</li>
<li>Beri tahu karyawan untuk cek slip gaji mereka di <a href="/payslip">Slip Gaji</a>.</li>
</ol>

<h6 class="fs-14 fw-semibold mb-2">7. Kebiasaan admin yang baik</h6>
<ul class="knowledgebase ps-3 mb-0">
<li class="d-flex align-items-baseline mb-2"><i class="ti ti-point-filled me-1"></i> Jangan mengubah data master besar-besaran di tengah proses penggajian tanpa koordinasi dengan bagian keuangan.</li>
<li class="d-flex align-items-baseline mb-2"><i class="ti ti-point-filled me-1"></i> Setelah mengubah pengaturan penting, uji coba dulu dengan satu data contoh sampai benar-benar muncul di layar.</li>
<li class="d-flex align-items-baseline mb-2"><i class="ti ti-point-filled me-1"></i> Kalau bingung urutan modul, balik lagi ke artikel ini sebelum loncat ke artikel panduan modul lainnya.</li>
</ul>
HTML,
                ],
                [
                    'slug' => 'panduan-karyawan-harian',
                    'visible_to' => ['employee'],
                    'title' => 'Panduan lengkap: karyawan menggunakan aplikasi untuk absen, cuti, slip gaji, dan tiket',
                    'reading_minutes' => 8,
                    'excerpt' => 'Panduan langkah demi langkah untuk karyawan: mulai dari login, absen setiap hari, ajukan cuti, cek slip gaji, sampai minta bantuan lewat tiket.',
                    'body_html' => <<<'HTML'
<p class="fs-14 fw-normal mb-3">Artikel ini khusus untuk <strong>karyawan atau pengguna biasa</strong> (bukan admin). Kalau Anda bukan admin, tugas Anda sehari-hari di aplikasi ini cukup simpel: absen, ajukan cuti kalau perlu, cek slip gaji, dan kalau ada masalah buat tiket bantuan. Yuk ikuti langkah-langkahnya.</p>

<h6 class="fs-14 fw-semibold mb-2">1. Login dan buka dashboard pribadi</h6>
<ol class="fs-14 mb-3">
<li>Masuk lewat <a href="/login">halaman login</a> pakai email dan kata sandi dari kantor.</li>
<li>Setelah berhasil masuk, biasanya Anda akan diarahkan ke <a href="/employee-dashboard">Dashboard Karyawan</a> — ini halaman pribadi Anda.</li>
<li>Kalau yang muncul malah dashboard penuh menu, mungkin akun Anda adalah akun admin — bukan akun karyawan biasa. Kalau ragu, tanya ke admin HR.</li>
</ol>

<h6 class="fs-14 fw-semibold mb-2">2. Absen setiap hari</h6>
<ol class="fs-14 mb-3">
<li>Buka <a href="/attendance-employee">Absensi Karyawan</a>.</li>
<li>Nanti browser akan minta izin untuk mengakses lokasi Anda — <strong>izinkan ya</strong>. Ini penting biar sistem tahu Anda benar-benar di kantor. Kalau tidak diizinkan, absensi Anda mungkin tidak tercatat.</li>
<li>Tekan tombol <strong>Masuk</strong> (punch in) saat datang, dan tombol <strong>Pulang</strong> (punch out) saat pulang.</li>
<li>Kalau perusahaan mewajibkan selfie, lakukan setelah berhasil absen. Kalau selfie ditolak, biasanya karena Anda belum melakukan absen masuk terlebih dahulu.</li>
</ol>

<h6 class="fs-14 fw-semibold mb-2">3. Ajukan cuti kalau perlu</h6>
<ol class="fs-14 mb-3">
<li>Buka <a href="/leaves-employee">Cuti Karyawan</a>.</li>
<li>Pilih jenis cuti (tahunan, sakit, dll), isi tanggal mulai dan selesai, serta tulis alasannya.</li>
<li>Setelah dikirim, statusnya akan <strong>Menunggu</strong> — artinya masih menunggu persetujuan atasan atau admin HR.</li>
<li>Kalau pilihan jenis cutinya kosong, berarti admin perusahaan belum mengaktifkan jenis cuti yang Anda butuhkan. Hubungi admin HR.</li>
</ol>

<h6 class="fs-14 fw-semibold mb-2">4. Cek slip gaji setelah gajian</h6>
<ol class="fs-14 mb-3">
<li>Buka <a href="/payslip">Slip Gaji</a>.</li>
<li>Pilih bulan dan tahun yang ingin dilihat.</li>
<li>Kalau datanya masih kosong, biasanya penggajian bulan itu belum dikunci oleh admin — tunggu saja atau tanya ke admin.</li>
</ol>

<h6 class="fs-14 fw-semibold mb-2">5. Minta bantuan lewat tiket kalau ada masalah</h6>
<ol class="fs-14 mb-3">
<li>Buka <a href="/tickets-employee">Tiket Bantuan</a>.</li>
<li>Buat tiket baru untuk masalah seperti: absen tidak masuk, cuti tidak bisa diajukan, atau pertanyaan administratif lainnya.</li>
<li>Tulis masalah dengan jelas, kalau perlu lampirkan bukti (screenshot, dll).</li>
</ol>

<h6 class="fs-14 fw-semibold mb-2">6. Hal penting yang perlu diketahui karyawan</h6>
<ul class="knowledgebase ps-3 mb-0">
<li class="d-flex align-items-baseline mb-2"><i class="ti ti-point-filled me-1"></i> Anda <strong>tidak bisa</strong> melihat atau mengubah data karyawan lain — hanya admin yang bisa.</li>
<li class="d-flex align-items-baseline mb-2"><i class="ti ti-point-filled me-1"></i> Kalau muncul tulisan "Akses Ditolak", itu wajar — biasanya karena halaman tersebut memang khusus admin.</li>
<li class="d-flex align-items-baseline mb-2"><i class="ti ti-point-filled me-1"></i> Untuk masalah akun atau wewenang, hubungi admin HR internal perusahaan Anda terlebih dahulu.</li>
</ul>
HTML,
                ],
                [
                    'slug' => 'mengelola-profil-data-diri',
                    'visible_to' => ['authenticated'],
                    'title' => 'Cara mengubah profil dan data diri',
                    'reading_minutes' => 4,
                    'excerpt' => 'Panduan mengubah nomor telepon, alamat, data rekening, dan field lain yang bisa Anda edit sendiri — serta mana yang harus minta bantuan admin.',
                    'body_html' => <<<'HTML'
<p class="fs-14 fw-normal mb-3">Kadang Anda perlu mengubah data diri — misalnya ganti nomor telepon, alamat rumah, atau nomor rekening. Beberapa data bisa diubah sendiri, sebagian lainnya hanya admin yang berhak mengubahnya.</p>

<h6 class="fs-14 fw-semibold mb-2">Data yang bisa Anda ubah sendiri</h6>
<p class="fs-14 fw-normal mb-3">Buka profil Anda lewat <a href="/employee-details">Detail Karyawan</a>. Dari sini Anda bisa mengubah:</p>
<ul class="knowledgebase ps-3 mb-3">
<li class="d-flex align-items-baseline mb-2"><i class="ti ti-point-filled me-1"></i> Nomor telepon dan kontak darurat</li>
<li class="d-flex align-items-baseline mb-2"><i class="ti ti-point-filled me-1"></i> Alamat tempat tinggal</li>
<li class="d-flex align-items-baseline mb-2"><i class="ti ti-point-filled me-1"></i> Data rekening bank (kalau perusahaan mengizinkan)</li>
</ul>

<h6 class="fs-14 fw-semibold mb-2">Data yang hanya bisa diubah admin</h6>
<p class="fs-14 fw-normal mb-3">Beberapa data sensitif hanya bisa diubah oleh admin HR, seperti:</p>
<ul class="knowledgebase ps-3 mb-3">
<li class="d-flex align-items-baseline mb-2"><i class="ti ti-point-filled me-1"></i> Gaji pokok dan tunjangan</li>
<li class="d-flex align-items-baseline mb-2"><i class="ti ti-point-filled me-1"></i> Jabatan dan departemen</li>
<li class="d-flex align-items-baseline mb-2"><i class="ti ti-point-filled me-1"></i> Status karyawan (aktif, cuti, keluar)</li>
</ul>

<p class="fs-14 fw-normal mb-0">Kalau ada data yang perlu diubah tapi tidak bisa Anda edit sendiri, hubungi admin HR untuk dibantu.</p>
HTML,
                ],
                [
                    'slug' => 'cara-reset-password',
                    'visible_to' => ['authenticated'],
                    'title' => 'Lupa kata sandi? Begini cara resetnya',
                    'reading_minutes' => 3,
                    'excerpt' => 'Lupa kata sandi akun Arcav HCM? Tenang, Anda bisa meresetnya sendiri lewat email — tidak perlu hubungi admin.',
                    'body_html' => <<<'HTML'
<p class="fs-14 fw-normal mb-3">Tenang, lupa kata sandi itu hal yang wajar. Anda bisa meresetnya sendiri tanpa harus lapor ke admin.</p>

<ol class="fs-14 mb-3">
<li>Buka halaman <a href="/login">login</a>.</li>
<li>Klik tautan <strong>Lupa Kata Sandi</strong> di bawah kolom kata sandi.</li>
<li>Masukkan email akun Anda — pastikan email yang dipakai sesuai dengan yang terdaftar di sistem.</li>
<li>Cek email masuk Anda. Biasanya dalam beberapa menit akan ada email berisi tautan untuk membuat kata sandi baru.</li>
<li>Klik tautan tersebut, lalu buat kata sandi baru. Usahakan jangan pakai kata sandi yang mudah ditebak ya.</li>
</ol>

<p class="fs-14 fw-normal mb-0">Kalau sudah berhasil, coba login dengan kata sandi baru. Kalau masih belum bisa, hubungi admin HR untuk dibantu.</p>
HTML,
                ],
            ],
        ],

        // ====================================================================
        // KATEGORI: DASHBOARD
        // ====================================================================
        [
            'slug' => 'dashboard',
            'title' => 'Dashboard dan Beranda',
            'icon' => 'ti ti-layout-dashboard',
            'visible_to' => ['authenticated'],
            'description' => 'Perbedaan beranda admin dan dashboard karyawan, serta ekspektasi data yang muncul.',
            'articles' => [
                [
                    'slug' => 'dashboard-admin-index',
                    'visible_to' => ['admin'],
                    'title' => 'Beranda admin (/index)',
                    'reading_minutes' => 4,
                    'excerpt' => 'Beranda utama setelah login. Menampilkan ringkasan jumlah karyawan, absensi hari ini, cuti yang menunggu, dan pengumuman penting.',
                    'body_html' => <<<'HTML'
<p class="fs-14 fw-normal mb-3"><a href="/index">Beranda</a> adalah halaman pertama yang muncul setelah Anda login. Di sini Anda bisa melihat ringkasan kondisi perusahaan hari ini: jumlah karyawan aktif, siapa saja yang sudah absen, cuti yang masih menunggu persetujuan, dan pemberitahuan penting lainnya.</p>

<h6 class="fs-14 fw-semibold mb-2">Yang perlu diketahui</h6>
<ol class="fs-14 mb-3">
<li>Kalau widget di beranda mendadak kosong, coba muat ulang halaman. Kalau masih kosong, mungkin sesi login Anda sudah habis — coba login ulang.</li>
<li>Beranda hanya ringkasan. Untuk pekerjaan yang sebenarnya, buka menu dari samping kiri — misalnya Karyawan, Absensi, atau Penggajian.</li>
<li>Untuk laporan resmi (misalnya laporan karyawan untuk auditor), gunakan halaman laporan seperti <a href="/employee-report">Laporan Karyawan</a> yang menampilkan data lebih lengkap.</li>
</ol>

<p class="fs-14 fw-normal mb-0">Admin dengan wewenang terbatas mungkin hanya melihat sebagian menu — itu sudah sesuai pengaturan. Kalau ada menu yang tidak muncul padahal seharusnya ada, hubungi admin utama.</p>
HTML,
                ],
                [
                    'slug' => 'dashboard-karyawan',
                    'visible_to' => ['employee'],
                    'title' => 'Dashboard karyawan (/employee-dashboard)',
                    'reading_minutes' => 3,
                    'excerpt' => 'Halaman pribadi Anda: ringkasan kehadiran, cuti, dan pengumuman.',
                    'body_html' => <<<'HTML'
<p class="fs-14 fw-normal mb-3"><a href="/employee-dashboard">Dashboard Karyawan</a> adalah halaman pribadi Anda. Di sini Anda bisa melihat ringkasan kehadiran, sisa cuti, dan pengumuman penting. Fokusnya ke hal-hal yang berkaitan dengan Anda seorang, bukan data seluruh perusahaan.</p>

<ul class="knowledgebase ps-3 mb-0">
<li class="d-flex align-items-baseline mb-2"><i class="ti ti-point-filled me-1"></i> Slip gaji bisa dicek di <a href="/payslip">Slip Gaji</a> setelah penggajian selesai dikunci admin.</li>
<li class="d-flex align-items-baseline mb-2"><i class="ti ti-point-filled me-1"></i> Kalau ada masalah yang butuh bantuan, buka <a href="/tickets-employee">Tiket Bantuan</a>.</li>
</ul>
HTML,
                ],
            ],
        ],

        // ====================================================================
        // KATEGORI: KARYAWAN DAN ORGANISASI
        // ====================================================================
        [
            'slug' => 'organisasi',
            'title' => 'Karyawan dan Organisasi',
            'icon' => 'ti ti-users',
            'visible_to' => ['admin'],
            'description' => 'Mengelola data karyawan, struktur organisasi (departemen dan jabatan), serta kebijakan perusahaan.',
            'articles' => [
                [
                    'slug' => 'direktori-profil-karyawan',
                    'visible_to' => ['admin'],
                    'title' => 'Data karyawan dan profil — panduan admin',
                    'reading_minutes' => 6,
                    'excerpt' => 'Cara melihat daftar semua karyawan, membuka profil seseorang, serta data apa saja yang bisa diubah karyawan sendiri vs hanya admin.',
                    'body_html' => <<<'HTML'
<h6 class="fs-14 fw-semibold mb-2">Admin: melihat dan mengelola data karyawan</h6>
<p class="fs-14 fw-normal mb-3">Buka <a href="/employees">Karyawan</a> untuk melihat daftar semua karyawan. Dari sini admin bisa menambah karyawan baru, mengimpor data banyak karyawan sekaligus, atau membuka profil seseorang lewat <a href="/employee-details">Detail Karyawan</a>. Fitur tambah dan impor hanya bisa dilakukan oleh admin — karyawan biasa tidak bisa menambah data rekan kerja.</p>

<h6 class="fs-14 fw-semibold mb-2">Karyawan: data diri sendiri</h6>
<p class="fs-14 fw-normal mb-3">Karyawan biasa yang membuka profil hanya melihat data dirinya sendiri. Beberapa data bisa diubah sendiri (misalnya nomor telepon dan alamat), tapi data gaji pokok dan tunjangan hanya bisa diubah oleh admin.</p>

<h6 class="fs-14 fw-semibold mb-2">Tips data yang rapi</h6>
<ul class="knowledgebase ps-3 mb-3">
<li class="d-flex align-items-baseline mb-2"><i class="ti ti-point-filled me-1"></i> Isi dulu departemen dan jabatan sebelum mengimpor data karyawan — biar pilihan di form sudah tersedia.</li>
<li class="d-flex align-items-baseline mb-2"><i class="ti ti-point-filled me-1"></i> Usahakan nomor induk karyawan (NIK) unik dan konsisten formatnya.</li>
<li class="d-flex align-items-baseline mb-2"><i class="ti ti-point-filled me-1"></i> Setelah mengubah data penting, muat ulang halaman detail untuk memastikan data yang tampil sudah yang terbaru.</li>
</ul>

<p class="fs-14 fw-normal mb-0">Sebelum mengisi data detail karyawan, pastikan data master (departemen, jabatan, status kerja) sudah diisi semua biar tidak ada field kosong yang bingung.</p>
HTML,
                ],
                [
                    'slug' => 'master-departemen-jabatan',
                    'visible_to' => ['admin'],
                    'title' => 'Departemen dan jabatan — panduan admin',
                    'reading_minutes' => 4,
                    'excerpt' => 'Cara mengisi struktur organisasi, dampaknya ke form di modul lain, dan tips menghindari duplikasi nama.',
                    'body_html' => <<<'HTML'
<p class="fs-14 fw-normal mb-3">Halaman <a href="/departments">Departemen</a> dan <a href="/designations">Jabatan</a> adalah tempat Anda membuat struktur organisasi. Data ini akan muncul sebagai pilihan di form karyawan, filter laporan, dan beberapa alur persetujuan.</p>

<p class="fs-14 fw-normal mb-3">Satu saran: kalau Anda mengganti nama departemen setelah banyak transaksi (misalnya sudah ada puluhan slip gaji), hal ini bisa membingungkan waktu audit. Lebih baik nonaktifkan departemen lama dan buat yang baru — biar jejaknya jelas.</p>

<h6 class="fs-14 fw-semibold mb-2">Checklist admin</h6>
<ul class="knowledgebase ps-3 mb-0">
<li class="d-flex align-items-baseline mb-2"><i class="ti ti-point-filled me-1"></i> Hindari membuat dua nama yang sama beda huruf besar/kecil — misalnya "HR" dan "hr" — karena bisa membingungkan.</li>
<li class="d-flex align-items-baseline mb-2"><i class="ti ti-point-filled me-1"></i> Setelah mengubah daftar departemen, coba buka satu form karyawan untuk memastikan pilihan terbaru sudah muncul.</li>
</ul>
HTML,
                ],
                [
                    'slug' => 'kebijakan-perusahaan',
                    'visible_to' => ['admin'],
                    'title' => 'Kebijakan perusahaan (Policies)',
                    'reading_minutes' => 3,
                    'excerpt' => 'Halaman untuk menyimpan aturan-aturan perusahaan yang dipakai oleh modul lain. Hanya admin yang bisa mengubah isinya.',
                    'body_html' => <<<'HTML'
<p class="fs-14 fw-normal mb-3">Halaman <a href="/policy">Kebijakan</a> dipakai untuk menyimpan aturan-aturan perusahaan — misalnya aturan cuti, aturan disiplin, atau SOP internal. Jangan hapus kebijakan yang masih dipakai tanpa koordinasi dulu.</p>
<p class="fs-14 fw-normal mb-0">Semua perubahan data di halaman ini hanya bisa dilakukan oleh admin HCM. Karyawan biasa tidak bisa mengubah kebijakan, mereka hanya melihat efeknya (misalnya batas cuti yang muncul di form pengajuan).</p>
HTML,
                ],
                [
                    'slug' => 'grid-vs-daftar-karyawan',
                    'visible_to' => ['admin'],
                    'title' => 'Tampilan grid vs daftar karyawan (/employees-grid)',
                    'reading_minutes' => 3,
                    'excerpt' => 'Kapan memakai tampilan grid — cocok untuk review cepat atau presentasi.',
                    'body_html' => <<<'HTML'
<p class="fs-14 fw-normal mb-3"><a href="/employees-grid">Grid Karyawan</a> menampilkan data yang sama dengan halaman <a href="/employees">Karyawan</a> biasa, tapi dalam bentuk kartu-kartu. Cocok kalau Anda mau lihat sekilas atau presentasi di layar lebar.</p>

<ol class="fs-14 mb-0">
<li>Pilih periode filter yang sama dengan laporan yang akan cocokkan.</li>
<li>Kalau mau mengedit data kompensasi, buka lewat kartu ke halaman <a href="/employee-details">Detail Karyawan</a>.</li>
<li>Tampilan grid bersifat ringkas — untuk aksi lengkap (seperti menambah atau mengimpor), gunakan halaman daftar biasa.</li>
</ol>
HTML,
                ],
            ],
        ],

        // ====================================================================
        // KATEGORI: CUTI DAN LIBUR
        // ====================================================================
        [
            'slug' => 'cuti-dan-libur',
            'title' => 'Cuti dan Hari Libur',
            'icon' => 'ti ti-calendar-time',
            'visible_to' => ['authenticated'],
            'description' => 'Panduan mengajukan cuti (karyawan), menyetujui cuti (admin), mengatur jenis cuti, dan mengisi hari libur nasional.',
            'articles' => [
                [
                    'slug' => 'pengajuan-cuti-karyawan',
                    'visible_to' => ['employee'],
                    'title' => 'Cara mengajukan cuti — panduan karyawan',
                    'reading_minutes' => 5,
                    'excerpt' => 'Langkah mengajukan cuti, memantau status pengajuan, dan apa yang harus dilakukan kalau pilihan jenis cuti kosong.',
                    'body_html' => <<<'HTML'
<p class="fs-14 fw-normal mb-3">Halaman <a href="/leaves-employee">Cuti Karyawan</a> adalah tempat Anda mengajukan cuti. Isi tanggal, pilih jenis cuti, tulis alasan, lalu kirim. Nanti atasan atau admin HR akan menyetujuinya (atau menolaknya).</p>

<h6 class="fs-14 fw-semibold mb-2">Yang sering ditanyakan</h6>
<ul class="knowledgebase ps-3 mb-3">
<li class="d-flex align-items-baseline mb-2"><i class="ti ti-point-filled me-1"></i> <strong>"Kok pilihan jenis cutinya kosong?"</strong> — Berarti admin perusahaan belum mengaktifkan jenis cuti yang dibutuhkan. Hubungi admin HR.</li>
<li class="d-flex align-items-baseline mb-2"><i class="ti ti-point-filled me-1"></i> <strong>"Tanggalnya tidak bisa dipilih"</strong> — Mungkin tanggal tersebut sudah termasuk hari libur, atau kuota cuti Anda sudah habis, atau ada aturan bahwa cuti harus diajukan minimal H-1.</li>
</ul>

<p class="fs-14 fw-normal mb-0">Sistem otomatis menampilkan hanya data cuti milik Anda sendiri — tidak perlu khawatir melihat data orang lain.</p>
HTML,
                ],
                [
                    'slug' => 'persetujuan-cuti-admin',
                    'visible_to' => ['admin'],
                    'title' => 'Menyetujui atau menolak cuti — panduan admin',
                    'reading_minutes' => 5,
                    'excerpt' => 'Halaman Cuti Admin untuk melihat semua pengajuan, menyetujui atau menolak, dan batasan bahwa hanya admin yang bisa mengubah cuti orang lain.',
                    'body_html' => <<<'HTML'
<p class="fs-14 fw-normal mb-3">Halaman <a href="/leaves">Cuti Admin</a> adalah tempat Anda sebagai admin menyetujui atau menolak pengajuan cuti dari karyawan. Kalau Anda bukan admin dan membuka halaman ini, sistem akan mengalihkan ke halaman cuti karyawan.</p>

<h6 class="fs-14 fw-semibold mb-2">Aturan penting</h6>
<p class="fs-14 fw-normal mb-3">Hanya admin yang bisa menyetujui atau menolak cuti milik karyawan lain. Karyawan biasa tidak bisa mengubah cuti rekan kerjanya meskipun tahu URL halaman ini — ini adalah perlindungan data.</p>

<ul class="knowledgebase ps-3 mb-0">
<li class="d-flex align-items-baseline mb-2"><i class="ti ti-point-filled me-1"></i> Gunakan filter tanggal saat antrian cuti sedang banyak.</li>
<li class="d-flex align-items-baseline mb-2"><i class="ti ti-point-filled me-1"></i> Setelah menolak cuti, pastikan karyawan mendapat pemberitahuan (kalau fitur notifikasi aktif).</li>
</ul>
HTML,
                ],
                [
                    'slug' => 'pengaturan-jenis-cuti',
                    'visible_to' => ['admin'],
                    'title' => 'Mengatur jenis cuti dan aturannya — panduan admin',
                    'reading_minutes' => 4,
                    'excerpt' => 'Halaman Pengaturan Cuti dan Jenis Cuti: tempat menentukan kuota, siapa yang menyetujui, dan jenis cuti apa saja yang tersedia.',
                    'body_html' => <<<'HTML'
<p class="fs-14 fw-normal mb-3"><a href="/leave-settings">Pengaturan Cuti</a> dipakai untuk mengatur aturan cuti secara umum (kuota per tahun, alur persetujuan). <a href="/leave-type">Jenis Cuti</a> dipakai untuk membuat daftar jenis cuti yang muncul di pilihan form pengajuan — misalnya Cuti Tahunan, Cuti Sakit, Cuti Melahirkan.</p>

<p class="fs-14 fw-normal mb-0">Kedua halaman ini hanya bisa diakses admin. Perubahan di sini langsung terlihat di form pengajuan karyawan — jadi sebaiknya lakukan perubahan di luar jam sibuk pengajuan cuti.</p>
HTML,
                ],
                [
                    'slug' => 'hari-libur-nasional',
                    'visible_to' => ['admin'],
                    'title' => 'Mengisi hari libur (Holidays)',
                    'reading_minutes' => 3,
                    'excerpt' => 'Data hari libur mempengaruhi perhitungan hari kerja. Bisa diisi manual atau disinkronkan dengan kalender libur Indonesia.',
                    'body_html' => <<<'HTML'
<p class="fs-14 fw-normal mb-3">Halaman <a href="/holidays">Hari Libur</a> untuk admin. Hari libur nasional bisa diisi satu per satu atau disinkronkan dengan kalender libur Indonesia otomatis.</p>
<p class="fs-14 fw-normal mb-0">Pastikan zona waktu yang dipilih perusahaan sudah sesuai — karena definisi "hari" bisa berbeda pengaruhnya ke perhitungan cuti dan absensi.</p>
HTML,
                ],
            ],
        ],

        // ====================================================================
        // KATEGORI: ABSENSI
        // ====================================================================
        [
            'slug' => 'absensi',
            'title' => 'Absensi dan Jadwal',
            'icon' => 'ti ti-clock',
            'visible_to' => ['authenticated'],
            'description' => 'Absen setiap hari dengan GPS, selfie setelah absen, rekap absensi (admin), timesheet, dan pengaturan jam kerja.',
            'articles' => [
                [
                    'slug' => 'absen-dan-gps',
                    'visible_to' => ['employee'],
                    'title' => 'Absen mandiri: GPS, selfie, dan cara mengatasi masalah — panduan karyawan',
                    'reading_minutes' => 6,
                    'excerpt' => 'Panduan absen harian: izinkan lokasi, tekan tombol Masuk/Pulang, selfie kalau diwajibkan, dan cara mengatasi kalau GPS tidak berfungsi.',
                    'body_html' => <<<'HTML'
<p class="fs-14 fw-normal mb-3">Halaman <a href="/attendance-employee">Absensi Karyawan</a> adalah tempat Anda absen setiap hari — langsung dari browser, tanpa perlu aplikasi tambahan.</p>

<h6 class="fs-14 fw-semibold mb-2">Langkah absen</h6>
<ol class="fs-14 mb-3">
<li>Buka halaman Absensi Karyawan.</li>
<li>Nanti browser akan minta izin untuk mengakses lokasi Anda — <strong>izinkan ya</strong>. Ini penting biar sistem tahu Anda benar-benar di kantor. Kalau tidak diizinkan, absensi Anda mungkin tidak tercatat.</li>
<li>Tekan tombol <strong>Masuk</strong> saat datang, dan tombol <strong>Pulang</strong> saat pulang.</li>
<li>Kalau perusahaan mewajibkan selfie, lakukan setelah berhasil absen. Kalau selfie ditolak, biasanya karena Anda belum melakukan absen masuk terlebih dahulu.</li>
</ol>

<h6 class="fs-14 fw-semibold mb-2">Kalau GPS tidak berfungsi</h6>
<p class="fs-14 fw-normal mb-3">Kadang browser menolak izin lokasi — entah karena pengaturan atau koneksi. Tenang, Anda bisa mengklik titik di peta sebagai lokasi manual. Koordinat yang dipilih tetap akan dikirim ke server.</p>

<h6 class="fs-14 fw-semibold mb-2">Tips</h6>
<p class="fs-14 fw-normal mb-0">Absenlah 5 menit sebelum jam masuk — biar tidak terburu-buru. Kalau lupa absen, segera hubungi admin HR untuk dibantu.</p>
HTML,
                ],
                [
                    'slug' => 'jadwal-shift-dan-jam-kerja',
                    'visible_to' => ['admin'],
                    'title' => 'Jadwal shift dan jam kerja — panduan admin',
                    'reading_minutes' => 5,
                    'excerpt' => 'Cara membuat master shift dan memasangkannya ke jadwal masing-masing karyawan.',
                    'body_html' => <<<'HTML'
<p class="fs-14 fw-normal mb-3"><a href="/shift-master">Master Shift</a> adalah tempat Anda mendefinisikan jam kerja — misalnya Shift Pagi (08:00–17:00) atau Shift Malam (20:00–05:00). Setelah itu, buka <a href="/schedule-timing">Jadwal Karyawan</a> untuk memasangkan shift ke setiap karyawan.</p>
<p class="fs-14 fw-normal mb-3">Kalau jadwal shift tidak ditentukan untuk seorang karyawan, sistem akan menggunakan jam kerja default yang sudah dikonfigurasi.</p>
<p class="fs-14 fw-normal mb-0">Semua perubahan di sini hanya bisa dilakukan oleh admin HCM. Kesalahan jadwal bisa berdampak ke perhitungan lembur — jadi periksa sekali lagi sebelum menyimpan.</p>
HTML,
                ],
                [
                    'slug' => 'timesheet-dan-rekap-absensi',
                    'visible_to' => ['admin'],
                    'title' => 'Timesheet dan rekapitulasi absensi — panduan admin',
                    'reading_minutes' => 3,
                    'excerpt' => 'Halaman Timesheet untuk melihat rekap jam kerja. Cocokkan rentang tanggalnya dengan periode penggajian.',
                    'body_html' => <<<'HTML'
<p class="fs-14 fw-normal mb-3"><a href="/timesheets">Timesheet</a> membantu admin melihat rekap jam kerja semua karyawan. Gunakan filter tanggal yang sama dengan periode penggajian yang sedang diproses — biar angka-angka cocok.</p>
<p class="fs-14 fw-normal mb-0">Kalau ada selisih angka antara timesheet dan slip gaji, periksa dulu apakah ada koreksi absensi atau status "perlu ditinjau" di beberapa hari tertentu.</p>
HTML,
                ],
            ],
        ],

        // ====================================================================
        // KATEGORI: PENGGAJIAN
        // ====================================================================
        [
            'slug' => 'payroll',
            'title' => 'Penggajian dan Kompensasi',
            'icon' => 'ti ti-currency-dollar',
            'visible_to' => ['admin'],
            'description' => 'Proses penggajian bulanan, komponen gaji, kompensasi per karyawan, THR, slip gaji mandiri, dan lembur/PKWT.',
            'articles' => [
                [
                    'slug' => 'proses-penggajian-bulanan',
                    'visible_to' => ['admin'],
                    'title' => 'Proses penggajian bulanan — panduan admin',
                    'reading_minutes' => 7,
                    'excerpt' => 'Alur lengkap dari hitung draft, periksa, kunci, cairkan gaji, sampai lihat riwayat penggajian.',
                    'body_html' => <<<'HTML'
<h6 class="fs-14 fw-semibold mb-2">Halaman yang perlu diketahui</h6>
<p class="fs-14 fw-normal mb-3"><a href="/payroll-run">Proses Payroll</a> adalah halaman utama untuk menjalankan penggajian bulan ini. <a href="/payroll-run-history">Riwayat Payroll</a> menyimpan data penggajian bulan-bulan sebelumnya — berguna untuk audit atau cek ulang.</p>

<h6 class="fs-14 fw-semibold mb-2">Urutan kerja yang disarankan</h6>
<ol class="fs-14 mb-3">
<li>Pastikan data absensi, lembur, dan cuti periode ini sudah beres semua.</li>
<li>Di halaman Proses Payroll, jalankan <strong>Hitung Draft</strong> — sistem akan menghitung gaji sementara.</li>
<li>Periksa angka per karyawan — pastikan gaji pokok, tunjangan, potongan, dan lembur sudah sesuai.</li>
<li>Kalau sudah cocok, <strong>kunci (finalisasi)</strong> gaji tersebut. Ingat: setelah dikunci, data tidak bisa diubah lagi — jadi periksa baik-baik.</li>
<li>Kalau perusahaan Anda mewajibkan ekspor bukti sebelum pencairan (fitur rekonsiliasi), lakukan ekspor dulu.</li>
<li>Lanjutkan ke <strong>pencairan (disburse)</strong> sesuai prosedur — biasanya lewat transfer bank atau metode yang sudah diatur.</li>
<li>Beri tahu karyawan untuk cek slip gaji mereka di <a href="/payslip">Slip Gaji</a>.</li>
</ol>

<h6 class="fs-14 fw-semibold mb-2">Yang perlu diperhatikan</h6>
<p class="fs-14 fw-normal mb-0">Gaji pokok + tunjangan tetap dari profil karyawan, ditambah komponen gaji lain (bonus, potongan, lembur) akan masuk ke perhitungan. Pastikan data master tidak berubah di tengah proses tanpa koordinasi dengan bagian keuangan.</p>
HTML,
                ],
                [
                    'slug' => 'komponen-gaji-payroll-items',
                    'visible_to' => ['admin'],
                    'title' => 'Komponen gaji — panduan admin',
                    'reading_minutes' => 5,
                    'excerpt' => 'Cara mengelola komponen gaji (penambah dan potongan) serta ekspor untuk rekonsiliasi.',
                    'body_html' => <<<'HTML'
<p class="fs-14 fw-normal mb-3"><a href="/payroll">Payroll</a> (komponen penambah) dan <a href="/payroll-deduction">Potongan Payroll</a> adalah tempat Anda membuat komponen gaji kustom — misalnya tunjangan transport, bonus, atau potongan pinjaman.</p>

<ul class="knowledgebase ps-3 mb-0">
<li class="d-flex align-items-baseline mb-2"><i class="ti ti-point-filled me-1"></i> Mengubah nama komponen tidak akan mengubah data slip gaji yang sudah dikunci sebelumnya — hanya mempengaruhi periode berikutnya.</li>
<li class="d-flex align-items-baseline mb-2"><i class="ti ti-point-filled me-1"></i> Gunakan fitur export (CSV/XLSX) kalau bagian keuangan minta data untuk rekonsiliasi eksternal.</li>
</ul>
HTML,
                ],
                [
                    'slug' => 'gaji-per-karyawan',
                    'visible_to' => ['admin'],
                    'title' => 'Kompensasi per karyawan (/employee-salary)',
                    'reading_minutes' => 5,
                    'excerpt' => 'Mengatur gaji pokok, tunjangan tetap, dan komponen gaji per karyawan. Halaman ini hanya untuk admin.',
                    'body_html' => <<<'HTML'
<p class="fs-14 fw-normal mb-3"><a href="/employee-salary">Gaji Karyawan</a> adalah tempat Anda mengatur kompensasi setiap karyawan: gaji pokok, tunjangan tetap, dan komponen gaji tambahan. Halaman ini hanya bisa diakses oleh admin — karyawan biasa tidak bisa melihat atau mengubah data gaji rekan kerjanya.</p>
<p class="fs-14 fw-normal mb-0">Setelah mengubah kompensasi seorang karyawan, jalankan ulang hitung draft payroll periode berjalan agar angka di slip barunya tercermin.</p>
HTML,
                ],
                [
                    'slug' => 'slip-gaji-karyawan',
                    'visible_to' => ['employee'],
                    'title' => 'Cara cek slip gaji (/payslip)',
                    'reading_minutes' => 4,
                    'excerpt' => 'Slip gaji hanya muncul kalau penggajian bulan itu sudah dikunci admin. Panduan untuk karyawan.',
                    'body_html' => <<<'HTML'
<p class="fs-14 fw-normal mb-3">Halaman <a href="/payslip">Slip Gaji</a> hanya menampilkan data gaji Anda sendiri — bukan data orang lain. Kalau Anda admin dan membuka halaman ini, biasanya akan dialihkan ke halaman laporan slip agar tidak salah konteks.</p>

<h6 class="fs-14 fw-semibold mb-2">Yang perlu diketahui</h6>
<ul class="knowledgebase ps-3 mb-3">
<li class="d-flex align-items-baseline mb-2"><i class="ti ti-point-filled me-1"></i> Pilih bulan dan tahun yang ingin dilihat.</li>
<li class="d-flex align-items-baseline mb-2"><i class="ti ti-point-filled me-1"></i> Kalau datanya kosong, kemungkinan penggajian bulan itu belum dikunci oleh admin. Tunggu saja atau tanyakan ke admin.</li>
<li class="d-flex align-items-baseline mb-2"><i class="ti ti-point-filled me-1"></i> Slip gaji bisa diunduh dalam bentuk PDF kalau fitur unduhan tersedia.</li>
</ul>

<h6 class="fs-14 fw-semibold mb-2">Privasi</h6>
<p class="fs-14 fw-normal mb-0">Jangan membagikan tautan unduhan slip gaji ke publik atau media sosial — karena file tersebut berisi data penghasilan pribadi Anda.</p>
HTML,
                ],
                [
                    'slug' => 'thr-dan-batch',
                    'visible_to' => ['admin'],
                    'title' => 'THR dan pembayaran massal (/payroll-thr)',
                    'reading_minutes' => 5,
                    'excerpt' => 'Pengaturan THR tahunan, kalkulator, proses batch massal, dan pengiriman slip THR.',
                    'body_html' => <<<'HTML'
<p class="fs-14 fw-normal mb-3">Halaman <a href="/payroll-thr">THR</a> mencakup pengaturan tahunan, estimasi THR per karyawan, proses batch (generate → cairkan → posting), dan kirim slip THR.</p>
<p class="fs-14 fw-normal mb-0">Setiap langkah punya pengaman agar tidak terjadi pembayaran ganda. Karyawan bisa melihat ringkasan THR mereka lewat halaman slip sendiri — tidak ada halaman khusus THR untuk karyawan.</p>
HTML,
                ],
                [
                    'slug' => 'export-rekonsiliasi',
                    'visible_to' => ['admin'],
                    'title' => 'Export bukti sebelum pencairan gaji',
                    'reading_minutes' => 4,
                    'excerpt' => 'Kalau fitur rekonsiliasi aktif, Anda wajib menghasilkan bukti export sebelum mencairkan gaji. Ini untuk keperluan audit.',
                    'body_html' => <<<'HTML'
<p class="fs-14 fw-normal mb-3">Pada perusahaan yang mengaktifkan fitur rekonsiliasi, admin diwajibkan menghasilkan bukti export (CSV atau ZIP) sebelum menekan tombol kunci atau cairkan gaji. Tujuannya: memastikan setiap proses penggajian memiliki jejak audit yang lengkap.</p>
<p class="fs-14 fw-normal mb-0">Kalau tombol aksi tetap tidak aktif meskipun sudah mencoba, periksa periode yang dipilih — harus sama dengan periode penggajian yang sedang diproses.</p>
HTML,
                ],
                [
                    'slug' => 'gaji-lembur-dan-pkwt',
                    'visible_to' => ['admin'],
                    'title' => 'Payroll lembur dan kompensasi PKWT',
                    'reading_minutes' => 5,
                    'excerpt' => 'Cara mengelola gaji lembur dan kompensasi kontrak waktu tertentu.',
                    'body_html' => <<<'HTML'
<h6 class="fs-14 fw-semibold mb-2">Gaji lembur</h6>
<ol class="fs-14 mb-3">
<li>Pastikan permintaan lembur sudah disetujui di <a href="/overtime">Lembur</a> untuk periode yang sama.</li>
<li>Buka <a href="/payroll-overtime">Payroll Lembur</a>; samakan filter tanggal dengan absensi admin saat memverifikasi jam kerja.</li>
<li>Periksa baris per karyawan sebelum menjalankan hitung gaji utama.</li>
</ol>

<h6 class="fs-14 fw-semibold mb-2">Kompensasi PKWT (kontrak)</h6>
<p class="fs-14 fw-normal mb-0">Gunakan <a href="/payroll-pkwt-compensation">Kompensasi PKWT</a> untuk skenario kompensasi khusus kontrak waktu tertentu. Pastikan angka yang dimasukkan konsisten dengan aturan pembulatan yang dipakai modul payroll utama.</p>
HTML,
                ],
            ],
        ],

        // ====================================================================
        // KATEGORI: PENGATURAN SISTEM
        // ====================================================================
        [
            'slug' => 'pengaturan-sistem',
            'title' => 'Pengaturan Sistem & Master Pendukung',
            'icon' => 'ti ti-settings',
            'visible_to' => ['admin'],
            'description' => 'Konfigurasi sistem: jadwal otomatis, pengguna dan wewenang, lokalisasi (bahasa/zona waktu) — biasanya diatur sekali lalu jarang diubah.',
            'articles' => [
                [
                    'slug' => 'jadwal-otomatis-cronjob',
                    'visible_to' => ['admin'],
                    'title' => 'Mengatur jadwal otomatis (/cronjob)',
                    'reading_minutes' => 5,
                    'excerpt' => 'Halaman Cronjob untuk mengatur tugas-tugas yang berjalan otomatis — seperti pengingat, sinkronisasi, dan pembersihan data.',
                    'body_html' => <<<'HTML'
<p class="fs-14 fw-normal mb-3">Halaman <a href="/cronjob">Cronjob</a> hanya bisa diakses oleh admin HCM. Di sini Anda bisa mengatur jadwal tugas-tugas yang berjalan otomatis, misalnya: pengingat absensi, sinkronisasi data, atau pembersihan log.</p>

<ol class="fs-14 mb-3">
<li>Baca dulu pengaturan yang sudah ada sebelum mengubah jadwal.</li>
<li>Setelah menyimpan perubahan, pantau apakah tugas berjalan sesuai jadwal.</li>
<li>Jangan menonaktifkan tugas penting (seperti backup data atau pemantauan) tanpa koordinasi dengan tim teknis.</li>
</ol>

<p class="fs-14 fw-normal mb-0">Catat setiap perubahan di buku internal tim Anda untuk keperluan audit.</p>
HTML,
                ],
                [
                    'slug' => 'pengguna-dan-wewenang',
                    'visible_to' => ['admin'],
                    'title' => 'Mengelola pengguna dan wewenang (/users, /roles-permissions)',
                    'reading_minutes' => 6,
                    'excerpt' => 'Cara menambah pengguna baru, mengubah wewenang, dan perbedaan antara akun web dan akun API HR.',
                    'body_html' => <<<'HTML'
<p class="fs-14 fw-normal mb-3">Halaman <a href="/users">Pengguna</a> dan <a href="/roles-permissions">Peran & Izin</a> adalah tempat mengatur siapa saja yang bisa login ke Arcav HCM dan apa yang bisa mereka lakukan.</p>

<ol class="fs-14 mb-3">
<li>Tambahkan pengguna baru, lalu beri wewenang minimal yang diperlukan.</li>
<li>Perubahan wewenang berlaku segera — karyawan yang baru diberi akses admin tidak perlu logout dulu.</li>
<li>Uji coba login dengan akun tersebut di browser privat (incognito) untuk memastikan tidak ada sisa data login lama.</li>
<li>Catat perubahan wewenang di tiket internal kalau perusahaan Anda mewajibkan jejak audit.</li>
</ol>

<p class="fs-14 fw-normal mb-0">Jangan memberi wewenang luas hanya untuk "mempermudah demo" — gunakan akun percobaan terpisah.</p>
HTML,
                ],
                [
                    'slug' => 'lokalisasi-dan-prefix',
                    'visible_to' => ['admin'],
                    'title' => 'Lokalisasi dan format nomor (/localization-settings, /prefixes)',
                    'reading_minutes' => 4,
                    'excerpt' => 'Zona waktu, bahasa tampilan, dan format nomor referensi (cuti, tiket, dll).',
                    'body_html' => <<<'HTML'
<p class="fs-14 fw-normal mb-3"><a href="/localization-settings">Pengaturan Lokalisasi</a> mempengaruhi format tanggal, angka, dan bahasa yang muncul di layar. <a href="/prefixes">Prefix</a> mengatur awalan nomor referensi (misalnya nomor cuti atau tiket) sesuai kebiasaan perusahaan.</p>

<ol class="fs-14 mb-0">
<li>Setel zona waktu dan bahasa sebelum perusahaan mulai menggunakan payroll — biar periode bulanan konsisten.</li>
<li>Ubah prefix di luar jam sibuk kalau memungkinkan.</li>
<li>Setelah mengubah prefix, uji coba dengan satu dokumen contoh (misalnya unduh PDF) sebelum diumumkan ke semua staf.</li>
</ol>
HTML,
                ],
            ],
        ],

        // ====================================================================
        // KATEGORI: LAPORAN
        // ====================================================================
        [
            'slug' => 'laporan',
            'title' => 'Laporan HCM',
            'icon' => 'ti ti-report-analytics',
            'visible_to' => ['admin'],
            'description' => 'Panduan memakai halaman laporan: filter, export, dan cara membaca data.',
            'articles' => [
                [
                    'slug' => 'laporan-karyawan',
                    'visible_to' => ['admin'],
                    'title' => 'Laporan data karyawan (/employee-report)',
                    'reading_minutes' => 4,
                    'excerpt' => 'Filter departemen/jabatan, export data, dan pastikan jumlah baris sesuai daftar karyawan.',
                    'body_html' => <<<'HTML'
<ol class="fs-14 mb-3">
<li>Buka <a href="/employee-report">Laporan Karyawan</a> sebagai admin HCM.</li>
<li>Gunakan filter (departemen, jabatan) kalau data karyawan banyak — biar hasilnya cepat dan mudah diexport.</li>
<li>Export data kalau tersedia; bandingkan jumlah baris dengan halaman <a href="/employees">Karyawan</a> untuk memastikan tidak ada data yang terlewat.</li>
</ol>
<p class="fs-14 fw-normal mb-0">Halaman ini hanya bisa diakses admin HCM — karyawan biasa tidak bisa melihat laporan seluruh karyawan.</p>
HTML,
                ],
                [
                    'slug' => 'laporan-absensi',
                    'visible_to' => ['admin'],
                    'title' => 'Laporan absensi (/attendance-report)',
                    'reading_minutes' => 4,
                    'excerpt' => 'Menjembatani data timesheet harian dengan rekap periode.',
                    'body_html' => <<<'HTML'
<ol class="fs-14 mb-3">
<li>Buka <a href="/attendance-report">Laporan Absensi</a>; atur rentang tanggal sama dengan periode payroll atau audit HR.</li>
<li>Kalau ada angka yang janggal, cek dulu koreksi manual di halaman <a href="/attendance-admin">Absensi Admin</a>.</li>
<li>Gunakan bersama <a href="/timesheets">Timesheet</a> untuk melihat detail per hari.</li>
</ol>
<p class="fs-14 fw-normal mb-0">Pastikan zona waktu perusahaan sama dengan yang dipakai karyawan saat absen.</p>
HTML,
                ],
                [
                    'slug' => 'laporan-slip-gaji',
                    'visible_to' => ['admin'],
                    'title' => 'Laporan slip gaji (/payslip-report)',
                    'reading_minutes' => 4,
                    'excerpt' => 'Agregat slip untuk finance. Beda dengan /payslip yang hanya untuk karyawan sendiri.',
                    'body_html' => <<<'HTML'
<p class="fs-14 fw-normal mb-3"><a href="/payslip-report">Laporan Slip Gaji</a> adalah untuk admin/Finance yang perlu melihat data gaji banyak karyawan. Berbeda dengan <a href="/payslip">Slip Gaji</a> yang hanya menampilkan data diri sendiri.</p>

<ol class="fs-14 mb-0">
<li>Pilih periode tahun-bulan yang sama dengan penggajian di <a href="/payroll-run-history">Riwayat Payroll</a>.</li>
<li>Export data kalau tersedia; simpan file sesuai aturan penyimpanan data perusahaan.</li>
<li>Kalau ada koreksi gaji, ubah data di <a href="/employee-salary">Gaji Karyawan</a> lalu hitung ulang draft payroll — jangan mengedit file PDF ekspor secara manual.</li>
</ol>
HTML,
                ],
                [
                    'slug' => 'laporan-cuti',
                    'visible_to' => ['admin'],
                    'title' => 'Laporan cuti (/leave-report)',
                    'reading_minutes' => 3,
                    'excerpt' => 'Halaman laporan cuti masih dalam pengembangan. Sementara gunakan filter di Cuti Admin.',
                    'body_html' => <<<'HTML'
<p class="fs-14 fw-normal mb-3">Halaman <a href="/leave-report">Laporan Cuti</a> tersedia di menu untuk rencana ke depan. Kalau tampilannya masih belum berisi data, gunakan sementara halaman <a href="/leaves">Cuti Admin</a> dengan filter tanggal dan export manual sesuai kebutuhan audit.</p>
<p class="fs-14 fw-normal mb-0">Pantau pembaruan aplikasi — begitu fitur laporan cuti sudah aktif, artikel ini akan diperbarui.</p>
HTML,
                ],
            ],
        ],

        // ====================================================================
        // KATEGORI: KINERJA, GOALS, DAN PELATIHAN
        // ====================================================================
        [
            'slug' => 'kinerja-goals-pelatihan',
            'title' => 'Kinerja, Sasaran, dan Pelatihan',
            'icon' => 'ti ti-chart-line',
            'visible_to' => ['admin'],
            'description' => 'Penilaian kinerja, pelacakan sasaran karyawan, dan administrasi pelatihan.',
            'articles' => [
                [
                    'slug' => 'penilaian-kinerja',
                    'visible_to' => ['admin'],
                    'title' => 'Penilaian kinerja: indikator → siklus → review',
                    'reading_minutes' => 8,
                    'excerpt' => 'Urutan kerja: buat template indikator dulu, buat siklus penilaian, lalu isi review oleh karyawan/manager.',
                    'body_html' => <<<'HTML'
<h6 class="fs-14 fw-semibold mb-2">1. Siapkan indikator penilaian</h6>
<p class="fs-14 fw-normal mb-3">Di halaman <a href="/performance-indicator">Indikator Kinerja</a>, buat template beserta item-item penilaian. Ini hanya bisa diubah oleh admin HCM.</p>

<h6 class="fs-14 fw-semibold mb-2">2. Buat siklus penilaian</h6>
<p class="fs-14 fw-normal mb-3">Di halaman <a href="/performance-appraisal">Siklus Penilaian</a>, buat siklus untuk periode tertentu. Pastikan tanggalnya tidak bertumpuk dengan siklus lama yang masih "terbuka".</p>

<h6 class="fs-14 fw-semibold mb-2">3. Isi review</h6>
<ol class="fs-14 mb-0">
<li>Karyawan atau atasan mengisi review di halaman <a href="/performance-review">Review Kinerja</a> — tergantung wewenang (diri sendiri vs anggota tim vs finalisasi admin).</li>
<li>Setelah nilai final, jangan ubah indikator secara drastis — karena data historis review tetap merujuk definisi saat review diisi.</li>
</ol>
HTML,
                ],
                [
                    'slug' => 'sasaran-goal-tracking',
                    'visible_to' => ['admin'],
                    'title' => 'Sasaran karyawan: jenis sasaran & pelacakan (/goal-type, /goal-tracking)',
                    'reading_minutes' => 6,
                    'excerpt' => 'Sasaran pribadi, tim, dan organisasi — tampilan disesuaikan otomatis berdasarkan peran Anda.',
                    'body_html' => <<<'HTML'
<ol class="fs-14 mb-3">
<li>Admin siapkan katalog jenis sasaran di <a href="/goal-type">Jenis Sasaran</a>.</li>
<li>Penetapan dan pencatatan progres sasaran dilakukan di <a href="/goal-tracking">Pelacakan Sasaran</a>: karyawan melihat sasaran sendiri, manajer melihat sasaran tim, admin melihat seluruh organisasi.</li>
<li>Export CSV dari halaman kalau tersedia untuk review kuartalan.</li>
</ol>

<p class="fs-14 fw-normal mb-0">Kalau manajer mengeluh "tidak bisa melihat sasaran tim", periksa relasi atasan di data karyawan — pastikan atasan sudah ditentukan dengan benar.</p>
HTML,
                ],
                [
                    'slug' => 'pelatihan-karyawan',
                    'visible_to' => ['admin'],
                    'title' => 'Pelatihan: jenis, trainer, jadwal (/training-type, /trainers, /training)',
                    'reading_minutes' => 7,
                    'excerpt' => 'Siapkan master jenis pelatihan dan trainer dulu, baru buat jadwal pelatihan.',
                    'body_html' => <<<'HTML'
<ol class="fs-14 mb-3">
<li>Isi dulu <a href="/training-type">Jenis Pelatihan</a> dan <a href="/trainers">Trainer</a> — biar form pembuatan pelatihan tidak kosong pilhannya.</li>
<li>Buat pelatihan di <a href="/training">Pelatihan</a>; pilih peserta dari daftar karyawan.</li>
<li>Umumkan jadwal ke peserta lewat saluran internal perusahaan.</li>
</ol>

<p class="fs-14 fw-normal mb-0">Pastikan data peserta sudah lengkap sebagai karyawan aktif sebelum mendaftarkan mereka ke program pelatihan.</p>
HTML,
                ],
            ],
        ],

        // ====================================================================
        // KATEGORI: MASTER WILAYAH
        // ====================================================================
        [
            'slug' => 'master-wilayah',
            'title' => 'Master Wilayah (Negara, Provinsi, Kota)',
            'icon' => 'ti ti-map-pin',
            'visible_to' => ['admin'],
            'description' => 'Data geografis untuk alamat karyawan dan filter. Biasanya diisi sekali lalu jarang diubah.',
            'articles' => [
                [
                    'slug' => 'negara-provinsi-kota',
                    'visible_to' => ['admin'],
                    'title' => 'Mengisi data negara, provinsi, dan kota',
                    'reading_minutes' => 4,
                    'excerpt' => 'Urutan hierarki dan tips menjaga konsistensi kode wilayah untuk form alamat.',
                    'body_html' => <<<'HTML'
<ol class="fs-14 mb-3">
<li>Buka <a href="/countries">Negara</a> → pastikan negara tempat perusahaan beroperasi sudah aktif.</li>
<li>Lanjut ke <a href="/states">Provinsi</a> → isi sesuai standar yang dipakai HR.</li>
<li>Terakhir <a href="/cities">Kota/Kabupaten</a> → hindari duplikasi nama tanpa kode wilayah yang berbeda.</li>
</ol>

<p class="fs-14 fw-normal mb-0">Setelah data wilayah stabil, uji coba satu form karyawan baru untuk memastikan pilihan berantai (negara → provinsi → kota) muncul dengan benar.</p>
HTML,
                ],
            ],
        ],

        // ====================================================================
        // KATEGORI: LEMBUR
        // ====================================================================
        [
            'slug' => 'lembur',
            'title' => 'Lembur',
            'icon' => 'ti ti-flame',
            'visible_to' => ['authenticated'],
            'description' => 'Permintaan lembur, master tipe lembur, dan kaitannya dengan payroll.',
            'articles' => [
                [
                    'slug' => 'lembur-admin-karyawan',
                    'visible_to' => ['authenticated'],
                    'title' => 'Lembur: admin vs karyawan',
                    'reading_minutes' => 5,
                    'excerpt' => 'Admin melihat semua pengajuan lembur, karyawan hanya melihat milik sendiri. Kalkulator lembur mengikuti aturan ketenagakerjaan Indonesia.',
                    'body_html' => <<<'HTML'
<p class="fs-14 fw-normal mb-3">Admin menggunakan halaman <a href="/overtime">Lembur</a> untuk melihat dan mengelola semua pengajuan lembur. Karyawan menggunakan <a href="/overtime-employee">Lembur Karyawan</a> yang hanya menampilkan data diri sendiri.</p>

<p class="fs-14 fw-normal mb-3">Master tipe lembur diatur di <a href="/overtime-master">Master Lembur</a> — ini mempengaruhi jenis lembur yang bisa dipilih di form.</p>

<p class="fs-14 fw-normal mb-0">Data lembur yang sudah disetujui akan masuk ke perhitungan <a href="/payroll-overtime">Payroll Lembur</a> saat admin menjalankan penggajian.</p>
HTML,
                ],
            ],
        ],

        // ====================================================================
        // KATEGORI: TIKET
        // ====================================================================
        [
            'slug' => 'tiket',
            'title' => 'Tiket Bantuan',
            'icon' => 'ti ti-lifebuoy',
            'visible_to' => ['authenticated'],
            'description' => 'Tiket bantuan untuk melaporkan masalah atau permintaan — karyawan membuat, admin menindaklanjuti.',
            'articles' => [
                [
                    'slug' => 'tiket-bantuan-internal',
                    'visible_to' => ['authenticated'],
                    'title' => 'Menggunakan tiket bantuan internal',
                    'reading_minutes' => 4,
                    'excerpt' => 'Karyawan buat tiket untuk masalah teknis/administratif, admin menindaklanjuti. Ada lampiran dan komentar untuk audit.',
                    'body_html' => <<<'HTML'
<p class="fs-14 fw-normal mb-3">Admin menggunakan <a href="/tickets-admin">Tiket Admin</a> untuk melihat dan mengelola semua tiket. Karyawan menggunakan <a href="/tickets-employee">Tiket Bantuan</a> untuk mengajukan masalah.</p>

<h6 class="fs-14 fw-semibold mb-2">Kapan memakai tiket</h6>
<ul class="knowledgebase ps-3 mb-0">
<li class="d-flex align-items-baseline mb-2"><i class="ti ti-point-filled me-1"></i> Permintaan perubahan data penting (wewenang, gaji) yang perlu jejak persetujuan.</li>
<li class="d-flex align-items-baseline mb-2"><i class="ti ti-point-filled me-1"></i> Masalah teknis yang tidak bisa diperbaiki sendiri (aplikasi error, akses bermasalah).</li>
</ul>
HTML,
                ],
                [
                    'slug' => 'master-kategori-tiket',
                    'visible_to' => ['admin'],
                    'title' => 'Mengatur kategori tiket (/ticket-master)',
                    'reading_minutes' => 4,
                    'excerpt' => 'Buat kategori tiket sebelum karyawan bisa mengajukan — misalnya IT, HR, Fasilitas.',
                    'body_html' => <<<'HTML'
<ol class="fs-14 mb-3">
<li>Buka <a href="/ticket-master">Master Tiket</a> sebagai admin HCM.</li>
<li>Buat kategori yang sesuai dengan SLA perusahaan — misalnya IT untuk masalah komputer, HR untuk masalah kepegawaian. Hindari kategori "Lainnya" yang terlalu umum.</li>
<li>Uji coba dengan membuat satu tiket dari halaman <a href="/tickets-employee">Tiket Bantuan</a> untuk memastikan pilihan kategori muncul.</li>
</ol>

<p class="fs-14 fw-normal mb-0">Perubahan kategori tidak akan mengubah tiket lama yang sudah ditutup — itu sudah sesuai untuk keperluan audit.</p>
HTML,
                ],
                [
                    'slug' => 'sop-tiket-admin-harian',
                    'visible_to' => ['admin'],
                    'title' => 'SOP harian: memproses tiket sebagai admin',
                    'reading_minutes' => 6,
                    'excerpt' => 'Dari melihat antrian, memberikan tanggapan, mengubah status, sampai menutup tiket.',
                    'body_html' => <<<'HTML'
<ol class="fs-14 mb-3">
<li>Pantau antrian tiket di <a href="/tickets-admin">Tiket Admin</a> atau tampilan grid <a href="/tickets-grid">Grid Tiket</a>.</li>
<li>Buka detail tiket lewat URL <code>/ticket-details/{id}</code>; tambahkan komentar — bedakan komentar internal vs yang bisa dilihat pemohon.</li>
<li>Ubah status atau tugaskan ke petugas yang tepat; lampirkan file bukti kalau diperlukan.</li>
<li>Tutup tiket hanya setelah pemohon mengonfirmasi atau SLA internal terpenuhi. Tiket yang sudah ditutup biasanya tidak bisa diedit lagi oleh pemohon.</li>
</ol>

<p class="fs-14 fw-normal mb-0">Untuk masalah platform (bukan isi HR), gunakan jalur Super Admin atau tiket internal terpisah agar tidak tercampur dengan keluhan karyawan biasa.</p>
HTML,
                ],
            ],
        ],

        // ====================================================================
        // KATEGORI: SUPER ADMIN (SAAS)
        // ====================================================================
        [
            'slug' => 'super-admin-saas',
            'title' => 'Super Admin (Operator Platform)',
            'icon' => 'ti ti-building-bank',
            'visible_to' => ['global_admin'],
            'description' => 'Menu untuk operator platform: mengelola perusahaan, paket, langganan, domain, dan transaksi. Bukan untuk admin HR harian.',
            'articles' => [
                [
                    'slug' => 'menu-super-admin',
                    'visible_to' => ['global_admin'],
                    'title' => 'Menu Super Admin: paket, langganan, perusahaan, domain',
                    'reading_minutes' => 5,
                    'excerpt' => 'Akses terbatas untuk operator platform yang mengelola tenant SaaS — beda dengan admin HR yang mengurus satu perusahaan.',
                    'body_html' => <<<'HTML'
<p class="fs-14 fw-normal mb-3">Submenu <strong>Super Admin</strong> di samping kiri (Perusahaan, Langganan, Paket, Domain, Transaksi) khusus untuk <strong>operator platform</strong> — bukan untuk admin HR yang mengurus karyawan di satu perusahaan.</p>

<ul class="knowledgebase ps-3 mb-3">
<li class="d-flex align-items-baseline mb-2"><i class="ti ti-point-filled me-1"></i> <a href="/packages">Paket</a> — definisi fitur yang tersedia per paket langganan.</li>
<li class="d-flex align-items-baseline mb-2"><i class="ti ti-point-filled me-1"></i> <a href="/subscription">Langganan</a> — melihat status langganan setiap perusahaan.</li>
<li class="d-flex align-items-baseline mb-2"><i class="ti ti-point-filled me-1"></i> <a href="/companies">Perusahaan</a> — data perusahaan pelanggan.</li>
</ul>

<p class="fs-14 fw-normal mb-0">Kalau menu ini tidak muncul di akun Anda, berarti Anda bukan operator platform global — kemungkinan Anda hanya admin di satu perusahaan.</p>
HTML,
                ],
                [
                    'slug' => 'domain-dan-transaksi',
                    'visible_to' => ['global_admin'],
                    'title' => 'Domain custom dan transaksi pembelian',
                    'reading_minutes' => 3,
                    'excerpt' => 'Verifikasi domain tenant dan lihat jejak transaksi untuk audit billing.',
                    'body_html' => <<<'HTML'
<p class="fs-14 fw-normal mb-3"><a href="/domain">Domain</a> mengelola alamat website khusus setiap perusahaan. <a href="/purchase-transaction">Transaksi Pembelian</a> mencatat semua transaksi paket dan add-on.</p>
<p class="fs-14 fw-normal mb-0">Untuk pertanyaan tentang domain atau status verifikasi, hubungi tim operator platform melalui tiket dukungan internal.</p>
HTML,
                ],
                [
                    'slug' => 'invoice-dan-pembayaran',
                    'visible_to' => ['global_admin'],
                    'title' => 'Invoice, pembayaran, dan tampilan billing',
                    'reading_minutes' => 6,
                    'excerpt' => 'Berbagai halaman invoice dan pembayaran — pastikan memakai yang sesuai dengan peran operasional Anda.',
                    'body_html' => <<<'HTML'
<p class="fs-14 fw-normal mb-3">Billing memiliki beberapa halaman: <a href="/invoices">Invoice</a> (umum), <a href="/saas/invoices">Invoice SaaS</a>, dan <a href="/company/invoices">Invoice Perusahaan</a>. Gunakan yang sesuai peran Anda — jangan membagikan URL admin ke pemilik tenant.</p>

<ol class="fs-14 mb-3">
<li>Cocokkan invoice dengan data langganan di <a href="/subscription">Langganan</a> (status paket).</li>
<li>Catat pembayaran lewat halaman pembayaran yang sesuai.</li>
<li>Semua aksi finansial sebaiknya punya jejak tiket atau catatan internal.</li>
</ol>

<p class="fs-14 fw-normal mb-0">Untuk pertanyaan tentang jatuh tempo atau penghentian layanan, hubungi operator platform.</p>
HTML,
                ],
            ],
        ],

        // ====================================================================
        // KATEGORI: REFERENSI DAN DUKUNGAN
        // ====================================================================
        [
            'slug' => 'referensi-dukungan',
            'title' => 'Referensi dan Dukungan',
            'icon' => 'ti ti-lifebuoy',
            'visible_to' => ['admin'],
            'description' => 'Pointer ke laporan, mutasi karyawan (promosi/resignasi/terminasi), dan dokumentasi teknis.',
            'articles' => [
                [
                    'slug' => 'laporan-hr-ringkas',
                    'visible_to' => ['admin'],
                    'title' => 'Laporan HR: ringkasan',
                    'reading_minutes' => 4,
                    'excerpt' => 'Laporan karyawan, absensi, slip gaji — dan perbedaan antara data langsung vs arsip (kalau fitur snapshot aktif).',
                    'body_html' => <<<'HTML'
<p class="fs-14 fw-normal mb-3">Halaman laporan seperti <a href="/employee-report">Laporan Karyawan</a>, <a href="/attendance-report">Laporan Absensi</a>, dan <a href="/payslip-report">Laporan Slip Gaji</a> menggunakan pola yang sama: filter, export, dan batas halaman sesuai pengaturan.</p>
<p class="fs-14 fw-normal mb-0">Kalau perusahaan Anda memakai fitur snapshot (arsip laporan), tanyakan ke admin utama perbedaan antara mode langsung (live) dan arsip — biar laporan yang Anda lihat sesuai kebutuhan.</p>
HTML,
                ],
                [
                    'slug' => 'mutasi-karyawan',
                    'visible_to' => ['admin'],
                    'title' => 'Mutasi karyawan: promosi, pengunduran diri, pemutusan',
                    'reading_minutes' => 3,
                    'excerpt' => 'Tiga modul administrasi perubahan status karyawan — semua hanya untuk admin.',
                    'body_html' => <<<'HTML'
<p class="fs-14 fw-normal mb-3">Halaman <a href="/promotion">Promosi</a>, <a href="/resignation">Pengunduran Diri</a>, dan <a href="/termination">Pemutusan</a> mencatat perubahan status karyawan. Non-admin hanya melihat data terkait diri sendiri di halaman detail karyawan — daftar global tetap untuk admin.</p>
<p class="fs-14 fw-normal mb-0">Sebelum mencatat mutasi, pastikan cuti dan penggajian periode berjalan sudah tidak bertabrakan dengan tanggal efektif mutasi.</p>
HTML,
                ],
                [
                    'slug' => 'kinerja-dan-pelatihan-ringkas',
                    'visible_to' => ['admin'],
                    'title' => 'Kinerja dan pelatihan — mulai dari sini',
                    'reading_minutes' => 2,
                    'excerpt' => 'Untuk panduan lengkap penilaian kinerja, sasaran, dan pelatihan, buka kategori Kinerja, Sasaran, dan Pelatihan di Pusat Bantuan ini.',
                    'body_html' => <<<'HTML'
<p class="fs-14 fw-normal mb-3">Butuh panduan <strong>lengkap</strong> tentang penilaian kinerja, pelacakan sasaran, atau pelatihan? Buka kategori <a href="/knowledgebase/category/kinerja-goals-pelatihan">Kinerja, Sasaran, dan Pelatihan</a> di Pusat Bantuan ini — di sana ada panduan langkah demi langkah.</p>
<p class="fs-14 fw-normal mb-0">Artikel ini hanya pengingat singkat.</p>
HTML,
                ],
                [
                    'slug' => 'ekspor-rekonsiliasi',
                    'visible_to' => ['admin'],
                    'title' => 'Export rekonsiliasi dan audit payroll',
                    'reading_minutes' => 3,
                    'excerpt' => 'Cara menghasilkan bukti export sebelum finalisasi gaji — penting untuk audit keuangan.',
                    'body_html' => <<<'HTML'
<p class="fs-14 fw-normal mb-3">Sebelum mengunci penggajian, sistem mungkin mewajibkan export data rekonsiliasi terlebih dahulu. Ini untuk memastikan setiap proses gaji punya bukti audit yang lengkap.</p>
<p class="fs-14 fw-normal mb-0">Kalau tombol finalisasi payroll terkunci, periksa apakah langkah export rekonsiliasi sudah dikerjakan. Kalau belum ada halaman khusus untuk ini, hubungi operator platform untuk panduan lebih lanjut.</p>
HTML,
                ],
            ],
        ],

        // ====================================================================
        // KATEGORI: FAQ (BARU)
        // ====================================================================
        [
            'slug' => 'faq',
            'title' => 'Pertanyaan yang Sering Diajukan (FAQ)',
            'icon' => 'ti ti-question-mark',
            'visible_to' => ['authenticated'],
            'description' => 'Kumpulan pertanyaan yang paling sering ditanyakan pengguna — dari absen tidak masuk sampai slip gaji kosong.',
            'articles' => [
                [
                    'slug' => 'faq-absensi-cuti-gaji',
                    'visible_to' => ['authenticated'],
                    'title' => 'FAQ: absensi, cuti, slip gaji, dan masalah umum',
                    'reading_minutes' => 6,
                    'excerpt' => 'Jawaban singkat untuk pertanyaan yang paling sering muncul: kenapa absen tidak masuk, kenapa cuti ditolak, kenapa slip gaji kosong, dan lainnya.',
                    'body_html' => <<<'HTML'
<h6 class="fs-14 fw-semibold mb-2">Absensi</h6>

<p class="fs-14 fw-normal mb-2"><strong>Q: Saya sudah absen, tapi kenapa tidak tercatat?</strong></p>
<p class="fs-14 fw-normal mb-3">A: Mungkin koneksi internet terputus saat Anda menekan tombol absen. Coba muat ulang halaman dan periksa apakah status absen sudah "Masuk" atau "Pulang". Kalau masih tidak muncul, hubungi admin HR.</p>

<p class="fs-14 fw-normal mb-2"><strong>Q: GPS tidak berfungsi, bagaimana?</strong></p>
<p class="fs-14 fw-normal mb-3">A: Pastikan browser Anda sudah mengizinkan akses lokasi. Kalau tetap tidak bisa, Anda bisa mengklik titik di peta sebagai lokasi manual.</p>

<h6 class="fs-14 fw-semibold mb-2">Cuti</h6>

<p class="fs-14 fw-normal mb-2"><strong>Q: Kok pilihan jenis cutinya kosong?</strong></p>
<p class="fs-14 fw-normal mb-3">A: Admin perusahaan belum mengaktifkan jenis cuti yang Anda butuhkan. Hubungi admin HR untuk minta diaktifkan.</p>

<p class="fs-14 fw-normal mb-2"><strong>Q: Kenapa cuti saya ditolak?</strong></p>
<p class="fs-14 fw-normal mb-3">A: Beberapa kemungkinan: kuota cuti Anda sudah habis, tanggal yang dipilih sudah termasuk hari libur, atau ada aturan cuti yang tidak terpenuhi. Cek pesan penolakan di halaman cuti Anda.</p>

<h6 class="fs-14 fw-semibold mb-2">Slip Gaji</h6>

<p class="fs-14 fw-normal mb-2"><strong>Q: Slip gaji saya kosong, kenapa?</strong></p>
<p class="fs-14 fw-normal mb-3">A: Penggajian bulan itu belum dikunci (finalisasi) oleh admin. Tunggu saja atau tanyakan ke admin kira-kira kaka selesai.</p>

<p class="fs-14 fw-normal mb-2"><strong>Q: Angka di slip gaji tidak sesuai?</strong></p>
<p class="fs-14 fw-normal mb-3">A: Hubungi admin HR atau bagian keuangan untuk klarifikasi. Jangan mengubah data sendiri karena data gaji hanya bisa diubah oleh admin.</p>

<h6 class="fs-14 fw-semibold mb-2">Login & Akun</h6>

<p class="fs-14 fw-normal mb-2"><strong>Q: Tiba-tiba diminta login lagi?</strong></p>
<p class="fs-14 fw-normal mb-3">A: Itu wajar — biasanya karena sesi login sudah habis. Cukup login ulang.</p>

<p class="fs-14 fw-normal mb-2"><strong>Q: Lupa password, bagaimana?</strong></p>
<p class="fs-14 fw-normal mb-3">A: Klik "Lupa Kata Sandi" di halaman login. Email reset akan dikirim ke email Anda.</p>

<p class="fs-14 fw-normal mb-2"><strong>Q: Muncul tulisan "Akses Ditolak"?</strong></p>
<p class="fs-14 fw-normal mb-3">A: Itu berarti halaman yang Anda buka khusus untuk admin. Bukan error — ini perlindungan data.</p>

<h6 class="fs-14 fw-semibold mb-2">Lain-lain</h6>

<p class="fs-14 fw-normal mb-2"><strong>Q: Bagaimana cara mengubah nomor telepon atau alamat?</strong></p>
<p class="fs-14 fw-normal mb-3">A: Buka profil Anda di <a href="/employee-details">Detail Karyawan</a>. Beberapa data bisa diubah sendiri, tapi data gaji dan jabatan hanya bisa diubah admin.</p>

<p class="fs-14 fw-normal mb-0"><strong>Q: Ada masalah lain yang tidak tercantum di sini?</strong></p>
<p class="fs-14 fw-normal mb-0">A: Buat tiket bantuan lewat <a href="/tickets-employee">Tiket Bantuan</a> — tim kami akan membantu.</p>
HTML,
                ],
            ],
        ],
    ],
];
