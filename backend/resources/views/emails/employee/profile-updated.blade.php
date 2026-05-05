@component('mail::message')
# Notifikasi Perubahan Data Profil

Yth. **{{ $employeeName }}**,

Kami menginformasikan bahwa data profil Anda telah diperbarui pada **{{ $updatedAt }}** di sistem ARCAV HCM.

**Data yang diperbarui:**

@component('mail::table')
| Field |
|-------|
@foreach($changedFields as $field)
| {{ ucwords(str_replace('_', ' ', $field)) }} |
@endforeach
@endcomponent

Jika Anda tidak merasa melakukan perubahan ini atau ada ketidaksesuaian data, segera hubungi departemen HR atau administrator sistem Anda.

Sesuai dengan **Undang-Undang Perlindungan Data Pribadi (UU PDP)**, Anda berhak mengetahui perubahan data yang dilakukan atas data pribadi Anda.

Terima kasih,
Tim ARCAV HCM
@endcomponent
