@php
    $productOptions = $products->map(fn ($p) => [
        'id' => (string) $p->id,
        'name' => $p->name,
        'sku' => $p->sku,
        'selling_price' => (float) $p->selling_price,
        'stock' => (float) $p->stock_quantity,
        'unit' => $p->unit?->symbol,
    ])->values();

    $customerOptions = $customers->map(fn ($c) => [
        'id' => (string) $c->id,
        'name' => $c->name,
        'phone' => $c->phone,
        'outstanding' => (float) $c->outstanding_balance,
    ])->values();
@endphp

<x-pos-layout>
    <form
        method="POST"
        action="{{ route('store.sales.store') }}"
        class="mx-auto h-full max-w-[1600px]"
        x-data="posForm()"
        x-on:submit="prepareSubmit"
    >
        @csrf

        <div class="grid gap-4 xl:grid-cols-12 xl:gap-5">
            {{-- Left: scan + cart --}}
            <div class="space-y-4 xl:col-span-8">
                <div class="rounded-2xl border border-amber-300/80 bg-gradient-to-br from-amber-50 via-white to-sun-50 p-4 shadow-sm sm:p-5">
                    <label for="barcode_scan" class="text-xs font-semibold uppercase tracking-wider text-amber-900/70">Barcode / SKU</label>
                    <div class="mt-2 flex flex-col gap-2 sm:flex-row">
                        <input
                            id="barcode_scan"
                            type="text"
                            x-model="scan"
                            x-ref="scan"
                            x-on:keydown.enter.prevent="applyScan()"
                            class="block w-full rounded-xl border-2 border-amber-300 bg-white px-4 py-4 font-mono text-xl shadow-inner focus:border-amber-500 focus:ring-amber-500"
                            placeholder="Scan barcode here…"
                            autocomplete="off"
                            autofocus
                        >
                        <button type="button" x-on:click="applyScan()" class="btn btn-primary shrink-0 rounded-xl px-8 py-4 text-base">Add</button>
                    </div>
                    <p
                        x-show="scanMessage"
                        x-cloak
                        class="mt-2 rounded-lg px-3 py-2 text-sm font-medium"
                        :class="scanOk ? 'bg-emerald-50 text-emerald-800' : 'bg-rose-50 text-rose-800'"
                        x-text="scanMessage"
                    ></p>
                </div>

                <div class="relative rounded-2xl border border-walnut-200/80 bg-white p-4 shadow-sm sm:p-5">
                    <label class="text-xs font-semibold uppercase tracking-wider text-slate-500">Search product</label>
                    <input
                        type="text"
                        x-model="productQuery"
                        x-on:focus="productOpen = true"
                        x-on:keydown.arrow-down.prevent="highlightProduct(1)"
                        x-on:keydown.arrow-up.prevent="highlightProduct(-1)"
                        x-on:keydown.enter.prevent="pickHighlightedProduct()"
                        x-on:keydown.escape="productOpen = false"
                        class="mt-2 block w-full rounded-xl border-slate-300 px-4 py-3 text-base focus:border-amber-500 focus:ring-amber-500"
                        placeholder="Type product name or SKU…"
                        autocomplete="off"
                    >
                    <div
                        x-show="productOpen && productQuery.trim().length > 0"
                        x-cloak
                        x-on:click.outside="productOpen = false"
                        class="absolute left-4 right-4 z-40 mt-1 max-h-72 overflow-y-auto rounded-xl border border-slate-200 bg-white shadow-xl sm:left-5 sm:right-5"
                    >
                        <template x-for="(p, i) in filteredProducts" :key="p.id">
                            <button
                                type="button"
                                class="flex w-full items-center justify-between gap-3 border-b border-slate-50 px-4 py-3 text-left last:border-0 hover:bg-amber-50"
                                :class="productHighlight === i ? 'bg-amber-50' : ''"
                                x-on:click="addProduct(p); productQuery = ''; productOpen = false; focusScan()"
                                x-on:mouseenter="productHighlight = i"
                            >
                                <span>
                                    <span class="block font-medium text-slate-900" x-text="p.name"></span>
                                    <span class="text-xs text-slate-500" x-text="p.sku + ' · stock ' + p.stock + ' ' + (p.unit || '')"></span>
                                </span>
                                <span class="shrink-0 font-semibold tabular-nums text-amber-800" x-text="'Rs. ' + Number(p.selling_price).toFixed(2)"></span>
                            </button>
                        </template>
                        <p x-show="filteredProducts.length === 0" class="px-4 py-6 text-center text-sm text-slate-400">No products found</p>
                    </div>
                </div>

                <div class="overflow-hidden rounded-2xl border border-walnut-200/80 bg-white shadow-sm">
                    <div class="flex items-center justify-between border-b border-slate-100 bg-slate-50/80 px-4 py-3 sm:px-5">
                        <h3 class="font-semibold text-slate-800">Cart <span class="text-sm font-normal text-slate-500" x-text="'(' + items.length + ')'"></span></h3>
                        <button type="button" x-show="items.length" x-cloak x-on:click="items = []" class="text-xs font-semibold text-rose-600 hover:text-rose-800">Clear cart</button>
                    </div>

                    <div class="divide-y divide-slate-100">
                        <template x-for="(item, index) in items" :key="item._key">
                            <div class="flex flex-col gap-3 px-4 py-4 sm:flex-row sm:items-center sm:px-5">
                                <input type="hidden" :name="'items['+index+'][product_id]'" :value="item.product_id">
                                <div class="min-w-0 flex-1">
                                    <p class="truncate font-semibold text-slate-900" x-text="item.name"></p>
                                    <p class="text-xs text-slate-500" x-text="item.sku"></p>
                                </div>
                                <div class="flex flex-wrap items-end gap-2 sm:gap-3">
                                    <div>
                                        <label class="block text-[10px] font-semibold uppercase text-slate-400">Qty</label>
                                        <input type="number" step="0.001" min="0.001" :name="'items['+index+'][quantity]'" x-model.number="item.quantity" class="w-20 rounded-lg border-slate-300 text-sm" required>
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-semibold uppercase text-slate-400">Price</label>
                                        <input type="number" step="0.01" min="0" :name="'items['+index+'][unit_price]'" x-model.number="item.unit_price" class="w-24 rounded-lg border-slate-300 text-sm" required>
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-semibold uppercase text-slate-400">Disc.</label>
                                        <input type="number" step="0.01" min="0" :name="'items['+index+'][discount]'" x-model.number="item.discount" class="w-20 rounded-lg border-slate-300 text-sm">
                                    </div>
                                    <div class="min-w-[5.5rem] pb-2 text-right">
                                        <p class="text-[10px] font-semibold uppercase text-slate-400">Line</p>
                                        <p class="font-bold tabular-nums text-slate-900" x-text="'Rs. ' + lineTotal(item).toFixed(2)"></p>
                                    </div>
                                    <button type="button" x-on:click="removeItem(index)" class="mb-1 rounded-lg px-2.5 py-2 text-sm font-bold text-rose-600 hover:bg-rose-50" title="Remove">×</button>
                                </div>
                            </div>
                        </template>
                        <div x-show="items.length === 0" class="px-4 py-16 text-center">
                            <p class="text-lg font-medium text-slate-400">Cart is empty</p>
                            <p class="mt-1 text-sm text-slate-400">Scan a barcode or search a product to start</p>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Right: checkout panel --}}
            <div class="xl:col-span-4">
                <div class="sticky top-4 space-y-4 rounded-2xl border border-walnut-200/80 bg-white p-4 shadow-md sm:p-5">
                    <div>
                        <label class="text-xs font-semibold uppercase tracking-wider text-slate-500">Customer</label>
                        <input type="hidden" name="customer_id" :value="customerId">
                        <input type="hidden" name="walk_in_name" :value="effectiveWalkInName">
                        <div class="relative mt-2">
                            <input
                                type="text"
                                x-model="customerQuery"
                                x-on:focus="openCustomerList()"
                                x-on:click="openCustomerList()"
                                x-on:input="customerOpen = true; customerBrowsing = false"
                                class="block w-full rounded-xl border-slate-300 px-3 py-2.5 text-sm focus:border-amber-500 focus:ring-amber-500"
                                placeholder="Click to choose registered customer…"
                                autocomplete="off"
                            >
                            <button
                                type="button"
                                x-show="customerId"
                                x-cloak
                                x-on:click="clearCustomer()"
                                class="absolute right-2 top-1/2 -translate-y-1/2 text-xs font-semibold text-slate-400 hover:text-slate-700"
                            >Clear</button>
                            <div
                                x-show="customerOpen"
                                x-cloak
                                x-on:click.outside="customerOpen = false"
                                class="absolute z-40 mt-1 max-h-56 w-full overflow-y-auto rounded-xl border border-slate-200 bg-white shadow-lg"
                            >
                                <button type="button" class="block w-full px-3 py-2.5 text-left text-sm text-slate-500 hover:bg-amber-50" x-on:click="clearCustomer(); customerOpen = false">
                                    Walk-in (auto name on bill)
                                </button>
                                <template x-for="c in filteredCustomers" :key="c.id">
                                    <button
                                        type="button"
                                        class="block w-full px-3 py-2.5 text-left text-sm hover:bg-amber-50"
                                        x-on:click="selectCustomer(c)"
                                    >
                                        <span class="font-medium text-slate-900" x-text="c.name"></span>
                                        <span class="block text-xs text-slate-500" x-text="c.phone || ''"></span>
                                    </button>
                                </template>
                                <p x-show="filteredCustomers.length === 0" class="px-3 py-3 text-sm text-slate-400">No customers found</p>
                            </div>
                        </div>
                        <p x-show="customerId" x-cloak class="mt-1 text-sm font-medium text-emerald-800" x-text="selectedCustomerLabel"></p>
                        <div x-show="customerId && customerPreviousDue > 0" x-cloak class="mt-3 rounded-xl border border-amber-200 bg-amber-50 p-3">
                            <p class="text-sm font-semibold text-amber-950">
                                Previous due: Rs. <span x-text="customerPreviousDue.toFixed(2)"></span>
                            </p>
                            <label class="mt-2 flex cursor-pointer items-start gap-2 text-sm text-amber-950">
                                <input
                                    type="checkbox"
                                    name="include_previous_balance"
                                    value="1"
                                    x-model="includePreviousBalance"
                                    class="mt-0.5 rounded border-amber-400 text-amber-600 focus:ring-amber-500"
                                >
                                <span>Add previous balance to this bill</span>
                            </label>
                        </div>
                        <div x-show="!customerId" x-cloak class="mt-2">
                            <input
                                type="text"
                                x-model="walkInName"
                                class="block w-full rounded-xl border-slate-300 px-3 py-2.5 text-sm"
                                placeholder="Name on bill (optional — defaults to Walk-in Customer)"
                            >
                            <p class="mt-1 text-xs text-slate-500">If left empty, bill uses <span class="font-medium">Walk-in Customer</span>.</p>
                        </div>
                    </div>

                    <div>
                        <label for="sale_date" class="text-xs font-semibold uppercase tracking-wider text-slate-500">Date</label>
                        <input id="sale_date" name="sale_date" type="date" value="{{ old('sale_date', now()->toDateString()) }}" required class="mt-2 block w-full rounded-xl border-slate-300 text-sm">
                    </div>

                    <div>
                        <label for="discount" class="text-xs font-semibold uppercase tracking-wider text-slate-500">Discount (Rs.)</label>
                        <input id="discount" name="discount" type="number" step="0.01" min="0" x-model.number="discount" class="mt-2 block w-full rounded-xl border-slate-300 text-sm">
                        <input type="hidden" name="tax" value="0">
                    </div>

                    <div class="rounded-2xl bg-gradient-to-br from-walnut-900 to-walnut-800 px-4 py-4 text-white shadow-inner">
                        <p class="text-xs font-semibold uppercase tracking-wider text-amber-200/80">Bill total</p>
                        <template x-if="includePreviousBalance && customerPreviousDue > 0">
                            <div class="mt-2 space-y-1 text-sm text-white/80">
                                <div class="flex justify-between"><span>This sale</span><span x-text="'Rs. ' + saleTotal().toFixed(2)"></span></div>
                                <div class="flex justify-between"><span>Previous balance</span><span x-text="'Rs. ' + customerPreviousDue.toFixed(2)"></span></div>
                            </div>
                        </template>
                        <p class="mt-1 text-4xl font-bold tabular-nums tracking-tight" x-text="'Rs. ' + grandTotal().toFixed(2)"></p>
                        <p class="mt-1 text-xs text-white/60" x-text="items.length + ' item(s)'"></p>
                    </div>

                    @if (auth()->user()->canConfirmTill())
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Payment</p>
                            <input type="hidden" name="payment_method" :value="paymentMethod">
                            <div class="mt-2 grid grid-cols-2 gap-2">
                                <template x-for="method in paymentMethods" :key="method.value">
                                    <button
                                        type="button"
                                        class="rounded-xl border px-3 py-2.5 text-sm font-semibold transition"
                                        :class="paymentMethod === method.value ? 'border-amber-500 bg-amber-50 text-amber-950 ring-2 ring-amber-400' : 'border-slate-200 bg-white text-slate-700 hover:border-amber-300'"
                                        x-on:click="paymentMethod = method.value"
                                        x-text="method.label"
                                    ></button>
                                </template>
                            </div>
                        </div>

                        <div x-show="paymentMethod !== 'credit'" x-cloak>
                            <label for="payment_amount" class="text-xs font-semibold uppercase tracking-wider text-slate-500">Amount paid</label>
                            <input
                                id="payment_amount"
                                name="payment_amount"
                                type="number"
                                step="0.01"
                                min="0.01"
                                x-model.number="tendered"
                                class="mt-2 block w-full rounded-xl border-slate-300 px-3 py-3 text-xl font-bold tabular-nums focus:border-amber-500 focus:ring-amber-500"
                                placeholder="0.00"
                            >
                            <button type="button" class="mt-2 text-xs font-semibold text-amber-800 hover:underline" x-on:click="tendered = grandTotal()">Exact amount</button>
                            <p class="mt-2 rounded-lg bg-emerald-50 px-3 py-2 text-sm font-semibold text-emerald-800" x-show="changeDue() > 0" x-cloak>
                                Change: Rs. <span x-text="changeDue().toFixed(2)"></span>
                            </p>
                            <p class="mt-2 rounded-lg bg-rose-50 px-3 py-2 text-sm font-semibold text-rose-800" x-show="tendered > 0 && tendered < grandTotal()" x-cloak>
                                Balance: Rs. <span x-text="Math.max(0, grandTotal() - tendered).toFixed(2)"></span>
                            </p>
                        </div>
                        <p x-show="paymentMethod === 'credit'" x-cloak class="text-sm text-slate-500">Credit sale — full balance due on the bill.</p>
                    @endif

                    <div class="space-y-2 pt-1">
                        @if (auth()->user()->canConfirmTill())
                            <button
                                type="submit"
                                name="complete"
                                value="1"
                                class="btn btn-success w-full justify-center rounded-xl py-3.5 text-base font-bold shadow-sm"
                                :disabled="items.length === 0"
                                :class="items.length === 0 ? 'opacity-50 cursor-not-allowed' : ''"
                            >
                                Pay &amp; print receipt
                            </button>
                        @endif
                        <button
                            type="submit"
                            class="btn btn-secondary w-full justify-center rounded-xl py-2.5"
                            :disabled="items.length === 0"
                            :class="items.length === 0 ? 'opacity-50 cursor-not-allowed' : ''"
                        >
                            Save draft
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </form>

    @push('scripts')
        <script>
            function posForm() {
                return {
                    products: @json($productOptions),
                    customers: @json($customerOptions),
                    items: [],
                    customerId: @json((string) old('customer_id', '')),
                    walkInName: @json((string) old('walk_in_name', '')),
                    includePreviousBalance: @json((bool) old('include_previous_balance', false)),
                    customerQuery: '',
                    customerOpen: false,
                    customerBrowsing: false,
                    productQuery: '',
                    productOpen: false,
                    productHighlight: 0,
                    paymentMethod: @json((string) old('payment_method', 'cash')),
                    paymentMethods: [
                        { value: 'cash', label: 'Cash' },
                        { value: 'card', label: 'Card' },
                        { value: 'bank_transfer', label: 'Bank' },
                        { value: 'credit', label: 'Credit' },
                    ],
                    tendered: {{ (float) old('payment_amount', 0) }},
                    discount: {{ (float) old('discount', 0) }},
                    tax: 0,
                    scan: '',
                    scanMessage: '',
                    scanOk: false,
                    keySeq: 0,
                    init() {
                        this.focusScan();
                    },
                    focusScan() {
                        this.$nextTick(() => this.$refs.scan && this.$refs.scan.focus());
                    },
                    get effectiveWalkInName() {
                        if (this.customerId) {
                            return '';
                        }
                        const name = String(this.walkInName || '').trim();
                        return name !== '' ? name : 'Walk-in Customer';
                    },
                    get filteredProducts() {
                        const q = this.productQuery.trim().toLowerCase();
                        if (!q) return [];
                        return this.products.filter((p) => {
                            return (p.name + ' ' + p.sku).toLowerCase().includes(q);
                        }).slice(0, 40);
                    },
                    get filteredCustomers() {
                        const q = this.customerBrowsing ? '' : this.customerQuery.trim().toLowerCase();
                        const list = !q
                            ? this.customers
                            : this.customers.filter((c) => {
                                return (c.name + ' ' + (c.phone || '')).toLowerCase().includes(q);
                            });
                        return list.slice(0, 80);
                    },
                    get selectedCustomerLabel() {
                        const c = this.customers.find((x) => String(x.id) === String(this.customerId));
                        return c ? (c.name + (c.phone ? ' · ' + c.phone : '')) : '';
                    },
                    get customerPreviousDue() {
                        const c = this.customers.find((x) => String(x.id) === String(this.customerId));
                        return c ? (Number(c.outstanding) || 0) : 0;
                    },
                    openCustomerList() {
                        this.customerOpen = true;
                        this.customerBrowsing = true;
                        this.customerQuery = '';
                    },
                    highlightProduct(dir) {
                        const max = this.filteredProducts.length - 1;
                        if (max < 0) return;
                        this.productHighlight = Math.max(0, Math.min(max, this.productHighlight + dir));
                    },
                    pickHighlightedProduct() {
                        const p = this.filteredProducts[this.productHighlight];
                        if (p) {
                            this.addProduct(p);
                            this.productQuery = '';
                            this.productOpen = false;
                            this.focusScan();
                        } else {
                            this.applyScan();
                        }
                    },
                    selectCustomer(c) {
                        this.customerId = String(c.id);
                        this.customerQuery = c.name;
                        this.walkInName = '';
                        this.customerBrowsing = false;
                        this.customerOpen = false;
                        this.includePreviousBalance = false;
                    },
                    clearCustomer() {
                        this.customerId = '';
                        this.customerQuery = '';
                        this.customerBrowsing = false;
                        this.includePreviousBalance = false;
                    },
                    prepareSubmit(e) {
                        if (this.items.length === 0) {
                            e.preventDefault();
                            this.scanMessage = 'Add at least one product.';
                            this.scanOk = false;
                            this.focusScan();
                            return;
                        }
                        if (! this.customerId && ! String(this.walkInName || '').trim()) {
                            this.walkInName = 'Walk-in Customer';
                        }
                        if (this.includePreviousBalance && this.customerPreviousDue > 0) {
                            const message = 'Add previous balance of Rs. ' + this.customerPreviousDue.toFixed(2) + ' to this bill?';
                            if (! window.confirm(message)) {
                                e.preventDefault();
                            }
                        }
                    },
                    findProduct(code) {
                        const q = String(code || '').trim().toLowerCase();
                        if (!q) return null;
                        return this.products.find((p) => String(p.sku).toLowerCase() === q || String(p.id) === q) || null;
                    },
                    applyScan() {
                        const code = this.scan;
                        const product = this.findProduct(code);
                        if (!product) {
                            this.scanMessage = code ? ('No product for "' + code + '"') : 'Scan or type a SKU first.';
                            this.scanOk = false;
                            this.scan = '';
                            this.focusScan();
                            return;
                        }
                        this.addProduct(product);
                        this.scanMessage = 'Added ' + product.name;
                        this.scanOk = true;
                        this.scan = '';
                        this.focusScan();
                    },
                    addProduct(product) {
                        const existing = this.items.find((i) => String(i.product_id) === String(product.id));
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
                    saleTotal() {
                        const subtotal = this.items.reduce((sum, item) => sum + this.lineTotal(item), 0);
                        return Math.max(0, subtotal - (Number(this.discount) || 0) + (Number(this.tax) || 0));
                    },
                    grandTotal() {
                        const total = this.saleTotal();
                        if (this.includePreviousBalance && this.customerPreviousDue > 0) {
                            return total + this.customerPreviousDue;
                        }
                        return total;
                    },
                    changeDue() {
                        return Math.max(0, (Number(this.tendered) || 0) - this.grandTotal());
                    },
                };
            }
        </script>
    @endpush
</x-pos-layout>
