<x-app-layout>
    <!-- Header Section -->
    <div class="mb-6 sm:mb-8">
        <div class="flex items-center gap-3 mb-4 sm:mb-6">
            <div class="w-12 h-12 sm:w-14 sm:h-14 bg-[#003049] rounded-xl flex items-center justify-center shadow-md">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 sm:w-7 sm:h-7 text-white" fill="none"
                    stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
                </svg>
            </div>
            <div>
                <h1 class="text-xl sm:text-2xl font-bold text-[#003049]">Panel Details</h1>
                <p class="text-gray-600 text-xs sm:text-sm">{{ $panel->name }} - {{ $panel->code }}</p>
            </div>
        </div>

        <!-- Back Button -->
        <div class="mb-4">
            <a href="{{ route('panels.index') }}"
                class="inline-flex items-center px-4 py-2 text-sm font-medium text-[#003049] bg-white border border-[#003049]/20 rounded-lg hover:bg-[#003049]/5 transition-colors duration-200">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                </svg>
                Back to Panels
            </a>
        </div>
    </div>

    <!-- Panel Overview Card -->
    <div class="bg-white rounded-2xl shadow-lg border border-[#003049]/10 overflow-hidden mb-6 sm:mb-8">
        <div class="p-4 sm:p-6 border-b border-[#003049]/10">
            <div class="flex items-center gap-3 mb-2 sm:mb-0">
                <div class="w-10 h-10 bg-[#003049]/10 rounded-xl flex items-center justify-center">
                    <svg class="w-6 h-6 text-[#003049]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
                    </svg>
                </div>
                <h2 class="text-lg sm:text-xl font-semibold text-[#003049]">Panel Overview</h2>
            </div>
        </div>

        <div class="p-4 sm:p-6">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                <!-- Panel Details -->
                <div class="bg-gray-50 rounded-xl p-4 border border-gray-200">
                    <h3 class="font-semibold text-[#003049] mb-3 flex items-center gap-2">
                        <svg class="w-5 h-5 text-[#003049]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
                        </svg>
                        Panel Information
                    </h3>
                    <div class="space-y-3">
                        <div>
                            <div class="text-xs text-gray-600 uppercase tracking-wider">Panel Name</div>
                            <div class="text-sm font-medium text-[#003049]">{{ $panel->name }}</div>
                        </div>
                        <div>
                            <div class="text-xs text-gray-600 uppercase tracking-wider">Panel Code</div>
                            <span
                                class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-[#003049]/10 text-[#003049] border border-[#003049]/20">
                                {{ $panel->code }}
                            </span>
                            @if ($panel->panelTags && count($panel->panelTags) > 0)
                                <div class="mt-2">
                                    <div class="text-xs text-gray-600 uppercase tracking-wider mb-1">Panel Tag Codes
                                    </div>
                                    <div class="flex flex-wrap gap-1">
                                        @foreach ($panel->panelTags as $tag)
                                            @if ($tag->code)
                                                <span
                                                    class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-orange-100 text-orange-800 border border-orange-200">
                                                    {{ $tag->code }}
                                                </span>
                                            @endif
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        </div>
                        @if ($panel->int_code)
                            <div>
                                <div class="text-xs text-gray-600 uppercase tracking-wider">LIS Code</div>
                                <div class="text-sm font-medium text-gray-700">{{ $panel->int_code }}</div>
                            </div>
                        @endif
                        @if ($panel->sequence)
                            <div>
                                <div class="text-xs text-gray-600 uppercase tracking-wider">Sequence</div>
                                <div class="text-sm font-medium text-gray-700">{{ $panel->sequence }}</div>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Lab Details -->
                <div class="bg-gray-50 rounded-xl p-4 border border-gray-200">
                    <h3 class="font-semibold text-[#003049] mb-3 flex items-center gap-2">
                        <svg class="w-5 h-5 text-[#003049]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                        </svg>
                        Laboratory Information
                    </h3>
                    <div class="space-y-3">
                        <div>
                            <div class="text-xs text-gray-600 uppercase tracking-wider">Lab Name</div>
                            <div class="text-sm font-medium text-[#003049]">{{ $panel->lab->name ?? 'N/A' }}</div>
                        </div>
                        @if ($panel->lab && $panel->lab->code)
                            <div>
                                <div class="text-xs text-gray-600 uppercase tracking-wider">Lab Code</div>
                                <div class="text-sm font-medium text-gray-700">{{ $panel->lab->code }}</div>
                            </div>
                        @endif
                        @if ($panel->lab && $panel->lab->status)
                            <div>
                                <div class="text-xs text-gray-600 uppercase tracking-wider">Status</div>
                                <span
                                    class="inline-flex items-center px-2 py-1 rounded-md text-xs font-medium {{ ucfirst($panel->lab->status) === '1' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' }}">
                                    {{ ucfirst($panel->lab->status) != 1 ? 'Inactive' : 'Active' }}
                                </span>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Statistics Row -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="bg-blue-50 rounded-lg p-3 border border-blue-200">
                    <div class="text-xs text-gray-600 mb-1">Panel Items</div>
                    <div class="text-lg font-bold text-[#003049] flex items-center gap-2">
                        {{-- {{ count($panel->panelItems ?? []) }} --}}
                        <span
                            class="inline-flex items-center justify-center w-6 h-6 text-xs font-medium text-white bg-blue-500 rounded-full">
                            {{ count($panel->panelItems ?? []) }}
                        </span>
                    </div>
                </div>

                @php
                    $totalRefRanges = 0;
                    $totalResults = 0;
                    foreach ($panel->panelItems ?? [] as $item) {
                        $panelPanelItem = \App\Models\PanelPanelItem::where('panel_id', $panel->id)
                            ->where('panel_item_id', $item->id)
                            ->first();
                        if ($panelPanelItem) {
                            $totalRefRanges += $panelPanelItem->referenceRanges->count();
                            $totalResults += $panelPanelItem->testResultItems->count();
                        }
                    }
                @endphp

                <div class="bg-purple-50 rounded-lg p-3 border border-purple-200">
                    <div class="text-xs text-gray-600 mb-1">Reference Ranges</div>
                    <div class="text-lg font-bold text-[#003049] flex items-center gap-2">
                        {{-- {{ $totalRefRanges }} --}}
                        <span
                            class="inline-flex items-center justify-center w-6 h-6 text-xs font-medium text-white bg-purple-500 rounded-full">
                            {{ $totalRefRanges }}
                        </span>
                    </div>
                </div>

                <div class="bg-green-50 rounded-lg p-3 border border-green-200">
                    <div class="text-xs text-gray-600 mb-1">Total Results</div>
                    <div class="text-lg font-bold text-[#003049] flex items-center gap-2">
                        {{-- {{ $totalResults }} --}}
                        <span
                            class="inline-flex items-center justify-center w-6 h-6 text-xs font-medium text-white bg-green-500 rounded-full">
                            {{ $totalResults }}
                        </span>
                    </div>
                </div>
            </div>

            @if ($panel->overall_notes)
                <div class="mt-6 p-4 bg-yellow-50 rounded-lg border border-yellow-200">
                    <h4 class="text-sm font-medium text-yellow-800 mb-2">Notes</h4>
                    <p class="text-sm text-yellow-700">{{ $panel->overall_notes }}</p>
                </div>
            @endif
        </div>
    </div>


    <!-- Panel Items -->
    <div class="space-y-6">
        @if ($panel->panelItems && count($panel->panelItems) > 0)
            @foreach ($panel->panelItems as $item)
                @php
                    $panelPanelItem = \App\Models\PanelPanelItem::where('panel_id', $panel->id)
                        ->where('panel_item_id', $item->id)
                        ->first();
                    $refRanges = $panelPanelItem ? $panelPanelItem->referenceRanges : collect([]);
                    $resultItems = $panelPanelItem ? $panelPanelItem->testResultItems : collect([]);
                @endphp
                <div class="bg-white rounded-2xl shadow-lg border border-[#003049]/10 overflow-hidden">
                    <div class="p-4 sm:p-6 border-b border-[#003049]/10">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 bg-[#003049]/10 rounded-xl flex items-center justify-center">
                                    <svg class="w-6 h-6 text-[#003049]" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" />
                                    </svg>
                                </div>
                                <div>
                                    <h3 class="text-lg sm:text-xl font-semibold text-[#003049]">{{ $item->name }}
                                    </h3>
                                    @if ($item->code)
                                        <p class="text-sm text-gray-600">Code: {{ $item->code }}</p>
                                    @endif
                                </div>
                            </div>
                            {{-- <div class="flex items-center gap-2">
                                <span
                                    class="inline-flex items-center justify-center w-8 h-8 text-sm font-medium text-white bg-blue-500 rounded-full"
                                    title="Reference Ranges">
                                    {{ $refRanges->count() }}
                                </span>
                                <span
                                    class="inline-flex items-center justify-center w-8 h-8 text-sm font-medium text-white bg-green-500 rounded-full"
                                    title="Test Results">
                                    {{ $resultItems->count() }}
                                </span>
                            </div> --}}
                        </div>
                    </div>

                    <div class="p-4 sm:p-6">
                        <!-- Panel Item Details -->
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                            @if ($item->unit)
                                <div class="bg-gray-50 rounded-lg p-3">
                                    <div class="text-xs text-gray-600 uppercase tracking-wider mb-1">Unit</div>
                                    <div class="text-sm font-medium text-[#003049]">{{ $item->unit }}</div>
                                </div>
                            @endif
                            @if ($item->decimal_point !== null)
                                <div class="bg-gray-50 rounded-lg p-3">
                                    <div class="text-xs text-gray-600 uppercase tracking-wider mb-1">Decimal Points
                                    </div>
                                    <div class="text-sm font-medium text-[#003049]">{{ $item->decimal_point }}</div>
                                </div>
                            @endif
                            @if ($item->sequence)
                                <div class="bg-gray-50 rounded-lg p-3">
                                    <div class="text-xs text-gray-600 uppercase tracking-wider mb-1">Sequence</div>
                                    <div class="text-sm font-medium text-[#003049]">{{ $item->sequence }}</div>
                                </div>
                            @endif
                            @if ($item->result_type)
                                <div class="bg-gray-50 rounded-lg p-3">
                                    <div class="text-xs text-gray-600 uppercase tracking-wider mb-1">Result Type</div>
                                    <div class="text-sm font-medium text-[#003049]">{{ $item->result_type }}</div>
                                </div>
                            @endif
                        </div>

                        <!-- Reference Ranges -->
                        @if ($refRanges->count() > 0)
                            <div class="mb-6">
                                <h4 class="text-base font-semibold text-[#003049] mb-3 flex items-center gap-2">
                                    <svg class="w-5 h-5 text-[#003049]" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                                    </svg>
                                    Reference Ranges
                                    <span
                                        class="inline-flex items-center justify-center w-6 h-6 text-xs font-medium text-white bg-blue-500 rounded-full">
                                        {{ $refRanges->count() }}
                                    </span>
                                </h4>
                                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                                    @foreach ($refRanges as $range)
                                        <div class="bg-blue-50 rounded-lg p-3 border border-blue-200">
                                            <div class="text-sm font-medium text-blue-900">{{ $range->value }}</div>
                                            <div class="text-xs text-blue-600 mt-1">Range #{{ $loop->iteration }}
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        <!-- Results Summary -->
                        <div class="bg-green-50 rounded-lg p-4 border border-green-200">
                            <h4 class="text-base font-semibold text-green-900 mb-2 flex items-center gap-2">
                                <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                Test Results Summary
                                <span
                                    class="inline-flex items-center justify-center w-6 h-6 text-xs font-medium text-white bg-green-500 rounded-full">
                                    {{ $resultItems->count() }}
                                </span>
                            </h4>
                            <div class="text-sm text-green-800">
                                This panel item has been used in <strong>{{ $resultItems->count() }}</strong> test
                                results.
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        @else
            <div class="bg-white rounded-2xl shadow-lg border border-[#003049]/10 p-6 sm:p-12 text-center">
                <div
                    class="w-12 h-12 sm:w-16 sm:h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-3 sm:mb-4">
                    <svg class="w-6 h-6 sm:w-8 sm:h-8 text-gray-400" fill="none" stroke="currentColor"
                        viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" />
                    </svg>
                </div>
                <h3 class="text-base sm:text-lg font-medium text-gray-900 mb-2">No panel items found</h3>
                <p class="text-sm sm:text-base text-gray-500">This panel doesn't have any items configured yet.</p>
            </div>
        @endif
    </div>
</x-app-layout>
