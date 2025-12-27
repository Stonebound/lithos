<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\WhitelistUser;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ImportLegacyWhitelist extends Command
{
    protected $signature = 'whitelist:import-legacy {whitelistPath} {logFolderPath} {--fresh : Truncate audit_logs and whitelist_users before import}';

    protected $description = 'Import legacy whitelist.json and logs into the database';

    public function handle(): int
    {
        if ($this->option('fresh')) {
            $this->info('Removing audit_logs for whitelist users and truncating whitelist_users...');
            Schema::disableForeignKeyConstraints();
            // delete only audit log entries that belong to the WhitelistUser model
            DB::table('audit_logs')->where('model_type', '=', WhitelistUser::class)->delete();
            DB::table('whitelist_users')->truncate();
            Schema::enableForeignKeyConstraints();
            $this->info('Removed and truncated.');
        }

        $path = $this->option('whitelistPath');

        if (! $path || ! file_exists($path)) {
            $this->error("Legacy whitelist.json not found at {$path}");

            return Command::FAILURE;
        }

        $logDir = $this->option('logFolderPath');
        if (! $logDir || ! is_dir($logDir)) {
            $this->error("Legacy log folder not found at {$logDir}");

            return Command::FAILURE;
        }

        // Import whitelist.json entries first (source of truth)
        $this->info('Importing whitelist.json entries...');

        $json = json_decode(file_get_contents($path), true, JSON_THROW_ON_ERROR);
        if (! is_array($json)) {
            $this->error('Invalid JSON file');

            return Command::FAILURE;
        }

        DB::transaction(function () use ($json) {
            foreach ($json as $entry) {
                $uuid = $entry['uuid'] ?? null;
                $name = $entry['name'] ?? null;
                if (! $uuid) {
                    continue;
                }

                WhitelistUser::forceCreate([
                    'uuid' => $uuid,
                    'username' => $name,
                    'source' => 'legacy',
                    'created_at' => null,
                ]);
            }
        });

        $this->info('Imported whitelist.json entries. Now importing logs (only for whitelist users)...');

        $files = glob($logDir.DIRECTORY_SEPARATOR.'*.log');
        foreach ($files as $file) {
            $this->importLogFile($file);
        }

        // After importing logs, set each whitelist user's created_at to the first time they were whitelisted
        $this->info('Setting whitelist users created_at from audit logs...');
        $this->setWhitelistUsersCreatedAtFromAudit();

        $this->info('Done.');

        return Command::SUCCESS;
    }

    /**
     * For each WhitelistUser, find the earliest AuditLog with action 'create' and set created_at accordingly.
     */
    protected function setWhitelistUsersCreatedAtFromAudit(): void
    {
        $users = WhitelistUser::all();
        foreach ($users as $user) {
            $first = AuditLog::where('model_type', WhitelistUser::class)
                ->where('model_id', $user->id)
                ->where('action', 'create')
                ->orderBy('created_at', 'asc')
                ->value('created_at');

            // Update the user's created_at (and leave updated_at alone). Use model saving but disable timestamps.
            $user->timestamps = false;
            $user->created_at = $first ?: null;
            $user->save();
        }
    }

    protected function importLogFile(string $file): void
    {
        $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            // Example line format: [12:40:14] POST ... - phit(91.10.191.127): Added notch - 069a79f4-... to the whitelist
            if (preg_match('/^\[(?<time>[^\]]+)\] .* - (?<actor>[^\(]+)\((?<ip>[^\)]+)\): (?<action>Added|Removed) (?<name>[^ -]+) - (?<uuid>[0-9a-fA-F\-]+) /', $line, $m)) {
                $time = $m['time'];
                $date = $this->dateFromFilename($file);
                $datetime = date('Y-m-d H:i:s', strtotime($date.' '.$time));

                $actor = trim($m['actor']);
                $ip = $m['ip'];
                $action = strtolower($m['action']) === 'added' ? 'create' : 'delete';
                $name = $m['name'];
                $uuid = $m['uuid'];

                $model = WhitelistUser::where('uuid', $uuid)->first();

                AuditLog::forceCreate([
                    'user_id' => null,
                    'model_type' => WhitelistUser::class,
                    'model_id' => $model->id ?? 0,
                    'action' => $action,
                    'old_values' => null,
                    'new_values' => ['username' => $name, 'uuid' => $uuid],
                    'ip_address' => $ip,
                    'user_agent' => $actor,
                    'created_at' => $datetime,
                ]);
            }
        }
    }

    /**
     * Extract a YYYY-MM-DD date from the filename. Legacy files always use this format.
     */
    protected function dateFromFilename(string $file): string
    {
        $base = basename($file);

        if (preg_match('/(?P<d>\d{4}-\d{2}-\d{2})/', $base, $m)) {
            return $m['d'];
        }

        throw new \RuntimeException("Could not extract date from filename: {$file}");
    }
}
