<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\FileChangeType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $release_id
 * @property string $relative_path
 * @property FileChangeType $change_type
 * @property bool $is_binary
 * @property string|null $diff_summary
 * @property string|null $checksum_old
 * @property string|null $checksum_new
 * @property int|null $size_old
 * @property int|null $size_new
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Release $release
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FileChange newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FileChange newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FileChange query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FileChange whereChangeType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FileChange whereChecksumNew($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FileChange whereChecksumOld($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FileChange whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FileChange whereDiffSummary($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FileChange whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FileChange whereIsBinary($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FileChange whereRelativePath($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FileChange whereReleaseId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FileChange whereSizeNew($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FileChange whereSizeOld($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FileChange whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class FileChange extends Model
{
    protected $fillable = [
        'release_id', 'relative_path', 'change_type', 'is_binary', 'diff_summary', 'checksum_old', 'checksum_new', 'size_old', 'size_new',
    ];

    protected function casts(): array
    {
        return [
            'is_binary' => 'bool',
            'change_type' => FileChangeType::class,
        ];
    }

    /**
     * @return BelongsTo<Release, $this>
     */
    public function release(): BelongsTo
    {
        return $this->belongsTo(Release::class);
    }
}
