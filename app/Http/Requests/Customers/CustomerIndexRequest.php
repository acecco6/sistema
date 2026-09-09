<?php

namespace App\Http\Requests\Customers;

use Illuminate\Foundation\Http\FormRequest;

final class CustomerIndexRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array { return ['search' => ['nullable','string','max:100'], 'active' => ['nullable','boolean'], 'page' => ['nullable','integer','min:1'], 'per_page' => ['nullable','integer','min:1','max:100']]; }
}
