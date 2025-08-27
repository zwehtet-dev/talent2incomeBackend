<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Health check endpoints
Route::get('/health', [App\Http\Controllers\HealthController::class, 'check']);
Route::get('/health/simple', [App\Http\Controllers\HealthController::class, 'simple']);
