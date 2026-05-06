<?php

namespace App\Http\Controllers\API\General;

use App\Http\Controllers\API\BaseResultsController;
use App\Http\Requests\StorePatientResultRequest;
use App\Models\DeliveryFile;
use App\Models\DeliveryFileHistory;
use App\Models\Doctor;
use App\Models\MasterPanel;
use App\Models\MasterPanelItem;
use App\Models\Panel;
use App\Models\PanelItem;
use App\Models\PanelPanelItem;
use App\Models\PanelProfile;
use App\Models\Patient;
use App\Models\ReferenceRange;
use App\Models\TestResult;
use App\Models\TestResultItem;
use App\Models\TestResultProfile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class LabResultsController extends BaseResultsController
{
    /**
     * @OA\Post(
     *     path="/api/v1/result/patient",
     *     summary="Submit lab results for a patient.",
     *     description="Receives a formatted JSON payload containing complete lab test results for a patient including multiple panels and tests.",
     *     operationId="labResults",
     *     tags={"Result"},
     *     security={{"bearerAuth": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         description="Lab results data",
     *         @OA\JsonContent(
     *             type="object",
     *             required={"lab_no", "doctor", "patient", "results"},
     *             @OA\Property(
     *                 property="reference_id",
     *                 type="string",
     *                 description="Reference ID for the test",
     *                 example="ABC12345"
     *             ),
     *             @OA\Property(
     *                 property="lab_no",
     *                 type="string",
     *                 description="Laboratory number",
     *                 example="123456789"
     *             ),
     *             @OA\Property(
     *                 property="bill_code",
     *                 type="string",
     *                 description="Billing code",
     *                 example="AMC_ALPRO"
     *             ),
     *             @OA\Property(
     *                 property="doctor",
     *                 type="object",
     *                 required={"name"},
     *                 @OA\Property(
     *                     property="code",
     *                     type="string",
     *                     description="Doctor code",
     *                     example=""
     *                 ),
     *                 @OA\Property(
     *                     property="name",
     *                     type="string",
     *                     description="Doctor name",
     *                     example="AMC NETWORK SDN BHD (ALPRO PHARMACY LUAK BAY)"
     *                 ),
     *                 @OA\Property(
     *                     property="address",
     *                     type="string",
     *                     description="Doctor address",
     *                     example="Lot 8524 Block 1, Lambir Land District, Jalan Luak Bay, 98000 Miri, Sarawak"
     *                 ),
     *                 @OA\Property(
     *                     property="phone",
     *                     type="string",
     *                     description="Doctor phone number",
     *                     example="0192672923"
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="received_date",
     *                 type="string",
     *                 format="date-time",
     *                 description="Date when sample was received",
     *                 example="2025-01-19 00:00:00"
     *             ),
     *             @OA\Property(
     *                 property="reported_date",
     *                 type="string",
     *                 format="date-time",
     *                 description="Date when results were reported",
     *                 example="2025-01-19 00:00:00"
     *             ),
     *             @OA\Property(
     *                 property="collected_date",
     *                 type="string",
     *                 format="date-time",
     *                 description="Date when sample was collected",
     *                 example="2025-01-19 00:00:00"
     *             ),
     *             @OA\Property(
     *                 property="validated_by",
     *                 type="string",
     *                 description="Person who validated the results",
     *                 example="Richard Roe, Bsc in Biomedical"
     *             ),
     *             @OA\Property(
     *                 property="patient",
     *                 type="object",
     *                 required={"icno", "name"},
     *                 @OA\Property(
     *                     property="icno",
     *                     type="string",
     *                     description="Patient IC number",
     *                     example="870521145681"
     *                 ),
     *                 @OA\Property(
     *                     property="gender",
     *                     type="string",
     *                     description="Patient gender",
     *                     example="Male"
     *                 ),
     *                 @OA\Property(
     *                     property="age",
     *                     type="string",
     *                     description="Patient age",
     *                     example="54"
     *                 ),
     *                 @OA\Property(
     *                     property="name",
     *                     type="string",
     *                     description="Patient name",
     *                     example="JOHN DOE"
     *                 ),
     *                 @OA\Property(
     *                     property="tel",
     *                     type="string",
     *                     description="Patient telephone number",
     *                     example="0123456789"
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="package_name",
     *                 type="string",
     *                 description="Test package name",
     *                 example="AC ESSENTIAL PACKAGE"
     *             ),
     *             @OA\Property(
     *                 property="results",
     *                 type="object",
     *                 description="Test results organized by panel",
     *                 @OA\Property(
     *                     property="Haematology",
     *                     type="object",
     *                     @OA\Property(
     *                         property="panel_sequence",
     *                         type="integer",
     *                         nullable=true,
     *                         description="Panel sequence number",
     *                         example=1
     *                     ),
     *                     @OA\Property(
     *                         property="panel_remarks",
     *                         type="string",
     *                         nullable=true,
     *                         description="Panel remarks",
     *                         example=null
     *                     ), 
     *                      @OA\Property(
     *                         property="result_status",
     *                         type="integer",
     *                         description="Result status",
     *                         example=1
     *                     ),
     *                     @OA\Property(
     *                         property="tests",
     *                         type="array",
     *                         @OA\Items(
     *                             type="object",
     *                             required={"test_name", "report_sequence"},
     *                             @OA\Property(property="test_name", type="string", example="Haemoglobin"),
     *                             @OA\Property(property="result_value", type="string", example="15.7"),
     *                             @OA\Property(property="result_flag", type="string", nullable=true, example=null),
     *                             @OA\Property(property="unit", type="string", example="g/dL"),
     *                             @OA\Property(property="ref_range", type="string", example="M: 13.0 - 18.0; F: 11.5 - 16.0"),
     *                             @OA\Property(property="test_note", type="string", nullable=true, example=null),
     *                             @OA\Property(property="report_sequence", type="integer", example=1)
     *                         )
     *                     )
     *                 ),
     *                 @OA\Property(
     *                     property="Liver Function Tests",
     *                     type="object",
     *                     @OA\Property(property="panel_sequence", type="integer", example=2),
     *                     @OA\Property(property="panel_remarks", type="string", nullable=true, example=null),
     *                     @OA\Property(property="result_status", type="integer", example=1),
     *                     @OA\Property(
     *                         property="tests",
     *                         type="array",
     *                         @OA\Items(
     *                             type="object",
     *                             @OA\Property(property="test_name", type="string", example="Total Bilirubin"),
     *                             @OA\Property(property="result_value", type="string", example="9.7"),
     *                             @OA\Property(property="result_flag", type="string", nullable=true, example=null),
     *                             @OA\Property(property="unit", type="string", example="µmol/L"),
     *                             @OA\Property(property="ref_range", type="string", example="<25.7"),
     *                             @OA\Property(property="test_note", type="string", nullable=true, example=null),
     *                             @OA\Property(property="report_sequence", type="integer", example=17)
     *                         )
     *                     )
     *                 ),
     *                 @OA\Property(
     *                     property="~ Urine: Microscopic",
     *                     type="object",
     *                     @OA\Property(property="panel_sequence", type="integer", example=5),
     *                     @OA\Property(property="panel_remarks", type="string", nullable=true, example=null),
     *                     @OA\Property(property="result_status", type="integer", example=1),
     *                     @OA\Property(
     *                         property="tests",
     *                         type="array",
     *                         @OA\Items(
     *                             type="object",
     *                             @OA\Property(property="test_name", type="string", example="Erythrocytes (Red blood cells)"),
     *                             @OA\Property(property="result_value", type="string", example="Nil"),
     *                             @OA\Property(property="result_flag", type="string", nullable=true, example=null),
     *                             @OA\Property(property="unit", type="string", example="/HPF"),
     *                             @OA\Property(property="ref_range", type="string", example="0-3"),
     *                             @OA\Property(property="test_note", type="string", nullable=true, example=null),
     *                             @OA\Property(property="report_sequence", type="integer", example=69)
     *                         )
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Lab results processed successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Lab results processed successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="test_result_id", type="integer", example=123)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized - Invalid or missing authentication token",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error - Invalid input data",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string", example="The given data was invalid."),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(
     *                     property="patient.icno",
     *                     type="array",
     *                     @OA\Items(type="string", example="The patient.icno field is required.")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Failed to process lab results"),
     *             @OA\Property(property="error", type="string", example="Internal server error")
     *         )
     *     )
     * )
     */
    public function labResults(StorePatientResultRequest $request)
    {
        $validated = $request->validated();
        $lab_id = null;
        $test_result_id = null;
        $sending_facility = null;
        $batch_id = null;

        try {
            Log::info('Lab results submission started', [
                'lab_id' => Auth::guard('lab')->user()->lab_id ?? null,
                'lab_no' => $validated['lab_no'] ?? null,
                'patient_icno' => $validated['patient']['icno'] ?? null
            ]);

            if ($validated) {
                //get current user role
                $user = Auth::guard('lab')->user();
                $lab_id = $user->lab_id;

                DB::transaction(function () use ($validated, $lab_id, $user, &$test_result_id, &$deliveryFile, &$sending_facility, &$batch_id, &$patient_id) {

                    //checking for batch file
                    if (filled($validated['sending_facility']) && $validated['sending_facility'] === 'INN') {
                    $sending_facility = $validated['sending_facility'];
                    $batch_id = $validated['batch_id'] ?? null;
                } else {
                    //create new batch if not exist
                    $sending_facility = $user->lab->code . 'API';
                    $batch_id = now()->format('YmdHis') . $user->lab->code;
                }

                //set validated data to variable
                $doctor_code = $validated['doctor']['code'];
                $doctor_name = $validated['doctor']['name'];
                $doctor_address = $validated['doctor']['address'];
                $doctor_phone = $validated['doctor']['phone'];
                $doctor_type = $validated['doctor']['type'];

                $patient_icno = $validated['patient']['icno'];
                $ic_type = $validated['patient']['ic_type'];
                $patient_age = $validated['patient']['age'];
                $patient_gender = $validated['patient']['gender'];
                $patient_tel = $validated['patient']['tel'];
                $patient_name = $validated['patient']['name'];

                $reference_id = $validated['reference_id'];
                $bill_code = $validated['bill_code'];
                $lab_no = $validated['lab_no'];
                $collected_date = $validated['collected_date'];
                $received_date = $validated['received_date'];
                $reported_date = $validated['reported_date'];
                $validated_by = $validated['validated_by'];
                $package_name = $validated['package_name'];
                $results = $validated['results'];

                // Generate doctor code if not provided
                $finalDoctorCode = !empty($doctor_code) ? $doctor_code : $this->generateDoctorCode($doctor_name);

                $doctor = Doctor::firstOrCreate(
                    [
                        'lab_id' => $lab_id,
                        'code' => $finalDoctorCode,
                    ],
                    [
                        'name' => $doctor_name,
                        'type' => $doctor_type ?? 'PHARMACY',
                        'outlet_name' => $doctor_name,
                        'outlet_address' => $doctor_address,
                        'outlet_phone' => $doctor_phone,
                    ]
                );

                //get doctor code id
                $doctor_id = $doctor->id;

                //create patient
                $patient = Patient::firstOrCreate(
                    [
                        'icno' => $patient_icno
                    ],
                    [
                        'ic_type' => $ic_type,
                        'name' => $patient_name,
                        'dob' => null,
                        'age' => $patient_age,
                        'gender' => $patient_gender,
                        'tel' => $patient_tel,
                    ]
                );

                //get patient id
                $patient_id = $patient->id;

                //get package name
                $panel_profile_id = null;
                if (filled($package_name)) {
                    $panel_profile = PanelProfile::firstOrCreate(
                        ['lab_id' => $lab_id, 'name' => $package_name]
                    );

                    $package_code = strtoupper(substr(str_replace(' ', '', $package_name), 0, 3));

                    $panel_profile->update(['code' => $package_code]);

                    $panel_profile_id = $panel_profile->id;
                }

                //create test result 
                $test_result = TestResult::updateOrCreate(
                    [
                        'doctor_id' => $doctor_id,
                        'patient_id' => $patient_id,
                        'lab_no' => $lab_no,
                    ],
                    [
                        'ref_id' => $reference_id,
                        'collected_date' => $collected_date,
                        'received_date' => $received_date,
                        'reported_date' => $reported_date,
                        'is_completed' => true,
                        'validated_by' => $validated_by,
                    ]
                );

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

                //loop through results
                foreach ($results as $key => $item) {
                    //assign array key as panel name
                    $panel_name = $key;
                    // Generate panel code from panel name if not provided
                    $panel_code = $this->generatePanelCode($panel_name);
                    $panel_sequence = $item['panel_sequence'] ?? null;
                    $overall_notes = $item['panel_remarks'];
                    $result_status = $item['result_status'];

                    // 1. First, create or find master panel
                    $masterPanel = MasterPanel::firstOrCreate([
                        'name' => $panel_name
                    ]);

                    // 2. Create or get Panel with master panel reference
                    $panel = Panel::firstOrCreate([
                        'lab_id' => $lab_id,
                        'master_panel_id' => $masterPanel->id
                    ], [
                        'name' => $panel_name,
                        'code' => $panel_code,
                        'sequence' => $panel_sequence
                    ]);

                    //check if array tests available
                    if (filled($item['tests'])) {
                        //loop through tests
                        foreach ($item['tests'] as $index => $test) {
                            // Generate test code and identifier from test name if not provided
                            $test_code = $this->generateTestCode($test['test_name']);
                            $identifier = $panel_code . '#' . $test_code;

                            // 1. Create or find master panel item
                            $masterPanelItem = MasterPanelItem::firstOrCreate([
                                'name' => $test['test_name'],
                                'unit' => $test['unit'],
                            ]);

                            // 2. Create panel item with master panel item reference
                            $panel_item = PanelItem::firstOrCreate([
                                'lab_id' => $lab_id,
                                'master_panel_item_id' => $masterPanelItem->id
                            ], [
                                'name' => $test['test_name'],
                                'identifier' => $identifier,
                                'unit' => $masterPanelItem->unit,
                            ]);

                            // 3. Link panel item to panel through pivot table
                            $panel->panelItems()->syncWithoutDetaching([$panel_item->id]);

                            //get panel item id
                            $panel_item_id = $panel_item->id;

                            //get panel panel item id
                            $panel_panel_item_id = PanelPanelItem::where('panel_id', $panel->id)->where('panel_item_id', $panel_item_id)->first()?->id;

                            //check if panel item has reference range
                            $ref_range_id = null;
                            if (filled($test['ref_range'])) {
                                //create reference range
                                $ref_range = ReferenceRange::firstOrCreate(
                                    [
                                        'value' => $test['ref_range'],
                                        'panel_panel_item_id' => $panel_panel_item_id,
                                    ]
                                );

                                //get reference range id
                                $ref_range_id = $ref_range->id;
                            }

                            //create test result item (with result value)
                            TestResultItem::firstOrCreate(
                                [
                                    'test_result_id' => $test_result_id,
                                    'panel_panel_item_id' => $panel_panel_item_id,
                                ],
                                [
                                    'reference_range_id' => $ref_range_id,
                                    'value' => $test['result_value'],
                                    'flag' => $test['result_flag'],
                                    'sequence' => $test['report_sequence'],
                                    'has_amended' => false
                                ]
                            );
                        }
                    }
                }

                    //create delivery file for tracking purposes
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
                });

                Log::info('Lab results processed successfully', [
                    'test_result_id' => $test_result_id,
                    'lab_id' => $lab_id,
                    'patient_id' => $patient_id
                ]);

                return response()->json([
                    'success' => true,
                    'data' => [
                        'test_result_id' => $test_result_id
                    ],
                    'message' => 'Lab results processed successfully'
                ], 200);
            }
        } catch (Throwable $e) {
            /** @var \Illuminate\Http\Request $request */
            Log::error('Failed to save data', [
                'exception' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'data' => json_encode($request->all()),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to process results',
                'error' => 'Internal server error'
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/result/{id}",
     *     tags={"Result"},
     *     summary="Retrieve test result by ID",
     *     description="Get a specific test result with all associated data including patient, doctor, panel results and test items",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Test result ID",
     *         @OA\Schema(type="integer", example=123)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Test result retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Test result retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="reference_id", type="string", nullable=true, example="ABC12345"),
     *                 @OA\Property(property="lab_no", type="string", example="123456789"),
     *                 @OA\Property(property="bill_code", type="string", nullable=true, example="AMC_ALPRO"),
     *                 @OA\Property(property="collected_date", type="string", nullable=true, example="2025-01-19 00:00:00"),
     *                 @OA\Property(property="received_date", type="string", nullable=true, example="2025-01-19 00:00:00"),
     *                 @OA\Property(property="reported_date", type="string", nullable=true, example="2025-01-19 00:00:00"),
     *                 @OA\Property(property="validated_by", type="string", nullable=true, example="Richard Roe, Bsc in Biomedical"),
     *                 @OA\Property(property="is_completed", type="boolean", example=true),
     *                 @OA\Property(property="is_tagon", type="boolean", example=false),
     *                 @OA\Property(
     *                     property="doctor",
     *                 type="object",
     *                 @OA\Property(property="name", type="string", example="Dr. John Smith"),
     *                 @OA\Property(property="code", type="string", nullable=true, example="DOC123"),
     *                 @OA\Property(property="type", type="string", nullable=true, example="clinic"),
     *                 @OA\Property(property="outlet_name", type="string", nullable=true, example="Smith Clinic"),
     *                 @OA\Property(property="outlet_address", type="string", nullable=true, example="123 Medical Street"),
     *                 @OA\Property(property="outlet_phone", type="string", nullable=true, example="03-12345678")
     *             ),
     *             @OA\Property(
     *                 property="patient",
     *                 type="object",
     *                 @OA\Property(property="icno", type="string", example="870521145681"),
     *                 @OA\Property(property="ic_type", type="string", example="NRIC"),
     *                 @OA\Property(property="name", type="string", nullable=true, example="JOHN DOE"),
     *                 @OA\Property(property="dob", type="string", nullable=true, example="1987-05-21"),
     *                 @OA\Property(property="age", type="string", nullable=true, example="37"),
     *                 @OA\Property(property="gender", type="string", nullable=true, example="M"),
     *                 @OA\Property(property="tel", type="string", nullable=true, example="012-3456789")
     *             ),
     *             @OA\Property(
     *                 property="package",
     *                 type="object",
     *                 nullable=true,
     *                 @OA\Property(property="name", type="string", example="AC ESSENTIAL PACKAGE"),
     *                 @OA\Property(property="code", type="string", nullable=true, example="AC_ESS")
     *             ),
     *             @OA\Property(
     *                 property="results",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="panel_name", type="string", example="Haematology"),
     *                     @OA\Property(property="panel_code", type="string", nullable=true, example="HAE"),
     *                     @OA\Property(property="panel_sequence", type="integer", nullable=true, example=1),
     *                     @OA\Property(property="overall_notes", type="string", nullable=true, example=null),
     *                     @OA\Property(
     *                         property="tests",
     *                         type="array",
     *                         @OA\Items(
     *                             type="object",
     *                             @OA\Property(property="test_name", type="string", example="Haemoglobin"),
     *                             @OA\Property(property="test_code", type="string", nullable=true, example="HGB"),
     *                             @OA\Property(property="result_value", type="string", nullable=true, example="15.7"),
     *                             @OA\Property(property="unit", type="string", nullable=true, example="g/dL"),
     *                             @OA\Property(property="reference_range", type="string", nullable=true, example="M: 13.0 - 18.0; F: 11.5 - 16.0"),
     *                             @OA\Property(property="result_flag", type="string", nullable=true, example=null),
     *                             @OA\Property(property="test_notes", type="string", nullable=true, example=null),
     *                             @OA\Property(property="status", type="string", nullable=true, example="F"),
     *                             @OA\Property(property="is_completed", type="boolean", example=true),
     *                             @OA\Property(property="sequence", type="integer", nullable=true, example=1)
     *                         )
     *                     )
     *                 )
     *             )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Test result not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Test result not found"),
     *             @OA\Property(property="error", type="string", example="Not found")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized - Invalid or missing authentication token"
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Failed to retrieve test result"),
     *             @OA\Property(property="error", type="string", example="Internal server error")
     *         )
     *     )
     * )
     */
    public function show($id)
    {
        try {
            // Get current user's lab ID
            $user = Auth::guard('lab')->user();
            $lab_id = $user->lab_id;

            // Find test result with lab_id constraint through doctor relationship using Eloquent
            $testResult = TestResult::whereHas('doctor', function ($query) use ($lab_id) {
                $query->where('lab_id', $lab_id);
            })->with([
                'doctor',
                'patient',
                'panelProfile',
                'testResultItems' => function ($query) use ($lab_id) {
                    $query->with([
                        'panelItem' => function ($query) use ($lab_id) {
                            $query->where('lab_id', $lab_id)->with([
                                'masterPanelItem',
                                'panels' => function ($query) use ($lab_id) {
                                    $query->where('lab_id', $lab_id)->with('masterPanel');
                                }
                            ]);
                        },
                        'referenceRange'
                    ]);
                }
            ])->find($id);

            if (!$testResult) {
                Log::warning('Test result not found', [
                    'test_result_id' => $id,
                    'lab_id' => $lab_id
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Test result not found',
                    'error' => 'Not found'
                ], 404);
            }

            // Group test result items by panel
            $groupedResults = [];
            foreach ($testResult->testResultItems as $item) {
                $panelItem = $item->panelItem;
                $panels = $panelItem ? $panelItem->panels : collect();

                // Handle cases where panel item might not have panels or has multiple panels
                if ($panels->isEmpty()) {
                    $panelName = 'Uncategorized';
                    $panelCode = null;
                    $panelSequence = null;
                    $overallNotes = null;
                } else {
                    // Take the first panel if multiple panels exist
                    $panel = $panels->first();
                    $panelName = $panel->name;
                    $panelCode = $panel->code;
                    $panelSequence = $panel->sequence;
                    $overallNotes = $panel->overall_notes;
                }

                if (!isset($groupedResults[$panelName])) {
                    $groupedResults[$panelName] = [
                        'panel_name' => $panelName,
                        'panel_code' => $panelCode,
                        'panel_sequence' => $panelSequence,
                        'overall_notes' => $overallNotes,
                        'tests' => []
                    ];
                }

                // Get master panel item data for the test
                $masterPanelItem = $panelItem ? $panelItem->masterPanelItem : null;

                $groupedResults[$panelName]['tests'][] = [
                    'test_name' => $masterPanelItem ? $masterPanelItem->name : ($panelItem ? $panelItem->identifier : null),
                    'test_code' => $masterPanelItem ? $masterPanelItem->code : null,
                    'result_value' => $item->value,
                    'unit' => $masterPanelItem ? $masterPanelItem->unit : null,
                    'reference_range' => $item->referenceRange ? $item->referenceRange->value : null,
                    'result_flag' => $item->flag,
                    'test_notes' => null, // Removed from new structure
                    'status' => null, // Removed from new structure  
                    'is_completed' => (bool) $item->is_completed,
                    'sequence' => $item->sequence ?? null
                ];
            }

            // Sort grouped results by panel sequence
            uasort($groupedResults, function ($a, $b) {
                return ($a['panel_sequence'] ?? 999) <=> ($b['panel_sequence'] ?? 999);
            });

            // Sort tests within each panel by sequence
            foreach ($groupedResults as &$panel) {
                usort($panel['tests'], function ($a, $b) {
                    return ($a['sequence'] ?? 999) <=> ($b['sequence'] ?? 999);
                });
            }

            // Prepare response data
            $responseData = [
                'reference_id' => $testResult->ref_id,
                'lab_no' => $testResult->lab_no,
                'collected_date' => $testResult->collected_date,
                'received_date' => $testResult->received_date,
                'reported_date' => $testResult->reported_date,
                'validated_by' => $testResult->validated_by,
                'is_completed' => (bool) $testResult->is_completed,
                'doctor' => $testResult->doctor ? [
                    'name' => $testResult->doctor->name,
                    'code' => $testResult->doctor->code,
                    'type' => $testResult->doctor->type,
                    'outlet_name' => $testResult->doctor->outlet_name,
                    'outlet_address' => $testResult->doctor->outlet_address,
                    'outlet_phone' => $testResult->doctor->outlet_phone
                ] : null,
                'patient' => $testResult->patient ? [
                    'icno' => $testResult->patient->icno,
                    'ic_type' => $testResult->patient->ic_type,
                    'name' => $testResult->patient->name,
                    'dob' => $testResult->patient->dob,
                    'age' => $testResult->patient->age,
                    'gender' => $testResult->patient->gender,
                    'tel' => $testResult->patient->tel
                ] : null,
                'package' => $testResult->panelProfile ? [
                    'name' => $testResult->panelProfile->name,
                    'code' => $testResult->panelProfile->code
                ] : null,
                'results' => array_values($groupedResults)
            ];

            Log::info('Test result retrieved successfully', [
                'test_result_id' => $id,
                'lab_id' => $lab_id
            ]);

            return response()->json([
                'success' => true,
                'data' => $responseData,
                'message' => 'Test result retrieved successfully'
            ], 200);
        } catch (Throwable $e) {
            Log::error('Failed to retrieve test result', [
                'test_result_id' => $id,
                'exception' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve test result',
                'error' => 'Internal server error'
            ], 500);
        }
    }
}