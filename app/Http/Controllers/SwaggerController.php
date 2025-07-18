<?php

namespace App\Http\Controllers;

/**
 * @OA\Info(
 *     version="1.0",
 *     title="Blood Stream v1 API",
 *     description="",
 *     @OA\Contact(name="Digital Innovation")
 * )
 * 
 * @OA\SecurityScheme(
 *     securityScheme="bearerAuth",
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="JWT"
 * )
 * @OA\Server(
 *      url=L5_SWAGGER_CONST_HOST,
 *      description=L5_SWAGGER_CONST_DESCRIPTION
 * )
 * @OA\Schema(
 *     schema="Result",
 *     type="object",
 *     required={"lab_no", "doctor_code", "validated_by", "patient", "package_name", "results"},
 *     @OA\Property(property="reference_id", type="string", example="ABC12345"),
 *     @OA\Property(property="lab_no", type="string", example="123456789"),
 *     @OA\Property(property="bill_code", type="string", example="AMC_ALPRO"),
 *     @OA\Property(property="doctor_code", type="string", example="ABC122"),
 *     @OA\Property(property="received_date", type="string", format="date-time", example="2025-01-19 00:00:00"),
 *     @OA\Property(property="reported_date", type="string", format="date-time", example="2025-01-19 00:00:00"),
 *     @OA\Property(property="collected_date", type="string", format="date-time", example="2025-01-19 00:00:00"),
 *     @OA\Property(property="validated_by", type="string", example="Richard Roe, Bsc in Biomedical"),
 *     @OA\Property(
 *         property="patient",
 *         type="object",
 *         required={"patient_icno", "patient_name", "patient_gender", "patient_age"},
 *         @OA\Property(property="patient_icno", type="string", example="870521145681"),
 *         @OA\Property(property="patient_gender", type="string", example="Male"),
 *         @OA\Property(property="patient_age", type="string", example="54"),
 *         @OA\Property(property="patient_name", type="string", example="JOHN DOE"),
 *         @OA\Property(property="patient_tel", type="string", example="012-3456789")
 *     ),
 *     @OA\Property(property="package_name", type="string", example="AC ESSENTIAL PACKAGE"),
 *     @OA\Property(
 *         property="results",
 *         type="object",
 *         @OA\Property(
 *             property="Haematology",
 *             type="object",
 *             required={"result_status", "tests"},
 *             @OA\Property(property="panel_sequence", type="integer", nullable=true, example=1),
 *             @OA\Property(property="panel_remarks", type="string", nullable=true, example=null),
 *             @OA\Property(property="result_status", type="integer", example=1),
 *             @OA\Property(
 *                 property="tests",
 *                 type="array",
 *                 @OA\Items(
 *                     type="object",
 *                     required={"test_name", "item_sequence"},
 *                     @OA\Property(property="test_name", type="string", example="Haemoglobin"),
 *                     @OA\Property(property="result_value", type="string", example="15.7"),
 *                     @OA\Property(property="result_flag", type="string", nullable=true, example=null),
 *                     @OA\Property(property="unit", type="string", example="g/dL"),
 *                     @OA\Property(property="ref_range", type="string", example="M: 13.0 - 18.0; F: 11.5 - 16.0"),
 *                     @OA\Property(property="test_note", type="string", nullable=true, example=null),
 *                     @OA\Property(property="report_sequence", type="integer", example=1)
 *                 )
 *             )
 *         )
 *     )
 * )
 */

class SwaggerController extends Controller
{
    // This controller is only for OpenAPI annotations
}
