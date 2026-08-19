@props(['status'])

@php
    $map = [
        'draft' => 'bg-amber-100 text-amber-800',
        'completed' => 'bg-emerald-100 text-emerald-800',
        'cancelled' => 'bg-slate-100 text-slate-600',
        'active' => 'bg-emerald-100 text-emerald-800',
        'inactive' => 'bg-slate-100 text-slate-600',
        'low' => 'bg-rose-100 text-rose-800',
        'ok' => 'bg-emerald-100 text-emerald-800',
        'queued' => 'bg-sky-100 text-sky-800',
        'sending' => 'bg-amber-100 text-amber-800',
        'sent' => 'bg-emerald-100 text-emerald-800',
        'delivered' => 'bg-emerald-100 text-emerald-800',
        'failed' => 'bg-rose-100 text-rose-800',
        'skipped' => 'bg-slate-100 text-slate-600',
        'unpaid' => 'bg-rose-100 text-rose-800',
        'partial' => 'bg-amber-100 text-amber-800',
        'pending' => 'bg-amber-100 text-amber-800',
        'approved' => 'bg-emerald-100 text-emerald-800',
        'partially_approved' => 'bg-sky-100 text-sky-800',
        'rejected' => 'bg-rose-100 text-rose-800',
        'partially_issued' => 'bg-amber-100 text-amber-800',
        'issued' => 'bg-emerald-100 text-emerald-800',
        'planning' => 'bg-slate-100 text-slate-700',
        'on_hold' => 'bg-amber-100 text-amber-800',
    ];
    $key = $status instanceof \BackedEnum
        ? $status->value
        : strtolower((string) $status);
    $label = is_object($status) && method_exists($status, 'label')
        ? $status->label()
        : ucfirst(str_replace('_', ' ', $key));
@endphp

<span @class(['inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium', $map[$key] ?? 'bg-slate-100 text-slate-700'])>
    {{ $label }}
</span>
