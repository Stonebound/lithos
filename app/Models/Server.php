<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Server extends Model
{
    protected $fillable = [
        'name', 'host', 'port', 'username', 'auth_type', 'password', 'private_key_path', 'remote_root_path', 'include_paths',
        'provider', 'provider_pack_id', 'provider_current_version',
    ];

    protected function casts(): array
    {
        return [
            'include_paths' => 'array',
        ];
    }

    public function releases(): HasMany
    {
        return $this->hasMany(Release::class);
    }

    public function overrideRules(): HasMany
    {
        return $this->hasMany(OverrideRule::class);
    }
}
