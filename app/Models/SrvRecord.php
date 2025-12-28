<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $subdomain
 * @property int $port
 * @property array<array-key, mixed>|null $record_ids
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 *
 * @method static \Database\Factories\SrvRecordFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SrvRecord newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SrvRecord newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SrvRecord query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SrvRecord whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SrvRecord whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SrvRecord wherePort($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SrvRecord whereRecordIds($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SrvRecord whereSubdomain($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SrvRecord whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class SrvRecord extends Model
{
    /** @use HasFactory<\Database\Factories\SrvRecordFactory> */
    use HasFactory;

    protected $fillable = [
        'subdomain',
        'port',
        'record_ids',
    ];

    protected function casts(): array
    {
        return [
            'port' => 'integer',
            'record_ids' => 'array',
        ];
    }
}
