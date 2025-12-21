<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum FileChangeType: string implements HasColor, HasLabel
{
    case Added = 'added';
    case Removed = 'removed';
    case Modified = 'modified';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Added => 'Added',
            self::Removed => 'Removed',
            self::Modified => 'Modified',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Added => 'success',
            self::Removed => 'danger',
            self::Modified => 'warning',
        };
    }
}
