<?php

namespace App\Http\Controllers;

use App\Models\Source;
use Illuminate\Http\Request;

class SourceController extends Controller
{
    public function index()
    {
        return Source::latest()->paginate(20);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'type' => 'required|string',
            'name' => 'required|string|max:255',
            'url' => 'nullable|url',
            'check_interval_minutes' => 'nullable|integer|min:1',
        ]);
        return Source::create($data);
    }

    public function show(Source $source)
    {
        return $source;
    }

    public function update(Request $request, Source $source)
    {
        $source->update($request->only(['name', 'url', 'is_active', 'check_interval_minutes', 'configuration']));
        return $source;
    }

    public function destroy(Source $source)
    {
        $source->delete();
        return response()->noContent();
    }
}