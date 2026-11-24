<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>رصدیار — RunyMediaMonitor</title>
    @stack('head')
    <style>
        body{font-family:Tahoma,Vazirmatn,sans-serif;background:#f4f6fa;margin:0;color:#1f2937}
        header{background:#1e3a8a;color:#fff;padding:12px 20px;display:flex;justify-content:space-between;align-items:center}
        main{max-width:1150px;margin:20px auto;padding:0 12px}
        .card{background:#fff;border-radius:10px;box-shadow:0 1px 4px rgba(0,0,0,.08);padding:16px;margin-bottom:18px}
        table{width:100%;border-collapse:collapse;font-size:13px}
        th,td{border-bottom:1px solid #e5e7eb;padding:7px;text-align:right;vertical-align:top}
        input,select,button{font-family:inherit;font-size:14px;padding:7px 10px;border:1px solid #cbd5e1;border-radius:6px}
        button{background:#2563eb;color:#fff;border:0;cursor:pointer}
        button.gray{background:#64748b} button.red{background:#dc2626}
        .ok{background:#dcfce7;color:#166534;padding:10px;border-radius:6px;margin-bottom:12px}
        .err{background:#fee2e2;color:#991b1b;padding:10px;border-radius:6px;margin-bottom:12px}
        .badge{display:inline-block;padding:2px 7px;border-radius:10px;font-size:12px;background:#e2e8f0}
        .s-sent{background:#dcfce7}.s-failed{background:#fee2e2}.s-pending,.s-downloading{background:#fef9c3}
        form.inline{display:inline}
        a{color:#1d4ed8}
        small{color:#64748b}
    </style>
</head>
<body>
<header>
    <strong>رصدیار — پایش یوتیوب و اینستاگرام</strong>
    @auth
        <nav>
            <a href="/" style="color:#fff;margin-left:14px">منابع و پایش</a>
            <a href="/manual" style="color:#fff;margin-left:14px">دانلود با لینک</a>
            <form method="post" action="/logout" class="inline">@csrf <button class="gray">خروج</button></form>
        </nav>
    @endauth
</header>
<main>
    @if(session('status'))<div class="ok">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="err">{{ $errors->first() }}</div>@endif
    @yield('content')
</main>
</body>
</html>
