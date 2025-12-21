<?php

declare(strict_types=1);

namespace App\Filament\Resources\FileChanges\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class FileChangeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('release_id')
                    ->relationship('release', 'id')
                    ->required(),
                TextInput::make('relative_path')
                    ->required(),
                TextInput::make('change_type')
                    ->required(),
                Toggle::make('is_binary')
                    ->required(),
                Textarea::make('diff_summary')
                    ->columnSpanFull(),
                TextInput::make('checksum_old'),
                TextInput::make('checksum_new'),
                TextInput::make('size_old')
                    ->numeric(),
                TextInput::make('size_new')
                    ->numeric(),
            ]);
    }
}
