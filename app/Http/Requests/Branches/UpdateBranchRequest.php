<?php

namespace App\Http\Requests\Branches;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBranchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'         => 'required|string|max:100',
            'address'      => 'nullable|string|max:255',
            'opening_time' => 'nullable|date_format:H:i:s,H:i',
            'closing_time' => 'nullable|date_format:H:i:s,H:i',
            'active'       => 'nullable|boolean',
        ];
    }
}
