<?php

namespace App\Http\Requests\Parties;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can($this->route('customer') ? 'customers.edit' : 'customers.create');
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['is_active' => $this->boolean('is_active')]);
    }

    public function rules(): array
    {
        $customer = $this->route('customer');

        return [
            'name' => ['required', 'string', 'max:100'],
            'phone' => ['required', 'string', 'max:30', 'regex:/^[0-9+\-\s]{7,20}$/', Rule::unique('customers', 'phone')->ignore($customer)],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'cnic' => ['nullable', 'regex:/^\d{5}-?\d{7}-?\d$/'],
            'credit_limit' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            // Opening balance can only be set when the customer is created; later changes go through payments.
            'opening_balance' => [$customer ? 'prohibited' : 'nullable', 'numeric', 'min:0', 'max:999999999'],
            'is_active' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'phone.regex' => 'Enter a valid phone number, e.g. 0300-1234567.',
            'phone.unique' => 'Another customer already has this phone number.',
            'cnic.regex' => 'CNIC format: 41303-1234567-1.',
            'opening_balance.prohibited' => 'Opening balance cannot be changed after the customer is created.',
        ];
    }
}
