<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <h2 class="text-xl font-semibold text-slate-800">My Material Requests</h2>
            <a href="{{ route('construction.material-requests.create') }}" class="btn btn-primary">New Request</a>
        </div>
    </x-slot>

    <form method="GET" class="mb-4 grid gap-3 sm:grid-cols-3">
        <input type="text" name="q" value="{{ request('q') }}" placeholder="Request no, project or product" class="rounded-lg border-slate-300 text-sm">
        <select name="status" class="rounded-lg border-slate-300 text-sm">
            <option value="">All statuses</option>
            @foreach (App\Enums\MaterialRequestStatus::cases() as $status)
                <option value="{{ $status->value }}" @selected(request('status') === $status->value)>{{ $status->label() }}</option>
            @endforeach
        </select>
        <button class="btn btn-dark">Filter</button>
    </form>

    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full divide-y text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase text-slate-500">
                <tr>
                    <th class="px-4 py-3">Request</th>
                    <th class="px-4 py-3">Products</th>
                    <th class="px-4 py-3">Project</th>
                    <th class="px-4 py-3">Date</th>
                    <th class="px-4 py-3">Items</th>
                    <th class="px-4 py-3">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse ($requests as $request)
                    <tr>
                        <td class="px-4 py-3 font-mono text-xs">
                            <a href="{{ route('construction.material-requests.show', $request) }}" class="font-semibold text-amber-700 hover:underline">{{ $request->request_no }}</a>
                        </td>
                        <td class="px-4 py-3 text-xs text-slate-600">{{ $request->itemsSummary() ?: '—' }}</td>
                        <td class="px-4 py-3">{{ $request->project?->name }}</td>
                        <td class="px-4 py-3">{{ $request->request_date->format('d/m/Y') }}</td>
                        <td class="px-4 py-3">{{ $request->items_count }}</td>
                        <td class="px-4 py-3"><x-status-badge :status="$request->status" /></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-8 text-center text-slate-500">No material requests yet.</td></tr>
                @endforelse
            </tbody>
        </table>
        @if ($requests->hasPages())
            <div class="border-t px-4 py-3">{{ $requests->links() }}</div>
        @endif
    </div>
</x-app-layout>
