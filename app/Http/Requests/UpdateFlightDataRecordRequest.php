<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateFlightDataRecordRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Identity is verified by the plugin.token middleware before this
        // request class is resolved.
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'controller_cid' => ['required', 'integer'],
            'controller_callsign' => ['required', 'string'],

            // Datalink authority - who the submitting plugin's own FDP2.FDR.ControllerTracking
            // says currently owns this flight, which is not necessarily the submitter
            // (controller_cid/controller_callsign above): a controller merely observing a flight
            // still pushes its data, attributing authority to whoever actually has it (self,
            // another controller's callsign, or neither - null - when nobody has assumed it yet).
            'controlling_cid' => ['nullable', 'integer'],
            'controlling_callsign' => ['nullable', 'string', 'max:20'],

            'callsign' => ['required', 'string', 'max:20'],
            'state' => ['nullable', 'string', 'max:50'],
            'flight_rules' => ['nullable', 'string', 'max:1'],
            'aircraft_type' => ['nullable', 'string', 'max:10'],
            'aircraft_wake' => ['nullable', 'string', 'max:1'],
            'aircraft_equip' => ['nullable', 'string', 'max:20'],
            'aircraft_surv_equip' => ['nullable', 'string', 'max:20'],
            'aircraft_count' => ['nullable', 'integer', 'min:1'],
            'dep_airport' => ['nullable', 'string', 'max:4'],
            'des_airport' => ['nullable', 'string', 'max:4'],
            'route' => ['nullable', 'string'],
            'parsed_route' => ['nullable', 'array'],
            'parsed_route.*' => ['string'],
            'sid_star_string' => ['nullable', 'string'],
            'runway_string' => ['nullable', 'string'],
            'departure_runway' => ['nullable', 'string'],
            'rfl' => ['nullable', 'integer', 'min:0'],
            'cfl_lower' => ['nullable', 'integer', 'min:0'],
            'cfl_upper' => ['nullable', 'integer', 'min:0'],
            'assigned_ssr_code' => ['nullable', 'integer'],
            'atd' => ['nullable', 'date'],
            'etd' => ['nullable', 'date'],
            'eet_minutes' => ['nullable', 'integer', 'min:0'],
            'tas' => ['nullable', 'integer', 'min:0'],
            'text_only' => ['nullable', 'boolean'],
            'receive_only' => ['nullable', 'boolean'],
            'label_op_data' => ['nullable', 'string'],
            'remarks' => ['nullable', 'string'],

            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lon' => ['nullable', 'numeric', 'between:-180,180'],
            'altitude' => ['nullable', 'integer'],
            'ground_speed' => ['nullable', 'integer', 'min:0'],
            'heading' => ['nullable', 'integer', 'between:0,359'],
            'vertical_rate' => ['nullable', 'integer'],
            'on_ground' => ['nullable', 'boolean'],
        ];
    }
}
