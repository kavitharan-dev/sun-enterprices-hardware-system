<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="text-xl font-semibold text-slate-800">Material issues</h2>
            <a href="{{ route('reports.index') }}" class="btn btn-secondary btn-sm">All reports</a>
        </div>
    </x-slot>

    @include('reports.partials.date-filter')

    <p class="mb-4 text-sm text-slate-600">Total cost Rs. {{ number_format($total, 2) }}</p>

    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase text-slate-500">
                <tr>
                    <th class="px-4 py-3">Date</th>
                    <th class="px-4 py-3">Issue</th>
                    <th class="px-4 py-3">Products</th>
                    <th class="px-4 py-3">Project</th>
                    <th class="px-4 py-3 text-right">Cost</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse ($rows as $issue)
                    <tr>
                        <td class="px-4 py-3">{{ $issue->issue_date->format('d/m/Y') }}</td>
                        <td class="px-4 py-3">{{ $issue->issue_no }}</td>
                        <td class="px-4 py-3 text-xs text-slate-600">{{ $issue->itemsSummary() ?: '—' }}</td>
                        <td class="px-4 py-3">{{ $issue->project?->name }}</td>
                        <td class="px-4 py-3 text-right">{{ number_format((float) $issue->total_cost, 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-8 text-center text-slate-500">No issues in this period.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-app-layout>
