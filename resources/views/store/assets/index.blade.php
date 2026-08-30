<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <h2 class="text-xl font-semibold text-slate-800">Tools &amp; Vehicles</h2>
            @can('create', App\Models\StoreAsset::class)
                <a href="{{ route('store.assets.create', request()->only('type')) }}" class="btn btn-primary">Register asset</a>
            @endcan
        </div>
    </x-slot>

    <div class="space-y-4">
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('store.assets.index') }}" class="btn btn-sm {{ ! request('type') ? 'btn-primary' : 'btn-secondary' }}">All</a>
            <a href="{{ route('store.assets.index', ['type' => 'tool']) }}" class="btn btn-sm {{ request('type') === 'tool' ? 'btn-primary' : 'btn-secondary' }}">Tools</a>
            <a href="{{ route('store.assets.index', ['type' => 'vehicle']) }}" class="btn btn-sm {{ request('type') === 'vehicle' ? 'btn-primary' : 'btn-secondary' }}">Vehicles</a>
            <a href="{{ route('store.assets.index', ['status' => 'out']) }}" class="btn btn-sm {{ request('status') === 'out' ? 'btn-primary' : 'btn-secondary' }}">Out now</a>
            <a href="{{ route('store.assets.index', ['status' => 'available']) }}" class="btn btn-sm {{ request('status') === 'available' ? 'btn-primary' : 'btn-secondary' }}">Available</a>
        </div>

        <form method="GET" class="flex flex-wrap gap-2">
            @foreach (request()->except('q', 'page') as $key => $value)
                <input type="hidden" name="{{ $key }}" value="{{ $value }}">
            @endforeach
            <input type="search" name="q" value="{{ request('q') }}" placeholder="Search name, plate, code…" class="rounded-lg border-slate-300 text-sm">
            <button class="btn btn-secondary btn-sm">Search</button>
        </form>

        @if ($outNow->isNotEmpty())
            <div class="rounded-xl border border-amber-200 bg-amber-50 p-4">
                <h3 class="font-semibold text-amber-950">Currently out</h3>
                <ul class="mt-2 space-y-2 text-sm text-amber-950">
                    @foreach ($outNow as $assignment)
                        <li>
                            <a href="{{ route('store.assets.show', $assignment->asset) }}" class="font-medium hover:underline">
                                {{ $assignment->asset->displayLabel() }}
                            </a>
                            — {{ $assignment->worker->name }}
                            @if ($assignment->project)
                                · {{ $assignment->project->name }}
                            @endif
                            · since {{ $assignment->issued_at->format('d/m/Y h:i A') }}
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Asset</th>
                        <th class="px-4 py-3">Type</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">With</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($assets as $asset)
                        <tr>
                            <td class="px-4 py-3">
                                <div class="font-medium">{{ $asset->name }}</div>
                                @if ($asset->identifier || $asset->vehicle_kind)
                                    <div class="text-xs text-slate-500">{{ trim($asset->identifier.' '.$asset->vehicle_kind) }}</div>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-slate-600">{{ $asset->type->label() }}</td>
                            <td class="px-4 py-3">
                                @if ($asset->activeAssignment)
                                    <x-status-badge status="out" />
                                @elseif ($asset->is_active)
                                    <x-status-badge status="available" />
                                @else
                                    <x-status-badge status="inactive" />
                                @endif
                            </td>
                            <td class="px-4 py-3 text-slate-600">
                                @if ($asset->activeAssignment)
                                    {{ $asset->activeAssignment->worker->name }}
                                    @if ($asset->activeAssignment->project)
                                        <span class="block text-xs text-slate-500">{{ $asset->activeAssignment->project->name }}</span>
                                    @endif
                                @else
                                    —
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('store.assets.show', $asset) }}" class="btn btn-secondary btn-sm">Open</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-8 text-center text-slate-500">No assets registered yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
            @if ($assets->hasPages())
                <div class="border-t px-4 py-3">{{ $assets->links() }}</div>
            @endif
        </div>
    </div>
</x-app-layout>
