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
        'rejected_at',
    ];

    protected function casts(): array
    {
        return [
            'requesting_cid' => 'integer',
            'target_cid' => 'integer',
            'rejected_at' => 'datetime',
        ];
    }

    /**
     * Still awaiting a decision. Everything that treats a request as
     * actionable - the owner's incoming list, accept, the "any other pending
     * requests are moot now" cleanup - means this rather than "the row
     * exists", now that a rejected row outlives its own decision so the
     * requester can be told about it.
     */
    public function scopePending($query)
    {
        return $query->whereNull('rejected_at');
    }

    public function sector(): BelongsTo
    {
        return $this->belongsTo(Sector::class);
    }
}
