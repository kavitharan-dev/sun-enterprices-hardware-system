<?php

namespace App\Policies;

use App\Models\MaterialIssue;
use App\Models\User;

class MaterialIssuePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canIssueMaterials() || $user->canViewConstruction();
    }

    public function view(User $user, MaterialIssue $materialIssue): bool
    {
        if ($user->canIssueMaterials() || $user->hasRole('admin')) {
            return true;
        }

        return $materialIssue->project?->isAssignedTo($user) ?? false;
    }

    public function create(User $user): bool
    {
        return $user->canIssueMaterials();
    }
}
