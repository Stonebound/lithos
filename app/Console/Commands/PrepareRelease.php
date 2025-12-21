<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Release;
use App\Models\Server;
use App\Services\DiffService;
use App\Services\ModpackImporter;
use App\Services\OverrideApplier;
use App\Services\SftpService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PrepareRelease extends Command
{
    protected $signature = 'modpack:prepare {server_id} {source} {--version=}';

    protected $description = 'Import source, fetch remote snapshot, compute diff, apply overrides.';

    public function handle(ModpackImporter $importer, SftpService $sftpService, DiffService $diffService, OverrideApplier $overrideApplier): int
    {
        $serverId = (int) $this->argument('server_id');
        $source = (string) $this->argument('source');
        $version = (string) ($this->option('version') ?? '');

        /** @var Server|null $server */
        $server = Server::query()->find($serverId);
        if (! $server) {
            $this->error('Server not found: '.$serverId);

            return self::FAILURE;
        }

        $sourceType = is_dir($source) ? 'dir' : (str_ends_with(strtolower($source), '.zip') ? 'zip' : null);
        if (! $sourceType) {
            $this->error('Source must be a directory or a .zip file');

            return self::FAILURE;
        }

        $release = DB::transaction(function () use ($server, $source, $sourceType, $version) {
            return Release::query()->create([
                'server_id' => $server->id,
                'version_label' => $version ?: null,
                'source_type' => $sourceType,
                'source_path' => $source,
                'status' => 'draft',
            ]);
        });

        $this->info('Release created: ID '.$release->id);

        $this->info('Importing new modpack...');
        $extracted = $importer->import($release);
        $release->update(['extracted_path' => $extracted]);

        $this->info('Fetching remote snapshot via SFTP...');
        $sftp = $sftpService->connect($server);
        $remoteSnapshot = storage_path('app/modpacks/'.$release->id.'/current');
        $include = is_array($server->include_paths)
            ? $server->include_paths
            : array_values(array_filter(array_map(fn ($l) => trim($l), preg_split('/\r\n|\r|\n/', (string) ($server->include_paths ?? '')))));
        $sftpService->downloadDirectory($sftp, rtrim($server->remote_root_path, '/'), $remoteSnapshot, $include);
        $release->update(['remote_snapshot_path' => $remoteSnapshot]);

        $this->info('Computing diffs...');
        $changes = $diffService->compute($release, $remoteSnapshot, $extracted);
        foreach ($changes as $change) {
            $change->save();
        }

        $summary = [
            'added' => count(array_filter($changes, fn ($c) => $c->change_type === 'added')),
            'removed' => count(array_filter($changes, fn ($c) => $c->change_type === 'removed')),
            'modified' => count(array_filter($changes, fn ($c) => $c->change_type === 'modified')),
        ];
        $release->update(['summary_json' => $summary]);

        $this->info('Applying overrides...');
        $preparedPath = storage_path('app/modpacks/'.$release->id.'/prepared');
        $overrideApplier->apply($release, $extracted, $preparedPath);
        $release->update(['prepared_path' => $preparedPath, 'status' => 'prepared']);

        $this->newLine();
        $this->info('Prepare complete. Summary:');
        $this->line(json_encode($summary, JSON_PRETTY_PRINT));
        $this->line('Release ID: '.$release->id);
        $this->line('Prepared Path: '.$preparedPath);

        return self::SUCCESS;
    }
}
