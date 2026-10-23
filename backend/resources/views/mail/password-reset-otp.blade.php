<x-mail::message>
# Permintaan pemulihan kata sandi

Sistem menerima permintaan pemulihan kata sandi untuk username **{{ $username }}**.

Kode OTP:

# {{ $otp }}

Kode ini berlaku selama {{ $validMinutes }} menit dan hanya dapat dicoba sebanyak tiga kali. Sampaikan kode kepada pengguna melalui jalur komunikasi yang telah disepakati. Abaikan email ini jika permintaan tidak dikenali.

Salam,<br>
{{ config('app.name') }}
</x-mail::message>
