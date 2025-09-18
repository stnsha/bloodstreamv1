<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\DoctorReview;
use App\Models\Patient;
use App\Models\TestResult;
use App\Models\ResultLibrary;
use App\Services\MyHealthService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Http;

class DoctorReviewController extends Controller
{
    protected $myHealthService;

    public function __construct(MyHealthService $myHealthService)
    {
        $this->myHealthService = $myHealthService;
    }

    public function store($id, $testResultData, $result)
    {
        DoctorReview::firstOrCreate(
            [
                'test_result_id' => $id,
            ],
            [
                'compiled_results' => $testResultData,
                'review' => $result,
                'is_sync' => false
            ]
        );
    }

    /**
     * Compile raw data from Test Result, Test Result Item and MyHealth
     * Send compiled data in JSON format to API AI
     */
    public function processResult()
    {
        // Increase execution time for external API calls
        ini_set('max_execution_time', 300); // 5 minutes

        // Initialize tracking variables for summary
        $totalProcessed = 0;
        $successfulReviewsGenerated = 0;
        $successfulStores = 0;
        $failedResults = [];
        $processingStartTime = now();

        try {
            DB::beginTransaction();

            $testResults = TestResult::with([
                'patient',
                'testResultItems.panelPanelItem.panel.panelCategory',
                'testResultItems.referenceRange',
                'testResultItems.panelPanelItem.panelItem',
                'testResultItems.panelComments.masterPanelComment',
            ])
                ->where('is_reviewed', false)
                ->where('is_completed', true)
                ->whereHas('patient', function ($query) {
                    $query->where('ic_type', 'NRIC');
                })
                ->take(20) //First 5
                ->get();

            if ($testResults->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'message' => 'No test results found to process',
                    'summary' => [
                        'total_found' => 0,
                        'total_processed' => 0,
                        'successful_reviews_generated' => 0,
                        'successful_stores' => 0,
                        'failed_results' => 0,
                        'processing_time' => '0s'
                    ]
                ]);
            }

            // Login once before processing all results
            Log::info("Attempting to login to external AI service");
            $login = Http::timeout(60)->post(config('credentials.ai_review.login'), [
                "username" => config('credentials.odb.username'),
                "password" => config('credentials.odb.password')
            ]);

            if ($login->failed()) {
                DB::rollBack();
                Log::error('External AI service login failed');
                return response()->json([
                    'success' => false,
                    'message' => 'External AI service login failed - all processing stopped',
                    'summary' => [
                        'total_found' => $testResults->count(),
                        'total_processed' => 0,
                        'successful_reviews_generated' => 0,
                        'successful_stores' => 0,
                        'failed_results' => $testResults->count(),
                        'processing_time' => now()->diffInSeconds($processingStartTime) . 's',
                        'login_failed' => true
                    ]
                ], 401);
            }

            $loginData = $login->json();
            $token = $loginData['token'];
            Log::info("Successfully logged into external AI service");

            foreach ($testResults as $tr) {
                $totalProcessed++;
                $healthDetails = []; // Initialize for each test result
                $finalResults = []; // Initialize for each test result

                try {
                    $icno = $tr->patient->icno;
                    $checkRecords = $this->myHealthService->getCheckRecordIdByIC($icno);

                    $patientInfo = [
                        'Age' => $tr->patient->age
                    ];

                    if ($checkRecords) {
                        foreach ($checkRecords as $cr) {
                            $recordId = $cr->id;
                            $recordGender = $cr->gender;
                            $recordDate = Carbon::parse($cr->date_time)->format('Y-m-d');

                            if (is_null($tr->patient->gender)) {
                                $tr->patient->gender = $recordGender == 1 ? Patient::GENDER_MALE : Patient::GENDER_FEMALE;
                                $tr->patient->save();
                            }

                            $patientInfo['Gender'] = $tr->patient->gender;

                            $recordDetails = $this->myHealthService->getRecordDetailsByRecordId($recordId);
                            if (count($recordDetails) != 0) {
                                $transformedRecordDetails = [];

                                foreach ($recordDetails as $rd) {
                                    if (isset($rd->parameter)) {
                                        $parameterName = $rd->parameter;
                                        unset($rd->parameter);
                                        $transformedRecordDetails[$parameterName] = $rd;
                                    }
                                }
                                $healthDetails[$recordDate] = $transformedRecordDetails;
                                $patientInfo = array_merge($patientInfo, $healthDetails);
                            }
                        }
                    }

                    if (!$tr || !$tr->id) {
                        Log::error('Invalid test result object');
                        $failedResults[] = ['id' => 'unknown', 'reason' => 'Invalid test result object'];
                        continue;
                    }

                    if (!$tr->patient) {
                        Log::warning('Test result has no associated patient', ['test_result_id' => $tr->id]);
                        $failedResults[] = ['id' => $tr->id, 'reason' => 'Missing patient information'];
                        continue;
                    }

                    $reportDate = Carbon::parse($tr->reported_date)->format('Y-m-d');
                    $categorizedItems = [];
                    $validItemsCount = 0;

                    if ($tr->testResultItems->isEmpty()) {
                        Log::warning('Test result has no test result items', ['test_result_id' => $tr->id]);
                        $failedResults[] = ['id' => $tr->id, 'reason' => 'No test result items found'];
                        continue;
                    }

                    foreach ($tr->testResultItems as $ri) {
                        try {
                            if (!$ri || !$ri->id) {
                                Log::warning('Invalid result item', ['test_result_id' => $tr->id]);
                                continue;
                            }

                            if (!$ri->panelPanelItem) {
                                Log::warning('Test result item missing panel relationship', [
                                    'result_item_id' => $ri->id,
                                    'test_result_id' => $tr->id
                                ]);
                                continue;
                            }

                            if (!$ri->panelPanelItem->panelItem) {
                                Log::warning('Test result item missing panel item relationship', [
                                    'result_item_id' => $ri->id,
                                    'test_result_id' => $tr->id
                                ]);
                                continue;
                            }

                            $panelName = $ri->panelPanelItem->panel->name;
                            $categoryName = null;

                            // Check if panel category exists
                            if ($ri->panelPanelItem->panel->panelCategory && !empty($ri->panelPanelItem->panel->panelCategory->name)) {
                                $categoryName = $ri->panelPanelItem->panel->panelCategory->name;
                            }

                            // Build the hierarchical structure
                            if ($categoryName) {
                                // Has category: Category > Panel > Items
                                if (!isset($categorizedItems[$categoryName])) {
                                    $categorizedItems[$categoryName] = [];
                                }
                                if (!isset($categorizedItems[$categoryName][$panelName])) {
                                    $categorizedItems[$categoryName][$panelName] = [];
                                }
                            } else {
                                // No category: Panel > Items (panel becomes top level)
                                if (!isset($categorizedItems[$panelName])) {
                                    $categorizedItems[$panelName] = [];
                                }
                            }

                            $flagDescription = $ri->flag;
                            if (!empty($ri->flag)) {
                                try {
                                    $resultLibrary = ResultLibrary::where('code', '0078')
                                        ->where('value', $ri->flag)
                                        ->first();
                                    if ($resultLibrary && !empty($resultLibrary->description)) {
                                        // Remove content within parentheses and trim whitespace
                                        $flagDescription = trim(preg_replace('/\s*\([^)]*\)/', '', $resultLibrary->description));
                                    } else {
                                        $flagDescription = $ri->flag;
                                    }
                                } catch (Exception $e) {
                                    Log::error('Error fetching flag description from ResultLibrary', [
                                        'error' => $e->getMessage(),
                                        'flag' => $ri->flag,
                                        'result_item_id' => $ri->id
                                    ]);
                                }
                            }

                            $itemData = [
                                'panel_item_name' => $ri->panelPanelItem->panelItem->name ?? 'Unknown Item',
                                'result_value' => $ri->value ?? null,
                                'panel_item_unit' => $ri->panelPanelItem->panelItem->unit ?? null,
                                'result_status' => $flagDescription ?? null,
                                'reference_range' => null,
                                'comments' => []
                            ];

                            if ($ri->reference_range_id && $ri->referenceRange) {
                                try {
                                    $itemData['reference_range'] = $ri->referenceRange->value;
                                } catch (Exception $e) {
                                    Log::warning('Error accessing reference range', [
                                        'error' => $e->getMessage(),
                                        'result_item_id' => $ri->id
                                    ]);
                                }
                            }

                            if ($ri->panelComments && !$ri->panelComments->isEmpty()) {
                                try {
                                    foreach ($ri->panelComments as $pc) {
                                        if ($pc && $pc->masterPanelComment && !empty($pc->masterPanelComment->comment)) {
                                            $itemData['comments'][] = $pc->masterPanelComment->comment;
                                        }
                                    }
                                } catch (Exception $e) {
                                    Log::warning('Error processing panel comments', [
                                        'error' => $e->getMessage(),
                                        'result_item_id' => $ri->id
                                    ]);
                                }
                            }

                            if ($categoryName) {
                                $categorizedItems[$categoryName][$panelName][] = $itemData;
                            } else {
                                $categorizedItems[$panelName][] = $itemData;
                            }
                            $validItemsCount++;
                        } catch (Exception $e) {
                            Log::error('Error processing test result item', [
                                'error' => $e->getMessage(),
                                'result_item_id' => $ri->id ?? 'unknown',
                                'test_result_id' => $tr->id,
                                'trace' => $e->getTraceAsString()
                            ]);
                        }
                    }

                    if ($validItemsCount === 0) {
                        Log::warning('No valid test result items processed', ['test_result_id' => $tr->id]);
                        $failedResults[] = ['id' => $tr->id, 'reason' => 'No valid test result items'];
                        continue;
                    }

                    $finalResults[$reportDate] = $categorizedItems;

                    $testResultData = [
                        'Health History' => $patientInfo,
                        'Blood Test Results' => $finalResults
                    ];

                    // Call AI API using the token from login
                    $response = Http::timeout(120)->withToken($token)
                        ->post(config('credentials.ai_review.analysis'), $testResultData);

                    if ($response->failed()) {
                        Log::error('AI analysis API call failed', [
                            'test_result_id' => $tr->id,
                            'response_status' => $response->status()
                        ]);
                        $failedResults[] = ['id' => $tr->id, 'reason' => 'AI analysis API call failed'];
                        continue;
                    }

                    $responseData = $response->json();
                    $result = $this->formatMarkdownToHTML($responseData['ai_analysis']);
                    $successfulReviewsGenerated++;

                    // Store the generated review
                    $this->store($tr->id, $testResultData, $result);
                    $successfulStores++;

                    // Mark as reviewed (comment for testing)
                    $tr->is_reviewed = true;
                    $tr->save();

                    Log::info('Successfully processed test result', [
                        'test_result_id' => $tr->id,
                        'patient_icno' => $tr->patient->icno
                    ]);
                } catch (Exception $e) {
                    Log::error('Critical error processing individual test result', [
                        'error' => $e->getMessage(),
                        'test_result_id' => $tr->id ?? 'unknown',
                        'trace' => $e->getTraceAsString()
                    ]);
                    $failedResults[] = ['id' => $tr->id ?? 'unknown', 'reason' => 'Critical processing error: ' . $e->getMessage()];
                }
            }

            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Critical error in processResult method', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Critical error occurred during processing',
                'error' => $e->getMessage(),
                'summary' => [
                    'total_found' => $testResults->count() ?? 0,
                    'total_processed' => $totalProcessed,
                    'successful_reviews_generated' => $successfulReviewsGenerated,
                    'successful_stores' => $successfulStores,
                    'failed_results' => count($failedResults),
                    'processing_time' => now()->diffInSeconds($processingStartTime) . 's'
                ]
            ], 500);
        }

        // Generate final summary
        $processingTime = now()->diffInSeconds($processingStartTime);
        $totalFound = $testResults->count();
        $failedCount = count($failedResults);
        $successRate = $totalFound > 0 ? round(($successfulStores / $totalFound) * 100, 2) : 0;

        return response()->json([
            'success' => $failedCount === 0,
            'message' => $failedCount === 0
                ? "All {$totalFound} test results processed successfully"
                : "Processed {$totalFound} test results with {$failedCount} failures",
            'summary' => [
                'total_found' => $totalFound,
                'total_processed' => $totalProcessed,
                'successful_reviews_generated' => $successfulReviewsGenerated,
                'successful_stores' => $successfulStores,
                'failed_results' => $failedCount,
                'success_rate' => $successRate . '%',
                'processing_time' => $processingTime . 's',
                'failed_details' => $failedResults
            ]
        ], $failedCount === 0 ? 200 : 207); // 207 = Multi-Status (partial success)
    }

    private function convertTableBlock(array $tableLines): string
    {
        $html = "<table border='1' cellpadding='6' cellspacing='0' style='border-collapse:collapse;'>\n";
        $rows = [];

        // Normalize and collect rows
        foreach ($tableLines as $line) {
            $line = trim($line);
            if ($line === '') continue;
            // remove starting/trailing pipe
            $line = preg_replace('/^\||\|$/', '', $line);
            $cells = array_map('trim', explode('|', $line));
            $rows[] = $cells;
        }

        if (count($rows) === 0) return '';

        // If the second row is a separator row (---|---), skip it
        $startDataIndex = 1;
        if (isset($rows[1])) {
            $joined = implode('', $rows[1]);
            // if row contains only dashes, spaces, colons (alignment) -> treat as separator
            if (preg_match('/^[\s\-:|]+$/', $joined)) {
                $startDataIndex = 2;
            } else {
                $startDataIndex = 1;
            }
        } else {
            $startDataIndex = 1;
        }

        // Header row is the first row
        $header = $rows[0];

        // Build thead
        $html .= "<thead><tr>";
        foreach ($header as $hcell) {
            $html .= '<th>' . htmlspecialchars($hcell) . '</th>';
        }
        $html .= "</tr></thead>\n";

        // Build tbody
        $html .= "<tbody>\n";
        for ($i = $startDataIndex; $i < count($rows); $i++) {
            $html .= "<tr>";
            // ensure same number of columns as header (pad empty if necessary)
            $cols = $rows[$i];
            for ($c = 0; $c < count($header); $c++) {
                $cell = $cols[$c] ?? '';
                
                // Check if cell contains bullet points and convert to unordered list
                if (strpos($cell, '•') !== false) {
                    // Split by bullet points and create list items
                    $parts = preg_split('/\s*•\s*/', $cell);
                    $firstPart = trim($parts[0]); // Text before first bullet
                    $listItems = array_slice($parts, 1); // Everything after bullets
                    
                    if (!empty($listItems)) {
                        $cellHtml = '';
                        if (!empty($firstPart)) {
                            $escapedFirstPart = htmlspecialchars($firstPart);
                            // Apply bold formatting to first part
                            $escapedFirstPart = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $escapedFirstPart);
                            $cellHtml .= $escapedFirstPart . ' ';
                        }
                        $cellHtml .= '<ul>';
                        foreach ($listItems as $item) {
                            $item = trim($item);
                            if (!empty($item)) {
                                $escapedItem = htmlspecialchars($item);
                                // Apply bold formatting to list items
                                $escapedItem = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $escapedItem);
                                $cellHtml .= '<li>' . $escapedItem . '</li>';
                            }
                        }
                        $cellHtml .= '</ul>';
                        $html .= '<td>' . $cellHtml . '</td>';
                    } else {
                        $escapedCell = htmlspecialchars($cell);
                        $escapedCell = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $escapedCell);
                        $html .= '<td>' . $escapedCell . '</td>';
                    }
                } else {
                    $escapedCell = htmlspecialchars($cell);
                    // Apply bold formatting to regular cells
                    $escapedCell = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $escapedCell);
                    $html .= '<td>' . $escapedCell . '</td>';
                }
            }
            $html .= "</tr>\n";
        }
        $html .= "</tbody></table>\n";

        return $html;
    }

    private function formatMarkdownToHTML($text)
    {
        // Normalize line endings and trim
        $text = str_replace(["\r\n", "\r"], "\n", trim($text));
        
        // Remove --- before every \n\n**SECTION
        $text = preg_replace('/---\s*\n\n\*\*SECTION/', "\n\n**SECTION", $text);
        $lines = explode("\n", $text);

        $html = '';
        $inList = false;
        $listType = 'ul'; // default list type
        $i = 0;
        $n = count($lines);

        while ($i < $n) {
            $line = rtrim($lines[$i]);

            // --- TABLE DETECTION: consecutive lines starting with '|' form a table block
            if (preg_match('/^\s*\|/', $line)) {
                $tableLines = [];
                while ($i < $n && preg_match('/^\s*\|/', rtrim($lines[$i]))) {
                    $tableLines[] = $lines[$i];
                    $i++;
                }
                // close any open list
                if ($inList) {
                    $html .= "</{$listType}>\n";
                    $inList = false;
                }

                // **Important fix**: remove trailing blank lines/newlines so table attaches directly
                $html = rtrim($html, "\n");

                $html .= $this->convertTableBlock($tableLines);
                continue; // continue while loop (i already advanced)
            }

            $trimmed = trim($line);

            // --- LISTS: lines starting with '* ' or '- ' or numbered lists '1. '
            if (preg_match('/^(\* |- )\s*(.+)$/', $trimmed, $m)) {
                if (!$inList) {
                    $inList = true;
                    $listType = 'ul';
                    $html .= "<{$listType}>\n";
                }
                $itemText = $m[2];
                // Escape then apply inline formatting (header/bold)
                $escaped = htmlspecialchars($itemText);
                // Section header pattern inside list item (rare) converted to <h3>
                $escaped = preg_replace('/\*\*(\d+\..*?)\*\*/', '<h3>$1</h3>', $escaped);
                // Bold
                $escaped = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $escaped);
                $html .= "<li>{$escaped}</li>\n";
                $i++;
                continue;
            }

            // --- Numbered lists (e.g. "1. Do this")
            if (preg_match('/^\d+\.\s+(.+)$/', $trimmed, $m)) {
                if (!$inList) {
                    $inList = true;
                    $listType = 'ol';
                    $html .= "<{$listType}>\n";
                } elseif ($inList && $listType !== 'ol') {
                    // close previous list and start ordered
                    $html .= "</{$listType}>\n";
                    $listType = 'ol';
                    $html .= "<{$listType}>\n";
                }
                $itemText = $m[1];
                $escaped = htmlspecialchars($itemText);
                $escaped = preg_replace('/\*\*(\d+\..*?)\*\*/', '<h3>$1</h3>', $escaped);
                $escaped = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $escaped);
                $html .= "<li>{$escaped}</li>\n";
                $i++;
                continue;
            }

            // If we reach here and a list was open, close it
            if ($inList) {
                $html .= "</{$listType}>\n";
                $inList = false;
                $listType = 'ul';
            }

            // --- Skip empty lines (do NOT add extra newlines that become visible space)
            if ($trimmed === '') {
                // Look ahead: if next non-empty line is a table, just skip; otherwise skip too.
                // This avoids producing an empty newline between heading and table.
                $i++;
                continue;
            }

            // --- HEADERS & BOLD for normal lines
            // Escape line content first
            $escapedLine = htmlspecialchars($trimmed);

            // Section headers like **3. Diet Plan**
            $escapedLine = preg_replace('/\*\*(\d+\..*?)\*\*/', '<h3>$1</h3>', $escapedLine);

            // Bold **text**
            $escapedLine = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $escapedLine);

            // Wrap in <p> unless it's a header already (starts with <h3>)
            if (preg_match('/^\s*<h3>.*<\/h3>\s*$/i', $escapedLine)) {
                $html .= $escapedLine . "\n";
            } else {
                $html .= "<p>{$escapedLine}</p>\n";
            }

            $i++;
        }

        // Close any remaining open list
        if ($inList) {
            $html .= "</{$listType}>\n";
            $inList = false;
        }

        return $html;
    }

    public function sync(Request $request)
    {
        $validated = $request->validate([
            'icno' => 'required|string',
            'refid' => 'nullable|string',
        ], [
            'icno.required' => 'IC No. is required.',
        ]);

        if ($validated) {
            $icno = $validated['icno'];
            $refid = $validated['refid'] ?? null;

            $query = DoctorReview::with(['testResult', 'testResult.patient'])
                ->where('is_sync', false)
                ->whereHas('testResult.patient', function ($q) use ($icno) {
                    $q->where('icno', $icno);
                });

            $review = $query->first();

            if (!$review && $refid) {
                $review = DoctorReview::with(['testResult', 'testResult.patient'])
                    ->where('is_sync', false)
                    ->whereHas('testResult', function ($t) use ($refid) {
                        $t->where('ref_id', $refid);
                    })
                    ->first();
            }

            if (!$review) {
                return response()->json([
                    'success' => false,
                    'message' => 'No review found.'
                ], 404);
            }

            //comment while still testing
            // $review->is_sync = true;
            // $review->save();

            return response()->json([
                'success' => true,
                'review' => $review->review,
                'message' => 'Review generated successfully'
            ], 200);
        }
    }
}