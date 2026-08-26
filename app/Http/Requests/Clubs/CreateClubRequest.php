<?php

namespace App\Http\Requests\Clubs;

use Illuminate\Foundation\Http\FormRequest;

class CreateClubRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:100',
        ];
    }
}
