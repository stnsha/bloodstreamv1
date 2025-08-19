<x-app-layout>
    <!-- Header Section -->
    <div class="mb-6 sm:mb-8">
        <div class="flex items-center gap-3 mb-4 sm:mb-6">
            <div class="w-12 h-12 sm:w-14 sm:h-14 bg-[#003049] rounded-xl flex items-center justify-center shadow-md">
                <svg class="w-6 h-6 sm:w-7 sm:h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
                </svg>
            </div>
            <div>
                <h1 class="text-xl sm:text-2xl font-bold text-[#003049]">Laboratory Management</h1>
                <p class="text-gray-600 text-xs sm:text-sm">Manage laboratory information and configurations</p>
            </div>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4 sm:gap-6 mb-6 sm:mb-8">
        <div class="bg-white rounded-2xl shadow-lg p-4 sm:p-6 border border-[#003049]/10 hover:shadow-xl transition-all duration-300 transform hover:scale-105">
            <div class="flex items-center gap-3">
                <div class="w-12 h-12 bg-[#003049]/10 rounded-xl flex items-center justify-center">
                    <svg class="w-6 h-6 text-[#003049]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v6a2 2 0 002 2h2m5 0h2a2 2 0 002-2V7a2 2 0 00-2-2h-2m-5 4h6m-6 4h6m-6-8h6" />
                    </svg>
                </div>
                <div>
                    <h3 class="text-xl font-bold text-[#003049]">{{ count($labs) }}</h3>
                    <p class="text-sm text-gray-600 font-medium">Total Labs</p>
                </div>
            </div>
        </div>
        
        <div class="bg-white rounded-2xl shadow-lg p-4 sm:p-6 border border-[#003049]/10 hover:shadow-xl transition-all duration-300 transform hover:scale-105">
            <div class="flex items-center gap-3">
                <div class="w-12 h-12 bg-[#991B1B]/10 rounded-xl flex items-center justify-center">
                    <svg class="w-6 h-6 text-[#991B1B]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <div>
                    <h3 class="text-xl font-bold text-[#003049]">{{ count($labs->where('path', '!=', null)) }}</h3>
                    <p class="text-sm text-gray-600 font-medium">Active Paths</p>
                </div>
            </div>
        </div>
        
        <div class="bg-white rounded-2xl shadow-lg p-4 sm:p-6 border border-[#003049]/10 hover:shadow-xl transition-all duration-300 transform hover:scale-105">
            <div class="flex items-center gap-3">
                <div class="w-12 h-12 bg-[#003049]/10 rounded-xl flex items-center justify-center">
                    <svg class="w-6 h-6 text-[#003049]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                    </svg>
                </div>
                <div>
                    <h3 class="text-xl font-bold text-[#003049]">{{ count($labs->where('code', '!=', null)) }}</h3>
                    <p class="text-sm text-gray-600 font-medium">Configured</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Labs Table Card -->
    <div class="bg-white rounded-2xl shadow-lg border border-[#003049]/10 overflow-hidden">
        <div class="p-4 sm:p-6 border-b border-[#003049]/10">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-center gap-3 mb-2 sm:mb-0">
                    <div class="w-10 h-10 bg-[#003049]/10 rounded-xl flex items-center justify-center">
                        <svg class="w-6 h-6 text-[#003049]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                        </svg>
                    </div>
                    <h2 class="text-lg sm:text-xl font-semibold text-[#003049]">Lab Information</h2>
                </div>
                <div class="text-xs sm:text-sm text-gray-500 bg-gray-50 px-3 py-1 rounded-full">
                    {{ count($labs) }} {{ Str::plural('lab', count($labs)) }} configured
                </div>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-[#003049]/5 border-b border-[#003049]/10">
                    <tr>
                        <th scope="col" class="px-3 sm:px-6 py-3 sm:py-4 text-left text-xs font-semibold text-[#003049] uppercase tracking-wider">
                            Lab Name
                        </th>
                        <th scope="col" class="px-3 sm:px-6 py-3 sm:py-4 text-left text-xs font-semibold text-[#003049] uppercase tracking-wider">
                            Code
                        </th>
                        <th scope="col" class="px-3 sm:px-6 py-3 sm:py-4 text-left text-xs font-semibold text-[#003049] uppercase tracking-wider hidden sm:table-cell">
                            Path
                        </th>
                        <th scope="col" class="px-3 sm:px-6 py-3 sm:py-4 text-left text-xs font-semibold text-[#003049] uppercase tracking-wider">
                            Status
                        </th>
                        <th scope="col" class="px-3 sm:px-6 py-3 sm:py-4 text-left text-xs font-semibold text-[#003049] uppercase tracking-wider hidden sm:table-cell">
                            Action
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @foreach ($labs as $lab)
                        <tr class="hover:bg-[#003049]/5 transition-colors duration-200">
                            <td class="px-3 sm:px-6 py-3 sm:py-4 whitespace-nowrap">
                                <div class="flex items-center gap-2 sm:gap-3">
                                    <div class="w-8 h-8 bg-[#003049] rounded-lg flex items-center justify-center shadow-sm">
                                        <span class="text-white text-xs font-bold">{{ substr($lab->name, 0, 1) }}</span>
                                    </div>
                                    <div>
                                        <div class="text-xs sm:text-sm font-medium text-[#003049]">{{ $lab->name }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-3 sm:px-6 py-3 sm:py-4 whitespace-nowrap">
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-[#003049]/10 text-[#003049] border border-[#003049]/20">
                                    {{ $lab->code }}
                                </span>
                            </td>
                            <td class="px-3 sm:px-6 py-3 sm:py-4 whitespace-nowrap hidden sm:table-cell">
                                @if($lab->path)
                                    <div class="text-sm text-[#003049] font-mono bg-[#003049]/5 px-3 py-1 rounded border border-[#003049]/10">
                                        {{ $lab->path }}
                                    </div>
                                @else
                                    <span class="text-sm text-gray-500 italic bg-gray-50 px-2 py-1 rounded">No path configured</span>
                                @endif
                            </td>
                            <td class="px-3 sm:px-6 py-3 sm:py-4 whitespace-nowrap">
                                @if($lab->path && $lab->code)
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-[#991B1B]/10 text-[#991B1B] border border-[#991B1B]/20">
                                        <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                        </svg>
                                        Active
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800 border border-yellow-200">
                                        <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                                        </svg>
                                        Incomplete
                                    </span>
                                @endif
                            </td>
                            <td class="px-3 sm:px-6 py-3 sm:py-4 whitespace-nowrap text-sm text-gray-500 hidden sm:table-cell">
                                <div class="flex items-center gap-2">
                                    <button type="button" disabled 
                                        class="inline-flex items-center px-2 sm:px-3 py-1 sm:py-1.5 border border-gray-300 rounded-lg text-xs font-medium text-gray-400 bg-gray-50 cursor-not-allowed">
                                        <svg class="w-3 h-3 sm:w-4 sm:h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                        </svg>
                                        Sync
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if(count($labs) === 0)
            <div class="p-6 sm:p-12 text-center">
                <div class="w-12 h-12 sm:w-16 sm:h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-3 sm:mb-4">
                    <svg class="w-6 h-6 sm:w-8 sm:h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                    </svg>
                </div>
                <h3 class="text-base sm:text-lg font-medium text-gray-900 mb-2">No labs configured</h3>
                <p class="text-sm sm:text-base text-gray-500 mb-4 sm:mb-6">Get started by configuring your first laboratory.</p>
            </div>
        @endif
    </div>
</x-app-layout>
