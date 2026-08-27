<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold text-slate-800">Dashboard</h2>
    </x-slot>

@php $currency = 'Rs.'; @endphp

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
