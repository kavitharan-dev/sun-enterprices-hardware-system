@php
    $unreadNotifications = auth()->user()->unreadNotifications()->latest()->limit(8)->get();
    $unreadCount = auth()->user()->unreadNotifications()->count();
@endphp

<header class="sticky top-0 z-30 flex h-[4.25rem] items-center gap-4 border-b border-sun-200/70 bg-white/85 px-4 backdrop-blur sm:px-6 lg:px-8">
        <button
            type="button"
            class="btn btn-secondary btn-sm lg:hidden"
            @click="sidebarOpen = !sidebarOpen"
        >
        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
        </svg>
    </button>

    <div class="min-w-0 flex-1">
        <x-shop-brand tone="dark" name-size="text-lg" />
        <p class="truncate text-sm text-walnut-800/70">Welcome back, {{ auth()->user()->name }}</p>
    </div>

    <div class="flex items-center gap-2">
        <div class="relative" x-data="{ open: false }">
            <button type="button" @click="open = !open" class="relative rounded-full border border-sun-200 bg-white p-2 text-walnut-800 hover:bg-sun-50">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0" />
                </svg>
                @if ($unreadCount > 0)
                    <span class="absolute -right-1 -top-1 inline-flex min-w-[1.1rem] items-center justify-center rounded-full bg-rose-600 px-1 text-[10px] font-bold text-white">{{ $unreadCount > 9 ? '9+' : $unreadCount }}</span>
                @endif
            </button>
            <div x-show="open" @click.outside="open = false" x-cloak class="absolute right-0 mt-2 w-80 rounded-2xl border border-sun-200 bg-white py-2 shadow-xl">
                <div class="flex items-center justify-between px-3 pb-2">
                    <p class="text-sm font-semibold">Notifications</p>
                    @if ($unreadCount > 0)
                        <form method="POST" action="{{ route('notifications.read-all') }}">
                            @csrf
                            <button class="btn btn-secondary btn-sm">Mark all read</button>
                        </form>
                    @endif
                </div>
                @forelse ($unreadNotifications as $notification)
                    <form method="POST" action="{{ route('notifications.read', $notification) }}">
                        @csrf
                        <button class="block w-full px-3 py-2 text-left hover:bg-sun-50">
                            <p class="text-sm font-medium text-walnut-900">{{ $notification->data['title'] ?? 'Alert' }}</p>
                            <p class="text-xs text-walnut-800/70">{{ \Illuminate\Support\Str::limit($notification->data['body'] ?? '', 80) }}</p>
                        </button>
                    </form>
                @empty
                    <p class="px-3 py-4 text-sm text-walnut-800/70">No unread notifications.</p>
                @endforelse
                <a href="{{ route('notifications.index') }}" class="btn btn-secondary btn-sm mx-3 my-2">View all</a>
            </div>
        </div>

        <span class="hidden rounded-full bg-sun-100 px-3 py-1 text-xs font-medium text-sun-800 sm:inline">
            {{ ucfirst(str_replace('_', ' ', auth()->user()->getRoleNames()->first() ?? 'user')) }}
        </span>

        <x-dropdown align="right" width="48">
            <x-slot name="trigger">
                <button class="btn btn-secondary btn-sm">
                    <span class="hidden sm:inline">Account</span>
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                    </svg>
                </button>
            </x-slot>

            <x-slot name="content">
                <x-dropdown-link :href="route('profile.edit')">
                    {{ __('Profile') }}
                </x-dropdown-link>

                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <x-dropdown-link :href="route('logout')"
                        onclick="event.preventDefault(); this.closest('form').submit();">
                        {{ __('Log Out') }}
                    </x-dropdown-link>
                </form>
            </x-slot>
        </x-dropdown>
    </div>
</header>
