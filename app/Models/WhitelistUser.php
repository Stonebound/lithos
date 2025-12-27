<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\MinecraftApi;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WhitelistUser extends Model
{
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
