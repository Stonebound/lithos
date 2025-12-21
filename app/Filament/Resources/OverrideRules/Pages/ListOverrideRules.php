<?php

declare(strict_types=1);

namespace App\Filament\Resources\OverrideRules\Pages;

use App\Filament\Resources\OverrideRules\OverrideRuleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListOverrideRules extends ListRecords
{
    protected static string $resource = OverrideRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
