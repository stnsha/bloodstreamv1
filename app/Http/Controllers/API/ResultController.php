<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\InnoquestResultRequest;
use App\Http\Requests\StorePatientResultRequest;
use App\Models\DeliveryFile;
use App\Models\DeliveryFileHistory;
use App\Models\Doctor;
use App\Models\Panel;
use App\Models\PanelCategory;
use App\Models\PanelComment;
use App\Models\PanelItem;
use App\Models\PanelMetadata;
use App\Models\PanelProfile;
use App\Models\PanelTag;
use App\Models\Patient;
use App\Models\ReferenceRange;
use App\Models\TestResult;
use App\Models\TestResultItem;
use App\Models\TestResultReport;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ResultController extends Controller
{
    public function panelResults(InnoquestResultRequest $request)
    {
        $validated = $request->validated();
        try {
            if ($validated) {
                DB::beginTransaction();
                //get current user role
                $user = Auth::guard('lab')->user();
                $lab_id = $user->lab_id;

                //check for batch
                if (filled($validated['SendingFacility'])) {
                    $sending_facility = $validated['SendingFacility'];
                    $batch_id = $validated['MessageControlID'] ?? null;
                }

                //check for reference id in field (before confirmed)
                $reference_id = null;

                //check for age and gender
                $icInfo = checkIcno($validated['patient']['AlternatePatientID']);
                $icno = $icInfo['icno'];
                $ic_type = $icInfo['type'];
                $patient_gender = $icInfo['gender'];
                $age = $icInfo['age'];

                //get from json if available
                $patient_name = filled($validated['patient']['PatientLastName']) ? $validated['patient']['PatientLastName'] : null;
                $patient_dob = filled($validated['patient']['PatientDOB']) ? $validated['patient']['PatientDOB'] : null;
                $gender = filled($validated['patient']['PatientGender']) ? $validated['patient']['PatientGender'] : $patient_gender;

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
                        'gender' => $gender
                    ]
                );

                //get patient id
                $patient_id = $patient->id;

                //loop through orders
                foreach ($validated['Orders'] as $key => $od) {
                    if (is_null($reference_id) && filled($od['PlacerOrderNumber'])) $reference_id = $od['PlacerOrderNumber'];
                    //check if observations exist
                    if (filled($od['Observations'])) {
                        //loop through observations
                        foreach ($od['Observations'] as $key => $obv) {

                            //get doctor name and code
                            $doctor_name = $obv['OrderingProvider']['Name'];
                            $doctor_id = $obv['OrderingProvider']['Code'];

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

                            //get labno
                            $lab_no = $obv['FillerOrderNumber'];
                            //confirm field for reference id = PlacerOrderNumber
                            //check if previous reference id is null and PlacerOrderNumber exist
                            if (is_null($reference_id) && filled($obv['PlacerOrderNumber'])) $reference_id = $obv['PlacerOrderNumber'];

                            //bill code is not sent in json payload
                            $bill_code = null;

                            //get collected and referred (reported)date
                            $collected_date = $this->convertDatetime($obv['SpecimenDateTime']);
                            // $received_date = $obv['EndDateTime'];
                            $reported_date = $this->convertDatetime($obv['RequestedDateTime']);

                            //create test result
                            $test_result = TestResult::firstOrCreate([
                                'doctor_id' => $doctor_id,
                                'patient_id' => $patient_id,
                                'ref_id' => $reference_id,
                                'bill_code' => $bill_code,
                                'lab_no' => $lab_no,
                                'collected_date' => $collected_date,
                                'received_date' => null,
                                'reported_date' => $reported_date,
                                'is_completed' => false
                            ]);

                            //get test result id
                            $test_result_id = $test_result->id;

                            //get profile code
                            $profile_code = $obv['PackageCode'];
                            $panel_profile_id = null;
                            $panel_profile = PanelProfile::where('lab_id', $lab_id)->where('code', $profile_code)->first();

                            if (!$panel_profile) {
                                $panel_profile = PanelProfile::firstOrCreate(
                                    [
                                        'lab_id' => $lab_id,
                                        'code' => $profile_code,
                                    ],
                                    [
                                        'name' => $profile_code,
                                    ]
                                );
                                $panel_profile_id = $panel_profile->id;
                            } else {
                                $panel_profile_id = $panel_profile->id;
                            }

                            $panel_category_id = null;

                            if ($obv['Results'][0]['Identifier'] == 'REPORT') {
                                $value = trim($obv['Results'][0]['Value']);
                                $words = preg_split('/\s+/', $value);
                                $lab_category = $words[0];

                                if ($panel_profile_id != null) {
                                    $panel_category = PanelCategory::where('panel_profile_id', $panel_profile_id)->where('name', $lab_category)->first();

                                    if ($panel_category) {
                                        $panel_category_id = $panel_category->id;
                                    } else {
                                        // Create the panel category if it doesn't exist
                                        $panel_category = PanelCategory::firstOrCreate(
                                            [
                                                'lab_id' => $lab_id,
                                                'panel_profile_id' => $panel_profile_id,
                                                'name' => $lab_category,
                                            ],
                                            [
                                                'code' => $lab_category, // or generate appropriate code
                                            ]
                                        );
                                        $panel_category_id = $panel_category->id;
                                    }
                                }
                            }

                            //get panel code
                            $panel_code = $obv['ProcedureCode'];
                            $panel_name = $obv['ProcedureDescription'];
                            $panel_notes = filled($obv['ClinicalInformation']) ? $obv['ClinicalInformation'] : null; //overall notes

                            $panel = Panel::where('code', $panel_code)->where('name', $panel_name)->first();
                            $panel_tag_id = null;
                            if (!$panel) {
                                //search if tag on code
                                $panel_tag = PanelTag::where('code', $panel_code)->where('name', $panel_name)->first();

                                if (!$panel_tag) {
                                    //create panel if no tag on code
                                    $panel = Panel::firstOrCreate(
                                        [
                                            'lab_id' => $lab_id,
                                            'panel_category_id' => $panel_category_id,
                                            'name' => $panel_name,
                                        ],
                                        [
                                            'code' => $panel_code,
                                            'sequence' => null,
                                            'overall_notes' => $panel_notes
                                        ]
                                    );

                                    //get panel id
                                    $panel_id = $panel->id;
                                } else {
                                    //if panel tag exist
                                    $panel_tag_id = $panel_tag->id;
                                    $panel_id = $panel_tag->panel_id;
                                }
                            }

                            $panel_id = $panel->id;

                            //check if result is completed
                            $is_completed_result = (filled($obv['ResultStatus']) && $obv['ResultStatus'] == 'F')  ? true : false;

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
                                    $result_status = filled($res['Status']) ? $res['Status'] : null;

                                    //result items 
                                    if (filled($res['Text']) && $res['Text'] != 'COMMENT') {
                                        //create panel items
                                        $panel_item = PanelItem::firstOrCreate(
                                            [
                                                'panel_id' => $panel_id,
                                                'panel_tag_id' => $panel_tag_id,
                                                'name' => $res['Text'],
                                            ],
                                            [
                                                'decimal_point' => null,
                                                'unit' => $unit,
                                                'item_sequence' => null
                                            ]
                                        );

                                        //get panel item id
                                        $panel_item_id = $panel_item->id;

                                        //create reference range
                                        if (filled($res['ReferenceRange'])) {
                                            $ref_range = ReferenceRange::firstOrCreate(
                                                [
                                                    'value' => $res['ReferenceRange'],
                                                    'panel_item_id' => $panel_item_id,
                                                ]
                                            );

                                            //get reference range
                                            $ref_range_id = $ref_range->id;
                                        }

                                        //store field value to variable
                                        $ordinal_id = $res['ID'];
                                        $type = $res['Type'];
                                        $identifier = $res['Identifier'];

                                        $main = explode('#', $identifier)[0];
                                        $suffix = strpos($identifier, '#') !== false ? explode('#', $identifier)[1] : null;

                                        $query = PanelMetadata::where('panel_item_id', $panel_item_id)
                                            ->whereRaw("SUBSTRING_INDEX(identifier, '#', 1) = ?", [$main]);

                                        if (!is_null($suffix)) {
                                            $query->where('code', $suffix);
                                        }

                                        $panel_metadata = $query->first();

                                        if ($panel_metadata) {
                                            $panel_metadata->update([
                                                'type' => $type,
                                                'ordinal_id' => $ordinal_id,
                                            ]);
                                        } else {
                                            PanelMetadata::create([
                                                'panel_item_id' => $panel_item_id,
                                                'ordinal_id' => $ordinal_id,
                                                'type' => $type,
                                                'identifier' => $identifier,
                                                'code' => $suffix,
                                            ]);
                                        }

                                        //final insert result ite 
                                        TestResultItem::firstOrCreate(
                                            [

                                                'test_result_id' => $test_result_id,
                                                'reference_range_id' => $ref_range_id,
                                                'value' => $result_value
                                            ],
                                            [
                                                'flag' => $result_flag,
                                                'test_notes' => null,
                                                'status' => $result_status,
                                                'is_completed' => $is_completed_result
                                            ]
                                        );
                                    }

                                    //panel comments
                                    if ($res['Text'] == 'COMMENT') {
                                        PanelComment::firstOrCreate(
                                            [
                                                'panel_item_id' => $panel_item_id,
                                                'identifier' => $identifier
                                            ],
                                            [
                                                'comment' => $result_value,
                                                'sequence' => null
                                            ]
                                        );
                                    }

                                    //panel compiled report (formatted)
                                    if ($res['Identifier'] == 'REPORT') {
                                        TestResultReport::firstOrCreate([
                                            'test_result_id' => $test_result_id,
                                            'panel_id' => $panel_id,
                                            'text' => $result_value,
                                            'is_completed' => $is_completed_result,
                                        ]);
                                    }
                                }
                            }
                        }
                    }
                }

                //check embedded pdf exist to complete blood report status
                if (isset($validated['EncodedBase64pdf']) && filled($validated['EncodedBase64pdf'])) {
                    try {
                        $test_result->is_completed = true;
                        $test_result->save();
                        //Decode the base64 PDF data
                        // $pdfData = base64_decode($validated['EncodedBase64pdf']);

                        //Generate a unique filename
                        // $filename = 'test_result_' . $test_result_id . '_' . time() . '.pdf';

                        //Store the PDF file in storage/app/public/test_results directory
                        // $filePath = 'pdf/' . $filename;
                        // Storage::disk('public')->put($filePath, $pdfData);

                        // Update test result with PDF file path and mark as completed

                        // Log::info('PDF file saved successfully', [
                        //     'test_result_id' => $test_result_id,
                        //     'file_path' => $filePath
                        // ]);
                    } catch (\Exception $e) {
                        Log::error('Failed to decode and save PDF', [
                            'test_result_id' => $test_result_id,
                            'error' => $e->getMessage()
                        ]);

                        // Still mark as completed even if PDF save fails
                        $test_result->is_completed = true;
                        $test_result->save();
                    }
                }

                //create delivery file for tracking purposes
                $deliveryFile = DeliveryFile::firstOrCreate(
                    [

                        'test_result_id' => $test_result_id,
                    ],
                    [
                        'lab_id' => $lab_id,
                        'sending_facility' => $sending_facility,
                        'batch_id' => $batch_id,
                        'json_content' => json_encode($validated),
                        'status' => DeliveryFile::compl,
                    ]
                );

                DB::commit();
                //return result id
                return response()->json($test_result_id, 200);
            }
        } catch (\Throwable $e) {

            DB::rollBack();
            /** @var \Illuminate\Http\Request $request */
            Log::error('Failed to save data', [
                'exception' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'data' => json_encode($request->all()),
            ]);

            // Create delivery file if it doesn't exist for error tracking
            if (!isset($deliveryFile)) {
                $deliveryFile = DeliveryFile::create([
                    'lab_id' => $lab_id ?? null,
                    'test_result_id' => null,
                    'sending_facility' => $sending_facility ?? 'UNKNOWN',
                    'batch_id' => $batch_id ?? 'ERROR_' . now()->format('YmdHis'),
                    'json_content' => json_encode($validated),
                    'status' => DeliveryFile::fld,
                ]);
            }

            // Always create delivery file history for errors
            DeliveryFileHistory::create([
                'delivery_file_id' => $deliveryFile->id,
                'message' => $e->getMessage(),
                'err_code' => '500',
            ]);

            // Update delivery file status to failed
            $deliveryFile->update(['status' => DeliveryFile::fld]);

            return response()->json([
                'error' => 'Failed to save data',
            ], 500);
        }
    }

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
     *                 required={},
     *                 @OA\Property(
     *                     property="code",
     *                     type="string",
     *                     description="Doctor code",
     *                     example="ABC122"
     *                 ),
     *                 @OA\Property(
     *                     property="name",
     *                     type="string",
     *                     description="Doctor name",
     *                     example="Dr. John Smith"
     *                 ),
     *                 @OA\Property(
     *                     property="address",
     *                     type="string",
     *                     description="Doctor address",
     *                     example="123 Medical Center Street, City"
     *                 ),
     *                 @OA\Property(
     *                     property="phone",
     *                     type="string",
     *                     description="Doctor phone number",
     *                     example="03-12345678"
     *                 ),
     *                 @OA\Property(
     *                     property="type",
     *                     type="string",
     *                     description="Doctor type",
     *                     example="clinic"
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
     *                 required={"icno"},
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
     *                     example="012-3456789"
     *                 ),
     *                 @OA\Property(
     *                     property="ic_type",
     *                     type="string",
     *                     description="IC type",
     *                     enum={"NRIC", "OTHERS"},
     *                     example="NRIC"
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
     *                         property="panel_code",
     *                         type="string",
     *                         nullable=true,
     *                         description="Panel code",
     *                         example="HAE"
     *                     ),
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
     *                             @OA\Property(property="test_name", type="string", example="Haemoglobin"),
     *                             @OA\Property(property="result_value", type="string", example="15.7"),
     *                             @OA\Property(property="decimal_point", type="string", nullable=true, example="1"),
     *                             @OA\Property(property="result_flag", type="string", nullable=true, example=null),
     *                             @OA\Property(property="unit", type="string", example="g/dL"),
     *                             @OA\Property(property="ref_range", type="string", example="M: 13.0 - 18.0; F: 11.5 - 16.0"),
     *                             @OA\Property(property="test_note", type="string", nullable=true, example=null),
     *                             @OA\Property(property="item_sequence", type="integer", example=1)
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
     *             type="integer",
     *             description="Test result ID",
     *             example=123
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
     *         description="Internal server error - Failed to save data",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="error", type="string", example="Failed to save data")
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
            if ($validated) {
                DB::beginTransaction();
                //get current user role
                $user = Auth::guard('lab')->user();
                $lab_id = $user->lab_id;

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

                $doctor = Doctor::firstOrCreate(
                    [
                        'lab_id' => $lab_id,
                        'code' => $doctor_code,
                    ],
                    [
                        'name' => $doctor_name,
                        'type' => $doctor_type,
                        'outlet_name' => $doctor_name,
                        'outlet_address' => $doctor_address,
                        'outlet_phone' => $doctor_phone,
                    ]
                );

                $doctor->code = $doctor->code . $doctor->id . substr($doctor->type, 0, 3);
                $doctor->save();

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

                //create test result 
                $test_result = TestResult::firstOrCreate(
                    [
                        'doctor_id' => $doctor_id,
                        'patient_id' => $patient_id,
                        'lab_no' => $lab_no,
                    ],
                    [
                        'ref_id' => $reference_id,
                        'bill_code' => $bill_code,
                        'collected_date' => $collected_date,
                        'received_date' => $received_date,
                        'reported_date' => $reported_date,
                        'is_completed' => true,
                        'validated_by' => $validated_by,
                    ]
                );

                $panel_profile = PanelProfile::firstOrCreate(['lab_id' => $lab_id, 'name' => $package_name]);
                $panel_profile_id = $panel_profile->id;

                //create same category with profile
                $panel_category = PanelCategory::firstOrCreate(['lab_id' => $lab_id, 'panel_profile_id' => $panel_profile_id], ['name' => $package_name]);
                $panel_category_id = $panel_category->id;

                //get test result id
                $test_result_id = $test_result->id;

                //loop through results
                foreach ($results as $key => $item) {
                    //assign array key as panel name
                    $panel_name = $key;
                    $panel_code = $item['panel_code'];
                    $panel_sequence = $item['panel_sequence'];
                    $overall_notes = $item['panel_remarks'];
                    $result_status = $item['result_status'];

                    //create panel
                    $panel = Panel::firstOrCreate(
                        [
                            'lab_id' => $lab_id,
                            'name' => $panel_name,
                            'panel_category_id' => $panel_category_id,
                            'code' => $panel_code,
                        ],
                        [
                            'sequence' => $panel_sequence,
                            'overall_notes' => $overall_notes
                        ]
                    );

                    //get panel id
                    $panel_id = $panel->id;

                    //check if array tests available
                    if (filled($item['tests'])) {
                        //loop through tests
                        foreach ($item['tests'] as $index => $test) {
                            //create panel item
                            $panel_item = PanelItem::firstOrCreate(
                                [
                                    'panel_id' => $panel_id,
                                    'name' => $test['test_name'],
                                ],
                                [
                                    'decimal_point' => $test['decimal_point'],
                                    'unit' => $test['unit'],
                                    'item_sequence' => $test['item_sequence']
                                ]
                            );

                            //get panel item id
                            $panel_item_id = $panel_item->id;

                            //check if panel item has reference range
                            if (filled($test['ref_range'])) {
                                //create reference range
                                $ref_range = ReferenceRange::firstOrCreate(
                                    [
                                        'value' => $test['ref_range'],
                                        'panel_item_id' => $panel_item_id,
                                    ]
                                );

                                //get reference range id
                                $ref_range_id = $ref_range->id;
                            }

                            //create test result item (with result value)
                            TestResultItem::firstOrCreate(
                                [
                                    'test_result_id' => $test_result_id,
                                    'reference_range_id' => $ref_range_id,
                                    'value' => $test['result_value']
                                ],
                                [
                                    'flag' => $test['result_flag'],
                                    'test_notes' => $test['test_note'],
                                    'status' => 'C',
                                    'is_completed' => $result_status != 1 ? false : true
                                ]
                            );
                        }
                    }
                }

                //create delivery file for tracking purposes
                $deliveryFile = DeliveryFile::firstOrCreate(
                    [
                        'test_result_id' => $test_result_id,
                    ],
                    [
                        'lab_id' => $lab_id,
                        'sending_facility' => $sending_facility,
                        'batch_id' => $batch_id,
                        'json_content' => json_encode($validated),
                        'status' => DeliveryFile::compl,
                    ]
                );

                DB::commit();
                //return result id
                return response()->json($test_result_id, 200);
            }
        } catch (\Throwable $e) {
            DB::rollBack();

            /** @var \Illuminate\Http\Request $request */
            Log::error('Failed to save data', [
                'exception' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'data' => json_encode($request->all()),
            ]);

            // Create delivery file if it doesn't exist for error tracking
            if (!isset($deliveryFile)) {
                $deliveryFile = DeliveryFile::create([
                    'lab_id' => $lab_id ?? null,
                    'test_result_id' => null,
                    'sending_facility' => $sending_facility ?? 'UNKNOWN',
                    'batch_id' => $batch_id ?? 'ERROR_' . now()->format('YmdHis'),
                    'json_content' => json_encode($validated),
                    'status' => DeliveryFile::fld,
                ]);
            }

            // Always create delivery file history for errors
            DeliveryFileHistory::create([
                'delivery_file_id' => $deliveryFile->id,
                'message' => $e->getMessage(),
                'err_code' => '500',
            ]);

            // Update delivery file status to failed
            $deliveryFile->update(['status' => DeliveryFile::fld]);

            return response()->json([
                'error' => 'Failed to save data',
            ], 500);
        }
    }

    /**
     * Convert datetime string to Carbon instance
     * Handles formats: YYYYMMDD and YYYYMMDDHHMM
     */
    private function convertDatetime($dateString)
    {
        if (empty($dateString)) {
            return null;
        }

        try {
            // Handle YYYYMMDD format (8 digits)
            if (strlen($dateString) === 8) {
                return Carbon::createFromFormat('Ymd H:i:s', $dateString . ' 00:00:00');
            }
            // Handle YYYYMMDDHHMM format (12 digits)
            elseif (strlen($dateString) === 12) {
                return Carbon::createFromFormat('YmdHi', $dateString);
            }
            // Handle other potential formats
            else {
                return Carbon::parse($dateString);
            }
        } catch (\Exception $e) {
            Log::warning('Failed to parse datetime', [
                'dateString' => $dateString,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
}
