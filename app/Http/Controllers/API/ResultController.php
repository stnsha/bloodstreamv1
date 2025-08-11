<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\InnoquestResultRequest;
use App\Http\Requests\StorePatientResultRequest;
use App\Models\DeliveryFile;
use App\Models\DeliveryFileHistory;
use App\Models\Doctor;
use App\Models\Panel;
use App\Models\PanelComment;
use App\Models\PanelItem;
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
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Exception;

class ResultController extends Controller
{
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
     *                 @OA\Property(property="test_result_id", type="integer", example=123)
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
        // dd($validated);
        $lab_id = null;
        $test_result = null;
        $deliveryFile = null;
        $sending_facility = null;
        $batch_id = null;

        try {
            Log::info('Panel results submission started', [
                'lab_id' => Auth::guard('lab')->user()->lab_id ?? null,
                'sending_facility' => $validated['SendingFacility'] ?? null,
                'message_control_id' => $validated['MessageControlID'] ?? null
            ]);

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

                //check for age and gender from AlternatePatientID (NRIC) if available
                $icno = null;
                $ic_type = Patient::IC_TYPE_OTHERS;
                $patient_gender = null;
                $age = null;

                if (filled($validated['patient']['AlternatePatientID'])) {
                    $icInfo = checkIcno($validated['patient']['AlternatePatientID']);
                    $icno = $icInfo['icno'];
                    $ic_type = $icInfo['type'];
                    $patient_gender = $icInfo['gender'];
                    $age = $icInfo['age'];
                } else {
                    // Use PatientID as fallback if AlternatePatientID not available
                    $icno = $validated['patient']['PatientID'] ?? 'N/A_' . $batch_id;
                }

                //get from json (PatientLastName is always expected)
                $patient_name = $validated['patient']['PatientLastName'];
                $patient_dob = filled($validated['patient']['PatientDOB']) ? $validated['patient']['PatientDOB'] : null;
                $gender = $validated['patient']['PatientGender']; // Always expected

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

                //get patient id
                $patient_id = $patient->id;

                //loop through orders
                foreach ($validated['Orders'] as $key => $od) {
                    if (is_null($reference_id) && filled($od['PlacerOrderNumber'])) $reference_id = $od['PlacerOrderNumber'];

                    //get doctor name and code
                    $doctor_name = $od['OrderingProvider']['Name'];
                    $doctor_id = $od['OrderingProvider']['Code'];

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

                            //check if previous reference id is null and PlacerOrderNumber exist
                            if (is_null($reference_id) && filled($obv['PlacerOrderNumber'])) $reference_id = $obv['PlacerOrderNumber'];

                            //bill code is not sent in json payload
                            $bill_code = null;

                            //get collected and referred (reported)date
                            $collected_date = $this->convertDatetime($obv['SpecimenDateTime']);
                            // $received_date = $obv['EndDateTime'];
                            $reported_date = $this->convertDatetime($obv['RequestedDateTime']);

                            //get profile code (optional)
                            $profile_code = $obv['PackageCode'] ?? null;
                            $panel_profile_id = null;

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
                                $panel_profile_id = $panel_profile->id;
                            }


                            //get panel code
                            $panel_code = $obv['ProcedureCode'];
                            $panel_name = $obv['ProcedureDescription'];
                            $panel_notes = filled($obv['ClinicalInformation']) ? $obv['ClinicalInformation'] : null; //overall notes

                            $panel = Panel::where('lab_id', $lab_id)->where('code', $panel_code)->where('name', $panel_name)->first();
                            $isTagOn = false;
                            if (!$panel) {
                                $isTagOn = $this->isTagOnItem($panel_name);

                                if ($isTagOn) {
                                    $isTagOn = true;

                                    //search if tag on code
                                    $panelTag = PanelTag::where('lab_id', $lab_id)->where('code', $panel_code)->first();

                                    //If not found
                                    if (!$panelTag) {
                                        //remove word tag on on panel name to search in panel 
                                        $tempPanelName = $this->extractBasePanelName($panel_name);

                                        //Search panel by name
                                        $isPanelExist = Panel::where(function ($query) use ($tempPanelName) {
                                            $query->whereRaw('LOWER(name) = ?', [strtolower($tempPanelName)]);
                                        })
                                            ->where('lab_id', $lab_id)
                                            ->first();

                                        //If found
                                        if ($isPanelExist) {
                                            $panel_id = $isPanelExist->id;
                                        }

                                        //create new panel tag
                                        PanelTag::firstOrCreate(
                                            [
                                                'lab_id' => $lab_id,
                                                'panel_id' => $panel_id,
                                                'code' => $panel_code,
                                            ],
                                            [
                                                'name' => $panel_name,
                                            ]
                                        );
                                    } else {
                                        $panel_id = $panelTag->panel_id;
                                    }
                                } else {
                                    $createPanel = Panel::firstOrCreate(
                                        [
                                            'lab_id' => $lab_id,
                                            'code' => $panel_code,
                                        ],
                                        [
                                            'name' => $panel_name,
                                        ]
                                    );

                                    $panel_id = $createPanel->id;
                                }
                            } else {
                                $panel_id = $panel->id;
                            }

                            //create test result
                            $test_result = TestResult::firstOrCreate(
                                [
                                    'ref_id' => $reference_id,
                                    'lab_no' => $lab_no,
                                ],
                                [
                                    'doctor_id' => $doctor_id,
                                    'patient_id' => $patient_id,
                                    'bill_code' => $bill_code,
                                    'panel_profile_id' => $panel_profile_id,
                                    'is_tagon' => $isTagOn,
                                    'collected_date' => $collected_date,
                                    'received_date' => null,
                                    'reported_date' => $reported_date,
                                    'is_completed' => false
                                ]
                            );

                            //get test result id
                            $test_result_id = $test_result->id;

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


                                    //store field value to variable
                                    $type = $res['Type'];
                                    $identifier = $res['Identifier'];

                                    $suffix = strpos($identifier, '#') !== false ? explode('#', $identifier)[1] : null;

                                    //result items 
                                    if (filled($res['Text']) && $res['Text'] != 'COMMENT') {
                                        //create panel items
                                        $panel_item = PanelItem::firstOrCreate(
                                            [
                                                'lab_id' => $lab_id,
                                                'name' => $res['Text'],
                                            ],
                                            [
                                                'decimal_point' => null,
                                                'unit' => $unit,
                                                'sequence' => null,
                                                'result_type' => $type,
                                                'identifier' => $identifier,
                                                'code' => $suffix,
                                            ]
                                        );

                                        // Attach the panel to this panel item (many-to-many)
                                        $panel = Panel::find($panel_id);
                                        if ($panel) {
                                            $panel->panelItems()->syncWithoutDetaching([$panel_item->id]);
                                        }

                                        //get panel item id
                                        $panel_item_id = $panel_item->id;

                                        //create reference range
                                        $ref_range_id = null;
                                        if (filled($res['ReferenceRange'])) {
                                            $ref_range = ReferenceRange::firstOrCreate(
                                                [
                                                    'value' => $res['ReferenceRange'],
                                                    'panel_item_id' => $panel_item_id,
                                                ]
                                            );
                                            $ref_range_id = $ref_range->id;
                                        }

                                        //final insert result item
                                        TestResultItem::firstOrCreate(
                                            [
                                                'test_result_id' => $test_result_id,
                                                'panel_item_id' => $panel_item_id,
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
                                    if ($res['Text'] == 'COMMENT' && isset($panel_item_id)) {
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
                if (isset($validated['EncodedBase64pdf']) && filled($validated['EncodedBase64pdf']) && $test_result) {
                    try {
                        $test_result->is_completed = true;
                        $test_result->save();
                        //Decode the base64 PDF data
                        // $pdfData = base64_decode($validated['EncodedBase64pdf']);

                        //Generate a unique filename
                        // $filename = 'test_result_' . $test_result->id . '_' . time() . '.pdf';

                        //Store the PDF file in storage/app/public/test_results directory
                        // $filePath = 'pdf/' . $filename;
                        // Storage::disk('public')->put($filePath, $pdfData);

                        // Update test result with PDF file path and mark as completed

                        // Log::info('PDF file saved successfully', [
                        //     'test_result_id' => $test_result->id,
                        //     'file_path' => $filePath
                        // ]);
                    } catch (Exception $e) {
                        Log::error('Failed to decode and save PDF', [
                            'test_result_id' => $test_result->id,
                            'error' => $e->getMessage()
                        ]);

                        // Still mark as completed even if PDF save fails
                        $test_result->is_completed = true;
                        $test_result->save();
                    }
                }

                //create delivery file for tracking purposes
                if ($test_result) {
                    $deliveryFile = DeliveryFile::firstOrCreate(
                        [
                            'test_result_id' => $test_result->id,
                        ],
                        [
                            'lab_id' => $lab_id,
                            'sending_facility' => $sending_facility,
                            'batch_id' => $batch_id,
                            'json_content' => json_encode($validated),
                            'status' => DeliveryFile::compl,
                        ]
                    );
                }

                DB::commit();

                Log::info('Panel results processed successfully', [
                    'test_result_id' => $test_result->id ?? null,
                    'lab_id' => $lab_id,
                    'patient_id' => $patient_id ?? null
                ]);

                return response()->json([
                    'success' => true,
                    'data' => [
                        'test_result_id' => $test_result->id ?? null
                    ],
                    'message' => 'Panel results processed successfully'
                ], 200);
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

            //sCreate delivery file if it doesn't exist for error tracking
            if (!$deliveryFile) {
                $deliveryFile = DeliveryFile::create([
                    'lab_id' => $lab_id ?? null,
                    'test_result_id' => $test_result->id ?? null,
                    'sending_facility' => $sending_facility ?? 'UNKNOWN',
                    'batch_id' => $batch_id ?? 'ERROR_' . now()->format('YmdHis'),
                    'json_content' => json_encode($validated ?? []),
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
                'success' => false,
                'message' => 'Failed to process results',
                'error' => 'Internal server error'
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
                        'code' => $doctor_code . substr($doctor_type, 0, 3),
                    ],
                    [
                        'name' => $doctor_name,
                        'type' => $doctor_type,
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
                    $panel_profile = PanelProfile::firstOrCreate(['lab_id' => $lab_id, 'name' => $package_name]);
                    $panel_profile_id = $panel_profile->id;
                }

                //create test result 
                $test_result = TestResult::firstOrCreate(
                    [
                        'doctor_id' => $doctor_id,
                        'patient_id' => $patient_id,
                        'lab_no' => $lab_no,
                    ],
                    [
                        'ref_id' => $reference_id,
                        'panel_profile_id' => $panel_profile_id,
                        'bill_code' => $bill_code,
                        'collected_date' => $collected_date,
                        'received_date' => $received_date,
                        'reported_date' => $reported_date,
                        'is_completed' => true,
                        'validated_by' => $validated_by,
                    ]
                );


                //get test result id
                $test_result_id = $test_result->id;

                //loop through results
                foreach ($results as $key => $item) {
                    //assign array key as panel name
                    $panel_name = $key;
                    $panel_code = $item['panel_code'];
                    $panel_sequence = $item['panel_sequence'] ?? null;
                    $overall_notes = $item['panel_remarks'];
                    $result_status = $item['result_status'];

                    //create panel
                    $panel = Panel::firstOrCreate(
                        [
                            'lab_id' => $lab_id,
                            'name' => $panel_name,
                            'code' => $panel_code,
                        ],
                        [
                            'sequence' => $panel_sequence,
                            'overall_notes' => $overall_notes
                        ]
                    );

                    //check if array tests available
                    if (filled($item['tests'])) {
                        //loop through tests
                        foreach ($item['tests'] as $index => $test) {
                            //create panel item
                            $panel_item = PanelItem::firstOrCreate(
                                [
                                    'lab_id' => $lab_id,
                                    'code' => $test['test_code'],
                                    'name' => $test['test_name'],
                                ],
                                [
                                    'decimal_point' => $test['decimal_point'],
                                    'unit' => $test['unit'],
                                    'sequence' => $test['report_sequence']
                                ]
                            );

                            // Attach the panel to this panel item (many-to-many)
                            $panel->panelItems()->syncWithoutDetaching([$panel_item->id]);

                            //get panel item id
                            $panel_item_id = $panel_item->id;

                            //check if panel item has reference range
                            $ref_range_id = null;
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
                                    'panel_item_id' => $panel_item_id,
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
                'success' => false,
                'message' => 'Failed to process results',
                'error' => 'Internal server error'
            ], 500);
        }
    }

    // /**
    //  * Convert datetime string to Carbon instance
    //  * Handles formats: YYYYMMDD and YYYYMMDDHHMM
    //  */
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
        } catch (Exception $e) {
            Log::warning('Failed to parse datetime', [
                'dateString' => $dateString,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v1/testPanel",
     *     tags={"Test"},
     *     summary="Test endpoint for panel data",
     *     description="Test endpoint that logs and returns the request data",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             type="object",
     *             description="Any JSON data for testing"
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Test response with request data",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Request received"),
     *             @OA\Property(property="data", type="object", description="The request data that was sent")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Test panel request failed"),
     *             @OA\Property(property="error", type="string", example="Internal server error")
     *         )
     *     )
     * )
     */
    public function testPanel(Request $request)
    {
        try {
            Log::info('Test panel endpoint accessed', [
                'data' => $request->all()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Request received',
                'data' => $request->all()
            ], 200);
        } catch (\Throwable $e) {
            Log::error('Test panel endpoint error', [
                'exception' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Test panel request failed',
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
                            $query->where('lab_id', $lab_id)->with(['panels' => function ($query) use ($lab_id) {
                                $query->where('lab_id', $lab_id);
                            }]);
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

                $groupedResults[$panelName]['tests'][] = [
                    'test_name' => $panelItem ? $panelItem->name : null,
                    'test_code' => $panelItem ? $panelItem->code : null,
                    'result_value' => $item->value,
                    'unit' => $panelItem ? $panelItem->unit : null,
                    'reference_range' => $item->referenceRange ? $item->referenceRange->value : null,
                    'result_flag' => $item->flag,
                    'test_notes' => $item->test_notes,
                    'status' => $item->status,
                    'is_completed' => (bool) $item->is_completed,
                    'sequence' => $panelItem ? $panelItem->sequence : null
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
                'bill_code' => $testResult->bill_code,
                'collected_date' => $testResult->collected_date,
                'received_date' => $testResult->received_date,
                'reported_date' => $testResult->reported_date,
                'validated_by' => $testResult->validated_by,
                'is_completed' => (bool) $testResult->is_completed,
                'is_tagon' => (bool) $testResult->is_tagon,
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
        } catch (\Throwable $e) {
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

    private function isTagOnItem($panelName)
    {
        // Handle case where panelName might be an array or null
        $trimmed = trimOrNull($panelName);
        if (!$trimmed) {
            return false;
        }

        $tagOnKeywords = ['TAG ON', 'TAGON', 'TAG-ON'];
        foreach ($tagOnKeywords as $keyword) {
            if (Str::contains(strtoupper($trimmed), $keyword)) {
                return true;
            }
        }
        return false;
    }

    private function extractBasePanelName($panelName)
    {
        // Handle case where panelName might be an array or null
        $trimmed = trimOrNull($panelName);
        if (!$trimmed) {
            return '';
        }

        // Remove TAG ON related keywords and clean up
        $baseName = preg_replace('/\s*\(?\s*(TAG[\s\-]?ON)\s*\)?/i', '', $trimmed);
        $baseName = preg_replace('/\s*TAGON\s*/i', '', $baseName);

        return trimOrNull($baseName) ?: '';
    }
}
