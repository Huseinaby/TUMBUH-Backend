<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\modulController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Mail;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');


Route::post('/register', [AuthController::class, 'register']);

Route::post('/login', [AuthController::class, 'login']);

Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');

Route::post('/oauth/google/callback', [AuthController::class, 'handleProviderCallback']);

Route::post('/verify-email', [AuthController::class, 'verifyEmail'])->name('verification.verify');

Route::post('/resend-otp', [AuthController::class, 'resendOtp']);

Route::post('/forgot-password', [AuthController::class, 'sendResetPassword']);

Route::get('/reset-password/{token}', function (Request $request, $token) {
    return redirect()->away("tumbuh://resetPassword?token={$token}&email=" . urlencode($request->email));
})->name('password.reset');

Route::post('/reset-password', [AuthController::class, 'resetPassword']);

Route::get('/modul', [modulController::class, 'index']);

Route::post('/modul', [modulController::class, 'store']);

Route::get('/modul/{id}', [modulController::class, 'show']);

Route::put('/modul/{id}', [modulController::class, 'update']);

Route::delete('/modul/{id}', [modulController::class, 'destroy']);

Route::post('/modul/generate', [modulController::class, 'generateContent']);





Route::get('/test-email', function () {
    Mail::raw('Halo dari TUMBUH!', function ($message) {
        $message->to('adef6477@gmail.com')
            ->subject('Tes Email dari Resend');
    });

    return 'Email terkirim';
});
