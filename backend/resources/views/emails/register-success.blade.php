@component('mail::message')
# Registrasi Berhasil

Halo {{ $user->name }},

Akun Anda berhasil dibuat dengan email **{{ $user->email }}**.

Anda sekarang bisa login dan melanjutkan setup company atau langganan sesuai flow onboarding.

@component('mail::button', ['url' => config('app.url') . '/login'])
Login Sekarang
@endcomponent

Terima kasih,

{{ $issuerName ?? config('app.name') }}
@endcomponent
