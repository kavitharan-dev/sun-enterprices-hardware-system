<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('brand.name') }} — @yield('title', 'Dashboard')</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=outfit:400,500,600,700|playfair-display:600,700,800" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @stack('styles')
    </head>
    <body class="font-sans antialiased text-walnut-900" x-data="{ sidebarOpen: false }">
        <div class="page-shell min-h-screen lg:flex">
            @include('layouts.partials.sidebar')

            <div class="flex flex-1 flex-col min-w-0">
                @include('layouts.partials.topbar')

                @isset($header)
                    <header class="border-b border-sun-200/80 bg-white/80 backdrop-blur">
                        <div class="px-4 sm:px-6 lg:px-8 py-4">
                            {{ $header }}
                        </div>
                    </header>
                @endisset

                <main class="flex-1 p-4 sm:p-6 lg:p-8">
                    @if (session('success'))
                        <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                            {{ session('success') }}
                        </div>
                    @endif
                    @if (session('error'))
                        <div class="mb-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                            {{ session('error') }}
                        </div>
                    @endif
                    @if ($errors->any())
                        <div class="mb-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                            <p class="font-medium">Please fix the following:</p>
                            <ul class="mt-1 list-disc ps-5">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                    {{ $slot }}
                </main>
            </div>
        </div>

        <div
            x-show="sidebarOpen"
            x-transition:enter="transition-opacity ease-linear duration-200"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition-opacity ease-linear duration-200"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            class="fixed inset-0 z-40 bg-walnut-950/50 lg:hidden"
            @click="sidebarOpen = false"
            x-cloak
        ></div>

        @stack('scripts')
    </body>
</html>
