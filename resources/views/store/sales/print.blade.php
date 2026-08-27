<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $sale->invoice_no }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=playfair-display:700|outfit:400,600" rel="stylesheet" />
    <style>
        body { font-family: Outfit, Arial, sans-serif; color: #1c1510; margin: 32px; }
        h1 { margin: 0 0 4px; font-size: 28px; font-family: 'Playfair Display', Georgia, serif; color: #5c400c; }
        .muted { color: #7a550a; }
        .row { display: flex; justify-content: space-between; margin-bottom: 24px; padding-bottom: 16px; border-bottom: 3px solid #d4a017; }
        table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        th, td { padding: 8px; border-bottom: 1px solid #f6d48a; text-align: left; }
        th { background: #fff8eb; font-size: 11px; text-transform: uppercase; letter-spacing: .08em; }
        .right { text-align: right; }
        .totals { width: 280px; margin-left: auto; }
        .balance { color: #be123c; font-weight: bold; }
        @media print { .no-print { display: none; } }
    </style>
</head>
<body>
    @if (session('success'))
        <p class="no-print" style="margin:0 0 12px;padding:10px 14px;background:#ecfdf3;border:1px solid:#abefc6;border-radius:8px;color:#085d3a;">{{ session('success') }}</p>
    @endif
    <div class="no-print" style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:20px;align-items:center;">
        <button type="button" onclick="window.print()" style="padding:10px 16px;font-weight:700;background:#f59e0b;color:#0f172a;border:0;border-radius:8px;cursor:pointer;">Print bill</button>
        <a href="{{ route('store.sales.thermal', $sale) }}" style="padding:10px 16px;font-weight:700;background:#f59e0b;color:#0f172a;border-radius:8px;text-decoration:none;">Thermal print</a>
        <a href="{{ $nextUrl ?? route('store.sales.pos') }}" style="padding:10px 16px;font-weight:700;background:#2f6b4f;color:#fff;border-radius:8px;text-decoration:none;">New sale</a>
        <a href="{{ route('store.sales.bill', $sale) }}" style="padding:10px 16px;font-weight:700;background:#fff;color:#1c1510;border:1px solid #d4b896;border-radius:8px;text-decoration:none;">Stay on bill</a>
        <a href="{{ route('store.sales.index') }}" style="padding:10px 16px;font-weight:600;color:#5c400c;text-decoration:none;">All sales</a>
        @if (! empty($goToNewSale))
            <span style="font-size:13px;color:#7a550a;">Print dialog will open. After print, the next new sale screen opens.</span>
        @endif
    </div>

    <div class="row">
        <div>
            <h1>{{ $company['name'] }}</h1>
            <div class="muted" style="letter-spacing:.12em;text-transform:uppercase;font-size:11px;font-weight:600;">{{ $company['tagline'] }}</div>
            <div class="muted">{{ $company['address'] }}</div>
            <div class="muted">{{ $company['phone'] }} @if($company['email']) · {{ $company['email'] }} @endif</div>
        </div>
        <div class="right">
            <h1>INVOICE</h1>
            <div><strong>{{ $sale->invoice_no }}</strong></div>
            <div>Date: {{ $sale->sale_date->format('d/m/Y') }}</div>
            <div>Payment: {{ $sale->payment_status->label() }}</div>
        </div>
    </div>

    <p>
        <strong>Bill to</strong><br>
        {{ $sale->customerName() }}<br>
        @if ($sale->customer)
            {{ $sale->customer->phone }}<br>
            {{ $sale->customer->address }}
        @endif
    </p>

    <table>
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
        <tr><td><strong>Bill total</strong></td><td class="right"><strong>{{ $company['currency'] }} {{ number_format((float) $sale->total, 2) }}</strong></td></tr>
        <tr><td>Customer paid</td><td class="right">{{ $company['currency'] }} {{ number_format($sale->amountReceived(), 2) }}</td></tr>
        @if ($sale->changeDue() > 0)
            <tr><td><strong>Change to return</strong></td><td class="right"><strong>{{ $company['currency'] }} {{ number_format($sale->changeDue(), 2) }}</strong></td></tr>
        @endif
        <tr><td>Balance due</td><td class="right {{ $sale->balance > 0 ? 'balance' : '' }}">{{ $company['currency'] }} {{ number_format((float) $sale->balance, 2) }}</td></tr>
    </table>

    @if ($sale->payments->isNotEmpty())
        <p><strong>Payments</strong></p>
        <table>
            @foreach ($sale->payments as $payment)
                <tr>
                    <td>{{ $payment->payment_date->format('d/m/Y') }}</td>
                    <td>{{ $payment->payment_method->label() }}</td>
                    <td class="right">{{ $company['currency'] }} {{ number_format((float) $payment->amount, 2) }}</td>
                </tr>
            @endforeach
        </table>
    @endif

    @if (! empty($goToNewSale))
        <script>
            const nextSaleUrl = @json($nextUrl ?? route('store.sales.pos'));
            let movedOn = false;

            function goToNewSale() {
                if (movedOn) return;
                movedOn = true;
                window.location.href = nextSaleUrl;
            }

            window.addEventListener('afterprint', goToNewSale);
            window.addEventListener('load', function () {
                window.setTimeout(function () {
                    window.print();
                }, 400);
            });
        </script>
    @endif
</body>
</html>
