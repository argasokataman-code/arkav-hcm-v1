<div class="modal fade" id="arcav_upgrade_required" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
	<div class="modal-dialog modal-dialog-centered">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" data-arcav-upgrade-title>Akses dibatasi</h5>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
			</div>
			<div class="modal-body">
				<div class="text-muted" data-arcav-upgrade-body>
					Fitur ini terkunci untuk paket saat ini.
				</div>
				<div class="mt-3 p-3 rounded-3 bg-light" data-arcav-upgrade-tip>
					<div class="small text-muted">Tips</div>
					<div class="fw-semibold">Yuk subscribe sekarang untuk akses fitur lebih lengkap.</div>
				</div>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-light" data-bs-dismiss="modal">Tutup</button>
				<a class="btn btn-outline-secondary" href="{{ url('/landing#pricing') }}" data-arcav-upgrade-secondary>Lihat paket</a>
				<a class="btn btn-primary" href="{{ url('/subscription') }}" data-arcav-upgrade-primary>
					<i class="ti ti-rocket me-1"></i> Subscribe sekarang
				</a>
			</div>
		</div>
	</div>
</div>

