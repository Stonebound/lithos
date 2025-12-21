<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Server;
use Illuminate\Console\Command;

class AddServer extends Command
{
    protected $signature = 'server:add {name} {host} {username} {remote_root_path} {--port=3875} {--auth=password} {--password=} {--key=}';

    protected $description = 'Add or update a server configuration.';

    public function handle(): int
    {
        $name = (string) $this->argument('name');
        $host = (string) $this->argument('host');
        $username = (string) $this->argument('username');
        $remoteRoot = (string) $this->argument('remote_root_path');
        $port = (int) $this->option('port');
        $auth = (string) $this->option('auth');
        $password = (string) ($this->option('password') ?? '');
        $key = (string) ($this->option('key') ?? '');

        $server = Server::query()->updateOrCreate([
            'name' => $name,
        ], [
            'host' => $host,
            'username' => $username,
            'remote_root_path' => $remoteRoot,
            'port' => $port,
            'auth_type' => in_array($auth, ['password', 'key']) ? $auth : 'password',
            'password' => $auth === 'password' ? $password : null,
            'private_key_path' => $auth === 'key' ? $key : null,
        ]);

        $this->info('Server saved: ID '.$server->id.' ('.$server->name.')');

        return self::SUCCESS;
    }
}
