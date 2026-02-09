<?php

namespace App\Jobs\Innoquest;

use App\Constants\Innoquest\PanelPanelItem as PanelPanelItemConstants;
use App\Jobs\SendToAIServer;
use App\Models\DeliveryFile;
use App\Models\Doctor;
use App\Models\Lab;
use App\Models\MasterPanel;
use App\Models\MasterPanelComment;
use App\Models\MasterPanelItem;
use App\Models\Panel;
use App\Models\PanelComment;
use App\Models\PanelItem;
use App\Models\PanelPanelItem;
use App\Models\PanelProfile;
use App\Models\Patient;
use App\Models\ReferenceRange;
use App\Models\TestResult;
use App\Models\TestResultComment;
use App\Models\TestResultItem;
use App\Models\TestResultProfile;
use App\Services\MyHealthService;
use App\Services\OctopusApiService;
use App\Services\PanelInterpretationService;
use Carbon\Carbon;
use DateTime;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessPanelResults implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 300;

    public $tries = 3;

    public $backoff = [30, 60, 120];

    protected $validatedData;

    protected $requestId;

    protected $receivedAt;

    protected $labId;

    public function __construct(array $validatedData, string $requestId, int $labId)
    {
        $this->validatedData = $validatedData;
        $this->requestId = $requestId;
        $this->receivedAt = now();
        $this->labId = $labId;

        $this->onQueue('panel');
    }

    /**
     * Helper method to cache firstOrCreate operations
     * Prevents concurrent jobs from hitting database for same lookups
     * TTL set to 5 minutes (300s) to align with auto-cache release configuration
     */
    protected function cachedFirstOrCreate($model, array $attributes, array $values, $cacheKey, $ttl = 300)
    {
        return Cache::remember($cacheKey, $ttl, function () use ($model, $attributes, $values) {
            return $model::firstOrCreate($attributes, $values);
        });
    }

    public function handle()
    {
        $startTime = microtime(true);

        Log::channel('performance')->info('Panel job started', [
            'request_id' => $this->requestId,
            'received_at' => $this->receivedAt,
            'queue_delay_seconds' => now()->diffInSeconds($this->receivedAt),
            'attempt' => $this->attempts(),
        ]);

        try {
            $result = $this->processPanel();

            $duration = round((microtime(true) - $startTime) * 1000, 2);

            Log::channel('performance')->info('Panel job completed', [
                'request_id' => $this->requestId,
                'test_result_id' => $result['test_result_id'] ?? null,
                'duration_ms' => $duration,
            ]);

            Log::info('Panel results processed successfully', [
                'test_result_id' => $result['test_result_id'] ?? null,
                'lab_no' => $result['lab_no'] ?? null,
                'patient_id' => $result['patient_id'] ?? null,
                'has_pdf' => $result['has_pdf'] ?? false,
                'orders_count' => $result['orders_count'] ?? 0,
                'observations_count' => $result['observations_count'] ?? 0,
                'data_stored' => true,
            ]);

        } catch (Exception $e) {
            $duration = round((microtime(true) - $startTime) * 1000, 2);

            Log::channel('performance')->error('Panel job failed', [
                'request_id' => $this->requestId,
                'duration_ms' => $duration,
                'attempt' => $this->attempts(),
                'error' => $e->getMessage(),
            ]);

            Log::error('Failed to process panel results', [
                'exception' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'has_pdf' => isset($this->validatedData['EncodedBase64pdf']) && filled($this->validatedData['EncodedBase64pdf']),
                'data_stored' => false,
            ]);

            throw $e;
        }
    }

    protected function processPanel()
    {
        $validated = $this->validatedData;
        $lab_id = $this->labId;
        $test_result = null;
        $deliveryFile = null;
        $sending_facility = null;
        $batch_id = null;
        $orders_count = 0;
        $observations_count = 0;
        $patient_id = null;
        $panel = null;

        try {
            DB::beginTransaction();

            if (filled($validated['SendingFacility'])) {
                $sending_facility = $validated['SendingFacility'];
                $batch_id = $validated['MessageControlID'] ?? null;
            }

            $patient_id = $this->findOrCreatePatient($validated['patient'], $batch_id);

            $reference_id = null;
            $orders_count = 0;
            $observations_count = 0;

            foreach ($validated['Orders'] as $key => $od) {
                $orders_count++;
                if (is_null($reference_id) && filled($od['PlacerOrderNumber'])) {
                    $reference_id = strtoupper($od['PlacerOrderNumber']);
                }

                $doctor_name = $od['OrderingProvider']['Name'];
                $doctor_code = $od['OrderingProvider']['Code'];

                $cacheKey = "doctor_{$lab_id}_{$doctor_code}";
                $doctor = $this->cachedFirstOrCreate(
                    Doctor::class,
                    [
                        'lab_id' => $lab_id,
                        'code' => $doctor_code,
                    ],
                    [
                        'name' => $doctor_name,
                    ],
                    $cacheKey
                );

                $doctor_id = $doctor->id;

                if (filled($od['Observations'])) {
                    foreach ($od['Observations'] as $key => $obv) {
                        $observations_count++;

                        $lab_no = $obv['FillerOrderNumber'];

                        $collected_date = $this->convertDatetime($obv['SpecimenDateTime']);
                        $reported_date = $this->convertDatetime($obv['RequestedDateTime']);

                        $panel_code = $obv['ProcedureCode'];
                        $panel_name = $obv['ProcedureDescription'];

                        $isTagOn = $this->isTagOnItem($panel_name, $panel_code);

                        $panel = $this->findOrCreatePanel($lab_id, $panel_code, $panel_name);
                        $panel_id = $panel->id;

                        $panel_profile_id = $this->findOrCreateProfile($lab_id, $obv['PackageCode']);

                        // Phase 2 & 5: Cache TestResult lookup and use updateOrCreate
                        $testResultCacheKey = "test_result_lab_no_{$lab_no}";
                        $cachedTestResult = Cache::get($testResultCacheKey);

                        if ($cachedTestResult) {
                            // Update the cached record with fresh data
                            $cachedTestResult->update([
                                'ref_id' => $reference_id,
                                'doctor_id' => $doctor_id,
                                'patient_id' => $patient_id,
                                'collected_date' => $collected_date,
                                'reported_date' => $reported_date,
                            ]);
                            $test_result = $cachedTestResult;
                        } else {
                            // Use updateOrCreate for atomic operation
                            $test_result = TestResult::updateOrCreate(
                                ['lab_no' => $lab_no],
                                [
                                    'ref_id' => $reference_id,
                                    'doctor_id' => $doctor_id,
                                    'patient_id' => $patient_id,
                                    'collected_date' => $collected_date,
                                    'reported_date' => $reported_date,
                                    'is_completed' => false,
                                ]
                            );
                        }

                        // Update cache with fresh data (5-minute TTL)
                        Cache::put($testResultCacheKey, $test_result, 300);

                        $test_result_id = $test_result->id;

                        if ($panel_profile_id) {
                            TestResultProfile::firstOrCreate(
                                [
                                    'test_result_id' => $test_result_id,
                                    'panel_profile_id' => $panel_profile_id,
                                ]
                            );
                        }

                        $results = $obv['Results'];
                        if (filled($results)) {
                            // Phase 4: Pre-fetch all existing items for this test_result_id for amendment detection
                            $existingItems = TestResultItem::where('test_result_id', $test_result_id)
                                ->pluck('value', 'panel_panel_item_id')
                                ->toArray();

                            // Phase 3: Collect items for batch upsert
                            $itemsToUpsert = [];
                            $commentItems = [];

                            foreach ($results as $key => $res) {
                                $result_value = filled($res['Value']) ? $res['Value'] : null;
                                $unit = filled($res['Units']) ? $res['Units'] : null;
                                $result_flag = filled($res['Flags']) ? $res['Flags'] : null;

                                $identifier = $res['Identifier'];

                                if (filled($res['Text']) && ($res['Text'] != 'COMMENT' && $res['Text'] != 'NOTE')) {
                                    $masterPanelItemCacheKey = 'master_panel_item_'.md5($res['Text'].$unit);
                                    $masterPanelItem = $this->cachedFirstOrCreate(
                                        MasterPanelItem::class,
                                        [
                                            'name' => $res['Text'],
                                            'unit' => $unit,
                                        ],
                                        [
                                            'chi_character' => null,
                                        ],
                                        $masterPanelItemCacheKey
                                    );

                                    $panelItemCacheKey = "panel_item_{$lab_id}_{$masterPanelItem->id}_{$identifier}";
                                    $panel_item = $this->cachedFirstOrCreate(
                                        PanelItem::class,
                                        [
                                            'lab_id' => $lab_id,
                                            'master_panel_item_id' => $masterPanelItem->id,
                                            'identifier' => $identifier,
                                        ],
                                        [
                                            'name' => $res['Text'],
                                            'unit' => $masterPanelItem->unit,
                                        ],
                                        $panelItemCacheKey
                                    );

                                    $panel_item_id = $panel_item->id;

                                    $panel->panelItems()->syncWithoutDetaching([$panel_item_id]);

                                    $panel_panel_item_id = PanelPanelItem::where('panel_id', $panel_id)->where('panel_item_id', $panel_item_id)->first()?->id;

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

                                    // Phase 4: Check amendment using pre-fetched data
                                    $hasAmended = false;
                                    $existingValue = $existingItems[$panel_panel_item_id] ?? null;
                                    if ($existingValue !== null) {
                                        $normalized_existing = $existingValue === '' ? null : $existingValue;
                                        $normalized_new = $result_value === '' ? null : $result_value;
                                        $hasAmended = $normalized_existing !== $normalized_new;
                                    }

                                    // Collect for batch upsert
                                    $itemsToUpsert[] = [
                                        'test_result_id' => $test_result_id,
                                        'panel_panel_item_id' => $panel_panel_item_id,
                                        'reference_range_id' => $ref_range_id,
                                        'value' => $result_value,
                                        'flag' => $result_flag,
                                        'sequence' => $key,
                                        'is_tagon' => $isTagOn,
                                        'has_amended' => $hasAmended,
                                        'created_at' => now(),
                                        'updated_at' => now(),
                                    ];
                                }

                                if (($res['Text'] == 'NOTE' || $res['Text'] == 'COMMENT') && isset($panel_id)) {
                                    // Store comment data for processing after upsert
                                    $commentItems[] = [
                                        'result_value' => $result_value,
                                        'panel_panel_item_id' => $panel_panel_item_id ?? null,
                                    ];
                                }
                            }

                            // Phase 3: Batch upsert all TestResultItems at once
                            if (! empty($itemsToUpsert)) {
                                TestResultItem::upsert(
                                    $itemsToUpsert,
                                    ['test_result_id', 'panel_panel_item_id'],
                                    ['reference_range_id', 'value', 'flag', 'sequence', 'is_tagon', 'has_amended', 'updated_at']
                                );
                            }

                            // Process comments after upsert (need TestResultItem IDs)
                            foreach ($commentItems as $commentData) {
                                $masterPanelComment = MasterPanelComment::firstOrCreate(
                                    [
                                        'comment' => $commentData['result_value'],
                                    ]
                                );

                                $existingPanelComment = PanelComment::where([
                                    'panel_id' => $panel_id,
                                    'master_panel_comment_id' => $masterPanelComment->id,
                                ])->first();

                                if (! $existingPanelComment) {
                                    $panelComment = PanelComment::create([
                                        'panel_id' => $panel_id,
                                        'master_panel_comment_id' => $masterPanelComment->id,
                                    ]);
                                }

                                $panel_comment_id = $existingPanelComment->id ?? $panelComment->id;

                                // Get the TestResultItem for this comment
                                if ($commentData['panel_panel_item_id']) {
                                    $testResultItem = TestResultItem::where('test_result_id', $test_result_id)
                                        ->where('panel_panel_item_id', $commentData['panel_panel_item_id'])
                                        ->first();

                                    if ($testResultItem) {
                                        TestResultComment::firstOrCreate([
                                            'test_result_item_id' => $testResultItem->id,
                                            'panel_comment_id' => $panel_comment_id,
                                        ]);
                                    }
                                }
                            }
                        }
                    }
                }
            }

            // Mark as completed if PDF received (inside transaction)
            $hasPdf = isset($validated['EncodedBase64pdf']) && filled($validated['EncodedBase64pdf']);
            if ($hasPdf && $test_result) {
                $test_result->is_completed = true;
                $test_result->is_reviewed = false;
                $test_result->save();
            }

            DB::commit();

            // AFTER TRANSACTION: Non-critical operations that don't need atomicity
            // These are moved outside to reduce transaction lock hold time

            // Resolve null ref_id via exact IC match from ODB
            if ($test_result && $reference_id === null) {
                try {
                    $patient = Patient::find($patient_id);
                    $patientIc = $patient->icno ?? null;

                    if ($patientIc) {
                        $lab = Lab::find($lab_id);
                        $labCode = $lab->code ?? null;

                        Log::info('Attempting ref_id resolution via IC match', [
                            'test_result_id' => $test_result->id,
                            'lab_no' => $test_result->lab_no,
                            'patient_ic' => $patientIc,
                            'lab_code' => $labCode,
                        ]);

                        $octopusApi = app(OctopusApiService::class);
                        $exactMatch = $octopusApi->getCustomerByIc($patientIc, $labCode);

                        if ($exactMatch && !empty($exactMatch['refid'])) {
                            $test_result->ref_id = $exactMatch['refid'];
                            $test_result->save();

                            Log::info('Resolved null ref_id via exact IC match', [
                                'test_result_id' => $test_result->id,
                                'lab_no' => $test_result->lab_no,
                                'resolved_ref_id' => $exactMatch['refid'],
                                'patient_ic' => $patientIc,
                            ]);
                        } else {
                            Log::info('No exact IC match found for ref_id resolution', [
                                'test_result_id' => $test_result->id,
                                'lab_no' => $test_result->lab_no,
                                'patient_ic' => $patientIc,
                            ]);
                        }
                    }
                } catch (Throwable $e) {
                    Log::error('Failed to resolve null ref_id via IC match', [
                        'test_result_id' => $test_result->id,
                        'error' => $e->getMessage(),
                        'file' => $e->getFile(),
                        'line' => $e->getLine(),
                    ]);
                }
            }

            // Calculate special tests OUTSIDE transaction (queries external DB)
            if ($hasPdf && $test_result) {
                try {
                    Log::info('Starting special test calculation', [
                        'test_result_id' => $test_result->id,
                        'lab_no' => $test_result->lab_no,
                    ]);

                    $this->calculateSpecialTests($test_result);

                    Log::info('Special test calculation completed', [
                        'test_result_id' => $test_result->id,
                    ]);
                } catch (Throwable $e) {
                    // Log error but DO NOT rethrow - allow main job to complete
                    Log::error('Special test calculation failed', [
                        'test_result_id' => $test_result->id,
                        'error' => $e->getMessage(),
                        'file' => $e->getFile(),
                        'line' => $e->getLine(),
                    ]);
                }
            }

            // DeliveryFile tracking OUTSIDE main transaction (non-critical)
            if ($test_result) {
                try {
                    DeliveryFile::firstOrCreate(
                        [
                            'lab_id' => $lab_id,
                            'sending_facility' => $sending_facility,
                            'batch_id' => $batch_id,
                        ],
                        [
                            'json_content' => json_encode($validated),
                            'status' => DeliveryFile::compl,
                        ]
                    );
                } catch (Throwable $e) {
                    // Non-critical - log but don't fail the job
                    Log::warning('Failed to create DeliveryFile record', [
                        'error' => $e->getMessage(),
                        'batch_id' => $batch_id,
                    ]);
                }
            }

            // Dispatch to AI server queue if PDF was received
            if (isset($validated['EncodedBase64pdf']) && filled($validated['EncodedBase64pdf']) && $test_result && $test_result->id) {
                try {
                    SendToAIServer::dispatch($test_result->id);
                    Log::info('Dispatched test result to AI server queue', [
                        'test_result_id' => $test_result->id,
                        'lab_no' => $test_result->lab_no ?? null,
                    ]);
                } catch (Throwable $e) {
                    Log::error('Failed to dispatch test result to AI server queue', [
                        'test_result_id' => $test_result->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            return [
                'test_result_id' => $test_result->id ?? null,
                'lab_no' => $test_result->lab_no ?? null,
                'patient_id' => $patient_id,
                'panel_name' => $panel->name ?? null,
                'has_pdf' => isset($validated['EncodedBase64pdf']) && filled($validated['EncodedBase64pdf']),
                'orders_count' => $orders_count,
                'observations_count' => $observations_count,
            ];

        } catch (Exception $e) {
            DB::rollBack();

            Log::error('Failed to process panel results in job', [
                'error' => $e->getMessage(),
                'request_id' => $this->requestId,
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            throw $e;
        }
    }

    public function failed(Throwable $exception)
    {
        Log::channel('performance')->error('Panel job failed permanently', [
            'request_id' => $this->requestId,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }

    private function findOrCreatePatient(array $patient, $batch_id = null)
    {
        $icno = null;
        $ic_type = Patient::IC_TYPE_OTHERS;
        $patient_gender = null;
        $age = null;

        if (filled($patient['AlternatePatientID'])) {
            $icInfo = checkIcno($patient['AlternatePatientID'], $patient['PatientDOB'] ?? null);
            $icno = $icInfo['icno'];
            $ic_type = $icInfo['type'];
            $patient_gender = $icInfo['gender'];
            $age = $icInfo['age'];
        } else {
            $icno = $patient['PatientID'] ?? 'N/A_'.$batch_id;
        }

        $patient_name = $patient['PatientLastName'];
        $patient_dob = filled($patient['PatientDOB']) ? $patient['PatientDOB'] : null;
        $gender = $patient['PatientGender'];

        $cacheKey = "patient_{$icno}";
        $patient = $this->cachedFirstOrCreate(
            Patient::class,
            [
                'icno' => $icno,
            ],
            [
                'ic_type' => $ic_type,
                'name' => $patient_name,
                'dob' => $patient_dob,
                'age' => $age,
                'gender' => $gender ?? $patient_gender,
            ],
            $cacheKey
        );

        // Update age if it was null and we now have a calculated age
        if ($patient->age === null && $age !== null) {
            $patient->update(['age' => $age]);
            Cache::forget($cacheKey);
            Log::info('Updated patient age from null', [
                'patient_id' => $patient->id,
                'icno' => $icno,
                'new_age' => $age,
            ]);
        }

        // Update dob if patient has null dob but we have one
        if ($patient->dob === null && $patient_dob !== null) {
            $patient->update(['dob' => $patient_dob]);
            Cache::forget($cacheKey);
            Log::info('Updated patient dob from null', [
                'patient_id' => $patient->id,
                'icno' => $icno,
                'new_dob' => $patient_dob,
            ]);
        }

        return $patient->id;
    }

    private function findOrCreatePanel($lab_id, $panel_code, $panel_name)
    {
        $masterPanelCacheKey = 'master_panel_'.md5($panel_name);
        $masterPanel = $this->cachedFirstOrCreate(
            MasterPanel::class,
            ['name' => $panel_name],
            [],
            $masterPanelCacheKey
        );

        $panelCacheKey = "panel_{$lab_id}_{$panel_code}";
        $panel = $this->cachedFirstOrCreate(
            Panel::class,
            [
                'lab_id' => $lab_id,
                'master_panel_id' => $masterPanel->id,
                'code' => $panel_code,
            ],
            [
                'name' => $panel_name,
            ],
            $panelCacheKey
        );

        return $panel;
    }

    private function findOrCreateProfile($lab_id, $profile_code = null)
    {
        if (filled($profile_code)) {
            $panelProfileCacheKey = "panel_profile_{$lab_id}_{$profile_code}";
            $panel_profile = $this->cachedFirstOrCreate(
                PanelProfile::class,
                [
                    'lab_id' => $lab_id,
                    'code' => $profile_code,
                ],
                [
                    'name' => $profile_code,
                ],
                $panelProfileCacheKey
            );

            return $panel_profile->id;
        }

        return null;
    }

    private function convertDatetime($datetime)
    {
        if (empty($datetime)) {
            return null;
        }

        $datetime = trim($datetime);
        $len = strlen($datetime);

        if ($len == 8) {
            return DateTime::createFromFormat('Ymd', $datetime)->format('Y-m-d H:i:s');
        } elseif ($len == 12) {
            return DateTime::createFromFormat('YmdHi', $datetime)->format('Y-m-d H:i:s');
        } elseif ($len == 14) {
            return DateTime::createFromFormat('YmdHis', $datetime)->format('Y-m-d H:i:s');
        }

        return null;
    }

    private function isTagOnItem($panel_name, $panel_code)
    {
        $tagOnKeywords = [
            'TAG',
            'TAGON',
            'ADD ON',
            'ADDON',
            'ADDITIONAL',
        ];

        foreach ($tagOnKeywords as $keyword) {
            if (stripos($panel_name, $keyword) !== false || stripos($panel_code, $keyword) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get platelets value with fallback from primary (61) to alternate (166).
     *
     * @param  \Illuminate\Support\Collection  $testResultItems
     */
    private function getPlateletsValue($testResultItems): ?string
    {
        $item = $testResultItems[PanelPanelItemConstants::PLATELETS] ?? null;
        if ($item !== null && $item->value !== null && $item->value !== '') {
            return $item->value;
        }

        $altItem = $testResultItems[PanelPanelItemConstants::PLATELETS_ALT] ?? null;
        if ($altItem !== null && $altItem->value !== null && $altItem->value !== '') {
            return $altItem->value;
        }

        return null;
    }

    /**
     * Calculate special tests and interpretations for a completed test result.
     * Calculates: CRI-I, CRI-II, AIP, AC, FIB-4, APRI, NFS
     */
    protected function calculateSpecialTests(TestResult $testResult): void
    {
        $panelInterpretationService = app(PanelInterpretationService::class);
        $myHealthService = app(MyHealthService::class);

        // Load patient relationship
        $testResult->load('patient');

        // Get relevant test result items
        $testResultItems = $testResult->testResultItems()
            ->whereIn('panel_panel_item_id', PanelPanelItemConstants::PANEL_PANEL_ITEM_IDS)
            ->get()
            ->keyBy('panel_panel_item_id');

        // 1. Lipid Interpretation (CRI-I, CRI-II, AIP)
        $lr = $panelInterpretationService->lipidInterpretation(
            cri_i: $testResultItems[PanelPanelItemConstants::CRI_I]->value ?? null,
            cri_ii: $testResultItems[PanelPanelItemConstants::CRI_II]->value ?? null,
            aip: $testResultItems[PanelPanelItemConstants::AIP]->value ?? null,
        );

        // 2. Atherogenic Coefficient
        $ac = $panelInterpretationService->calculateAC(
            totalCholesterol: $testResultItems[PanelPanelItemConstants::TOTAL_CHOLESTEROL]->value ?? null,
            hdlCholesterol: $testResultItems[PanelPanelItemConstants::HDL]->value ?? null,
        );

        // 3. FIB-4 Index
        $age = $testResult->patient->age ?? ($testResult->patient->dob ? Carbon::parse($testResult->patient->dob)->age : null);
        $fib = $panelInterpretationService->calculateFIB(
            age: $age,
            ast: $testResultItems[PanelPanelItemConstants::AST]->value ?? null,
            alt: $testResultItems[PanelPanelItemConstants::ALT]->value ?? null,
            plateletCount: $this->getPlateletsValue($testResultItems),
        );

        // 4. APRI - requires AST upper limit from reference range
        $astUpperLimit = null;
        $astItem = $testResultItems[PanelPanelItemConstants::AST] ?? null;

        if ($astItem && $astItem->reference_range_id) {
            $referenceRange = $astItem->referenceRange;
            if ($referenceRange) {
                $astUpperLimit = extractUpperLimit($referenceRange->value);
            }
        }

        $ap = $panelInterpretationService->calculateAPRI(
            ast: $testResultItems[PanelPanelItemConstants::AST]->value ?? null,
            astRef: $astUpperLimit,
            plateletCount: $this->getPlateletsValue($testResultItems),
        );

        // 5. NFS - requires BMI from MyHealth
        $glucoseFastingItem = $testResultItems[PanelPanelItemConstants::GLUCOSE_FASTING_TYPE] ?? null;
        $fasting = $glucoseFastingItem && $glucoseFastingItem->value == 'Fasting';
        $bmi = $myHealthService->getPatientBMI($testResult->patient->icno);

        $nfs = $panelInterpretationService->calculateNFS(
            age: $age,
            bmi: $bmi,
            fasting: $fasting,
            ast: $testResultItems[PanelPanelItemConstants::AST]->value ?? null,
            alt: $testResultItems[PanelPanelItemConstants::ALT]->value ?? null,
            plateletCount: $this->getPlateletsValue($testResultItems),
            albumin: $testResultItems[PanelPanelItemConstants::ALBUMIN]->value ?? null,
        );

        // Compile data for saving
        $data = [
            'cri_i' => [
                'panel_panel_item_id' => $lr['cri_i_panel_panel_item_id'],
                'value' => $testResultItems[PanelPanelItemConstants::CRI_I]->value ?? null,
                'panel_interpretation_id' => $lr['cri_i_interpretation'],
            ],
            'cri_ii' => [
                'panel_panel_item_id' => $lr['cri_ii_panel_panel_item_id'],
                'value' => $testResultItems[PanelPanelItemConstants::CRI_II]->value ?? null,
                'panel_interpretation_id' => $lr['cri_ii_interpretation'],
            ],
            'aip' => [
                'panel_panel_item_id' => $lr['aip_panel_panel_item_id'],
                'value' => $testResultItems[PanelPanelItemConstants::AIP]->value ?? null,
                'panel_interpretation_id' => $lr['aip_interpretation'],
            ],
            'ac' => [
                'panel_panel_item_id' => $ac['panel_panel_item_id'],
                'value' => $ac['value'],
                'panel_interpretation_id' => $ac['ac_interpretation'],
            ],
            'fib' => [
                'panel_panel_item_id' => $fib['panel_panel_item_id'],
                'value' => $fib['value'],
                'panel_interpretation_id' => $fib['fib_interpretation'],
            ],
            'apri' => [
                'panel_panel_item_id' => $ap['panel_panel_item_id'],
                'value' => $ap['value'],
                'panel_interpretation_id' => $ap['apri_interpretation'],
            ],
            'nfs' => [
                'panel_panel_item_id' => $nfs['panel_panel_item_id'],
                'value' => $nfs['value'],
                'panel_interpretation_id' => $nfs['nfs_interpretation'],
            ],
        ];

        // Save special tests
        foreach ($data as $key => $item) {
            if ($item['panel_panel_item_id']) {
                $testResult->testResultSpecialTests()->updateOrCreate(
                    [
                        'test_result_id' => $testResult->id,
                        'panel_panel_item_id' => $item['panel_panel_item_id'],
                    ],
                    [
                        'value' => $item['value'],
                        'panel_interpretation_id' => $item['panel_interpretation_id'],
                    ]
                );
            }
        }
    }
}
