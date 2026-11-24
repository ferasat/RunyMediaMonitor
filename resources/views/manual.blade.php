@extends('layouts.app')
@if($busy)
    @push('head')<meta http-equiv="refresh" content="10">@endpush
@endif
@section('content')
<div class="card">
    <h3>دانلود با لینک</h3>
    <form method="post" action="/manual">
        @csrf
        <input type="url" name="url" value="{{ old('url') }}" required style="width:70%"
               placeholder="لینک ویدیو/شورت یوتیوب یا پست/ریلز اینستاگرام">
        <button>دانلود و ارسال به بله</button>
    </form>
    <small>کیفیت: ۷۲۰ ← ۴۸۰ ← ۳۶۰ ← ۲۴۰ ← بهترین موجود. فایل تا {{ config('rmm.bale.max_upload_mb') }} مگابایت خودِ فایل + لینک، بزرگ‌تر فقط لینک. لینک‌ها {{ config('rmm.link_days') }} روز معتبرند. درخواست‌ها در صف و یکی‌یکی انجام می‌شوند.</small>
</div>
<div class="card">
    <h3>تاریخچه دانلودهای دستی</h3>
    @if($busy)<small>⏳ در حال پردازش… صفحه هر ۱۰ ثانیه به‌روز می‌شود.</small>@endif
    <table>
        <tr><th>#</th><th>پلتفرم</th><th>لینک / عنوان</th><th>وضعیت</th><th>فایل‌ها (لینک دانلود)</th><th>زمان</th><th></th></tr>
        @foreach($items as $c)
        @php
            $labels = ['pending' => 'در صف', 'downloading' => 'در حال دانلود', 'downloaded' => 'در حال ارسال', 'sent' => 'ارسال شد', 'failed' => 'ناموفق', 'expired' => 'منقضی'];
        @endphp
        <tr>
            <td>{{ $c->id }}</td>
            <td>{{ $c->source->type }}{{ $c->is_short ? ' (Shorts)' : '' }}</td>
            <td><a href="{{ $c->canonical_url }}" target="_blank" rel="noopener">{{ \Illuminate\Support\Str::limit($c->title ?: $c->body ?: $c->canonical_url, 80) }}</a>
                @if($c->status === 'failed' && $c->error_message)<br><small style="color:#b91c1c">{{ \Illuminate\Support\Str::limit($c->error_message, 220) }}</small>@endif</td>
            <td><span class="badge s-{{ $c->status }}">{{ $labels[$c->status] ?? $c->status }}</span></td>
            <td>
                @forelse($c->files as $f)
                    @if($f->isAvailable())
                        <a href="{{ $f->downloadUrl() }}">⬇️ {{ $f->kind }} {{ $f->sizeMb() }}MB</a>
                        <small>{{ $f->delivery === 'link' ? '(در بله فقط لینک)' : ($f->delivery ? '(فایل + لینک در بله)' : '') }}</small>
                    @else
                        <small>{{ $f->kind }} (منقضی)</small>
                    @endif
                    <br>
                @empty
                    <small>—</small>
                @endforelse
            </td>
            <td><small>{{ $c->updated_at?->timezone(config('rmm.timezone'))->format('Y-m-d H:i') }}</small></td>
            <td><form method="post" action="/contents/{{ $c->id }}/send" class="inline">@csrf <button class="gray">دوباره</button></form></td>
        </tr>
        @endforeach
    </table>
    {{ $items->links() }}
</div>
@endsection
