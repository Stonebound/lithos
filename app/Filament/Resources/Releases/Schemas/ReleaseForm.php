<?php

declare(strict_types=1);

namespace App\Filament\Resources\Releases\Schemas;

use App\Models\Server;
use App\Services\Providers\ProviderResolver;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ReleaseForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('server_id')
                    ->label('Target server')
                    ->relationship('server', 'name')
                    ->required()
                    ->helperText('Choose the server to prepare and deploy this release to.')
                    ->reactive(),
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
                    ->default('upload')
                    ->dehydrated(false)
                    ->live(),
                // Provider-driven version selection (not saved to the model).
                Select::make('provider_version_id')
                    ->label('Provider version')
                    ->helperText('Pick a version from the configured provider for the selected server.')
                    ->reactive()
                    ->dehydrated(false)
                    ->hidden(fn ($get) => $get('source_mode') !== 'provider')
                    ->options(function ($get): array {
                        $serverId = $get('server_id');
                        if (! $serverId) {
                            return [];
                        }

                        /** @var Server|null $server */
                        $server = Server::query()->find($serverId);
                        if (! $server) {
                            return [];
                        }

                        /** @var ProviderResolver $resolver */
                        $resolver = app(ProviderResolver::class);
                        $provider = $resolver->for($server);
                        if (! $provider) {
                            return [];
                        }

                        $versions = $provider->listVersions($server);
                        $out = [];
                        foreach ($versions as $ver) {
                            $out[(string) ($ver['id'] ?? '')] = (string) ($ver['name'] ?? $ver['id'] ?? '');
                        }

                        return $out;
                    }),
                FileUpload::make('source_zip')
                    ->label('Source zip')
                    ->helperText('Upload a modpack .zip when not using a provider.')
                    ->acceptedFileTypes(['application/zip', '.zip'])
                    ->disk('local')
                    ->directory('uploads')
                    ->hidden(fn ($get) => $get('source_mode') !== 'upload'),
                // Internal/system fields (hidden)
                TextInput::make('source_type')->hidden(),
                TextInput::make('source_path')->hidden(),
                TextInput::make('extracted_path')->hidden(),
                TextInput::make('remote_snapshot_path')->hidden(),
                TextInput::make('prepared_path')->hidden(),
                TextInput::make('status')
                    ->hidden()
                    ->default('draft'),
                Textarea::make('summary_json')
                    ->hidden()
                    ->columnSpanFull(),
            ]);
    }
}
