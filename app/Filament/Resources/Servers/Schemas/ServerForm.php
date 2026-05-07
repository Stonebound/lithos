<?php

declare(strict_types=1);

namespace App\Filament\Resources\Servers\Schemas;

use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

class ServerForm
{
    public static function configure(Schema $schema): Schema
    {
        /** @var User|null $user */
        $user = Auth::user();
        $isAdmin = $user?->isAdmin() ?? false;

        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Server name')
                    ->required()
                    ->placeholder('Modpackname'),
                Select::make('minecraft_version')
                    ->label('Minecraft version')
                    ->relationship('minecraftVersion', 'id')
                    ->searchable()
                    ->preload()
                    ->native(false),
                TextInput::make('host')
                    ->label('Host')
                    ->required()
                    ->default('mc.stonebound.net')
                    ->disabled(fn (string $operation): bool => $operation === 'edit' && ! $isAdmin),
                TextInput::make('port')
                    ->label('SSH port')
                    ->required()
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(65535)
                    ->default(3875)
                    ->disabled(fn (string $operation): bool => $operation === 'edit' && ! $isAdmin),
                TextInput::make('username')
                    ->label('SSH username')
                    ->required()
                    ->placeholder('deployer')
                    ->disabled(fn (string $operation): bool => $operation === 'edit' && ! $isAdmin),
                Select::make('auth_type')
                    ->label('Authentication')
                    ->options([
                        'password' => 'Password',
                        'private_key' => 'SSH key',
                    ])
                    ->required()
                    ->default('password')
                    ->native(false)
                    ->reactive()
                    ->disabled(fn (string $operation): bool => $operation === 'edit' && ! $isAdmin),
                TextInput::make('password')
                    ->label('Password')
                    ->password()
                    ->revealable()
                    ->required(fn (Get $get, string $operation): bool => $operation === 'create' && $get('auth_type') === 'password')
                    ->hidden(fn (Get $get): bool => $get('auth_type') !== 'password')
                    ->dehydrated(fn (Get $get, mixed $state): bool => $get('auth_type') === 'password' && filled($state))
                    ->disabled(fn (string $operation): bool => $operation === 'edit' && ! $isAdmin),
                TextInput::make('private_key_path')
                    ->label('Private key path')
                    ->placeholder('/home/user/.ssh/id_rsa')
                    ->required(fn (Get $get, string $operation): bool => $operation === 'create' && $get('auth_type') === 'private_key')
                    ->hidden(fn (Get $get): bool => $get('auth_type') !== 'private_key')
                    ->dehydrated(fn (Get $get): bool => $get('auth_type') === 'private_key')
                    ->disabled(fn (string $operation): bool => $operation === 'edit' && ! $isAdmin),
                TextInput::make('remote_root_path')
                    ->label('Remote root path')
                    ->required()
                    ->default('/')
                    ->disabled(fn (string $operation): bool => $operation === 'edit' && ! $isAdmin),
                TagsInput::make('include_paths')
                    ->label('Include folders')
                    ->placeholder('Add folders to include')
                    ->suggestions(['config', 'mods', 'kubejs', 'datapacks', 'defaultconfigs', 'resourcepacks'])
                    ->default(['config', 'mods', 'kubejs', 'datapacks', 'defaultconfigs', 'resourcepacks'])
                    ->helperText('Top-level folders to sync. Defaults to common modpack folders if left empty.')
                    ->columnSpanFull(),
                Select::make('provider')
                    ->label('Source provider (optional)')
                    ->options([
                        'curseforge' => 'CurseForge',
                        'ftb' => 'Feed The Beast',
                    ])
                    ->nullable()
                    ->native(false)
                    ->helperText('Set a provider to enable provider-driven releases.'),
                TextInput::make('provider_pack_id')
                    ->label('Current pack ID')
                    ->numeric()
                    ->placeholder('e.g. 123456')
                    ->helperText('For CurseForge: project ID. For FTB: pack ID.'),
                TextInput::make('provider_current_version')
                    ->label('Last version ID')
                    ->disabled()
                    ->helperText('Set automatically when preparing a release.'),
            ]);
    }
}
