<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SectorResumeSnapshot extends Model
{
    /**
     * How long after disconnecting a controller may still resume. Matches
     * SectorOwnership::DISCONNECT_GRACE_MINUTES deliberately: an ungraceful
     * disconnect holds its sectors for that long, so a graceful one being
     * resumable for the same window means both routes back behave the same
     * from the controller's seat.
     */
    public const RESUME_WINDOW_MINUTES = SectorOwnership::DISCONNECT_GRACE_MINUTES;

    protected $fillable = [
        'controller_cid',
        'controller_callsign',
        'sectors',
        'flights',
    ];

    protected function casts(): array
    {
        return [
            'controller_cid' => 'integer',
            'sectors' => 'array',
            'flights' => 'array',
        ];
    }

    public function isResumable(): bool
    {
        return $this->created_at !== null
            && $this->created_at->gt(now()->subMinutes(self::RESUME_WINDOW_MINUTES));
    }
}
