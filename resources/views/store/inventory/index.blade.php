<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-xl font-semibold text-slate-800">Inventory</h2>
                <p class="text-sm text-slate-500">{{ $lowStockCount }} product(s) at or below minimum stock</p>
            </div>
            <a href="{{ route('store.inventory.movements') }}" class="btn btn-dark">Stock movements</a>
        </div>
    </x-slot>

    <form method="GET" class="mb-4 flex flex-wrap gap-3">
        <input type="text" name="q" value="{{ request('q') }}" placeholder="Search products" class="rounded-lg border-slate-300 text-sm">
        <label class="flex items-center gap-2 text-sm text-slate-600">
            <input type="checkbox" name="low_stock" value="1" @checked(request()->boolean('low_stock')) class="rounded border-slate-300 text-amber-500">
            Low stock only
        </label>
        <button class="btn btn-dark">Filter</button>
    </form>

    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full divide-y text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase text-slate-500">
                <tr>
                    <th class="px-4 py-3">Product</th>
                    <th class="px-4 py-3">Category</th>
                    <th class="px-4 py-3 text-right">On hand</th>
                    <th class="px-4 py-3 text-right">Min</th>
                    <th class="px-4 py-3">Alert</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse ($products as $product)
                    <tr>
                        <td class="px-4 py-3">
                            <p class="font-medium">{{ $product->name }}</p>
                            <p class="text-xs font-mono text-slate-500">{{ $product->sku }}</p>
                        </td>
                        <td class="px-4 py-3 text-slate-600">{{ $product->category?->name }}</td>
                        <td class="px-4 py-3 text-right font-semibold {{ $product->isLowStock() ? 'text-rose-600' : '' }}">{{ $product->formatQuantity() }}</td>
                        <td class="px-4 py-3 text-right text-slate-500">{{ rtrim(rtrim(number_format((float) $product->min_stock_level, 3, '.', ''), '0'), '.') }}</td>
                        <td class="px-4 py-3">
                            @if ($product->isLowStock())
                                <x-status-badge status="low" />
                            @else
                                <x-status-badge status="ok" />
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('store.products.show', $product) }}" class="btn btn-secondary btn-sm">History</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-8 text-center text-slate-500">No inventory records.</td></tr>
                @endforelse
            </tbody>
        </table>
        @if ($products->hasPages())
            <div class="border-t px-4 py-3">{{ $products->links() }}</div>
        @endif
    </div>

    <div class="mt-6 max-w-xl rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <h3 class="font-semibold text-slate-800">Stock adjustment</h3>
        <p class="mt-1 text-sm text-slate-500">Use this for damaged goods, counts, or corrections. Every change is logged.</p>
        <form method="POST" action="{{ route('store.inventory.adjust') }}" class="mt-4 space-y-3">
            @csrf
            <div>
                <x-input-label for="product_id" value="Product" />
                <x-searchable-select
                    name="product_id"
                    :options="$adjustmentProducts->map(fn ($p) => ['value' => (string) $p->id, 'label' => $p->sku.' — '.$p->name, 'search' => $p->sku.' '.$p->name])->values()"
                    :value="(string) old('product_id')"
                    placeholder="Search product…"
                    empty-label="Select product"
                    :allow-empty="false"
                    :required="true"
                    class="mt-1"
                />
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <x-input-label for="direction" value="Type" />
                    <x-searchable-select
                        name="direction"
                        :options="[
                            ['value' => 'in', 'label' => 'Increase'],
                            ['value' => 'out', 'label' => 'Decrease'],
                        ]"
                        :value="(string) old('direction', 'in')"
                        empty-label="Increase"
                        :allow-empty="false"
                        :required="true"
                        placeholder="Type"
                        class="mt-1"
                    />
                </div>
                <div>
                    <x-input-label for="quantity" value="Quantity" />
                    <x-text-input id="quantity" name="quantity" type="number" step="0.001" min="0.001" class="mt-1 block w-full" required />
                </div>
            </div>
            <div>
                <x-input-label for="movement_date" value="Date" />
                <x-text-input id="movement_date" name="movement_date" type="date" class="mt-1 block w-full" :value="now()->toDateString()" required />
            </div>
            <div>
                <x-input-label for="notes" value="Reason" />
                <textarea id="notes" name="notes" rows="2" class="mt-1 block w-full rounded-md border-gray-300" required placeholder="e.g. Damaged bags / stock count correction"></textarea>
            </div>
            <x-primary-button>Record adjustment</x-primary-button>
        </form>
    </div>
</x-app-layout>
