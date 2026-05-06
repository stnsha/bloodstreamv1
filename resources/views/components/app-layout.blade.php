<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Blood Stream v1</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="antialiased min-h-screen">
    <nav class="bg-white border-b-2 border-[#003049]/10 shadow-md">
        <div class="flex justify-between items-center px-4 py-3 mx-auto max-w-7xl">
            <!-- Left side: Logo only -->
            <div class="flex items-center">
                <div class="flex items-center space-x-3">
                    <div class="w-10 h-10 bg-[#003049] rounded-xl flex items-center justify-center shadow-md">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
                        </svg>
                    </div>
                    <span class="text-xl font-bold text-[#003049] tracking-tight">BloodStream</span>
                </div>
            </div>

            <!-- Right side: Navigation + Logout Button -->
            <div class="hidden md:flex items-center">
                <ul class="flex flex-row font-medium mt-0 space-x-8 rtl:space-x-reverse text-sm mr-8">
                        <a href="{{ route('apis.index') }}"
                            class="{{ request()->is('api') ? 'text-[#991B1B] font-semibold border-b-2 border-[#991B1B] pb-1' : 'text-[#003049] hover:text-[#991B1B] transition-colors duration-200' }}">API
                            Documentation</a>
                    </li>
                </ul>

                <a href="{{ route('logout') }}" type="button"
                    class="text-white bg-[#003049] hover:bg-[#002135] focus:ring-4 focus:outline-none focus:ring-[#003049]/30 font-medium rounded-lg text-sm px-6 py-2.5 text-center transition-all duration-200 shadow-md hover:shadow-lg transform hover:scale-105">
                    Logout
                </a>
            </div>

            <!-- Mobile Hamburger Button -->
            <div class="md:hidden">
                <button type="button" id="mobile-menu-button"
                    class="text-[#003049] hover:text-[#991B1B] focus:outline-none focus:text-[#991B1B] transition-colors duration-200">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M4 6h16M4 12h16M4 18h16"></path>
                    </svg>
                </button>
            </div>
        </div>

        <!-- Mobile Navigation Menu (hidden by default) -->
        <div id="mobile-menu" class="md:hidden hidden bg-white border-t border-gray-200 shadow-lg">
            <div class="px-4 py-3 space-y-3">
                <a href="{{ route('apis.index') }}"
                    class="block py-2 text-[#003049] hover:text-[#991B1B] transition-colors duration-200">API
                    Documentation</a>
                <a href="{{ route('logout') }}"
                    class="block text-white bg-[#003049] hover:bg-[#002135] font-medium rounded-lg text-sm px-4 py-2 text-center mt-3 transition-all duration-200 shadow-md transform hover:scale-105">
                    Logout
                </a>
            </div>
        </div>
    </nav>

    <main class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-6 sm:py-8">
        {{ $slot }}
    </main>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        // Mobile menu toggle
        document.getElementById('mobile-menu-button').addEventListener('click', function() {
            const mobileMenu = document.getElementById('mobile-menu');
            mobileMenu.classList.toggle('hidden');
        });
    </script>
</body>

</html>
