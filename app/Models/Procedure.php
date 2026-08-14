<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Procedure extends Model
{
    protected $fillable = [
        'runway_id',
        'type',
        'name',
        'aircraft_type',
        'is_default',
        'approach_name',
        'op_data_flag',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
        ];
    }

    public function runway(): BelongsTo
    {
        return $this->belongsTo(Runway::class);
    }
}
