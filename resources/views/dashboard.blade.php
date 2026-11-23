<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>RunyMediaMonitor — داشبورد</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-4">
    <h1 class="mb-4">RunyMediaMonitor</h1>
    <p class="text-muted">سیستم پایش شبکه‌های اجتماعی و وب</p>

    <div class="card mb-4">
        <div class="card-body">
            <h5>افزودن منبع جدید</h5>
            <form method="POST" action="/sources" class="row g-2">
                @csrf
                <div class="col-md-3"><input name="type" class="form-control" placeholder="نوع (website/youtube/...)" required></div>
                <div class="col-md-3"><input name="name" class="form-control" placeholder="نام" required></div>
                <div class="col-md-4"><input name="url" class="form-control" placeholder="URL / Feed"></div>
                <div class="col-md-2"><button class="btn btn-primary w-100">افزودن</button></div>
            </form>
        </div>
    </div>

    <h3>منابع ({{ $sources->count() }})</h3>
    <table class="table table-striped mb-4">
        <thead><tr><th>نام</th><th>نوع</th><th>URL</th><th>فعال</th><th>آخرین بررسی</th></tr></thead>
        <tbody>
        @foreach($sources as $s)
            <tr>
                <td>{{ $s->name }}</td>
                <td>{{ $s->type }}</td>
                <td>{{ $s->url }}</td>
                <td>{{ $s->is_active ? 'بله' : 'خیر' }}</td>
                <td>{{ $s->last_checked_at }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>

    <h3>محتوای رصد شده ({{ $contents->count() }})</h3>
    <table class="table table-striped">
        <thead><tr><th>عنوان</th><th>منبع</th><th>نوع</th><th>انتشار</th><th>لینک</th></tr></thead>
        <tbody>
        @foreach($contents as $c)
            <tr>
                <td>{{ $c->title }}</td>
                <td>{{ $c->source->name ?? '-' }}</td>
                <td>{{ $c->media_type }}</td>
                <td>{{ $c->published_at }}</td>
                <td>
                    @if($c->media_url)<a href="{{ $c->media_url }}" download class="btn btn-sm btn-success">دانلود</a>@endif
                    @if($c->canonical_url)<a href="{{ $c->canonical_url }}" target="_blank" class="btn btn-sm btn-outline-secondary">باز کردن</a>@endif
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
</body>
</html>