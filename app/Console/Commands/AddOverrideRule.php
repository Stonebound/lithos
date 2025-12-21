<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\OverrideRule;
use Illuminate\Console\Command;

class AddOverrideRule extends Command
{
    protected $signature = 'override:add {name} {type} {path_pattern} {--scope=global} {--server_id=} {--payload=} {--priority=0}';

    protected $description = 'Add an override rule (payload as JSON string or @file path).';

    public function handle(): int
    {
        $name = (string) $this->argument('name');
        $type = (string) $this->argument('type');
        $pattern = (string) $this->argument('path_pattern');
        $scope = (string) $this->option('scope');
        $serverId = $this->option('server_id') ? (int) $this->option('server_id') : null;
        $payloadOpt = (string) ($this->option('payload') ?? '');
        $priority = (int) $this->option('priority');

        if (! in_array($type, ['text_replace', 'json_patch', 'yaml_patch'])) {
            $this->error('Invalid type. Use text_replace|json_patch|yaml_patch');

            return self::FAILURE;
        }
        if (! in_array($scope, ['global', 'server'])) {
            $this->error('Invalid scope. Use global|server');

            return self::FAILURE;
        }
        $payload = [];
        if ($payloadOpt) {
            if (str_starts_with($payloadOpt, '@')) {
                $file = substr($payloadOpt, 1);
                $json = @file_get_contents($file);
                $payload = json_decode($json ?: '[]', true) ?? [];
            } else {
                $payload = json_decode($payloadOpt, true) ?? [];
            }
        }

        $rule = OverrideRule::query()->create([
            'name' => $name,
            'type' => $type,
            'path_pattern' => $pattern,
            'scope' => $scope,
            'server_id' => $scope === 'server' ? $serverId : null,
            'payload' => $payload,
            'priority' => $priority,
            'enabled' => true,
        ]);

        $this->info('Override rule created: ID '.$rule->id);

        return self::SUCCESS;
    }
}
