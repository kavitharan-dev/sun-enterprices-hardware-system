<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <h2 class="text-xl font-semibold text-slate-800">Purchases</h2>
            <a href="{{ route('store.purchases.create') }}" class="btn btn-primary">New Purchase</a>
        </div>
    </x-slot>

    <form method="GET" class="mb-4 grid gap-3 sm:grid-cols-3">
        <input type="text" name="q" value="{{ request('q') }}" placeholder="Search reference or supplier" class="rounded-lg border-slate-300 text-sm">
        <select name="status" class="rounded-lg border-slate-300 text-sm">
            <option value="">All statuses</option>
            <option value="draft" @selected(request('status') === 'draft')>Draft</option>
            <option value="completed" @selected(request('status') === 'completed')>Completed</option>
            <option value="cancelled" @selected(request('status') === 'cancelled')>Cancelled</option>
        </select>
        <button class="btn btn-dark sm:min-w-32">Filter</button>
    </form>

    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase text-slate-500">
                <tr>
                    <th class="px-4 py-3">Reference</th>
                    <th class="px-4 py-3">Supplier</th>
                    <th class="px-4 py-3">Date</th>
                    <th class="px-4 py-3 text-right">Total</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($purchases as $purchase)
                    <tr>
                        <td class="px-4 py-3 font-mono text-xs">
                            <a href="{{ route('store.purchases.show', $purchase) }}" class="font-semibold text-amber-700 hover:underline">{{ $purchase->reference_no }}</a>
                        </td>
                        <td class="px-4 py-3">{{ $purchase->supplier?->name }}</td>
                        <td class="px-4 py-3">{{ $purchase->purchase_date->format('d/m/Y') }}</td>
                        <td class="px-4 py-3 text-right">Rs. {{ number_format((float) $purchase->total, 2) }}</td>
                        <td class="px-4 py-3"><x-status-badge :status="$purchase->status" /></td>
                        <td class="px-4 py-3 text-right">
                            @if ($purchase->isDraft())
                                <a href="{{ route('store.purchases.edit', $purchase) }}" class="btn btn-secondary btn-sm">Edit</a>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-8 text-center text-slate-500">No purchases yet. Receive stock from a supplier.</td></tr>
                @endforelse
            </tbody>
        </table>
        @if ($purchases->hasPages())
            <div class="border-t px-4 py-3">{{ $purchases->links() }}</div>
        @endif
    </div>
</x-app-layout>
