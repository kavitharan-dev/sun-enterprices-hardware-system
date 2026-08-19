<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-xl font-semibold text-slate-800">{{ $worker->name }}</h2>
                <p class="text-sm text-slate-500">{{ $worker->worker_code }}</p>
            </div>
            <a href="{{ route('construction.workers.edit', $worker) }}" class="btn btn-dark">Edit</a>
        </div>
    </x-slot>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="rounded-xl border border-slate-200 bg-white p-5 text-sm shadow-sm space-y-2">
            <p><span class="text-slate-500">Role:</span> {{ $worker->job_role ?? '—' }}</p>
            <p><span class="text-slate-500">Phone:</span> {{ $worker->phone ?? '—' }}</p>
            <p><span class="text-slate-500">NIC:</span> {{ $worker->nic ?? '—' }}</p>
            <p><span class="text-slate-500">Daily rate:</span> Rs. {{ number_format((float) $worker->daily_rate, 2) }}</p>
            <p><x-status-badge :status="$worker->status" /></p>
        </div>
        <div class="lg:col-span-2 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b px-5 py-3 font-semibold">Assigned projects</div>
            <table class="min-w-full text-sm">
                <tbody class="divide-y">
                    @forelse ($worker->projects as $project)
                        <tr>
                            <td class="px-4 py-3">
                                <a href="{{ route('construction.projects.show', $project) }}" class="font-medium text-amber-700">{{ $project->name }}</a>
                            </td>
                            <td class="px-4 py-3">{{ $project->pivot->role_on_site }}</td>
                            <td class="px-4 py-3 text-slate-500">{{ \Illuminate\Support\Carbon::parse($project->pivot->assigned_from)->format('d/m/Y') }}</td>
                        </tr>
                    @empty
                        <tr><td class="px-4 py-8 text-center text-slate-500">Not assigned to a project yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
