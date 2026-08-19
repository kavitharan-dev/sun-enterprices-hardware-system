<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold text-slate-800">Dashboard</h2>
    </x-slot>

@php $currency = 'Rs.'; @endphp

<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-bold text-slate-900">Site Manager Dashboard</h1>
        <p class="mt-1 text-sm text-slate-500">Your assigned projects, material requests, and progress.</p>
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
        <x-stat-card label="Active Projects" :value="number_format($stats['active_projects'])" color="sky" />
        <x-stat-card label="Pending Material Requests" :value="number_format($stats['pending_material_requests'])" color="amber" />
        <x-stat-card label="Project Expenses" :value="$currency . number_format($stats['project_expenses'], 2)" color="emerald" />
    </div>
    <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="flex flex-wrap gap-3">
        <a href="{{ route('construction.projects.index') }}" class="btn btn-primary">My projects</a>
        <a href="{{ route('construction.material-requests.create') }}" class="btn btn-secondary">New material request</a>
        </div>
    </div>

    @isset($assignedProjects)
        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b px-5 py-3 font-semibold">Assigned projects</div>
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Project</th>
                        <th class="px-4 py-3 text-right">Budget used</th>
                        <th class="px-4 py-3 text-right">Progress</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @forelse ($assignedProjects as $project)
                        <tr>
                            <td class="px-4 py-3">
                                <a href="{{ route('construction.projects.show', $project) }}" class="font-medium text-amber-700">{{ $project->name }}</a>
                                <p class="text-xs text-slate-500">{{ $project->project_code }}</p>
                            </td>
                            <td class="px-4 py-3 text-right">{{ number_format($project->budgetUsedPercent(), 1) }}%</td>
                            <td class="px-4 py-3 text-right">{{ number_format((float) $project->progress_percentage, 1) }}%</td>
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('construction.projects.dashboard', $project) }}" class="btn btn-secondary btn-sm">Dashboard</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-4 py-6 text-center text-slate-500">No projects assigned yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endisset
</div>
</x-app-layout>
