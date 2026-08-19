<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-xl font-semibold text-slate-800">Weekly wages</h2>
                <p class="text-sm text-slate-500">{{ $weekStart->format('d/m/Y') }} — {{ $weekEnd->format('d/m/Y') }} · paid on Saturday</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('construction.payroll.index', ['week' => $previousWeek]) }}" class="btn btn-secondary">&larr; Previous week</a>
                <a href="{{ route('construction.payroll.index') }}" class="btn btn-dark">This week</a>
                <a href="{{ route('construction.payroll.index', ['week' => $nextWeek]) }}" class="btn btn-secondary">Next week &rarr;</a>
            </div>
        </div>
    </x-slot>

    @php
        $totalSalary = 0;
        $totalPaid = 0;
        $totalRemaining = 0;
        $totalDebt = 0;

        foreach ($rows as $row) {
            $totalSalary += $row['week'] ? (float) $row['week']->weekly_salary : (float) $row['worker']->weekly_salary;
            $totalPaid += $row['week']?->totalPaid() ?? 0;
            $totalRemaining += $row['week']?->remainingSalary() ?? (float) $row['worker']->weekly_salary;
            $totalDebt += $row['debt'];
        }
    @endphp

    <div class="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <p class="text-xs uppercase text-slate-500">Wage bill this week</p>
            <p class="mt-1 text-2xl font-semibold text-slate-800">Rs. {{ number_format($totalSalary, 2) }}</p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <p class="text-xs uppercase text-slate-500">Paid so far</p>
            <p class="mt-1 text-2xl font-semibold text-slate-800">Rs. {{ number_format($totalPaid, 2) }}</p>
        </div>
        <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 shadow-sm">
            <p class="text-xs uppercase text-amber-700">Still to pay</p>
            <p class="mt-1 text-2xl font-semibold text-amber-800">Rs. {{ number_format($totalRemaining, 2) }}</p>
        </div>
        <div class="rounded-xl border p-4 shadow-sm {{ $totalDebt > 0 ? 'border-rose-200 bg-rose-50' : 'border-slate-200 bg-white' }}">
            <p class="text-xs uppercase {{ $totalDebt > 0 ? 'text-rose-600' : 'text-slate-500' }}">Worker debt outstanding</p>
            <p class="mt-1 text-2xl font-semibold {{ $totalDebt > 0 ? 'text-rose-700' : 'text-slate-800' }}">Rs. {{ number_format($totalDebt, 2) }}</p>
        </div>
    </div>

    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full divide-y text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase text-slate-500">
                <tr>
                    <th class="px-4 py-3">Worker</th>
                    <th class="px-4 py-3 text-right">Weekly salary</th>
                    <th class="px-4 py-3 text-right">Advances</th>
                    <th class="px-4 py-3 text-right">Debt</th>
                    <th class="px-4 py-3 text-right">Remaining</th>
                    <th class="px-4 py-3">Week</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse ($rows as $row)
                    @php
                        $worker = $row['worker'];
                        $week = $row['week'];
                        $advances = $week ? $week->advancesDeducted() + $week->advancesToDebt() : 0;
                        $remaining = $week?->remainingSalary() ?? (float) $worker->weekly_salary;
                    @endphp
                    <tr>
                        <td class="px-4 py-3">
                            <a href="{{ route('construction.workers.show', $worker) }}" class="font-medium text-slate-900 hover:text-amber-700">{{ $worker->name }}</a>
                            <p class="text-xs text-slate-500">{{ $worker->job_role ?? $worker->worker_code }}</p>
                        </td>
                        <td class="px-4 py-3 text-right">Rs. {{ number_format($week ? (float) $week->weekly_salary : (float) $worker->weekly_salary, 2) }}</td>
                        <td class="px-4 py-3 text-right">{{ $advances > 0 ? 'Rs. '.number_format($advances, 2) : '—' }}</td>
                        <td class="px-4 py-3 text-right {{ $row['debt'] > 0 ? 'font-semibold text-rose-700' : 'text-slate-400' }}">
                            {{ $row['debt'] > 0 ? 'Rs. '.number_format($row['debt'], 2) : '—' }}
                        </td>
                        <td class="px-4 py-3 text-right font-semibold {{ $remaining > 0 ? 'text-amber-700' : 'text-emerald-700' }}">Rs. {{ number_format($remaining, 2) }}</td>
                        <td class="px-4 py-3">
                            @if (! $week)
                                <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-600">Not started</span>
                            @elseif ($week->isSettled())
                                <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-semibold text-emerald-700">Settled</span>
                            @else
                                <span class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-800">Open</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('construction.workers.payroll', ['worker' => $worker, 'week' => $weekStart->toDateString()]) }}" class="btn btn-secondary btn-sm">Open</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-8 text-center text-slate-500">No active workers yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-app-layout>
