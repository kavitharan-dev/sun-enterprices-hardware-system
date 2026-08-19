<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-xl font-semibold text-slate-800">{{ $materialIssue->issue_no }}</h2>
                <p class="text-sm text-slate-500">{{ $materialIssue->project?->name }} · {{ $materialIssue->issue_date->format('d/m/Y') }}</p>
            </div>
            <x-status-badge :status="$materialIssue->status" />
        </div>
    </x-slot>

    <div class="space-y-6">
        <div class="grid gap-4 sm:grid-cols-3">
            <div class="rounded-xl border border-slate-200 bg-white p-4 text-sm shadow-sm">
                <p class="text-slate-500">Request</p>
                <p class="font-semibold">
                    @if ($materialIssue->materialRequest)
                        <a href="{{ route('store.material-requests.show', $materialIssue->materialRequest) }}" class="text-amber-700">{{ $materialIssue->materialRequest->request_no }}</a>
                    @else
                        —
                    @endif
                </p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-4 text-sm shadow-sm">
                <p class="text-slate-500">Issued by</p>
                <p class="font-semibold">{{ $materialIssue->issuer?->name }}</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-4 text-sm shadow-sm">
                <p class="text-slate-500">Total cost</p>
                <p class="font-semibold">Rs. {{ number_format((float) $materialIssue->total_cost, 2) }}</p>
            </div>
        </div>

        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <table class="min-w-full divide-y text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Product</th>
                        <th class="px-4 py-3 text-right">Qty</th>
                        <th class="px-4 py-3 text-right">Unit cost</th>
                        <th class="px-4 py-3 text-right">Subtotal</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @foreach ($materialIssue->items as $item)
                        <tr>
                            <td class="px-4 py-3">
                                <p class="font-medium">{{ $item->product?->name }}</p>
                                <p class="text-xs text-slate-500">{{ $item->product?->sku }}</p>
                            </td>
                            <td class="px-4 py-3 text-right">{{ rtrim(rtrim(number_format((float) $item->quantity, 3, '.', ''), '0'), '.') }} {{ $item->product?->unit?->symbol }}</td>
                            <td class="px-4 py-3 text-right">Rs. {{ number_format((float) $item->unit_cost, 2) }}</td>
                            <td class="px-4 py-3 text-right">Rs. {{ number_format((float) $item->subtotal, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if ($materialIssue->notes)
            <p class="text-sm text-slate-600">{{ $materialIssue->notes }}</p>
        @endif

        <p class="text-sm text-slate-500">Stock was reduced with a <code>material_issue_out</code> movement. A material expense was posted to the project.</p>
        <a href="{{ route('store.material-issues.index') }}" class="btn btn-secondary btn-sm">All issues</a>
    </div>
</x-app-layout>
