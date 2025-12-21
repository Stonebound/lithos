<?php

declare(strict_types=1);

namespace App\Filament\Resources\OverrideRules\Pages;

use App\Filament\Resources\OverrideRules\OverrideRuleResource;
use Filament\Resources\Pages\CreateRecord;

class CreateOverrideRule extends CreateRecord
{
    protected static string $resource = OverrideRuleResource::class;
}
