<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <h2 class="text-xl font-semibold text-slate-800">Material Issues</h2>
            <a href="{{ route('store.material-requests.index', ['status' => 'approved']) }}" class="btn btn-secondary btn-sm">Approved requests</a>
        </div>
    </x-slot>

    <form method="GET" class="mb-4 flex gap-3">
        <input type="text" name="q" value="{{ request('q') }}" placeholder="Issue no, product, request or project" class="w-full max-w-sm rounded-lg border-slate-300 text-sm">
        <button class="btn btn-dark">Search</button>
    </form>

    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full divide-y text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase text-slate-500">
                <tr>
                    <th class="px-4 py-3">Issue</th>
                    <th class="px-4 py-3">Products</th>
                    <th class="px-4 py-3">Project</th>
                    <th class="px-4 py-3">Request</th>
                    <th class="px-4 py-3">Date</th>
                    <th class="px-4 py-3 text-right">Cost</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse ($issues as $issue)
                    <tr>
                        <td class="px-4 py-3 font-mono text-xs">
                            <a href="{{ route('store.material-issues.show', $issue) }}" class="font-semibold text-amber-700 hover:underline">{{ $issue->issue_no }}</a>
                        </td>
                        <td class="px-4 py-3 text-xs text-slate-600">{{ $issue->itemsSummary() ?: '—' }}</td>
                        <td class="px-4 py-3">{{ $issue->project?->name }}</td>
                        <td class="px-4 py-3">{{ $issue->materialRequest?->request_no ?? '—' }}</td>
                        <td class="px-4 py-3">{{ $issue->issue_date->format('d/m/Y') }}</td>
                        <td class="px-4 py-3 text-right">Rs. {{ number_format((float) $issue->total_cost, 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-8 text-center text-slate-500">No material issues yet.</td></tr>
                @endforelse
            </tbody>
        </table>
        @if ($issues->hasPages())
            <div class="border-t px-4 py-3">{{ $issues->links() }}</div>
        @endif
    </div>
</x-app-layout>
