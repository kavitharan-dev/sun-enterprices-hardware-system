<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="text-xl font-semibold text-slate-800">Projects report</h2>
            <a href="{{ route('reports.index') }}" class="btn btn-secondary btn-sm">All reports</a>
        </div>
    </x-slot>

    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <p class="text-sm text-slate-600">Budget Rs. {{ number_format($budget, 2) }} · Received Rs. {{ number_format($received, 2) }} · Spent Rs. {{ number_format($spent, 2) }}</p>
        <a href="{{ request()->fullUrlWithQuery(['export' => 1]) }}" class="btn btn-secondary">Export CSV</a>
    </div>

    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase text-slate-500">
                <tr>
                    <th class="px-4 py-3">Project</th>
                    <th class="px-4 py-3 text-right">Budget</th>
                    <th class="px-4 py-3 text-right">Received</th>
                    <th class="px-4 py-3 text-right">Still to receive</th>
                    <th class="px-4 py-3 text-right">Spent</th>
                    <th class="px-4 py-3 text-right">Cash balance</th>
                    <th class="px-4 py-3 text-right">Progress</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse ($rows as $project)
                    <tr>
                        <td class="px-4 py-3">
                            <a href="{{ route('construction.projects.dashboard', $project) }}" class="font-medium text-amber-700">{{ $project->name }}</a>
                            <p class="text-xs text-slate-500">{{ $project->project_code }}</p>
                        </td>
                        <td class="px-4 py-3 text-right">{{ number_format((float) $project->budget, 2) }}</td>
                        <td class="px-4 py-3 text-right">{{ number_format((float) $project->received_total, 2) }}</td>
                        <td class="px-4 py-3 text-right">{{ number_format((float) $project->budget - (float) $project->received_total, 2) }}</td>
                        <td class="px-4 py-3 text-right">{{ number_format((float) $project->spent_total, 2) }}</td>
                        <td class="px-4 py-3 text-right">{{ number_format((float) $project->received_total - (float) $project->spent_total, 2) }}</td>
                        <td class="px-4 py-3 text-right">{{ number_format((float) $project->progress_percentage, 1) }}%</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-8 text-center text-slate-500">No projects.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-app-layout>
