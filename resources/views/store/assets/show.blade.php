<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-xl font-semibold text-slate-800">{{ $asset->displayLabel() }}</h2>
                <p class="text-sm text-slate-500">{{ $asset->type->label() }}</p>
            </div>
            <div class="flex flex-wrap gap-2">
                @can('update', $asset)
                    <a href="{{ route('store.assets.edit', $asset) }}" class="btn btn-secondary">Edit</a>
                @endcan
                <a href="{{ route('store.assets.index', ['type' => $asset->type->value]) }}" class="btn btn-secondary">Back</a>
            </div>
        </div>
    </x-slot>

    <div class="grid gap-4 lg:grid-cols-2">
        <div class="space-y-4">
            @if ($asset->activeAssignment)
                <div class="rounded-xl border border-amber-200 bg-amber-50 p-4">
                    <h3 class="font-semibold text-amber-950">Currently out</h3>
                    <dl class="mt-3 space-y-2 text-sm text-amber-950">
                        <div class="flex justify-between gap-4"><dt>Worker</dt><dd class="font-medium">{{ $asset->activeAssignment->worker->name }}</dd></div>
                        @if ($asset->activeAssignment->project)
                            <div class="flex justify-between gap-4"><dt>Site</dt><dd>{{ $asset->activeAssignment->project->name }}</dd></div>
                        @endif
                        <div class="flex justify-between gap-4"><dt>Issued</dt><dd>{{ $asset->activeAssignment->issued_at->format('d/m/Y h:i A') }}</dd></div>
                        @if ($asset->activeAssignment->purpose)
                            <div><dt class="mb-1">Purpose</dt><dd>{{ $asset->activeAssignment->purpose }}</dd></div>
                        @endif
                    </dl>
                    @can('return', $asset->activeAssignment)
                        <form method="POST" action="{{ route('store.asset-assignments.return', $asset->activeAssignment) }}" class="mt-4" onsubmit="return confirm('Mark this asset as returned?')">
                            @csrf
                            <button class="btn btn-success">Mark returned</button>
                        </form>
                    @endcan
                </div>
            @elseif ($asset->is_active)
                @can('issue', $asset)
                    <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                        <h3 class="font-semibold text-slate-800">Issue asset</h3>
                        <form method="POST" action="{{ route('store.assets.issue', $asset) }}" class="mt-4 space-y-3">
                            @csrf
                            <div>
                                <x-input-label for="worker_id" value="Worker" />
                                <select id="worker_id" name="worker_id" class="mt-1 block w-full rounded-md border-gray-300" required>
                                    <option value="">Choose worker…</option>
                                    @foreach ($workers as $worker)
                                        <option value="{{ $worker->id }}" @selected(old('worker_id') == $worker->id)>{{ $worker->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <x-input-label for="project_id" value="Site / project (optional)" />
                                <select id="project_id" name="project_id" class="mt-1 block w-full rounded-md border-gray-300">
                                    <option value="">—</option>
                                    @foreach ($projects as $project)
                                        <option value="{{ $project->id }}" @selected(old('project_id') == $project->id)>{{ $project->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <x-input-label for="issued_at" value="Issue date &amp; time" />
                                <x-text-input id="issued_at" name="issued_at" type="datetime-local" class="mt-1 block w-full" :value="old('issued_at', now()->format('Y-m-d\TH:i'))" required />
                            </div>
                            <div>
                                <x-input-label for="purpose" value="Purpose" />
                                <textarea id="purpose" name="purpose" rows="2" class="mt-1 block w-full rounded-md border-gray-300">{{ old('purpose') }}</textarea>
                            </div>
                            <div>
                                <x-input-label for="notes" value="Notes" />
                                <textarea id="notes" name="notes" rows="2" class="mt-1 block w-full rounded-md border-gray-300">{{ old('notes') }}</textarea>
                            </div>
                            <x-primary-button>Issue now</x-primary-button>
                        </form>
                    </div>
                @endcan
            @else
                <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-600">This asset is inactive.</div>
            @endif

            @if ($asset->notes)
                <div class="rounded-xl border border-slate-200 bg-white p-4 text-sm text-slate-600">{{ $asset->notes }}</div>
            @endif
        </div>

        <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-100 px-4 py-3 font-semibold text-slate-800">History</div>
            <div class="divide-y divide-slate-100">
                @forelse ($asset->assignments as $assignment)
                    <div class="px-4 py-3 text-sm">
                        <div class="flex items-center justify-between gap-2">
                            <span class="font-medium text-slate-900">{{ $assignment->worker->name }}</span>
                            @if ($assignment->isOpen())
                                <x-status-badge status="out" />
                            @else
                                <x-status-badge status="returned" />
                            @endif
                        </div>
                        @if ($assignment->project)
                            <p class="text-slate-500">{{ $assignment->project->name }}</p>
                        @endif
                        <p class="mt-1 text-slate-600">
                            Out: {{ $assignment->issued_at->format('d/m/Y h:i A') }}
                            @if ($assignment->returned_at)
                                · Back: {{ $assignment->returned_at->format('d/m/Y h:i A') }}
                            @endif
                        </p>
                        @if ($assignment->purpose)
                            <p class="text-slate-500">{{ $assignment->purpose }}</p>
                        @endif
                    </div>
                @empty
                    <p class="px-4 py-8 text-center text-slate-500">No issue history yet.</p>
                @endforelse
            </div>
        </div>
    </div>
</x-app-layout>
