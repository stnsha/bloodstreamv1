<?php

namespace App\Http\Controllers\API\Innoquest;

use App\Http\Controllers\API\BaseResultsController;
use App\Http\Requests\InnoquestResultRequest;
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
use App\Jobs\Innoquest\ProcessPanelResults;
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
        $requestStartTime = microtime(true);
        $requestId = uniqid('panel_', true);

        $validated = $request->validated();

        $user = Auth::guard('lab')->user();
        $lab_id = $user->lab_id;

        Log::channel('performance')->info('Panel request received (async)', [
            'request_id' => $requestId,
            'orders_count' => count($request->input('Orders', [])),
            'has_patient' => $request->has('patient'),
            'has_pdf' => $request->has('EncodedBase64pdf'),
            'payload_size_kb' => round(strlen(json_encode($request->all())) / 1024, 2),
        ]);

        ProcessPanelResults::dispatch($validated, $requestId, $lab_id);

        $duration = round((microtime(true) - $requestStartTime) * 1000, 2);

        Log::channel('performance')->info('Panel request queued', [
            'request_id' => $requestId,
            'duration_ms' => $duration,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Panel results received and queued for processing',
            'request_id' => $requestId,
            'received_at' => now()->toIso8601String(),
        ], 202);
    }

}