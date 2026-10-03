<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ResolveReconciliationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'resolution_note' => ['required', 'string', 'max:2000'],
        ];
    }
}
