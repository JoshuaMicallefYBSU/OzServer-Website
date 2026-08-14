<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Runway extends Model
{
    protected $fillable = [
        'airport_icao',
        'name',
        'data_runway',
    ];

    public function procedures(): HasMany
    {
        return $this->hasMany(Procedure::class);
    }
}
