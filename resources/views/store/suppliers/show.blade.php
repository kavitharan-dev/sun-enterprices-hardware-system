<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="text-xl font-semibold text-slate-800">{{ $supplier->name }}</h2>
            <a href="{{ route('store.suppliers.edit', $supplier) }}" class="btn btn-dark">Edit</a>
        </div>
    </x-slot>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="rounded-xl border border-slate-200 bg-white p-5 text-sm shadow-sm space-y-2">
            <p><span class="text-slate-500">Contact:</span> {{ $supplier->contact_person ?? '—' }}</p>
            <p><span class="text-slate-500">Phone:</span> {{ $supplier->phone ?? '—' }}</p>
            <p><span class="text-slate-500">Email:</span> {{ $supplier->email ?? '—' }}</p>
            <p><span class="text-slate-500">Address:</span> {{ $supplier->address ?? '—' }}</p>
            <p><span class="text-slate-500">Status:</span> {{ $supplier->is_active ? 'Active' : 'Inactive' }}</p>
        </div>
        <div class="lg:col-span-2 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b px-5 py-3 font-semibold">Recent purchases</div>
            <table class="min-w-full text-sm">
                <tbody class="divide-y">
                    @forelse ($supplier->purchases as $purchase)
                        <tr>
                            <td class="px-4 py-3">
                                <a href="{{ route('store.purchases.show', $purchase) }}" class="font-medium text-amber-700">{{ $purchase->reference_no }}</a>
                            </td>
                            <td class="px-4 py-3">{{ $purchase->purchase_date->format('d/m/Y') }}</td>
                            <td class="px-4 py-3">Rs. {{ number_format((float) $purchase->total, 2) }}</td>
                            <td class="px-4 py-3"><x-status-badge :status="$purchase->status" /></td>
                        </tr>
                    @empty
                        <tr><td class="px-4 py-8 text-center text-slate-500">No purchases yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
