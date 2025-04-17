<x-mail::message>
# Reset Password Akun TUMBUH

Halo, {{ $username }}! 🌱

Kami menerima permintaan untuk mereset kata sandi akunmu. Silahkan klik tombol di bawah ini untuk melanjutkan:

<x-mail::panel>
    <h2 style="text-align: center; font-size: 24px; color: #2d6a2d;">
        {{ $otp }}
    </h2>
</x-mail::panel>

Jika kamu tidak merasa meminta reset password, kamu boleh abaikan email ini.

Terima kasih telah menggunakan **TUMBUH**!  
Tumbuh bersama tanaman, tumbuh bersama cinta~ 💚🌿

Salam hangat,  
**{{ config('app.name') }}**
</x-mail::message>