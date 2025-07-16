<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    public function index()
    {
        return view('welcome');
    }

    public function login(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ], [
            'email.required' => 'Email is required.',
            'password.required' => 'Password is required.',
        ]);

        if ($validated) {
            $credentials = $request->only('email', 'password');

            if (Auth::attempt($credentials)) {
                return redirect()->intended('dashboard');
            }
        }
        return back()->withErrors(['login' => 'Invalid email or password. Please contact admin.']);
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
