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

Route::get('/verify-email/{id}', [AuthController::class, 'verifyEmail'])->name('verification.verify');

Route::post('/forgot-password', [AuthController::class, 'sendResetPasswordOtp']);