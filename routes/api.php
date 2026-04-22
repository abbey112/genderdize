<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ClassifyController;
use App\Http\Controllers\ProfileController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');


Route::get('/classify', [ClassifyController::class, 'classify']);

Route::post('/profiles', [ProfileController::class, 'store']);
Route::get('/profiles', [ProfileController::class, 'index']);
Route::get('/profiles/search', [ProfileController::class, 'search']);
Route::get('/profiles/{id}', [ProfileController::class, 'show']);
Route::delete('/profiles/{id}', [ProfileController::class, 'destroy']);