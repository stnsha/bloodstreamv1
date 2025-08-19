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
                <h1 class="text-xl sm:text-2xl font-bold text-[#003049]">Test Results</h1>
                <p class="text-gray-600 text-xs sm:text-sm">View and manage laboratory test results</p>
            </div>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4 sm:gap-6 mb-6 sm:mb-8">
        <div
            class="bg-white rounded-2xl shadow-lg p-4 sm:p-6 border border-[#003049]/10 hover:shadow-xl transition-all duration-300 transform hover:scale-105">
            <div class="flex items-center gap-3">
                <div class="w-12 h-12 bg-[#003049]/10 rounded-xl flex items-center justify-center">
                    <svg class="w-6 h-6 text-[#003049]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 5H7a2 2 0 00-2 2v6a2 2 0 002 2h2m5 0h2a2 2 0 002-2V7a2 2 0 00-2-2h-2m-5 4h6m-6 4h6m-6-8h6" />
                    </svg>
                </div>
                <div>
                    <h3 class="text-xl font-bold text-[#003049]">{{ count($testResults ?? []) }}</h3>
                    <p class="text-sm text-gray-600 font-medium">Total Results</p>
                </div>
            </div>
        </div>

        <div
            class="bg-white rounded-2xl shadow-lg p-4 sm:p-6 border border-[#003049]/10 hover:shadow-xl transition-all duration-300 transform hover:scale-105">
            <div class="flex items-center gap-3">
                <div class="w-12 h-12 bg-[#991B1B]/10 rounded-xl flex items-center justify-center">
                    <svg class="w-6 h-6 text-[#991B1B]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z" />
                    </svg>
                </div>
                <div>
                    <h3 class="text-xl font-bold text-[#003049]">
                        {{ ($testResults ?? collect())->pluck('patient')->unique('id')->count() }}</h3>
                    <p class="text-sm text-gray-600 font-medium">Patients</p>
                </div>
            </div>
        </div>

        <div
            class="bg-white rounded-2xl shadow-lg p-4 sm:p-6 border border-[#003049]/10 hover:shadow-xl transition-all duration-300 transform hover:scale-105">
            <div class="flex items-center gap-3">
                <div class="w-12 h-12 bg-[#003049]/10 rounded-xl flex items-center justify-center">
                    <svg class="w-6 h-6 text-[#003049]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                    </svg>
                </div>
                <div>
                    <h3 class="text-xl font-bold text-[#003049]">
                        {{ ($testResults ?? collect())->pluck('doctor.lab')->unique('id')->count() }}</h3>
                    <p class="text-sm text-gray-600 font-medium">Total Labs</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Test Results Table Card -->
    <div class="bg-white rounded-2xl shadow-lg border border-[#003049]/10 overflow-hidden">
        <div class="p-4 sm:p-6 border-b border-[#003049]/10">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-[#003049]/10 rounded-xl flex items-center justify-center">
                        <svg class="w-6 h-6 text-[#003049]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                        </svg>
                    </div>
                    <h2 class="text-lg sm:text-xl font-semibold text-[#003049]">Test Results</h2>
                </div>

                <!-- Search Input -->
                <div class="flex items-center gap-3 flex-1 max-w-md">
                    <div class="relative flex-1">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                        </div>
                        <input type="text" id="search-input"
                            placeholder="Search by patient name, lab no, or ref ID..."
                            class="block w-full pl-10 pr-10 py-2 text-sm border border-gray-300 rounded-lg focus:ring-[#003049] focus:border-[#003049] bg-white">
                        <button type="button" id="clear-search"
                            class="absolute inset-y-0 right-0 pr-3 flex items-center hidden">
                            <svg class="h-4 w-4 text-gray-400 hover:text-gray-600 cursor-pointer" fill="none"
                                stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </div>

                <div class="text-xs sm:text-sm text-gray-500 bg-gray-50 px-3 py-1 rounded-full">
                    <span id="results-count">{{ count($testResults ?? []) }}</span> of
                    <span id="total-count">{{ count($testResults ?? []) }}</span>
                    {{ Str::plural('result', count($testResults ?? [])) }}
                </div>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-[#003049]/5 border-b border-[#003049]/10">
                    <tr>
                        <th scope="col"
                            class="px-3 sm:px-6 py-3 sm:py-4 text-left text-xs font-semibold text-[#003049] uppercase tracking-wider">
                            Lab & Doctor Details
                        </th>
                        <th scope="col"
                            class="px-3 sm:px-6 py-3 sm:py-4 text-left text-xs font-semibold text-[#003049] uppercase tracking-wider">
                            Patient Information
                        </th>
                        <th scope="col"
                            class="px-3 sm:px-6 py-3 sm:py-4 text-left text-xs font-semibold text-[#003049] uppercase tracking-wider">
                            Lab No
                        </th>
                        <th scope="col"
                            class="px-3 sm:px-6 py-3 sm:py-4 text-left text-xs font-semibold text-[#003049] uppercase tracking-wider hidden sm:table-cell">
                            Ref ID
                        </th>
                        <th scope="col"
                            class="px-3 sm:px-6 py-3 sm:py-4 text-left text-xs font-semibold text-[#003049] uppercase tracking-wider">
                            Profiles
                        </th>
                        <th scope="col"
                            class="px-3 sm:px-6 py-3 sm:py-4 text-left text-xs font-semibold text-[#003049] uppercase tracking-wider hidden sm:table-cell">
                            Status
                        </th>
                        <th scope="col"
                            class="px-3 sm:px-6 py-3 sm:py-4 text-left text-xs font-semibold text-[#003049] uppercase tracking-wider">
                            Action
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200" id="results-tbody">
                    @foreach ($testResults ?? [] as $index => $result)
                        <tr class="hover:bg-[#003049]/5 transition-colors duration-200 cursor-pointer result-row"
                            data-index="{{ $index }}"
                            data-patient-name="{{ strtolower($result->patient->name) }}"
                            data-lab-no="{{ strtolower($result->lab_no) }}"
                            data-ref-id="{{ strtolower($result->ref_id ?? '') }}"
                            onclick="toggleAccordion('result-{{ $result->id }}')">
                            <td class="px-3 sm:px-6 py-3 sm:py-4 whitespace-nowrap">
                                <div class="text-xs sm:text-sm">
                                    <div class="font-medium text-[#003049] mb-0.5">{{ $result->doctor->lab->name }}
                                    </div>
                                    <div class="font-medium text-[#003049]">
                                        {{ $result->doctor->name }}
                                        @if ($result->doctor->code)
                                            ({{ $result->doctor->code }})
                                        @endif
                                    </div>
                                    @if ($result->doctor->outlet_name)
                                        <div class="text-gray-500">{{ $result->doctor->outlet_name }}</div>
                                    @endif
                                </div>
                            </td>
                            <td class="px-3 sm:px-6 py-3 sm:py-4 whitespace-nowrap">
                                <div class="flex items-center gap-2 sm:gap-3">
                                    {{-- <div
                                        class="w-8 h-8 bg-[#003049] rounded-lg flex items-center justify-center shadow-sm">
                                        <span
                                            class="text-white text-xs font-bold">{{ substr($result->patient->name, 0, 1) }}</span>
                                    </div> --}}
                                    <div>
                                        <div class="text-xs sm:text-sm font-medium text-[#003049]">
                                            {{ $result->patient->name }}</div>
                                        <div class="text-xs text-gray-500">{{ $result->patient->age }} years,
                                            {{ $result->patient->gender == 'F' ? 'Female' : 'Male' }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-3 sm:px-6 py-3 sm:py-4 whitespace-nowrap">
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-[#003049]/10 text-[#003049] border border-[#003049]/20">
                                    {{ $result->lab_no }}
                                </span>
                            </td>
                            <td class="px-3 sm:px-6 py-3 sm:py-4 whitespace-nowrap hidden sm:table-cell">
                                @if ($result->ref_id)
                                    <span
                                        class="text-sm text-[#003049] font-mono bg-[#003049]/5 px-3 py-1 rounded border border-[#003049]/10">
                                        {{ $result->ref_id }}
                                    </span>
                                @endif
                            </td>
                            <td class="px-3 sm:px-6 py-3 sm:py-4 whitespace-nowrap">
                                <div class="flex flex-wrap gap-1">
                                    @foreach ($result->profiles as $profile)
                                        <span
                                            class="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-blue-100 text-blue-800 border border-blue-200">
                                            {{ $profile->code }}
                                        </span>
                                    @endforeach

                                    @if ($result->is_tagon)
                                        <span
                                            class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800 border border-green-200">
                                            <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd"
                                                    d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
                                                    clip-rule="evenodd" />
                                            </svg>
                                            Tagon
                                        </span>
                                    @endif
                                </div>
                            </td>
                            <td class="px-3 sm:px-6 py-3 sm:py-4 whitespace-nowrap hidden sm:table-cell">
                                @if ($result->is_completed)
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
                            <td class="px-3 sm:px-6 py-3 sm:py-4 whitespace-nowrap">
                                <div class="flex items-center gap-2">
                                    <button type="button"
                                        class="inline-flex items-center px-3 py-1 border border-[#003049]/20 rounded-lg text-xs font-medium text-[#003049] bg-[#003049]/5 hover:bg-[#003049]/10 transition-colors duration-200"
                                        onclick="event.stopPropagation(); toggleAccordion('result-{{ $result->id }}')">
                                        <svg class="w-3 h-3 mr-1 transform transition-transform duration-200"
                                            id="chevron-{{ $result->id }}" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M19 9l-7 7-7-7" />
                                        </svg>
                                        Preview
                                    </button>
                                    <a href="{{ route('results.show', $result->id) }}"
                                        class="inline-flex items-center px-3 py-1 border border-blue-300 rounded-lg text-xs font-medium text-blue-700 bg-blue-50 hover:bg-blue-100 transition-colors duration-200">
                                        <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                        </svg>
                                        Details
                                    </a>
                                </div>
                            </td>
                        </tr>

                        <!-- Accordion Content -->
                        <tr id="result-{{ $result->id }}" class="hidden accordion-row"
                            data-index="{{ $index }}">
                            <td colspan="7" class="px-3 sm:px-6 py-4 bg-gray-50">
                                <div class="space-y-4">
                                    @foreach ($result->testResultItems->groupBy('panel.name') as $panelName => $items)
                                        @php
                                            // Check if any item in this group has is_tagon = true
                                            $hasTagOn = $items->contains('is_tagon', true);
                                            $displayName = $panelName;
                                            
                                            if ($hasTagOn) {
                                                // Get the first item with is_tagon = true to access its panel tag
                                                $tagOnItem = $items->first(function($item) {
                                                    return $item->is_tagon;
                                                });
                                                if ($tagOnItem && $tagOnItem->panel && $tagOnItem->panel->panelTags->isNotEmpty()) {
                                                    $displayName = $tagOnItem->panel->panelTags->first()->name;
                                                }
                                            }
                                        @endphp
                                        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
                                            <h4 class="font-semibold text-[#003049] mb-3 flex items-center gap-2">
                                                <svg class="w-4 h-4 text-[#003049]" fill="none"
                                                    stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2"
                                                        d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
                                                </svg>
                                                {{ $displayName }}
                                                @if($hasTagOn)
                                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-orange-100 text-orange-800 border border-orange-200">
                                                        <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                                        </svg>
                                                        Tag On
                                                    </span>
                                                @endif
                                            </h4>
                                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                                                @foreach ($items as $item)
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
                                                        @if ($item->referenceRange)
                                                            <div class="text-xs text-gray-600 mt-1">
                                                                Range: {{ $item->referenceRange->value }}
                                                            </div>
                                                        @endif
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <!-- Pagination Controls -->
        <div id="pagination-controls"
            class="px-4 sm:px-6 py-4 border-t border-[#003049]/10 bg-gray-50 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <span class="text-sm text-gray-700">
                    Showing <span id="showing-start">1</span> to <span id="showing-end">5</span> of <span
                        id="total-results">{{ count($testResults ?? []) }}</span> results
                </span>
            </div>
            <div class="flex items-center gap-1">
                <button id="prev-btn" onclick="changePage(-1)"
                    class="px-3 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 hover:text-gray-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors duration-200">
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                    </svg>
                    {{-- Previous --}}
                </button>
                <div id="page-numbers" class="flex gap-1 mx-2"></div>
                <button id="next-btn" onclick="changePage(1)"
                    class="px-3 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 hover:text-gray-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors duration-200">
                    {{-- Next --}}
                    <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                    </svg>
                </button>
            </div>
        </div>

        @if (count($testResults ?? []) === 0)
            <div class="p-6 sm:p-12 text-center">
                <div
                    class="w-12 h-12 sm:w-16 sm:h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-3 sm:mb-4">
                    <svg class="w-6 h-6 sm:w-8 sm:h-8 text-gray-400" fill="none" stroke="currentColor"
                        viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 5H7a2 2 0 00-2 2v6a2 2 0 002 2h2m5 0h2a2 2 0 002-2V7a2 2 0 00-2-2h-2m-5 4h6m-6 4h6m-6-8h6" />
                    </svg>
                </div>
                <h3 class="text-base sm:text-lg font-medium text-gray-900 mb-2">No test results found</h3>
                <p class="text-sm sm:text-base text-gray-500 mb-4 sm:mb-6">No test results have been recorded yet.</p>
            </div>
        @endif
    </div>

    <script>
        let currentPage = 1;
        const resultsPerPage = 5;
        let totalResults = {{ count($testResults ?? []) }};
        let filteredResults = [];
        let isSearching = false;
        let totalPages = Math.ceil(totalResults / resultsPerPage);

        // Search functionality
        function performSearch() {
            const searchTerm = document.getElementById('search-input').value.toLowerCase().trim();
            const clearButton = document.getElementById('clear-search');

            if (searchTerm === '') {
                isSearching = false;
                filteredResults = [];
                clearButton.classList.add('hidden');
                currentPage = 1;
                updateResultsCount(totalResults, totalResults);
                updatePagination();
                showPage(1);
                return;
            }

            clearButton.classList.remove('hidden');
            isSearching = true;
            filteredResults = [];

            const allRows = document.querySelectorAll('.result-row');
            allRows.forEach((row, index) => {
                const patientName = row.dataset.patientName || '';
                const labNo = row.dataset.labNo || '';
                const refId = row.dataset.refId || '';

                if (patientName.includes(searchTerm) ||
                    labNo.includes(searchTerm) ||
                    refId.includes(searchTerm)) {
                    filteredResults.push(index);
                }
            });

            updateResultsCount(filteredResults.length, totalResults);
            currentPage = 1;
            updatePagination();
            showFilteredPage(1);
        }

        function clearSearch() {
            document.getElementById('search-input').value = '';
            document.getElementById('clear-search').classList.add('hidden');
            isSearching = false;
            filteredResults = [];
            currentPage = 1;
            updateResultsCount(totalResults, totalResults);
            updatePagination();
            showPage(1);
        }

        function updateResultsCount(showing, total) {
            document.getElementById('results-count').textContent = showing;
            document.getElementById('total-count').textContent = total;
        }

        function toggleAccordion(rowId) {
            const row = document.getElementById(rowId);
            const chevron = document.getElementById('chevron-' + rowId.split('-')[1]);
            const isCurrentlyHidden = row.classList.contains('hidden');

            // Close all other accordions first
            const allAccordions = document.querySelectorAll('.accordion-row');
            const allChevrons = document.querySelectorAll('[id^="chevron-"]');
            
            allAccordions.forEach(accordion => {
                if (accordion.id !== rowId) {
                    accordion.classList.add('hidden');
                }
            });
            
            allChevrons.forEach(chevronEl => {
                if (chevronEl.id !== 'chevron-' + rowId.split('-')[1]) {
                    chevronEl.style.transform = 'rotate(0deg)';
                }
            });

            // Toggle the clicked accordion
            if (isCurrentlyHidden) {
                row.classList.remove('hidden');
                chevron.style.transform = 'rotate(180deg)';
            } else {
                row.classList.add('hidden');
                chevron.style.transform = 'rotate(0deg)';
            }
        }

        function showPage(page) {
            if (isSearching) {
                showFilteredPage(page);
                return;
            }

            const allRows = document.querySelectorAll('.result-row');
            const allAccordions = document.querySelectorAll('.accordion-row');

            // Hide all rows and accordions first
            allRows.forEach(row => row.style.display = 'none');
            allAccordions.forEach(accordion => {
                accordion.style.display = 'none';
                accordion.classList.add('hidden');
            });

            // Calculate range for current page
            const startIndex = (page - 1) * resultsPerPage;
            const endIndex = Math.min(startIndex + resultsPerPage, totalResults);

            // Show rows for current page
            for (let i = startIndex; i < endIndex; i++) {
                const resultRow = document.querySelector(`[data-index="${i}"].result-row`);
                const accordionRow = document.querySelector(`[data-index="${i}"].accordion-row`);

                if (resultRow) {
                    resultRow.style.display = '';
                }
                if (accordionRow) {
                    accordionRow.style.display = '';
                }
            }

            // Reset all chevrons to closed state
            document.querySelectorAll('[id^="chevron-"]').forEach(chevron => {
                chevron.style.transform = 'rotate(0deg)';
            });

            // Update pagination info
            updatePaginationInfo(page, startIndex + 1, endIndex);
            updatePaginationButtons();
        }

        function showFilteredPage(page) {
            const allRows = document.querySelectorAll('.result-row');
            const allAccordions = document.querySelectorAll('.accordion-row');

            // Hide all rows and accordions first
            allRows.forEach(row => row.style.display = 'none');
            allAccordions.forEach(accordion => {
                accordion.style.display = 'none';
                accordion.classList.add('hidden');
            });

            // Calculate range for current page of filtered results
            const startIndex = (page - 1) * resultsPerPage;
            const endIndex = Math.min(startIndex + resultsPerPage, filteredResults.length);

            // Show filtered rows for current page
            for (let i = startIndex; i < endIndex; i++) {
                const originalIndex = filteredResults[i];
                const resultRow = document.querySelector(`[data-index="${originalIndex}"].result-row`);
                const accordionRow = document.querySelector(`[data-index="${originalIndex}"].accordion-row`);

                if (resultRow) {
                    resultRow.style.display = '';
                }
                if (accordionRow) {
                    accordionRow.style.display = '';
                }
            }

            // Reset all chevrons to closed state
            document.querySelectorAll('[id^="chevron-"]').forEach(chevron => {
                chevron.style.transform = 'rotate(0deg)';
            });

            // Update pagination info
            updatePaginationInfo(page, startIndex + 1, endIndex);
            updatePaginationButtons();
        }

        function updatePaginationInfo(page, start, end) {
            document.getElementById('showing-start').textContent = start;
            document.getElementById('showing-end').textContent = end;
            document.getElementById('total-results').textContent = totalResults;
        }

        function updatePagination() {
            if (isSearching) {
                totalPages = Math.ceil(filteredResults.length / resultsPerPage);
            } else {
                totalPages = Math.ceil(totalResults / resultsPerPage);
            }
            updatePaginationButtons();
        }

        function updatePaginationButtons() {
            const prevBtn = document.getElementById('prev-btn');
            const nextBtn = document.getElementById('next-btn');

            prevBtn.disabled = currentPage === 1;
            nextBtn.disabled = currentPage === totalPages;

            if (prevBtn.disabled) {
                prevBtn.classList.add('opacity-50', 'cursor-not-allowed');
            } else {
                prevBtn.classList.remove('opacity-50', 'cursor-not-allowed');
            }

            if (nextBtn.disabled) {
                nextBtn.classList.add('opacity-50', 'cursor-not-allowed');
            } else {
                nextBtn.classList.remove('opacity-50', 'cursor-not-allowed');
            }

            // Update page numbers
            updatePageNumbers();
        }

        function updatePageNumbers() {
            const pageNumbersContainer = document.getElementById('page-numbers');
            pageNumbersContainer.innerHTML = '';

            for (let i = 1; i <= totalPages; i++) {
                const pageBtn = document.createElement('button');
                pageBtn.textContent = i;
                pageBtn.onclick = () => goToPage(i);
                pageBtn.className = `px-3 py-2 text-sm font-medium rounded-lg transition-colors duration-200 ${
                    i === currentPage 
                        ? 'bg-[#003049] text-white' 
                        : 'text-gray-500 bg-white border border-gray-300 hover:bg-gray-50 hover:text-gray-700'
                }`;
                pageNumbersContainer.appendChild(pageBtn);
            }
        }

        function changePage(direction) {
            const newPage = currentPage + direction;
            if (newPage >= 1 && newPage <= totalPages) {
                goToPage(newPage);
            }
        }

        function goToPage(page) {
            currentPage = page;
            showPage(currentPage);
        }

        // Hide pagination if no results or only one page
        function initializePagination() {
            const paginationControls = document.getElementById('pagination-controls');

            if (totalResults === 0 || totalPages <= 1) {
                paginationControls.style.display = 'none';
            } else {
                paginationControls.style.display = 'flex';
            }

            // Show first page
            showPage(1);
        }

        // Initialize pagination when page loads
        document.addEventListener('DOMContentLoaded', function() {
            initializePagination();

            // Setup search functionality
            const searchInput = document.getElementById('search-input');
            const clearButton = document.getElementById('clear-search');

            // Search on input with debounce
            let searchTimeout;
            searchInput.addEventListener('input', function() {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(performSearch, 300);
            });

            // Clear search button
            clearButton.addEventListener('click', clearSearch);

            // Search on Enter key
            searchInput.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    clearTimeout(searchTimeout);
                    performSearch();
                }
            });
        });
    </script>
</x-app-layout>
