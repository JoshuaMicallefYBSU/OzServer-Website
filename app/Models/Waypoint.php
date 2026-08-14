<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Waypoint extends Model
{
    protected $fillable = [
        'name',
        'type',
        'lat',
        'lon',
    ];

    protected function casts(): array
    {
        return [
            'lat' => 'float',
            'lon' => 'float',
        ];
    }
}
