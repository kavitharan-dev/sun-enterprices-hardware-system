<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold text-slate-800">SMS Logs</h2>
    </x-slot>

    <form method="GET" class="mb-4 flex flex-wrap items-center gap-3">
        <x-searchable-select
            name="status"
            :options="collect(['queued','sending','sent','delivered','failed','skipped'])->map(fn ($s) => ['value' => $s, 'label' => ucfirst($s)])->values()"
            :value="(string) request('status')"
            empty-label="All statuses"
            :allow-empty="true"
            placeholder="Search status…"
            class="min-w-48"
        />
        <button class="btn btn-dark">Filter</button>
    </form>

    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full divide-y text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase text-slate-500">
                <tr>
                    <th class="px-4 py-3">Time</th>
                    <th class="px-4 py-3">Recipient</th>
                    <th class="px-4 py-3">Event</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3">Provider ID</th>
                    <th class="px-4 py-3">Error</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse ($logs as $log)
                    <tr>
                        <td class="px-4 py-3 whitespace-nowrap">{{ $log->created_at->format('d/m/Y H:i') }}</td>
                        <td class="px-4 py-3 font-mono text-xs">{{ $log->recipient }}</td>
                        <td class="px-4 py-3">{{ str_replace('_', ' ', $log->event_type) }}</td>
                        <td class="px-4 py-3"><x-status-badge :status="$log->status" /></td>
                        <td class="px-4 py-3 font-mono text-xs">{{ $log->provider_message_id ?? '—' }}</td>
                        <td class="px-4 py-3 text-rose-600">{{ $log->error_message }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-8 text-center text-slate-500">No SMS logs yet.</td></tr>
                @endforelse
            </tbody>
        </table>
        @if ($logs->hasPages())
            <div class="border-t px-4 py-3">{{ $logs->links() }}</div>
        @endif
    </div>
</x-app-layout>
