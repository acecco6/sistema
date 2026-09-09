<?php

namespace App\Http\Requests\Customers;

use Illuminate\Foundation\Http\FormRequest;

final class ChangeCustomerStatusRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array { return ['active' => ['required','boolean']]; }
}
