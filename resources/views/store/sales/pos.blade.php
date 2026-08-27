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
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-xl font-semibold text-slate-800">POS</h2>
                <p class="text-sm text-slate-500">Scan barcode / SKU or search to add items. Completes into Daily Accounts.</p>
            </div>
            <a href="{{ route('store.sales.create') }}" class="btn btn-secondary btn-sm">Classic sale form</a>
        </div>
    </x-slot>

    <form
        method="POST"
        action="{{ route('store.sales.store') }}"
        class="space-y-4"
        x-data="posForm()"
        @submit="prepareSubmit"
    >
        @csrf

        <div class="grid gap-4 lg:grid-cols-3">
            <div class="space-y-4 lg:col-span-2">
                <div class="rounded-xl border border-amber-200 bg-amber-50/80 p-4 shadow-sm">
                    <x-input-label for="barcode_scan" value="Barcode / SKU scan" />
                    <div class="mt-1 flex gap-2">
                        <input
                            id="barcode_scan"
                            type="text"
                            x-model="scan"
                            x-ref="scan"
                            @keydown.enter.prevent="applyScan()"
                            class="block w-full rounded-md border-amber-300 text-lg font-mono shadow-sm focus:border-amber-500 focus:ring-amber-500"
                            placeholder="Scan here, then Enter"
                            autocomplete="off"
                            autofocus
                        >
                        <button type="button" @click="applyScan()" class="btn btn-primary shrink-0">Add</button>
                    </div>
                    <p class="mt-1 text-xs text-amber-900/70">Scanner types the SKU and presses Enter. Product SKU must match the barcode.</p>
                    <p x-show="scanMessage" x-cloak class="mt-2 text-sm font-medium" :class="scanOk ? 'text-emerald-700' : 'text-rose-700'" x-text="scanMessage"></p>
                </div>

                <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                    <x-input-label value="Search & add product" />
                    <div
                        class="relative mt-1"
                        x-data="searchableSelect({
                            options: products.map(p => ({
                                value: String(p.id),
                                label: p.sku + ' — ' + p.name + ' (stock ' + p.stock + ' ' + (p.unit || '') + ')',
                                search: p.sku + ' ' + p.name,
                            })),
                            value: '',
                            name: '',
                            allowEmpty: false,
                            emptyLabel: 'Search product…',
                            placeholder: 'Type name or SKU…',
                            onChange: (val) => { addProductById(val); },
                        })"
                        @click.outside="open = false"
                    >
                        <button
                            type="button"
                            @click="toggle()"
                            @keydown="onKeydown($event)"
                            class="flex w-full items-center justify-between gap-2 rounded-md border border-gray-300 bg-white px-3 py-2 text-left text-sm shadow-sm hover:border-amber-400 focus:border-amber-500 focus:outline-none focus:ring-1 focus:ring-amber-500"
                        >
                            <span class="truncate text-slate-400" x-text="placeholder"></span>
                            <svg class="h-4 w-4 text-slate-400" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.17l3.71-3.94a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd"/></svg>
                        </button>
                        <div x-show="open" x-cloak class="absolute z-50 mt-1 w-full overflow-hidden rounded-md border border-slate-200 bg-white shadow-lg">
                            <div class="border-b p-2">
                                <input x-ref="search" type="text" x-model="query" @keydown="onKeydown($event)" class="block w-full rounded-md border-gray-300 text-sm" placeholder="Type name or SKU…" autocomplete="off">
                            </div>
                            <ul class="max-h-64 overflow-y-auto py-1 text-sm">
                                <template x-for="(opt, i) in filtered" :key="opt.value">
                                    <li>
                                        <button type="button" class="flex w-full px-3 py-2 text-left hover:bg-amber-50" :class="highlighted === i ? 'bg-slate-50' : ''" @click="select(opt); value = '';" @mouseenter="highlighted = i" x-text="opt.label"></button>
                                    </li>
                                </template>
                                <li x-show="filtered.length === 0" class="px-3 py-2 text-slate-400">No matches</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                    <div class="border-b px-4 py-3">
                        <h3 class="font-semibold text-slate-800">Cart</h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase text-slate-500">
                                <tr>
                                    <th class="px-4 py-3">Product</th>
                                    <th class="px-4 py-3 w-24">Qty</th>
                                    <th class="px-4 py-3 w-28">Price</th>
                                    <th class="px-4 py-3 w-24">Disc.</th>
                                    <th class="px-4 py-3 w-28 text-right">Total</th>
                                    <th class="px-4 py-3 w-12"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-for="(item, index) in items" :key="item._key">
                                    <tr class="border-t">
                                        <td class="px-4 py-3">
                                            <input type="hidden" :name="'items['+index+'][product_id]'" :value="item.product_id">
                                            <p class="font-medium text-slate-800" x-text="item.name"></p>
                                            <p class="text-xs text-slate-500" x-text="item.sku + (item.unit ? ' · ' + item.unit : '')"></p>
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
                                            <button type="button" @click="removeItem(index)" class="btn btn-danger-outline btn-sm">×</button>
                                        </td>
                                    </tr>
                                </template>
                                <tr x-show="items.length === 0">
                                    <td colspan="6" class="px-4 py-8 text-center text-slate-400">Scan or search to add products</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="space-y-4">
                <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm space-y-3">
                    <div>
                        <x-input-label value="Customer" />
                        <div class="relative mt-1"
                            x-data="searchableSelect({
                                options: @json($customerOptions),
                                value: @json((string) old('customer_id', '')),
                                name: 'customer_id',
                                allowEmpty: true,
                                emptyLabel: 'Walk-in (type name)',
                                placeholder: 'Search customer…',
                                onChange: (v) => { customerId = v; },
                            })"
                            @click.outside="open = false"
                        >
                            @include('components.partials.searchable-select-inner')
                        </div>
                    </div>
                    <div x-show="!customerId" x-cloak>
                        <x-input-label for="walk_in_name" value="Walk-in name" />
                        <x-text-input id="walk_in_name" name="walk_in_name" class="mt-1 block w-full" :value="old('walk_in_name')" placeholder="Name on bill" x-bind:required="!customerId" />
                    </div>
                    <div>
                        <x-input-label for="sale_date" value="Date" />
                        <x-text-input id="sale_date" name="sale_date" type="date" class="mt-1 block w-full" :value="old('sale_date', now()->toDateString())" required />
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <x-input-label for="discount" value="Discount" />
                            <input id="discount" name="discount" type="number" step="0.01" min="0" x-model.number="discount" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                        </div>
                        <div>
                            <x-input-label for="tax" value="Tax" />
                            <input id="tax" name="tax" type="number" step="0.01" min="0" x-model.number="tax" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                        </div>
                    </div>
                </div>

                <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm space-y-3">
                    <div class="rounded-lg bg-slate-900 px-4 py-3 text-white">
                        <p class="text-xs uppercase tracking-wide text-slate-300">Total</p>
                        <p class="text-3xl font-bold tabular-nums" x-text="'Rs. ' + grandTotal().toFixed(2)"></p>
                    </div>

                    @if (auth()->user()->canConfirmTill())
                        <div>
                            <x-input-label value="Payment" />
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
                                })"
                                @click.outside="open = false"
                            >
                                @include('components.partials.searchable-select-inner')
                            </div>
                        </div>
                        <div x-show="paymentMethod !== 'credit'" x-cloak>
                            <x-input-label for="payment_amount" value="Amount paid (Rs.)" />
                            <input id="payment_amount" name="payment_amount" type="number" step="0.01" min="0.01" x-model.number="tendered" class="mt-1 block w-full rounded-md border-gray-300 text-lg font-semibold" value="{{ old('payment_amount') }}">
                            <p class="mt-2 text-sm text-emerald-700" x-show="(Number(tendered) || 0) > grandTotal()">
                                Change: Rs. <span x-text="Math.max(0, (Number(tendered) || 0) - grandTotal()).toFixed(2)"></span>
                            </p>
                        </div>
                    @endif
                </div>

                <div class="flex flex-col gap-2">
                    @if (auth()->user()->canConfirmTill())
                        <button type="submit" name="complete" value="1" class="btn btn-success w-full justify-center py-3 text-base" :disabled="items.length === 0">
                            Pay &amp; thermal print
                        </button>
                    @endif
                    <button type="submit" class="btn btn-primary w-full justify-center" :disabled="items.length === 0">Save draft</button>
                    <a href="{{ route('store.sales.index') }}" class="btn btn-secondary w-full justify-center">Cancel</a>
                </div>
            </div>
        </div>
    </form>

    @push('scripts')
        <script>
            function posForm() {
                return {
                    products: @json($productOptions),
                    items: [],
                    customerId: @json((string) old('customer_id', '')),
                    paymentMethod: @json((string) old('payment_method', 'cash')),
                    tendered: {{ (float) old('payment_amount', 0) }},
                    discount: {{ (float) old('discount', 0) }},
                    tax: {{ (float) old('tax', 0) }},
                    scan: '',
                    scanMessage: '',
                    scanOk: false,
                    keySeq: 0,
                    init() {
                        this.$nextTick(() => this.$refs.scan?.focus());
                    },
                    prepareSubmit(e) {
                        if (this.items.length === 0) {
                            e.preventDefault();
                            this.scanMessage = 'Add at least one product.';
                            this.scanOk = false;
                        }
                    },
                    findProduct(code) {
                        const q = String(code || '').trim().toLowerCase();
                        if (!q) return null;
                        return this.products.find(p =>
                            String(p.sku).toLowerCase() === q || String(p.id) === q
                        ) || null;
                    },
                    applyScan() {
                        const product = this.findProduct(this.scan);
                        if (!product) {
                            this.scanMessage = 'No product for "' + this.scan + '"';
                            this.scanOk = false;
                            this.scan = '';
                            return;
                        }
                        this.addProduct(product);
                        this.scanMessage = 'Added ' + product.name;
                        this.scanOk = true;
                        this.scan = '';
                        this.$nextTick(() => this.$refs.scan?.focus());
                    },
                    addProductById(id) {
                        const product = this.products.find(p => String(p.id) === String(id));
                        if (product) this.addProduct(product);
                        this.$nextTick(() => this.$refs.scan?.focus());
                    },
                    addProduct(product) {
                        const existing = this.items.find(i => String(i.product_id) === String(product.id));
                        if (existing) {
                            existing.quantity = (Number(existing.quantity) || 0) + 1;
                            return;
                        }
                        this.keySeq += 1;
                        this.items.push({
                            _key: 'p' + this.keySeq,
                            product_id: String(product.id),
                            name: product.name,
                            sku: product.sku,
                            unit: product.unit,
                            quantity: 1,
                            unit_price: product.selling_price,
                            discount: 0,
                        });
                    },
                    removeItem(index) {
                        this.items.splice(index, 1);
                    },
                    lineTotal(item) {
                        return Math.max(0, ((Number(item.quantity) || 0) * (Number(item.unit_price) || 0)) - (Number(item.discount) || 0));
                    },
                    grandTotal() {
                        const subtotal = this.items.reduce((sum, item) => sum + this.lineTotal(item), 0);
                        return Math.max(0, subtotal - (Number(this.discount) || 0) + (Number(this.tax) || 0));
                    },
                };
            }
        </script>
    @endpush
</x-app-layout>
