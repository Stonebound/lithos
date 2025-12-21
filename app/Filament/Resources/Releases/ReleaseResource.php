<?php

declare(strict_types=1);

namespace App\Filament\Resources\Releases;

use App\Enums\ReleaseStatus;
use App\Filament\Resources\Releases\Pages\CreateRelease;
use App\Filament\Resources\Releases\Pages\EditRelease;
use App\Filament\Resources\Releases\Pages\ListReleases;
use App\Filament\Resources\Releases\Schemas\ReleaseForm;
use App\Filament\Resources\Releases\Tables\ReleasesTable;
use App\Models\FileChange;
use App\Models\Release;
use App\Models\Server;
use App\Services\DiffService;
use App\Services\ModpackImporter;
use App\Services\OverrideApplier;
use App\Services\Providers\ProviderResolver;
use App\Services\SftpService;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class ReleaseResource extends Resource
{
    protected static ?string $model = Release::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRocketLaunch;

    public static function form(Schema $schema): Schema
    {
        return ReleaseForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ReleasesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListReleases::route('/'),
            'create' => CreateRelease::route('/create'),
            'edit' => EditRelease::route('/{record}/edit'),
        ];
    }

    /**
     * Prepare the release by fetching provider source (if provided), importing,
     * snapshotting the remote server, applying overrides, and computing diffs.
     */
    public static function prepareRelease(Release $release, ?string $providerVersionId = null): void
    {
        // Resolve provider source if requested
        if ($providerVersionId) {
            /** @var Server $server */
            $server = $release->server;
            /** @var ProviderResolver $resolver */
            $resolver = app(ProviderResolver::class);
            $provider = $resolver->for($server);
            if (! $provider) {
                throw new \RuntimeException('No provider configured for this server.');
            }
            $src = $provider->fetchSource($server->provider_pack_id, $providerVersionId);
            $release->source_type = $src['type'];
            $release->source_path = $src['path'];
            $release->version_label = $release->version_label ?: (string) $providerVersionId;
            $release->save();

            dump('Fetched source from provider: '.$src['path']);
        }

        if (! $release->source_type || ! $release->source_path) {
            throw new \RuntimeException('Source not set. Select a provider version or specify source fields.');
        }

        /** @var ModpackImporter $importer */
        $importer = app(ModpackImporter::class);
        $newDir = $importer->import($release);
        $release->extracted_path = $newDir;

        dump('Imported to '.$newDir);
        // Snapshot remote
        $remoteDir = 'modpacks/'.$release->id.'/remote';
        /** @var SftpService $sftpSvc */
        $sftpSvc = app(SftpService::class);
        $sftp = $sftpSvc->connect($release->server);
        $include = $release->server->include_paths ?? [];
        $skipPatterns = \App\Models\OverrideRule::getSkipPatternsForServer($release->server);
        $sftpSvc->downloadDirectory($sftp, $release->server->remote_root_path, $remoteDir, $include, 0, $skipPatterns);
        $release->remote_snapshot_path = $remoteDir;

        dump('Snapshotted to '.$remoteDir);
        // Apply overrides
        $preparedDir = 'modpacks/'.$release->id.'/prepared';
        /** @var OverrideApplier $applier */
        $applier = app(OverrideApplier::class);
        $applier->apply($release, $newDir, $preparedDir, $remoteDir);
        $release->prepared_path = $preparedDir;

        dump('Prepared to '.$preparedDir);
        // Compute diffs (remote vs prepared)
        /** @var DiffService $diff */
        $diff = app(DiffService::class);
        $changes = $diff->compute($release, $remoteDir, $preparedDir);

        dump('Computed diffs, '.count($changes).' changes found.');
        // Persist file changes
        FileChange::query()->where('release_id', $release->id)->delete();
        foreach ($changes as $change) {
            $change->save();
        }

        $release->status = ReleaseStatus::Prepared;
        $release->summary_json = [
            'added' => count(array_filter($changes, fn ($c) => $c->change_type === 'added')),
            'modified' => count(array_filter($changes, fn ($c) => $c->change_type === 'modified')),
            'removed' => count(array_filter($changes, fn ($c) => $c->change_type === 'removed')),
        ];
        $release->save();
    }

    /**
     * Deploy the prepared directory to the remote server via SFTP.
     */
    public static function deployRelease(Release $release, bool $deleteRemoved = false): void
    {
        if (! $release->prepared_path) {
            throw new \RuntimeException('Release not prepared.');
        }

        /** @var SftpService $sftpSvc */
        $sftpSvc = app(SftpService::class);
        $sftp = $sftpSvc->connect($release->server);
        $include = $release->server->include_paths;
        $skipPatterns = \App\Models\OverrideRule::getSkipPatternsForServer($release->server);
        $sftpSvc->syncDirectory($sftp, $release->prepared_path, $release->server->remote_root_path, false, $include, $skipPatterns);

        if ($deleteRemoved) {
            \App\Jobs\DeleteRemovedFiles::dispatch($release->id, Auth::id());
        }

        $release->status = ReleaseStatus::Deployed;
        $release->save();
    }
}
