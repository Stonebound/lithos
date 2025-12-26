<?php

declare(strict_types=1);

namespace App\Filament\Resources\SrvRecords\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class SrvRecordForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('subdomain')
                    ->required(),
                TextInput::make('port')
                    ->required()
                    ->minValue(20000)
                    ->maxValue(65535)
                    ->maxLength(5)
                    ->integer(),
            ]);
    }
}
