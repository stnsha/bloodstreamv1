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
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 5H7a2 2 0 00-2 2v6a2 2 0 002 2h2m5 0h2a2 2 0 002-2V7a2 2 0 00-2-2h-2m-5 4h6m-6 4h6m-6-8h6" />
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
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                        </svg>
                        Patient Information
                    </h3>
                    <div class="space-y-2">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 bg-[#003049] rounded-lg flex items-center justify-center shadow-sm">
                                <span
                                    class="text-white text-xs font-bold">{{ substr($testResult->patient->name, 0, 1) }}</span>
                            </div>
                            <div>
                                <div class="text-sm font-medium text-[#003049]">{{ $testResult->patient->name }}</div>
                                <div class="text-xs text-gray-500">{{ $testResult->patient->age }} years,
                                    {{ $testResult->patient->gender }}</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Doctor Details -->
                <div class="bg-gray-50 rounded-xl p-4 border border-gray-200">
                    <h3 class="font-semibold text-[#003049] mb-3 flex items-center gap-2">
                        <svg class="w-5 h-5 text-[#003049]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        Doctor Information
                    </h3>
                    <div class="space-y-2">
                        <div class="text-sm font-medium text-[#003049]">{{ $testResult->doctor->name }}</div>
                        <div class="text-xs text-gray-500">
                            @if ($testResult->doctor->code)
                                Code: {{ $testResult->doctor->code }}
                            @endif
                            @if ($testResult->doctor->type)
                                @if ($testResult->doctor->code)
                                    |
                                @endif
                                {{ $testResult->doctor->type }}
                            @endif
                        </div>
                        @if ($testResult->doctor->outlet_name)
                            <div class="text-xs text-gray-500">{{ $testResult->doctor->outlet_name }}</div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Lab Details Row -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                <div class="bg-blue-50 rounded-lg p-3 border border-blue-200">
                    <div class="text-xs text-gray-600 mb-1">Lab Number</div>
                    <div class="text-sm font-bold text-[#003049]">{{ $testResult->lab_no }}</div>
                </div>

                @if ($testResult->ref_id)
                    <div class="bg-yellow-50 rounded-lg p-3 border border-yellow-200">
                        <div class="text-xs text-gray-600 mb-1">Reference ID</div>
                        <div class="text-sm font-bold text-[#003049] font-mono">{{ $testResult->ref_id }}</div>
                    </div>
                @endif

                <div class="bg-purple-50 rounded-lg p-3 border border-purple-200">
                    <div class="text-xs text-gray-600 mb-1">Profiles</div>
                    <div class="flex flex-wrap gap-1">
                        @foreach ($testResult->profiles as $profile)
                            <span
                                class="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-purple-100 text-purple-800 border border-purple-200">
                                {{ $profile->code }}
                            </span>
                        @endforeach
                    </div>
                </div>

                <div
                    class="{{ $testResult->is_completed ? 'bg-green-50' : 'bg-orange-50' }} rounded-lg p-3 border {{ $testResult->is_completed ? 'border-green-200' : 'border-orange-200' }} ">
                    <div class="text-xs text-gray-600 mb-1">Status</div>
                    @if ($testResult->is_completed)
                        <span
                            class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800 border border-green-200">
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
                </div>
            </div>
        </div>
    </div>

    <!-- Test Results Table -->
    {{-- <div class="bg-white rounded-2xl shadow-lg border border-[#003049]/10 overflow-hidden mb-6 sm:mb-8">
        <div class="p-4 sm:p-6 border-b border-[#003049]/10">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-[#003049]/10 rounded-xl flex items-center justify-center">
                    <svg class="w-6 h-6 text-[#003049]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 5H7a2 2 0 00-2 2v6a2 2 0 002 2h2m5 0h2a2 2 0 002-2V7a2 2 0 00-2-2h-2m-5 4h6m-6 4h6m-6-8h6" />
                    </svg>
                </div>
                <div>
                    <h2 class="text-lg sm:text-xl font-semibold text-[#003049]">Test Results</h2>
                    <p class="text-sm text-gray-600">All test parameters and results</p>
                </div>
            </div>
        </div>

        @php
            $hasAnyItems = false;
            foreach ($profileResults as $profileResult) {
                if ($profileResult['totalItems'] > 0) {
                    $hasAnyItems = true;
                    break;
                }
            }
        @endphp

        @if ($hasAnyItems)
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-[#003049]">
                        <tr>
                            <th scope="col" class="px-3 sm:px-6 py-3 sm:py-4 text-left text-xs font-medium text-white uppercase tracking-wider">
                                Panel Category
                            </th>
                            <th scope="col" class="px-3 sm:px-6 py-3 sm:py-4 text-left text-xs font-medium text-white uppercase tracking-wider">
                                Test Parameter
                            </th>
                            <th scope="col" class="px-3 sm:px-6 py-3 sm:py-4 text-left text-xs font-medium text-white uppercase tracking-wider">
                                Result Value
                            </th>
                            <th scope="col" class="px-3 sm:px-6 py-3 sm:py-4 text-left text-xs font-medium text-white uppercase tracking-wider">
                                Unit
                            </th>
                            <th scope="col" class="px-3 sm:px-6 py-3 sm:py-4 text-left text-xs font-medium text-white uppercase tracking-wider">
                                Reference Range
                            </th>
                            <th scope="col" class="px-3 sm:px-6 py-3 sm:py-4 text-left text-xs font-medium text-white uppercase tracking-wider hidden sm:table-cell">
                                Status
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @foreach ($profileResults as $profileResult)
                            @if ($profileResult['totalItems'] > 0)
                                @foreach ($profileResult['panelGroups'] as $panelGroup)
                                    @foreach ($panelGroup['items'] as $itemIndex => $item)
                                        <tr class="hover:bg-[#003049]/5 transition-colors duration-200">
                                            <!-- Panel Category - only show on first item of each category -->
                                            @if ($itemIndex === 0)
                                                <td class="px-3 sm:px-6 py-3 sm:py-4 whitespace-nowrap {{ $panelGroup['itemCount'] > 1 ? 'border-r-2 border-[#003049]/20' : '' }}" 
                                                    @if ($panelGroup['itemCount'] > 1) rowspan="{{ $panelGroup['itemCount'] }}" @endif>
                                                    <div class="flex items-center gap-2">
                                                        <div class="w-6 h-6 bg-[#003049]/10 rounded-lg flex items-center justify-center">
                                                            <svg class="w-4 h-4 text-[#003049]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                                    d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
                                                            </svg>
                                                        </div>
                                                        <div>
                                                            <div class="text-sm font-semibold text-[#003049]">{{ $panelGroup['displayName'] }}</div>
                                                            <div class="text-xs text-gray-500">{{ $panelGroup['itemCount'] }} {{ Str::plural('item', $panelGroup['itemCount']) }}</div>
                                                        </div>
                                                    </div>
                                                </td>
                                            @endif
                                            
                                            <td class="px-3 sm:px-6 py-3 sm:py-4 whitespace-nowrap">
                                                <div class="text-sm font-medium text-[#003049]">
                                                    {{ $item->panelPanelItem && $item->panelPanelItem->panelItem ? $item->panelPanelItem->panelItem->name : 'Unknown Item' }}
                                                </div>
                                            </td>
                                            <td class="px-3 sm:px-6 py-3 sm:py-4 whitespace-nowrap">
                                                <div class="text-sm font-bold text-[#003049]">
                                                    {{ $item->value ?? 'N/A' }}
                                                </div>
                                            </td>
                                            <td class="px-3 sm:px-6 py-3 sm:py-4 whitespace-nowrap">
                                                <div class="text-sm text-gray-600">
                                                    {{ $item->panelPanelItem && $item->panelPanelItem->panelItem ? $item->panelPanelItem->panelItem->unit : '' }}
                                                </div>
                                            </td>
                                            <td class="px-3 sm:px-6 py-3 sm:py-4 whitespace-nowrap">
                                                @if ($item->referenceRange)
                                                    <div class="text-sm text-gray-600 bg-gray-100 px-2 py-1 rounded border">
                                                        {{ $item->referenceRange->value }}
                                                    </div>
                                                @else
                                                    <span class="text-xs text-gray-400">N/A</span>
                                                @endif
                                            </td>
                                            <td class="px-3 sm:px-6 py-3 sm:py-4 whitespace-nowrap hidden sm:table-cell">
                                                @if ($item->flag)
                                                    @php
                                                        $flagColors = [
                                                            'H' => 'bg-red-100 text-red-800 border-red-200',
                                                            'L' => 'bg-blue-100 text-blue-800 border-blue-200', 
                                                            'N' => 'bg-green-100 text-green-800 border-green-200',
                                                            'HH' => 'bg-red-100 text-red-800 border-red-200',
                                                            'LL' => 'bg-blue-100 text-blue-800 border-blue-200',
                                                            'HHH' => 'bg-red-100 text-red-800 border-red-200',
                                                            'Above high normal' => 'bg-red-100 text-red-800 border-red-200',
                                                            'Below low normal' => 'bg-blue-100 text-blue-800 border-blue-200',
                                                            'Above upper panic limits' => 'bg-red-100 text-red-800 border-red-200',
                                                            'Normal (applies to non-numeric results)' => 'bg-green-100 text-green-800 border-green-200',
                                                        ];
                                                        $flagClass = $flagColors[$item->flag] ?? 'bg-yellow-100 text-yellow-800 border-yellow-200';
                                                    @endphp
                                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium border {{ $flagClass }}">
                                                        @if (in_array($item->flag, ['H', 'HH', 'HHH']))
                                                            High
                                                        @elseif (in_array($item->flag, ['L', 'LL']))
                                                            Low
                                                        @elseif ($item->flag === 'Above high normal')
                                                            High
                                                        @elseif ($item->flag === 'Below low normal')
                                                            Low
                                                        @elseif ($item->flag === 'Above upper panic limits')
                                                            Critical High
                                                        @elseif (in_array($item->flag, ['N', 'Normal (applies to non-numeric results)']))
                                                            Normal
                                                        @else
                                                            {{ $item->flag }}
                                                        @endif
                                                    </span>
                                                @else
                                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800 border border-green-200">
                                                        Normal
                                                    </span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                @endforeach
                            @endif
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="p-6 sm:p-12 text-center">
                <div class="w-12 h-12 sm:w-16 sm:h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-3 sm:mb-4">
                    <svg class="w-6 h-6 sm:w-8 sm:h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 5H7a2 2 0 00-2 2v6a2 2 0 002 2h2m5 0h2a2 2 0 002-2V7a2 2 0 00-2-2h-2m-5 4h6m-6 4h6m-6-8h6" />
                    </svg>
                </div>
                <h3 class="text-base sm:text-lg font-medium text-gray-900 mb-2">No test results found</h3>
                <p class="text-sm sm:text-base text-gray-500">This test result doesn't have any test items yet.</p>
            </div>
        @endif
    </div> --}}

    <!-- Doctor Review Section -->
    @if ($testResult->is_completed && $testResult->review)
        <div class="bg-white rounded-2xl shadow-lg border border-[#003049]/10 overflow-hidden mb-6 sm:mb-8">
            <div class="p-4 sm:p-6 border-b border-[#003049]/10">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-green-100 rounded-xl flex items-center justify-center">
                        <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <div>
                        <h2 class="text-lg sm:text-xl font-semibold text-[#003049]">Doctor Review</h2>
                        <p class="text-sm text-gray-600">Medical analysis and recommendations</p>
                    </div>
                    <div class="ml-auto">
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800 border border-green-200">
                            <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd"
                                    d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
                                    clip-rule="evenodd" />
                            </svg>
                            Reviewed
                        </span>
                    </div>
                </div>
            </div>

            <div class="p-4 sm:p-6">
                <div class="prose prose-sm max-w-none">
                    <div class="bg-gray-50 rounded-xl p-4 sm:p-6 border border-gray-200">
                        <div style="white-space: pre-line;">
                            {!! $testResult->review->review !!}
                        </div>
                    </div>
                </div>
                
                @if ($testResult->review->created_at)
                    <div class="mt-4 pt-4 border-t border-gray-200">
                        <div class="flex items-center justify-between text-sm text-gray-500">
                            <div class="flex items-center gap-2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <span>Reviewed on {{ $testResult->review->created_at->format('M d, Y \a\t h:i A') }}</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                </svg>
                                <span>AI-Powered Medical Review</span>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    @elseif ($testResult->is_completed && !$testResult->review)
        <div class="bg-white rounded-2xl shadow-lg border border-[#003049]/10 overflow-hidden mb-6 sm:mb-8">
            <div class="p-4 sm:p-6 border-b border-[#003049]/10">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-yellow-100 rounded-xl flex items-center justify-center">
                        <svg class="w-6 h-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 15.5c-.77.833.192 2.5 1.732 2.5z" />
                        </svg>
                    </div>
                    <div>
                        <h2 class="text-lg sm:text-xl font-semibold text-[#003049]">Doctor Review</h2>
                        <p class="text-sm text-gray-600">Medical review pending</p>
                    </div>
                </div>
            </div>

            <div class="p-4 sm:p-6 text-center">
                <div class="w-12 h-12 bg-yellow-100 rounded-full flex items-center justify-center mx-auto mb-3">
                    <svg class="w-6 h-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <h3 class="text-base font-medium text-gray-900 mb-2">Review In Progress</h3>
                <p class="text-sm text-gray-500">Test results are complete. Doctor review will be available shortly.</p>
            </div>
        </div>
    @endif

    @if ($testResult->testResultItems->count() === 0)
        <div class="bg-white rounded-2xl shadow-lg border border-[#003049]/10 p-6 sm:p-12 text-center">
            <div
                class="w-12 h-12 sm:w-16 sm:h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-3 sm:mb-4">
                <svg class="w-6 h-6 sm:w-8 sm:h-8 text-gray-400" fill="none" stroke="currentColor"
                    viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M9 5H7a2 2 0 00-2 2v6a2 2 0 002 2h2m5 0h2a2 2 0 002-2V7a2 2 0 00-2-2h-2m-5 4h6m-6 4h6m-6-8h6" />
                </svg>
            </div>
            <h3 class="text-base sm:text-lg font-medium text-gray-900 mb-2">No test items found</h3>
            <p class="text-sm sm:text-base text-gray-500">This test result doesn't have any panel items yet.</p>
        </div>
    @endif


</x-app-layout>
