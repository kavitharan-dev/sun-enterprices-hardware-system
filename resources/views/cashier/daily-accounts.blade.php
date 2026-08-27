<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="text-xl font-semibold text-slate-800">Daily accounts</h2>
            <p class="text-sm text-slate-500">The cashier records money once here. Daily Accounts is the cash book; sales, stock, workers, and projects update from the same transaction ID.</p>
        </div>
    </x-slot>

    @php
        $from = $filters['from'];
        $to = $filters['to'];
    @endphp

    <div class="space-y-6">
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-xs uppercase text-slate-500">Opening balance</p>
                <p class="mt-1 text-2xl font-semibold text-slate-800">Rs. {{ number_format($totals['opening'], 2) }}</p>
                <p class="mt-1 text-xs text-slate-500">{{ \Illuminate\Support\Carbon::parse($from)->format('d/m/Y') }}</p>
            </div>
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 shadow-sm">
                <p class="text-xs uppercase text-emerald-700">Total income</p>
                <p class="mt-1 text-2xl font-semibold text-emerald-800">Rs. {{ number_format($onlyDate ? $totals['income'] : $filteredIncome, 2) }}</p>
            </div>
            <div class="rounded-xl border border-rose-200 bg-rose-50 p-4 shadow-sm">
                <p class="text-xs uppercase text-rose-600">Total expenses</p>
                <p class="mt-1 text-2xl font-semibold text-rose-700">Rs. {{ number_format($onlyDate ? $totals['expense'] : $filteredExpense, 2) }}</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-xs uppercase text-slate-500">Closing balance</p>
                <p class="mt-1 text-2xl font-semibold {{ $totals['closing'] < 0 ? 'text-rose-700' : 'text-slate-800' }}">Rs. {{ number_format($onlyDate ? ($day->isClosed() && $day->closing_balance !== null ? (float) $day->closing_balance : $totals['closing']) : ($totals['opening'] + $filteredIncome - $filteredExpense), 2) }}</p>
                <p class="mt-1 text-xs text-slate-500">Opening + income − expenses</p>
            </div>
        </div>

        @include('cashier.partials.record-money-form')

        @if ($onlyDate)
            <div class="flex flex-wrap items-center gap-3 rounded-xl border border-slate-200 bg-white px-5 py-4 shadow-sm">
                <div class="flex-1">
                    @if ($day->isClosed())
                        <p class="font-semibold text-emerald-800">Till closed for {{ \Illuminate\Support\Carbon::parse($from)->format('d/m/Y') }}</p>
                        <p class="text-sm text-slate-500">
                            Closed by {{ $day->closer?->name ?? '—' }}
                            @if ($day->closed_at) at {{ $day->closed_at->format('d/m/Y H:i') }} @endif
                            · Print or download the PDF for the daily check / paper backup.
                        </p>
                    @else
                        <p class="font-semibold text-slate-800">End of day</p>
                        <p class="text-sm text-slate-500">Print an interim report any time. Close the day when the till check is done — no more money can be recorded for this date.</p>
                    @endif
                </div>
                <a href="{{ route('cashier.daily-accounts.print', ['date' => $from]) }}" class="btn btn-dark">Print day report</a>
                <a href="{{ route('cashier.daily-accounts.pdf', ['date' => $from]) }}" class="btn btn-secondary">Download PDF</a>
                @if ($canClose)
                    <details class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2">
                        <summary class="cursor-pointer text-sm font-semibold text-amber-900">Close day…</summary>
                        <form method="POST" action="{{ route('cashier.daily-accounts.close') }}" class="mt-3 space-y-3" onsubmit="return confirm('Close this day? No more transactions can be recorded for this date until an admin reopens it.')">
                            @csrf
                            <input type="hidden" name="business_date" value="{{ $from }}">
                            <div>
                                <x-input-label for="counted_cash" value="Cash counted in till (optional)" />
                                <x-text-input id="counted_cash" name="counted_cash" type="number" step="0.01" class="mt-1 block w-full" :value="old('counted_cash')" placeholder="Physical count" />
                            </div>
                            <div>
                                <x-input-label for="close_notes" value="Close notes" />
                                <x-text-input id="close_notes" name="close_notes" class="mt-1 block w-full" :value="old('close_notes')" placeholder="Optional" />
                            </div>
                            <x-primary-button>Close day &amp; print</x-primary-button>
                        </form>
                    </details>
                @endif
                @if ($canReopen)
                    <form method="POST" action="{{ route('cashier.daily-accounts.reopen') }}" onsubmit="return confirm('Reopen this day so money can be recorded again?')">
                        @csrf
                        <input type="hidden" name="business_date" value="{{ $from }}">
                        <button type="submit" class="btn btn-secondary">Reopen day</button>
                    </form>
                @endif
            </div>
        @endif

        @if ($pending->isNotEmpty())
            <div class="overflow-hidden rounded-xl border border-amber-200 bg-white shadow-sm">
                <div class="border-b border-amber-100 bg-amber-50 px-5 py-3">
                    <p class="font-semibold text-amber-900">Older requests still waiting ({{ $pending->count() }})</p>
                    <p class="text-xs text-amber-800">New money is recorded in Record money above. Confirm these leftover requests if they were sent before that change.</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-slate-50 text-left text-xs font-semibold uppercase text-slate-500">
                            <tr>
                                <th class="px-3 py-2">Type</th>
                                <th class="px-3 py-2">Description</th>
                                <th class="px-3 py-2">Project / Worker</th>
                                <th class="px-3 py-2">Requested by</th>
                                <th class="px-3 py-2 text-right">Amount</th>
                                <th class="px-3 py-2">Confirm</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            @foreach ($pending as $item)
                                <tr>
                                    <td class="px-3 py-3 whitespace-nowrap">
                                        <p class="font-medium">{{ $item->type->label() }}</p>
                                        <p class="text-xs {{ $item->direction === 'income' ? 'text-emerald-700' : 'text-rose-700' }}">{{ $item->direction === 'income' ? 'Money in' : 'Money out' }}</p>
                                    </td>
                                    <td class="px-3 py-3">{{ $item->description }}</td>
                                    <td class="px-3 py-3">
                                        {{ $item->project?->name ?? '—' }}
                                        @if ($item->worker)
                                            <p class="text-xs text-slate-500">{{ $item->worker->name }}</p>
                                        @endif
                                    </td>
                                    <td class="px-3 py-3">{{ $item->requester?->name ?? '—' }}<p class="text-xs text-slate-500">{{ $item->created_at->format('d/m/Y H:i') }}</p></td>
                                    <td class="px-3 py-3 text-right font-semibold">Rs. {{ number_format((float) $item->amount, 2) }}</td>
                                    <td class="px-3 py-3">
                                        @if (auth()->user()->canConfirmTill())
                                            <form method="POST" action="{{ route('cashier.requests.confirm', $item) }}" class="flex flex-wrap items-end gap-2">
                                                @csrf
                                                <input type="date" name="payment_date" value="{{ $item->payment_date?->toDateString() ?? now()->toDateString() }}" class="rounded-md border-gray-300 text-xs" required>
                                                <x-searchable-select
                                                    name="method"
                                                    :options="collect($methods)->map(fn ($m) => ['value' => $m->value, 'label' => $m->label()])->values()"
                                                    :value="(string) ($item->method?->value ?? 'cash')"
                                                    empty-label="Cash"
                                                    :allow-empty="false"
                                                    :required="true"
                                                    placeholder="Method"
                                                    class="min-w-28 text-xs"
                                                />
                                                <input type="text" name="reference" value="{{ $item->reference }}" placeholder="Receipt no." class="w-28 rounded-md border-gray-300 text-xs">
                                                <button class="btn btn-success btn-sm">Confirm</button>
                                            </form>
                                            <form method="POST" action="{{ route('cashier.requests.reject', $item) }}" class="mt-1" onsubmit="return confirm('Reject this request? No money will be recorded.')">
                                                @csrf
                                                <button class="text-xs font-semibold text-rose-600">Reject</button>
                                            </form>
                                        @else
                                            <span class="text-xs text-slate-500">Waiting for cashier</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        <form method="GET" class="grid gap-3 overflow-visible rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:grid-cols-2 lg:grid-cols-4">
            <div>
                <x-input-label for="from" value="From" />
                <x-text-input id="from" name="from" type="date" class="mt-1 block w-full" :value="$from" />
            </div>
            <div>
                <x-input-label for="to" value="To" />
                <x-text-input id="to" name="to" type="date" class="mt-1 block w-full" :value="$to" />
            </div>
            <div class="relative z-30">
                <x-input-label for="project_id" value="Project" />
                <x-searchable-select
                    name="project_id"
                    :options="$projects->map(fn ($p) => ['value' => (string) $p->id, 'label' => $p->name, 'search' => $p->name])->values()"
                    :value="(string) ($filters['project_id'] ?? '')"
                    empty-label="All projects"
                    :allow-empty="true"
                    placeholder="Search project…"
                    class="mt-1"
                />
            </div>
            <div class="relative z-30">
                <x-input-label for="worker_id" value="Worker" />
                <x-searchable-select
                    name="worker_id"
                    :options="$workers->map(fn ($w) => [
                        'value' => (string) $w->id,
                        'label' => $w->name.($w->worker_code ? ' — '.$w->worker_code : ''),
                        'search' => $w->name.' '.($w->worker_code ?? ''),
                    ])->values()"
                    :value="(string) ($filters['worker_id'] ?? '')"
                    empty-label="All workers"
                    :allow-empty="true"
                    placeholder="Type worker name or code…"
                    class="mt-1"
                />
            </div>
            <div class="relative z-20">
                <x-input-label for="type" value="Transaction type" />
                <x-searchable-select
                    name="type"
                    :options="collect($types)->map(fn ($t) => [
                        'value' => $t->value,
                        'label' => $t->shortLabel().' ('.$t->directionLabel().')',
                        'search' => $t->shortLabel().' '.$t->label().' '.$t->directionLabel(),
                    ])->values()"
                    :value="(string) ($filters['type'] ?? '')"
                    empty-label="All types"
                    :allow-empty="true"
                    placeholder="Search type…"
                    class="mt-1"
                />
            </div>
            <div>
                <x-input-label for="category" value="Category" />
                <x-searchable-select
                    name="category"
                    :options="collect($categories)->map(fn ($c) => ['value' => $c->value, 'label' => $c->label()])->values()"
                    :value="(string) ($filters['category'] ?? '')"
                    empty-label="All categories"
                    :allow-empty="true"
                    placeholder="Search category…"
                    class="mt-1"
                />
            </div>
            <div>
                <x-input-label for="direction" value="Income / Expense" />
                <x-searchable-select
                    name="direction"
                    :options="[
                        ['value' => 'income', 'label' => 'Income'],
                        ['value' => 'expense', 'label' => 'Expense'],
                    ]"
                    :value="(string) ($filters['direction'] ?? '')"
                    empty-label="Both"
                    :allow-empty="true"
                    placeholder="Direction"
                    class="mt-1"
                />
            </div>
            <div>
                <x-input-label for="method" value="Payment method" />
                <x-searchable-select
                    name="method"
                    :options="collect($methods)->map(fn ($m) => ['value' => $m->value, 'label' => $m->label()])->values()"
                    :value="(string) ($filters['method'] ?? '')"
                    empty-label="All methods"
                    :allow-empty="true"
                    placeholder="Search method…"
                    class="mt-1"
                />
            </div>
            <div class="flex items-end gap-2 lg:col-span-4">
                <x-primary-button>Filter</x-primary-button>
                <a href="{{ route('cashier.daily-accounts.index') }}" class="btn btn-secondary">Today</a>
            </div>
        </form>

        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase text-slate-500">
                            <tr>
                                <th class="px-3 py-2">Txn ID</th>
                                <th class="px-3 py-2">Date</th>
                            <th class="px-3 py-2">Transaction type</th>
                            <th class="px-3 py-2">Category</th>
                            <th class="px-3 py-2">Description</th>
                            <th class="px-3 py-2">Project</th>
                            <th class="px-3 py-2">Worker</th>
                            <th class="px-3 py-2">Reference</th>
                            <th class="px-3 py-2 text-right">Income</th>
                            <th class="px-3 py-2 text-right">Expense</th>
                            <th class="px-3 py-2 text-right">Balance</th>
                            <th class="px-3 py-2"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @if ($onlyDate)
                            <tr class="bg-slate-50">
                                <td class="px-3 py-2 text-slate-500" colspan="8">Opening balance</td>
                                <td class="px-3 py-2 text-right text-slate-400">—</td>
                                <td class="px-3 py-2 text-right text-slate-400">—</td>
                                <td class="px-3 py-2 text-right font-semibold">Rs. {{ number_format($totals['opening'], 2) }}</td>
                                <td></td>
                            </tr>
                        @endif
                        @forelse ($rows as $row)
                            @php $entry = $row['entry']; @endphp
                            <tr>
                                <td class="whitespace-nowrap px-3 py-2"><x-transaction-no :no="$entry->transaction_no" /></td>
                                <td class="whitespace-nowrap px-3 py-2">{{ $entry->occurred_on->format('d/m/Y') }}</td>
                                <td class="px-3 py-2">
                                    <p class="font-medium">{{ $entry->type->shortLabel() }}</p>
                                    <p class="text-xs {{ $entry->type->isIncome() ? 'text-emerald-700' : 'text-rose-700' }}">{{ $entry->type->directionLabel() }}</p>
                                </td>
                                <td class="px-3 py-2">{{ $entry->category->label() }}</td>
                                <td class="px-3 py-2">{{ $entry->description }}</td>
                                <td class="px-3 py-2">{{ $entry->project?->name ?? '—' }}</td>
                                <td class="px-3 py-2">{{ $entry->worker?->name ?? '—' }}</td>
                                <td class="px-3 py-2 text-slate-500">{{ $entry->reference_no ?: ($entry->method?->label() ?? '—') }}</td>
                                <td class="px-3 py-2 text-right text-emerald-700">{{ (float) $entry->income > 0 ? 'Rs. '.number_format((float) $entry->income, 2) : '—' }}</td>
                                <td class="px-3 py-2 text-right text-rose-700">{{ (float) $entry->expense > 0 ? 'Rs. '.number_format((float) $entry->expense, 2) : '—' }}</td>
                                <td class="px-3 py-2 text-right font-semibold">Rs. {{ number_format($row['balance'], 2) }}</td>
                                <td class="px-3 py-2 text-right">
                                    @if ($entry->is_manual)
                                        <form method="POST" action="{{ route('cashier.daily-accounts.destroy', $entry) }}" onsubmit="return confirm('Remove this manual entry?')">
                                            @csrf @method('DELETE')
                                            <button class="text-xs font-semibold text-rose-600">Remove</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="12" class="px-3 py-8 text-center text-slate-500">No transactions for these filters.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <form method="POST" action="{{ route('cashier.daily-accounts.opening') }}" class="max-w-xl space-y-4 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            @csrf @method('PUT')
            <div>
                <p class="font-semibold text-slate-800">Opening balance</p>
                <p class="mt-1 text-sm text-slate-500">Cash in the till at the start of the day.</p>
            </div>
            <input type="hidden" name="business_date" value="{{ $from }}">
            <div>
                <x-input-label for="opening_balance" value="Opening (Rs.)" />
                <x-text-input id="opening_balance" name="opening_balance" type="number" step="0.01" class="mt-1 block w-full" :value="old('opening_balance', number_format((float) $day->opening_balance, 2, '.', ''))" required :disabled="$day->isClosed()" />
            </div>
            <div>
                <x-input-label for="opening_notes" value="Notes" />
                <x-text-input id="opening_notes" name="notes" class="mt-1 block w-full" :value="old('notes', $day->notes)" placeholder="Optional" :disabled="$day->isClosed()" />
            </div>
            @unless ($day->isClosed())
                <x-primary-button>Save opening</x-primary-button>
            @else
                <p class="text-sm text-slate-500">Opening is locked because this day is closed.</p>
            @endunless
        </form>
    </div>
</x-app-layout>
