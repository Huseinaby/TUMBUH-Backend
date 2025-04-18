<?php

use App\Http\Controllers\AuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Mail;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');


Route::post('/register', [AuthController::class, 'register']);

Route::post('/login', [AuthController::class, 'login']);

Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');

Route::get('/oauth/google', [AuthController::class, 'redirectToProvider']);

Route::get('/oauth/google/callback', [AuthController::class, 'handleProviderCallback']);

Route::post('/verify-email', [AuthController::class, 'verifyEmail'])->name('verification.verify');

Route::post('/resend-otp', [AuthController::class, 'resendOtp']);

Route::post('/forgot-password', [AuthController::class, 'sendResetPassword']);

Route::get('/reset-password/{token}', function (Request $request, $token){
    return redirect()->away(config('app.frontend_url') . '/reset-password?token=' . $token . '&email=' . urlencode($request->email));
})->name('password.reset');

Route::post('/reset-password', [AuthController::class, 'resetPassword']);

Route::get('/test-email', function () {
    Mail::raw('Halo dari TUMBUH!', function ($message) {
        $message->to('adef6477@gmail.com')
                ->subject('Tes Email dari Resend');
    });

    return 'Email terkirim';
});