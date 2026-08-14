<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SectorOwnershipRequest extends Model
{
    protected $fillable = [
        'sector_id',
        'requesting_cid',
        'requesting_callsign',
        'target_cid',
        'target_callsign',
    ];

    protected function casts(): array
    {
        return [
            'requesting_cid' => 'integer',
            'target_cid' => 'integer',
        ];
    }

    public function sector(): BelongsTo
    {
        return $this->belongsTo(Sector::class);
    }
}
