<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\OverrideRuleType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property string $scope
 * @property array<array-key, mixed> $path_patterns
 * @property OverrideRuleType $type
 * @property array<array-key, mixed>|null $payload
 * @property int $enabled
 * @property int $priority
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $minecraft_version
 * @property-read \App\Models\MinecraftVersion|null $minecraftVersion
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Server> $servers
 * @property-read int|null $servers_count
 *
 * @method static \Database\Factories\OverrideRuleFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OverrideRule newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OverrideRule newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OverrideRule query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OverrideRule whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OverrideRule whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OverrideRule whereEnabled($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OverrideRule whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OverrideRule whereMinecraftVersion($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OverrideRule whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OverrideRule wherePathPatterns($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OverrideRule wherePayload($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OverrideRule wherePriority($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OverrideRule whereScope($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OverrideRule whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OverrideRule whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class OverrideRule extends Model
{
    /** @use HasFactory<\Database\Factories\OverrideRuleFactory> */
    use HasFactory;

    protected $fillable = [
        'name', 'description', 'scope', 'path_patterns', 'type', 'payload', 'enabled', 'priority', 'minecraft_version',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'path_patterns' => 'array',
            'type' => OverrideRuleType::class,
        ];
    }

    /**
     * @return BelongsToMany<Server, $this>
     */
    public function servers(): BelongsToMany
    {
        return $this->belongsToMany(Server::class);
    }

    /**
     * @return BelongsTo<MinecraftVersion, $this>
     */
    public function minecraftVersion(): BelongsTo
    {
        return $this->belongsTo(MinecraftVersion::class, 'minecraft_version')->orderByDesc('release_time');
    }

    /**
     * Get all skip patterns that apply to the given server.
     *
     * @return array<string>
     */
    public static function getSkipPatternsForServer(Server $server): array
    {
        return self::query()
            ->where('type', OverrideRuleType::FileSkip)
            ->where('enabled', true)
            ->where(function ($q) use ($server) {
                $q->where(function ($q2) use ($server) {
                    $q2->where('scope', 'global')
                        ->where(function ($q) use ($server) {
                            $q->whereNull('minecraft_version')
                                ->orWhere('minecraft_version', ($server->minecraft_version));
                        });
                })
                    ->orWhere(function ($q2) use ($server) {
                        $q2->where('scope', 'server')
                            ->whereHas('servers', function ($q3) use ($server) {
                                $q3->where('servers.id', $server->id);
                            });
                    });
            })
            ->pluck('path_patterns')
            ->flatten()
            ->filter()
            ->unique()
            ->toArray();
    }
}
