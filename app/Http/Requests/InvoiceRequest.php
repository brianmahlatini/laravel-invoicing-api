<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $partial = $this->isMethod('PATCH') ? 'sometimes' : 'required';

        return [
            // exists scoped to the caller: you can't invoice someone else's customer
            'customer_id' => [$partial, 'integer', Rule::exists('customers', 'id')->where('user_id', $this->user()->id)],
            'issue_date' => [$partial, 'date_format:Y-m-d'],
            'due_date' => [$partial, 'date_format:Y-m-d', 'after_or_equal:issue_date'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'lines' => [$partial, 'array', 'min:1', 'max:100'],
            'lines.*.description' => ['required', 'string', 'max:500'],
            'lines.*.quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
            'lines.*.unit_price_cents' => ['required', 'integer', 'min:0', 'max:10000000000'],
            'lines.*.tax_rate_bps' => ['sometimes', 'integer', 'min:0', 'max:10000'],
        ];
    }
}
