<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-xl font-semibold text-slate-800">{{ $materialRequest->request_no }}</h2>
                <p class="text-sm text-slate-500">{{ $materialRequest->project?->name }} · {{ $materialRequest->requester?->name }} · {{ $materialRequest->request_date->format('d/m/Y') }}</p>
            </div>
            <x-status-badge :status="$materialRequest->status" />
        </div>
    </x-slot>

    <div class="space-y-6">
        @if ($materialRequest->isPending())
            <form method="POST" action="{{ route('store.material-requests.approve', $materialRequest) }}" class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                @csrf
                <table class="min-w-full divide-y text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase text-slate-500">
                        <tr>
                            <th class="px-4 py-3">Product</th>
                            <th class="px-4 py-3 text-right">Requested</th>
                            <th class="px-4 py-3 text-right">In stock</th>
                            <th class="px-4 py-3 w-36">Approve qty</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @foreach ($materialRequest->items as $index => $item)
                            <tr>
                                <td class="px-4 py-3">
                                    <input type="hidden" name="items[{{ $index }}][id]" value="{{ $item->id }}">
                                    <p class="font-medium">{{ $item->product?->name }}</p>
                                    <p class="text-xs text-slate-500">{{ $item->product?->sku }}</p>
                                </td>
                                <td class="px-4 py-3 text-right">{{ rtrim(rtrim(number_format((float) $item->quantity_requested, 3, '.', ''), '0'), '.') }} {{ $item->product?->unit?->symbol }}</td>
                                <td class="px-4 py-3 text-right {{ $item->product && $item->product->stock_quantity < $item->quantity_requested ? 'text-rose-600 font-semibold' : '' }}">
                                    {{ $item->product?->formatQuantity() }}
                                </td>
                                <td class="px-4 py-3">
                                    <input type="number" name="items[{{ $index }}][quantity_approved]" step="0.001" min="0" max="{{ $item->quantity_requested }}" value="{{ $item->quantity_requested }}" class="w-full rounded-md border-gray-300 text-sm" required>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <div class="border-t px-4 py-4">
                    <button class="btn btn-success">Approve request</button>
                    <p class="mt-2 text-xs text-slate-500">Approving does not reduce stock. Issue materials after approval.</p>
                </div>
            </form>

            <form method="POST" action="{{ route('store.material-requests.reject', $materialRequest) }}" class="max-w-xl space-y-3 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                @csrf
                <h3 class="font-semibold">Reject request</h3>
                <textarea name="rejection_reason" rows="3" class="block w-full rounded-md border-gray-300 text-sm" placeholder="Reason" required></textarea>
                <button class="btn btn-danger-outline">Reject</button>
            </form>
        @else
            <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                <table class="min-w-full divide-y text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase text-slate-500">
                        <tr>
                            <th class="px-4 py-3">Product</th>
                            <th class="px-4 py-3 text-right">Requested</th>
                            <th class="px-4 py-3 text-right">Approved</th>
                            <th class="px-4 py-3 text-right">Issued</th>
                            <th class="px-4 py-3 text-right">Remaining</th>
                            <th class="px-4 py-3 text-right">In stock</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @foreach ($materialRequest->items as $item)
                            <tr>
                                <td class="px-4 py-3">{{ $item->product?->name }}</td>
                                <td class="px-4 py-3 text-right">{{ rtrim(rtrim(number_format((float) $item->quantity_requested, 3, '.', ''), '0'), '.') }}</td>
                                <td class="px-4 py-3 text-right">{{ rtrim(rtrim(number_format((float) $item->quantity_approved, 3, '.', ''), '0'), '.') }}</td>
                                <td class="px-4 py-3 text-right">{{ rtrim(rtrim(number_format((float) $item->quantity_issued, 3, '.', ''), '0'), '.') }}</td>
                                <td class="px-4 py-3 text-right font-semibold">{{ rtrim(rtrim(number_format($item->remainingToIssue(), 3, '.', ''), '0'), '.') }}</td>
                                <td class="px-4 py-3 text-right">{{ $item->product?->formatQuantity() }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($materialRequest->rejection_reason)
                <div class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">{{ $materialRequest->rejection_reason }}</div>
            @endif
        @endif

        @can('issue', $materialRequest)
            <form method="POST" action="{{ route('store.material-requests.issue', $materialRequest) }}" class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                @csrf
                <div class="border-b px-5 py-3">
                    <h3 class="font-semibold">Issue materials</h3>
                    <p class="text-xs text-slate-500">This reduces store stock and adds a material expense to the project.</p>
                </div>
                <div class="grid gap-4 px-5 py-4 sm:grid-cols-2">
                    <div>
                        <x-input-label for="issue_date" value="Issue date" />
                        <x-text-input id="issue_date" name="issue_date" type="date" class="mt-1 block w-full" :value="old('issue_date', now()->toDateString())" required />
                    </div>
                    <div>
                        <x-input-label for="notes" value="Notes" />
                        <x-text-input id="notes" name="notes" class="mt-1 block w-full" :value="old('notes')" />
                    </div>
                </div>
                <table class="min-w-full divide-y text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase text-slate-500">
                        <tr>
                            <th class="px-4 py-3">Product</th>
                            <th class="px-4 py-3 text-right">Remaining</th>
                            <th class="px-4 py-3 text-right">In stock</th>
                            <th class="px-4 py-3 w-36">Issue qty</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @foreach ($materialRequest->items as $index => $item)
                            <tr>
                                <td class="px-4 py-3">
                                    <input type="hidden" name="items[{{ $index }}][id]" value="{{ $item->id }}">
                                    {{ $item->product?->name }}
                                </td>
                                <td class="px-4 py-3 text-right">{{ rtrim(rtrim(number_format($item->remainingToIssue(), 3, '.', ''), '0'), '.') }}</td>
                                <td class="px-4 py-3 text-right {{ $item->product && $item->product->stock_quantity < $item->remainingToIssue() ? 'text-rose-600 font-semibold' : '' }}">
                                    {{ $item->product?->formatQuantity() }}
                                </td>
                                <td class="px-4 py-3">
                                    <input type="number" name="items[{{ $index }}][quantity]" step="0.001" min="0" max="{{ $item->remainingToIssue() }}" value="{{ old('items.'.$index.'.quantity', $item->remainingToIssue()) }}" class="w-full rounded-md border-gray-300 text-sm" required>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <div class="border-t px-5 py-4">
                    <button class="btn btn-primary">Issue & reduce stock</button>
                </div>
            </form>
        @endcan

        @if ($materialRequest->issues->isNotEmpty())
            <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b px-5 py-3 font-semibold">Issues</div>
                <table class="min-w-full text-sm">
                    <tbody class="divide-y">
                        @foreach ($materialRequest->issues as $issue)
                            <tr>
                                <td class="px-4 py-3">
                                    <a href="{{ route('store.material-issues.show', $issue) }}" class="font-medium text-amber-700">{{ $issue->issue_no }}</a>
                                </td>
                                <td class="px-4 py-3">{{ $issue->issue_date->format('d/m/Y') }}</td>
                                <td class="px-4 py-3">Rs. {{ number_format((float) $issue->total_cost, 2) }}</td>
                                <td class="px-4 py-3 text-slate-500">{{ $issue->issuer?->name }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</x-app-layout>
