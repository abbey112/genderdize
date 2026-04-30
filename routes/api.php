<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ClassifyController;
use App\Http\Controllers\ProfileController;
use App\Http\Middleware\RoleMiddleware;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');


Route::get('/classify', [ClassifyController::class, 'classify']);



Route::prefix('v1')->group(function () {
    Route::post('/register', [App\Http\Controllers\AuthController::class, 'register']);
    Route::post('/login', [App\Http\Controllers\AuthController::class, 'login']);
    Route::post('/refresh-token', [App\Http\Controllers\AuthController::class, 'refreshToken']);

    Route::middleware('auth:sanctum')->group(function () {

    // Admin ONLY
        Route::middleware(['auth:sanctum', 'role:admin'])->group(function () {
            Route::get('/profiles/export', [ProfileController::class, 'export']);
        });

        Route::post('/profiles', [ProfileController::class, 'store']);
        Route::get('/profiles', [ProfileController::class, 'index']);
        Route::get('/profiles/search', [ProfileController::class, 'search']);
        Route::get('/profiles/{id}', [ProfileController::class, 'show']);
        Route::delete('/profiles/{id}', [ProfileController::class, 'destroy']); 
    });


    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [App\Http\Controllers\AuthController::class, 'logout']);
    });
});

Route::prefix('v1/auth')->group(function () {
    Route::get('/github/redirect', [App\Http\Controllers\AuthController::class, 'githubRedirect']);
    Route::get('/github/callback', [App\Http\Controllers\AuthController::class, 'githubCallback']);
});