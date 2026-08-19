@props(['no'])

@if ($no)
    <span {{ $attributes->class(['font-mono text-xs font-semibold text-slate-600']) }}>{{ $no }}</span>
@else
    <span {{ $attributes->class(['text-xs text-slate-400']) }}>—</span>
@endif
