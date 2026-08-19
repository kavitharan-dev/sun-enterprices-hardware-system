<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-xl font-semibold text-slate-800">{{ $purchase->reference_no }}</h2>
                <p class="text-sm text-slate-500">{{ $purchase->supplier?->name }} · {{ $purchase->purchase_date->format('d/m/Y') }}</p>
            </div>
            <x-status-badge :status="$purchase->status" />
        </div>
    </x-slot>

    <div class="space-y-6">
        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <table class="min-w-full divide-y text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Product</th>
                        <th class="px-4 py-3 text-right">Qty</th>
                        <th class="px-4 py-3 text-right">Unit cost</th>
                        <th class="px-4 py-3 text-right">Subtotal</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @foreach ($purchase->items as $item)
                        <tr>
                            <td class="px-4 py-3">
                                <p class="font-medium">{{ $item->product?->name }}</p>
                                <p class="text-xs text-slate-500">{{ $item->product?->sku }}</p>
                            </td>
                            <td class="px-4 py-3 text-right">{{ rtrim(rtrim(number_format((float) $item->quantity, 3, '.', ''), '0'), '.') }} {{ $item->product?->unit?->symbol }}</td>
                            <td class="px-4 py-3 text-right">Rs. {{ number_format((float) $item->unit_cost, 2) }}</td>
                            <td class="px-4 py-3 text-right">Rs. {{ number_format((float) $item->subtotal, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <div class="border-t bg-slate-50 px-4 py-4 text-sm space-y-1 text-right">
                <p>Subtotal: Rs. {{ number_format((float) $purchase->subtotal, 2) }}</p>
                <p>Discount: Rs. {{ number_format((float) $purchase->discount, 2) }}</p>
                <p>Tax: Rs. {{ number_format((float) $purchase->tax, 2) }}</p>
                <p class="text-lg font-bold text-slate-900">Total: Rs. {{ number_format((float) $purchase->total, 2) }}</p>
                @if ($purchase->transactionNo())
                    <p>Daily accounts: <x-transaction-no :no="$purchase->transactionNo()" /></p>
                @endif
            </div>
        </div>

        @if ($purchase->isDraft())
            <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                The cashier records this supplier payment on Daily Accounts. Stock then updates from that transaction.
            </div>
            <div class="flex flex-wrap gap-3">
                <a href="{{ route('store.purchases.edit', $purchase) }}" class="btn btn-secondary">Edit draft</a>
                <form method="POST" action="{{ route('store.purchases.destroy', $purchase) }}" onsubmit="return confirm('Cancel this draft purchase?')">
                    @csrf @method('DELETE')
                    <button class="btn btn-danger-outline">Cancel draft</button>
                </form>
            </div>
        @endif
    </div>
</x-app-layout>
