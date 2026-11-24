@extends('layouts.app')
@section('content')
<div class="card" style="max-width:380px;margin:60px auto">
    <h3>ورود مدیر</h3>
    <form method="post" action="/login">
        @csrf
        <p><input type="email" name="email" value="{{ old('email') }}" placeholder="ایمیل" required style="width:93%"></p>
        <p><input type="password" name="password" placeholder="رمز عبور" required style="width:93%"></p>
        <p><label><input type="checkbox" name="remember" value="1"> مرا به خاطر بسپار</label></p>
        <button>ورود</button>
    </form>
</div>
@endsection
