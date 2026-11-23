<?php

use App\Models\Source;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('dashboard', ['sources' => Source::latest()->get()]);
});