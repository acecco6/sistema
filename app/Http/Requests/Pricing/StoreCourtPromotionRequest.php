<?php

namespace App\Http\Requests\Pricing;

use Illuminate\Foundation\Http\FormRequest;

class StoreCourtPromotionRequest extends FormRequest
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

            'price' => [
                'required',
                'numeric',
                'gt:0',
                'decimal:0,2',
            ],

            'day_of_week' => [
                'nullable',
                'integer',
                'between:1,7',
            ],

            'specific_date' => [
                'nullable',
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
                'after:start_time',
            ],

            'priority' => [
                'required',
                'integer',
                'min:0',
            ],

            'starts_at' => [
                'nullable',
                'date',
            ],

            'ends_at' => [
                'nullable',
                'date',
                'after:starts_at',
            ],
        ];
    }
}
