<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="text-xl font-semibold text-slate-800">{{ $customer->name }}</h2>
            <a href="{{ route('store.customers.edit', $customer) }}" class="btn btn-dark">Edit</a>
        </div>
    </x-slot>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="rounded-xl border border-slate-200 bg-white p-5 text-sm shadow-sm space-y-2">
            <p><span class="text-slate-500">Phone:</span> {{ $customer->phone ?? '—' }}</p>
            <p><span class="text-slate-500">Email:</span> {{ $customer->email ?? '—' }}</p>
            <p><span class="text-slate-500">NIC:</span> {{ $customer->nic ?? '—' }}</p>
            <p><span class="text-slate-500">Address:</span> {{ $customer->address ?? '—' }}</p>
            <p class="font-semibold {{ $customer->outstanding_balance > 0 ? 'text-rose-600' : '' }}">
                Outstanding: Rs. {{ number_format((float) $customer->outstanding_balance, 2) }}
            </p>
        </div>
        <div class="lg:col-span-2 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b px-5 py-3 font-semibold">Recent sales</div>
            <table class="min-w-full text-sm">
                <tbody class="divide-y">
                    @forelse ($customer->sales as $sale)
                        <tr>
                            <td class="px-4 py-3">
                                <a href="{{ route('store.sales.show', $sale) }}" class="font-medium text-amber-700">{{ $sale->invoice_no ?? 'Draft' }}</a>
                            </td>
                            <td class="px-4 py-3">{{ $sale->sale_date->format('d/m/Y') }}</td>
                            <td class="px-4 py-3">Rs. {{ number_format((float) $sale->total, 2) }}</td>
                            <td class="px-4 py-3"><x-status-badge :status="$sale->payment_status" /></td>
                        </tr>
                    @empty
                        <tr><td class="px-4 py-8 text-center text-slate-500">No sales yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
