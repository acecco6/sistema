<?php

namespace App\Http\Requests\Customers;

use Illuminate\Foundation\Http\FormRequest;

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
}
