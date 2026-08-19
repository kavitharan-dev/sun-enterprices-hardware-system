<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold text-slate-800">Dashboard</h2>
    </x-slot>

@php
    $currency = 'Rs.';
@endphp

<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-bold text-slate-900">Admin Dashboard</h1>
        <p class="mt-1 text-sm text-slate-500">Overview of hardware store and construction operations.</p>
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat-card label="Today's Sales" :value="$currency . number_format($stats['today_sales'], 2)" color="amber" />
        <x-stat-card label="Monthly Sales" :value="$currency . number_format($stats['monthly_sales'], 2)" color="emerald" />
        <x-stat-card label="Total Products" :value="number_format($stats['total_products'])" color="sky" />
        <x-stat-card label="Low Stock Items" :value="number_format($stats['low_stock_products'])" color="rose" hint="Requires attention" />
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat-card label="Active Projects" :value="number_format($stats['active_projects'])" />
        <x-stat-card label="Completed Projects" :value="number_format($stats['completed_projects'])" />
        <x-stat-card label="Pending Material Requests" :value="number_format($stats['pending_material_requests'])" color="amber" />
        <x-stat-card label="Outstanding Payments" :value="$currency . number_format($stats['outstanding_payments'], 2)" color="rose" />
    </div>

    <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="flex items-center justify-between">
            <h2 class="text-lg font-semibold text-slate-900">Low stock alerts</h2>
            <a href="{{ route('store.inventory.index', ['low_stock' => 1]) }}" class="btn btn-secondary btn-sm">View inventory</a>
        </div>
        @if ($lowStock->isEmpty())
            <p class="mt-3 text-sm text-slate-500">All products are above minimum stock.</p>
        @else
            <ul class="mt-3 divide-y divide-slate-100">
                @foreach ($lowStock as $product)
                    <li class="flex items-center justify-between py-2 text-sm">
                        <a href="{{ route('store.products.show', $product) }}" class="font-medium text-slate-800 hover:text-amber-700">{{ $product->name }}</a>
                        <span class="text-rose-600 font-semibold">{{ $product->formatQuantity() }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    @php $maxTrend = max(1, (float) $salesTrend->max('total')); @endphp
    <div class="grid gap-6 lg:grid-cols-2">
        <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex items-center justify-between">
                <h2 class="text-lg font-semibold text-slate-900">Sales (14 days)</h2>
                <a href="{{ route('reports.sales') }}" class="btn btn-secondary btn-sm">Sales report</a>
            </div>
            <div class="mt-4 flex h-40 items-end gap-1">
                @foreach ($salesTrend as $point)
                    <div class="flex flex-1 flex-col items-center justify-end gap-1">
                        <div class="w-full rounded-t bg-amber-400" style="height: {{ max(4, ($point->total / $maxTrend) * 100) }}%"></div>
                        <span class="text-[9px] text-slate-400">{{ $point->label }}</span>
                    </div>
                @endforeach
            </div>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex items-center justify-between">
                <h2 class="text-lg font-semibold text-slate-900">Recent sales</h2>
                <a href="{{ route('store.sales.index') }}" class="btn btn-secondary btn-sm">View sales</a>
            </div>
            <ul class="mt-3 divide-y">
                @forelse ($recentSales as $sale)
                    <li class="flex items-center justify-between py-2 text-sm">
                        <span>{{ $sale->invoice_no }} · {{ $sale->customerName() }}</span>
                        <span class="font-medium">Rs. {{ number_format((float) $sale->total, 2) }}</span>
                    </li>
                @empty
                    <li class="py-4 text-sm text-slate-500">No completed sales yet.</li>
                @endforelse
            </ul>
        </div>
    </div>
</div>
</x-app-layout>
