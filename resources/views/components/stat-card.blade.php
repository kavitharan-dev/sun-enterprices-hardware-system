@props([
    'label',
    'value',
    'hint' => null,
    'color' => 'slate',
])

@php
    $colors = [
        'slate' => 'bg-white border-slate-200',
        'amber' => 'bg-amber-50 border-amber-200',
        'emerald' => 'bg-emerald-50 border-emerald-200',
        'rose' => 'bg-rose-50 border-rose-200',
        'sky' => 'bg-sky-50 border-sky-200',
    ];
@endphp

<div @class(['rounded-xl border p-5 shadow-sm', $colors[$color] ?? $colors['slate']])>
    <p class="text-sm font-medium text-slate-500">{{ $label }}</p>
    <p class="mt-2 text-2xl font-bold text-slate-900">{{ $value }}</p>
    @if ($hint)
        <p class="mt-1 text-xs text-slate-500">{{ $hint }}</p>
    @endif
</div>
