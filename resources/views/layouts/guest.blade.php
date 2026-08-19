<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('brand.name') }} — Login</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=outfit:400,500,600,700|playfair-display:600,700,800" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-walnut-900 antialiased">
        <div class="min-h-screen lg:grid lg:grid-cols-2">
            <div class="relative hidden overflow-hidden bg-walnut-950 lg:flex flex-col justify-between p-10 text-sun-50">
                <div class="pointer-events-none absolute -left-16 top-10 h-64 w-64 rounded-full bg-sun-400/20 blur-3xl"></div>
                <div class="pointer-events-none absolute bottom-0 right-0 h-80 w-80 rounded-full bg-sun-600/20 blur-3xl"></div>
                <div class="relative">
                    <x-brand-mark size="lg" />
                    <x-shop-brand class="mt-8" name-size="text-5xl" />
                </div>
                <x-shop-brand name-size="text-2xl" />
            </div>

            <div class="flex items-center justify-center px-6 py-12">
                <div class="w-full max-w-md rounded-3xl border border-sun-200/80 bg-white/90 p-8 shadow-xl shadow-walnut-900/10">
                    <div class="mb-6">
                        <x-brand-mark />
                        <x-shop-brand class="mt-4" tone="dark" name-size="text-3xl" />
                    </div>
                    {{ $slot }}
                </div>
            </div>
        </div>
    </body>
</html>
