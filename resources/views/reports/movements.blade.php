<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="text-xl font-semibold text-slate-800">Stock movements</h2>
            <a href="{{ route('reports.index') }}" class="btn btn-secondary btn-sm">All reports</a>
        </div>
    </x-slot>

    @include('reports.partials.date-filter')

    <p class="mb-3 text-xs text-slate-500">Showing the latest 500 movements in this date range.</p>

    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase text-slate-500">
                <tr>
                    <th class="px-4 py-3">Date</th>
                    <th class="px-4 py-3">Product</th>
                    <th class="px-4 py-3">Type</th>
                    <th class="px-4 py-3 text-right">Qty</th>
                    <th class="px-4 py-3 text-right">Balance</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse ($rows as $movement)
                    <tr>
                        <td class="px-4 py-3 whitespace-nowrap">{{ $movement->movement_date->format('d/m/Y') }}</td>
                        <td class="px-4 py-3">{{ $movement->product?->name }}</td>
                        <td class="px-4 py-3">{{ str_replace('_', ' ', $movement->movement_type->value) }}</td>
                        <td class="px-4 py-3 text-right">{{ number_format((float) $movement->quantity, 3) }}</td>
                        <td class="px-4 py-3 text-right">{{ number_format((float) $movement->balance_after, 3) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-8 text-center text-slate-500">No movements in this period.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-app-layout>
