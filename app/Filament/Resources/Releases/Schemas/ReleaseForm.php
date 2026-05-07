<?php

declare(strict_types=1);

namespace App\Filament\Resources\Releases\Schemas;

use App\Concerns\NormalizesStringValues;
use App\Enums\ReleaseStatus;
use App\Livewire\Releases\ReleaseLogs;
use App\Models\Release;
use App\Models\Server;
use App\Services\PhpUploadLimit;
use App\Services\Providers\ProviderInterface;
use App\Services\Providers\ProviderResolver;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class ReleaseForm
{
    use NormalizesStringValues;

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Logs')
                    ->description('Live output from the preparation and deployment jobs.')
                    ->schema([
                        Livewire::make(ReleaseLogs::class, fn (?Release $record): array => ['release' => $record])
                            ->hidden(fn (?Release $record): bool => $record === null),
                    ])
                    ->columnSpanFull()
                    ->collapsible()
                    ->collapsed(fn (?Release $record): bool => $record?->status === ReleaseStatus::Deployed),
                Select::make('server_id')
                    ->label('Target server')
                    ->relationship('server', 'name')
                    ->required()
                    ->helperText('Choose the server to prepare and deploy this release to.')
                    ->reactive()
                    ->afterStateUpdated(function (mixed $state, Set $set): void {
                        $server = self::serverFromState($state);

                        if ($server && self::hasProviderSource($server)) {
                            $set('source_mode', 'provider');
                        } else {
                            $set('source_mode', 'upload');
                        }
                    }),
                TextInput::make('version_label')
                    ->label('Version label')
                    ->placeholder('e.g. 1.0.0'),
                // Source selection UX
                Radio::make('source_mode')
                    ->label('Source')
                    ->options([
                        'provider' => 'Use provider',
                        'upload' => 'Upload zip',
                    ])
                    ->default(fn (Get $get): string => self::hasProviderSource(self::selectedServer($get)) ? 'provider' : 'upload')
                    ->dehydrated(false)
                    ->live(),
                // Provider-driven version selection.
                Select::make('provider_version_id')
                    ->label('Provider version')
                    ->helperText('Pick a version from the configured provider for the selected server.')
                    ->reactive()
                    ->hidden(fn (Get $get): bool => $get('source_mode') !== 'provider')
                    ->options(function (Get $get): array {
                        $server = self::selectedServer($get);
                        $provider = self::providerForServer($server);

                        if (! $server || ! $provider || ! is_string($server->provider_pack_id) || $server->provider_pack_id === '') {
                            return [];
                        }

                        $versions = $provider->listVersions($server->provider_pack_id);
                        $out = [];
                        foreach ($versions as $ver) {
                            $out[(string) $ver['id']] = $ver['name'];
                        }

                        return $out;
                    })
                    ->afterStateUpdated(function (mixed $state, Set $set, Get $get): void {
                        if (! $state) {
                            return;
                        }

                        $server = self::selectedServer($get);
                        $provider = self::providerForServer($server);

                        if (! $server || ! $provider || ! is_string($server->provider_pack_id) || $server->provider_pack_id === '') {
                            return;
                        }

                        $versions = $provider->listVersions($server->provider_pack_id);
                        $selectedVersionId = self::normalizeStringValue($state);

                        foreach ($versions as $ver) {
                            /** @var array{id: int|string, name: string} $ver */
                            if ((string) $ver['id'] === $selectedVersionId) {
                                $set('version_label', $ver['name']);
                                break;
                            }
                        }
                    }),
                FileUpload::make('source_zip')
                    ->label('Source zip')
                    ->helperText('Upload a modpack .zip when not using a provider. Maximum file size: '.PhpUploadLimit::humanReadableMaxUpload().'.')
                    ->acceptedFileTypes(['application/zip', '.zip'])
                    ->disk('local')
                    ->directory('tmp')
                    ->hidden(fn (Get $get): bool => $get('source_mode') !== 'upload'),
            ]);
    }

    private static function selectedServer(Get $get): ?Server
    {
        return self::serverFromState($get('server_id'));
    }

    private static function serverFromState(mixed $state): ?Server
    {
        if (! is_int($state) && ! is_string($state) && ! is_numeric($state)) {
            return null;
        }

        /** @var Server|null $server */
        $server = Server::query()->find($state);

        return $server;
    }

    private static function hasProviderSource(?Server $server): bool
    {
        return $server instanceof Server
            && is_string($server->provider)
            && $server->provider !== ''
            && is_string($server->provider_pack_id)
            && $server->provider_pack_id !== '';
    }

    private static function providerForServer(?Server $server): ?ProviderInterface
    {
        if (! $server instanceof Server || ! self::hasProviderSource($server)) {
            return null;
        }

        /** @var ProviderResolver $resolver */
        $resolver = app(ProviderResolver::class);

        return $resolver->for($server);
    }
}
