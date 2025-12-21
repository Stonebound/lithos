<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OverrideRule extends Model
{
    protected $fillable = [
        'name', 'description', 'scope', 'server_id', 'path_pattern', 'type', 'payload', 'enabled', 'priority',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
        ];
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }
}
