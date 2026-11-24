<?php

namespace App\Http\Controllers;

use App\Jobs\DeliverContentJob;
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
        $source = Source::manualFor($link->platform);
        $content = Content::withTrashed()->firstOrNew(['source_id' => $source->id, 'external_id' => $link->id]);
        $content->fill([
            'canonical_url' => $link->url,
            'content_hash' => hash('sha256', $link->id.'|'.$link->url),
            'media_type' => $link->platform === 'instagram' ? ($link->kind === 'reel' ? 'video' : 'unknown') : 'video',
            'is_short' => $link->isShort,
            'status' => 'pending',
            'attempts' => 0,
            'error_message' => null,
            'metadata' => array_merge($content->metadata ?? [], ['manual' => true, 'requested_at' => now()->toIso8601String()]),
        ]);
        $content->deleted_at = null;
        $content->save();
        $content->touch();
        DeliverContentJob::dispatch($content->fresh('source'));
        return redirect('/manual')->with('status', "لینک در صف دانلود قرار گرفت (#{$content->id}).");
    }
}
