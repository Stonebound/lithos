<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $release_id
 * @property string $level
 * @property string $message
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Release $release
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReleaseLog newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReleaseLog newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReleaseLog query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReleaseLog whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReleaseLog whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReleaseLog whereLevel($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReleaseLog whereMessage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReleaseLog whereReleaseId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReleaseLog whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class ReleaseLog extends Model
{
    protected $fillable = ['release_id', 'level', 'message'];

    /**
     * @return BelongsTo<Release, $this>
     */
    public function release(): BelongsTo
    {
        return $this->belongsTo(Release::class);
    }
}
