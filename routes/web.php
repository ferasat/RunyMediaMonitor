<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MediaDownloadController;
use Illuminate\Support\Facades\Route;

Route::get('/login', [LoginController::class, 'show'])->name('login')->middleware('guest');
Route::post('/login', [LoginController::class, 'login'])->middleware('guest');
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

// Signed, time-limited public download links (sent to Bale).
Route::get('/d/{token}/{name}', MediaDownloadController::class)
    ->name('media.download')->middleware(['signed', 'throttle:60,1']);

Route::middleware('auth')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::post('/sources', [DashboardController::class, 'store']);
    Route::post('/sources/{source}/check', [DashboardController::class, 'check']);
    Route::post('/sources/{source}/toggle', [DashboardController::class, 'toggle']);
    Route::delete('/sources/{source}', [DashboardController::class, 'destroy']);
    Route::post('/contents/{content}/send', [DashboardController::class, 'send']);
});
