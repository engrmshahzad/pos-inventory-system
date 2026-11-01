<?php

namespace App\Http\Requests\Branch;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class BranchRequest extends FormRequest
{
    public function authorize(): bool
    {
        $branch = $this->route('branch');

        return $branch ? $this->user()->can('update', $branch) : $this->user()->can('create', \App\Models\Branch::class);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => Str::upper(trim((string) $this->input('code'))),
            'is_active' => $this->boolean('is_active'),
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            // Soft-deleted branches keep their code so old invoice numbers stay unique.
            'code' => ['required', 'regex:/^[A-Z]{3}$/', Rule::unique('branches', 'code')->ignore($this->route('branch'))],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:150'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:100'],
            'is_active' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.regex' => 'The code must be exactly 3 letters, e.g. HYD.',
            'code.unique' => 'This code is already used by another branch.',
        ];
    }
}
