<?php

namespace App\Http\Requests\Courts;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Models\Court;


class UpdateCourtRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }



    public function rules(): array
    {
        $courtId = (int) $this->route('id');
        $court = Court::findOrFail($courtId);
        $branchId = $court->branch_id;
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
                    ->where('branch_id', $branchId)->ignore($courtId),
            ],
        ];
    }
}
