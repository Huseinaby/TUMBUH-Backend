<?php

namespace App\Http\Controllers;

use App\Mail\VerifyAccountMail;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    //
    public function register(Request $request)
    {

        $validator = validator($request->all(), [
            'username' => 'required|string|min:3|max:20|unique:users',
            'email' => 'required|email|unique:users',
            'photo' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            'password' => 'required|string|min:6|max:20'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $photo = $request->input('photo', 'https://avatar.iran.liara.run/public');

        $user = User::create([
            'username' => $request->username,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => 'user',
            'photo' => $photo,
        ]);

        $otp = rand(100000, 999999);

        DB::table('otp_verification')->updateOrInsert(
            ['email' => $user->email],
            ['otp' => $otp, 'otp_expired' => now()->addMinutes(10)]
        );

        Mail::to($user->email)->send(new VerifyAccountMail($user->username, $otp));

        return response()->json([
            'message' => 'User created successfully',
            'user' => $user,
        ], 201);
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string'
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => 'Invalid credentials'
            ], 401);
        }

        if (!$user->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'Email belum diverifikasi'
            ], 403);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'User logged in successfully',
            'user' => $user,
            'token' => $token
        ], 200);
    }

    public function logout(Request $request)
    {
        $token = $request->user()->currentAccessToken();

        if($token) {
            $token->delete();
        }

        return response()->json([
            'message' => 'User logged out successfully'
        ], 200);
    }

    public function handleProviderCallback(Request $request)
    {
        try {
            $idToken = $request->input('id_token');

            if (!$idToken) {
                return response()->json([
                    'message' => 'ID token tidak ditemukan',
                    'idtoken' => $idToken
                ], 400);
            }

            $client = new \Google_Client(['client_id' => env('GOOGLE_CLIENT_ID')]);
            $payload = $client->verifyIdToken($idToken);

            if (!$payload) {
                return response()->json([
                    'message' => 'ID token tidak valid',
                    'idtoken' => $idToken,
                    'payload' => $payload
                ], 401);
            }

            if(!isset($payload['email'], $payload['sub'], $payload['name'])) {
                return response()->json([
                    'message' => 'Payload dari Google tidak lengkap'
                ], 422);
            }


            $name = $payload['name'];
            $parts = explode('-', $name);
            if(count($parts) > 1) {
                array_pop($parts);
            } 
            $username = implode(' ', $parts);

            $googleId = $payload['sub'];
            $email = $payload['email'];            
            $photo = $payload['picture'] ?? 'https://avatar.iran.liara.run/public';

            $user = User::where('gauth_id', $googleId)->first();

            if (!$user) {
                $user = User::where('email', $email)->first();

                if ($user) {
                    $user->update([
                        'photo' => $user->photo ?? $photo,
                        'email_verified_at' => now(),
                        'gauth_id' => $googleId,
                        'gauth_type' => 'google'
                    ]);
                } else {
                    $user = User::create([
                        'username' => $username,
                        'email' => $email,
                        'role' => 'user',
                        'photo' => $photo,
                        'email_verified_at' => now(),
                        'password' => Hash::make(Str::random(16)),
                        'gauth_id' => $googleId,
                        'gauth_type' => 'google'
                    ]);
                }
            } else {
                if (!$user->photo) {
                    $user->update(['photo' => $photo]);
                }
            }

            $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'message' => 'Login berhasil',
                'user' => [
                    'id' => $user->id,
                    'username' => $user->username,
                    'email' => $user->email,
                    'photo' => $user->photo,
                    'role' => $user->role,
                ],
                'token' => $token
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Login gagal',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function verifyEmail(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'otp' => 'required|string|size:6'
        ]);

        $otpRecord = DB::table('otp_verification')
            ->where('email', $request->email)
            ->where('otp', $request->otp)
            ->where('otp_expired', '>', now())
            ->first();

        if (!$otpRecord) {
            return response()->json([
                'message' => 'OTP tidak valid atau sudah kadaluarsa'
            ], 422);
        }

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json([
                'message' => 'User tidak ditemukan'
            ], 404);
        }

        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'Email sudah diverifikasi'
            ], 200);
        }

        $user->markEmailAsVerified();

        DB::table('otp_verification')->where('email', $request->email)->delete();

        return response()->json([
            'message' => 'Email berhasil diverifikasi'
        ], 200);
    }

    public function resendOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email'
        ]);

        $user = User::where('email', $request->email)->first();

        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'Email sudah diverifikasi'
            ], 200);
        }

        $otp = rand(100000, 999999);

        DB::table('otp_verification')->updateOrInsert(
            ['email' => $user->email],
            ['otp' => $otp, 'otp_expired' => now()->addMinutes(10)]
        );

        Mail::to($user->email)->send(new VerifyAccountMail($user->username, $otp));

        return response()->json([
            'message' => 'OTP baru telah dikirim ke email Anda'
        ], 200);
    }

    public function sendResetPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $status = Password::sendResetLink($request->only('email'));

        if ($status === Password::RESET_LINK_SENT) {
            return response()->json([
                'message' => 'Link reset password telah dikirim ke email Anda'
            ], 200);
        }

        return response()->json([
            'message' => 'Gagal mengirim link reset password'
        ], 500);
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string|min:6',
            'token' => 'required|string'
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'token'),
            function ($user, $password) {
                $user->password = Hash::make($password);
                $user->save();
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return response()->json([
                'message' => 'Password berhasil direset'
            ], 200);
        }

        return response()->json([
            'message' => 'Gagal mereset password'
        ], 500);
    }
}
