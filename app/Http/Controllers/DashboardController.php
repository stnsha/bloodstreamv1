<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function index()
    {
        $user_name = Auth::user()->name;
        $lab_id = session()->get('lab_id');
        return view('dashboard', compact('user_name', 'lab_id'));
    }
}
