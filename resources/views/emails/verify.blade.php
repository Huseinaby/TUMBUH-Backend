<x-mail::message>
# Verifikasi Akun TUMBUH

Halo, {{ $username }} 👋

Terima kasih telah mendaftar di **TUMBUH**, platform ekosistem digital bagi pegiat tanaman 🌿  
Untuk menyelesaikan proses pendaftaran, silakan gunakan kode OTP di bawah ini untuk melanjutkan:

<x-mail::panel>
    <h2 style="text-align: center; font-size: 24px; color: #2d6a2d;">
        {{ $otp }}
    </h2>
</x-mail::panel>

Kode OTP ini hanya berlaku selama 10 menit, jadi jangan lama-lama yaa~ 😤

Jika kamu tidak merasa mendaftar, abaikan saja email ini 😊

Salam hangat,<br>
Tim {{ config('app.name') }}
</x-mail::message>

