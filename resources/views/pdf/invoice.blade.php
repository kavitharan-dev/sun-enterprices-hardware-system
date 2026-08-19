<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $sale->invoice_no }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #1c1510; }
        h1 { font-size: 22px; margin: 0 0 4px; color: #5c400c; letter-spacing: 0.5px; }
        .muted { color: #64748b; }
        .header { width: 100%; margin-bottom: 24px; }
        .header td { vertical-align: top; }
        .right { text-align: right; }
        table.items { width: 100%; border-collapse: collapse; margin-top: 16px; }
        table.items th { background: #f1f5f9; text-align: left; padding: 8px; font-size: 10px; text-transform: uppercase; }
        table.items td { border-bottom: 1px solid #e2e8f0; padding: 8px; }
        .totals { width: 280px; margin-left: auto; margin-top: 16px; }
        .totals td { padding: 4px 0; }
        .grand { font-size: 14px; font-weight: bold; }
        .balance { color: #be123c; font-weight: bold; }
    </style>
</head>
<body>
    <table class="header">
        <tr>
            <td>
                @if (! empty($company['logo']) && file_exists(storage_path('app/public/'.$company['logo'])))
                    <img src="{{ storage_path('app/public/'.$company['logo']) }}" alt="" style="max-height: 48px; margin-bottom: 8px;">
                @endif
                <h1>{{ $company['name'] }}</h1>
                <div class="muted" style="letter-spacing:1.5px;text-transform:uppercase;font-size:10px;">{{ $company['tagline'] }}</div>
                <div class="muted">{{ $company['address'] }}</div>
                <div class="muted">{{ $company['phone'] }} @if($company['email']) · {{ $company['email'] }} @endif</div>
            </td>
            <td class="right">
                <h1>INVOICE</h1>
                <div><strong>{{ $sale->invoice_no }}</strong></div>
                <div>Date: {{ $sale->sale_date->format('d/m/Y') }}</div>
                <div>Payment: {{ $sale->payment_status->label() }}</div>
            </td>
        </tr>
    </table>

    <p>
        <strong>Bill to</strong><br>
        {{ $sale->customerName() }}<br>
        @if ($sale->customer)
            {{ $sale->customer->phone }}<br>
            {{ $sale->customer->address }}
        @endif
    </p>

    <table class="items">
        <thead>
            <tr>
                <th>Product</th>
                <th class="right">Qty</th>
                <th class="right">Unit price</th>
                <th class="right">Discount</th>
                <th class="right">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($sale->items as $item)
                <tr>
                    <td>{{ $item->product?->name }}<br><span class="muted">{{ $item->product?->sku }}</span></td>
                    <td class="right">{{ rtrim(rtrim(number_format((float) $item->quantity, 3, '.', ''), '0'), '.') }} {{ $item->product?->unit?->symbol }}</td>
                    <td class="right">{{ $company['currency'] }} {{ number_format((float) $item->unit_price, 2) }}</td>
                    <td class="right">{{ $company['currency'] }} {{ number_format((float) $item->discount, 2) }}</td>
                    <td class="right">{{ $company['currency'] }} {{ number_format((float) $item->subtotal, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals">
        <tr><td>Subtotal</td><td class="right">{{ $company['currency'] }} {{ number_format((float) $sale->subtotal, 2) }}</td></tr>
        <tr><td>Discount</td><td class="right">{{ $company['currency'] }} {{ number_format((float) $sale->discount, 2) }}</td></tr>
        <tr><td>Tax</td><td class="right">{{ $company['currency'] }} {{ number_format((float) $sale->tax, 2) }}</td></tr>
        <tr class="grand"><td>Bill total</td><td class="right">{{ $company['currency'] }} {{ number_format((float) $sale->total, 2) }}</td></tr>
        <tr><td>Customer paid</td><td class="right">{{ $company['currency'] }} {{ number_format($sale->amountReceived(), 2) }}</td></tr>
        @if ($sale->changeDue() > 0)
            <tr><td>Change to return</td><td class="right">{{ $company['currency'] }} {{ number_format($sale->changeDue(), 2) }}</td></tr>
        @endif
        <tr><td>Balance due</td><td class="right {{ $sale->balance > 0 ? 'balance' : '' }}">{{ $company['currency'] }} {{ number_format((float) $sale->balance, 2) }}</td></tr>
    </table>

    @if ($sale->payments->isNotEmpty())
        <p><strong>Payments</strong></p>
        <table class="items">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Method</th>
                    <th class="right">Amount</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($sale->payments as $payment)
                    <tr>
                        <td>{{ $payment->payment_date->format('d/m/Y') }}</td>
                        <td>{{ $payment->payment_method->label() }}</td>
                        <td class="right">{{ $company['currency'] }} {{ number_format((float) $payment->amount, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @if ($sale->notes)
        <p class="muted">Notes: {{ $sale->notes }}</p>
    @endif

    <p class="muted" style="margin-top: 32px;">Thank you for your business.</p>
</body>
</html>
