<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\TestResult;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;

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
                    'testResultItems'
                ]
            )->find(1);

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

            // if (count($testResult->profiles) != 0) {
            //     dd($testResult->profiles);
            // }

            foreach ($testResult->testResultItems as $ri) {
                if (!is_null($ri->panel->panel_category_id)) {
                    $category_descr = '';
                    if ($ri->panel->panelCategory->id == 4) {
                        $category_descr = 'SPECIMEN: WHOLE BLOOD';
                    }

                    if ($ri->panel->panelCategory->id == 1 || $ri->panel->panelCategory->id == 2 || $ri->panel->panelCategory->id == 6 || $ri->panel->panelCategory->id == 7) {
                        $category_descr = 'SPECIMEN: SERUM';
                    }

                    if ($ri->panel->panelCategory->id == 3) {
                        $category_descr = 'SPECIMEN: BLOOD';
                    }

                    $resultItems[$ri->panel->id]['category_name'] = $ri->panel->panelCategory->name;
                    $resultItems[$ri->panel->id]['category_descr'] = $category_descr;
                }

                $resultItems[$ri->panel->id]['sequence'] = $ri->panel->sequence;
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

            // Sort grouped items within each panel by sequence, with special handling for Platelets
            foreach ($resultItems as &$panel) {
                uasort($panel['items'], function($a, $b) {
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
                // Convert associative array to indexed array
                $panel['items'] = array_values($panel['items']);
            }

            // Now sort panels by sequence
            usort($resultItems, fn($a, $b) => $a['sequence'] <=> $b['sequence']);

            // Wrap everything in result array
            $result = [
                'patient_info' => $patient_info,
                'test_dates' => $test_dates,
                'doctor_info' => $doctor_info,
                'lab_info' => $lab_info,
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
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate PDF',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}