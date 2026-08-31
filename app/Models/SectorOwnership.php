<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SectorOwnership extends Model
{
    /**
     * How long an ownership survives its owner vanishing from the datafeed.
     * Also covers the lag between a brand-new connection claiming its sectors
     * and that connection showing up in the feed at all.
     */
    public const DISCONNECT_GRACE_MINUTES = 2;

    protected $fillable = [
        'sector_id',
        'controller_cid',
        'controller_callsign',
        'last_seen_online_at',
    ];

    protected function casts(): array
    {
        return [
            'controller_cid' => 'integer',
            'last_seen_online_at' => 'datetime',
        ];
    }

    /**
     * Whether this ownership is still protected from being reaped or taken
     * over after its owner stopped appearing in the VATSIM datafeed.
     *
     * This is the whole difference between a crash and a clean exit: a clean
     * exit releases explicitly and never reaches here, so anything that is
     * merely absent is assumed to have dropped out and gets its sectors held
     * for long enough to reconnect and carry on.
     */
    public function withinDisconnectGrace(): bool
    {
        $seen = $this->last_seen_online_at ?? $this->created_at;

        return $seen !== null && $seen->gt(now()->subMinutes(self::DISCONNECT_GRACE_MINUTES));
    }

    public function sector(): BelongsTo
    {
        return $this->belongsTo(Sector::class);
    }
}
