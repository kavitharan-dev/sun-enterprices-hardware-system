<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-xl font-semibold text-slate-800">{{ $sale->invoice_no ?? 'Draft sale' }}</h2>
                <p class="text-sm text-slate-500">{{ $sale->customerName() }} · {{ $sale->sale_date->format('d/m/Y') }}</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <x-status-badge :status="$sale->status" />
                <x-status-badge :status="$sale->payment_status" />
                @if ($sale->isCompleted())
                    <a href="{{ route('store.sales.create') }}" class="btn btn-success">New sale</a>
                    <a href="{{ route('store.sales.bill', $sale) }}" class="btn btn-primary">View bill</a>
                    <a href="{{ route('store.sales.print', $sale) }}" class="btn btn-dark">Print invoice</a>
                    <a href="{{ route('store.sales.invoice', $sale) }}" target="_blank" class="btn btn-secondary">PDF</a>
                @endif
            </div>
        </div>
    </x-slot>

    <div class="space-y-6">
        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <table class="min-w-full divide-y text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Product</th>
                        <th class="px-4 py-3 text-right">Qty</th>
                        <th class="px-4 py-3 text-right">Unit price</th>
                        <th class="px-4 py-3 text-right">Discount</th>
                        <th class="px-4 py-3 text-right">Total</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @foreach ($sale->items as $item)
                        <tr>
                            <td class="px-4 py-3">
                                <p class="font-medium">{{ $item->product?->name }}</p>
                                <p class="text-xs text-slate-500">{{ $item->product?->sku }}</p>
                            </td>
                            <td class="px-4 py-3 text-right">{{ rtrim(rtrim(number_format((float) $item->quantity, 3, '.', ''), '0'), '.') }} {{ $item->product?->unit?->symbol }}</td>
                            <td class="px-4 py-3 text-right">Rs. {{ number_format((float) $item->unit_price, 2) }}</td>
                            <td class="px-4 py-3 text-right">Rs. {{ number_format((float) $item->discount, 2) }}</td>
                            <td class="px-4 py-3 text-right">Rs. {{ number_format((float) $item->subtotal, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <div class="border-t bg-slate-50 px-4 py-4 text-sm space-y-1 text-right">
                <p>Subtotal: Rs. {{ number_format((float) $sale->subtotal, 2) }}</p>
                <p>Discount: Rs. {{ number_format((float) $sale->discount, 2) }}</p>
                <p>Tax: Rs. {{ number_format((float) $sale->tax, 2) }}</p>
                <p class="text-lg font-bold">Bill total: Rs. {{ number_format((float) $sale->total, 2) }}</p>
                <p>Customer paid: Rs. {{ number_format($sale->amountReceived(), 2) }}</p>
                @if ($sale->changeDue() > 0)
                    <p class="font-semibold text-emerald-700">Change to return: Rs. {{ number_format($sale->changeDue(), 2) }}</p>
                @endif
                <p class="font-semibold {{ $sale->balance > 0 ? 'text-rose-600' : 'text-emerald-700' }}">Balance due: Rs. {{ number_format((float) $sale->balance, 2) }}</p>
            </div>
        </div>

        @if ($sale->isDraft() && auth()->user()->canConfirmTill())
            <form method="POST" action="{{ route('store.sales.complete', $sale) }}" class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm space-y-4" x-data="{ method: 'cash', tendered: {{ (float) $sale->total }}, total: {{ (float) $sale->total }} }">
                @csrf
                <h3 class="font-semibold">Take payment</h3>
                <p class="text-sm text-slate-500">Records the money in Daily Accounts and updates stock from the same transaction.</p>
                @unless ($sale->customer_id)
                    <p class="text-sm text-slate-600">Walk-in customer on this draft: <strong>{{ $sale->customerName() }}</strong>. Edit the draft if you need to change the name before completing.</p>
                @endunless
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <x-input-label for="payment_method" value="Payment method" />
                        <select id="payment_method" name="payment_method" x-model="method" class="mt-1 block w-full rounded-md border-gray-300">
                            <option value="cash">Cash</option>
                            <option value="card">Card</option>
                            <option value="bank_transfer">Bank Transfer</option>
                            <option value="credit">Credit (pay later)</option>
                        </select>
                    </div>
                    <div x-show="method !== 'credit'">
                        <x-input-label for="payment_amount" value="Amount customer paid (Rs.)" />
                        <input id="payment_amount" name="payment_amount" type="number" step="0.01" min="0.01" x-model.number="tendered" x-bind:required="method !== 'credit'" class="mt-1 block w-full rounded-md border-gray-300" value="{{ old('payment_amount', $sale->total) }}">
                        <p class="mt-1 text-xs text-slate-500">If the bill is Rs. {{ number_format((float) $sale->total, 2) }} and they give a larger note, enter that note. Change is calculated for you.</p>
                    </div>
                </div>
                <div x-show="method !== 'credit'" class="rounded-lg bg-sun-50 p-3 text-sm">
                    <p>Bill total: Rs. <span x-text="total.toFixed(2)"></span></p>
                    <p>Customer paid: Rs. <span x-text="(Number(tendered) || 0).toFixed(2)"></span></p>
                    <p class="font-semibold text-emerald-700" x-show="(Number(tendered) || 0) > total">Change to return: Rs. <span x-text="Math.max(0, (Number(tendered) || 0) - total).toFixed(2)"></span></p>
                    <p class="font-semibold text-rose-600" x-show="(Number(tendered) || 0) < total">Balance due: Rs. <span x-text="Math.max(0, total - (Number(tendered) || 0)).toFixed(2)"></span></p>
                </div>
                <p x-show="method === 'credit'" class="text-sm text-slate-500">Credit sale: no paid amount needed. The full bill stays as balance due.</p>
                <div class="flex gap-3">
                    <button class="btn btn-success">Complete, print bill &amp; next sale</button>
                    <a href="{{ route('store.sales.edit', $sale) }}" class="rounded-lg border px-4 py-2 text-sm">Edit draft</a>
                </div>
            </form>
            <form method="POST" action="{{ route('store.sales.destroy', $sale) }}" onsubmit="return confirm('Cancel this draft sale?')">
                @csrf
                @method('DELETE')
                <button class="btn btn-danger-outline btn-sm">Cancel draft</button>
            </form>
        @elseif ($sale->isDraft())
            <p class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">Draft saved. The cashier records the payment on Daily Accounts; stock and sales totals update from that transaction.</p>
        @endif

        @if ($sale->isCompleted())
            <div class="flex flex-wrap gap-3 rounded-xl border border-amber-200 bg-amber-50 p-4">
                    <a href="{{ route('store.sales.create') }}" class="btn btn-success">New sale</a>
                    <a href="{{ route('store.sales.bill', $sale) }}" class="btn btn-primary">View bill</a>
                    <a href="{{ route('store.sales.print', $sale) }}" class="btn btn-dark">Print invoice</a>
                <a href="{{ route('store.sales.invoice', $sale) }}" target="_blank" class="btn btn-secondary">View PDF</a>
                <a href="{{ route('store.sales.invoice.download', $sale) }}" class="btn btn-secondary">Download PDF</a>
            </div>

            @if ($sale->balance > 0 && auth()->user()->canConfirmTill())
                <form method="POST" action="{{ route('store.sales.pay', $sale) }}" class="max-w-xl rounded-xl border border-slate-200 bg-white p-6 shadow-sm space-y-3">
                    @csrf
                    <h3 class="font-semibold">Record payment</h3>
                    <div>
                        <x-input-label for="amount" value="Amount" />
                        <x-text-input id="amount" name="amount" type="number" step="0.01" min="0.01" class="mt-1 block w-full" :value="$sale->balance" required />
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <x-input-label for="payment_method" value="Method" />
                            <select id="payment_method" name="payment_method" class="mt-1 block w-full rounded-md border-gray-300" required>
                                <option value="cash">Cash</option>
                                <option value="card">Card</option>
                                <option value="bank_transfer">Bank Transfer</option>
                            </select>
                        </div>
                        <div>
                            <x-input-label for="payment_date" value="Date" />
                            <x-text-input id="payment_date" name="payment_date" type="date" class="mt-1 block w-full" :value="now()->toDateString()" required />
                        </div>
                    </div>
                    <div>
                        <x-input-label for="reference" value="Reference (optional)" />
                        <x-text-input id="reference" name="reference" class="mt-1 block w-full" />
                    </div>
                    <x-primary-button>Save payment</x-primary-button>
                </form>
            @endif

            @if ($sale->payments->isNotEmpty())
                <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                    <div class="border-b px-5 py-3 font-semibold">Payments</div>
                    <table class="min-w-full text-sm">
                        <tbody class="divide-y">
                            @foreach ($sale->payments as $payment)
                                <tr>
                                    <td class="px-4 py-3">{{ $payment->payment_date->format('d/m/Y') }}</td>
                                    <td class="px-4 py-3"><x-transaction-no :no="$payment->transactionNo()" /></td>
                                    <td class="px-4 py-3">{{ $payment->payment_method->label() }}</td>
                                    <td class="px-4 py-3">Rs. {{ number_format((float) $payment->amount, 2) }}</td>
                                    <td class="px-4 py-3 text-slate-500">{{ $payment->receiver?->name }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        @endif
    </div>
</x-app-layout>
