<?php

use App\Http\Controllers\SourceController;
use Illuminate\Support\Facades\Route;

// JSON API for sources; requires a logged-in admin session.
Route::middleware(['web', 'auth'])->group(function () {
    Route::apiResource('sources', SourceController::class);
});
