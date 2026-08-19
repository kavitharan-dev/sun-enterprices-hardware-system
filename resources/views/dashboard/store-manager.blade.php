<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold text-slate-800">Dashboard</h2>
    </x-slot>

@php $currency = 'Rs.'; @endphp

<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-bold text-slate-900">Store Manager Dashboard</h1>
        <p class="mt-1 text-sm text-slate-500">Inventory, purchases, and material request overview.</p>
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat-card label="Today's Sales" :value="$currency . number_format($stats['today_sales'], 2)" color="amber" />
        <x-stat-card label="Monthly Sales" :value="$currency . number_format($stats['monthly_sales'], 2)" color="emerald" />
        <x-stat-card label="Total Products" :value="number_format($stats['total_products'])" color="sky" />
        <x-stat-card label="Low Stock Items" :value="number_format($stats['low_stock_products'])" color="rose" />
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat-card label="Pending Material Requests" :value="number_format($stats['pending_material_requests'])" color="amber" />
        <x-stat-card label="Total Suppliers" :value="number_format($stats['total_suppliers'])" />
        <x-stat-card label="Customers" :value="number_format($stats['total_customers'])" />
        <x-stat-card label="Outstanding Payments" :value="$currency . number_format($stats['outstanding_payments'], 2)" color="rose" />
    </div>

    <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="flex items-center justify-between">
            <h2 class="text-lg font-semibold text-slate-900">Low stock alerts</h2>
            <div class="flex gap-4">
                <a href="{{ route('store.material-issues.index') }}" class="btn btn-secondary btn-sm">Issues</a>
                <a href="{{ route('store.material-requests.index') }}" class="btn btn-secondary btn-sm">Review requests</a>
                <a href="{{ route('reports.index') }}" class="btn btn-secondary btn-sm">Reports</a>
                <a href="{{ route('store.inventory.index', ['low_stock' => 1]) }}" class="btn btn-secondary btn-sm">View inventory</a>
            </div>
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
</div>
</x-app-layout>
