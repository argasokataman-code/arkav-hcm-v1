<!-- Biometric Consent Modal (shared) -->
<div class="modal fade" id="arcav_biometric_consent_modal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-semibold"><i class="ti ti-shield-lock me-2 text-primary"></i>Persetujuan Data Biometrik</h5>
            </div>
            <div class="modal-body">
                <p class="text-body mb-3">Fitur selfie absensi menggunakan data foto (biometrik) Anda. Sesuai <strong>UU PDP</strong>, kami memerlukan persetujuan eksplisit Anda sebelum memproses data ini.</p>
                <ul class="mb-3 ps-3 text-body small">
                    <li>Foto selfie disimpan terenkripsi dan hanya digunakan untuk verifikasi kehadiran.</li>
                    <li>Anda dapat mencabut persetujuan ini kapan saja melalui pengaturan privasi.</li>
                    <li>Data tidak dibagikan ke pihak ketiga tanpa izin.</li>
                </ul>
                <div class="alert alert-warning small mb-0 py-2">
                    <i class="ti ti-info-circle me-1"></i>Tanpa persetujuan, fitur selfie tidak dapat digunakan.
                </div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-light" id="arcav_biometric_consent_decline_btn">Tolak</button>
                <button type="button" class="btn btn-primary" id="arcav_biometric_consent_agree_btn">
                    <i class="ti ti-check me-1"></i>Saya Setuju
                </button>
            </div>
        </div>
    </div>
</div>
