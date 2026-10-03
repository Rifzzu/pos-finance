<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'sku' => ['sometimes', 'string', 'max:50', Rule::unique('products', 'sku')->ignore($this->route('id'))],
            'name' => ['sometimes', 'string', 'max:200'],
            'price' => ['sometimes', 'numeric', 'min:0.01'],
        ];
    }
}
