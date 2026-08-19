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
                <p class="mt-1 text-2xl font-semibold {{ $totals['closing'] < 0 ? 'text-rose-700' : 'text-slate-800' }}">Rs. {{ number_format($onlyDate ? $totals['closing'] : ($totals['opening'] + $filteredIncome - $filteredExpense), 2) }}</p>
                <p class="mt-1 text-xs text-slate-500">Opening + income − expenses</p>
            </div>
        </div>

        @if ($pending->isNotEmpty())
            <div class="overflow-hidden rounded-xl border border-amber-200 bg-white shadow-sm">
                <div class="border-b border-amber-100 bg-amber-50 px-5 py-3">
                    <p class="font-semibold text-amber-900">Older requests still waiting ({{ $pending->count() }})</p>
                    <p class="text-xs text-amber-800">New money is recorded in the form below. Confirm these leftover requests if they were sent before that change.</p>
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
                                                <select name="method" class="rounded-md border-gray-300 text-xs" required>
                                                    @foreach ($methods as $method)
                                                        <option value="{{ $method->value }}" @selected(($item->method?->value ?? 'cash') === $method->value)>{{ $method->label() }}</option>
                                                    @endforeach
                                                </select>
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

        <form method="GET" class="grid gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:grid-cols-2 lg:grid-cols-4">
            <div>
                <x-input-label for="from" value="From" />
                <x-text-input id="from" name="from" type="date" class="mt-1 block w-full" :value="$from" />
            </div>
            <div>
                <x-input-label for="to" value="To" />
                <x-text-input id="to" name="to" type="date" class="mt-1 block w-full" :value="$to" />
            </div>
            <div>
                <x-input-label for="project_id" value="Project" />
                <select id="project_id" name="project_id" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                    <option value="">All projects</option>
                    @foreach ($projects as $project)
                        <option value="{{ $project->id }}" @selected((string) $filters['project_id'] === (string) $project->id)>{{ $project->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <x-input-label for="worker_id" value="Worker" />
                <select id="worker_id" name="worker_id" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                    <option value="">All workers</option>
                    @foreach ($workers as $worker)
                        <option value="{{ $worker->id }}" @selected((string) $filters['worker_id'] === (string) $worker->id)>{{ $worker->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <x-input-label for="type" value="Transaction type" />
                <select id="type" name="type" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                    <option value="">All types</option>
                    @foreach ($types as $type)
                        <option value="{{ $type->value }}" @selected($filters['type'] === $type->value)>{{ $type->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <x-input-label for="category" value="Category" />
                <select id="category" name="category" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                    <option value="">All categories</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->value }}" @selected($filters['category'] === $category->value)>{{ $category->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <x-input-label for="direction" value="Income / Expense" />
                <select id="direction" name="direction" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                    <option value="">Both</option>
                    <option value="income" @selected($filters['direction'] === 'income')>Income</option>
                    <option value="expense" @selected($filters['direction'] === 'expense')>Expense</option>
                </select>
            </div>
            <div>
                <x-input-label for="method" value="Payment method" />
                <select id="method" name="method" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                    <option value="">All methods</option>
                    @foreach ($methods as $method)
                        <option value="{{ $method->value }}" @selected($filters['method'] === $method->value)>{{ $method->label() }}</option>
                    @endforeach
                </select>
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
                                <td class="px-3 py-2">{{ $entry->type->label() }}</td>
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

        <div class="grid gap-6 lg:grid-cols-2">
            <form method="POST" action="{{ route('cashier.daily-accounts.opening') }}" class="space-y-4 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                @csrf @method('PUT')
                <div>
                    <p class="font-semibold text-slate-800">Opening balance</p>
                    <p class="mt-1 text-sm text-slate-500">Set the till at the start of the day. Yesterday’s closing is used until you change it.</p>
                </div>
                <input type="hidden" name="business_date" value="{{ $from }}">
                <div>
                    <x-input-label for="opening_balance" value="Opening (Rs.)" />
                    <x-text-input id="opening_balance" name="opening_balance" type="number" step="0.01" class="mt-1 block w-full" :value="old('opening_balance', number_format((float) $day->opening_balance, 2, '.', ''))" required />
                </div>
                <div>
                    <x-input-label for="opening_notes" value="Notes" />
                    <x-text-input id="opening_notes" name="notes" class="mt-1 block w-full" :value="old('notes', $day->notes)" placeholder="Optional" />
                </div>
                <x-primary-button>Save opening</x-primary-button>
            </form>

            @if ($canRecord)
            <form method="POST" action="{{ route('cashier.daily-accounts.store') }}" class="space-y-4 rounded-xl border border-slate-200 bg-white p-5 shadow-sm lg:col-span-1" x-data="{ type: '{{ old('type', 'owner_payment') }}' }">
                @csrf
                <div>
                    <p class="font-semibold text-slate-800">Record money</p>
                    <p class="mt-1 text-sm text-slate-500">Enter the cash, card, or bank movement once. Daily Accounts posts it and the related sale, purchase, worker, or project updates automatically.</p>
                </div>
                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <x-input-label for="occurred_on" value="Date" />
                        <x-text-input id="occurred_on" name="occurred_on" type="date" class="mt-1 block w-full" :value="old('occurred_on', $from)" required />
                    </div>
                    <div>
                        <x-input-label for="manual_type" value="What is this money?" />
                        <select id="manual_type" name="type" x-model="type" class="mt-1 block w-full rounded-md border-gray-300 text-sm" required>
                            @foreach ($types as $type)
                                <option value="{{ $type->value }}">{{ $type->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div x-show="type === 'sale'" x-cloak class="sm:col-span-2">
                        <x-input-label for="sale_id" value="Sale" />
                        <select id="sale_id" name="sale_id" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                            <option value="">Select a draft or unpaid sale</option>
                            @foreach ($openSales as $sale)
                                <option value="{{ $sale->id }}" @selected((string) old('sale_id') === (string) $sale->id)>
                                    {{ $sale->invoice_no ?: 'Draft' }} — {{ $sale->customerName() }} — Rs. {{ number_format((float) ($sale->balance > 0 ? $sale->balance : $sale->total), 2) }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div x-show="type === 'purchase'" x-cloak class="sm:col-span-2">
                        <x-input-label for="purchase_id" value="Purchase" />
                        <select id="purchase_id" name="purchase_id" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                            <option value="">Select a draft purchase to pay</option>
                            @foreach ($draftPurchases as $purchase)
                                <option value="{{ $purchase->id }}" @selected((string) old('purchase_id') === (string) $purchase->id)>
                                    {{ $purchase->reference_no }} — {{ $purchase->supplier?->name }} — Rs. {{ number_format((float) $purchase->total, 2) }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div x-show="['owner_payment','project_expense','worker_advance','worker_settlement'].includes(type)" x-cloak>
                        <x-input-label for="manual_project" value="Project" />
                        <select id="manual_project" name="project_id" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                            <option value="">{{ in_array(old('type'), ['owner_payment', 'project_expense'], true) ? 'Select project' : 'Optional' }}</option>
                            @foreach ($projects as $project)
                                <option value="{{ $project->id }}" @selected((string) old('project_id') === (string) $project->id)>{{ $project->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div x-show="['worker_advance','worker_settlement','other_income','other_expense'].includes(type)" x-cloak>
                        <x-input-label for="manual_worker" value="Worker" />
                        <select id="manual_worker" name="worker_id" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                            <option value="">{{ str_starts_with(old('type', ''), 'worker') ? 'Select worker' : 'Optional' }}</option>
                            @foreach ($workers as $worker)
                                <option value="{{ $worker->id }}" @selected((string) old('worker_id') === (string) $worker->id)>{{ $worker->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div x-show="type === 'project_expense'" x-cloak>
                        <x-input-label for="expense_category" value="Expense category" />
                        <select id="expense_category" name="expense_category" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                            <option value="">Category</option>
                            @foreach ($expenseCategories as $category)
                                <option value="{{ $category->value }}" @selected(old('expense_category') === $category->value)>{{ $category->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div x-show="type === 'other_income' || type === 'other_expense'" x-cloak>
                        <x-input-label for="manual_category" value="Category" />
                        <select id="manual_category" name="category" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                            <option value="other_income">Other income</option>
                            <option value="other">Other</option>
                            <option value="transport">Transport</option>
                            <option value="labour">Labour</option>
                        </select>
                    </div>
                    <div x-show="type !== 'purchase'">
                        <x-input-label for="manual_amount" value="Amount (Rs.)" />
                        <x-text-input id="manual_amount" name="amount" type="number" step="0.01" min="0" class="mt-1 block w-full" :value="old('amount')" />
                    </div>
                    <div>
                        <x-input-label for="manual_method" value="Method" />
                        <select id="manual_method" name="method" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
                            @foreach ($methods as $method)
                                <option value="{{ $method->value }}">{{ $method->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <x-input-label for="manual_reference" value="Receipt / reference" />
                        <x-text-input id="manual_reference" name="reference_no" class="mt-1 block w-full" :value="old('reference_no')" placeholder="Slip / voucher" />
                    </div>
                    <div x-show="type === 'worker_advance'" x-cloak class="sm:col-span-2">
                        <label class="inline-flex items-center gap-2 text-sm">
                            <input type="checkbox" name="deduct_from_week" value="1" class="rounded border-gray-300" @checked(old('deduct_from_week'))>
                            Deduct this advance from the current week salary
                        </label>
                    </div>
                    <div x-show="type === 'worker_settlement'" x-cloak>
                        <x-input-label for="debt_deducted" value="Recover worker debt (Rs.)" />
                        <x-text-input id="debt_deducted" name="debt_deducted" type="number" step="0.01" min="0" class="mt-1 block w-full" :value="old('debt_deducted', 0)" />
                    </div>
                    <div class="sm:col-span-2">
                        <x-input-label for="manual_description" value="Notes" />
                        <x-text-input id="manual_description" name="description" class="mt-1 block w-full" :value="old('description')" placeholder="Optional except for other income, other expense, and site expense" />
                    </div>
                </div>
                <x-primary-button>Record in Daily Accounts</x-primary-button>
            </form>
            @else
            <div class="rounded-xl border border-slate-200 bg-slate-50 p-5 text-sm text-slate-600">
                You can view this cash book. Only the cashier (or admin covering the till) records money here.
            </div>
            @endif
        </div>
    </div>
</x-app-layout>
