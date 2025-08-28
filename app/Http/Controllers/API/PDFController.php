<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\PanelPanelProfile;
use App\Models\TestResult;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Log;

class PDFController extends Controller
{
    public function export()
    {
        try {
            $testResult = TestResult::with(
                [
                    'doctor',
                    'patient',
                    'testResultProfiles',
                    'testResultItems',
                    'profiles'
                ]
            )->find(5);

            // Patient information
            $patient_info = [
                'name' => $testResult->patient->name,
                'dob' => $testResult->patient->dob,
                'icno' => $testResult->patient->icno,
                'gender' => $testResult->patient->gender == 'M' ? 'Male' : 'Female',
                'age' => $testResult->patient->age . ' Years',
            ];

            // Test dates - format to dd/mm/yy and extract time
            $test_dates = [
                'collected_date' => $testResult->collected_date ? date('d/m/y', strtotime($testResult->collected_date)) : '',
                'collected_time' => $testResult->collected_date ? date('H:i', strtotime($testResult->collected_date)) : '',
                'reported_date' => $testResult->reported_date ? date('d/m/y', strtotime($testResult->reported_date)) : '',
                'reported_time' => $testResult->reported_date ? date('H:i', strtotime($testResult->reported_date)) : '',
            ];

            // Doctor information
            $doctor_info = [
                'name' => $testResult->doctor->name,
                'outlet_name' => $testResult->doctor->outlet_name,
                'outlet_address' => $testResult->doctor->outlet_address,
            ];

            // Lab information
            $lab_info = [
                'labno' => $testResult->lab_no,
                'refid' => filled($testResult->ref_id) ? $testResult->ref_id : null,
            ];

            $result = [];
            $resultItems = [];

            // Determine sequence source based on whether test result has profiles
            $hasProfiles = count($testResult->profiles) > 0;
            $panelSequences = [];

            if ($hasProfiles) {
                // Get panel sequences from PanelPanelProfile for each profile
                foreach ($testResult->profiles as $profile) {
                    $profilePanels = PanelPanelProfile::where('panel_profile_id', $profile->id)
                        ->with('panel')
                        ->orderBy('sequence')
                        ->get();

                    foreach ($profilePanels as $profilePanel) {
                        $panelSequences[$profilePanel->panel_id] = $profilePanel->sequence;
                    }
                }
            }

            foreach ($testResult->testResultItems as $ri) {
                // Always set category_name - use panel category name if available, otherwise null
                $resultItems[$ri->panel->id]['category_name'] = !is_null($ri->panel->panel_category_id)
                    ? $ri->panel->panelCategory->name
                    : null;

                // Commented out category description logic
                // if (!is_null($ri->panel->panel_category_id)) {
                //     $category_descr = '';
                //     if ($ri->panel->panelCategory->id == 4) {
                //         $category_descr = 'SPECIMEN: WHOLE BLOOD';
                //     }
                //     if ($ri->panel->panelCategory->id == 1 || $ri->panel->panelCategory->id == 2 || $ri->panel->panelCategory->id == 6 || $ri->panel->panelCategory->id == 7) {
                //         $category_descr = 'SPECIMEN: SERUM';
                //     }
                //     if ($ri->panel->panelCategory->id == 3) {
                //         $category_descr = 'SPECIMEN: BLOOD';
                //     }
                //     $resultItems[$ri->panel->id]['category_descr'] = $category_descr;
                // }

                // Use PanelPanelProfile sequence if has profiles, otherwise use Panel sequence
                $sequence = $hasProfiles && isset($panelSequences[$ri->panel->id])
                    ? $panelSequences[$ri->panel->id]
                    : $ri->panel->sequence;

                $resultItems[$ri->panel->id]['sequence'] = $sequence;
                $resultItems[$ri->panel->id]['name'] = $ri->panel->name;

                $unit = $ri->panelItem->masterPanelItem->unit;

                // If unit contains ^ followed by digits, replace with <sup>digits</sup>

                if (!empty($unit)) {
                    // Convert all *digits into <sup>digits</sup>
                    $unit = preg_replace('/\*(\d+)/', '<sup>$1</sup>', $unit);
                }

                // Determine if this is a percentage or value item
                $isPercentage = str_ends_with($ri->panelItem->masterPanelItem->name, ' %');
                $baseName = $isPercentage ? substr($ri->panelItem->masterPanelItem->name, 0, -2) : $ri->panelItem->masterPanelItem->name;

                // Initialize the group if it doesn't exist
                if (!isset($resultItems[$ri->panel->id]['items'][$baseName])) {
                    $resultItems[$ri->panel->id]['items'][$baseName] = [
                        'base_name' => $baseName,
                        'percentage' => null,
                        'value' => null,
                        'sequence' => $ri->sequence
                    ];
                }

                // Add the item data
                $itemData = [
                    'panel_item_id' => $ri->panelItem->masterPanelItem->id,
                    'panel_item_name' => $ri->panelItem->masterPanelItem->name,
                    'result_value' => $ri->value,
                    'unit' => $unit,
                    'ref_range' => !is_null($ri->reference_range_id) ? '(' . $ri->referenceRange->value . ')' : null,
                    'flag' => $ri->flag,
                    'sequence' => $ri->sequence
                ];

                if ($isPercentage) {
                    $resultItems[$ri->panel->id]['items'][$baseName]['percentage'] = $itemData;
                } else {
                    $resultItems[$ri->panel->id]['items'][$baseName]['value'] = $itemData;
                    // Use the sequence from value item for sorting
                    $resultItems[$ri->panel->id]['items'][$baseName]['sequence'] = $ri->sequence;
                }
            }

            // FORCE HAE SEQUENCE FOR ALL HAEMATOLOGY PANELS - SIMPLIFIED APPROACH
            foreach ($resultItems as $panelId => &$panel) {
                // Get panel details
                $firstItem = $testResult->testResultItems->where('panel_id', $panelId)->first();
                $panelName = $panel['name'] ?? ($firstItem ? $firstItem->panel->name : '');

                // FORCE custom sequence for panel ID 84 (HAE panel)
                if ($panelId == 84) {

                    // EXACT HAE SEQUENCE - FORCE THIS ORDER
                    $orderedItems = [];
                    $haeOrder = [
                        'Haemoglobin',
                        'Red Cell Count',
                        'Packed Cell Volume',
                        'Mean Cell Volume',
                        'Mean Cell Haemoglobin',
                        'MCHC',
                        'Red Cell Distribution Width',
                        'White Cell Count',
                        'Neutrophils',
                        'Lymphocytes',
                        'Monocytes',
                        'Eosinophils',
                        'Basophils',
                        'N:L Ratio',
                        'Platelets',
                        'E.S.R',
                        'Blood Film'
                    ];

                    // Create a lookup array of existing items
                    $itemsByName = [];
                    foreach ($panel['items'] as $item) {
                        $itemsByName[$item['base_name'] ?? ''] = $item;
                    }

                    // Build ordered array following HAE sequence
                    foreach ($haeOrder as $expectedName) {
                        if (isset($itemsByName[$expectedName])) {
                            $orderedItems[] = $itemsByName[$expectedName];
                        }
                    }

                    // Add any remaining items that weren't in our sequence
                    foreach ($panel['items'] as $item) {
                        $itemName = $item['base_name'] ?? '';
                        if (!in_array($itemName, $haeOrder) && !empty($itemName)) {
                            $orderedItems[] = $item;
                        }
                    }

                    // Replace the panel items with our ordered items
                    $panel['items'] = $orderedItems;
                } else {
                    // Default sorting logic for other panels
                    uasort($panel['items'], function ($a, $b) {
                        $aName = $a['base_name'] ?? $a['panel_item_name'] ?? '';
                        $bName = $b['base_name'] ?? $b['panel_item_name'] ?? '';

                        // If item is Platelets, put it last
                        if ($aName === 'Platelets' && $bName !== 'Platelets') {
                            return 1; // a goes after b
                        }
                        if ($bName === 'Platelets' && $aName !== 'Platelets') {
                            return -1; // a goes before b
                        }

                        // Normal sequence sorting
                        return $a['sequence'] <=> $b['sequence'];
                    });
                }

                // Convert associative array to indexed array
                $panel['items'] = array_values($panel['items']);
            }

            // Now sort panels by sequence
            usort($resultItems, fn($a, $b) => $a['sequence'] <=> $b['sequence']);

            // Get profile name (use first profile if multiple exist)
            $profile_name = $testResult->profiles->isNotEmpty() ? $testResult->profiles->first()->name : '';

            // Wrap everything in result array
            $result = [
                'patient_info' => $patient_info,
                'test_dates' => $test_dates,
                'doctor_info' => $doctor_info,
                'lab_info' => $lab_info,
                'profile_name' => $profile_name,
                'resultItems' => $resultItems
            ];

            // return response()->json($result);
            // exit;

            $pdf = Pdf::loadView('pdf.dummy', compact('result'));

            // Return PDF as binary stream for Postman preview
            return response($pdf->output(), 200)
                ->header('Content-Type', 'application/pdf')
                ->header('Content-Disposition', 'inline; filename="dummy.pdf"');

            // Base64 return (commented out for now)
            // $pdfContent = $pdf->output();
            // $base64Pdf = base64_encode($pdfContent);
            // return response()->json([
            //     'success' => true,
            //     'message' => 'PDF generated successfully',
            //     'data' => [
            //         'test_result_id' => $testResult->id,
            //         'pdf_base64' => $base64Pdf,
            //         'generated_at' => now()->toISOString()
            //     ]
            // ]);

        } catch (\Exception $e) {
            Log::info([
                'success' => false,
                'message' => 'Failed to generate PDF',
                'error'   => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
                'trace'   => $e->getTraceAsString(), // full stack trace
            ]);
            return response()->json(['success' => false, 'message' => 'Failed to generate PDF', 'error' => $e->getMessage()], 500);
        }
    }
}