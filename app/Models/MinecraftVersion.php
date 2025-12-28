<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property \Illuminate\Support\Carbon $release_time
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MinecraftVersion newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MinecraftVersion newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MinecraftVersion query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MinecraftVersion whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MinecraftVersion whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MinecraftVersion whereReleaseTime($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MinecraftVersion whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
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
