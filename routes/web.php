<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    // return view('welcome');
    $name = 'test actions LAST';
    return $name;
});
