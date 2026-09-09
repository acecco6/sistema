<?php

namespace App\Http\Requests\Customers;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class CreateCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }
    public function rules(): array
    {
        return [
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'name' => ['required_without_all:user_id,customer_id', 'nullable', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $hasCustomerId = $this->filled('customer_id');
            $hasUserId = $this->filled('user_id');
            $hasManualData = $this->filled('name')
                || $this->filled('email')
                || $this->filled('phone');

            if ($hasCustomerId && $hasUserId) {
                $validator->errors()->add('customer_id', 'No podés enviar customer_id y user_id al mismo tiempo.');
            }

            if (($hasCustomerId || $hasUserId) && $hasManualData) {
                $validator->errors()->add(
                    $hasCustomerId ? 'customer_id' : 'user_id',
                    'Al asociar un Customer o User existente no podés enviar name, email ni phone.'
                );
            }
        });
    }
}
