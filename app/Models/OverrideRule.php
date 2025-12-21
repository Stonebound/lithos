<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\OverrideRuleType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class OverrideRule extends Model
{
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

    public function servers(): BelongsToMany
    {
        return $this->belongsToMany(Server::class);
    }

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
