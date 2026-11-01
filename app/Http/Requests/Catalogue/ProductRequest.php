<?php

namespace App\Http\Requests\Catalogue;

use App\Models\Product;
use App\Models\Unit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        $product = $this->route('product');

        return $product ? $this->user()->can('products.edit') : $this->user()->can('products.create');
    }

    protected function prepareForValidation(): void
    {
        $data = [
            'is_active' => $this->boolean('is_active'),
            'track_expiry' => $this->boolean('track_expiry'),
            'sku' => $this->filled('sku') ? strtoupper(trim($this->input('sku'))) : null,
            'barcode' => $this->filled('barcode') ? trim($this->input('barcode')) : null,
        ];

        // Opening stock: owners may fill every branch, others only their own.
        $opening = collect((array) $this->input('opening_stock', []))->filter(fn ($q) => $q !== null && $q !== '');
        if (! $this->user()->isOwner()) {
            $opening = $opening->only([(string) $this->user()->branch_id, $this->user()->branch_id]);
        }
        $data['opening_stock'] = $opening->all();

        $this->merge($data);
    }

    public function rules(): array
    {
        $product = $this->route('product');

        return [
            'name' => ['required', 'string', 'max:255'],
            'sku' => ['nullable', 'string', 'max:50', 'regex:/^[A-Z0-9\-_.]+$/', Rule::unique('products', 'sku')->ignore($product)],
            'barcode' => ['nullable', 'string', 'max:50', 'regex:/^[A-Za-z0-9\-]+$/', Rule::unique('products', 'barcode')->ignore($product)],
            'category_id' => ['nullable', Rule::exists('categories', 'id')->whereNull('deleted_at')],
            'brand_id' => ['nullable', Rule::exists('brands', 'id')->whereNull('deleted_at')],
            'unit_id' => ['required', Rule::exists('units', 'id')],
            'purchase_price' => ['required', 'numeric', 'min:0', 'max:9999999999'],
            'sale_price' => ['required', 'numeric', 'min:0', 'max:9999999999'],
            'wholesale_price' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'tax_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'min_stock' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'track_expiry' => ['boolean'],
            'description' => ['nullable', 'string', 'max:2000'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_image' => ['nullable', 'boolean'],
            'is_active' => ['boolean'],
            'opening_stock' => ['array'],
            'opening_stock.*' => ['numeric', 'min:0', 'max:999999999'],
        ];
    }

    public function after(): array
    {
        return [function ($validator) {
            $unit = Unit::find($this->input('unit_id'));
            if ($unit && ! $unit->allow_decimal) {
                foreach ((array) $this->input('opening_stock', []) as $branchId => $qty) {
                    if (is_numeric($qty) && floor((float) $qty) != (float) $qty) {
                        $validator->errors()->add("opening_stock.{$branchId}", "{$unit->name} does not allow decimal quantities.");
                    }
                }
            }
        }];
    }

    /** Validated data without the stock/image keys, with defaults applied. */
    public function productData(): array
    {
        $data = collect($this->validated())->except(['image', 'remove_image', 'opening_stock'])->all();
        $data['tax_percent'] ??= 0;
        $data['min_stock'] ??= 0;

        return $data;
    }

    public function messages(): array
    {
        return [
            'sku.regex' => 'SKU may contain letters, numbers, dashes, dots and underscores only.',
            'barcode.regex' => 'Barcode may contain letters, numbers and dashes only.',
            'opening_stock.*.numeric' => 'Enter a valid quantity.',
        ];
    }
}
