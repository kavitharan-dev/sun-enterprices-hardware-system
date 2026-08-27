<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <h2 class="text-xl font-semibold text-slate-800">Sales</h2>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('store.sales.pos') }}" class="btn btn-success">POS</a>
                <a href="{{ route('store.sales.create') }}" class="btn btn-primary">New Sale</a>
            </div>
        </div>
    </x-slot>

    <form method="GET" class="mb-4 grid gap-3 sm:grid-cols-4">
        <input type="text" name="q" value="{{ request('q') }}" placeholder="Invoice or customer" class="rounded-lg border-slate-300 text-sm">
        <x-searchable-select
            name="status"
            :options="[
                ['value' => 'draft', 'label' => 'Draft'],
                ['value' => 'completed', 'label' => 'Completed'],
                ['value' => 'cancelled', 'label' => 'Cancelled'],
            ]"
            :value="(string) request('status')"
            empty-label="All statuses"
            :allow-empty="true"
            placeholder="Search status…"
        />
        <x-searchable-select
            name="payment_status"
            :options="[
                ['value' => 'unpaid', 'label' => 'Unpaid'],
                ['value' => 'partial', 'label' => 'Partial'],
                ['value' => 'paid', 'label' => 'Paid'],
            ]"
            :value="(string) request('payment_status')"
            empty-label="All payments"
            :allow-empty="true"
            placeholder="Search payment…"
        />
        <button class="btn btn-dark">Filter</button>
    </form>

    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full divide-y text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase text-slate-500">
                <tr>
                    <th class="px-4 py-3">Invoice</th>
                    <th class="px-4 py-3">Customer</th>
                    <th class="px-4 py-3">Date</th>
                    <th class="px-4 py-3 text-right">Total</th>
                    <th class="px-4 py-3 text-right">Balance</th>
                    <th class="px-4 py-3">Payment</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse ($sales as $sale)
                    <tr>
                        <td class="px-4 py-3 font-mono text-xs">
                            <a href="{{ route('store.sales.show', $sale) }}" class="font-semibold text-amber-700 hover:underline">{{ $sale->invoice_no ?? 'Draft' }}</a>
                        </td>
                        <td class="px-4 py-3">{{ $sale->customerName() }}</td>
                        <td class="px-4 py-3">{{ $sale->sale_date->format('d/m/Y') }}</td>
                        <td class="px-4 py-3 text-right">Rs. {{ number_format((float) $sale->total, 2) }}</td>
                        <td class="px-4 py-3 text-right {{ $sale->balance > 0 ? 'text-rose-600 font-semibold' : '' }}">Rs. {{ number_format((float) $sale->balance, 2) }}</td>
                        <td class="px-4 py-3"><x-status-badge :status="$sale->payment_status" /></td>
                        <td class="px-4 py-3"><x-status-badge :status="$sale->status" /></td>
                        <td class="px-4 py-3 text-right">
                            @if ($sale->isCompleted())
                                <a href="{{ route('store.sales.bill', $sale) }}" class="btn btn-primary btn-sm">View bill</a>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="px-4 py-8 text-center text-slate-500">No sales yet. Create a sale from the counter.</td></tr>
                @endforelse
            </tbody>
        </table>
        @if ($sales->hasPages())
            <div class="border-t px-4 py-3">{{ $sales->links() }}</div>
        @endif
    </div>
</x-app-layout>
