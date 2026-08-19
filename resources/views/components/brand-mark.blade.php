@props([
    'size' => 'md',
])

@php
    $box = $size === 'lg' ? 'h-14 w-14' : ($size === 'sm' ? 'h-9 w-9' : 'h-11 w-11');
    $icon = $size === 'lg' ? 'h-8 w-8' : ($size === 'sm' ? 'h-5 w-5' : 'h-6 w-6');
@endphp

<div {{ $attributes->merge(['class' => "brand-mark {$box}"]) }}>
    <svg class="{{ $icon }}" viewBox="0 0 48 48" fill="none" aria-hidden="true">
        <circle cx="24" cy="24" r="8" fill="currentColor"/>
        <path stroke="currentColor" stroke-width="2.4" stroke-linecap="round" d="M24 4v6M24 38v6M4 24h6M38 24h6M9.2 9.2l4.2 4.2M34.6 34.6l4.2 4.2M9.2 38.8l4.2-4.2M34.6 13.4l4.2-4.2"/>
    </svg>
</div>
