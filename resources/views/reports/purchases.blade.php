<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="text-xl font-semibold text-slate-800">Purchases report</h2>
            <a href="{{ route('reports.index') }}" class="btn btn-secondary btn-sm">All reports</a>
        </div>
    </x-slot>

    @include('reports.partials.date-filter')

    <div class="mb-4 grid gap-4 sm:grid-cols-2">
        <div class="rounded-xl border border-slate-200 bg-white p-4 text-sm"><p class="text-slate-500">Purchases</p><p class="text-lg font-semibold">{{ number_format($count) }}</p></div>
        <div class="rounded-xl border border-slate-200 bg-white p-4 text-sm"><p class="text-slate-500">Total</p><p class="text-lg font-semibold">Rs. {{ number_format($total, 2) }}</p></div>
    </div>

    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase text-slate-500">
                <tr>
                    <th class="px-4 py-3">Date</th>
                    <th class="px-4 py-3">Reference</th>
                    <th class="px-4 py-3">Supplier</th>
                    <th class="px-4 py-3 text-right">Total</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse ($rows as $purchase)
                    <tr>
                        <td class="px-4 py-3">{{ $purchase->purchase_date->format('d/m/Y') }}</td>
                        <td class="px-4 py-3">{{ $purchase->reference_no }}</td>
                        <td class="px-4 py-3">{{ $purchase->supplier?->name }}</td>
                        <td class="px-4 py-3 text-right">{{ number_format((float) $purchase->total, 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-4 py-8 text-center text-slate-500">No purchases in this period.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-app-layout>
