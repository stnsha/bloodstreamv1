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
                <h1 class="text-xl sm:text-2xl font-bold text-[#003049]">Panels</h1>
                <p class="text-gray-600 text-xs sm:text-sm">Manage laboratory test panels and their items</p>
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
                            d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
                    </svg>
                </div>
                <div>
                    <h3 class="text-xl font-bold text-[#003049]">{{ count($panels ?? []) }}</h3>
                    <p class="text-sm text-gray-600 font-medium">Total Panels</p>
                </div>
            </div>
        </div>

        <div
            class="bg-white rounded-2xl shadow-lg p-4 sm:p-6 border border-[#003049]/10 hover:shadow-xl transition-all duration-300 transform hover:scale-105">
            <div class="flex items-center gap-3">
                <div class="w-12 h-12 bg-[#991B1B]/10 rounded-xl flex items-center justify-center">
                    <svg class="w-6 h-6 text-[#991B1B]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" />
                    </svg>
                </div>
                <div>
                    <h3 class="text-xl font-bold text-[#003049]">
                        {{ ($panels ?? collect())->sum('panel_items_count') }}
                    </h3>
                    <p class="text-sm text-gray-600 font-medium">Total Panel Items</p>
                </div>
            </div>
        </div>

        <div
            class="bg-white rounded-2xl shadow-lg p-4 sm:p-6 border border-[#003049]/10 hover:shadow-xl transition-all duration-300 transform hover:scale-105">
            <div class="flex items-center gap-3">
                <div class="w-12 h-12 bg-[#059669]/10 rounded-xl flex items-center justify-center">
                    <svg class="w-6 h-6 text-[#059669]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                    </svg>
                </div>
                <div>
                    <h3 class="text-xl font-bold text-[#003049]">
                        {{ ($panels ?? collect())->pluck('lab')->unique('id')->count() }}
                    </h3>
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
                        placeholder="Search by panel name, code, or lab name...">
                    <button id="clear-search"
                        class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600 transition-colors duration-200 hidden">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                <div class="flex items-center gap-2 text-sm text-gray-600">
                    <span id="results-count">{{ count($panels ?? []) }}</span>
                    <span>panels found</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Panels Table -->
    <div class="bg-white rounded-2xl shadow-lg border border-[#003049]/10 overflow-hidden mb-6 sm:mb-8">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200" id="panels-table">
                <thead class="bg-[#003049]">
                    <tr>
                        <th scope="col"
                            class="px-3 sm:px-6 py-3 sm:py-4 text-left text-xs font-medium text-white uppercase tracking-wider">
                            Lab Name</th>
                        <th scope="col"
                            class="px-3 sm:px-6 py-3 sm:py-4 text-left text-xs font-medium text-white uppercase tracking-wider">
                            Panel Name</th>
                        <th scope="col"
                            class="px-3 sm:px-6 py-3 sm:py-4 text-left text-xs font-medium text-white uppercase tracking-wider">
                            Panel Code</th>
                        <th scope="col"
                            class="px-3 sm:px-6 py-3 sm:py-4 text-left text-xs font-medium text-white uppercase tracking-wider">
                            Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200" id="panels-tbody">
                    @foreach ($panels as $index => $panel)
                        <tr class="hover:bg-gray-50 transition-colors duration-150 panel-row"
                            data-index="{{ $index }}">
                            <td class="px-3 sm:px-6 py-3 sm:py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-[#003049]">
                                    {{ $panel->lab->name ?? 'N/A' }}
                                </div>
                            </td>
                            <td class="px-3 sm:px-6 py-3 sm:py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-[#003049]">{{ $panel->name }}</div>
                            </td>
                            <td class="px-3 sm:px-6 py-3 sm:py-4 whitespace-nowrap">
                                <span
                                    class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-[#003049]/10 text-[#003049] border border-[#003049]/20">
                                    {{ $panel->code }}
                                </span>
                            </td>
                            <td class="px-3 sm:px-6 py-3 sm:py-4 whitespace-nowrap">
                                <a href="{{ route('panels.show', $panel->id) }}"
                                    class="inline-flex items-center px-3 py-1 border border-blue-300 rounded-lg text-xs font-medium text-blue-700 bg-blue-50 hover:bg-blue-100 transition-colors duration-200">
                                    <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                    </svg>
                                    View
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    @if (count($panels) === 0)
        <div class="bg-white rounded-2xl shadow-lg border border-[#003049]/10 p-6 sm:p-12 text-center">
            <div
                class="w-12 h-12 sm:w-16 sm:h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-3 sm:mb-4">
                <svg class="w-6 h-6 sm:w-8 sm:h-8 text-gray-400" fill="none" stroke="currentColor"
                    viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
                </svg>
            </div>
            <h3 class="text-base sm:text-lg font-medium text-gray-900 mb-2">No panels found</h3>
            <p class="text-sm sm:text-base text-gray-500">There are no panels available at the moment.</p>
        </div>
    @endif

    <!-- Pagination -->
    <div class="flex items-center justify-between bg-white rounded-2xl shadow-lg p-4 sm:p-6 border border-[#003049]/10"
        id="pagination-container">
        <div class="flex items-center gap-4">
            <span class="text-sm text-gray-600">
                Showing <span id="current-range">1-{{ min(15, count($panels)) }}</span> of <span
                    id="total-count">{{ count($panels) }}</span>
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
        let allPanels = [];
        let filteredPanels = [];
        let currentPage = 1;
        const itemsPerPage = 10;
        let searchTimeout;

        // Initialize panels data
        document.addEventListener('DOMContentLoaded', function() {
            allPanels = Array.from(document.querySelectorAll('.panel-row')).map(row => ({
                element: row,
                searchText: row.textContent.toLowerCase()
            }));

            filteredPanels = [...allPanels];
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
                filteredPanels = [...allPanels];
            } else {
                filteredPanels = allPanels.filter(panel =>
                    panel.searchText.includes(searchTerm)
                );
            }

            currentPage = 1;
            updateResultsCount();
            updatePagination();
            updateDisplay();
        }

        function updateResultsCount() {
            document.getElementById('results-count').textContent = filteredPanels.length;
        }

        function updateDisplay() {
            // Hide all panels
            allPanels.forEach(panel => {
                panel.element.style.display = 'none';
            });

            // Show current page panels
            const start = (currentPage - 1) * itemsPerPage;
            const end = start + itemsPerPage;
            const currentPanels = filteredPanels.slice(start, end);

            currentPanels.forEach(panel => {
                panel.element.style.display = '';
            });

            // Update pagination info
            const totalItems = filteredPanels.length;
            const rangeStart = totalItems === 0 ? 0 : start + 1;
            const rangeEnd = Math.min(end, totalItems);

            document.getElementById('current-range').textContent = `${rangeStart}-${rangeEnd}`;
            document.getElementById('total-count').textContent = totalItems;
        }

        function updatePagination() {
            const totalPages = Math.ceil(filteredPanels.length / itemsPerPage);
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

        // Pagination event listeners
        document.getElementById('prev-page').addEventListener('click', function() {
            if (currentPage > 1) {
                goToPage(currentPage - 1);
            }
        });

        document.getElementById('next-page').addEventListener('click', function() {
            const totalPages = Math.ceil(filteredPanels.length / itemsPerPage);
            if (currentPage < totalPages) {
                goToPage(currentPage + 1);
            }
        });
    </script>
</x-app-layout>
