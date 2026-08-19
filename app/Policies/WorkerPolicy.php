<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Worker;

class WorkerPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canManageWorkers();
    }

    public function view(User $user, Worker $worker): bool
    {
        return $user->canManageWorkers();
    }

    public function create(User $user): bool
    {
        return $user->canManageWorkers();
    }

    public function update(User $user, Worker $worker): bool
    {
        return $user->canManageWorkers();
    }

    public function delete(User $user, Worker $worker): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Site managers fill in the weekly work sheet.
     */
    public function recordWork(User $user, Worker $worker): bool
    {
        return $user->canManageWorkers();
    }

    /**
     * Handing over money and closing a pay week is an owner's decision.
     */
    public function managePayroll(User $user, Worker $worker): bool
    {
        return $user->hasRole('admin');
    }
}
