<?php

/**
 * Bantuan dalam aplikasi (Knowledge Base). Konten statis tepercaya dari repo.
 * Slug artikel harus unik seluruh kategori. Field opsional: category `description`, article `reading_minutes`.
 */
return [
    'categories' => [
        [
            'slug' => 'memulai',
            'title' => 'Memulai dan akun',
            'icon' => 'ti ti-book',
            'visible_to' => ['authenticated'],
            'description' => 'Cara masuk aplikasi, memahami peran pengguna, dan menavigasi Arcav HCM dengan benar.',
            'articles' => [
                [
                    'slug' => 'login-perusahaan-dan-token',
                    'title' => 'Cara login dan memilih konteks perusahaan',
                    'reading_minutes' => 4,
                    'excerpt' => 'Langkah masuk ke Arcav HCM, memastikan perusahaan yang aktif sudah benar, dan apa yang dilakukan saat sesi habis atau tampilan kosong.',
                    'body_html' => <<<'HTML'
<p class="fs-14 fw-normal mb-3">Masuk ke Arcav HCM melalui <a href="/login">/login</a> dengan email dan kata sandi yang diberikan admin Anda. Setelah berhasil, sistem menyimpan sesi login di browser Anda secara otomatis.</p>
<h6 class="fs-14 fw-semibold mb-2">Organisasi dengan lebih dari satu perusahaan</h6>
<p class="fs-14 fw-normal mb-3">Jika organisasi Anda terdaftar dengan beberapa entitas perusahaan, pastikan perusahaan yang aktif di layar sudah sesuai. Data cuti, absensi, dan payroll tampil berdasarkan perusahaan yang sedang aktif — jika daftar terlihat kosong padahal seharusnya ada data, periksa konteks perusahaan di sudut kanan atas.</p>
<h6 class="fs-14 fw-semibold mb-2">Sesi habis atau diarahkan ke halaman login</h6>
<p class="fs-14 fw-normal mb-3">Jika tiba-tiba diarahkan kembali ke halaman login di tengah pekerjaan, sesi Anda telah berakhir. Cukup login ulang dan lanjutkan aktivitas. Jika tampilan terasa tidak sesuai setelah pembaruan sistem, muat ulang halaman dengan menekan Ctrl + Shift + R (atau Cmd + Shift + R di Mac).</p>
<h6 class="fs-14 fw-semibold mb-2">Tips login yang baik</h6>
<ul class="knowledgebase ps-3 mb-0">
<li class="d-flex align-items-baseline mb-2"><i class="ti ti-point-filled me-1"></i> Gunakan akun yang sesuai peran Anda — akun admin dan akun karyawan menampilkan menu yang berbeda.</li>
<li class="d-flex align-items-baseline mb-2"><i class="ti ti-point-filled me-1"></i> Jangan berbagi akun login dengan orang lain; setiap pengguna sebaiknya memiliki akun sendiri agar jejak aktivitas tetap akurat.</li>
<li class="d-flex align-items-baseline mb-2"><i class="ti ti-point-filled me-1"></i> Jika lupa kata sandi, gunakan tautan <strong>Lupa Kata Sandi</strong> di halaman login untuk mereset lewat email.</li>
</ul>
HTML,
                ],
                [
                    'slug' => 'peran-hcm-admin-vs-karyawan',
                    'title' => 'Perbedaan akses: admin HCM dan karyawan',
                    'reading_minutes' => 5,
                    'excerpt' => 'Siapa yang bisa mengelola data semua orang, siapa yang hanya mengakses data diri sendiri, dan apa yang terjadi saat akses ditolak.',
                    'body_html' => <<<'HTML'
<p class="fs-14 fw-normal mb-3">Arcav HCM membedakan dua jenis pengguna utama: <strong>Admin HCM</strong> dan <strong>Karyawan</strong>. Perbedaan ini menentukan menu apa yang tampil dan aksi apa yang bisa dilakukan.</p>
<h6 class="fs-14 fw-semibold mb-2">HCM Admin</h6>
<p class="fs-14 fw-normal mb-3">Admin dapat mengelola data seluruh karyawan di perusahaan: membuka direktori karyawan, mengatur shift dan jadwal, menjalankan payroll, menyetujui cuti, mengelola pengaturan sistem, dan melihat laporan agregat. Jika menu-menu ini tidak muncul, kemungkinan akun Anda belum dikonfigurasi sebagai admin oleh administrator utama.</p>
<h6 class="fs-14 fw-semibold mb-2">Karyawan</h6>
<p class="fs-14 fw-normal mb-3">Karyawan hanya bisa mengakses data milik sendiri: absensi, pengajuan cuti, slip gaji, dan tiket bantuan. Halaman seperti direktori karyawan lengkap atau pengaturan sistem tidak dapat diakses oleh akun karyawan biasa.</p>
<h6 class="fs-14 fw-semibold mb-2">Saat akses ditolak</h6>
<p class="fs-14 fw-normal mb-3">Jika Anda mencoba membuka halaman yang bukan hak Anda, sistem akan menolak atau mengalihkan ke halaman yang sesuai. Ini bukan error — melainkan perlindungan data agar informasi karyawan lain tidak bisa diakses sembarangan.</p>
<div class="table-responsive mb-0"><table class="table table-sm table-bordered fs-13 mb-0"><thead><tr><th>Skenario</th><th>Yang terjadi</th></tr></thead><tbody><tr><td>Karyawan membuka halaman admin</td><td>Dialihkan ke halaman yang sesuai atau ditolak</td></tr><tr><td>Admin membuka halaman self-service karyawan</td><td>Biasanya dialihkan ke tampilan admin setara</td></tr><tr><td>Sesi habis</td><td>Diarahkan kembali ke halaman login</td></tr></tbody></table></div>
HTML,
                ],
                [
                    'slug' => 'peta-modul-dari-halaman-pages',
                    'title' => 'Daftar modul dan halaman (/pages)',
                    'reading_minutes' => 3,
                    'excerpt' => 'Indeks cepat seluruh halaman Arcav HCM; berguna untuk admin baru yang ingin tahu ada fitur apa saja.',
                    'body_html' => <<<'HTML'
<p class="fs-14 fw-normal mb-3">Halaman <a href="/pages">/pages</a> menampilkan daftar seluruh modul dan halaman yang tersedia di Arcav HCM. Gunakan halaman ini jika Anda ingin menjelajahi fitur yang belum pernah Anda buka sebelumnya.</p>
<p class="fs-14 fw-normal mb-0">Cukup klik nama halaman yang ingin Anda buka. Halaman yang memerlukan akses admin tidak akan bisa dibuka oleh akun karyawan biasa — sistem akan mengalihkan secara otomatis.</p>
HTML,
                ],
                [
                    'slug' => 'troubleshooting-akses-401-403-422',
                    'title' => 'Mengatasi masalah akses: tidak bisa masuk, ditolak, atau data tidak valid',
                    'reading_minutes' => 4,
                    'excerpt' => 'Panduan untuk tiga masalah umum: sesi habis, akses ditolak, dan isian yang ditolak sistem.',
                    'body_html' => <<<'HTML'
<p class="fs-14 fw-normal mb-3">Ada tiga jenis masalah akses yang paling sering dialami pengguna Arcav HCM. Berikut cara mengenali dan mengatasinya.</p>
<h6 class="fs-14 fw-semibold mb-2">Diarahkan kembali ke halaman login</h6>
<p class="fs-14 fw-normal mb-3">Artinya sesi Anda telah berakhir atau belum login. Solusi: masuk kembali dengan akun Anda. Jika terjadi berulang dalam waktu singkat, hubungi admin untuk memeriksa status akun.</p>
<h6 class="fs-14 fw-semibold mb-2">Muncul pesan akses ditolak</h6>
<p class="fs-14 fw-normal mb-3">Artinya halaman atau aksi tersebut memerlukan peran yang lebih tinggi dari akun Anda. Misalnya, halaman pengelolaan payroll hanya bisa dibuka oleh admin HCM. Jika Anda merasa seharusnya punya akses, hubungi admin HR untuk menyesuaikan peran akun Anda.</p>
<h6 class="fs-14 fw-semibold mb-2">Sistem menolak data yang diisi</h6>
<p class="fs-14 fw-normal mb-3">Artinya ada isian yang tidak sesuai aturan, misalnya: tanggal cuti yang sudah lewat, absensi tanpa izin lokasi, atau konfirmasi kata sandi yang tidak cocok. Baca pesan yang muncul di layar — sistem akan menunjukkan bagian mana yang perlu diperbaiki.</p>
<ul class="knowledgebase ps-3 mb-0">
<li class="d-flex align-items-baseline mb-2"><i class="ti ti-point-filled me-1"></i> Izinkan browser mengakses lokasi jika melakukan absensi.</li>
<li class="d-flex align-items-baseline mb-2"><i class="ti ti-point-filled me-1"></i> Pastikan semua kolom wajib sudah diisi sebelum menekan tombol simpan.</li>
<li class="d-flex align-items-baseline mb-2"><i class="ti ti-point-filled me-1"></i> Jika masalah berlanjut setelah mencoba langkah di atas, buat tiket bantuan lewat <a href="/tickets-employee">/tickets-employee</a>.</li>
</ul>
HTML,
                ],
                [
                    'slug' => 'checklist-onboarding-admin-hcm',
                    'visible_to' => ['admin'],
                    'title' => 'Checklist onboarding admin HCM (minggu pertama)',
                    'reading_minutes' => 8,
                    'excerpt' => 'Urutan kerja disarankan: master → karyawan → cuti/absensi → payroll — supaya tim tidak bolak-balik tanya hal yang sama.',
                    'body_html' => <<<'HTML'
<p class="fs-14 fw-normal mb-3">Gunakan daftar ini sebagai <strong>SOP internal</strong> saat tenant baru live atau admin baru bergabung. Tandai setiap poin di spreadsheet proyek Anda.</p>
<h6 class="fs-14 fw-semibold mb-2">Hari 1 — fondasi data</h6>
<ol class="fs-14 mb-3">
<li>Buka <a href="/departments">/departments</a> dan <a href="/designations">/designations</a> → lengkapi struktur organisasi minimal 1 level.</li>
<li>Buka <a href="/policy">/policy</a> → masukkan kebijakan yang dipakai modul lain (cuti, disiplin) bila ada.</li>
<li>Buka <a href="/leave-type">/leave-type</a> dan <a href="/leave-settings">/leave-settings</a> → aktifkan tipe cuti dan aturan kuota/approval.</li>
<li>Buka <a href="/holidays">/holidays</a> → isi libur nasional / sync baseline.</li>
</ol>
<h6 class="fs-14 fw-semibold mb-2">Hari 2 — orang dan akses</h6>
<ol class="fs-14 mb-3">
<li><a href="/employees">/employees</a> → impor atau entri karyawan; verifikasi satu profil di <a href="/employee-details">/employee-details</a>.</li>
<li><a href="/shift-master">/shift-master</a> → definisi jam kerja; <a href="/schedule-timing">/schedule-timing</a> → pasangkan ke karyawan.</li>
<li><a href="/users">/users</a> dan <a href="/roles-permissions">/roles-permissions</a> → cek mapping role jika organisasi memakai RBAC lanjutan (selaras API user-management bila di-wire).</li>
</ol>
<h6 class="fs-14 fw-semibold mb-2">Hari 3–5 — operasional harian</h6>
<ol class="fs-14 mb-3">
<li>Uji <a href="/attendance-employee">/attendance-employee</a> (punch + GPS) dengan akun karyawan sungguhan.</li>
<li>Uji <a href="/leaves-employee">/leaves-employee</a> → ajukan cuti → setujui di <a href="/leaves">/leaves</a>.</li>
<li>Siapkan <a href="/payroll">/payroll</a> items → <a href="/employee-salary">/employee-salary</a> assignment → dry-run <a href="/payroll-run">/payroll-run</a> di periode uji.</li>
</ol>
<p class="fs-14 fw-normal mb-0">Setelah stabil, arahkan user ke <strong>Knowledge Base</strong> (halaman ini) dan <a href="/pages">/pages</a> agar pertanyaan berulang berkurang.</p>
HTML,
                ],
                [
                    'slug' => 'panduan-admin-harian-hcm',
                    'visible_to' => ['admin'],
                    'title' => 'Tutorial lengkap: admin memakai aplikasi dari login sampai operasional harian',
                    'reading_minutes' => 10,
                    'excerpt' => 'Panduan end-to-end untuk admin: masuk aplikasi, cek dashboard, kelola master, verifikasi karyawan, proses cuti/absensi, sampai review payroll.',
                    'body_html' => <<<'HTML'
<p class="fs-14 fw-normal mb-3">Artikel ini ditujukan untuk <strong>admin HR / operator HCM</strong> yang baru memakai Arcav HCM dan butuh panduan praktis, bukan hanya daftar menu. Ikuti urutan ini agar Anda paham jalur kerja harian tanpa lompat-lompat halaman.</p>
<h6 class="fs-14 fw-semibold mb-2">1. Login dan pastikan konteks perusahaan benar</h6>
<ol class="fs-14 mb-3">
<li>Masuk lewat <a href="/login">/login</a> dengan akun admin tenant.</li>
<li>Setelah berhasil, buka <a href="/index">/index</a> dan pastikan sidebar admin tampil lengkap. Jika menu payroll, employees, atau settings tidak muncul, cek role Anda.</li>
<li>Jika data tampak kosong, periksa apakah company/tenant yang aktif benar. Di Arcav HCM, konteks perusahaan mempengaruhi semua data yang terbaca.</li>
</ol>
<h6 class="fs-14 fw-semibold mb-2">2. Pahami dashboard, tapi jangan berhenti di sana</h6>
<p class="fs-14 fw-normal mb-3">Dashboard di <a href="/index">/index</a> hanya pintu masuk. Gunakan untuk melihat ringkasan, lalu masuk ke modul kerja sebenarnya dari sidebar. Banyak admin baru salah paham karena mengira semua aksi utama dilakukan dari dashboard.</p>
<h6 class="fs-14 fw-semibold mb-2">3. Siapkan master sebelum mengurus transaksi</h6>
<ol class="fs-14 mb-3">
<li>Buka <a href="/departments">/departments</a> dan <a href="/designations">/designations</a> untuk menyiapkan struktur organisasi.</li>
<li>Buka <a href="/policy">/policy</a> bila perusahaan Anda memakai kebijakan HR internal yang ingin dirujuk sistem.</li>
<li>Jika akan memakai cuti, isi <a href="/leave-type">/leave-type</a>, <a href="/leave-settings">/leave-settings</a>, dan <a href="/holidays">/holidays</a> lebih dulu.</li>
<li>Jika akan memakai absensi, siapkan <a href="/shift-master">/shift-master</a> lalu pasangkan ke user di <a href="/schedule-timing">/schedule-timing</a>.</li>
</ol>
<h6 class="fs-14 fw-semibold mb-2">4. Kelola data karyawan</h6>
<ol class="fs-14 mb-3">
<li>Masuk ke <a href="/employees">/employees</a> untuk daftar utama karyawan.</li>
<li>Tambah atau impor data, lalu buka satu profil lewat <a href="/employee-details">/employee-details</a> untuk verifikasi departemen, jabatan, kontak, dan status kerja.</li>
<li>Jika karyawan akan langsung ikut payroll, cek juga kompensasinya di <a href="/employee-salary">/employee-salary</a>.</li>
</ol>
<h6 class="fs-14 fw-semibold mb-2">5. Jalankan operasional harian</h6>
<ul class="knowledgebase ps-3 mb-3">
<li class="d-flex align-items-baseline mb-2"><i class="ti ti-point-filled me-1"></i> <strong>Cuti:</strong> monitor pengajuan di <a href="/leaves">/leaves</a>, approve/decline sesuai kebijakan.</li>
<li class="d-flex align-items-baseline mb-2"><i class="ti ti-point-filled me-1"></i> <strong>Absensi:</strong> cek rekap di <a href="/attendance-admin">/attendance-admin</a>, cocokkan dengan <a href="/timesheets">/timesheets</a> jika ada selisih jam.</li>
<li class="d-flex align-items-baseline mb-2"><i class="ti ti-point-filled me-1"></i> <strong>Tiket:</strong> jika tenant memakai helpdesk internal, pantau di <a href="/tickets-admin">/tickets-admin</a>.</li>
</ul>
<h6 class="fs-14 fw-semibold mb-2">6. Tutup periode dengan payroll</h6>
<ol class="fs-14 mb-3">
<li>Pastikan absensi, lembur, dan cuti di periode tersebut sudah final.</li>
<li>Masuk ke <a href="/payroll-run">/payroll-run</a> untuk calculate draft.</li>
<li>Review angka per karyawan, finalisasi jika sudah sesuai, lalu lanjut ke disburse sesuai alur tenant Anda.</li>
<li>Setelah final, minta karyawan cek slip mereka di <a href="/payslip">/payslip</a>.</li>
</ol>
<h6 class="fs-14 fw-semibold mb-2">7. Kebiasaan admin yang benar</h6>
<ul class="knowledgebase ps-3 mb-0">
<li class="d-flex align-items-baseline mb-2"><i class="ti ti-point-filled me-1"></i> Jangan ubah master besar-besaran di tengah proses payroll tanpa komunikasi ke finance.</li>
<li class="d-flex align-items-baseline mb-2"><i class="ti ti-point-filled me-1"></i> Setelah mengubah setting penting, uji satu data contoh sampai benar-benar muncul di layar operasional.</li>
<li class="d-flex align-items-baseline mb-2"><i class="ti ti-point-filled me-1"></i> Kalau bingung urutan modul, kembali ke artikel ini dulu sebelum lompat ke artikel teknis per modul.</li>
</ul>
HTML,
                ],
                [
                    'slug' => 'panduan-karyawan-harian-self-service',
                    'visible_to' => ['employee'],
                    'title' => 'Tutorial lengkap: karyawan memakai aplikasi untuk absensi, cuti, slip, dan tiket',
                    'reading_minutes' => 8,
                    'excerpt' => 'Panduan end-to-end untuk user karyawan: masuk aplikasi, absen, ajukan cuti, lihat slip gaji, dan minta bantuan lewat tiket.',
                    'body_html' => <<<'HTML'
<p class="fs-14 fw-normal mb-3">Artikel ini khusus untuk <strong>karyawan / end user</strong>. Jika Anda bukan admin, fokus Anda ada di fitur self-service berikut: dashboard pribadi, absensi, cuti, slip gaji, dan tiket bantuan.</p>
<h6 class="fs-14 fw-semibold mb-2">1. Login dan buka dashboard pribadi</h6>
<ol class="fs-14 mb-3">
<li>Masuk lewat <a href="/login">/login</a> dengan akun karyawan.</li>
<li>Setelah login, biasanya Anda akan diarahkan ke <a href="/employee-dashboard">/employee-dashboard</a>.</li>
<li>Kalau yang muncul justru dashboard admin atau menu terlalu banyak, kemungkinan akun yang dipakai bukan akun karyawan biasa.</li>
</ol>
<h6 class="fs-14 fw-semibold mb-2">2. Lakukan absensi harian</h6>
<ol class="fs-14 mb-3">
<li>Buka <a href="/attendance-employee">/attendance-employee</a>.</li>
<li>Izinkan browser mengakses lokasi agar sistem bisa mengambil koordinat GPS.</li>
<li>Tekan punch in / punch out sesuai jam kerja Anda.</li>
<li>Jika perusahaan mewajibkan selfie, lakukan setelah punch berhasil. Jika selfie ditolak, biasanya karena absensi hari itu belum dimulai.</li>
</ol>
<h6 class="fs-14 fw-semibold mb-2">3. Ajukan cuti saat diperlukan</h6>
<ol class="fs-14 mb-3">
<li>Buka <a href="/leaves-employee">/leaves-employee</a>.</li>
<li>Pilih tipe cuti, tanggal mulai, tanggal selesai, dan alasan.</li>
<li>Setelah disubmit, pantau statusnya. Pending berarti masih menunggu persetujuan admin/atasan.</li>
<li>Jika dropdown tipe cuti kosong, artinya admin perusahaan belum mengaktifkan master cuti yang dibutuhkan.</li>
</ol>
<h6 class="fs-14 fw-semibold mb-2">4. Cek slip gaji setelah payroll final</h6>
<ol class="fs-14 mb-3">
<li>Buka <a href="/payslip">/payslip</a>.</li>
<li>Pilih periode yang ingin dilihat.</li>
<li>Jika data masih kosong, biasanya payroll periode itu belum difinalisasi admin.</li>
</ol>
<h6 class="fs-14 fw-semibold mb-2">5. Minta bantuan lewat tiket bila ada kendala</h6>
<ol class="fs-14 mb-3">
<li>Buka <a href="/tickets-employee">/tickets-employee</a>.</li>
<li>Buat tiket untuk masalah seperti absensi tidak masuk, cuti tidak bisa diajukan, atau pertanyaan administratif yang perlu jejak.</li>
<li>Isi masalah dengan jelas dan lampirkan bukti jika perlu.</li>
</ol>
<h6 class="fs-14 fw-semibold mb-2">6. Hal yang perlu dipahami karyawan</h6>
<ul class="knowledgebase ps-3 mb-0">
<li class="d-flex align-items-baseline mb-2"><i class="ti ti-point-filled me-1"></i> Karyawan tidak bisa membuka atau mengubah data semua orang seperti admin.</li>
<li class="d-flex align-items-baseline mb-2"><i class="ti ti-point-filled me-1"></i> Jika sistem menampilkan 403 atau akses ditolak, itu biasanya karena halaman tersebut memang hanya untuk admin.</li>
<li class="d-flex align-items-baseline mb-2"><i class="ti ti-point-filled me-1"></i> Untuk kendala akun atau role, hubungi admin HR internal Anda lebih dulu.</li>
</ul>
HTML,
                ],
            ],
        ],
        [
            'slug' => 'dashboard',
            'title' => 'Dashboard dan beranda',
            'icon' => 'ti ti-layout-dashboard',
            'visible_to' => ['authenticated'],
            'description' => 'Perbedaan dashboard admin vs karyawan dan ekspektasi data yang tampil.',
            'articles' => [
                [
                    'slug' => 'tutorial-dashboard-admin-index',
                    'visible_to' => ['admin'],
                    'title' => 'Dashboard admin (/index)',
                    'reading_minutes' => 4,
                    'excerpt' => 'Ringkasan widget, chart template, dan bagian mana yang perlu di-wire ke API produksi.',
                    'body_html' => <<<'HTML'
<p class="fs-14 fw-normal mb-3"><a href="/index">/index</a> adalah beranda utama setelah login. Beranda menampilkan ringkasan status terkini: total karyawan aktif, absensi hari ini, cuti yang menunggu persetujuan, dan notifikasi penting lainnya sesuai konfigurasi perusahaan.</p>
<h6 class="fs-14 fw-semibold mb-2">Langkah</h6>
<ol class="fs-14 mb-3"><li>Jika widget beranda mendadak kosong, coba muat ulang halaman atau login ulang jika sesi telah habis.</li><li>Buka submenu prioritas (Karyawan, Absensi, Payroll) dari sidebar — beranda merupakan ringkasan, bukan pengganti halaman operasional.</li><li>Untuk laporan resmi, gunakan halaman laporan terkait seperti <a href="/employee-report">/employee-report</a> yang menampilkan data lengkap.</li></ol>
<p class="fs-14 fw-normal mb-0">Admin sekunder atau dengan peran terbatas mungkin melihat subset menu — itu sudah sesuai konfigurasi akses yang ditentukan admin utama; hubungi admin utama jika ada menu yang seharusnya tersedia.</p>
HTML,
                ],
                [
                    'slug' => 'tutorial-dashboard-karyawan',
                    'visible_to' => ['employee'],
                    'title' => 'Dashboard karyawan (/employee-dashboard)',
                    'reading_minutes' => 3,
                    'excerpt' => 'Ringkasan self-service: cuti, absensi, slip; redirect admin agar tidak salah konteks.',
                    'body_html' => <<<'HTML'
<p class="fs-14 fw-normal mb-3"><a href="/employee-dashboard">/employee-dashboard</a> memfokuskan pengguna pada tugas harian: kehadiran, cuti, pengumuman ringkas. HCM admin yang membuka URL karyawan mungkin diarahkan ke <code>/index</code> agar tidak mencampur aksi admin dengan konteks self.</p>
<ul class="knowledgebase ps-3 mb-0">
<li class="d-flex align-items-baseline mb-2"><i class="ti ti-point-filled me-1"></i> Ajarkan karyawan baru: slip gaji ada di <a href="/payslip">/payslip</a> setelah payroll final.</li>
<li class="d-flex align-items-baseline mb-2"><i class="ti ti-point-filled me-1"></i> Tiket bantuan: <a href="/tickets-employee">/tickets-employee</a> untuk eskalasi non-gaji.</li>
</ul>
HTML,
                ],
            ],
        ],
        [
            'slug' => 'organisasi',
            'title' => 'Karyawan dan organisasi',
            'icon' => 'ti ti-users',
            'visible_to' => ['admin'],
            'description' => 'Direktori karyawan, master organisasi, kebijakan, dan batas akses profil untuk admin vs self.',
            'articles' => [
                [
                    'slug' => 'direktori-karyawan-dan-profil',
                    'title' => 'Direktori karyawan dan halaman detail',
                    'reading_minutes' => 6,
                    'excerpt' => 'Alur /employees dan /employee-details: list dan bulk untuk admin, profil self untuk karyawan, serta field sensitif yang hanya boleh diubah admin.',
                    'body_html' => <<<'HTML'
<h6 class="fs-14 fw-semibold mb-2">Admin: direktori</h6>
<p class="fs-14 fw-normal mb-3"><a href="/employees">/employees</a> (dan varian grid) memuat daftar paginated. Dari sini admin membuka <a href="/employee-details">/employee-details</a> dengan context karyawan yang dipilih. Operasi <strong>buat / bulk</strong> hanya untuk HCM admin — jangan ekspektasi karyawan biasa bisa menambah rekan dari URL yang sama.</p>
<h6 class="fs-14 fw-semibold mb-2">Karyawan: profil sendiri</h6>
<p class="fs-14 fw-normal mb-3">Non-admin yang membuka detail profil hanya menyentuh data diri. Field yang boleh di-<code>PUT</code> dibatasi subset (mis. kontak, rekening jika diizinkan kebijakan) — kompensasi pokok dan item payroll sering <strong>hanya admin</strong>.</p>
<h6 class="fs-14 fw-semibold mb-2">Praktik data yang rapi</h6>
<ul class="knowledgebase ps-3 mb-3">
<li class="d-flex align-items-baseline mb-2"><i class="ti ti-point-filled me-1"></i> Isi <strong>departemen</strong> dan <strong>jabatan</strong> sebelum impor massal agar pilihan dropdown di form karyawan baru sudah tersedia.</li>
<li class="d-flex align-items-baseline mb-2"><i class="ti ti-point-filled me-1"></i> Samakan format NIK/employee code dengan kebijakan unik per tenant.</li>
<li class="d-flex align-items-baseline mb-2"><i class="ti ti-point-filled me-1"></i> Setelah perubahan besar, refresh halaman detail untuk memastikan cache sisi klien tidak menampilkan label lama.</li>
</ul>
<p class="fs-14 fw-normal mb-0">Pastikan seluruh data master (jabatan, departemen, status kepegawaian) sudah diisi sebelum mengisi detail karyawan untuk menghindari isian kosong atau error validasi.</p>
HTML,
                ],
                [
                    'slug' => 'master-departemen-dan-jabatan',
                    'title' => 'Master departemen dan jabatan',
                    'reading_minutes' => 4,
                    'excerpt' => 'Urutan pengisian master, dampak ke dropdown di seluruh modul, dan antisipasi duplikasi nama.',
                    'body_html' => <<<'HTML'
<p class="fs-14 fw-normal mb-3"><a href="/departments">/departments</a> dan <a href="/designations">/designations</a> menjadi sumber dropdown di form karyawan, filter laporan, dan beberapa alur persetujuan. Mengganti nama departemen setelah transaksi historis banyak dapat membingungkan audit — pertimbangkan penonaktifan + pembuatan baru jika kebijakan perusahaan menghendaki jejak jelas.</p>
<h6 class="fs-14 fw-semibold mb-2">Checklist admin</h6>
<ul class="knowledgebase ps-3 mb-0">
<li class="d-flex align-items-baseline mb-2"><i class="ti ti-point-filled me-1"></i> Cegah duplikasi case-insensitive jika bisnis melarang (contoh: “HR” vs “hr”).</li>
<li class="d-flex align-items-baseline mb-2"><i class="ti ti-point-filled me-1"></i> Setelah mengubah master, verifikasi satu karyawan sampel di form edit untuk memastikan opsi terbaru termuat.</li>
</ul>
HTML,
                ],
                [
                    'slug' => 'kebijakan-perusahaan-policies',
                    'title' => 'Kebijakan perusahaan (Policies)',
                    'reading_minutes' => 3,
                    'excerpt' => 'Master policy yang dipakai modul lain; hanya HCM admin yang mengubah isi master.',
                    'body_html' => <<<'HTML'
<p class="fs-14 fw-normal mb-3">Halaman <a href="/policy">/policy</a> mengelola kebijakan organisasi. Modul lain dapat mereferensikan policy lewat relasi API — jangan hapus policy yang masih terikat ke workflow aktif tanpa migrasi data.</p>
<p class="fs-14 fw-normal mb-0">Semua verb CRUD pada endpoint policy dilindungi admin HCM; karyawan umumnya hanya melihat efek kebijakan lewat form (mis. batas cuti) tanpa akses master.</p>
HTML,
                ],
                [
                    'slug' => 'employees-grid-vs-daftar',
                    'title' => 'Grid karyawan vs daftar (/employees-grid)',
                    'reading_minutes' => 3,
                    'excerpt' => 'Kapan memakai tampilan grid, filter massal, dan batasan yang sama dengan /employees.',
                    'body_html' => <<<'HTML'
<p class="fs-14 fw-normal mb-3"><a href="/employees-grid">/employees-grid</a> memakai sumber data yang sama dengan <a href="/employees">/employees</a> tetapi dengan tata letak grid untuk skenario review cepat atau presentasi. Semua aksi admin (tambah, bulk) tetap memerlukan akses admin HCM.</p>
<ol class="fs-14 mb-0"><li>Pilih periode filter yang sama dengan laporan yang akan Anda cocokkan.</li><li>Untuk edit detail kompensasi, buka lewat kartu karyawan ke <a href="/employee-details">/employee-details</a>.</li><li>Tampilan grid bersifat ringkas — untuk aksi lengkap gunakan halaman karyawan standar.</li></ol>
HTML,
                ],
            ],
        ],
        [
            'slug' => 'cuti-absensi',
            'title' => 'Cuti dan kalender',
            'icon' => 'ti ti-calendar-time',
            'visible_to' => ['authenticated'],
            'description' => 'Cuti karyawan vs persetujuan admin, pengaturan tipe cuti, dan hari libur nasional/lokal.',
            'articles' => [
                [
                    'slug' => 'cuti-karyawan-scope-me',
                    'visible_to' => ['employee'],
                    'title' => 'Pengajuan cuti sebagai karyawan',
                    'reading_minutes' => 5,
                    'excerpt' => 'Halaman /leaves-employee, parameter scope, status pengajuan, dan sinkronisasi dengan master tipe cuti.',
                    'body_html' => <<<'HTML'
<p class="fs-14 fw-normal mb-3"><a href="/leaves-employee">/leaves-employee</a> adalah jalur utama non-admin. Form meminta tanggal, tipe cuti, dan alasan sesuai validasi API. Status awal biasanya <em>pending</em> hingga atasan/HR menyetujui di jalur admin.</p>
<h6 class="fs-14 fw-semibold mb-2">Yang sering ditanyakan user</h6>
<ul class="knowledgebase ps-3 mb-3">
<li class="d-flex align-items-baseline mb-2"><i class="ti ti-point-filled me-1"></i> “Tipe cuti kosong” — pastikan master <code>/leave-type</code> dan pengaturan cuti perusahaan sudah mengaktifkan tipe yang dibutuhkan.</li>
<li class="d-flex align-items-baseline mb-2"><i class="ti ti-point-filled me-1"></i> “Tanggal tidak bisa dipilih” — cek overlap hari libur, kuota, atau minimum notice di pengaturan.</li>
</ul>
<p class="fs-14 fw-normal mb-0">Sistem secara otomatis menyaring data agar karyawan hanya melihat cuti milik sendiri — tidak perlu konfigurasi tambahan.</p>
HTML,
                ],
                [
                    'slug' => 'cuti-admin-persetujuan',
                    'visible_to' => ['admin'],
                    'title' => 'Cuti admin: persetujuan dan monitoring',
                    'reading_minutes' => 5,
                    'excerpt' => 'Halaman /leaves untuk HCM admin: filter, approve/decline, dan batas siapa yang boleh mengubah cuti orang lain.',
                    'body_html' => <<<'HTML'
<p class="fs-14 fw-normal mb-3"><a href="/leaves">/leaves</a> ditujukan untuk operator HR. Di sinilah persetujuan cuti atas nama karyawan lain dilakukan. Jika Anda bukan admin HCM dan membuka halaman ini, sistem akan mengalihkan ke tampilan cuti karyawan secara otomatis.</p>
<h6 class="fs-14 fw-semibold mb-2">Aturan bisnis penting</h6>
<p class="fs-14 fw-normal mb-3">Hanya admin HCM yang dapat mengubah cuti milik karyawan lain — karyawan biasa tidak bisa mengubah cuti rekan melalui halaman ini meski mengetahui URL-nya. Ini adalah perlindungan data kepegawaian.</p>
<ul class="knowledgebase ps-3 mb-0">
<li class="d-flex align-items-baseline mb-2"><i class="ti ti-point-filled me-1"></i> Gunakan filter periode saat menangani antrian besar.</li>
<li class="d-flex align-items-baseline mb-2"><i class="ti ti-point-filled me-1"></i> Setelah decline, pastikan karyawan mendapat notifikasi (jika saluran notifikasi tenant aktif).</li>
</ul>
HTML,
                ],
                [
                    'slug' => 'pengaturan-cuti-dan-tipe',
                    'visible_to' => ['admin'],
                    'title' => 'Pengaturan cuti dan master tipe cuti',
                    'reading_minutes' => 4,
                    'excerpt' => '/leave-settings dan /leave-type: kuota, aturan approval, dan katalog tipe untuk dropdown.',
                    'body_html' => <<<'HTML'
<p class="fs-14 fw-normal mb-3"><a href="/leave-settings">/leave-settings</a> mengatur parameter global cuti per tenant (kuota, alur, batasan). <a href="/leave-type">/leave-type</a> mengelola katalog tipe yang muncul di dropdown pengajuan.</p>
<p class="fs-14 fw-normal mb-3">Keduanya <strong>admin-only</strong> di API. Perubahan di sini berdampak langsung ke form karyawan — lakukan di luar jam puncak pengajuan bila memungkinkan.</p>
<p class="fs-14 fw-normal mb-0">Endpoint <code>GET /leave-type-options</code> menyediakan opsi ringkas untuk modal; jangan duplikasi logika validasi di frontend saja.</p>
HTML,
                ],
                [
                    'slug' => 'hari-libur-holidays',
                    'visible_to' => ['admin'],
                    'title' => 'Hari libur (Holidays)',
                    'reading_minutes' => 3,
                    'excerpt' => 'Master libur mempengaruhi perhitungan hari kerja; sinkron baseline Indonesia tersedia lewat API.',
                    'body_html' => <<<'HTML'
<p class="fs-14 fw-normal mb-3"><a href="/holidays">/holidays</a> untuk admin. Libur nasional dapat diisi manual atau disinkronkan (lihat dokumentasi API sync Indonesia di spesifikasi).</p>
<p class="fs-14 fw-normal mb-0">Pastikan zona waktu tenant konsisten dengan definisi “hari” pada cuti dan absensi.</p>
HTML,
                ],
            ],
        ],
        [
            'slug' => 'absensi',
            'title' => 'Absensi dan jadwal',
            'icon' => 'ti ti-clock',
            'visible_to' => ['authenticated'],
            'description' => 'Punch karyawan dengan GPS, selfie setelah punch, rekap admin, timesheet, dan master shift.',
            'articles' => [
                [
                    'slug' => 'absensi-dan-gps',
                    'visible_to' => ['employee'],
                    'title' => 'Absensi mandiri: punch, peta, dan GPS',
                    'reading_minutes' => 6,
                    'excerpt' => '/attendance-employee: koordinat wajib, fallback peta OSM, urutan punch in sebelum selfie, dan ringkasan produktivitas.',
                    'body_html' => <<<'HTML'
<p class="fs-14 fw-normal mb-3">Halaman <a href="/attendance-employee">/attendance-employee</a> memudahkan absensi berbasis lokasi langsung dari browser. Saat melakukan punch in atau punch out, browser akan meminta izin untuk mengakses lokasi Anda — izinkan agar sistem dapat mencatat koordinat kehadiran.</p>
<h6 class="fs-14 fw-semibold mb-2">Browser menolak GPS</h6>
<p class="fs-14 fw-normal mb-3">Gunakan titik di peta sebagai lokasi manual; koordinat yang dipilih tetap dikirim ke server. Edukasikan pengguna untuk memberi izin lokasi pada HTTPS.</p>
<h6 class="fs-14 fw-semibold mb-2">Selfie</h6>
<p class="fs-14 fw-normal mb-3">Foto selfie dapat dilampirkan saat absensi sesuai kebijakan perusahaan. Jika Anda menekan tombol selfie tanpa melakukan punch in lebih dulu, sistem akan menginformasikan bahwa Anda perlu melakukan absensi masuk terlebih dahulu.</p>
<h6 class="fs-14 fw-semibold mb-2">Admin</h6>
<p class="fs-14 fw-normal mb-0"><a href="/attendance-admin">/attendance-admin</a> untuk rekap seluruh karyawan; koordinasi dengan <a href="/timesheets">/timesheets</a> untuk analisis jam.</p>
HTML,
                ],
                [
                    'slug' => 'jadwal-shift-dan-schedule',
                    'visible_to' => ['admin'],
                    'title' => 'Jadwal per karyawan dan master shift',
                    'reading_minutes' => 5,
                    'excerpt' => '/schedule-timing, /shift-master, dan cara menghubungkan jadwal karyawan ke master shift.',
                    'body_html' => <<<'HTML'
<p class="fs-14 fw-normal mb-3"><a href="/shift-master">/shift-master</a> mendefinisikan jam kerja referensi. <a href="/schedule-timing">/schedule-timing</a> menetapkan shift per karyawan; jika shift tidak dipilih, sistem menggunakan jam kerja default yang telah dikonfigurasi.</p>
<p class="fs-14 fw-normal mb-0">Semua mutasi di area ini memerlukan HCM admin — kesalahan jadwal berdampak ke perhitungan lembur dan produktivitas.</p>
HTML,
                ],
                [
                    'slug' => 'timesheet-dan-rekap',
                    'visible_to' => ['admin'],
                    'title' => 'Timesheet dan rekapitulasi',
                    'reading_minutes' => 3,
                    'excerpt' => 'Halaman /timesheets untuk pandangan agregat; cocokkan rentang tanggal dengan periode payroll.',
                    'body_html' => <<<'HTML'
<p class="fs-14 fw-normal mb-3"><a href="/timesheets">/timesheets</a> membantu admin memverifikasi jam sebelum menutup periode payroll. Gunakan filter yang sama dengan tanggal yang Anda pakai di payroll run untuk mengurangi selisih interpretasi.</p>
<p class="fs-14 fw-normal mb-0">Jika angka tidak cocok dengan slip, telusuri dulu koreksi absensi dan status <em>needs review</em>.</p>
HTML,
                ],
            ],
        ],
        [
            'slug' => 'payroll',
            'title' => 'Payroll dan kompensasi',
            'icon' => 'ti ti-currency-dollar',
            'visible_to' => ['admin'],
            'description' => 'Run bulanan, komponen gaji, kompensasi per karyawan, THR, slip mandiri, dan gate export rekonsiliasi.',
            'articles' => [
                [
                    'slug' => 'payroll-run-bulanan',
                    'title' => 'Payroll run bulanan (admin)',
                    'reading_minutes' => 7,
                    'excerpt' => 'Alur lengkap /payroll-run: periode aktif, calculate draft, finalisasi, export bukti bila gate aktif, disburse, dan riwayat.',
                    'body_html' => <<<'HTML'
<h6 class="fs-14 fw-semibold mb-2">Lanskap halaman</h6>
<p class="fs-14 fw-normal mb-3"><a href="/payroll-run">/payroll-run</a> mengunci tampilan ke <strong>periode aktif</strong>. <a href="/payroll-run-history">/payroll-run-history</a> menyimpan audit run lampau untuk forensik gaji.</p>
<h6 class="fs-14 fw-semibold mb-2">Urutan operasi yang disarankan</h6>
<ol class="fs-14 mb-3"><li>Pastikan data absensi &amp; lembur periode sudah stabil.</li><li>Jalankan <em>calculate draft</em>; tinjau baris per karyawan.</li><li>Finalisasi bila sudah sesuai kebijakan.</li><li>Jika <strong>gate rekonsiliasi</strong> aktif untuk tenant, lakukan export evidence sebelum aksi finansial (finalize/disburse) sesuai dokumentasi fitur.</li><li>Lakukan disburse melalui gateway yang terkonfigurasi; pantau status baris.</li></ol>
<h6 class="fs-14 fw-semibold mb-2">Komponen yang mempengaruhi draft</h6>
<p class="fs-14 fw-normal mb-0">Gaji pokok + tunjangan tetap dari profil, payroll items (tambah/kurang), assignment per karyawan, dan aturan lembur yang sudah disetujui masuk ke mesin perhitungan — pastikan master tidak berubah di tengah audit tanpa komunikasi.</p>
HTML,
                ],
                [
                    'slug' => 'komponen-payroll-items',
                    'title' => 'Komponen gaji: payroll items dan master',
                    'reading_minutes' => 5,
                    'excerpt' => '/payroll, /payroll-deduction, dan tautan ke salary components; bedakan katalog vs assignment.',
                    'body_html' => <<<'HTML'
<p class="fs-14 fw-normal mb-3"><a href="/payroll">/payroll</a> (penambah) dan <a href="/payroll-deduction">/payroll-deduction</a> (potongan) mengelola item kustom. Item dapat ditaut ke master komponen gaji bila perusahaan menyelaraskan dengan katalog pusat.</p>
<ul class="knowledgebase ps-3 mb-0">
<li class="d-flex align-items-baseline mb-2"><i class="ti ti-point-filled me-1"></i> Perubahan nama item tidak mengubah histori slip yang sudah final — hanya mempengaruhi run berikutnya.</li>
<li class="d-flex align-items-baseline mb-2"><i class="ti ti-point-filled me-1"></i> Gunakan export CSV/XLSX untuk rekonsiliasi dengan finance eksternal bila diperlukan.</li>
</ul>
HTML,
                ],
                [
                    'slug' => 'gaji-karyawan-compensation',
                    'title' => 'Kompensasi per karyawan (/employee-salary)',
                    'reading_minutes' => 5,
                    'excerpt' => 'Base salary, tunjangan tetap, dan penugasan komponen gaji per karyawan; halaman ini hanya untuk admin.',
                    'body_html' => <<<'HTML'
<p class="fs-14 fw-normal mb-3"><a href="/employee-salary">/employee-salary</a> memusatkan pengelolaan kompensasi dan penugasan komponen gaji per karyawan. Halaman ini hanya bisa diakses oleh admin HCM — karyawan biasa tidak dapat melihat atau mengubah gaji rekan meski mengetahui URL-nya.</p>
<p class="fs-14 fw-normal mb-0">Setelah mengubah kompensasi, jalankan ulang draft payroll periode berjalan agar angka slip mencerminkan perubahan.</p>
HTML,
                ],
                [
                    'slug' => 'slip-gaji-mandiri',
                    'visible_to' => ['employee'],
                    'title' => 'Slip gaji mandiri (/payslip)',
                    'reading_minutes' => 4,
                    'excerpt' => 'Self-service slip: hanya periode dengan run finalized; PDF bila endpoint tersedia; admin dialihkan ke konteks laporan.',
                    'body_html' => <<<'HTML'
<p class="fs-14 fw-normal mb-3"><a href="/payslip">/payslip</a> hanya menampilkan data untuk pemanggil. Jika periode dipilih belum punya run <strong>finalized</strong>, ringkasan kosong — itu perilaku normal, bukan error.</p>
<p class="fs-14 fw-normal mb-3">Akun HCM admin sering dialihkan ke laporan agar tidak salah mengira halaman ini adalah agregat seluruh karyawan.</p>
<h6 class="fs-14 fw-semibold mb-2">Privasi</h6>
<p class="fs-14 fw-normal mb-0">Jangan membagikan tautan unduh slip ke channel publik; file mengandung data penghasilan.</p>
HTML,
                ],
                [
                    'slug' => 'thr-payroll-batch',
                    'title' => 'THR dan batch (/payroll-thr)',
                    'reading_minutes' => 5,
                    'excerpt' => 'Pengaturan tahunan, kalkulator, batch massal, disburse, posting, dan slip per baris.',
                    'body_html' => <<<'HTML'
<p class="fs-14 fw-normal mb-3"><a href="/payroll-thr">/payroll-thr</a> mencakup pengaturan tahunan, estimasi, serta alur batch: generate → disburse → post-payroll → kirim slip. Setiap langkah punya guard status agar tidak dobel bayar.</p>
<p class="fs-14 fw-normal mb-0">Karyawan melihat ringkasan THR self lewat endpoint slip khusus (lihat spesifikasi API payroll/THR); tidak ada duplikasi halaman web slip THR terpisah dari kontrak terkini.</p>
HTML,
                ],
                [
                    'slug' => 'export-rekonsiliasi-payroll',
                    'title' => 'Export rekonsiliasi sebelum aksi finansial',
                    'reading_minutes' => 4,
                    'excerpt' => 'Gate wajib export untuk operator bila fitur diaktifkan; mencegah finalize/disburse tanpa bukti.',
                    'body_html' => <<<'HTML'
<p class="fs-14 fw-normal mb-3">Pada perusahaan yang mengaktifkan fitur rekonsiliasi, admin wajib menghasilkan bukti export (CSV/ZIP sesuai konfigurasi) sebelum menekan aksi kritis pada payroll. Ini memastikan setiap run gaji punya jejak audit yang lengkap.</p>
<p class="fs-14 fw-normal mb-0">Jika tombol aksi tetap nonaktif, periksa status evidence di API dan ulangi export dengan parameter periode yang sama dengan run.</p>
HTML,
                ],
                [
                    'slug' => 'payroll-overtime-dan-pkwt',
                    'title' => 'Payroll lembur & kompensasi PKWT',
                    'reading_minutes' => 5,
                    'excerpt' => 'Alur /payroll-overtime dan /payroll-pkwt-compensation setelah master lembur dan kontrak siap.',
                    'body_html' => <<<'HTML'
<h6 class="fs-14 fw-semibold mb-2">Payroll lembur</h6>
<ol class="fs-14 mb-3">
<li>Pastikan permintaan lembur sudah disetujui di <a href="/overtime">/overtime</a> untuk periode yang sama dengan tanggal kerja.</li>
<li>Buka <a href="/payroll-overtime">/payroll-overtime</a>; samakan filter tanggal dengan <a href="/attendance-admin">/attendance-admin</a> bila Anda memverifikasi jam nyata.</li>
<li>Review baris per karyawan sebelum run gaji utama di <a href="/payroll-run">/payroll-run</a>.</li>
</ol>
<h6 class="fs-14 fw-semibold mb-2">PKWT / kompensasi kontrak</h6>
<p class="fs-14 fw-normal mb-0">Gunakan <a href="/payroll-pkwt-compensation">/payroll-pkwt-compensation</a> untuk skenario kompensasi khusus kontrak waktu tertentu sesuai kebijakan perusahaan dan dokumentasi API terkait. Input numerik harus konsisten dengan pembulatan yang dipakai modul payroll utama.</p>
HTML,
                ],
            ],
        ],
        [
            'slug' => 'pengaturan-sistem',
            'title' => 'Pengaturan sistem & master pendukung',
            'icon' => 'ti ti-settings',
            'visible_to' => ['admin'],
            'description' => 'Cronjob, pengguna/role, lokalisasi, prefix nomor — biasanya sekali setup lalu jarang diubah.',
            'articles' => [
                [
                    'slug' => 'tutorial-cronjob-dan-jadwal',
                    'title' => 'Cronjob & jadwal scheduler (/cronjob)',
                    'reading_minutes' => 5,
                    'excerpt' => 'Form web menyimpan konfigurasi ke grup settings; Kernel + console Laravel mengeksekusi.',
                    'body_html' => <<<'HTML'
<p class="fs-14 fw-normal mb-3">Halaman <a href="/cronjob">/cronjob</a> hanya untuk <strong>HCM admin</strong>. Perubahan mempengaruhi job berjadwal (reminder, sinkronisasi, dll.) — dokumentasikan perubahan di runbook internal.</p>
<ol class="fs-14 mb-3"><li>Baca nilai saat ini sebelum mengubah ekspresi jadwal.</li><li>Simpan perubahan; pantau log aplikasi setelah window eksekusi berikutnya.</li><li>Jika ada halaman terkait <a href="/cronjob-schedule">/cronjob-schedule</a>, gunakan sebagai referensi visual tanpa menggandakan sumber kebenaran.</li></ol>
<p class="fs-14 fw-normal mb-0">Jangan menonaktifkan proses terjadwal yang kritis (seperti backup token atau pemantauan batas karyawan) tanpa mitigasi yang memadai — konsultasikan dengan tim teknis sebelum mengubah konfigurasi penjadwalan.</p>
HTML,
                ],
                [
                    'slug' => 'tutorial-users-dan-roles-permissions',
                    'title' => 'Pengguna web & mapping role (/users, /roles-permissions)',
                    'reading_minutes' => 6,
                    'excerpt' => 'Membedakan user web template vs API user-management HCM; kapan perlu keduanya.',
                    'body_html' => <<<'HTML'
<p class="fs-14 fw-normal mb-3"><a href="/users">/users</a> dan <a href="/roles-permissions">/roles-permissions</a> mengatur akun yang bisa login ke Arcav HCM dan izin menu tingkat tinggi. Perubahan peran pengguna berlaku segera setelah disimpan — karyawan yang baru diberi akses admin tidak perlu logout dan login ulang.</p>
<ol class="fs-14 mb-3"><li>Tambah user → assign role minimal.</li><li>Uji login sekunder di browser privat untuk memastikan tidak ada cache token lama.</li><li>Catat perubahan role di tiket internal bila organisasi Anda mewajibkan jejak audit.</li></ol>
<p class="fs-14 fw-normal mb-0">Jangan memberi role luas hanya untuk “mempermudah demo” — gunakan akun QA terpisah.</p>
HTML,
                ],
                [
                    'slug' => 'tutorial-lokalisasi-dan-prefix',
                    'title' => 'Lokalisasi & prefix dokumen (/localization-settings, /prefixes)',
                    'reading_minutes' => 4,
                    'excerpt' => 'Zona waktu, bahasa tampilan, format nomor surat — dampaknya ke slip dan PDF.',
                    'body_html' => <<<'HTML'
<p class="fs-14 fw-normal mb-3"><a href="/localization-settings">/localization-settings</a> mempengaruhi format tanggal/angka yang diterjemahkan UI. <a href="/prefixes">/prefixes</a> mengatur awalan nomor referensi (cuti, tiket, dll.) sesuai konvensi perusahaan.</p>
<ol class="fs-14 mb-0"><li>Set locale/timezone sebelum go-live payroll agar periode bulanan konsisten.</li><li>Ubah prefix di luar jam cetak slip massal bila memungkinkan.</li><li>Setelah ubah prefix, uji satu dokumen contoh (unduh PDF) sebelum komunikasi ke seluruh staf.</li></ol>
HTML,
                ],
            ],
        ],
        [
            'slug' => 'pelaporan-detail',
            'title' => 'Pelaporan HCM (langkah demi langkah)',
            'icon' => 'ti ti-report-analytics',
            'visible_to' => ['admin'],
            'description' => 'Cara memakai setiap layar laporan admin: filter, export, dan interpretasi data.',
            'articles' => [
                [
                    'slug' => 'tutorial-laporan-karyawan',
                    'title' => 'Laporan karyawan (/employee-report)',
                    'reading_minutes' => 4,
                    'excerpt' => 'Filter departemen/jabatan, export data, dan memastikan jumlah baris sesuai direktori karyawan.',
                    'body_html' => <<<'HTML'
<ol class="fs-14 mb-3"><li>Buka <a href="/employee-report">/employee-report</a> sebagai HCM admin.</li><li>Terapkan filter departemen/jabatan jika dataset besar untuk mempercepat hasil dan memudahkan export.</li><li>Export bila tersedia; bandingkan jumlah baris dengan <a href="/employees">/employees</a> untuk memastikan tidak ada data yang terlewat.</li></ol>
<p class="fs-14 fw-normal mb-0">Halaman ini hanya dapat diakses oleh admin HCM — akun karyawan tidak dapat melihat laporan seluruh karyawan dari halaman ini.</p>
HTML,
                ],
                [
                    'slug' => 'tutorial-laporan-absensi',
                    'title' => 'Laporan absensi (/attendance-report)',
                    'reading_minutes' => 4,
                    'excerpt' => 'Menjembatani timesheet harian dengan rekap periode.',
                    'body_html' => <<<'HTML'
<ol class="fs-14 mb-3"><li>Buka <a href="/attendance-report">/attendance-report</a>; set rentang tanggal sama dengan periode payroll atau audit HR.</li><li>Jika angka janggal, telusuri dulu koreksi manual di <a href="/attendance-admin">/attendance-admin</a>.</li><li>Gunakan bersama <a href="/timesheets">/timesheets</a> untuk drill-down per hari.</li></ol>
<p class="fs-14 fw-normal mb-0">Pastikan timezone tenant sama dengan yang dipakai karyawan saat punch.</p>
HTML,
                ],
                [
                    'slug' => 'tutorial-laporan-slip-gaji',
                    'title' => 'Laporan slip gaji (/payslip-report)',
                    'reading_minutes' => 4,
                    'excerpt' => 'Agregat slip untuk finance; bedakan dengan /payslip self karyawan.',
                    'body_html' => <<<'HTML'
<p class="fs-14 fw-normal mb-3"><a href="/payslip-report">/payslip-report</a> ditujukan admin/Finance untuk melintasi banyak karyawan. <a href="/payslip">/payslip</a> hanya data diri pemanggil setelah run <strong>finalized</strong>.</p>
<ol class="fs-14 mb-0"><li>Pilih periode tahun-bulan yang sama dengan run di <a href="/payroll-run-history">/payroll-run-history</a>.</li><li>Ekspor bila ada; simpan file sesuai kebijakan retensi data.</li><li>Untuk koreksi gaji, kembali ke <a href="/employee-salary">/employee-salary</a> lalu ulang draft payroll — jangan mengedit PDF arsip manual sebagai sumber kebenaran.</li></ol>
HTML,
                ],
                [
                    'slug' => 'tutorial-laporan-cuti',
                    'title' => 'Laporan cuti (/leave-report)',
                    'reading_minutes' => 3,
                    'excerpt' => 'Placeholder laporan; persiapkan data dari /leaves bila agregat API belum tersedia.',
                    'body_html' => <<<'HTML'
<p class="fs-14 fw-normal mb-3">Halaman <a href="/leave-report">/leave-report</a> ada di menu untuk rencana agregat cuti. Jika UI masih placeholder, gunakan sementara filter di <a href="/leaves">/leaves</a> (admin) dan ekspor manual sesuai kebutuhan audit.</p>
<p class="fs-14 fw-normal mb-0">Pantau dokumentasi <code>docs/planning/</code> untuk status API agregat cuti — saat live, sesuaikan langkah di artikel ini.</p>
HTML,
                ],
            ],
        ],
        [
            'slug' => 'kinerja-goals-training',
            'title' => 'Kinerja, goals, dan pelatihan',
            'icon' => 'ti ti-chart-line',
            'visible_to' => ['admin'],
            'description' => 'Workflow appraisal, tracking sasaran, dan administrasi pelatihan untuk admin dan manager.',
            'articles' => [
                [
                    'slug' => 'tutorial-performance-indicator-sampai-review',
                    'title' => 'Performance: indikator → siklus → review',
                    'reading_minutes' => 8,
                    'excerpt' => 'Urutan master template, appraisal admin, lalu pengisian review oleh karyawan/manager.',
                    'body_html' => <<<'HTML'
<h6 class="fs-14 fw-semibold mb-2">1. Master indikator</h6>
<p class="fs-14 fw-normal mb-3">Di <a href="/performance-indicator">/performance-indicator</a>, buat atau salin template beserta item penilaian. Ini hanya bisa diubah admin HCM.</p>
<h6 class="fs-14 fw-semibold mb-2">2. Siklus appraisal</h6>
<p class="fs-14 fw-normal mb-3">Di <a href="/performance-appraisal">/performance-appraisal</a>, buat siklus per periode bisnis; pastikan rentang tanggal tidak overlap siklus lama yang masih “open”.</p>
<h6 class="fs-14 fw-semibold mb-2">3. Review</h6>
<ol class="fs-14 mb-0">
<li>Karyawan/manager mengisi lewat <a href="/performance-review">/performance-review</a> sesuai RBAC (self vs team vs finalize admin).</li>
<li>Setelah skor final, jangan ubah template indikator secara drastis — histori review tetap merujuk definisi saat submit.</li>
</ol>
HTML,
                ],
                [
                    'slug' => 'tutorial-goal-type-dan-goal-tracking',
                    'title' => 'Goals: tipe sasaran & tracking (/goal-type, /goal-tracking)',
                    'reading_minutes' => 6,
                    'excerpt' => 'Sasaran pribadi, tim, dan seluruh organisasi — tampilan disesuaikan otomatis berdasarkan peran Anda.',
                    'body_html' => <<<'HTML'
<ol class="fs-14 mb-3"><li>Admin siapkan katalog tipe sasaran di <a href="/goal-type">/goal-type</a>.</li><li>Penetapan sasaran dan pencatatan progres di <a href="/goal-tracking">/goal-tracking</a>: karyawan melihat sasaran milik sendiri, manajer melihat sasaran tim, admin dapat melihat seluruh organisasi.</li><li>Export CSV dari UI bila tersedia untuk review kuartalan.</li></ol>
<p class="fs-14 fw-normal mb-0">Jika pengguna mengeluh "tidak ada data tim", verifikasi relasi atasan di data karyawan — pastikan atasan sudah ditentukan, bukan hanya label jabatan.</p>
HTML,
                ],
                [
                    'slug' => 'tutorial-training-type-trainers-training',
                    'title' => 'Pelatihan: tipe, trainer, jadwal (/training-type, /trainers, /training)',
                    'reading_minutes' => 7,
                    'excerpt' => 'Fase 1: peserta manual user ID; master harus lengkap sebelum komunikasi ke karyawan.',
                    'body_html' => <<<'HTML'
<ol class="fs-14 mb-3"><li>Isi <a href="/training-type">/training-type</a> dan <a href="/trainers">/trainers</a> agar form utama tidak punya opsi kosong.</li><li>Buat pelatihan di <a href="/training">/training</a>; pilih peserta dari daftar karyawan yang tersedia.</li><li>Umumkan ke peserta lewat channel internal; notifikasi otomatis mengikuti pengaturan yang ada di perusahaan Anda.</li></ol>
<p class="fs-14 fw-normal mb-0">Pastikan data peserta sudah lengkap sebagai karyawan aktif sebelum didaftarkan ke program pelatihan.</p>
HTML,
                ],
            ],
        ],
        [
            'slug' => 'wilayah-indonesia',
            'title' => 'Master wilayah (negara, provinsi, kota)',
            'icon' => 'ti ti-map-pin',
            'visible_to' => ['admin'],
            'description' => 'Referensi geografis untuk alamat karyawan dan filter; jarang diubah setelah stabil.',
            'articles' => [
                [
                    'slug' => 'tutorial-countries-states-cities',
                    'title' => 'Mengisi & memelihara /countries, /states, /cities',
                    'reading_minutes' => 4,
                    'excerpt' => 'Urutan hierarki dan konsistensi kode wilayah untuk form alamat.',
                    'body_html' => <<<'HTML'
<ol class="fs-14 mb-3"><li>Buka <a href="/countries">/countries</a> → pastikan negara operasi utama aktif.</li><li>Lanjut <a href="/states">/states</a> → provinsi sesuai standar yang dipakai HRD Anda.</li><li><a href="/cities">/cities</a> → kota/kabupaten; hindari duplikasi nama tanpa kode berbeda.</li></ol>
<p class="fs-14 fw-normal mb-0">Setelah master stabil, uji satu form karyawan baru untuk memastikan dropdown berantai memuat opsi lengkap.</p>
HTML,
                ],
            ],
        ],
        [
            'slug' => 'lembur-tiket',
            'title' => 'Lembur dan tiket',
            'icon' => 'ti ti-flame',
            'visible_to' => ['authenticated'],
            'description' => 'Permintaan lembur, master tipe lembur, dan modul tiket untuk eskalasi operasional.',
            'articles' => [
                [
                    'slug' => 'lembur-alur-admin-karyawan',
                    'visible_to' => ['authenticated'],
                    'title' => 'Lembur: jalur admin vs karyawan',
                    'reading_minutes' => 5,
                    'excerpt' => '/overtime vs /overtime-employee, kalkulator, dan tautan komponen slip.',
                    'body_html' => <<<'HTML'
<p class="fs-14 fw-normal mb-3">Admin menggunakan <a href="/overtime">/overtime</a> dengan filter karyawan penuh; karyawan memakai <a href="/overtime-employee">/overtime-employee</a> dengan scope diri. Kalkulator memakai aturan kepegawaian Indonesia (acuan PP 35/2021) sesuai dokumentasi API.</p>
<p class="fs-14 fw-normal mb-3">Master tipe lembur di <a href="/overtime-master">/overtime-master</a> mempengaruhi pilihan pada form.</p>
<p class="fs-14 fw-normal mb-0">Payroll lembur terintegrasi lewat menu <a href="/payroll-overtime">/payroll-overtime</a> untuk admin review sebelum run gaji.</p>
HTML,
                ],
                [
                    'slug' => 'tiket-internal',
                    'visible_to' => ['authenticated'],
                    'title' => 'Tiket dukungan internal',
                    'reading_minutes' => 4,
                    'excerpt' => 'Jalur /tickets-admin vs /tickets-employee, SLA, lampiran, dan kapan menggunakan tiket vs email bebas.',
                    'body_html' => <<<'HTML'
<p class="fs-14 fw-normal mb-3"><a href="/tickets-admin">/tickets-admin</a> untuk operator; <a href="/tickets-employee">/tickets-employee</a> untuk pengajuan self. Detail tiket menyimpan komentar dan lampiran untuk audit.</p>
<h6 class="fs-14 fw-semibold mb-2">Kapan memakai tiket</h6>
<ul class="knowledgebase ps-3 mb-0">
<li class="d-flex align-items-baseline mb-2"><i class="ti ti-point-filled me-1"></i> Permintaan perubahan data sensitif (role, gaji) dengan jejak persetujuan.</li>
<li class="d-flex align-items-baseline mb-2"><i class="ti ti-point-filled me-1"></i> Masalah teknis di level platform (gangguan sistem, akses yang tidak bisa diperbaiki sendiri) yang perlu ditangani tim teknis.</li>
</ul>
HTML,
                ],
                [
                    'slug' => 'tutorial-ticket-master-kategori',
                    'visible_to' => ['admin'],
                    'title' => 'Master kategori tiket (/ticket-master)',
                    'reading_minutes' => 4,
                    'excerpt' => 'Mendefinisikan kategori & opsi form sebelum karyawan mengajukan tiket.',
                    'body_html' => <<<'HTML'
<ol class="fs-14 mb-3"><li>Buka <a href="/ticket-master">/ticket-master</a> sebagai HCM admin.</li><li>Buat kategori yang mencerminkan SLA nyata (IT, HR, Fasilitas) — hindari kategori “Lainnya” terlalu luas.</li><li>Uji satu tiket dari <a href="/tickets-employee">/tickets-employee</a> untuk memastikan dropdown terisi.</li></ol>
<p class="fs-14 fw-normal mb-0">Perubahan kategori tidak mengubah tiket lama yang sudah <em>closed</em> — itu perilaku audit yang diinginkan.</p>
HTML,
                ],
                [
                    'slug' => 'tutorial-alur-tiket-admin-harian',
                    'visible_to' => ['admin'],
                    'title' => 'SOP harian tiket admin (/tickets-admin, /tickets-grid, /ticket-details)',
                    'reading_minutes' => 6,
                    'excerpt' => 'Dari antrian → assign → komentar → tutup; konsistensi dengan URL berbasis ID.',
                    'body_html' => <<<'HTML'
<ol class="fs-14 mb-3">
<li>Pantau antrian di <a href="/tickets-admin">/tickets-admin</a> atau tampilan grid <a href="/tickets-grid">/tickets-grid</a> bila organisasi memakainya untuk shift operator.</li>
<li>Buka detail lewat URL <code>/ticket-details/{id}</code>; tambahkan komentar internal vs publik sesuai kebijakan Anda.</li>
<li>Ubah status/assignee; lampirkan file bukti bila diperlukan kebijakan SDM.</li>
<li>Tutup tiket hanya setelah pemohon mengonfirmasi atau SLA internal terpenuhi — tiket <em>closed</em> biasanya terkunci dari edit karyawan.</li>
</ol>
<p class="fs-14 fw-normal mb-0">Eskalasi ke platform SaaS (bukan isi HR) lewat jalur Super Admin / tiket internal terpisah agar tidak bercampur dengan keluhan karyawan biasa.</p>
HTML,
                ],
            ],
        ],
        [
            'slug' => 'admin-saas',
            'title' => 'Super Admin (tenant SaaS)',
            'icon' => 'ti ti-building-bank',
            'visible_to' => ['global_admin'],
            'description' => 'Operator internal: perusahaan, paket, langganan, domain, dan transaksi — bukan rutinitas HR harian.',
            'articles' => [
                [
                    'slug' => 'hub-super-admin-menu',
                    'title' => 'Menu Super Admin: paket, langganan, perusahaan',
                    'reading_minutes' => 5,
                    'excerpt' => 'Akses terbatas HCM admin; membedakan operator platform vs admin perusahaan pelanggan.',
                    'body_html' => <<<'HTML'
<p class="fs-14 fw-normal mb-3">Submenu <strong>Super Admin</strong> di sidebar (Companies, Subscriptions, Packages, Domain, Purchase Transaction) ditujukan untuk <strong>operator platform</strong> yang mengelola tenant SaaS. Ini berbeda dari admin HR perusahaan yang mengurus karyawan di dalam satu tenant.</p>
<ul class="knowledgebase ps-3 mb-3">
<li class="d-flex align-items-baseline mb-2"><i class="ti ti-point-filled me-1"></i> <a href="/packages">/packages</a> (alias <code>/saas/packages</code>) — definisi fitur per paket.</li>
<li class="d-flex align-items-baseline mb-2"><i class="ti ti-point-filled me-1"></i> <a href="/subscription">/subscription</a> — lifecycle langganan perusahaan ke paket.</li>
<li class="d-flex align-items-baseline mb-2"><i class="ti ti-point-filled me-1"></i> <a href="/companies">/companies</a> — data perusahaan pelanggan dan kode tenant.</li>
</ul>
<p class="fs-14 fw-normal mb-0">Jika menu tidak tampil, periksa apakah akun Anda termasuk HCM admin global (bukan hanya admin perusahaan di satu tenant).</p>
HTML,
                ],
                [
                    'slug' => 'domain-dan-pembelian',
                    'title' => 'Domain custom dan pembelian',
                    'reading_minutes' => 3,
                    'excerpt' => 'Verifikasi domain dan jejak transaksi pembelian untuk audit billing.',
                    'body_html' => <<<'HTML'
<p class="fs-14 fw-normal mb-3"><a href="/domain">/domain</a> mengelola hostname tenant dan status verifikasi. <a href="/purchase-transaction">/purchase-transaction</a> merangkum transaksi terkait paket/add-on.</p>
<p class="fs-14 fw-normal mb-0">Untuk pertanyaan tentang domain custom atau status verifikasi, hubungi tim operator platform melalui tiket dukungan.</p>
HTML,
                ],
                [
                    'slug' => 'tutorial-invoice-pembayaran-dan-saas-views',
                    'title' => 'Invoice, pembayaran, dan tampilan SaaS/Company',
                    'reading_minutes' => 6,
                    'excerpt' => 'Membedakan /invoices CRM umum vs jalur /saas/* dan /company/* untuk operator tenant.',
                    'body_html' => <<<'HTML'
<p class="fs-14 fw-normal mb-3">Billing memiliki beberapa permukaan web: <a href="/invoices">/invoices</a> (aliran umum), <a href="/saas/invoices">/saas/invoices</a>, <a href="/company/invoices">/company/invoices</a>, serta halaman tambah/edit terkait. Gunakan yang sesuai peran operasional Anda — jangan membagikan URL admin ke pemilik tenant jika mereka hanya perlu portal terbatas.</p>
<ol class="fs-14 mb-3"><li>Cocokkan invoice dengan entri di <a href="/subscription">/subscription</a> (status langganan, paket).</li><li>Rekam pembayaran lewat <a href="/payments">/payments</a> atau <a href="/saas/payments">/saas/payments</a> sesuai konteks deployment.</li><li>Untuk memahami skenario jatuh tempo dan penghentian layanan otomatis, hubungi operator platform Anda.</li></ol>
<p class="fs-14 fw-normal mb-0">Semua aksi finansial sensitif harus punya jejak tiket atau runbook internal.</p>
HTML,
                ],
            ],
        ],
        [
            'slug' => 'dukungan',
            'title' => 'Referensi dan pelaporan',
            'icon' => 'ti ti-lifebuoy',
            'visible_to' => ['admin'],
            'description' => 'Pointer ke laporan terperinci, promosi/mutasi, rekonsiliasi API, dan dokumentasi teknis.',
            'articles' => [
                [
                    'slug' => 'laporan-hcm-ringkas',
                    'title' => 'Laporan HR (ringkas)',
                    'reading_minutes' => 4,
                    'excerpt' => 'Employee report, attendance report, payslip report — mode live vs arsip bila snapshot aktif.',
                    'body_html' => <<<'HTML'
<p class="fs-14 fw-normal mb-3">Halaman seperti <a href="/employee-report">/employee-report</a>, <a href="/attendance-report">/attendance-report</a>, dan <a href="/payslip-report">/payslip-report</a> memakai pola admin: filter, export, dan batas per halaman sesuai API.</p>
<p class="fs-14 fw-normal mb-0">Jika organisasi Anda memakai fitur snapshot reporting, tanyakan kepada admin utama perbedaan antara mode laporan langsung (live) dan arsip untuk memastikan laporan yang Anda lihat sesuai kebutuhan.</p>
HTML,
                ],
                [
                    'slug' => 'kinerja-dan-pelatihan-ringkas',
                    'visible_to' => ['admin'],
                    'title' => 'Kinerja dan pelatihan: mulai dari sini',
                    'reading_minutes' => 2,
                    'excerpt' => 'Tutorial langkah demi langkah dipindah ke kategori khusus agar mudah dijelaskan ke admin baru.',
                    'body_html' => <<<'HTML'
<p class="fs-14 fw-normal mb-3">Ringkasan modul: Performance, Goals, Training. Untuk <strong>SOP lengkap bernomor</strong> (template → siklus → review, goal tracking, administrasi training), buka kategori <a href="/knowledgebase/category/kinerja-goals-training">Kinerja, goals, dan pelatihan</a> di Knowledge Base ini.</p>
<p class="fs-14 fw-normal mb-0">Untuk panduan lengkap masing-masing modul kinerja, sasaran, dan pelatihan, buka kategori <a href="/knowledgebase/category/kinerja-goals-training">Kinerja, goals, dan pelatihan</a> di Knowledge Base ini.</p>
HTML,
                ],
                [
                    'slug' => 'export-rekonsiliasi-api-operator',
                    'visible_to' => ['admin'],
                    'title' => 'Export rekonsiliasi dan audit payroll',
                    'reading_minutes' => 3,
                    'excerpt' => 'Cara menghasilkan bukti export sebelum finalisasi payroll, dan mengapa langkah ini penting untuk audit.',
                    'body_html' => <<<'HTML'
<p class="fs-14 fw-normal mb-3">Sebelum menjalankan finalisasi payroll, sistem mungkin mensyaratkan export data rekonsiliasi terlebih dahulu. Langkah ini memastikan ada jejak audit yang lengkap setiap kali gaji diproses.</p>
<p class="fs-14 fw-normal mb-0">Jika tombol finalisasi payroll terkunci, periksa apakah langkah export rekonsiliasi sudah diselesaikan. Jika belum ada halaman web khusus untuk ini di perusahaan Anda, hubungi operator platform untuk panduan lebih lanjut.</p>
HTML,
                ],
                [
                    'slug' => 'promosi-mutasi-keluar',
                    'visible_to' => ['admin'],
                    'title' => 'Promosi, pengunduran diri, dan pemutusan',
                    'reading_minutes' => 3,
                    'excerpt' => 'Tiga modul administrasi mutasi staf; semua mutasi sensitif admin-only di web.',
                    'body_html' => <<<'HTML'
<p class="fs-14 fw-normal mb-3">Halaman <a href="/promotion">/promotion</a>, <a href="/resignation">/resignation</a>, dan <a href="/termination">/termination</a> memuat catatan HR yang mempengaruhi status karyawan. Non-admin hanya melihat entri terkait diri sendiri pada bagian tertentu di detail karyawan — daftar global tetap admin.</p>
<p class="fs-14 fw-normal mb-0">Sebelum mengunci data, pastikan cuti dan payroll periode berjalan sudah tidak konflik dengan tanggal efektif mutasi.</p>
HTML,
                ],
            ],
        ],
    ],
];
