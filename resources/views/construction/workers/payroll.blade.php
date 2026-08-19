<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-xl font-semibold text-slate-800">{{ $worker->name }} — weekly salary</h2>
                <p class="text-sm text-slate-500">{{ $worker->worker_code }} · {{ $week->label() }} · paid every Saturday</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('construction.workers.payroll', ['worker' => $worker, 'week' => $previousWeek]) }}" class="btn btn-secondary">&larr; Previous week</a>
                <a href="{{ route('construction.workers.payroll', ['worker' => $worker, 'week' => $nextWeek]) }}" class="btn btn-secondary">Next week &rarr;</a>
                <a href="{{ route('construction.workers.show', $worker) }}" class="btn btn-dark">Worker</a>
            </div>
        </div>
    </x-slot>

    @php
        $advancesDeducted = $week->advancesDeducted();
        $advancesToDebt = $week->advancesToDebt();
        $settled = $week->settlementsPaid();
        $remaining = $week->remainingSalary();
    @endphp

    <div class="space-y-6">
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-xs uppercase text-slate-500">Weekly salary</p>
                <p class="mt-1 text-2xl font-semibold text-slate-800">Rs. {{ number_format((float) $week->weekly_salary, 2) }}</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-xs uppercase text-slate-500">Total paid this week</p>
                <p class="mt-1 text-2xl font-semibold text-slate-800">Rs. {{ number_format($week->totalPaid(), 2) }}</p>
                <p class="mt-1 text-xs text-slate-500">Advances plus Saturday payout</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-xs uppercase text-slate-500">Remaining salary</p>
                <p class="mt-1 text-2xl font-semibold {{ $remaining > 0 ? 'text-amber-700' : 'text-emerald-700' }}">Rs. {{ number_format($remaining, 2) }}</p>
                <p class="mt-1 text-xs text-slate-500">{{ $week->isSettled() ? 'Week settled' : 'Still to hand over' }}</p>
            </div>
            <div class="rounded-xl border p-4 shadow-sm {{ $debtBalance > 0 ? 'border-rose-200 bg-rose-50' : 'border-slate-200 bg-white' }}">
                <p class="text-xs uppercase {{ $debtBalance > 0 ? 'text-rose-600' : 'text-slate-500' }}">Worker debt</p>
                <p class="mt-1 text-2xl font-semibold {{ $debtBalance > 0 ? 'text-rose-700' : 'text-slate-800' }}">Rs. {{ number_format($debtBalance, 2) }}</p>
                <p class="mt-1 text-xs {{ $debtBalance > 0 ? 'text-rose-600' : 'text-slate-500' }}">Carried forward from earlier weeks</p>
            </div>
        </div>

        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="font-semibold text-slate-800">How this week adds up</p>
            <div class="mt-3 max-w-md space-y-1 text-sm">
                <div class="flex justify-between"><span class="text-slate-500">Weekly salary</span><span>Rs. {{ number_format((float) $week->weekly_salary, 2) }}</span></div>
                <div class="flex justify-between"><span class="text-slate-500">Less advances deducted from this week</span><span class="text-rose-700">− Rs. {{ number_format($advancesDeducted, 2) }}</span></div>
                <div class="flex justify-between"><span class="text-slate-500">Less old debt recovered</span><span class="text-rose-700">− Rs. {{ number_format((float) $week->debt_deducted, 2) }}</span></div>
                <div class="flex justify-between border-t pt-1 font-semibold"><span>Payable on Saturday</span><span>Rs. {{ number_format($week->netPayable(), 2) }}</span></div>
                <div class="flex justify-between"><span class="text-slate-500">Already paid on Saturday</span><span>Rs. {{ number_format($settled, 2) }}</span></div>
                <div class="flex justify-between border-t pt-1 font-semibold"><span>Remaining salary</span><span>Rs. {{ number_format($remaining, 2) }}</span></div>
                @if ($advancesToDebt > 0)
                    <p class="mt-2 rounded-md bg-rose-50 px-3 py-2 text-xs text-rose-700">
                        Rs. {{ number_format($advancesToDebt, 2) }} was taken this week without deducting it, so it was added to worker debt.
                    </p>
                @endif
            </div>
        </div>

        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="flex items-center justify-between border-b px-5 py-3">
                <p class="font-semibold text-slate-800">Work sheet</p>
                <p class="text-sm text-slate-500">{{ $week->workDays->count() }} {{ \Illuminate\Support\Str::plural('day', $week->workDays->count()) }} worked</p>
            </div>
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase text-slate-500">
                    <tr>
                        <th class="px-4 py-2">Date</th>
                        <th class="px-4 py-2">Day</th>
                        <th class="px-4 py-2">Project / Site</th>
                        <th class="px-4 py-2">Notes</th>
                        <th class="px-4 py-2"></th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @forelse ($week->workDays as $day)
                        <tr>
                            <td class="px-4 py-3">{{ $day->work_date->format('d/m/Y') }}</td>
                            <td class="px-4 py-3">{{ $day->dayName() }}</td>
                            <td class="px-4 py-3">
                                @if ($day->project)
                                    <a href="{{ route('construction.projects.show', $day->project) }}" class="font-medium text-amber-700">{{ $day->project->name }}</a>
                                @else
                                    <span class="text-slate-400">{{ $day->siteName() }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-slate-500">{{ $day->notes ?? '—' }}</td>
                            <td class="px-4 py-3 text-right">
                                @can('recordWork', $worker)
                                    @unless ($week->isSettled())
                                        <form method="POST" action="{{ route('construction.workers.payroll.work-days.destroy', ['worker' => $worker, 'workDay' => $day]) }}" onsubmit="return confirm('Remove this day from the sheet?')">
                                            @csrf @method('DELETE')
                                            <button class="text-xs font-semibold text-rose-600">Remove</button>
                                        </form>
                                    @endunless
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-8 text-center text-slate-500">No days recorded for this week yet.</td></tr>
                    @endforelse
                </tbody>
            </table>

            @can('recordWork', $worker)
                @unless ($week->isSettled())
                    <form method="POST" action="{{ route('construction.workers.payroll.work-days.store', $worker) }}" class="grid gap-3 border-t bg-slate-50 p-4 sm:grid-cols-4">
                        @csrf
                        <div>
                            <x-input-label for="work_date" value="Date" />
                            <x-text-input id="work_date" name="work_date" type="date" class="mt-1 block w-full"
                                :value="old('work_date', now()->between($week->week_start, $week->week_end) ? now()->toDateString() : $week->week_start->toDateString())"
                                :min="$week->week_start->toDateString()" :max="$week->week_end->toDateString()" required />
                        </div>
                        <div>
                            <x-input-label for="work_day_project" value="Project / Site" />
                            <select id="work_day_project" name="project_id" class="mt-1 block w-full rounded-md border-gray-300">
                                <option value="">Not on a site</option>
                                @foreach ($projects as $project)
                                    <option value="{{ $project->id }}" @selected(old('project_id') == $project->id)>{{ $project->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <x-input-label for="work_day_notes" value="Notes" />
                            <x-text-input id="work_day_notes" name="notes" class="mt-1 block w-full" :value="old('notes')" placeholder="Optional" />
                        </div>
                        <div class="flex items-end">
                            <x-primary-button>Add day</x-primary-button>
                        </div>
                    </form>
                @endunless
            @endcan
        </div>

        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b px-5 py-3 font-semibold text-slate-800">Money given this week</div>
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase text-slate-500">
                    <tr>
                        <th class="px-4 py-2">Date</th>
                        <th class="px-4 py-2">Type</th>
                        <th class="px-4 py-2">Effect</th>
                        <th class="px-4 py-2">Site</th>
                        <th class="px-4 py-2 text-right">Amount</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @forelse ($week->payments->sortBy('payment_date') as $payment)
                        <tr>
                            <td class="px-4 py-3">{{ $payment->payment_date->format('d/m/Y') }}</td>
                            <td class="px-4 py-3">{{ $payment->type->shortLabel() }}</td>
                            <td class="px-4 py-3 {{ $payment->createsDebt() ? 'text-rose-700' : 'text-slate-600' }}">{{ $payment->effect() }}</td>
                            <td class="px-4 py-3 text-slate-500">{{ $payment->project?->name ?? '—' }}</td>
                            <td class="px-4 py-3 text-right font-semibold">Rs. {{ number_format((float) $payment->amount, 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-8 text-center text-slate-500">Nothing paid this week yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @can('managePayroll', $worker)
            @if ($week->isSettled())
                <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-5 text-sm text-emerald-800">
                    <p class="font-semibold">Week settled on {{ $week->settled_at->format('d/m/Y') }}{{ $week->settler ? ' by '.$week->settler->name : '' }}.</p>
                    <p class="mt-1">Total handed over Rs. {{ number_format($week->totalPaid(), 2) }}. The work sheet and payments for this week are locked.</p>
                    @if ($remaining > 0)
                        <p class="mt-1">Rs. {{ number_format($remaining, 2) }} of this week's salary was not paid out.</p>
                    @endif
                    <form method="POST" action="{{ route('construction.workers.payroll.reopen', ['worker' => $worker, 'week' => $week]) }}" class="mt-3" onsubmit="return confirm('Reopen this week so it can be corrected?')">
                        @csrf
                        <button class="btn btn-secondary btn-sm">Reopen to correct</button>
                    </form>
                </div>
            @else
                <div class="grid gap-6 lg:grid-cols-2">
                    <form method="POST" action="{{ route('construction.workers.payroll.advances.store', $worker) }}" class="space-y-4 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                        @csrf
                        <div>
                            <p class="font-semibold text-slate-800">Give money before Saturday</p>
                            <p class="mt-1 text-sm text-slate-500">Choose whether it comes off this week's salary or becomes debt.</p>
                        </div>
                        <div class="grid gap-3 sm:grid-cols-2">
                            <div>
                                <x-input-label for="amount" value="Amount (Rs.)" />
                                <x-text-input id="amount" name="amount" type="number" step="0.01" min="0.01" class="mt-1 block w-full" :value="old('amount')" required />
                            </div>
                            <div>
                                <x-input-label for="payment_date" value="Date" />
                                <x-text-input id="payment_date" name="payment_date" type="date" class="mt-1 block w-full"
                                    :value="old('payment_date', now()->between($week->week_start, $week->week_end) ? now()->toDateString() : $week->week_start->toDateString())" required />
                            </div>
                        </div>
                        <fieldset class="space-y-2">
                            <legend class="text-sm font-medium text-slate-700">Deduct from current week salary?</legend>
                            <label class="flex cursor-pointer items-start gap-2 rounded-md border border-slate-200 p-3 text-sm">
                                <input type="radio" name="deduct_from_week" value="1" class="mt-0.5" @checked(old('deduct_from_week', '1') === '1')>
                                <span>
                                    <span class="font-medium">Yes — take it off this week</span>
                                    <span class="block text-xs text-slate-500">Saturday payout drops by this amount. No debt created.</span>
                                </span>
                            </label>
                            <label class="flex cursor-pointer items-start gap-2 rounded-md border border-slate-200 p-3 text-sm">
                                <input type="radio" name="deduct_from_week" value="0" class="mt-0.5" @checked(old('deduct_from_week') === '0')>
                                <span>
                                    <span class="font-medium">No — add it to worker debt</span>
                                    <span class="block text-xs text-slate-500">Worker still gets the full salary on Saturday and owes this amount later.</span>
                                </span>
                            </label>
                        </fieldset>
                        <div class="grid gap-3 sm:grid-cols-2">
                            <div>
                                <x-input-label for="advance_project" value="Site (optional)" />
                                <select id="advance_project" name="project_id" class="mt-1 block w-full rounded-md border-gray-300">
                                    <option value="">—</option>
                                    @foreach ($projects as $project)
                                        <option value="{{ $project->id }}" @selected(old('project_id') == $project->id)>{{ $project->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <x-input-label for="advance_notes" value="Notes" />
                                <x-text-input id="advance_notes" name="notes" class="mt-1 block w-full" :value="old('notes')" placeholder="Reason" />
                            </div>
                        </div>
                        <x-primary-button>Record advance</x-primary-button>
                    </form>

                    <form method="POST" action="{{ route('construction.workers.payroll.settle', ['worker' => $worker, 'week' => $week]) }}" class="space-y-4 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                        @csrf
                        <div>
                            <p class="font-semibold text-slate-800">Saturday settlement</p>
                            <p class="mt-1 text-sm text-slate-500">Pay the balance and close the week.</p>
                        </div>
                        @if ($debtBalance > 0)
                            <div class="rounded-md border border-rose-200 bg-rose-50 p-3">
                                <x-input-label for="debt_deducted" value="Recover debt now (Rs.)" />
                                <x-text-input id="debt_deducted" name="debt_deducted" type="number" step="0.01" min="0" :max="$debtBalance" class="mt-1 block w-full" :value="old('debt_deducted', 0)" />
                                <p class="mt-1 text-xs text-rose-700">
                                    Worker owes Rs. {{ number_format($debtBalance, 2) }}. Enter how much to take off this week's salary, or leave 0 to carry it forward.
                                </p>
                            </div>
                        @else
                            <input type="hidden" name="debt_deducted" value="0">
                        @endif
                        <div>
                            <x-input-label for="settle_amount" value="Amount to pay now (Rs.)" />
                            <x-text-input id="settle_amount" name="amount" type="number" step="0.01" min="0" class="mt-1 block w-full" :value="old('amount', number_format($remaining, 2, '.', ''))" required />
                            <p class="mt-1 text-xs text-slate-500">Rs. {{ number_format($remaining, 2) }} is left before any debt recovery.</p>
                        </div>
                        <div>
                            <x-input-label for="settle_notes" value="Notes" />
                            <x-text-input id="settle_notes" name="notes" class="mt-1 block w-full" :value="old('notes')" placeholder="Optional" />
                        </div>
                        <button class="btn btn-success">Settle week</button>
                        <p class="text-xs text-slate-500">Wages are posted to the sites on the work sheet as a labour expense.</p>
                    </form>
                </div>
            @endif
        @endcan

        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b px-5 py-3 font-semibold text-slate-800">Recent weeks</div>
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase text-slate-500">
                    <tr>
                        <th class="px-4 py-2">Week</th>
                        <th class="px-4 py-2 text-right">Salary</th>
                        <th class="px-4 py-2 text-right">Advances</th>
                        <th class="px-4 py-2 text-right">Debt recovered</th>
                        <th class="px-4 py-2 text-right">Total paid</th>
                        <th class="px-4 py-2">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @foreach ($history as $row)
                        <tr class="{{ $row->id === $week->id ? 'bg-amber-50' : '' }}">
                            <td class="px-4 py-3">
                                <a href="{{ route('construction.workers.payroll', ['worker' => $worker, 'week' => $row->week_start->toDateString()]) }}" class="font-medium text-amber-700">{{ $row->label() }}</a>
                            </td>
                            <td class="px-4 py-3 text-right">Rs. {{ number_format((float) $row->weekly_salary, 2) }}</td>
                            <td class="px-4 py-3 text-right">Rs. {{ number_format($row->advancesDeducted() + $row->advancesToDebt(), 2) }}</td>
                            <td class="px-4 py-3 text-right">Rs. {{ number_format((float) $row->debt_deducted, 2) }}</td>
                            <td class="px-4 py-3 text-right font-semibold">Rs. {{ number_format($row->totalPaid(), 2) }}</td>
                            <td class="px-4 py-3">
                                <span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ $row->isSettled() ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-800' }}">
                                    {{ $row->isSettled() ? 'Settled' : 'Open' }}
                                </span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
