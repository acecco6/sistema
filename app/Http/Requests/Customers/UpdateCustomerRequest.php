<?php

namespace App\Http\Requests\Customers;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateCustomerRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array { return ['name' => ['sometimes','string','max:100'], 'email' => ['sometimes','nullable','email','max:150'], 'phone' => ['sometimes','nullable','string','max:30'], 'notes' => ['sometimes','nullable','string','max:2000']]; }
}
