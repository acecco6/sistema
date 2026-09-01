<?php

namespace App\Http\Requests\Reservations;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class GetTipoCourtAvailabilityRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'tipo_court_id' => [
                'required',
                'integer',
                'exists:tipos_court,id',
            ],
            'date' => [
                'required',
                'date_format:Y-m-d',
            ],
            'start_time' => [
                'nullable',
                'date_format:H:i:s',
                'required_with:end_time',
            ],
            'end_time' => [
                'nullable',
                'date_format:H:i:s',
                'required_with:start_time',
            ],
            'duration_minutes' => [
                'nullable',
                'integer',
                'min:60',
            ],
        ];
    }
}
