<?php

use Illuminate\Support\Facades\Route;
use App\Events\OrderCreated;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/broadcast', function () {
    $sellerId = 1; // Example seller ID
    $message = 'New order received!';

    // Dispatch the event
    event(new OrderCreated($sellerId, $message));

    return 'Event broadcasted successfully!';
});


Route::get('/test-pusher/{sellerId}', function ($sellerId) {
    broadcast(new OrderCreated($sellerId, 'Notifikasi test dari backend'))->toOthers();
    return 'Broadcast sent';
});



