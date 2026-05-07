<?php

declare(strict_types=1);

namespace App\Filament\Resources\WhitelistUsers\Schemas;

use App\Models\WhitelistUser;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Image;
use Filament\Schemas\Schema;

class WhitelistUserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->schema([
            TextInput::make('username')
                ->label('Username')
                ->required()
                ->alphaDash()
                ->trim()
                ->autocapitalize(false)
                ->autocomplete(false),
            TextInput::make('uuid')
                ->label('UUID')
                ->disabled(),
            Image::make('skin', fn (?WhitelistUser $record): ?string => $record?->username)
                ->columnSpanFull()
                ->url(fn (?WhitelistUser $record): ?string => $record?->getSkinUrl())
                ->imageHeight('12rem')
                ->visible(fn (string $operation): bool => $operation !== 'create'),
        ]);
    }
}
