<?php

namespace App\Http\Requests\Payments;

use App\Domain\Payments\Enums\RefundStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ListRefundsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => [
                'nullable',
                Rule::enum(RefundStatus::class),
            ],
        ];
    }
}
