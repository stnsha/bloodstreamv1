<?php

namespace App\Http\Controllers;

use App\Models\TestResult;
use Illuminate\Http\Request;

class TestResultController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $user_name = session()->get('username');
        $lab_id = session()->get('lab_id');
        
        $testResults = TestResult::with([
            'patient',
            'doctor',
            'doctor.lab',
            'profiles',
            'testResultItems.panelItem',
            'testResultItems.panel',
            'testResultItems.panel.panelTags',
            'testResultItems.referenceRange'
        ])->get();
        
        return view('results.index', compact('user_name', 'lab_id', 'testResults'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $testResult = TestResult::with([
            'patient',
            'doctor',
            'profiles',
            'testResultItems.panelItem',
            'testResultItems.panel',
            'testResultItems.panel.panelTags',
            'testResultItems.referenceRange'
        ])->findOrFail($id);

        return view('results.show', compact('testResult'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}