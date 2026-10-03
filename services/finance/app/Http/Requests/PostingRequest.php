<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PostingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'idempotency_key' => ['required', 'string', 'max:80'],
            'entry_type' => ['required', Rule::in(['sale', 'reversal'])],
            'trx_number' => ['required', 'string', 'max:50'],
            'outlet_id' => ['required', 'integer', 'min:1'],
            'outlet_code' => ['required', 'string', 'max:20'],
            'shift_id' => ['required', 'integer', 'min:1'],
            'business_date' => ['required', 'date_format:Y-m-d'],
            'payment_method' => ['required', Rule::in(['cash', 'debit', 'qris'])],
            'net_sales_amount' => ['required', 'numeric'],
            'tax_amount' => ['required', 'numeric'],
            'total_amount' => ['required', 'numeric'],
            'occurred_at' => ['sometimes', 'nullable', 'date'],
        ];
    }
}
