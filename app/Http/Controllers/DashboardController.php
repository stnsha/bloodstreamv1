<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function index()
    {
        // dd(Auth::guard('web')->check(), Auth::guard('lab')->check());

        $user_name = Auth::user()->name;
        return view('dashboard', compact('user_name'));
    }
}
