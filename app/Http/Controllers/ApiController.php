<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ApiController extends Controller
{
    /**
     * Display the API documentation page.
     */
    public function index()
    {
        $lab_id = session()->get('lab_id');
        
        return view('apis.index', compact('lab_id'));
    }
}