<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="text-xl font-semibold text-slate-800">Sales report</h2>
            <a href="{{ route('reports.index') }}" class="btn btn-secondary btn-sm">All reports</a>
        </div>
    </x-slot>

    @include('reports.partials.date-filter')

    <div class="mb-4 grid gap-4 sm:grid-cols-3">
        <div class="rounded-xl border border-slate-200 bg-white p-4 text-sm"><p class="text-slate-500">Invoices</p><p class="text-lg font-semibold">{{ number_format($count) }}</p></div>
        <div class="rounded-xl border border-slate-200 bg-white p-4 text-sm"><p class="text-slate-500">Total</p><p class="text-lg font-semibold">Rs. {{ number_format($total, 2) }}</p></div>
        <div class="rounded-xl border border-slate-200 bg-white p-4 text-sm"><p class="text-slate-500">Outstanding</p><p class="text-lg font-semibold">Rs. {{ number_format($balance, 2) }}</p></div>
    </div>

    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase text-slate-500">
                <tr>
                    <th class="px-4 py-3">Date</th>
                    <th class="px-4 py-3">Invoice</th>
                    <th class="px-4 py-3">Customer</th>
                    <th class="px-4 py-3 text-right">Total</th>
                    <th class="px-4 py-3 text-right">Balance</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse ($rows as $sale)
                    <tr>
                        <td class="px-4 py-3">{{ $sale->sale_date->format('d/m/Y') }}</td>
                        <td class="px-4 py-3">{{ $sale->invoice_no }}</td>
                        <td class="px-4 py-3">{{ $sale->customerName() }}</td>
                        <td class="px-4 py-3 text-right">{{ number_format((float) $sale->total, 2) }}</td>
                        <td class="px-4 py-3 text-right">{{ number_format((float) $sale->balance, 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-8 text-center text-slate-500">No sales in this period.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-app-layout>
