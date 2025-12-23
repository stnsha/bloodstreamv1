<?php

namespace App\Http\Controllers\API\Innoquest;

use App\Http\Controllers\API\BaseResultsController;
use App\Http\Requests\InnoquestResultRequest;
use App\Jobs\ProcessAIReview;
use App\Services\AIReviewService;
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
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class PanelResultsController extends BaseResultsController
{
    protected $aiReviewService;

    public function __construct(AIReviewService $aiReviewService)
    {
        $this->aiReviewService = $aiReviewService;
    }

    /**
     * @OA\Post(
     *     path="/api/v1/result/panel",
     *     tags={"Result"},
     *     summary="Submit Innoquest panel results",
     *     description="Process lab results from Innoquest system in HL7-like format",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         description="Innoquest panel results data",
     *         @OA\JsonContent(
     *             type="object",
     *             required={"patient", "Orders"},
     *             @OA\Property(property="SendingFacility", type="string", example="BIOMARK"),
     *             @OA\Property(property="MessageControlID", type="string", example="169126507"),
     *             @OA\Property(
     *                 property="patient",
     *                 type="object",
     *                 required={"PatientLastName", "PatientGender"},
     *                 @OA\Property(property="PatientID", type="string", example=""),
     *                 @OA\Property(property="PatientExternalID", type="string", example=""),
     *                 @OA\Property(property="AlternatePatientID", type="string", example="010325055234"),
     *                 @OA\Property(property="PatientLastName", type="string", example="SANJEV TESTING"),
     *                 @OA\Property(property="PatientFirstName", type="string", example=""),
     *                 @OA\Property(property="PatientMiddleName", type="string", example=""),
     *                 @OA\Property(property="PatientDOB", type="string", example="20010325"),
     *                 @OA\Property(property="PatientGender", type="string", example="F"),
     *                 @OA\Property(property="PatientAddress", type="string", example="KLANG"),
     *                 @OA\Property(property="PatientNationality", type="string", example="")
     *             ),
     *             @OA\Property(
     *                 property="Orders",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="PlacerOrderNumber", type="string", example="INN12345"),
     *                     @OA\Property(property="FillerOrderNumber", type="string", example="25-8888861"),
     *                     @OA\Property(property="PlacerGroupNumber", type="string", example=""),
     *                     @OA\Property(property="Status", type="string", example=""),
     *                     @OA\Property(property="Quantity", type="string", example=""),
     *                     @OA\Property(property="TransactionDateTime", type="string", example=""),
     *                     @OA\Property(
     *                         property="OrderingProvider",
     *                         type="object",
     *                         @OA\Property(property="Code", type="string", example="NMGL9"),
     *                         @OA\Property(property="Name", type="string", example="NG MING LEE (BUKIT BARU)")
     *                     ),
     *                     @OA\Property(property="Organization", type="string", example=""),
     *                     @OA\Property(
     *                         property="Observations",
     *                         type="array",
     *                         @OA\Items(
     *                             type="object",
     *                             @OA\Property(property="PlacerOrderNumber", type="string", example=""),
     *                             @OA\Property(property="FillerOrderNumber", type="string", example="25-8888861"),
     *                             @OA\Property(property="ProcedureCode", type="string", example="FBC"),
     *                             @OA\Property(property="ProcedureDescription", type="string", example="FULL BLOOD COUNT"),
     *                             @OA\Property(property="PackageCode", type="string", example=""),
     *                             @OA\Property(property="Priority", type="string", example=""),
     *                             @OA\Property(property="RequestedDateTime", type="string", example="20250404"),
     *                             @OA\Property(property="StartDateTime", type="string", example="202507101000"),
     *                             @OA\Property(property="EndDateTime", type="string", example=""),
     *                             @OA\Property(property="ClinicalInformation", type="string", example=""),
     *                             @OA\Property(property="SpecimenDateTime", type="string", example="202507100918"),
     *                             @OA\Property(
     *                                 property="OrderingProvider",
     *                                 type="object",
     *                                 @OA\Property(property="Code", type="string", example="NMGL9"),
     *                                 @OA\Property(property="Name", type="string", example="NG MING LEE (BUKIT BARU)")
     *                             ),
     *                             @OA\Property(property="ResultStatus", type="string", example="F"),
     *                             @OA\Property(property="ServiceDateTime", type="string", example="20250404"),
     *                             @OA\Property(property="ResultPriority", type="string", example="R"),
     *                             @OA\Property(
     *                                 property="Results",
     *                                 type="array",
     *                                 @OA\Items(
     *                                     type="object",
     *                                     @OA\Property(property="ID", type="string", example="1"),
     *                                     @OA\Property(property="Type", type="string", example="NM"),
     *                                     @OA\Property(property="Identifier", type="string", example="718-7"),
     *                                     @OA\Property(property="Text", type="string", example="Haemoglobin"),
     *                                     @OA\Property(property="CodingSystem", type="string", example="LN"),
     *                                     @OA\Property(property="Value", type="string", example="130"),
     *                                     @OA\Property(property="Units", type="string", example="g/L"),
     *                                     @OA\Property(property="ReferenceRange", type="string", example="120-150"),
     *                                     @OA\Property(property="Flags", type="string", example="N"),
     *                                     @OA\Property(property="Status", type="string", example="F"),
     *                                     @OA\Property(property="ObservationDateTime", type="string", example="202507101608")
     *                                 )
     *                             )
     *                         )
     *                     )
     *                 )
     *             ),
     *             @OA\Property(property="EncodedBase64pdf", type="string", nullable=true, description="Base64 encoded PDF report")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Panel results processed successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Panel results processed successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="test_result_id", type="integer", example=123),
     *                 @OA\Property(property="panel", type="string", example="Full Blood Count")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Failed to process panel results"),
     *             @OA\Property(property="error", type="string", example="Internal server error")
     *         )
     *     )
     * )
     */
    public function panelResults(InnoquestResultRequest $request)
    {
        $validated = $request->validated();
        $lab_id = null;
        $test_result = null;
        $deliveryFile = null;
        $sending_facility = null;
        $batch_id = null;
        $orders_count = 0;
        $observations_count = 0;
        $patient_id = null;

        // $tr = new GoogleTranslate();
        // $tr->setSource('en');
        // $tr->setTarget('zh-CN');

        try {
            if ($validated) {
                //get current user lab id
                $user = Auth::guard('lab')->user();
                $lab_id = $user->lab_id;

                DB::transaction(function () use ($validated, $lab_id, &$test_result, &$deliveryFile, &$sending_facility, &$batch_id, &$orders_count, &$observations_count, &$patient_id) {
                    //check for batch
                    if (filled($validated['SendingFacility'])) {
                        $sending_facility = $validated['SendingFacility'];
                        $batch_id = $validated['MessageControlID'] ?? null;
                    }

                    //Find or create patient information
                    $patient_id = $this->findOrCreatePatient($validated['patient'], $batch_id);

                    //check for reference id in field (before confirmed)
                    $reference_id = null;

                    //counters for logging summary
                    $orders_count = 0;
                    $observations_count = 0;

                    //loop through orders
                    foreach ($validated['Orders'] as $key => $od) {
                        $orders_count++;
                        if (is_null($reference_id) && filled($od['PlacerOrderNumber'])) $reference_id = strtoupper($od['PlacerOrderNumber']);

                        //get doctor name and code
                        $doctor_name = $od['OrderingProvider']['Name'];
                        $doctor_id = $od['OrderingProvider']['Code'];

                        //create doctor code
                        $doctor_id = Doctor::firstOrCreate(
                            [
                                'lab_id' => $lab_id,
                                'code' => $doctor_id
                            ],
                            [
                                'name' => $doctor_name,
                            ]
                        );

                        //get doctor id
                        $doctor_id = $doctor_id->id;

                        //check if observations exist
                        if (filled($od['Observations'])) {
                            //loop through observations
                            foreach ($od['Observations'] as $key => $obv) {
                                $observations_count++;

                                //get labno
                                $lab_no = $obv['FillerOrderNumber'];

                                //get collected and referred (reported)date
                                $collected_date = $this->convertDatetime($obv['SpecimenDateTime']);
                                // $received_date = $obv['EndDateTime'];
                                $reported_date = $this->convertDatetime($obv['RequestedDateTime']);

                                //get panel code
                                $panel_code = $obv['ProcedureCode'];
                                $panel_name = $obv['ProcedureDescription'];

                                // Check if panel is TAG ON first
                                $isTagOn = $this->isTagOnItem($panel_name, $panel_code);

                                //Find or create panel
                                $panel = $this->findOrCreatePanel($lab_id, $panel_code, $panel_name);
                                $panel_id = $panel->id;

                                //get profile code (optional)
                                $panel_profile_id = $this->findOrCreateProfile($lab_id, $obv['PackageCode']);

                                //create test result - separate create/update to preserve is_completed status
                                $existingTestResult = TestResult::where('lab_no', $lab_no)->first();

                                if ($existingTestResult) {
                                    // UPDATE: Preserve is_completed status
                                    $existingTestResult->update([
                                        'ref_id' => $reference_id,
                                        'doctor_id' => $doctor_id,
                                        'patient_id' => $patient_id,
                                        'collected_date' => $collected_date,
                                        'reported_date' => $reported_date,
                                        // is_completed NOT updated - preserved
                                    ]);
                                    $test_result = $existingTestResult;

                                    Log::info('Test result updated - completion status preserved', [
                                        'lab_no' => $lab_no,
                                        'test_result_id' => $test_result->id,
                                        'is_completed' => $test_result->is_completed
                                    ]);
                                } else {
                                    // CREATE: New record starts incomplete
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

                                //get test result id
                                $test_result_id = $test_result->id;

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
                                //check if results exist
                                if (filled($results)) {
                                    //loop through results
                                    foreach ($results as $key => $res) {
                                        //check if value exist and store to variable
                                        $result_value = filled($res['Value']) ? $res['Value'] : null;
                                        $unit = filled($res['Units']) ? $res['Units'] : null;
                                        $result_flag = filled($res['Flags']) ? $res['Flags'] : null;

                                        //store field value to variable
                                        $identifier = $res['Identifier'];

                                        //result items 
                                        if (filled($res['Text']) && ($res['Text'] != 'COMMENT' && $res['Text'] != 'NOTE')) {
                                            // 1. Create or find master panel item
                                            $masterPanelItem = MasterPanelItem::updateOrCreate(
                                                [
                                                    'name' => $res['Text'],
                                                    'unit' => $unit,
                                                ],
                                                [
                                                    'chi_character' => null, //$tr->translate($res['Text'])
                                                ]
                                            );

                                            // 2. Create panel item with master panel item reference
                                            $panel_item = PanelItem::updateOrCreate([
                                                'lab_id' => $lab_id,
                                                'master_panel_item_id' => $masterPanelItem->id,
                                                'identifier' => $identifier
                                            ], [
                                                'name' => $res['Text'],
                                                'unit' => $masterPanelItem->unit,
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
                                        //panel comments - create both master and panel-specific comments
                                        if (($res['Text'] == 'NOTE' || $res['Text'] == 'COMMENT') && isset($panel_id)) {
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

                                            // Create relationship using TestResultComment model
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

                    //check embedded pdf exist to complete blood report status
                    if (isset($validated['EncodedBase64pdf']) && filled($validated['EncodedBase64pdf']) && $test_result) {
                        $test_result->is_completed = true;
                        $test_result->is_reviewed = false;
                        $test_result->save();
                    }

                    //create delivery file for tracking purposes
                    if ($test_result) {
                        $deliveryFile = DeliveryFile::firstOrCreate(
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
                });

                Log::info('Panel results processed successfully', [
                    'test_result_id' => $test_result->id ?? null,
                    'lab_no' => $test_result->lab_no ?? null,
                    'patient_id' => $patient_id ?? null,
                    'has_pdf' => isset($validated['EncodedBase64pdf']) && filled($validated['EncodedBase64pdf']),
                    'orders_count' => $orders_count,
                    'observations_count' => $observations_count,
                    'data_stored' => true
                ]);

                return response()->json([
                    'success' => true,
                    'data' => [
                        'test_result_id' => $test_result->id ?? null,
                        'panel' => $panel->name ?? null,
                    ],
                    'message' => 'Panel results processed successfully'
                ], 200);
            }
        } catch (Throwable $e) {
            /** @var \Illuminate\Http\Request $request */
            Log::error('Failed to process panel results', [
                'exception' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'has_pdf' => isset($validated['EncodedBase64pdf']) && filled($validated['EncodedBase64pdf']),
                'data_stored' => false
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to process results',
                'error' => 'Internal server error'
            ], 500);
        }
    }

    private function findOrCreatePatient(array $patient, $batch_id = null)
    {
        //check for age and gender from AlternatePatientID (NRIC) if available
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
            // Use PatientID as fallback if AlternatePatientID not available
            $icno = $patient['PatientID'] ?? 'N/A_' . $batch_id;
        }

        //get from json (PatientLastName is always expected)
        $patient_name = $patient['PatientLastName'];
        $patient_dob = filled($patient['PatientDOB']) ? $patient['PatientDOB'] : null;
        $gender = $patient['PatientGender']; // Always expected

        //create patient
        $patient = Patient::firstOrCreate(
            [
                'icno' => $icno,
            ],
            [
                'ic_type' => $ic_type,
                'name' => $patient_name,
                'dob' => $patient_dob,
                'age' => $age,
                'gender' => $gender ?? $patient_gender
            ]
        );

        return $patient->id;
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
}