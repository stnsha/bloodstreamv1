<?php

namespace App\Jobs;

use App\Models\DoctorReview;
use App\Models\TestResult;
use App\Models\ResultLibrary;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AIReviewJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public $tries = 2;

    /**
     * The maximum number of seconds the job can run before timing out.
     */
    public $timeout = 1800; // 30 minutes for AI processing

    /**
     * The number of seconds to wait before retrying the job.
     */
    public $backoff = 300; // 5 minutes between retries

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        $this->onQueue('ai-review');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            Log::info('AIReviewJob started');

            $result = $this->processUnreviewedTestResults();

            Log::info('AIReviewJob completed successfully', [
                'processed_count' => $result['processed_count'],
                'failed_count' => $result['failed_count'],
                'total_found' => $result['total_found'],
                'success_rate' => $result['success_rate']
            ]);
        } catch (Exception $e) {
            Log::error('AIReviewJob failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(Exception $exception): void
    {
        Log::error('AIReviewJob failed permanently', [
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts()
        ]);
    }

    /**
     * Process unreviewed test results with AI analysis.
     */
    private function processUnreviewedTestResults(): array
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
                Log::info('No unreviewed test results found');
                return [
                    'processed_count' => 0,
                    'failed_count' => 0,
                    'total_found' => 0,
                    'success_rate' => 0
                ];
            }

            $processedResults = [];
            $failedResults = [];

            // Process test results in chunks of 10
            $testResultChunks = $testResults->chunk(10);

            foreach ($testResultChunks as $chunkIndex => $chunk) {
                Log::info("Processing chunk " . ($chunkIndex + 1) . " of " . $testResultChunks->count(), [
                    'chunk_size' => $chunk->count()
                ]);

                $chunkData = [];
                $chunkTestResults = [];

                // Prepare data for the entire chunk
                foreach ($chunk as $tr) {
                    try {
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

                        $patientInfo = [
                            'patient_age' => $tr->patient->age ?? null,
                            'patient_gender' => $tr->patient->gender ?? null,
                        ];

                        if (!$patientInfo['patient_gender']) {
                            Log::warning('Missing patient gender', [
                                'test_result_id' => $tr->id,
                                'patient_id' => $tr->patient->id ?? 'unknown'
                            ]);
                            $failedResults[] = ['id' => $tr->id, 'reason' => 'Missing patient gender'];
                            continue;
                        }

                        if (!$patientInfo['patient_age']) {
                            Log::info('Patient age is null, continuing with analysis', [
                                'test_result_id' => $tr->id,
                                'patient_id' => $tr->patient->id ?? 'unknown'
                            ]);
                            // Set default age for analysis
                            $patientInfo['patient_age'] = 'unknown';
                        }

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
                                            $flagDescription = $resultLibrary->description;
                                        }
                                    } catch (Exception $e) {
                                        Log::error('Error fetching flag description from ResultLibrary', [
                                            'error' => $e->getMessage(),
                                            'flag' => $ri->flag,
                                            'result_item_id' => $ri->id
                                        ]);
                                        $flagDescription = $ri->flag;
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

                        $testResultData = [
                            'patient_info' => $patientInfo,
                            'blood_test_results' => $categorizedItems,
                            'metadata' => [
                                'test_result_id' => $tr->id,
                                'total_items' => $tr->testResultItems->count(),
                                'valid_items' => $validItemsCount,
                                'categories_count' => count($categorizedItems)
                            ]
                        ];

                        // Store data for batch processing
                        $chunkData[] = $testResultData;
                        $chunkTestResults[] = $tr;
                    } catch (Exception $e) {
                        Log::error('Critical error processing individual test result', [
                            'error' => $e->getMessage(),
                            'test_result_id' => $tr->id ?? 'unknown',
                            'trace' => $e->getTraceAsString()
                        ]);
                        $failedResults[] = ['id' => $tr->id ?? 'unknown', 'reason' => 'Critical processing error'];
                    }
                }

                // Send entire chunk to OpenAI if we have valid data
                if (!empty($chunkData)) {
                    try {
                        $batchAnalysis = $this->sendToOpenAI($chunkData);

                        if ($batchAnalysis && is_array($batchAnalysis) && count($batchAnalysis) === count($chunkTestResults)) {
                            // Process successful AI responses
                            foreach ($chunkTestResults as $index => $tr) {
                                $finalAnalysis = $batchAnalysis[$index] ?? null;

                                if (!$finalAnalysis) {
                                    $tr->is_reviewed = false;
                                    $tr->save();
                                    Log::error('AI analysis failed for test result in batch', ['test_result_id' => $tr->id]);
                                    $failedResults[] = ['id' => $tr->id, 'reason' => 'AI analysis failed in batch'];
                                    continue;
                                }

                                try {
                                    $tr->is_reviewed = true;
                                    $tr->save();
                                } catch (Exception $e) {
                                    Log::error('Failed to update test result status', [
                                        'error' => $e->getMessage(),
                                        'test_result_id' => $tr->id
                                    ]);
                                    $failedResults[] = ['id' => $tr->id, 'reason' => 'Database update failed'];
                                    continue;
                                }

                                try {
                                    $doctorReview = DoctorReview::updateOrCreate(
                                        ['test_result_id' => $tr->id],
                                        [
                                            'compiled_results' => $chunkData[$index],
                                            'review' => $finalAnalysis,
                                            'is_sync' => false
                                        ]
                                    );
                                } catch (Exception $e) {
                                    Log::error('Failed to create/update doctor review', [
                                        'error' => $e->getMessage(),
                                        'test_result_id' => $tr->id,
                                        'trace' => $e->getTraceAsString()
                                    ]);
                                    $failedResults[] = ['id' => $tr->id, 'reason' => 'Doctor review creation failed'];
                                    continue;
                                }

                                $processedResults[] = [
                                    'test_result_id' => $tr->id,
                                    'doctor_review_id' => $doctorReview->id,
                                    'patient_age' => $chunkData[$index]['patient_info']['patient_age'],
                                    'patient_gender' => $chunkData[$index]['patient_info']['patient_gender'],
                                    'categories_processed' => array_keys($chunkData[$index]['blood_test_results']),
                                    'items_count' => $chunkData[$index]['metadata']['valid_items'],
                                    'processed_at' => now()->toISOString()
                                ];
                            }
                        } else {
                            // Batch analysis failed, mark all as failed
                            foreach ($chunkTestResults as $tr) {
                                $tr->is_reviewed = false;
                                $tr->save();
                                $failedResults[] = ['id' => $tr->id, 'reason' => 'Batch AI analysis failed'];
                            }
                            Log::error('Batch AI analysis failed for chunk', [
                                'chunk_index' => $chunkIndex,
                                'chunk_size' => count($chunkTestResults),
                                'response_valid' => is_array($batchAnalysis),
                                'response_count' => is_array($batchAnalysis) ? count($batchAnalysis) : 0
                            ]);
                        }
                    } catch (Exception $e) {
                        // Handle batch processing error
                        foreach ($chunkTestResults as $tr) {
                            $failedResults[] = ['id' => $tr->id, 'reason' => 'Batch processing exception: ' . $e->getMessage()];
                        }
                        Log::error('Exception during batch OpenAI API call', [
                            'error' => $e->getMessage(),
                            'chunk_index' => $chunkIndex,
                            'trace' => $e->getTraceAsString()
                        ]);
                    }
                }
            }

            return [
                'processed_count' => count($processedResults),
                'failed_count' => count($failedResults),
                'total_found' => $testResults->count(),
                'success_rate' => $testResults->count() > 0 ? round((count($processedResults) / $testResults->count()) * 100, 2) : 0,
                'processed_results' => $processedResults,
                'failed_results' => $failedResults
            ];
        } catch (Exception $e) {
            Log::critical('Critical error in AIReviewJob processing', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);

            throw $e;
        }
    }

    /**
     * Send test result data to OpenAI for analysis.
     * Can handle both single test result or batch of test results.
     */
    private function sendToOpenAI($results)
    {
        if (empty($results) || !is_array($results)) {
            Log::error('Invalid results data provided to sendToOpenAI', [
                'results_type' => gettype($results),
                'results_empty' => empty($results)
            ]);
            return false;
        }

        try {
            ini_set('max_execution_time', 300);
            ini_set('memory_limit', '512M');
        } catch (Exception $e) {
            Log::warning('Failed to set execution limits', ['error' => $e->getMessage()]);
        }

        $apiKey = env('OPENAI_API_KEY');
        if (empty($apiKey)) {
            Log::error('OpenAI API key is not configured');
            return false;
        }

        // Detect if this is batch processing or single result
        $isBatch = isset($results[0]) && is_array($results[0]);

        try {
            $promptData = json_encode($results, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('JSON encoding failed: ' . json_last_error_msg());
            }
        } catch (Exception $e) {
            Log::error('Failed to encode results as JSON', [
                'error' => $e->getMessage(),
                'results_structure' => $isBatch ? 'batch_array' : array_keys($results),
                'is_batch' => $isBatch
            ]);
            return false;
        }

        $batchPromptAddition = $isBatch ? "

            ## BATCH PROCESSING
            You are receiving multiple blood test results in an array. Analyze each test result separately and return an array of medical reports in the same order. Each report should follow the same format as described below." : "";

        $systemPrompt = "## ROLE
            You are an experienced Malaysian doctor specializing in laboratory medicine.

            ## TASK  
            Analyze blood test JSON data and generate a structured medical report." . $batchPromptAddition . "

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
            - **FORBIDDEN: any *, -, •, em dash or sub-numbering**
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

            ## CRITICAL REMINDER
            Each numbered point (1., 2., 3.) must be a complete, standalone sentence with NO sub-points, bullets, or additional formatting. Write everything in simple numbered list format only.";

        $requestPayload = [
            'model' => 'gpt-5-2025-08-07',
            'reasoning' => ['effort' => 'medium'],
            'input' => [
                ['role' => 'developer', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $promptData]
            ]
        ];

        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $apiKey,
            ])
                ->timeout(120)
                ->connectTimeout(30)
                ->retry(2, 1000)
                ->post('https://api.openai.com/v1/responses', $requestPayload);
        } catch (Exception $e) {
            Log::error('HTTP request to OpenAI failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }

        if (!$response->successful()) {
            $statusCode = $response->status();
            $responseBody = $response->body();

            Log::error('OpenAI API request failed', [
                'status_code' => $statusCode,
                'response_body' => $responseBody,
                'request_payload_size' => strlen(json_encode($requestPayload))
            ]);

            if ($statusCode === 401) {
                Log::critical('OpenAI API authentication failed - check API key');
            } elseif ($statusCode === 403) {
                Log::critical('OpenAI API access forbidden - check API key permissions');
            } elseif ($statusCode === 429) {
                Log::warning('OpenAI API rate limit exceeded');
            } elseif ($statusCode === 500) {
                Log::error('OpenAI API server error');
            } elseif ($statusCode >= 400 && $statusCode < 500) {
                Log::error('OpenAI API client error', ['status' => $statusCode]);
            } elseif ($statusCode >= 500) {
                Log::error('OpenAI API server error', ['status' => $statusCode]);
            }

            return false;
        }

        try {
            $openaiResult = $response->json();
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('Failed to decode OpenAI response JSON: ' . json_last_error_msg());
            }
        } catch (Exception $e) {
            Log::error('Failed to decode OpenAI response', [
                'error' => $e->getMessage(),
                'response_body_length' => strlen($response->body()),
                'response_preview' => substr($response->body(), 0, 200)
            ]);
            return false;
        }

        // Handle different OpenAI API response structures
        $messageContent = null;
        
        // Try reasoning API structure first
        if (isset($openaiResult['output']) && !empty($openaiResult['output'])) {
            $output = $openaiResult['output'];
            
            // Handle structure: ['output'][1]['content'][0]['text']
            if (is_array($output) && isset($output[1]['content'][0]['text'])) {
                $messageContent = $output[1]['content'][0]['text'];
            }
            // Handle alternative structures
            elseif (is_array($output) && isset($output['content'])) {
                $messageContent = $output['content'];
            } elseif (is_string($output)) {
                $messageContent = $output;
            } elseif (is_array($output) && !empty($output)) {
                // Sometimes reasoning API returns array of responses
                $messageContent = $output[0] ?? null;
                if (is_array($messageContent) && isset($messageContent['content'])) {
                    $messageContent = $messageContent['content'];
                }
            }
        }
        
        // Fallback to standard chat completions structure
        if (!$messageContent && isset($openaiResult['choices']) && !empty($openaiResult['choices'])) {
            $firstChoice = $openaiResult['choices'][0] ?? null;
            if ($firstChoice && isset($firstChoice['message']['content'])) {
                $messageContent = $firstChoice['message']['content'];
            }
        }

        if (empty($messageContent)) {
            Log::error('OpenAI response missing content in all formats', [
                'response_structure' => array_keys($openaiResult),
                'output_structure' => isset($openaiResult['output']) ? 
                    (is_array($openaiResult['output']) ? array_keys($openaiResult['output']) : gettype($openaiResult['output'])) : 'not_present',
                'has_choices' => isset($openaiResult['choices']),
                'has_error' => isset($openaiResult['error']),
                'error_details' => $openaiResult['error'] ?? null,
                'full_response_preview' => substr(json_encode($openaiResult), 0, 500)
            ]);
            return false;
        }

        try {
            if ($isBatch) {
                // For batch processing, try to parse as JSON array first
                $batchResults = json_decode($messageContent, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($batchResults)) {
                    // Process each result in the batch
                    $formattedBatch = [];
                    foreach ($batchResults as $singleResult) {
                        if (is_string($singleResult)) {
                            $formatted = $this->formatOpenAIResponse($singleResult);
                            $formattedBatch[] = $formatted;
                        } else {
                            $formattedBatch[] = $singleResult;
                        }
                    }

                    Log::info('OpenAI batch analysis completed successfully', [
                        'batch_size' => count($formattedBatch),
                        'original_length' => strlen($messageContent),
                        'usage' => $openaiResult['usage'] ?? null
                    ]);

                    return $formattedBatch;
                } else {
                    // Fallback: treat as single response even though batch was expected
                    Log::warning('Batch processing expected but response not parseable as JSON array', [
                        'json_error' => json_last_error_msg(),
                        'content_preview' => substr($messageContent, 0, 200)
                    ]);
                }
            }

            // Single result processing (or batch fallback)
            $formattedHtml = $this->formatOpenAIResponse($messageContent);

            Log::info('OpenAI analysis completed successfully', [
                'original_length' => strlen($messageContent),
                'formatted_length' => strlen($formattedHtml),
                'usage' => $openaiResult['usage'] ?? null,
                'is_batch' => $isBatch
            ]);

            return $isBatch ? [$formattedHtml] : $formattedHtml;
        } catch (Exception $e) {
            Log::error('Failed to format OpenAI response', [
                'error' => $e->getMessage(),
                'original_content_length' => strlen($messageContent),
                'original_content_preview' => substr($messageContent, 0, 100),
                'is_batch' => $isBatch
            ]);

            return $isBatch ? [$messageContent] : $messageContent;
        }
    }

    /**
     * Format OpenAI response with consistent HTML structure
     */
    private function formatOpenAIResponse($content)
    {
        if (empty($content) || !is_string($content)) {
            return $content;
        }

        try {
            // Add <strong> for specific section titles first
            $content = preg_replace(
                '/\b(Blood Test Summary Report|Summaries|Plans & Recommendations)\b/',
                '<strong>$1</strong>',
                $content
            );

            // Convert **bold** text to <strong>
            $messageContent = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $content);

            // Ensure section titles and numbered lists are formatted correctly
            $formattedHtml = preg_replace([
                '/(<strong>.*?<\/strong>)/',   // Bold section titles
                '/(\d+)\.\s(?=[A-Za-z])/'      // Numbered lists (only when followed by a letter)
            ], [
                '<br><br>$1',  // Ensure section titles have extra space
                '<br>$1. '     // Ensure numbered lists start on a new line without breaking content
            ], $messageContent);

            return $formattedHtml;
        } catch (Exception $e) {
            Log::error('Error formatting OpenAI response', [
                'error' => $e->getMessage(),
                'content_preview' => substr($content, 0, 200)
            ]);
            return $content;
        }
    }
}