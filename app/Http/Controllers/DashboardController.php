<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function index()
    {
        $user_name = Auth::user()->name;
        $lab_id = Auth::user()->credential ? Auth::user()->credential->lab_id : null;
        return view('dashboard', compact('user_name', 'lab_id'));
    }
}
