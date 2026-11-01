<?php

namespace App\Http\Requests\Purchases;

use App\Models\Payment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Shape only; quantities, totals and stock rules live in PurchaseService. */
class PurchaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('purchases.create');
    }

    protected function prepareForValidation(): void
    {
        if (! $this->user()->isOwner()) {
            $this->merge(['branch_id' => $this->user()->branch_id]);
        }
    }

    public function rules(): array
    {
        return [
            'branch_id' => ['required', Rule::exists('branches', 'id')->where('is_active', true)->whereNull('deleted_at')],
            'supplier_id' => ['required', 'integer'],
            'reference_no' => ['nullable', 'string', 'max:60'],
            'purchase_date' => ['required', 'date', 'before_or_equal:today'],
            'status' => ['required', Rule::in(['received', 'ordered'])],
            'items' => ['required', 'array', 'min:1', 'max:300'],
            'items.*.product_id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.unit_cost' => ['required', 'numeric', 'min:0'],
            'items.*.discount' => ['nullable', 'numeric', 'min:0'],
            'items.*.tax_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items.*.update_price' => ['nullable', 'boolean'],
            'items.*.new_sale_price' => ['nullable', 'numeric', 'min:0'],
            'items.*.expiry_date' => ['nullable', 'date'],
            'items.*.batch_no' => ['nullable', 'string', 'max:50'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'shipping' => ['nullable', 'numeric', 'min:0'],
            'paid_amount' => ['nullable', 'numeric', 'min:0'],
            'payment_method' => ['nullable', Rule::in(array_keys(Payment::METHODS))],
            'payment_reference' => ['nullable', 'string', 'max:100'],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return ['items.required' => 'Add at least one product.', 'supplier_id.required' => 'Choose a supplier.'];
    }
}
