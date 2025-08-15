<?php

use App\Http\Controllers\articleController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\cartController;
use App\Http\Controllers\GroupController;
use App\Http\Controllers\locationController;
use App\Http\Controllers\modulController;
use App\Http\Controllers\notificationController;
use App\Http\Controllers\PostCommentController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\productController;
use App\Http\Controllers\quizController;
use App\Http\Controllers\reviewController;
use App\Http\Controllers\sellerController;
use App\Http\Controllers\ShippingController;
use App\Http\Controllers\transactionController;
use App\Http\Controllers\userAddressController;
use App\Http\Controllers\userController;
use App\Http\Controllers\videoController;
use App\Http\Controllers\wallateHistoryController;
use App\Http\Controllers\withdrawController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Broadcast;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');
Route::put('/user', [userController::class, 'update'])->middleware('auth:sanctum');
Route::post('/user/fcm-token', [userController::class, 'storeFcmToken'])->middleware('auth:sanctum');
Route::get('/user/test-notif/{userId}', [userController::class, 'sendNotifToUser']);

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
Route::post('/update-password', [AuthController::class, 'updatePassword'])->middleware('auth:sanctum');
Route::get('/user/request-delete', [AuthController::class, 'requestAccountDeletion'])->middleware('auth:sanctum');

//Modul
Route::get('/modul', [modulController::class, 'index']);
Route::post('/modul', [modulController::class, 'store']);
Route::get('/modul/{id}', [modulController::class, 'show']);
Route::put('/modul/{id}', [modulController::class, 'update']);
Route::delete('/modul/{id}', [modulController::class, 'destroy']);
Route::get('/modul/{userId}/user', [modulController::class, 'getExceptByUser']);
Route::post('/modul/generate', [modulController::class, 'generateContent']);
Route::get('/modul/user/{userId}', [modulController::class, 'getModulByUser']);
Route::get('/modul/favorite/list', [modulController::class, 'getFavoriteModul']);
Route::get('/modul/favorite/most', [modulController::class, 'getMostFavoriteModul']);
Route::get('/modul/favorite/{modulId}', [modulController::class, 'favoriteUser']);

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
Route::get('/product/category', [productController::class, 'getCategory']);
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
Route::post('/transaction/courierCost', [transactionController::class, 'getCourierCost']);

Route::get('/transaction', [transactionController::class, 'index']);
Route::get('/transaction/user', [transactionController::class, 'getByUser']);
Route::get('/transaction/user/completed', [transactionController::class, 'getByUserCompleted']);
Route::get('/transaction/user/paid', [transactionController::class, 'getByUserPaid']);
Route::get('/transaction/user/pending', [transactionController::class, 'getByUserPending']);
Route::get('/transaction/seller', [transactionController::class, 'getBySeller']);
Route::get('/transaction/{id}/recieved', [transactionController::class, 'confirmRecieved']);

Route::post('/transaction/webhook', [transactionController::class, 'handleWebhook']);
Route::get('/transaction/finish', [transactionController::class, 'finishPayment']);
Route::get('/transaction/error', [transactionController::class, 'paymentError']);

Route::get('/transaction/income', [transactionController::class, 'sellerIncome']);
Route::post('/transaction/{id}/resi', [transactionController::class, 'inputResi']);
Route::get('/transaction/{id}/track', [transactionController::class, 'cekResi']);
Route::get('/transaction/{id}/confirm', [transactionController::class, 'confirmTransaction']);
Route::post('/transaction/{id}/cancel', [transactionController::class, 'cancelTransaction']);
Route::get('/transaction/{id}', [transactionController::class, 'show']);

Route::post('/shippingCost/test', [transactionController::class, 'shippingCostTest']);

//review
Route::get('/user/orderItem', [reviewController::class, 'getOrderItem']);
Route::post('/review', [reviewController::class, 'store']);
Route::get('/review/product/{productId}', [reviewController::class, 'getReviewsByProduct']);
Route::get('/review/user/{userId}', [reviewController::class, 'getReviewsByUser']);
Route::put('/review/update/{id}', [reviewController::class, 'updateReview']);
Route::delete('/review/delete/{id}', [reviewController::class, 'deleteReview']);

//withdraw
Route::get('/wigthdraw', [withdrawController::class, 'listWithdraw']);
Route::get('/withdraw/user/{userId}', [withdrawController::class, 'listWithdrawByUser']);
Route::post('/withdraw/request', [withdrawController::class, 'requestWithdraw']);
Route::post('/withdraw/{id}/handle', [withdrawController::class, 'handleWithdraw']);

//seller
Route::post('/user/seller', [sellerController::class, 'register']);
Route::get('/origin/seller', [sellerController::class, 'getOriginSeller']);
Route::post('/seller/status', [sellerController::class, 'verifySeller']);
Route::get('/seller', [sellerController::class, 'getSeller']);
Route::put('/seller', [sellerController::class, 'update']);
Route::delete('/seller', [sellerController::class, 'destroy']);
Route::get('/seller/dashboard', [sellerController::class, 'getDashboard']);
Route::get('/seller/saldo-history/{userId}', [wallateHistoryController::class, 'getByUser']);

//location
Route::get('/location/province', [locationController::class, 'getProvince']);
Route::get('/location/province/sync', [locationController::class, 'syncProvince']);
Route::get('/location/origin-id', [locationController::class, 'getOrigin']);

//address
Route::get('/address', [userAddressController::class, 'getAddress']);
Route::post('/address', [userAddressController::class, 'store']);
Route::delete('/address/{id}', [userAddressController::class, 'destroy']);
Route::put('/address/{id}', [userAddressController::class, 'update']);

//shipping
Route::get('/shipping/destination', [ShippingController::class, 'searchDestination']);
Route::post('/shipping/cost', [ShippingController::class, 'cost']);

//notification
Route::get('/notification', [notificationController::class, 'getUserNotifications']);
Route::post('/notification', [notificationController::class, 'store']);
Route::delete('/notifications', [notificationController::class, 'deleteNotifications']);
Route::put('/notifications/read', [notificationController::class, 'readNotifications']);
Route::put('/notification/{id}/read', [notificationController::class, 'markAsRead']);
Route::get('/notification/{id}', [notificationController::class, 'show']);

//group
Route::get('/group', [GroupController::class, 'index']);
Route::post('/group', [GroupController::class, 'store']);
Route::get('/group/{id}', [GroupController::class, 'show']);
Route::put('/group/{id}', [GroupController::class, 'update']);
Route::delete('/group/{id}', [GroupController::class, 'destroy']);
Route::get('/group/{id}/members', [GroupController::class, 'members']);
Route::post('/group/{id}/join', [GroupController::class, 'join']);
Route::post('/group/{id}/leave', [GroupController::class, 'leave']);

//post
Route::delete('/post/image/{id}', [PostController::class, 'destroyImage']);
Route::get('/group/{groupId}/post', [PostController::class, 'index']);
Route::post('/group/{groupId}/post', [PostController::class, 'store']);
Route::get('/post/{id}', [PostController::class, 'show']);
Route::put('/post/{id}', [PostController::class, 'update']);
Route::delete('/post/{id}', [PostController::class, 'destroy']);
Route::get('/post/{id}/like', [PostController::class, 'toggleLikePost']);

//post comment
Route::get('/post/{postId}/comment', [PostCommentController::class, 'index']);
Route::post('/post/{postId}/comment', [PostCommentController::class, 'store']);
Route::get('/comment/{commentId}', [PostCommentController::class, 'show']);
Route::put('/comment/{commentId}', [PostCommentController::class, 'update']);
Route::delete('/comment/{commentId}', [PostCommentController::class, 'destroy']);



//test generate quiz
Route::post('/quiz/generate', [QuizController::class, 'generateQuiz']);

Broadcast::routes(['middleware' => ['auth:sanctum']]);


//Test Email
Route::get('/test-email', function () {
    Mail::raw('Halo dari TUMBUH!', function ($message) {
        $message->to('adef6477@gmail.com')
            ->subject('Tes Email dari Resend');
    });

    return 'Email terkirim';
});
