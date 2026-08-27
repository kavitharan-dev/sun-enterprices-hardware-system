<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold text-slate-800">{{ isset($product) ? 'Edit Product' : 'Add Product' }}</h2>
    </x-slot>

    <form method="POST" action="{{ isset($product) ? route('store.products.update', $product) : route('store.products.store') }}" class="max-w-3xl space-y-6">
        @csrf
        @if (isset($product))
            @method('PUT')
        @endif

        <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm space-y-4">
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <x-input-label for="name" value="Product name" />
                    <x-text-input id="name" name="name" class="mt-1 block w-full" :value="old('name', $product->name ?? '')" required />
                </div>
                <div>
                    <x-input-label for="sku" value="SKU / barcode" />
                    <x-text-input id="sku" name="sku" class="mt-1 block w-full" :value="old('sku', $product->sku ?? '')" placeholder="Leave blank to auto-generate" />
                </div>
                <div>
                    <x-input-label for="category_id" value="Category" />
                    <x-searchable-select
                        name="category_id"
                        :options="$categories->map(fn ($c) => ['value' => (string) $c->id, 'label' => $c->name, 'search' => $c->name])->values()"
                        :value="(string) old('category_id', $product->category_id ?? '')"
                        placeholder="Search category…"
                        empty-label="Select category"
                        :allow-empty="false"
                        :required="true"
                        class="mt-1"
                    />
                </div>
                <div>
                    <x-input-label for="brand_id" value="Brand" />
                    <x-searchable-select
                        name="brand_id"
                        :options="$brands->map(fn ($b) => ['value' => (string) $b->id, 'label' => $b->name, 'search' => $b->name])->values()"
                        :value="(string) old('brand_id', $product->brand_id ?? '')"
                        placeholder="Search brand…"
                        empty-label="No brand"
                        :allow-empty="true"
                        class="mt-1"
                    />
                </div>
                <div>
                    <x-input-label for="unit_id" value="Unit" />
                    <x-searchable-select
                        name="unit_id"
                        :options="$units->map(fn ($u) => ['value' => (string) $u->id, 'label' => $u->name.' ('.$u->symbol.')', 'search' => $u->name.' '.$u->symbol])->values()"
                        :value="(string) old('unit_id', $product->unit_id ?? '')"
                        placeholder="Search unit…"
                        empty-label="Select unit"
                        :allow-empty="false"
                        :required="true"
                        class="mt-1"
                    />
                </div>
                <div>
                    <x-input-label for="min_stock_level" value="Minimum stock level" />
                    <x-text-input id="min_stock_level" name="min_stock_level" type="number" step="0.001" min="0" class="mt-1 block w-full" :value="old('min_stock_level', $product->min_stock_level ?? 0)" required />
                </div>
                <div>
                    <x-input-label for="purchase_price" value="Purchase price (Rs.)" />
                    <x-text-input id="purchase_price" name="purchase_price" type="number" step="0.01" min="0" class="mt-1 block w-full" :value="old('purchase_price', $product->purchase_price ?? 0)" required />
                </div>
                <div>
                    <x-input-label for="selling_price" value="Selling price (Rs.)" />
                    <x-text-input id="selling_price" name="selling_price" type="number" step="0.01" min="0" class="mt-1 block w-full" :value="old('selling_price', $product->selling_price ?? 0)" required />
                </div>
                @unless (isset($product))
                    <div>
                        <x-input-label for="opening_stock" value="Opening stock (optional)" />
                        <x-text-input id="opening_stock" name="opening_stock" type="number" step="0.001" min="0" class="mt-1 block w-full" :value="old('opening_stock', 0)" />
                        <p class="mt-1 text-xs text-slate-500">Recorded as an opening-balance stock movement.</p>
                    </div>
                @endunless
            </div>
            <div>
                <x-input-label for="description" value="Description" />
                <textarea id="description" name="description" rows="3" class="mt-1 block w-full rounded-md border-gray-300">{{ old('description', $product->description ?? '') }}</textarea>
            </div>
            <label class="flex items-center gap-2 text-sm text-slate-700">
                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $product->is_active ?? true)) class="rounded border-slate-300 text-amber-500">
                Active
            </label>
        </div>

        <div class="flex gap-3">
            <x-primary-button>{{ isset($product) ? 'Update product' : 'Save product' }}</x-primary-button>
            <a href="{{ route('store.products.index') }}" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</x-app-layout>
