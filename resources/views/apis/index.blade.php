<x-app-layout>
    <!-- Header Section -->
    <div class="mb-6 sm:mb-8">
        <div class="flex items-center gap-3 mb-4 sm:mb-6">
            <div class="w-10 h-10 sm:w-12 sm:h-12 bg-gradient-to-r from-blue-600 to-blue-800 rounded-xl flex items-center justify-center">
                <svg class="w-6 h-6 sm:w-8 sm:h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4" />
                </svg>
            </div>
            <div>
                <h1 class="text-xl sm:text-2xl font-bold text-gray-900">API Documentation</h1>
                <p class="text-gray-600 text-xs sm:text-sm">Complete BloodStream API reference and examples</p>
            </div>
        </div>
    </div>

    <!-- Quick Access -->
    <div class="mb-6 sm:mb-8">
        <div class="bg-gradient-to-br from-emerald-50 to-teal-100 rounded-2xl shadow-lg p-4 sm:p-6 border border-emerald-200">
            <div class="flex items-center gap-3 mb-4">
                <div class="w-10 h-10 bg-emerald-500 rounded-xl flex items-center justify-center">
                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
                    </svg>
                </div>
                <h3 class="text-lg font-semibold text-emerald-800">Quick Access</h3>
            </div>
            <div class="space-y-3">
                <a href="api/documentation"
                    class="block p-3 bg-gradient-to-r from-orange-400 to-red-500 rounded-lg hover:from-orange-500 hover:to-red-600 transition-all duration-300 transform hover:scale-105 shadow-md hover:shadow-lg">
                    <div class="text-sm font-medium text-white flex items-center">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                        </svg>
                        Swagger Documentation
                    </div>
                    <div class="text-xs text-orange-100">Interactive API explorer</div>
                </a>
                <div class="p-3 bg-white rounded-lg shadow-sm border border-emerald-200">
                    <div class="text-sm font-medium text-emerald-800">Lab ID: {{ $lab_id ?? 'N/A' }}</div>
                    <div class="text-xs text-emerald-600">Current session</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Base URL and Authentication - 2 Columns -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 sm:gap-6 mb-6 sm:mb-8">
        <!-- Base URL Section -->
        <div class="bg-white rounded-2xl shadow-lg border border-gray-100 overflow-hidden">
            <div class="bg-gray-50 border-l-4 border-gray-500 p-3 sm:p-4">
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
                            📌 <strong>Note:</strong> These URLs are accessible over the internet. Ensure your system
                            can
                            make outbound HTTPS requests
                            to the above domains. An arrangement can be made if an external lab requires a VPN or static
                            IP
                            whitelisting upon request.
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Authentication Section -->
        <div class="bg-white rounded-2xl shadow-lg border border-gray-100 overflow-hidden">
            <div class="bg-blue-50 border-l-4 border-blue-500 p-3 sm:p-4 h-full">
                <span class="font-semibold text-md pb-2 tracking-wide block text-blue-800">Authentication</span>
                <span class="font-normal text-sm text-justify tracking-wide block mb-3">
                    Each laboratory is assigned exactly one unique username and password, which are used to log in and
                    generate
                    a secure access token. After logging in, a JWT token is issued and must be included in all
                    subsequent
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
        </div>
    </div>

    <!-- Description -->
    <div class="bg-gradient-to-r from-blue-600 to-blue-800 rounded-2xl shadow-lg p-4 sm:p-6 border border-blue-200 mb-6 sm:mb-8">
        <div class="flex flex-col sm:flex-row justify-start sm:items-end mb-4">
            <div class="flex items-center mb-2 sm:mb-0">
                <img src="{{ asset('logo.svg') }}" class="w-6 h-6 sm:w-8 sm:h-8 opacity-90 mr-2 filter brightness-0 invert" />
                <span class="font-semibold text-base sm:text-lg tracking-wide text-white">BloodStream v1 - API Documentation</span>
            </div>
        </div>
        <span class="font-normal text-sm text-justify tracking-wide text-blue-100">
            BloodStream is a centralized middleware system designed to act as a secure and customizable bridge
            between your organization and external laboratories. Its core function is to collect, normalize, and
            centralize patient blood test results, enabling healthcare professionals to review, analyze, and act
            upon laboratory findings efficiently.
        </span>
    </div>

    @include('components.api-endpoints')
</x-app-layout>