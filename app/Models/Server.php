<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Server extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'host', 'port', 'username', 'auth_type', 'private_key_path', 'remote_root_path', 'include_paths',
        'provider', 'provider_pack_id', 'provider_current_version', 'minecraft_version',
    ];

    protected $hidden = [
        'password',
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

    public function overrideRules(): BelongsToMany
    {
        return $this->belongsToMany(OverrideRule::class);
    }

    public function minecraftVersion(): BelongsTo
    {
        return $this->belongsTo(MinecraftVersion::class, 'minecraft_version')->orderByDesc('release_time');
    }
}
