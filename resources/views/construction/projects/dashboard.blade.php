<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h2 class="text-xl font-semibold text-slate-800">{{ $project->name }} dashboard</h2>
                <p class="text-sm text-slate-500">{{ $project->project_code }} · {{ $project->location }}</p>
            </div>
            <div class="flex gap-2">
                <x-status-badge :status="$project->status" />
                <a href="{{ route('construction.projects.show', $project) }}" class="btn btn-secondary">Project details</a>
            </div>
        </div>
    </x-slot>

    <div class="space-y-6">
        @if ((float) $project->budget > 0 && $usedPercent >= 80)
            <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                This project has used {{ number_format($usedPercent, 1) }}% of its budget.
            </div>
        @endif

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
            <div class="rounded-xl border border-slate-200 bg-white p-4 text-sm shadow-sm">
                <p class="text-slate-500">Budget</p>
                <p class="text-lg font-semibold">Rs. {{ number_format((float) $project->budget, 2) }}</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-4 text-sm shadow-sm">
                <p class="text-slate-500">Spent</p>
                <p class="text-lg font-semibold {{ $remaining < 0 ? 'text-rose-600' : '' }}">Rs. {{ number_format($spent, 2) }}</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-4 text-sm shadow-sm">
                <p class="text-slate-500">Remaining</p>
                <p class="text-lg font-semibold">Rs. {{ number_format($remaining, 2) }}</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-4 text-sm shadow-sm">
                <p class="text-slate-500">Budget used</p>
                <p class="text-lg font-semibold">{{ number_format($usedPercent, 1) }}%</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-4 text-sm shadow-sm">
                <p class="text-slate-500">Work progress</p>
                <p class="text-lg font-semibold">{{ number_format((float) $project->progress_percentage, 1) }}%</p>
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
            <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b px-5 py-3 font-semibold">Spend by category</div>
                <table class="min-w-full text-sm">
                    <tbody class="divide-y">
                        @forelse ($spendByCategory as $label => $total)
                            <tr>
                                <td class="px-4 py-3">{{ $label }}</td>
                                <td class="px-4 py-3 text-right font-medium">Rs. {{ number_format($total, 2) }}</td>
                            </tr>
                        @empty
                            <tr><td class="px-4 py-6 text-center text-slate-500">No expenses yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b px-5 py-3 font-semibold">Materials received</div>
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase text-slate-500">
                        <tr>
                            <th class="px-4 py-2">Product</th>
                            <th class="px-4 py-2 text-right">Qty</th>
                            <th class="px-4 py-2 text-right">Cost</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @forelse ($materialsReceived as $row)
                            <tr>
                                <td class="px-4 py-3">{{ $row->product?->name ?? 'Product #'.$row->product_id }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format((float) $row->quantity, 3) }} {{ $row->product?->unit?->symbol }}</td>
                                <td class="px-4 py-3 text-right">Rs. {{ number_format((float) $row->cost, 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="px-4 py-6 text-center text-slate-500">No materials issued to this project yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
            <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b px-5 py-3 font-semibold">Recent progress</div>
                <table class="min-w-full text-sm">
                    <tbody class="divide-y">
                        @forelse ($recentProgress as $progress)
                            <tr>
                                <td class="px-4 py-3 whitespace-nowrap">{{ $progress->progress_date->format('d/m/Y') }}</td>
                                <td class="px-4 py-3">
                                    <p>{{ $progress->work_completed }}</p>
                                    <p class="text-xs text-slate-500">{{ $progress->workers_present }} workers · {{ $progress->recorder?->name }}</p>
                                </td>
                                <td class="px-4 py-3 text-right">{{ $progress->progress_percentage !== null ? number_format((float) $progress->progress_percentage, 1).'%' : '—' }}</td>
                            </tr>
                        @empty
                            <tr><td class="px-4 py-6 text-center text-slate-500">No progress logged yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b px-5 py-3 font-semibold">Recent material issues</div>
                <table class="min-w-full text-sm">
                    <tbody class="divide-y">
                        @forelse ($recentIssues as $issue)
                            <tr>
                                <td class="px-4 py-3">
                                    <p class="font-medium">{{ $issue->issue_no }}</p>
                                    <p class="text-xs text-slate-500">{{ $issue->issue_date->format('d/m/Y') }} · {{ $issue->items->count() }} lines</p>
                                </td>
                                <td class="px-4 py-3 text-right font-medium">Rs. {{ number_format((float) $issue->total_cost, 2) }}</td>
                            </tr>
                        @empty
                            <tr><td class="px-4 py-6 text-center text-slate-500">No issues yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
