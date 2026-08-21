<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Daily accounts {{ \Illuminate\Support\Carbon::parse($date)->format('d/m/Y') }}</title>
    <style>
        body { font-family: Arial, sans-serif; color: #1c1510; margin: 28px; font-size: 13px; }
        h1 { margin: 0 0 4px; font-size: 24px; color: #5c400c; }
        .muted { color: #7a550a; }
        .row { display: flex; justify-content: space-between; margin-bottom: 20px; padding-bottom: 14px; border-bottom: 3px solid #d4a017; }
        .cards { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; margin-bottom: 18px; }
        .card { border: 1px solid #e2e8f0; border-radius: 8px; padding: 10px 12px; }
        .card .label { font-size: 10px; text-transform: uppercase; color: #64748b; letter-spacing: .06em; }
        .card .value { margin-top: 4px; font-size: 18px; font-weight: 700; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { padding: 7px 6px; border-bottom: 1px solid #f6d48a; text-align: left; font-size: 12px; }
        th { background: #fff8eb; font-size: 10px; text-transform: uppercase; letter-spacing: .06em; }
        .right { text-align: right; }
        .closed { display: inline-block; padding: 2px 8px; border-radius: 999px; background: #ecfdf3; color: #085d3a; font-size: 11px; font-weight: 700; }
        .open { display: inline-block; padding: 2px 8px; border-radius: 999px; background: #fff7ed; color: #9a3412; font-size: 11px; font-weight: 700; }
        .sign { margin-top: 36px; display: grid; grid-template-columns: 1fr 1fr; gap: 40px; }
        .sign .line { margin-top: 40px; border-top: 1px solid #94a3b8; padding-top: 6px; color: #64748b; font-size: 12px; }
        @media print { .no-print { display: none !important; } body { margin: 12px; } }
    </style>
</head>
<body>
    @if (session('success'))
        <p class="no-print" style="margin:0 0 12px;padding:10px 14px;background:#ecfdf3;border:1px solid #abefc6;border-radius:8px;color:#085d3a;">{{ session('success') }}</p>
    @endif

    <div class="no-print" style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:18px;align-items:center;">
        <button type="button" onclick="window.print()" style="padding:10px 16px;font-weight:700;background:#f59e0b;color:#0f172a;border:0;border-radius:8px;cursor:pointer;">Print day report</button>
        <a href="{{ route('cashier.daily-accounts.pdf', ['date' => $date]) }}" style="padding:10px 16px;font-weight:700;background:#2f6b4f;color:#fff;border-radius:8px;text-decoration:none;">Download PDF</a>
        <a href="{{ route('cashier.daily-accounts.index', ['from' => $date, 'to' => $date]) }}" style="padding:10px 16px;font-weight:600;color:#5c400c;text-decoration:none;">Back to Daily Accounts</a>
    </div>

    <div class="row">
        <div>
            <h1>{{ $company['name'] }}</h1>
            <div class="muted" style="letter-spacing:.12em;text-transform:uppercase;font-size:11px;font-weight:600;">{{ $company['tagline'] }}</div>
            <div class="muted">{{ $company['address'] }}</div>
            <div class="muted">{{ $company['phone'] }} @if($company['email']) · {{ $company['email'] }} @endif</div>
        </div>
        <div class="right">
            <h1>DAILY TILL REPORT</h1>
            <div><strong>{{ \Illuminate\Support\Carbon::parse($date)->format('d/m/Y') }}</strong></div>
            <div style="margin-top:6px;">
                @if ($day->isClosed())
                    <span class="closed">Closed</span>
                @else
                    <span class="open">Open (interim)</span>
                @endif
            </div>
            <div class="muted" style="margin-top:6px;">Printed {{ $printed_at->format('d/m/Y H:i') }}</div>
        </div>
    </div>

    <div class="cards">
        <div class="card">
            <div class="label">Opening</div>
            <div class="value">{{ $company['currency'] }} {{ number_format($totals['opening'], 2) }}</div>
        </div>
        <div class="card">
            <div class="label">Income</div>
            <div class="value" style="color:#047857;">{{ $company['currency'] }} {{ number_format($totals['income'], 2) }}</div>
        </div>
        <div class="card">
            <div class="label">Expenses</div>
            <div class="value" style="color:#be123c;">{{ $company['currency'] }} {{ number_format($totals['expense'], 2) }}</div>
        </div>
        <div class="card">
            <div class="label">Closing</div>
            <div class="value">{{ $company['currency'] }} {{ number_format($day->isClosed() && $day->closing_balance !== null ? (float) $day->closing_balance : $totals['closing'], 2) }}</div>
        </div>
    </div>

    @if ($day->isClosed())
        <p style="margin:0 0 14px;">
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

    <table>
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
                    <td>
                        {{ $entry->project?->name ?? '—' }}
                        @if ($entry->worker)
                            <br><span class="muted">{{ $entry->worker->name }}</span>
                        @endif
                    </td>
                    <td class="right">{{ (float) $entry->income > 0 ? $company['currency'].' '.number_format((float) $entry->income, 2) : '—' }}</td>
                    <td class="right">{{ (float) $entry->expense > 0 ? $company['currency'].' '.number_format((float) $entry->expense, 2) : '—' }}</td>
                    <td class="right">{{ $company['currency'] }} {{ number_format($row['balance'], 2) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="muted" style="text-align:center;padding:18px;">No transactions on this day.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="sign">
        <div>
            <div class="line">Cashier signature</div>
        </div>
        <div>
            <div class="line">Checked by (owner / admin)</div>
        </div>
    </div>
</body>
</html>
