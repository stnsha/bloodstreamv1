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
                    <p class="text-sm text-gray-600 font-medium">Results</p>
                </div>
            </div>
        </div>

        <div
            class="bg-white rounded-2xl shadow-lg p-4 sm:p-6 border border-[#003049]/10 hover:shadow-xl transition-all duration-300 transform hover:scale-105">
            <div class="flex items-center gap-3">
                <div class="w-12 h-12 bg-[#003049]/10 rounded-xl flex items-center justify-center">
                    <svg class="w-6 h-6 text-[#003049]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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
                    <p class="text-sm text-gray-600 font-medium">Labs</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Search Section -->
    <div class="mb-6 sm:mb-8">
        <div class="bg-white rounded-2xl shadow-lg p-4 sm:p-6 border border-[#003049]/10">
            <div class="flex flex-col sm:flex-row gap-4 items-start sm:items-center">
                <div class="flex-1 relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </div>
                    <input type="text" id="search-input"
                        class="block w-full pl-10 pr-12 py-3 border border-[#003049]/20 rounded-xl text-sm placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-[#003049] focus:border-transparent transition-colors duration-200"
                        placeholder="Search by patient name, lab no, or ref ID...">
                    <button id="clear-search"
                        class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600 transition-colors duration-200 hidden">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                <div class="flex items-center gap-2 text-sm text-gray-600">
                    <span id="results-count">{{ count($testResults ?? []) }}</span>
                    <span>results found</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Test Results Table -->
    <div class="bg-white rounded-2xl shadow-lg border border-[#003049]/10 overflow-hidden mb-6 sm:mb-8">

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200" id="results-table">
                <thead class="bg-[#003049]">
                    <tr>
                        <th scope="col"
                            class="px-3 sm:px-6 py-3 sm:py-4 text-left text-xs font-medium text-white uppercase tracking-wider">
                            Lab & Doctor Details
                        </th>
                        <th scope="col"
                            class="px-3 sm:px-6 py-3 sm:py-4 text-left text-xs font-medium text-white uppercase tracking-wider">
                            Patient Information
                        </th>
                        <th scope="col"
                            class="px-3 sm:px-6 py-3 sm:py-4 text-left text-xs font-medium text-white uppercase tracking-wider">
                            Lab No
                        </th>
                        <th scope="col"
                            class="px-3 sm:px-6 py-3 sm:py-4 text-left text-xs font-medium text-white uppercase tracking-wider hidden sm:table-cell">
                            Ref ID
                        </th>
                        <th scope="col"
                            class="px-3 sm:px-6 py-3 sm:py-4 text-left text-xs font-medium text-white uppercase tracking-wider">
                            Profiles
                        </th>
                        <th scope="col"
                            class="px-3 sm:px-6 py-3 sm:py-4 text-left text-xs font-medium text-white uppercase tracking-wider hidden sm:table-cell">
                            Status
                        </th>
                        <th scope="col"
                            class="px-3 sm:px-6 py-3 sm:py-4 text-left text-xs font-medium text-white uppercase tracking-wider">
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
                                <span
                                    class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-[#003049]/10 text-[#003049] border border-[#003049]/20">
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
                                            class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-orange-100 text-orange-800 border border-orange-200">
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
                                        <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
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
                                                $tagOnItem = $items->first(function ($item) {
                                                    return $item->is_tagon;
                                                });
                                                if (
                                                    $tagOnItem &&
                                                    $tagOnItem->panel &&
                                                    $tagOnItem->panel->panelTags->isNotEmpty()
                                                ) {
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
                                                @if ($hasTagOn)
                                                    <span
                                                        class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-orange-100 text-orange-800 border border-orange-200">
                                                        <svg class="w-3 h-3 mr-1" fill="currentColor"
                                                            viewBox="0 0 20 20">
                                                            <path fill-rule="evenodd"
                                                                d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
                                                                clip-rule="evenodd" />
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


    </div>

    @if (count($testResults ?? []) === 0)
        <div class="bg-white rounded-2xl shadow-lg border border-[#003049]/10 p-6 sm:p-12 text-center">
            <div
                class="w-12 h-12 sm:w-16 sm:h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-3 sm:mb-4">
                <svg class="w-6 h-6 sm:w-8 sm:h-8 text-gray-400" fill="none" stroke="currentColor"
                    viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M9 5H7a2 2 0 00-2 2v6a2 2 0 002 2h2m5 0h2a2 2 0 002-2V7a2 2 0 00-2-2h-2m-5 4h6m-6 4h6m-6-8h6" />
                </svg>
            </div>
            <h3 class="text-base sm:text-lg font-medium text-gray-900 mb-2">No test results found</h3>
            <p class="text-sm sm:text-base text-gray-500">No test results have been recorded yet.</p>
        </div>
    @endif

    <!-- Pagination -->
    <div class="flex items-center justify-between bg-white rounded-2xl shadow-lg p-4 sm:p-6 border border-[#003049]/10"
        id="pagination-container">
        <div class="flex items-center gap-4">
            <span class="text-sm text-gray-600">
                Showing <span id="current-range">1-{{ min(15, count($testResults ?? [])) }}</span> of <span
                    id="total-count">{{ count($testResults ?? []) }}</span>
            </span>
        </div>
        <div class="flex items-center gap-2">
            <button id="prev-page"
                class="px-3 py-2 text-sm font-medium text-[#003049] bg-white border border-[#003049]/20 rounded-lg hover:bg-[#003049]/5 transition-colors duration-200 disabled:opacity-50 disabled:cursor-not-allowed">
                Previous
            </button>
            <div class="flex items-center gap-1" id="page-numbers"></div>
            <button id="next-page"
                class="px-3 py-2 text-sm font-medium text-[#003049] bg-white border border-[#003049]/20 rounded-lg hover:bg-[#003049]/5 transition-colors duration-200 disabled:opacity-50 disabled:cursor-not-allowed">
                Next
            </button>
        </div>
    </div>

    <script>
        let allResults = [];
        let filteredResults = [];
        let currentPage = 1;
        const itemsPerPage = 5;
        let searchTimeout;

        // Initialize results data
        document.addEventListener('DOMContentLoaded', function() {
            allResults = Array.from(document.querySelectorAll('.result-row')).map((row, index) => ({
                element: row,
                accordionElement: row.nextElementSibling,
                searchText: row.textContent.toLowerCase()
            }));

            filteredResults = [...allResults];
            updatePagination();
            updateDisplay();
        });

        // Search functionality
        const searchInput = document.getElementById('search-input');
        const clearButton = document.getElementById('clear-search');

        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(performSearch, 300);

            if (this.value.length > 0) {
                clearButton.classList.remove('hidden');
            } else {
                clearButton.classList.add('hidden');
            }
        });

        clearButton.addEventListener('click', function() {
            searchInput.value = '';
            this.classList.add('hidden');
            performSearch();
        });

        function performSearch() {
            const searchTerm = searchInput.value.toLowerCase().trim();

            if (searchTerm === '') {
                filteredResults = [...allResults];
            } else {
                filteredResults = allResults.filter(result =>
                    result.searchText.includes(searchTerm)
                );
            }

            currentPage = 1;
            updateResultsCount();
            updatePagination();
            updateDisplay();
        }

        function updateResultsCount() {
            document.getElementById('results-count').textContent = filteredResults.length;
        }

        function updateDisplay() {
            // Hide all results
            allResults.forEach(result => {
                result.element.style.display = 'none';
                result.accordionElement.style.display = 'none';
                result.accordionElement.classList.add('hidden');
            });

            // Show current page results
            const start = (currentPage - 1) * itemsPerPage;
            const end = start + itemsPerPage;
            const currentResults = filteredResults.slice(start, end);

            currentResults.forEach(result => {
                result.element.style.display = '';
                result.accordionElement.style.display = '';
            });

            // Reset all chevrons to closed state
            document.querySelectorAll('[id^="chevron-"]').forEach(chevron => {
                chevron.style.transform = 'rotate(0deg)';
            });

            // Update pagination info
            const totalItems = filteredResults.length;
            const rangeStart = totalItems === 0 ? 0 : start + 1;
            const rangeEnd = Math.min(end, totalItems);

            document.getElementById('current-range').textContent = `${rangeStart}-${rangeEnd}`;
            document.getElementById('total-count').textContent = totalItems;
        }

        function updatePagination() {
            const totalPages = Math.ceil(filteredResults.length / itemsPerPage);
            const prevBtn = document.getElementById('prev-page');
            const nextBtn = document.getElementById('next-page');
            const pageNumbers = document.getElementById('page-numbers');

            // Update button states
            prevBtn.disabled = currentPage === 1;
            nextBtn.disabled = currentPage === totalPages || totalPages === 0;

            // Update page numbers
            pageNumbers.innerHTML = '';
            for (let i = 1; i <= totalPages; i++) {
                const pageBtn = document.createElement('button');
                pageBtn.textContent = i;
                pageBtn.className = `px-3 py-2 text-sm font-medium rounded-lg transition-colors duration-200 ${
                    i === currentPage 
                        ? 'bg-[#003049] text-white' 
                        : 'text-[#003049] bg-white border border-[#003049]/20 hover:bg-[#003049]/5'
                }`;
                pageBtn.addEventListener('click', () => goToPage(i));
                pageNumbers.appendChild(pageBtn);
            }
        }

        function goToPage(page) {
            currentPage = page;
            updatePagination();
            updateDisplay();
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

        // Pagination event listeners
        document.getElementById('prev-page').addEventListener('click', function() {
            if (currentPage > 1) {
                goToPage(currentPage - 1);
            }
        });

        document.getElementById('next-page').addEventListener('click', function() {
            const totalPages = Math.ceil(filteredResults.length / itemsPerPage);
            if (currentPage < totalPages) {
                goToPage(currentPage + 1);
            }
        });
    </script>
</x-app-layout>
