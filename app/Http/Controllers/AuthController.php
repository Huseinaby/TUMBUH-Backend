<?php

namespace App\Http\Controllers;

use App\Mail\VerifyAccountMail;
use App\Mail\ResetPasswordMail;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Laravel\Socialite\Facades\Socialite;
use Mockery\VerificationDirector;
use function Laravel\Prompts\table;

class AuthController extends Controller
{
    //
    public function register(Request $request)
    {

        $validator = validator($request->all(), [
            'username' => 'required|string|min:3|max:20|unique:users',
            'email' => 'required|email|unique:users',
            'password' => 'required|string|min:6|max:20'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = User::create([
            'username' => $request->username,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        $otp = rand(100000, 999999);

        DB::table('otp_verification')->updateOrInsert(
            ['email' => $user->email],
            ['otp' => $otp, 'otp_expired' => now()->addMinutes(10)]
        );

        Mail::to($user->email)->send(new VerifyAccountMail($user->username, $otp));

        return response()->json([
            'message' => 'User created successfully',
            'user' => $user,
            'token' => $token
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

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'User logged in successfully',
            'user' => $user,
            'token' => $token
        ], 200);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'User logged out successfully'
        ], 200);
    }

    public function redirectToProvider()
    {
        return response()->json([
            'url' => Socialite::driver('google')->stateless()->redirect()->getTargetUrl()
        ]);
    }

    public function handleProviderCallback()
    {
        try {
            $googleUser = Socialite::driver('google')->stateless()->user();

            $user = User::where('gauth_id', $googleUser->id)->first();

            if (!$user) {
                $user = User::where('email', $googleUser->email)->first();

                if ($user) {
                    $user->update([
                        'gauth_id' => $googleUser->id,
                        'gauth_type' => 'google'
                    ]);
                } else {
                    $user = User::create([
                        'username' => $googleUser->name,
                        'email' => $googleUser->email,
                        'role' => 'user',
                        'gauth_id' => $googleUser->id,
                        'gauth_type' => 'google',
                        'password' => Hash::make('1234password')
                    ]);
                }
            }

            $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'message' => 'User logged in successfully',
                'user' => $user,
                'token' => $token
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Login gagal',
                'message' => $e->getMessage(),
                'trace' => $e->getTrace(),
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

    public function sendResetPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $status = Password::sendResetLink($request->only('email'));

        if($status === Password::RESET_LINK_SENT) {
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
            $request->only('email', 'password','token'),
            function ($user, $password) {
                $user->password = Hash::make($password);
                $user->save();
            }
        );

        if($status === Password::PASSWORD_RESET) {
            return response()->json([
                'message' => 'Password berhasil direset'
            ], 200);
        }

        return response()->json([
            'message' => 'Gagal mereset password'
        ], 500);
    }
}

