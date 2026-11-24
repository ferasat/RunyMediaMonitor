<?php

namespace App\Http\Controllers;

use App\Jobs\DeliverContentJob;
use App\Jobs\SourceCheckJob;
use App\Models\Content;
use App\Models\Source;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index()
    {
        return view('dashboard', [
            'sources' => Source::withCount('contents')->latest()->get(),
            'contents' => Content::with(['source', 'files'])->whereNot('status', 'seen')->latest()->limit(100)->get(),
            'seenCount' => Content::where('status', 'seen')->count(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'url' => 'required|url|max:500',
            'name' => 'nullable|string|max:255',
            'send_latest' => 'nullable|integer|min:0|max:5',
        ]);
        $d = Source::detect($data['url']);
        if (! in_array($d['type'], ['youtube', 'instagram'])) {
            return back()->withErrors(['url' => 'فقط لینک کانال یوتیوب یا صفحه اینستاگرام پشتیبانی می‌شود.']);
        }
        $source = Source::firstOrCreate(['url' => $data['url']], array_merge($d, [
            'name' => $data['name'] ?: $d['name'],
            'is_active' => true,
            'check_interval_minutes' => 720,
        ]));
        SourceCheckJob::dispatch($source, (int) ($data['send_latest'] ?? 0));
        return redirect('/')->with('status', "منبع «{$source->name}» اضافه شد؛ اسکن اولیه (baseline) در صف است.");
    }

    public function check(Source $source)
    {
        SourceCheckJob::dispatch($source);
        return back()->with('status', "بررسی «{$source->name}» در صف قرار گرفت.");
    }

    public function toggle(Source $source)
    {
        $source->update(['is_active' => ! $source->is_active]);
        return back();
    }

    public function destroy(Source $source)
    {
        $source->delete();
        return back()->with('status', 'منبع حذف شد.');
    }

    public function send(Content $content)
    {
        $content->update(['status' => 'pending', 'attempts' => 0]);
        DeliverContentJob::dispatch($content);
        return back()->with('status', 'ارسال مجدد در صف قرار گرفت.');
    }
}
