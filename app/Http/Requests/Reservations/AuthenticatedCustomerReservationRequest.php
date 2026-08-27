<?php

namespace App\Http\Requests\Reservations;

use Illuminate\Foundation\Http\FormRequest;

class AuthenticatedCustomerReservationRequest extends FormRequest
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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'starts_at' => [
                'required',
                'date_format:Y-m-d H:i:s',
            ],

            'ends_at' => [
                'required',
                'date_format:Y-m-d H:i:s',
                'after:starts_at',
            ],

            'notes' => [
                'nullable',
                'string',
                'max:1000',
            ],
        ];
    }
}
