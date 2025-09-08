<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\DoctorReview;
use App\Models\TestResult;
use App\Models\ResultLibrary;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DoctorReviewController extends Controller
{
    public function index()
    {
        try {
            $testResults = TestResult::with([
                'patient',
                'testResultItems.panelPanelItem.panel.panelCategory',
                'testResultItems.referenceRange',
                'testResultItems.panelPanelItem.panelItem',
                'testResultItems.panelComments.masterPanelComment',
            ])
                ->where('is_reviewed', false)
                ->where('is_completed', true)
                ->get();

            if ($testResults->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'data' => [],
                    'message' => 'No unreviewed test results found'
                ], 200);
            }

            $processedResults = [];

            foreach ($testResults as $tr) {
                try {
                    if (!$tr->patient) {
                        Log::warning('Test result has no associated patient', ['test_result_id' => $tr->id]);
                        continue;
                    }

                    $patientInfo = [
                        'patient_age' => $tr->patient->age ?? null,
                        'patient_gender' => $tr->patient->gender ?? null,
                    ];

                    $categorizedItems = [];

                    foreach ($tr->testResultItems as $ri) {
                        try {
                            if (!$ri->panelPanelItem || !$ri->panelPanelItem->panelItem) {
                                Log::warning('Test result item missing required relationships', ['result_item_id' => $ri->id]);
                                continue;
                            }

                            $categoryName = $ri->panelPanelItem->panel->panelCategory->name ??
                                $ri->panelPanelItem->panel->name ??
                                'Unknown Category';

                            if (!isset($categorizedItems[$categoryName])) {
                                $categorizedItems[$categoryName] = [];
                            }

                            $flagDescription = $ri->flag;
                            if ($ri->flag) {
                                try {
                                    $resultLibrary = ResultLibrary::where('code', '0078')
                                        ->where('value', $ri->flag)
                                        ->first();
                                    $flagDescription = $resultLibrary ? $resultLibrary->description : $ri->flag;
                                } catch (Exception $e) {
                                    Log::error('Error fetching flag description', [
                                        'error' => $e->getMessage(),
                                        'flag' => $ri->flag
                                    ]);
                                }
                            }

                            $itemData = [
                                'panel_item_name' => $ri->panelPanelItem->panelItem->name ?? 'Unknown Item',
                                'result_value' => $ri->value,
                                'panel_item_unit' => $ri->panelPanelItem->panelItem->unit,
                                'result_status' => $flagDescription,
                                'reference_range' => $ri->reference_range_id != null && $ri->referenceRange ? $ri->referenceRange->value : null,
                                'comments' => []
                            ];

                            foreach ($ri->panelComments as $pc) {
                                if ($pc->masterPanelComment) {
                                    $itemData['comments'][] = $pc->masterPanelComment->comment;
                                }
                            }

                            $categorizedItems[$categoryName][] = $itemData;
                        } catch (Exception $e) {
                            Log::error('Error processing test result item', [
                                'error' => $e->getMessage(),
                                'result_item_id' => $ri->id ?? null,
                                'test_result_id' => $tr->id
                            ]);
                        }
                    }

                    $testResultData = [
                        'patient_info' => $patientInfo,
                        'blood_test_results' => $categorizedItems
                    ];

                    // Send to GenAI
                    $finalAnalysis = $this->sendToGemini($testResultData);

                    if (!$finalAnalysis) {
                        $tr->is_reviewed = false;
                        Log::error('AI analysis failed for test result', ['test_result_id' => $tr->id]);
                        continue;
                    } else {
                        $tr->is_reviewed = true;
                    }

                    $tr->save();

                    $doctorReview = DoctorReview::firstOrCreate(
                        ['test_result_id' => $tr->id],
                        [
                            'compiled_results' => $testResultData,
                            'review' => $finalAnalysis,
                            'is_sync' => false,
                        ]
                    );

                    $processedResults[] = [
                        'test_result_id' => $tr->id,
                        'doctor_review_id' => $doctorReview->id,
                        'patient_age' => $patientInfo['patient_age'],
                        'patient_gender' => $patientInfo['patient_gender'],
                        'processed_at' => now()->toISOString()
                    ];
                } catch (Exception $e) {
                    Log::error('Error processing individual test result', [
                        'error' => $e->getMessage(),
                        'test_result_id' => $tr->id ?? null,
                        'trace' => $e->getTraceAsString()
                    ]);
                }
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'processed_count' => count($processedResults),
                    'total_found' => $testResults->count(),
                    'processed_results' => $processedResults
                ],
                'message' => count($processedResults) > 0 ? 'Test results processed successfully' : 'No test results could be processed'
            ], 200);
        } catch (Exception $e) {
            Log::error('Error in DoctorReviewController index method', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while processing test results',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    private function sendToGemini($results)
    {
        // Increase execution time limit
        ini_set('max_execution_time', 300); // 5 minutes
        ini_set('memory_limit', '512M');
        $promptEg = json_encode($results);

        $apiKey = env('GEMINI_API_KEY');
        $systemPrompt = "## ROLE
            You are an experienced Malaysian doctor specializing in laboratory medicine.

            ## TASK  
            Analyze blood test JSON data and generate a structured medical report.

            ## DATA HANDLING
            - **Only analyze tests with valid result_value** (skip if null or empty)
            - **Ignore empty comments arrays []** - don't reference comments
            - **Use result_status as primary indicator** (Normal/High/Low takes precedence)
            - **Group related tests by panel** (e.g., \"liver function tests\" not individual enzymes)
            - **Skip tests with null panel_item_unit** but still analyze the result

            ## PATIENT CONTEXT  
            - **Always consider patient_age and patient_gender** for age/gender-appropriate interpretation
            - **Use demographic-appropriate reference ranges and risks**

            ## CONTENT PRIORITIES
            1. **Abnormal results first** (High/Low status)
            2. **Normal results for reassurance** (group similar tests)
            3. **Age/gender-specific health considerations**

            ## FORMATTING RULES
            - **Use numbered lists only** (1., 2., 3.)
            - **Maximum 25 words per point**
            - **NO bullet points, asterisks, or sub-bullets** 
            - **NO indentation or nested lists**
            - **Each numbered point must be complete sentence on single line**
            - **FORBIDDEN: any *, -, •, or sub-numbering**
            - **Group related tests**: \"liver function tests\" not \"AST, ALT, ALP individually\"
            - **Use panel names**: \"general biochemistry\" not individual test names
            - **Patient-friendly language**: avoid medical jargon

            ## MEDICAL INTERPRETATION
            - **Use result_status as primary guide** (Normal/High/Low)
            - **Cross-reference with reference_range when status is unclear**
            - **For normal results**: combine into single reassuring statements
            - **For abnormal results**: explain clinical significance briefly

            ## REQUIRED EXPLANATIONS FOR COMMON FINDINGS
            - **Elevated cholesterol** → mention heart disease risk
            - **Blood in urine** → mention possible causes (stones, infection, kidney issues)
            - **Elevated inflammation markers** → mention possible infections or inflammatory conditions  
            - **Low hemoglobin** → mention anemia symptoms and possible causes
            - **Abnormal liver enzymes** → mention liver function concerns
            - **High blood sugar** → mention diabetes risk or poor control

            ## CLINICAL CONTEXT REQUIREMENTS
            **Never just state results - always explain their significance:**
            - What does this finding suggest about the patient's health?
            - What are the potential causes or implications?
            - What health risks does this create?
            - What does this finding rule in or rule out?

            **Use explanatory language:**
            - \"indicating...\" 
            - \"suggesting possible...\"
            - \"may signal...\"
            - \"increases risk of...\"
            - \"could be due to...\"
            - \"requires investigation for...\"

            ## STRUCTURE REQUIREMENTS
            **Blood Test Summary Report**
            **Patient**: [age]-year-old [gender]

            **Summaries**
            1. [Most significant finding OR grouped normal results]
            2. [Secondary findings]
            3. [Reassurance about normal results]

            **Plans & Recommendations**
            1. [Most urgent action if abnormal results]
            2. [Lifestyle recommendations]
            3. [Follow-up monitoring]

            ## FORBIDDEN ACTIONS
            - **Never mention null values or missing data**
            - **Don't list individual normal tests separately** 
            - **Don't reference empty comments**
            - **Don't use medical abbreviations without explanation**
            - **Don't display numerical values** - describe as elevated/normal/low
            - **Plain text for all content**
            - **No symbols, superscripts, or subscripts**
            - **ABSOLUTELY NO sub-bullets, em dash, asterisks (*), or indented lists**
            - **NO nested formatting of any kind**

            ## SPECIAL HANDLING FOR YOUR JSON
            - **result_status contains \"(applies to non-numeric results)\"** - ignore this phrase, use Normal/High/Low only
            - **panel_item_unit may be null** - still analyze the result
            - **All comments are empty []** - proceed without comment context
            - **All results appear Normal** - focus on reassurance and prevention

            ## SPECIFIC RECOMMENDATIONS

            **Medication Triggers:**
            - LDL > 2.58 mmol/L → recommend Powerlife Arespid
            - HbA1c > 5.8% → recommend Powerlife Diareso
            - HbA1c ≥ 6.5% → refer for diabetes management
            - Non-reactive Hepatitis B antibodies → suggest vaccination (only if Hepatitis B results present)

            **Referral Criteria:**
            - Abnormal liver function → clinical follow-up
            - Persistent hematuria → urology referral
            - Abnormal blood counts → hematology consideration
            - All patients with abnormalities → nutritionist referral

            ## CORRECT FORMAT EXAMPLE
            **Plans & Recommendations**
            1. Follow heart-healthy diet with reduced saturated fats and regular exercise for cholesterol management.
            2. Include iron-rich foods like lean meat and spinach if mild anemia is suspected.
            3. Repeat lipid profile in 3 months after dietary changes and annual health screening.

            ## WRONG FORMAT (DO NOT USE)
            **Plans & Recommendations**
            1. **Manage cholesterol and heart health**
            * Reduce fried foods
            * Include more vegetables
            2. **Address possible mild microcytosis**
            * Include iron-rich foods

            ## EXAMPLE OUTPUT
            **Summaries**
            1. Blood Group: O+
            2. Blood sugar levels are significantly elevated, indicating possible diabetes
            3. Hepatitis B surface antibody is not detected, indicating a lack of protective immunity.

            **Plans & Recommendations**
            1. For low cholesterol diet, avoid high calories and high trans-fat food intake as well as to increase physical activity.
            2. To repeat lipid profile 3 months after lifestyle modification.
            3. Low eGFR might indicates reduced kidney function likely due to age factor and underlying chronic illness.

            ## CRITICAL REMINDER
            Each numbered point (1., 2., 3.) must be a complete, standalone sentence with NO sub-points, bullets, or additional formatting. Write everything in simple numbered list format only.
            ";

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'X-goog-api-key' => $apiKey,
        ])->post('https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent', [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $systemPrompt . "\n\n" . $promptEg]
                    ]
                ]
            ],
            'generationConfig' => [
                'temperature' => 0.2,
                'topP' => 0.5,
                'topK' => 40,
                'maxOutputTokens' => 1000,
                'candidateCount' => 1,
                'stopSequences' => []
            ],
            'safetySettings' => [
                [
                    'category' => 'HARM_CATEGORY_HARASSMENT',
                    'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'
                ],
                [
                    'category' => 'HARM_CATEGORY_HATE_SPEECH',
                    'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'
                ],
                [
                    'category' => 'HARM_CATEGORY_SEXUALLY_EXPLICIT',
                    'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'
                ],
                [
                    'category' => 'HARM_CATEGORY_DANGEROUS_CONTENT',
                    'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'
                ]
            ]
        ]);

        if ($response->successful()) {
            $genAiResult = $response->json();

            $messageContent = $genAiResult['candidates'][0]['content']['parts'][0]['text'] ?? 'No response from AI';

            // Convert **bold** text to <strong>
            $messageContent = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $messageContent);

            // Ensure section titles and numbered lists are formatted correctly
            $formattedHtml = preg_replace([
                '/(<strong>.*?<\/strong>)/',   // Bold section titles
                '/(\d+)\.\s(?=[A-Za-z])/'      // Numbered lists (only when followed by a letter)
            ], [
                '<br><br>$1',  // Ensure section titles have extra space
                '<br>$1. '     // Ensure numbered lists start on a new line without breaking content
            ], $messageContent);

            return $formattedHtml;
        } else {
            Log::error('OpenAI API request failed', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);

            return null;
        }
    }

    private function sendToOpenAI($results)
    {
        $apiKey = env('OPENAI_API_KEY');
        $promptEg = json_encode($results);

        $systemPrompt = "## ROLE
            You are an experienced Malaysian doctor specializing in laboratory medicine.

            ## TASK  
            Analyze blood test JSON data and generate a structured medical report.

            ## DATA HANDLING
            - **Only analyze tests with valid result_value** (skip if null or empty)
            - **Ignore empty comments arrays []** - don't reference comments
            - **Use result_status as primary indicator** (Normal/High/Low takes precedence)
            - **Group related tests by panel** (e.g., \"liver function tests\" not individual enzymes)
            - **Skip tests with null panel_item_unit** but still analyze the result

            ## PATIENT CONTEXT  
            - **Always consider patient_age and patient_gender** for age/gender-appropriate interpretation
            - **Use demographic-appropriate reference ranges and risks**

            ## CONTENT PRIORITIES
            1. **Abnormal results first** (High/Low status)
            2. **Normal results for reassurance** (group similar tests)
            3. **Age/gender-specific health considerations**

            ## FORMATTING RULES
            - **Use numbered lists only** (1., 2., 3.)
            - **Maximum 25 words per point**
            - **NO bullet points, asterisks, or sub-bullets** 
            - **NO indentation or nested lists**
            - **Each numbered point must be complete sentence on single line**
            - **FORBIDDEN: any *, -, •, or sub-numbering**
            - **Group related tests**: \"liver function tests\" not \"AST, ALT, ALP individually\"
            - **Use panel names**: \"general biochemistry\" not individual test names
            - **Patient-friendly language**: avoid medical jargon

            ## MEDICAL INTERPRETATION
            - **Use result_status as primary guide** (Normal/High/Low)
            - **Cross-reference with reference_range when status is unclear**
            - **For normal results**: combine into single reassuring statements
            - **For abnormal results**: explain clinical significance briefly

            ## STRUCTURE REQUIREMENTS
            **Blood Test Summary Report**
            **Patient**: [age]-year-old [gender]

            **Summaries**
            1. [Most significant finding OR grouped normal results]
            2. [Secondary findings]
            3. [Reassurance about normal results]

            **Plans & Recommendations**
            1. [Most urgent action if abnormal results]
            2. [Lifestyle recommendations]
            3. [Follow-up monitoring]

            ## FORBIDDEN ACTIONS
            - **Never mention null values or missing data**
            - **Don't list individual normal tests separately** 
            - **Don't reference empty comments**
            - **Don't use medical abbreviations without explanation**
            - **Don't display numerical values** - describe as elevated/normal/low
            - **Plain text for all content**
            - **No symbols, superscripts, or subscripts**
            - **ABSOLUTELY NO sub-bullets, em dash, asterisks (*), or indented lists**
            - **NO nested formatting of any kind**

            ## SPECIAL HANDLING FOR YOUR JSON
            - **result_status contains \"(applies to non-numeric results)\"** - ignore this phrase, use Normal/High/Low only
            - **panel_item_unit may be null** - still analyze the result
            - **All comments are empty []** - proceed without comment context
            - **All results appear Normal** - focus on reassurance and prevention

            ## SPECIFIC RECOMMENDATIONS

            **Medication Triggers:**
            - LDL > 2.58 mmol/L → recommend Powerlife Arespid
            - HbA1c > 5.8% → recommend Powerlife Diareso
            - HbA1c ≥ 6.5% → refer for diabetes management
            - Non-reactive Hepatitis B antibodies → suggest vaccination (only if Hepatitis B results present)

            **Referral Criteria:**
            - Abnormal liver function → clinical follow-up
            - Persistent hematuria → urology referral
            - Abnormal blood counts → hematology consideration
            - All patients with abnormalities → nutritionist referral

            ## CORRECT FORMAT EXAMPLE
            **Plans & Recommendations**
            1. Follow heart-healthy diet with reduced saturated fats and regular exercise for cholesterol management.
            2. Include iron-rich foods like lean meat and spinach if mild anemia is suspected.
            3. Repeat lipid profile in 3 months after dietary changes and annual health screening.

            ## WRONG FORMAT (DO NOT USE)
            **Plans & Recommendations**
            1. **Manage cholesterol and heart health**
            * Reduce fried foods
            * Include more vegetables
            2. **Address possible mild microcytosis**
            * Include iron-rich foods

            ## EXAMPLE OUTPUT
            **Summaries**
            1. Blood Group: O+
            2. Blood sugar levels are significantly elevated, indicating possible diabetes
            3. Hepatitis B surface antibody is not detected, indicating a lack of protective immunity.

            **Plans & Recommendations**
            1. For low cholesterol diet, avoid high calories and high trans-fat food intake as well as to increase physical activity.
            2. To repeat lipid profile 3 months after lifestyle modification.
            3. Low eGFR might indicates reduced kidney function likely due to age factor and underlying chronic illness.

            ## CRITICAL REMINDER
            Each numbered point (1., 2., 3.) must be a complete, standalone sentence with NO sub-points, bullets, or additional formatting. Write everything in simple numbered list format only.

            ";

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $apiKey,
        ])->post('https://api.openai.com/v1/chat/completions', [
            'model' => 'gpt-5',
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $promptEg]
            ],
            'temperature' => 0.2,  // Lower temperature ensures factual and structured responses
            'max_tokens' => 1000,  // Adjust as needed to control response length
            'top_p' => 0.5,  // Limits randomness, keeping responses relevant
            'frequency_penalty' => 0.4,  // Reduces repetitive words
            'presence_penalty' => 0.1   // Encourages varied wording without going off-topic
        ]);

        if ($response->successful()) {
            $openaiResult = $response->json();
            $messageContent = $openaiResult['choices'][0]['message']['content'] ?? 'No response from AI';

            // Convert **bold** text to <strong>
            $messageContent = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $messageContent);

            // Ensure section titles and numbered lists are formatted correctly
            $formattedHtml = preg_replace([
                '/(<strong>.*?<\/strong>)/',   // Bold section titles
                '/(\d+)\.\s(?=[A-Za-z])/'      // Numbered lists (only when followed by a letter)
            ], [
                '<br><br>$1',  // Ensure section titles have extra space
                '<br>$1. '     // Ensure numbered lists start on a new line without breaking content
            ], $messageContent);

            return $formattedHtml;
        } else {
            Log::error('OpenAI API request failed', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);

            return null;
        }
    }
}
/* $systemPrompt =
            "You are an experienced doctor in Malaysia. Generate a structured Blood Test Summary Report with two sections: Summaries and Plans & Recommendations based on JSON result given.

            ## PATIENT COMMUNICATION GUIDELINES

            **Language & Tone:**
            - Use neutral, professional tone: \"The lab results indicate...\" / \"Findings suggest...\" / \"Test results reveal...\"
            - Use impersonal phrasing:
            * Instead of \"Your cholesterol is high\" → \"The cholesterol levels are elevated\"
            * Instead of \"You should follow a diet\" → \"A low-sugar diet is recommended\"
            * Instead of \"Your kidney function is normal\" → \"The kidney function tests are normal\"
            * Instead of \"You need to see a doctor\" → \"Medical consultation is recommended\"
            - Replace medical jargon with patient-friendly terms:
            * \"microscopic hematuria\" → \"small amounts of blood in urine\"
            * \"glycated haemoglobin\" → \"long-term blood sugar levels\"
            * \"lymphocyte counts\" → \"infection-fighting white blood cells\"
            * \"erythrocyte sedimentation rate\" → \"inflammation markers\"
            * \"estimated glomerular filtration rate\" → \"kidney filtering function\"
            - Use \"higher than normal\" instead of \"markedly elevated\"
            - Use \"blood sugar\" instead of \"glucose\"
            - Use \"cholesterol levels\" instead of \"lipid profile parameters\"

            **Content Priorities (in order):**
            1. Life-threatening or urgent abnormalities
            2. Diabetes/chronic disease indicators  
            3. Infection or inflammation markers
            4. Normal results for reassurance (minimum 2 mentions)
            5. Monitoring recommendations

            ## MEDICAL CONTENT RULES

            **Panel Naming:**
            - STRICTLY use group names only: \"renal profile,\" \"liver function tests,\" \"lipid profile,\" \"full blood count,\" \"urine analysis\"
            - NEVER list individual test components (e.g., don't say \"urea, creatinine, electrolytes\")
            - Only mention specific parameters when highlighting abnormalities

            **Clinical Interpretation:**
            - Compare results to standard reference ranges considering age/gender
            - Use descriptive terms: \"slightly elevated,\" \"mildly low,\" \"within normal range\"
            - Clarify risks for borderline results (e.g., prediabetes)
            - Do not display numerical values, only observations
            - Skip any parameters with null values
            - **Follow comments when available**: If JSON contains \"comments\" field, use this information to:
            * Explain what abnormal results mean (e.g., \"indicating Type 2 diabetes\" not just \"higher than normal\")
            * Provide clinical context from the comments
            * Explain health implications and risks
            * Use comment guidelines for diagnostic categories (normal/prediabetes/diabetes)
            * Reference Malaysian clinical practice guidelines when mentioned in comments

            **Explanation Requirements:**
            - Don't just state results - explain their meaning and implications
            - For elevated HbA1c: specify if it indicates prediabetes, diabetes, or needs further testing
            - For abnormal blood counts: explain potential causes (infection, inflammation, etc.)
            - For urine abnormalities: explain what they might suggest
            - Use comments to provide accurate diagnostic interpretations

            **Special Considerations:**
            - Microcytic anemia: suggest iron studies or hemoglobin electrophoresis
            - Elevated potassium with hemolysis: recommend repeat test
            - Positive urine nitrites: evaluate for UTI
            - Low WBC: assess for infection/inflammation
            - Deranged liver function: refer to doctor immediately
            - Normal urine results: explicitly mention for reassurance

            ## OUTPUT STRUCTURE

            **Summaries Section (5-6 points maximum):**
            - Each point: maximum 25 words (increased from 20 to allow explanations)
            - Combine related normal findings: \"Kidney and liver function tests show normal results\"
            - Lead with most significant abnormalities AND their clinical meaning
            - Include 2-3 normal results for patient reassurance
            - One finding per point - don't mix different panels
            - Use available comments to explain what abnormal results indicate
            - Provide context: \"Long-term blood sugar levels indicate Type 2 diabetes requiring management\"

            **Plans & Recommendations Section (4-6 points maximum):**
            - Start with most urgent actions
            - Use impersonal, recommendation language: \"A low-sugar diet is advised\" not \"You should follow a low-sugar diet\"
            - Combine related lifestyle advice into single points
            - Use action-oriented language: \"Follow a low-sugar diet\" not \"Advise adopting balanced nutrition\"
            - Each recommendation: maximum 25 words
            - Group monitoring/follow-up items together

            ## SPECIFIC RECOMMENDATIONS

            **Medication Triggers:**
            - LDL > 2.58 mmol/L → recommend Powerlife Arespid
            - HbA1c > 5.8% → recommend Powerlife Diareso
            - HbA1c ≥ 6.5% → refer for diabetes management
            - Non-reactive Hepatitis B antibodies → suggest vaccination (only if Hepatitis B results present)

            **Referral Criteria:**
            - Abnormal liver function → clinical follow-up
            - Persistent hematuria → urology referral
            - Abnormal blood counts → hematology consideration
            - All patients with abnormalities → nutritionist referral

            ## TECHNICAL FORMATTING

            - Use numbered lists (1., 2., 3.) throughout - NO bullets, asterisks, or dashes
            - Bold section headers only: **Summaries** and **Plans & Recommendations**
            - Plain text for all content
            - No symbols, superscripts, or subscripts
            - Write out comparisons: \"more than,\" \"less than\"

            ## QUALITY TARGETS

            - Total report: 200-250 words maximum
            - Eliminate redundant phrases: \"at this time,\" \"currently,\" \"at present\"
            - Focus on actionable information patients need
            - Prioritize clinical significance over comprehensive listing
            - Balance reassurance with necessary medical action

            ## REPORT TEMPLATE

            **Blood Test Summary Report**

            **Summaries**
            1. [Most urgent abnormality in simple terms - max 30 words]
            2. [Secondary abnormality if present - max 30 words]  
            3. [Combined normal results for reassurance - max 30 words]
            4. [Additional significant finding if needed - max 30 words]
            5. [Overall health status summary - max 30 words]

            **Plans & Recommendations**
            1. [Most urgent action required - max 25 words]
            2. [Lifestyle/dietary changes - max 25 words]
            3. [Medication if criteria met - max 25 words]
            4. [Follow-up/monitoring plan - max 25 words]
            5. [Additional referrals if needed - max 25 words]";*/