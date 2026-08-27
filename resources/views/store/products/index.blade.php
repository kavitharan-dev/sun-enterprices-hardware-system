<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <h2 class="text-xl font-semibold text-slate-800">Products</h2>
            <a href="{{ route('store.products.create') }}" class="btn btn-primary">
                Add Product
            </a>
        </div>
    </x-slot>

    <div class="space-y-4">
        @include('store.partials.catalog-nav')

        <form method="GET" class="grid gap-3 rounded-xl border border-slate-200 bg-white p-4 sm:grid-cols-4">
            <input type="text" name="q" value="{{ request('q') }}" placeholder="Search name or SKU" class="rounded-lg border-slate-300 text-sm sm:col-span-2">
            <x-searchable-select
                name="category_id"
                :options="$categories->map(fn ($c) => ['value' => (string) $c->id, 'label' => $c->name, 'search' => $c->name])->values()"
                :value="(string) request('category_id')"
                empty-label="All categories"
                :allow-empty="true"
                placeholder="Search category…"
            />
            <div class="flex items-center gap-3">
                <label class="flex items-center gap-2 text-sm text-slate-600">
                    <input type="checkbox" name="low_stock" value="1" @checked(request()->boolean('low_stock')) class="rounded border-slate-300 text-amber-500">
                    Low stock
                </label>
                <button class="btn btn-dark ms-auto">Filter</button>
            </div>
        </form>

        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-3">SKU</th>
                            <th class="px-4 py-3">Product</th>
                            <th class="px-4 py-3">Category</th>
                            <th class="px-4 py-3 text-right">Stock</th>
                            <th class="px-4 py-3 text-right">Min</th>
                            <th class="px-4 py-3 text-right">Buy / Sell</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($products as $product)
                            <tr class="hover:bg-slate-50">
                                <td class="px-4 py-3 font-mono text-xs text-slate-500">{{ $product->sku }}</td>
                                <td class="px-4 py-3">
                                    <a href="{{ route('store.products.show', $product) }}" class="font-medium text-slate-900 hover:text-amber-700">{{ $product->name }}</a>
                                    <p class="text-xs text-slate-500">{{ $product->brand?->name }} · {{ $product->unit?->symbol }}</p>
                                </td>
                                <td class="px-4 py-3 text-slate-600">{{ $product->category?->name }}</td>
                                <td class="px-4 py-3 text-right font-semibold {{ $product->isLowStock() ? 'text-rose-600' : 'text-slate-900' }}">
                                    {{ rtrim(rtrim(number_format((float) $product->stock_quantity, 3, '.', ''), '0'), '.') }}
                                </td>
                                <td class="px-4 py-3 text-right text-slate-500">{{ rtrim(rtrim(number_format((float) $product->min_stock_level, 3, '.', ''), '0'), '.') }}</td>
                                <td class="px-4 py-3 text-right text-slate-600">
                                    Rs. {{ number_format((float) $product->purchase_price, 2) }}<br>
                                    <span class="text-xs">Rs. {{ number_format((float) $product->selling_price, 2) }}</span>
                                </td>
                                <td class="px-4 py-3">
                                    @if ($product->isLowStock())
                                        <x-status-badge status="low" />
                                    @else
                                        <x-status-badge :status="$product->is_active ? 'ok' : 'inactive'" />
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <a href="{{ route('store.products.edit', $product) }}" class="btn btn-secondary btn-sm">Edit</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-4 py-10 text-center text-slate-500">No products yet. Add your first product.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($products->hasPages())
                <div class="border-t border-slate-200 px-4 py-3">{{ $products->links() }}</div>
            @endif
        </div>
    </div>
</x-app-layout>
