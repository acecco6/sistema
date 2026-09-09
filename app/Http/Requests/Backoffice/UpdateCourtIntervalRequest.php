<?php

namespace App\Http\Requests\Backoffice;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateCourtIntervalRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'interval_minutes' => ['required', 'integer', 'min:5', 'max:240', 'multiple_of:5'],
        ];
    }
}
