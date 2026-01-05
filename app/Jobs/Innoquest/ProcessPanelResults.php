<?php

namespace App\Jobs\Innoquest;

use App\Jobs\SendToAIServer;
use App\Models\DeliveryFile;
use App\Models\Doctor;
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
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Exception;
use Throwable;
use DateTime;

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
    protected function cachedFirstOrCreate($model, array $attributes, array $values = [], $cacheKey, $ttl = 300)
    {
        return Cache::remember($cacheKey, $ttl, function() use ($model, $attributes, $values) {
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
                'data_stored' => true
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
                'data_stored' => false
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
                if (is_null($reference_id) && filled($od['PlacerOrderNumber'])) $reference_id = strtoupper($od['PlacerOrderNumber']);

                $doctor_name = $od['OrderingProvider']['Name'];
                $doctor_code = $od['OrderingProvider']['Code'];

                $cacheKey = "doctor_{$lab_id}_{$doctor_code}";
                $doctor = $this->cachedFirstOrCreate(
                    Doctor::class,
                    [
                        'lab_id' => $lab_id,
                        'code' => $doctor_code
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

                        $existingTestResult = TestResult::where('lab_no', $lab_no)->first();

                        if ($existingTestResult) {
                            $existingTestResult->update([
                                'ref_id' => $reference_id,
                                'doctor_id' => $doctor_id,
                                'patient_id' => $patient_id,
                                'collected_date' => $collected_date,
                                'reported_date' => $reported_date,
                            ]);
                            $test_result = $existingTestResult;

                            Log::info('Test result updated - completion status preserved', [
                                'lab_no' => $lab_no,
                                'test_result_id' => $test_result->id,
                                'is_completed' => $test_result->is_completed
                            ]);
                        } else {
                            $test_result = TestResult::create([
                                'lab_no' => $lab_no,
                                'ref_id' => $reference_id,
                                'doctor_id' => $doctor_id,
                                'patient_id' => $patient_id,
                                'collected_date' => $collected_date,
                                'reported_date' => $reported_date,
                                'is_completed' => false
                            ]);

                            Log::info('New test result created', [
                                'lab_no' => $lab_no,
                                'test_result_id' => $test_result->id
                            ]);
                        }

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
                            foreach ($results as $key => $res) {
                                $result_value = filled($res['Value']) ? $res['Value'] : null;
                                $unit = filled($res['Units']) ? $res['Units'] : null;
                                $result_flag = filled($res['Flags']) ? $res['Flags'] : null;

                                $identifier = $res['Identifier'];

                                if (filled($res['Text']) && ($res['Text'] != 'COMMENT' && $res['Text'] != 'NOTE')) {
                                    $masterPanelItemCacheKey = "master_panel_item_" . md5($res['Text'] . $unit);
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
                                            'identifier' => $identifier
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

                                    $existing_test_result_item = TestResultItem::where('test_result_id', $test_result_id)
                                        ->where('panel_panel_item_id', $panel_panel_item_id)
                                        ->first();

                                    $hasAmended = false;

                                    if ($existing_test_result_item) {
                                        $existing_value = $existing_test_result_item->value;

                                        $normalized_existing = $existing_value === '' ? null : $existing_value;
                                        $normalized_new = $result_value === '' ? null : $result_value;

                                        $hasAmended = $normalized_existing !== $normalized_new;
                                    }

                                    $testResultItem = TestResultItem::updateOrCreate(
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

                                if (($res['Text'] == 'NOTE' || $res['Text'] == 'COMMENT') && isset($panel_id)) {
                                    $masterPanelComment = MasterPanelComment::firstOrCreate(
                                        [
                                            'comment' => $result_value
                                        ]
                                    );

                                    $existingPanelComment = PanelComment::where([
                                        'panel_id' => $panel_id,
                                        'master_panel_comment_id' => $masterPanelComment->id,
                                    ])->first();

                                    if (!$existingPanelComment) {
                                        $panelComment = PanelComment::create([
                                            'panel_id' => $panel_id,
                                            'master_panel_comment_id' => $masterPanelComment->id,
                                        ]);
                                    }

                                    $panel_comment_id = $existingPanelComment->id ?? $panelComment->id;

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

            if (isset($validated['EncodedBase64pdf']) && filled($validated['EncodedBase64pdf']) && $test_result) {
                $test_result->is_completed = true;
                $test_result->is_reviewed = false;
                $test_result->save();
            }

            if ($test_result) {
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
            }

            DB::commit();

            //Dispatch to AI server queue if PDF was received
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
            $icInfo = checkIcno($patient['AlternatePatientID']);
            $icno = $icInfo['icno'];
            $ic_type = $icInfo['type'];
            $patient_gender = $icInfo['gender'];
            $age = $icInfo['age'];
        } else {
            $icno = $patient['PatientID'] ?? 'N/A_' . $batch_id;
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
                'gender' => $gender ?? $patient_gender
            ],
            $cacheKey
        );

        return $patient->id;
    }

    private function findOrCreatePanel($lab_id, $panel_code, $panel_name)
    {
        $masterPanelCacheKey = "master_panel_" . md5($panel_name);
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
                'name' => $panel_name
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
            'ADDITIONAL'
        ];

        foreach ($tagOnKeywords as $keyword) {
            if (stripos($panel_name, $keyword) !== false || stripos($panel_code, $keyword) !== false) {
                return true;
            }
        }

        return false;
    }
}
