<?php

declare(strict_types=1);

namespace App\Filament\Concerns;

use Illuminate\Support\Facades\Auth;

trait HasAuthUserId
{
    protected static function authUserId(): ?int
    {
        $userId = Auth::id();

        if ($userId === null) {
            return null;
        }

        return is_int($userId) ? $userId : (is_numeric($userId) ? (int) $userId : null);
    }
}
