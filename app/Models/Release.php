<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ReleaseStatus;
use App\Jobs\DeployRelease;
use App\Jobs\PrepareRelease;
use Illuminate\Bus\UniqueLock;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;

/**
 * @property int $id
 * @property int $server_id
 * @property string|null $version_label
 * @property string $source_type
 * @property string $source_path
 * @property string|null $extracted_path
 * @property string|null $prepared_path
 * @property ReleaseStatus $status
 * @property array<array-key, mixed>|null $summary_json
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $provider_version_id
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\FileChange> $fileChanges
 * @property-read int|null $file_changes_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\ReleaseLog> $logs
 * @property-read int|null $logs_count
 * @property-read \App\Models\Server $server
 *
 * @method static \Database\Factories\ReleaseFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Release newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Release newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Release query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Release whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Release whereExtractedPath($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Release whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Release wherePreparedPath($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Release whereProviderVersionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Release whereServerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Release whereSourcePath($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Release whereSourceType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Release whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Release whereSummaryJson($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Release whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Release whereVersionLabel($value)
 *
 * @mixin \Eloquent
 */
class Release extends Model
{
    /** @use HasFactory<\Database\Factories\ReleaseFactory> */
    use HasFactory;

    protected $fillable = [
        'server_id', 'version_label', 'provider_version_id', 'source_type', 'source_path', 'extracted_path', 'prepared_path', 'status', 'summary_json',
    ];

    protected function casts(): array
    {
        return [
            'status' => ReleaseStatus::class,
            'summary_json' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Server, $this>
     */
    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    /**
     * @return HasMany<FileChange, $this>
     */
    public function fileChanges(): HasMany
    {
        return $this->hasMany(FileChange::class);
    }

    /**
     * @return HasMany<ReleaseLog, $this>
     */
    public function logs(): HasMany
    {
        return $this->hasMany(ReleaseLog::class);
    }

    public function isDeploying(): bool
    {
        return Cache::lock(UniqueLock::getKey(new DeployRelease($this->id)), 1)->get(fn () => true) === false;
    }

    public function isPreparing(): bool
    {
        return Cache::lock(UniqueLock::getKey(new PrepareRelease($this->id)), 1)->get(fn () => true) === false;
    }
}
