<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Blood Stream v1</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="antialiased bg-stone-50">
    <nav class="border-b-2 border-stone-100">
        <div class="flex justify-between max-w-screen-xl px-4 py-3 mx-auto">
            <div class="flex items-center">
                <ul class="flex flex-row font-medium mt-0 space-x-8 rtl:space-x-reverse text-sm">
                    <li>
                        <a href="{{ route('dashboard') }}"
                            class="{{ request()->is('dashboard') ? 'text-red-800 hover:text-[#003049]' : 'text-gray-900 hover:text-red-800' }}">
                            Dashboard
                        </a>
                    </li>
                    <li>
                        <a href="{{ route('lab.index') }}"
                            class="{{ request()->is('lab') ? 'text-red-800 hover:text-[#003049]' : 'text-gray-900 hover:text-red-800' }}">Labs</a>
                    </li>
                    {{-- <li>
                        <a href="{{ route('sync.index') }}"
                            class="{{ request()->is('sync') ? 'text-red-800 hover:text-[#003049]' : 'text-gray-900 hover:text-red-800' }}">File
                            History</a>
                    </li>
                    <li>
                        <a href="{{ route('review.index') }}"
                            class="{{ request()->is('review') ? 'text-red-800 hover:text-[#003049]' : 'text-gray-900 hover:text-red-800' }}">AI
                            Review</a>
                    </li>
                    <li>
                        <a href="{{ route('panel.index') }}"
                            class="{{ request()->is('panel') ? 'text-red-800 hover:text-[#003049]' : 'text-gray-900 hover:text-red-800' }}">Panels</a>
                    </li> --}}
                </ul>
            </div>
            <div class="flex items-center">
                <a href="{{ route('logout') }}" type="button"
                    class="text-white bg-[#003049] hover:bg-blue-900 focus:ring-4 focus:outline-none focus:ring-blue-300 font-medium rounded-lg text-sm w-full sm:w-auto px-5 py-2.5 text-center">Logout</a>
            </div>
        </div>
    </nav>

    <div class="flex flex-col justify-center mx-auto items-start md:ml-36 md:mt-4 md:mr-36">
        {{ $slot }}
    </div>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

</body>

</html>
