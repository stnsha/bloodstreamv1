<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\LabCredential;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class LoginController extends Controller
{
    public function index()
    {
        return view('welcome');
    }

    public function login(Request $request)
    {
        $validated = $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ], [
            'username.required' => 'Username is required.',
            'password.required' => 'Password is required.',
        ]);

        if ($validated) {
            $credentials = $request->only('username', 'password');
            $username = $request->input('username');

            // If it's not an email, search for username in lab credentials using the relationship
            $labCredential = LabCredential::where('username', $username)->first();

            if ($labCredential && Hash::check($credentials['password'], $labCredential->password)) {
                // Get the associated user through the relationship
                $user = User::find($labCredential->user_id);
                if ($user) {
                    // Login the user
                    Auth::login($user);
                    session()->put('lab_id', $labCredential->lab_id);
                    session()->put('username', $labCredential->username);
                    return redirect()->route('apis.index');
                }
            }
        }

        return back()->withErrors(['login' => 'Invalid username or password. Please contact admin.']);
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}