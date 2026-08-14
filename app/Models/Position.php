<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Position extends Model
{
    protected $fillable = [
        'group',
        'name',
        'type',
        'default_lat',
        'default_lon',
        'default_range',
        'magnetic_variation',
        'rotation',
        'asmgcs_airport',
        'visibility_range',
    ];

    protected function casts(): array
    {
        return [
            'default_lat' => 'float',
            'default_lon' => 'float',
            'default_range' => 'float',
            'magnetic_variation' => 'float',
            'rotation' => 'float',
            'visibility_range' => 'float',
        ];
    }

    public function sectors(): BelongsToMany
    {
        return $this->belongsToMany(Sector::class);
    }
}
