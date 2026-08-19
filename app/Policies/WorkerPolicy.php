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
}
