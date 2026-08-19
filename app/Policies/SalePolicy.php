<?php

namespace App\Policies;

use App\Models\Sale;
use App\Models\User;

class SalePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canHandleSales();
    }

    public function view(User $user, Sale $sale): bool
    {
        return $user->canHandleSales();
    }

    public function create(User $user): bool
    {
        return $user->canHandleSales();
    }

    public function update(User $user, Sale $sale): bool
    {
        return $user->canHandleSales() && $sale->isDraft();
    }

    public function delete(User $user, Sale $sale): bool
    {
        return $user->canHandleSales() && $sale->isDraft();
    }

    public function complete(User $user, Sale $sale): bool
    {
        return $user->canConfirmTill() && $sale->isDraft();
    }

    public function pay(User $user, Sale $sale): bool
    {
        return $user->canConfirmTill() && $sale->isCompleted();
    }
}
