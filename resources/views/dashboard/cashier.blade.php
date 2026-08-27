<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold text-slate-800">Dashboard</h2>
    </x-slot>

@php $currency = 'Rs.'; @endphp

@if (session('cashier_welcome') && auth()->user()?->hasRole('cashier'))
    <div
        x-data="{ show: true }"
        x-init="setTimeout(function () { show = false; }, 4500)"
        x-show="show"
        x-cloak
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-700"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="cashier-welcome fixed inset-0 z-[100] flex items-center justify-center"
        x-on:click="show = false"
        role="dialog"
        aria-live="polite"
        aria-label="Welcome"
    >
        <div class="cashier-welcome__backdrop absolute inset-0"></div>

        <div class="cashier-welcome__balloons pointer-events-none absolute inset-0 overflow-hidden" aria-hidden="true">
            @foreach ([
                ['left' => '8%', 'delay' => '0s', 'duration' => '4.2s', 'color' => '#e8b84a', 'size' => '2.6rem'],
                ['left' => '18%', 'delay' => '0.35s', 'duration' => '4.8s', 'color' => '#ef4444', 'size' => '2.1rem'],
                ['left' => '28%', 'delay' => '0.15s', 'duration' => '4.4s', 'color' => '#3b82f6', 'size' => '2.4rem'],
                ['left' => '40%', 'delay' => '0.55s', 'duration' => '5s', 'color' => '#22c55e', 'size' => '2.2rem'],
                ['left' => '52%', 'delay' => '0.1s', 'duration' => '4.6s', 'color' => '#a855f7', 'size' => '2.8rem'],
                ['left' => '64%', 'delay' => '0.4s', 'duration' => '4.3s', 'color' => '#f97316', 'size' => '2.3rem'],
                ['left' => '74%', 'delay' => '0.25s', 'duration' => '4.9s', 'color' => '#06b6d4', 'size' => '2rem'],
                ['left' => '86%', 'delay' => '0.5s', 'duration' => '4.5s', 'color' => '#e8b84a', 'size' => '2.5rem'],
                ['left' => '12%', 'delay' => '0.7s', 'duration' => '5.1s', 'color' => '#f43f5e', 'size' => '1.8rem'],
                ['left' => '48%', 'delay' => '0.85s', 'duration' => '4.7s', 'color' => '#84cc16', 'size' => '2rem'],
                ['left' => '78%', 'delay' => '0.65s', 'duration' => '5.2s', 'color' => '#6366f1', 'size' => '2.2rem'],
            ] as $balloon)
                <span
                    class="cashier-balloon"
                    style="left: {{ $balloon['left'] }}; animation-delay: {{ $balloon['delay'] }}; animation-duration: {{ $balloon['duration'] }}; --balloon-color: {{ $balloon['color'] }}; --balloon-size: {{ $balloon['size'] }};"
                ></span>
            @endforeach
        </div>

        <div
            class="relative z-10 mx-4 max-w-lg rounded-3xl border border-sun-200/80 bg-white/95 px-8 py-10 text-center shadow-2xl backdrop-blur"
            x-on:click.stop
        >
            <p class="text-sm font-semibold uppercase tracking-[0.18em] text-amber-700">Welcome back</p>
            <p class="mt-3 font-display text-3xl font-bold text-walnut-950 sm:text-4xl">
                {{ auth()->user()->name }}
            </p>
            <p class="mt-3 text-base text-slate-600">Have a great shift at the till.</p>
            <button type="button" class="btn btn-primary mt-6" x-on:click="show = false">Let's go</button>
        </div>
    </div>
@endif

<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-bold text-slate-900">Shop Dashboard</h1>
        <p class="mt-1 text-sm text-slate-500">POS, products, stock, purchases, and daily accounts.</p>
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat-card label="Today's Sales" :value="$currency . number_format($stats['today_sales'], 2)" color="amber" />
        <x-stat-card label="Monthly Sales" :value="$currency . number_format($stats['monthly_sales'], 2)" color="emerald" />
        <x-stat-card label="Total Products" :value="number_format($stats['total_products'])" color="sky" />
        <x-stat-card label="Low Stock Items" :value="number_format($stats['low_stock_products'])" color="rose" />
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat-card label="Pending Material Requests" :value="number_format($stats['pending_material_requests'])" color="amber" />
        <x-stat-card label="Customers" :value="number_format($stats['total_customers'])" />
        <x-stat-card label="Suppliers" :value="number_format($stats['total_suppliers'])" />
        <x-stat-card label="Outstanding Payments" :value="$currency . number_format($stats['outstanding_payments'], 2)" color="rose" />
    </div>

    <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="flex flex-wrap gap-3">
            <a href="{{ route('store.sales.pos') }}" class="btn btn-success">Open POS</a>
            <a href="{{ route('store.products.create') }}" class="btn btn-primary">Add product</a>
            <a href="{{ route('store.products.index') }}" class="btn btn-secondary">Products</a>
            <a href="{{ route('store.inventory.index') }}" class="btn btn-secondary">Inventory</a>
            <a href="{{ route('store.purchases.create') }}" class="btn btn-secondary">New purchase</a>
            <a href="{{ route('cashier.daily-accounts.index') }}" class="btn btn-secondary">Daily accounts</a>
            <a href="{{ route('store.customers.index') }}" class="btn btn-secondary">Customers</a>
        </div>
    </div>

    @isset($lowStock)
        <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-lg font-semibold text-slate-900">Low stock alerts</h2>
            <ul class="mt-4 divide-y text-sm">
                @forelse ($lowStock as $product)
                    <li class="flex items-center justify-between py-2">
                        <span>{{ $product->name }} <span class="text-slate-400">({{ $product->sku }})</span></span>
                        <span class="font-semibold text-rose-600">{{ $product->formatQuantity() }}</span>
                    </li>
                @empty
                    <li class="py-2 text-slate-500">No low-stock items right now.</li>
                @endforelse
            </ul>
        </div>
    @endisset
</div>
</x-app-layout>
