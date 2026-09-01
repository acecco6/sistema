<?php

namespace App\Http\Requests\Payments;

use App\Domain\Payments\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterManualPaymentRequest extends FormRequest
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
                'gt:0',
            ],

            'method' => [
                'required',
                'string',
                Rule::enum(PaymentMethod::class),
            ],
        ];
    }
}
