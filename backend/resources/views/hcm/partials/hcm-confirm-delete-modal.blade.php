{{-- Shared Bootstrap confirm (replaces window.confirm) — included from main layout for app shell pages. --}}
<div class="modal fade" id="arcav_hcm_confirm_delete" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" data-arcav-confirm-title>Konfirmasi</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="mb-0 text-muted" data-arcav-confirm-body></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-danger" data-arcav-confirm-yes>Ya, lanjutkan</button>
            </div>
        </div>
    </div>
</div>
