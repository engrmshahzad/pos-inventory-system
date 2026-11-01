<?php

namespace App\Http\Requests\Catalogue;

use App\Models\Category;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('categories.manage');
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['is_active' => $this->boolean('is_active'), 'parent_id' => $this->input('parent_id') ?: null]);
    }

    public function rules(): array
    {
        $category = $this->route('category');

        return [
            'name' => ['required', 'string', 'max:100',
                Rule::unique('categories')->where(fn ($q) => $q->where('parent_id', $this->input('parent_id'))->whereNull('deleted_at'))->ignore($category)],
            // Only one level: the parent must itself be a top-level category.
            'parent_id' => ['nullable', Rule::exists('categories', 'id')->whereNull('parent_id')->whereNull('deleted_at'),
                Rule::notIn([$category?->id])],
            'icon' => ['nullable', 'string', 'max:50'],
            'color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'is_active' => ['boolean'],
        ];
    }

    public function after(): array
    {
        return [function ($validator) {
            $category = $this->route('category');
            if ($category && $this->input('parent_id') && $category->children()->exists()) {
                $validator->errors()->add('parent_id', 'This category has sub-categories, so it cannot become a sub-category itself.');
            }
        }];
    }

    public function messages(): array
    {
        return ['parent_id.exists' => 'Choose a top-level category as the parent.', 'name.unique' => 'A category with this name already exists here.'];
    }
}
