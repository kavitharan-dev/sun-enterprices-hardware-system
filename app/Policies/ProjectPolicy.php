<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;

class ProjectPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canViewConstruction();
    }

    public function view(User $user, Project $project): bool
    {
        return $user->hasRole('admin') || $project->isAssignedTo($user);
    }

    public function create(User $user): bool
    {
        return $user->canManageProjects();
    }

    public function update(User $user, Project $project): bool
    {
        return $user->canManageProjects();
    }

    public function delete(User $user, Project $project): bool
    {
        return $user->canManageProjects();
    }

    public function assignWorker(User $user, Project $project): bool
    {
        return $user->hasRole('admin') || $project->isAssignedTo($user);
    }

    public function recordProgress(User $user, Project $project): bool
    {
        return $user->hasRole('admin') || $project->isAssignedTo($user);
    }

    public function manageExpenses(User $user, Project $project): bool
    {
        return $user->hasRole('admin') || $project->isAssignedTo($user);
    }

    public function recordOwnerPayments(User $user, Project $project): bool
    {
        return $user->hasRole('admin') || $project->isAssignedTo($user);
    }
}
