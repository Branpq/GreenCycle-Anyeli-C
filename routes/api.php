<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\TreeCareController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'me']);
    Route::post('/trees/{tree}/care', [TreeCareController::class, 'store']);
});
