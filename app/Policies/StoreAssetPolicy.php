<?php

namespace App\Policies;

use App\Models\StoreAsset;
use App\Models\User;

class StoreAssetPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canViewStoreAssets();
    }

    public function view(User $user, StoreAsset $storeAsset): bool
    {
        return $user->canViewStoreAssets();
    }

    public function create(User $user): bool
    {
        return $user->canManageStoreAssets();
    }

    public function update(User $user, StoreAsset $storeAsset): bool
    {
        return $user->canManageStoreAssets();
    }

    public function delete(User $user, StoreAsset $storeAsset): bool
    {
        return $user->canManageStoreAssets();
    }

    public function issue(User $user, StoreAsset $storeAsset): bool
    {
        return $user->canManageStoreAssets();
    }
}
