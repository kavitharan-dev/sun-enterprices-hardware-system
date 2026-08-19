<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <h2 class="text-xl font-semibold text-slate-800">Suppliers</h2>
            <a href="{{ route('store.suppliers.create') }}" class="btn btn-primary">Add Supplier</a>
        </div>
    </x-slot>

    <form method="GET" class="mb-4 flex gap-3">
        <input type="text" name="q" value="{{ request('q') }}" placeholder="Search suppliers" class="w-full max-w-sm rounded-lg border-slate-300 text-sm">
        <button class="btn btn-dark">Search</button>
    </form>

    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase text-slate-500">
                <tr>
                    <th class="px-4 py-3">Supplier</th>
                    <th class="px-4 py-3">Contact</th>
                    <th class="px-4 py-3">Purchases</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($suppliers as $supplier)
                    <tr>
                        <td class="px-4 py-3">
                            <a href="{{ route('store.suppliers.show', $supplier) }}" class="font-medium text-slate-900 hover:text-amber-700">{{ $supplier->name }}</a>
                            <p class="text-xs text-slate-500">{{ $supplier->address }}</p>
                        </td>
                        <td class="px-4 py-3 text-slate-600">
                            {{ $supplier->contact_person }}<br>
                            <span class="text-xs">{{ $supplier->phone }}</span>
                        </td>
                        <td class="px-4 py-3">{{ $supplier->purchases_count }}</td>
                        <td class="px-4 py-3"><x-status-badge :status="$supplier->is_active ? 'active' : 'inactive'" /></td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('store.suppliers.edit', $supplier) }}" class="btn btn-secondary btn-sm">Edit</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-8 text-center text-slate-500">No suppliers yet.</td></tr>
                @endforelse
            </tbody>
        </table>
        @if ($suppliers->hasPages())
            <div class="border-t px-4 py-3">{{ $suppliers->links() }}</div>
        @endif
    </div>
</x-app-layout>
