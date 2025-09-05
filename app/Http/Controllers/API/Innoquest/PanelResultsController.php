<?php

namespace App\Http\Controllers\API\Innoquest;

use App\Http\Controllers\API\BaseResultsController;
use App\Http\Requests\InnoquestResultRequest;
use App\Models\DeliveryFile;
use App\Models\DeliveryFileHistory;
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
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PanelResultsController extends BaseResultsController
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

        try {
            Log::info('Panel results submission started', [
                'lab_id' => Auth::guard('lab')->user()->lab_id ?? null,
                'sending_facility' => $validated['SendingFacility'] ?? null,
                'message_control_id' => $validated['MessageControlID'] ?? null,
                'json_content' => $validated
            ]);

            if ($validated) {
                DB::beginTransaction();
                //get current user lab id
                $user = Auth::guard('lab')->user();
                $lab_id = $user->lab_id;

                //check for batch
                if (filled($validated['SendingFacility'])) {
                    $sending_facility = $validated['SendingFacility'];
                    $batch_id = $validated['MessageControlID'] ?? null;
                }

                //Find or create patient information
                $patient_id = $this->findOrCreatePatient($validated['patient'], $batch_id);

                //check for reference id in field (before confirmed)
                $reference_id = null;

                //loop through orders
                foreach ($validated['Orders'] as $key => $od) {
                    if (is_null($reference_id) && filled($od['PlacerOrderNumber'])) $reference_id = $od['PlacerOrderNumber'];

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

                            //create test result - firstOrCreate for local tests
                            $test_result = TestResult::updateOrCreate(
                                [
                                    'lab_no' => $lab_no,
                                ],
                                [
                                    'ref_id' => $reference_id,
                                    'doctor_id' => $doctor_id,
                                    'patient_id' => $patient_id,
                                    'collected_date' => $collected_date,
                                    // 'received_date' => null,
                                    'reported_date' => $reported_date,
                                    'is_completed' => false
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

                                    // if ($identifier == 'REPORT') {
                                    //     // Handle report identifier
                                    //     $result = $this->parseLabReport($res['Value']);

                                    //     return json_encode($result, JSON_PRETTY_PRINT);
                                    // }
                                    // exit;

                                    //result items 
                                    if (filled($res['Text']) && ($res['Text'] != 'COMMENT' && $res['Text'] != 'NOTE')) {

                                        // 1. Create or find master panel item
                                        $masterPanelItem = MasterPanelItem::updateOrCreate([
                                            'name' => $res['Text'],
                                            'unit' => $unit,
                                        ]);

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
                    Log::info('Processing EncodedBase64pdf', [
                        'test_result_id' => $test_result->id,
                        'pdf_data' => $validated['EncodedBase64pdf']
                    ]);

                    $test_result->is_completed = true;
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

                DB::commit();

                Log::info('Panel results processed successfully', [
                    'test_result_id' => $test_result->id ?? null,
                    'lab_id' => $lab_id,
                    'patient_id' => $patient_id ?? null
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

    private function parseLabReport($raw)
    {
        // Initialize the result structure
        $result = [
            'department' => null,
            'section' => null,
            'specimen' => null,
            'tests' => [],
            'notes' => []
        ];

        // Rule 1: Replace line breaks with actual newlines - handle all patterns
        $text = preg_replace('/\\\\.br\\\\\\\\/', "\n", $raw);
        $text = preg_replace('/\\\\.br\\\\/', "\n", $text);
        $text = trim($text);

        // Split into lines for processing
        $lines = explode("\n", $text);
        $lines = array_filter(array_map('trim', $lines));

        $lastMainTest = null;
        $lastMainTestIndex = -1;

        foreach ($lines as $line) {
            if (empty($line)) continue;

            // Rule 2: Extract department and specimen - be more flexible
            if (preg_match('/^(.+?)\s+SPECIMEN:\s*(.+)$/i', $line, $matches)) {
                $result['department'] = trim($matches[1]);
                $result['specimen'] = trim($matches[2]);
                continue;
            }

            // If no specimen header found, try to extract department from first meaningful line
            if (!$result['department'] && preg_match('/^([A-Z\s]{3,}(?:STUDIES|CHEMISTRY|HAEMATOLOGY|BIOCHEMISTRY|LIPID|URINE|SPECIAL))(?:\s|$)/i', $line, $matches)) {
                $result['department'] = trim($matches[1]);
                continue;
            }

            // Skip lines that are clearly notes/metadata
            if (preg_match('/^(Reference|Source|Note|IFG:|IGT:|OGTT:|T2DM|Recommend|Clinical Practice|Ministry of Health|Academy of Medicine|Diagnostic Values|Target|Tight target)/i', $line)) {
                $result['notes'][] = $line;
                continue;
            }

            // Skip lines with only symbols or formatting
            if (preg_match('/^[\s\*\-\=<>]*$/', $line) || strlen($line) < 3) {
                continue;
            }

            // Try to parse as a test result - Pattern 1: Test with multiple values (like HbA1c with % and mmol/mol)
            if (preg_match('/^\s*([A-Za-z][A-Za-z0-9\s\.-]+?)\s+(\d+(?:\.\d+)?)\s*([%\w\/\s<>]+?)\s+(\d+(?:\.\d+)?)\s*([\w\/\s]+)(?:\s*\(([^)]+)\))?/i', $line, $matches)) {
                $testName = trim($matches[1]);
                $value1 = floatval($matches[2]);
                $unit1 = trim($matches[3]);
                $value2 = floatval($matches[4]);
                $unit2 = trim($matches[5]);
                $refRange = isset($matches[6]) ? trim($matches[6]) : null;
                $flag = $this->detectFlag($line, $testName);

                $test = [
                    'name' => $testName,
                    'value' => $value1,
                    'unit' => $unit1,
                    'reference_range' => $refRange,
                    'flag' => $flag,
                    'differentials' => [],
                    'indices' => [
                        [
                            'name' => $testName . ' (IFCC)',
                            'value' => $value2,
                            'unit' => $unit2,
                            'reference_range' => null
                        ]
                    ]
                ];

                $result['tests'][] = $test;
                $lastMainTest = $test;
                $lastMainTestIndex = count($result['tests']) - 1;
                continue;
            }

            // Pattern 2: Standard test result - Test Name Value Unit (Reference)
            if (preg_match('/^\s*(\*?\s*)?([A-Za-z][A-Za-z0-9\s\.-\/\:]+?)\s+(\\\\H\\\\)?(\d+(?:\.\d+)?)(\\\\N\\\\)?\s*([\w\/\s\^<>x-]+)?\s*(?:\(([^)]+)\))?/i', $line, $matches)) {
                $testName = trim($matches[2]);
                $hasAbnormalMarker = !empty($matches[3]) || !empty($matches[5]) || !empty($matches[1]);
                $value = floatval($matches[4]);
                $unit = isset($matches[6]) ? $this->fixUnits(trim($matches[6])) : null;
                $refRange = isset($matches[7]) ? trim($matches[7]) : null;
                $flag = $hasAbnormalMarker ? 'Abnormal' : $this->detectFlag($line, $testName);

                // Skip if this looks like a reference table or metadata
                if (preg_match('/^[<>=\d\s\.-]+$/', $testName) || strlen($testName) < 2) {
                    $result['notes'][] = $line;
                    continue;
                }

                $test = [
                    'name' => $testName,
                    'value' => $value,
                    'unit' => $unit,
                    'reference_range' => $refRange,
                    'flag' => $flag,
                    'differentials' => [],
                    'indices' => []
                ];

                $result['tests'][] = $test;
                $lastMainTest = $test;
                $lastMainTestIndex = count($result['tests']) - 1;
                continue;
            }

            // Pattern 3: Differential counts (percentage + absolute)
            if (preg_match('/^\s*(\*?\s*)?([A-Za-z][A-Za-z\s\.-]+?)\s+(\d+(?:\.\d+)?)\s*%\s+(\d+(?:\.\d+)?)\s+([\w\/\s\^<>x-]+)\s*(?:\(([^)]+)\))?/i', $line, $matches)) {
                $testName = trim($matches[2]);
                $percentage = floatval($matches[3]);
                $absolute = floatval($matches[4]);
                $unit = $this->fixUnits(trim($matches[5]));
                $refRange = isset($matches[6]) ? trim($matches[6]) : null;
                $flag = $this->detectFlag($line, $testName);

                // Find or create White Cell Count parent
                $parentTestIndex = $this->findParentTestIndex($result['tests'], 'White Cell Count');
                if ($parentTestIndex === -1) {
                    // Create parent test
                    $result['tests'][] = [
                        'name' => 'White Cell Count',
                        'value' => null,
                        'unit' => null,
                        'reference_range' => null,
                        'flag' => null,
                        'differentials' => [],
                        'indices' => []
                    ];
                    $parentTestIndex = count($result['tests']) - 1;
                }

                $differential = [
                    'name' => $testName,
                    'percentage' => $percentage,
                    'absolute' => $absolute,
                    'unit' => $unit,
                    'reference_range' => $refRange,
                    'flag' => $flag
                ];

                $result['tests'][$parentTestIndex]['differentials'][] = $differential;
                continue;
            }

            // If no pattern matches, treat as notes
            $result['notes'][] = $line;
        }

        return $result;
    }

    private function fixUnits($unit)
    {
        // Fix units like "x 10 -S-12" → "x 10^12"
        $unit = preg_replace('/x\s*10\s*-S-(\d+)/', 'x 10^$1', $unit);

        // Fix other common unit patterns
        $unit = preg_replace('/\s+/', ' ', $unit);
        $unit = str_replace(' ^ ', '^', $unit);

        return trim($unit);
    }

    private function detectFlag($line, $testName)
    {
        // Check for abnormal markers
        if (preg_match('/\\\\H\\\\[^\\\\]*\\\\N\\\\/', $line)) {
            return 'Abnormal';
        }

        // Check for asterisk indicating abnormal
        if (preg_match('/^\s*\*/', $line)) {
            return 'Abnormal';
        }

        return null;
    }

    private function isIndex($testName, $parentTest)
    {
        $indexNames = ['MPV', 'PDW', 'PCT', 'PLCR'];
        $parentName = $parentTest['name'];

        // Check if it's a known index under Platelets
        if (stripos($parentName, 'Platelet') !== false && in_array(strtoupper($testName), $indexNames)) {
            return true;
        }

        // Check if it's a ratio
        if (stripos($testName, 'ratio') !== false || stripos($testName, '/') !== false) {
            return true;
        }

        return false;
    }

    private function tokenizeLabText($text)
    {
        // Split by common test name patterns while preserving the full test info
        $tokens = [];

        // List of common test names to split on
        $testNames = [
            'Haemoglobin',
            'RBC',
            'PCV',
            'MCV',
            'MCH',
            'MCHC',
            'RDW',
            'White Cell Count',
            'Neutrophils',
            'Lymphocytes',
            'Monocytes',
            'Eosinophils',
            'Basophils',
            'N:L Ratio',
            'Platelets',
            'MPV',
            'PDW',
            'PCT',
            'PLCR',
            'ESR',
            'Total Protein',
            'Albumin',
            'Globulin',
            'Alkaline Phosphatase',
            'Total Bilirubin',
            'GGT',
            'AST',
            'ALT',
            'Cholesterol',
            'Triglycerides',
            'HDL',
            'LDL'
        ];

        $pattern = '/\b(' . implode('|', array_map('preg_quote', $testNames)) . ')\b/i';
        $parts = preg_split($pattern, $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);

        // Recombine test names with their values
        $currentToken = '';
        for ($i = 0; $i < count($parts); $i++) {
            $part = trim($parts[$i]);
            if (empty($part)) continue;

            // Check if this part is a test name
            if (preg_match('/^(' . implode('|', array_map('preg_quote', $testNames)) . ')$/i', $part)) {
                // If we have a previous token, add it
                if (!empty($currentToken)) {
                    $tokens[] = trim($currentToken);
                }
                // Start new token with test name
                $currentToken = $part;
                // Add the next part (values, units, etc.)
                if ($i + 1 < count($parts)) {
                    $i++;
                    $currentToken .= ' ' . trim($parts[$i]);
                }
            } else {
                $currentToken .= ' ' . $part;
            }
        }

        // Add the last token
        if (!empty($currentToken)) {
            $tokens[] = trim($currentToken);
        }

        return $tokens;
    }

    private function findParentTestIndex($tests, $parentName)
    {
        foreach ($tests as $index => $test) {
            if (stripos($test['name'], $parentName) !== false) {
                return $index;
            }
        }
        return -1;
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