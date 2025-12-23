<?php

declare(strict_types=1);

namespace App\Filament\Resources\Releases;

use App\Enums\FileChangeType;
use App\Enums\ReleaseStatus;
use App\Filament\Resources\Releases\Pages\CreateRelease;
use App\Filament\Resources\Releases\Pages\EditRelease;
use App\Filament\Resources\Releases\Pages\ListReleases;
use App\Filament\Resources\Releases\Schemas\ReleaseForm;
use App\Filament\Resources\Releases\Tables\ReleasesTable;
use App\Jobs\DeleteRemovedFiles;
use App\Models\FileChange;
use App\Models\OverrideRule;
use App\Models\Release;
use App\Models\Server;
use App\Services\DiffService;
use App\Services\FileUtility;
use App\Services\ModpackImporter;
use App\Services\OverrideApplier;
use App\Services\Providers\ProviderResolver;
use App\Services\SftpService;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ReleaseResource extends Resource
{
    protected static ?string $model = Release::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRocketLaunch;

    protected static ?int $navigationSort = 10;

    public static function log(Release $release, string $message, string $level = 'info'): void
    {
        $release->logs()->create([
            'message' => $message,
            'level' => $level,
        ]);
    }

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
            RelationManagers\FileChangesRelationManager::class,
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
        $release->logs()->delete();
        self::log($release, 'Starting release preparation...');

        // Resolve provider source if requested
        if ($providerVersionId) {
            self::log($release, "Resolving provider version: {$providerVersionId}");
            /** @var Server $server */
            $server = $release->server;
            /** @var ProviderResolver $resolver */
            $resolver = app(ProviderResolver::class);
            $provider = $resolver->for($server);
            if (! $provider) {
                self::log($release, 'No provider configured for this server.', 'error');
                throw new \RuntimeException('No provider configured for this server.');
            }
            $src = $provider->fetchSource($server->provider_pack_id, $providerVersionId);
            $release->source_type = $src['type'];
            $release->source_path = $src['path'];
            $release->provider_version_id = $providerVersionId;
            $release->version_label = $release->version_label ?: (string) $providerVersionId;
            $release->save();
            self::log($release, "Source resolved: {$src['type']} at {$src['path']}");
        }

        if (! $release->source_type || ! $release->source_path) {
            self::log($release, 'Source not set. Select a provider version or specify source fields.', 'error');
            throw new \RuntimeException('Source not set. Select a provider version or specify source fields.');
        }

        // Clear local folders before snapshot/import
        $baseDir = 'modpacks/'.$release->id;
        FileUtility::deleteDirectory($baseDir.'/remote');
        FileUtility::deleteDirectory($baseDir.'/new');
        FileUtility::deleteDirectory($baseDir.'/prepared');

        self::log($release, 'Importing modpack...');
        /** @var ModpackImporter $importer */
        $importer = app(ModpackImporter::class);
        $newDir = $importer->import($release, function ($action, $file) use ($release) {
            self::log($release, "Importing: {$file}");
        });
        $release->extracted_path = $newDir;
        self::log($release, "Modpack imported to: {$newDir}");

        // Snapshot remote
        self::log($release, 'Snapshotting remote server...');
        $remoteDir = $baseDir.'/remote';
        /** @var SftpService $sftpSvc */
        $sftpSvc = app(SftpService::class);

        $sftp = $sftpSvc->connect($release->server);

        $include = $release->server->include_paths ?? [];
        $skipPatterns = OverrideRule::getSkipPatternsForServer($release->server);

        $sftpSvc->downloadDirectory($sftp, $release->server->remote_root_path, $remoteDir, $include, 0, $skipPatterns);
        self::log($release, "Remote snapshot completed to: {$remoteDir}");

        // Apply overrides
        self::log($release, 'Applying overrides...');
        $preparedDir = 'modpacks/'.$release->id.'/prepared';
        /** @var OverrideApplier $applier */
        $applier = app(OverrideApplier::class);
        $applier->apply($release, $newDir, $preparedDir, $remoteDir, function ($action, $name) use ($release) {
            self::log($release, "Applying rule: {$name}");
        });
        $release->prepared_path = $preparedDir;
        self::log($release, "Overrides applied to: {$preparedDir}");

        // Compute diffs (remote vs prepared)
        self::log($release, 'Computing diffs...');
        /** @var DiffService $diff */
        $diff = app(DiffService::class);
        $changes = $diff->compute($release, $remoteDir, $preparedDir);

        // Persist file changes
        FileChange::query()->where('release_id', $release->id)->delete();
        foreach ($changes as $change) {
            $change->save();
        }
        self::log($release, 'File changes persisted.');

        $release->status = ReleaseStatus::Prepared;
        $release->summary_json = [
            'added' => count(array_filter($changes, fn ($c) => $c->change_type === FileChangeType::Added)),
            'modified' => count(array_filter($changes, fn ($c) => $c->change_type === FileChangeType::Modified)),
            'removed' => count(array_filter($changes, fn ($c) => $c->change_type === FileChangeType::Removed)),
        ];
        $release->save();
        self::log($release, 'Release preparation completed successfully.');
    }

    /**
     * Deploy the prepared directory to the remote server via SFTP.
     */
    public static function deployRelease(Release $release): void
    {
        if (! $release->prepared_path) {
            self::log($release, 'Release not prepared.', 'error');
            throw new \RuntimeException('Release not prepared.');
        }

        self::log($release, 'Starting deployment...');
        /** @var SftpService $sftpSvc */
        $sftpSvc = app(SftpService::class);
        $sftp = $sftpSvc->connect($release->server);
        $skipPatterns = \App\Models\OverrideRule::getSkipPatternsForServer($release->server);

        self::log($release, 'Syncing directory to remote...');
        $sftpSvc->syncDirectory($sftp, $release->prepared_path, $release->server->remote_root_path, $skipPatterns, function ($action, $file) use ($release) {
            self::log($release, "Uploaded: {$file}");
        });

        self::log($release, 'Cleaning up removed files...');
        DeleteRemovedFiles::dispatchSync($release->id);

        $release->status = ReleaseStatus::Deployed;
        $release->save();

        $release->server->update([
            'provider_current_version' => $release->provider_version_id,
        ]);
        self::log($release, 'Deployment completed successfully.');
    }
}
