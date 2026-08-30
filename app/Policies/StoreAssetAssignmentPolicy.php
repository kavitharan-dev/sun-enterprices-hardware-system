<?php

namespace App\Policies;

use App\Models\StoreAssetAssignment;
use App\Models\User;

class StoreAssetAssignmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canViewStoreAssets();
    }

    public function return(User $user, StoreAssetAssignment $assignment): bool
    {
        return $user->canManageStoreAssets();
    }
}
