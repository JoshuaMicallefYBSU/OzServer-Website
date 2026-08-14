<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SectorOwnership extends Model
{
    protected $fillable = [
        'sector_id',
        'controller_cid',
        'controller_callsign',
    ];

    protected function casts(): array
    {
        return [
            'controller_cid' => 'integer',
        ];
    }

    public function sector(): BelongsTo
    {
        return $this->belongsTo(Sector::class);
    }
}
