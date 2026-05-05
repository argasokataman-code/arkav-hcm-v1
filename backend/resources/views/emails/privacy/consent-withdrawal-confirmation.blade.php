@component('mail::message')
# Konfirmasi Pencabutan Persetujuan

Halo {{ $recipientName }},

Kami telah menerima dan memproses permintaan pencabutan persetujuan data Anda pada **{{ $withdrawnAt }}**.

**Ruang lingkup permintaan:** {{ $scope }}

**Status pencabutan:**
- Persetujuan AI Chat: {{ $withdrawnScopes['ai_chat'] ? 'Dicabut' : 'Tidak diubah' }}
- Persetujuan Biometrik (selfie/GPS): {{ $withdrawnScopes['biometric'] ? 'Dicabut' : 'Tidak diubah' }}

Jika Anda tidak merasa melakukan perubahan ini, segera hubungi DPO kami di **{{ $dpoEmail }}**.

Terima kasih,
ARCAV HCM
@endcomponent
