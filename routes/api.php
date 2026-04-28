<?php

use App\Http\Controllers\Api\AttendanceController;
use App\Http\Middleware\ValidateApiKey;
use Illuminate\Support\Facades\Route;

Route::middleware(ValidateApiKey::class)->group(function () {
    Route::post('/presensi', [AttendanceController::class, 'store']);
    Route::get('/ping', fn () => response()->json(['status' => 'ok', 'timestamp' => now()]));
});
