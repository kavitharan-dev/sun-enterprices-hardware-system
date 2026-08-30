<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Receipt {{ $sale->invoice_no }}</title>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
            color: #111;
            margin: 0 auto;
            padding: 8px;
            width: 80mm;
            max-width: 100%;
            font-size: 12px;
            line-height: 1.35;
        }
        .center { text-align: center; }
        .muted { color: #444; }
        .bold { font-weight: 700; }
        .rule { border-top: 1px dashed #000; margin: 8px 0; }
        .row { display: flex; justify-content: space-between; gap: 8px; }
        .grow { flex: 1; }
        .right { text-align: right; }
        table { width: 100%; border-collapse: collapse; }
        td { vertical-align: top; padding: 2px 0; }
        .totals td { padding: 2px 0; }
        .no-print { margin-bottom: 12px; }
        @page { size: 80mm auto; margin: 0; }
        @media print {
            .no-print { display: none !important; }
            body { width: 72mm; padding: 0; }
        }
        @media (max-width: 420px) {
            body { width: 58mm; font-size: 11px; }
        }
    </style>
</head>
<body>
    <div class="no-print" style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;font-family:system-ui,sans-serif;">
        <button type="button" onclick="window.print()" style="padding:10px 14px;font-weight:700;background:#f59e0b;border:0;border-radius:8px;cursor:pointer;">Print thermal</button>
        <a href="{{ route('store.sales.pos') }}" style="padding:10px 14px;font-weight:700;background:#2f6b4f;color:#fff;border-radius:8px;text-decoration:none;">New POS sale</a>
        <a href="{{ route('store.sales.print', $sale) }}" style="padding:10px 14px;border:1px solid #ccc;border-radius:8px;text-decoration:none;color:#111;">A4 invoice</a>
        <a href="{{ route('store.sales.show', $sale) }}" style="padding:10px 14px;text-decoration:none;color:#555;">Sale details</a>
    </div>

    @if (session('success'))
        <p class="no-print" style="font-family:system-ui,sans-serif;padding:8px 12px;background:#ecfdf3;border:1px solid #abefc6;border-radius:8px;color:#085d3a;">{{ session('success') }}</p>
    @endif

    <div class="center">
        <div class="bold" style="font-size:14px;">{{ $company['name'] }}</div>
        <div class="muted">{{ $company['tagline'] }}</div>
        <div class="muted">{{ $company['address'] }}</div>
        <div class="muted">{{ $company['phone'] }}</div>
    </div>

    <div class="rule"></div>

    <div class="row"><span>Receipt</span><span class="bold">{{ $sale->invoice_no }}</span></div>
    <div class="row"><span>Date</span><span>{{ $sale->sale_date->format('d/m/Y') }}</span></div>
    <div class="row"><span>Time</span><span>{{ $sale->billedAt()->timezone(config('app.timezone'))->format('d/m/Y h:i A') }}</span></div>
    <div class="row"><span>Payment</span><span>{{ $sale->payment_status->label() }}</span></div>
    <div style="margin-top:4px;"><span class="muted">Bill to</span><br><span class="bold">{{ $sale->customerName() }}</span></div>

    <div class="rule"></div>

    <table>
        @foreach ($sale->items as $item)
            <tr>
                <td colspan="2" class="bold">{{ $item->product?->name }}</td>
            </tr>
            <tr>
                <td class="muted">{{ rtrim(rtrim(number_format((float) $item->quantity, 3, '.', ''), '0'), '.') }} {{ $item->product?->unit?->symbol }} × {{ number_format((float) $item->unit_price, 2) }}</td>
                <td class="right">{{ number_format((float) $item->subtotal, 2) }}</td>
            </tr>
        @endforeach
    </table>

    <div class="rule"></div>

    <table class="totals">
        <tr><td>Subtotal</td><td class="right">{{ number_format((float) $sale->subtotal, 2) }}</td></tr>
        @if ((float) $sale->discount > 0)
            <tr><td>Discount</td><td class="right">-{{ number_format((float) $sale->discount, 2) }}</td></tr>
        @endif
        @if ((float) $sale->tax > 0)
            <tr><td>Tax</td><td class="right">{{ number_format((float) $sale->tax, 2) }}</td></tr>
        @endif
        <tr><td class="bold">TOTAL</td><td class="right bold">{{ $company['currency'] }} {{ number_format((float) $sale->total, 2) }}</td></tr>
        <tr><td>Paid</td><td class="right">{{ number_format($sale->amountReceived(), 2) }}</td></tr>
        @if ($sale->changeDue() > 0)
            <tr><td class="bold">Change</td><td class="right bold">{{ number_format($sale->changeDue(), 2) }}</td></tr>
        @endif
        @if ((float) $sale->balance > 0)
            <tr><td class="bold">Balance</td><td class="right bold">{{ number_format((float) $sale->balance, 2) }}</td></tr>
        @endif
    </table>

    <div class="rule"></div>
    <div class="center muted">Thank you</div>

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
                window.setTimeout(function () { window.print(); }, 400);
            });
        </script>
    @endif
</body>
</html>
