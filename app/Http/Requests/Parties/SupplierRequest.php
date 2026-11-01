<?php

namespace App\Http\Requests\Parties;

use Illuminate\Foundation\Http\FormRequest;

class SupplierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can($this->route('supplier') ? 'suppliers.edit' : 'suppliers.create');
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['is_active' => $this->boolean('is_active')]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'company' => ['nullable', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+\-\s]{7,20}$/'],
            'email' => ['nullable', 'email', 'max:150'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'ntn' => ['nullable', 'string', 'max:30'],
            'opening_balance' => [$this->route('supplier') ? 'prohibited' : 'nullable', 'numeric', 'min:0', 'max:999999999'],
            'is_active' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return ['opening_balance.prohibited' => 'Opening balance cannot be changed after the supplier is created.'];
    }
}
