<?php

namespace App\Policies;

use App\Models\Purchase;
use App\Models\User;

class PurchasePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canManageStore();
    }

    public function view(User $user, Purchase $purchase): bool
    {
        return $user->canManageStore();
    }

    public function create(User $user): bool
    {
        return $user->canManageStore();
    }

    public function update(User $user, Purchase $purchase): bool
    {
        return $user->canManageStore() && $purchase->isDraft();
    }

    public function delete(User $user, Purchase $purchase): bool
    {
        return $user->canManageStore() && $purchase->isDraft();
    }

    public function complete(User $user, Purchase $purchase): bool
    {
        return $user->canManageStore() && $purchase->isDraft();
    }
}
