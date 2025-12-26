<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\SrvRecord;
use App\Models\User;

class SrvRecordPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, SrvRecord $srvRecord): bool
    {
        return true;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return in_array($user->role, [UserRole::Admin, UserRole::Maintainer], true);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, SrvRecord $srvRecord): bool
    {
        return in_array($user->role, [UserRole::Admin, UserRole::Maintainer], true);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, SrvRecord $srvRecord): bool
    {
        return in_array($user->role, [UserRole::Admin, UserRole::Maintainer], true);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, SrvRecord $srvRecord): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, SrvRecord $srvRecord): bool
    {
        return false;
    }
}
