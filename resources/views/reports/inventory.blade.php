<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="text-xl font-semibold text-slate-800">Inventory report</h2>
            <a href="{{ route('reports.index') }}" class="btn btn-secondary btn-sm">All reports</a>
        </div>
    </x-slot>

    <div class="mb-4 flex flex-wrap gap-3">
        <a href="{{ request()->fullUrlWithQuery(['export' => 1]) }}" class="btn btn-secondary">Export CSV</a>
    </div>

    <div class="mb-4 grid gap-4 sm:grid-cols-2">
        <div class="rounded-xl border border-slate-200 bg-white p-4 text-sm"><p class="text-slate-500">Stock value (cost)</p><p class="text-lg font-semibold">Rs. {{ number_format($stock_value, 2) }}</p></div>
        <div class="rounded-xl border border-slate-200 bg-white p-4 text-sm"><p class="text-slate-500">Low stock items</p><p class="text-lg font-semibold">{{ number_format($low_stock) }}</p></div>
    </div>

    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase text-slate-500">
                <tr>
                    <th class="px-4 py-3">SKU</th>
                    <th class="px-4 py-3">Product</th>
                    <th class="px-4 py-3">Category</th>
                    <th class="px-4 py-3 text-right">Qty</th>
                    <th class="px-4 py-3 text-right">Min</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @foreach ($rows as $product)
                    <tr class="{{ $product->isLowStock() ? 'bg-rose-50' : '' }}">
                        <td class="px-4 py-3 font-mono text-xs">{{ $product->sku }}</td>
                        <td class="px-4 py-3">{{ $product->name }}</td>
                        <td class="px-4 py-3">{{ $product->category?->name }}</td>
                        <td class="px-4 py-3 text-right">{{ $product->formatQuantity() }}</td>
                        <td class="px-4 py-3 text-right">{{ number_format((float) $product->min_stock_level, 3) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</x-app-layout>
