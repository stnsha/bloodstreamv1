<?php

namespace App\Http\Controllers\API\Innoquest;

use App\Http\Controllers\Controller;
use App\Http\Requests\ODB\ODBRequest;
use App\Models\PanelPanelItem;
use App\Models\PanelPanelProfile;
use App\Models\TestResult;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Mpdf\Mpdf;

class PDFController extends Controller
{
    private function getLogChannel()
    {
        return 'odb-log';
    }

    /**
     * API for PDF generation from ODB
     * Flow: ODB > export > processTestResult > export
     */
    public function export(ODBRequest $request)
    {
        try {
            $result = $this->processTestResult($request);

            // Check if processTestResult returned an error response
            if ($result instanceof JsonResponse) {
                return $result;
            }

            $mpdf = new Mpdf([
                'mode' => 'utf-8',
                'format' => 'A4',
                'margin_top' => 70,
                'margin_footer' => 5,
                'default_font' => 'Arial',
            ]);

            $mpdf->useAdobeCJK = true;
            $mpdf->autoScriptToLang = true;
            $mpdf->autoLangToFont = true;
            $mpdf->allow_charset_conversion = true;

            $header = $this->header($result);
            $footer = $this->footer();

            $mpdf->SetHTMLHeader($header);
            $mpdf->SetHTMLFooter($footer);

            $content = '
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset="utf-8">
                <title>Blood Test Report</title>
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
                </style>
            </head>
            <body>
                <div class="content">
                    <table style="width: 100%; border-collapse: collapse; font-size:11.5px; margin-top:15px; text-align:left;">
                        <thead>
                            <tr>
                                <th style="padding:2px 0px 2px 15px;text-align:left;text-transform:uppercase;width:500px;border-top:1px solid #000; border-bottom:1px solid #000;">Analytes</th>
                                <th style="padding:2px 0px 2px 0px;text-align:left;text-transform:uppercase;width:60px;border-top:1px solid #000; border-bottom:1px solid #000;">Results</th>
                                <th style="padding:2px 0px 2px 0px;text-align:left;text-transform:uppercase;width:80px;border-top:1px solid #000; border-bottom:1px solid #000;">Units</th>
                                <th style="padding:2px 0px 2px 0px;text-align:left;text-transform:uppercase;width:90px;border-top:1px solid #000; border-bottom:1px solid #000;">Ref. Ranges</th>
                            </tr>
                        </thead>
                        <tbody>
                            ';

            // Pagination: Profile name only on first page, max 4 panels per page
            $pageIndex = 0;
            $showProfileName = true; // Only show on first page
            $allTestsRequested = []; // Collect all tests/categories for the final summary

            // FIRST: Display profile name at the very top if profiles exist
            $content .= $this->displayProfileName($result, $showProfileName);

            // SECOND: Display profiles if they exist
            if (isset($result['data']['profiles'])) {
                foreach ($result['data']['profiles'] as $pr) {
                    $profile_name = $pr['profile_name'];
                    if (isset($pr['categories'])) {
                        foreach ($pr['categories'] as $cat) {
                            $category_name = $cat['category_name'];
                            $allTestsRequested[] = $category_name; // Collect category name

                            if (isset($cat['panels'])) {
                                // Group panels into chunks - max 2 per page if any panel has comments, otherwise 4
                                $panelChunks = $this->createPanelChunks($cat['panels']);

                                foreach ($panelChunks as $chunkIndex => $panelGroup) {
                                    // Force page break before each page (except first)
                                    if ($pageIndex > 0) {
                                        // Close current table
                                        $content .= '
                                    </tbody>
                                </table>
                            </div>';

                                        // Write current content and add page break using mPDF
                                        $mpdf->WriteHTML($content);
                                        $mpdf->AddPage();

                                        // Reset content and start new page with table
                                        $content = '
                            <div class="content">
                                <table style="width: 100%; border-collapse: collapse; font-size:11.5px; margin-top:92px; text-align:left;">
                                    <thead>
                                        <tr>
                                            <th style="padding:2px 0px 2px 15px;text-align:left;text-transform:uppercase;width:500px;border-top:1px solid #000; border-bottom:1px solid #000;">Analytes</th>
                                            <th style="padding:2px 0px 2px 0px;text-align:left;text-transform:uppercase;width:60px;border-top:1px solid #000; border-bottom:1px solid #000;">Results</th>
                                            <th style="padding:2px 0px 2px 0px;text-align:left;text-transform:uppercase;width:80px;border-top:1px solid #000; border-bottom:1px solid #000;">Units</th>
                                            <th style="padding:2px 0px 2px 0px;text-align:left;text-transform:uppercase;width:90px;border-top:1px solid #000; border-bottom:1px solid #000;">Ref. Ranges</th>
                                        </tr>
                                    </thead>
                                    <tbody>';
                                    }

                                    // Add category header (show on first chunk of each category)
                                    if ($chunkIndex == 0) {
                                        $content .= '<tr>
                                            <td colspan="4" style="padding:15px 0px 5px 0px;font-style:light;font-weight:bold;text-transform:uppercase;">' . $category_name . '</td>
                                        </tr>';
                                    }

                                    // Add panels for this chunk (max 4)
                                    foreach ($panelGroup as $pl) {
                                        $panel_name = $pl['panel_name'];

                                        $content .= '<tr>
                                                <td colspan="4" style="padding:10px 0px 10px 10px;font-style:light;font-weight:bold;text-transform:uppercase;">' . $panel_name . '</td>
                                                </tr>';

                                        if (isset($pl['panel_items'])) {
                                            $panelItems = $pl['panel_items'];

                                            // Custom sorting for panel_category_id = 4 (HAE sequence)  
                                            if (isset($pl['panel_category_id']) && $pl['panel_category_id'] == 4) {
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

                                                usort($panelItems, function ($a, $b) use ($haeOrder) {
                                                    $posA = array_search($a['panel_item_name'], $haeOrder);
                                                    $posB = array_search($b['panel_item_name'], $haeOrder);

                                                    // If both items are in the custom order
                                                    if ($posA !== false && $posB !== false) {
                                                        return $posA - $posB;
                                                    }

                                                    // If only A is in custom order, A comes first
                                                    if ($posA !== false && $posB === false) {
                                                        return -1;
                                                    }

                                                    // If only B is in custom order, B comes first
                                                    if ($posA === false && $posB !== false) {
                                                        return 1;
                                                    }

                                                    // If neither is in custom order, use result_sequence
                                                    return $a['result_sequence'] - $b['result_sequence'];
                                                });
                                            }

                                            foreach ($panelItems as $pi) {
                                                $colspan = 'none';
                                                // if (empty($pi['panel_item_unit'])) {
                                                //     $colspan = 4;
                                                // }

                                                $isSpecialItem = in_array($pi['panel_item_name'], ['Haemoglobin', 'White Cell Count', 'Platelets']);
                                                $boldStyle = $isSpecialItem ? 'font-style:light;font-weight:bold;' : '';

                                                $isSpecialPadding = in_array($pi['panel_item_name'], ['White Cell Count', 'Platelets']);
                                                $paddingStyle = $isSpecialPadding ? 'padding: 20px 0px;' : '';

                                                $formattedStyle = '';
                                                if (!empty($pi['result_flag'])) {
                                                    $formattedStyle = 'font-weight:bold;text-decoration:underline';
                                                }
                                                if ($pi['panel_item_name'] == 'Blood Film') {

                                                    $content .= '<tr>
                                                                    <td style="font-family: Courier New;font-stretch: expanded;padding:10px 30px;"><span style="font-weight:bold;margin-right:10px;">FILM:</span>' . $pi['result_value'] . '</td>
                                                                    </tr>';
                                                } else if ($pi['panel_item_id'] == 25) {
                                                    $content .= '<tr>
                                                                    <td style="font-family: Courier New;font-stretch: expanded;padding:10px 30px;">
                                                                    ' . nl2br(str_replace('\.br\\', '<br>', $pi['result_value'])) . '
                                                                    </td>
                                                                    </tr>';
                                                } else if ($pi['panel_item_id'] == 26) {
                                                    $content .= '<tr>
                                                                    <td style="font-family: Courier New;font-stretch: expanded;padding:10px 30px;">Group:
                                                                    ' . nl2br(str_replace('\.br\\', '<br>', $pi['result_value'])) . '
                                                                    </td>
                                                                    </tr>';
                                                } else {
                                                    $name = $pi['panel_item_name'];
                                                    $displayName = $this->formatPanelItemName($name);
                                                    $content .= '<tr>
                                                                        <td style="padding:3px 10px;">
                                                                            <table style="border-collapse:collapse; width:100%; table-layout:fixed;">
                                                                                <tr>
                                                                                    <td style="padding:0; font-weight:bold; width:15px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;text-align:center;">
                                                                                        ' . $pi['result_flag'] . '
                                                                                    </td>
                                                                                    <td style="padding:0; width:240px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; ' . $boldStyle .  ' ' . $paddingStyle . ' ">'
                                                        . $displayName .
                                                        '</td>
                                                                                    <td style="padding:0; width:120px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">' . $pi['chinese_character'] . '</td>
                                                                                    <td style="padding:0px 10px 0px 0px; text-align:right; width:125px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
                                                                                        ' . ($pi['percentage_value'] != '0' && $pi['is_percentage'] ? $pi['percentage_value'] . '%' : '') . '
                                                                                    </td>
                                                                                </tr>
                                                                            </table>
                                                                        </td>
                                                                        <td colspan=' . $colspan . ' style="' . $formattedStyle . '">' . str_replace(["\\H\\", "\\N\\", ".\\br\\"], ['', '', '<br>'], $pi['result_value']) . '</td>
                                                                        <td>' . $pi['panel_item_unit'] . '</td>
                                                                        <td>' . $pi['reference_range'] . '</td>
                                                                    </tr> ';
                                                }
                                            }
                                        }

                                        // Add panel comments if they exist
                                        if (!empty($pl['panel_comments'])) {
                                            foreach ($pl['panel_comments'] as $comment) {
                                                $formattedComment = $this->formatPanelComment($comment['comment']);
                                                $content .= '<tr>
                                                                <td colspan="4" style="font-family: Courier New;font-stretch: expanded;padding:15px 15px 5px 15px;">
                                                                    ' . $formattedComment . '
                                                                </td>
                                                            </tr>';
                                            }
                                        }
                                    }
                                    $pageIndex++;
                                }
                            }
                        }
                    }
                }
            }

            // THIRD: Display standalone panels (not belonging to profiles or categories)  
            if (isset($result['data']['panels'])) {
                foreach ($result['data']['panels'] as $pl) {
                    $panel_name = $pl['panel_name'];
                    $allTestsRequested[] = $panel_name; // Collect standalone panel name

                    $content .= '<tr>
                                    <td colspan="4" style="padding:10px 0px 10px 10px;font-style:light;font-weight:bold;text-transform:uppercase;">' . $panel_name . '</td>
                                    </tr>';

                    // Sort panel items by result_sequence
                    if (isset($pl['panel_items'])) {
                        usort($pl['panel_items'], function ($a, $b) {
                            return $a['result_sequence'] - $b['result_sequence'];
                        });

                        foreach ($pl['panel_items'] as $pi) {
                            $formattedStyle = '';
                            if (!empty($pi['result_flag'])) {
                                $formattedStyle = 'font-weight:bold;text-decoration:underline';
                            }

                            if ($pi['panel_item_name'] == 'Blood Film') {
                                $content .= '<tr>
                                                <td style="font-family: Courier New;font-stretch: expanded;padding:10px 30px;"><span style="font-weight:bold;margin-right:10px;">FILM:</span>' . $pi['result_value'] . '</td>
                                                </tr>';
                            } else if ($pi['panel_item_id'] == 25) {
                                $content .= '<tr>
                                                <td style="font-family: Courier New;font-stretch: expanded;padding:10px 30px;">
                                                ' . nl2br(str_replace('\.br\\', '<br>', $pi['result_value'])) . '
                                                </td>
                                                </tr>';
                            } else if ($pi['panel_item_id'] == 26) {
                                $content .= '<tr>
                                                <td style="font-family: Courier New;font-stretch: expanded;padding:10px 30px;">Group:
                                                ' . $pi['result_value'] . '
                                                </td>
                                                </tr>';
                            } else {
                                $name = $pi['panel_item_name'];
                                $displayName = $this->formatPanelItemName($name);
                                $content .= '<tr>
                                                    <td style="padding:0px 10px;">
                                                        <table style="border-collapse:collapse; width:100%; table-layout:fixed;">
                                                            <tr>
                                                                <td style="padding:0; font-weight:bold; width:15px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;text-align:center;">
                                                                    ' . $pi['result_flag'] . '
                                                                </td>
                                                                <td style="padding:0; width:240px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">'
                                    . $displayName .
                                    '</td>
                                                                <td style="padding:0; width:90px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; text-align:center; ' . $formattedStyle . '">'
                                    . $pi['result_value'] .
                                    '</td>
                                                                <td style="padding:0; width:80px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;text-align:center;">'
                                    . $pi['panel_item_unit'] .
                                    '</td>
                                                                <td style="padding:0; width:80px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;text-align:center; font-size:10px;">'
                                    . $pi['reference_range'] .
                                    '</td>
                                                            </tr>
                                                        </table>
                                                    </td>
                                                </tr>';

                                // Add item comments if they exist
                                if (!empty($pi['item_comments'])) {
                                    foreach ($pi['item_comments'] as $comment) {
                                        $formattedComment = $this->formatPanelComment($comment['comment']);
                                        $content .= '<tr>
                                                        <td colspan="4" style="font-family: Courier New;font-stretch: expanded;padding:15px 15px 5px 15px;">
                                                            ' . $formattedComment . '
                                                        </td>
                                                    </tr>';
                                    }
                                }
                            }
                        }

                        // Add panel comments if they exist
                        if (!empty($pl['panel_comments'])) {
                            foreach ($pl['panel_comments'] as $comment) {
                                $formattedComment = $this->formatPanelComment($comment['comment']);
                                $content .= '<tr>
                                                <td colspan="4" style="font-family: Courier New;font-stretch: expanded;padding:15px 15px 5px 15px;">
                                                    ' . $formattedComment . '
                                                </td>
                                            </tr>';
                            }
                        }
                    }
                }
            }

            // FOURTH: Display standalone categories (not belonging to profiles)
            if (isset($result['data']['categories'])) {
                foreach ($result['data']['categories'] as $cat) {
                    $category_name = $cat['category_name'];
                    $allTestsRequested[] = $category_name; // Collect standalone category name

                    if (isset($cat['panels'])) {
                        // Group panels into chunks - max 2 per page if any panel has comments, otherwise 4
                        $panelChunks = $this->createPanelChunks($cat['panels']);

                        foreach ($panelChunks as $chunkIndex => $panelGroup) {
                            // Force page break before each page (except first)
                            if ($pageIndex > 0) {
                                // Close current table
                                $content .= '
                                    </tbody>
                                </table>
                            </div>';

                                // Write current content and add page break using mPDF
                                $mpdf->WriteHTML($content);
                                $mpdf->AddPage();

                                // Reset content and start new page with table
                                $content = '
                            <div class="content">
                                <table style="width: 100%; border-collapse: collapse; font-size:11.5px; margin-top:92px; text-align:left;">
                                    <thead>
                                        <tr>
                                            <th style="padding:2px 0px 2px 15px;text-align:left;text-transform:uppercase;width:500px;border-top:1px solid #000; border-bottom:1px solid #000;">Analytes</th>
                                            <th style="padding:2px 0px 2px 0px;text-align:left;text-transform:uppercase;width:60px;border-top:1px solid #000; border-bottom:1px solid #000;">Results</th>
                                            <th style="padding:2px 0px 2px 0px;text-align:left;text-transform:uppercase;width:80px;border-top:1px solid #000; border-bottom:1px solid #000;">Units</th>
                                            <th style="padding:2px 0px 2px 0px;text-align:left;text-transform:uppercase;width:90px;border-top:1px solid #000; border-bottom:1px solid #000;">Ref. Ranges</th>
                                        </tr>
                                    </thead>
                                    <tbody>';
                            }

                            // Add category header (show on first chunk of each category)
                            if ($chunkIndex == 0) {
                                $content .= '<tr>
                                            <td colspan="4" style="padding:15px 0px 5px 0px;font-style:light;font-weight:bold;text-transform:uppercase;">' . $category_name . '</td>
                                        </tr>';
                            }

                            // Add panels for this chunk (max 4)
                            foreach ($panelGroup as $pl) {
                                $panel_name = $pl['panel_name'];

                                $content .= '<tr>
                                                <td colspan="4" style="padding:10px 0px 10px 10px;font-style:light;font-weight:bold;text-transform:uppercase;">' . $panel_name . '</td>
                                                </tr>';

                                if (isset($pl['panel_items'])) {
                                    $panelItems = $pl['panel_items'];

                                    // Custom sorting for panel_category_id = 4 (HAE sequence)  
                                    if (isset($pl['panel_category_id']) && $pl['panel_category_id'] == 4) {
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

                                        usort($panelItems, function ($a, $b) use ($haeOrder) {
                                            $posA = array_search($a['panel_item_name'], $haeOrder);
                                            $posB = array_search($b['panel_item_name'], $haeOrder);

                                            // If both items are in the custom order
                                            if ($posA !== false && $posB !== false) {
                                                return $posA - $posB;
                                            }

                                            // If only A is in custom order, A comes first
                                            if ($posA !== false && $posB === false) {
                                                return -1;
                                            }

                                            // If only B is in custom order, B comes first
                                            if ($posA === false && $posB !== false) {
                                                return 1;
                                            }

                                            // If neither is in custom order, use result_sequence
                                            return $a['result_sequence'] - $b['result_sequence'];
                                        });
                                    }

                                    foreach ($panelItems as $pi) {
                                        $colspan = 'none';
                                        if (empty($pi['panel_item_unit'])) {
                                            $colspan = 1;
                                        }

                                        $formattedStyle = '';
                                        if (!empty($pi['result_flag']) || str_contains($pi['result_value'], '\H')) {
                                            $formattedStyle = 'font-weight:bold;text-decoration:underline';
                                        }

                                        if ($pi['panel_item_name'] == 'Blood Film') {
                                            $content .= '<tr>
                                                        <td style="font-family: Courier New;font-stretch: expanded;padding:10px 30px;"><span style="font-weight:bold;margin-right:10px;">FILM:</span>' . $pi['result_value'] . '</td>
                                                        </tr>';
                                        } else if ($pi['panel_item_id'] == 25) {
                                            $content .= '<tr>
                                                        <td style="font-family: Courier New;font-stretch: expanded;padding:10px 30px;">
                                                        ' . nl2br(str_replace('\.br\\', '<br>', $pi['result_value'])) . '
                                                        </td>
                                                        </tr>';
                                        } else if ($pi['panel_item_id'] == 26) {
                                            $content .= '<tr>
                                                                    <td style="font-family: Courier New;font-stretch: expanded;padding:10px 30px;">Group:
                                                                    ' . nl2br(str_replace('\.br\\', '<br>', $pi['result_value'])) . '
                                                                    </td>
                                                                    </tr>';
                                        } else {
                                            $name = $pi['panel_item_name'];
                                            $displayName = $this->formatPanelItemName($name);
                                            $content .= '<tr>
                                                                        <td style="padding:0px 10px;">
                                                                            <table style="border-collapse:collapse; width:100%; table-layout:fixed;">
                                                                                <tr>
                                                                                    <td style="padding:0; font-weight:bold; width:15px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;text-align:center;">
                                                                                        ' . $pi['result_flag'] . '
                                                                                    </td>
                                                                                    <td style="padding:0; width:240px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">'
                                                . $displayName .
                                                '</td>
                                                                                    <td style="padding:0; width:120px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">' . $pi['chinese_character'] . '</td>
                                                                                    <td style="padding:0px 10px 0px 0px; text-align:right; width:125px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
                                                                                        ' . ($pi['percentage_value'] != '0' && $pi['is_percentage'] ? $pi['percentage_value'] . '%' : '') . '
                                                                                    </td>
                                                                                </tr>
                                                                            </table>
                                                                        </td>
                                                                        <td colspan=' . $colspan . ' style="' . $formattedStyle . '">' . str_replace(["\\H\\", "\\N\\", ".\\br\\"], ['', '', '<br>'], $pi['result_value']) . '</td>
                                                                        <td>' . $pi['panel_item_unit'] . '</td>
                                                                        <td>' . $pi['reference_range'] . '</td>
                                                                    </tr> ';
                                        }
                                    }

                                    // Add panel comments if they exist
                                    if (!empty($pl['panel_comments'])) {
                                        foreach ($pl['panel_comments'] as $comment) {
                                            $formattedComment = $this->formatPanelComment($comment['comment']);
                                            $content .= '<tr>
                                                            <td colspan="4" style="font-family: Courier New;font-stretch: expanded;padding:15px 15px 5px 15px;">
                                                                ' . $formattedComment . '
                                                            </td>
                                                        </tr>';
                                        }
                                    }
                                }
                            }
                            $pageIndex++;
                        }
                    }
                }
            }

            // Add REPORT COMPLETED section at the end
            if (!empty($allTestsRequested)) {
                // Remove duplicates and sort alphabetically
                $uniqueTests = array_unique($allTestsRequested);
                sort($uniqueTests);
                $testsString = implode(', ', $uniqueTests);

                $content .= '
                                <tr>
                                    <td colspan="4" style=" padding-top: 25px;text-align:center;">
                                        <div style="text-align:center;font-weight:bold; font-size:12px; text-decoration:underline; margin-bottom:10px;">
                                            REPORT COMPLETED
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <td colspan="4" style="font-size:11.5px;">
                                        Tests Requested:<br>
                                        ' . strtoupper($testsString) . '
                                    </td>
                                </tr>';
            }

            $content .= '
                        </tbody>
                    </table>
                </div>
            </body>
            </html>';

            // Write the final content
            $mpdf->WriteHTML($content);

            // return response($mpdf->Output('', 'S'), 200)
            //     ->header('Content-Type', 'application/pdf')
            //     ->header('Content-Disposition', 'inline; filename="dummy-report.pdf"');

            // Get PDF content as string and convert to base64
            $pdfContent = $mpdf->Output('', 'S');
            $base64Pdf = base64_encode($pdfContent);

            return response()->json([
                'success' => true,
                'report_id' => $result['test_result_id'],
                'pdf' => $base64Pdf,
                'message' => 'PDF generated successfully'
            ], 200);
        } catch (Exception $e) {
            Log::error('PDF Generation Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate PDF',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate a PDF from a pre-compiled result array and return it as a base64-encoded JSON response.
     *
     * @param array $result The result array as returned by processTestResult()
     * @return JsonResponse
     */
    private function generatePdf(array $result): JsonResponse
    {
        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'margin_top' => 70,
            'margin_footer' => 5,
            'default_font' => 'Arial',
        ]);

        $mpdf->useAdobeCJK = true;
        $mpdf->autoScriptToLang = true;
        $mpdf->autoLangToFont = true;
        $mpdf->allow_charset_conversion = true;

        $header = $this->header($result);
        $footer = $this->footer();

        $mpdf->SetHTMLHeader($header);
        $mpdf->SetHTMLFooter($footer);

        $content = '
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset="utf-8">
                <title>Blood Test Report</title>
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
                </style>
            </head>
            <body>
                <div class="content">
                    <table style="width: 100%; border-collapse: collapse; font-size:11.5px; margin-top:15px; text-align:left;">
                        <thead>
                            <tr>
                                <th style="padding:2px 0px 2px 15px;text-align:left;text-transform:uppercase;width:500px;border-top:1px solid #000; border-bottom:1px solid #000;">Analytes</th>
                                <th style="padding:2px 0px 2px 0px;text-align:left;text-transform:uppercase;width:60px;border-top:1px solid #000; border-bottom:1px solid #000;">Results</th>
                                <th style="padding:2px 0px 2px 0px;text-align:left;text-transform:uppercase;width:80px;border-top:1px solid #000; border-bottom:1px solid #000;">Units</th>
                                <th style="padding:2px 0px 2px 0px;text-align:left;text-transform:uppercase;width:90px;border-top:1px solid #000; border-bottom:1px solid #000;">Ref. Ranges</th>
                            </tr>
                        </thead>
                        <tbody>
                            ';

        // Pagination: Profile name only on first page, max 4 panels per page
        $pageIndex = 0;
        $showProfileName = true; // Only show on first page
        $allTestsRequested = []; // Collect all tests/categories for the final summary

        // FIRST: Display profile name at the very top if profiles exist
        $content .= $this->displayProfileName($result, $showProfileName);

        // SECOND: Display profiles if they exist
        if (isset($result['data']['profiles'])) {
            foreach ($result['data']['profiles'] as $pr) {
                $profile_name = $pr['profile_name'];
                if (isset($pr['categories'])) {
                    foreach ($pr['categories'] as $cat) {
                        $category_name = $cat['category_name'];
                        $allTestsRequested[] = $category_name; // Collect category name

                        if (isset($cat['panels'])) {
                            // Group panels into chunks - max 2 per page if any panel has comments, otherwise 4
                            $panelChunks = $this->createPanelChunks($cat['panels']);

                            foreach ($panelChunks as $chunkIndex => $panelGroup) {
                                // Force page break before each page (except first)
                                if ($pageIndex > 0) {
                                    // Close current table
                                    $content .= '
                                    </tbody>
                                </table>
                            </div>';

                                    // Write current content and add page break using mPDF
                                    $mpdf->WriteHTML($content);
                                    $mpdf->AddPage();

                                    // Reset content and start new page with table
                                    $content = '
                            <div class="content">
                                <table style="width: 100%; border-collapse: collapse; font-size:11.5px; margin-top:92px; text-align:left;">
                                    <thead>
                                        <tr>
                                            <th style="padding:2px 0px 2px 15px;text-align:left;text-transform:uppercase;width:500px;border-top:1px solid #000; border-bottom:1px solid #000;">Analytes</th>
                                            <th style="padding:2px 0px 2px 0px;text-align:left;text-transform:uppercase;width:60px;border-top:1px solid #000; border-bottom:1px solid #000;">Results</th>
                                            <th style="padding:2px 0px 2px 0px;text-align:left;text-transform:uppercase;width:80px;border-top:1px solid #000; border-bottom:1px solid #000;">Units</th>
                                            <th style="padding:2px 0px 2px 0px;text-align:left;text-transform:uppercase;width:90px;border-top:1px solid #000; border-bottom:1px solid #000;">Ref. Ranges</th>
                                        </tr>
                                    </thead>
                                    <tbody>';
                                }

                                // Add category header (show on first chunk of each category)
                                if ($chunkIndex == 0) {
                                    $content .= '<tr>
                                            <td colspan="4" style="padding:15px 0px 5px 0px;font-style:light;font-weight:bold;text-transform:uppercase;">' . $category_name . '</td>
                                        </tr>';
                                }

                                // Add panels for this chunk (max 4)
                                foreach ($panelGroup as $pl) {
                                    $panel_name = $pl['panel_name'];

                                    $content .= '<tr>
                                                <td colspan="4" style="padding:10px 0px 10px 10px;font-style:light;font-weight:bold;text-transform:uppercase;">' . $panel_name . '</td>
                                                </tr>';

                                    if (isset($pl['panel_items'])) {
                                        $panelItems = $pl['panel_items'];

                                        // Custom sorting for panel_category_id = 4 (HAE sequence)
                                        if (isset($pl['panel_category_id']) && $pl['panel_category_id'] == 4) {
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

                                            usort($panelItems, function ($a, $b) use ($haeOrder) {
                                                $posA = array_search($a['panel_item_name'], $haeOrder);
                                                $posB = array_search($b['panel_item_name'], $haeOrder);

                                                // If both items are in the custom order
                                                if ($posA !== false && $posB !== false) {
                                                    return $posA - $posB;
                                                }

                                                // If only A is in custom order, A comes first
                                                if ($posA !== false && $posB === false) {
                                                    return -1;
                                                }

                                                // If only B is in custom order, B comes first
                                                if ($posA === false && $posB !== false) {
                                                    return 1;
                                                }

                                                // If neither is in custom order, use result_sequence
                                                return $a['result_sequence'] - $b['result_sequence'];
                                            });
                                        }

                                        foreach ($panelItems as $pi) {
                                            $colspan = 'none';
                                            // if (empty($pi['panel_item_unit'])) {
                                            //     $colspan = 4;
                                            // }

                                            $isSpecialItem = in_array($pi['panel_item_name'], ['Haemoglobin', 'White Cell Count', 'Platelets']);
                                            $boldStyle = $isSpecialItem ? 'font-style:light;font-weight:bold;' : '';

                                            $isSpecialPadding = in_array($pi['panel_item_name'], ['White Cell Count', 'Platelets']);
                                            $paddingStyle = $isSpecialPadding ? 'padding: 20px 0px;' : '';

                                            $formattedStyle = '';
                                            if (!empty($pi['result_flag'])) {
                                                $formattedStyle = 'font-weight:bold;text-decoration:underline';
                                            }
                                            if ($pi['panel_item_name'] == 'Blood Film') {

                                                $content .= '<tr>
                                                                <td style="font-family: Courier New;font-stretch: expanded;padding:10px 30px;"><span style="font-weight:bold;margin-right:10px;">FILM:</span>' . $pi['result_value'] . '</td>
                                                                </tr>';
                                            } else if ($pi['panel_item_id'] == 25) {
                                                $content .= '<tr>
                                                                <td style="font-family: Courier New;font-stretch: expanded;padding:10px 30px;">
                                                                ' . nl2br(str_replace('\.br\\', '<br>', $pi['result_value'])) . '
                                                                </td>
                                                                </tr>';
                                            } else if ($pi['panel_item_id'] == 26) {
                                                $content .= '<tr>
                                                                <td style="font-family: Courier New;font-stretch: expanded;padding:10px 30px;">Group:
                                                                ' . nl2br(str_replace('\.br\\', '<br>', $pi['result_value'])) . '
                                                                </td>
                                                                </tr>';
                                            } else {
                                                $name = $pi['panel_item_name'];
                                                $displayName = $this->formatPanelItemName($name);
                                                $content .= '<tr>
                                                                        <td style="padding:3px 10px;">
                                                                            <table style="border-collapse:collapse; width:100%; table-layout:fixed;">
                                                                                <tr>
                                                                                    <td style="padding:0; font-weight:bold; width:15px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;text-align:center;">
                                                                                        ' . $pi['result_flag'] . '
                                                                                    </td>
                                                                                    <td style="padding:0; width:240px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; ' . $boldStyle .  ' ' . $paddingStyle . ' ">'
                                                    . $displayName .
                                                    '</td>
                                                                                    <td style="padding:0; width:120px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">' . $pi['chinese_character'] . '</td>
                                                                                    <td style="padding:0px 10px 0px 0px; text-align:right; width:125px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
                                                                                        ' . ($pi['percentage_value'] != '0' && $pi['is_percentage'] ? $pi['percentage_value'] . '%' : '') . '
                                                                                    </td>
                                                                                </tr>
                                                                            </table>
                                                                        </td>
                                                                        <td colspan=' . $colspan . ' style="' . $formattedStyle . '">' . str_replace(["\\H\\", "\\N\\", ".\\br\\"], ['', '', '<br>'], $pi['result_value']) . '</td>
                                                                        <td>' . $pi['panel_item_unit'] . '</td>
                                                                        <td>' . $pi['reference_range'] . '</td>
                                                                    </tr> ';
                                            }
                                        }
                                    }

                                    // Add panel comments if they exist
                                    if (!empty($pl['panel_comments'])) {
                                        foreach ($pl['panel_comments'] as $comment) {
                                            $formattedComment = $this->formatPanelComment($comment['comment']);
                                            $content .= '<tr>
                                                            <td colspan="4" style="font-family: Courier New;font-stretch: expanded;padding:15px 15px 5px 15px;">
                                                                ' . $formattedComment . '
                                                            </td>
                                                        </tr>';
                                        }
                                    }
                                }
                                $pageIndex++;
                            }
                        }
                    }
                }
            }
        }

        // THIRD: Display standalone panels (not belonging to profiles or categories)
        if (isset($result['data']['panels'])) {
            foreach ($result['data']['panels'] as $pl) {
                $panel_name = $pl['panel_name'];
                $allTestsRequested[] = $panel_name; // Collect standalone panel name

                $content .= '<tr>
                                <td colspan="4" style="padding:10px 0px 10px 10px;font-style:light;font-weight:bold;text-transform:uppercase;">' . $panel_name . '</td>
                                </tr>';

                // Sort panel items by result_sequence
                if (isset($pl['panel_items'])) {
                    usort($pl['panel_items'], function ($a, $b) {
                        return $a['result_sequence'] - $b['result_sequence'];
                    });

                    foreach ($pl['panel_items'] as $pi) {
                        $formattedStyle = '';
                        if (!empty($pi['result_flag'])) {
                            $formattedStyle = 'font-weight:bold;text-decoration:underline';
                        }

                        if ($pi['panel_item_name'] == 'Blood Film') {
                            $content .= '<tr>
                                            <td style="font-family: Courier New;font-stretch: expanded;padding:10px 30px;"><span style="font-weight:bold;margin-right:10px;">FILM:</span>' . $pi['result_value'] . '</td>
                                            </tr>';
                        } else if ($pi['panel_item_id'] == 25) {
                            $content .= '<tr>
                                            <td style="font-family: Courier New;font-stretch: expanded;padding:10px 30px;">
                                            ' . nl2br(str_replace('\.br\\', '<br>', $pi['result_value'])) . '
                                            </td>
                                            </tr>';
                        } else if ($pi['panel_item_id'] == 26) {
                            $content .= '<tr>
                                            <td style="font-family: Courier New;font-stretch: expanded;padding:10px 30px;">Group:
                                            ' . $pi['result_value'] . '
                                            </td>
                                            </tr>';
                        } else {
                            $name = $pi['panel_item_name'];
                            $displayName = $this->formatPanelItemName($name);
                            $content .= '<tr>
                                                <td style="padding:0px 10px;">
                                                    <table style="border-collapse:collapse; width:100%; table-layout:fixed;">
                                                        <tr>
                                                            <td style="padding:0; font-weight:bold; width:15px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;text-align:center;">
                                                                ' . $pi['result_flag'] . '
                                                            </td>
                                                            <td style="padding:0; width:240px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">'
                                . $displayName .
                                '</td>
                                                            <td style="padding:0; width:90px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; text-align:center; ' . $formattedStyle . '">'
                                . $pi['result_value'] .
                                '</td>
                                                            <td style="padding:0; width:80px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;text-align:center;">'
                                . $pi['panel_item_unit'] .
                                '</td>
                                                            <td style="padding:0; width:80px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;text-align:center; font-size:10px;">'
                                . $pi['reference_range'] .
                                '</td>
                                                        </tr>
                                                    </table>
                                                </td>
                                            </tr>';
                        }

                        // Add item comments if they exist
                        if (!empty($pi['item_comments'])) {
                            foreach ($pi['item_comments'] as $comment) {
                                $formattedComment = $this->formatPanelComment($comment['comment']);
                                $content .= '<tr>
                                                <td colspan="4" style="font-family: Courier New;font-stretch: expanded;padding:15px 15px 5px 15px;">
                                                    ' . $formattedComment . '
                                                </td>
                                            </tr>';
                            }
                        }
                    }

                    // Add panel comments if they exist
                    if (!empty($pl['panel_comments'])) {
                        foreach ($pl['panel_comments'] as $comment) {
                            $formattedComment = $this->formatPanelComment($comment['comment']);
                            $content .= '<tr>
                                            <td colspan="4" style="font-family: Courier New;font-stretch: expanded;padding:15px 15px 5px 15px;">
                                                ' . $formattedComment . '
                                            </td>
                                        </tr>';
                        }
                    }
                }
            }
        }

        // FOURTH: Display standalone categories (not belonging to profiles)
        if (isset($result['data']['categories'])) {
            foreach ($result['data']['categories'] as $cat) {
                $category_name = $cat['category_name'];
                $allTestsRequested[] = $category_name; // Collect standalone category name

                if (isset($cat['panels'])) {
                    // Group panels into chunks - max 2 per page if any panel has comments, otherwise 4
                    $panelChunks = $this->createPanelChunks($cat['panels']);

                    foreach ($panelChunks as $chunkIndex => $panelGroup) {
                        // Force page break before each page (except first)
                        if ($pageIndex > 0) {
                            // Close current table
                            $content .= '
                                    </tbody>
                                </table>
                            </div>';

                                // Write current content and add page break using mPDF
                                $mpdf->WriteHTML($content);
                                $mpdf->AddPage();

                                // Reset content and start new page with table
                                $content = '
                            <div class="content">
                                <table style="width: 100%; border-collapse: collapse; font-size:11.5px; margin-top:92px; text-align:left;">
                                    <thead>
                                        <tr>
                                            <th style="padding:2px 0px 2px 15px;text-align:left;text-transform:uppercase;width:500px;border-top:1px solid #000; border-bottom:1px solid #000;">Analytes</th>
                                            <th style="padding:2px 0px 2px 0px;text-align:left;text-transform:uppercase;width:60px;border-top:1px solid #000; border-bottom:1px solid #000;">Results</th>
                                            <th style="padding:2px 0px 2px 0px;text-align:left;text-transform:uppercase;width:80px;border-top:1px solid #000; border-bottom:1px solid #000;">Units</th>
                                            <th style="padding:2px 0px 2px 0px;text-align:left;text-transform:uppercase;width:90px;border-top:1px solid #000; border-bottom:1px solid #000;">Ref. Ranges</th>
                                        </tr>
                                    </thead>
                                    <tbody>';
                        }

                        // Add category header (show on first chunk of each category)
                        if ($chunkIndex == 0) {
                            $content .= '<tr>
                                        <td colspan="4" style="padding:15px 0px 5px 0px;font-style:light;font-weight:bold;text-transform:uppercase;">' . $category_name . '</td>
                                    </tr>';
                        }

                        // Add panels for this chunk (max 4)
                        foreach ($panelGroup as $pl) {
                            $panel_name = $pl['panel_name'];

                            $content .= '<tr>
                                            <td colspan="4" style="padding:10px 0px 10px 10px;font-style:light;font-weight:bold;text-transform:uppercase;">' . $panel_name . '</td>
                                            </tr>';

                            if (isset($pl['panel_items'])) {
                                $panelItems = $pl['panel_items'];

                                // Custom sorting for panel_category_id = 4 (HAE sequence)
                                if (isset($pl['panel_category_id']) && $pl['panel_category_id'] == 4) {
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

                                    usort($panelItems, function ($a, $b) use ($haeOrder) {
                                        $posA = array_search($a['panel_item_name'], $haeOrder);
                                        $posB = array_search($b['panel_item_name'], $haeOrder);

                                        // If both items are in the custom order
                                        if ($posA !== false && $posB !== false) {
                                            return $posA - $posB;
                                        }

                                        // If only A is in custom order, A comes first
                                        if ($posA !== false && $posB === false) {
                                            return -1;
                                        }

                                        // If only B is in custom order, B comes first
                                        if ($posA === false && $posB !== false) {
                                            return 1;
                                        }

                                        // If neither is in custom order, use result_sequence
                                        return $a['result_sequence'] - $b['result_sequence'];
                                    });
                                }

                                foreach ($panelItems as $pi) {
                                    $colspan = 'none';
                                    if (empty($pi['panel_item_unit'])) {
                                        $colspan = 1;
                                    }

                                    $formattedStyle = '';
                                    if (!empty($pi['result_flag']) || str_contains($pi['result_value'], '\H')) {
                                        $formattedStyle = 'font-weight:bold;text-decoration:underline';
                                    }

                                    if ($pi['panel_item_name'] == 'Blood Film') {
                                        $content .= '<tr>
                                                    <td style="font-family: Courier New;font-stretch: expanded;padding:10px 30px;"><span style="font-weight:bold;margin-right:10px;">FILM:</span>' . $pi['result_value'] . '</td>
                                                    </tr>';
                                    } else if ($pi['panel_item_id'] == 25) {
                                        $content .= '<tr>
                                                    <td style="font-family: Courier New;font-stretch: expanded;padding:10px 30px;">
                                                    ' . nl2br(str_replace('\.br\\', '<br>', $pi['result_value'])) . '
                                                    </td>
                                                    </tr>';
                                    } else if ($pi['panel_item_id'] == 26) {
                                        $content .= '<tr>
                                                                    <td style="font-family: Courier New;font-stretch: expanded;padding:10px 30px;">Group:
                                                                    ' . nl2br(str_replace('\.br\\', '<br>', $pi['result_value'])) . '
                                                                    </td>
                                                                    </tr>';
                                    } else {
                                        $name = $pi['panel_item_name'];
                                        $displayName = $this->formatPanelItemName($name);
                                        $content .= '<tr>
                                                                        <td style="padding:0px 10px;">
                                                                            <table style="border-collapse:collapse; width:100%; table-layout:fixed;">
                                                                                <tr>
                                                                                    <td style="padding:0; font-weight:bold; width:15px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;text-align:center;">
                                                                                        ' . $pi['result_flag'] . '
                                                                                    </td>
                                                                                    <td style="padding:0; width:240px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">'
                                            . $displayName .
                                            '</td>
                                                                                    <td style="padding:0; width:120px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">' . $pi['chinese_character'] . '</td>
                                                                                    <td style="padding:0px 10px 0px 0px; text-align:right; width:125px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
                                                                                        ' . ($pi['percentage_value'] != '0' && $pi['is_percentage'] ? $pi['percentage_value'] . '%' : '') . '
                                                                                    </td>
                                                                                </tr>
                                                                            </table>
                                                                        </td>
                                                                        <td colspan=' . $colspan . ' style="' . $formattedStyle . '">' . str_replace(["\\H\\", "\\N\\", ".\\br\\"], ['', '', '<br>'], $pi['result_value']) . '</td>
                                                                        <td>' . $pi['panel_item_unit'] . '</td>
                                                                        <td>' . $pi['reference_range'] . '</td>
                                                                    </tr> ';
                                    }
                                }

                                // Add panel comments if they exist
                                if (!empty($pl['panel_comments'])) {
                                    foreach ($pl['panel_comments'] as $comment) {
                                        $formattedComment = $this->formatPanelComment($comment['comment']);
                                        $content .= '<tr>
                                                        <td colspan="4" style="font-family: Courier New;font-stretch: expanded;padding:15px 15px 5px 15px;">
                                                            ' . $formattedComment . '
                                                        </td>
                                                    </tr>';
                                    }
                                }
                            }
                        }
                        $pageIndex++;
                    }
                }
            }
        }

        // Add REPORT COMPLETED section at the end
        if (!empty($allTestsRequested)) {
            // Remove duplicates and sort alphabetically
            $uniqueTests = array_unique($allTestsRequested);
            sort($uniqueTests);
            $testsString = implode(', ', $uniqueTests);

            $content .= '
                            <tr>
                                <td colspan="4" style=" padding-top: 25px;text-align:center;">
                                    <div style="text-align:center;font-weight:bold; font-size:12px; text-decoration:underline; margin-bottom:10px;">
                                        REPORT COMPLETED
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td colspan="4" style="font-size:11.5px;">
                                    Tests Requested:<br>
                                    ' . strtoupper($testsString) . '
                                </td>
                            </tr>';
        }

        $content .= '
                    </tbody>
                </table>
            </div>
        </body>
        </html>';

        // Write the final content
        $mpdf->WriteHTML($content);

        // Get PDF content as string and convert to base64
        $pdfContent = $mpdf->Output('', 'S');
        $base64Pdf = base64_encode($pdfContent);

        return response()->json([
            'success' => true,
            'report_id' => $result['test_result_id'],
            'pdf' => $base64Pdf,
            'message' => 'PDF generated successfully',
        ], 200);
    }

    /**
     * Generate a PDF for a TestResult by its primary key.
     *
     * Loads the TestResult with all required eager-loaded relationships,
     * validates that it is completed and reviewed, builds the result array
     * using the same logic as processTestResult(), then delegates to generatePdf().
     *
     * @param int $testResultId The primary key of the TestResult record
     * @return JsonResponse
     */
    public function exportByTestResultId(int $testResultId, bool $requireReviewed = true): JsonResponse
    {
        Log::info('exportByTestResultId: Starting', ['test_result_id' => $testResultId]);

        $testResult = TestResult::with([
            'doctor',
            'patient',
            'testResultProfiles',
            'testResultItems.panelComments.masterPanelComment',
            'profiles'
        ])->find($testResultId);

        if (!$testResult) {
            Log::warning('exportByTestResultId: TestResult not found', ['test_result_id' => $testResultId]);
            return response()->json([
                'success' => false,
                'message' => 'Test result not found',
                'error'   => 'PDF can only be generated for completed test results'
            ], 404);
        }

        if (!$testResult->is_completed || ($requireReviewed && !$testResult->is_reviewed)) {
            Log::warning('exportByTestResultId: TestResult not completed or not reviewed', [
                'test_result_id' => $testResultId,
                'is_completed'   => $testResult->is_completed,
                'is_reviewed'    => $testResult->is_reviewed,
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Test result not completed or not reviewed yet',
                'error'   => 'PDF can only be generated for completed and reviewed test results'
            ], 404);
        }

        Log::info('exportByTestResultId: TestResult found, building result array', ['test_result_id' => $testResultId]);

        $dob = $testResult->patient->dob != null ? Carbon::createFromFormat('Ymd', str_replace('-', '', $testResult->patient->dob))->format('d/m/y') : null;

        // Patient information
        $patient_info = [
            'name' => $testResult->patient->name,
            'dob' => $dob,
            'icno' => substr_replace($testResult->patient->icno, 'XXXX', -4), //censored last 4 digits for data privacy
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

        $profilesData = [];

        // Determine sequence source based on whether test result has profiles
        $hasProfiles = count($testResult->profiles) > 0;

        if ($hasProfiles) {
            foreach ($testResult->testResultProfiles as $trp) {
                $ppps = PanelPanelProfile::with(['panel', 'panelProfile'])->where('panel_profile_id', $trp->panel_profile_id)->get();
                foreach ($ppps as $ppp) {
                    $profilesData[$testResult->id]['profiles'][$ppp->panel_profile_id]['profile_id'] = $ppp->panelProfile->id;
                    $profilesData[$testResult->id]['profiles'][$ppp->panel_profile_id]['profile_name'] = $ppp->panelProfile->name;
                    $profilesData[$testResult->id]['profiles'][$ppp->panel_profile_id]['panels'][$ppp->panel_id]['panel_name'] = $ppp->panel->name;
                    $profilesData[$testResult->id]['profiles'][$ppp->panel_profile_id]['panels'][$ppp->panel_id]['panel_profile_sequence'] = $ppp->sequence;
                }
            }
        }

        // Build hierarchical structure: Profile > Category > Panel > Panel Item
        $hierarchicalData = [];
        $combinedItems = []; // Store items to combine percentage and absolute values

        foreach ($testResult->testResultItems as $ri) {
            // Extract all data from result item
            $res_value = $ri->value;
            $res_flag = $ri->flag != 'N' ? '*' : '';
            $res_sequence = $ri->sequence;
            $is_tagon = $ri->is_tagon;
            $ref_range_id = $ri->reference_range_id;

            // Find panel panel item with all related data
            $ppi = PanelPanelItem::with([
                'panel',
                'panel.panelCategory',
                'panelItem',
                'referenceRanges' => function ($query) use ($ref_range_id) {
                    $query->where('id', $ref_range_id);
                }
            ])->find($ri->panel_panel_item_id);

            // Get item-specific comments from TestResultItem panelComments relationship
            $itemComments = [];
            foreach ($ri->panelComments as $panelComment) {
                if ($panelComment->masterPanelComment) {
                    $itemComments[] = [
                        'comment' => $panelComment->masterPanelComment->comment
                    ];
                }
            }

            $reference_range = $ppi->referenceRanges->first()->value ?? null;
            if ($reference_range) {
                $reference_range = str_replace(['(', ')'], '', $reference_range);
            }

            $unit = $ppi->panelItem->unit;

            if (!empty($unit)) {
                // Convert all *digits into <sup>digits</sup>
                $unit = preg_replace('/\*(\d+)/', '<sup>$1</sup>', $unit);
                $unit = preg_replace('/([a-zA-Z])(\d+)/', '$1<sup>$2</sup>', $unit);
            }

            // Panel item data
            $panelItemData = [
                'panel_item_id' => $ppi->panel_item_id,
                'panel_item_name' => $ppi->panelItem->name,
                'chinese_character' => $ppi->panelItem->chi_character,
                'panel_item_unit' => $unit,
                'result_value' => $res_value,
                'result_flag' => $res_flag,
                'is_tagon' => $is_tagon,
                'result_sequence' => $res_sequence,
                'reference_range' => $reference_range != null ? '(' . $reference_range . ')' : '',
                'is_percentage' => false,
                'percentage_value' => null,
                'item_comments' => $itemComments
            ];

            // Check if this is a combinable item (percentage or absolute)
            $itemName = $ppi->panelItem->name;
            $itemUnit = $ppi->panelItem->unit;
            $isPercentage = $itemUnit === '%';
            $isAbsolute = $itemUnit === 'x 10*9/L';

            // Clean name for comparison (remove " %" suffix)
            $cleanItemName = str_replace(' %', '', $itemName);
            $combinableNames = ['Neutrophils', 'Lymphocytes', 'Monocytes', 'Eosinophils', 'Basophils'];
            $isCombinable = in_array($cleanItemName, $combinableNames);

            if ($isCombinable) {
                $baseKey = $cleanItemName . '_' . $ppi->panel_id; // Use clean name for grouping

                if (!isset($combinedItems[$baseKey])) {
                    $combinedItems[$baseKey] = [];
                }

                if ($isPercentage) {
                    $combinedItems[$baseKey]['percentage'] = $panelItemData;
                } elseif ($isAbsolute) {
                    $combinedItems[$baseKey]['absolute'] = $panelItemData;
                }
            } else {
                // Store the panel item data with hierarchy info for processing later
                $panelItemData['_hierarchy_info'] = [
                    'panel_id' => $ppi->panel_id,
                    'panel' => $ppi->panel
                ];
                $hierarchicalData['_temp_items'][] = $panelItemData;
            }
        }

        // Process combined items - merge percentage and absolute values
        foreach ($combinedItems as $baseKey => $items) {
            if (isset($items['absolute'])) {
                $finalItem = $items['absolute']; // Use absolute as base

                if (isset($items['percentage'])) {
                    $finalItem['is_percentage'] = true;
                    $finalItem['percentage_value'] = $items['percentage']['result_value'];
                    // Merge comments from both percentage and absolute items
                    $finalItem['item_comments'] = array_merge(
                        $finalItem['item_comments'] ?? [],
                        $items['percentage']['item_comments'] ?? []
                    );
                }

                // Store the final combined item with hierarchy info
                $ppi = PanelPanelItem::with([
                    'panel',
                    'panel.panelCategory',
                ])->where('panel_item_id', $finalItem['panel_item_id'])
                    ->first();

                $finalItem['_hierarchy_info'] = [
                    'panel_id' => $ppi->panel_id,
                    'panel' => $ppi->panel,
                ];
                $hierarchicalData['_temp_items'][] = $finalItem;
            }
        }

        // Now process all items (both combined and regular) into the hierarchy
        $processedItems = $hierarchicalData['_temp_items'] ?? [];
        $hierarchicalData = []; // Reset to build properly

        foreach ($processedItems as $panelItemData) {
            $ppi = (object)$panelItemData['_hierarchy_info'];
            unset($panelItemData['_hierarchy_info']); // Remove temporary hierarchy info

            // Determine hierarchy based on priority: Profile > Category > Panel > Panel Item
            $profileId = null;
            $profileName = null;
            $profileSequence = null;

            // Check if this panel belongs to any profile
            if ($hasProfiles && isset($profilesData[$testResult->id]['profiles'])) {
                foreach ($profilesData[$testResult->id]['profiles'] as $profileData) {
                    if (isset($profileData['panels'][$ppi->panel_id])) {
                        $profileId = $profileData['profile_id'];
                        $profileName = $profileData['profile_name'];
                        $profileSequence = $profileData['panels'][$ppi->panel_id]['panel_profile_sequence'];
                        break;
                    }
                }
            }

            // Build hierarchical structure based on available data
            if ($hasProfiles && $profileId) {
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
                        'panel_category_id' => $ppi->panel->panel_category_id,
                        'panel_comments' => [], // Will be built from item comments
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
                        'panel_comments' => [], // Will be built from item comments
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
                        'panel_category_id' => $ppi->panel->panel_category_id,
                        'panel_comments' => [], // Will be built from item comments
                        'panel_items' => []
                    ];
                }

                $hierarchicalData['panels'][$ppi->panel_id]['panel_items'][] = $panelItemData;
            }
        }

        // Build panel comments from item comments
        $this->buildPanelCommentsFromItems($hierarchicalData);

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

                        // Sort panel items within each panel - custom sorting for panel_category_id = 4
                        foreach ($category['panels'] as &$panel) {
                            if (isset($panel['panel_items']) && $category['category_id'] == 4) {
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
                                foreach ($panel['panel_items'] as $item) {
                                    $itemsByName[$item['panel_item_name']] = $item;
                                }

                                // Build ordered array following HAE sequence
                                foreach ($haeOrder as $expectedName) {
                                    if (isset($itemsByName[$expectedName])) {
                                        $orderedItems[] = $itemsByName[$expectedName];
                                    }
                                }

                                // Add any remaining items that weren't in our sequence
                                foreach ($panel['panel_items'] as $item) {
                                    $itemName = $item['panel_item_name'];
                                    if (!in_array($itemName, $haeOrder)) {
                                        $orderedItems[] = $item;
                                    }
                                }

                                // Replace the panel items with our ordered items
                                $panel['panel_items'] = $orderedItems;
                            } elseif (isset($panel['panel_items'])) {
                                // Default sorting by result_sequence for other panels
                                usort($panel['panel_items'], function ($a, $b) {
                                    return $a['result_sequence'] - $b['result_sequence'];
                                });
                            }
                        }
                        unset($panel);
                    }
                    unset($category);
                }
            }
            unset($profile);
        }

        // Sort panel items for categories not in profiles
        if (isset($hierarchicalData['categories'])) {
            foreach ($hierarchicalData['categories'] as &$category) {
                if (isset($category['panels'])) {
                    foreach ($category['panels'] as &$panel) {
                        if (isset($panel['panel_items']) && $category['category_id'] == 4) {
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
                            foreach ($panel['panel_items'] as $item) {
                                $itemsByName[$item['panel_item_name']] = $item;
                            }

                            // Build ordered array following HAE sequence
                            foreach ($haeOrder as $expectedName) {
                                if (isset($itemsByName[$expectedName])) {
                                    $orderedItems[] = $itemsByName[$expectedName];
                                }
                            }

                            // Add any remaining items that weren't in our sequence
                            foreach ($panel['panel_items'] as $item) {
                                $itemName = $item['panel_item_name'];
                                if (!in_array($itemName, $haeOrder)) {
                                    $orderedItems[] = $item;
                                }
                            }

                            // Replace the panel items with our ordered items
                            $panel['panel_items'] = $orderedItems;
                        } elseif (isset($panel['panel_items'])) {
                            usort($panel['panel_items'], function ($a, $b) {
                                return $a['result_sequence'] - $b['result_sequence'];
                            });
                        }
                    }
                    unset($panel);
                }
            }
            unset($category);
        }

        // Sort panel items for panels not in categories
        if (isset($hierarchicalData['panels'])) {
            foreach ($hierarchicalData['panels'] as &$panel) {
                if (isset($panel['panel_items']) && $panel['panel_category_id'] == 4) {
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
                    foreach ($panel['panel_items'] as $item) {
                        $itemsByName[$item['panel_item_name']] = $item;
                    }

                    // Build ordered array following HAE sequence
                    foreach ($haeOrder as $expectedName) {
                        if (isset($itemsByName[$expectedName])) {
                            $orderedItems[] = $itemsByName[$expectedName];
                        }
                    }

                    // Add any remaining items that weren't in our sequence
                    foreach ($panel['panel_items'] as $item) {
                        $itemName = $item['panel_item_name'];
                        if (!in_array($itemName, $haeOrder)) {
                            $orderedItems[] = $item;
                        }
                    }

                    // Replace the panel items with our ordered items
                    $panel['panel_items'] = $orderedItems;
                } elseif (isset($panel['panel_items'])) {
                    usort($panel['panel_items'], function ($a, $b) {
                        return $a['result_sequence'] - $b['result_sequence'];
                    });
                }
            }
            unset($panel);
        }

        // Remove profiles structure if no profiles exist
        if (!$hasProfiles && isset($hierarchicalData['profiles'])) {
            unset($hierarchicalData['profiles']);
        }

        // Convert arrays to use sequential keys while maintaining structure
        if (isset($hierarchicalData['profiles'])) {
            $hierarchicalData['profiles'] = array_values($hierarchicalData['profiles']);
            foreach ($hierarchicalData['profiles'] as &$profile) {
                if (isset($profile['categories'])) {
                    $profile['categories'] = array_values($profile['categories']);
                    foreach ($profile['categories'] as &$category) {
                        if (isset($category['panels'])) {
                            $category['panels'] = array_values($category['panels']);
                        }
                    }
                    unset($category);
                }
            }
            unset($profile);
        }

        if (isset($hierarchicalData['categories'])) {
            $hierarchicalData['categories'] = array_values($hierarchicalData['categories']);
            foreach ($hierarchicalData['categories'] as &$category) {
                if (isset($category['panels'])) {
                    $category['panels'] = array_values($category['panels']);
                }
            }
            unset($category);
        }

        if (isset($hierarchicalData['panels'])) {
            $hierarchicalData['panels'] = array_values($hierarchicalData['panels']);
        }

        // Assemble the result array in the same shape as processTestResult() returns
        $result = [
            'test_result_id' => $testResult->id,
            'patient_info' => $patient_info,
            'test_dates' => $test_dates,
            'doctor_info' => $doctor_info,
            'lab_info' => $lab_info,
            'data' => $hierarchicalData
        ];

        Log::info('exportByTestResultId: Result array built, delegating to generatePdf', ['test_result_id' => $testResultId]);

        return $this->generatePdf($result);
    }

    /**
     * Generate a PDF for a TestResult by its primary key, for the consult call context.
     *
     * Identical to exportByTestResultId() except that is_reviewed is not required —
     * only is_completed must be true. Consult call staff may view results before
     * the AI review cycle completes.
     *
     * @param int $testResultId The primary key of the TestResult record
     * @return JsonResponse
     */
    public function exportByTestResultIdForConsultCall(int $testResultId): JsonResponse
    {
        return $this->exportByTestResultId($testResultId, false);
    }

    /**
     * Generate a PDF for a TestResult by its primary key, for the nexus context.
     *
     * Identical to exportByTestResultId() except that is_reviewed is not required —
     * only is_completed must be true. 
     *
     * @param int $testResultId The primary key of the TestResult record
     * @return JsonResponse
     */
    public function exportByTestResultIdForNexus(int $testResultId): JsonResponse
    {
        return $this->exportByTestResultId($testResultId, false);
    }

    /**
     * Compile raw data of Test Result and its relationship by IC No and Reference ID
     */
    private function processTestResult(ODBRequest $request)
    {
        $validated = $request->all();
        $item = $validated[0] ?? null;

        if (!$item) {
            return response()->json([
                'success' => false,
                'message' => 'No data provided in request'
            ], 400);
        }

        $icno = $item['icno'];
        $refid = $item['refid'] ?? null;
        $month = $item['month'] ?? null;
        $year  = $item['year'] ?? null;

        $year  = $year  ?: date('Y');
        $month = $month ?: date('m');

        Log::channel($this->getLogChannel())->debug('processTestResult: Starting search', [
            'icno' => $icno,
            'refid' => $refid,
            'month' => $month,
            'year' => $year,
            'date_range_start' => Carbon::create($year, $month, 1)->startOfMonth()->format('Y-m-d H:i:s'),
            'date_range_end' => Carbon::create($year, $month, 1)->endOfMonth()->format('Y-m-d H:i:s'),
            'current_month' => date('m'),
            'step4_eligible' => ($month != date('m')) ? 'yes' : 'no'
        ]);

        $testResult = null;
        $dateRangeStart = Carbon::create($year, $month, 1)->startOfMonth();
        $dateRangeEnd = Carbon::create($year, $month, 1)->endOfMonth();

        // Step 1: If refid provided, try searching by BOTH IC number AND refid
        if ($refid) {
            Log::channel($this->getLogChannel())->debug('processTestResult [STEP 1]: Searching by IC + ref_id', [
                'step' => 1,
                'icno' => $icno,
                'ref_id' => $refid,
                'date_range' => [$dateRangeStart->format('Y-m-d H:i:s'), $dateRangeEnd->format('Y-m-d H:i:s')]
            ]);

            $testResult = TestResult::with([
                'doctor',
                'patient',
                'testResultProfiles',
                'testResultItems.panelComments.masterPanelComment',
                'profiles'
            ])
                ->whereHas('patient', function ($p) use ($icno) {
                    $p->where('icno', $icno);
                })
                ->where('ref_id', $refid)
                ->where('is_completed', true)
                ->where('is_reviewed', true)
                ->whereNotNull('collected_date')
                ->whereBetween('collected_date', [
                    $dateRangeStart,
                    $dateRangeEnd
                ])
                ->latest()
                ->first();

            if ($testResult) {
                Log::channel($this->getLogChannel())->debug('processTestResult [STEP 1]: SUCCESS', [
                    'step' => 1,
                    'test_result_id' => $testResult->id,
                    'collected_date' => $testResult->collected_date,
                    'is_completed' => $testResult->is_completed,
                    'is_reviewed' => $testResult->is_reviewed,
                    'patient_icno' => $testResult->patient->icno
                ]);
            } else {
                Log::channel($this->getLogChannel())->debug('processTestResult [STEP 1]: Not found - proceeding to Step 2', ['step' => 1]);
            }
        }


        // Step 2: Search by IC number only
        if (!$testResult) {
            Log::channel($this->getLogChannel())->debug('processTestResult [STEP 2]: Searching by IC only', [
                'step' => 2,
                'icno' => $icno,
                'ref_id_filter' => ($refid ? 'WHERE ref_id IS NULL' : 'no filter'),
                'date_range' => [$dateRangeStart->format('Y-m-d H:i:s'), $dateRangeEnd->format('Y-m-d H:i:s')]
            ]);

            $query = TestResult::with([
                'doctor',
                'patient',
                'testResultProfiles',
                'testResultItems.panelComments.masterPanelComment',
                'profiles'
            ])
                ->whereHas('patient', function ($p) use ($icno) {
                    $p->where('icno', $icno);
                });

            // Only require NULL ref_id if user provided a refid
            if ($refid) {
                $query->whereNull('ref_id');
            }

            $testResult = $query
                ->where('is_completed', true)
                ->where('is_reviewed', true)
                ->whereNotNull('collected_date')
                ->whereBetween('collected_date', [
                    $dateRangeStart,
                    $dateRangeEnd
                ])
                ->latest()
                ->first();

            // Update ref_id if it's null and we have a refid to set
            if ($testResult && $refid && is_null($testResult->ref_id)) {
                Log::channel($this->getLogChannel())->debug('processTestResult [STEP 2]: Updating ref_id', [
                    'test_result_id' => $testResult->id,
                    'new_ref_id' => $refid
                ]);
                $testResult->ref_id = $refid;
                $testResult->save();
            }

            if ($testResult) {
                Log::channel($this->getLogChannel())->debug('processTestResult [STEP 2]: SUCCESS', [
                    'step' => 2,
                    'test_result_id' => $testResult->id,
                    'collected_date' => $testResult->collected_date,
                    'is_completed' => $testResult->is_completed,
                    'is_reviewed' => $testResult->is_reviewed
                ]);
            } else {
                Log::channel($this->getLogChannel())->debug('processTestResult [STEP 2]: Not found - proceeding to Step 3', ['step' => 2]);
            }
        }

        // Step 3: Fallback to search by refid if provided
        if (!$testResult && $refid) {
            Log::channel($this->getLogChannel())->debug('processTestResult [STEP 3]: Searching by ref_id only', [
                'step' => 3,
                'ref_id' => $refid,
                'date_range' => [$dateRangeStart->format('Y-m-d H:i:s'), $dateRangeEnd->format('Y-m-d H:i:s')]
            ]);

            $testResult = TestResult::with([
                'doctor',
                'patient',
                'testResultProfiles',
                'testResultItems.panelComments.masterPanelComment',
                'profiles'
            ])
                ->where('ref_id', $refid)
                ->where('is_completed', true)
                ->where('is_reviewed', true)
                ->whereNotNull('collected_date')
                ->whereBetween('collected_date', [
                    $dateRangeStart,
                    $dateRangeEnd
                ])
                ->latest()
                ->first();

            // Verify IC mismatch - only return if IC is different
            if ($testResult) {
                $foundIcno = $testResult->patient->icno ?? null;

                Log::channel($this->getLogChannel())->debug('processTestResult [STEP 3]: Found record - verifying IC match', [
                    'step' => 3,
                    'found_icno' => $foundIcno,
                    'provided_icno' => $icno,
                    'ic_matches' => ($foundIcno === $icno) ? 'yes' : 'no'
                ]);

                if ($foundIcno === $icno) {
                    Log::channel($this->getLogChannel())->debug('processTestResult [STEP 3]: REJECTING - IC matches (Step 3 is for IC mismatch only)', [
                        'step' => 3,
                        'action' => 'rejected',
                        'reason' => 'IC matches provided IC',
                        'test_result_id' => $testResult->id
                    ]);
                    // IC matches - reject to avoid returning mismatched record
                    $testResult = null;
                }
            }

            if (!$testResult) {
                Log::channel($this->getLogChannel())->debug('processTestResult [STEP 3]: Not found (or rejected) - proceeding to Step 4', ['step' => 3]);
            } else {
                Log::channel($this->getLogChannel())->debug('processTestResult [STEP 3]: SUCCESS - Found with IC mismatch', [
                    'step' => 3,
                    'test_result_id' => $testResult->id
                ]);
            }
        }

        //Step 4: Check with manual sync for unmatch date
        Log::channel($this->getLogChannel())->debug('processTestResult [STEP 4]: Manual sync eligibility check', [
            'step' => 4,
            'provided_month' => $month,
            'current_month' => date('m'),
            'eligible' => ($month != date('m')),
            'has_refid' => !empty($refid)
        ]);

        if (!$testResult && $month != date('m') && $refid) {
            Log::channel($this->getLogChannel())->debug('processTestResult [STEP 4]: Searching by manual_sync_date', [
                'step' => 4,
                'icno' => $icno,
                'ref_id' => $refid
            ]);

            $testResult = TestResult::whereHas('patient', function ($p) use ($icno) {
                $p->where('icno', $icno);
            })
                ->where('ref_id', $refid)
                ->where('is_completed', true)
                ->where('is_reviewed', true)
                ->whereNotNull('manual_sync_date')
                ->latest()->first();

            if ($testResult) {
                Log::channel($this->getLogChannel())->debug('processTestResult [STEP 4]: SUCCESS', [
                    'step' => 4,
                    'test_result_id' => $testResult->id,
                    'manual_sync_date' => $testResult->manual_sync_date
                ]);
            } else {
                Log::channel($this->getLogChannel())->debug('processTestResult [STEP 4]: Not found', ['step' => 4]);
            }
        }

        if (!$testResult) {
            // Diagnostic queries
            $icOnlyCount = TestResult::whereHas('patient', function ($p) use ($icno) {
                $p->where('icno', $icno);
            })->count();

            $completedCount = TestResult::whereHas('patient', function ($p) use ($icno) {
                $p->where('icno', $icno);
            })->where('is_completed', true)->count();

            $reviewedCount = TestResult::whereHas('patient', function ($p) use ($icno) {
                $p->where('icno', $icno);
            })->where('is_reviewed', true)->count();

            $collectedCount = TestResult::whereHas('patient', function ($p) use ($icno) {
                $p->where('icno', $icno);
            })->whereNotNull('collected_date')->count();

            $recentSample = TestResult::whereHas('patient', function ($p) use ($icno) {
                $p->where('icno', $icno);
            })
            ->select('id', 'ref_id', 'collected_date', 'is_completed', 'is_reviewed')
            ->latest('created_at')
            ->first();

            Log::channel($this->getLogChannel())->error('processTestResult [FINAL]: ALL STEPS FAILED', [
                'success' => false,
                'input' => [
                    'icno' => $icno,
                    'refid' => $refid,
                    'month' => $month,
                    'year' => $year,
                    'date_range' => [$dateRangeStart->format('Y-m-d H:i:s'), $dateRangeEnd->format('Y-m-d H:i:s')]
                ],
                'diagnostics' => [
                    'total_records_for_patient' => $icOnlyCount,
                    'completed_records' => $completedCount,
                    'reviewed_records' => $reviewedCount,
                    'records_with_collected_date' => $collectedCount,
                    'most_recent_record' => $recentSample ? [
                        'id' => $recentSample->id,
                        'ref_id' => $recentSample->ref_id,
                        'collected_date' => $recentSample->collected_date,
                        'is_completed' => $recentSample->is_completed,
                        'is_reviewed' => $recentSample->is_reviewed
                    ] : null
                ]
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Test result not found or not completed yet',
                'error'   => 'PDF can only be generated for completed test results'
            ], 404);
        }

        $dob = $testResult->patient->dob != null ? Carbon::createFromFormat('Ymd', str_replace('-', '', $testResult->patient->dob))->format('d/m/y') : null;

        // Patient information
        $patient_info = [
            'name' => $testResult->patient->name,
            'dob' => $dob,
            // 'icno' => $testResult->patient->icno,
            'icno' => substr_replace($testResult->patient->icno, 'XXXX', -4), //censored last 4 digits for data privacy
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
        $combinedItems = []; // Store items to combine percentage and absolute values

        foreach ($testResult->testResultItems as $ri) {
            // Extract all data from result item
            $res_value = $ri->value;
            $res_flag = $ri->flag != 'N' ? '*' : '';
            $res_sequence = $ri->sequence;
            $is_tagon = $ri->is_tagon;
            $ref_range_id = $ri->reference_range_id;

            // Find panel panel item with all related data
            $ppi = PanelPanelItem::with([
                'panel',
                'panel.panelCategory',
                'panelItem',
                'referenceRanges' => function ($query) use ($ref_range_id) {
                    $query->where('id', $ref_range_id);
                }
            ])->find($ri->panel_panel_item_id);

            // Get item-specific comments from TestResultItem panelComments relationship
            $itemComments = [];
            foreach ($ri->panelComments as $panelComment) {
                if ($panelComment->masterPanelComment) {
                    $itemComments[] = [
                        'comment' => $panelComment->masterPanelComment->comment
                    ];
                }
            }

            $reference_range = $ppi->referenceRanges->first()->value ?? null;
            if ($reference_range) {
                $reference_range = str_replace(['(', ')'], '', $reference_range);
            }

            $unit = $ppi->panelItem->unit;

            if (!empty($unit)) {
                // Convert all *digits into <sup>digits</sup>
                $unit = preg_replace('/\*(\d+)/', '<sup>$1</sup>', $unit);
                $unit = preg_replace('/([a-zA-Z])(\d+)/', '$1<sup>$2</sup>', $unit);
            }

            // Panel item data
            $panelItemData = [
                'panel_item_id' => $ppi->panel_item_id,
                'panel_item_name' => $ppi->panelItem->name,
                'chinese_character' => $ppi->panelItem->chi_character,
                'panel_item_unit' => $unit,
                'result_value' => $res_value,
                'result_flag' => $res_flag,
                'is_tagon' => $is_tagon,
                'result_sequence' => $res_sequence,
                'reference_range' => $reference_range != null ? '(' . $reference_range . ')' : '',
                'is_percentage' => false,
                'percentage_value' => null,
                'item_comments' => $itemComments
            ];

            // Check if this is a combinable item (percentage or absolute)
            $itemName = $ppi->panelItem->name;
            $itemUnit = $ppi->panelItem->unit;
            $isPercentage = $itemUnit === '%';
            $isAbsolute = $itemUnit === 'x 10*9/L';

            // Clean name for comparison (remove " %" suffix)
            $cleanItemName = str_replace(' %', '', $itemName);
            $combinableNames = ['Neutrophils', 'Lymphocytes', 'Monocytes', 'Eosinophils', 'Basophils'];
            $isCombinable = in_array($cleanItemName, $combinableNames);

            if ($isCombinable) {
                $baseKey = $cleanItemName . '_' . $ppi->panel_id; // Use clean name for grouping

                if (!isset($combinedItems[$baseKey])) {
                    $combinedItems[$baseKey] = [];
                }

                if ($isPercentage) {
                    $combinedItems[$baseKey]['percentage'] = $panelItemData;
                } elseif ($isAbsolute) {
                    $combinedItems[$baseKey]['absolute'] = $panelItemData;
                }
            } else {
                // Store the panel item data with hierarchy info for processing later
                $panelItemData['_hierarchy_info'] = [
                    'panel_id' => $ppi->panel_id,
                    'panel' => $ppi->panel
                ];
                $hierarchicalData['_temp_items'][] = $panelItemData;
            }
        }

        // Process combined items - merge percentage and absolute values
        foreach ($combinedItems as $baseKey => $items) {
            if (isset($items['absolute'])) {
                $finalItem = $items['absolute']; // Use absolute as base

                if (isset($items['percentage'])) {
                    $finalItem['is_percentage'] = true;
                    $finalItem['percentage_value'] = $items['percentage']['result_value'];
                    // Merge comments from both percentage and absolute items
                    $finalItem['item_comments'] = array_merge(
                        $finalItem['item_comments'] ?? [],
                        $items['percentage']['item_comments'] ?? []
                    );
                }

                // Store the final combined item with hierarchy info
                $ppi = PanelPanelItem::with([
                    'panel',
                    'panel.panelCategory',
                ])->where('panel_item_id', $finalItem['panel_item_id'])
                    ->first();

                $finalItem['_hierarchy_info'] = [
                    'panel_id' => $ppi->panel_id,
                    'panel' => $ppi->panel,
                ];
                $hierarchicalData['_temp_items'][] = $finalItem;
            }
        }

        // Now process all items (both combined and regular) into the hierarchy
        $processedItems = $hierarchicalData['_temp_items'] ?? [];
        $hierarchicalData = []; // Reset to build properly

        foreach ($processedItems as $panelItemData) {
            $ppi = (object)$panelItemData['_hierarchy_info'];
            unset($panelItemData['_hierarchy_info']); // Remove temporary hierarchy info

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
            if ($hasProfiles && $profileId) {
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
                        'panel_category_id' => $ppi->panel->panel_category_id,
                        'panel_comments' => [], // Will be built from item comments
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
                        'panel_comments' => [], // Will be built from item comments
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
                        'panel_category_id' => $ppi->panel->panel_category_id,
                        'panel_comments' => [], // Will be built from item comments
                        'panel_items' => []
                    ];
                }

                $hierarchicalData['panels'][$ppi->panel_id]['panel_items'][] = $panelItemData;
            }
        }

        // Build panel comments from item comments
        $this->buildPanelCommentsFromItems($hierarchicalData);

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

                        // Sort panel items within each panel - custom sorting for panel_category_id = 4
                        foreach ($category['panels'] as &$panel) {
                            if (isset($panel['panel_items']) && $category['category_id'] == 4) {
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
                                foreach ($panel['panel_items'] as $item) {
                                    $itemsByName[$item['panel_item_name']] = $item;
                                }

                                // Build ordered array following HAE sequence
                                foreach ($haeOrder as $expectedName) {
                                    if (isset($itemsByName[$expectedName])) {
                                        $orderedItems[] = $itemsByName[$expectedName];
                                    }
                                }

                                // Add any remaining items that weren't in our sequence
                                foreach ($panel['panel_items'] as $item) {
                                    $itemName = $item['panel_item_name'];
                                    if (!in_array($itemName, $haeOrder)) {
                                        $orderedItems[] = $item;
                                    }
                                }

                                // Replace the panel items with our ordered items
                                $panel['panel_items'] = $orderedItems;
                            } elseif (isset($panel['panel_items'])) {
                                // Default sorting by result_sequence for other panels
                                usort($panel['panel_items'], function ($a, $b) {
                                    return $a['result_sequence'] - $b['result_sequence'];
                                });
                            }
                        }
                        unset($panel);
                    }
                    unset($category);
                }
            }
            unset($profile);
        }

        // Sort panel items for categories not in profiles
        if (isset($hierarchicalData['categories'])) {
            foreach ($hierarchicalData['categories'] as &$category) {
                if (isset($category['panels'])) {
                    foreach ($category['panels'] as &$panel) {
                        if (isset($panel['panel_items']) && $category['category_id'] == 4) {
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
                            foreach ($panel['panel_items'] as $item) {
                                $itemsByName[$item['panel_item_name']] = $item;
                            }

                            // Build ordered array following HAE sequence
                            foreach ($haeOrder as $expectedName) {
                                if (isset($itemsByName[$expectedName])) {
                                    $orderedItems[] = $itemsByName[$expectedName];
                                }
                            }

                            // Add any remaining items that weren't in our sequence
                            foreach ($panel['panel_items'] as $item) {
                                $itemName = $item['panel_item_name'];
                                if (!in_array($itemName, $haeOrder)) {
                                    $orderedItems[] = $item;
                                }
                            }

                            // Replace the panel items with our ordered items
                            $panel['panel_items'] = $orderedItems;
                        } elseif (isset($panel['panel_items'])) {
                            usort($panel['panel_items'], function ($a, $b) {
                                return $a['result_sequence'] - $b['result_sequence'];
                            });
                        }
                    }
                    unset($panel);
                }
            }
            unset($category);
        }

        // Sort panel items for panels not in categories
        if (isset($hierarchicalData['panels'])) {
            foreach ($hierarchicalData['panels'] as &$panel) {
                if (isset($panel['panel_items']) && $panel['panel_category_id'] == 4) {
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
                    foreach ($panel['panel_items'] as $item) {
                        $itemsByName[$item['panel_item_name']] = $item;
                    }

                    // Build ordered array following HAE sequence
                    foreach ($haeOrder as $expectedName) {
                        if (isset($itemsByName[$expectedName])) {
                            $orderedItems[] = $itemsByName[$expectedName];
                        }
                    }

                    // Add any remaining items that weren't in our sequence
                    foreach ($panel['panel_items'] as $item) {
                        $itemName = $item['panel_item_name'];
                        if (!in_array($itemName, $haeOrder)) {
                            $orderedItems[] = $item;
                        }
                    }

                    // Replace the panel items with our ordered items
                    $panel['panel_items'] = $orderedItems;
                } elseif (isset($panel['panel_items'])) {
                    usort($panel['panel_items'], function ($a, $b) {
                        return $a['result_sequence'] - $b['result_sequence'];
                    });
                }
            }
            unset($panel);
        }

        // Remove profiles structure if no profiles exist
        if (!$hasProfiles && isset($hierarchicalData['profiles'])) {
            unset($hierarchicalData['profiles']);
        }

        // Convert arrays to use sequential keys while maintaining structure
        if (isset($hierarchicalData['profiles'])) {
            $hierarchicalData['profiles'] = array_values($hierarchicalData['profiles']);
            foreach ($hierarchicalData['profiles'] as &$profile) {
                if (isset($profile['categories'])) {
                    $profile['categories'] = array_values($profile['categories']);
                    foreach ($profile['categories'] as &$category) {
                        if (isset($category['panels'])) {
                            $category['panels'] = array_values($category['panels']);
                        }
                    }
                    unset($category);
                }
            }
            unset($profile);
        }

        if (isset($hierarchicalData['categories'])) {
            $hierarchicalData['categories'] = array_values($hierarchicalData['categories']);
            foreach ($hierarchicalData['categories'] as &$category) {
                if (isset($category['panels'])) {
                    $category['panels'] = array_values($category['panels']);
                }
            }
            unset($category);
        }

        if (isset($hierarchicalData['panels'])) {
            $hierarchicalData['panels'] = array_values($hierarchicalData['panels']);
        }

        // Add hierarchical structure to result - this replaces the separate resultItems structure
        $result =
            [
                'test_result_id' => $testResult->id,
                'patient_info' => $patient_info,
                'test_dates' => $test_dates,
                'doctor_info' => $doctor_info,
                'lab_info' => $lab_info,
                'data' => $hierarchicalData
            ];

        return $result;
    }

    /**
     * Create panel chunks based on comment presence - max 2 per page if any panel has comments, otherwise 4
     * 
     * @param array $panels Array of panels to chunk
     * @return array Array of panel chunks
     */
    private function createPanelChunks($panels)
    {
        if (empty($panels)) {
            return [];
        }

        $chunks = [];
        $currentChunk = [];
        $currentChunkHasComments = false;
        $maxPanelsPerPage = 2; // Conservative approach for taller footer

        foreach ($panels as $panel) {
            // Check if this panel has comments
            $panelHasComments = !empty($panel['panel_comments']);

            // If adding this panel would exceed the limit, start a new chunk
            if (!empty($currentChunk)) {
                $currentMaxForChunk = ($currentChunkHasComments || $panelHasComments) ? 1 : 2;

                if (count($currentChunk) >= $currentMaxForChunk) {
                    // Close current chunk and start new one
                    $chunks[] = $currentChunk;
                    $currentChunk = [];
                    $currentChunkHasComments = false;
                }
            }

            // Add panel to current chunk
            $currentChunk[] = $panel;

            // Update chunk comment status
            if ($panelHasComments) {
                $currentChunkHasComments = true;
            }
        }

        // Add the last chunk if not empty
        if (!empty($currentChunk)) {
            $chunks[] = $currentChunk;
        }

        return $chunks;
    }

    /**
     * Format panel item name with proper capitalization
     * Keeps abbreviations like E.S.R, H.D.L, MCHC as-is, capitalizes normal names
     * 
     * @param string $name The panel item name
     * @return string Formatted name
     */
    private function formatPanelItemName($name)
    {
        if (empty($name)) {
            return $name;
        }

        // Check if it's an abbreviation (contains dots or is all uppercase without spaces)
        $isAbbreviation = false;

        // Has dots like "E.S.R", "H.D.L" - likely abbreviation
        if (strpos($name, '.') !== false) {
            $isAbbreviation = true;
        }

        // Is all uppercase without spaces like "MCHC" - likely abbreviation  
        if (!$isAbbreviation && !preg_match('/[a-z]/', $name) && !preg_match('/\s/', $name)) {
            $isAbbreviation = true;
        }

        // If it's an abbreviation, keep as-is
        if ($isAbbreviation) {
            return $name;
        }

        // Handle names with parentheses - capitalize content inside parentheses too
        if (preg_match('/^(.+?)(\(.+\))(.*)$/', $name, $matches)) {
            $beforeParens = trim($matches[1]);
            $insideParens = $matches[2]; // includes the parentheses
            $afterParens = trim($matches[3]);

            // Capitalize the main part
            $formattedBefore = ucwords(strtolower($beforeParens));

            // Capitalize content inside parentheses
            $insideContent = trim($insideParens, '()');
            $formattedInside = ucwords(strtolower($insideContent));

            // Capitalize the after part if any
            $formattedAfter = !empty($afterParens) ? ucwords(strtolower($afterParens)) : '';

            return $formattedBefore . ' (' . $formattedInside . ')' . (!empty($formattedAfter) ? ' ' . $formattedAfter : '');
        }

        // Otherwise, capitalize first letter of each word
        return ucwords(strtolower($name));
    }

    /**
     * Format panel comment content by converting special markers to HTML
     * 
     * @param string $commentText Raw comment text with \.br\ markers
     * @return string Formatted HTML with proper line breaks
     */
    private function formatPanelComment($commentText)
    {
        if (empty($commentText)) {
            return '';
        }

        // First convert double line breaks \.br\\.br\ to <br><br> for paragraph separation
        $formatted = str_replace('\.br\\.br\\', '<br><br>', $commentText);

        // Then convert single line breaks \.br\ to <br>
        $formatted = str_replace('\.br\\', '<br>', $formatted);

        // Convert \H\ to bold start and \N\ to new line, \T\ to tab
        $formatted = str_replace('\\H\\', '<strong>', $formatted);
        $formatted = str_replace('\\N\\', '</strong><br>', $formatted);
        $formatted = str_replace('\\T\\', '&nbsp;&nbsp;&nbsp;&nbsp;', $formatted);

        // Apply htmlspecialchars to protect against XSS but preserve spacing and line breaks
        $formatted = htmlspecialchars($formatted, ENT_QUOTES, 'UTF-8');

        // Restore the HTML tags that were escaped by htmlspecialchars
        $formatted = str_replace(['&lt;br&gt;', '&lt;strong&gt;', '&lt;/strong&gt;', '&amp;nbsp;'], ['<br>', '<strong>', '</strong>', '&nbsp;'], $formatted);

        // Preserve spacing by converting multiple spaces to non-breaking spaces
        // This ensures the original spacing and alignment is maintained
        $formatted = preg_replace_callback('/  +/', function ($matches) {
            return str_repeat('&nbsp;', strlen($matches[0]));
        }, $formatted);

        return $formatted;
    }

    /**
     * Build panel comments from item comments
     * 
     * @param array $hierarchicalData The hierarchical data structure
     * @return void
     */
    private function buildPanelCommentsFromItems(&$hierarchicalData)
    {
        // Process profiles
        if (isset($hierarchicalData['profiles'])) {
            foreach ($hierarchicalData['profiles'] as &$profile) {
                if (isset($profile['categories'])) {
                    foreach ($profile['categories'] as &$category) {
                        if (isset($category['panels'])) {
                            foreach ($category['panels'] as &$panel) {
                                $this->collectPanelComments($panel);
                            }
                        }
                    }
                }
            }
        }

        // Process categories (no profiles)
        if (isset($hierarchicalData['categories'])) {
            foreach ($hierarchicalData['categories'] as &$category) {
                if (isset($category['panels'])) {
                    foreach ($category['panels'] as &$panel) {
                        $this->collectPanelComments($panel);
                    }
                }
            }
        }

        // Process panels (no categories)
        if (isset($hierarchicalData['panels'])) {
            foreach ($hierarchicalData['panels'] as &$panel) {
                $this->collectPanelComments($panel);
            }
        }
    }

    /**
     * Collect unique comments from panel items
     * 
     * @param array $panel Panel data
     * @return void
     */
    private function collectPanelComments(&$panel)
    {
        $uniqueComments = [];
        $seenComments = [];

        if (isset($panel['panel_items'])) {
            foreach ($panel['panel_items'] as $item) {
                if (!empty($item['item_comments'])) {
                    foreach ($item['item_comments'] as $comment) {
                        $commentText = $comment['comment'];
                        if (!in_array($commentText, $seenComments)) {
                            $uniqueComments[] = $comment;
                            $seenComments[] = $commentText;
                        }
                    }
                }
            }
        }

        $panel['panel_comments'] = $uniqueComments;
    }

    /**
     * Display profile name at the top
     */
    private function displayProfileName($result, &$showProfileName)
    {
        $content = '';
        if (isset($result['data']['profiles']) && $showProfileName) {
            foreach ($result['data']['profiles'] as $pr) {
                $profile_name = $pr['profile_name'];
                $content .= '
                        <tr>
                                <td colspan="4" style="padding: 5px 0px;font-style:light;font-weight:bold;text-decoration:underline;text-transform:uppercase;">
                                    ' . $profile_name . '
                                </td>
                            </tr>';
                $showProfileName = false; // Don't show again
                break; // Only show first profile name
            }
        }
        return $content;
    }

    private function header($result)
    {
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
                                <td style="width:200px;padding:0;">' . strtoupper($result['patient_info']['name']) . '</td>
                                <td style="width:40px;padding:0;"></td>
                                <td style="width:5px;padding:0;"></td>
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
                                <td style="padding:10px 0 0 0;;">DOB</td>
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
                                <td style="padding:0 0 0 0;">Age</td>
                                <td style="padding:0 0 0 0;">:</td>
                                <td style="padding:0 0 0 0;">' . $result['patient_info']['age'] . '</td>
                            </tr>
                            <tr>
                                <td style="padding:0;">Collected</td>
                                <td style="padding:0;">:</td>
                                <td style="padding:0;">' . $result['test_dates']['collected_date'] . ' ' . $result['test_dates']['collected_time'] . '</td>
                                <td style="padding:0 0 0 0;">Ward</td>
                                <td style="padding:0 0 0 0;">:</td>
                                <td style="padding:0;"></td>
                            </tr>
                            <tr>
                                <td style="padding:0;">Referred</td>
                                <td style="padding:0;">:</td>
                                <td style="padding:0;">' . $result['test_dates']['reported_date'] . '</td>
                                <td style="padding:0 0 0 0;">Yr Ref.</td>
                                <td style="padding:0 0 0 0;">:</td>
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
                                <td style="width:80px; padding:5px 0px 3px 0px;">Lab No.</td>
                                <td style="width:5px; padding:5px 0px 3px 0px;">:</td>
                                <td style="padding:5px 0px 3px 0px;">' . $result['lab_info']['labno'] . '</td>
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

        return $header;
    }

    public function footer()
    {
        $footer = '
            <table style="width:100%">
                <tr>
                    <td colspan="2" style="font-family: Courier New;font-stretch: expanded;text-transform:uppercase;text-align:center;font-size:10px;padding-bottom:10px;">Computer generated report - no signature required</td>
                </tr>
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

        return $footer;
    }

    /**
     * Compile raw data of Test Result and its relationship by ID
     * Testing purposes
     */
    public function getResultById($id)
    {

        $testResult = TestResult::with([
            'doctor',
            'patient',
            'testResultProfiles',
            'testResultItems.panelComments.masterPanelComment',
            'profiles'
        ])
            //->where('is_completed', true)
            //->whereNotNull('collected_date')
            //->whereYear('collected_date', date('Y'))
            ->where('id', $id)
            ->first();

        if (!$testResult) {
            return response()->json([
                'success' => false,
                'message' => 'Test result not found or not completed yet',
                'error'   => 'PDF can only be generated for completed test results'
            ], 404);
        }

        $dob = $testResult->patient->dob != null ? Carbon::createFromFormat('Ymd', str_replace('-', '', $testResult->patient->dob))->format('d/m/y') : null;

        // Patient information
        $patient_info = [
            'name' => $testResult->patient->name,
            'dob' => $dob,
            // 'icno' => $testResult->patient->icno,
            'icno' => substr_replace($testResult->patient->icno, 'XXXX', -4),
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
        $combinedItems = []; // Store items to combine percentage and absolute values

        foreach ($testResult->testResultItems as $ri) {
            // Extract all data from result item
            $res_value = $ri->value;
            $res_flag = $ri->flag != 'N' ? '*' : '';
            $res_sequence = $ri->sequence;
            $is_tagon = $ri->is_tagon;
            $ref_range_id = $ri->reference_range_id;

            // Find panel panel item with all related data
            $ppi = PanelPanelItem::with([
                'panel',
                'panel.panelCategory',
                'panelItem',
                'referenceRanges' => function ($query) use ($ref_range_id) {
                    $query->where('id', $ref_range_id);
                }
            ])->find($ri->panel_panel_item_id);

            // Get item-specific comments from TestResultItem panelComments relationship
            $itemComments = [];
            foreach ($ri->panelComments as $panelComment) {
                if ($panelComment->masterPanelComment) {
                    $itemComments[] = [
                        'comment' => $panelComment->masterPanelComment->comment
                    ];
                }
            }

            $reference_range = $ppi->referenceRanges->first()->value ?? null;
            if ($reference_range) {
                $reference_range = str_replace(['(', ')'], '', $reference_range);
            }

            $unit = $ppi->panelItem->unit;

            if (!empty($unit)) {
                // Convert all *digits into <sup>digits</sup>
                $unit = preg_replace('/\*(\d+)/', '<sup>$1</sup>', $unit);
                $unit = preg_replace('/([a-zA-Z])(\d+)/', '$1<sup>$2</sup>', $unit);
            }

            // Panel item data
            $panelItemData = [
                'panel_item_id' => $ppi->panel_item_id,
                'panel_item_name' => $ppi->panelItem->name,
                'chinese_character' => $ppi->panelItem->chi_character,
                'panel_item_unit' => $unit,
                'result_value' => $res_value,
                'result_flag' => $res_flag,
                'is_tagon' => $is_tagon,
                'result_sequence' => $res_sequence,
                'reference_range' => $reference_range != null ? '(' . $reference_range . ')' : '',
                'is_percentage' => false,
                'percentage_value' => null,
                'item_comments' => $itemComments
            ];

            // Check if this is a combinable item (percentage or absolute)
            $itemName = $ppi->panelItem->name;
            $itemUnit = $ppi->panelItem->unit;
            $isPercentage = $itemUnit === '%';
            $isAbsolute = $itemUnit === 'x 10*9/L';

            // Clean name for comparison (remove " %" suffix)
            $cleanItemName = str_replace(' %', '', $itemName);
            $combinableNames = ['Neutrophils', 'Lymphocytes', 'Monocytes', 'Eosinophils', 'Basophils'];
            $isCombinable = in_array($cleanItemName, $combinableNames);

            if ($isCombinable) {
                $baseKey = $cleanItemName . '_' . $ppi->panel_id; // Use clean name for grouping

                if (!isset($combinedItems[$baseKey])) {
                    $combinedItems[$baseKey] = [];
                }

                if ($isPercentage) {
                    $combinedItems[$baseKey]['percentage'] = $panelItemData;
                } elseif ($isAbsolute) {
                    $combinedItems[$baseKey]['absolute'] = $panelItemData;
                }
            } else {
                // Store the panel item data with hierarchy info for processing later
                $panelItemData['_hierarchy_info'] = [
                    'panel_id' => $ppi->panel_id,
                    'panel' => $ppi->panel
                ];
                $hierarchicalData['_temp_items'][] = $panelItemData;
            }
        }

        // Process combined items - merge percentage and absolute values
        foreach ($combinedItems as $baseKey => $items) {
            if (isset($items['absolute'])) {
                $finalItem = $items['absolute']; // Use absolute as base

                if (isset($items['percentage'])) {
                    $finalItem['is_percentage'] = true;
                    $finalItem['percentage_value'] = $items['percentage']['result_value'];
                    // Merge comments from both percentage and absolute items
                    $finalItem['item_comments'] = array_merge(
                        $finalItem['item_comments'] ?? [],
                        $items['percentage']['item_comments'] ?? []
                    );
                }

                // Store the final combined item with hierarchy info
                $ppi = PanelPanelItem::with([
                    'panel',
                    'panel.panelCategory',
                ])->where('panel_item_id', $finalItem['panel_item_id'])
                    ->first();

                $finalItem['_hierarchy_info'] = [
                    'panel_id' => $ppi->panel_id,
                    'panel' => $ppi->panel,
                ];
                $hierarchicalData['_temp_items'][] = $finalItem;
            }
        }

        // Now process all items (both combined and regular) into the hierarchy
        $processedItems = $hierarchicalData['_temp_items'] ?? [];
        $hierarchicalData = []; // Reset to build properly

        foreach ($processedItems as $panelItemData) {
            $ppi = (object)$panelItemData['_hierarchy_info'];
            unset($panelItemData['_hierarchy_info']); // Remove temporary hierarchy info

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
            if ($hasProfiles && $profileId) {
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
                        'panel_category_id' => $ppi->panel->panel_category_id,
                        'panel_comments' => [], // Will be built from item comments
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
                        'panel_comments' => [], // Will be built from item comments
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
                        'panel_category_id' => $ppi->panel->panel_category_id,
                        'panel_comments' => [], // Will be built from item comments
                        'panel_items' => []
                    ];
                }

                $hierarchicalData['panels'][$ppi->panel_id]['panel_items'][] = $panelItemData;
            }
        }

        // Build panel comments from item comments
        $this->buildPanelCommentsFromItems($hierarchicalData);

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

                        // Sort panel items within each panel - custom sorting for panel_category_id = 4
                        foreach ($category['panels'] as &$panel) {
                            if (isset($panel['panel_items']) && $category['category_id'] == 4) {
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
                                foreach ($panel['panel_items'] as $item) {
                                    $itemsByName[$item['panel_item_name']] = $item;
                                }

                                // Build ordered array following HAE sequence
                                foreach ($haeOrder as $expectedName) {
                                    if (isset($itemsByName[$expectedName])) {
                                        $orderedItems[] = $itemsByName[$expectedName];
                                    }
                                }

                                // Add any remaining items that weren't in our sequence
                                foreach ($panel['panel_items'] as $item) {
                                    $itemName = $item['panel_item_name'];
                                    if (!in_array($itemName, $haeOrder)) {
                                        $orderedItems[] = $item;
                                    }
                                }

                                // Replace the panel items with our ordered items
                                $panel['panel_items'] = $orderedItems;
                            } elseif (isset($panel['panel_items'])) {
                                // Default sorting by result_sequence for other panels
                                usort($panel['panel_items'], function ($a, $b) {
                                    return $a['result_sequence'] - $b['result_sequence'];
                                });
                            }
                        }
                        unset($panel);
                    }
                    unset($category);
                }
            }
            unset($profile);
        }

        // Sort panel items for categories not in profiles
        if (isset($hierarchicalData['categories'])) {
            foreach ($hierarchicalData['categories'] as &$category) {
                if (isset($category['panels'])) {
                    foreach ($category['panels'] as &$panel) {
                        if (isset($panel['panel_items']) && $category['category_id'] == 4) {
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
                            foreach ($panel['panel_items'] as $item) {
                                $itemsByName[$item['panel_item_name']] = $item;
                            }

                            // Build ordered array following HAE sequence
                            foreach ($haeOrder as $expectedName) {
                                if (isset($itemsByName[$expectedName])) {
                                    $orderedItems[] = $itemsByName[$expectedName];
                                }
                            }

                            // Add any remaining items that weren't in our sequence
                            foreach ($panel['panel_items'] as $item) {
                                $itemName = $item['panel_item_name'];
                                if (!in_array($itemName, $haeOrder)) {
                                    $orderedItems[] = $item;
                                }
                            }

                            // Replace the panel items with our ordered items
                            $panel['panel_items'] = $orderedItems;
                        } elseif (isset($panel['panel_items'])) {
                            usort($panel['panel_items'], function ($a, $b) {
                                return $a['result_sequence'] - $b['result_sequence'];
                            });
                        }
                    }
                    unset($panel);
                }
            }
            unset($category);
        }

        // Sort panel items for panels not in categories
        if (isset($hierarchicalData['panels'])) {
            foreach ($hierarchicalData['panels'] as &$panel) {
                if (isset($panel['panel_items']) && $panel['panel_category_id'] == 4) {
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
                    foreach ($panel['panel_items'] as $item) {
                        $itemsByName[$item['panel_item_name']] = $item;
                    }

                    // Build ordered array following HAE sequence
                    foreach ($haeOrder as $expectedName) {
                        if (isset($itemsByName[$expectedName])) {
                            $orderedItems[] = $itemsByName[$expectedName];
                        }
                    }

                    // Add any remaining items that weren't in our sequence
                    foreach ($panel['panel_items'] as $item) {
                        $itemName = $item['panel_item_name'];
                        if (!in_array($itemName, $haeOrder)) {
                            $orderedItems[] = $item;
                        }
                    }

                    // Replace the panel items with our ordered items
                    $panel['panel_items'] = $orderedItems;
                } elseif (isset($panel['panel_items'])) {
                    usort($panel['panel_items'], function ($a, $b) {
                        return $a['result_sequence'] - $b['result_sequence'];
                    });
                }
            }
            unset($panel);
        }

        // Remove profiles structure if no profiles exist
        if (!$hasProfiles && isset($hierarchicalData['profiles'])) {
            unset($hierarchicalData['profiles']);
        }

        // Convert arrays to use sequential keys while maintaining structure
        if (isset($hierarchicalData['profiles'])) {
            $hierarchicalData['profiles'] = array_values($hierarchicalData['profiles']);
            foreach ($hierarchicalData['profiles'] as &$profile) {
                if (isset($profile['categories'])) {
                    $profile['categories'] = array_values($profile['categories']);
                    foreach ($profile['categories'] as &$category) {
                        if (isset($category['panels'])) {
                            $category['panels'] = array_values($category['panels']);
                        }
                    }
                    unset($category);
                }
            }
            unset($profile);
        }

        if (isset($hierarchicalData['categories'])) {
            $hierarchicalData['categories'] = array_values($hierarchicalData['categories']);
            foreach ($hierarchicalData['categories'] as &$category) {
                if (isset($category['panels'])) {
                    $category['panels'] = array_values($category['panels']);
                }
            }
            unset($category);
        }

        if (isset($hierarchicalData['panels'])) {
            $hierarchicalData['panels'] = array_values($hierarchicalData['panels']);
        }

        // Add hierarchical structure to result - this replaces the separate resultItems structure
        $result =
            [
                'test_result_id' => $testResult->id,
                'patient_info' => $patient_info,
                'test_dates' => $test_dates,
                'doctor_info' => $doctor_info,
                'lab_info' => $lab_info,
                'data' => $hierarchicalData
            ];

        // return response()->json($result);
        try {
            $mpdf = new Mpdf([
                'mode' => 'utf-8',
                'format' => 'A4',
                'margin_top' => 70,
                'margin_footer' => 5,
                'default_font' => 'Arial',
            ]);

            $mpdf->useAdobeCJK = true;
            $mpdf->autoScriptToLang = true;
            $mpdf->autoLangToFont = true;
            $mpdf->allow_charset_conversion = true;

            $header = $this->header($result);
            $footer = $this->footer();

            $mpdf->SetHTMLHeader($header);
            $mpdf->SetHTMLFooter($footer);

            $content = '
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset="utf-8">
                <title>Blood Test Report</title>
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
                </style>
            </head>
            <body>
                <div class="content">
                    <table style="width: 100%; border-collapse: collapse; font-size:11.5px; margin-top:15px; text-align:left;">
                        <thead>
                            <tr>
                                <th style="padding:2px 0px 2px 15px;text-align:left;text-transform:uppercase;width:500px;border-top:1px solid #000; border-bottom:1px solid #000;">Analytes</th>
                                <th style="padding:2px 0px 2px 0px;text-align:left;text-transform:uppercase;width:60px;border-top:1px solid #000; border-bottom:1px solid #000;">Results</th>
                                <th style="padding:2px 0px 2px 0px;text-align:left;text-transform:uppercase;width:80px;border-top:1px solid #000; border-bottom:1px solid #000;">Units</th>
                                <th style="padding:2px 0px 2px 0px;text-align:left;text-transform:uppercase;width:90px;border-top:1px solid #000; border-bottom:1px solid #000;">Ref. Ranges</th>
                            </tr>
                        </thead>
                        <tbody>
                            ';

            // Pagination: Profile name only on first page, max 4 panels per page
            $pageIndex = 0;
            $showProfileName = true; // Only show on first page
            $allTestsRequested = []; // Collect all tests/categories for the final summary

            // FIRST: Display profile name at the very top if profiles exist
            $content .= $this->displayProfileName($result, $showProfileName);

            // SECOND: Display profiles if they exist
            if (isset($result['data']['profiles'])) {
                foreach ($result['data']['profiles'] as $pr) {
                    $profile_name = $pr['profile_name'];
                    if (isset($pr['categories'])) {
                        foreach ($pr['categories'] as $cat) {
                            $category_name = $cat['category_name'];
                            $allTestsRequested[] = $category_name; // Collect category name

                            if (isset($cat['panels'])) {
                                // Group panels into chunks - max 2 per page if any panel has comments, otherwise 4
                                $panelChunks = $this->createPanelChunks($cat['panels']);

                                foreach ($panelChunks as $chunkIndex => $panelGroup) {
                                    // Force page break before each page (except first)
                                    if ($pageIndex > 0) {
                                        // Close current table
                                        $content .= '
                                    </tbody>
                                </table>
                            </div>';

                                        // Write current content and add page break using mPDF
                                        $mpdf->WriteHTML($content);
                                        $mpdf->AddPage();

                                        // Reset content and start new page with table
                                        $content = '
                            <div class="content">
                                <table style="width: 100%; border-collapse: collapse; font-size:11.5px; margin-top:92px; text-align:left;">
                                    <thead>
                                        <tr>
                                            <th style="padding:2px 0px 2px 15px;text-align:left;text-transform:uppercase;width:500px;border-top:1px solid #000; border-bottom:1px solid #000;">Analytes</th>
                                            <th style="padding:2px 0px 2px 0px;text-align:left;text-transform:uppercase;width:60px;border-top:1px solid #000; border-bottom:1px solid #000;">Results</th>
                                            <th style="padding:2px 0px 2px 0px;text-align:left;text-transform:uppercase;width:80px;border-top:1px solid #000; border-bottom:1px solid #000;">Units</th>
                                            <th style="padding:2px 0px 2px 0px;text-align:left;text-transform:uppercase;width:90px;border-top:1px solid #000; border-bottom:1px solid #000;">Ref. Ranges</th>
                                        </tr>
                                    </thead>
                                    <tbody>';
                                    }

                                    // Add category header (show on first chunk of each category)
                                    if ($chunkIndex == 0) {
                                        $content .= '<tr>
                                            <td colspan="4" style="padding:15px 0px 5px 0px;font-style:light;font-weight:bold;text-transform:uppercase;">' . $category_name . '</td>
                                        </tr>';
                                    }

                                    // Add panels for this chunk (max 4)
                                    foreach ($panelGroup as $pl) {
                                        $panel_name = $pl['panel_name'];

                                        $content .= '<tr>
                                                <td colspan="4" style="padding:10px 0px 10px 10px;font-style:light;font-weight:bold;text-transform:uppercase;">' . $panel_name . '</td>
                                                </tr>';

                                        if (isset($pl['panel_items'])) {
                                            $panelItems = $pl['panel_items'];

                                            // Custom sorting for panel_category_id = 4 (HAE sequence)  
                                            if (isset($pl['panel_category_id']) && $pl['panel_category_id'] == 4) {
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

                                                usort($panelItems, function ($a, $b) use ($haeOrder) {
                                                    $posA = array_search($a['panel_item_name'], $haeOrder);
                                                    $posB = array_search($b['panel_item_name'], $haeOrder);

                                                    // If both items are in the custom order
                                                    if ($posA !== false && $posB !== false) {
                                                        return $posA - $posB;
                                                    }

                                                    // If only A is in custom order, A comes first
                                                    if ($posA !== false && $posB === false) {
                                                        return -1;
                                                    }

                                                    // If only B is in custom order, B comes first
                                                    if ($posA === false && $posB !== false) {
                                                        return 1;
                                                    }

                                                    // If neither is in custom order, use result_sequence
                                                    return $a['result_sequence'] - $b['result_sequence'];
                                                });
                                            }

                                            foreach ($panelItems as $pi) {
                                                $colspan = 'none';
                                                // if (empty($pi['panel_item_unit'])) {
                                                //     $colspan = 4;
                                                // }

                                                $isSpecialItem = in_array($pi['panel_item_name'], ['Haemoglobin', 'White Cell Count', 'Platelets']);
                                                $boldStyle = $isSpecialItem ? 'font-style:light;font-weight:bold;' : '';

                                                $isSpecialPadding = in_array($pi['panel_item_name'], ['White Cell Count', 'Platelets']);
                                                $paddingStyle = $isSpecialPadding ? 'padding: 20px 0px;' : '';

                                                $formattedStyle = '';
                                                if (!empty($pi['result_flag'])) {
                                                    $formattedStyle = 'font-weight:bold;text-decoration:underline';
                                                }
                                                if ($pi['panel_item_name'] == 'Blood Film') {

                                                    $content .= '<tr>
                                                                    <td style="font-family: Courier New;font-stretch: expanded;padding:10px 30px;"><span style="font-weight:bold;margin-right:10px;">FILM:</span>' . $pi['result_value'] . '</td>
                                                                    </tr>';
                                                } else if ($pi['panel_item_id'] == 25) {
                                                    $content .= '<tr>
                                                                    <td style="font-family: Courier New;font-stretch: expanded;padding:10px 30px;">
                                                                    ' . nl2br(str_replace('\.br\\', '<br>', $pi['result_value'])) . '
                                                                    </td>
                                                                    </tr>';
                                                } else if ($pi['panel_item_id'] == 26) {
                                                    $content .= '<tr>
                                                                    <td style="font-family: Courier New;font-stretch: expanded;padding:10px 30px;">Group:
                                                                    ' . nl2br(str_replace('\.br\\', '<br>', $pi['result_value'])) . '
                                                                    </td>
                                                                    </tr>';
                                                } else {
                                                    $name = $pi['panel_item_name'];
                                                    $displayName = $this->formatPanelItemName($name);
                                                    $content .= '<tr>
                                                                        <td style="padding:3px 10px;">
                                                                            <table style="border-collapse:collapse; width:100%; table-layout:fixed;">
                                                                                <tr>
                                                                                    <td style="padding:0; font-weight:bold; width:15px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;text-align:center;">
                                                                                        ' . $pi['result_flag'] . '
                                                                                    </td>
                                                                                    <td style="padding:0; width:240px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; ' . $boldStyle .  ' ' . $paddingStyle . ' ">'
                                                        . $displayName .
                                                        '</td>
                                                                                    <td style="padding:0; width:120px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">' . $pi['chinese_character'] . '</td>
                                                                                    <td style="padding:0px 10px 0px 0px; text-align:right; width:125px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
                                                                                        ' . ($pi['percentage_value'] != '0' && $pi['is_percentage'] ? $pi['percentage_value'] . '%' : '') . '
                                                                                    </td>
                                                                                </tr>
                                                                            </table>
                                                                        </td>
                                                                        <td colspan=' . $colspan . ' style="' . $formattedStyle . '">' . str_replace(["\\H\\", "\\N\\", ".\\br\\"], ['', '', '<br>'], $pi['result_value']) . '</td>
                                                                        <td>' . $pi['panel_item_unit'] . '</td>
                                                                        <td>' . $pi['reference_range'] . '</td>
                                                                    </tr> ';
                                                }
                                            }
                                        }

                                        // Add panel comments if they exist
                                        if (!empty($pl['panel_comments'])) {
                                            foreach ($pl['panel_comments'] as $comment) {
                                                $formattedComment = $this->formatPanelComment($comment['comment']);
                                                $content .= '<tr>
                                                                <td colspan="4" style="font-family: Courier New;font-stretch: expanded;padding:15px 15px 5px 15px;">
                                                                    ' . $formattedComment . '
                                                                </td>
                                                            </tr>';
                                            }
                                        }
                                    }
                                    $pageIndex++;
                                }
                            }
                        }
                    }
                }
            }

            // THIRD: Display standalone panels (not belonging to profiles or categories)  
            if (isset($result['data']['panels'])) {
                foreach ($result['data']['panels'] as $pl) {
                    $panel_name = $pl['panel_name'];
                    $allTestsRequested[] = $panel_name; // Collect standalone panel name

                    $content .= '<tr>
                                    <td colspan="4" style="padding:10px 0px 10px 10px;font-style:light;font-weight:bold;text-transform:uppercase;">' . $panel_name . '</td>
                                    </tr>';

                    // Sort panel items by result_sequence
                    if (isset($pl['panel_items'])) {
                        usort($pl['panel_items'], function ($a, $b) {
                            return $a['result_sequence'] - $b['result_sequence'];
                        });

                        foreach ($pl['panel_items'] as $pi) {
                            $formattedStyle = '';
                            if (!empty($pi['result_flag'])) {
                                $formattedStyle = 'font-weight:bold;text-decoration:underline';
                            }

                            if ($pi['panel_item_name'] == 'Blood Film') {
                                $content .= '<tr>
                                                <td style="font-family: Courier New;font-stretch: expanded;padding:10px 30px;"><span style="font-weight:bold;margin-right:10px;">FILM:</span>' . $pi['result_value'] . '</td>
                                                </tr>';
                            } else if ($pi['panel_item_id'] == 25) {
                                $content .= '<tr>
                                                <td style="font-family: Courier New;font-stretch: expanded;padding:10px 30px;">
                                                ' . nl2br(str_replace('\.br\\', '<br>', $pi['result_value'])) . '
                                                </td>
                                                </tr>';
                            } else if ($pi['panel_item_id'] == 26) {
                                $content .= '<tr>
                                                <td style="font-family: Courier New;font-stretch: expanded;padding:10px 30px;">Group:
                                                ' . $pi['result_value'] . '
                                                </td>
                                                </tr>';
                            } else {
                                $name = $pi['panel_item_name'];
                                $displayName = $this->formatPanelItemName($name);
                                $content .= '<tr>
                                                    <td style="padding:0px 10px;">
                                                        <table style="border-collapse:collapse; width:100%; table-layout:fixed;">
                                                            <tr>
                                                                <td style="padding:0; font-weight:bold; width:15px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;text-align:center;">
                                                                    ' . $pi['result_flag'] . '
                                                                </td>
                                                                <td style="padding:0; width:240px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">'
                                    . $displayName .
                                    '</td>
                                                                <td style="padding:0; width:90px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; text-align:center; ' . $formattedStyle . '">'
                                    . $pi['result_value'] .
                                    '</td>
                                                                <td style="padding:0; width:80px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;text-align:center;">'
                                    . $pi['panel_item_unit'] .
                                    '</td>
                                                                <td style="padding:0; width:80px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;text-align:center; font-size:10px;">'
                                    . $pi['reference_range'] .
                                    '</td>
                                                            </tr>
                                                        </table>
                                                    </td>
                                                </tr>';

                                // Add item comments if they exist
                                if (!empty($pi['item_comments'])) {
                                    foreach ($pi['item_comments'] as $comment) {
                                        $formattedComment = $this->formatPanelComment($comment['comment']);
                                        $content .= '<tr>
                                                        <td colspan="4" style="font-family: Courier New;font-stretch: expanded;padding:15px 15px 5px 15px;">
                                                            ' . $formattedComment . '
                                                        </td>
                                                    </tr>';
                                    }
                                }
                            }
                        }

                        // Add panel comments if they exist
                        if (!empty($pl['panel_comments'])) {
                            foreach ($pl['panel_comments'] as $comment) {
                                $formattedComment = $this->formatPanelComment($comment['comment']);
                                $content .= '<tr>
                                                <td colspan="4" style="font-family: Courier New;font-stretch: expanded;padding:15px 15px 5px 15px;">
                                                    ' . $formattedComment . '
                                                </td>
                                            </tr>';
                            }
                        }
                    }
                }
            }

            // FOURTH: Display standalone categories (not belonging to profiles)
            if (isset($result['data']['categories'])) {
                foreach ($result['data']['categories'] as $cat) {
                    $category_name = $cat['category_name'];
                    $allTestsRequested[] = $category_name; // Collect standalone category name

                    if (isset($cat['panels'])) {
                        // Group panels into chunks - max 2 per page if any panel has comments, otherwise 4
                        $panelChunks = $this->createPanelChunks($cat['panels']);

                        foreach ($panelChunks as $chunkIndex => $panelGroup) {
                            // Force page break before each page (except first)
                            if ($pageIndex > 0) {
                                // Close current table
                                $content .= '
                                    </tbody>
                                </table>
                            </div>';

                                // Write current content and add page break using mPDF
                                $mpdf->WriteHTML($content);
                                $mpdf->AddPage();

                                // Reset content and start new page with table
                                $content = '
                            <div class="content">
                                <table style="width: 100%; border-collapse: collapse; font-size:11.5px; margin-top:92px; text-align:left;">
                                    <thead>
                                        <tr>
                                            <th style="padding:2px 0px 2px 15px;text-align:left;text-transform:uppercase;width:500px;border-top:1px solid #000; border-bottom:1px solid #000;">Analytes</th>
                                            <th style="padding:2px 0px 2px 0px;text-align:left;text-transform:uppercase;width:60px;border-top:1px solid #000; border-bottom:1px solid #000;">Results</th>
                                            <th style="padding:2px 0px 2px 0px;text-align:left;text-transform:uppercase;width:80px;border-top:1px solid #000; border-bottom:1px solid #000;">Units</th>
                                            <th style="padding:2px 0px 2px 0px;text-align:left;text-transform:uppercase;width:90px;border-top:1px solid #000; border-bottom:1px solid #000;">Ref. Ranges</th>
                                        </tr>
                                    </thead>
                                    <tbody>';
                            }

                            // Add category header (show on first chunk of each category)
                            if ($chunkIndex == 0) {
                                $content .= '<tr>
                                            <td colspan="4" style="padding:15px 0px 5px 0px;font-style:light;font-weight:bold;text-transform:uppercase;">' . $category_name . '</td>
                                        </tr>';
                            }

                            // Add panels for this chunk (max 4)
                            foreach ($panelGroup as $pl) {
                                $panel_name = $pl['panel_name'];

                                $content .= '<tr>
                                                <td colspan="4" style="padding:10px 0px 10px 10px;font-style:light;font-weight:bold;text-transform:uppercase;">' . $panel_name . '</td>
                                                </tr>';

                                if (isset($pl['panel_items'])) {
                                    $panelItems = $pl['panel_items'];

                                    // Custom sorting for panel_category_id = 4 (HAE sequence)  
                                    if (isset($pl['panel_category_id']) && $pl['panel_category_id'] == 4) {
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

                                        usort($panelItems, function ($a, $b) use ($haeOrder) {
                                            $posA = array_search($a['panel_item_name'], $haeOrder);
                                            $posB = array_search($b['panel_item_name'], $haeOrder);

                                            // If both items are in the custom order
                                            if ($posA !== false && $posB !== false) {
                                                return $posA - $posB;
                                            }

                                            // If only A is in custom order, A comes first
                                            if ($posA !== false && $posB === false) {
                                                return -1;
                                            }

                                            // If only B is in custom order, B comes first
                                            if ($posA === false && $posB !== false) {
                                                return 1;
                                            }

                                            // If neither is in custom order, use result_sequence
                                            return $a['result_sequence'] - $b['result_sequence'];
                                        });
                                    }

                                    foreach ($panelItems as $pi) {
                                        $colspan = 'none';
                                        if (empty($pi['panel_item_unit'])) {
                                            $colspan = 1;
                                        }

                                        $formattedStyle = '';
                                        if (!empty($pi['result_flag']) || str_contains($pi['result_value'], '\H')) {
                                            $formattedStyle = 'font-weight:bold;text-decoration:underline';
                                        }

                                        if ($pi['panel_item_name'] == 'Blood Film') {
                                            $content .= '<tr>
                                                        <td style="font-family: Courier New;font-stretch: expanded;padding:10px 30px;"><span style="font-weight:bold;margin-right:10px;">FILM:</span>' . $pi['result_value'] . '</td>
                                                        </tr>';
                                        } else if ($pi['panel_item_id'] == 25) {
                                            $content .= '<tr>
                                                        <td style="font-family: Courier New;font-stretch: expanded;padding:10px 30px;">
                                                        ' . nl2br(str_replace('\.br\\', '<br>', $pi['result_value'])) . '
                                                        </td>
                                                        </tr>';
                                        } else if ($pi['panel_item_id'] == 26) {
                                            $content .= '<tr>
                                                                    <td style="font-family: Courier New;font-stretch: expanded;padding:10px 30px;">Group:
                                                                    ' . nl2br(str_replace('\.br\\', '<br>', $pi['result_value'])) . '
                                                                    </td>
                                                                    </tr>';
                                        } else {
                                            $name = $pi['panel_item_name'];
                                            $displayName = $this->formatPanelItemName($name);
                                            $content .= '<tr>
                                                                        <td style="padding:0px 10px;">
                                                                            <table style="border-collapse:collapse; width:100%; table-layout:fixed;">
                                                                                <tr>
                                                                                    <td style="padding:0; font-weight:bold; width:15px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;text-align:center;">
                                                                                        ' . $pi['result_flag'] . '
                                                                                    </td>
                                                                                    <td style="padding:0; width:240px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">'
                                                . $displayName .
                                                '</td>
                                                                                    <td style="padding:0; width:120px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">' . $pi['chinese_character'] . '</td>
                                                                                    <td style="padding:0px 10px 0px 0px; text-align:right; width:125px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
                                                                                        ' . ($pi['percentage_value'] != '0' && $pi['is_percentage'] ? $pi['percentage_value'] . '%' : '') . '
                                                                                    </td>
                                                                                </tr>
                                                                            </table>
                                                                        </td>
                                                                        <td colspan=' . $colspan . ' style="' . $formattedStyle . '">' . str_replace(["\\H\\", "\\N\\", ".\\br\\"], ['', '', '<br>'], $pi['result_value']) . '</td>
                                                                        <td>' . $pi['panel_item_unit'] . '</td>
                                                                        <td>' . $pi['reference_range'] . '</td>
                                                                    </tr> ';
                                        }
                                    }

                                    // Add panel comments if they exist
                                    if (!empty($pl['panel_comments'])) {
                                        foreach ($pl['panel_comments'] as $comment) {
                                            $formattedComment = $this->formatPanelComment($comment['comment']);
                                            $content .= '<tr>
                                                            <td colspan="4" style="font-family: Courier New;font-stretch: expanded;padding:15px 15px 5px 15px;">
                                                                ' . $formattedComment . '
                                                            </td>
                                                        </tr>';
                                        }
                                    }
                                }
                            }
                            $pageIndex++;
                        }
                    }
                }
            }

            // Add REPORT COMPLETED section at the end
            if (!empty($allTestsRequested)) {
                // Remove duplicates and sort alphabetically
                $uniqueTests = array_unique($allTestsRequested);
                sort($uniqueTests);
                $testsString = implode(', ', $uniqueTests);

                $content .= '
                                <tr>
                                    <td colspan="4" style=" padding-top: 25px;text-align:center;">
                                        <div style="text-align:center;font-weight:bold; font-size:12px; text-decoration:underline; margin-bottom:10px;">
                                            REPORT COMPLETED
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <td colspan="4" style="font-size:11.5px;">
                                        Tests Requested:<br>
                                        ' . strtoupper($testsString) . '
                                    </td>
                                </tr>';
            }

            $content .= '
                        </tbody>
                    </table>
                </div>
            </body>
            </html>';

            // Write the final content
            $mpdf->WriteHTML($content);

            return response($mpdf->Output('', 'S'), 200)
                ->header('Content-Type', 'application/pdf')
                ->header('Content-Disposition', 'inline; filename="' . $result['test_result_id'] . '-report.pdf"');

            // Get PDF content as string and convert to base64
            // $pdfContent = $mpdf->Output('', 'S');
            // $base64Pdf = base64_encode($pdfContent);

            // return response()->json([
            //     'success' => true,
            //     'pdf' => $base64Pdf,
            //     'message' => 'PDF generated successfully'
            // ], 200);
        } catch (Exception $e) {
            Log::error('PDF Generation Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate PDF',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}