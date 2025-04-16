<?php

namespace App\Http\Controllers;

use App\Mail\VerifyAccountMail;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Laravel\Socialite\Facades\Socialite;

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

        $verificationUrl = url('/api/verify-email/' . $user->id);

        Mail::to($user->email)->send(new VerifyAccountMail($user->username, $verificationUrl));

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
        ],200);
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

            if(!$user){
                $user = User::where('email', $googleUser->email)->first();

                if($user){
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

            }  catch (\Exception $e) {
                return response()->json([
                    'error' => 'Login gagal',
                    'message' => $e->getMessage(),
                    'trace' => $e->getTrace(),
                ], 500);
            }
        }
    }

