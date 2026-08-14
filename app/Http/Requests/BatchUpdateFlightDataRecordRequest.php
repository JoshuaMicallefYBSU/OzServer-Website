<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * One request, many flights - the plugin's own batched replacement for
 * calling POST /fdr once per aircraft. Same per-flight fields as
 * UpdateFlightDataRecordRequest (see FlightDataRecordRules), just nested
 * under `flights`.
 */
class BatchUpdateFlightDataRecordRequest extends FormRequest
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
            'flights' => ['required', 'array', 'min:1'],
            ...FlightDataRecordRules::prefixed('flights.*'),
        ];
    }
}
