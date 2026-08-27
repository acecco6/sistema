<?php

namespace App\Http\Requests\Pricing;

use Illuminate\Foundation\Http\FormRequest;

class StoreCourtPriceRequest extends FormRequest
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
            'tipo_court_id' => [
                'required',
                'integer',
                'exists:tipos_court,id',
            ],

            'price' => [
                'required',
                'numeric',
                'gt:0',
                'decimal:0,2',
            ],
        ];
    }
}
