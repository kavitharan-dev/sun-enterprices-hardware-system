@php
    $currency = $company['currency'] ?? 'Rs.';
@endphp
<div class="flex justify-between"><span>Subtotal</span><span>{{ $currency }} {{ number_format((float) $sale->subtotal, 2) }}</span></div>
<div class="flex justify-between"><span>Discount</span><span>{{ $currency }} {{ number_format((float) $sale->discount, 2) }}</span></div>
<div class="flex justify-between"><span>Tax</span><span>{{ $currency }} {{ number_format((float) $sale->tax, 2) }}</span></div>
<div class="flex justify-between text-base font-bold"><span>Bill total</span><span>{{ $currency }} {{ number_format((float) $sale->total, 2) }}</span></div>
<div class="flex justify-between"><span>Customer paid</span><span>{{ $currency }} {{ number_format($sale->amountReceived(), 2) }}</span></div>
@if ($sale->changeDue() > 0)
    <div class="flex justify-between font-semibold text-emerald-700"><span>Change to return</span><span>{{ $currency }} {{ number_format($sale->changeDue(), 2) }}</span></div>
@endif
<div class="flex justify-between font-semibold {{ $sale->balance > 0 ? 'text-rose-600' : 'text-emerald-700' }}">
    <span>Balance due</span>
    <span>{{ $currency }} {{ number_format((float) $sale->balance, 2) }}</span>
</div>
