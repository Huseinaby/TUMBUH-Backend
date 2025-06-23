<?php

use App\Events\UserNotification;
use Illuminate\Support\Facades\Route;
use App\Events\OrderCreated;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/broadcast', function () {
    $userId = 4; // Example seller ID
    $message = 'New order received!';

    // Dispatch the event
    event(new UserNotification($userId, $message, 'info'));

    return 'Event broadcasted successfully!';
});

Route::get('/test-pusher/{sellerId}', function ($sellerId) {
    broadcast(new OrderCreated($sellerId, 'Notifikasi test dari backend'))->toOthers();
    return 'Broadcast sent';
});

Route::get('/test-pusher', function () {
    return view('testPusher');
});



