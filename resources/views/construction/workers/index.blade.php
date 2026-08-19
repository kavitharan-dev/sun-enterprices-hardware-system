<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <h2 class="text-xl font-semibold text-slate-800">Workers</h2>
            <a href="{{ route('construction.workers.create') }}" class="btn btn-primary">Add Worker</a>
        </div>
    </x-slot>

    <form method="GET" class="mb-4 grid gap-3 sm:grid-cols-3">
        <input type="text" name="q" value="{{ request('q') }}" placeholder="Name, code, phone or NIC" class="rounded-lg border-slate-300 text-sm">
        <select name="status" class="rounded-lg border-slate-300 text-sm">
            <option value="">All statuses</option>
            <option value="active" @selected(request('status') === 'active')>Active</option>
            <option value="inactive" @selected(request('status') === 'inactive')>Inactive</option>
        </select>
        <button class="btn btn-dark">Filter</button>
    </form>

    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full divide-y text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase text-slate-500">
                <tr>
                    <th class="px-4 py-3">Worker</th>
                    <th class="px-4 py-3">Role</th>
                    <th class="px-4 py-3">Phone</th>
                    <th class="px-4 py-3 text-right">Weekly salary</th>
                    <th class="px-4 py-3 text-right">Debt</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse ($workers as $worker)
                    <tr>
                        <td class="px-4 py-3">
                            <a href="{{ route('construction.workers.show', $worker) }}" class="font-medium text-slate-900 hover:text-amber-700">{{ $worker->name }}</a>
                            <p class="text-xs text-slate-500">{{ $worker->worker_code }}</p>
                        </td>
                        <td class="px-4 py-3">{{ $worker->job_role ?? '—' }}</td>
                        <td class="px-4 py-3">{{ $worker->phone ?? '—' }}</td>
                        <td class="px-4 py-3 text-right">Rs. {{ number_format((float) $worker->weekly_salary, 2) }}</td>
                        @php($debt = $worker->debtBalance())
                        <td class="px-4 py-3 text-right {{ $debt > 0 ? 'font-semibold text-rose-700' : 'text-slate-400' }}">
                            {{ $debt > 0 ? 'Rs. '.number_format($debt, 2) : '—' }}
                        </td>
                        <td class="px-4 py-3"><x-status-badge :status="$worker->status" /></td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('construction.workers.payroll', $worker) }}" class="btn btn-secondary btn-sm">Salary</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-8 text-center text-slate-500">No workers yet.</td></tr>
                @endforelse
            </tbody>
        </table>
        @if ($workers->hasPages())
            <div class="border-t px-4 py-3">{{ $workers->links() }}</div>
        @endif
    </div>
</x-app-layout>
