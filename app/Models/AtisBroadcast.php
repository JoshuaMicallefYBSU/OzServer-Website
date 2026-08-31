<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AtisBroadcast extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'icao',
        'atis_letter',
        'content',
        'frequency',
        'last_seen_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'content' => 'array',
            'frequency' => 'integer',
            'last_seen_at' => 'datetime',
        ];
    }
}
