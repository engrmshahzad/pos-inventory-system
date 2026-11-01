<?php

namespace App\Http\Requests\Catalogue;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('categories.manage');
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['allow_decimal' => $this->boolean('allow_decimal'), 'short_name' => strtolower(trim((string) $this->input('short_name')))]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:50'],
            'short_name' => ['required', 'string', 'max:15', Rule::unique('units')->ignore($this->route('unit'))],
            'allow_decimal' => ['boolean'],
        ];
    }
}
