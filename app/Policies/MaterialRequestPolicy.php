<?php

namespace App\Policies;

use App\Models\MaterialRequest;
use App\Models\User;

class MaterialRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canCreateMaterialRequests() || $user->canReviewMaterialRequests();
    }

    public function view(User $user, MaterialRequest $materialRequest): bool
    {
        if ($user->hasRole('admin') || $user->canReviewMaterialRequests()) {
            return true;
        }

        return $materialRequest->requested_by === $user->id
            || $materialRequest->project?->isAssignedTo($user);
    }

    public function create(User $user): bool
    {
        return $user->canCreateMaterialRequests();
    }

    public function update(User $user, MaterialRequest $materialRequest): bool
    {
        if (! $materialRequest->isDraft()) {
            return false;
        }

        return $user->hasRole('admin') || $materialRequest->requested_by === $user->id;
    }

    public function delete(User $user, MaterialRequest $materialRequest): bool
    {
        return $this->update($user, $materialRequest);
    }

    public function submit(User $user, MaterialRequest $materialRequest): bool
    {
        return $this->update($user, $materialRequest);
    }

    public function review(User $user, MaterialRequest $materialRequest): bool
    {
        if (! $user->canReviewMaterialRequests() || ! $materialRequest->isPending()) {
            return false;
        }

        return $materialRequest->requested_by !== $user->id;
    }

    public function issue(User $user, MaterialRequest $materialRequest): bool
    {
        return $user->canIssueMaterials() && $materialRequest->canIssue();
    }
}
