<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <h2 class="text-xl font-semibold text-slate-800">Projects</h2>
            @can('create', App\Models\Project::class)
                <a href="{{ route('construction.projects.create') }}" class="btn btn-primary">New Project</a>
            @endcan
        </div>
    </x-slot>

    <form method="GET" class="mb-4 grid gap-3 sm:grid-cols-3">
        <input type="text" name="q" value="{{ request('q') }}" placeholder="Name, code or location" class="rounded-lg border-slate-300 text-sm">
        <x-searchable-select
            name="status"
            :options="collect(App\Enums\ProjectStatus::cases())->map(fn ($s) => ['value' => $s->value, 'label' => $s->label()])->values()"
            :value="(string) request('status')"
            empty-label="All statuses"
            :allow-empty="true"
            placeholder="Search status…"
        />
        <button class="btn btn-dark">Filter</button>
    </form>

    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full divide-y text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase text-slate-500">
                <tr>
                    <th class="px-4 py-3">Project</th>
                    <th class="px-4 py-3">Customer</th>
                    <th class="px-4 py-3">Site manager</th>
                    <th class="px-4 py-3 text-right">Budget</th>
                    <th class="px-4 py-3 text-right">Still to receive</th>
                    <th class="px-4 py-3 text-right">Progress</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse ($projects as $project)
                    <tr>
                        <td class="px-4 py-3">
                            <a href="{{ route('construction.projects.show', $project) }}" class="font-medium text-amber-700 hover:underline">{{ $project->name }}</a>
                            <p class="text-xs text-slate-500">{{ $project->project_code }} · {{ $project->location }}</p>
                        </td>
                        <td class="px-4 py-3">{{ $project->customer?->name }}</td>
                        <td class="px-4 py-3">{{ $project->siteManager?->name ?? '—' }}</td>
                        <td class="px-4 py-3 text-right">Rs. {{ number_format((float) $project->budget, 2) }}</td>
                        <td class="px-4 py-3 text-right">Rs. {{ number_format((float) $project->budget - (float) $project->received_total, 2) }}</td>
                        <td class="px-4 py-3 text-right">{{ number_format((float) $project->progress_percentage, 1) }}%</td>
                        <td class="px-4 py-3"><x-status-badge :status="$project->status" /></td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('construction.projects.dashboard', $project) }}" class="btn btn-secondary btn-sm">Dashboard</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="px-4 py-8 text-center text-slate-500">No projects yet.</td></tr>
                @endforelse
            </tbody>
        </table>
        @if ($projects->hasPages())
            <div class="border-t px-4 py-3">{{ $projects->links() }}</div>
        @endif
    </div>
</x-app-layout>
