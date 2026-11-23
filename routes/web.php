<?php

use App\Models\Content;
use App\Models\Source;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('dashboard', [
        'sources' => Source::latest()->get(),
        'contents' => Content::with('source')->latest()->get(),
    ]);
});

Route::post('/sources', function (Request $request) {
    $data = $request->validate([
        'type' => 'required|string',
        'name' => 'required|string|max:255',
        'url' => 'nullable|url',
    ]);
    Source::create($data);
    return redirect('/');
});