    <!-- API Endpoints Documentation - Each API per row -->
    <div class="space-y-4 sm:space-y-6">
        <!-- Login Endpoint -->
        <div class="print-section bg-white rounded-2xl shadow-lg border border-[#003049]/10 overflow-hidden">
            <div class="p-4 sm:p-6 border-b border-[#003049]/10">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-[#003049]/10 rounded-xl flex items-center justify-center">
                        <svg class="w-6 h-6 text-[#003049]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 12l2 2 4-4m5.5-2A8.5 8.5 0 119.5 3a8.5 8.5 0 010 17 8.5 8.5 0 01-.5-17z" />
                        </svg>
                    </div>
                    <h2 class="text-lg sm:text-xl font-semibold text-[#003049]">Login</h2>
                </div>
            </div>
            <div class="p-4 sm:p-6">
                <div class="flex items-center mb-2">
                    <span class="bg-blue-600 text-white px-2 py-1 rounded text-xs font-bold mr-2">POST</span>
                    <span class="font-mono text-sm">/api/v1/login</span>
                </div>
                <span class="font-normal text-sm tracking-wide block mb-3">Authenticate lab user and return JWT
                    token.</span>

                <div class="mb-3">
                    <span class="font-semibold text-sm pb-1 tracking-wide block">Request Body:</span>
                    <pre class="bg-[#003049]/5 border border-[#003049]/10 p-3 rounded text-xs font-mono overflow-x-auto text-[#003049]"><code>{
  "username": "LAB001user",     // Required: string
  "password": "password123"     // Required: string
}</code></pre>
                </div>

                <div class="mb-3">
                    <span class="font-semibold text-sm pb-1 tracking-wide block">CURL Example:</span>
                    <pre class="bg-[#003049]/5 border border-[#003049]/10 p-3 rounded text-xs font-mono overflow-x-auto text-[#003049]"><code>curl -X POST "https://mytotalhealth.com.my/staging/api/v1/login" \
     -H "Content-Type: application/json" \
     -d '{
       "username": "LAB001user",
       "password": "password123"
     }'</code></pre>
                </div>

                <div class="mb-2">
                    <span class="font-semibold text-sm pb-1 tracking-wide block">Response Examples:</span>
                    <div class="mb-2">
                        <span
                            class="bg-[#991B1B]/10 text-[#991B1B] px-3 py-1 rounded text-xs font-bold mr-2 border border-[#991B1B]/20">200
                            Success</span>
                        <pre class="bg-[#003049]/5 border border-[#003049]/10 p-2 rounded text-xs font-mono mt-2 text-[#003049]"><code>{
  "success": true,
  "data": {
    "access_token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
    "token_type": "bearer",
    "expires_in": 2592000
  },
  "message": "Login successful"
}</code></pre>
                    </div>
                    <div>
                        <span class="bg-red-100 text-red-800 px-2 py-1 rounded text-xs font-bold mr-2">401
                            Unauthorized</span>
                        <pre class="bg-[#003049]/5 border border-[#003049]/10 p-2 rounded text-xs font-mono mt-2 text-[#003049]"><code>{
  "success": false,
  "message": "Invalid credentials",
  "error": "Unauthorized"
}</code></pre>
                    </div>
                </div>
            </div>
        </div>

        <!-- Logout Endpoint -->
        <div class="print-page-break print-section bg-white rounded-2xl shadow-lg border border-[#003049]/10 overflow-hidden">
            <div class="p-4 sm:p-6 border-b border-[#003049]/10">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-[#003049]/10 rounded-xl flex items-center justify-center">
                        <svg class="w-6 h-6 text-[#003049]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                        </svg>
                    </div>
                    <h2 class="text-lg sm:text-xl font-semibold text-[#003049]">Logout</h2>
                </div>
            </div>
            <div class="p-4 sm:p-6">
                <div class="flex items-center mb-2">
                    <span class="bg-blue-600 text-white px-2 py-1 rounded text-xs font-bold mr-2">POST</span>
                    <span class="font-mono text-sm">/api/v1/logout</span>
                </div>
                <span class="font-normal text-sm tracking-wide block mb-3">Logout the authenticated lab user and
                    invalidate the JWT token.</span>

                <div class="mb-3">
                    <span class="font-semibold text-sm pb-1 tracking-wide block">Headers:</span>
                    <pre class="bg-[#003049]/5 border border-[#003049]/10 p-3 rounded text-xs font-mono overflow-x-auto text-[#003049]"><code>Authorization: Bearer {your_jwt_token}</code></pre>
                </div>

                <div class="mb-2">
                    <span class="font-semibold text-sm pb-1 tracking-wide block">CURL Example:</span>
                    <pre class="bg-[#003049]/5 border border-[#003049]/10 p-3 rounded text-xs font-mono overflow-x-auto text-[#003049]"><code>curl -X POST "https://mytotalhealth.com.my/staging/api/v1/logout" \
     -H "Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9..."</code></pre>
                </div>
            </div>
        </div>

        @if (false) {{-- Panel Results Endpoint hidden temporarily --}}
            <!-- Panel Results Endpoint -->
            <div class="print-section bg-white rounded-2xl shadow-lg border border-[#003049]/10 overflow-hidden">
                <div class="p-4 sm:p-6 border-b border-[#003049]/10">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 bg-[#003049]/10 rounded-xl flex items-center justify-center">
                            <svg class="w-6 h-6 text-[#003049]" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M9 5H7a2 2 0 00-2 2v6a2 2 0 002 2h2m5 0h2a2 2 0 002-2V7a2 2 0 00-2-2h-2m-5 4h6m-6 4h6m-6-8h6" />
                            </svg>
                        </div>
                        <h2 class="text-lg sm:text-xl font-semibold text-[#003049]">Panel Results Endpoint</h2>
                    </div>
                </div>
                <div class="p-4 sm:p-6">

                    <div>
                        <div class="flex items-center mb-2">
                            <span class="bg-blue-600 text-white px-2 py-1 rounded text-xs font-bold mr-2">POST</span>
                            <span class="font-mono text-sm">/api/v1/result/panel</span>
                        </div>
                        <span class="font-normal text-sm tracking-wide block mb-3">Process lab results from Innoquest
                            system in HL7-like format.</span>

                        <div class="mb-3">
                            <span class="font-semibold text-sm pb-1 tracking-wide block">IQMY Pathology Results JSON
                                Specification:</span>
                            <div class="overflow-x-auto">
                                <table class="w-full border border-gray-200 rounded-md shadow-sm">
                                    <thead class="bg-[#003049]/5 border-b border-[#003049]/10">
                                        <tr>
                                            <th
                                                class="px-3 sm:px-6 py-2 sm:py-3 text-left text-xs font-semibold text-[#003049] uppercase tracking-wider">
                                                Field</th>
                                            <th
                                                class="px-3 sm:px-6 py-2 sm:py-3 text-left text-xs font-semibold text-[#003049] uppercase tracking-wider">
                                                Type</th>
                                            <th
                                                class="px-3 sm:px-6 py-2 sm:py-3 text-left text-xs font-semibold text-[#003049] uppercase tracking-wider">
                                                Required</th>
                                            <th
                                                class="px-3 sm:px-6 py-2 sm:py-3 text-left text-xs font-semibold text-[#003049] uppercase tracking-wider">
                                                Description</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <!-- Root Level -->
                                        <tr class="bg-[#003049]/5">
                                            <td colspan="4"
                                                class="px-3 sm:px-6 py-2 sm:py-3 font-semibold text-[#003049] text-sm">
                                                Root Level
                                            </td>
                                        </tr>
                                        <tr class="hover:bg-[#003049]/5 transition-colors duration-200">
                                            <td class="px-3 sm:px-6 py-2 sm:py-3 whitespace-nowrap">
                                                <div class="text-xs sm:text-sm font-mono text-[#003049]">SendingFacility
                                                </div>
                                            </td>
                                            <td class="px-3 sm:px-6 py-2 sm:py-3 whitespace-nowrap">
                                                <div class="text-xs sm:text-sm text-gray-700">String</div>
                                            </td>
                                            <td class="px-3 sm:px-6 py-2 sm:py-3 whitespace-nowrap">
                                                <span
                                                    class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-[#991B1B]/10 text-[#991B1B] border border-[#991B1B]/20">Yes</span>
                                            </td>
                                            <td class="px-3 sm:px-6 py-2 sm:py-3">
                                                <div class="text-xs sm:text-sm text-gray-700">Identifier for the sending
                                                    facility</div>
                                            </td>
                                        </tr>
                                        <tr class="hover:bg-[#003049]/5 transition-colors duration-200">
                                            <td class="px-3 sm:px-6 py-2 sm:py-3 whitespace-nowrap">
                                                <div class="text-xs sm:text-sm font-mono text-[#003049]">
                                                    MessageControlID</div>
                                            </td>
                                            <td class="px-3 sm:px-6 py-2 sm:py-3 whitespace-nowrap">
                                                <div class="text-xs sm:text-sm text-gray-700">String</div>
                                            </td>
                                            <td class="px-3 sm:px-6 py-2 sm:py-3 whitespace-nowrap">
                                                <span
                                                    class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-[#991B1B]/10 text-[#991B1B] border border-[#991B1B]/20">Yes</span>
                                            </td>
                                            <td class="px-3 sm:px-6 py-2 sm:py-3">
                                                <div class="text-xs sm:text-sm text-gray-700">Unique message identifier
                                                </div>
                                            </td>
                                        </tr>
                                        <!-- Patient Information -->
                                        <tr class="bg-[#003049]/5">
                                            <td colspan="4"
                                                class="px-3 sm:px-6 py-2 sm:py-3 font-semibold text-[#003049] text-sm">
                                                Patient Information
                                            </td>
                                        </tr>
                                        <tr class="hover:bg-[#003049]/5 transition-colors duration-200">
                                            <td class="px-3 sm:px-6 py-2 sm:py-3 whitespace-nowrap">
                                                <div class="text-xs sm:text-sm font-mono text-[#003049]">PatientID</div>
                                            </td>
                                            <td class="px-3 sm:px-6 py-2 sm:py-3 whitespace-nowrap">
                                                <div class="text-xs sm:text-sm text-gray-700">String</div>
                                            </td>
                                            <td class="px-3 sm:px-6 py-2 sm:py-3 whitespace-nowrap">
                                                <span
                                                    class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800 border border-yellow-200">Optional</span>
                                            </td>
                                            <td class="px-3 sm:px-6 py-2 sm:py-3">
                                                <div class="text-xs sm:text-sm text-gray-700">Unique identifier for the
                                                    patient (MRN)</div>
                                            </td>
                                        </tr>
                                        <tr class="hover:bg-[#003049]/5 transition-colors duration-200">
                                            <td class="px-3 sm:px-6 py-2 sm:py-3 whitespace-nowrap">
                                                <div class="text-xs sm:text-sm font-mono text-[#003049]">
                                                    AlternatePatientID</div>
                                            </td>
                                            <td class="px-3 sm:px-6 py-2 sm:py-3 whitespace-nowrap">
                                                <div class="text-xs sm:text-sm text-gray-700">String</div>
                                            </td>
                                            <td class="px-3 sm:px-6 py-2 sm:py-3 whitespace-nowrap">
                                                <span
                                                    class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800 border border-yellow-200">Optional</span>
                                            </td>
                                            <td class="px-3 sm:px-6 py-2 sm:py-3">
                                                <div class="text-xs sm:text-sm text-gray-700">Alternate patient
                                                    identifier (NRIC)</div>
                                            </td>
                                        </tr>
                                        <tr class="hover:bg-[#003049]/5 transition-colors duration-200">
                                            <td class="px-3 sm:px-6 py-2 sm:py-3 whitespace-nowrap">
                                                <div class="text-xs sm:text-sm font-mono text-[#003049]">
                                                    PatientLastName</div>
                                            </td>
                                            <td class="px-3 sm:px-6 py-2 sm:py-3 whitespace-nowrap">
                                                <div class="text-xs sm:text-sm text-gray-700">String</div>
                                            </td>
                                            <td class="px-3 sm:px-6 py-2 sm:py-3 whitespace-nowrap">
                                                <span
                                                    class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-[#991B1B]/10 text-[#991B1B] border border-[#991B1B]/20">Yes</span>
                                            </td>
                                            <td class="px-3 sm:px-6 py-2 sm:py-3">
                                                <div class="text-xs sm:text-sm text-gray-700">Full Name of Patient will
                                                    be in this field</div>
                                            </td>
                                        </tr>
                                        <tr class="hover:bg-[#003049]/5 transition-colors duration-200">
                                            <td class="px-3 sm:px-6 py-2 sm:py-3 whitespace-nowrap">
                                                <div class="text-xs sm:text-sm font-mono text-[#003049]">PatientDOB
                                                </div>
                                            </td>
                                            <td class="px-3 sm:px-6 py-2 sm:py-3 whitespace-nowrap">
                                                <div class="text-xs sm:text-sm text-gray-700">String</div>
                                            </td>
                                            <td class="px-3 sm:px-6 py-2 sm:py-3 whitespace-nowrap">
                                                <span
                                                    class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-[#991B1B]/10 text-[#991B1B] border border-[#991B1B]/20">Yes</span>
                                            </td>
                                            <td class="px-3 sm:px-6 py-2 sm:py-3">
                                                <div class="text-xs sm:text-sm text-gray-700">Date of birth of the
                                                    patient (YYYYMMDD)</div>
                                            </td>
                                        </tr>
                                        <tr class="hover:bg-[#003049]/5 transition-colors duration-200">
                                            <td class="px-3 sm:px-6 py-2 sm:py-3 whitespace-nowrap">
                                                <div class="text-xs sm:text-sm font-mono text-[#003049]">PatientGender
                                                </div>
                                            </td>
                                            <td class="px-3 sm:px-6 py-2 sm:py-3 whitespace-nowrap">
                                                <div class="text-xs sm:text-sm text-gray-700">String</div>
                                            </td>
                                            <td class="px-3 sm:px-6 py-2 sm:py-3 whitespace-nowrap">
                                                <span
                                                    class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-[#991B1B]/10 text-[#991B1B] border border-[#991B1B]/20">Yes</span>
                                            </td>
                                            <td class="px-3 sm:px-6 py-2 sm:py-3">
                                                <div class="text-xs sm:text-sm text-gray-700">Gender of the patient
                                                    ('M','F')</div>
                                            </td>
                                        </tr>
                                        <tr class="hover:bg-[#003049]/5 transition-colors duration-200">
                                            <td class="px-3 sm:px-6 py-2 sm:py-3 whitespace-nowrap">
                                                <div class="text-xs sm:text-sm font-mono text-[#003049]">PatientAddress
                                                </div>
                                            </td>
                                            <td class="px-3 sm:px-6 py-2 sm:py-3 whitespace-nowrap">
                                                <div class="text-xs sm:text-sm text-gray-700">String</div>
                                            </td>
                                            <td class="px-3 sm:px-6 py-2 sm:py-3 whitespace-nowrap">
                                                <span
                                                    class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800 border border-yellow-200">Optional</span>
                                            </td>
                                            <td class="px-3 sm:px-6 py-2 sm:py-3">
                                                <div class="text-xs sm:text-sm text-gray-700">Address of the patient -
                                                    not always stored</div>
                                            </td>
                                        </tr>
                                        <!-- Orders -->
                                        <tr class="bg-[#003049]/5">
                                            <td colspan="4"
                                                class="px-3 sm:px-6 py-2 sm:py-3 font-semibold text-[#003049] text-sm">
                                                Orders
                                            </td>
                                        </tr>
                                        <tr class="hover:bg-[#003049]/5 transition-colors duration-200">
                                            <td class="px-3 sm:px-6 py-2 sm:py-3 whitespace-nowrap">
                                                <div class="text-xs sm:text-sm font-mono text-[#003049]">
                                                    FillerOrderNumber</div>
                                            </td>
                                            <td class="px-3 sm:px-6 py-2 sm:py-3 whitespace-nowrap">
                                                <div class="text-xs sm:text-sm text-gray-700">String</div>
                                            </td>
                                            <td class="px-3 sm:px-6 py-2 sm:py-3 whitespace-nowrap">
                                                <span
                                                    class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-[#991B1B]/10 text-[#991B1B] border border-[#991B1B]/20">Yes</span>
                                            </td>
                                            <td class="px-3 sm:px-6 py-2 sm:py-3">
                                                <div class="text-xs sm:text-sm text-gray-700">IQMY Request Number</div>
                                            </td>
                                        </tr>
                                        <tr class="hover:bg-[#003049]/5 transition-colors duration-200">
                                            <td class="px-3 sm:px-6 py-2 sm:py-3 whitespace-nowrap">
                                                <div class="text-xs sm:text-sm font-mono text-[#003049]">
                                                    OrderingProvider.Code</div>
                                            </td>
                                            <td class="px-3 sm:px-6 py-2 sm:py-3 whitespace-nowrap">
                                                <div class="text-xs sm:text-sm text-gray-700">String</div>
                                            </td>
                                            <td class="px-3 sm:px-6 py-2 sm:py-3 whitespace-nowrap">
                                                <span
                                                    class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-[#991B1B]/10 text-[#991B1B] border border-[#991B1B]/20">Yes</span>
                                            </td>
                                            <td class="px-3 sm:px-6 py-2 sm:py-3">
                                                <div class="text-xs sm:text-sm text-gray-700">IQMY Doctor Code</div>
                                            </td>
                                        </tr>
                                        <tr class="hover:bg-[#003049]/5 transition-colors duration-200">
                                            <td class="px-3 sm:px-6 py-2 sm:py-3 whitespace-nowrap">
                                                <div class="text-xs sm:text-sm font-mono text-[#003049]">
                                                    OrderingProvider.Name</div>
                                            </td>
                                            <td class="px-3 sm:px-6 py-2 sm:py-3 whitespace-nowrap">
                                                <div class="text-xs sm:text-sm text-gray-700">String</div>
                                            </td>
                                            <td class="px-3 sm:px-6 py-2 sm:py-3 whitespace-nowrap">
                                                <span
                                                    class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-[#991B1B]/10 text-[#991B1B] border border-[#991B1B]/20">Yes</span>
                                            </td>
                                            <td class="px-3 sm:px-6 py-2 sm:py-3">
                                                <div class="text-xs sm:text-sm text-gray-700">Doctor Name</div>
                                            </td>
                                        </tr>
                                        <!-- Observations -->
                                        <tr class="bg-[#003049]/5">
                                            <td colspan="4"
                                                class="px-3 sm:px-6 py-2 sm:py-3 font-semibold text-[#003049] text-sm">
                                                Observations
                                            </td>
                                        </tr>
                                        <tr class="hover:bg-[#003049]/5 transition-colors duration-200">
                                            <td class="px-3 sm:px-6 py-2 sm:py-3 whitespace-nowrap">
                                                <div class="text-xs sm:text-sm font-mono text-[#003049]">ProcedureCode
                                                </div>
                                            </td>
                                            <td class="px-3 sm:px-6 py-2 sm:py-3 whitespace-nowrap">
                                                <div class="text-xs sm:text-sm text-gray-700">String</div>
                                            </td>
                                            <td class="px-3 sm:px-6 py-2 sm:py-3 whitespace-nowrap">
                                                <span
                                                    class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-[#991B1B]/10 text-[#991B1B] border border-[#991B1B]/20">Yes</span>
                                            </td>
                                            <td class="px-3 sm:px-6 py-2 sm:py-3">
                                                <div class="text-xs sm:text-sm text-gray-700">Testing Panel Code</div>
                                            </td>
                                        </tr>
                                        <tr class="hover:bg-[#003049]/5 transition-colors duration-200">
                                            <td class="px-3 sm:px-6 py-2 sm:py-3 whitespace-nowrap">
                                                <div class="text-xs sm:text-sm font-mono text-[#003049]">
                                                    ProcedureDescription</div>
                                            </td>
                                            <td class="px-3 sm:px-6 py-2 sm:py-3 whitespace-nowrap">
                                                <div class="text-xs sm:text-sm text-gray-700">String</div>
                                            </td>
                                            <td class="px-3 sm:px-6 py-2 sm:py-3 whitespace-nowrap">
                                                <span
                                                    class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-[#991B1B]/10 text-[#991B1B] border border-[#991B1B]/20">Yes</span>
                                            </td>
                                            <td class="px-3 sm:px-6 py-2 sm:py-3">
                                                <div class="text-xs sm:text-sm text-gray-700">Panel Description</div>
                                            </td>
                                        </tr>
                                        <tr class="hover:bg-[#003049]/5 transition-colors duration-200">
                                            <td class="px-3 sm:px-6 py-2 sm:py-3 whitespace-nowrap">
                                                <div class="text-xs sm:text-sm font-mono text-[#003049]">ResultStatus
                                                </div>
                                            </td>
                                            <td class="px-3 sm:px-6 py-2 sm:py-3 whitespace-nowrap">
                                                <div class="text-xs sm:text-sm text-gray-700">String</div>
                                            </td>
                                            <td class="px-3 sm:px-6 py-2 sm:py-3 whitespace-nowrap">
                                                <span
                                                    class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-[#991B1B]/10 text-[#991B1B] border border-[#991B1B]/20">Yes</span>
                                            </td>
                                            <td class="px-3 sm:px-6 py-2 sm:py-3">
                                                <div class="text-xs sm:text-sm text-gray-700">Status of the result
                                                </div>
                                            </td>
                                        </tr>
                                        <tr class="hover:bg-[#003049]/5 transition-colors duration-200">
                                            <td class="px-3 sm:px-6 py-2 sm:py-3 whitespace-nowrap">
                                                <div class="text-xs sm:text-sm font-mono text-[#003049]">
                                                    ServiceDateTime</div>
                                            </td>
                                            <td class="px-3 sm:px-6 py-2 sm:py-3 whitespace-nowrap">
                                                <div class="text-xs sm:text-sm text-gray-700">String</div>
                                            </td>
                                            <td class="px-3 sm:px-6 py-2 sm:py-3 whitespace-nowrap">
                                                <span
                                                    class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-[#991B1B]/10 text-[#991B1B] border border-[#991B1B]/20">Yes</span>
                                            </td>
                                            <td class="px-3 sm:px-6 py-2 sm:py-3">
                                                <div class="text-xs sm:text-sm text-gray-700">Date and time of service
                                                </div>
                                            </td>
                                        </tr>
                                        <!-- Results -->
                                        <tr class="bg-[#003049]/5">
                                            <td colspan="4"
                                                class="px-3 sm:px-6 py-2 sm:py-3 font-semibold text-[#003049] text-sm">
                                                Results
                                            </td>
                                        </tr>
                                        <tr class="hover:bg-[#003049]/5 transition-colors duration-200">
                                            <td class="px-3 sm:px-6 py-2 sm:py-3 whitespace-nowrap">
                                                <div class="text-xs sm:text-sm font-mono text-[#003049]">Results.ID
                                                </div>
                                            </td>
                                            <td class="px-3 sm:px-6 py-2 sm:py-3 whitespace-nowrap">
                                                <div class="text-xs sm:text-sm text-gray-700">String</div>
                                            </td>
                                            <td class="px-3 sm:px-6 py-2 sm:py-3 whitespace-nowrap">
                                                <span
                                                    class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-[#991B1B]/10 text-[#991B1B] border border-[#991B1B]/20">Yes</span>
                                            </td>
                                            <td class="px-3 sm:px-6 py-2 sm:py-3">
                                                <div class="text-xs sm:text-sm text-gray-700">Ordinal ID within this
                                                    results message</div>
                                            </td>
                                        </tr>
                                        <tr class="hover:bg-[#003049]/5 transition-colors duration-200">
                                            <td class="px-3 sm:px-6 py-2 sm:py-3 whitespace-nowrap">
                                                <div class="text-xs sm:text-sm font-mono text-[#003049]">Results.Type
                                                </div>
                                            </td>
                                            <td class="px-3 sm:px-6 py-2 sm:py-3 whitespace-nowrap">
                                                <div class="text-xs sm:text-sm text-gray-700">String</div>
                                            </td>
                                            <td class="px-3 sm:px-6 py-2 sm:py-3 whitespace-nowrap">
                                                <span
                                                    class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-[#991B1B]/10 text-[#991B1B] border border-[#991B1B]/20">Yes</span>
                                            </td>
                                            <td class="px-3 sm:px-6 py-2 sm:py-3">
                                                <div class="text-xs sm:text-sm text-gray-700">Type of result
                                                    (numerical, text)</div>
                                            </td>
                                        </tr>
                                        <tr class="hover:bg-[#003049]/5 transition-colors duration-200">
                                            <td class="px-3 sm:px-6 py-2 sm:py-3 whitespace-nowrap">
                                                <div class="text-xs sm:text-sm font-mono text-[#003049]">
                                                    Results.Identifier</div>
                                            </td>
                                            <td class="px-3 sm:px-6 py-2 sm:py-3 whitespace-nowrap">
                                                <div class="text-xs sm:text-sm text-gray-700">String</div>
                                            </td>
                                            <td class="px-3 sm:px-6 py-2 sm:py-3 whitespace-nowrap">
                                                <span
                                                    class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-[#991B1B]/10 text-[#991B1B] border border-[#991B1B]/20">Yes</span>
                                            </td>
                                            <td class="px-3 sm:px-6 py-2 sm:py-3">
                                                <div class="text-xs sm:text-sm text-gray-700">Unique identifier for the
                                                    test/analyte (LOINC)</div>
                                            </td>
                                        </tr>
                                        <tr class="hover:bg-[#003049]/5 transition-colors duration-200">
                                            <td class="px-3 sm:px-6 py-2 sm:py-3 whitespace-nowrap">
                                                <div class="text-xs sm:text-sm font-mono text-[#003049]">Results.Text
                                                </div>
                                            </td>
                                            <td class="px-3 sm:px-6 py-2 sm:py-3 whitespace-nowrap">
                                                <div class="text-xs sm:text-sm text-gray-700">String</div>
                                            </td>
                                            <td class="px-3 sm:px-6 py-2 sm:py-3 whitespace-nowrap">
                                                <span
                                                    class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-[#991B1B]/10 text-[#991B1B] border border-[#991B1B]/20">Yes</span>
                                            </td>
                                            <td class="px-3 sm:px-6 py-2 sm:py-3">
                                                <div class="text-xs sm:text-sm text-gray-700">Test description</div>
                                            </td>
                                        </tr>
                                        <tr class="hover:bg-[#003049]/5 transition-colors duration-200">
                                            <td class="px-3 sm:px-6 py-2 sm:py-3 whitespace-nowrap">
                                                <div class="text-xs sm:text-sm font-mono text-[#003049]">Results.Value
                                                </div>
                                            </td>
                                            <td class="px-3 sm:px-6 py-2 sm:py-3 whitespace-nowrap">
                                                <div class="text-xs sm:text-sm text-gray-700">String</div>
                                            </td>
                                            <td class="px-3 sm:px-6 py-2 sm:py-3 whitespace-nowrap">
                                                <span
                                                    class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-[#991B1B]/10 text-[#991B1B] border border-[#991B1B]/20">Yes</span>
                                            </td>
                                            <td class="px-3 sm:px-6 py-2 sm:py-3">
                                                <div class="text-xs sm:text-sm text-gray-700">Result value</div>
                                            </td>
                                        </tr>
                                        <tr class="hover:bg-[#003049]/5 transition-colors duration-200">
                                            <td class="px-3 sm:px-6 py-2 sm:py-3 whitespace-nowrap">
                                                <div class="text-xs sm:text-sm font-mono text-[#003049]">Results.Units
                                                </div>
                                            </td>
                                            <td class="px-3 sm:px-6 py-2 sm:py-3 whitespace-nowrap">
                                                <div class="text-xs sm:text-sm text-gray-700">String</div>
                                            </td>
                                            <td class="px-3 sm:px-6 py-2 sm:py-3 whitespace-nowrap">
                                                <span
                                                    class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-[#991B1B]/10 text-[#991B1B] border border-[#991B1B]/20">Yes</span>
                                            </td>
                                            <td class="px-3 sm:px-6 py-2 sm:py-3">
                                                <div class="text-xs sm:text-sm text-gray-700">Units of measurement
                                                </div>
                                            </td>
                                        </tr>
                                        <tr class="hover:bg-[#003049]/5 transition-colors duration-200">
                                            <td class="px-3 sm:px-6 py-2 sm:py-3 whitespace-nowrap">
                                                <div class="text-xs sm:text-sm font-mono text-[#003049]">
                                                    Results.ReferenceRange</div>
                                            </td>
                                            <td class="px-3 sm:px-6 py-2 sm:py-3 whitespace-nowrap">
                                                <div class="text-xs sm:text-sm text-gray-700">String</div>
                                            </td>
                                            <td class="px-3 sm:px-6 py-2 sm:py-3 whitespace-nowrap">
                                                <span
                                                    class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-[#991B1B]/10 text-[#991B1B] border border-[#991B1B]/20">Yes</span>
                                            </td>
                                            <td class="px-3 sm:px-6 py-2 sm:py-3">
                                                <div class="text-xs sm:text-sm text-gray-700">Normal reference range
                                                </div>
                                            </td>
                                        </tr>
                                        <tr class="hover:bg-[#003049]/5 transition-colors duration-200">
                                            <td class="px-3 sm:px-6 py-2 sm:py-3 whitespace-nowrap">
                                                <div class="text-xs sm:text-sm font-mono text-[#003049]">Results.Flags
                                                </div>
                                            </td>
                                            <td class="px-3 sm:px-6 py-2 sm:py-3 whitespace-nowrap">
                                                <div class="text-xs sm:text-sm text-gray-700">String</div>
                                            </td>
                                            <td class="px-3 sm:px-6 py-2 sm:py-3 whitespace-nowrap">
                                                <span
                                                    class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-[#991B1B]/10 text-[#991B1B] border border-[#991B1B]/20">Yes</span>
                                            </td>
                                            <td class="px-3 sm:px-6 py-2 sm:py-3">
                                                <div class="text-xs sm:text-sm text-gray-700">Flags indicating
                                                    abnormalities</div>
                                            </td>
                                        </tr>
                                        <tr class="hover:bg-[#003049]/5 transition-colors duration-200">
                                            <td class="px-3 sm:px-6 py-2 sm:py-3 whitespace-nowrap">
                                                <div class="text-xs sm:text-sm font-mono text-[#003049]">Results.Status
                                                </div>
                                            </td>
                                            <td class="px-3 sm:px-6 py-2 sm:py-3 whitespace-nowrap">
                                                <div class="text-xs sm:text-sm text-gray-700">String</div>
                                            </td>
                                            <td class="px-3 sm:px-6 py-2 sm:py-3 whitespace-nowrap">
                                                <span
                                                    class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-[#991B1B]/10 text-[#991B1B] border border-[#991B1B]/20">Yes</span>
                                            </td>
                                            <td class="px-3 sm:px-6 py-2 sm:py-3">
                                                <div class="text-xs sm:text-sm text-gray-700">Result status</div>
                                            </td>
                                        </tr>
                                        <tr class="hover:bg-[#003049]/5 transition-colors duration-200">
                                            <td class="px-3 sm:px-6 py-2 sm:py-3 whitespace-nowrap">
                                                <div class="text-xs sm:text-sm font-mono text-[#003049]">
                                                    Results.ObservationDateTime</div>
                                            </td>
                                            <td class="px-3 sm:px-6 py-2 sm:py-3 whitespace-nowrap">
                                                <div class="text-xs sm:text-sm text-gray-700">String</div>
                                            </td>
                                            <td class="px-3 sm:px-6 py-2 sm:py-3 whitespace-nowrap">
                                                <span
                                                    class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-[#991B1B]/10 text-[#991B1B] border border-[#991B1B]/20">Yes</span>
                                            </td>
                                            <td class="px-3 sm:px-6 py-2 sm:py-3">
                                                <div class="text-xs sm:text-sm text-gray-700">Date and time of test
                                                    (YYYYMMDDhhmm)</div>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="mb-3">
                            <span class="font-semibold text-sm pb-1 tracking-wide block">Headers:</span>
                            <pre class="bg-[#003049]/5 border border-[#003049]/10 p-3 rounded text-xs font-mono overflow-x-auto text-[#003049]"><code>Authorization: Bearer {your_jwt_token}
Content-Type: application/json
Accept: application/json</code></pre>
                        </div>

                        <div class="mb-3">
                            <span class="font-semibold text-sm pb-1 tracking-wide block">Request Body Example:</span>
                            <pre class="bg-[#003049]/5 border border-[#003049]/10 p-3 rounded text-xs font-mono overflow-x-auto text-[#003049]"><code>{
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
                            <pre class="bg-[#003049]/5 border border-[#003049]/10 p-3 rounded text-xs font-mono overflow-x-auto text-[#003049]"><code>curl -X POST "https://mytotalhealth.com.my/staging/api/v1/result/panel" \
     -H "accept: application/json" \
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
                                <pre class="bg-[#003049]/5 border border-[#003049]/10 p-2 rounded text-xs font-mono mt-2 text-[#003049]"><code>{
  "success": true,
  "message": "Panel results processed successfully",
  "data": {
    "test_result_id": 123,
    "panel": "Full Blood Count"
  }
}</code></pre>
                            </div>
                            <div class="mb-2">
                                <span
                                    class="bg-yellow-100 text-yellow-800 px-2 py-1 rounded text-xs font-bold mr-2">422
                                    Validation Error</span>
                                <pre class="bg-[#003049]/5 border border-[#003049]/10 p-2 rounded text-xs font-mono mt-2 text-[#003049]"><code>{
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
                                    Server Error</span>
                                <pre class="bg-[#003049]/5 border border-[#003049]/10 p-2 rounded text-xs font-mono mt-2 text-[#003049]"><code>{
  "success": false,
  "message": "Failed to process panel results",
  "error": "Internal server error"
}</code></pre>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        @if ($lab_id == 1 || $lab_id == 3)
            <!-- Patient Results Endpoint -->
            <div class="print-hide print-section bg-white rounded-2xl shadow-lg border border-[#003049]/10 overflow-hidden">
                <div class="p-4 sm:p-6 border-b border-[#003049]/10">
                    <div class="flex items-center gap-3 mb-4">
                        <div class="w-10 h-10 bg-[#003049]/10 rounded-xl flex items-center justify-center">
                            <svg class="w-6 h-6 text-[#003049]" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                            </svg>
                        </div>
                        <span class="text-lg sm:text-xl font-semibold text-[#003049]">Patient Results Endpoint</span>
                    </div>
                </div>
                <div class="p-4 sm:p-6">
                    <div>
                        <div class="flex items-center mb-2">
                            <span class="bg-blue-600 text-white px-2 py-1 rounded text-xs font-bold mr-2">POST</span>
                            <span class="font-mono text-sm">/api/v1/result/patient</span>
                        </div>
                        <span class="font-normal text-sm tracking-wide block mb-3">Submit lab results for a patient in
                            standard format.</span>

                        <div class="mb-3">
                            <span class="font-semibold text-sm pb-1 tracking-wide block">Key Required Fields:</span>
                            <div class="overflow-x-auto">
                                <table class="w-full border border-gray-200 rounded-md shadow-sm">
                                    <thead class="bg-[#003049]/5 border-b border-[#003049]/10">
                                        <tr>
                                            <th
                                                class="px-3 sm:px-6 py-2 sm:py-3 text-left text-xs font-semibold text-[#003049] uppercase tracking-wider">
                                                Field</th>
                                            <th
                                                class="px-3 sm:px-6 py-2 sm:py-3 text-left text-xs font-semibold text-[#003049] uppercase tracking-wider">
                                                Type</th>
                                            <th
                                                class="px-3 sm:px-6 py-2 sm:py-3 text-left text-xs font-semibold text-[#003049] uppercase tracking-wider">
                                                Required</th>
                                            <th
                                                class="px-3 sm:px-6 py-2 sm:py-3 text-left text-xs font-semibold text-[#003049] uppercase tracking-wider">
                                                Description</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <!-- Root Fields -->
                                        <tr class="bg-[#003049]/5">
                                            <td colspan="4" class="px-3 sm:px-6 py-2 sm:py-3 font-semibold text-[#003049] text-sm">Root Fields</td>
                                        </tr>
                                        <tr class="hover:bg-[#003049]/5 transition-colors duration-200">
                                            <td class="px-3 sm:px-6 py-2 sm:py-3 whitespace-nowrap">
                                                <div class="text-xs sm:text-sm font-mono text-[#003049]">reference_id</div>
                                            </td>
                                            <td class="px-3 sm:px-6 py-2 sm:py-3 whitespace-nowrap">
                                                <div class="text-xs sm:text-sm text-gray-700">string</div>
                                            </td>
                                            <td class="px-3 sm:px-6 py-2 sm:py-3 whitespace-nowrap">
                                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-600 border border-gray-200">No</span>
                                            </td>
                                            <td class="px-3 sm:px-6 py-2 sm:py-3">
                                                <div class="text-xs sm:text-sm text-gray-700">Obtained from Alpro Platform when printing the lab form with sample</div>
                                            </td>
                                        </tr>
                                        <tr class="hover:bg-[#003049]/5 transition-colors duration-200">
                                            <td class="px-3 sm:px-6 py-2 sm:py-3 whitespace-nowrap">
                                                <div class="text-xs sm:text-sm font-mono text-[#003049]">lab_no</div>
                                            </td>
                                            <td class="px-3 sm:px-6 py-2 sm:py-3 whitespace-nowrap">
                                                <div class="text-xs sm:text-sm text-gray-700">string</div>
                                            </td>
                                            <td class="px-3 sm:px-6 py-2 sm:py-3 whitespace-nowrap">
                                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-[#991B1B]/10 text-[#991B1B] border border-[#991B1B]/20">Yes</span>
                                            </td>
                                            <td class="px-3 sm:px-6 py-2 sm:py-3">
                                                <div class="text-xs sm:text-sm text-gray-700">Laboratory accession number for this sample</div>
                                            </td>
                                        </tr>
                                        <tr class="hover:bg-[#003049]/5 transition-colors duration-200">
                                            <td class="px-3 sm:px-6 py-2 sm:py-3 whitespace-nowrap">
                                                <div class="text-xs sm:text-sm font-mono text-[#003049]">bill_code</div>
                                            </td>
                                            <td class="px-3 sm:px-6 py-2 sm:py-3 whitespace-nowrap">
                                                <div class="text-xs sm:text-sm text-gray-700">string</div>
                                            </td>
                                            <td class="px-3 sm:px-6 py-2 sm:py-3 whitespace-nowrap">
                                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-600 border border-gray-200">No</span>
                                            </td>
                                            <td class="px-3 sm:px-6 py-2 sm:py-3">
                                                <div class="text-xs sm:text-sm text-gray-700">Billing or clinic code associated with the request</div>
                                            </td>
                                        </tr>
                                        <tr class="hover:bg-[#003049]/5 transition-colors duration-200">
                                            <td class="px-3 sm:px-6 py-2 sm:py-3 whitespace-nowrap">
                                                <div class="text-xs sm:text-sm font-mono text-[#003049]">doctor</div>
                                            </td>
                                            <td class="px-3 sm:px-6 py-2 sm:py-3 whitespace-nowrap">
                                                <div class="text-xs sm:text-sm text-gray-700">object</div>
                                            </td>
                                            <td class="px-3 sm:px-6 py-2 sm:py-3 whitespace-nowrap">
                                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-[#991B1B]/10 text-[#991B1B] border border-[#991B1B]/20">Yes</span>
                                            </td>
                                            <td class="px-3 sm:px-6 py-2 sm:py-3">
                                                <div class="text-xs sm:text-sm text-gray-700">Referring doctor / clinic information</div>
                                            </td>
                                        </tr>
                                        <tr class="hover:bg-[#003049]/5 transition-colors duration-200">
                                            <td class="px-3 sm:px-6 py-2 sm:py-3 whitespace-nowrap">
                                                <div class="text-xs sm:text-sm font-mono text-[#003049]">patient</div>
                                            </td>
                                            <td class="px-3 sm:px-6 py-2 sm:py-3 whitespace-nowrap">
                                                <div class="text-xs sm:text-sm text-gray-700">object</div>
                                            </td>
                                            <td class="px-3 sm:px-6 py-2 sm:py-3 whitespace-nowrap">
                                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-[#991B1B]/10 text-[#991B1B] border border-[#991B1B]/20">Yes</span>
                                            </td>
                                            <td class="px-3 sm:px-6 py-2 sm:py-3">
                                                <div class="text-xs sm:text-sm text-gray-700">Patient demographic information</div>
                                            </td>
                                        </tr>
                                        <tr class="hover:bg-[#003049]/5 transition-colors duration-200">
                                            <td class="px-3 sm:px-6 py-2 sm:py-3 whitespace-nowrap">
                                                <div class="text-xs sm:text-sm font-mono text-[#003049]">validated_by</div>
                                            </td>
                                            <td class="px-3 sm:px-6 py-2 sm:py-3 whitespace-nowrap">
                                                <div class="text-xs sm:text-sm text-gray-700">string</div>
                                            </td>
                                            <td class="px-3 sm:px-6 py-2 sm:py-3 whitespace-nowrap">
                                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-600 border border-gray-200">No</span>
                                            </td>
                                            <td class="px-3 sm:px-6 py-2 sm:py-3">
                                                <div class="text-xs sm:text-sm text-gray-700">Name and credentials of the validating pathologist</div>
                                            </td>
                                        </tr>
                                        <tr class="hover:bg-[#003049]/5 transition-colors duration-200">
                                            <td class="px-3 sm:px-6 py-2 sm:py-3 whitespace-nowrap">
                                                <div class="text-xs sm:text-sm font-mono text-[#003049]">report_status</div>
                                            </td>
                                            <td class="px-3 sm:px-6 py-2 sm:py-3 whitespace-nowrap">
                                                <div class="text-xs sm:text-sm text-gray-700">boolean</div>
                                            </td>
                                            <td class="px-3 sm:px-6 py-2 sm:py-3 whitespace-nowrap">
                                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-[#991B1B]/10 text-[#991B1B] border border-[#991B1B]/20">Yes</span>
                                            </td>
                                            <td class="px-3 sm:px-6 py-2 sm:py-3">
                                                <div class="text-xs sm:text-sm text-gray-700">Indicates whether the report is finalized (true = final, false = partial)</div>
                                            </td>
                                        </tr>
                                        <tr class="hover:bg-[#003049]/5 transition-colors duration-200">
                                            <td class="px-3 sm:px-6 py-2 sm:py-3 whitespace-nowrap">
                                                <div class="text-xs sm:text-sm font-mono text-[#003049]">package_name</div>
                                            </td>
                                            <td class="px-3 sm:px-6 py-2 sm:py-3 whitespace-nowrap">
                                                <div class="text-xs sm:text-sm text-gray-700">string</div>
                                            </td>
                                            <td class="px-3 sm:px-6 py-2 sm:py-3 whitespace-nowrap">
                                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-600 border border-gray-200">No</span>
                                            </td>
                                            <td class="px-3 sm:px-6 py-2 sm:py-3">
                                                <div class="text-xs sm:text-sm text-gray-700">Health package or profile name for grouping</div>
                                            </td>
                                        </tr>
                                        <!-- Date Fields -->
                                        <tr class="bg-[#003049]/5">
                                            <td colspan="4" class="px-3 sm:px-6 py-2 sm:py-3 font-semibold text-[#003049] text-sm">Date Fields</td>
                                        </tr>
                                        <tr class="hover:bg-[#003049]/5 transition-colors duration-200">
                                            <td class="px-3 sm:px-6 py-2 sm:py-3 whitespace-nowrap">
                                                <div class="text-xs sm:text-sm font-mono text-[#003049]">collected_date</div>
                                            </td>
                                            <td class="px-3 sm:px-6 py-2 sm:py-3 whitespace-nowrap">
                                                <div class="text-xs sm:text-sm text-gray-700">string</div>
                                            </td>
                                            <td class="px-3 sm:px-6 py-2 sm:py-3 whitespace-nowrap">
                                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-600 border border-gray-200">No</span>
                                            </td>
                                            <td class="px-3 sm:px-6 py-2 sm:py-3">
                                                <div class="text-xs sm:text-sm text-gray-700">Date and time the sample was collected (YYYY-MM-DD HH:mm:ss)</div>
                                            </td>
                                        </tr>
                                        <tr class="hover:bg-[#003049]/5 transition-colors duration-200">
                                            <td class="px-3 sm:px-6 py-2 sm:py-3 whitespace-nowrap">
                                                <div class="text-xs sm:text-sm font-mono text-[#003049]">received_date</div>
                                            </td>
                                            <td class="px-3 sm:px-6 py-2 sm:py-3 whitespace-nowrap">
                                                <div class="text-xs sm:text-sm text-gray-700">string</div>
                                            </td>
                                            <td class="px-3 sm:px-6 py-2 sm:py-3 whitespace-nowrap">
                                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-600 border border-gray-200">No</span>
                                            </td>
                                            <td class="px-3 sm:px-6 py-2 sm:py-3">
                                                <div class="text-xs sm:text-sm text-gray-700">Date and time the sample was received at the laboratory</div>
                                            </td>
                                        </tr>
                                        <tr class="hover:bg-[#003049]/5 transition-colors duration-200">
                                            <td class="px-3 sm:px-6 py-2 sm:py-3 whitespace-nowrap">
                                                <div class="text-xs sm:text-sm font-mono text-[#003049]">reported_date</div>
                                            </td>
                                            <td class="px-3 sm:px-6 py-2 sm:py-3 whitespace-nowrap">
                                                <div class="text-xs sm:text-sm text-gray-700">string</div>
                                            </td>
                                            <td class="px-3 sm:px-6 py-2 sm:py-3 whitespace-nowrap">
                                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-600 border border-gray-200">No</span>
                                            </td>
                                            <td class="px-3 sm:px-6 py-2 sm:py-3">
                                                <div class="text-xs sm:text-sm text-gray-700">Date and time the result was reported</div>
                                            </td>
                                        </tr>
                                        <!-- Doctor Object -->
                                        <tr class="bg-[#003049]/5">
                                            <td colspan="4" class="px-3 sm:px-6 py-2 sm:py-3 font-semibold text-[#003049] text-sm">Doctor Object</td>
                                        </tr>
                                        <tr class="hover:bg-[#003049]/5 transition-colors duration-200">
                                            <td class="px-3 sm:px-6 py-2 sm:py-3 whitespace-nowrap">
                                                <div class="text-xs sm:text-sm font-mono text-[#003049]">doctor.code</div>
                                            </td>
                                            <td class="px-3 sm:px-6 py-2 sm:py-3 whitespace-nowrap">
                                                <div class="text-xs sm:text-sm text-gray-700">string</div>
                                            </td>
                                            <td class="px-3 sm:px-6 py-2 sm:py-3 whitespace-nowrap">
                                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-600 border border-gray-200">No</span>
                                            </td>
                                            <td class="px-3 sm:px-6 py-2 sm:py-3">
                                                <div class="text-xs sm:text-sm text-gray-700">Short code identifying the referring doctor or clinic</div>
                                            </td>
                                        </tr>
                                        <tr class="hover:bg-[#003049]/5 transition-colors duration-200">
                                            <td class="px-3 sm:px-6 py-2 sm:py-3 whitespace-nowrap">
                                                <div class="text-xs sm:text-sm font-mono text-[#003049]">doctor.name</div>
                                            </td>
                                            <td class="px-3 sm:px-6 py-2 sm:py-3 whitespace-nowrap">
                                                <div class="text-xs sm:text-sm text-gray-700">string</div>
                                            </td>
                                            <td class="px-3 sm:px-6 py-2 sm:py-3 whitespace-nowrap">
                                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-[#991B1B]/10 text-[#991B1B] border border-[#991B1B]/20">Yes</span>
                                            </td>
                                            <td class="px-3 sm:px-6 py-2 sm:py-3">
                                                <div class="text-xs sm:text-sm text-gray-700">Full name of the referring doctor or clinic</div>
                                            </td>
                                        </tr>
                                        <tr class="hover:bg-[#003049]/5 transition-colors duration-200">
                                            <td class="px-3 sm:px-6 py-2 sm:py-3 whitespace-nowrap">
                                                <div class="text-xs sm:text-sm font-mono text-[#003049]">doctor.type</div>
                                            </td>
                                            <td class="px-3 sm:px-6 py-2 sm:py-3 whitespace-nowrap">
                                                <div class="text-xs sm:text-sm text-gray-700">string</div>
                                            </td>
                                            <td class="px-3 sm:px-6 py-2 sm:py-3 whitespace-nowrap">
                                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-600 border border-gray-200">No</span>
                                            </td>
                                            <td class="px-3 sm:px-6 py-2 sm:py-3">
                                                <div class="text-xs sm:text-sm text-gray-700">Type of referring entity (e.g., "clinic", "hospital")</div>
                                            </td>
                                        </tr>
                                        <tr class="hover:bg-[#003049]/5 transition-colors duration-200">
                                            <td class="px-3 sm:px-6 py-2 sm:py-3 whitespace-nowrap">
                                                <div class="text-xs sm:text-sm font-mono text-[#003049]">doctor.address</div>
                                            </td>
                                            <td class="px-3 sm:px-6 py-2 sm:py-3 whitespace-nowrap">
                                                <div class="text-xs sm:text-sm text-gray-700">string</div>
                                            </td>
                                            <td class="px-3 sm:px-6 py-2 sm:py-3 whitespace-nowrap">
                                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-600 border border-gray-200">No</span>
                                            </td>
                                            <td class="px-3 sm:px-6 py-2 sm:py-3">
                                                <div class="text-xs sm:text-sm text-gray-700">Physical address of the referring doctor or clinic</div>
                                            </td>
                                        </tr>
                                        <tr class="hover:bg-[#003049]/5 transition-colors duration-200">
                                            <td class="px-3 sm:px-6 py-2 sm:py-3 whitespace-nowrap">
                                                <div class="text-xs sm:text-sm font-mono text-[#003049]">doctor.phone</div>
                                            </td>
                                            <td class="px-3 sm:px-6 py-2 sm:py-3 whitespace-nowrap">
                                                <div class="text-xs sm:text-sm text-gray-700">string</div>
                                            </td>
                                            <td class="px-3 sm:px-6 py-2 sm:py-3 whitespace-nowrap">
                                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-600 border border-gray-200">No</span>
                                            </td>
                                            <td class="px-3 sm:px-6 py-2 sm:py-3">
                                                <div class="text-xs sm:text-sm text-gray-700">Contact phone number of the referring doctor or clinic</div>
                                            </td>
                                        </tr>
                                        <!-- Patient Object -->
                                        <tr class="bg-[#003049]/5">
                                            <td colspan="4" class="px-3 sm:px-6 py-2 sm:py-3 font-semibold text-[#003049] text-sm">Patient Object</td>
                                        </tr>
                                        <tr class="hover:bg-[#003049]/5 transition-colors duration-200">
                                            <td class="px-3 sm:px-6 py-2 sm:py-3 whitespace-nowrap">
                                                <div class="text-xs sm:text-sm font-mono text-[#003049]">patient.icno</div>
                                            </td>
                                            <td class="px-3 sm:px-6 py-2 sm:py-3 whitespace-nowrap">
                                                <div class="text-xs sm:text-sm text-gray-700">string</div>
                                            </td>
                                            <td class="px-3 sm:px-6 py-2 sm:py-3 whitespace-nowrap">
                                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-[#991B1B]/10 text-[#991B1B] border border-[#991B1B]/20">Yes</span>
                                            </td>
                                            <td class="px-3 sm:px-6 py-2 sm:py-3">
                                                <div class="text-xs sm:text-sm text-gray-700">Patient identity card number (NRIC or passport)</div>
                                            </td>
                                        </tr>
                                        <tr class="hover:bg-[#003049]/5 transition-colors duration-200">
                                            <td class="px-3 sm:px-6 py-2 sm:py-3 whitespace-nowrap">
                                                <div class="text-xs sm:text-sm font-mono text-[#003049]">patient.ic_type</div>
                                            </td>
                                            <td class="px-3 sm:px-6 py-2 sm:py-3 whitespace-nowrap">
                                                <div class="text-xs sm:text-sm text-gray-700">string</div>
                                            </td>
                                            <td class="px-3 sm:px-6 py-2 sm:py-3 whitespace-nowrap">
                                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-600 border border-gray-200">No</span>
                                            </td>
                                            <td class="px-3 sm:px-6 py-2 sm:py-3">
                                                <div class="text-xs sm:text-sm text-gray-700">Type of identity document (e.g., "NRIC", "PASSPORT")</div>
                                            </td>
                                        </tr>
                                        <tr class="hover:bg-[#003049]/5 transition-colors duration-200">
                                            <td class="px-3 sm:px-6 py-2 sm:py-3 whitespace-nowrap">
                                                <div class="text-xs sm:text-sm font-mono text-[#003049]">patient.name</div>
                                            </td>
                                            <td class="px-3 sm:px-6 py-2 sm:py-3 whitespace-nowrap">
                                                <div class="text-xs sm:text-sm text-gray-700">string</div>
                                            </td>
                                            <td class="px-3 sm:px-6 py-2 sm:py-3 whitespace-nowrap">
                                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-[#991B1B]/10 text-[#991B1B] border border-[#991B1B]/20">Yes</span>
                                            </td>
                                            <td class="px-3 sm:px-6 py-2 sm:py-3">
                                                <div class="text-xs sm:text-sm text-gray-700">Full name of the patient</div>
                                            </td>
                                        </tr>
                                        <tr class="hover:bg-[#003049]/5 transition-colors duration-200">
                                            <td class="px-3 sm:px-6 py-2 sm:py-3 whitespace-nowrap">
                                                <div class="text-xs sm:text-sm font-mono text-[#003049]">patient.age</div>
                                            </td>
                                            <td class="px-3 sm:px-6 py-2 sm:py-3 whitespace-nowrap">
                                                <div class="text-xs sm:text-sm text-gray-700">string</div>
                                            </td>
                                            <td class="px-3 sm:px-6 py-2 sm:py-3 whitespace-nowrap">
                                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-600 border border-gray-200">No</span>
                                            </td>
                                            <td class="px-3 sm:px-6 py-2 sm:py-3">
                                                <div class="text-xs sm:text-sm text-gray-700">Age of the patient in years</div>
                                            </td>
                                        </tr>
                                        <tr class="hover:bg-[#003049]/5 transition-colors duration-200">
                                            <td class="px-3 sm:px-6 py-2 sm:py-3 whitespace-nowrap">
                                                <div class="text-xs sm:text-sm font-mono text-[#003049]">patient.gender</div>
                                            </td>
                                            <td class="px-3 sm:px-6 py-2 sm:py-3 whitespace-nowrap">
                                                <div class="text-xs sm:text-sm text-gray-700">string</div>
                                            </td>
                                            <td class="px-3 sm:px-6 py-2 sm:py-3 whitespace-nowrap">
                                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-600 border border-gray-200">No</span>
                                            </td>
                                            <td class="px-3 sm:px-6 py-2 sm:py-3">
                                                <div class="text-xs sm:text-sm text-gray-700">Gender of the patient ("M" or "F")</div>
                                            </td>
                                        </tr>
                                        <tr class="hover:bg-[#003049]/5 transition-colors duration-200">
                                            <td class="px-3 sm:px-6 py-2 sm:py-3 whitespace-nowrap">
                                                <div class="text-xs sm:text-sm font-mono text-[#003049]">patient.tel</div>
                                            </td>
                                            <td class="px-3 sm:px-6 py-2 sm:py-3 whitespace-nowrap">
                                                <div class="text-xs sm:text-sm text-gray-700">string</div>
                                            </td>
                                            <td class="px-3 sm:px-6 py-2 sm:py-3 whitespace-nowrap">
                                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-600 border border-gray-200">No</span>
                                            </td>
                                            <td class="px-3 sm:px-6 py-2 sm:py-3">
                                                <div class="text-xs sm:text-sm text-gray-700">Contact phone number of the patient</div>
                                            </td>
                                        </tr>
                                        <!-- Results Structure -->
                                        <tr class="bg-[#003049]/5">
                                            <td colspan="4" class="px-3 sm:px-6 py-2 sm:py-3 font-semibold text-[#003049] text-sm">Results Structure</td>
                                        </tr>
                                        <tr class="hover:bg-[#003049]/5 transition-colors duration-200">
                                            <td class="px-3 sm:px-6 py-2 sm:py-3 whitespace-nowrap">
                                                <div class="text-xs sm:text-sm font-mono text-[#003049]">results</div>
                                            </td>
                                            <td class="px-3 sm:px-6 py-2 sm:py-3 whitespace-nowrap">
                                                <div class="text-xs sm:text-sm text-gray-700">object</div>
                                            </td>
                                            <td class="px-3 sm:px-6 py-2 sm:py-3 whitespace-nowrap">
                                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-[#991B1B]/10 text-[#991B1B] border border-[#991B1B]/20">Yes</span>
                                            </td>
                                            <td class="px-3 sm:px-6 py-2 sm:py-3">
                                                <div class="text-xs sm:text-sm text-gray-700">Map of panel name keys to panel result objects</div>
                                            </td>
                                        </tr>
                                        <tr class="hover:bg-[#003049]/5 transition-colors duration-200">
                                            <td class="px-3 sm:px-6 py-2 sm:py-3 whitespace-nowrap">
                                                <div class="text-xs sm:text-sm font-mono text-[#003049]">results.{panel_name}</div>
                                            </td>
                                            <td class="px-3 sm:px-6 py-2 sm:py-3 whitespace-nowrap">
                                                <div class="text-xs sm:text-sm text-gray-700">object</div>
                                            </td>
                                            <td class="px-3 sm:px-6 py-2 sm:py-3 whitespace-nowrap">
                                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-[#991B1B]/10 text-[#991B1B] border border-[#991B1B]/20">Yes</span>
                                            </td>
                                            <td class="px-3 sm:px-6 py-2 sm:py-3">
                                                <div class="text-xs sm:text-sm text-gray-700">Result object for a named panel (e.g., "Haematology")</div>
                                            </td>
                                        </tr>
                                        <tr class="hover:bg-[#003049]/5 transition-colors duration-200">
                                            <td class="px-3 sm:px-6 py-2 sm:py-3 whitespace-nowrap">
                                                <div class="text-xs sm:text-sm font-mono text-[#003049]">results.{panel_name}.panel_code</div>
                                            </td>
                                            <td class="px-3 sm:px-6 py-2 sm:py-3 whitespace-nowrap">
                                                <div class="text-xs sm:text-sm text-gray-700">string</div>
                                            </td>
                                            <td class="px-3 sm:px-6 py-2 sm:py-3 whitespace-nowrap">
                                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-600 border border-gray-200">No</span>
                                            </td>
                                            <td class="px-3 sm:px-6 py-2 sm:py-3">
                                                <div class="text-xs sm:text-sm text-gray-700">Short code identifying the panel</div>
                                            </td>
                                        </tr>
                                        <tr class="hover:bg-[#003049]/5 transition-colors duration-200">
                                            <td class="px-3 sm:px-6 py-2 sm:py-3 whitespace-nowrap">
                                                <div class="text-xs sm:text-sm font-mono text-[#003049]">results.{panel_name}.panel_sequence</div>
                                            </td>
                                            <td class="px-3 sm:px-6 py-2 sm:py-3 whitespace-nowrap">
                                                <div class="text-xs sm:text-sm text-gray-700">integer</div>
                                            </td>
                                            <td class="px-3 sm:px-6 py-2 sm:py-3 whitespace-nowrap">
                                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-600 border border-gray-200">No</span>
                                            </td>
                                            <td class="px-3 sm:px-6 py-2 sm:py-3">
                                                <div class="text-xs sm:text-sm text-gray-700">Display ordering sequence for the panel</div>
                                            </td>
                                        </tr>
                                        <tr class="hover:bg-[#003049]/5 transition-colors duration-200">
                                            <td class="px-3 sm:px-6 py-2 sm:py-3 whitespace-nowrap">
                                                <div class="text-xs sm:text-sm font-mono text-[#003049]">results.{panel_name}.panel_remarks</div>
                                            </td>
                                            <td class="px-3 sm:px-6 py-2 sm:py-3 whitespace-nowrap">
                                                <div class="text-xs sm:text-sm text-gray-700">string|null</div>
                                            </td>
                                            <td class="px-3 sm:px-6 py-2 sm:py-3 whitespace-nowrap">
                                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-600 border border-gray-200">No</span>
                                            </td>
                                            <td class="px-3 sm:px-6 py-2 sm:py-3">
                                                <div class="text-xs sm:text-sm text-gray-700">Free-text remarks for the panel; nullable</div>
                                            </td>
                                        </tr>
                                        <tr class="hover:bg-[#003049]/5 transition-colors duration-200">
                                            <td class="px-3 sm:px-6 py-2 sm:py-3 whitespace-nowrap">
                                                <div class="text-xs sm:text-sm font-mono text-[#003049]">results.{panel_name}.result_status</div>
                                            </td>
                                            <td class="px-3 sm:px-6 py-2 sm:py-3 whitespace-nowrap">
                                                <div class="text-xs sm:text-sm text-gray-700">integer</div>
                                            </td>
                                            <td class="px-3 sm:px-6 py-2 sm:py-3 whitespace-nowrap">
                                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-[#991B1B]/10 text-[#991B1B] border border-[#991B1B]/20">Yes</span>
                                            </td>
                                            <td class="px-3 sm:px-6 py-2 sm:py-3">
                                                <div class="text-xs sm:text-sm text-gray-700">Result status flag (1 = final)</div>
                                            </td>
                                        </tr>
                                        <tr class="hover:bg-[#003049]/5 transition-colors duration-200">
                                            <td class="px-3 sm:px-6 py-2 sm:py-3 whitespace-nowrap">
                                                <div class="text-xs sm:text-sm font-mono text-[#003049]">results.{panel_name}.tests</div>
                                            </td>
                                            <td class="px-3 sm:px-6 py-2 sm:py-3 whitespace-nowrap">
                                                <div class="text-xs sm:text-sm text-gray-700">array</div>
                                            </td>
                                            <td class="px-3 sm:px-6 py-2 sm:py-3 whitespace-nowrap">
                                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-[#991B1B]/10 text-[#991B1B] border border-[#991B1B]/20">Yes</span>
                                            </td>
                                            <td class="px-3 sm:px-6 py-2 sm:py-3">
                                                <div class="text-xs sm:text-sm text-gray-700">Array of individual test result objects within the panel</div>
                                            </td>
                                        </tr>
                                        <!-- Tests Array Items -->
                                        <tr class="bg-[#003049]/5">
                                            <td colspan="4" class="px-3 sm:px-6 py-2 sm:py-3 font-semibold text-[#003049] text-sm">Tests Array Items (results.{panel_name}.tests[])</td>
                                        </tr>
                                        <tr class="hover:bg-[#003049]/5 transition-colors duration-200">
                                            <td class="px-3 sm:px-6 py-2 sm:py-3 whitespace-nowrap">
                                                <div class="text-xs sm:text-sm font-mono text-[#003049]">tests[].test_code</div>
                                            </td>
                                            <td class="px-3 sm:px-6 py-2 sm:py-3 whitespace-nowrap">
                                                <div class="text-xs sm:text-sm text-gray-700">string</div>
                                            </td>
                                            <td class="px-3 sm:px-6 py-2 sm:py-3 whitespace-nowrap">
                                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-600 border border-gray-200">No</span>
                                            </td>
                                            <td class="px-3 sm:px-6 py-2 sm:py-3">
                                                <div class="text-xs sm:text-sm text-gray-700">Short code for the individual test. If provided, used directly as the test identifier; if null, one is auto-generated from the test name.</div>
                                            </td>
                                        </tr>
                                        <tr class="hover:bg-[#003049]/5 transition-colors duration-200">
                                            <td class="px-3 sm:px-6 py-2 sm:py-3 whitespace-nowrap">
                                                <div class="text-xs sm:text-sm font-mono text-[#003049]">tests[].test_name</div>
                                            </td>
                                            <td class="px-3 sm:px-6 py-2 sm:py-3 whitespace-nowrap">
                                                <div class="text-xs sm:text-sm text-gray-700">string</div>
                                            </td>
                                            <td class="px-3 sm:px-6 py-2 sm:py-3 whitespace-nowrap">
                                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-[#991B1B]/10 text-[#991B1B] border border-[#991B1B]/20">Yes</span>
                                            </td>
                                            <td class="px-3 sm:px-6 py-2 sm:py-3">
                                                <div class="text-xs sm:text-sm text-gray-700">Human-readable name of the individual test</div>
                                            </td>
                                        </tr>
                                        <tr class="hover:bg-[#003049]/5 transition-colors duration-200">
                                            <td class="px-3 sm:px-6 py-2 sm:py-3 whitespace-nowrap">
                                                <div class="text-xs sm:text-sm font-mono text-[#003049]">tests[].result_value</div>
                                            </td>
                                            <td class="px-3 sm:px-6 py-2 sm:py-3 whitespace-nowrap">
                                                <div class="text-xs sm:text-sm text-gray-700">string</div>
                                            </td>
                                            <td class="px-3 sm:px-6 py-2 sm:py-3 whitespace-nowrap">
                                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-[#991B1B]/10 text-[#991B1B] border border-[#991B1B]/20">Yes</span>
                                            </td>
                                            <td class="px-3 sm:px-6 py-2 sm:py-3">
                                                <div class="text-xs sm:text-sm text-gray-700">The result value as a string</div>
                                            </td>
                                        </tr>
                                        <tr class="hover:bg-[#003049]/5 transition-colors duration-200">
                                            <td class="px-3 sm:px-6 py-2 sm:py-3 whitespace-nowrap">
                                                <div class="text-xs sm:text-sm font-mono text-[#003049]">tests[].result_flag</div>
                                            </td>
                                            <td class="px-3 sm:px-6 py-2 sm:py-3 whitespace-nowrap">
                                                <div class="text-xs sm:text-sm text-gray-700">string|null</div>
                                            </td>
                                            <td class="px-3 sm:px-6 py-2 sm:py-3 whitespace-nowrap">
                                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-600 border border-gray-200">No</span>
                                            </td>
                                            <td class="px-3 sm:px-6 py-2 sm:py-3">
                                                <div class="text-xs sm:text-sm text-gray-700">Abnormality flag (e.g., "H", "L"); nullable</div>
                                            </td>
                                        </tr>
                                        <tr class="hover:bg-[#003049]/5 transition-colors duration-200">
                                            <td class="px-3 sm:px-6 py-2 sm:py-3 whitespace-nowrap">
                                                <div class="text-xs sm:text-sm font-mono text-[#003049]">tests[].unit</div>
                                            </td>
                                            <td class="px-3 sm:px-6 py-2 sm:py-3 whitespace-nowrap">
                                                <div class="text-xs sm:text-sm text-gray-700">string</div>
                                            </td>
                                            <td class="px-3 sm:px-6 py-2 sm:py-3 whitespace-nowrap">
                                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-600 border border-gray-200">No</span>
                                            </td>
                                            <td class="px-3 sm:px-6 py-2 sm:py-3">
                                                <div class="text-xs sm:text-sm text-gray-700">Unit of measurement (e.g., "g/dL")</div>
                                            </td>
                                        </tr>
                                        <tr class="hover:bg-[#003049]/5 transition-colors duration-200">
                                            <td class="px-3 sm:px-6 py-2 sm:py-3 whitespace-nowrap">
                                                <div class="text-xs sm:text-sm font-mono text-[#003049]">tests[].ref_range</div>
                                            </td>
                                            <td class="px-3 sm:px-6 py-2 sm:py-3 whitespace-nowrap">
                                                <div class="text-xs sm:text-sm text-gray-700">string</div>
                                            </td>
                                            <td class="px-3 sm:px-6 py-2 sm:py-3 whitespace-nowrap">
                                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-600 border border-gray-200">No</span>
                                            </td>
                                            <td class="px-3 sm:px-6 py-2 sm:py-3">
                                                <div class="text-xs sm:text-sm text-gray-700">Reference range string</div>
                                            </td>
                                        </tr>
                                        <tr class="hover:bg-[#003049]/5 transition-colors duration-200">
                                            <td class="px-3 sm:px-6 py-2 sm:py-3 whitespace-nowrap">
                                                <div class="text-xs sm:text-sm font-mono text-[#003049]">tests[].test_note</div>
                                            </td>
                                            <td class="px-3 sm:px-6 py-2 sm:py-3 whitespace-nowrap">
                                                <div class="text-xs sm:text-sm text-gray-700">string|null</div>
                                            </td>
                                            <td class="px-3 sm:px-6 py-2 sm:py-3 whitespace-nowrap">
                                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-600 border border-gray-200">No</span>
                                            </td>
                                            <td class="px-3 sm:px-6 py-2 sm:py-3">
                                                <div class="text-xs sm:text-sm text-gray-700">Additional note for the test; nullable</div>
                                            </td>
                                        </tr>
                                        <tr class="hover:bg-[#003049]/5 transition-colors duration-200">
                                            <td class="px-3 sm:px-6 py-2 sm:py-3 whitespace-nowrap">
                                                <div class="text-xs sm:text-sm font-mono text-[#003049]">tests[].report_sequence</div>
                                            </td>
                                            <td class="px-3 sm:px-6 py-2 sm:py-3 whitespace-nowrap">
                                                <div class="text-xs sm:text-sm text-gray-700">integer</div>
                                            </td>
                                            <td class="px-3 sm:px-6 py-2 sm:py-3 whitespace-nowrap">
                                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-600 border border-gray-200">No</span>
                                            </td>
                                            <td class="px-3 sm:px-6 py-2 sm:py-3">
                                                <div class="text-xs sm:text-sm text-gray-700">Display ordering sequence within the panel</div>
                                            </td>
                                        </tr>
                                        <tr class="hover:bg-[#003049]/5 transition-colors duration-200">
                                            <td class="px-3 sm:px-6 py-2 sm:py-3 whitespace-nowrap">
                                                <div class="text-xs sm:text-sm font-mono text-[#003049]">tests[].decimal_point</div>
                                            </td>
                                            <td class="px-3 sm:px-6 py-2 sm:py-3 whitespace-nowrap">
                                                <div class="text-xs sm:text-sm text-gray-700">integer</div>
                                            </td>
                                            <td class="px-3 sm:px-6 py-2 sm:py-3 whitespace-nowrap">
                                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-600 border border-gray-200">No</span>
                                            </td>
                                            <td class="px-3 sm:px-6 py-2 sm:py-3">
                                                <div class="text-xs sm:text-sm text-gray-700">Number of decimal places to display for the result</div>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="mb-3">
                            <span class="font-semibold text-sm pb-1 tracking-wide block">Headers:</span>
                            <pre class="bg-[#003049]/5 border border-[#003049]/10 p-3 rounded text-xs font-mono overflow-x-auto text-[#003049]"><code>Authorization: Bearer {your_jwt_token}
Content-Type: application/json
Accept: application/json</code></pre>
                        </div>

                        <div class="mb-3 print-page-break">
                            <span class="font-semibold text-sm pb-1 tracking-wide block">Request Body Example:</span>
                            <pre class="bg-[#003049]/5 border border-[#003049]/10 p-3 rounded text-xs font-mono overflow-x-auto text-[#003049]"><code>{
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
  "report_status": true,
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

                        <div class="mb-3 print-page-break">
                            <span class="font-semibold text-sm pb-1 tracking-wide block">CURL Example:</span>
                            <pre class="bg-[#003049]/5 border border-[#003049]/10 p-3 rounded text-xs font-mono overflow-x-auto text-[#003049]"><code>curl -X POST "https://mytotalhealth.com.my/staging/api/v1/result/patient" \
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
     }'</code></pre>
                        </div>

                        <div class="mb-3">
                            <div class="bg-blue-50 border border-blue-200 rounded-lg p-3">
                                <div class="text-xs font-semibold text-blue-800 uppercase tracking-wide mb-1">Amendment Behaviour</div>
                                <div class="text-xs text-blue-800">
                                    Submitting the same lab number more than once is supported. Behaviour depends on the previous <span class="font-mono">report_status</span>:
                                    <ul class="list-disc pl-4 mt-1 space-y-1">
                                        <li><strong>No existing record</strong> — created fresh.</li>
                                        <li><strong>Existing record, previous <span class="font-mono">report_status = false</span> (partial)</strong> — replaced in place. No amendment flag set.</li>
                                        <li><strong>Existing record, previous <span class="font-mono">report_status = true</span> (final)</strong> — a new record is created alongside the original with <span class="font-mono">has_amended = true</span>. The original is preserved for audit purposes.</li>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        <div class="mb-2">
                            <span class="font-semibold text-sm pb-1 tracking-wide block">Response Examples:</span>
                            <div class="mb-2">
                                <span class="bg-green-100 text-green-800 px-2 py-1 rounded text-xs font-bold mr-2">200
                                    Success</span>
                                <pre class="bg-[#003049]/5 border border-[#003049]/10 p-2 rounded text-xs font-mono mt-2 text-[#003049]"><code>{
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
                                <pre class="bg-[#003049]/5 border border-[#003049]/10 p-2 rounded text-xs font-mono mt-2 text-[#003049]"><code>{
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
                                    Server Error</span>
                                <pre class="bg-[#003049]/5 border border-[#003049]/10 p-2 rounded text-xs font-mono mt-2 text-[#003049]"><code>{
  "success": false,
  "message": "Failed to process lab results",
  "error": "Internal server error"
}</code></pre>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        @if ($lab_id != 1 && $lab_id != 2 && $lab_id != 3)
            <!-- No Access Message -->
            <div class="bg-gray-50 rounded-2xl shadow-lg border border-[#003049]/10 p-8 text-center">
                <div class="w-16 h-16 bg-[#003049]/10 rounded-full flex items-center justify-center mx-auto mb-4">
                    <svg class="w-8 h-8 text-[#003049]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                    </svg>
                </div>
                <h3 class="text-lg font-semibold text-[#003049] mb-2">🚫 Access Restricted</h3>
                <p class="text-gray-600 text-sm">
                    Your lab (ID: {{ $lab_id ?? 'Unknown' }}) does not have access to result management endpoints.
                    Contact your system administrator if you need access to submit lab results.
                </p>
            </div>
        @endif

        <!-- Other Endpoints -->
        <div class="print-hide print-section bg-white rounded-2xl shadow-lg border border-[#003049]/10 overflow-hidden">
            <div class="p-4 sm:p-6 border-b border-[#003049]/10">
                <div class="flex items-center gap-3 mb-4">
                    <div class="w-10 h-10 bg-[#003049]/10 rounded-xl flex items-center justify-center">
                        <svg class="w-6 h-6 text-[#003049]" fill="none" stroke="currentColor"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                        </svg>
                    </div>
                    <span class="text-lg sm:text-xl font-semibold text-[#003049]">Other Endpoints</span>
                </div>
            </div>
            <div class="p-4 sm:p-6">
                <div class="space-y-6">
                    <!-- Get Result Endpoint -->
                    <div>
                        <div class="flex items-center mb-2">
                            <span class="bg-green-600 text-white px-2 py-1 rounded text-xs font-bold mr-2">GET</span>
                            <span class="font-mono text-sm">/api/v1/result/{id}</span>
                        </div>
                        <span class="font-normal text-sm tracking-wide block mb-3">Get a specific test result with all
                            associated data including patient, doctor, panel results and test items.</span>

                        <div class="mb-3">
                            <span class="font-semibold text-sm pb-1 tracking-wide block">URL Parameters:</span>
                            <ul class="list-disc pl-5">
                                <li class="font-normal text-sm tracking-wide"><span
                                        class="font-mono text-sm">id</span> (integer, required): Test result ID</li>
                            </ul>
                        </div>

                        <div class="mb-2">
                            <span class="font-semibold text-sm pb-1 tracking-wide block">CURL Example:</span>
                            <pre class="bg-[#003049]/5 border border-[#003049]/10 p-3 rounded text-xs font-mono overflow-x-auto text-[#003049]"><code>curl -X GET "https://mytotalhealth.com.my/staging/api/v1/result/123" \
     -H "Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9..."</code></pre>
                        </div>
                    </div>

                </div>
            </div>
        </div>

    </div>
