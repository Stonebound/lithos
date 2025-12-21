<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ReleaseStatus: string implements HasColor, HasLabel
{
    case Draft = 'draft';
    case Prepared = 'prepared';
    case Deployed = 'deployed';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Prepared => 'Prepared',
            self::Deployed => 'Deployed',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Draft => 'secondary',
            self::Prepared => 'primary',
            self::Deployed => 'info',
        };
    }
}
