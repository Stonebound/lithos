<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class CreateUser extends Command
{
    protected $signature = 'user:create {--name=} {--email=} {--password=} {--role=maintainer}';

    protected $description = 'Create a user with a role (viewer|maintainer|admin).';

    public function handle(): int
    {
        $name = (string) ($this->option('name') ?? 'Maintainer');
        $email = (string) ($this->option('email') ?? 'maintainer@example.com');
        $role = (string) ($this->option('role') ?? 'maintainer');
        $password = (string) ($this->option('password') ?? Str::random(16));

        if (! in_array($role, ['viewer', 'maintainer', 'admin'], true)) {
            $this->error('Invalid role. Use viewer|maintainer|admin');

            return self::FAILURE;
        }

        $existing = User::query()->where('email', $email)->first();
        if ($existing) {
            $this->error('User already exists with email: '.$email);

            return self::FAILURE;
        }

        $user = User::query()->create([
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'role' => $role,
        ]);
        $user->forceFill(['email_verified_at' => now()])->save();

        $this->info('User created: ID '.$user->id);
        $this->line('Email: '.$email);
        $this->line('Password: '.$password);
        $this->line('Role: '.$role);
        $this->newLine();
        $this->warn('Store this password securely. You can change it after login.');

        return self::SUCCESS;
    }
}
