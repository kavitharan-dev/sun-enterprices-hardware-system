@php
    $productOptions = $products->map(fn ($p) => [
        'id' => $p->id,
        'name' => $p->name,
        'sku' => $p->sku,
        'purchase_price' => (float) $p->purchase_price,
        'unit' => $p->unit?->symbol,
    ])->values();

    $existingItems = old('items', isset($purchase)
        ? $purchase->items->map(fn ($item) => [
            'product_id' => (string) $item->product_id,
            'quantity' => (float) $item->quantity,
            'unit_cost' => (float) $item->unit_cost,
        ])->all()
        : [['product_id' => '', 'quantity' => 1, 'unit_cost' => 0]]
    );
@endphp

<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold text-slate-800">{{ isset($purchase) ? 'Edit Purchase '.$purchase->reference_no : 'New Purchase' }}</h2>
    </x-slot>

    <form
        method="POST"
        action="{{ isset($purchase) ? route('store.purchases.update', $purchase) : route('store.purchases.store') }}"
        class="space-y-6"
        x-data="purchaseForm()"
    >
        @csrf
        @isset($purchase) @method('PUT') @endisset

        <div class="grid gap-4 rounded-xl border border-slate-200 bg-white p-6 shadow-sm sm:grid-cols-3">
            <div>
                <x-input-label for="supplier_id" value="Supplier" />
                <select id="supplier_id" name="supplier_id" class="mt-1 block w-full rounded-md border-gray-300" required>
                    <option value="">Select supplier</option>
                    @foreach ($suppliers as $supplier)
                        <option value="{{ $supplier->id }}" @selected(old('supplier_id', $purchase->supplier_id ?? '') == $supplier->id)>{{ $supplier->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <x-input-label for="purchase_date" value="Purchase date" />
                <x-text-input id="purchase_date" name="purchase_date" type="date" class="mt-1 block w-full" :value="old('purchase_date', isset($purchase) ? $purchase->purchase_date->format('Y-m-d') : now()->toDateString())" required />
            </div>
            <div>
                <x-input-label for="notes" value="Notes" />
                <x-text-input id="notes" name="notes" class="mt-1 block w-full" :value="old('notes', $purchase->notes ?? '')" />
            </div>
        </div>

        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="flex items-center justify-between border-b px-5 py-3">
                <h3 class="font-semibold text-slate-800">Line items</h3>
                <button type="button" @click="addItem()" class="btn btn-secondary btn-sm">Add line</button>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase text-slate-500">
                        <tr>
                            <th class="px-4 py-3">Product</th>
                            <th class="px-4 py-3 w-28">Qty</th>
                            <th class="px-4 py-3 w-36">Unit cost</th>
                            <th class="px-4 py-3 w-32 text-right">Subtotal</th>
                            <th class="px-4 py-3 w-12"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="(item, index) in items" :key="index">
                            <tr class="border-t">
                                <td class="px-4 py-3">
                                    <select :name="'items['+index+'][product_id]'" x-model="item.product_id" @change="applyProduct(item)" class="w-full rounded-md border-gray-300 text-sm" required>
                                        <option value="">Select product</option>
                                        <template x-for="product in products" :key="product.id">
                                            <option :value="product.id" x-text="product.sku + ' — ' + product.name" :selected="item.product_id == product.id"></option>
                                        </template>
                                    </select>
                                </td>
                                <td class="px-4 py-3">
                                    <input type="number" step="0.001" min="0.001" :name="'items['+index+'][quantity]'" x-model.number="item.quantity" class="w-full rounded-md border-gray-300 text-sm" required>
                                </td>
                                <td class="px-4 py-3">
                                    <input type="number" step="0.01" min="0" :name="'items['+index+'][unit_cost]'" x-model.number="item.unit_cost" class="w-full rounded-md border-gray-300 text-sm" required>
                                </td>
                                <td class="px-4 py-3 text-right font-medium" x-text="'Rs. ' + lineTotal(item).toFixed(2)"></td>
                                <td class="px-4 py-3">
                                    <button type="button" @click="removeItem(index)" class="btn btn-danger-outline btn-sm">Remove</button>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
            <div class="grid gap-4 border-t px-5 py-4 sm:grid-cols-3">
                <div>
                    <x-input-label for="discount" value="Discount (Rs.)" />
                    <input id="discount" name="discount" type="number" step="0.01" min="0" x-model.number="discount" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                </div>
                <div>
                    <x-input-label for="tax" value="Tax (Rs.)" />
                    <input id="tax" name="tax" type="number" step="0.01" min="0" x-model.number="tax" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                </div>
                <div class="rounded-lg bg-slate-50 p-3">
                    <p class="text-xs text-slate-500">Grand total</p>
                    <p class="text-xl font-bold text-slate-900" x-text="'Rs. ' + grandTotal().toFixed(2)"></p>
                </div>
            </div>
        </div>

        <div class="flex flex-wrap gap-3">
            <x-primary-button>Save as draft</x-primary-button>
            @unless (isset($purchase))
                <button type="submit" name="complete" value="1" class="btn btn-success">
                    Save & receive stock
                </button>
            @endunless
            <a href="{{ route('store.purchases.index') }}" class="btn btn-secondary">Cancel</a>
        </div>
    </form>

    @push('scripts')
        <script>
            function purchaseForm() {
                return {
                    products: @json($productOptions),
                    items: @json($existingItems),
                    discount: {{ (float) old('discount', $purchase->discount ?? 0) }},
                    tax: {{ (float) old('tax', $purchase->tax ?? 0) }},
                    addItem() {
                        this.items.push({ product_id: '', quantity: 1, unit_cost: 0 });
                    },
                    removeItem(index) {
                        if (this.items.length === 1) return;
                        this.items.splice(index, 1);
                    },
                    applyProduct(item) {
                        const product = this.products.find(p => String(p.id) === String(item.product_id));
                        if (product) item.unit_cost = product.purchase_price;
                    },
                    lineTotal(item) {
                        return (Number(item.quantity) || 0) * (Number(item.unit_cost) || 0);
                    },
                    grandTotal() {
                        const subtotal = this.items.reduce((sum, item) => sum + this.lineTotal(item), 0);
                        return subtotal - (Number(this.discount) || 0) + (Number(this.tax) || 0);
                    }
                }
            }
        </script>
    @endpush
</x-app-layout>
