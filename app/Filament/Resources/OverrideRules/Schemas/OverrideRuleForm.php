<?php

declare(strict_types=1);

namespace App\Filament\Resources\OverrideRules\Schemas;

use App\Concerns\NormalizesStringValues;
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
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class OverrideRuleForm
{
    use NormalizesStringValues;

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
                TextInput::make('minecraft_version')
                    ->label('Minecraft version')
                    ->helperText('Optional: only apply this rule to servers with this Minecraft version. Uses regular expressions, so make sure to escape dots.')
                    ->prefix('/^')
                    ->suffix('$/')
                    ->visible(fn (Get $get): bool => $get('scope') === 'global'),
                Select::make('servers')
                    ->label('Servers')
                    ->relationship('servers', 'name')
                    ->multiple()
                    ->preload()
                    ->hidden(fn (Get $get): bool => $get('scope') !== 'server')
                    ->required(fn (Get $get): bool => $get('scope') === 'server'),
                Select::make('type')
                    ->label('Rule type')
                    ->options(OverrideRuleType::class)
                    ->required()
                    ->reactive(),
                TagsInput::make('path_patterns')
                    ->label('Path patterns')
                    ->helperText('Glob pattern(s) that match files to change, e.g. config/**/*.json. Press enter after each pattern.')
                    ->default(['*'])
                    ->required(fn (Get $get): bool => self::selectedRuleType($get) !== OverrideRuleType::FileAdd)
                    ->hidden(fn (Get $get): bool => self::selectedRuleType($get) === OverrideRuleType::FileAdd)
                    ->dehydratedWhenHidden()
                    ->dehydrateStateUsing(fn (mixed $state, Get $get): array => self::selectedRuleType($get) === OverrideRuleType::FileAdd ? ['*'] : self::normalizeStringList($state)),
                // Inputs for file_add
                Repeater::make('add_files')
                    ->label('Files to add')
                    ->schema([
                        FileUpload::make('from_upload')
                            ->label('File')
                            ->disk('local')
                            ->directory('override-files')
                            ->required(),
                        TextInput::make('to')
                            ->label('File name')
                            ->placeholder('mods/Extra.jar')
                            ->required(),
                    ])
                    ->afterStateHydrated(function (Set $set, Get $get): void {
                        $payload = self::payloadArray($get('payload'));

                        if (self::selectedRuleType($get) === OverrideRuleType::FileAdd) {
                            $files = $payload['files'] ?? null;

                            if (is_array($files)) {
                                $set('add_files', $files);
                            }

                            $set('overwrite', (bool) ($payload['overwrite'] ?? true));
                        }
                    })
                    ->columns(2)
                    ->hidden(fn (Get $get): bool => self::selectedRuleType($get) !== OverrideRuleType::FileAdd)
                    ->required(fn (Get $get): bool => self::selectedRuleType($get) === OverrideRuleType::FileAdd),
                Toggle::make('overwrite')
                    ->label('Overwrite if exists')
                    ->default(true)
                    ->hidden(fn (Get $get): bool => self::selectedRuleType($get) !== OverrideRuleType::FileAdd),
                CodeEditor::make('payload')
                    ->label('Payload (JSON)')
                    ->required(fn (Get $get): bool => ! in_array(self::selectedRuleType($get), [
                        OverrideRuleType::FileAdd,
                        OverrideRuleType::FileRemove,
                        OverrideRuleType::FileSkip,
                    ], true))
                    ->language(Language::Json)
                    ->columnSpanFull()
                    ->helperText('Examples: text_replace: {"search":"...","replace":"...","regex":false} • json/yaml_patch: {"merge":{...}}')
                    ->formatStateUsing(fn (mixed $state, Get $get): string => in_array(self::selectedRuleType($get), [
                        OverrideRuleType::FileAdd,
                        OverrideRuleType::FileRemove,
                        OverrideRuleType::FileSkip,
                    ], true)
                        ? ''
                        : (is_array($state) ? json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) : self::normalizeStringValue($state)))
                    ->dehydrateStateUsing(function (mixed $state, Get $get): array {
                        $type = self::selectedRuleType($get);

                        if ($type === OverrideRuleType::FileAdd) {
                            return [
                                'files' => self::arrayValue($get('add_files')),
                                'overwrite' => (bool) ($get('overwrite') ?? true),
                            ];
                        }

                        if ($type === OverrideRuleType::FileRemove || $type === OverrideRuleType::FileSkip) {
                            return [];
                        }

                        if (is_string($state)) {
                            $decoded = json_decode($state ?: '[]', true, flags: JSON_THROW_ON_ERROR);

                            return is_array($decoded) ? $decoded : [];
                        }

                        return is_array($state) ? $state : [];
                    })
                    ->dehydratedWhenHidden()
                    ->hidden(fn (Get $get): bool => in_array(self::selectedRuleType($get), [
                        OverrideRuleType::FileAdd,
                        OverrideRuleType::FileRemove,
                        OverrideRuleType::FileSkip,
                    ], true)),
                TextInput::make('priority')
                    ->label('Priority')
                    ->helperText('Higher runs earlier. 0 is default.')
                    ->required()
                    ->numeric()
                    ->default(0),
            ]);
    }

    private static function selectedRuleType(Get $get): ?OverrideRuleType
    {
        $type = $get('type');

        if ($type instanceof OverrideRuleType) {
            return $type;
        }

        return is_string($type) ? OverrideRuleType::tryFrom($type) : null;
    }

    /**
     * @return array<string, mixed>
     */
    private static function payloadArray(mixed $payload): array
    {
        if (! is_array($payload)) {
            return [];
        }

        /** @var array<string, mixed> $payload */
        return $payload;
    }

    /**
     * @return array<int|string, mixed>
     */
    private static function arrayValue(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }
}
