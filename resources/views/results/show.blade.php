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

    <!-- Test Results Profiles -->
    <div class="space-y-3">
        @foreach ($profileResults as $index => $profileResult)
            <!-- Profile Header -->
            <div class="bg-blue-50 rounded-2xl border border-blue-200 overflow-hidden">
                <button
                    class="w-full p-4 text-left hover:bg-blue-100 transition-colors duration-200 flex items-center justify-between"
                    onclick="toggleProfile({{ $index }})" type="button">
                    <div>
                        <h2 class="text-xl font-bold text-blue-900">{{ $profileResult['profile']->name }}</h2>
                        <p class="text-sm text-blue-700">{{ $profileResult['totalItems'] }} total
                            {{ Str::plural('item', $profileResult['totalItems']) }}</p>
                    </div>
                    <div class="transform transition-transform duration-200" id="arrow-{{ $index }}">
                        <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7">
                            </path>
                        </svg>
                    </div>
                </button>
            </div>

            <!-- Panels within this Profile -->
            <div class="space-y-4 overflow-hidden transition-all duration-500 ease-in-out"
                id="profile-{{ $index }}" style="max-height: 0; opacity: 0; margin-top: 0; padding-top: 0; padding-bottom: 0;">
                @foreach ($profileResult['panelGroups'] as $panelGroup)
                    <div class="bg-white rounded-2xl shadow-lg border border-[#003049]/10 overflow-hidden">
                        <div class="p-4 sm:p-6 border-b border-[#003049]/10">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 bg-[#003049]/10 rounded-xl flex items-center justify-center">
                                    <svg class="w-6 h-6 text-[#003049]" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
                                    </svg>
                                </div>
                                <h3 class="text-lg sm:text-xl font-semibold text-[#003049]">
                                    {{ $panelGroup['displayName'] }}</h3>
                                @if ($panelGroup['hasTagOn'])
                                    <span
                                        class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-orange-100 text-orange-800 border border-orange-200">
                                        <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd"
                                                d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
                                                clip-rule="evenodd" />
                                        </svg>
                                        Tag On
                                    </span>
                                @endif
                                <div
                                    class="text-xs sm:text-sm text-gray-500 bg-gray-50 px-3 py-1 rounded-full ml-auto">
                                    {{ $panelGroup['itemCount'] }} {{ Str::plural('item', $panelGroup['itemCount']) }}
                                </div>
                            </div>
                        </div>

                        <div class="p-4 sm:p-6">
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                                @foreach ($panelGroup['items'] as $item)
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
                    </div>
                @endforeach
            </div>
        @endforeach
    </div>

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

    <script>
        function toggleProfile(index) {
            const allProfiles = document.querySelectorAll('[id^="profile-"]');
            const allArrows = document.querySelectorAll('[id^="arrow-"]');
            const currentProfile = document.getElementById('profile-' + index);
            const currentArrow = document.getElementById('arrow-' + index);
            
            const isCurrentlyOpen = currentProfile.style.maxHeight !== '0px' && currentProfile.style.maxHeight !== '';

            // Close all other profiles smoothly
            allProfiles.forEach((profile, i) => {
                if (i !== index) {
                    // Set current height before closing for smooth transition
                    profile.style.maxHeight = profile.scrollHeight + 'px';
                    // Force reflow
                    profile.offsetHeight;
                    // Close
                    profile.style.maxHeight = '0';
                    profile.style.opacity = '0';
                    profile.style.marginTop = '0';
                    profile.style.paddingTop = '0';
                    profile.style.paddingBottom = '0';
                }
            });

            // Reset all other arrows
            allArrows.forEach((arrow, i) => {
                if (i !== index) {
                    arrow.style.transform = 'rotate(0deg)';
                }
            });

            // Toggle current profile
            if (!isCurrentlyOpen) {
                // Open current profile
                currentProfile.style.maxHeight = currentProfile.scrollHeight + 'px';
                currentProfile.style.opacity = '1';
                currentProfile.style.marginTop = '0.75rem';
                currentProfile.style.paddingTop = '1rem';
                currentProfile.style.paddingBottom = '1rem';
                currentArrow.style.transform = 'rotate(90deg)';

                // After animation completes, set to auto for dynamic content
                setTimeout(() => {
                    if (currentProfile.style.opacity === '1') {
                        currentProfile.style.maxHeight = 'none';
                    }
                }, 500);
            } else {
                // Close current profile
                currentProfile.style.maxHeight = currentProfile.scrollHeight + 'px';
                // Force reflow
                currentProfile.offsetHeight;
                currentProfile.style.maxHeight = '0';
                currentProfile.style.opacity = '0';
                currentProfile.style.marginTop = '0';
                currentProfile.style.paddingTop = '0';
                currentProfile.style.paddingBottom = '0';
                currentArrow.style.transform = 'rotate(0deg)';
            }
        }

        // Open first profile by default
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(() => {
                if (document.getElementById('profile-0')) {
                    toggleProfile(0);
                }
            }, 100);
        });
    </script>
</x-app-layout>
