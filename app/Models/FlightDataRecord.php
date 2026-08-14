<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FlightDataRecord extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'callsign',
        'state',
        'flight_rules',
        'aircraft_type',
        'aircraft_wake',
        'aircraft_equip',
        'aircraft_surv_equip',
        'aircraft_count',
        'dep_airport',
        'des_airport',
        'route',
        'parsed_route',
        'sid_star_string',
        'runway_string',
        'departure_runway',
        'rfl',
        'cfl_lower',
        'cfl_upper',
        'assigned_ssr_code',
        'atd',
        'etd',
        'eet_minutes',
        'tas',
        'text_only',
        'receive_only',
        'label_op_data',
        'remarks',
        'controlling_cid',
        'controlling_callsign',
        'last_seen_at',
        'lat',
        'lon',
        'altitude',
        'ground_speed',
        'heading',
        'vertical_rate',
        'on_ground',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'parsed_route' => 'array',
            'atd' => 'datetime',
            'etd' => 'datetime',
            'last_seen_at' => 'datetime',
            'text_only' => 'boolean',
            'receive_only' => 'boolean',
            'aircraft_count' => 'integer',
            'rfl' => 'integer',
            'cfl_lower' => 'integer',
            'cfl_upper' => 'integer',
            'assigned_ssr_code' => 'integer',
            'eet_minutes' => 'integer',
            'tas' => 'integer',
            'controlling_cid' => 'integer',
            'lat' => 'float',
            'lon' => 'float',
            'altitude' => 'integer',
            'ground_speed' => 'integer',
            'heading' => 'integer',
            'vertical_rate' => 'integer',
            'on_ground' => 'boolean',
        ];
    }
}
