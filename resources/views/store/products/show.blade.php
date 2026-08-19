<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-xl font-semibold text-slate-800">{{ $product->name }}</h2>
                <p class="text-sm text-slate-500">{{ $product->sku }} · {{ $product->category?->name }}</p>
            </div>
            <a href="{{ route('store.products.edit', $product) }}" class="btn btn-primary">Edit</a>
        </div>
    </x-slot>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-1">
            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-sm text-slate-500">Current stock</p>
                <p class="mt-1 text-3xl font-bold {{ $product->isLowStock() ? 'text-rose-600' : 'text-slate-900' }}">{{ $product->formatQuantity() }}</p>
                <p class="mt-2 text-xs text-slate-500">Minimum: {{ rtrim(rtrim(number_format((float) $product->min_stock_level, 3, '.', ''), '0'), '.') }} {{ $product->unit?->symbol }}</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm text-sm space-y-2">
                <p><span class="text-slate-500">Brand:</span> {{ $product->brand?->name ?? '—' }}</p>
                <p><span class="text-slate-500">Purchase:</span> Rs. {{ number_format((float) $product->purchase_price, 2) }}</p>
                <p><span class="text-slate-500">Selling:</span> Rs. {{ number_format((float) $product->selling_price, 2) }}</p>
                <p><span class="text-slate-500">Status:</span> {{ $product->is_active ? 'Active' : 'Inactive' }}</p>
            </div>
        </div>

        <div class="lg:col-span-2 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-5 py-3 font-semibold text-slate-800">Stock movements</div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase text-slate-500">
                        <tr>
                            <th class="px-4 py-3">Date</th>
                            <th class="px-4 py-3">Type</th>
                            <th class="px-4 py-3 text-right">Qty</th>
                            <th class="px-4 py-3 text-right">Balance</th>
                            <th class="px-4 py-3">By</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($movements as $movement)
                            <tr>
                                <td class="px-4 py-3">{{ $movement->movement_date->format('d/m/Y') }}</td>
                                <td class="px-4 py-3">{{ $movement->movement_type->label() }}</td>
                                <td class="px-4 py-3 text-right font-medium {{ $movement->quantity >= 0 ? 'text-emerald-700' : 'text-rose-700' }}">
                                    {{ $movement->quantity > 0 ? '+' : '' }}{{ rtrim(rtrim(number_format((float) $movement->quantity, 3, '.', ''), '0'), '.') }}
                                </td>
                                <td class="px-4 py-3 text-right">{{ rtrim(rtrim(number_format((float) $movement->balance_after, 3, '.', ''), '0'), '.') }}</td>
                                <td class="px-4 py-3 text-slate-500">{{ $movement->user?->name ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-4 py-8 text-center text-slate-500">No stock movements yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($movements->hasPages())
                <div class="border-t px-4 py-3">{{ $movements->links() }}</div>
            @endif
        </div>
    </div>
</x-app-layout>
