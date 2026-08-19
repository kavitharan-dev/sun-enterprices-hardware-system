<?php

namespace App\Policies;

use App\Models\Customer;
use App\Models\User;

class CustomerPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canHandleSales();
    }

    public function view(User $user, Customer $customer): bool
    {
        return $user->canHandleSales();
    }

    public function create(User $user): bool
    {
        return $user->canHandleSales();
    }

    public function update(User $user, Customer $customer): bool
    {
        return $user->canHandleSales();
    }

    public function delete(User $user, Customer $customer): bool
    {
        return $user->canManageStore();
    }
}
