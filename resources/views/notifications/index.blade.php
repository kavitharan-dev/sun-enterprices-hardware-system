<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold text-slate-800">Notifications</h2>
    </x-slot>

    <form method="POST" action="{{ route('notifications.read-all') }}" class="mb-4">
        @csrf
        <button class="btn btn-secondary">Mark all as read</button>
    </form>

    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <ul class="divide-y">
            @forelse ($notifications as $notification)
                <li class="flex items-start justify-between gap-4 px-4 py-3 {{ $notification->read_at ? '' : 'bg-amber-50/60' }}">
                    <div>
                        <p class="font-medium text-slate-800">{{ $notification->data['title'] ?? 'Alert' }}</p>
                        <p class="text-sm text-slate-600">{{ $notification->data['body'] ?? '' }}</p>
                        <p class="mt-1 text-xs text-slate-400">{{ $notification->created_at->format('d/m/Y H:i') }}</p>
                    </div>
                    @unless ($notification->read_at)
                        <form method="POST" action="{{ route('notifications.read', $notification) }}">
                            @csrf
                            <button class="btn btn-secondary btn-sm">Open</button>
                        </form>
                    @endunless
                </li>
            @empty
                <li class="px-4 py-8 text-center text-slate-500">No notifications yet.</li>
            @endforelse
        </ul>
        @if ($notifications->hasPages())
            <div class="border-t px-4 py-3">{{ $notifications->links() }}</div>
        @endif
    </div>
</x-app-layout>
