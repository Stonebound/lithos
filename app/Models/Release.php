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

class Release extends Model
{
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

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    public function fileChanges(): HasMany
    {
        return $this->hasMany(FileChange::class);
    }

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
