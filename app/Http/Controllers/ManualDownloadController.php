<?php

namespace App\Http\Controllers;

use App\Models\Content;
use App\Models\Source;
use App\Support\MediaLink;
use Illuminate\Http\Request;

class ManualDownloadController extends Controller
{
    public function index()
    {
        $items = Content::with(['source', 'files'])
            ->whereHas('source', fn ($q) => $q->where('url', 'like', Source::MANUAL_URL_PREFIX.'%'))
            ->latest('updated_at')->paginate(30);
        $busy = $items->getCollection()->contains(fn ($c) => in_array($c->status, ['pending', 'downloading', 'downloaded']));
        return view('manual', ['items' => $items, 'busy' => $busy]);
    }

    public function store(Request $request)
    {
        $request->validate(['url' => 'required|string|max:500']);
        $link = MediaLink::parse($request->input('url'));
        if (! $link) {
            return back()->withInput()->withErrors(['url' => 'لینک معتبر نیست. فقط لینک ویدیو/شورت یوتیوب (youtube.com / youtu.be) یا پست/ریلز اینستاگرام (instagram.com) پذیرفته می‌شود.']);
        }
        [$content, $busy] = app(\App\Services\ManualDownloads::class)->queue($link, 'panel');
        if ($busy) {
            return redirect('/manual')->with('status', "این لینک هم‌اکنون در صف/در حال پردازش است (#{$content->id}).");
        }
        return redirect('/manual')->with('status', "لینک در صف دانلود قرار گرفت (#{$content->id}).");
    }
}
