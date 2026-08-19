<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <h2 class="text-xl font-semibold text-slate-800">Users</h2>
            <a href="{{ route('admin.users.create') }}" class="btn btn-primary">New user</a>
        </div>
    </x-slot>

    <form method="GET" class="mb-4 grid gap-3 sm:grid-cols-4">
        <input type="text" name="q" value="{{ request('q') }}" placeholder="Name, email or phone" class="rounded-lg border-slate-300 text-sm">
        <select name="role" class="rounded-lg border-slate-300 text-sm">
            <option value="">All roles</option>
            @foreach ($roles as $role)
                <option value="{{ $role }}" @selected(request('role') === $role)>{{ str_replace('_', ' ', $role) }}</option>
            @endforeach
        </select>
        <select name="status" class="rounded-lg border-slate-300 text-sm">
            <option value="">All statuses</option>
            <option value="active" @selected(request('status') === 'active')>Active</option>
            <option value="inactive" @selected(request('status') === 'inactive')>Inactive</option>
        </select>
        <button class="btn btn-dark">Filter</button>
    </form>

    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase text-slate-500">
                <tr>
                    <th class="px-4 py-3">User</th>
                    <th class="px-4 py-3">Role</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse ($users as $user)
                    <tr>
                        <td class="px-4 py-3">
                            <p class="font-medium">{{ $user->name }}</p>
                            <p class="text-xs text-slate-500">{{ $user->email }} @if($user->phone)· {{ $user->phone }}@endif</p>
                        </td>
                        <td class="px-4 py-3">{{ str_replace('_', ' ', $user->getRoleNames()->first() ?? '—') }}</td>
                        <td class="px-4 py-3">
                            <span class="rounded-full px-2 py-0.5 text-xs {{ $user->is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-600' }}">
                                {{ $user->is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right space-x-3">
                            <a href="{{ route('admin.users.edit', $user) }}" class="btn btn-secondary btn-sm">Edit</a>
                            @if ($user->id !== auth()->id())
                                <form method="POST" action="{{ route('admin.users.deactivate', $user) }}" class="inline" onsubmit="return confirm('Change this user\'s active status?')">
                                    @csrf
                                    <button class="btn btn-secondary btn-sm">{{ $user->is_active ? 'Deactivate' : 'Activate' }}</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-4 py-8 text-center text-slate-500">No users found.</td></tr>
                @endforelse
            </tbody>
        </table>
        @if ($users->hasPages())
            <div class="border-t px-4 py-3">{{ $users->links() }}</div>
        @endif
    </div>
</x-app-layout>
