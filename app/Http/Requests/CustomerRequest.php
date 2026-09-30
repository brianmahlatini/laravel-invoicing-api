<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // ownership is checked by the policy in the controller
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $partial = $this->isMethod('PATCH') ? 'sometimes' : 'required';
        $customerId = $this->route('customer')?->id;

        return [
            'name' => [$partial, 'string', 'max:200'],
            'email' => [$partial, 'email:rfc', 'max:254',
                Rule::unique('customers')->where('user_id', $this->user()->id)->ignore($customerId)],
            'currency' => ['sometimes', 'string', Rule::in(['ZAR', 'USD', 'EUR', 'GBP', 'AED'])],
            'address' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
    }
}
