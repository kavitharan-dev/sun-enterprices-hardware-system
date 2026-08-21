<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Daily accounts {{ \Illuminate\Support\Carbon::parse($date)->format('d/m/Y') }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1c1510; }
        h1 { font-size: 18px; margin: 0 0 4px; color: #5c400c; }
        .muted { color: #64748b; }
        .header { width: 100%; margin-bottom: 16px; }
        .header td { vertical-align: top; }
        .right { text-align: right; }
        .cards { width: 100%; margin-bottom: 12px; }
        .cards td { width: 25%; border: 1px solid #e2e8f0; padding: 8px; }
        .label { font-size: 9px; text-transform: uppercase; color: #64748b; }
        .value { margin-top: 3px; font-size: 13px; font-weight: bold; }
        table.items { width: 100%; border-collapse: collapse; margin-top: 8px; }
        table.items th { background: #f1f5f9; text-align: left; padding: 6px; font-size: 9px; text-transform: uppercase; }
        table.items td { border-bottom: 1px solid #e2e8f0; padding: 6px; }
        .sign { width: 100%; margin-top: 40px; }
        .sign td { width: 50%; padding-top: 36px; border-top: 1px solid #94a3b8; color: #64748b; }
    </style>
</head>
<body>
    <table class="header">
        <tr>
            <td>
                <h1>{{ $company['name'] }}</h1>
                <div class="muted">{{ $company['tagline'] }}</div>
                <div class="muted">{{ $company['address'] }}</div>
                <div class="muted">{{ $company['phone'] }} @if($company['email']) · {{ $company['email'] }} @endif</div>
            </td>
            <td class="right">
                <h1>DAILY TILL REPORT</h1>
                <div><strong>{{ \Illuminate\Support\Carbon::parse($date)->format('d/m/Y') }}</strong></div>
                <div>{{ $day->isClosed() ? 'Closed' : 'Open (interim)' }}</div>
                <div class="muted">Printed {{ $printed_at->format('d/m/Y H:i') }}</div>
            </td>
        </tr>
    </table>

    <table class="cards" cellspacing="6">
        <tr>
            <td>
                <div class="label">Opening</div>
                <div class="value">{{ $company['currency'] }} {{ number_format($totals['opening'], 2) }}</div>
            </td>
            <td>
                <div class="label">Income</div>
                <div class="value">{{ $company['currency'] }} {{ number_format($totals['income'], 2) }}</div>
            </td>
            <td>
                <div class="label">Expenses</div>
                <div class="value">{{ $company['currency'] }} {{ number_format($totals['expense'], 2) }}</div>
            </td>
            <td>
                <div class="label">Closing</div>
                <div class="value">{{ $company['currency'] }} {{ number_format($day->isClosed() && $day->closing_balance !== null ? (float) $day->closing_balance : $totals['closing'], 2) }}</div>
            </td>
        </tr>
    </table>

    @if ($day->isClosed())
        <p>
            Closed by {{ $day->closer?->name ?? '—' }}
            @if ($day->closed_at) at {{ $day->closed_at->format('d/m/Y H:i') }} @endif
            @if ($day->counted_cash !== null)
                · Cash counted: {{ $company['currency'] }} {{ number_format((float) $day->counted_cash, 2) }}
                @if ($variance !== null)
                    · Variance: {{ $company['currency'] }} {{ number_format($variance, 2) }}
                @endif
            @endif
            @if ($day->close_notes)
                · Note: {{ $day->close_notes }}
            @endif
        </p>
    @endif

    <table class="items">
        <thead>
            <tr>
                <th>Txn</th>
                <th>Type</th>
                <th>Description</th>
                <th>Project / Worker</th>
                <th class="right">Income</th>
                <th class="right">Expense</th>
                <th class="right">Balance</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td colspan="4" class="muted">Opening balance</td>
                <td class="right muted">—</td>
                <td class="right muted">—</td>
                <td class="right"><strong>{{ $company['currency'] }} {{ number_format($totals['opening'], 2) }}</strong></td>
            </tr>
            @forelse ($rows as $row)
                @php $entry = $row['entry']; @endphp
                <tr>
                    <td>{{ $entry->transaction_no }}</td>
                    <td>{{ $entry->type->label() }}</td>
                    <td>{{ $entry->description }}</td>
                    <td>{{ $entry->project?->name ?? '—' }}@if($entry->worker) / {{ $entry->worker->name }}@endif</td>
                    <td class="right">{{ (float) $entry->income > 0 ? number_format((float) $entry->income, 2) : '—' }}</td>
                    <td class="right">{{ (float) $entry->expense > 0 ? number_format((float) $entry->expense, 2) : '—' }}</td>
                    <td class="right">{{ number_format($row['balance'], 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="muted" style="text-align:center;">No transactions on this day.</td></tr>
            @endforelse
        </tbody>
    </table>

    <table class="sign">
        <tr>
            <td>Cashier signature</td>
            <td style="padding-left: 40px;">Checked by (owner / admin)</td>
        </tr>
    </table>
</body>
</html>
