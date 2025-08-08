<x-app-layout>
    <div class="flex flex-col w-full pr-32">
        <div class="flex justify-start items-end mb-4">
            <img src="{{ asset('logo.svg') }}" class="w-8 h-8 opacity-80 mr-2" />
            <span class="font-semibold text-lg tracking-wide">BloodStream v1 - API Documentation</span>
        </div>

        <span class="font-normal text-sm text-justify tracking-wide mb-6">
            BloodStream is a centralized middleware system designed to act as a secure and customizable bridge
            between your organization and external laboratories. Its core function is to collect, normalize, and
            centralize patient blood test results, enabling healthcare professionals to review, analyze, and act
            upon laboratory findings efficiently.
        </span>

        <!-- Base URL Section -->
        <div class="bg-gray-50 border-l-4 border-gray-500 p-4 mb-6">
            <span class="font-semibold text-md pb-2 tracking-wide block text-gray-800">Base URL</span>
            <span class="font-normal text-sm text-justify tracking-wide block mb-3">
                This API is accessible via staging and production environments hosted on publicly reachable domains.
                No VPN is required to access these endpoints unless specifically requested by an external party for
                security reasons.
            </span>
            <div class="mb-3">
                <span class="font-semibold text-sm pb-1 tracking-wide block">Available Environments:</span>
                <ul class="list-disc pl-5 mb-3">
                    <li class="font-normal text-sm tracking-wide pb-1">
                        <span
                            class="font-mono text-green-700 text-sm bg-green-50 px-2 py-1 rounded">https://mytotalhealth.com.my/staging</span>
                        <span class="ml-2 text-gray-600">— Development environment</span>
                    </li>
                    <li class="font-normal text-sm tracking-wide pb-1">
                        <span
                            class="font-mono text-green-700 text-sm bg-green-50 px-2 py-1 rounded">https://mytotalhealth.com.my/production</span>
                        <span class="ml-2 text-gray-600">— Production environment</span>
                    </li>
                    <li class="font-normal text-sm tracking-wide">
                        <span class="font-mono text-green-700 text-sm bg-green-50 px-2 py-1 rounded">/api/v1</span>
                        <span class="ml-2 text-gray-600">— Versioned API prefix</span>
                    </li>
                </ul>
                <div class="bg-amber-50 border-l-4 border-amber-400 p-3 rounded">
                    <span class="font-normal text-sm text-amber-800">
                        📌 <strong>Note:</strong> These URLs are accessible over the internet. Ensure your system can
                        make outbound HTTPS requests
                        to the above domains. An arrangement can be made if an external lab requires a VPN or static IP
                        whitelisting upon request.
                    </span>
                </div>
            </div>
        </div>

        <!-- Authentication Section -->
        <div class="bg-blue-50 border-l-4 border-blue-500 p-4 mb-6">
            <span class="font-semibold text-md pb-2 tracking-wide block text-blue-800">Authentication</span>
            <span class="font-normal text-sm text-justify tracking-wide block mb-3">
                Each laboratory is assigned exactly one unique username and password, which are used to log in and
                generate
                a secure access token. After logging in, a JWT token is issued and must be included in all subsequent
                API requests.
            </span>
            <div class="mb-3">
                <span class="font-semibold text-sm pb-1 tracking-wide block">Authorization Header Format:</span>
                <pre class="bg-gray-100 p-3 rounded text-xs font-mono overflow-x-auto"><code>Authorization: Bearer {your_jwt_token}</code></pre>
            </div>
            <div class="bg-amber-50 border-l-4 border-amber-400 p-3 rounded">
                <span class="font-normal text-sm text-amber-800">
                    <strong>Security:</strong> JWT tokens expire after 30 days. Store tokens securely and implement
                    proper token refresh mechanisms.
                </span>
            </div>
        </div>

        <!-- API Endpoints Documentation -->
        <div class="mt-1.5">
            <!-- Authentication Endpoints -->
            <div class="mb-6">
                <span class="font-semibold text-md pb-2 tracking-wide block text-blue-800">Authentication
                    Endpoints</span>

                <!-- Login Endpoint -->
                <div class="bg-green-50 border-l-4 border-green-500 p-4 mb-4">
                    <div class="flex items-center mb-2">
                        <span class="bg-green-500 text-white px-2 py-1 rounded text-xs font-bold mr-2">POST</span>
                        <span class="font-mono text-sm">/api/v1/login</span>
                    </div>
                    <span class="font-normal text-sm tracking-wide block mb-3">Authenticate lab user and return JWT
                        token.</span>

                    <div class="mb-3">
                        <span class="font-semibold text-sm pb-1 tracking-wide block">Request Body:</span>
                        <pre class="bg-gray-100 p-3 rounded text-xs font-mono overflow-x-auto"><code>{
  "username": "LAB001user",     // Required: string
  "password": "password123"     // Required: string
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
                            <span class="bg-green-100 text-green-800 px-2 py-1 rounded text-xs font-bold mr-2">200
                                Success</span>
                            <pre class="bg-gray-100 p-2 rounded text-xs font-mono mt-1"><code>{
  "access_token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
  "token_type": "bearer",
  "expires_in": 3600
}</code></pre>
                        </div>
                        <div>
                            <span class="bg-red-100 text-red-800 px-2 py-1 rounded text-xs font-bold mr-2">401
                                Unauthorized</span>
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
                    <span class="font-normal text-sm tracking-wide block mb-3">Logout the authenticated lab user and
                        invalidate the JWT token.</span>

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
                <span class="font-semibold text-md pb-2 tracking-wide block text-blue-800">Result Management
                    Endpoints</span>

                @if ($lab_id == 1 || $lab_id == 2)
                    <!-- Panel Results Endpoint -->
                    <div class="bg-green-50 border-l-4 border-green-500 p-4 mb-4">
                        <div class="flex items-center mb-2">
                            <span class="bg-green-500 text-white px-2 py-1 rounded text-xs font-bold mr-2">POST</span>
                            <span class="font-mono text-sm">/api/v1/result/panel</span>
                        </div>
                        <span class="font-normal text-sm tracking-wide block mb-3">Process lab results from Innoquest
                            system
                            in HL7-like format.</span>

                        <div class="mb-3">
                            <span class="font-semibold text-sm pb-1 tracking-wide block">IQMY Pathology Results JSON
                                Specification:</span>
                            <div class="overflow-x-auto">
                                <table class="min-w-full border border-gray-200 text-xs">
                                    <thead class="bg-gray-100">
                                        <tr>
                                            <th class="border border-gray-200 px-2 py-1 text-left font-semibold">Field
                                            </th>
                                            <th class="border border-gray-200 px-2 py-1 text-left font-semibold">Type
                                            </th>
                                            <th class="border border-gray-200 px-2 py-1 text-left font-semibold">
                                                Expected</th>
                                            <th class="border border-gray-200 px-2 py-1 text-left font-semibold">
                                                Description</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-gray-50">
                                        <!-- Root Level -->
                                        <tr class="bg-blue-50">
                                            <td colspan="4"
                                                class="border border-gray-200 px-2 py-1 font-semibold text-blue-800">
                                                Root Level</td>
                                        </tr>
                                        <tr>
                                            <td class="border border-gray-200 px-2 py-1 font-mono">SendingFacility</td>
                                            <td class="border border-gray-200 px-2 py-1">String</td>
                                            <td class="border border-gray-200 px-2 py-1 text-green-600">Always Expected
                                            </td>
                                            <td class="border border-gray-200 px-2 py-1">Identifier for the sending
                                                facility</td>
                                        </tr>
                                        <tr>
                                            <td class="border border-gray-200 px-2 py-1 font-mono">MessageControlID</td>
                                            <td class="border border-gray-200 px-2 py-1">String</td>
                                            <td class="border border-gray-200 px-2 py-1 text-green-600">Always Expected
                                            </td>
                                            <td class="border border-gray-200 px-2 py-1">Unique message identifier</td>
                                        </tr>

                                        <!-- Patient Information -->
                                        <tr class="bg-green-50">
                                            <td colspan="4"
                                                class="border border-gray-200 px-2 py-1 font-semibold text-green-800">
                                                Patient Information</td>
                                        </tr>
                                        <tr>
                                            <td class="border border-gray-200 px-2 py-1 font-mono">PatientID</td>
                                            <td class="border border-gray-200 px-2 py-1">String</td>
                                            <td class="border border-gray-200 px-2 py-1 text-orange-600">Optional</td>
                                            <td class="border border-gray-200 px-2 py-1">Unique identifier for the
                                                patient (MRN)</td>
                                        </tr>
                                        <tr>
                                            <td class="border border-gray-200 px-2 py-1 font-mono">AlternatePatientID
                                            </td>
                                            <td class="border border-gray-200 px-2 py-1">String</td>
                                            <td class="border border-gray-200 px-2 py-1 text-orange-600">Optional</td>
                                            <td class="border border-gray-200 px-2 py-1">Alternate patient identifier
                                                (NRIC)</td>
                                        </tr>
                                        <tr>
                                            <td class="border border-gray-200 px-2 py-1 font-mono">PatientLastName</td>
                                            <td class="border border-gray-200 px-2 py-1">String</td>
                                            <td class="border border-gray-200 px-2 py-1 text-green-600">Always Expected
                                            </td>
                                            <td class="border border-gray-200 px-2 py-1">Full Name of Patient will be
                                                in this field</td>
                                        </tr>
                                        <tr>
                                            <td class="border border-gray-200 px-2 py-1 font-mono">PatientDOB</td>
                                            <td class="border border-gray-200 px-2 py-1">String</td>
                                            <td class="border border-gray-200 px-2 py-1 text-green-600">Always Expected
                                            </td>
                                            <td class="border border-gray-200 px-2 py-1">Date of birth of the patient
                                                (YYYYMMDD)</td>
                                        </tr>
                                        <tr>
                                            <td class="border border-gray-200 px-2 py-1 font-mono">PatientGender</td>
                                            <td class="border border-gray-200 px-2 py-1">String</td>
                                            <td class="border border-gray-200 px-2 py-1 text-green-600">Always Expected
                                            </td>
                                            <td class="border border-gray-200 px-2 py-1">Gender of the patient
                                                ('M','F')</td>
                                        </tr>
                                        <tr>
                                            <td class="border border-gray-200 px-2 py-1 font-mono">PatientAddress</td>
                                            <td class="border border-gray-200 px-2 py-1">String</td>
                                            <td class="border border-gray-200 px-2 py-1 text-orange-600">Optional</td>
                                            <td class="border border-gray-200 px-2 py-1">Address of the patient - not
                                                always stored</td>
                                        </tr>

                                        <!-- Orders -->
                                        <tr class="bg-purple-50">
                                            <td colspan="4"
                                                class="border border-gray-200 px-2 py-1 font-semibold text-purple-800">
                                                Orders</td>
                                        </tr>
                                        <tr>
                                            <td class="border border-gray-200 px-2 py-1 font-mono">FillerOrderNumber
                                            </td>
                                            <td class="border border-gray-200 px-2 py-1">String</td>
                                            <td class="border border-gray-200 px-2 py-1 text-green-600">Always Expected
                                            </td>
                                            <td class="border border-gray-200 px-2 py-1">IQMY Request Number</td>
                                        </tr>
                                        <tr>
                                            <td class="border border-gray-200 px-2 py-1 font-mono">
                                                OrderingProvider.Code</td>
                                            <td class="border border-gray-200 px-2 py-1">String</td>
                                            <td class="border border-gray-200 px-2 py-1 text-green-600">Always Expected
                                            </td>
                                            <td class="border border-gray-200 px-2 py-1">IQMY Doctor Code</td>
                                        </tr>
                                        <tr>
                                            <td class="border border-gray-200 px-2 py-1 font-mono">
                                                OrderingProvider.Name</td>
                                            <td class="border border-gray-200 px-2 py-1">String</td>
                                            <td class="border border-gray-200 px-2 py-1 text-green-600">Always Expected
                                            </td>
                                            <td class="border border-gray-200 px-2 py-1">Doctor Name</td>
                                        </tr>

                                        <!-- Observations -->
                                        <tr class="bg-yellow-50">
                                            <td colspan="4"
                                                class="border border-gray-200 px-2 py-1 font-semibold text-yellow-800">
                                                Observations</td>
                                        </tr>
                                        <tr>
                                            <td class="border border-gray-200 px-2 py-1 font-mono">ProcedureCode</td>
                                            <td class="border border-gray-200 px-2 py-1">String</td>
                                            <td class="border border-gray-200 px-2 py-1 text-green-600">Always Expected
                                            </td>
                                            <td class="border border-gray-200 px-2 py-1">Testing Panel Code</td>
                                        </tr>
                                        <tr>
                                            <td class="border border-gray-200 px-2 py-1 font-mono">ProcedureDescription
                                            </td>
                                            <td class="border border-gray-200 px-2 py-1">String</td>
                                            <td class="border border-gray-200 px-2 py-1 text-green-600">Always Expected
                                            </td>
                                            <td class="border border-gray-200 px-2 py-1">Panel Description</td>
                                        </tr>
                                        <tr>
                                            <td class="border border-gray-200 px-2 py-1 font-mono">ResultStatus</td>
                                            <td class="border border-gray-200 px-2 py-1">String</td>
                                            <td class="border border-gray-200 px-2 py-1 text-green-600">Always Expected
                                            </td>
                                            <td class="border border-gray-200 px-2 py-1">Status of the result</td>
                                        </tr>
                                        <tr>
                                            <td class="border border-gray-200 px-2 py-1 font-mono">ServiceDateTime</td>
                                            <td class="border border-gray-200 px-2 py-1">String</td>
                                            <td class="border border-gray-200 px-2 py-1 text-green-600">Always Expected
                                            </td>
                                            <td class="border border-gray-200 px-2 py-1">Date and time of service</td>
                                        </tr>

                                        <!-- Results -->
                                        <tr class="bg-red-50">
                                            <td colspan="4"
                                                class="border border-gray-200 px-2 py-1 font-semibold text-red-800">
                                                Results</td>
                                        </tr>
                                        <tr>
                                            <td class="border border-gray-200 px-2 py-1 font-mono">Results.ID</td>
                                            <td class="border border-gray-200 px-2 py-1">String</td>
                                            <td class="border border-gray-200 px-2 py-1 text-green-600">Always Expected
                                            </td>
                                            <td class="border border-gray-200 px-2 py-1">Ordinal ID within this results
                                                message</td>
                                        </tr>
                                        <tr>
                                            <td class="border border-gray-200 px-2 py-1 font-mono">Results.Type</td>
                                            <td class="border border-gray-200 px-2 py-1">String</td>
                                            <td class="border border-gray-200 px-2 py-1 text-green-600">Always Expected
                                            </td>
                                            <td class="border border-gray-200 px-2 py-1">Type of result (numerical,
                                                text)</td>
                                        </tr>
                                        <tr>
                                            <td class="border border-gray-200 px-2 py-1 font-mono">Results.Identifier
                                            </td>
                                            <td class="border border-gray-200 px-2 py-1">String</td>
                                            <td class="border border-gray-200 px-2 py-1 text-green-600">Always Expected
                                            </td>
                                            <td class="border border-gray-200 px-2 py-1">Unique identifier for the
                                                test/analyte (LOINC)</td>
                                        </tr>
                                        <tr>
                                            <td class="border border-gray-200 px-2 py-1 font-mono">Results.Text</td>
                                            <td class="border border-gray-200 px-2 py-1">String</td>
                                            <td class="border border-gray-200 px-2 py-1 text-green-600">Always Expected
                                            </td>
                                            <td class="border border-gray-200 px-2 py-1">Test description</td>
                                        </tr>
                                        <tr>
                                            <td class="border border-gray-200 px-2 py-1 font-mono">Results.Value</td>
                                            <td class="border border-gray-200 px-2 py-1">String</td>
                                            <td class="border border-gray-200 px-2 py-1 text-green-600">Always Expected
                                            </td>
                                            <td class="border border-gray-200 px-2 py-1">Result value</td>
                                        </tr>
                                        <tr>
                                            <td class="border border-gray-200 px-2 py-1 font-mono">Results.Units</td>
                                            <td class="border border-gray-200 px-2 py-1">String</td>
                                            <td class="border border-gray-200 px-2 py-1 text-green-600">Always Expected
                                            </td>
                                            <td class="border border-gray-200 px-2 py-1">Units of measurement</td>
                                        </tr>
                                        <tr>
                                            <td class="border border-gray-200 px-2 py-1 font-mono">
                                                Results.ReferenceRange</td>
                                            <td class="border border-gray-200 px-2 py-1">String</td>
                                            <td class="border border-gray-200 px-2 py-1 text-green-600">Always Expected
                                            </td>
                                            <td class="border border-gray-200 px-2 py-1">Normal reference range</td>
                                        </tr>
                                        <tr>
                                            <td class="border border-gray-200 px-2 py-1 font-mono">Results.Flags</td>
                                            <td class="border border-gray-200 px-2 py-1">String</td>
                                            <td class="border border-gray-200 px-2 py-1 text-green-600">Always Expected
                                            </td>
                                            <td class="border border-gray-200 px-2 py-1">Flags indicating abnormalities
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="border border-gray-200 px-2 py-1 font-mono">Results.Status</td>
                                            <td class="border border-gray-200 px-2 py-1">String</td>
                                            <td class="border border-gray-200 px-2 py-1 text-green-600">Always Expected
                                            </td>
                                            <td class="border border-gray-200 px-2 py-1">Result status</td>
                                        </tr>
                                        <tr>
                                            <td class="border border-gray-200 px-2 py-1 font-mono">
                                                Results.ObservationDateTime</td>
                                            <td class="border border-gray-200 px-2 py-1">String</td>
                                            <td class="border border-gray-200 px-2 py-1 text-green-600">Always Expected
                                            </td>
                                            <td class="border border-gray-200 px-2 py-1">Date and time of test
                                                (YYYYMMDDhhmm)</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="mb-3">
                            <span class="font-semibold text-sm pb-1 tracking-wide block">Headers:</span>
                            <pre class="bg-gray-100 p-3 rounded text-xs font-mono overflow-x-auto"><code>Authorization: Bearer {your_jwt_token}
Content-Type: application/json</code></pre>
                        </div>

                        <div class="mb-3">
                            <span class="font-semibold text-sm pb-1 tracking-wide block">Request Body Example:</span>
                            <pre class="bg-gray-100 p-3 rounded text-xs font-mono overflow-x-auto"><code>{
  "SendingFacility": "BIOMARK",
  "MessageControlID": "169126507",
  "patient": {
    "PatientID": "",
    "PatientExternalID": "",
    "AlternatePatientID": "010325055234",
    "PatientLastName": "JOHN DOE",
    "PatientFirstName": "",
    "PatientDOB": "19870521",
    "PatientGender": "M",
    "PatientAddress": "KUALA LUMPUR"
  },
  "Orders": [{
    "PlacerOrderNumber": "INN12345",
    "FillerOrderNumber": "25-8888861",
    "OrderingProvider": {
      "Code": "DOC001",
      "Name": "DR. SMITH (CLINIC)"
    },
    "Observations": [{
      "FillerOrderNumber": "25-8888861",
      "ProcedureCode": "FBC",
      "ProcedureDescription": "FULL BLOOD COUNT",
      "PackageCode": "HEALTH001",
      "RequestedDateTime": "20250808",
      "SpecimenDateTime": "202508081000",
      "ClinicalInformation": "Routine checkup",
      "OrderingProvider": {
        "Code": "DOC001",
        "Name": "DR. SMITH (CLINIC)"
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
        "Units": "g/L",
        "ReferenceRange": "120-150",
        "Flags": "N",
        "Status": "F",
        "ObservationDateTime": "202508081600"
      }]
    }]
  }],
  "EncodedBase64pdf": "JVBERi0xLjQKMSAwIG9iago8PAo..."
}</code></pre>
                        </div>

                        <div class="mb-3">
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

                        <div class="mb-2">
                            <span class="font-semibold text-sm pb-1 tracking-wide block">Response Examples:</span>
                            <div class="mb-2">
                                <span class="bg-green-100 text-green-800 px-2 py-1 rounded text-xs font-bold mr-2">200
                                    Success</span>
                                <pre class="bg-gray-100 p-2 rounded text-xs font-mono mt-1"><code>{
  "success": true,
  "message": "Panel results processed successfully",
  "data": {
    "test_result_id": 123
  }
}</code></pre>
                            </div>
                            <div class="mb-2">
                                <span
                                    class="bg-yellow-100 text-yellow-800 px-2 py-1 rounded text-xs font-bold mr-2">422
                                    Validation Error</span>
                                <pre class="bg-gray-100 p-2 rounded text-xs font-mono mt-1"><code>{
  "message": "The given data was invalid.",
  "errors": {
    "patient.PatientLastName": [
      "The patient.PatientLastName field is required."
    ],
    "Orders": [
      "The Orders field must have at least 1 items."
    ]
  }
}</code></pre>
                            </div>
                            <div>
                                <span class="bg-red-100 text-red-800 px-2 py-1 rounded text-xs font-bold mr-2">500
                                    Server
                                    Error</span>
                                <pre class="bg-gray-100 p-2 rounded text-xs font-mono mt-1"><code>{
  "success": false,
  "message": "Failed to process panel results",
  "error": "Internal server error"
}</code></pre>
                            </div>
                        </div>
                    </div>
                @endif

                @if ($lab_id == 1 || $lab_id == 3)
                    <!-- Patient Results Endpoint -->
                    <div class="bg-green-50 border-l-4 border-green-500 p-4 mb-4">
                        <div class="flex items-center mb-2">
                            <span class="bg-green-500 text-white px-2 py-1 rounded text-xs font-bold mr-2">POST</span>
                            <span class="font-mono text-sm">/api/v1/result/patient</span>
                        </div>
                        <span class="font-normal text-sm tracking-wide block mb-3">Submit lab results for a patient in
                            standard format.</span>

                        <div class="mb-3">
                            <span class="font-semibold text-sm pb-1 tracking-wide block">Key Required Fields:</span>
                            <div class="overflow-x-auto">
                                <table class="min-w-full border border-gray-200 text-xs">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="border border-gray-200 px-2 py-1 text-left font-semibold">Field
                                            </th>
                                            <th class="border border-gray-200 px-2 py-1 text-left font-semibold">Type
                                            </th>
                                            <th class="border border-gray-200 px-2 py-1 text-left font-semibold">
                                                Required
                                            </th>
                                            <th class="border border-gray-200 px-2 py-1 text-left font-semibold">
                                                Description</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td class="border border-gray-200 px-2 py-1 font-mono">lab_no</td>
                                            <td class="border border-gray-200 px-2 py-3">string</td>
                                            <td class="border border-gray-200 px-2 py-1 text-green-600">✓</td>
                                            <td class="border border-gray-200 px-2 py-3">Laboratory number</td>
                                        </tr>
                                        <tr>
                                            <td class="border border-gray-200 px-2 py-1 font-mono">doctor</td>
                                            <td class="border border-gray-200 px-2 py-3">object</td>
                                            <td class="border border-gray-200 px-2 py-1 text-green-600">✓</td>
                                            <td class="border border-gray-200 px-2 py-3">Doctor information</td>
                                        </tr>
                                        <tr>
                                            <td class="border border-gray-200 px-2 py-1 font-mono">patient</td>
                                            <td class="border border-gray-200 px-2 py-3">object</td>
                                            <td class="border border-gray-200 px-2 py-1 text-green-600">✓</td>
                                            <td class="border border-gray-200 px-2 py-3">Patient information</td>
                                        </tr>
                                        <tr>
                                            <td class="border border-gray-200 px-2 py-1 font-mono">patient.icno</td>
                                            <td class="border border-gray-200 px-2 py-3">string</td>
                                            <td class="border border-gray-200 px-2 py-1 text-green-600">✓</td>
                                            <td class="border border-gray-200 px-2 py-3">Patient IC number</td>
                                        </tr>
                                        <tr>
                                            <td class="border border-gray-200 px-2 py-1 font-mono">results</td>
                                            <td class="border border-gray-200 px-2 py-3">object</td>
                                            <td class="border border-gray-200 px-2 py-1 text-green-600">✓</td>
                                            <td class="border border-gray-200 px-2 py-3">Test results by panel</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="mb-3">
                            <span class="font-semibold text-sm pb-1 tracking-wide block">Headers:</span>
                            <pre class="bg-gray-100 p-3 rounded text-xs font-mono overflow-x-auto"><code>Authorization: Bearer {your_jwt_token}
Content-Type: application/json</code></pre>
                        </div>

                        <div class="mb-3">
                            <span class="font-semibold text-sm pb-1 tracking-wide block">Request Body Example:</span>
                            <pre class="bg-gray-100 p-3 rounded text-xs font-mono overflow-x-auto"><code>{
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
        "unit": "μmol/L",
        "ref_range": "<25.7",
        "test_note": null,
        "report_sequence": 17,
        "decimal_point": 1
      }]
    }
  }
}</code></pre>
                        </div>

                        <div class="mb-3">
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

                        <div class="mb-2">
                            <span class="font-semibold text-sm pb-1 tracking-wide block">Response Examples:</span>
                            <div class="mb-2">
                                <span class="bg-green-100 text-green-800 px-2 py-1 rounded text-xs font-bold mr-2">200
                                    Success</span>
                                <pre class="bg-gray-100 p-2 rounded text-xs font-mono mt-1"><code>{
  "success": true,
  "message": "Lab results processed successfully",
  "data": {
    "test_result_id": 456
  }
}</code></pre>
                            </div>
                            <div class="mb-2">
                                <span
                                    class="bg-yellow-100 text-yellow-800 px-2 py-1 rounded text-xs font-bold mr-2">422
                                    Validation Error</span>
                                <pre class="bg-gray-100 p-2 rounded text-xs font-mono mt-1"><code>{
  "message": "The given data was invalid.",
  "errors": {
    "patient.icno": [
      "The patient.icno field is required."
    ],
    "lab_no": [
      "The lab_no field is required."
    ],
    "results": [
      "The results field is required."
    ]
  }
}</code></pre>
                            </div>
                            <div>
                                <span class="bg-red-100 text-red-800 px-2 py-1 rounded text-xs font-bold mr-2">500
                                    Server
                                    Error</span>
                                <pre class="bg-gray-100 p-2 rounded text-xs font-mono mt-1"><code>{
  "success": false,
  "message": "Failed to process lab results",
  "error": "Internal server error"
}</code></pre>
                            </div>
                        </div>
                    </div>
                @endif

                @if ($lab_id != 1 && $lab_id != 2 && $lab_id != 3)
                    <!-- No Access Message -->
                    <div class="bg-gray-50 border-l-4 border-gray-400 p-4 mb-4">
                        <span class="font-semibold text-sm pb-2 tracking-wide block text-gray-700">🚫 Access
                            Restricted</span>
                        <span class="font-normal text-sm text-justify tracking-wide block text-gray-600">
                            Your lab (ID: {{ $lab_id ?? 'Unknown' }}) does not have access to result management
                            endpoints.
                            Contact your system administrator if you need access to submit lab results.
                        </span>
                    </div>
                @endif

                <!-- Get Result Endpoint -->
                <div class="bg-blue-50 border-l-4 border-blue-500 p-4 mb-4">
                    <div class="flex items-center mb-2">
                        <span class="bg-blue-500 text-white px-2 py-1 rounded text-xs font-bold mr-2">GET</span>
                        <span class="font-mono text-sm">/api/v1/result/{id}</span>
                    </div>
                    <span class="font-normal text-sm tracking-wide block mb-3">Get a specific test result with all
                        associated data including patient, doctor, panel results and test items.</span>

                    <div class="mb-3">
                        <span class="font-semibold text-sm pb-1 tracking-wide block">URL Parameters:</span>
                        <ul class="list-disc pl-5">
                            <li class="font-normal text-sm tracking-wide"><span class="font-mono text-sm">id</span>
                                (integer, required): Test result ID</li>
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
                    <span class="font-normal text-sm tracking-wide block mb-3">Test endpoint that logs and returns the
                        request data.</span>

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
                    <span class="font-semibold text-sm pb-1 tracking-wide block">All error responses follow this
                        standardized format:</span>
                    <pre class="bg-gray-100 p-2 rounded text-xs font-mono mb-3"><code>{
  "success": false,
  "message": "User-friendly error description",
  "error": "Error category or technical details"
}</code></pre>
                    <span class="font-semibold text-sm pb-1 tracking-wide block">Common Error Status Codes:</span>
                    <div class="overflow-x-auto">
                        <table class="min-w-full border border-gray-200 text-xs">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="border border-gray-200 px-2 py-1 text-left font-semibold">Status Code
                                    </th>
                                    <th class="border border-gray-200 px-2 py-1 text-left font-semibold">Description
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td class="border border-gray-200 px-2 py-3"><span
                                            class="bg-red-100 text-red-800 px-2 py-1 rounded text-xs font-bold">400</span>
                                    </td>
                                    <td class="border border-gray-200 px-2 py-3">Bad Request - Malformed request or
                                        invalid data</td>
                                </tr>
                                <tr>
                                    <td class="border border-gray-200 px-2 py-3"><span
                                            class="bg-red-100 text-red-800 px-2 py-1 rounded text-xs font-bold">401</span>
                                    </td>
                                    <td class="border border-gray-200 px-2 py-3">Unauthorized - Invalid or missing
                                        authentication token</td>
                                </tr>
                                <tr>
                                    <td class="border border-gray-200 px-2 py-3"><span
                                            class="bg-red-100 text-red-800 px-2 py-1 rounded text-xs font-bold">404</span>
                                    </td>
                                    <td class="border border-gray-200 px-2 py-3">Not Found - Requested resource not
                                        found</td>
                                </tr>
                                <tr>
                                    <td class="border border-gray-200 px-2 py-3"><span
                                            class="bg-yellow-100 text-yellow-800 px-2 py-1 rounded text-xs font-bold">422</span>
                                    </td>
                                    <td class="border border-gray-200 px-2 py-3">Unprocessable Entity - Validation
                                        errors in request data</td>
                                </tr>
                                <tr>
                                    <td class="border border-gray-200 px-2 py-3"><span
                                            class="bg-red-100 text-red-800 px-2 py-1 rounded text-xs font-bold">500</span>
                                    </td>
                                    <td class="border border-gray-200 px-2 py-3">Internal Server Error - Server-side
                                        errors</td>
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
                                <strong>Date fields:</strong> <span class="font-mono text-sm">YYYYMMDD</span> (e.g.,
                                "20250808")
                            </li>
                            <li class="font-normal text-sm tracking-wide pb-1">
                                <strong>DateTime fields:</strong> <span class="font-mono text-sm">YYYY-MM-DD
                                    HH:mm:ss</span> (e.g., "2025-08-08 14:30:00")
                            </li>
                            <li class="font-normal text-sm tracking-wide">
                                <strong>DateTime responses:</strong> <span class="font-mono text-sm">ISO 8601</span>
                                format with timezone (e.g., "2025-08-08T14:30:00.000000Z")
                            </li>
                        </ul>
                    </div>
                    <div>
                        <span class="font-semibold text-sm pb-1 tracking-wide block">Field Requirements:</span>
                        <ul class="list-disc pl-5">
                            <li class="font-normal text-sm tracking-wide pb-1"><strong>Required:</strong> Field must be
                                present and not empty</li>
                            <li class="font-normal text-sm tracking-wide pb-1"><strong>Nullable:</strong> Field can be
                                omitted or set to null</li>
                            <li class="font-normal text-sm tracking-wide"><strong>Optional:</strong> Field can be
                                omitted but if present must have valid value</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
