<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold text-slate-800">Material Requests</h2>
    </x-slot>

    <form method="GET" class="mb-4 grid gap-3 sm:grid-cols-3">
        <input type="text" name="q" value="{{ request('q') }}" placeholder="Request no, project or product" class="rounded-lg border-slate-300 text-sm">
        <x-searchable-select
            name="status"
            :options="collect([
                ['value' => 'pending', 'label' => 'Pending review'],
                ['value' => 'all', 'label' => 'All statuses'],
            ])->merge(
                collect(App\Enums\MaterialRequestStatus::cases())
                    ->reject(fn ($s) => $s->value === 'pending')
                    ->map(fn ($s) => ['value' => $s->value, 'label' => $s->label()])
            )->values()"
            :value="(string) request('status', 'pending')"
            empty-label="Pending review"
            :allow-empty="false"
            placeholder="Search status…"
        />
        <button class="btn btn-dark">Filter</button>
    </form>

    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full divide-y text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase text-slate-500">
                <tr>
                    <th class="px-4 py-3">Request</th>
                    <th class="px-4 py-3">Products</th>
                    <th class="px-4 py-3">Project</th>
                    <th class="px-4 py-3">Requested by</th>
                    <th class="px-4 py-3">Date</th>
                    <th class="px-4 py-3">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse ($requests as $request)
                    <tr>
                        <td class="px-4 py-3 font-mono text-xs">
                            <a href="{{ route('store.material-requests.show', $request) }}" class="font-semibold text-amber-700 hover:underline">{{ $request->request_no }}</a>
                        </td>
                        <td class="px-4 py-3 text-xs text-slate-600">{{ $request->itemsSummary() ?: '—' }}</td>
                        <td class="px-4 py-3">{{ $request->project?->name }}</td>
                        <td class="px-4 py-3">{{ $request->requester?->name }}</td>
                        <td class="px-4 py-3">{{ $request->request_date->format('d/m/Y') }}</td>
                        <td class="px-4 py-3"><x-status-badge :status="$request->status" /></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-8 text-center text-slate-500">No material requests in this filter.</td></tr>
                @endforelse
            </tbody>
        </table>
        @if ($requests->hasPages())
            <div class="border-t px-4 py-3">{{ $requests->links() }}</div>
        @endif
    </div>
</x-app-layout>
