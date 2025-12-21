<?php

declare(strict_types=1);

namespace App\Filament\Resources\OverrideRules\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class OverrideRuleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Rule name')
                    ->required()
                    ->placeholder('EnableFeature'),
                Textarea::make('description')
                    ->label('Description')
                    ->placeholder('Explain what this rule changes and why')
                    ->columnSpanFull(),
                Select::make('scope')
                    ->label('Scope')
                    ->options([
                        'global' => 'Global',
                        'server' => 'Server-specific',
                    ])
                    ->required()
                    ->default('global'),
                Select::make('server_id')
                    ->label('Server')
                    ->relationship('server', 'name')
                    ->hidden(fn ($get) => $get('scope') !== 'server')
                    ->required(fn ($get) => $get('scope') === 'server'),
                TextInput::make('path_pattern')
                    ->label('Path pattern')
                    ->helperText('Glob pattern(s) that match files to change, e.g. config/**/*.json')
                    ->required(),
                Select::make('type')
                    ->label('Rule type')
                    ->options([
                        'text_replace' => 'Text Replace',
                        'json_patch' => 'JSON Patch',
                        'yaml_patch' => 'YAML Patch',
                    ])
                    ->required(),
                Textarea::make('payload')
                    ->label('Payload (JSON)')
                    ->required()
                    ->columnSpanFull()
                    ->helperText('Examples: text_replace: {"search":"...","replace":"...","regex":false} • json/yaml_patch: {"merge":{...}}')
                    ->formatStateUsing(fn ($state) => is_array($state) ? json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : (string) ($state ?? ''))
                    ->dehydrateStateUsing(fn ($state) => is_string($state) ? (json_decode($state ?: '[]', true) ?: []) : ($state ?? [])),
                Toggle::make('enabled')
                    ->label('Enabled')
                    ->required(),
                TextInput::make('priority')
                    ->label('Priority')
                    ->helperText('Higher runs earlier. 0 is default.')
                    ->required()
                    ->numeric()
                    ->default(0),
            ]);
    }
}
