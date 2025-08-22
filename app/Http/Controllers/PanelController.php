<?php

namespace App\Http\Controllers;

use App\Models\Panel;
use Illuminate\Http\Request;

class PanelController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $user_name = session()->get('username');
        $lab_id = session()->get('lab_id');
        
        $panels = Panel::with([
            'lab',
            'panelItems'
        ])
        ->withCount(['panelItems as panel_items_count'])
        ->get();
        
        return view('panels.index', compact('user_name', 'lab_id', 'panels'));
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $panel = Panel::with([
            'lab',
            'panelItems',
            'panelTags'
        ])->findOrFail($id);

        return view('panels.show', compact('panel'));
    }
}