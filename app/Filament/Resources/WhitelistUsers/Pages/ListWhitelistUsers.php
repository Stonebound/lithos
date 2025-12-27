<?php

declare(strict_types=1);

namespace App\Filament\Resources\WhitelistUsers\Pages;

use App\Filament\Resources\WhitelistUsers\WhitelistUserResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListWhitelistUsers extends ListRecords
{
    protected static string $resource = WhitelistUserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            Action::make('download_json')
                ->label('JSON')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->url(route('whitelist.json'))
                ->openUrlInNewTab(),
            Action::make('download_txt')
                ->label('TXT')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->url(route('whitelist.txt'))
                ->openUrlInNewTab(),
        ];
    }
}
