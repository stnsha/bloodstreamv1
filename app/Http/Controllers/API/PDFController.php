<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\PanelPanelItem;
use App\Models\PanelPanelProfile;
use App\Models\PanelProfile;
use App\Models\TestResult;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Log;
use Mpdf\Mpdf;

class PDFController extends Controller
{
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
            )->find(69);

            //69 - ada profile
            // 49 - takda profile dan takda category

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

            // Determine sequence source based on whether test result has profiles
            $hasProfiles = count($testResult->profiles) > 0;

            if ($hasProfiles) {
                foreach ($testResult->testResultProfiles as $trp) {
                    $ppps = PanelPanelProfile::with(['panel', 'panelProfile'])->where('panel_profile_id', $trp->panel_profile_id)->get();
                    foreach ($ppps as $ppp) {
                        $result[$testResult->id]['profiles'][$ppp->panel_profile_id]['profile_id'] = $ppp->panelProfile->id;
                        $result[$testResult->id]['profiles'][$ppp->panel_profile_id]['profile_name'] = $ppp->panelProfile->name;
                        $result[$testResult->id]['profiles'][$ppp->panel_profile_id]['panels'][$ppp->panel_id]['panel_name'] = $ppp->panel->name;
                        $result[$testResult->id]['profiles'][$ppp->panel_profile_id]['panels'][$ppp->panel_id]['panel_profile_sequence'] = $ppp->sequence;
                    }
                }
            }

            // Build hierarchical structure: Profile > Category > Panel > Panel Item
            $hierarchicalData = [];

            foreach ($testResult->testResultItems as $ri) {
                // Extract all data from result item
                $res_value = $ri->value;
                $res_flag = $ri->flag;
                $res_sequence = $ri->sequence;
                $is_tagon = $ri->is_tagon;
                $ref_range_id = $ri->reference_range_id;

                // Find panel panel item with all related data
                $ppi = PanelPanelItem::with([
                    'panel',
                    'panel.panelCategory',
                    'panel.panelComments',
                    'panelItem',
                    'referenceRanges' => function ($query) use ($ref_range_id) {
                        $query->where('id', $ref_range_id);
                    }
                ])->find($ri->panel_panel_item_id);

                $reference_range = $ppi->referenceRanges->first()->value ?? null;
                if ($reference_range) {
                    $reference_range = str_replace(['(', ')'], '', $reference_range);
                }

                // Panel item data
                $panelItemData = [
                    'panel_item_id' => $ppi->panel_item_id,
                    'result_value' => $res_value,
                    'result_flag' => $res_flag,
                    'is_tagon' => $is_tagon,
                    'result_sequence' => $res_sequence,
                    'reference_range' => $reference_range
                ];

                // Determine hierarchy based on priority: Profile > Category > Panel > Panel Item
                $profileId = null;
                $profileName = null;
                $profileSequence = null;

                // Check if this panel belongs to any profile
                if ($hasProfiles && isset($result[$testResult->id]['profiles'])) {
                    foreach ($result[$testResult->id]['profiles'] as $profileData) {
                        if (isset($profileData['panels'][$ppi->panel_id])) {
                            $profileId = $profileData['profile_id'];
                            $profileName = $profileData['profile_name'];
                            $profileSequence = $profileData['panels'][$ppi->panel_id]['panel_profile_sequence'];
                            break;
                        }
                    }
                }

                // Build hierarchical structure based on available data
                if ($profileId) {
                    // Level 1: Profile exists - group under Profile > Category > Panel > Panel Item
                    $categoryId = $ppi->panel->panel_category_id ?? 'no_category';
                    $categoryName = $ppi->panel->panelCategory->name ?? 'No Category';

                    if (!isset($hierarchicalData['profiles'][$profileId])) {
                        $hierarchicalData['profiles'][$profileId] = [
                            'profile_id' => $profileId,
                            'profile_name' => $profileName,
                            'profile_sequence' => $profileSequence,
                            'categories' => []
                        ];
                    }

                    if (!isset($hierarchicalData['profiles'][$profileId]['categories'][$categoryId])) {
                        $hierarchicalData['profiles'][$profileId]['categories'][$categoryId] = [
                            'category_id' => $categoryId,
                            'category_name' => $categoryName,
                            'panels' => []
                        ];
                    }

                    if (!isset($hierarchicalData['profiles'][$profileId]['categories'][$categoryId]['panels'][$ppi->panel_id])) {
                        $hierarchicalData['profiles'][$profileId]['categories'][$categoryId]['panels'][$ppi->panel_id] = [
                            'panel_id' => $ppi->panel_id,
                            'panel_name' => $ppi->panel->name,
                            'panel_sequence' => $ppi->panel->sequence,
                            'panel_profile_sequence' => $profileSequence,
                            'panel_items' => []
                        ];
                    }

                    $hierarchicalData['profiles'][$profileId]['categories'][$categoryId]['panels'][$ppi->panel_id]['panel_items'][] = $panelItemData;
                } elseif ($ppi->panel->panel_category_id) {
                    // Level 2: No Profile but Category exists - group under Category > Panel > Panel Item
                    $categoryId = $ppi->panel->panel_category_id;
                    $categoryName = $ppi->panel->panelCategory->name;

                    if (!isset($hierarchicalData['categories'][$categoryId])) {
                        $hierarchicalData['categories'][$categoryId] = [
                            'category_id' => $categoryId,
                            'category_name' => $categoryName,
                            'panels' => []
                        ];
                    }

                    if (!isset($hierarchicalData['categories'][$categoryId]['panels'][$ppi->panel_id])) {
                        $hierarchicalData['categories'][$categoryId]['panels'][$ppi->panel_id] = [
                            'panel_id' => $ppi->panel_id,
                            'panel_name' => $ppi->panel->name,
                            'panel_sequence' => $ppi->panel->sequence,
                            'panel_items' => []
                        ];
                    }

                    $hierarchicalData['categories'][$categoryId]['panels'][$ppi->panel_id]['panel_items'][] = $panelItemData;
                } else {
                    // Level 3: No Profile and No Category - group under Panel > Panel Item
                    if (!isset($hierarchicalData['panels'][$ppi->panel_id])) {
                        $hierarchicalData['panels'][$ppi->panel_id] = [
                            'panel_id' => $ppi->panel_id,
                            'panel_name' => $ppi->panel->name,
                            'panel_sequence' => $ppi->panel->sequence,
                            'panel_items' => []
                        ];
                    }

                    $hierarchicalData['panels'][$ppi->panel_id]['panel_items'][] = $panelItemData;
                }
            }

            // Sort categories within profiles by panel_profile_sequence average
            if (isset($hierarchicalData['profiles'])) {
                foreach ($hierarchicalData['profiles'] as &$profile) {
                    if (isset($profile['categories'])) {
                        // Sort categories by average sequence (lowest first)
                        uasort($profile['categories'], function ($a, $b) {
                            // Calculate average for category A
                            $totalSequenceA = 0;
                            $panelCountA = 0;
                            foreach ($a['panels'] as $panel) {
                                if (isset($panel['panel_profile_sequence'])) {
                                    $totalSequenceA += $panel['panel_profile_sequence'];
                                    $panelCountA++;
                                }
                            }
                            $avgA = $panelCountA > 0 ? $totalSequenceA / $panelCountA : 999999;

                            // Calculate average for category B
                            $totalSequenceB = 0;
                            $panelCountB = 0;
                            foreach ($b['panels'] as $panel) {
                                if (isset($panel['panel_profile_sequence'])) {
                                    $totalSequenceB += $panel['panel_profile_sequence'];
                                    $panelCountB++;
                                }
                            }
                            $avgB = $panelCountB > 0 ? $totalSequenceB / $panelCountB : 999999;

                            return $avgA <=> $avgB;
                        });

                        // Also sort panels within each category by panel_profile_sequence
                        foreach ($profile['categories'] as &$category) {
                            uasort($category['panels'], function ($a, $b) {
                                $seqA = $a['panel_profile_sequence'] ?? 999999;
                                $seqB = $b['panel_profile_sequence'] ?? 999999;
                                return $seqA <=> $seqB;
                            });
                        }
                        unset($category);
                    }
                }
                unset($profile);
            }

            // Add hierarchical structure to result - this replaces the separate resultItems structure
            $result =
                [
                    'patient_info' => $patient_info,
                    'test_dates' => $test_dates,
                    'doctor_info' => $doctor_info,
                    'lab_info' => $lab_info,
                    'results' => $hierarchicalData
                ];

            // return response()->json($result);
            // exit;

            $mpdf = new Mpdf([
                'mode' => 'utf-8',
                'format' => 'A4',
                // 'margin_left' => 40,
                // 'margin_right' => 38,
                'margin_top' => 70,
                // 'margin_bottom' => 0,
                // 'margin_header' => 10,
                'margin_footer' => 5,
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
                                        <td colspan="4" style="width:200px;padding:0;">' . strtoupper($result['patient_info']['name']) . '</td>
                                        <td style="padding:0;"></td>
                                        <td style="padding:0;"></td>
                                        <td style="padding:0;"></td>
                                        <td style="padding:0;"></td>
                                    </tr>
                                    <tr>
                                        <td style="padding:0;">UR</td>
                                        <td style="padding:0;">:</td>
                                        <td style="padding:0;"></td>
                                        <td style="padding:0;"></td>
                                    </tr>
                                    <tr>
                                        <td style="padding:0;">Ref</td>
                                        <td style="padding:0;">:</td>
                                        <td style="padding:0;">' . $result['lab_info']['refid'] . '</td>
                                        <td style="padding:0;"></td>
                                    </tr>
                                    <tr>
                                        <td style="padding:10px 0 0 0;">DOB</td>
                                        <td style="padding:10px 0 0 0;">:</td>
                                        <td style="padding:10px 0 0 0;">' . $result['patient_info']['dob'] . '</td>
                                        <td style="padding:10px 0 0 -15px;">Sex</td>
                                        <td style="padding:10px 0 0 -15px;">:</td>
                                        <td style="padding:10px 0 0 -25px;">' . $result['patient_info']['gender'] . '</td>
                                    </tr>
                                    <tr>
                                        <td style="padding:0;">IC NO.</td>
                                        <td style="padding:0;">:</td>
                                        <td style="padding:0;">' . $result['patient_info']['icno'] . '</td>
                                        <td style="padding:0 0 0 -15px;">Age</td>
                                        <td style="padding:0 0 0 -15px;">:</td>
                                        <td style="padding:0 0 0 -25px;">' . $result['patient_info']['age'] . '</td>
                                    </tr>
                                    <tr>
                                        <td style="padding:0;">Collected</td>
                                        <td style="padding:0;">:</td>
                                        <td style="padding:0;">' . $result['test_dates']['collected_date'] . ' ' . $result['test_dates']['collected_time'] . '</td>
                                        <td style="padding:0 0 0 -15px;">Ward</td>
                                        <td style="padding:0 0 0 -15px;">:</td>
                                        <td style="padding:0;"></td>
                                    </tr>
                                    <tr>
                                        <td style="padding:0;">Referred</td>
                                        <td style="padding:0;">:</td>
                                        <td style="padding:0;">' . $result['test_dates']['reported_date'] . '</td>
                                        <td style="padding:0 0 0 -15px;">Yr Ref.</td>
                                        <td style="padding:0 0 0 -15px;">:</td>
                                        <td style="padding:0;"></td>
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
                                        <td style="padding:0px 0px 3px 0px;"></td>
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
                                <div style="font-size:11px; font-weight:normal;">
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
                                <th style="padding:0px 0px 0px 0px; text-align:left; text-transform:uppercase;width:386px; border-top:1px solid #000; border-bottom:1px solid #000;">Analytes</th>
                                <th style="padding:0px 0px 0px 0px; text-align:left; text-transform:uppercase;width:60px; border-top:1px solid #000; border-bottom:1px solid #000;">Results</th>
                                <th style="padding:0px 0px 0px 0px; text-align:left; text-transform:uppercase; border-top:1px solid #000; border-bottom:1px solid #000;">Units</th>
                                <th style="padding:0px 0px 0px 0px; text-align:left; text-transform:uppercase; border-top:1px solid #000; border-bottom:1px solid #000;">Ref. Ranges</th>
                            </tr>
                        </thead>
                        <tbody>';

                    // Reset row count for new page
                    $currentRowCount = 1;
                }

                // Add category header
                $content .= '
                            <tr>
                                <td colspan="4" style="padding: 15px 0px 5px 0px;text-transform:uppercase;font-style:light;font-weight:bold;text-decoration:underline;">
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
                                            <td style="width:90%; padding:5px 5px 10px 10px;">FILM: 
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

                        $isColspan = empty($unit) || empty($refRange) ? 'rowspan="1"' : '';

                        // Special styling for certain items
                        $isSpecialItem = in_array($item['base_name'], ['Haemoglobin', 'White Cell Count', 'Platelets']);
                        $specialPadding = in_array($item['base_name'], ['White Cell Count', 'Platelets']) ? 'padding:10px 0px;' : 'padding:0px 0px 3px 0px;';
                        $boldStyle = $isSpecialItem || $flag ? 'font-style:light;font-weight:bold;' : '';
                        $flagStyle = ($flag) ? 'font-style:light;font-weight:bold; text-decoration:underline;' : '';

                        $content .= '
                            <tr>
                                <td style="' . $specialPadding . '">
                                    <table style="border-collapse:collapse; width:100%;">
                                        <tr>
                                        <td style="width:10px; padding:0; font-weight:bold;">' . $flag . '</td>
                                        <td style="width:210px; padding:0; ' . $boldStyle . '">' . ($item['base_name'] ?? '') . '</td>
                                        <td style="width:200px; padding:0;"></td>
                                        <td style="width:70px; padding:0px 10px 0px 0px; text-align:right;">' . $percentage . '</td>
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