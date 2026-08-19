@php
    $productOptions = $products->map(fn ($p) => [
        'id' => $p->id,
        'name' => $p->name,
        'sku' => $p->sku,
        'selling_price' => (float) $p->selling_price,
        'stock' => (float) $p->stock_quantity,
        'unit' => $p->unit?->symbol,
    ])->values();

    $existingItems = old('items', isset($sale)
        ? $sale->items->map(fn ($item) => [
            'product_id' => (string) $item->product_id,
            'quantity' => (float) $item->quantity,
            'unit_price' => (float) $item->unit_price,
            'discount' => (float) $item->discount,
        ])->all()
        : [['product_id' => '', 'quantity' => 1, 'unit_price' => 0, 'discount' => 0]]
    );
@endphp

<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold text-slate-800">{{ isset($sale) ? 'Edit Sale' : 'New Sale' }}</h2>
    </x-slot>

    <form
        method="POST"
        action="{{ isset($sale) ? route('store.sales.update', $sale) : route('store.sales.store') }}"
        class="space-y-6"
        x-data="saleForm()"
    >
        @csrf
        @isset($sale) @method('PUT') @endisset

        <div class="grid gap-4 rounded-xl border border-slate-200 bg-white p-6 shadow-sm sm:grid-cols-3">
            <div>
                <x-input-label for="customer_id" value="Registered customer" />
                <select id="customer_id" name="customer_id" x-model="customerId" class="mt-1 block w-full rounded-md border-gray-300">
                    <option value="">Walk-in (type name)</option>
                    @foreach ($customers as $customer)
                        <option value="{{ $customer->id }}" @selected(old('customer_id', $sale->customer_id ?? '') == $customer->id)>{{ $customer->name }}</option>
                    @endforeach
                </select>
            </div>
            <div x-show="!customerId" x-cloak>
                <x-input-label for="walk_in_name" value="Walk-in customer name" />
                <x-text-input id="walk_in_name" name="walk_in_name" class="mt-1 block w-full" :value="old('walk_in_name', $sale->walk_in_name ?? '')" placeholder="Name for the bill" x-bind:required="!customerId" />
                <p class="mt-1 text-xs text-slate-500">This name is printed on the bill.</p>
            </div>
            <div>
                <x-input-label for="sale_date" value="Sale date" />
                <x-text-input id="sale_date" name="sale_date" type="date" class="mt-1 block w-full" :value="old('sale_date', isset($sale) ? $sale->sale_date->format('Y-m-d') : now()->toDateString())" required />
            </div>
            <div class="sm:col-span-3">
                <x-input-label for="notes" value="Notes" />
                <x-text-input id="notes" name="notes" class="mt-1 block w-full" :value="old('notes', $sale->notes ?? '')" />
            </div>
        </div>

        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="flex items-center justify-between border-b px-5 py-3">
                <h3 class="font-semibold text-slate-800">Cart</h3>
                <button type="button" @click="addItem()" class="btn btn-secondary btn-sm">Add product</button>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase text-slate-500">
                        <tr>
                            <th class="px-4 py-3">Product</th>
                            <th class="px-4 py-3 w-24">Qty</th>
                            <th class="px-4 py-3 w-28">Unit price</th>
                            <th class="px-4 py-3 w-24">Discount</th>
                            <th class="px-4 py-3 w-28 text-right">Line total</th>
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
                                            <option :value="product.id" x-text="product.sku + ' — ' + product.name + ' (' + product.stock + ' ' + (product.unit || '') + ')'"></option>
                                        </template>
                                    </select>
                                </td>
                                <td class="px-4 py-3">
                                    <input type="number" step="0.001" min="0.001" :name="'items['+index+'][quantity]'" x-model.number="item.quantity" class="w-full rounded-md border-gray-300 text-sm" required>
                                </td>
                                <td class="px-4 py-3">
                                    <input type="number" step="0.01" min="0" :name="'items['+index+'][unit_price]'" x-model.number="item.unit_price" class="w-full rounded-md border-gray-300 text-sm" required>
                                </td>
                                <td class="px-4 py-3">
                                    <input type="number" step="0.01" min="0" :name="'items['+index+'][discount]'" x-model.number="item.discount" class="w-full rounded-md border-gray-300 text-sm">
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
                    <x-input-label for="discount" value="Invoice discount (Rs.)" />
                    <input id="discount" name="discount" type="number" step="0.01" min="0" x-model.number="discount" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                </div>
                <div>
                    <x-input-label for="tax" value="Tax (Rs.)" />
                    <input id="tax" name="tax" type="number" step="0.01" min="0" x-model.number="tax" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                </div>
                <div class="rounded-lg bg-slate-50 p-3">
                    <p class="text-xs text-slate-500">Grand total</p>
                    <p class="text-xl font-bold" x-text="'Rs. ' + grandTotal().toFixed(2)"></p>
                </div>
            </div>
        </div>

        @unless (isset($sale))
            <div class="grid gap-4 rounded-xl border border-slate-200 bg-white p-6 shadow-sm sm:grid-cols-2">
                <div>
                    <x-input-label for="payment_method" value="Payment method" />
                    <select id="payment_method" name="payment_method" x-model="paymentMethod" class="mt-1 block w-full rounded-md border-gray-300">
                        <option value="cash">Cash</option>
                        <option value="card">Card</option>
                        <option value="bank_transfer">Bank Transfer</option>
                        <option value="credit">Credit (pay later)</option>
                    </select>
                </div>
                <div x-show="paymentMethod !== 'credit'">
                    <x-input-label for="payment_amount" value="Amount customer paid (Rs.)" />
                    <input id="payment_amount" name="payment_amount" type="number" step="0.01" min="0.01" x-model.number="tendered" x-bind:disabled="paymentMethod === 'credit'" class="mt-1 block w-full rounded-md border-gray-300" value="{{ old('payment_amount') }}">
                    <p class="mt-1 text-xs text-slate-500">Required for cash, card, and transfer. Example: bill Rs. 2,387.00, customer gives Rs. 5,000.00 — enter 5000.</p>
                </div>
                <div x-show="paymentMethod !== 'credit'" class="sm:col-span-2 rounded-lg bg-sun-50 p-3 text-sm">
                    <p>Bill total: Rs. <span x-text="grandTotal().toFixed(2)"></span></p>
                    <p>Customer paid: Rs. <span x-text="(Number(tendered) || 0).toFixed(2)"></span></p>
                    <p class="font-semibold text-emerald-700" x-show="(Number(tendered) || 0) > grandTotal()">Change to return: Rs. <span x-text="Math.max(0, (Number(tendered) || 0) - grandTotal()).toFixed(2)"></span></p>
                    <p class="font-semibold text-rose-600" x-show="(Number(tendered) || 0) > 0 && (Number(tendered) || 0) < grandTotal()">Balance due: Rs. <span x-text="Math.max(0, grandTotal() - (Number(tendered) || 0)).toFixed(2)"></span></p>
                </div>
                <p x-show="paymentMethod === 'credit'" class="sm:col-span-2 text-sm text-slate-500">Credit: paid amount is not needed. The bill will show the full balance due.</p>
            </div>
        @endunless

        <div class="flex flex-wrap gap-3">
            <x-primary-button>Save as draft</x-primary-button>
            @unless (isset($sale))
                @if (auth()->user()->canConfirmTill())
                    <button type="submit" name="complete" value="1" class="btn btn-success">
                        Complete, print bill &amp; next sale
                    </button>
                @endif
            @endunless
            <a href="{{ route('store.sales.index') }}" class="btn btn-secondary">Cancel</a>
        </div>
    </form>

    @push('scripts')
        <script>
            function saleForm() {
                return {
                    products: @json($productOptions),
                    items: @json($existingItems),
                    customerId: @json((string) old('customer_id', $sale->customer_id ?? '')),
                    paymentMethod: @json((string) old('payment_method', 'cash')),
                    tendered: {{ (float) old('payment_amount', 0) }},
                    discount: {{ (float) old('discount', $sale->discount ?? 0) }},
                    tax: {{ (float) old('tax', $sale->tax ?? 0) }},
                    addItem() {
                        this.items.push({ product_id: '', quantity: 1, unit_price: 0, discount: 0 });
                    },
                    removeItem(index) {
                        if (this.items.length === 1) return;
                        this.items.splice(index, 1);
                    },
                    applyProduct(item) {
                        const product = this.products.find(p => String(p.id) === String(item.product_id));
                        if (product) item.unit_price = product.selling_price;
                    },
                    lineTotal(item) {
                        return Math.max(0, ((Number(item.quantity) || 0) * (Number(item.unit_price) || 0)) - (Number(item.discount) || 0));
                    },
                    grandTotal() {
                        const subtotal = this.items.reduce((sum, item) => sum + this.lineTotal(item), 0);
                        return Math.max(0, subtotal - (Number(this.discount) || 0) + (Number(this.tax) || 0));
                    }
                }
            }
        </script>
    @endpush
</x-app-layout>
