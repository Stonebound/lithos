<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SrvRecord extends Model
{
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
