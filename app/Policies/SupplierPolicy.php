<?php

namespace App\Policies;

use App\Models\Supplier;
use App\Models\User;

class SupplierPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canManageStore();
    }

    public function view(User $user, Supplier $supplier): bool
    {
        return $user->canManageStore();
    }

    public function create(User $user): bool
    {
        return $user->canManageStore();
    }

    public function update(User $user, Supplier $supplier): bool
    {
        return $user->canManageStore();
    }

    public function delete(User $user, Supplier $supplier): bool
    {
        return $user->canManageStore();
    }
}
