<?php

namespace App\Http\Requests\Backoffice;

use Illuminate\Foundation\Http\FormRequest;

final class UserSearchRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->route('club_id') !== null) {
            $this->merge(['club_id' => (int) $this->route('club_id')]);
        }
    }

    public function rules(): array
    {
        return [
            'club_id' => ['required', 'integer', 'min:1'],
            'branch_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'search' => ['sometimes', 'string', 'max:100'],
            'active' => ['sometimes', 'boolean'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
