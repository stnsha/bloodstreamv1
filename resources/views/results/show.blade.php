<x-app-layout>
    <!-- Header Section -->
    <div class="mb-6 sm:mb-8">
        <div class="flex items-center gap-3 mb-4 sm:mb-6">
            <div class="w-12 h-12 sm:w-14 sm:h-14 bg-[#003049] rounded-xl flex items-center justify-center shadow-md">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 sm:w-7 sm:h-7 text-white" fill="none"
                    stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M8.25 7.5V6.108c0-1.135.845-2.098 1.976-2.192.373-.03.748-.057 1.123-.08M15.75 18H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08M15.75 18.75v-1.875a3.375 3.375 0 0 0-3.375-3.375h-1.5a1.125 1.125 0 0 1-1.125-1.125v-1.5A3.375 3.375 0 0 0 6.375 7.5H5.25m11.9-3.664A2.251 2.251 0 0 0 15 2.25h-1.5a2.251 2.251 0 0 0-2.15 1.586m5.8 0c.065.21.1.433.1.664v.75h-6V4.5c0-.231.035-.454.1-.664M6.75 7.5H4.875c-.621 0-1.125.504-1.125 1.125v12c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V16.5a9 9 0 0 0-9-9Z" />
                </svg>
            </div>
            <div>
                <h1 class="text-xl sm:text-2xl font-bold text-[#003049]">Test Result Details</h1>
                <p class="text-gray-600 text-xs sm:text-sm">Lab No: {{ $testResult->lab_no }}</p>
            </div>
        </div>
        
        <!-- Back Button -->
        <div class="mb-4">
            <a href="{{ route('results.index') }}" 
               class="inline-flex items-center px-4 py-2 text-sm font-medium text-[#003049] bg-white border border-[#003049]/20 rounded-lg hover:bg-[#003049]/5 transition-colors duration-200">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                </svg>
                Back to Results
            </a>
        </div>
    </div>

    <!-- Test Result Overview Card -->
    <div class="bg-white rounded-2xl shadow-lg border border-[#003049]/10 overflow-hidden mb-6 sm:mb-8">
        <div class="p-4 sm:p-6 border-b border-[#003049]/10">
            <div class="flex items-center gap-3 mb-2 sm:mb-0">
                <div class="w-10 h-10 bg-[#003049]/10 rounded-xl flex items-center justify-center">
                    <svg class="w-6 h-6 text-[#003049]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v6a2 2 0 002 2h2m5 0h2a2 2 0 002-2V7a2 2 0 00-2-2h-2m-5 4h6m-6 4h6m-6-8h6" />
                    </svg>
                </div>
                <h2 class="text-lg sm:text-xl font-semibold text-[#003049]">Test Result Overview</h2>
            </div>
        </div>

        <div class="p-4 sm:p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                <!-- Patient Details -->
                <div class="bg-gray-50 rounded-xl p-4 border border-gray-200">
                    <h3 class="font-semibold text-[#003049] mb-3 flex items-center gap-2">
                        <svg class="w-5 h-5 text-[#003049]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                        </svg>
                        Patient Information
                    </h3>
                    <div class="space-y-2">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 bg-[#003049] rounded-lg flex items-center justify-center shadow-sm">
                                <span class="text-white text-xs font-bold">{{ substr($testResult->patient->name, 0, 1) }}</span>
                            </div>
                            <div>
                                <div class="text-sm font-medium text-[#003049]">{{ $testResult->patient->name }}</div>
                                <div class="text-xs text-gray-500">{{ $testResult->patient->age }} years, {{ $testResult->patient->gender }}</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Doctor Details -->
                <div class="bg-gray-50 rounded-xl p-4 border border-gray-200">
                    <h3 class="font-semibold text-[#003049] mb-3 flex items-center gap-2">
                        <svg class="w-5 h-5 text-[#003049]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        Doctor Information
                    </h3>
                    <div class="space-y-2">
                        <div class="text-sm font-medium text-[#003049]">{{ $testResult->doctor->name }}</div>
                        <div class="text-xs text-gray-500">
                            @if($testResult->doctor->code)
                                Code: {{ $testResult->doctor->code }}
                            @endif
                            @if($testResult->doctor->type)
                                @if($testResult->doctor->code) | @endif
                                {{ $testResult->doctor->type }}
                            @endif
                        </div>
                        @if($testResult->doctor->outlet_name)
                            <div class="text-xs text-gray-500">{{ $testResult->doctor->outlet_name }}</div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Lab Details Row -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
                <div class="bg-blue-50 rounded-lg p-3 border border-blue-200">
                    <div class="text-xs text-gray-600 mb-1">Lab Number</div>
                    <div class="text-sm font-bold text-[#003049]">{{ $testResult->lab_no }}</div>
                </div>
                
                @if($testResult->ref_id)
                <div class="bg-green-50 rounded-lg p-3 border border-green-200">
                    <div class="text-xs text-gray-600 mb-1">Reference ID</div>
                    <div class="text-sm font-bold text-[#003049] font-mono">{{ $testResult->ref_id }}</div>
                </div>
                @endif

                <div class="bg-purple-50 rounded-lg p-3 border border-purple-200">
                    <div class="text-xs text-gray-600 mb-1">Profiles</div>
                    <div class="flex flex-wrap gap-1">
                        @foreach($testResult->profiles as $profile)
                            <span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-purple-100 text-purple-800 border border-purple-200">
                                {{ $profile->code }}
                            </span>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Test Results Panels -->
    <div class="space-y-6">
        @foreach($testResult->testResultItems->groupBy('panel.name') as $panelName => $items)
            <div class="bg-white rounded-2xl shadow-lg border border-[#003049]/10 overflow-hidden">
                <div class="p-4 sm:p-6 border-b border-[#003049]/10">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 bg-[#003049]/10 rounded-xl flex items-center justify-center">
                            <svg class="w-6 h-6 text-[#003049]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
                            </svg>
                        </div>
                        <h3 class="text-lg sm:text-xl font-semibold text-[#003049]">{{ $panelName }}</h3>
                        <div class="text-xs sm:text-sm text-gray-500 bg-gray-50 px-3 py-1 rounded-full ml-auto">
                            {{ count($items) }} {{ Str::plural('item', count($items)) }}
                        </div>
                    </div>
                </div>

                <div class="p-4 sm:p-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                        @foreach($items as $item)
                            <div class="bg-gray-50 rounded-lg p-3 border border-gray-100">
                                <div class="text-sm font-medium text-[#003049] mb-1">
                                    {{ $item->panelItem->name }}
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-lg font-bold text-[#003049]">
                                        {{ $item->value }}
                                    </span>
                                    <span class="text-xs text-gray-500">
                                        {{ $item->panelItem->unit }}
                                    </span>
                                </div>
                                @if($item->referenceRange)
                                    <div class="text-xs text-gray-600 mt-1">
                                        Range: {{ $item->referenceRange->value }}
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    @if($testResult->testResultItems->count() === 0)
        <div class="bg-white rounded-2xl shadow-lg border border-[#003049]/10 p-6 sm:p-12 text-center">
            <div class="w-12 h-12 sm:w-16 sm:h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-3 sm:mb-4">
                <svg class="w-6 h-6 sm:w-8 sm:h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v6a2 2 0 002 2h2m5 0h2a2 2 0 002-2V7a2 2 0 00-2-2h-2m-5 4h6m-6 4h6m-6-8h6" />
                </svg>
            </div>
            <h3 class="text-base sm:text-lg font-medium text-gray-900 mb-2">No test items found</h3>
            <p class="text-sm sm:text-base text-gray-500">This test result doesn't have any panel items yet.</p>
        </div>
    @endif
</x-app-layout>