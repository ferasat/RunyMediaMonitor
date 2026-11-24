@extends('layouts.app')
@section('content')
<div class="card">
    <h3>افزودن منبع</h3>
    <form method="post" action="/sources">
        @csrf
        <input type="url" name="url" placeholder="https://www.youtube.com/@channel یا https://www.instagram.com/page/" required style="width:45%">
        <input type="text" name="name" placeholder="نام (اختیاری)">
        <select name="send_latest" title="در اولین اسکن، چند پست آخر هم ارسال شود؟">
            <option value="0">اسکن اول: فقط علامت‌گذاری (بدون ارسال)</option>
            <option value="1">اسکن اول: ارسال ۱ پست آخر</option>
            <option value="2">اسکن اول: ارسال ۲ پست آخر</option>
        </select>
        <button>افزودن</button>
    </form>
    <small>بررسی خودکار: هر روز ساعت {{ implode(' و ', config('rmm.check_times')) }} ({{ config('rmm.timezone') }}) — {{ config('rmm.pages') }} صفحه آخر هر منبع (ویدیوها + شورت‌ها / پست‌ها + ریلز). فایل‌ها {{ config('rmm.retention_days') }} روز نگهداری می‌شوند.</small>
</div>

<div class="card">
    <h3>منابع ({{ $sources->count() }})</h3>
    <table>
        <tr><th>#</th><th>نوع</th><th>نام / لینک</th><th>آخرین بررسی</th><th>تعداد</th><th>وضعیت</th><th></th></tr>
        @foreach($sources as $s)
        <tr>
            <td>{{ $s->id }}</td>
            <td>{{ $s->type }}</td>
            <td>{{ $s->name }}<br><small><a href="{{ $s->url }}" target="_blank" rel="noopener">{{ $s->url }}</a></small>
                @if($s->last_error)<br><small style="color:#b91c1c">{{ \Illuminate\Support\Str::limit($s->last_error, 160) }}</small>@endif</td>
            <td><small>{{ $s->last_checked_at?->timezone(config('rmm.timezone'))->format('Y-m-d H:i') ?? '—' }}</small></td>
            <td>{{ $s->contents_count }}</td>
            <td>{!! $s->is_active ? '<span class="badge s-sent">فعال</span>' : '<span class="badge">غیرفعال</span>' !!}</td>
            <td>
                <form method="post" action="/sources/{{ $s->id }}/check" class="inline">@csrf <button>بررسی الان</button></form>
                <form method="post" action="/sources/{{ $s->id }}/toggle" class="inline">@csrf <button class="gray">{{ $s->is_active ? 'توقف' : 'فعال‌سازی' }}</button></form>
                <form method="post" action="/sources/{{ $s->id }}" class="inline" onsubmit="return confirm('حذف شود؟')">@csrf @method('DELETE') <button class="red">حذف</button></form>
            </td>
        </tr>
        @endforeach
    </table>
</div>

<div class="card">
    <h3>پست‌های دریافتی (۱۰۰ مورد آخر)</h3>
    <small>{{ $seenCount }} پست قدیمی در اسکن اولیه فقط علامت‌گذاری شده‌اند.</small>
    <table>
        <tr><th>#</th><th>منبع</th><th>عنوان</th><th>وضعیت</th><th>فایل‌ها</th><th></th></tr>
        @foreach($contents as $c)
        <tr>
            <td>{{ $c->id }}</td>
            <td>{{ $c->source?->name }}{{ $c->is_short ? ' (Shorts)' : '' }}</td>
            <td><a href="{{ $c->canonical_url }}" target="_blank" rel="noopener">{{ \Illuminate\Support\Str::limit($c->title ?: $c->body ?: $c->external_id, 80) }}</a>
                @if($c->error_message)<br><small style="color:#b91c1c">{{ \Illuminate\Support\Str::limit($c->error_message, 160) }}</small>@endif</td>
            <td><span class="badge s-{{ $c->status }}">{{ $c->status }}</span><br><small>{{ $c->sent_at?->timezone(config('rmm.timezone'))->format('m-d H:i') }}</small></td>
            <td>
                @foreach($c->files as $f)
                    @if($f->isAvailable())
                        <a href="{{ $f->downloadUrl() }}">{{ $f->kind }} {{ $f->sizeMb() }}MB</a>
                    @else
                        <small>{{ $f->kind }} (منقضی)</small>
                    @endif
                    <br>
                @endforeach
            </td>
            <td><form method="post" action="/contents/{{ $c->id }}/send" class="inline">@csrf <button class="gray">ارسال مجدد</button></form></td>
        </tr>
        @endforeach
    </table>
</div>
@endsection
