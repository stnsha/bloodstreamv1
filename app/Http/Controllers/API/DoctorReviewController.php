<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\DoctorReview;
use App\Models\Patient;
use App\Models\TestResult;
use App\Models\ResultLibrary;
use App\Services\MyHealthService;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Exception;

class DoctorReviewController extends Controller
{
    protected $myHealthService;

    public function __construct(MyHealthService $myHealthService)
    {
        $this->myHealthService = $myHealthService;
    }

    /**
     * Compile raw data from Test Result, Test Result Item and MyHealth
     * Send compiled data in JSON format to API AI
     */
    public function processResult()
    {
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
            ->take(5) //First 5
            ->get();

        foreach ($testResults as $tr) {
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

            try {
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

                        $categoryName = 'Unknown Category';
                        try {
                            $categoryName = $ri->panelPanelItem->panel->panelCategory->name ??
                                $ri->panelPanelItem->panel->name ??
                                'Unknown Category';
                        } catch (Exception $e) {
                            Log::warning('Error determining category name', [
                                'error' => $e->getMessage(),
                                'result_item_id' => $ri->id
                            ]);
                        }

                        if (!isset($categorizedItems[$categoryName])) {
                            $categorizedItems[$categoryName] = [];
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

                        $categorizedItems[$categoryName][] = $itemData;
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

                return response()->json($testResultData); //Send to API

            } catch (Exception $e) {
                Log::error('Critical error processing individual test result', [
                    'error' => $e->getMessage(),
                    'test_result_id' => $tr->id ?? 'unknown',
                    'trace' => $e->getTraceAsString()
                ]);
                $failedResults[] = ['id' => $tr->id ?? 'unknown', 'reason' => 'Critical processing error'];
            }
        }
    }

    /**
     * Return formatted result of response from API AI and store to Doctor Review
     */
    public function formatResponse()
    {
        $response = "**SECTION A1:Your Health at a Glance** \n\n| HealthArea | Status (🟢🟡🔴) | Notes |\n|-------------|--------------|-------|\n| **Cardiovascular Health** | 🔴 | LDL5.0 mmolL⁻¹ (high), Total Cholesterol5.3 mmolL⁻¹ (high), Chol/HDL5.5 (high). Elevated risk of atherosclerosis. |\n| **Blood Sugar & Metabolism** | 🟢 | Glucose 4.3 mmolL⁻¹ - within normal fasting range. |\n| **Liver Function** | 🔴 | ALT50 U L⁻¹, GGT50 U L⁻¹, ALP 150 U L⁻¹, Total Bilirubin50 µmolL⁻¹ - all above reference. Suggests mild hepatocellular injury/cholestasis. |\n| **Kidney Function** | 🟢 | Creatinine 54 µmolL⁻¹ - within normal range; eGFR likely > 60 mLmin⁻¹ 1.73 m⁻². |\n| **Nutritional Status** | 🟢 | Total Protein72 gL⁻¹, Albumin44 gL⁻¹, Globulin28 gL⁻¹ - all normal; adequate protein intake. |\n| **Inflammation / Infection** | 🟢 | No acute inflammatory markers provided; liver enzymes are the only abnormality. |\n| **Urinary Tract Health** | 🟢 | Urea 3.8 mmolL⁻¹, electrolytes normal - no evidence of infection or obstruction. |\n| **Add-On Packages** | | |\n| - Thyroid Health | — | Not requested. |\n| - Stress & Mood Biomarkers | — | Not requested. |\n\n--- \n\n**SECTION A2:Your Body System Highlights**\n\n1. **High LDL and total cholesterol** - these fats can build plaque in arteries, increasing heart-disease risk. \n2. **Elevated liver enzymes and bilirubin** - mild liver stress or early cholestasis; keep liver-friendly habits and avoid alcohol. \n3. **Kidney function is good** - creatinine is normal and eGFR is likely healthy. \n4. **Blood sugar is fine** - fasting glucose is in the safe range. \n5. **Overall nutrition looks solid** - protein levels are normal, so you're meeting your protein needs.\n\n---\n\n**SECTION B: Alpro Care for You - 3-6 Month HealthAction**\n\n| Timeline | Action | Goals | Alpro Care for You | Appointment Date & Place |\n|----------|--------|-------|--------------------|--------------------------|\n| **Month0-1** | • Start a plant-based protein-rich diet (oat, soy, pea). • Cut saturated fats (no butter, limit red meat). • Increase fiber (fruits, veggies, whole grains). • 150 min/week moderate exercise (brisk walk, cycling). | • Lower LDL by ≥ 10 %. • Bring total cholesterol < 5.2 mmolL⁻¹. • Reduce liver enzyme elevation to ≤ 1.5x upper limit. | • DailyAlpro oat milk (fortified with calcium & vitaminD). • Alpro plant-protein shakes for post-workout recovery. • Alpro chia-seed-rich smoothies for omega-3. | **Week 4** - Dietitian review (clinic or telehealth). |\n| **Month2- 3** | • Re-measure lipids & liver enzymes. • If LDL remains > 3.4 mmolL⁻¹, discuss statin initiation with GP. • Continue liver-friendly diet (low alcohol, avoid over-cooked foods). • Maintain weight-loss if BMI > 25. | • LDL < 3.4 mmolL⁻¹. • Total cholesterol < 5.2 mmolL⁻¹. • Bilirubin, ALT, GGT within normal limits. | • Add Alpro fortified protein bar (low sugar) for on-the-go nutrition. • Alpro \"Omega-3\"-enriched nut-milk to support liver. | **Week 12** - GP follow-up & medication review. |\n| **Month4-6** | • Repeat full lipid panel and liver panel. • Adjust diet or medications based on results. • Continue regular exercise and weight-maintenance plan. | • Sustain all target ranges. • Achieve stable eGFR and normal renal profile. | • Keep dailyAlpro plant-milk & protein shakes. • Explore Alpro \"Low-Sodium\" options if blood pressure rises. | **Week 24** - Specialist (cardio or hepatology) check-in if needed. |\n\n---\n\n**SECTION C: With Care, fromAlpro (Final Note)** \n\nHello [Name],\n\nYou've taken an important step by reviewing your blood work, and it's encouraging to see that your kidney function, blood sugar, and overall nutrition are all in good shape. The main areas we'll focus on are your cholesterol profile and the slight elevations in your liver enzymes. With a few sensible lifestyle tweaks—think plant-based meals, more fiber, regular movement, and reduced alcohol—you can move those numbers toward healthier targets, which in turn gives you more energy, steadier mood, and long-term protection against heart disease.\n\nYour health priorities in the next six months are: \n1. **Lower LDL & total cholesterol** so the heart can stay clear of plaque. \n2. **Normalize liver enzymes** to reduce stress on the liver. \n3. **Maintain kidney health** - you're already on the right track! \n\nBy keeping to the action plan above and usingAlpro products as tasty, convenient tools, you'll feel more vibrant and confident in your daily life. Remember, this plan is a guide—always feel free to reach out to your healthcare team if you have questions or concerns.\n\n**You're on the right path.** Let's keep moving forward together.\n\nWith warmth, \nThe Alpro Care Team\n\n---\n\n**Disclaimer** \nThis report is for educational purposes only. It is not a medical diagnosis and should not replace consultation with a qualified healthcare professional. Always discuss your results and health concerns with your doctor.";

        return $this->formatMarkdownToHTML($response);
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
                    'message' => 'No review found.'
                ], 404);
            }

            $review->is_sync = true;
            $review->save();

            return response()->json([
                'success' => true,
                'review' => $review->review,
                'message' => 'Review generated successfully'
            ], 200);
        }
    }
}