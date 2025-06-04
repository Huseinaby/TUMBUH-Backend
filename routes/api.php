<?php

use App\Http\Controllers\articleController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\cartController;
use App\Http\Controllers\checkoutController;
use App\Http\Controllers\locationController;
use App\Http\Controllers\modulController;
use App\Http\Controllers\productController;
use App\Http\Controllers\quizController;
use App\Http\Controllers\reviewController;
use App\Http\Controllers\sellerController;
use App\Http\Controllers\ShippingController;
use App\Http\Controllers\transactionController;
use App\Http\Controllers\userAddressController;
use App\Http\Controllers\userController;
use App\Http\Controllers\videoController;
use App\Http\Controllers\withdrawController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Mail;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

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
Route::get('/modul/user/{userId}', [modulController::class, 'getModulByUser']);
Route::post('/modul/{id}/favorite', [ModulController::class, 'favoriteUser']);
Route::get('/modul/favorite/list', [ModulController::class, 'getFavoriteModul']);

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
Route::post('/quiz/progress', [QuizController::class, 'updateProgress']);
Route::get('/quiz/progress/{userId}', [QuizController::class, 'getProgress']);

//product
Route::get('/product', [productController::class, 'index']);
Route::get('/product/{id}', [productController::class, 'show']);
Route::post('/product', [productController::class, 'store']);
Route::put('/product/{id}', [productController::class, 'update']);
Route::delete('/product/{id}', [productController::class, 'destroy']);
Route::get('/product/user/{userId}', [productController::class, 'getProductByUser']);   
Route::delete('/product/image/{id}', [productController::class, 'destroyImage']);

//cartItem
Route::get('/cart', [cartController::class, 'index']);
Route::post('/cart', [cartController::class, 'store']);
Route::put('/cart/{id}', [cartController::class, 'update']);
Route::delete('/cart/{id}', [cartController::class, 'destroy']);

//transaction
Route::post('/checkout/summary', [transactionController::class, 'checkoutSummary']);
Route::post('/checkout', [transactionController::class, 'store']);
Route::post('/buynow/summary', [transactionController::class, 'buyNowSummary']);
Route::post('/buynow', [transactionController::class, 'buyNow']);
Route::get('/transaction', [transactionController::class, 'index']);
Route::get('/transaction/{id}', [transactionController::class, 'show']);
Route::post('/transaction/webhook', [transactionController::class, 'handleWebhook']);
Route::get('/transaction/success', [transactionController::class, 'paymentSuccess']);
Route::get('/transaction/failed', [transactionController::class, 'paymentFailed']);
Route::get('/transaction/income', [transactionController::class, 'sellerIncome']);
Route::post('/transaction/{id}/resi', [transactionController::class, 'inputResi']);
Route::get('/transaction/{id}/track', [transactionController::class, 'cekResi']);
Route::get('/transaction/{id}/confirm-recieved', [transactionController::class, 'confirmRecieved']);
Route::get('/transaction/{id}/confirm', [transactionController::class, 'confirmTransaction']);
Route::post('/transaction/{id}/cancel', [transactionController::class, 'cancelTransaction']);


//review
Route::post('/review', [reviewController::class, 'storeReview']);
Route::get('/review/product/{productId}', [reviewController::class, 'getReviewsByProduct']);
Route::get('/review/user/{userId}', [reviewController::class, 'getReviewsByUser']);
Route::put('/review/{id}/update', [reviewController::class, 'updateReview']);
Route::delete('/review/{id}', [reviewController::class, 'deleteReview']);

//withdraw
Route::get('/withdraw', [withdrawController::class, 'listWithdraw']);
Route::post('/wihdraw/request', [withdrawController::class, 'requestWithdraw']);
Route::post('/withdraw/{id}/handle', [withdrawController::class, 'handleWithdraw']);

//seller
Route::post('/user/seller', [sellerController::class, 'register']);
Route::get('/origin/seller', [sellerController::class, 'getOriginSeller']);
Route::post('/seller/status', [sellerController::class, 'verifySeller']);

//location
Route::get('/location/province', [locationController::class, 'getProvince']);
Route::get('/location/province/sync', [locationController::class, 'syncProvince']);
Route::post('/location/kabupaten', [locationController::class, 'getKabupaten']);
Route::post('/location/kecamatan', [locationController::class, 'getKecamatan']);
Route::get('/location/origin-id', [locationController::class, 'getOriginByKecamatan']);

//address
Route::get('/address', [userAddressController::class, 'getAddress']);
Route::post('/address', [userAddressController::class, 'store']);
Route::delete('/address/{id}', [userAddressController::class, 'destroy']);

//shipping
Route::get('/shipping/destination', [ShippingController::class, 'searchDestination']);
Route::post('/shipping/cost', [ShippingController::class, 'cost']);

//test generate quiz
Route::post('/quiz/generate', [QuizController::class, 'generateQuiz']);

//Test Email
Route::get('/test-email', function () {
    Mail::raw('Halo dari TUMBUH!', function ($message) {
        $message->to('adef6477@gmail.com')
            ->subject('Tes Email dari Resend');
    });

    return 'Email terkirim';
});
