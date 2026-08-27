@php
    $productOptions = $products->map(fn ($p) => [
        'id' => $p->id,
        'name' => $p->name,
        'sku' => $p->sku,
        'selling_price' => (float) $p->selling_price,
        'stock' => (float) $p->stock_quantity,
        'unit' => $p->unit?->symbol,
    ])->values();

    $customerOptions = $customers->map(fn ($c) => [
        'value' => (string) $c->id,
        'label' => $c->name.($c->phone ? ' · '.$c->phone : ''),
        'search' => trim($c->name.' '.$c->phone),
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
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <h2 class="text-xl font-semibold text-slate-800">{{ isset($sale) ? 'Edit Sale' : 'New Sale' }}</h2>
            @unless (isset($sale))
                <a href="{{ route('store.sales.pos') }}" class="btn btn-success btn-sm">Open POS (scan + search)</a>
            @endunless
        </div>
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
                <div class="relative mt-1"
                    x-data="searchableSelect({
                        options: @json($customerOptions),
                        value: @json((string) old('customer_id', $sale->customer_id ?? '')),
                        name: 'customer_id',
                        allowEmpty: true,
                        emptyLabel: 'Walk-in (type name)',
                        placeholder: 'Search customer…',
                        onChange: (v) => { customerId = v; },
                        getValue: () => customerId,
                    })"
                    @click.outside="open = false"
                >
                    @include('components.partials.searchable-select-inner')
                </div>
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
                                    <div class="relative"
                                        x-data="searchableSelect({
                                            options: products.map(p => ({
                                                value: String(p.id),
                                                label: p.sku + ' — ' + p.name + ' (' + p.stock + ' ' + (p.unit || '') + ')',
                                                search: p.sku + ' ' + p.name,
                                            })),
                                            value: item.product_id,
                                            name: () => 'items[' + index + '][product_id]',
                                            required: true,
                                            allowEmpty: true,
                                            emptyLabel: 'Select product',
                                            placeholder: 'Search product…',
                                            onChange: (v) => { item.product_id = v; applyProduct(item); },
                                            getValue: () => item.product_id,
                                        })"
                                        @click.outside="open = false"
                                    >
                                        @include('components.partials.searchable-select-inner')
                                    </div>
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
                    <div class="relative mt-1"
                        x-data="searchableSelect({
                            options: [
                                { value: 'cash', label: 'Cash' },
                                { value: 'card', label: 'Card' },
                                { value: 'bank_transfer', label: 'Bank Transfer' },
                                { value: 'credit', label: 'Credit (pay later)' },
                            ],
                            value: @json((string) old('payment_method', 'cash')),
                            name: 'payment_method',
                            allowEmpty: false,
                            emptyLabel: 'Cash',
                            placeholder: 'Payment method',
                            onChange: (v) => { paymentMethod = v; },
                            getValue: () => paymentMethod,
                        })"
                        @click.outside="open = false"
                    >
                        @include('components.partials.searchable-select-inner')
                    </div>
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
                        Complete, thermal print &amp; next sale
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
