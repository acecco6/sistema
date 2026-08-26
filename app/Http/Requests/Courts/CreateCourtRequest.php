<?php

namespace App\Http\Requests\Courts;

use Illuminate\Validation\Rule;


use Illuminate\Foundation\Http\FormRequest;

class CreateCourtRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $branchId = $this->route('branch_id');
        return [
            'tipo_court_id' => [
                'required',
                'integer',
                'exists:tipos_court,id',
            ],

            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('courts', 'name')
                    ->where('branch_id', $branchId),
            ],
        ];
    }
}
