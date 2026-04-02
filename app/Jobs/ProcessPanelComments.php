<?php

namespace App\Jobs;

use App\Http\Requests\InnoquestResultRequest;
use App\Models\DeliveryFile;
use App\Models\PanelProfile;
use App\Models\TestResult;
use App\Models\TestResultProfile;
use App\Models\MasterPanelComment;
use App\Models\MasterPanelItem;
use App\Models\MasterPanel;
use App\Models\Panel;
use App\Models\PanelComment;
use App\Models\PanelItem;
use App\Models\PanelPanelItem;
use App\Models\ReferenceRange;
use App\Models\TestResultComment;
use App\Models\TestResultItem;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class ProcessPanelComments implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $batchNumber;
    protected $deliveryFileIds;
    protected $labId;

    public $timeout = 600; // 10 minutes per job
    public $tries = 3;
    public $backoff = [60, 300, 900]; // Retry after 1min, 5min, 15min

    public function __construct($batchNumber, $deliveryFileIds, $labId)
    {
        $this->batchNumber = $batchNumber;
        $this->deliveryFileIds = $deliveryFileIds;
        $this->labId = $labId;
    }

    public function handle()
    {
        $batchStart = microtime(true);
        $batchProcessedFiles = 0;
        $batchFailedFiles = 0;
        $batchComments = 0;
        $batchExistingLabNumbers = 0;
        $batchNonExistingLabNumbers = 0;
        $batchErrors = [];

        Log::info("Job batch {$this->batchNumber} started with " . count($this->deliveryFileIds) . " files");

        try {
            DB::beginTransaction();

            $files = DeliveryFile::whereIn('id', $this->deliveryFileIds)
                ->select(['id', 'json_content'])
                ->get();

            foreach ($files as $file) {
                try {
                    // Decode JSON content to array
                    $jsonData = json_decode($file->json_content, true);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        $batchErrors[] = 'Invalid JSON content: ' . json_last_error_msg();
                        $batchFailedFiles++;
                        continue;
                    }

                    $request = new InnoquestResultRequest();
                    $validator = Validator::make($jsonData, $request->rules());

                    if ($validator->fails()) {
                        $batchErrors[] = 'Validation failed: ' . json_encode($validator->errors());
                        $batchFailedFiles++;
                        continue;
                    }

                    $validated = $validator->validated();

                    foreach ($validated['Orders'] as $key => $od) {
                        if (filled($od['Observations'])) {
                            foreach ($od['Observations'] as $key => $obv) {
                                $lab_no = $obv['FillerOrderNumber'];

                                // Get panel code and name from observation
                                $panel_code = $obv['ProcedureCode'];
                                $panel_name = $obv['ProcedureDescription'];

                                // Check if panel is TAG ON first
                                $isTagOn = $this->isTagOnItem($panel_name, $panel_code);

                                // Find or create panel
                                $panel = $this->findOrCreatePanel($this->labId, $panel_code, $panel_name);
                                $panel_id = $panel->id;

                                //get profile code (optional)
                                $panel_profile_id = $this->findOrCreateProfile($this->labId, $obv['PackageCode']);

                                $testResult = TestResult::with(['testResultItems', 'testResultProfiles'])
                                    ->where('lab_no', $lab_no)->first();

                                if ($testResult) {
                                    $batchExistingLabNumbers++;
                                    $test_result_id = $testResult->id;

                                    if ($panel_profile_id) {
                                        //create test result profile
                                        TestResultProfile::firstOrCreate(
                                            [
                                                'test_result_id' => $test_result_id,
                                                'panel_profile_id' => $panel_profile_id,
                                            ]
                                        );
                                    }

                                    //results
                                    $results = $obv['Results'];

                                    // Track last created TestResultItem for comments
                                    $lastTestResultItem = null;

                                    //check if results exist
                                    if (filled($results)) {
                                        foreach ($results as $key => $res) {
                                            //check if value exist and store to variable
                                            $result_value = filled($res['Value']) ? $res['Value'] : null;
                                            $unit = filled($res['Units']) ? $res['Units'] : null;
                                            $result_flag = filled($res['Flags']) ? $res['Flags'] : null;

                                            //store field value to variable
                                            $identifier = $res['Identifier'];

                                            //result items 
                                            if (filled($res['Text']) && ($res['Text'] != 'COMMENT' && $res['Text'] != 'NOTE')) {
                                                // 1. Create or find master panel item - Skip translation
                                                $masterPanelItem = MasterPanelItem::updateOrCreate(
                                                    [
                                                        'name' => $res['Text'],
                                                        'unit' => $unit,
                                                    ],
                                                    [
                                                        'chi_character' => null, // Skip Google Translate
                                                        'unit' => $unit,
                                                    ]
                                                );

                                                // 2. Create panel item with master panel item reference
                                                $panel_item = PanelItem::updateOrCreate([
                                                    'lab_id' => $this->labId,
                                                    'master_panel_item_id' => $masterPanelItem->id,
                                                    'identifier' => $identifier
                                                ], [
                                                    'name' => $res['Text'],
                                                    'unit' => $masterPanelItem->unit,
                                                    'chi_character' => null,
                                                ]);

                                                $panel_item_id = $panel_item->id;

                                                // 3. Link panel item to panel through pivot table
                                                $panel->panelItems()->syncWithoutDetaching([$panel_item_id]);

                                                //get panel panel item id
                                                $panel_panel_item_id = PanelPanelItem::where('panel_id', $panel_id)->where('panel_item_id', $panel_item_id)->first()?->id;

                                                //create reference range
                                                $ref_range_id = null;
                                                if (filled($res['ReferenceRange'])) {
                                                    $ref_range = ReferenceRange::firstOrCreate(
                                                        [
                                                            'value' => $res['ReferenceRange'],
                                                            'panel_panel_item_id' => $panel_panel_item_id,
                                                        ]
                                                    );
                                                    $ref_range_id = $ref_range->id;
                                                }

                                                //check for existing result item to determine hasAmended
                                                $existing_test_result_item = TestResultItem::where('test_result_id', $test_result_id)
                                                    ->where('panel_panel_item_id', $panel_panel_item_id)
                                                    ->first();

                                                $hasAmended = false;

                                                if ($existing_test_result_item) {
                                                    // Compare existing value with new value
                                                    $existing_value = $existing_test_result_item->value;

                                                    // Normalize empty strings to null for consistent comparison
                                                    $normalized_existing = $existing_value === '' ? null : $existing_value;
                                                    $normalized_new = $result_value === '' ? null : $result_value;

                                                    $hasAmended = $normalized_existing !== $normalized_new;
                                                }

                                                //final insert/update result item
                                                $lastTestResultItem = TestResultItem::updateOrCreate(
                                                    [
                                                        'test_result_id' => $test_result_id,
                                                        'panel_panel_item_id' => $panel_panel_item_id,
                                                    ],
                                                    [
                                                        'reference_range_id' => $ref_range_id,
                                                        'value' => $result_value,
                                                        'flag' => $result_flag,
                                                        'sequence' => $key,
                                                        'is_tagon' => $isTagOn,
                                                        'has_amended' => $hasAmended
                                                    ]
                                                );
                                            }

                                            //panel comments - create both master and panel-specific comments
                                            if (($res['Text'] == 'NOTE' || $res['Text'] == 'COMMENT') && isset($panel_id) && $lastTestResultItem) {
                                                // Create master panel comment if doesn't exist
                                                $masterPanelComment = MasterPanelComment::firstOrCreate(
                                                    [
                                                        'comment' => $result_value
                                                    ]
                                                );

                                                // Check if panel comment already exists for this combination
                                                $existingPanelComment = PanelComment::where([
                                                    'panel_id' => $panel_id,
                                                    'master_panel_comment_id' => $masterPanelComment->id,
                                                ])->first();

                                                if (!$existingPanelComment) {
                                                    // Create new panel comment
                                                    $panelComment = PanelComment::create([
                                                        'panel_id' => $panel_id,
                                                        'master_panel_comment_id' => $masterPanelComment->id,
                                                    ]);
                                                }

                                                $panel_comment_id = $existingPanelComment->id ?? $panelComment->id;

                                                // Create relationship using TestResultComment model - linked to last created TestResultItem
                                                TestResultComment::firstOrCreate([
                                                    'test_result_item_id' => $lastTestResultItem->id,
                                                    'panel_comment_id' => $panel_comment_id,
                                                ]);

                                                $batchComments++;
                                            }
                                        }
                                    }
                                } else {
                                    // Lab number doesn't exist - log and continue
                                    Log::warning('Lab number not found in TestResult', [
                                        'lab_no' => $lab_no,
                                        'panel_code' => $panel_code,
                                        'panel_name' => $panel_name
                                    ]);
                                    $batchNonExistingLabNumbers++;
                                }
                            }
                        }
                    }

                    $batchProcessedFiles++;

                } catch (Exception $e) {
                    Log::error('Error processing delivery file in job', [
                        'error' => $e->getMessage(),
                        'file_id' => $file->id,
                        'batch' => $this->batchNumber
                    ]);
                    $batchErrors[] = 'File processing error: ' . $e->getMessage();
                    $batchFailedFiles++;
                }
            }

            DB::commit();

        } catch (Exception $e) {
            DB::rollBack();
            Log::error("Job batch {$this->batchNumber} failed", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e; // Rethrow to trigger retry
        }

        // Record batch statistics
        $batchTime = round(microtime(true) - $batchStart, 2);
        $batchStat = [
            'batch_number' => $this->batchNumber,
            'processed_files' => $batchProcessedFiles,
            'failed_files' => $batchFailedFiles,
            'comments_created' => $batchComments,
            'existing_lab_numbers' => $batchExistingLabNumbers,
            'non_existing_lab_numbers' => $batchNonExistingLabNumbers,
            'processing_time' => $batchTime . 's',
            'memory_usage' => round(memory_get_peak_usage(true) / 1024 / 1024, 2) . 'MB'
        ];

        Log::info("Job batch {$this->batchNumber} completed", $batchStat);
    }

    private function findOrCreateProfile($lab_id, $profile_code = null)
    {
        if (filled($profile_code)) {
            $panel_profile = PanelProfile::firstOrCreate(
                [
                    'lab_id' => $lab_id,
                    'code' => $profile_code,
                ],
                [
                    'name' => $profile_code,
                ]
            );

            return $panel_profile->id;
        }

        return null;
    }

    private function findOrCreatePanel($lab_id, $panel_code, $panel_name)
    {
        // 1. First, create or find master panel
        $masterPanel = MasterPanel::firstOrCreate([
            'name' => $panel_name
        ]);

        // 2. Create or get Panel with master panel reference
        $panel = Panel::firstOrCreate([
            'lab_id' => $lab_id,
            'master_panel_id' => $masterPanel->id,
            'code' => $panel_code,
        ], [
            'name' => $panel_name
        ]);

        return $panel;
    }

    private function isTagOnItem($panel_name, $panel_code)
    {
        // Check if this is a TAG ON panel based on name or code patterns
        $tagOnPatterns = [
            '/tag.*on/i',
            '/ton/i',
            '/qon/i'
        ];

        foreach ($tagOnPatterns as $pattern) {
            if (preg_match($pattern, $panel_name) || preg_match($pattern, $panel_code)) {
                return true;
            }
        }

        return false;
    }
}