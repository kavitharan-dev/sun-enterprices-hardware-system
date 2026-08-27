        @if ($canRecord)
            @php
                $saleOptions = $openSales->map(fn ($s) => [
                    'value' => (string) $s->id,
                    'label' => ($s->invoice_no ?: 'Draft').' — '.$s->customerName().' — Rs. '.number_format((float) ($s->balance > 0 ? $s->balance : $s->total), 2),
                    'search' => ($s->invoice_no ?: 'Draft').' '.$s->customerName(),
                ])->values();
                $purchaseOptions = $draftPurchases->map(fn ($p) => [
                    'value' => (string) $p->id,
                    'label' => $p->reference_no.' — '.($p->supplier?->name ?? '').' — Rs. '.number_format((float) $p->total, 2),
                    'search' => $p->reference_no.' '.($p->supplier?->name ?? ''),
                ])->values();
                $projectOptions = $projects->map(fn ($p) => [
                    'value' => (string) $p->id,
                    'label' => $p->name.($p->project_code ? ' — '.$p->project_code : ''),
                    'search' => $p->name.' '.($p->project_code ?? ''),
                ])->values();
                $workerOptions = $workers->map(fn ($w) => [
                    'value' => (string) $w->id,
                    'label' => $w->name.($w->worker_code ? ' — '.$w->worker_code : ''),
                    'search' => $w->name.' '.($w->worker_code ?? ''),
                ])->values();
                $expenseCategoryOptions = collect($expenseCategories)->map(fn ($c) => [
                    'value' => $c->value,
                    'label' => $c->label(),
                ])->values();
                $initialType = old('type', '');
            @endphp
            <form
                method="POST"
                action="{{ route('cashier.daily-accounts.store') }}"
                class="relative z-40 space-y-5 overflow-visible rounded-xl border-2 border-amber-200 bg-white p-5 shadow-sm sm:p-6"
                x-data="dailyRecordForm({{ \Illuminate\Support\Js::from($initialType) }})"
            >
                @csrf
                <input type="hidden" name="type" :value="type">

                <div>
                    <p class="text-lg font-semibold text-slate-900">Record money</p>
                    <p class="mt-1 text-sm text-slate-600">Tap what happened, then fill only the boxes that appear.</p>
                </div>

                <div>
                    <p class="text-sm font-semibold text-slate-800">1. What happened?</p>
                    <div class="mt-3 grid gap-4 lg:grid-cols-2">
                        <div class="rounded-xl border border-emerald-200 bg-emerald-50/60 p-3 sm:p-4">
                            <p class="mb-2 text-xs font-bold uppercase tracking-wide text-emerald-800">Money in · till receives</p>
                            <div class="grid gap-2">
                                @foreach ($incomeTypes as $t)
                                    <button
                                        type="button"
                                        @click="type = {{ \Illuminate\Support\Js::from($t->value) }}"
                                        class="rounded-lg border bg-white px-3 py-3 text-left transition focus:outline-none focus:ring-2 focus:ring-emerald-500"
                                        :class="type === {{ \Illuminate\Support\Js::from($t->value) }} ? 'border-emerald-600 ring-2 ring-emerald-500 shadow-sm' : 'border-emerald-100 hover:border-emerald-300'"
                                    >
                                        <span class="block text-sm font-semibold text-slate-900">{{ $t->label() }}</span>
                                        <span class="mt-0.5 block text-xs text-slate-500">{{ $t->choiceHint() }}</span>
                                    </button>
                                @endforeach
                            </div>
                        </div>
                        <div class="rounded-xl border border-rose-200 bg-rose-50/60 p-3 sm:p-4">
                            <p class="mb-2 text-xs font-bold uppercase tracking-wide text-rose-800">Money out · till pays</p>
                            <div class="grid gap-2">
                                @foreach ($expenseTypes as $t)
                                    <button
                                        type="button"
                                        @click="type = {{ \Illuminate\Support\Js::from($t->value) }}"
                                        class="rounded-lg border bg-white px-3 py-3 text-left transition focus:outline-none focus:ring-2 focus:ring-rose-500"
                                        :class="type === {{ \Illuminate\Support\Js::from($t->value) }} ? 'border-rose-600 ring-2 ring-rose-500 shadow-sm' : 'border-rose-100 hover:border-rose-300'"
                                    >
                                        <span class="block text-sm font-semibold text-slate-900">{{ $t->label() }}</span>
                                        <span class="mt-0.5 block text-xs text-slate-500">{{ $t->choiceHint() }}</span>
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    </div>
                    <p x-show="!type" x-cloak class="mt-3 text-sm font-medium text-amber-800">Choose one option above to continue.</p>
                </div>

                <div x-show="type" x-cloak class="space-y-4 border-t border-slate-200 pt-5">
                    <div class="flex flex-wrap items-center gap-2">
                        <p class="text-sm font-semibold text-slate-800">2. Fill the details</p>
                        <span
                            class="rounded-full px-2.5 py-0.5 text-xs font-semibold"
                            :class="isIncome ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800'"
                            x-text="isIncome ? 'Money in' : 'Money out'"
                        ></span>
                    </div>

                    <div class="grid gap-4 overflow-visible sm:grid-cols-2">
                        <div>
                            <x-input-label for="occurred_on" value="Date" />
                            <x-text-input id="occurred_on" name="occurred_on" type="date" class="mt-1 block w-full" :value="old('occurred_on', $from)" required />
                        </div>

                        <div x-show="type === 'sale'" class="sm:col-span-2 relative z-40">
                            <x-input-label for="sale_id" value="Which sale?" />
                            <x-searchable-select
                                name="sale_id"
                                :options="$saleOptions"
                                :value="(string) old('sale_id')"
                                empty-label="Type invoice number or customer name…"
                                :allow-empty="true"
                                placeholder="Search sale…"
                                class="mt-1"
                            />
                        </div>

                        <div x-show="type === 'purchase'" class="sm:col-span-2 relative z-40">
                            <x-input-label for="purchase_id" value="Which purchase bill?" />
                            <x-searchable-select
                                name="purchase_id"
                                :options="$purchaseOptions"
                                :value="(string) old('purchase_id')"
                                empty-label="Type purchase number or supplier…"
                                :allow-empty="true"
                                placeholder="Search purchase…"
                                class="mt-1"
                            />
                        </div>

                        <div x-show="needsProject" class="relative z-40">
                            <x-input-label for="manual_project" value="Which site / project?" />
                            <x-searchable-select
                                name="project_id"
                                :options="$projectOptions"
                                :value="(string) old('project_id')"
                                empty-label="Type project name or code…"
                                :allow-empty="true"
                                placeholder="Search project…"
                                class="mt-1"
                            />
                        </div>

                        <div x-show="needsWorker" class="relative z-40">
                            <x-input-label for="manual_worker" value="Which worker?" />
                            <x-searchable-select
                                name="worker_id"
                                :options="$workerOptions"
                                :value="(string) old('worker_id')"
                                empty-label="Type worker name or code…"
                                :allow-empty="true"
                                placeholder="Search worker…"
                                class="mt-1"
                            />
                        </div>

                        <div x-show="type === 'project_expense'" class="relative z-40">
                            <x-input-label for="expense_category" value="What kind of site cost?" />
                            <x-searchable-select
                                name="expense_category"
                                :options="$expenseCategoryOptions"
                                :value="(string) old('expense_category')"
                                empty-label="Transport, labour, tools…"
                                :allow-empty="true"
                                placeholder="Search…"
                                class="mt-1"
                            />
                        </div>

                        <div x-show="type === 'other_income' || type === 'other_expense'" class="relative z-40">
                            <x-input-label for="manual_category" value="Category" />
                            <select id="manual_category" name="category" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500">
                                <option value="other_income" @selected(old('category', 'other_income') === 'other_income')">Other income</option>
                                <option value="other" @selected(old('category') === 'other')">Other</option>
                                <option value="transport" @selected(old('category') === 'transport')">Transport</option>
                                <option value="labour" @selected(old('category') === 'labour')">Labour</option>
                            </select>
                        </div>

                        <div x-show="type !== 'purchase'" class="sm:col-span-2">
                            <x-input-label for="manual_amount" value="How much? (Rs.)" />
                            <x-text-input id="manual_amount" name="amount" type="number" step="0.01" min="0" class="mt-1 block w-full text-lg font-semibold" :value="old('amount')" placeholder="0.00" />
                        </div>

                        <div>
                            <x-input-label for="manual_method" value="Paid how?" />
                            <select id="manual_method" name="method" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500" required>
                                @foreach ($methods as $m)
                                    <option value="{{ $m->value }}" @selected(old('method', 'cash') === $m->value)>{{ $m->label() }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <x-input-label for="manual_reference" value="Receipt / slip no. (optional)" />
                            <x-text-input id="manual_reference" name="reference_no" class="mt-1 block w-full" :value="old('reference_no')" placeholder="Optional" />
                        </div>

                        <div x-show="type === 'worker_advance'" class="sm:col-span-2 rounded-lg border border-amber-100 bg-amber-50 px-3 py-3 space-y-2">
                            <label class="inline-flex items-start gap-2 text-sm text-slate-800">
                                <input type="checkbox" name="deduct_from_week" value="1" class="mt-0.5 rounded border-gray-300" @checked(old('deduct_from_week'))>
                                <span>Take this advance from this week’s salary (tick if yes)</span>
                            </label>
                            <p class="text-xs text-amber-900/80">If that week’s salary is already paid, the advance is saved on the next open week automatically.</p>
                        </div>

                        <div x-show="type === 'worker_settlement'">
                            <x-input-label for="debt_deducted" value="Recover old debt from this pay (Rs.)" />
                            <x-text-input id="debt_deducted" name="debt_deducted" type="number" step="0.01" min="0" class="mt-1 block w-full" :value="old('debt_deducted', 0)" />
                        </div>

                        <div class="sm:col-span-2">
                            <x-input-label for="manual_description" value="Short note" />
                            <x-text-input id="manual_description" name="description" class="mt-1 block w-full" :value="old('description')" placeholder="Needed for site expense / other money — optional otherwise" />
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-3 pt-1">
                        <x-primary-button>Save to cash book</x-primary-button>
                        <button type="button" class="btn btn-secondary" @click="type = ''" x-show="type">Change what happened</button>
                    </div>
                </div>
            </form>

            @push('scripts')
                <script>
                    function dailyRecordForm(initialType) {
                        return {
                            type: initialType || '',
                            get needsProject() {
                                return ['owner_payment', 'project_expense', 'worker_advance', 'worker_settlement'].indexOf(this.type) !== -1;
                            },
                            get needsWorker() {
                                return ['worker_advance', 'worker_settlement'].indexOf(this.type) !== -1;
                            },
                            get isIncome() {
                                return ['sale', 'owner_payment', 'other_income'].indexOf(this.type) !== -1;
                            },
                        };
                    }
                </script>
            @endpush
        @else
            <div class="rounded-xl border border-slate-200 bg-slate-50 p-5 text-sm text-slate-600">
                @if ($day->isClosed())
                    This day is closed. Print or download the day report for the till check. An admin can reopen the day if a correction is needed.
                @else
                    You can view this cash book. Only the cashier (or admin covering the till) records money here.
                @endif
            </div>
        @endif
