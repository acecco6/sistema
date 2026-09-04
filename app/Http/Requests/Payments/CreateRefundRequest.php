<?php

namespace App\Http\Requests\Payments;

use Illuminate\Foundation\Http\FormRequest;

final class CreateRefundRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'amount' => [
                'required',
                'numeric',
                'decimal:0,2',
                'gt:0',
            ],

            'reason' => [
                'nullable',
                'string',
                'max:1000',
            ],
        ];
    }
}
