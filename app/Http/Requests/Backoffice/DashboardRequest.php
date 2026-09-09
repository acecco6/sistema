<?php

namespace App\Http\Requests\Backoffice;

use Illuminate\Foundation\Http\FormRequest;

final class DashboardRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (! $this->has('date')) {
            $this->merge(['date' => now()->toDateString()]);
        }
    }

    public function rules(): array
    {
        return ['date' => ['required', 'date_format:Y-m-d']];
    }
}
