<x-app-layout>
    <!-- Header Section -->
    <div class="mb-6 sm:mb-8">
        <div class="flex items-center gap-3 mb-4 sm:mb-6">
            <div class="w-12 h-12 sm:w-14 sm:h-14 bg-[#003049] rounded-xl flex items-center justify-center shadow-md">
                <svg class="w-6 h-6 sm:w-7 sm:h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4" />
                </svg>
            </div>
            <div>
                <h1 class="text-xl sm:text-2xl font-bold text-[#003049]">API Documentation</h1>
                <p class="text-gray-600 text-xs sm:text-sm">Complete BloodStream API reference and examples</p>
            </div>
        </div>
    </div>

    <!-- Stats Cards
    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4 sm:gap-6 mb-6 sm:mb-8">
        <div class="bg-white rounded-2xl shadow-lg p-4 sm:p-6 border border-[#003049]/10 hover:shadow-xl transition-all duration-300 transform hover:scale-105">
            <div class="flex items-center gap-3">
                <div class="w-12 h-12 bg-[#003049]/10 rounded-xl flex items-center justify-center">
                    <svg class="w-6 h-6 text-[#003049]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                    </svg>
                </div>
                <div>
                    <h3 class="text-xl font-bold text-[#003049]">3</h3>
                    <p class="text-sm text-gray-600 font-medium">Total Endpoints</p>
                </div>
            </div>
        </div>
        
        <div class="bg-white rounded-2xl shadow-lg p-4 sm:p-6 border border-[#003049]/10 hover:shadow-xl transition-all duration-300 transform hover:scale-105">
            <div class="flex items-center gap-3">
                <div class="w-12 h-12 bg-[#991B1B]/10 rounded-xl flex items-center justify-center">
                    <svg class="w-6 h-6 text-[#991B1B]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.5-2A8.5 8.5 0 119.5 3a8.5 8.5 0 010 17 8.5 8.5 0 01-.5-17z" />
                    </svg>
                </div>
                <div>
                    <h3 class="text-xl font-bold text-[#003049]">{{ $lab_id ?? 'N/A' }}</h3>
                    <p class="text-sm text-gray-600 font-medium">Current Lab ID</p>
                </div>
            </div>
        </div>
        
        <div class="bg-white rounded-2xl shadow-lg p-4 sm:p-6 border border-[#003049]/10 hover:shadow-xl transition-all duration-300 transform hover:scale-105">
            <div class="flex items-center gap-3">
                <div class="w-12 h-12 bg-[#003049]/10 rounded-xl flex items-center justify-center">
                    <svg class="w-6 h-6 text-[#003049]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                    </svg>
                </div>
                <div>
                    <h3 class="text-xl font-bold text-[#003049]">JWT</h3>
                    <p class="text-sm text-gray-600 font-medium">Authentication</p>
                </div>
            </div>
        </div>
    </div>-->

    <!-- Content Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6 sm:mb-8">
        <!-- API Summary
        <div class="lg:col-span-2 bg-white rounded-2xl shadow-lg border border-[#003049]/10 overflow-hidden">
            <div class="p-4 sm:p-6 border-b border-[#003049]/10">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 bg-[#003049]/10 rounded-xl flex items-center justify-center">
                            <svg class="w-6 h-6 text-[#003049]" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                            </svg>
                        </div>
                        <h2 class="text-lg sm:text-xl font-semibold text-[#003049]">BloodStream API v1</h2>
                    </div>
                    <span class="text-xs sm:text-sm text-gray-500 bg-gray-50 px-3 py-1 rounded-full">REST API</span>
                </div>
            </div>
            <div class="p-4 sm:p-6">
                <p class="text-gray-700 mb-4">
                    BloodStream is a centralized middleware system designed to act as a secure and customizable bridge
                    between your organization and external laboratories. Its core function is to collect, normalize, and
                    centralize patient blood test results.
                </p>
                <div class="grid grid-cols-2 gap-4">
                    <div class="bg-[#003049]/5 rounded-lg p-3">
                        <div class="text-sm font-medium text-[#003049] mb-1">Base URL</div>
                        <div class="text-xs text-gray-600">mytotalhealth.com.my</div>
                    </div>
                    <div class="bg-[#991B1B]/5 rounded-lg p-3">
                        <div class="text-sm font-medium text-[#991B1B] mb-1">Version</div>
                        <div class="text-xs text-gray-600">/api/v1</div>
                    </div>
                </div>
            </div>
        </div>-->
        <div class="lg:col-span-2 bg-white rounded-2xl shadow-lg border border-[#003049]/10 overflow-hidden">
            <div class="p-4 sm:p-6 border-b border-[#003049]/10">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-[#003049]/10 rounded-xl flex items-center justify-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 text-[#003049]" fill="none"
                            stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M11.35 3.836c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m8.9-4.414c.376.023.75.05 1.124.08 1.131.094 1.976 1.057 1.976 2.192V16.5A2.25 2.25 0 0 1 18 18.75h-2.25m-7.5-10.5H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V18.75m-7.5-10.5h6.375c.621 0 1.125.504 1.125 1.125v9.375m-8.25-3 1.5 1.5 3-3.75" />
                        </svg>

                    </div>
                    <h2 class="text-lg sm:text-xl font-semibold text-[#003049]">Environment & Authentication</h2>
                </div>
            </div>
            <div class="p-4 sm:p-6">
                <div class="space-y-6">
                    <!-- Base URLs -->
                    <div>
                        <h3 class="text-md font-semibold text-[#003049] mb-3">Available Environments</h3>
                        <div class="space-y-3 mb-4">
                            <div
                                class="flex items-center justify-between p-3 bg-[#991B1B]/5 rounded-lg border border-[#991B1B]/10">
                                <div>
                                    <div class="text-sm font-medium text-[#991B1B]">Staging</div>
                                    <div class="text-xs text-gray-600 font-mono">https://mytotalhealth.com.my/staging
                                    </div>
                                </div>
                                <div class="px-2 py-1 bg-[#991B1B]/10 rounded text-xs font-medium text-[#991B1B]">DEV
                                </div>
                            </div>
                            <div
                                class="flex items-center justify-between p-3 bg-[#003049]/5 rounded-lg border border-[#003049]/10">
                                <div>
                                    <div class="text-sm font-medium text-[#003049]">Production</div>
                                    <div class="text-xs text-gray-600 font-mono">
                                        https://mytotalhealth.com.my/production
                                    </div>
                                </div>
                                <div class="px-2 py-1 bg-[#003049]/10 rounded text-xs font-medium text-[#003049]">PROD
                                </div>
                            </div>
                        </div>
                        <div class="bg-amber-50 border border-amber-200 rounded-lg p-3">
                            <div class="text-xs text-amber-800">
                                <strong>Note:</strong> All endpoints use <span class="font-mono">/api/v1</span> prefix
                                and
                                are accessible over HTTPS.
                            </div>
                        </div>
                    </div>
                    <!-- Authentication -->
                    <div>
                        <h3 class="text-md font-semibold text-[#003049] mb-3">Authentication</h3>
                        <div class="space-y-3 mb-4">
                            <div class="p-3 bg-gray-50 rounded-lg">
                                <div class="text-sm font-medium text-gray-900 mb-1">Authorization Header</div>
                                <div class="font-mono text-xs bg-white border rounded p-2 text-[#003049]">
                                    Authorization: Bearer {jwt_token}
                                </div>
                            </div>
                            <div class="grid grid-cols-2 gap-3">
                                <div class="text-center p-3 bg-[#003049]/5 rounded-lg">
                                    <div class="text-lg font-bold text-[#003049]">JWT</div>
                                    <div class="text-xs text-gray-600">Token Type</div>
                                </div>
                                <div class="text-center p-3 bg-[#991B1B]/5 rounded-lg">
                                    <div class="text-lg font-bold text-[#991B1B]">30</div>
                                    <div class="text-xs text-gray-600">Days Expiry</div>
                                </div>
                            </div>
                        </div>
                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-3">
                            <div class="text-xs text-blue-800">
                                <strong>Security:</strong> Store tokens securely and implement proper refresh
                                mechanisms.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- Quick Access & System Info -->
        <div class="space-y-6">
            <!-- Session Info -->
            <div class="bg-white rounded-2xl shadow-lg p-4 sm:p-6 border border-[#003049]/10">
                <div class="flex items-center gap-3 mb-4">
                    <div class="w-10 h-10 bg-[#991B1B]/10 rounded-xl flex items-center justify-center">
                        <svg class="w-6 h-6 text-[#991B1B]" fill="none" stroke="currentColor"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                        </svg>
                    </div>
                    <h3 class="text-lg font-semibold text-[#003049]">Session Info</h3>
                </div>
                <div class="space-y-4">
                    <div class="flex justify-between items-center py-2 border-b border-gray-100">
                        <span class="text-sm text-gray-600">Current Lab ID</span>
                        <span
                            class="text-sm font-medium text-[#003049] bg-[#003049]/5 px-2 py-1 rounded">{{ $lab_id ?? 'N/A' }}</span>
                    </div>
                    <div class="flex justify-between items-center py-2 border-b border-gray-100">
                        <span class="text-sm text-gray-600">Authentication</span>
                        <span class="text-sm font-medium text-[#991B1B] bg-[#991B1B]/10 px-2 py-1 rounded">JWT
                            Token</span>
                    </div>
                    <div class="flex justify-between items-center py-2">
                        <span class="text-sm text-gray-600">API Version</span>
                        <span class="text-sm font-medium text-[#003049] bg-[#003049]/5 px-2 py-1 rounded">v1</span>
                    </div>
                </div>
            </div>

            <!-- Quick Access -->
            <div class="bg-white rounded-2xl shadow-lg p-4 sm:p-6 border border-[#003049]/10">
                <div class="flex items-center gap-3 mb-4">
                    <div class="w-10 h-10 bg-[#003049] rounded-xl flex items-center justify-center shadow-md">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
                        </svg>
                    </div>
                    <h3 class="text-lg font-semibold text-[#003049]">Quick Access</h3>
                </div>
                <div class="space-y-3">
                    <a href="api/documentation"
                        class="block p-3 bg-gradient-to-r from-[#047857] to-[#065F46] rounded-lg hover:from-[#059669] hover:to-[#047857] transition-all duration-300 transform hover:scale-105 shadow-md hover:shadow-lg">
                        <div class="text-sm font-medium text-white flex items-center">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                            </svg>
                            Swagger Documentation
                        </div>
                        <div class="text-xs text-white/80">Interactive API explorer</div>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Environment & Authentication Info
    <div class="bg-white rounded-2xl shadow-lg border border-[#003049]/10 overflow-hidden mb-6 sm:mb-8">
    </div>-->

    @include('components.api-endpoints')
</x-app-layout>
