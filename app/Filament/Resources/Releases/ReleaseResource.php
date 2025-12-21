<?php

declare(strict_types=1);

namespace App\Filament\Resources\Releases;

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
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class ReleaseResource extends Resource
{
    protected static ?string $model = Release::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

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
        }

        if (! $release->source_type || ! $release->source_path) {
            throw new \RuntimeException('Source not set. Select a provider version or specify source fields.');
        }

        /** @var ModpackImporter $importer */
        $importer = app(ModpackImporter::class);
        $newDir = $importer->import($release);
        $release->extracted_path = $newDir;

        // Snapshot remote
        $remoteDir = storage_path('app/modpacks/'.$release->id.'/remote');
        /** @var SftpService $sftpSvc */
        $sftpSvc = app(SftpService::class);
        $sftp = $sftpSvc->connect($release->server);
        $include = is_array($release->server->include_paths)
            ? $release->server->include_paths
            : array_values(array_filter(array_map(fn ($l) => trim($l), preg_split('/\r\n|\r|\n/', (string) ($release->server->include_paths ?? '')))));
        $sftpSvc->downloadDirectory($sftp, $release->server->remote_root_path, $remoteDir, $include);
        $release->remote_snapshot_path = $remoteDir;

        // Apply overrides
        $preparedDir = storage_path('app/modpacks/'.$release->id.'/prepared');
        /** @var OverrideApplier $applier */
        $applier = app(OverrideApplier::class);
        $applier->apply($release, $newDir, $preparedDir);
        $release->prepared_path = $preparedDir;

        // Compute diffs (remote vs prepared)
        /** @var DiffService $diff */
        $diff = app(DiffService::class);
        $changes = $diff->compute($release, $remoteDir, $preparedDir);

        // Persist file changes
        FileChange::query()->where('release_id', $release->id)->delete();
        foreach ($changes as $change) {
            $change->save();
        }

        $release->status = 'prepared';
        $release->summary_json = [
            'added' => count(array_filter($changes, fn ($c) => $c->change_type === 'added')),
            'modified' => count(array_filter($changes, fn ($c) => $c->change_type === 'modified')),
            'removed' => count(array_filter($changes, fn ($c) => $c->change_type === 'removed')),
        ];
        $release->save();

        Notification::make()
            ->title('Release prepared')
            ->body('Overrides applied and diffs computed.')
            ->success()
            ->send();
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
        $sftpSvc->syncDirectory($sftp, $release->prepared_path, $release->server->remote_root_path, false, $include);

        if ($deleteRemoved) {
            \App\Jobs\DeleteRemovedFiles::dispatch($release->id, Auth::id());
        }

        $release->status = 'deployed';
        $release->save();

        Notification::make()
            ->title('Deployment complete')
            ->body($deleteRemoved ? 'Sync complete. Removal of deleted files queued.' : 'Prepared files synchronized to remote server.')
            ->success()
            ->send();
    }
}
