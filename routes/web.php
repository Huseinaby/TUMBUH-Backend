<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    // return view('welcome');
    $name = 'Nakja saja';
    return $name;
});
