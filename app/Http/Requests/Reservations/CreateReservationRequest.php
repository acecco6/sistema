<?php

namespace App\Http\Requests\Reservations;

use Illuminate\Foundation\Http\FormRequest;

class CreateReservationRequest extends FormRequest
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
            'club_customer_id' => ['nullable','integer','exists:club_customers,id'],
            /*
             * Cliente registrado.
             *
             * Puede venir NULL si la reserva es para un guest.
             */
            'customer_user_id' => [
                'nullable',
                'integer',
                'exists:users,id',
            ],

            /*
             * Datos del guest.
             *
             * No los hacemos required acá porque la Entity
             * ya valida que exista cliente registrado O guest.
             */
            'guest_name' => [
                'required_without_all:customer_user_id,club_customer_id',
                'string',
                'max:100'
            ],

            'guest_email' => [
                'required_without_all:customer_user_id,club_customer_id',
                'email',
                'max:150',
            ],

            'guest_phone' => [
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

            'confirmed' => [
                'nullable',
                'boolean',
            ],
        ];
    }
}
