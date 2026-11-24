<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    public function show()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $data = $request->validate(['email' => 'required|email', 'password' => 'required|string']);
        $key = 'login:'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['email' => 'تلاش زیاد؛ چند دقیقه بعد دوباره امتحان کنید.']);
        }
        if (! Auth::attempt($data, $request->boolean('remember'))) {
            RateLimiter::hit($key, 300);
            throw ValidationException::withMessages(['email' => 'ایمیل یا رمز عبور اشتباه است.']);
        }
        RateLimiter::clear($key);
        $request->session()->regenerate();
        return redirect()->intended('/');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect('/login');
    }
}
