<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="text-xl font-semibold text-slate-800">Outstanding payments</h2>
            <a href="{{ route('reports.index') }}" class="btn btn-secondary btn-sm">All reports</a>
        </div>
    </x-slot>

    <div class="mb-4 flex items-center justify-between">
        <p class="text-sm text-slate-600">Total outstanding: <strong>Rs. {{ number_format($total, 2) }}</strong></p>
        <a href="{{ request()->fullUrlWithQuery(['export' => 1]) }}" class="btn btn-secondary">Export CSV</a>
    </div>

    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase text-slate-500">
                <tr>
                    <th class="px-4 py-3">Invoice</th>
                    <th class="px-4 py-3">Customer</th>
                    <th class="px-4 py-3">Date</th>
                    <th class="px-4 py-3 text-right">Total</th>
                    <th class="px-4 py-3 text-right">Balance</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse ($rows as $sale)
                    <tr>
                        <td class="px-4 py-3">{{ $sale->invoice_no }}</td>
                        <td class="px-4 py-3">{{ $sale->customerName() }}</td>
                        <td class="px-4 py-3">{{ $sale->sale_date->format('d/m/Y') }}</td>
                        <td class="px-4 py-3 text-right">{{ number_format((float) $sale->total, 2) }}</td>
                        <td class="px-4 py-3 text-right font-medium text-rose-600">{{ number_format((float) $sale->balance, 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-8 text-center text-slate-500">No outstanding balances.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-app-layout>
