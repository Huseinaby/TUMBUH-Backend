<x-mail::message>
# Verifikasi Akun TUMBUH

Halo, {{ $username }} 👋

Terima kasih telah mendaftar di **TUMBUH**, platform ekosistem digital bagi pegiat tanaman 🌿  
Untuk menyelesaikan proses pendaftaran, silakan klik tombol di bawah ini:

<x-mail::button :url="$verificationUrl">
Verifikasi Sekarang
</x-mail::button>

Jika kamu tidak merasa mendaftar, abaikan saja email ini 😊

Salam hangat,<br>
Tim {{ config('app.name') }}
</x-mail::message>
