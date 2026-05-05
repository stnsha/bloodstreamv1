<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>BloodStream API Documentation</title>
    <style>
        @page { size: A4; margin: 20mm 15mm 20mm 15mm; }
        body { font-family: Arial, sans-serif; font-size: 11px; color: #1a1a1a; margin: 0; padding: 0; }
        h1 { font-size: 20px; color: #003049; border-bottom: 2px solid #003049; padding-bottom: 6px; margin-bottom: 4px; }
        h2 { font-size: 14px; color: #003049; margin-top: 18px; margin-bottom: 6px; border-left: 4px solid #003049; padding-left: 8px; }
        h3 { font-size: 12px; color: #003049; margin-top: 12px; margin-bottom: 4px; }
        h4 { font-size: 11px; color: #003049; margin-top: 8px; margin-bottom: 3px; }
        p { margin: 4px 0; font-size: 11px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 10px; font-size: 10px; }
        th { background-color: #e8eef3; color: #003049; text-align: left; padding: 5px 8px; font-size: 10px; text-transform: uppercase; border: 1px solid #c5d3dc; font-weight: bold; }
        td { padding: 4px 8px; font-size: 10px; border: 1px solid #d1d5db; vertical-align: top; }
        tr.group-header td { background-color: #e8eef3; color: #003049; font-weight: bold; font-size: 10px; }
        .badge-yes { background-color: #fee2e2; color: #991b1b; padding: 1px 6px; border-radius: 10px; font-size: 9px; font-weight: bold; display: inline-block; }
        .badge-no { background-color: #f3f4f6; color: #6b7280; padding: 1px 6px; border-radius: 10px; font-size: 9px; display: inline-block; }
        .method-post { background-color: #1d4ed8; color: #ffffff; padding: 1px 6px; border-radius: 3px; font-size: 9px; font-weight: bold; display: inline-block; }
        .method-get { background-color: #15803d; color: #ffffff; padding: 1px 6px; border-radius: 3px; font-size: 9px; font-weight: bold; display: inline-block; }
        .status-200 { background-color: #dcfce7; color: #166534; padding: 1px 6px; border-radius: 3px; font-size: 9px; font-weight: bold; display: inline-block; }
        .status-401 { background-color: #fee2e2; color: #991b1b; padding: 1px 6px; border-radius: 3px; font-size: 9px; font-weight: bold; display: inline-block; }
        .status-422 { background-color: #fef9c3; color: #854d0e; padding: 1px 6px; border-radius: 3px; font-size: 9px; font-weight: bold; display: inline-block; }
        .status-4xx { background-color: #fee2e2; color: #991b1b; padding: 1px 6px; border-radius: 3px; font-size: 9px; font-weight: bold; display: inline-block; }
        pre { background-color: #f1f5f9; border: 1px solid #cbd5e1; padding: 8px; font-family: 'Courier New', monospace; font-size: 9px; white-space: pre-wrap; word-break: break-all; margin: 4px 0 8px 0; }
        .section-card { border: 1px solid #d1d9e0; border-radius: 4px; padding: 10px 14px; margin-bottom: 14px; }
        .endpoint-block { margin-bottom: 14px; padding-bottom: 10px; border-bottom: 1px dashed #d1d9e0; }
        .endpoint-block:last-child { border-bottom: none; margin-bottom: 0; padding-bottom: 0; }
        .endpoint-title { display: flex; align-items: center; gap: 8px; margin-bottom: 4px; }
        .endpoint-desc { font-size: 11px; color: #374151; margin-bottom: 8px; }
        .label { font-size: 10px; font-weight: bold; margin-bottom: 3px; display: block; color: #1a1a1a; }
        .generated-date { font-size: 9px; color: #6b7280; margin-top: 4px; margin-bottom: 16px; }
        .page-break { page-break-before: always; }
        .url-mono { font-family: 'Courier New', monospace; font-size: 11px; }
        ul { margin: 4px 0 8px 0; padding-left: 18px; }
        li { font-size: 11px; margin-bottom: 2px; }
    </style>
</head>
<body>

    <!-- Cover / Header -->
    <h1>BloodStream API Documentation</h1>
    <p class="generated-date">Generated: {{ $generatedAt }}</p>

    <!-- Section 1: Authentication Endpoints -->
    <div class="section-card">
        <h2>Authentication Endpoints</h2>

        <!-- Login -->
        <div class="endpoint-block">
            <div class="endpoint-title">
                <span class="method-post">POST</span>
                <span class="url-mono">/api/v1/login</span>
            </div>
            <p class="endpoint-desc">Authenticate lab user and return JWT token.</p>

            <span class="label">Request Body:</span>
            <pre>{
  "username": "LAB001user",     // Required: string
  "password": "password123"     // Required: string
}</pre>

            <span class="label">CURL Example:</span>
            <pre>curl -X POST "https://mytotalhealth.com.my/staging/api/v1/login" \
     -H "Content-Type: application/json" \
     -d '{
       "username": "LAB001user",
       "password": "password123"
     }'</pre>

            <span class="label">Response Examples:</span>
            <span class="status-200">200 Success</span>
            <pre>{
  "success": true,
  "data": {
    "access_token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
    "token_type": "bearer",
    "expires_in": 2592000
  },
  "message": "Login successful"
}</pre>
            <span class="status-401">401 Unauthorized</span>
            <pre>{
  "success": false,
  "message": "Invalid credentials",
  "error": "Unauthorized"
}</pre>
        </div>

        <!-- Logout -->
        <div class="endpoint-block">
            <div class="endpoint-title">
                <span class="method-post">POST</span>
                <span class="url-mono">/api/v1/logout</span>
            </div>
            <p class="endpoint-desc">Logout the authenticated lab user and invalidate the JWT token.</p>

            <span class="label">Headers:</span>
            <pre>Authorization: Bearer {your_jwt_token}</pre>

            <span class="label">CURL Example:</span>
            <pre>curl -X POST "https://mytotalhealth.com.my/staging/api/v1/logout" \
     -H "Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9..."</pre>
        </div>
    </div>

    <!-- Section 2: Patient Results Endpoint -->
    <div class="section-card page-break">
        <h2>Patient Results Endpoint</h2>

        <div class="endpoint-block">
            <div class="endpoint-title">
                <span class="method-post">POST</span>
                <span class="url-mono">/api/v1/result/patient</span>
            </div>
            <p class="endpoint-desc">Submit lab results for a patient in standard format.</p>

            <h3>Key Required Fields</h3>
            <table>
                <thead>
                    <tr>
                        <th style="width:30%">Field</th>
                        <th style="width:12%">Type</th>
                        <th style="width:12%">Required</th>
                        <th style="width:46%">Description</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Root Fields -->
                    <tr class="group-header">
                        <td colspan="4">Root Fields</td>
                    </tr>
                    <tr>
                        <td><code>reference_id</code></td>
                        <td>string</td>
                        <td><span class="badge-no">No</span></td>
                        <td>External reference identifier for the request</td>
                    </tr>
                    <tr>
                        <td><code>lab_no</code></td>
                        <td>string</td>
                        <td><span class="badge-yes">Yes</span></td>
                        <td>Laboratory accession number for this sample</td>
                    </tr>
                    <tr>
                        <td><code>bill_code</code></td>
                        <td>string</td>
                        <td><span class="badge-no">No</span></td>
                        <td>Billing or clinic code associated with the request</td>
                    </tr>
                    <tr>
                        <td><code>doctor</code></td>
                        <td>object</td>
                        <td><span class="badge-yes">Yes</span></td>
                        <td>Referring doctor / clinic information</td>
                    </tr>
                    <tr>
                        <td><code>patient</code></td>
                        <td>object</td>
                        <td><span class="badge-yes">Yes</span></td>
                        <td>Patient demographic information</td>
                    </tr>
                    <tr>
                        <td><code>validated_by</code></td>
                        <td>string</td>
                        <td><span class="badge-no">No</span></td>
                        <td>Name and credentials of the validating pathologist</td>
                    </tr>
                    <tr>
                        <td><code>package_name</code></td>
                        <td>string</td>
                        <td><span class="badge-no">No</span></td>
                        <td>Health package or profile name for grouping</td>
                    </tr>
                    <!-- Date Fields -->
                    <tr class="group-header">
                        <td colspan="4">Date Fields</td>
                    </tr>
                    <tr>
                        <td><code>collected_date</code></td>
                        <td>string</td>
                        <td><span class="badge-no">No</span></td>
                        <td>Date and time the sample was collected (YYYY-MM-DD HH:mm:ss)</td>
                    </tr>
                    <tr>
                        <td><code>received_date</code></td>
                        <td>string</td>
                        <td><span class="badge-no">No</span></td>
                        <td>Date and time the sample was received at the laboratory</td>
                    </tr>
                    <tr>
                        <td><code>reported_date</code></td>
                        <td>string</td>
                        <td><span class="badge-no">No</span></td>
                        <td>Date and time the result was reported</td>
                    </tr>
                    <!-- Doctor Object -->
                    <tr class="group-header">
                        <td colspan="4">Doctor Object</td>
                    </tr>
                    <tr>
                        <td><code>doctor.code</code></td>
                        <td>string</td>
                        <td><span class="badge-no">No</span></td>
                        <td>Short code identifying the referring doctor or clinic</td>
                    </tr>
                    <tr>
                        <td><code>doctor.name</code></td>
                        <td>string</td>
                        <td><span class="badge-yes">Yes</span></td>
                        <td>Full name of the referring doctor or clinic</td>
                    </tr>
                    <tr>
                        <td><code>doctor.type</code></td>
                        <td>string</td>
                        <td><span class="badge-no">No</span></td>
                        <td>Type of referring entity (e.g., "clinic", "hospital")</td>
                    </tr>
                    <tr>
                        <td><code>doctor.address</code></td>
                        <td>string</td>
                        <td><span class="badge-no">No</span></td>
                        <td>Physical address of the referring doctor or clinic</td>
                    </tr>
                    <tr>
                        <td><code>doctor.phone</code></td>
                        <td>string</td>
                        <td><span class="badge-no">No</span></td>
                        <td>Contact phone number of the referring doctor or clinic</td>
                    </tr>
                    <!-- Patient Object -->
                    <tr class="group-header">
                        <td colspan="4">Patient Object</td>
                    </tr>
                    <tr>
                        <td><code>patient.icno</code></td>
                        <td>string</td>
                        <td><span class="badge-yes">Yes</span></td>
                        <td>Patient identity card number (NRIC or passport)</td>
                    </tr>
                    <tr>
                        <td><code>patient.ic_type</code></td>
                        <td>string</td>
                        <td><span class="badge-no">No</span></td>
                        <td>Type of identity document (e.g., "NRIC", "PASSPORT")</td>
                    </tr>
                    <tr>
                        <td><code>patient.name</code></td>
                        <td>string</td>
                        <td><span class="badge-yes">Yes</span></td>
                        <td>Full name of the patient</td>
                    </tr>
                    <tr>
                        <td><code>patient.age</code></td>
                        <td>string</td>
                        <td><span class="badge-no">No</span></td>
                        <td>Age of the patient in years</td>
                    </tr>
                    <tr>
                        <td><code>patient.gender</code></td>
                        <td>string</td>
                        <td><span class="badge-no">No</span></td>
                        <td>Gender of the patient ("M" or "F")</td>
                    </tr>
                    <tr>
                        <td><code>patient.tel</code></td>
                        <td>string</td>
                        <td><span class="badge-no">No</span></td>
                        <td>Contact phone number of the patient</td>
                    </tr>
                    <!-- Results Structure -->
                    <tr class="group-header">
                        <td colspan="4">Results Structure</td>
                    </tr>
                    <tr>
                        <td><code>results</code></td>
                        <td>object</td>
                        <td><span class="badge-yes">Yes</span></td>
                        <td>Map of panel name keys to panel result objects</td>
                    </tr>
                    <tr>
                        <td><code>results.{panel_name}</code></td>
                        <td>object</td>
                        <td><span class="badge-yes">Yes</span></td>
                        <td>Result object for a named panel (e.g., "Haematology")</td>
                    </tr>
                    <tr>
                        <td><code>results.{panel_name}.panel_code</code></td>
                        <td>string</td>
                        <td><span class="badge-no">No</span></td>
                        <td>Short code identifying the panel</td>
                    </tr>
                    <tr>
                        <td><code>results.{panel_name}.panel_sequence</code></td>
                        <td>integer</td>
                        <td><span class="badge-no">No</span></td>
                        <td>Display ordering sequence for the panel</td>
                    </tr>
                    <tr>
                        <td><code>results.{panel_name}.panel_remarks</code></td>
                        <td>string|null</td>
                        <td><span class="badge-no">No</span></td>
                        <td>Free-text remarks for the panel; nullable</td>
                    </tr>
                    <tr>
                        <td><code>results.{panel_name}.result_status</code></td>
                        <td>integer</td>
                        <td><span class="badge-yes">Yes</span></td>
                        <td>Result status flag (1 = final)</td>
                    </tr>
                    <tr>
                        <td><code>results.{panel_name}.tests</code></td>
                        <td>array</td>
                        <td><span class="badge-yes">Yes</span></td>
                        <td>Array of individual test result objects within the panel</td>
                    </tr>
                    <!-- Tests Array Items -->
                    <tr class="group-header">
                        <td colspan="4">Tests Array Items (results.{panel_name}.tests[])</td>
                    </tr>
                    <tr>
                        <td><code>tests[].test_code</code></td>
                        <td>string</td>
                        <td><span class="badge-no">No</span></td>
                        <td>Short code for the individual test</td>
                    </tr>
                    <tr>
                        <td><code>tests[].test_name</code></td>
                        <td>string</td>
                        <td><span class="badge-yes">Yes</span></td>
                        <td>Human-readable name of the individual test</td>
                    </tr>
                    <tr>
                        <td><code>tests[].result_value</code></td>
                        <td>string</td>
                        <td><span class="badge-yes">Yes</span></td>
                        <td>The result value as a string</td>
                    </tr>
                    <tr>
                        <td><code>tests[].result_flag</code></td>
                        <td>string|null</td>
                        <td><span class="badge-no">No</span></td>
                        <td>Abnormality flag (e.g., "H", "L"); nullable</td>
                    </tr>
                    <tr>
                        <td><code>tests[].unit</code></td>
                        <td>string</td>
                        <td><span class="badge-no">No</span></td>
                        <td>Unit of measurement (e.g., "g/dL")</td>
                    </tr>
                    <tr>
                        <td><code>tests[].ref_range</code></td>
                        <td>string</td>
                        <td><span class="badge-no">No</span></td>
                        <td>Reference range string</td>
                    </tr>
                    <tr>
                        <td><code>tests[].test_note</code></td>
                        <td>string|null</td>
                        <td><span class="badge-no">No</span></td>
                        <td>Additional note for the test; nullable</td>
                    </tr>
                    <tr>
                        <td><code>tests[].report_sequence</code></td>
                        <td>integer</td>
                        <td><span class="badge-no">No</span></td>
                        <td>Display ordering sequence within the panel</td>
                    </tr>
                    <tr>
                        <td><code>tests[].decimal_point</code></td>
                        <td>integer</td>
                        <td><span class="badge-no">No</span></td>
                        <td>Number of decimal places to display for the result</td>
                    </tr>
                </tbody>
            </table>

            <span class="label">Headers:</span>
            <pre>Authorization: Bearer {your_jwt_token}
Content-Type: application/json
Accept: application/json</pre>

            <span class="label">Request Body Example:</span>
            <pre>{
  "reference_id": "REF123456",
  "lab_no": "LAB789012",
  "bill_code": "AMC_ALPRO",
  "doctor": {
    "code": "DOC001",
    "name": "AMC NETWORK SDN BHD (ALPRO PHARMACY)",
    "type": "clinic",
    "address": "123 Medical Street, KL",
    "phone": "03-12345678"
  },
  "patient": {
    "icno": "870521145681",
    "ic_type": "NRIC",
    "name": "JOHN DOE",
    "age": "37",
    "gender": "M",
    "tel": "012-3456789"
  },
  "collected_date": "2025-08-08 09:30:00",
  "received_date": "2025-08-08 10:00:00",
  "reported_date": "2025-08-08 14:30:00",
  "validated_by": "Dr. Richard Roe, Bsc in Biomedical",
  "package_name": "COMPREHENSIVE HEALTH PACKAGE",
  "results": {
    "Haematology": {
      "panel_code": "HAEM",
      "panel_sequence": 1,
      "panel_remarks": null,
      "result_status": 1,
      "tests": [{
        "test_code": "HGB",
        "test_name": "Haemoglobin",
        "result_value": "15.7",
        "result_flag": null,
        "unit": "g/dL",
        "ref_range": "M: 13.0 - 18.0; F: 11.5 - 16.0",
        "test_note": null,
        "report_sequence": 1,
        "decimal_point": 1
      }]
    },
    "Liver Function Tests": {
      "panel_code": "LFT",
      "panel_sequence": 2,
      "panel_remarks": "All values within normal range",
      "result_status": 1,
      "tests": [{
        "test_code": "TBIL",
        "test_name": "Total Bilirubin",
        "result_value": "9.7",
        "result_flag": null,
        "unit": "umol/L",
        "ref_range": "&lt;25.7",
        "test_note": null,
        "report_sequence": 17,
        "decimal_point": 1
      }]
    }
  }
}</pre>

            <span class="label">CURL Example:</span>
            <pre>curl -X POST "https://mytotalhealth.com.my/staging/api/v1/result/patient" \
     -H "accept: application/json" \
     -H "Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9..." \
     -H "Content-Type: application/json" \
     -d '{
       "lab_no": "123456789",
       "doctor": {
         "name": "AMC NETWORK SDN BHD (ALPRO PHARMACY)"
       },
       "patient": {
         "icno": "870521145681",
         "name": "JOHN DOE"
       },
       "results": {
         "Haematology": {
           "result_status": 1,
           "tests": [{
             "test_name": "Haemoglobin",
             "result_value": "15.7",
             "unit": "g/dL",
             "ref_range": "M: 13.0 - 18.0; F: 11.5 - 16.0",
             "report_sequence": 1
           }]
         }
       }
     }'</pre>

            <span class="label">Response Examples:</span>
            <span class="status-200">200 Success</span>
            <pre>{
  "success": true,
  "message": "Lab results processed successfully",
  "data": {
    "test_result_id": 456
  }
}</pre>
            <span class="status-422">422 Validation Error</span>
            <pre>{
  "message": "The given data was invalid.",
  "errors": {
    "patient.icno": ["The patient.icno field is required."],
    "lab_no": ["The lab_no field is required."],
    "results": ["The results field is required."]
  }
}</pre>
            <span class="status-4xx">500 Server Error</span>
            <pre>{
  "success": false,
  "message": "Failed to process lab results",
  "error": "Internal server error"
}</pre>
        </div>
    </div>

    <!-- Section 3: Other Endpoints -->
    <div class="section-card">
        <h2>Other Endpoints</h2>

        <!-- Get Result -->
        <div class="endpoint-block">
            <div class="endpoint-title">
                <span class="method-get">GET</span>
                <span class="url-mono">/api/v1/result/{id}</span>
            </div>
            <p class="endpoint-desc">Get a specific test result with all associated data including patient, doctor, panel results and test items.</p>

            <span class="label">URL Parameters:</span>
            <ul>
                <li><code>id</code> (integer, required): Test result ID</li>
            </ul>

            <span class="label">CURL Example:</span>
            <pre>curl -X GET "https://mytotalhealth.com.my/staging/api/v1/result/123" \
     -H "Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9..."</pre>
        </div>

        <!-- Test Panel -->
        <div class="endpoint-block">
            <div class="endpoint-title">
                <span class="method-post">POST</span>
                <span class="url-mono">/api/v1/testPanel</span>
            </div>
            <p class="endpoint-desc">Test endpoint that logs and returns the request data.</p>

            <span class="label">CURL Example:</span>
            <pre>curl -X POST "https://mytotalhealth.com.my/staging/api/v1/testPanel" \
     -H "accept: application/json" \
     -H "Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9..." \
     -H "Content-Type: application/json" \
     -d '{"test": "data"}'</pre>
        </div>
    </div>

    <!-- Section 4: Error Responses & Data Formats -->
    <div class="section-card">
        <h2>Error Responses</h2>

        <span class="label">All error responses follow this standardized format:</span>
        <pre>{
  "success": false,
  "message": "User-friendly error description",
  "error": "Error category or technical details"
}</pre>

        <span class="label">Common Error Status Codes:</span>
        <table>
            <thead>
                <tr>
                    <th style="width:20%">Status Code</th>
                    <th style="width:80%">Description</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><span class="status-4xx">400</span></td>
                    <td>Bad Request - Malformed request or invalid data</td>
                </tr>
                <tr>
                    <td><span class="status-4xx">401</span></td>
                    <td>Unauthorized - Invalid or missing authentication token</td>
                </tr>
                <tr>
                    <td><span class="status-4xx">404</span></td>
                    <td>Not Found - Requested resource not found</td>
                </tr>
                <tr>
                    <td><span class="status-422">422</span></td>
                    <td>Unprocessable Entity - Validation errors in request data</td>
                </tr>
                <tr>
                    <td><span class="status-4xx">500</span></td>
                    <td>Internal Server Error - Server-side errors</td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="section-card">
        <h2>Data Formats</h2>

        <h3>Date/Time Format</h3>
        <ul>
            <li><strong>Date fields:</strong> <code>YYYYMMDD</code> (e.g., "20250808")</li>
            <li><strong>DateTime fields:</strong> <code>YYYY-MM-DD HH:mm:ss</code> (e.g., "2025-08-08 14:30:00")</li>
            <li><strong>DateTime responses:</strong> ISO 8601 format with timezone (e.g., "2025-08-08T14:30:00.000000Z")</li>
        </ul>

        <h3>Field Requirements</h3>
        <ul>
            <li><strong>Required:</strong> Field must be present and not empty</li>
            <li><strong>Nullable:</strong> Field can be omitted or set to null</li>
            <li><strong>No (Optional):</strong> Field can be omitted but if present must have a valid value</li>
        </ul>
    </div>

</body>
</html>
