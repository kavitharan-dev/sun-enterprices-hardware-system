@php
    $userRoles = auth()->user()->getRoleNames()->toArray();
@endphp

<aside
    class="fixed inset-y-0 left-0 z-50 w-64 bg-walnut-950 text-sun-50 transform transition-transform duration-200 ease-in-out lg:translate-x-0 lg:static lg:inset-auto lg:flex lg:flex-col lg:shrink-0"
    :class="{ '-translate-x-full': !sidebarOpen, 'translate-x-0': sidebarOpen }"
>
    <div class="relative overflow-hidden border-b border-white/10 px-4 py-5">
        <div class="pointer-events-none absolute -right-8 -top-10 h-28 w-28 rounded-full bg-sun-400/20 blur-2xl"></div>
        <div class="relative flex items-center gap-3">
            <x-brand-mark />
            <x-shop-brand />
        </div>
    </div>

    <nav class="flex-1 overflow-y-auto px-3 py-4 space-y-1">
        @foreach (config('navigation') as $item)
            @continue(! empty($item['roles']) && empty(array_intersect($item['roles'], $userRoles)))

            @if (($item['type'] ?? null) === 'heading')
                <p class="px-3 pt-4 pb-2 text-[11px] font-semibold uppercase tracking-[0.16em] text-sun-200/50">
                    {{ $item['label'] }}
                </p>
                @continue
            @endif

            @php
                $patterns = $item['active'] ?? $item['route'] ?? '#';
                $isActive = is_array($patterns)
                    ? request()->routeIs(...$patterns)
                    : ($patterns !== '#' && request()->routeIs($patterns));
            @endphp

            <a
                href="{{ ($item['coming_soon'] ?? false) ? '#' : (isset($item['route']) && $item['route'] !== '#' ? route($item['route']) : '#') }}"
                @class([
                    'flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition',
                    'bg-sun-400/15 text-sun-200 shadow-inner' => $isActive,
                    'text-sun-50/75 hover:bg-white/5 hover:text-sun-100' => ! $isActive,
                    'opacity-60 cursor-not-allowed' => ($item['coming_soon'] ?? false),
                ])
            >
                @include('layouts.partials.nav-icon', ['icon' => $item['icon'] ?? 'circle'])
                <span class="truncate">{{ $item['label'] }}</span>
                @if ($item['coming_soon'] ?? false)
                    <span class="ml-auto rounded bg-white/10 px-1.5 py-0.5 text-[10px] uppercase text-sun-100/60">Soon</span>
                @endif
            </a>
        @endforeach
    </nav>

    <div class="border-t border-white/10 p-4">
        <div class="rounded-xl bg-white/5 p-3 ring-1 ring-sun-200/10">
            <p class="truncate text-sm font-medium text-sun-50">{{ auth()->user()->name }}</p>
            <p class="truncate text-xs uppercase tracking-wider text-sun-200/60">{{ auth()->user()->getRoleNames()->first() }}</p>
        </div>
    </div>
</aside>
