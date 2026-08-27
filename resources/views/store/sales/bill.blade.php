<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-xl font-semibold text-slate-800">Bill {{ $sale->invoice_no }}</h2>
                <p class="text-sm text-slate-500">{{ $sale->customerName() }} · {{ $sale->sale_date->format('d/m/Y') }}</p>
            </div>
            <div class="no-print flex flex-wrap gap-2">
                <a href="{{ route('store.sales.pos') }}" class="btn btn-success">New sale</a>
                <a href="{{ route('store.sales.thermal', $sale) }}" class="btn btn-primary">Thermal print</a>
                <button type="button" onclick="window.print()" class="btn btn-primary">Print bill</button>
                <a href="{{ route('store.sales.print', $sale) }}" class="btn btn-dark">Printer page</a>
                <a href="{{ route('store.sales.invoice', $sale) }}" target="_blank" class="btn btn-secondary">PDF</a>
                <a href="{{ route('store.sales.show', $sale) }}" class="btn btn-secondary">Sale details</a>
                <a href="{{ route('store.sales.index') }}" class="btn btn-secondary">All sales</a>
            </div>
        </div>
    </x-slot>

    @push('styles')
        <style>
            @media print {
                aside, header.sticky, .no-print, [x-cloak] { display: none !important; }
                .lg\:flex { display: block !important; }
                main { padding: 0 !important; }
                body { background: #fff !important; }
            }
        </style>
    @endpush

    <div class="mx-auto max-w-3xl rounded-xl border border-slate-200 bg-white p-6 shadow-sm print:border-0 print:shadow-none">
        <div class="flex flex-col gap-4 sm:flex-row sm:justify-between">
            <div>
                <h1 class="brand-wordmark text-3xl text-walnut-900">{{ $company['name'] }}</h1>
                <p class="brand-tagline mt-1 text-sun-800">{{ $company['tagline'] }}</p>
                <p class="text-sm text-slate-500">{{ $company['address'] }}</p>
                <p class="text-sm text-slate-500">{{ $company['phone'] }} @if($company['email']) · {{ $company['email'] }}@endif</p>
            </div>
            <div class="sm:text-right">
                <p class="text-lg font-bold">INVOICE</p>
                <p class="font-mono font-semibold">{{ $sale->invoice_no }}</p>
                <p class="text-sm">Date: {{ $sale->sale_date->format('d/m/Y') }}</p>
                <p class="text-sm">Payment: {{ $sale->payment_status->label() }}</p>
            </div>
        </div>

        <div class="mt-6">
            <p class="text-xs font-semibold uppercase text-slate-500">Bill to</p>
            <p class="font-semibold">{{ $sale->customerName() }}</p>
            @if ($sale->customer)
                <p class="text-sm text-slate-600">{{ $sale->customer->phone }}</p>
                <p class="text-sm text-slate-600">{{ $sale->customer->address }}</p>
            @endif
        </div>

        <table class="mt-6 min-w-full text-sm">
            <thead class="border-b text-left text-xs font-semibold uppercase text-slate-500">
                <tr>
                    <th class="py-2">Product</th>
                    <th class="py-2 text-right">Qty</th>
                    <th class="py-2 text-right">Unit price</th>
                    <th class="py-2 text-right">Discount</th>
                    <th class="py-2 text-right">Total</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @foreach ($sale->items as $item)
                    <tr>
                        <td class="py-2">
                            <p>{{ $item->product?->name }}</p>
                            <p class="text-xs text-slate-500">{{ $item->product?->sku }}</p>
                        </td>
                        <td class="py-2 text-right">{{ rtrim(rtrim(number_format((float) $item->quantity, 3, '.', ''), '0'), '.') }} {{ $item->product?->unit?->symbol }}</td>
                        <td class="py-2 text-right">{{ $company['currency'] }} {{ number_format((float) $item->unit_price, 2) }}</td>
                        <td class="py-2 text-right">{{ $company['currency'] }} {{ number_format((float) $item->discount, 2) }}</td>
                        <td class="py-2 text-right">{{ $company['currency'] }} {{ number_format((float) $item->subtotal, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="mt-4 ml-auto w-full max-w-xs space-y-1 text-sm">
            @include('store.sales.partials.settlement', ['sale' => $sale, 'company' => $company])
        </div>

        @if ($sale->payments->isNotEmpty())
            <p class="mt-6 text-sm font-semibold">Payments</p>
            <table class="min-w-full text-sm">
                @foreach ($sale->payments as $payment)
                    <tr class="border-t">
                        <td class="py-2">{{ $payment->payment_date->format('d/m/Y') }}</td>
                        <td class="py-2">{{ $payment->payment_method->label() }}</td>
                        <td class="py-2 text-right">{{ $company['currency'] }} {{ number_format((float) $payment->amount, 2) }}</td>
                    </tr>
                @endforeach
            </table>
        @endif

        <p class="mt-8 text-sm text-slate-500">Thank you for your business.</p>
    </div>
</x-app-layout>
