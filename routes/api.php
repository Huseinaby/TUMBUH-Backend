<?php

use App\Http\Controllers\articleController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\modulController;
use App\Http\Controllers\quizController;
use App\Http\Controllers\videoController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Mail;

//Authentication
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


//Modul
Route::get('/modul', [modulController::class, 'index']);
Route::post('/modul', [modulController::class, 'store']);
Route::get('/modul/{id}', [modulController::class, 'show']);
Route::put('/modul/{id}', [modulController::class, 'update']);
Route::delete('/modul/{id}', [modulController::class, 'destroy']);
Route::post('/modul/generate', [modulController::class, 'generateContent']);

//article
Route::get('/article', [articleController::class, 'index']);
Route::get('/article/{id}', [articleController::class, 'show']);
Route::delete('/article/{id}', [articleController::class, 'destroy']);
Route::get('/article/modul/{modulId}', [articleController::class, 'getByModul']);
Route::post('/article/generate', [articleController::class, 'generateMoreArticle']);

//video
Route::get('/video', [videoController::class, 'index']);
Route::get('/video/{id}', [videoController::class, 'show']);
Route::delete('/video/{id}', [videoController::class, 'destroy']);
Route::get('/video/modul/{modulId}', [videoController::class, 'getByModul']);
Route::post('/video/generate', [videoController::class, 'generateMoreVideo']);

//quiz
Route::get('/quiz', [QuizController::class, 'index']);
Route::get('/quiz/{id}', [QuizController::class, 'show']);
Route::delete('/quiz/{id}', [quizController::class, 'destroy']);
Route::get('/quiz/modul/{modulId}', [QuizController::class, 'getByModul']);




//Test Email
Route::get('/test-email', function () {
    Mail::raw('Halo dari TUMBUH!', function ($message) {
        $message->to('adef6477@gmail.com')
            ->subject('Tes Email dari Resend');
    });

    return 'Email terkirim';
});
