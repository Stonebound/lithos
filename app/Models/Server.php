<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string $host
 * @property int $port
 * @property string $username
 * @property string $auth_type
 * @property string|null $password
 * @property string|null $private_key_path
 * @property string $remote_root_path
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $provider
 * @property string|null $provider_pack_id
 * @property string|null $provider_current_version
 * @property array<array-key, mixed>|null $include_paths
 * @property string|null $minecraft_version
 * @property-read \App\Models\MinecraftVersion|null $minecraftVersion
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\OverrideRule> $overrideRules
 * @property-read int|null $override_rules_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Release> $releases
 * @property-read int|null $releases_count
 *
 * @method static \Database\Factories\ServerFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Server newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Server newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Server query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Server whereAuthType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Server whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Server whereHost($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Server whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Server whereIncludePaths($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Server whereMinecraftVersion($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Server whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Server wherePassword($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Server wherePort($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Server wherePrivateKeyPath($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Server whereProvider($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Server whereProviderCurrentVersion($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Server whereProviderPackId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Server whereRemoteRootPath($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Server whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Server whereUsername($value)
 *
 * @mixin \Eloquent
 */
class Server extends Model
{
    /** @use HasFactory<\Database\Factories\ServerFactory> */
    use HasFactory;

    protected $fillable = [
        'name', 'host', 'port', 'username', 'auth_type', 'password', 'private_key_path', 'remote_root_path', 'include_paths',
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

    /**
     * @return HasMany<Release, $this>
     */
    public function releases(): HasMany
    {
        return $this->hasMany(Release::class);
    }

    /**
     * @return BelongsToMany<OverrideRule, $this>
     */
    public function overrideRules(): BelongsToMany
    {
        return $this->belongsToMany(OverrideRule::class);
    }

    /**
     * @return BelongsTo<MinecraftVersion, $this>
     */
    public function minecraftVersion(): BelongsTo
    {
        return $this->belongsTo(MinecraftVersion::class, 'minecraft_version')->orderByDesc('release_time');
    }
}
