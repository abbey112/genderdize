<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::options('/classify', function() {
    return response()->json([], 200);
});

Route::get('/classify', [App\Http\Controllers\ClassifyController::class, 'classify']);