<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel Results Testing</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .json-editor {
            font-family: 'Courier New', monospace;
            font-size: 14px;
        }
    </style>
</head>
<body class="bg-gray-50">
    <div class="container mx-auto px-4 py-8 max-w-6xl">
        <div class="bg-white rounded-lg shadow-lg p-6">
            <div class="flex items-center mb-6">
                <h1 class="text-2xl font-bold text-gray-800">Panel Results API Testing</h1>
                <span class="ml-4 bg-blue-100 text-blue-800 px-3 py-1 rounded-full text-sm">POST /api/v1/result/panel</span>
            </div>

            <!-- Login Section -->
            <div class="bg-gray-50 rounded-lg p-4 mb-6">
                <h2 class="text-lg font-semibold text-gray-800 mb-3">Authentication</h2>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Username</label>
                        <input type="text" id="login-username" placeholder="LAB001user" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                        <input type="password" id="login-password" placeholder="Enter password" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm">
                    </div>
                    <div>
                        <button onclick="login()" class="w-full bg-green-600 text-white py-2 px-4 rounded-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 font-medium text-sm">
                            Get Token
                        </button>
                    </div>
                </div>
                <div id="login-status" class="mt-3"></div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Request Section -->
                <div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Authorization Token</label>
                        <input type="text" id="auth-token" placeholder="Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9..." 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <p class="text-xs text-gray-500 mt-1">Enter your JWT token (with or without 'Bearer ' prefix)</p>
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">JSON Request Body</label>
                        <textarea id="json-input" rows="20" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 json-editor" 
                                  placeholder="Enter your JSON payload here..."></textarea>
                        <div class="flex mt-2 space-x-2">
                            <button onclick="formatJson()" class="px-3 py-1 bg-gray-500 text-white rounded text-sm hover:bg-gray-600">Format JSON</button>
                            <button onclick="loadSample()" class="px-3 py-1 bg-green-500 text-white rounded text-sm hover:bg-green-600">Load Sample</button>
                            <button onclick="clearJson()" class="px-3 py-1 bg-red-500 text-white rounded text-sm hover:bg-red-600">Clear</button>
                        </div>
                    </div>

                    <button onclick="sendRequest()" class="w-full bg-blue-600 text-white py-3 px-4 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 font-medium">
                        Send Request
                    </button>
                </div>

                <!-- Response Section -->
                <div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Response</label>
                        <div id="response-status" class="mb-2"></div>
                        <pre id="response-output" class="w-full h-96 px-3 py-2 border border-gray-300 rounded-md bg-gray-50 overflow-auto json-editor text-sm"></pre>
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Request Info</label>
                        <pre id="request-info" class="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-50 overflow-auto text-xs"></pre>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Set up CSRF token for AJAX requests
        const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

        async function login() {
            const username = document.getElementById('login-username').value.trim();
            const password = document.getElementById('login-password').value.trim();
            const loginStatus = document.getElementById('login-status');

            // Clear previous status
            loginStatus.innerHTML = '';

            // Validate inputs
            if (!username || !password) {
                loginStatus.innerHTML = `<div class="px-3 py-2 bg-red-100 text-red-800 rounded text-sm">Please enter both username and password</div>`;
                return;
            }

            try {
                loginStatus.innerHTML = `<div class="px-3 py-2 bg-blue-100 text-blue-800 rounded text-sm">Logging in...</div>`;

                const response = await fetch('https://mytotalhealth.com.my/staging/api/v1/login', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': token
                    },
                    body: JSON.stringify({
                        username: username,
                        password: password
                    })
                });

                const responseData = await response.json();

                if (response.ok && responseData.success) {
                    // Success - extract token and populate auth field
                    const accessToken = responseData.data.access_token;
                    document.getElementById('auth-token').value = accessToken;
                    
                    const expiresIn = responseData.data.expires_in;
                    const expiryDate = new Date(Date.now() + (expiresIn * 1000));
                    
                    loginStatus.innerHTML = `<div class="px-3 py-2 bg-green-100 text-green-800 rounded text-sm">
                        ✅ Login successful! Token expires: ${expiryDate.toLocaleString()}
                        <br><small class="text-xs">Token has been automatically filled in the authorization field below.</small>
                    </div>`;
                } else {
                    // Error response
                    const errorMessage = responseData.message || 'Login failed';
                    loginStatus.innerHTML = `<div class="px-3 py-2 bg-red-100 text-red-800 rounded text-sm">
                        ❌ ${errorMessage}
                    </div>`;
                }
            } catch (error) {
                loginStatus.innerHTML = `<div class="px-3 py-2 bg-red-100 text-red-800 rounded text-sm">
                    ❌ Network error: ${error.message}
                </div>`;
            }
        }

        function formatJson() {
            const textarea = document.getElementById('json-input');
            try {
                const parsed = JSON.parse(textarea.value);
                textarea.value = JSON.stringify(parsed, null, 2);
            } catch (e) {
                alert('Invalid JSON format');
            }
        }

        function clearJson() {
            document.getElementById('json-input').value = '';
            document.getElementById('response-output').textContent = '';
            document.getElementById('response-status').innerHTML = '';
            document.getElementById('request-info').textContent = '';
        }

        function loadSample() {
            const sampleJson = {
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
            };

            document.getElementById('json-input').value = JSON.stringify(sampleJson, null, 2);
        }

        async function sendRequest() {
            const jsonInput = document.getElementById('json-input').value.trim();
            const authToken = document.getElementById('auth-token').value.trim();
            const responseOutput = document.getElementById('response-output');
            const responseStatus = document.getElementById('response-status');
            const requestInfo = document.getElementById('request-info');

            // Clear previous results
            responseOutput.textContent = '';
            responseStatus.innerHTML = '';
            requestInfo.textContent = '';

            // Validate inputs
            if (!jsonInput) {
                alert('Please enter JSON payload');
                return;
            }

            if (!authToken) {
                alert('Please enter authorization token');
                return;
            }

            // Validate JSON
            let jsonData;
            try {
                jsonData = JSON.parse(jsonInput);
            } catch (e) {
                alert('Invalid JSON format: ' + e.message);
                return;
            }

            // Prepare headers
            const headers = {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': token
            };

            // Add authorization header
            const bearerToken = authToken.startsWith('Bearer ') ? authToken : `Bearer ${authToken}`;
            headers['Authorization'] = bearerToken;

            // Show request info
            const requestDetails = {
                url: 'https://mytotalhealth.com.my/staging/api/v1/result/panel',
                method: 'POST',
                headers: headers,
                timestamp: new Date().toISOString()
            };
            requestInfo.textContent = JSON.stringify(requestDetails, null, 2);

            try {
                const response = await fetch('https://mytotalhealth.com.my/staging/api/v1/result/panel', {
                    method: 'POST',
                    headers: headers,
                    body: JSON.stringify(jsonData)
                });

                const statusClass = response.ok ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800';
                responseStatus.innerHTML = `<span class="px-3 py-1 rounded-full text-sm font-medium ${statusClass}">
                    ${response.status} ${response.statusText}
                </span>`;

                let responseData;
                const contentType = response.headers.get('content-type');
                if (contentType && contentType.includes('application/json')) {
                    responseData = await response.json();
                    responseOutput.textContent = JSON.stringify(responseData, null, 2);
                } else {
                    responseData = await response.text();
                    responseOutput.textContent = responseData;
                }

            } catch (error) {
                responseStatus.innerHTML = `<span class="px-3 py-1 rounded-full text-sm font-medium bg-red-100 text-red-800">
                    Network Error
                </span>`;
                responseOutput.textContent = `Error: ${error.message}`;
            }
        }

        // Load sample data on page load
        window.onload = function() {
            loadSample();
        };
    </script>
</body>
</html>