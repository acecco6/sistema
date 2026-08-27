<?php

namespace App\Http\Requests\Reservations;

use Illuminate\Foundation\Http\FormRequest;

class GuestReservationRequest extends FormRequest
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
            'name' => [
                'required',
                'string',
                'max:100',
            ],

            'email' => [
                'nullable',
                'email',
                'max:150',
            ],

            'phone' => [
                'nullable',
                'string',
                'max:30',
            ],

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

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if (
                empty($this->input('email'))
                && empty($this->input('phone'))
            ) {
                $validator->errors()->add(
                    'contact',
                    'Debes proporcionar un email o teléfono.'
                );
            }
        });
    }
}
