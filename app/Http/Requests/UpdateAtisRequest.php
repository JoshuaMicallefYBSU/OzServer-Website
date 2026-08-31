<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateAtisRequest extends FormRequest
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

            'icao' => ['required', 'string', 'max:4'],
            'atis_letter' => ['required', 'string', 'size:1'],
            'content' => ['required', 'array'],
            'content.*' => ['nullable', 'string'],
            'frequency' => ['nullable', 'integer'],
        ];
    }
}
