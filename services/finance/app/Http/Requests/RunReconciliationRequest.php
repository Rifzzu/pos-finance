<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RunReconciliationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'outlet_id' => ['required', 'integer', 'min:1'],
            'business_date' => ['required', 'date_format:Y-m-d'],
        ];
    }
}
