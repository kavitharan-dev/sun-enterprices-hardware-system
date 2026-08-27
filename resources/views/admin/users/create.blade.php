<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold text-slate-800">{{ isset($user) ? 'Edit user' : 'New user' }}</h2>
    </x-slot>

    <form method="POST" action="{{ isset($user) ? route('admin.users.update', $user) : route('admin.users.store') }}" class="max-w-2xl space-y-4 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        @csrf
        @if (isset($user))
            @method('PUT')
        @endif

        <div>
            <x-input-label for="name" value="Name" />
            <x-text-input id="name" name="name" class="mt-1 block w-full" :value="old('name', $user->name ?? '')" required />
        </div>
        <div>
            <x-input-label for="email" value="Email" />
            <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email', $user->email ?? '')" required />
        </div>
        <div>
            <x-input-label for="phone" value="Phone" />
            <x-text-input id="phone" name="phone" class="mt-1 block w-full" :value="old('phone', $user->phone ?? '')" />
        </div>
        <div>
            <x-input-label for="role" value="Role" />
            <x-searchable-select
                name="role"
                :options="collect($roles)->map(fn ($r) => ['value' => $r, 'label' => str_replace('_', ' ', $r)])->values()"
                :value="(string) old('role', isset($user) ? $user->getRoleNames()->first() : '')"
                empty-label="Select role"
                :allow-empty="false"
                :required="true"
                placeholder="Search role…"
                class="mt-1"
            />
        </div>
        <div>
            <x-input-label for="password" :value="isset($user) ? 'New password (leave blank to keep)' : 'Password'" />
            <x-text-input id="password" name="password" type="password" class="mt-1 block w-full" autocomplete="new-password" @unless(isset($user)) required @endunless />
        </div>
        <div>
            <x-input-label for="password_confirmation" value="Confirm password" />
            <x-text-input id="password_confirmation" name="password_confirmation" type="password" class="mt-1 block w-full" />
        </div>
        @if (! isset($user) || $user->id !== auth()->id())
            <label class="flex items-center gap-2 text-sm text-slate-700">
                <input type="hidden" name="is_active" value="0">
                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $user->is_active ?? true)) class="rounded border-slate-300 text-amber-500">
                Active
            </label>
        @endif

        <div class="flex gap-3">
            <x-primary-button>{{ isset($user) ? 'Update user' : 'Create user' }}</x-primary-button>
            <a href="{{ route('admin.users.index') }}" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</x-app-layout>
