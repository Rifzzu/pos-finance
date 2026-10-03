<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PayTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'payment_method' => ['required', Rule::in(['cash', 'debit', 'qris'])],
            'paid_amount' => ['required', 'numeric', 'min:0'],
        ];
    }
}
