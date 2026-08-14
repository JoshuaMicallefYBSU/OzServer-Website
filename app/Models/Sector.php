<?php

namespace App\Models;

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
}
