<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Airway extends Model
{
    protected $fillable = [
        'name',
        'waypoints',
    ];

    protected function casts(): array
    {
        return [
            'waypoints' => 'array',
        ];
    }
}
