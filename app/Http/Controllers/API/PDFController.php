<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\PanelPanelProfile;
use App\Models\TestResult;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Log;
use Mpdf\Mpdf;

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

    public function generateDummyPDF()
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
            )->find(4);

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

            $mpdf = new Mpdf([
                'mode' => 'utf-8',
                'format' => 'A4',
                // 'margin_left' => 40,
                // 'margin_right' => 38,
                'margin_top' => 70,
                // 'margin_bottom' => 30,
                // 'margin_header' => 10,
                // 'margin_footer' => 10
                'default_font' => 'Arial',
            ]);

            $header = '
            <div style="text-align: center; width: 100%;margin-bottom:10px;">
                <img src="img/innoquest.png" style="width:100%;" />
            </div>
                    <table style="width:100%; border-collapse:collapse; font-size:11px; padding:0px;text-align:left;">
                        <tr>
                            <!-- Patient Details -->
                            <td style="vertical-align:top; width:60%;">
                                <table style="width:100%; border-collapse:collapse; font-size:11px; padding:0;">
                                    <tr>
                                        <td colspan="6" style="font-style:light;font-weight:bold; padding:0px 0px 10px 0px;">Patient Details</td>
                                    </tr>
                                    <tr>
                                        <td style="width:63px;padding:0;">Name</td>
                                        <td style="width:5px;padding:0;">:</td>
                                        <td colspan="4" style="width:120px;padding:0;">' . strtoupper($result['patient_info']['name']) . '</td>
                                        <td style="width:50px;padding:0;"></td>
                                        <td style="width:5px;padding:0;"></td>
                                        <td style="padding:0;"></td>
                                    </tr>
                                    <tr>
                                        <td style="padding:0;">UR</td>
                                        <td style="padding:0;">:</td>
                                        <td style="padding:0;">UR001234</td>
                                        <td style="padding:0;"></td>
                                    </tr>
                                    <tr>
                                        <td style="padding:0;">Ref</td>
                                        <td style="padding:0;">:</td>
                                        <td style="padding:0;">REF789456</td>
                                        <td style="padding:0;"></td>
                                    </tr>
                                    <tr>
                                        <td style="padding:10px 0 0 0;">DOB</td>
                                        <td style="padding:10px 0 0 0;">:</td>
                                        <td style="padding:10px 0 0 0;">' . $result['patient_info']['dob'] . '</td>
                                        <td style="padding:10px 0 0 0;">Sex</td>
                                        <td style="padding:10px 0 0 0;">:</td>
                                        <td style="padding:10px 0 0 0;">' . $result['patient_info']['gender'] . '</td>
                                    </tr>
                                    <tr>
                                        <td style="padding:0;">IC NO.</td>
                                        <td style="padding:0;">:</td>
                                        <td style="padding:0;">' . $result['patient_info']['icno'] . '</td>
                                        <td style="padding:0;">Age</td>
                                        <td style="padding:0;">:</td>
                                        <td style="padding:0;">' . $result['patient_info']['age'] . '</td>
                                    </tr>
                                    <tr>
                                        <td style="padding:0;">Collected</td>
                                        <td style="padding:0;">:</td>
                                        <td style="padding:0;">' . $result['test_dates']['collected_date'] . ' ' . $result['test_dates']['collected_time'] . '</td>
                                        <td style="padding:0;">Ward</td>
                                        <td style="padding:0;">:</td>
                                        <td style="padding:0;">General</td>
                                    </tr>
                                    <tr>
                                        <td style="padding:0;">Referred</td>
                                        <td style="padding:0;">:</td>
                                        <td style="padding:0;">' . $result['test_dates']['reported_date'] . '</td>
                                        <td style="padding:0;">Yr Ref.</td>
                                        <td style="padding:0;">:</td>
                                        <td style="padding:0;">DR001</td>
                                    </tr>
                                </table>
                            </td>

                            <!-- Doctor Details -->
                            <td style="vertical-align:top; width:45%; padding-left:10px;">
                                <table style="width:70%; border-collapse:collapse; font-size:11px; padding:0;">
                                    <tr>
                                        <td colspan="3" style="font-style:light;font-weight:bold; padding:0;">Doctor Details</td>
                                    </tr>
                                    <tr>
                                        <td colspan="3" style="padding:10px 0px 0px 0px;text-transform:uppercase;">
' . strtoupper($result['doctor_info']['name']) . '<br>
                                            ' . strtoupper($result['doctor_info']['outlet_name']) . '
                                        </td>
                                    </tr>
                                    <tr>
                                        <td colspan="3" style="padding:0; word-wrap:break-word; max-width:80px;text-transform:uppercase;">
' . strtoupper($result['doctor_info']['outlet_address']) . '
                                        </td>
                                    </tr>
                                    <tr>
                                        <td colspan="3" style="padding:0px 0px 3px 0px;"></td>
                                    </tr>
                                    <tr>
                                        <td style="width:80px; padding:0px 0px 3px 0px;">Lab No.</td>
                                        <td style="width:5px; padding:0px 0px 3px 0px;">:</td>
                                        <td style="padding:0px 0px 3px 0px;">' . $result['lab_info']['labno'] . '</td>
                                    </tr>
                                    <tr>
                                        <td style="padding:0px 0px 3px 0px;">Courier Run</td>
                                        <td style="padding:0px 0px 3px 0px;">:</td>
                                        <td style="padding:0px 0px 3px 0px;">CR-001</td>
                                    </tr>
                                    <tr>
                                        <td style="padding:0px 0px 3px 0px;">Report Printed</td>
                                        <td style="padding:0px 0px 3px 0px;">:</td>
                                        <td style="padding:0px 0px 3px 0px;">' . date('d/m/Y H:i') . '</td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                    </table>';

            $footer = '
             <table style="width:100%">
            <tr>
                <td style="width:450px;">
                    <table style="border-collapse:collapse; width:100%;">
                        <!-- First row: Logo + Text (1 column) + QR beside -->
                        <tr>
                            <!-- Left column: Logo + Text -->
                            <td style="width:80px; text-align:center; vertical-align:middle; padding-right:15px;">
                                <img src="img/smm.png" style="width:70px; display:block; margin:0 auto;">
                                <div style="font-size:8px; margin-top:2px;">SAMM MT 319</div>
                            </td>

                            <!-- Right column: QR Code -->
                            <td style="text-align:left; vertical-align:middle;">
                                <img src="img/qrleft.png" style="width:55px;">
                            </td>
                        </tr>

                        <!-- Second row: Bold accreditation text -->
                        <tr>
                            <td colspan="2" style="font-size:9px; font-weight:bold; padding-top:6px;">
                                Innoquest Pathology Sdn. Bhd. is a full scope CAP and ISO15189 accredited laboratory.
                            </td>
                        </tr>

                        <!-- Third row: Lighter subtext -->
                        <tr>
                            <td colspan="2"
                                style="font-family: Arial, sans-serif; font-weight: 400;font-size:9px;color:#555;">
                                Few assays may be pending ISO15189 accreditation due to their recent launch. Scan our QR
                                to see the full list.
                            </td>
                        </tr>
                    </table>
                </td>

                <td style="vertical-align:top;">
                    <table style="border-collapse:collapse; text-align:center; width:100%;">
                        <tr>
                            <td style="vertical-align:top; text-align:left;">
                                <img src="img/qrright.png" style="width:55px; display:block;">
                            </td>
                            <td style="vertical-align:middle; text-align:center; padding-left:10px;">
                                <div style="font-size:8px; font-weight:normal;">
                                    Page {PAGENO} of {nb}
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td colspan="2"
                                style="font-size:9.5px; padding-top:9px; text-align:left;font-weight: 400;">
                                Please scan here to view test methodology or contact our customer care line for
                                assistance.
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>

        </table>';

            $mpdf->SetHTMLHeader($header);
            $mpdf->SetHTMLFooter($footer);

            $content = '
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset="utf-8">
                <title>Dummy PDF Report</title>
                <style>
                    body, p {
                        margin: 0;
                        padding: 0;
                        font-stretch: expanded;
                    }
                    sup {
                        font-size: 8px;
                        vertical-align: super;
                    }
                    .page-break {
                        page-break-before: always;
                    }
                    .force-page-break {
                        page-break-before: always;
                        height: 0;
                        margin: 0;
                        padding: 0;
                    }
                </style>
            </head>
            <body>
                <div class="content">
                    
                    <table style="width: 100%; border-collapse: collapse; font-size:11.5px; margin-top:15px; text-align:left;">
                        <thead>
                            <tr>
                                <th style="padding:0px 0px 0px 15px; text-align:left; text-transform:uppercase;width:400px; border-top:1px solid #000; border-bottom:1px solid #000;">Analytes</th>
                                <th style="padding:0px 0px 3px 0px; text-align:left; text-transform:uppercase;width:60px; border-top:1px solid #000; border-bottom:1px solid #000;">Results</th>
                                <th style="padding:0px 0px 3px 0px; text-align:left; text-transform:uppercase; border-top:1px solid #000; border-bottom:1px solid #000;">Units</th>
                                <th style="padding:0px 0px 3px 0px; text-align:left; text-transform:uppercase; border-top:1px solid #000; border-bottom:1px solid #000;">Ref. Ranges</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td colspan="4" style="padding: 5px 0px;font-style:light;font-weight:bold;text-decoration:underline;">
' . strtoupper($result['profile_name']) . '
                                </td>
                            </tr>
                            
';

            // Dynamic loop through result items with smart page breaks
            $currentRowCount = 5; // Start counting from initial rows (patient info, etc.)
            $maxRowsPerPage = 25; // Approximate rows that fit before footer

            foreach ($result['resultItems'] as $panelIndex => $panel) {

                // Calculate approximate rows this category will need
                $categoryRows = 2; // Category header + separator
                $categoryRows += count($panel['items']); // One row per item
                // Add extra rows for Blood Film items (they take more space)
                foreach ($panel['items'] as $item) {
                    if ($item['base_name'] === 'Blood Film') {
                        $categoryRows += 2; // Blood film takes extra space
                    }
                }

                // Check if this category would overflow the page
                if ($currentRowCount + $categoryRows > $maxRowsPerPage && $panelIndex > 0) {
                    // Close current table and force page break
                    $content .= '
                        </tbody>
                    </table>
                </div>
                
                <!-- Force Page Break -->
                <div class="force-page-break"></div>
                
                <!-- Start New Page -->
                <div class="content">
                    <table style="width: 100%; border-collapse: collapse; font-size:11.5px; margin-top:92px; text-align:left;">
                        <thead>
                            <tr>
                                <th style="padding:0px 0px 0px 15px; text-align:left; text-transform:uppercase;width:386px; border-top:1px solid #000; border-bottom:1px solid #000;">Analytes</th>
                                <th style="padding:0px 0px 3px 0px; text-align:left; text-transform:uppercase;width:60px; border-top:1px solid #000; border-bottom:1px solid #000;">Results</th>
                                <th style="padding:0px 0px 3px 0px; text-align:left; text-transform:uppercase; border-top:1px solid #000; border-bottom:1px solid #000;">Units</th>
                                <th style="padding:0px 0px 3px 0px; text-align:left; text-transform:uppercase; border-top:1px solid #000; border-bottom:1px solid #000;">Ref. Ranges</th>
                            </tr>
                        </thead>
                        <tbody>';

                    // Reset row count for new page
                    $currentRowCount = 1;
                }

                // Add category header
                $content .= '
                            <tr>
                                <td colspan="4" style="padding: 15px 0px 5px 0px;text-transform:uppercase;font-style:light;font-weight:bold;">
                                    ' . strtoupper($panel['category_name'] ?? $panel['name']) . '
                                </td>
                            </tr>';

                $currentRowCount += 1;

                // Loop through panel items
                foreach ($panel['items'] as $item) {
                    if ($item['base_name'] === 'Blood Film') {
                        // Special handling for Blood Film
                        $content .= '
                            <tr>
                                <td colspan="2" style="padding:0px 0px 3px 0px;">
                                    <table style="border-collapse:collapse; width:100%;font-family:\'Courier New\', Courier, monospace;line-spacing:1.5;font-size:11.5px;">
                                        <tr>
                                            <td style="width:90%; padding:5px 5px 5px 10px;">FILM: 
                                                ' . ($item['value']['result_value'] ?? '') . '
                                            </td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>';
                        $currentRowCount += 3; // Blood film takes more vertical space
                    } else {
                        // Regular test result items
                        $flag = ($item['value']['flag'] != 'N') ? '*' : '';
                        $resultValue = $item['value']['result_value'] ?? '';
                        $unit = $item['value']['unit'] ?? '';
                        $refRange = $item['value']['ref_range'] ?? '';
                        $percentage = isset($item['percentage']) ? $item['percentage']['result_value'] . '%' : '';

                        // Special styling for certain items
                        $isSpecialItem = in_array($item['base_name'], ['Haemoglobin', 'White Cell Count', 'Platelets']);
                        $specialPadding = in_array($item['base_name'], ['White Cell Count', 'Platelets']) ? 'padding:10px 0px;' : 'padding:0px 0px 3px 0px;';
                        $boldStyle = $isSpecialItem ? 'font-style:light;font-weight:bold;' : '';
                        $flagStyle = ($flag) ? 'font-style:light;font-weight:bold; text-decoration:underline;' : '';

                        $content .= '
                            <tr>
                                <td style="' . $specialPadding . '">
                                    <table style="border-collapse:collapse; width:100%;">
                                        <tr>
                                            <td style="width:3%; padding:0;font-style:light;font-weight:bold;">' . $flag . '</td>
                                            <td style="width:50%; padding:0; ' . $boldStyle . '">' . ($item['base_name'] ?? '') . '</td>
                                            <td style="width:40%; padding:0;"></td>
                                            <td style="width:15%; padding:0;">' . $percentage . '</td>
                                        </tr>
                                    </table>
                                </td>
                                <td style="' . $specialPadding . ' ' . $flagStyle . '">' . $resultValue . '</td>
                                <td style="' . $specialPadding . '">' . $unit . '</td>
                                <td style="' . $specialPadding . '">' . $refRange . '</td>
                            </tr>';
                        $currentRowCount += 1; // Regular items take 1 row
                    }
                }
            }

            $content .= '
                        </tbody>
                    </table>
                </div>
            </body>
            </html>';

            $mpdf->WriteHTML($content);

            return response($mpdf->Output('', 'S'), 200)
                ->header('Content-Type', 'application/pdf')
                ->header('Content-Disposition', 'inline; filename="dummy-report.pdf"');
        } catch (\Exception $e) {
            Log::error('PDF Generation Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate PDF',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}