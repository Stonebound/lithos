<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\MinecraftApi;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $uuid
 * @property string|null $username
 * @property string|null $source
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 *
 * @method static \Database\Factories\WhitelistUserFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WhitelistUser newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WhitelistUser newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WhitelistUser query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WhitelistUser whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WhitelistUser whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WhitelistUser whereSource($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WhitelistUser whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WhitelistUser whereUsername($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WhitelistUser whereUuid($value)
 *
 * @mixin \Eloquent
 */
class WhitelistUser extends Model
{
    /** @use HasFactory<\Database\Factories\WhitelistUserFactory> */
    use HasFactory;

    protected $fillable = [
        'uuid',
        'username',
        'source',
    ];

    public function getSkinUrl(): ?string
    {
        return 'https://api.mineatar.io/body/full/'.MinecraftApi::minifyUuid($this->uuid).'?scale=16';
    }
}
