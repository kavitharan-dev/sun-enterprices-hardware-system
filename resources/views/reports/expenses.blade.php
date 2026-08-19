<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="text-xl font-semibold text-slate-800">Project expenses</h2>
            <a href="{{ route('reports.index') }}" class="btn btn-secondary btn-sm">All reports</a>
        </div>
    </x-slot>

    @include('reports.partials.date-filter')

    <p class="mb-4 text-sm text-slate-600">Total Rs. {{ number_format($total, 2) }}</p>

    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase text-slate-500">
                <tr>
                    <th class="px-4 py-3">Date</th>
                    <th class="px-4 py-3">Project</th>
                    <th class="px-4 py-3">Category</th>
                    <th class="px-4 py-3">Description</th>
                    <th class="px-4 py-3 text-right">Amount</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse ($rows as $expense)
                    <tr>
                        <td class="px-4 py-3">{{ $expense->expense_date->format('d/m/Y') }}</td>
                        <td class="px-4 py-3">{{ $expense->project?->name }}</td>
                        <td class="px-4 py-3">{{ $expense->category->label() }}</td>
                        <td class="px-4 py-3">{{ $expense->description }}</td>
                        <td class="px-4 py-3 text-right">{{ number_format((float) $expense->amount, 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-8 text-center text-slate-500">No expenses in this period.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-app-layout>
