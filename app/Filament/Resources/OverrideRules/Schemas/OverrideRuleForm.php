<?php

declare(strict_types=1);

namespace App\Filament\Resources\OverrideRules\Schemas;

use Filament\Forms\Components\CodeEditor;
use Filament\Forms\Components\FileUpload;
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
                    ->default('global')
                    ->reactive(),
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
                        'file_add' => 'Add File',
                        'file_remove' => 'Remove Files',
                    ])
                    ->required()
                    ->reactive(),
                // Inputs for file_add
                FileUpload::make('upload_file')
                    ->label('File to add')
                    ->disk('local')
                    ->directory('uploads/override-files')
                    ->hidden(fn ($get) => $get('type') !== 'file_add')
                    ->required(fn ($get) => $get('type') === 'file_add'),
                TextInput::make('dest_path')
                    ->label('Destination path')
                    ->placeholder('mods/Extra.jar')
                    ->hidden(fn ($get) => $get('type') !== 'file_add')
                    ->required(fn ($get) => $get('type') === 'file_add'),
                Toggle::make('overwrite')
                    ->label('Overwrite if exists')
                    ->default(true)
                    ->hidden(fn ($get) => $get('type') !== 'file_add'),
                CodeEditor::make('payload')
                    ->label('Payload (JSON)')
                    ->required(fn ($get) => ! in_array($get('type'), ['file_add', 'file_remove']))
                    ->columnSpanFull()
                    ->helperText('Examples: text_replace: {"search":"...","replace":"...","regex":false} • json/yaml_patch: {"merge":{...}}')
                    ->formatStateUsing(fn ($state, $get) => in_array($get('type'), ['file_add', 'file_remove'])
                        ? ''
                        : (is_array($state) ? json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : (string) ($state ?? '')))
                    ->dehydrateStateUsing(function ($state, $get) {
                        $type = $get('type');
                        if ($type === 'file_add') {
                            return [
                                'from_upload' => (string) ($get('upload_file') ?? ''),
                                'to' => (string) ($get('dest_path') ?? ''),
                                'overwrite' => (bool) ($get('overwrite') ?? true),
                            ];
                        }
                        if ($type === 'file_remove') {
                            return [];
                        }

                        return is_string($state) ? (json_decode($state ?: '[]', true) ?: []) : ($state ?? []);
                    })
                    ->visible(fn ($get) => ! in_array($get('type'), ['file_add', 'file_remove'])),
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
