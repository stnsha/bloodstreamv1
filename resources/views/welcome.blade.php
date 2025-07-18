<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Blood Stream v1</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="antialiased">
    @if (app()->environment('production'))
        <h1 class="font-bold">403 Forbidden</h1>
        <p>You don't have permission to access this resource.</p>
    @else
        <div class="flex flex-col min-h-screen max-w-sm mx-auto justify-center">
            <form action="{{ route('login') }}" method="post">
                @csrf
                <div class="mb-5">
                    <label for="username" class="block mb-2 text-sm font-medium text-gray-900">Username</label>
                    <input type="text" id="username" name="username"
                        class="bg-gray-50 border border-gray-300 text-gray-900 text- rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5" />
                    @error('username')
                        <div class="bg-red-100 text-red-800 text-xs font-medium mt-2 me-2 px-2.5 py-0.5 rounded">
                            {{ $message }}
                        </div>
                    @enderror
                </div>
                <div class="mb-5">
                    <label for="password" class="block mb-2 text-sm font-medium text-gray-900">Password</label>
                    <input type="password" id="password" name="password"
                        class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5" />
                    @error('password')
                        <div class="bg-red-100 text-red-800 text-xs font-medium mt-2 me-2 px-2.5 py-1.5 rounded">
                            {{ $message }}
                        </div>
                    @enderror
                </div>
                @if ($errors->has('login'))
                    <div class="bg-red-100 text-red-800 text-xs font-medium me-2 px-2.5 py-1.5 rounded mb-5">
                        <ul>
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
                <button type="submit"
                    class="text-white bg-[#003049] hover:bg-blue-900 focus:ring-4 focus:outline-none focus:ring-blue-300 font-medium rounded-lg text-sm w-full sm:w-auto px-5 py-2.5 text-center">Login</button>
            </form>
        </div>
    @endif
</body>

</html>
