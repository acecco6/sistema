<?php

namespace App\Http\Requests\Memberships;

use Illuminate\Foundation\Http\FormRequest;

class CreateMembershipRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id'   => 'required|integer',
            'club_id'   => 'required|integer',
            'rol_id'    => 'required|integer',
            'branch_id' => 'nullable|integer',
        ];
    }
}
