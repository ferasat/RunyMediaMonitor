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
    <h3>منابع ({{ $sources->count() }})</h3>
    <table class="table table-striped">
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
</div>
</body>
</html>