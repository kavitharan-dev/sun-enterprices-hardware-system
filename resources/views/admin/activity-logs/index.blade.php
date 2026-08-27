<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold text-slate-800">Activity logs</h2>
    </x-slot>

    <form method="GET" class="mb-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
        <x-searchable-select
            name="user_id"
            :options="$users->map(fn ($u) => ['value' => (string) $u->id, 'label' => $u->name, 'search' => $u->name])->values()"
            :value="(string) request('user_id')"
            empty-label="All users"
            :allow-empty="true"
            placeholder="Search user…"
        />
        <x-searchable-select
            name="module"
            :options="collect($modules)->map(fn ($m) => ['value' => $m, 'label' => $m])->values()"
            :value="(string) request('module')"
            empty-label="All modules"
            :allow-empty="true"
            placeholder="Search module…"
        />
        <input type="date" name="from" value="{{ request('from') }}" class="rounded-lg border-slate-300 text-sm">
        <input type="date" name="to" value="{{ request('to') }}" class="rounded-lg border-slate-300 text-sm">
        <button class="btn btn-dark">Filter</button>
    </form>

    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase text-slate-500">
                <tr>
                    <th class="px-4 py-3">Time</th>
                    <th class="px-4 py-3">User</th>
                    <th class="px-4 py-3">Module</th>
                    <th class="px-4 py-3">Action</th>
                    <th class="px-4 py-3">Description</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse ($logs as $log)
                    <tr>
                        <td class="px-4 py-3 whitespace-nowrap">{{ $log->created_at->format('d/m/Y H:i') }}</td>
                        <td class="px-4 py-3">{{ $log->user?->name ?? 'System' }}</td>
                        <td class="px-4 py-3">{{ $log->module }}</td>
                        <td class="px-4 py-3">{{ $log->action }}</td>
                        <td class="px-4 py-3">{{ $log->description }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-8 text-center text-slate-500">No activity yet.</td></tr>
                @endforelse
            </tbody>
        </table>
        @if ($logs->hasPages())
            <div class="border-t px-4 py-3">{{ $logs->links() }}</div>
        @endif
    </div>
</x-app-layout>
