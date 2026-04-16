<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});


//Route::get('/classify', [App\Http\Controllers\ClassifyController::class, 'classify']);