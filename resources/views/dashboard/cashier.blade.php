<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold text-slate-800">Dashboard</h2>
    </x-slot>

@php $currency = 'Rs.'; @endphp

<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-bold text-slate-900">Cashier Dashboard</h1>
        <p class="mt-1 text-sm text-slate-500">Sales and customer payment overview.</p>
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat-card label="Today's Sales" :value="$currency . number_format($stats['today_sales'], 2)" color="amber" />
        <x-stat-card label="Monthly Sales" :value="$currency . number_format($stats['monthly_sales'], 2)" color="emerald" />
        <x-stat-card label="Customers" :value="number_format($stats['total_customers'])" color="sky" />
        <x-stat-card label="Outstanding Payments" :value="$currency . number_format($stats['outstanding_payments'], 2)" color="rose" />
    </div>
    <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="flex flex-wrap gap-3">
        <a href="{{ route('store.sales.create') }}" class="btn btn-primary">New sale</a>
        <a href="{{ route('store.customers.index') }}" class="btn btn-secondary">Customers</a>
        </div>
    </div>
</div>
</x-app-layout>
