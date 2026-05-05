@component('mail::message')
# Notifikasi Insiden Keamanan Data

Halo {{ $recipientName }},

Kami menginformasikan bahwa ARCAV HCM mendeteksi insiden keamanan data yang dapat berdampak pada data Anda.

**Ringkasan insiden**
- Judul: {{ $incidentTitle }}
- Waktu terdeteksi: {{ $detectedAt ?? '-' }}
- Jenis data terdampak:
@foreach($affectedDataTypes as $type)
  - {{ $type }}
@endforeach

**Detail singkat**
{{ $incidentDescription }}

**Langkah mitigasi yang sudah kami lakukan**
- Isolasi jalur pemrosesan yang terdampak.
- Penguatan validasi dan monitoring akses.
- Investigasi lanjutan bersama tim keamanan internal.

Sesuai Pasal 46 UU PDP, notifikasi ini dikirim agar Anda mendapatkan informasi transparan terkait insiden.

Jika Anda membutuhkan bantuan lebih lanjut, hubungi:
- {{ $dpoName }}
- {{ $dpoEmail }}
- {{ $privacyContactUrl }}

Terima kasih,
ARCAV HCM
@endcomponent
