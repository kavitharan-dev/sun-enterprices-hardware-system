<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UserRequest;
use App\Models\User;
use App\Traits\LogsActivity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    use LogsActivity;

    public function index(Request $request): View
    {
        $this->authorize('viewAny', User::class);

        $users = User::query()
            ->with('roles')
            ->when($request->filled('q'), function ($query) use ($request) {
                $term = '%'.$request->string('q').'%';
                $query->where(function ($inner) use ($term) {
                    $inner->where('name', 'like', $term)
                        ->orWhere('email', 'like', $term)
                        ->orWhere('phone', 'like', $term);
                });
            })
            ->when($request->filled('role'), fn ($query) => $query->role($request->string('role')))
            ->when($request->filled('status'), function ($query) use ($request) {
                $query->where('is_active', $request->string('status') === 'active');
            })
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        $roles = Role::query()->orderBy('name')->pluck('name');

        return view('admin.users.index', compact('users', 'roles'));
    }

    public function create(): View
    {
        $this->authorize('create', User::class);

        return view('admin.users.create', ['roles' => $this->roleOptions()]);
    }

    public function store(UserRequest $request): RedirectResponse
    {
        $user = User::query()->create([
            'name' => $request->string('name')->toString(),
            'email' => $request->string('email')->toString(),
            'phone' => $request->input('phone') ?: null,
            'password' => $request->string('password')->toString(),
            'is_active' => $request->boolean('is_active', true),
            'email_verified_at' => now(),
        ]);

        $user->syncRoles([$request->string('role')->toString()]);
        $this->logActivity('created', 'User', "Created user {$user->email} as {$request->string('role')}", $user);

        return redirect()->route('admin.users.index')->with('success', 'User created.');
    }

    public function edit(User $user): View
    {
        $this->authorize('update', $user);

        return view('admin.users.edit', [
            'user' => $user,
            'roles' => $this->roleOptions(),
        ]);
    }

    public function update(UserRequest $request, User $user): RedirectResponse
    {
        $this->authorize('update', $user);

        $role = $request->string('role')->toString();

        if ($this->wouldRemoveLastAdmin($user, $role, $request->boolean('is_active', $user->is_active))) {
            return back()->with('error', 'The last active administrator cannot be removed or deactivated.');
        }

        $payload = [
            'name' => $request->string('name')->toString(),
            'email' => $request->string('email')->toString(),
            'phone' => $request->input('phone') ?: null,
            'is_active' => $user->id === $request->user()->id
                ? true
                : $request->boolean('is_active', true),
        ];

        if ($request->filled('password')) {
            $payload['password'] = $request->string('password')->toString();
        }

        $user->update($payload);
        $user->syncRoles([$role]);
        $this->logActivity('updated', 'User', "Updated user {$user->email}", $user);

        return redirect()->route('admin.users.index')->with('success', 'User updated.');
    }

    public function deactivate(User $user): RedirectResponse
    {
        $this->authorize('deactivate', $user);

        if ($this->wouldRemoveLastAdmin($user, $user->getRoleNames()->first() ?? '', false)) {
            return back()->with('error', 'The last active administrator cannot be deactivated.');
        }

        $user->update(['is_active' => ! $user->is_active]);
        $state = $user->is_active ? 'activated' : 'deactivated';
        $this->logActivity($state, 'User', ucfirst($state)." user {$user->email}", $user);

        return back()->with('success', "User {$state}.");
    }

    /**
     * @return list<string>
     */
    private function roleOptions(): array
    {
        return ['admin', 'store_manager', 'cashier', 'site_manager'];
    }

    private function wouldRemoveLastAdmin(User $user, string $newRole, bool $willBeActive): bool
    {
        if (! $user->hasRole('admin')) {
            return false;
        }

        $stillAdmin = $newRole === 'admin' && $willBeActive;
        if ($stillAdmin) {
            return false;
        }

        return User::query()
            ->role('admin')
            ->where('is_active', true)
            ->where('id', '!=', $user->id)
            ->doesntExist();
    }
}
