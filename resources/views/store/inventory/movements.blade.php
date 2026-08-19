@php
    use App\Enums\MovementType;
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="text-xl font-semibold text-slate-800">Stock Movements</h2>
            <a href="{{ route('store.inventory.index') }}" class="btn btn-secondary btn-sm">Back to inventory</a>
        </div>
    </x-slot>

    <form method="GET" class="mb-4 grid gap-3 sm:grid-cols-5">
        <select name="product_id" class="rounded-lg border-slate-300 text-sm">
            <option value="">All products</option>
            @foreach ($products as $product)
                <option value="{{ $product->id }}" @selected(request('product_id') == $product->id)>{{ $product->sku }} — {{ $product->name }}</option>
            @endforeach
        </select>
        <select name="type" class="rounded-lg border-slate-300 text-sm">
            <option value="">All types</option>
            @foreach (MovementType::cases() as $type)
                <option value="{{ $type->value }}" @selected(request('type') === $type->value)>{{ $type->label() }}</option>
            @endforeach
        </select>
        <input type="date" name="from" value="{{ request('from') }}" class="rounded-lg border-slate-300 text-sm">
        <input type="date" name="to" value="{{ request('to') }}" class="rounded-lg border-slate-300 text-sm">
        <button class="btn btn-dark">Filter</button>
    </form>

    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full divide-y text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase text-slate-500">
                <tr>
                    <th class="px-4 py-3">Date</th>
                    <th class="px-4 py-3">Product</th>
                    <th class="px-4 py-3">Type</th>
                    <th class="px-4 py-3 text-right">Qty</th>
                    <th class="px-4 py-3 text-right">Balance</th>
                    <th class="px-4 py-3">User</th>
                    <th class="px-4 py-3">Notes</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse ($movements as $movement)
                    <tr>
                        <td class="px-4 py-3 whitespace-nowrap">{{ $movement->movement_date->format('d/m/Y') }}</td>
                        <td class="px-4 py-3">
                            <a href="{{ route('store.products.show', $movement->product) }}" class="font-medium hover:text-amber-700">{{ $movement->product?->name }}</a>
                        </td>
                        <td class="px-4 py-3">{{ $movement->movement_type->label() }}</td>
                        <td class="px-4 py-3 text-right font-semibold {{ $movement->quantity >= 0 ? 'text-emerald-700' : 'text-rose-700' }}">
                            {{ $movement->quantity > 0 ? '+' : '' }}{{ rtrim(rtrim(number_format((float) $movement->quantity, 3, '.', ''), '0'), '.') }}
                        </td>
                        <td class="px-4 py-3 text-right">{{ rtrim(rtrim(number_format((float) $movement->balance_after, 3, '.', ''), '0'), '.') }}</td>
                        <td class="px-4 py-3 text-slate-500">{{ $movement->user?->name ?? '—' }}</td>
                        <td class="px-4 py-3 text-slate-500">{{ $movement->notes }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-8 text-center text-slate-500">No movements recorded.</td></tr>
                @endforelse
            </tbody>
        </table>
        @if ($movements->hasPages())
            <div class="border-t px-4 py-3">{{ $movements->links() }}</div>
        @endif
    </div>
</x-app-layout>
