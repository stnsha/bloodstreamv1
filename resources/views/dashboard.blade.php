<x-app-layout>
    <div class="flex flex-col w-full pr-32">
        <div class="flex justify-start items-end mb-2">
            <img src="{{ asset('logo.svg') }}" class="w-8 h-8 opacity-80 mr-2" />
            <span class="font-semibold text-md tracking-wide">BloodStream v1</span>
        </div>
        <span class="font-normal text-sm text-justify tracking-wide mb-3">
            BloodStream is a centralized middleware system designed to act as a secure and customizable bridge
            between your organization and external laboratories. Its core function is to collect, normalize, and
            centralize patient blood test results, enabling healthcare professionals to review, analyze, and act
            upon laboratory findings efficiently.
        </span>

        <!-- Key Features -->
        <span class="font-semibold text-sm pb-1.5 tracking-wide">Key Features</span>
        <ul class="list-decimal pl-5">
            <li class="font-normal text-sm tracking-wide pb-1.5">Pulls blood test results from external lab APIs.</li>
            <li class="font-normal text-sm tracking-wide pb-1.5">Accepts results via custom POST APIs from external labs.</li>
            <li class="font-normal text-sm tracking-wide pb-1.5">Exposes endpoints for internal systems to retrieve test data.</li>
            <li class="font-normal text-sm tracking-wide pb-1.5">Supports custom integration workflows and flexible formatting.</li>
            <li class="font-normal text-sm tracking-wide pb-1.5">Ensures secure, structured data flow.</li>
        </ul>

        <!-- Base URL -->
        <span class="font-semibold text-sm pb-1.5 tracking-wide">Base URL</span>
        <span class="font-normal text-sm text-justify tracking-wide mb-3">This API is accessible via staging and
            production environments hosted on publicly reachable domains. No VPN is required to access these endpoints
            unless specifically requested by an external party for security reasons.</span>
        <ul class="list-disc pl-5 mb-2">
            <li class="font-normal text-sm tracking-wide pb-1.5">
                <span class="font-mono text-green-700 text-sm">https://mytotalhealth.com.my/staging</span>
            </li>
            <li class="font-normal text-sm tracking-wide pb-1.5">
                <span class="font-mono text-green-700 text-sm">https://mytotalhealth.com.my/production</span>
            </li>
            <li class="font-normal text-sm tracking-wide pb-1.5">
                <span class="font-mono text-green-700 text-sm">/api/v1</span>
                <span>— the versioned API prefix</span>
            </li>
        </ul>
        <span class="font-normal text-sm text-justify tracking-wide mb-3">📌Note: These URLs are accessible over the
            internet. Ensure your system can make outbound HTTPS requests
            to the above domains. If an external lab requires a VPN or static IP whitelisting, that can be arranged on
            request.</span>

        <!-- Authentication -->
        <span class="font-semibold text-sm pb-1.5 tracking-wide">Authentication</span>
        <span class="font-normal text-sm text-justify tracking-wide mb-5">Each laboratory is assigned exactly one unique
            username and password, which are used to log in and generate
            a secure access token. After logging in, a JWT token is issued and must be included in the <span
                class="font-mono text-green-700 text-sm">Authorization</span>
            header of all subsequent API requests, using the format: <span
                class="font-mono text-green-700 text-sm">Authorization: Bearer &lt;token&gt;</span>.
        </span>

        <!-- API Endpoints Documentation -->
        <div class="border-t pt-6 mt-6">
            <span class="font-semibold text-lg pb-3 tracking-wide block">API Endpoints Documentation</span>

            <!-- Authentication Endpoints -->
            <div class="mb-6">
                <span class="font-semibold text-md pb-2 tracking-wide block text-blue-800">Authentication Endpoints</span>

                <!-- Login Endpoint -->
                <div class="bg-green-50 border-l-4 border-green-500 p-4 mb-4">
                    <div class="flex items-center mb-2">
                        <span class="bg-green-500 text-white px-2 py-1 rounded text-xs font-bold mr-2">POST</span>
                        <span class="font-mono text-sm">/api/v1/login</span>
                    </div>
                    <span class="font-normal text-sm tracking-wide block mb-3">Authenticate lab user and return JWT token.</span>

                    <div class="mb-3">
                        <span class="font-semibold text-sm pb-1 tracking-wide block">Request Body:</span>
                        <pre class="bg-gray-100 p-3 rounded text-xs font-mono overflow-x-auto"><code>{
  "username": "LAB001user",     // Required: string, must exist in lab_credentials table
  "password": "password123"     // Required: string, minimum 8 characters
}</code></pre>
                    </div>

                    <div class="mb-3">
                        <span class="font-semibold text-sm pb-1 tracking-wide block">CURL Example:</span>
                        <pre class="bg-gray-800 text-green-300 p-3 rounded text-xs font-mono overflow-x-auto"><code>curl -X POST "https://mytotalhealth.com.my/production/api/v1/login" \
     -H "Content-Type: application/json" \
     -d '{
       "username": "LAB001user",
       "password": "password123"
     }'</code></pre>
                    </div>

                    <div class="mb-2">
                        <span class="font-semibold text-sm pb-1 tracking-wide block">Response Examples:</span>
                        <div class="mb-2">
                            <span class="bg-green-100 text-green-800 px-2 py-1 rounded text-xs font-bold mr-2">200 Success</span>
                            <pre class="bg-gray-100 p-2 rounded text-xs font-mono mt-1"><code>{
  "access_token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
  "token_type": "bearer",
  "expires_in": 3600
}</code></pre>
                        </div>
                        <div>
                            <span class="bg-red-100 text-red-800 px-2 py-1 rounded text-xs font-bold mr-2">401 Unauthorized</span>
                            <pre class="bg-gray-100 p-2 rounded text-xs font-mono mt-1"><code>{
  "error": "Unauthorized"
}</code></pre>
                        </div>
                    </div>
                </div>

                <!-- Logout Endpoint -->
                <div class="bg-green-50 border-l-4 border-green-500 p-4 mb-4">
                    <div class="flex items-center mb-2">
                        <span class="bg-green-500 text-white px-2 py-1 rounded text-xs font-bold mr-2">POST</span>
                        <span class="font-mono text-sm">/api/v1/logout</span>
                    </div>
                    <span class="font-normal text-sm tracking-wide block mb-3">Logout the authenticated lab user and invalidate the JWT token.</span>

                    <div class="mb-3">
                        <span class="font-semibold text-sm pb-1 tracking-wide block">Headers:</span>
                        <pre class="bg-gray-100 p-3 rounded text-xs font-mono overflow-x-auto"><code>Authorization: Bearer {your_jwt_token}</code></pre>
                    </div>

                    <div class="mb-2">
                        <span class="font-semibold text-sm pb-1 tracking-wide block">CURL Example:</span>
                        <pre class="bg-gray-800 text-green-300 p-3 rounded text-xs font-mono overflow-x-auto"><code>curl -X POST "https://mytotalhealth.com.my/production/api/v1/logout" \
     -H "Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9..."</code></pre>
                    </div>
                </div>
            </div>

            <!-- Result Management Endpoints -->
            <div class="mb-6">
                <span class="font-semibold text-md pb-2 tracking-wide block text-blue-800">Result Management Endpoints</span>

                <!-- Panel Results Endpoint -->
                <div class="bg-green-50 border-l-4 border-green-500 p-4 mb-4">
                    <div class="flex items-center mb-2">
                        <span class="bg-green-500 text-white px-2 py-1 rounded text-xs font-bold mr-2">POST</span>
                        <span class="font-mono text-sm">/api/v1/result/panel</span>
                    </div>
                    <span class="font-normal text-sm tracking-wide block mb-3">Process lab results from Innoquest system in HL7-like format.</span>

                    <div class="mb-3">
                        <span class="font-semibold text-sm pb-1 tracking-wide block">Key Required Fields:</span>
                        <div class="overflow-x-auto">
                            <table class="min-w-full border border-gray-200 text-xs">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="border border-gray-200 px-2 py-1 text-left font-semibold">Field</th>
                                        <th class="border border-gray-200 px-2 py-1 text-left font-semibold">Type</th>
                                        <th class="border border-gray-200 px-2 py-1 text-left font-semibold">Required</th>
                                        <th class="border border-gray-200 px-2 py-1 text-left font-semibold">Description</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td class="border border-gray-200 px-2 py-1 font-mono">SendingFacility</td>
                                        <td class="border border-gray-200 px-2 py-1">string</td>
                                        <td class="border border-gray-200 px-2 py-1 text-green-600">✓</td>
                                        <td class="border border-gray-200 px-2 py-1">Facility name</td>
                                    </tr>
                                    <tr>
                                        <td class="border border-gray-200 px-2 py-1 font-mono">MessageControlID</td>
                                        <td class="border border-gray-200 px-2 py-1">string</td>
                                        <td class="border border-gray-200 px-2 py-1 text-green-600">✓</td>
                                        <td class="border border-gray-200 px-2 py-1">Message control identifier</td>
                                    </tr>
                                    <tr>
                                        <td class="border border-gray-200 px-2 py-1 font-mono">patient.PatientLastName</td>
                                        <td class="border border-gray-200 px-2 py-1">string</td>
                                        <td class="border border-gray-200 px-2 py-1 text-green-600">✓</td>
                                        <td class="border border-gray-200 px-2 py-1">Patient full name</td>
                                    </tr>
                                    <tr>
                                        <td class="border border-gray-200 px-2 py-1 font-mono">patient.PatientDOB</td>
                                        <td class="border border-gray-200 px-2 py-1">string</td>
                                        <td class="border border-gray-200 px-2 py-1 text-green-600">✓</td>
                                        <td class="border border-gray-200 px-2 py-1">Date of birth (YYYYMMDD)</td>
                                    </tr>
                                    <tr>
                                        <td class="border border-gray-200 px-2 py-1 font-mono">patient.PatientGender</td>
                                        <td class="border border-gray-200 px-2 py-1">string</td>
                                        <td class="border border-gray-200 px-2 py-1 text-green-600">✓</td>
                                        <td class="border border-gray-200 px-2 py-1">Gender (M/F)</td>
                                    </tr>
                                    <tr>
                                        <td class="border border-gray-200 px-2 py-1 font-mono">Orders</td>
                                        <td class="border border-gray-200 px-2 py-1">array</td>
                                        <td class="border border-gray-200 px-2 py-1 text-green-600">✓</td>
                                        <td class="border border-gray-200 px-2 py-1">Array of orders (min: 1)</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="mb-2">
                        <span class="font-semibold text-sm pb-1 tracking-wide block">CURL Example:</span>
                        <pre class="bg-gray-800 text-green-300 p-3 rounded text-xs font-mono overflow-x-auto"><code>curl -X POST "https://mytotalhealth.com.my/production/api/v1/result/panel" \
     -H "Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9..." \
     -H "Content-Type: application/json" \
     -d '{
       "SendingFacility": "BIOMARK",
       "MessageControlID": "169126507",
       "patient": {
         "PatientLastName": "JOHN DOE",
         "PatientDOB": "19870521",
         "PatientGender": "M"
       },
       "Orders": [{
         "FillerOrderNumber": "25-8888861",
         "OrderingProvider": {
           "Code": "DOC001",
           "Name": "DR. SMITH"
         },
         "Observations": [{
           "FillerOrderNumber": "25-8888861",
           "ProcedureCode": "FBC",
           "ProcedureDescription": "FULL BLOOD COUNT",
           "OrderingProvider": {
             "Code": "DOC001",
             "Name": "DR. SMITH"
           },
           "ResultStatus": "F",
           "ServiceDateTime": "20250808",
           "ResultPriority": "R",
           "Results": [{
             "ID": "1",
             "Type": "NM",
             "Identifier": "718-7",
             "Text": "Haemoglobin",
             "CodingSystem": "LN",
             "Value": "130",
             "Status": "F",
             "ObservationDateTime": "202508081600"
           }]
         }]
       }]
     }'</code></pre>
                    </div>
                </div>

                <!-- Patient Results Endpoint -->
                <div class="bg-green-50 border-l-4 border-green-500 p-4 mb-4">
                    <div class="flex items-center mb-2">
                        <span class="bg-green-500 text-white px-2 py-1 rounded text-xs font-bold mr-2">POST</span>
                        <span class="font-mono text-sm">/api/v1/result/patient</span>
                    </div>
                    <span class="font-normal text-sm tracking-wide block mb-3">Submit lab results for a patient in standard format.</span>

                    <div class="mb-3">
                        <span class="font-semibold text-sm pb-1 tracking-wide block">Key Required Fields:</span>
                        <div class="overflow-x-auto">
                            <table class="min-w-full border border-gray-200 text-xs">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="border border-gray-200 px-2 py-1 text-left font-semibold">Field</th>
                                        <th class="border border-gray-200 px-2 py-1 text-left font-semibold">Type</th>
                                        <th class="border border-gray-200 px-2 py-1 text-left font-semibold">Required</th>
                                        <th class="border border-gray-200 px-2 py-1 text-left font-semibold">Description</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td class="border border-gray-200 px-2 py-1 font-mono">lab_no</td>
                                        <td class="border border-gray-200 px-2 py-1">string</td>
                                        <td class="border border-gray-200 px-2 py-1 text-green-600">✓</td>
                                        <td class="border border-gray-200 px-2 py-1">Laboratory number</td>
                                    </tr>
                                    <tr>
                                        <td class="border border-gray-200 px-2 py-1 font-mono">doctor</td>
                                        <td class="border border-gray-200 px-2 py-1">object</td>
                                        <td class="border border-gray-200 px-2 py-1 text-green-600">✓</td>
                                        <td class="border border-gray-200 px-2 py-1">Doctor information</td>
                                    </tr>
                                    <tr>
                                        <td class="border border-gray-200 px-2 py-1 font-mono">patient</td>
                                        <td class="border border-gray-200 px-2 py-1">object</td>
                                        <td class="border border-gray-200 px-2 py-1 text-green-600">✓</td>
                                        <td class="border border-gray-200 px-2 py-1">Patient information</td>
                                    </tr>
                                    <tr>
                                        <td class="border border-gray-200 px-2 py-1 font-mono">patient.icno</td>
                                        <td class="border border-gray-200 px-2 py-1">string</td>
                                        <td class="border border-gray-200 px-2 py-1 text-green-600">✓</td>
                                        <td class="border border-gray-200 px-2 py-1">Patient IC number</td>
                                    </tr>
                                    <tr>
                                        <td class="border border-gray-200 px-2 py-1 font-mono">results</td>
                                        <td class="border border-gray-200 px-2 py-1">object</td>
                                        <td class="border border-gray-200 px-2 py-1 text-green-600">✓</td>
                                        <td class="border border-gray-200 px-2 py-1">Test results by panel</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="mb-2">
                        <span class="font-semibold text-sm pb-1 tracking-wide block">CURL Example:</span>
                        <pre class="bg-gray-800 text-green-300 p-3 rounded text-xs font-mono overflow-x-auto"><code>curl -X POST "https://mytotalhealth.com.my/production/api/v1/result/patient" \
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
     }'</code></pre>
                    </div>
                </div>

                <!-- Get Result Endpoint -->
                <div class="bg-blue-50 border-l-4 border-blue-500 p-4 mb-4">
                    <div class="flex items-center mb-2">
                        <span class="bg-blue-500 text-white px-2 py-1 rounded text-xs font-bold mr-2">GET</span>
                        <span class="font-mono text-sm">/api/v1/result/{id}</span>
                    </div>
                    <span class="font-normal text-sm tracking-wide block mb-3">Get a specific test result with all associated data including patient, doctor, panel results and test items.</span>

                    <div class="mb-3">
                        <span class="font-semibold text-sm pb-1 tracking-wide block">URL Parameters:</span>
                        <ul class="list-disc pl-5">
                            <li class="font-normal text-sm tracking-wide"><span class="font-mono text-sm">id</span> (integer, required): Test result ID</li>
                        </ul>
                    </div>

                    <div class="mb-2">
                        <span class="font-semibold text-sm pb-1 tracking-wide block">CURL Example:</span>
                        <pre class="bg-gray-800 text-green-300 p-3 rounded text-xs font-mono overflow-x-auto"><code>curl -X GET "https://mytotalhealth.com.my/production/api/v1/result/123" \
     -H "Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9..."</code></pre>
                    </div>
                </div>

                <!-- Test Panel Endpoint -->
                <div class="bg-green-50 border-l-4 border-green-500 p-4 mb-4">
                    <div class="flex items-center mb-2">
                        <span class="bg-green-500 text-white px-2 py-1 rounded text-xs font-bold mr-2">POST</span>
                        <span class="font-mono text-sm">/api/v1/testPanel</span>
                    </div>
                    <span class="font-normal text-sm tracking-wide block mb-3">Test endpoint that logs and returns the request data.</span>

                    <div class="mb-2">
                        <span class="font-semibold text-sm pb-1 tracking-wide block">CURL Example:</span>
                        <pre class="bg-gray-800 text-green-300 p-3 rounded text-xs font-mono overflow-x-auto"><code>curl -X POST "https://mytotalhealth.com.my/production/api/v1/testPanel" \
     -H "Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9..." \
     -H "Content-Type: application/json" \
     -d '{"test": "data"}'</code></pre>
                    </div>
                </div>
            </div>

            <!-- Error Responses -->
            <div class="mb-6">
                <span class="font-semibold text-md pb-2 tracking-wide block text-red-800">Error Responses</span>
                <div class="bg-red-50 border-l-4 border-red-500 p-4">
                    <span class="font-semibold text-sm pb-1 tracking-wide block">Common Error Status Codes:</span>
                    <div class="overflow-x-auto">
                        <table class="min-w-full border border-gray-200 text-xs">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="border border-gray-200 px-2 py-1 text-left font-semibold">Status Code</th>
                                    <th class="border border-gray-200 px-2 py-1 text-left font-semibold">Description</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td class="border border-gray-200 px-2 py-1"><span class="bg-red-100 text-red-800 px-2 py-1 rounded text-xs font-bold">400</span></td>
                                    <td class="border border-gray-200 px-2 py-1">Bad Request - Malformed request or invalid data</td>
                                </tr>
                                <tr>
                                    <td class="border border-gray-200 px-2 py-1"><span class="bg-red-100 text-red-800 px-2 py-1 rounded text-xs font-bold">401</span></td>
                                    <td class="border border-gray-200 px-2 py-1">Unauthorized - Invalid or missing authentication token</td>
                                </tr>
                                <tr>
                                    <td class="border border-gray-200 px-2 py-1"><span class="bg-red-100 text-red-800 px-2 py-1 rounded text-xs font-bold">404</span></td>
                                    <td class="border border-gray-200 px-2 py-1">Not Found - Requested resource not found</td>
                                </tr>
                                <tr>
                                    <td class="border border-gray-200 px-2 py-1"><span class="bg-yellow-100 text-yellow-800 px-2 py-1 rounded text-xs font-bold">422</span></td>
                                    <td class="border border-gray-200 px-2 py-1">Unprocessable Entity - Validation errors in request data</td>
                                </tr>
                                <tr>
                                    <td class="border border-gray-200 px-2 py-1"><span class="bg-red-100 text-red-800 px-2 py-1 rounded text-xs font-bold">500</span></td>
                                    <td class="border border-gray-200 px-2 py-1">Internal Server Error - Server-side errors</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Data Formats -->
            <div class="mb-6">
                <span class="font-semibold text-md pb-2 tracking-wide block text-purple-800">Data Formats</span>
                <div class="bg-purple-50 border-l-4 border-purple-500 p-4">
                    <div class="mb-3">
                        <span class="font-semibold text-sm pb-1 tracking-wide block">Date/Time Format:</span>
                        <ul class="list-disc pl-5">
                            <li class="font-normal text-sm tracking-wide pb-1">
                                <strong>Date fields:</strong> <span class="font-mono text-sm">YYYYMMDD</span> (e.g., "20250808")
                            </li>
                            <li class="font-normal text-sm tracking-wide pb-1">
                                <strong>DateTime fields:</strong> <span class="font-mono text-sm">YYYY-MM-DD HH:mm:ss</span> (e.g., "2025-08-08 14:30:00")
                            </li>
                            <li class="font-normal text-sm tracking-wide">
                                <strong>DateTime responses:</strong> <span class="font-mono text-sm">ISO 8601</span> format with timezone (e.g., "2025-08-08T14:30:00.000000Z")
                            </li>
                        </ul>
                    </div>
                    <div>
                        <span class="font-semibold text-sm pb-1 tracking-wide block">Field Requirements:</span>
                        <ul class="list-disc pl-5">
                            <li class="font-normal text-sm tracking-wide pb-1"><strong>Required:</strong> Field must be present and not empty</li>
                            <li class="font-normal text-sm tracking-wide pb-1"><strong>Nullable:</strong> Field can be omitted or set to null</li>
                            <li class="font-normal text-sm tracking-wide"><strong>Optional:</strong> Field can be omitted but if present must have valid value</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>