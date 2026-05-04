<div class="modal fade" id="arcav_salary_component_guide" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Panduan Salary Component</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-light border mb-3">
                    <p class="mb-1 fw-medium">Tujuan halaman</p>
                    <p class="mb-0 small text-muted">Halaman ini dipakai HR/Payroll Admin untuk memastikan struktur Salary Component tetap konsisten sebelum proses payroll dijalankan.</p>
                </div>

                <h6 class="fw-semibold mb-2">Cara menggunakan halaman</h6>
                <ul class="mb-3 ps-3">
                    <li class="mb-1">Gunakan tab filter untuk fokus ke kelompok komponen yang ingin direview (pendapatan/potongan).</li>
                    <li class="mb-1">Cek kolom Integrasi untuk melihat komponen dipakai atau dikelola modul mana.</li>
                    <li class="mb-1">Periksa kolom Default %, Dasar hukum, dan Status sebelum payroll draft dihitung.</li>
                    <li>Gunakan icon edit/hapus hanya pada komponen yang tidak terkunci governance.</li>
                </ul>

                <h6 class="fw-semibold mb-2">Cara membaca Compliance Monitor</h6>
                <ul class="mb-0 ps-3">
                    <li class="mb-1"><strong>Compliance Score</strong> menunjukkan kualitas konfigurasi saat ini.</li>
                    <li class="mb-1"><strong>High Severity</strong> berarti risiko tinggi untuk payroll berikutnya dan perlu ditindak segera.</li>
                    <li class="mb-1"><strong>Medium Severity</strong> menunjukkan data perlu dirapikan agar audit trail tetap sehat.</li>
                    <li>Tabel temuan menampilkan komponen bermasalah beserta tindak lanjut yang disarankan.</li>
                </ul>

                <div class="alert alert-warning border mt-3 mb-0">
                    <p class="mb-0 small">Catatan: bila komponen berbadge governance (BPJS/PPh21/Allowance/dll), perubahan utama dilakukan dari modul governance asal agar sinkron lintas proses payroll.</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>
