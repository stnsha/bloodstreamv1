<x-app-layout>
    <!-- Welcome Section -->
    <div class="mb-6 sm:mb-8">
        <div class="bg-white rounded-2xl shadow-xl p-6 sm:p-8 border border-[#003049]/10 relative overflow-hidden">
            <div
                class="absolute top-0 right-0 w-32 h-32 bg-[#003049] rounded-full opacity-10 transform translate-x-16 -translate-y-16">
            </div>
            <div
                class="absolute bottom-0 left-0 w-24 h-24 bg-[#991B1B] rounded-full opacity-10 transform -translate-x-12 translate-y-12">
            </div>
            <div class="relative flex flex-col sm:flex-row sm:items-center sm:justify-between">
                <div class="mb-4 w-2/3 sm:mb-0">
                    <h1 class="text-2xl sm:text-3xl font-bold text-[#003049] mb-2">Welcome back, {{ $user_name }}!
                    </h1>
                    <p class="text-gray-600 text-sm sm:text-base">BloodStream is a centralized middleware system designed
                        to act as a secure and customizable bridge between your organization and external laboratories.
                        Its core function is to collect, normalize, and centralize patient blood test results.</p>
                </div>
                <div class="flex items-center">
                    <div class="text-right">
                        <div class="text-xs text-gray-500 uppercase tracking-wider">{{ now()->format('l') }}</div>
                        <div class="text-lg font-semibold text-[#003049]">{{ now()->format('F j, Y') }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6 mb-6 sm:mb-8">
        <!-- Total Tests -->
        <div
            class="bg-white rounded-2xl shadow-lg p-4 sm:p-6 border border-[#003049]/10 hover:shadow-xl transition-all duration-300 transform hover:scale-105">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600 mb-1">Total Tests</p>
                    <p class="text-2xl sm:text-3xl font-bold text-[#003049]">{{ number_format($stats['total_tests']) }}
                    </p>
                </div>
                <div class="w-12 h-12 bg-[#003049]/10 rounded-xl flex items-center justify-center">
                    <svg class="w-6 h-6 text-[#003049]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 5H7a2 2 0 00-2 2v6a2 2 0 002 2h2m5 0h2a2 2 0 002-2V7a2 2 0 00-2-2h-2m-5 4h6m-6 4h6m-6-8h6" />
                    </svg>
                </div>
            </div>
        </div>

        <!-- Total Patients -->
        <div
            class="bg-white rounded-2xl shadow-lg p-4 sm:p-6 border border-[#003049]/10 hover:shadow-xl transition-all duration-300 transform hover:scale-105">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600 mb-1">Total Labs</p>
                    <p class="text-2xl sm:text-3xl font-bold text-[#003049]">
                        {{ number_format($stats['total_labs']) }}</p>
                </div>
                <div class="w-12 h-12 bg-[#991B1B]/10 rounded-xl flex items-center justify-center">
                    <svg class="w-6 h-6 text-[#991B1B]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z" />
                    </svg>
                </div>
            </div>
        </div>

        <!-- Tests This Month -->
        <div
            class="bg-white rounded-2xl shadow-lg p-4 sm:p-6 border border-[#003049]/10 hover:shadow-xl transition-all duration-300 transform hover:scale-105">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600 mb-1">This Month</p>
                    <p class="text-2xl sm:text-3xl font-bold text-[#003049]">
                        {{ number_format($stats['tests_this_month']) }}</p>
                </div>
                <div class="w-12 h-12 bg-[#003049]/10 rounded-xl flex items-center justify-center">
                    <svg class="w-6 h-6 text-[#003049]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                </div>
            </div>
        </div>

        <!-- Completed Tests -->
        <div
            class="bg-white rounded-2xl shadow-lg p-4 sm:p-6 border border-[#003049]/10 hover:shadow-xl transition-all duration-300 transform hover:scale-105">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600 mb-1">Completed</p>
                    <p class="text-2xl sm:text-3xl font-bold text-[#003049]">
                        {{ number_format($stats['completed_tests']) }}</p>
                </div>
                <div class="w-12 h-12 bg-[#991B1B]/10 rounded-xl flex items-center justify-center">
                    <svg class="w-6 h-6 text-[#991B1B]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
            </div>
        </div>
    </div>

    <!-- Content Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6 sm:mb-8">
        <!-- Recent Test Results -->
        <div class="lg:col-span-2 bg-white rounded-2xl shadow-lg border border-[#003049]/10 overflow-hidden">
            <div class="p-4 sm:p-6 border-b border-[#003049]/10">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 bg-[#003049]/10 rounded-xl flex items-center justify-center">
                            <svg class="w-6 h-6 text-[#003049]" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M9 5H7a2 2 0 00-2 2v6a2 2 0 002 2h2m5 0h2a2 2 0 002-2V7a2 2 0 00-2-2h-2m-5 4h6m-6 4h6m-6-8h6" />
                            </svg>
                        </div>
                        <h2 class="text-lg sm:text-xl font-semibold text-[#003049]">Recent Test Results</h2>
                    </div>
                    <span class="text-xs sm:text-sm text-gray-500 bg-gray-50 px-3 py-1 rounded-full">Last 5
                        entries</span>
                </div>
            </div>
            <div class="overflow-x-auto">
                @if ($recent_tests->count() > 0)
                    <table class="w-full">
                        <thead class="bg-[#003049]/5 border-b border-[#003049]/10">
                            <tr>
                                <th
                                    class="px-4 py-3 text-left text-xs font-semibold text-[#003049] uppercase tracking-wider">
                                    Lab No</th>
                                <th
                                    class="px-4 py-3 text-left text-xs font-semibold text-[#003049] uppercase tracking-wider">
                                    Patient</th>
                                <th
                                    class="px-4 py-3 text-left text-xs font-semibold text-[#003049] uppercase tracking-wider">
                                    Doctor</th>
                                <th
                                    class="px-4 py-3 text-left text-xs font-semibold text-[#003049] uppercase tracking-wider">
                                    Date</th>
                                <th
                                    class="px-4 py-3 text-left text-xs font-semibold text-[#003049] uppercase tracking-wider">
                                    Status</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach ($recent_tests as $test)
                                <tr class="hover:bg-[#003049]/5 transition-colors duration-200">
                                    <td class="px-4 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-[#003049]">{{ $test->lab_no ?? 'N/A' }}
                                        </div>
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-700">{{ $test->patient->name ?? 'N/A' }}</div>
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-700">{{ $test->doctor->name ?? 'N/A' }}</div>
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-700">{{ $test->created_at->format('M j, Y') }}
                                        </div>
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap">
                                        @if ($test->is_completed)
                                            <span
                                                class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-[#991B1B]/10 text-[#991B1B] border border-[#991B1B]/20">
                                                <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd"
                                                        d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
                                                        clip-rule="evenodd" />
                                                </svg>
                                                Completed
                                            </span>
                                        @else
                                            <span
                                                class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800 border border-yellow-200">
                                                <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd"
                                                        d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z"
                                                        clip-rule="evenodd" />
                                                </svg>
                                                Processing
                                            </span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @else
                    <div class="p-8 text-center">
                        <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                            <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M9 5H7a2 2 0 00-2 2v6a2 2 0 002 2h2m5 0h2a2 2 0 002-2V7a2 2 0 00-2-2h-2m-5 4h6m-6 4h6m-6-8h6" />
                            </svg>
                        </div>
                        <h3 class="text-lg font-medium text-gray-900 mb-2">No test results yet</h3>
                        <p class="text-gray-500">Test results will appear here once they are submitted.</p>
                    </div>
                @endif
            </div>
        </div>

        <!-- Quick Access & System Info -->
        <div class="space-y-6">
            <!-- Quick Access -->
            <div
                class="bg-gradient-to-br from-[#003049]/5 to-[#003049]/10 rounded-2xl shadow-lg p-4 sm:p-6 border border-[#003049]/10">
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
                    <a href="{{ route('apis.index') }}"
                        class="block p-3 bg-gradient-to-r from-[#991B1B] to-[#7F1D1D] rounded-lg hover:from-[#B91C1C] hover:to-[#991B1B] transition-all duration-300 transform hover:scale-105 shadow-md hover:shadow-lg">
                        <div class="text-sm font-medium text-white flex items-center">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4" />
                            </svg>
                            API Documentation
                        </div>
                        <div class="text-xs text-white/80">Complete API reference</div>
                    </a>
                    <a href="api/documentation"
                        class="block p-3 bg-gradient-to-r from-[#003049] to-[#002135] rounded-lg hover:from-[#004663] hover:to-[#003049] transition-all duration-300 transform hover:scale-105 shadow-md hover:shadow-lg">
                        <div class="text-sm font-medium text-white flex items-center">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                            </svg>
                            Swagger Documentation
                        </div>
                        <div class="text-xs text-white/80">Interactive API explorer</div>
                    </a>
                    @if (Auth::user()->credential->role == 'admin')
                        <a href="{{ route('lab.index') }}"
                            class="block p-3 bg-white rounded-lg border border-[#003049]/20 hover:bg-[#003049]/5 transition-all duration-200 shadow-sm hover:shadow-md">
                            <div class="text-sm font-medium text-[#003049] flex items-center">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
                                </svg>
                                Lab Management
                            </div>
                            <div class="text-xs text-gray-600">Configure laboratories</div>
                        </a>
                    @endif
                </div>
            </div>

            <!-- System Statistics -->
            <div class="bg-white rounded-2xl shadow-lg p-4 sm:p-6 border border-[#003049]/10">
                <div class="flex items-center gap-3 mb-4">
                    <div class="w-10 h-10 bg-[#991B1B]/10 rounded-xl flex items-center justify-center">
                        <svg class="w-6 h-6 text-[#991B1B]" fill="none" stroke="currentColor"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                        </svg>
                    </div>
                    <h3 class="text-lg font-semibold text-[#003049]">System Overview</h3>
                </div>
                <div class="space-y-4">
                    <div class="flex justify-between items-center py-2 border-b border-gray-100">
                        <span class="text-sm text-gray-600">Total Doctors</span>
                        <span
                            class="text-sm font-medium text-[#003049] bg-[#003049]/5 px-2 py-1 rounded">{{ number_format($stats['total_doctors']) }}</span>
                    </div>
                    <div class="flex justify-between items-center py-2 border-b border-gray-100">
                        <span class="text-sm text-gray-600">Total Panels</span>
                        <span
                            class="text-sm font-medium text-[#003049] bg-[#003049]/5 px-2 py-1 rounded">{{ number_format($stats['total_panels']) }}</span>
                    </div>
                    <div class="flex justify-between items-center py-2 border-b border-gray-100">
                        <span class="text-sm text-gray-600">Pending Tests</span>
                        <span
                            class="text-sm font-medium text-[#991B1B] bg-[#991B1B]/10 px-2 py-1 rounded">{{ number_format($stats['pending_tests']) }}</span>
                    </div>
                    <div class="flex justify-between items-center py-2">
                        <span class="text-sm text-gray-600">Active Labs</span>
                        <span
                            class="text-sm font-medium text-[#003049] bg-[#003049]/5 px-2 py-1 rounded">{{ $labs->count() }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
