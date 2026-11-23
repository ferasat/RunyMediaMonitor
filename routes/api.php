<?php

use App\Http\Controllers\SourceController;
use Illuminate\Support\Facades\Route;

Route::apiResource('sources', SourceController::class);