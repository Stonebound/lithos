<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Server;
use App\Models\User;

class ServerPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role, [UserRole::Admin, UserRole::Maintainer], true);
    }

    public function view(User $user, Server $server): bool
    {
        return in_array($user->role, [UserRole::Admin, UserRole::Maintainer], true);
    }

    public function update(User $user, Server $server): bool
    {
        // Only Admins and Maintainers can update provider, game version, and title
        return in_array($user->role, [UserRole::Admin, UserRole::Maintainer], true);
    }

    public function create(User $user): bool
    {
        return $user->role === UserRole::Admin;
    }

    public function delete(User $user, Server $server): bool
    {
        return $user->role === UserRole::Admin;
    }
}
