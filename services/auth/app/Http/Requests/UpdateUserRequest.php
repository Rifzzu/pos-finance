<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('id');

        return [
            'name' => ['sometimes', 'string', 'max:150'],
            'email' => ['sometimes', 'email', 'max:150', Rule::unique('users', 'email')->ignore($id)],
            'password' => ['sometimes', 'nullable', 'string', 'min:8', 'max:100'],
            'role' => ['sometimes', Rule::in(User::ROLES)],
            'outlet_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
        ];
    }
}
