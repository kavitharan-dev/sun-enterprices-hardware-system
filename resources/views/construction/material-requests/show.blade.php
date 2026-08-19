<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-xl font-semibold text-slate-800">{{ $materialRequest->request_no }}</h2>
                <p class="text-sm text-slate-500">{{ $materialRequest->project?->name }} · {{ $materialRequest->request_date->format('d/m/Y') }}</p>
            </div>
            <x-status-badge :status="$materialRequest->status" />
        </div>
    </x-slot>

    <div class="space-y-6">
        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <table class="min-w-full divide-y text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Product</th>
                        <th class="px-4 py-3 text-right">Requested</th>
                        <th class="px-4 py-3 text-right">Approved</th>
                        <th class="px-4 py-3 text-right">Issued</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @foreach ($materialRequest->items as $item)
                        <tr>
                            <td class="px-4 py-3">
                                <p class="font-medium">{{ $item->product?->name }}</p>
                                <p class="text-xs text-slate-500">{{ $item->product?->sku }} @if($item->notes)· {{ $item->notes }}@endif</p>
                            </td>
                            <td class="px-4 py-3 text-right">{{ rtrim(rtrim(number_format((float) $item->quantity_requested, 3, '.', ''), '0'), '.') }} {{ $item->product?->unit?->symbol }}</td>
                            <td class="px-4 py-3 text-right">{{ rtrim(rtrim(number_format((float) $item->quantity_approved, 3, '.', ''), '0'), '.') }}</td>
                            <td class="px-4 py-3 text-right">{{ rtrim(rtrim(number_format((float) $item->quantity_issued, 3, '.', ''), '0'), '.') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if ($materialRequest->rejection_reason)
            <div class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                Rejected: {{ $materialRequest->rejection_reason }}
            </div>
        @endif

        @if ($materialRequest->isDraft())
            <div class="flex flex-wrap gap-3">
                <form method="POST" action="{{ route('construction.material-requests.submit', $materialRequest) }}">
                    @csrf
                    <button class="btn btn-success">Submit for approval</button>
                </form>
                <a href="{{ route('construction.material-requests.edit', $materialRequest) }}" class="rounded-lg border px-4 py-2 text-sm">Edit draft</a>
                <form method="POST" action="{{ route('construction.material-requests.destroy', $materialRequest) }}" onsubmit="return confirm('Cancel this draft request?')">
                    @csrf @method('DELETE')
                    <button class="btn btn-danger-outline btn-sm">Cancel draft</button>
                </form>
            </div>
        @endif

        @if ($materialRequest->isApproved() && ! $materialRequest->isFullyIssued())
            <p class="text-sm text-slate-500">Approved by {{ $materialRequest->reviewer?->name }}. Waiting for the store to issue remaining materials.</p>
        @endif

        @if ($materialRequest->isFullyIssued())
            <p class="text-sm text-emerald-700">All approved materials have been issued. Stock has been reduced and a project expense recorded.</p>
        @endif

        @if ($materialRequest->issues->isNotEmpty())
            <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b px-5 py-3 font-semibold">Issues</div>
                <table class="min-w-full text-sm">
                    <tbody class="divide-y">
                        @foreach ($materialRequest->issues as $issue)
                            <tr>
                                <td class="px-4 py-3 font-medium">{{ $issue->issue_no }}</td>
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
