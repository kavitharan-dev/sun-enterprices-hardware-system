<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h2 class="text-xl font-semibold text-slate-800">{{ $project->name }}</h2>
                <p class="text-sm text-slate-500">{{ $project->project_code }} · {{ $project->location }}</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <x-status-badge :status="$project->status" />
                <a href="{{ route('construction.projects.dashboard', $project) }}" class="btn btn-secondary">Dashboard</a>
                @can('update', $project)
                    <a href="{{ route('construction.projects.edit', $project) }}" class="btn btn-dark">Edit</a>
                @endcan
            </div>
        </div>
    </x-slot>

    <div class="space-y-6">
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-6">
            <div class="rounded-xl border border-slate-200 bg-white p-4 text-sm shadow-sm">
                <p class="text-slate-500">Customer</p>
                <p class="font-semibold">{{ $project->customer?->name }}</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-4 text-sm shadow-sm">
                <p class="text-slate-500">Site manager</p>
                <p class="font-semibold">{{ $project->siteManager?->name ?? 'Unassigned' }}</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-4 text-sm shadow-sm">
                <p class="text-slate-500">Budget</p>
                <p class="font-semibold">Rs. {{ number_format((float) $project->budget, 2) }}</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-4 text-sm shadow-sm">
                <p class="text-slate-500">Spent</p>
                <p class="font-semibold {{ $spent > (float) $project->budget && $project->budget > 0 ? 'text-rose-600' : '' }}">Rs. {{ number_format($spent, 2) }}</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-4 text-sm shadow-sm">
                <p class="text-slate-500">Remaining</p>
                <p class="font-semibold">Rs. {{ number_format($project->remainingBudget(), 2) }}</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-4 text-sm shadow-sm">
                <p class="text-slate-500">Progress</p>
                <p class="font-semibold">{{ number_format((float) $project->progress_percentage, 1) }}%</p>
                <p class="mt-1 text-xs text-slate-500">{{ $project->start_date->format('d/m/Y') }}@if($project->expected_end_date) — {{ $project->expected_end_date->format('d/m/Y') }}@endif</p>
            </div>
        </div>

        @if ($project->description)
            <p class="text-sm text-slate-600">{{ $project->description }}</p>
        @endif

        <div class="grid gap-6 lg:grid-cols-2">
            <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b px-5 py-3 font-semibold">Workers on site</div>
                <table class="min-w-full text-sm">
                    <tbody class="divide-y">
                        @forelse ($project->workers as $worker)
                            <tr>
                                <td class="px-4 py-3">
                                    <p class="font-medium">{{ $worker->name }}</p>
                                    <p class="text-xs text-slate-500">{{ $worker->pivot->role_on_site ?: $worker->job_role }}</p>
                                </td>
                                <td class="px-4 py-3 text-xs text-slate-500">{{ \Illuminate\Support\Carbon::parse($worker->pivot->assigned_from)->format('d/m/Y') }}</td>
                                @can('assignWorker', $project)
                                    <td class="px-4 py-3 text-right">
                                        <form method="POST" action="{{ route('construction.projects.workers.destroy', [$project, $worker->pivot->id]) }}" onsubmit="return confirm('Remove this worker from the project?')">
                                            @csrf @method('DELETE')
                                            <button class="btn btn-danger-outline btn-sm">Remove</button>
                                        </form>
                                    </td>
                                @endcan
                            </tr>
                        @empty
                            <tr><td class="px-4 py-6 text-center text-slate-500">No workers assigned.</td></tr>
                        @endforelse
                    </tbody>
                </table>
                @can('assignWorker', $project)
                    <form method="POST" action="{{ route('construction.projects.workers.store', $project) }}" class="grid gap-3 border-t p-4 sm:grid-cols-2">
                        @csrf
                        <select name="worker_id" class="rounded-md border-gray-300 text-sm" required>
                            <option value="">Select worker</option>
                            @foreach ($availableWorkers as $worker)
                                <option value="{{ $worker->id }}">{{ $worker->name }} ({{ $worker->job_role }})</option>
                            @endforeach
                        </select>
                        <input type="text" name="role_on_site" placeholder="Role on site" class="rounded-md border-gray-300 text-sm">
                        <input type="date" name="assigned_from" value="{{ now()->toDateString() }}" class="rounded-md border-gray-300 text-sm" required>
                        <input type="date" name="assigned_to" class="rounded-md border-gray-300 text-sm">
                        <button class="btn btn-primary sm:col-span-2">Assign worker</button>
                    </form>
                @endcan
            </div>

            <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                <div class="flex items-center justify-between border-b px-5 py-3">
                    <h3 class="font-semibold">Material requests</h3>
                    @can('create', App\Models\MaterialRequest::class)
                        <a href="{{ route('construction.material-requests.create', ['project_id' => $project->id]) }}" class="btn btn-secondary btn-sm">New request</a>
                    @endcan
                </div>
                <table class="min-w-full text-sm">
                    <tbody class="divide-y">
                        @forelse ($project->materialRequests as $request)
                            <tr>
                                <td class="px-4 py-3">
                                    <a href="{{ route('construction.material-requests.show', $request) }}" class="font-medium text-amber-700">{{ $request->request_no }}</a>
                                    @if ($request->itemsSummary() !== '')
                                        <p class="mt-0.5 text-xs text-slate-600">{{ $request->itemsSummary() }}</p>
                                    @endif
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">{{ $request->request_date->format('d/m/Y') }}</td>
                                <td class="px-4 py-3"><x-status-badge :status="$request->status" /></td>
                            </tr>
                        @empty
                            <tr><td class="px-4 py-6 text-center text-slate-500">No material requests yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b px-5 py-3 font-semibold">Recent expenses</div>
            <table class="min-w-full text-sm">
                <tbody class="divide-y">
                    @forelse ($project->expenses as $expense)
                        <tr>
                            <td class="px-4 py-3">{{ $expense->expense_date->format('d/m/Y') }}</td>
                            <td class="px-4 py-3">{{ $expense->category->label() }}</td>
                            <td class="px-4 py-3">
                                <p>{{ $expense->description }}</p>
                                @if ($expense->reference instanceof \App\Models\MaterialIssue && $expense->reference->itemsSummary() !== '')
                                    <p class="mt-0.5 text-xs text-slate-600">{{ $expense->reference->itemsSummary() }}</p>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right font-medium">Rs. {{ number_format((float) $expense->amount, 2) }}</td>
                            <td class="px-4 py-3 text-right">
                                @if (! $expense->isAutomatic())
                                    @can('manageExpenses', $project)
                                        <form method="POST" action="{{ route('construction.projects.expenses.destroy', [$project, $expense]) }}" onsubmit="return confirm('Delete this expense?')">
                                            @csrf @method('DELETE')
                                            <button class="btn btn-danger-outline btn-sm">Delete</button>
                                        </form>
                                    @endcan
                                @else
                                    <span class="text-xs text-slate-400">From issue</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td class="px-4 py-6 text-center text-slate-500">No expenses yet. Material issues post here automatically.</td></tr>
                    @endforelse
                </tbody>
            </table>
            @can('manageExpenses', $project)
                <form method="POST" action="{{ route('construction.projects.expenses.store', $project) }}" class="grid gap-3 border-t p-4 sm:grid-cols-2 lg:grid-cols-4">
                    @csrf
                    <select name="category" class="rounded-md border-gray-300 text-sm" required>
                        <option value="">Category</option>
                        @foreach ($manualCategories as $category)
                            <option value="{{ $category->value }}" @selected(old('category') === $category->value)>{{ $category->label() }}</option>
                        @endforeach
                    </select>
                    <input type="number" step="0.01" min="0.01" name="amount" value="{{ old('amount') }}" placeholder="Amount" class="rounded-md border-gray-300 text-sm" required>
                    <input type="date" name="expense_date" value="{{ old('expense_date', now()->toDateString()) }}" class="rounded-md border-gray-300 text-sm" required>
                    <input type="text" name="description" value="{{ old('description') }}" placeholder="Description" class="rounded-md border-gray-300 text-sm lg:col-span-3" required>
                    <button class="btn btn-primary">Add expense</button>
                </form>
            @endcan
        </div>

        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b px-5 py-3 font-semibold">Daily progress</div>
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase text-slate-500">
                    <tr>
                        <th class="px-4 py-2">Date</th>
                        <th class="px-4 py-2">Work completed</th>
                        <th class="px-4 py-2 text-right">Workers</th>
                        <th class="px-4 py-2 text-right">Progress</th>
                        <th class="px-4 py-2"></th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @forelse ($project->dailyProgress as $progress)
                        <tr>
                            <td class="px-4 py-3 whitespace-nowrap">{{ $progress->progress_date->format('d/m/Y') }}</td>
                            <td class="px-4 py-3">
                                <p>{{ $progress->work_completed }}</p>
                                @if ($progress->notes)
                                    <p class="text-xs text-slate-500">{{ $progress->notes }}</p>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right">{{ $progress->workers_present }}</td>
                            <td class="px-4 py-3 text-right">{{ $progress->progress_percentage !== null ? number_format((float) $progress->progress_percentage, 1).'%' : '—' }}</td>
                            <td class="px-4 py-3 text-right">
                                @can('recordProgress', $project)
                                    <form method="POST" action="{{ route('construction.projects.progress.destroy', [$project, $progress]) }}" onsubmit="return confirm('Delete this progress entry?')">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-danger-outline btn-sm">Delete</button>
                                    </form>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-6 text-center text-slate-500">No daily progress logged yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
            @can('recordProgress', $project)
                <form method="POST" action="{{ route('construction.projects.progress.store', $project) }}" class="grid gap-3 border-t p-4 sm:grid-cols-2 lg:grid-cols-4">
                    @csrf
                    <input type="date" name="progress_date" value="{{ old('progress_date', now()->toDateString()) }}" class="rounded-md border-gray-300 text-sm" required>
                    <input type="number" min="0" name="workers_present" value="{{ old('workers_present', 0) }}" placeholder="Workers present" class="rounded-md border-gray-300 text-sm" required>
                    <input type="number" step="0.01" min="0" max="100" name="progress_percentage" value="{{ old('progress_percentage') }}" placeholder="Progress %" class="rounded-md border-gray-300 text-sm">
                    <textarea name="work_completed" rows="2" placeholder="Work completed" class="rounded-md border-gray-300 text-sm sm:col-span-2 lg:col-span-4" required>{{ old('work_completed') }}</textarea>
                    <textarea name="notes" rows="2" placeholder="Notes (optional)" class="rounded-md border-gray-300 text-sm sm:col-span-2 lg:col-span-3">{{ old('notes') }}</textarea>
                    <button class="btn btn-primary">Log progress</button>
                </form>
            @endcan
        </div>
    </div>
</x-app-layout>
