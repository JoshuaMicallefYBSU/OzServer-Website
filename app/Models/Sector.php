<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Sector extends Model
{
    protected $fillable = [
        'name',
        'full_name',
        'frequency',
        'callsign',
        'type',
        'display_in_sectors_window',
        'display_in_handoff_window',
        'responsible_sectors',
    ];

    protected function casts(): array
    {
        return [
            'display_in_sectors_window' => 'boolean',
            'display_in_handoff_window' => 'boolean',
            'responsible_sectors' => 'array',
        ];
    }

    public function volumes(): BelongsToMany
    {
        return $this->belongsToMany(Volume::class);
    }

    public function positions(): BelongsToMany
    {
        return $this->belongsToMany(Position::class);
    }

    public function ownership(): HasOne
    {
        return $this->hasOne(SectorOwnership::class);
    }

    public function getRouteKeyName(): string
    {
        return 'name';
    }

    /**
     * This sector plus every sector it's "responsible for" - e.g. ASP
     * covers FOR/WAR/ASW/WRA/BKE/ESP too. Ownership always applies to the
     * whole group together, never just the parent alone.
     */
    public function coveredSectors(): Collection
    {
        $names = [$this->name, ...($this->responsible_sectors ?? [])];

        // Ownership eager-loaded because every caller immediately asks for it:
        // claim() inspects ->ownership once per covered sector, so a group like
        // ARL (which covers six others) issued seven separate queries per claim
        // - and the plugin re-asserts claims on every MMI change.
        return self::whereIn('name', $names)->with('ownership')->get();
    }
}
