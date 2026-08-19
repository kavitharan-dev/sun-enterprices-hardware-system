@props([
    'tone' => 'light',
    'nameSize' => 'text-[1.05rem]',
])

@php
    $name = config('brand.name');
    $tagline = config('brand.tagline');
    $nameClass = $tone === 'dark' ? 'text-walnut-900' : 'text-sun-200';
    $tagClass = $tone === 'dark' ? 'text-sun-800' : 'text-sun-100/75';
@endphp

<div {{ $attributes->class('min-w-0') }}>
    <p class="brand-wordmark truncate {{ $nameSize }} {{ $nameClass }}">{{ $name }}</p>
    <p class="brand-tagline mt-0.5 truncate {{ $tagClass }}">{{ $tagline }}</p>
</div>
