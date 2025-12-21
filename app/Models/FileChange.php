<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FileChange extends Model
{
    protected $fillable = [
        'release_id', 'relative_path', 'change_type', 'is_binary', 'diff_summary', 'checksum_old', 'checksum_new', 'size_old', 'size_new',
    ];

    protected function casts(): array
    {
        return [
            'is_binary' => 'bool',
        ];
    }

    public function release(): BelongsTo
    {
        return $this->belongsTo(Release::class);
    }
}
