<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('brand.name') }} — POS</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=outfit:400,500,600,700|playfair-display:600,700,800" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @stack('styles')
    </head>
    <body class="font-sans antialiased text-walnut-900">
        <div class="flex min-h-screen flex-col bg-[#f6efe4]">
            <header class="sticky top-0 z-50 border-b border-amber-200/80 bg-walnut-950 text-sun-50 shadow-md">
                <div class="flex flex-wrap items-center justify-between gap-3 px-4 py-3 sm:px-6">
                    <div class="flex min-w-0 items-center gap-3">
                        <x-brand-mark size="sm" />
                        <div class="min-w-0">
                            <p class="brand-wordmark truncate text-lg text-sun-200">{{ config('brand.name') }}</p>
                            <p class="text-xs font-semibold uppercase tracking-wider text-sun-100/70">Point of Sale</p>
                        </div>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <a href="{{ route('cashier.daily-accounts.index') }}" class="rounded-lg border border-white/15 bg-white/10 px-3 py-2 text-sm font-semibold text-sun-50 hover:bg-white/20">Daily Accounts</a>
                        <a href="{{ route('store.products.create') }}" class="rounded-lg border border-white/15 bg-white/10 px-3 py-2 text-sm font-semibold text-sun-50 hover:bg-white/20">Add product</a>
                        <a href="{{ route('dashboard') }}" class="rounded-lg bg-amber-500 px-3 py-2 text-sm font-bold text-walnut-950 hover:bg-amber-400">Exit POS</a>
                    </div>
                </div>
            </header>

            @if (session('success'))
                <div class="border-b border-emerald-200 bg-emerald-50 px-4 py-2 text-sm text-emerald-800 sm:px-6">{{ session('success') }}</div>
            @endif
            @if (session('error'))
                <div class="border-b border-rose-200 bg-rose-50 px-4 py-2 text-sm text-rose-800 sm:px-6">{{ session('error') }}</div>
            @endif
            @if ($errors->any())
                <div class="border-b border-rose-200 bg-rose-50 px-4 py-2 text-sm text-rose-800 sm:px-6">
                    <ul class="list-disc ps-5">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <main class="flex-1 p-3 sm:p-4 lg:p-5">
                {{ $slot }}
            </main>
        </div>

        @stack('scripts')
    </body>
</html>
