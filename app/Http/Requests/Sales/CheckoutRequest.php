<?php

namespace App\Http\Requests\Sales;

use App\Models\Payment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Shape validation only; prices, permissions and totals are enforced in SaleService. */
class CheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('sales.create');
    }

    public function rules(): array
    {
        return [
            'customer_id' => ['nullable', 'integer'],
            'hold_id' => ['nullable', 'integer'],
            'items' => ['required', 'array', 'min:1', 'max:300'],
            'items.*.product_id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0', 'max:999999'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'items.*.discount' => ['nullable', 'numeric', 'min:0'],
            'discount_type' => ['nullable', Rule::in(['amount', 'percent'])],
            'discount_value' => ['nullable', 'numeric', 'min:0'],
            'shipping' => ['nullable', 'numeric', 'min:0'],
            'payments' => ['nullable', 'array', 'max:6'],
            'payments.*.method' => ['required', Rule::in(array_keys(Payment::METHODS))],
            'payments.*.amount' => ['required', 'numeric', 'min:0'],
            'payments.*.reference' => ['nullable', 'string', 'max:100'],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return ['items.required' => 'The cart is empty.'];
    }
}
