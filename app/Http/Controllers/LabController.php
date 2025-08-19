<?php

namespace App\Http\Controllers;

use App\Models\Lab;
use Illuminate\Http\Request;

class LabController extends Controller
{
    public function index()
    {
        $user_name = session()->get('username');
        $lab_id = session()->get('lab_id');

        if ($lab_id != 1) {
            abort(403, 'Unauthorized');
        }

        $labs = Lab::all();

        return view('labs.index', compact('labs'));
    }
}