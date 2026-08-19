@php
    $links = [
        ['label' => 'Products', 'route' => 'store.products.index', 'match' => 'store.products.*'],
        ['label' => 'Categories', 'route' => 'store.categories.index', 'match' => 'store.categories.*'],
        ['label' => 'Brands', 'route' => 'store.brands.index', 'match' => 'store.brands.*'],
        ['label' => 'Units', 'route' => 'store.units.index', 'match' => 'store.units.*'],
    ];
@endphp

<div class="flex flex-wrap gap-2">
    @foreach ($links as $link)
        <a
            href="{{ route($link['route']) }}"
            @class([
                'btn btn-sm',
                'btn-dark' => request()->routeIs($link['match']),
                'btn-secondary' => ! request()->routeIs($link['match']),
            ])
        >
            {{ $link['label'] }}
        </a>
    @endforeach
</div>
