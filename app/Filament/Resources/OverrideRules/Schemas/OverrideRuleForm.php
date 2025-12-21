<?php

declare(strict_types=1);

namespace App\Filament\Resources\OverrideRules\Schemas;

use App\Enums\OverrideRuleType;
use Filament\Forms\Components\CodeEditor;
use Filament\Forms\Components\CodeEditor\Enums\Language;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
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
                Toggle::make('enabled')
                    ->label('Enabled')
                    ->default(true)
                    ->required(),
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
                Select::make('minecraft_version')
                    ->label('Minecraft version')
                    ->relationship('minecraftVersion', 'id')
                    ->searchable()
                    ->preload()
                    ->native(false)
                    ->helperText('Optional: only apply this rule to servers with this Minecraft version.')
                    ->visible(fn ($get) => $get('scope') === 'global'),
                Select::make('servers')
                    ->label('Servers')
                    ->relationship('servers', 'name')
                    ->multiple()
                    ->preload()
                    ->hidden(fn ($get) => $get('scope') !== 'server')
                    ->required(fn ($get) => $get('scope') === 'server'),
                Select::make('type')
                    ->label('Rule type')
                    ->options(OverrideRuleType::class)
                    ->required()
                    ->reactive(),
                TagsInput::make('path_patterns')
                    ->label('Path patterns')
                    ->helperText('Glob pattern(s) that match files to change, e.g. config/**/*.json. Press enter after each pattern.')
                    ->default(['*'])
                    ->required(fn ($get) => $get('type') !== OverrideRuleType::FileAdd)
                    ->hidden(fn ($get) => $get('type') === OverrideRuleType::FileAdd),
                // Inputs for file_add
                Repeater::make('add_files')
                    ->label('Files to add')
                    ->schema([
                        FileUpload::make('from_upload')
                            ->label('File')
                            ->disk('local')
                            ->directory('uploads/override-files')
                            ->required(),
                        TextInput::make('to')
                            ->label('Destination path')
                            ->placeholder('mods/Extra.jar')
                            ->required(),
                    ])
                    ->columns(2)
                    ->hidden(fn ($get) => $get('type') !== OverrideRuleType::FileAdd)
                    ->required(fn ($get) => $get('type') === OverrideRuleType::FileAdd),
                Toggle::make('overwrite')
                    ->label('Overwrite if exists')
                    ->default(true)
                    ->hidden(fn ($get) => $get('type') !== OverrideRuleType::FileAdd),
                CodeEditor::make('payload')
                    ->label('Payload (JSON)')
                    ->required(fn ($get) => ! in_array($get('type'), [
                        OverrideRuleType::FileAdd,
                        OverrideRuleType::FileRemove,
                        OverrideRuleType::FileSkip,
                    ]))
                    ->language(Language::Json)
                    ->columnSpanFull()
                    ->helperText('Examples: text_replace: {"search":"...","replace":"...","regex":false} • json/yaml_patch: {"merge":{...}}')
                    ->afterStateHydrated(function ($state, $set, $get) {
                        $type = $get('type');
                        if ($type === OverrideRuleType::FileAdd && is_array($state)) {
                            if (isset($state['files'])) {
                                $set('add_files', $state['files']);
                            } elseif (isset($state['from_upload']) || isset($state['to'])) {
                                // Migrate old single file rule to repeater
                                $set('add_files', [
                                    [
                                        'from_upload' => $state['from_upload'] ?? '',
                                        'to' => $state['to'] ?? '',
                                    ],
                                ]);
                            }
                            $set('overwrite', $state['overwrite'] ?? true);
                        }
                    })
                    ->formatStateUsing(fn ($state, $get) => in_array($get('type'), [
                        OverrideRuleType::FileAdd,
                        OverrideRuleType::FileRemove,
                        OverrideRuleType::FileSkip,
])
                        ? ''
                        : (is_array($state) ? json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : (string) ($state ?? '')))
                    ->dehydrateStateUsing(function ($state, $get) {
                        $type = $get('type');
                        if ($type === OverrideRuleType::FileAdd) {
                            return [
                                'files' => (array) ($get('add_files') ?? []),
                                'overwrite' => (bool) ($get('overwrite') ?? true),
                            ];
                        }
                        if ($type === OverrideRuleType::FileRemove || $type === OverrideRuleType::FileSkip) {
                            return [];
                        }

                        return is_string($state) ? (json_decode($state ?: '[]', true) ?: []) : ($state ?? []);
                    })
                    ->hidden(fn ($get) => in_array($get('type'), [
                        OverrideRuleType::FileAdd,
                        OverrideRuleType::FileRemove,
                        OverrideRuleType::FileSkip,
                    ])),
                TextInput::make('priority')
                    ->label('Priority')
                    ->helperText('Higher runs earlier. 0 is default.')
                    ->required()
                    ->numeric()
                    ->default(0),
            ]);
    }
}
