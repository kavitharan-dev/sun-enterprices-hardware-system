@props([
    'variant' => 'primary',
    'href' => null,
    'size' => 'md',
])

@php
    $variantClass = match ($variant) {
        'secondary' => 'btn-secondary',
        'dark' => 'btn-dark',
        'success' => 'btn-success',
        'danger' => 'btn-danger',
        'danger-outline' => 'btn-danger-outline',
        default => 'btn-primary',
    };

    $classes = 'btn '.$variantClass.($size === 'sm' ? ' btn-sm' : '');
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>
        {{ $slot }}
    </a>
@else
    <button {{ $attributes->merge(['type' => 'submit', 'class' => $classes]) }}>
        {{ $slot }}
    </button>
@endif
