<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Volume extends Model
{
    protected $fillable = [
        'name',
        'boundary',
        'lower_limit',
        'upper_limit',
    ];

    protected function casts(): array
    {
        return [
            'boundary' => 'array',
            'lower_limit' => 'integer',
            'upper_limit' => 'integer',
        ];
    }

    public function sectors(): BelongsToMany
    {
        return $this->belongsToMany(Sector::class);
    }
}
