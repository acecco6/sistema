<?php

namespace App\Http\Requests\Reservations\FixedReservations;

use Illuminate\Foundation\Http\FormRequest;

final class CreateFixedReservationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'club_customer_id' => ['nullable','integer','exists:club_customers,id'],
            'customer_user_id' => [
                'nullable',
                'integer',
                'exists:users,id',
                'required_without_all:guest_name,club_customer_id',
            ],

            'guest_name' => [
                'nullable',
                'string',
                'max:100',
                'required_without_all:customer_user_id,club_customer_id',
            ],

            'guest_email' => [
                'nullable',
                'email',
                'max:150',
            ],

            'guest_phone' => [
                'nullable',
                'string',
                'max:30',
            ],

            'starts_on' => [
                'required',
                'date_format:Y-m-d',
                'after_or_equal:today',
            ],

            'ends_on' => [
                'nullable',
                'date_format:Y-m-d',
                'after_or_equal:starts_on',
            ],

            'notes' => [
                'nullable',
                'string',
            ],

            'slots' => [
                'required',
                'array',
                'min:1',
            ],

            'slots.*.court_id' => [
                'required',
                'integer',
                'exists:courts,id',
            ],

            'slots.*.day_of_week' => [
                'required',
                'integer',
                'between:1,7',
            ],

            'slots.*.start_time' => [
                'required',
                'date_format:H:i',
            ],

            'slots.*.duration_minutes' => [
                'required',
                'integer',
                'min:60',
            ],
        ];
    }
}
