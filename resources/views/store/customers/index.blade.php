<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <h2 class="text-xl font-semibold text-slate-800">Customers</h2>
            <a href="{{ route('store.customers.create') }}" class="btn btn-primary">Add Customer</a>
        </div>
    </x-slot>

    <form method="GET" class="mb-4 flex gap-3">
        <input type="text" name="q" value="{{ request('q') }}" placeholder="Search name, phone or NIC" class="w-full max-w-sm rounded-lg border-slate-300 text-sm">
        <button class="btn btn-dark">Search</button>
    </form>

    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full divide-y text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase text-slate-500">
                <tr>
                    <th class="px-4 py-3">Customer</th>
                    <th class="px-4 py-3">Phone</th>
                    <th class="px-4 py-3 text-right">Outstanding</th>
                    <th class="px-4 py-3">Sales</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse ($customers as $customer)
                    <tr>
                        <td class="px-4 py-3">
                            <a href="{{ route('store.customers.show', $customer) }}" class="font-medium text-slate-900 hover:text-amber-700">{{ $customer->name }}</a>
                            <p class="text-xs text-slate-500">{{ $customer->nic }}</p>
                        </td>
                        <td class="px-4 py-3">{{ $customer->phone ?? '—' }}</td>
                        <td class="px-4 py-3 text-right font-semibold {{ $customer->outstanding_balance > 0 ? 'text-rose-600' : '' }}">
                            Rs. {{ number_format((float) $customer->outstanding_balance, 2) }}
                        </td>
                        <td class="px-4 py-3">{{ $customer->sales_count }}</td>
                        <td class="px-4 py-3"><x-status-badge :status="$customer->is_active ? 'active' : 'inactive'" /></td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('store.customers.edit', $customer) }}" class="btn btn-secondary btn-sm">Edit</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-8 text-center text-slate-500">No customers yet.</td></tr>
                @endforelse
            </tbody>
        </table>
        @if ($customers->hasPages())
            <div class="border-t px-4 py-3">{{ $customers->links() }}</div>
        @endif
    </div>
</x-app-layout>
