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
        
        // Process statistics
        $stats = [
            'totalResults' => $testResults->count(),
            'totalPatients' => $testResults->pluck('patient')->unique('id')->count(),
            'totalLabs' => $testResults->pluck('doctor.lab')->unique('id')->count()
        ];
        
        // Process each test result for display
        $processedResults = $testResults->map(function ($result) {
            // Process doctor info
            $doctorInfo = [
                'labName' => $result->doctor->lab->name,
                'doctorName' => $result->doctor->name,
                'doctorCode' => $result->doctor->code,
                'outletName' => $result->doctor->outlet_name
            ];
            
            // Process patient info
            $patientInfo = [
                'name' => $result->patient->name,
                'age' => $result->patient->age,
                'gender' => $result->patient->gender == 'F' ? 'Female' : 'Male',
                'initial' => substr($result->patient->name, 0, 1)
            ];
            
            // Process profiles with codes
            $profiles = $result->profiles->map(function ($profile) {
                return [
                    'id' => $profile->id,
                    'name' => $profile->name,
                    'code' => $profile->code
                ];
            });
            
            // Process test result items grouped by panel
            $panelGroups = [];
            $groupedItems = $result->testResultItems->groupBy('panel.name');
            
            foreach ($groupedItems as $panelName => $items) {
                // Check if any item in this panel has is_tagon = true
                $hasTagOn = $items->contains('is_tagon', true);
                $displayName = $panelName;

                if ($hasTagOn) {
                    // Get the first item with is_tagon = true to access its panel tag
                    $tagOnItem = $items->first(function ($item) {
                        return $item->is_tagon;
                    });
                    if ($tagOnItem && $tagOnItem->panel && $tagOnItem->panel->panelTags->isNotEmpty()) {
                        $displayName = $tagOnItem->panel->panelTags->first()->name;
                    }
                }
                
                // Process items in this panel
                $processedItems = $items->map(function ($item) {
                    return [
                        'name' => $item->panelItem->name,
                        'value' => $item->value,
                        'unit' => $item->panelItem->unit,
                        'referenceRange' => $item->referenceRange ? $item->referenceRange->value : null
                    ];
                });
                
                $panelGroups[] = [
                    'panelName' => $panelName,
                    'displayName' => $displayName,
                    'hasTagOn' => $hasTagOn,
                    'items' => $processedItems
                ];
            }
            
            return [
                'id' => $result->id,
                'labNo' => $result->lab_no,
                'refId' => $result->ref_id,
                'isCompleted' => $result->is_completed,
                'isTagOn' => $result->is_tagon,
                'doctorInfo' => $doctorInfo,
                'patientInfo' => $patientInfo,
                'profiles' => $profiles,
                'panelGroups' => $panelGroups,
                'searchData' => [
                    'patientName' => strtolower($result->patient->name),
                    'labNo' => strtolower($result->lab_no),
                    'refId' => strtolower($result->ref_id ?? '')
                ]
            ];
        });
        
        return view('results.index', compact('user_name', 'lab_id', 'processedResults', 'stats'));
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
            'testResultProfiles',
            'testResultItems.panelItem',
            'testResultItems.panel',
            'testResultItems.panel.panelTags',
            'testResultItems.referenceRange'
        ])->findOrFail($id);

        // Group test result items by profile, then by panel
        $profileResults = [];
        
        if ($testResult->profiles->isNotEmpty()) {
            // Process each profile
            foreach ($testResult->profiles as $profile) {
                // Get test result items that belong to panels associated with this profile
                $profileItems = $testResult->testResultItems->filter(function ($item) use ($testResult, $profile) {
                    $panel = $item->panel;
                    if (!$panel) {
                        return false;
                    }
                    
                    // Find if this panel is used in this profile through TestResultProfile
                    return $testResult->testResultProfiles
                        ->where('panel_profile_id', $profile->id)
                        ->isNotEmpty();
                });
                
                if ($profileItems->isNotEmpty()) {
                    // Group items by panel within this profile
                    $panelGroups = [];
                    $groupedItems = $profileItems->groupBy('panel.name');
                    
                    foreach ($groupedItems as $panelName => $items) {
                        // Check if any item in this panel has is_tagon = true
                        $hasTagOn = $items->contains('is_tagon', true);
                        $displayName = $panelName;

                        if ($hasTagOn) {
                            // Get the first item with is_tagon = true to access its panel tag
                            $tagOnItem = $items->first(function ($item) {
                                return $item->is_tagon;
                            });
                            if ($tagOnItem && $tagOnItem->panel && $tagOnItem->panel->panelTags->isNotEmpty()) {
                                $displayName = $tagOnItem->panel->panelTags->first()->name;
                            }
                        }
                        
                        $panelGroups[] = [
                            'panelName' => $panelName,
                            'items' => $items,
                            'hasTagOn' => $hasTagOn,
                            'displayName' => $displayName,
                            'itemCount' => count($items)
                        ];
                    }
                    
                    $profileResults[] = [
                        'profile' => $profile,
                        'panelGroups' => $panelGroups,
                        'totalItems' => count($profileItems)
                    ];
                }
            }
        } else {
            // Handle case where there are no profiles - just group all items by panel
            if ($testResult->testResultItems->isNotEmpty()) {
                $panelGroups = [];
                $groupedItems = $testResult->testResultItems->groupBy('panel.name');
                
                foreach ($groupedItems as $panelName => $items) {
                    // Check if any item in this panel has is_tagon = true
                    $hasTagOn = $items->contains('is_tagon', true);
                    $displayName = $panelName;

                    if ($hasTagOn) {
                        // Get the first item with is_tagon = true to access its panel tag
                        $tagOnItem = $items->first(function ($item) {
                            return $item->is_tagon;
                        });
                        if ($tagOnItem && $tagOnItem->panel && $tagOnItem->panel->panelTags->isNotEmpty()) {
                            $displayName = $tagOnItem->panel->panelTags->first()->name;
                        }
                    }
                    
                    $panelGroups[] = [
                        'panelName' => $panelName,
                        'items' => $items,
                        'hasTagOn' => $hasTagOn,
                        'displayName' => $displayName,
                        'itemCount' => count($items)
                    ];
                }
                
                // Create a single profile result with no profile but with all panels
                $profileResults[] = [
                    'profile' => null,
                    'panelGroups' => $panelGroups,
                    'totalItems' => count($testResult->testResultItems)
                ];
            }
        }

        return view('results.show', compact('testResult', 'profileResults'));
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