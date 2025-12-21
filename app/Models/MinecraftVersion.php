<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MinecraftVersion extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'release_time',
    ];

    protected function casts(): array
    {
        return [
            'release_time' => 'datetime',
        ];
    }
}
