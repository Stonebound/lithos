<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Server;
use Illuminate\Console\Command;

class SetServerExcludes extends Command
{
    protected $signature = 'server:include {server_id} {paths*}';

    protected $description = 'Set include top-level directories for a server (e.g., config mods kubejs).';

    public function handle(): int
    {
        $id = (int) $this->argument('server_id');
        $paths = (array) $this->argument('paths');

        /** @var Server|null $server */
        $server = Server::query()->find($id);
        if (! $server) {
            $this->error('Server not found: '.$id);

            return self::FAILURE;
        }

        $server->include_paths = array_values($paths);
        $server->save();
        $this->info('Include paths updated for server '.$server->id);

        return self::SUCCESS;
    }
}
